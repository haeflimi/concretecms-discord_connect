<?php

defined('C5_EXECUTE') or die('Access Denied.');

$user = app(Concrete\Core\User\User::class);
$discordId = $this->controller->getBindingForUser($user);
$profile = $this->controller->getProfile($user);
$discordConfig = $this->controller->getDiscordConfig();
$inviteUrl = $discordConfig->getInviteUrl();
?>

<?php if ($profile !== null && $profile->isGuildMember() === false) { ?>
    <div class="alert alert-info">
        <?= t('You are not a member of our Discord server yet.') ?>
        <?php if ($inviteUrl !== null) { ?>
            <a href="<?= h($inviteUrl) ?>" target="_blank" rel="noopener" class="alert-link"><?= t('Join it on Discord') ?></a>
        <?php } elseif ($discordConfig->isAutoJoin()) { ?>
            <a href="<?= URL::to('/ccm/system/authentication/oauth2/discord/attempt_attach') ?>" class="alert-link"><?= t('Reconnect your Discord account to join it') ?></a>
        <?php } ?>
    </div>
<?php } ?>

<div class="form-group">
    <span><?= t('Detach your %s account', 'Discord') ?></span>
    <hr>
</div>
<div class="form-group">
    <a href="<?= URL::to('/ccm/system/authentication/oauth2/discord/attempt_detach') ?>" class="btn btn-discord">
        <i class="fab fa-discord"></i>
        <?= t('Detach your %s account', 'Discord') ?>
    </a>
    <?php if ($discordId) { ?>
        <div class="form-text">
            <?php if ($profile !== null) { ?>
                <img src="<?= h($profile->getAvatarUrl(32)) ?>" alt="" width="16" height="16" class="rounded-circle" />
            <?php } ?>
            <?= t('Connected Discord account: %s', '<a href="https://discord.com/users/' . h($discordId) . '" target="_blank" rel="noopener">' . h($profile !== null ? $profile->getDisplayName() : $discordId) . '</a>') ?>
        </div>
    <?php } ?>
</div>

<style>
    .btn-discord,
    .ccm-ui .btn-discord {
        color: #fff;
        background-color: #5865f2;
    }
    .btn-discord:hover,
    .ccm-ui .btn-discord:hover {
        color: #fff;
        background-color: #4752c4;
    }
    .btn-discord .fa-discord {
        margin: 0 6px 0 3px;
    }
</style>
