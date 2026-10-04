<?php

namespace DiscordConnect\Api;

use Concrete\Core\Config\Repository\Repository;
use Concrete\Core\Http\Client\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Minimal client for the Discord REST API, authenticated as our bot (auth.discord.bot_token).
 * Use it for anything you want to do with the stored Discord user IDs.
 *
 * @see https://discord.com/developers/docs/reference
 */
class DiscordApi
{
    const BASE_URL = 'https://discord.com/api/v10/';

    /** Members per request of GET /guilds/{guild.id}/members (maximum allowed by Discord) */
    protected const MEMBERS_PER_PAGE = 1000;

    /** How often a rate limited request is retried */
    protected const MAX_RETRIES = 3;

    /** Longest rate limit we wait for, in seconds */
    protected const MAX_RETRY_AFTER = 10;

    /** @var Client */
    protected $httpClient;

    /** @var Repository */
    protected $config;

    public function __construct(Client $httpClient, Repository $config)
    {
        $this->httpClient = $httpClient;
        $this->config = $config;
    }

    public function hasBotToken(): bool
    {
        return $this->getBotToken() !== '';
    }

    /**
     * @throws DiscordApiException
     */
    public function getUser(string $userId): array
    {
        return $this->request('GET', 'users/' . $userId);
    }

    /**
     * @throws DiscordApiException
     */
    public function getGuild(string $guildId): array
    {
        return $this->request('GET', 'guilds/' . $guildId, ['query' => ['with_counts' => 'true']]);
    }

    /**
     * @return array|null the guild member object, null if the user is not on the server
     *
     * @throws DiscordApiException
     */
    public function getGuildMember(string $guildId, string $userId): ?array
    {
        try {
            return $this->request('GET', 'guilds/' . $guildId . '/members/' . $userId);
        } catch (DiscordApiException $e) {
            // 10007: Unknown Member, 10013: Unknown User
            if ($e->getCode() === 404 && in_array($e->getDiscordErrorCode(), [10007, 10013], true)) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * All members of the server. Requires the privileged "Server Members Intent" of the bot.
     *
     * @return array[] guild member objects
     *
     * @throws DiscordApiException
     */
    public function getGuildMembers(string $guildId): array
    {
        $members = [];
        $after = '0';
        do {
            $page = $this->request('GET', 'guilds/' . $guildId . '/members', [
                'query' => ['limit' => self::MEMBERS_PER_PAGE, 'after' => $after],
            ]);
            foreach ($page as $member) {
                $members[] = $member;
                $after = (string) $member['user']['id'];
            }
        } while (count($page) === self::MEMBERS_PER_PAGE);

        return $members;
    }

    /**
     * Add a user to the server. Needs an OAuth2 access token of the user with the "guilds.join" scope.
     *
     * @return bool true if the user has been added, false if they already were a member
     *
     * @throws DiscordApiException
     */
    public function addGuildMember(string $guildId, string $userId, string $accessToken): bool
    {
        return $this->request('PUT', 'guilds/' . $guildId . '/members/' . $userId, [
            'json' => ['access_token' => $accessToken],
        ]) !== null;
    }

    /**
     * @return array[] role objects, including @everyone (its ID is the server ID)
     *
     * @throws DiscordApiException
     */
    public function getGuildRoles(string $guildId): array
    {
        return $this->request('GET', 'guilds/' . $guildId . '/roles');
    }

    /**
     * Requires the "Manage Roles" permission, and the role has to be below the highest role of the bot.
     *
     * @throws DiscordApiException
     */
    public function addGuildMemberRole(string $guildId, string $userId, string $roleId): void
    {
        $this->request('PUT', 'guilds/' . $guildId . '/members/' . $userId . '/roles/' . $roleId, [
            'headers' => ['X-Audit-Log-Reason' => 'Discord Connect: role sync'],
        ]);
    }

    /**
     * @throws DiscordApiException
     */
    public function removeGuildMemberRole(string $guildId, string $userId, string $roleId): void
    {
        $this->request('DELETE', 'guilds/' . $guildId . '/members/' . $userId . '/roles/' . $roleId, [
            'headers' => ['X-Audit-Log-Reason' => 'Discord Connect: role sync'],
        ]);
    }

    /**
     * @return array|null the decoded response, null for "204 No Content"
     *
     * @throws DiscordApiException
     */
    public function request(string $method, string $path, array $options = []): ?array
    {
        if (!$this->hasBotToken()) {
            throw new DiscordApiException(t('No Discord bot token configured.'));
        }
        $options += [
            'timeout' => 20,
            'http_errors' => false,
        ];
        $options['headers'] = ($options['headers'] ?? []) + [
            'Authorization' => 'Bot ' . $this->getBotToken(),
            'User-Agent' => 'DiscordBot (https://github.com/haeflimi/concretecms-discord_connect, 1.0)',
            'Accept' => 'application/json',
        ];

        for ($attempt = 0; ; ++$attempt) {
            try {
                $response = $this->httpClient->request($method, self::BASE_URL . ltrim($path, '/'), $options);
            } catch (GuzzleException $e) {
                throw new DiscordApiException(t('Unable to reach the Discord API: %s', $e->getMessage()), 0, $e);
            }
            $status = $response->getStatusCode();
            $data = json_decode((string) $response->getBody(), true);

            if ($status === 429 && $attempt < self::MAX_RETRIES) {
                $retryAfter = (float) ($data['retry_after'] ?? $response->getHeaderLine('Retry-After') ?: 1);
                if ($retryAfter <= self::MAX_RETRY_AFTER) {
                    usleep((int) ceil($retryAfter * 1000000));
                    continue;
                }
            }
            if ($status === 204) {
                return null;
            }
            if ($status >= 400 || !is_array($data)) {
                $message = is_array($data) && isset($data['message']) ? $data['message'] : $response->getReasonPhrase();

                throw (new DiscordApiException(t('Discord API error %s on %s: %s', $status, $path, $message), $status))
                    ->setResponseData(is_array($data) ? $data : null);
            }

            return $data;
        }
    }

    protected function getBotToken(): string
    {
        return trim((string) $this->config->get('auth.discord.bot_token', ''));
    }
}
