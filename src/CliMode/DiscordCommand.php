<?php

namespace DiscordConnect\CliMode;

use CliMode\Command\ClosureCommand;
use CliMode\Terminal\Context;
use Concrete\Core\Authentication\AuthenticationType;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * CLI Mode command "discord" (registered by the package controller when cli_mode is installed): the browser leaves the
 * terminal for the Discord login. Guests sign in, members connect their Discord account (or hear it is connected).
 */
class DiscordCommand
{
    public static function make(): ClosureCommand
    {
        return ClosureCommand::make('discord', t('Sign in with %1$s or connect your %1$s account', 'Discord'), static function (Context $c, InputInterface $in, OutputInterface $out) {
            try {
                $type = AuthenticationType::getByHandle('discord');
            } catch (Throwable $e) {
                $type = null;
            }
            if (!$type || !$type->isEnabled()) {
                $out->writeln('<error> ' . t('%s login is switched off on this site.', 'Discord') . ' </error>');

                return 1;
            }
            $urls = $c->app->make('url/manager');
            if (!$c->isRegistered()) {
                $out->writeln(t('Off to %s. You come back signed in.', 'Discord'));
                $c->addAction('redirect', ['url' => (string) $urls->resolve(['/ccm/system/authentication/oauth2/discord/attempt_auth'])]);

                return 0;
            }
            if ($type->getController()->getBindingForUser($c->user)) {
                $out->writeln('<ok>' . t('Your %s account is already connected.', 'Discord') . '</ok>');

                return 0;
            }
            $out->writeln(t('Off to %s to connect your account.', 'Discord'));
            $c->addAction('redirect', ['url' => (string) $urls->resolve(['/ccm/system/authentication/oauth2/discord/attempt_attach'])]);

            return 0;
        }, ['category' => 'system', 'aliases' => [], 'help' => t('Not logged in: takes you to the %1$s login, you come back signed in. Logged in: connects your %1$s account to your club account.', 'Discord')]);
    }
}
