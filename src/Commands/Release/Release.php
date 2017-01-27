<?php

namespace SilverStripe\Cow\Commands\Release;

use SilverStripe\Cow\Commands\Command;
use SilverStripe\Cow\Model\ReleaseVersion;
use SilverStripe\Cow\Steps\Release\CreateBranch;
use SilverStripe\Cow\Steps\Release\CreateChangelog;
use SilverStripe\Cow\Steps\Release\CreateProject;
use SilverStripe\Cow\Steps\Release\RunTests;
use SilverStripe\Cow\Steps\Release\UpdateTranslations;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Process\Exception\InvalidArgumentException;

/**
 * Execute each release step in order to publish a new version
 *
 * @author dmooyman
 */
class Release extends Command
{
    protected $name = 'release';

    protected $description = 'Execute each release step in order to publish a new version';

    const BRANCH_AUTO = 'auto';

    protected function configureOptions()
    {
        $this
            ->addArgument('version', InputArgument::REQUIRED, 'Exact version tag to release this project as')
            ->addOption('from', 'f', InputOption::VALUE_REQUIRED, 'Version to generate changelog from')
            ->addOption('directory', 'd', InputOption::VALUE_REQUIRED, 'Optional directory to release project from')
            ->addOption('security', 's', InputOption::VALUE_NONE, 'Update git remotes to point to security project')
            ->addOption('branch', 'b', InputOption::VALUE_REQUIRED, 'Branch each module to this')
            ->addOption('branch-auto', 'a', InputOption::VALUE_NONE, 'Automatically branch to major.minor.patch')
            ->addOption('skip-tests', null, InputOption::VALUE_NONE, 'Skip the tests suite run when performing the release')
            ->addOption('repository', 'r', InputOption::VALUE_REQUIRED, 'Use a custom repository for the composer project');
    }

    protected function fire()
    {
        // Get arguments
        $version = $this->getInputVersion();
        $fromVersion = $this->getInputFromVersion($version);
        $directory = $this->getInputDirectory($version);
        $repo = $this->input->getOption('repository');
        $branch = $this->getInputBranch($version);

        // Make the directory
        $project = new CreateProject($this, $version, $directory, $repo);
        $project->run($this->input, $this->output);

        // Once the project is setup, we can extract the module list to publish
        $modules = $this->getReleaseModules($directory);

        // Change to the correct temp branch (if given)
        $branch = new CreateBranch($this, $directory, $branch, $modules);
        $branch->run($this->input, $this->output);

        // Update all translations
        $translate = new UpdateTranslations($this, $directory, $modules);
        $translate->run($this->input, $this->output);

        if (!$this->input->getOption('skip-tests')) {
            // Run tests
            $test = new RunTests($this, $directory);
            $test->run($this->input, $this->output);
        }

        // Generate changelog
        $changelogs = new CreateChangelog($this, $version, $fromVersion, $directory, $modules);
        $changelogs->run($this->input, $this->output);

        // Output completion
        $this->output->writeln("<info>Success!</info> Release has been updated.");
        $this->output->writeln(
            "Please check the changes made by this command, and run <info>cow release:publish</info>"
        );
    }

    /**
     * Get the version to release
     *
     * @return ReleaseVersion
     */
    protected function getInputVersion()
    {
        // Version
        $value = $this->input->getArgument('version');
        return new ReleaseVersion($value);
    }

    /**
     * Determine the branch name that should be used
     *
     * @param ReleaseVersion $version
     * @return string|null
     */
    protected function getInputBranch(ReleaseVersion $version)
    {
        $branch = $this->input->getOption('branch');
        if ($branch) {
            return $branch;
        }

        // If not explicitly specified, automatically select
        if ($this->input->getOption('branch-auto')) {
            return $version->getValueStable();
        }
        return null;
    }

    /**
     * Determine the 'from' version for generating changelogs
     *
     * @param ReleaseVersion $version
     * @return ReleaseVersion
     */
    protected function getInputFromVersion(ReleaseVersion $version)
    {
        $value = $this->input->getOption('from');
        if ($value) {
            return new ReleaseVersion($value);
        } else {
            return $version->getPriorVersion();
        }
    }

    /**
     * Get the directory the project is, or will be in
     *
     * @param ReleaseVersion $version
     * @return string
     */
    protected function getInputDirectory(ReleaseVersion $version)
    {
        $directory = $this->input->getOption('directory');
        if (!$directory) {
            $directory = $this->pickDirectory($version);
        }
        return $directory;
    }

    /**
     * Guess a directory to install/read the given version
     *
     * @param ReleaseVersion $version
     * @return string
     */
    protected function pickDirectory(ReleaseVersion $version)
    {
        $filename = DIRECTORY_SEPARATOR . 'release-' . $version->getValue();
        $cwd = getcwd();

        // Check if we are already in this directory
        if (strrpos($cwd, $filename) === strlen($cwd) - strlen($filename)) {
            return $cwd;
        }

        return $cwd . $filename;
    }

    /**
     * Determine if the release selected is a security one
     *
     * @return bool
     * @throws InvalidArgumentException
     */
    protected function getInputSecurity()
    {
        $security = $this->input->getOption('security');
        if ($security) {
            throw new InvalidArgumentException('--security flag not yet implemented');
        }
        return (bool)$security;
    }

    /**
     * Get modules to include in this release. Skips those not in the project's composer.json
     *
     * @param string $directory where the project is setup
     * @return array
     */
    protected function getReleaseModules($directory)
    {
        $path = realpath($directory);
        $composerPath = realpath($path . '/composer.json');
        if (empty($composerPath)) {
            throw new \InvalidArgumentException("Project not installed at \"{$path}\"");
        }
        $composer = json_decode(file_get_contents($composerPath), true);

        $modules = array('installer');
        foreach ($composer['require'] as $module => $version) {
            // Only include self.version modules
            if ($version !== 'self.version') {
                continue;
            }

            list($vendor, $module) = explode('/', $module);
            $modules[] = $module;
        }
        return $modules;
    }
}
