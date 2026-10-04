<?php

// Defaults of the Discord Connect package. Override them in application/config/discord_connect/settings.php,
// e.g. return ['auto_join' => false];
// Values saved in the dashboard (Discord authentication type) end up in application/config/generated_overrides.
// The OAuth2 client ID/secret and the bot token are stored in the core config (auth.discord.*).
return [
    // ID of our Discord server ("guild"), empty = no server features.
    // Discord: User Settings › Advanced › Developer Mode, then right-click the server › Copy Server ID.
    'guild_id' => '',
    // Invite link shown to linked users who are not on the server, e.g. https://discord.gg/abcdef
    'invite_url' => '',
    // Add users to the server when they log in or connect their account (requests the "guilds.join" scope,
    // the bot has to be on the server and needs the "Create Invite" permission).
    // Discord roles are linked with Concrete groups in the dashboard (Login & Registration › Discord Roles).
    'auto_join' => true,
];
