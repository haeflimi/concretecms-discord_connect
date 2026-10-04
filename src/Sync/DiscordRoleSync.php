<?php

namespace DiscordConnect\Sync;

use Concrete\Core\Database\Connection\Connection;
use Concrete\Core\Logging\Channels;
use Concrete\Core\Logging\LoggerFactory;
use Concrete\Core\User\Group\GroupRepository;
use Concrete\Core\User\User;
use DiscordConnect\Api\DiscordApi;
use DiscordConnect\Api\DiscordApiException;
use DiscordConnect\DiscordAccounts;
use DiscordConnect\DiscordConfig;
use DiscordConnect\Entity\DiscordRoleMapping;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Syncs Discord roles and Concrete groups as configured in the dashboard (Discord Roles). Nothing is synced without
 * mappings. Only users who linked their Discord account are affected: other members of a group are left alone.
 */
class DiscordRoleSync
{
    /** Groups that can't be synced: everybody is in them */
    protected const EXCLUDED_GROUP_IDS = [GUEST_GROUP_ID, REGISTERED_GROUP_ID];

    /** @var EntityManagerInterface */
    protected $entityManager;

    /** @var Connection */
    protected $db;

    /** @var DiscordApi */
    protected $api;

    /** @var DiscordConfig */
    protected $config;

    /** @var DiscordAccounts */
    protected $accounts;

    /** @var GroupRepository */
    protected $groupRepository;

    /** @var LoggerInterface */
    protected $logger;

    /** @var DiscordRoleMapping[]|null */
    protected $mappings;

    /** @var array<string, int> changes of the current sync, see getStats() */
    protected $stats = [];

    /** @var bool whether we're changing a group membership ourselves, see pushGroupChange() */
    protected $changingGroup = false;

    public function __construct(EntityManagerInterface $entityManager, Connection $db, DiscordApi $api, DiscordConfig $config, DiscordAccounts $accounts, GroupRepository $groupRepository, LoggerFactory $loggerFactory)
    {
        $this->entityManager = $entityManager;
        $this->db = $db;
        $this->api = $api;
        $this->config = $config;
        $this->accounts = $accounts;
        $this->groupRepository = $groupRepository;
        $this->logger = $loggerFactory->createLogger(Channels::CHANNEL_AUTHENTICATION);
    }

    /**
     * @return DiscordRoleMapping[]
     */
    public function getMappings(): array
    {
        if ($this->mappings === null) {
            $this->mappings = $this->entityManager->getRepository(DiscordRoleMapping::class)->findBy([], ['id' => 'ASC']);
        }

        return $this->mappings;
    }

    /**
     * The roles of our server, highest first. The @everyone role (ID = server ID) stands for the server membership.
     *
     * @return array<string, array{name: string, color: int, managed: bool, everyone: bool}>
     *
     * @throws DiscordApiException
     */
    public function getRoles(): array
    {
        $guildId = (string) $this->config->getGuildId();
        $roles = $this->api->getGuildRoles($guildId);
        usort($roles, static function (array $a, array $b) {
            return $b['position'] <=> $a['position'];
        });
        $result = [];
        foreach ($roles as $role) {
            $result[(string) $role['id']] = [
                'name' => (string) $role['name'],
                'color' => (int) ($role['color'] ?? 0),
                'managed' => !empty($role['managed']),
                'everyone' => (string) $role['id'] === $guildId,
            ];
        }

        return $result;
    }

    /**
     * @param array $roles see getRoles()
     *
     * @return string[] errors, empty if the mapping can be added
     */
    public function validateMapping(string $roleId, int $gID, string $direction, array $roles): array
    {
        $errors = [];
        $mapping = new DiscordRoleMapping($roleId, $gID, $direction);
        if (!isset(DiscordRoleMapping::getDirectionNames()[$direction])) {
            $errors[] = t('Please choose a direction.');
        }
        $role = $roles[$roleId] ?? null;
        if ($role === null) {
            $errors[] = t('Please choose a Discord role.');
        } elseif ($mapping->syncsToDiscord() && ($role['everyone'] || $role['managed'])) {
            $errors[] = t('The role "%s" can\'t be assigned by the bot, it can only be synced to the CMS.', $role['name']);
        }
        $group = $gID ? $this->groupRepository->getGroupById($gID) : null;
        if ($group === null) {
            $errors[] = t('Please choose a group.');
        } elseif (in_array($gID, self::EXCLUDED_GROUP_IDS, true)) {
            $errors[] = t('The group "%s" can\'t be synced.', $group->getGroupDisplayName(false));
        } elseif ($gID === ADMIN_GROUP_ID && $mapping->syncsToCms()) {
            // Anybody who can manage roles on Discord would be able to make themselves administrators of the website
            $errors[] = t('The Administrators group can only be synced to Discord.');
        }
        foreach ($this->getMappings() as $existing) {
            if ($existing->getRoleId() === $roleId && $existing->getGroupID() === $gID) {
                $errors[] = t('This role and group are already linked.');
            }
        }

        return $errors;
    }

    public function addMapping(string $roleId, int $gID, string $direction): DiscordRoleMapping
    {
        $mapping = new DiscordRoleMapping($roleId, $gID, $direction);
        $this->entityManager->persist($mapping);
        $this->entityManager->flush();
        $this->mappings = null;

        return $mapping;
    }

    public function deleteMapping(DiscordRoleMapping $mapping): void
    {
        $this->db->delete('DiscordConnectRoleSyncStates', ['mappingId' => $mapping->getId()]);
        $this->entityManager->remove($mapping);
        $this->entityManager->flush();
        $this->mappings = null;
    }

    /**
     * Apply all mappings to a linked user.
     *
     * @param array|null $member the guild member object, null if the user is not on the server
     */
    public function syncUser(string $discordId, int $uID, ?array $member): void
    {
        $user = $this->getMappings() === [] ? null : User::getByUserID($uID);
        if (!$user) {
            return;
        }
        $guildId = (string) $this->config->getGuildId();
        foreach ($this->getMappings() as $mapping) {
            $group = $this->groupRepository->getGroupById($mapping->getGroupID());
            if ($group === null) {
                continue;
            }
            $inDiscord = $member !== null && ($mapping->getRoleId() === $guildId || in_array($mapping->getRoleId(), array_map('strval', $member['roles'] ?? []), true));
            $inCms = (bool) $user->inGroup($group);

            switch ($mapping->getDirection()) {
                case DiscordRoleMapping::DIRECTION_DISCORD_TO_CMS:
                    $this->setGroup($user, $group, $inDiscord, $inCms);
                    break;
                case DiscordRoleMapping::DIRECTION_CMS_TO_DISCORD:
                    if ($member !== null) {
                        $this->setRole($guildId, $discordId, $mapping->getRoleId(), $inCms, $inDiscord);
                    }
                    break;
                case DiscordRoleMapping::DIRECTION_BOTH:
                    $synced = $this->getSyncState($mapping, $uID);
                    if ($inDiscord === $inCms) {
                        $state = $inDiscord;
                    } elseif ($inDiscord !== $synced) {
                        // Changed on Discord (or never synced and only Discord has it)
                        $this->setGroup($user, $group, $inDiscord, $inCms);
                        $state = $inDiscord;
                    } elseif ($member !== null && $this->setRole($guildId, $discordId, $mapping->getRoleId(), $inCms, $inDiscord)) {
                        // Changed in the CMS
                        $state = $inCms;
                    } else {
                        // Not on the server (yet) or the role couldn't be changed: try again next time
                        $state = $synced;
                    }
                    $this->setSyncState($mapping, $uID, $state);
                    break;
            }
        }
    }

    /**
     * A user entered or left a group in the CMS (on_user_enter_group/on_user_exit_group events): update their roles on
     * Discord right away for the mappings that sync to Discord. Failures are logged, the next sync retries them.
     */
    public function pushGroupChange(int $uID, int $gID, bool $entered): void
    {
        // Changes made by the sync itself come from Discord
        if ($this->changingGroup) {
            return;
        }
        $mappings = array_filter($this->getMappings(), static function (DiscordRoleMapping $mapping) use ($gID) {
            return $mapping->getGroupID() === $gID && $mapping->syncsToDiscord();
        });
        $guildId = $this->config->getGuildId();
        if ($mappings === [] || $guildId === null || !$this->api->hasBotToken()) {
            return;
        }
        $discordId = $this->accounts->getDiscordId($uID);
        if ($discordId === null) {
            return;
        }

        // A visitor may be waiting for the page to save
        $api = $this->api->withoutWaiting();
        foreach ($mappings as $mapping) {
            try {
                if ($entered) {
                    $api->addGuildMemberRole($guildId, $discordId, $mapping->getRoleId());
                } else {
                    $api->removeGuildMemberRole($guildId, $discordId, $mapping->getRoleId());
                }
            } catch (DiscordApiException $e) {
                // 10007: Unknown Member, the user is not on the server
                if ($e->getDiscordErrorCode() !== 10007) {
                    $this->logger->warning(t('Unable to change the Discord role %s of %s: %s', $mapping->getRoleId(), $discordId, $e->getMessage()));
                }
                continue;
            }
            if ($mapping->getDirection() === DiscordRoleMapping::DIRECTION_BOTH) {
                $this->setSyncState($mapping, $uID, $entered);
            }
        }
    }

    /**
     * A user unlinked their Discord account: remove them from the groups that are managed by Discord.
     */
    public function removeUser(int $uID): void
    {
        $user = $this->getMappings() === [] ? null : User::getByUserID($uID);
        if (!$user) {
            return;
        }
        foreach ($this->getMappings() as $mapping) {
            $group = $mapping->syncsToCms() ? $this->groupRepository->getGroupById($mapping->getGroupID()) : null;
            if ($group !== null) {
                $this->setGroup($user, $group, false, (bool) $user->inGroup($group));
            }
        }
        $this->db->delete('DiscordConnectRoleSyncStates', ['uID' => $uID]);
    }

    /**
     * @return array<string, int> number of changes since resetStats(): groupAdded, groupRemoved, roleAdded, roleRemoved, roleErrors
     */
    public function getStats(): array
    {
        return $this->stats + ['groupAdded' => 0, 'groupRemoved' => 0, 'roleAdded' => 0, 'roleRemoved' => 0, 'roleErrors' => 0];
    }

    public function resetStats(): void
    {
        $this->stats = [];
    }

    /**
     * @param \Concrete\Core\User\Group\Group $group
     */
    protected function setGroup(User $user, $group, bool $add, bool $isInGroup): void
    {
        if ($add === $isInGroup) {
            return;
        }
        $this->changingGroup = true;
        try {
            if ($add) {
                $user->enterGroup($group);
            } else {
                $user->exitGroup($group);
            }
        } finally {
            $this->changingGroup = false;
        }
        $this->count($add ? 'groupAdded' : 'groupRemoved');
    }

    /**
     * @return bool whether the user has the role as requested now
     */
    protected function setRole(string $guildId, string $discordId, string $roleId, bool $add, bool $hasRole): bool
    {
        if ($add === $hasRole) {
            return true;
        }
        try {
            if ($add) {
                $this->api->addGuildMemberRole($guildId, $discordId, $roleId);
            } else {
                $this->api->removeGuildMemberRole($guildId, $discordId, $roleId);
            }
        } catch (DiscordApiException $e) {
            // Usually missing "Manage Roles" permission, or the role is above the highest role of the bot
            $this->logger->warning(t('Unable to change the Discord role %s of %s: %s', $roleId, $discordId, $e->getMessage()));
            $this->count('roleErrors');

            return false;
        }
        $this->count($add ? 'roleAdded' : 'roleRemoved');

        return true;
    }

    protected function getSyncState(DiscordRoleMapping $mapping, int $uID): bool
    {
        return (bool) $this->db->fetchOne('SELECT 1 FROM DiscordConnectRoleSyncStates WHERE mappingId = ? AND uID = ?', [$mapping->getId(), $uID]);
    }

    protected function setSyncState(DiscordRoleMapping $mapping, int $uID, bool $state): void
    {
        $this->db->delete('DiscordConnectRoleSyncStates', ['mappingId' => $mapping->getId(), 'uID' => $uID]);
        if ($state) {
            $this->db->insert('DiscordConnectRoleSyncStates', ['mappingId' => $mapping->getId(), 'uID' => $uID]);
        }
    }

    protected function count(string $key): void
    {
        $this->stats[$key] = ($this->stats[$key] ?? 0) + 1;
    }
}
