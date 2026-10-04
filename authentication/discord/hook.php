<?php defined('C5_EXECUTE') or die('Access Denied.'); ?>

<div class="form-group">
    <span><?= t('Attach a %s account', 'Discord') ?></span>
    <hr>
</div>
<div class="form-group">
    <a href="<?= URL::to('/ccm/system/authentication/oauth2/discord/attempt_attach') ?>" class="btn btn-discord">
        <i class="fab fa-discord"></i>
        <?= t('Attach a %s account', 'Discord') ?>
    </a>
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
