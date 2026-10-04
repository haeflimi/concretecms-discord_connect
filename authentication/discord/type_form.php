<?php

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var Concrete\Core\Form\Service\Widget\GroupSelector $groupSelector
 * @var Concrete\Core\Form\Service\Form $form
 * @var string $callbackUrl
 * @var string $clientId
 * @var string $clientSecret
 * @var string $botToken
 * @var string|null $botInviteUrl
 * @var bool $registrationEnabled
 * @var int|null $registrationGroup
 * @var string $guildId
 * @var string $inviteUrl
 * @var bool $autoJoin
 * @var int $roleMappingCount
 * @var array|null $guild
 * @var string|null $guildError
 */
?>

<div class="alert alert-info">
    <?= t('Create an application in the <a href="%s" target="_blank" rel="noopener">Discord Developer Portal</a>. Under OAuth2, copy the client ID and secret and add this redirect URL:', 'https://discord.com/developers/applications') ?>
    <code class="d-block mt-1 user-select-all"><?= h($callbackUrl) ?></code>
</div>

<div class="form-group">
    <?= $form->label('client_id', t('Client ID')) ?>
    <?= $form->text('client_id', $clientId, ['autocomplete' => 'off', 'class' => 'font-monospace', 'spellcheck' => 'false']) ?>
</div>
<div class="form-group">
    <?= $form->label('client_secret', t('Client Secret')) ?>
    <?= $form->password('client_secret', $clientSecret, ['autocomplete' => 'off', 'class' => 'font-monospace', 'spellcheck' => 'false']) ?>
</div>

<fieldset>
    <legend><?= t('Our Discord Server') ?></legend>

    <?php if ($guild !== null) { ?>
        <div class="alert alert-success">
            <?= t('Connected to the server "%s" (%s members).', h($guild['name'] ?? ''), (int) ($guild['approximate_member_count'] ?? 0)) ?>
        </div>
    <?php } elseif ($guildError !== null) { ?>
        <div class="alert alert-danger"><?= h($guildError) ?></div>
    <?php } ?>

    <div class="form-group">
        <?= $form->label('bot_token', t('Bot Token')) ?>
        <?= $form->password('bot_token', $botToken, ['autocomplete' => 'off', 'class' => 'font-monospace', 'spellcheck' => 'false']) ?>
        <div class="form-text">
            <?= t('Optional, needed for all server features. In the Developer Portal, under Bot: reset and copy the token and enable the "Server Members Intent". The bot needs the "Create Invite" permission to add users to the server and "Manage Roles" to assign roles.') ?>
            <?php if ($botInviteUrl !== null) { ?>
                <?= t('Then <a href="%s" target="_blank" rel="noopener">add the bot to your server</a>.', h($botInviteUrl)) ?>
            <?php } ?>
        </div>
    </div>
    <div class="form-group">
        <?= $form->label('guild_id', t('Server ID')) ?>
        <?= $form->text('guild_id', $guildId, ['autocomplete' => 'off', 'class' => 'font-monospace', 'inputmode' => 'numeric']) ?>
        <div class="form-text"><?= t('Enable the Developer Mode in Discord (User Settings › Advanced), then right-click the server and choose "Copy Server ID".') ?></div>
    </div>
    <div class="form-group">
        <?= $form->label('invite_url', t('Invite Link')) ?>
        <?= $form->text('invite_url', $inviteUrl, ['placeholder' => 'https://discord.gg/...']) ?>
        <div class="form-text"><?= t('Optional. Shown in their profile to linked users who are not on the server.') ?></div>
    </div>
    <div class="form-group">
        <div class="form-check">
            <?= $form->checkbox('auto_join', '1', $autoJoin) ?>
            <label class="form-check-label" for="auto_join"><?= t('Add users to the server when they log in or connect their Discord account') ?></label>
        </div>
    </div>
    <div class="form-group">
        <?= $form->label('', t('Roles and Groups')) ?>
        <div class="form-text">
            <?= t2('%s Discord role is linked with a group.', '%s Discord roles are linked with groups.', $roleMappingCount) ?>
            <a href="<?= URL::to('/dashboard/system/registration/discord_roles') ?>"><?= t('Manage the role sync') ?></a>
        </div>
    </div>
</fieldset>

<fieldset>
    <legend><?= t('Registration') ?></legend>
    <div class="form-group">
        <div class="form-check">
            <?= $form->checkbox('registration_enabled', '1', $registrationEnabled) ?>
            <label class="form-check-label" for="registration_enabled"><?= t('Allow automatic registration') ?></label>
        </div>
        <div class="form-text"><?= t('Users without a verified email address on Discord have to enter and confirm one.') ?></div>
    </div>
    <div class="form-group registration-group">
        <?= $form->label('registration_group', t('Group to enter on registration')) ?>
        <?= $groupSelector->selectGroup('registration_group', $registrationGroup, tc('Group', 'None')) ?>
    </div>
</fieldset>

<script>
$(function() {
    $('input[name="registration_enabled"]')
        .on('change', function () {
            $('div.registration-group').toggle($(this).is(':checked'));
        })
        .trigger('change');
});
</script>
