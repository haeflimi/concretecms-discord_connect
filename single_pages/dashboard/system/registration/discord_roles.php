<?php

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var Concrete\Core\Form\Service\Form $form
 * @var Concrete\Core\Validation\CSRF\Token $token
 * @var Concrete\Core\Form\Service\Widget\GroupSelector $groupSelector
 * @var Concrete\Package\DiscordConnect\Controller\SinglePage\Dashboard\System\Registration\DiscordRoles $controller
 * @var bool $configured
 * @var array<string, array{name: string, color: int, managed: bool, everyone: bool}> $roles
 * @var string|null $rolesError
 * @var array<array{mapping: DiscordConnect\Entity\DiscordRoleMapping, role: array|null, groupName: string|null}> $rows
 * @var array<string, string> $directions
 */

$roleLabel = static function (?array $role, string $roleId): string {
    if ($role === null) {
        return '<span class="text-danger">' . t('Unknown role (%s)', h($roleId)) . '</span>';
    }
    $color = $role['color'] ? sprintf('#%06x', $role['color']) : '#99aab5';

    return '<span class="discord-role-dot" style="background-color: ' . $color . '"></span>'
        . h($role['name']) . ($role['everyone'] ? ' <span class="text-muted">' . t('(server membership)') . '</span>' : '');
};
?>

<?php if (!$configured) { ?>
    <div class="alert alert-warning">
        <?= t('Please configure the Discord server and the bot token in the <a href="%s">Discord authentication type</a> first.', URL::to('/dashboard/system/registration/authentication')) ?>
    </div>
<?php } elseif ($rolesError !== null) { ?>
    <div class="alert alert-danger"><?= t('Unable to read the roles of the Discord server: %s', h($rolesError)) ?></div>
<?php } ?>

<p>
    <?= t('Link roles of your Discord server with groups of this website. Nothing is synced without a link. Only users who connected their Discord account are affected, other members of a group are left alone.') ?>
</p>
<dl class="row">
    <dt class="col-sm-3"><?= $directions[DiscordConnect\Entity\DiscordRoleMapping::DIRECTION_DISCORD_TO_CMS] ?></dt>
    <dd class="col-sm-9"><?= t('Users with the role are put in the group, users without it are removed from it. Use the @everyone role for all members of the server.') ?></dd>
    <dt class="col-sm-3"><?= $directions[DiscordConnect\Entity\DiscordRoleMapping::DIRECTION_CMS_TO_DISCORD] ?></dt>
    <dd class="col-sm-9"><?= t('Members of the group get the role, everybody else loses it. The bot needs the "Manage Roles" permission and its highest role has to be above the role.') ?></dd>
    <dt class="col-sm-3"><?= $directions[DiscordConnect\Entity\DiscordRoleMapping::DIRECTION_BOTH] ?></dt>
    <dd class="col-sm-9"><?= t('A role or group membership that has been added or removed on one side is added or removed on the other one too.') ?></dd>
</dl>
<p class="text-muted">
    <?= t('Group changes in the CMS are pushed to Discord right away. Changes on Discord are synced when users log in with Discord or connect their account, and for everybody by the "Sync Discord Data" task (<a href="%s">Automation</a>), which also retries everything that failed.', URL::to('/dashboard/system/automation/tasks')) ?>
</p>

<h3><?= t('Linked Roles') ?></h3>
<?php if ($rows === []) { ?>
    <p><?= t('No roles are linked, nothing is synced.') ?></p>
<?php } else { ?>
    <table class="table table-striped align-middle">
        <thead>
            <tr>
                <th><?= t('Discord Role') ?></th>
                <th><?= t('Direction') ?></th>
                <th><?= t('Group') ?></th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $row) {
                $mapping = $row['mapping']; ?>
                <tr>
                    <td><?= $roleLabel($row['role'], $mapping->getRoleId()) ?></td>
                    <td><?= h($directions[$mapping->getDirection()] ?? $mapping->getDirection()) ?></td>
                    <td><?= $row['groupName'] === null ? '<span class="text-danger">' . t('Deleted group (%s)', $mapping->getGroupID()) . '</span>' : h($row['groupName']) ?></td>
                    <td class="text-end">
                        <form method="post" action="<?= $controller->action('delete_mapping') ?>" class="d-inline">
                            <?php $token->output('discord_delete_mapping') ?>
                            <input type="hidden" name="mappingId" value="<?= (int) $mapping->getId() ?>" />
                            <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm(<?= h(json_encode(t('Remove this link? Group memberships and roles are left as they are.'))) ?>)"><?= t('Remove') ?></button>
                        </form>
                    </td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
<?php } ?>

<?php if ($configured && $rolesError === null) { ?>
    <h3><?= t('Link a Role') ?></h3>
    <form method="post" action="<?= $controller->action('add_mapping') ?>">
        <?php $token->output('discord_add_mapping') ?>
        <div class="row">
            <div class="col-md-4 form-group">
                <?= $form->label('roleId', t('Discord Role')) ?>
                <select name="roleId" id="roleId" class="form-select" required>
                    <option value=""><?= t('Choose a Role') ?></option>
                    <?php foreach ($roles as $roleId => $role) { ?>
                        <option value="<?= h($roleId) ?>" data-assignable="<?= $role['everyone'] || $role['managed'] ? '0' : '1' ?>"<?= (string) $controller->request('roleId') === (string) $roleId ? ' selected' : '' ?>>
                            <?= h($role['name']) ?><?= $role['everyone'] ? ' ' . t('(server membership)') : '' ?><?= $role['managed'] ? ' ' . t('(managed by an integration)') : '' ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-md-3 form-group">
                <?= $form->label('direction', t('Direction')) ?>
                <?= $form->select('direction', $directions, DiscordConnect\Entity\DiscordRoleMapping::DIRECTION_DISCORD_TO_CMS) ?>
            </div>
            <div class="col-md-5 form-group">
                <?= $form->label('gID', t('Group')) ?>
                <?= $groupSelector->selectGroup('gID') ?>
            </div>
        </div>
        <div class="form-text mb-3"><?= t('@everyone and roles managed by an integration (bots, boosts) can only be synced to the website. The Administrators group can only be synced to Discord.') ?></div>
        <button type="submit" class="btn btn-primary"><?= t('Link Role') ?></button>
    </form>
<?php } ?>

<style>
    .discord-role-dot {
        display: inline-block;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        margin-right: 6px;
    }
</style>

<script>
$(function() {
    // Roles the bot can't assign can only be synced to the website
    var $role = $('#roleId'), $direction = $('#direction');
    $role.on('change', function () {
        var assignable = $role.find('option:selected').data('assignable') !== 0;
        $direction.find('option').not('[value="discord"]').prop('disabled', !assignable);
        if (!assignable) {
            $direction.val('discord');
        }
    }).trigger('change');
});
</script>
