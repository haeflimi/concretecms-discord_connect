<?php

namespace DiscordConnect\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Links a role on our Discord server with a Concrete group, see DiscordConnect\Sync\DiscordRoleSync.
 * Managed in the dashboard: System & Settings › Login & Registration › Discord Roles.
 *
 * @ORM\Entity()
 * @ORM\Table(name="DiscordConnectRoleMappings", uniqueConstraints={@ORM\UniqueConstraint(name="roleGroup", columns={"roleId", "gID"})})
 */
class DiscordRoleMapping
{
    /** Users with the Discord role are put in the group, everybody else is removed from it */
    const DIRECTION_DISCORD_TO_CMS = 'discord';

    /** Members of the group get the Discord role, everybody else loses it */
    const DIRECTION_CMS_TO_DISCORD = 'cms';

    /** Changes on either side are applied to the other one */
    const DIRECTION_BOTH = 'both';

    /**
     * @ORM\Id
     * @ORM\Column(type="integer", options={"unsigned": true})
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * Discord role ID. The ID of the server itself is the "everyone" role, i.e. membership on the server.
     *
     * @ORM\Column(type="string", length=20)
     */
    protected $roleId;

    /**
     * @ORM\Column(type="integer", options={"unsigned": true})
     */
    protected $gID;

    /**
     * @ORM\Column(type="string", length=16)
     */
    protected $direction;

    public function __construct(string $roleId, int $gID, string $direction)
    {
        $this->roleId = $roleId;
        $this->gID = $gID;
        $this->direction = $direction;
    }

    /**
     * @return array<string, string>
     */
    public static function getDirectionNames(): array
    {
        return [
            self::DIRECTION_DISCORD_TO_CMS => t('Discord → CMS'),
            self::DIRECTION_CMS_TO_DISCORD => t('CMS → Discord'),
            self::DIRECTION_BOTH => t('Both ways'),
        ];
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRoleId(): string
    {
        return $this->roleId;
    }

    public function getGroupID(): int
    {
        return (int) $this->gID;
    }

    public function getDirection(): string
    {
        return $this->direction;
    }

    public function syncsToCms(): bool
    {
        return $this->direction !== self::DIRECTION_CMS_TO_DISCORD;
    }

    public function syncsToDiscord(): bool
    {
        return $this->direction !== self::DIRECTION_DISCORD_TO_CMS;
    }
}
