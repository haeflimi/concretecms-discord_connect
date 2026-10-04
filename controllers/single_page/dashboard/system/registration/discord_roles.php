<?php

namespace Concrete\Package\DiscordConnect\Controller\SinglePage\Dashboard\System\Registration;

use Concrete\Core\Form\Service\Widget\GroupSelector;
use Concrete\Core\Page\Controller\DashboardPageController;
use Concrete\Core\User\Group\GroupRepository;
use DiscordConnect\Entity\DiscordRoleMapping;
use DiscordConnect\Sync\DiscordGuildSync;
use DiscordConnect\Sync\DiscordRoleSync;
use Throwable;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Link Discord roles with Concrete groups and choose the direction of the sync.
 */
class DiscordRoles extends DashboardPageController
{
    /** @var array|null roles of the server, see DiscordRoleSync::getRoles() */
    protected $roles;

    public function view()
    {
        $configured = $this->app->make(DiscordGuildSync::class)->isConfigured();
        $roleSync = $this->app->make(DiscordRoleSync::class);
        $groupRepository = $this->app->make(GroupRepository::class);

        $rolesError = null;
        if ($configured) {
            try {
                $this->getRoles();
            } catch (Throwable $e) {
                $rolesError = $e->getMessage();
            }
        }

        $rows = [];
        foreach ($roleSync->getMappings() as $mapping) {
            $group = $groupRepository->getGroupById($mapping->getGroupID());
            $rows[] = [
                'mapping' => $mapping,
                'role' => $this->roles[$mapping->getRoleId()] ?? null,
                'groupName' => $group === null ? null : $group->getGroupDisplayName(false),
            ];
        }

        $this->set('configured', $configured);
        $this->set('roles', $this->roles ?? []);
        $this->set('rolesError', $rolesError);
        $this->set('rows', $rows);
        $this->set('directions', DiscordRoleMapping::getDirectionNames());
        $this->set('groupSelector', $this->app->make(GroupSelector::class));
    }

    public function add_mapping()
    {
        if (!$this->token->validate('discord_add_mapping')) {
            $this->error->add($this->token->getErrorMessage());
        }
        $roleId = (string) $this->request->request->get('roleId', '');
        $gID = (int) $this->request->request->get('gID', 0);
        $direction = (string) $this->request->request->get('direction', '');
        $roleSync = $this->app->make(DiscordRoleSync::class);
        if (!$this->error->has()) {
            try {
                foreach ($roleSync->validateMapping($roleId, $gID, $direction, $this->getRoles()) as $error) {
                    $this->error->add($error);
                }
            } catch (Throwable $e) {
                $this->error->add($e->getMessage());
            }
        }
        if ($this->error->has()) {
            return $this->view();
        }

        $roleSync->addMapping($roleId, $gID, $direction);
        $this->flash('success', t('The role has been linked. It will be synced by the "Sync Discord Data" task and when users log in with Discord.'));

        return $this->buildRedirect($this->action());
    }

    public function delete_mapping()
    {
        if (!$this->token->validate('discord_delete_mapping')) {
            $this->error->add($this->token->getErrorMessage());

            return $this->view();
        }
        $mapping = $this->entityManager->find(DiscordRoleMapping::class, (int) $this->request->request->get('mappingId'));
        if ($mapping !== null) {
            $this->app->make(DiscordRoleSync::class)->deleteMapping($mapping);
            $this->flash('success', t('The link has been removed. Group memberships and roles are left as they are.'));
        }

        return $this->buildRedirect($this->action());
    }

    protected function getRoles(): array
    {
        if ($this->roles === null) {
            $this->roles = $this->app->make(DiscordRoleSync::class)->getRoles();
        }

        return $this->roles;
    }
}
