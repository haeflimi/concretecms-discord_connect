<?php

namespace DiscordConnect\Command\Task;

use Concrete\Core\Command\Task\Controller\AbstractController;
use Concrete\Core\Command\Task\Input\InputInterface;
use Concrete\Core\Command\Task\Runner\CommandTaskRunner;
use Concrete\Core\Command\Task\Runner\TaskRunnerInterface;
use Concrete\Core\Command\Task\TaskInterface;
use Concrete\Core\Error\UserMessageException;
use DiscordConnect\Command\SyncDiscordDataCommand;
use DiscordConnect\Sync\DiscordGuildSync;

class SyncDiscordDataController extends AbstractController
{
    /** @var DiscordGuildSync */
    protected $guildSync;

    public function __construct(DiscordGuildSync $guildSync)
    {
        $this->guildSync = $guildSync;
    }

    public function getName(): string
    {
        return t('Sync Discord Data');
    }

    public function getDescription(): string
    {
        return t('Checks which users who linked their Discord account are members of our Discord server, updates their Discord profile and syncs the roles linked with groups.');
    }

    public function getConsoleCommandName(): string
    {
        return 'sync-discord-data';
    }

    public function getTaskRunner(TaskInterface $task, InputInterface $input): TaskRunnerInterface
    {
        if (!$this->guildSync->isConfigured()) {
            throw new UserMessageException(t('Please configure the Discord server and the bot token in the Discord authentication type first.'));
        }

        return new CommandTaskRunner($task, new SyncDiscordDataCommand(), t('Discord data synced.'));
    }
}
