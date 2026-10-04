<?php

namespace DiscordConnect\OAuth;

use OAuth\Common\Consumer\CredentialsInterface;
use OAuth\Common\Http\Client\ClientInterface;
use OAuth\Common\Http\Exception\TokenResponseException;
use OAuth\Common\Http\Uri\Uri;
use OAuth\Common\Http\Uri\UriInterface;
use OAuth\Common\Storage\TokenStorageInterface;
use OAuth\OAuth2\Service\AbstractService;
use OAuth\OAuth2\Token\StdOAuth2Token;

/**
 * Discord OAuth2 service for the OAuth library used by the core (lusitanian/oauth doesn't ship one).
 *
 * @see https://discord.com/developers/docs/topics/oauth2
 */
class DiscordService extends AbstractService
{
    /** Read the user ID, username and avatar (GET /users/@me) */
    const SCOPE_IDENTIFY = 'identify';

    /** Add the email address to GET /users/@me */
    const SCOPE_EMAIL = 'email';

    /** Allows the bot to add the user to a server (PUT /guilds/{guild.id}/members/{user.id}) */
    const SCOPE_GUILDS_JOIN = 'guilds.join';

    public function __construct(
        CredentialsInterface $credentials,
        ClientInterface $httpClient,
        TokenStorageInterface $storage,
        $scopes = [],
        ?UriInterface $baseApiUri = null
    ) {
        // Always send and verify the state parameter
        parent::__construct($credentials, $httpClient, $storage, $scopes, $baseApiUri ?? new Uri('https://discord.com/api/v10/'), true);
    }

    public function getAuthorizationEndpoint()
    {
        return new Uri('https://discord.com/oauth2/authorize');
    }

    public function getAccessTokenEndpoint()
    {
        return new Uri('https://discord.com/api/oauth2/token');
    }

    protected function getAuthorizationMethod()
    {
        return static::AUTHORIZATION_METHOD_HEADER_BEARER;
    }

    protected function parseAccessTokenResponse($responseBody)
    {
        $data = json_decode($responseBody, true);
        if (!is_array($data)) {
            throw new TokenResponseException('Unable to parse response.');
        }
        if (isset($data['error'])) {
            throw new TokenResponseException('Error in retrieving token: "' . ($data['error_description'] ?? $data['error']) . '"');
        }
        if (empty($data['access_token'])) {
            throw new TokenResponseException('No access token received.');
        }

        $token = new StdOAuth2Token();
        $token->setAccessToken($data['access_token']);
        if (isset($data['expires_in'])) {
            $token->setLifetime((int) $data['expires_in']);
        }
        if (isset($data['refresh_token'])) {
            $token->setRefreshToken($data['refresh_token']);
        }
        unset($data['access_token'], $data['expires_in'], $data['refresh_token']);
        $token->setExtraParams($data);

        return $token;
    }
}
