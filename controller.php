<?php

namespace Concrete\Package\DiscordConnect;

use Concrete\Core\Authentication\AuthenticationType;
use Concrete\Core\Backup\ContentImporter;
use Concrete\Core\Command\Task\Manager as TaskManager;
use Concrete\Core\Database\EntityManager\Provider\ProviderAggregateInterface;
use Concrete\Core\Database\EntityManager\Provider\StandardPackageProvider;
use Concrete\Core\Package\Package;
use DiscordConnect\Command\Task\SyncDiscordDataController;
use Throwable;

defined('C5_EXECUTE') or die('Access Denied.');

class Controller extends Package implements ProviderAggregateInterface
{
    protected $pkgHandle = 'discord_connect';
    protected $appVersionRequired = '9.0.0';
    protected $pkgVersion = '1.1.0';
    protected $pkgAutoloaderRegistries = [
        'src' => 'DiscordConnect',
    ];

    public function getPackageName()
    {
        return t('Discord Connect');
    }

    public function getPackageDescription()
    {
        return t('Adds an Authenticator for Discord and connects the users to your Discord server.');
    }

    public function getEntityManagerProvider()
    {
        return new StandardPackageProvider($this->app, $this, [
            'src/Entity' => 'DiscordConnect\Entity',
        ]);
    }

    public function on_start()
    {
        $this->app->make(TaskManager::class)->extend('sync_discord_data', function () {
            return $this->app->make(SyncDiscordDataController::class);
        });
    }

    public function install()
    {
        $pkg = parent::install();
        $this->installAuthenticationType($pkg);
        $this->installContent();

        return $pkg;
    }

    public function upgrade()
    {
        parent::upgrade();
        $this->installAuthenticationType($this->getPackageEntity());
        $this->installContent();
    }

    public function uninstall()
    {
        $type = $this->getAuthenticationType();
        if ($type !== null) {
            $type->delete();
        }
        parent::uninstall();
    }

    protected function installContent(): void
    {
        $importer = new ContentImporter();
        $importer->importContentFile($this->getPackagePath() . '/install.xml');
    }

    protected function installAuthenticationType($pkg): void
    {
        // Checked in the database: loading the type would need its controller, which is not available while installing
        if (!$this->app->make('database')->connection()->fetchOne("SELECT 1 FROM AuthenticationTypes WHERE authTypeHandle = 'discord'")) {
            // Installed disabled: it has to be configured and enabled in /dashboard/system/registration/authentication.
            AuthenticationType::add('discord', 'Discord', 0, $pkg)->disable();
        }
    }

    protected function getAuthenticationType(): ?AuthenticationType
    {
        try {
            $type = AuthenticationType::getByHandle('discord');
        } catch (Throwable $e) {
            return null;
        }

        return is_object($type) && !$type->isError() ? $type : null;
    }
}
