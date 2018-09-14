<?php

namespace SilverStripe\Cow\Commands\GitHub;

use DateTime;
use SilverStripe\Cow\Commands\Command;
use SilverStripe\Cow\Utility\GitHubApi;

/**
 * Helper command to show the current status of the GitHub API rate limit
 */
class RateLimit extends Command
{
    protected $name = 'github:ratelimit';

    protected $description = 'Shows the current status of the GitHub API rate limiting';

    /**
     * @var GitHubApi
     */
    protected $github;

    /**
     * @param GitHubApi $github
     */
    public function __construct(GitHubApi $github)
    {
        parent::__construct();

        $this->github = $github;
    }

    protected function configureOptions()
    {
        // noop
    }

    protected function fire()
    {
        /** @var \Github\Api\RateLimit $rateLimitApi */
        $rateLimitApi = $this->github->getClient()->rateLimit();

        $result = $rateLimitApi->getRateLimits();

        if (empty($result['resources']['core'])) {
            $this->output->writeln('<error>Failed to get rate limiting data!</error>');
        }
        $data = $result['resources']['core'];

        $this->output->writeln('Limit: <comment>' . $data['limit'] . '</comment>');

        $remainingFormat = $data['remaining'] > 0 ? 'info' : 'error';
        $this->output->writeln(
            'Remaining: <' . $remainingFormat . '>' . $data['remaining'] . '</' . $remainingFormat . '>'
        );

        $now = new DateTime();
        $resetDate = new DateTime();
        $resetDate->setTimestamp($data['reset']);
        $this->output->writeln('Resets in: <comment>' . $resetDate->diff($now)->i . ' mins</comment>');
    }
}
