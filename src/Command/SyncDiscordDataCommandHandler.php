<?php

namespace DiscordConnect\Command;

use Concrete\Core\Command\Task\Output\OutputAwareInterface;
use Concrete\Core\Command\Task\Output\OutputAwareTrait;
use DiscordConnect\DiscordAccounts;
use DiscordConnect\Sync\DiscordGuildSync;

class SyncDiscordDataCommandHandler implements OutputAwareInterface
{
    use OutputAwareTrait;

    /** @var DiscordAccounts */
    protected $accounts;

    /** @var DiscordGuildSync */
    protected $guildSync;

    public function __construct(DiscordAccounts $accounts, DiscordGuildSync $guildSync)
    {
        $this->accounts = $accounts;
        $this->guildSync = $guildSync;
    }

    public function __invoke(SyncDiscordDataCommand $command)
    {
        $this->output->write(t('Removed the data of %s unlinked Discord accounts.', $this->accounts->pruneUnlinkedAccounts()));
        $this->guildSync->syncMembership($this->accounts->getLinkedAccounts(), function (string $message) {
            $this->output->write($message);
        });
    }
}
