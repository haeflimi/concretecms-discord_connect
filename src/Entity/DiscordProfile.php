<?php

namespace DiscordConnect\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;

/**
 * Discord account of a user who linked it, plus their membership on our Discord server.
 * The binding itself (user ID <-> Discord ID) is the core OauthUserMap (namespace "discord"), this is the data we
 * know about it. Written on login/attach and by the "Sync Discord Data" task (DiscordConnect\DiscordAccounts),
 * read-only everywhere else.
 *
 * @ORM\Entity()
 * @ORM\Table(name="DiscordConnectProfiles", indexes={@ORM\Index(name="uID", columns={"uID"})})
 */
class DiscordProfile
{
    /**
     * Discord user ID (snowflake).
     *
     * @ORM\Id
     * @ORM\Column(type="string", length=20)
     */
    protected $discordId;

    /**
     * @ORM\Column(type="integer", options={"unsigned": true})
     */
    protected $uID;

    /**
     * Unique Discord username.
     *
     * @ORM\Column(type="string", length=64, nullable=true)
     */
    protected $username;

    /**
     * Display name, null if the user didn't set one.
     *
     * @ORM\Column(type="string", length=64, nullable=true)
     */
    protected $globalName;

    /**
     * @ORM\Column(type="string", length=64, nullable=true)
     */
    protected $avatarHash;

    /**
     * Whether the user is on our Discord server, null if not checked (no server configured).
     *
     * @ORM\Column(type="boolean", nullable=true)
     */
    protected $guildMember;

    /**
     * Nickname on our server.
     *
     * @ORM\Column(type="string", length=64, nullable=true)
     */
    protected $guildNick;

    /**
     * IDs of the roles the user has on our server.
     *
     * @ORM\Column(type="json", nullable=true)
     */
    protected $guildRoles;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $guildJoinedAt;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $guildCheckedAt;

    /**
     * Last time the user logged in with or connected their Discord account.
     *
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $connectedAt;

    public function getDiscordId(): string
    {
        return $this->discordId;
    }

    public function getUserID(): int
    {
        return (int) $this->uID;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function getGlobalName(): ?string
    {
        return $this->globalName;
    }

    public function getDisplayName(): string
    {
        return $this->globalName ?: ($this->username ?: $this->discordId);
    }

    public function getAvatarHash(): ?string
    {
        return $this->avatarHash;
    }

    public function getAvatarUrl(int $size = 128): string
    {
        if ($this->avatarHash) {
            $extension = strpos($this->avatarHash, 'a_') === 0 ? 'gif' : 'png';

            return 'https://cdn.discordapp.com/avatars/' . $this->discordId . '/' . $this->avatarHash . '.' . $extension . '?size=' . $size;
        }

        return 'https://cdn.discordapp.com/embed/avatars/' . (((int) $this->discordId >> 22) % 6) . '.png';
    }

    public function getProfileUrl(): string
    {
        return 'https://discord.com/users/' . $this->discordId;
    }

    public function isGuildMember(): ?bool
    {
        return $this->guildMember === null ? null : (bool) $this->guildMember;
    }

    public function getGuildNick(): ?string
    {
        return $this->guildNick;
    }

    /**
     * @return string[]
     */
    public function getGuildRoles(): array
    {
        return is_array($this->guildRoles) ? $this->guildRoles : [];
    }

    public function hasGuildRole(string $roleId): bool
    {
        return in_array($roleId, $this->getGuildRoles(), true);
    }

    public function getGuildJoinedAt(): ?DateTime
    {
        return $this->guildJoinedAt;
    }

    public function getGuildCheckedAt(): ?DateTime
    {
        return $this->guildCheckedAt;
    }

    public function getConnectedAt(): ?DateTime
    {
        return $this->connectedAt;
    }
}
