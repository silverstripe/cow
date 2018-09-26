<?php

namespace SilverStripe\Cow\Commands\Module;

use SilverStripe\Cow\Commands\Command;
use SilverStripe\Cow\Utility\SupportedModuleLoader;
use Symfony\Component\Process\Process;

abstract class AbstractSyncCommand extends Command
{
    /**
     * @var SupportedModuleLoader
     */
    protected $supportedModuleLoader;

    /**
     * @param SupportedModuleLoader $supportedModuleLoader
     */
    public function __construct(SupportedModuleLoader $supportedModuleLoader)
    {
        parent::__construct();

        $this->setSupportedModuleLoader($supportedModuleLoader);
    }

    /**
     * Either clones or pulls a shallow cloned copy of each of the supported modules
     */
    protected function syncRepositories()
    {
        $repositories = $this->getSupportedModuleLoader()->getModules();
        $baseDir = $this->getBaseDir();
        $this->output->writeln('Temporary directory: <comment>' . $baseDir . '</comment>');

        foreach ($repositories as $repository) {
            $repositoryHash = sha1($repository);

            // Temporary: only process SilverStripe repos
            // @todo should this be part of the module configuration? e.g. "sync_files": true
            if (substr($repository, 0, 12) !== 'silverstripe') {
                $this->output->writeln('<error>Skipping ' . $repository . '</error>');
                continue;
            }

            if ($this->tempFolderExists($baseDir . $repositoryHash)) {
                $this->output->writeln('Updating <comment>' . $repository . '</comment>...');
                $this->updateRepository($repository);
            } else {
                $this->output->writeln('Cloning <comment>' . $repository . '</comment>...');
                $this->cloneRepository($repository);
            }
        }

        $this->output->writeln('<info>All repositories updated.</info>');
    }

    /**
     * Returns the base directory for storing Git repositories. Will create it if it doesn't exist yet.
     *
     * @return string
     */
    protected function getBaseDir()
    {
        $baseDir = __DIR__ . '/../../../temp/';
        if (!is_dir($baseDir)) {
            mkdir($baseDir);
        }
        return realpath($baseDir) . '/';
    }

    /**
     * @param string $folder
     * @return bool
     */
    protected function tempFolderExists($folder)
    {
        return is_dir($folder);
    }

    /**
     * Updates an existing Git repository
     *
     * @param string $repository
     */
    protected function updateRepository($repository)
    {
        $process = new Process([
            '/usr/bin/env',
            'git',
            'pull',
        ]);
        $process->setWorkingDirectory($this->getRepositoryPath($repository));

        $this->getHelper('process')->run($this->output, $process);
    }

    /**
     * Clones a Git repository
     *
     * @param string $repository
     */
    protected function cloneRepository($repository)
    {
        $process = new Process([
            '/usr/bin/env',
            'git',
            'clone',
            '--depth',
            '1',
            $this->getRepositoryUrl($repository),
            $this->getRepositoryPath($repository),
        ]);

        $this->getHelper('process')->run($this->output, $process);
    }

    /**
     * @param string $repository
     * @return string
     */
    protected function getRepositoryPath($repository)
    {
        return $this->getBaseDir() . $this->getRepositoryHash($repository);
    }

    /**
     * Returns the GitHub repository URL. Uses SSH protocol.
     *
     * @param string $repository
     * @return string
     */
    protected function getRepositoryUrl($repository)
    {
        return 'git@github.com:' . $repository;
    }

    /**
     * @param string $repository
     * @return string
     */
    protected function getRepositoryHash($repository)
    {
        return sha1($repository);
    }

    /**
     * @param SupportedModuleLoader $supportedModuleLoader
     * @return $this
     */
    public function setSupportedModuleLoader(SupportedModuleLoader $supportedModuleLoader)
    {
        $this->supportedModuleLoader = $supportedModuleLoader;
        return $this;
    }

    /**
     * @return SupportedModuleLoader
     */
    public function getSupportedModuleLoader()
    {
        return $this->supportedModuleLoader;
    }
}
