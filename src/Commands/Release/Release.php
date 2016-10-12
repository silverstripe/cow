<?php

namespace SilverStripe\Cow\Commands\Release;

use SilverStripe\Cow\Commands\Command;
use SilverStripe\Cow\Model\Modules\Project;
use SilverStripe\Cow\Model\Release\Version;
use SilverStripe\Cow\Steps\Release\RewriteReleaseBranches;
use SilverStripe\Cow\Steps\Release\CreateChangelog;
use SilverStripe\Cow\Steps\Release\CreateProject;
use SilverStripe\Cow\Steps\Release\PlanRelease;
use SilverStripe\Cow\Steps\Release\RunTests;
use SilverStripe\Cow\Steps\Release\UpdateTranslations;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use InvalidArgumentException;
use Symfony\Component\Console\Output\Output;

/**
 * Execute each release step in order to publish a new version
 */
class Release extends Command
{
    protected $name = 'release';

    protected $description = 'Execute each release step in order to publish a new version';

    protected function configureOptions()
    {
        $this
            ->addArgument('version', InputArgument::REQUIRED, 'Exact version tag to release this project as')
            ->addArgument('recipe', InputArgument::OPTIONAL, 'Recipe to release', 'silverstripe/installer')
            ->addOption('repository', "r", InputOption::VALUE_REQUIRED, "Custom repository url")
            ->addOption('directory', 'd', InputOption::VALUE_REQUIRED, 'Optional directory to release project from');
    }

    protected function fire()
    {
        // Get arguments
        $version = $this->getInputVersion();
        $recipe = $this->getInputRecipe();
        $directory = $this->getInputDirectory();
        $repository = $this->getInputRepository();

        // Make the directory
        $createProject = new CreateProject($this, $version, $recipe, $directory, $repository);
        $createProject->run($this->input, $this->output);
        $project = $this->getProject();

        // Build and confirm release plan
        $buildPlan = new PlanRelease($this, $project, $version);
        $buildPlan->run($this->input, $this->output);
        $releasePlan = $buildPlan->getReleasePlan();

        // Branch all modules properly
        $branchAlias = new RewriteReleaseBranches($this, $project, $releasePlan);
        $branchAlias->run($this->input, $this->output);

        // Update all translations
        $translate = new UpdateTranslations($this, $project, $releasePlan);
        $translate->run($this->input, $this->output);

        // Run tests
        $test = new RunTests($this, $project);
        $test->run($this->input, $this->output);

        // Generate changelog
        $changelogs = new CreateChangelog($this, $project, $releasePlan);
        $changelogs->run($this->input, $this->output);



        // Output completion
        $this->output->writeln("<info>Success!</info> Release has been updated.");
        $command = $this->getPublishCommand($version, $project);
        $this->output->writeln(
            "Please check the changes made by this command, and run <info>{$command}</info>"
        );
    }

    /**
     * Get the version to release
     *
     * @return Version
     */
    protected function getInputVersion()
    {
        // Version
        $value = $this->input->getArgument('version');
        return new Version($value);
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
     * Get custom repository
     *
     * @return string
     */
    protected function getInputRepository()
    {
        return $this->input->getOption('repository');
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
     * Get installed project
     *
     * @return Project
     */
    protected function getProject()
    {
        $directory = $this->getInputDirectory();
        return new Project($directory);
    }

    /**
     * Get command to suggest to publish this release
     *
     * @param Version $version
     * @param Project $project
     * @return string Command name
     */
    protected function getPublishCommand($version, $project)
    {
        $command = 'cow release:publish ' . $version->getValue() . ' ' . $project->getName();
        switch ($this->output->getVerbosity()) {
            case Output::VERBOSITY_DEBUG:
                $command .= ' -vvv';
                break;
            case Output::VERBOSITY_VERY_VERBOSE:
                $command .= ' -vv';
                break;
            case Output::VERBOSITY_VERBOSE:
                $command .= ' -v';
                break;
        }
        return $command;
    }
}
