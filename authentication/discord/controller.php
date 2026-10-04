<?php

namespace Concrete\Package\DiscordConnect\Authentication\Discord;

use Concrete\Core\Attribute\Key\UserKey;
use Concrete\Core\Authentication\Type\OAuth\OAuth2\GenericOauth2TypeController;
use Concrete\Core\Error\UserMessageException;
use Concrete\Core\Form\Service\Widget\GroupSelector;
use Concrete\Core\Routing\RedirectResponse;
use Concrete\Core\Url\Resolver\Manager\ResolverManagerInterface;
use Concrete\Core\User\Group\GroupRepository;
use Concrete\Core\User\User;
use Concrete\Core\User\UserInfo;
use Concrete\Core\User\UserInfoRepository;
use DiscordConnect\Api\DiscordApi;
use DiscordConnect\DiscordAccounts;
use DiscordConnect\DiscordConfig;
use DiscordConnect\Entity\DiscordProfile;
use DiscordConnect\OAuth\DiscordService;
use DiscordConnect\OAuth\DiscordServiceFactory;
use DiscordConnect\Sync\DiscordGuildSync;
use DiscordConnect\Sync\DiscordRoleSync;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Discord login. The core OAuth2 controller provides the routes (/ccm/system/authentication/oauth2/discord/...),
 * the user binding storage (OauthUserMap, the binding is the Discord user ID) and the attach/detach integration in
 * the user profile. The flows are overridden to return responses, to only create accounts with a confirmed email
 * address and to connect the users with our Discord server.
 */
class Controller extends GenericOauth2TypeController
{
    protected const SESSION_PENDING = 'discord_connect.pending_registration';

    /** The core routes the attach flow through the login callback too, it is recognized by this state prefix */
    protected const ATTACH_STATE_PREFIX = 'attach:';

    public function getHandle()
    {
        return 'discord';
    }

    public function getAuthenticationTypeIconHTML()
    {
        return '<i class="fab fa-discord"></i>';
    }

    public function supportsRegistration()
    {
        return (bool) $this->app->make('config')->get('auth.discord.registration.enabled', false);
    }

    public function registrationGroupID()
    {
        return (int) $this->app->make('config')->get('auth.discord.registration.group');
    }

    /**
     * @return DiscordService
     */
    public function getService()
    {
        if (!$this->service) {
            $this->service = $this->app->make(DiscordServiceFactory::class)->createService();
        }

        return $this->service;
    }

    public function getAdditionalRequestParameters()
    {
        // Skip the Discord consent screen if the user already authorized our application
        return ['prompt' => 'none'];
    }

    public function getUniqueId()
    {
        return $this->getBindingForUser($this->app->make(User::class));
    }

    public function handle_authentication_attempt()
    {
        if ($this->app->make(User::class)->isRegistered()) {
            return $this->errorResponse(t('You are already logged in.'));
        }

        return new RedirectResponse((string) $this->getService()->getAuthorizationUri($this->getAdditionalRequestParameters()));
    }

    public function handle_authentication_callback()
    {
        if (strpos((string) $this->request->query->get('state'), self::ATTACH_STATE_PREFIX) === 0) {
            return $this->handle_attach_callback();
        }
        if ($this->app->make(User::class)->isRegistered()) {
            return $this->errorResponse(t('You are already logged in.'));
        }

        $discordUser = $this->fetchDiscordUser();
        if ($discordUser === null) {
            return $this->errorResponse(t('Discord authentication failed. Please try again.'));
        }

        $userID = $this->getBoundUserID($discordUser['id']);
        if ($userID) {
            $userInfo = $this->app->make(UserInfoRepository::class)->getByID($userID);
            if (!$userInfo) {
                return $this->errorResponse(t('Failed to complete authentication.'));
            }
            // Accounts registered without a verified Discord email stay inactive until the email address has been confirmed.
            if (!$userInfo->isActive() && $userInfo->isValidated()) {
                return $this->errorResponse(t($this->app->make('config')->get('concrete.user.deactivation.message')));
            }
            if (!$userInfo->isActive() || ($this->app->make('config')->get('concrete.user.registration.validate_email') && !$userInfo->isValidated())) {
                return $this->errorResponse(t('This account has not yet been validated. Please check the email associated with this account and follow the link it contains.'));
            }

            return $this->login($userID, $discordUser);
        }

        if (!$this->supportsRegistration()) {
            return $this->errorResponse(t('No local user account is associated with this Discord account. Please log in with a local account and connect your Discord account from your user profile.'));
        }

        $email = $this->getVerifiedEmail($discordUser);
        if ($email !== null) {
            if ($this->app->make(UserInfoRepository::class)->getByEmail($email)) {
                return $this->errorResponse(t('A user account already exists for this email, please log in and attach your Discord account from your account page.'));
            }
            try {
                $userInfo = $this->registerUser($discordUser, $email, true);
            } catch (Throwable $e) {
                $this->logger->error($e->getMessage(), ['exception' => $e]);

                return $this->errorResponse(t('Unable to create new account.'));
            }

            return $this->login((int) $userInfo->getUserID(), $discordUser);
        }

        // Discord accounts don't need to have a (verified) email address: ask for one and confirm it before activating the account.
        $this->app->make('session')->set(self::SESSION_PENDING, [
            'discordUser' => $discordUser,
            'accessToken' => $this->getAccessToken(),
        ]);
        $token = $this->app->make('token')->generate('discord_register');

        return $this->redirectResponse('/login/callback/discord/handle_register/' . $token);
    }

    public function handle_register($token = null)
    {
        $session = $this->app->make('session');
        $pending = $session->get(self::SESSION_PENDING);
        $tokenValidator = $this->app->make('token');
        if (!$this->supportsRegistration() || !is_array($pending) || empty($pending['discordUser']['id'])
            || (!$tokenValidator->validate('discord_register', $token) && !$tokenValidator->validate('discord_register'))
        ) {
            $this->set('error', t('Your registration session has expired. Please sign in with Discord again.'));

            return;
        }
        $discordUser = $pending['discordUser'];

        $this->set('show_email', true);
        $this->set('username', $discordUser['global_name'] ?? $discordUser['username'] ?? $discordUser['id']);
        $this->set('token', $tokenValidator);

        $email = $this->request->request->get('uEmail');
        if (!is_string($email) || $email === '') {
            return;
        }
        $email = trim($email);
        if (!$this->app->make('helper/validation/strings')->email($email)) {
            $this->set('error', t('Please enter a valid email address.'));

            return;
        }
        if ($this->app->make(UserInfoRepository::class)->getByEmail($email)) {
            $this->set('error', t('A user account already exists for this email, please log in and attach your Discord account from your account page.'));

            return;
        }

        try {
            $userInfo = $this->registerUser($discordUser, $email, false);
        } catch (Throwable $e) {
            $this->logger->error($e->getMessage(), ['exception' => $e]);
            $this->set('error', t('Unable to create new account.'));

            return;
        }
        $session->remove(self::SESSION_PENDING);
        $this->onConnected($discordUser, (int) $userInfo->getUserID(), $pending['accessToken'] ?? null);

        $this->set('show_email', false);
        $this->set('message', t('Your account has been created. We sent an email to %s: click on the link it contains to confirm your email address, then you can log in with Discord.', $email));
    }

    public function handle_attach_attempt()
    {
        if (!$this->app->make(User::class)->isRegistered()) {
            return $this->errorResponse(t('A user must be logged in to attach a Discord account.'));
        }

        $state = self::ATTACH_STATE_PREFIX . bin2hex(random_bytes(16));

        return new RedirectResponse((string) $this->getService()->getAuthorizationUri($this->getAdditionalRequestParameters() + ['state' => $state]));
    }

    public function handle_attach_callback()
    {
        $user = $this->app->make(User::class);
        if (!$user->isRegistered()) {
            return $this->redirectResponse('/login');
        }

        $discordUser = $this->fetchDiscordUser();
        if ($discordUser === null) {
            return $this->errorResponse(t('Discord authentication failed. Please try again.'));
        }

        $userID = (int) $user->getUserID();
        $boundUserID = $this->getBoundUserID($discordUser['id']);
        if ($boundUserID && (int) $boundUserID !== $userID) {
            return $this->errorResponse(t('This Discord account is already connected to another user account.'));
        }

        try {
            $this->bindUserID($userID, $discordUser['id']);
        } catch (Throwable $e) {
            return $this->errorResponse(t('Unable to attach user.'));
        }
        $this->onConnected($discordUser, $userID, $this->getAccessToken());

        return $this->successResponse(t('Successfully attached.'));
    }

    public function handle_detach_attempt()
    {
        $user = $this->app->make(User::class);
        if (!$user->isRegistered()) {
            return $this->redirectResponse('/login');
        }

        $binding = $this->getBindingForUser($user);
        if ($binding !== null) {
            try {
                $this->getBindingService()->clearBinding($user->getUserID(), $binding, $this->getHandle(), true);
                $this->app->make(DiscordAccounts::class)->deleteProfile($binding);
                $this->app->make(DiscordRoleSync::class)->removeUser((int) $user->getUserID());
            } catch (Throwable $e) {
                return $this->errorResponse(t('Unable to detach account.'));
            }
        }

        return $this->successResponse(t('Successfully detached.'));
    }

    public function saveAuthenticationType($args)
    {
        $guildId = trim((string) ($args['guild_id'] ?? ''));
        if ($guildId !== '' && !DiscordConfig::isSnowflake($guildId)) {
            throw new UserMessageException(t('The Discord server ID must be a number.'));
        }
        $inviteUrl = trim((string) ($args['invite_url'] ?? ''));
        if ($inviteUrl !== '' && !preg_match('#^https://#i', $inviteUrl)) {
            throw new UserMessageException(t('The invite link must start with https://'));
        }

        $config = $this->app->make('config');
        $config->save('auth.discord.client_id', trim((string) ($args['client_id'] ?? '')));
        $config->save('auth.discord.client_secret', trim((string) ($args['client_secret'] ?? '')));
        $config->save('auth.discord.bot_token', trim((string) ($args['bot_token'] ?? '')));
        $config->save('auth.discord.registration.enabled', !empty($args['registration_enabled']));
        $config->save('auth.discord.registration.group', (int) ($args['registration_group'] ?? 0));
        $discordConfig = $this->app->make(DiscordConfig::class);
        $discordConfig->save('guild_id', $guildId);
        $discordConfig->save('invite_url', $inviteUrl);
        $discordConfig->save('auto_join', !empty($args['auto_join']));
    }

    public function edit()
    {
        $config = $this->app->make('config');
        $groupRepository = $this->app->make(GroupRepository::class);
        $discordConfig = $this->app->make(DiscordConfig::class);
        $clientId = (string) $config->get('auth.discord.client_id', '');

        $this->set('form', $this->app->make('helper/form'));
        $this->set('groupSelector', $this->app->make(GroupSelector::class));
        $this->set('callbackUrl', $this->app->make(DiscordServiceFactory::class)->getCallbackUrl());
        $this->set('clientId', $clientId);
        $this->set('clientSecret', (string) $config->get('auth.discord.client_secret', ''));
        $this->set('botToken', (string) $config->get('auth.discord.bot_token', ''));
        // Permissions "Create Invite" (1 << 0) to add users to the server and "Manage Roles" (1 << 28) for the role sync
        $this->set('botInviteUrl', $clientId === '' ? null : 'https://discord.com/oauth2/authorize?' . http_build_query(['client_id' => $clientId, 'scope' => 'bot', 'permissions' => (1 << 0) | (1 << 28)]));
        $this->set('registrationEnabled', (bool) $config->get('auth.discord.registration.enabled'));
        $registrationGroupID = (int) $config->get('auth.discord.registration.group');
        $registrationGroup = $registrationGroupID === 0 ? null : $groupRepository->getGroupById($registrationGroupID);
        $this->set('registrationGroup', $registrationGroup === null ? null : (int) $registrationGroup->getGroupID());
        $this->set('guildId', (string) $discordConfig->get('guild_id', ''));
        $this->set('inviteUrl', (string) $discordConfig->get('invite_url', ''));
        $this->set('autoJoin', $discordConfig->isAutoJoin());
        $this->set('roleMappingCount', count($this->app->make(DiscordRoleSync::class)->getMappings()));

        // Show whether the bot can access the server
        $guild = null;
        $guildError = null;
        if ($this->app->make(DiscordGuildSync::class)->isConfigured()) {
            try {
                $guild = $this->app->make(DiscordApi::class)->getGuild($discordConfig->getGuildId());
            } catch (Throwable $e) {
                $guildError = $e->getMessage();
            }
        }
        $this->set('guild', $guild);
        $this->set('guildError', $guildError);
    }

    /**
     * The stored Discord profile of a user, null if they didn't link a Discord account.
     */
    public function getProfile(User $user): ?DiscordProfile
    {
        return $this->app->make(DiscordAccounts::class)->getProfile($user);
    }

    public function getDiscordConfig(): DiscordConfig
    {
        return $this->app->make(DiscordConfig::class);
    }

    /**
     * Exchange the code of the OAuth2 callback for an access token and fetch the Discord user with it.
     *
     * @return array|null the Discord user object (id, username, global_name, avatar, email, verified, ...)
     */
    protected function fetchDiscordUser(): ?array
    {
        // No code: the user cancelled (?error=access_denied)
        $code = (string) $this->request->query->get('code');
        if ($code === '') {
            return null;
        }
        try {
            $service = $this->getService();
            $this->setToken($service->requestAccessToken($code, (string) $this->request->query->get('state')));
            $discordUser = json_decode($service->request('users/@me'), true);
        } catch (Throwable $e) {
            $this->logger->notice(t('Discord authentication failed: %s', $e->getMessage()));

            return null;
        }
        if (!is_array($discordUser) || !DiscordConfig::isSnowflake((string) ($discordUser['id'] ?? ''))) {
            return null;
        }
        $discordUser['id'] = (string) $discordUser['id'];

        return $discordUser;
    }

    protected function getAccessToken(): ?string
    {
        return $this->getToken() === null ? null : $this->getToken()->getAccessToken();
    }

    protected function getVerifiedEmail(array $discordUser): ?string
    {
        $email = trim((string) ($discordUser['email'] ?? ''));

        return $email !== '' && !empty($discordUser['verified']) ? $email : null;
    }

    /**
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function login(int $userID, array $discordUser)
    {
        $user = User::loginByUserID($userID);
        if (!$user || $user->isError()) {
            return $this->errorResponse(t('Failed to complete authentication.'));
        }
        $this->app->make('session')->migrate();
        $this->onConnected($discordUser, $userID, $this->getAccessToken());

        return $this->completeAuthentication($user);
    }

    /**
     * Store the Discord profile and connect the user with our Discord server.
     */
    protected function onConnected(array $discordUser, int $userID, ?string $accessToken): void
    {
        try {
            $this->app->make(DiscordAccounts::class)->saveUser($discordUser, $userID, true);
            $this->app->make(DiscordGuildSync::class)->connect($discordUser['id'], $userID, $accessToken);
        } catch (Throwable $e) {
            // The login itself worked, don't fail it
            $this->logger->warning(t('Unable to connect the Discord account %s with the Discord server: %s', $discordUser['id'], $e->getMessage()), ['exception' => $e]);
        }
    }

    /**
     * Create an account bound to the Discord ID. If the email address is not verified by Discord, the account is
     * inactive and the core "validate your email" mail is sent: the link in it (/login/callback/concrete/v/<hash>)
     * marks the account as validated and activates it.
     */
    protected function registerUser(array $discordUser, string $email, bool $emailVerified): UserInfo
    {
        $registration = $this->app->make('user/registration');
        $userInfo = $registration->create([
            'uName' => $registration->getNewUsernameFromUserDetails($email, $discordUser['username'] ?? null),
            'uPassword' => Str::random(64),
            'uEmail' => $email,
            'uIsValidated' => $emailVerified ? 1 : 0,
        ]);
        if (!$userInfo) {
            throw new RuntimeException('Unable to create new account.');
        }
        if (!$emailVerified) {
            $userInfo->deactivate();
        }

        if ($groupID = $this->registrationGroupID()) {
            $group = $this->app->make(GroupRepository::class)->getGroupById($groupID);
            if ($group) {
                User::getByUserID($userInfo->getUserID())->enterGroup($group);
            }
        }

        $attributes = UserKey::getRegistrationList();
        if (!empty($attributes)) {
            $userInfo->saveUserAttributesDefault($attributes);
        }

        $this->bindUserID((int) $userInfo->getUserID(), $discordUser['id']);

        if (!$emailVerified) {
            $this->app->make('user/status')->sendEmailValidation($userInfo);
        }

        return $userInfo;
    }

    protected function redirectResponse(string $path): RedirectResponse
    {
        return new RedirectResponse((string) $this->app->make(ResolverManagerInterface::class)->resolve([$path]));
    }

    protected function errorResponse(string $error): RedirectResponse
    {
        $this->markError($error);

        return $this->redirectResponse('/login/callback/discord/handle_error');
    }

    protected function successResponse(string $message): RedirectResponse
    {
        $this->markSuccess($message);

        return $this->redirectResponse('/login/callback/discord/handle_success');
    }
}
