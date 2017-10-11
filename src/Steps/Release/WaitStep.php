<?php

namespace SilverStripe\Cow\Steps\Release;

use Exception;
use SilverStripe\Cow\Model\Release\Archive;
use SilverStripe\Cow\Utility\Composer;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Creates a new project
 */
class WaitStep extends ReleaseStep
{
    protected $stability = 'dev';

    /**
     * Seconds to timeout error
     * Defaults to 15 minutes
     *
     * @var int
     */
    protected $timeout = 5400;

    /**
     * Seconds to wait between attempts
     *
     * @var int
     */
    protected $wait = 20;

    /**
     * Create a new project
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    public function run(InputInterface $input, OutputInterface $output)
    {
        // Get recipes and their versions to wait for
        $recipes = $this->getArchives($output);
        if (empty($recipes)) {
            $this->log($output, "No recipes configured for archive");
            return;
        };
        $count = count($recipes);
        $this->log($output, "Waiting for {$count} recipes to be available via packagist");
        $this->waitLoop($output, $recipes);
        $this->log($output, "All recipes are now available");
    }

    /**
     * @param OutputInterface $output
     * @param Archive[] $archives List of packages to wait for
     * @throws Exception
     */
    protected function waitLoop(OutputInterface $output, array $archives)
    {
        $start = time();
        while (true) {
            // Check all remaining recipes
            foreach ($archives as $name => $archive) {
                $version = $archive->getRelease()->getVersion()->getValue();
                $this->log(
                    $output,
                    "Waiting for recipe <info>$name</info> (<comment>$version</comment>) to be available"
                );
                $versions = Composer::getLibraryVersions($this->getCommandRunner($output), $name);
                if (in_array($version, $versions)) {
                    unset($archives[$name]);
                }
            }

            // Check if we have any recipes still to wait for
            $count = count($archives);
            if ($count === 0) {
                return;
            }

            // Check if waiting would push us over the time limit
            if (($start + $this->timeout) < (time() + $this->wait)) {
                throw new Exception(
                    "Waiting for {$count} recipes to be available timed out after {$this->timeout} seconds"
                );
            }

            // Wait with progress bar
            $this->log($output, "Versions for {$count} recipes not available; checking again in {$this->wait} seconds");
            $progress = new ProgressBar($output, $this->wait);
            $progress->start();
            for ($i = 0; $i < 20; $i++) {
                $progress->advance();
                sleep(1);
            }
            $progress->finish();
            $output->writeln('');
        }
    }

    public function getStepName()
    {
        return 'wait';
    }
}
