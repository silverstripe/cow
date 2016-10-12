<?php

namespace SilverStripe\Cow\Steps\Release;

use Exception;
use SilverStripe\Cow\Commands\Command;
use SilverStripe\Cow\Model\Release\Version;
use SilverStripe\Cow\Steps\Step;
use SilverStripe\Cow\Utility\Composer;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Creates a new project
 */
class Wait extends Step
{
    protected $package = 'silverstripe/installer';

    protected $stability = 'dev';

    /**
     * Seconds to timeout error
     * Defaults to 15 minutes
     *
     * @var int
     */
    protected $timeout = 5400;

    /**
     * @var \SilverStripe\Cow\Model\Versions\Version
     */
    protected $version;

    /**
     * @param Command $command
     * @param \SilverStripe\Cow\Model\Versions\\SilverStripe\Cow\Model\Release\Version $version
     */
    public function __construct(Command $command, Version $version)
    {
        parent::__construct($command);
        $this->version = $version;
    }

    /**
     * Create a new project
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    public function run(InputInterface $input, OutputInterface $output)
    {
        $version = $this->version->getValue();
        $this->log($output, "Waiting for version {$version} to be available via packagist");
        $this->waitLoop($output);
        $this->log($output, "Version {$version} is now available");
    }

    protected function waitLoop(OutputInterface $output)
    {
        $start = time();
        $version = $this->version->getValue();
        while ($start + $this->timeout >= time()) {
            // todo - this line should get project name
            throw new Exception("todo");

            $versions = Composer::getLibraryVersions($this->getCommandRunner($output), $this->project);
            if (in_array($version, $versions)) {
                return;
            }
            // Wait
            $this->log($output, "Version {$version} not available; checking again in 20 seconds");

            // Progress bar for 20 seconds
            $progress = new ProgressBar($output, 20);
            $progress->start();
            for ($i = 0; $i < 20; $i++) {
                $progress->advance();
                sleep(1);
            }
            $progress->finish();
            $output->writeln('');
        }

        // Timeout
        throw new Exception(
            "Waiting for version {$version} to be available timed out after " . $this->timeout . " seconds"
        );
    }

    /**
     * Determine installable versions composer knows about and can install
     *
     * @param OutputInterface $output
     * @return array
     * @throws Exception
     */
    protected function getAvailableVersions(OutputInterface $output)
    {
        $error = "Could not parse available versions from command \"composer show {$this->package}\"";
        $output = $this->runCommand($output, array("composer", "show", $this->package, "--all"), $error);

        // Parse output
        if ($output && preg_match('/^versions\s*:\s*(?<versions>(\S.+\S))\s*$/m', $output, $matches)) {
            return preg_split('/\s*,\s*/', $matches['versions']);
        }

        throw new Exception($error);
    }

    public function getStepName()
    {
        return 'wait';
    }
}
