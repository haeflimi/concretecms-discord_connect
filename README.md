# Discord Connect Package #

This package integrates an authenticator for Discord. It allows your website visitors to register and login using
their Discord account, linking the concreteCMS account with their Discord user ID in the process, and connects them
with your own Discord server.

**This is NOT an official implementation by Discord Inc. The creator is not associated with Discord Inc. in any way.**

## Usage ##

- Clone or download this repo from GitHub or install it via composer (`haeflimi/discord_connect`).
- Install the Package via c5 Dashboard.
- Create an application in the [Discord Developer Portal](https://discord.com/developers/applications). Under
  OAuth2, copy the client ID and secret and add the redirect URL shown in the dashboard
  (`https://<your site>/ccm/system/authentication/oauth2/discord/callback`).
- On /dashboard/system/registration/authentication Discord should be available as an Authentication type now.
- Edit it, enter the client ID and secret, and enable it.
- You are good to login with Discord now.

### Your Discord server ###

- In the Developer Portal, under Bot: reset and copy the bot token and enable the **Server Members Intent**.
- Add the bot to your server (the dashboard shows a link once the client ID is saved). It needs the
  "Create Invite" permission to add users to the server, and "Manage Roles" to assign roles.
- Enter the bot token and the server ID in the dashboard. The dashboard shows whether the bot can access the server.
- Schedule the "Sync Discord Data" task (Dashboard › System & Settings › Automation, or
  `concrete/bin/concrete tasks:sync-discord-data`).

### Roles and groups ###

Nothing is synced by default. In System & Settings › Login & Registration › Discord Roles you can link Discord roles
with Concrete groups and choose the direction for each link:

- **Discord → CMS**: users with the role are put in the group, users without it are removed from it. Use the
  @everyone role for all members of the server.
- **CMS → Discord**: members of the group get the role, everybody else loses it. The highest role of the bot has to
  be above the role.
- **Both ways**: a role or group membership added or removed on one side is added or removed on the other one too.

Only users who linked their Discord account are affected. Roles are synced on login/connect and by the
"Sync Discord Data" task. For security, the Administrators group can only be synced to Discord, and @everyone and
roles managed by integrations can only be synced to the website.

## Configuration ##

- **Dashboard** (System & Settings › Login & Registration › Authentication Types › Discord): OAuth2 client, bot token,
  our Discord server, invite link, adding users to the server, registration.
- **Dashboard** (System & Settings › Login & Registration › Discord Roles): links between Discord roles and groups.
- **Config file**: the package settings and their defaults are in `config/settings.php`. Override them in
  `application/config/discord_connect/settings.php`. The client ID/secret and the bot token are stored in the core
  config (`auth.discord.*`). Values saved in the dashboard are written to `application/config/generated_overrides/`.

## Features ##

- Register new users using Discord login (users without a verified Discord email address have to confirm theirs)
- Login existing users using Discord login
- Connect a Discord account to a concreteCMS account via the user profile
- Add users to your Discord server when they log in or connect their account (`guilds.join`)
- Check which linked users are on your Discord server
- Optionally sync Discord roles and Concrete groups, in the direction of your choice
- Store the Discord user IDs, profiles and server memberships for further use with the Discord API

## Developer Information ##

The Discord user ID is the binding in the core `OauthUserMap` table (namespace `discord`), the profile and server
membership are stored in `DiscordConnectProfiles`.

```php
use DiscordConnect\Api\DiscordApi;
use DiscordConnect\DiscordAccounts;
use DiscordConnect\DiscordConfig;

$accounts = app(DiscordAccounts::class);
$discordId = $accounts->getDiscordId($user);      // Discord user ID of a Concrete user, or null
$uID = $accounts->getUserID($discordId);          // and the other way around
$profile = $accounts->getProfile($user);          // DiscordConnect\Entity\DiscordProfile, or null
$all = $accounts->getLinkedAccounts();            // [discordId => uID]

// Discord REST API, authenticated as the bot
$api = app(DiscordApi::class);
$member = $api->getGuildMember(app(DiscordConfig::class)->getGuildId(), $discordId);
$api->request('PUT', "guilds/{$guildId}/members/{$discordId}/roles/{$roleId}");
```

## Prerequisites ##

- Concrete CMS 9.0 or higher, PHP 8
- A Discord application, and a bot on your server for the server features

## Support ##

This Package is Open Source software under the MIT License. It is provided "as is",
without warranty of any kind.
However, if you find a Bug or any kind of problem with it.: Feel free to create a Issue or
Pull Request here on Github.
