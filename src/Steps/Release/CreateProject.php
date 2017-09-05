<?php

namespace SilverStripe\Cow\Steps\Release;

use Exception;
use SilverStripe\Cow\Model\Project;
use SilverStripe\Cow\Model\ReleaseVersion;
use SilverStripe\Cow\Steps\Step;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Exception\InvalidArgumentException;

/**
 * Creates a new project
 */
class CreateProject extends Step
{
    protected $package = 'silverstripe/installer';

    protected $stability = 'dev';

    /**
     * @var ReleaseVersion
     */
    protected $version;

    /**
     * @var string
     */
    protected $directory;

    /**
     * @var string
     */
    protected $repo;

    /**
     *
     * @param Command $command
     * @param array $version
     * @param string $directory
     * @param string $repo
     */
    public function __construct(Command $command, ReleaseVersion $version, $directory = '.', $repo = null)
    {
        parent::__construct($command);

        $this->version = $version;
        $this->directory = $directory ?: '.';
        $this->repo = $repo;
    }


    /**
     * Create a new project
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    public function run(InputInterface $input, OutputInterface $output)
    {
        if ($this->repo) {
            $this->log($output, "Installing with custom repo: <info>{$this->repo}</info>");
        }
        // Check if output directory already exists
        if (Project::existsIn($this->directory)) {
            $this->log($output, "Project already exists in target directory. Skipping project creation", "error");
            return;
        }

        // Pick and install this version
        $version = $this->getBestVersion($output);
        $this->installVersion($output, $version);
        $this->log($output, "Project successfully created!");
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

    /**
     * Install a given version
     *
     * @param OutputInterface $output
     * @param string $version
     */
    protected function installVersion(OutputInterface $output, $version)
    {
        $this->log($output, "Installing version <info>{$version}</info> in <info>{$this->directory}</info>");
        $command = array(
            "composer", "create-project", "--ignore-platform-reqs", "--prefer-source", "--keep-vcs", $this->package, $this->directory, $version
        );
        if ($this->repo) {
            $this->log($output, "Using custom repo <info>{$this->repo}</info>");
            $command[] = sprintf("--repository=%s", $this->repo);
            $command[] = '--no-install';
        }
        $this->runCommand($output, $command, "Could not create project with version {$version}");
        if ($this->repo) {
            $repo = $this->repo;
            if (file_exists($repo)) {
                $repo = 'file://' . $repo;
            }
            $this->runCommand($output, array(
                'composer', 'config', 'repositories.repo', 'composer', $repo, '-d', $this->directory
            ));
            $this->runCommand($output, array(
                'composer', 'update', '--ignore-platform-reqs', '--prefer-source', '-d', $this->directory
            ));
        }
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

        // Determine best version to install
        $available = $this->getAvailableVersions($output);
        $versions = $this->version->getComposerVersions();

        // Choose based on available and preference
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
}
