<?php

namespace SilverStripe\Cow\Steps\Release;

use Exception;
use SilverStripe\Cow\Commands\Command;
use SilverStripe\Cow\Model\Modules\Project;
use SilverStripe\Cow\Model\Release\Version;
use SilverStripe\Cow\Steps\Step;
use SilverStripe\Cow\Utility\Composer;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Exception\InvalidArgumentException;

/**
 * Creates a new project
 */
class CreateProject extends Step
{
    /**
     * Recipe name to create
     *
     * @var string
     */
    protected $recipe = null;

    /**
     * @var Version
     */
    protected $version;

    /**
     * @var string
     */
    protected $directory;

    /**
     * Custom composer repository
     *
     * @var string
     */
    protected $repository;

    /**
     *
     * @param Command $command
     * @param Version $version
     * @param string $recipe
     * @param string $directory
     * @param string $repository Custom composer repository for this install
     */
    public function __construct(Command $command, Version $version, $recipe, $directory = '.', $repository = null)
    {
        parent::__construct($command);
        $this->setRecipe($recipe);
        $this->setVersion($version);
        $this->setDirectory($directory ?: '.');
        $this->setRepository($repository);
    }

    /**
     * Create a new project
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @throws Exception
     */
    public function run(InputInterface $input, OutputInterface $output)
    {
        // Check if output directory already exists
        if (Project::isProjectPath($this->directory)) {
            $this->log($output, "Project already exists in target directory. Skipping project creation", "error");
            return;
        }

        // Pick and install this version
        $version = $this->getBestVersion($output);
        $this->installVersion($output, $version);

        // Validate result
        if (!Project::isProjectPath($this->directory)) {
            throw new Exception("Could not create project");
        }

        // Revert composer / recipe-core changes to composer.json
        $library = $this->getProject();
        $fetchTags = !$input->getOption('skip-fetch-tags');
        $library->setFetchTags($fetchTags);

        $path = $library->getComposerPath();
        $repo = $library->getRepository();
        $status = $repo->run("status", [$path]);
        if (stripos($status, 'Changes not staged for commit:')) {
            $this->log($output, "Reverting installer changes to composer.json");
            $repo->run("checkout", ["--", $path]);
        }

        // If using custom repository, write `.cow.repository` file for later
        if ($repo) {
            file_put_contents($repo->getPath() . '/.cow.repository', $this->getRepository());
        }

        // Success
        $this->log($output, "Project successfully created!");
    }

    /**
     * @return Project
     */
    public function getProject()
    {
        return new Project($this->directory);
    }

    /**
     * Install a given version
     *
     * @param OutputInterface $output
     * @param string $installVersion Composer version to install
     */
    protected function installVersion(OutputInterface $output, $installVersion)
    {
        $this->log($output, "Installing version <info>{$installVersion}</info> in <info>{$this->directory}</info>");

        $runner = $this->getCommandRunner($output);
        $recipe = $this->getRecipe();
        $directory = $this->getDirectory();
        $repository = $this->getRepository();

        Composer::createProject($runner, $recipe, $directory, $installVersion, $repository);
        Composer::update($runner, $directory, $repository);
    }

    /**
     * Get best version to install
     *
     * @param OutputInterface $output
     * @return string
     * @throws InvalidArgumentException
     */
    protected function getBestVersion(OutputInterface $output)
    {
        $this->log($output, 'Determining best version to install');

        // Find all versions for the given recipe
        $available = Composer::getLibraryVersions($this->getCommandRunner($output), $this->getRecipe());

        // Choose based on available and preference
        $versions = $this->getVersion()->getComposerVersions();
        foreach ($versions as $version) {
            if (in_array($version, $available)) {
                return $version;
            }
        }

        throw new InvalidArgumentException("Could not install project from version ".$this->version->getValue());
    }

    public function getStepName()
    {
        return 'create project';
    }

    /**
     * @return string
     */
    public function getRecipe()
    {
        return $this->recipe;
    }

    /**
     * @param string $recipe
     * @return string
     */
    public function setRecipe($recipe)
    {
        $this->recipe = $recipe;
        return $this;
    }

    /**
     * @param string $directory
     * @return CreateProject
     */
    public function setDirectory($directory)
    {
        $this->directory = $directory;
        return $this;
    }

    /**
     * @return string
     */
    public function getDirectory()
    {
        return $this->directory;
    }

    /**
     * @param Version $version
     * @return $this
     */
    public function setVersion($version)
    {
        $this->version = $version;
        return $this;
    }

    /**
     * @return Version
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * Get custom composer repository
     *
     * @return string
     */
    public function getRepository()
    {
        return $this->repository;
    }

    /**
     * @param string $repository
     * @return $this
     */
    protected function setRepository($repository)
    {
        $this->repository = $repository;
        return $this;
    }
}
