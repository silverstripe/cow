<?php

namespace SilverStripe\Cow\Tests\Command\GitHub;

use Github\Client;
use PHPUnit_Framework_TestCase;
use SilverStripe\Cow\Commands\GitHub\RateLimit;
use SilverStripe\Cow\Utility\GitHubApi;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class RateLimitTest extends PHPUnit_Framework_TestCase
{
    public function testExecute()
    {
        $mockGitHub = $this->getMockBuilder(GitHubApi::class)
            ->setMethods(['getClient'])
            ->getMock();

        $mockClient = $this->getMockBuilder(Client::class)
            ->setMethods(['rateLimit'])
            ->getMock();

        $rateLimitMock = $this->getMockBuilder(\Github\Api\RateLimit::class)
            ->disableOriginalConstructor()
            ->setMethods(['getRateLimits'])
            ->getMock();

        $rateLimitMock->expects($this->once())->method('getRateLimits')->willReturn([
            'resources' => [
                'core' => [
                    'limit' => 5000,
                    'remaining' => 4000,
                    'reset' => strtotime('+5 minutes'),
                ],
            ],
        ]);

        $mockClient->expects($this->once())->method('rateLimit')->willReturn($rateLimitMock);
        $mockGitHub->expects($this->once())->method('getClient')->willReturn($mockClient);

        $application = new Application();
        $application->add(new RateLimit($mockGitHub));

        $command = $application->find('github:ratelimit');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
        ]);

        $output = $commandTester->getDisplay();
        $this->assertContains('5000', $output, 'Contains limit');
        $this->assertContains('4000', $output, 'Contains remaining');
        $this->assertContains('5 mins', $output, 'Contains reset time');
    }
}
