<?php

namespace DiscordConnect;

use Concrete\Core\Package\PackageService;

/**
 * Access to the package settings, see config/settings.php for the available keys and their defaults.
 */
class DiscordConfig
{
    /** @var \Concrete\Core\Config\Repository\Liaison */
    protected $config;

    public function __construct(PackageService $packageService)
    {
        $this->config = $packageService->getClass('discord_connect')->getFileConfig();
    }

    public function get(string $key, $default = null)
    {
        return $this->config->get('settings.' . $key, $default);
    }

    public function save(string $key, $value): void
    {
        $this->config->save('settings.' . $key, $value);
    }

    /**
     * @return string|null the ID of our Discord server, null if none is configured
     */
    public function getGuildId(): ?string
    {
        $guildId = trim((string) $this->get('guild_id', ''));

        return self::isSnowflake($guildId) ? $guildId : null;
    }

    public function getInviteUrl(): ?string
    {
        $url = trim((string) $this->get('invite_url', ''));

        return preg_match('#^https://#i', $url) ? $url : null;
    }

    public function isAutoJoin(): bool
    {
        return (bool) $this->get('auto_join', true);
    }

    /**
     * Discord IDs ("snowflakes") are 64 bit integers, serialized as strings.
     */
    public static function isSnowflake(string $value): bool
    {
        return (bool) preg_match('/^\d{15,20}$/', $value);
    }
}
