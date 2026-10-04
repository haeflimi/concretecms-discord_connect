<?php

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var string|null $error
 * @var string|null $message
 * @var bool|null $show_email
 * @var string|null $username
 * @var Concrete\Core\Validation\CSRF\Token|null $token
 */

if (!empty($error)) {
    ?>
    <div class="alert alert-danger"><?= h($error) ?></div>
    <?php
}
if (!empty($message)) {
    ?>
    <div class="alert alert-success"><?= h($message) ?></div>
    <?php
}

if (!empty($show_email)) {
    ?>
    <form method="post" action="<?= URL::to('/login/callback/discord/handle_register') ?>">
        <p><?= t('Register an account for "%s"', h($username)) ?></p>
        <div class="input-group">
            <input type="email" name="uEmail" placeholder="<?= t('Email Address') ?>" class="form-control" required />
            <button class="btn btn-primary"><?= t('Register') ?></button>
        </div>
        <?= $token->output('discord_register') ?>
    </form>
    <?php
} else {
    ?>
    <div class="form-group external-auth-option">
        <div class="d-grid">
            <a href="<?= URL::to('/ccm/system/authentication/oauth2/discord/attempt_auth') ?>" class="btn btn-discord">
                <i class="fab fa-discord"></i>
                <?= t('Log in with %s', 'Discord') ?>
            </a>
        </div>
    </div>
    <?php
}
?>
<style>
    .btn-discord,
    .ccm-ui .btn-discord {
        color: #fff;
        background-color: #5865f2;
    }
    .btn-discord:focus,
    .btn-discord:hover,
    .ccm-ui .btn-discord:focus,
    .ccm-ui .btn-discord:hover {
        color: #fff;
        background-color: #4752c4;
    }
    .btn-discord .fa-discord {
        margin: 0 6px 0 3px;
    }
</style>
