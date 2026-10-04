<?php

namespace DiscordConnect\OAuth;

use Concrete\Core\Authentication\Type\OAuth\HttpClient;
use Concrete\Core\Config\Repository\Repository;
use Concrete\Core\Http\Request;
use Concrete\Core\Url\Resolver\Manager\ResolverManagerInterface;
use DiscordConnect\DiscordConfig;
use OAuth\Common\Consumer\Credentials;
use OAuth\Common\Storage\SymfonySession;
use Symfony\Component\HttpFoundation\Session\Session;

class DiscordServiceFactory
{
    /** @var Repository */
    protected $config;

    /** @var DiscordConfig */
    protected $discordConfig;

    /** @var Session */
    protected $session;

    /** @var ResolverManagerInterface */
    protected $urlResolver;

    /** @var Request */
    protected $request;

    /** @var HttpClient */
    protected $httpClient;

    public function __construct(Repository $config, DiscordConfig $discordConfig, Session $session, ResolverManagerInterface $urlResolver, Request $request, HttpClient $httpClient)
    {
        $this->config = $config;
        $this->discordConfig = $discordConfig;
        $this->session = $session;
        $this->urlResolver = $urlResolver;
        $this->request = $request;
        $this->httpClient = $httpClient;
    }

    public function createService(): DiscordService
    {
        $credentials = new Credentials(
            (string) $this->config->get('auth.discord.client_id', ''),
            (string) $this->config->get('auth.discord.client_secret', ''),
            $this->getCallbackUrl()
        );

        $scopes = [DiscordService::SCOPE_IDENTIFY, DiscordService::SCOPE_EMAIL];
        if ($this->discordConfig->isAutoJoin() && $this->discordConfig->getGuildId() !== null) {
            $scopes[] = DiscordService::SCOPE_GUILDS_JOIN;
        }

        return new DiscordService($credentials, $this->httpClient, new SymfonySession($this->session, false), $scopes);
    }

    /**
     * The redirect URI that has to be added to the Discord application. Used for logging in and for attaching accounts.
     */
    public function getCallbackUrl(): string
    {
        $callbackUrl = $this->urlResolver->resolve(['/ccm/system/authentication/oauth2/discord/callback']);
        if ($callbackUrl->getHost() == '') {
            $callbackUrl = $callbackUrl->setHost($this->request->getHost());
            $callbackUrl = $callbackUrl->setScheme($this->request->getScheme());
        }

        return (string) $callbackUrl;
    }
}
