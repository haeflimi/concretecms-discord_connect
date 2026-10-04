<?php

namespace DiscordConnect\Sync;

use DiscordConnect\Api\DiscordApi;
use DiscordConnect\Api\DiscordApiException;
use DiscordConnect\DiscordAccounts;
use DiscordConnect\DiscordConfig;

/**
 * Connects the linked users with our Discord server ("guild"): adds them to the server, checks who is a member and
 * syncs the roles that are linked with Concrete groups (DiscordRoleSync).
 */
class DiscordGuildSync
{
    /** @var DiscordApi */
    protected $api;

    /** @var DiscordConfig */
    protected $config;

    /** @var DiscordAccounts */
    protected $accounts;

    /** @var DiscordRoleSync */
    protected $roleSync;

    public function __construct(DiscordApi $api, DiscordConfig $config, DiscordAccounts $accounts, DiscordRoleSync $roleSync)
    {
        $this->api = $api;
        $this->config = $config;
        $this->accounts = $accounts;
        $this->roleSync = $roleSync;
    }

    /**
     * Whether a server and the bot token are configured, which all server features need.
     */
    public function isConfigured(): bool
    {
        return $this->config->getGuildId() !== null && $this->api->hasBotToken();
    }

    /**
     * Called when a user logged in with or attached their Discord account: add them to the server (if enabled),
     * store their membership and sync their roles. Errors are not fatal for the login, they're thrown to be logged
     * by the caller.
     *
     * @param string|null $accessToken OAuth2 access token of the user, needed to add them to the server
     *
     * @throws DiscordApiException
     */
    public function connect(string $discordId, int $uID, ?string $accessToken): void
    {
        if (!$this->isConfigured()) {
            return;
        }
        $guildId = $this->config->getGuildId();
        try {
            if ($accessToken !== null && $this->config->isAutoJoin()) {
                $this->api->addGuildMember($guildId, $discordId, $accessToken);
            }
        } finally {
            // Even if joining failed: the user may already be a member
            $member = $this->api->getGuildMember($guildId, $discordId);
            $this->accounts->saveGuildMember($discordId, $uID, $member);
            $this->roleSync->syncUser($discordId, $uID, $member);
        }
    }

    /**
     * Check which linked users are members of the server and sync their roles.
     *
     * @param array<string, int> $accounts user IDs by Discord ID of all linked accounts
     *
     * @throws DiscordApiException if the member list can't be read
     */
    public function syncMembership(array $accounts, ?callable $log = null): void
    {
        $log = $log ?: static function () {};
        if (!$this->isConfigured()) {
            return;
        }

        $members = [];
        foreach ($this->api->getGuildMembers($this->config->getGuildId()) as $member) {
            $members[(string) $member['user']['id']] = $member;
        }
        $log(t('The Discord server has %s members.', count($members)));

        $this->roleSync->resetStats();
        $memberCount = 0;
        foreach ($accounts as $discordId => $uID) {
            $member = $members[(string) $discordId] ?? null;
            $this->accounts->saveGuildMember((string) $discordId, $uID, $member);
            $this->roleSync->syncUser((string) $discordId, $uID, $member);
            if ($member !== null) {
                ++$memberCount;
            }
        }
        $log(t('%s of %s linked users are members of the Discord server.', $memberCount, count($accounts)));

        if ($this->roleSync->getMappings() !== []) {
            $stats = $this->roleSync->getStats();
            $log(t('Role sync: %s group memberships added, %s removed; %s Discord roles added, %s removed, %s failed.',
                $stats['groupAdded'], $stats['groupRemoved'], $stats['roleAdded'], $stats['roleRemoved'], $stats['roleErrors']
            ));
        }
    }
}
