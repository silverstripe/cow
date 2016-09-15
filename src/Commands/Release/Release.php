<?php

namespace SilverStripe\Cow\Commands\Release;

use Exception;
use SilverStripe\Cow\Commands\Command;
use SilverStripe\Cow\Model\Modules\Project;
use SilverStripe\Cow\Model\Release\Version;
use SilverStripe\Cow\Steps\Release\CreateProject;
use SilverStripe\Cow\Steps\Release\PlanRelease;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use InvalidArgumentException;

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
            ->addArgument('recipe', InputArgument::OPTIONAL, 'Recipe to release', 'silverstripe/installer')
            ->addOption('from', 'f', InputOption::VALUE_REQUIRED, 'Version to generate changelog from')
            ->addOption('directory', 'd', InputOption::VALUE_REQUIRED, 'Optional directory to release project from')
            ->addOption('security', 's', InputOption::VALUE_NONE, 'Update git remotes to point to security project')
            ->addOption('branch', 'b', InputOption::VALUE_REQUIRED, 'Branch each module to this')
            ->addOption('branch-auto', 'a', InputOption::VALUE_NONE, 'Automatically branch to major.minor.patch');
    }

    protected function fire()
    {
        // Get arguments
        $version = $this->getInputVersion();
        $recipe = $this->getInputRecipe();
        $directory = $this->getInputDirectory();
        $branch = $this->getInputBranch();

        // Make the directory
        $createProject = new CreateProject($this, $version, $recipe, $directory);
        $createProject->run($this->input, $this->output);
        $project = $this->getProject();

        // Build and confirm release plan
        $buildPlan = new PlanRelease($this, $project, $version);
        $buildPlan->run($this->input, $this->output);

        // Change to the correct temp branch (if given)
        /*
        $branch = new CreateBranch($this, $project, $branch);
        $branch->run($this->input, $this->output);
        */

        /*
        // Update all translations
        $translate = new UpdateTranslations($this, $project);
        $translate->run($this->input, $this->output);

        // Run tests
        $test = new RunTests($this, $project);
        $test->run($this->input, $this->output);

        // Generate changelog
        $changelogs = new CreateChangelog($this, $project, $version, $fromVersion);
        $changelogs->run($this->input, $this->output);

        // Output completion
        $this->output->writeln("<info>Success!</info> Release has been updated.");
        $this->output->writeln(
            "Please check the changes made by this command, and run <info>cow release:publish</info>"
        );
        */
    }

    /**
     * Get the version to release
     *
     * @return \SilverStripe\Cow\Model\Versions\\SilverStripe\Cow\Model\\SilverStripe\Cow\Model\Release\Version
     */
    protected function getInputVersion()
    {
        // Version
        $value = $this->input->getArgument('version');
        return new Version($value);
    }

    /**
     * Determine the branch name that should be used
     *
     * @return string|null
     */
    protected function getInputBranch()
    {
        $branch = $this->input->getOption('branch');
        if ($branch) {
            return $branch;
        }

        // If not explicitly specified, automatically select
        if ($this->input->getOption('branch-auto')) {
            $version = $this->getInputVersion();
            return $version->getValueStable();
        }
        return null;
    }

    /**
     * Get the directory the project is, or will be in
     *
     * @return string
     */
    protected function getInputDirectory()
    {
        $directory = $this->input->getOption('directory');
        if (!$directory) {
            $directory = $this->pickDirectory();
        }
        return $directory;
    }

    /**
     * Guess a directory to install/read the given version
     *
     * @return string
     */
    protected function pickDirectory()
    {
        $version = $this->getInputVersion();
        $recipe = $this->getInputRecipe();

        $filename = DIRECTORY_SEPARATOR . 'release-' . str_replace('/', '_', $recipe) . '-'. $version->getValue();
        $cwd = getcwd();

        // Check if we are already in this directory
        if (strrpos($cwd, $filename) === strlen($cwd) - strlen($filename)) {
            return $cwd;
        }

        return $cwd . $filename;
    }

    /**
     * Gets recipe name to release
     *
     * @return string
     */
    protected function getInputRecipe()
    {
        $recipe = $this->input->getArgument('recipe');
        if (!preg_match('#(\w+)/(\w+)#', $recipe)) {
            throw new InvalidArgumentException("Invalid recipe composer name $recipe");
        }
        return $recipe;
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
     * Get installed project
     *
     * @return Project
     */
    protected function getProject()
    {
        $directory = $this->getInputDirectory();
        return new Project($directory);
    }


}
