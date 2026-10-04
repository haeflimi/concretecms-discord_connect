<?php

namespace DiscordConnect\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * For mappings synced both ways: a row means the user had the role and was in the group on the last sync.
 * It tells which side changed when they differ.
 *
 * @ORM\Entity()
 * @ORM\Table(name="DiscordConnectRoleSyncStates")
 */
class DiscordRoleSyncState
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer", options={"unsigned": true})
     */
    protected $mappingId;

    /**
     * @ORM\Id
     * @ORM\Column(type="integer", options={"unsigned": true})
     */
    protected $uID;
}
