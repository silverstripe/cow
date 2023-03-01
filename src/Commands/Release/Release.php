<?php

namespace SilverStripe\Cow\Commands\Release;

use InvalidArgumentException;
use SilverStripe\Cow\Commands\Command;
use SilverStripe\Cow\Model\Modules\Project;
use SilverStripe\Cow\Model\Release\Version;
use SilverStripe\Cow\Steps\Release\CreateChangelog;
use SilverStripe\Cow\Steps\Release\CreateProject;
use SilverStripe\Cow\Steps\Release\PlanRelease;
use SilverStripe\Cow\Steps\Release\RewriteReleaseBranches;
use SilverStripe\Cow\Steps\Release\RunTests;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
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
        $branchOptions = implode('|', array_keys(Branch::OPTIONS));
        $this
            ->addArgument('version', InputArgument::REQUIRED, 'Exact version tag to release this project as')
            ->addArgument('recipe', InputArgument::OPTIONAL, 'Recipe to release', 'silverstripe/installer')
            ->addOption('repository', 'r', InputOption::VALUE_REQUIRED, "Custom repository url")
            ->addOption('directory', 'd', InputOption::VALUE_REQUIRED, 'Optional directory to release project from')
            ->addOption(
                'skip-tests',
                null,
                InputOption::VALUE_NONE,
                'Skip the tests suite run when performing the release'
            )
            ->addOption(
                'skip-emulate-requirements',
                null,
                InputOption::VALUE_NONE,
                'Do not emulate php and extension requirements through composer config platform'
            )
            ->addOption(
                'skip-fetch-tags',
                null,
                InputOption::VALUE_NONE,
                'Skip fetching tags from origin'
            )
            ->addOption(
                'branching',
                'b',
                InputOption::VALUE_REQUIRED,
                "Branching strategy. One of [{$branchOptions}]"
            )
            ->addOption(
                'include-other-changes',
                null,
                InputOption::VALUE_NONE,
                'Include other changes in the changelog (default: false)'
            )
            ->addOption(
                'include-upgrade-only',
                null,
                InputOption::VALUE_NONE,
                'Include upgrade-only changes in the changelog (default: false)'
            )
            ->addOption(
                'changelog--use-legacy-format',
                null,
                InputOption::VALUE_NONE,
                'Use legacy changelog format, hardcoded in Changelog model'
            )
            ->addOption(
                'changelog--audit-mode',
                null,
                InputOption::VALUE_NONE,
                'Generate changelogs for security audit.'
                . ' Include every change, use audit template.'
                . '(implicitly activates include-upgrade-only and include-other-changes)'
            )
            ;
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
        $branching = $this->getBranching();

        // Build and confirm release plan
        $buildPlan = new PlanRelease($this, $project, $version, $branching, $this->progressBar);
        $buildPlan->run($this->input, $this->output);
        $releasePlan = $buildPlan->getReleasePlan();

        // Branch all modules properly
        $branchAlias = new RewriteReleaseBranches($this, $project, $releasePlan);
        $branchAlias->run($this->input, $this->output);

        // Run tests
        if (!$this->input->getOption('skip-tests')) {
            $test = new RunTests($this, $project);
            $test->run($this->input, $this->output);
        }

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
        // Check specified repository
        $repository = $this->input->getOption('repository');
        if ($repository) {
            return $repository;
        }

        // Check if repository was used during install
        // Prevents mistake publishing a project created with a repository
        $directory = $this->getInputDirectory();
        if (file_exists($directory . '/.cow.repository')) {
            return file_get_contents($directory . '/.cow.repository');
        }

        return null;
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

        $filename = DIRECTORY_SEPARATOR . 'release-' . str_replace('/', '_', $recipe) . '-' . $version->getValue();
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
        $project = new Project($directory);

        $fetchTags = !$this->input->getOption('skip-fetch-tags');
        $project->setFetchTags($fetchTags);

        return $project;
    }

    /**
     * Get branching strategy
     *
     * @return string Branching strategy, or null to inherit / default
     */
    protected function getBranching()
    {
        $branch = $this->input->getOption('branching');
        if ($branch && !array_key_exists($branch, Branch::OPTIONS)) {
            throw new InvalidArgumentException("Invalid branching option {$branch}");
        }
        return $branch;
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
        $binName = $this->getApplication()->getBinName();
        $command = $binName . ' release:publish ' . $version->getValue() . ' ' . $project->getName();
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

    /**
     * Whether to include all commits in the changelog. Will look at project level configuration as well as the
     * specific argument passed to the command.
     *
     * @return bool
     */
    public function getIncludeOtherChanges()
    {
        // If an argument was explicitly passed, use it (true)
        if ($this->input->getOption('include-other-changes') || $this->getChangelogAuditMode()) {
            return true;
        }

        // No argument was passed, fall back to project level configuration
        $projectConfig = $this->getProject()->getChangelogIncludeOtherChanges();
        if ($projectConfig !== null) {
            return $projectConfig;
        }

        // Default value
        return false;
    }

    /**
     * Whether to include upgrade-only changes.
     *
     * @return bool
     */
    public function getIncludeUpgradeOnly(): bool
    {
        return $this->input->getOption('include-upgrade-only') || $this->getChangelogAuditMode();
    }

    /**
     * Whether to generate changelogs for Security Audit
     *
     * @return bool
     */
    public function getChangelogAuditMode(): bool
    {
        return $this->input->getOption('changelog--audit-mode');
    }

    /**
     * Whether generated changelog should use the legacy format
     *
     * @return bool
     */
    public function getChangelogUseLegacyFormat(): bool
    {
        return $this->input->getOption('changelog--use-legacy-format');
    }
}
