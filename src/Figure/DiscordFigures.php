<?php

namespace DiscordConnect\Figure;

use CommunityEventManager\Figure\CallbackFigure;
use CommunityEventManager\Figure\FigureContext;
use CommunityEventManager\Figure\FigureOptions as O;
use CommunityEventManager\Figure\FigureRegistry;
use CommunityEventManager\Figure\FigureValue;

/**
 * Figures for the block "Event Figure" (community_event_manager), registered on on_event_figures_register.
 */
class DiscordFigures
{
    public static function register(FigureRegistry $registry): void
    {
        $group = t('Discord');
        $registry->register(CallbackFigure::make('discord.accounts', t('Connected Discord accounts'), $group, 'fab fa-discord', [], static function (FigureContext $c, array $o) {
            return FigureValue::number($c->db->fetchOne('SELECT COUNT(*) FROM DiscordConnectProfiles WHERE uID IS NOT NULL'));
        }));

        $registry->register(CallbackFigure::make('discord.server_members', t('Members on our Discord server'), $group, 'fas fa-headset', [], static function (FigureContext $c, array $o) {
            return FigureValue::number($c->db->fetchOne('SELECT COUNT(*) FROM DiscordConnectProfiles WHERE guildMember = 1'));
        }));
    }
}
