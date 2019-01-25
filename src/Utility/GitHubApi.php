<?php

namespace SilverStripe\Cow\Utility;

use Github\Client;
use RuntimeException;

/**
 * Returns an authenticated GitHub API client
 */
class GitHubApi
{
    /**
     * @var string
     */
    const TOKEN_ENV_VAR = 'GITHUB_ACCESS_TOKEN';

    /**
     * @var Client
     */
    protected $client;

    /**
     * Authenticates and returns a GitHub API client
     *
     * @return Client
     */
    public function getClient()
    {
        if (!$this->client) {
            // Handled here rather than constructor so that exceptions will be formatted by SymfonyStyle
            if (!getenv(self::TOKEN_ENV_VAR)) {
                throw new RuntimeException(self::TOKEN_ENV_VAR . ' environment variable is not defined!');
            }

            $this->client = new Client();
            $this->client->authenticate(getenv(self::TOKEN_ENV_VAR), null, Client::AUTH_HTTP_TOKEN);
        }
        return $this->client;
    }
}
