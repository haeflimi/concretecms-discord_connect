<?php

namespace DiscordConnect;

use Concrete\Core\Database\Connection\Connection;
use Concrete\Core\User\User;
use DateTime;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use DiscordConnect\Entity\DiscordProfile;
use Throwable;

/**
 * The linked Discord accounts: the Discord IDs of our users and what we know about them.
 *
 * Example: $discordId = app(DiscordAccounts::class)->getDiscordId($user);
 */
class DiscordAccounts
{
    const BINDING_NAMESPACE = 'discord';

    protected const DATE_FORMAT = 'Y-m-d H:i:s';

    /** @var Connection */
    protected $db;

    /** @var EntityManagerInterface */
    protected $entityManager;

    public function __construct(Connection $db, EntityManagerInterface $entityManager)
    {
        $this->db = $db;
        $this->entityManager = $entityManager;
    }

    /**
     * @return array<string, int> user IDs by Discord ID of all users who linked their Discord account
     */
    public function getLinkedAccounts(): array
    {
        $accounts = [];
        foreach ($this->db->fetchAllAssociative('SELECT binding, user_id FROM OauthUserMap WHERE namespace = ? ORDER BY user_id', [self::BINDING_NAMESPACE]) as $row) {
            $accounts[(string) $row['binding']] = (int) $row['user_id'];
        }

        return $accounts;
    }

    /**
     * @param User|\Concrete\Core\User\UserInfo|int $user
     */
    public function getDiscordId($user): ?string
    {
        $binding = $this->db->fetchOne(
            'SELECT binding FROM OauthUserMap WHERE namespace = ? AND user_id = ?',
            [self::BINDING_NAMESPACE, is_object($user) ? (int) $user->getUserID() : (int) $user]
        );

        return $binding === false ? null : (string) $binding;
    }

    public function getUserID(string $discordId): ?int
    {
        $userID = $this->db->fetchOne('SELECT user_id FROM OauthUserMap WHERE namespace = ? AND binding = ?', [self::BINDING_NAMESPACE, $discordId]);

        return $userID === false ? null : (int) $userID;
    }

    /**
     * @param User|\Concrete\Core\User\UserInfo|int $user
     */
    public function getProfile($user): ?DiscordProfile
    {
        $discordId = $this->getDiscordId($user);

        return $discordId === null ? null : $this->entityManager->find(DiscordProfile::class, $discordId);
    }

    /**
     * Store the Discord user object (GET /users/@me, or the "user" of a guild member).
     */
    public function saveUser(array $discordUser, int $uID, bool $connected = false): void
    {
        $values = ['uID' => $uID] + $this->getUserValues($discordUser);
        if ($connected) {
            $values['connectedAt'] = (new DateTime())->format(self::DATE_FORMAT);
        }
        $this->saveProfile((string) $discordUser['id'], $values);
    }

    /**
     * Store the membership on our server.
     *
     * @param array|null $member the guild member object, null if the user is not on the server
     */
    public function saveGuildMember(string $discordId, int $uID, ?array $member): void
    {
        $values = [
            'uID' => $uID,
            'guildMember' => $member === null ? 0 : 1,
            'guildNick' => $member['nick'] ?? null,
            'guildRoles' => $member === null ? null : json_encode(array_values(array_map('strval', $member['roles'] ?? []))),
            'guildJoinedAt' => $this->formatTimestamp($member['joined_at'] ?? null),
            'guildCheckedAt' => (new DateTime())->format(self::DATE_FORMAT),
        ];
        if (isset($member['user']['id'])) {
            $values += $this->getUserValues($member['user']);
        }
        $this->saveProfile($discordId, $values);
    }

    public function deleteProfile(string $discordId): void
    {
        $this->db->delete('DiscordConnectProfiles', ['discordId' => $discordId]);
    }

    /**
     * Remove the data of Discord accounts that are not linked anymore.
     *
     * @return int number of removed profiles
     */
    public function pruneUnlinkedAccounts(): int
    {
        $unlinked = $this->db->fetchFirstColumn(
            'SELECT p.discordId FROM DiscordConnectProfiles p LEFT JOIN OauthUserMap m ON m.namespace = ? AND m.binding = p.discordId WHERE m.user_id IS NULL',
            [self::BINDING_NAMESPACE]
        );
        foreach ($unlinked as $discordId) {
            $this->deleteProfile((string) $discordId);
        }

        return count($unlinked);
    }

    protected function saveProfile(string $discordId, array $values): void
    {
        if ($this->db->fetchOne('SELECT 1 FROM DiscordConnectProfiles WHERE discordId = ?', [$discordId])) {
            $this->db->update('DiscordConnectProfiles', $values, ['discordId' => $discordId]);
        } else {
            $this->db->insert('DiscordConnectProfiles', ['discordId' => $discordId] + $values);
        }
    }

    protected function getUserValues(array $discordUser): array
    {
        return [
            'username' => isset($discordUser['username']) ? mb_substr((string) $discordUser['username'], 0, 64) : null,
            'globalName' => isset($discordUser['global_name']) ? mb_substr((string) $discordUser['global_name'], 0, 64) : null,
            'avatarHash' => isset($discordUser['avatar']) ? (string) $discordUser['avatar'] : null,
        ];
    }

    protected function formatTimestamp(?string $timestamp): ?string
    {
        if (empty($timestamp)) {
            return null;
        }
        try {
            return (new DateTime($timestamp))->setTimezone(new DateTimeZone(date_default_timezone_get()))->format(self::DATE_FORMAT);
        } catch (Throwable $e) {
            return null;
        }
    }
}
