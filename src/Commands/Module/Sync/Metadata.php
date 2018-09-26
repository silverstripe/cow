<?php

namespace SilverStripe\Cow\Commands\Module\Sync;

use SilverStripe\Cow\Commands\Module\AbstractSyncCommand;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Process\Process;

/**
 * This class synchronises metadata files to all supported module repositories, e.g. licenses, contributing
 * guides, editorconfig files etc
 */
class Metadata extends AbstractSyncCommand
{
    protected $name = 'module:sync:metadata';

    protected $description = 'Synchronizes metadata files across all supported repositories';

    /**
     * The files that will be sync'd to all repos
     *
     * @var string[]
     */
    const SYNC_FILES = [
        'templates/LICENSE.md',
    ];

    protected function configureOptions()
    {
        $this->addOption(
            'skip-update',
            null,
            InputOption::VALUE_NONE,
            'Skip fetching latest repository changes'
        );
    }

    protected function fire()
    {
        // Ensure that all repositories are available and up to date
        if (!$this->input->getOption('skip-update')) {
            $this->syncRepositories();
        }
        $repositories = $this->getSupportedModuleLoader()->getModules();

        /** @var QuestionHelper $questionHelper */
        $questionHelper = $this->getHelper('question');

        foreach (self::SYNC_FILES as $filename) {
            $data = $this->getSupportedModuleLoader()->getRemoteData($filename);
            $baseFilename = basename($filename);

            // Confirm diff before proceeding
            $this->output->writeln($data);
            $question = new ConfirmationQuestion(
                'Confirm file contents for <comment>' . $baseFilename . '</comment>? '
            );
            $confirm = $questionHelper->ask($this->input, $this->output, $question);

            if (!$confirm) {
                $this->output->writeln('<error>Skipping ' . $baseFilename . '</error>');
                continue;
            }

            foreach ($repositories as $repository) {
                // @todo implement check e.g. "sync_files": true
                if (substr($repository, 0, 12) !== 'silverstripe') {
                    continue;
                }
                $basePath = $this->getRepositoryPath($repository);

                $this->writeDataToFile($basePath . '/' . $baseFilename, $data);
                $this->stageFile($basePath, $baseFilename);
                if (!$this->hasChanges($basePath)) {
                    continue;
                }
                $this->commitChanges($basePath, $baseFilename);
                $this->pushChanges($basePath);
            }
        }

        $this->output->writeln('<info>Done</info>');
    }

    /**
     * Writes the given data to the given filename
     *
     * @param string $filename
     * @param string $data
     */
    protected function writeDataToFile($filename, $data)
    {
        file_put_contents($filename, $data);
    }

    /**
     * Adds the given filename to stage
     *
     * @param string $basePath
     * @param string $filename
     */
    protected function stageFile($basePath, $filename)
    {
        $process = new Process(['/usr/bin/env', 'git', 'add', $filename, strtolower($filename)]);
        $process->setWorkingDirectory($basePath);
        // We don't need to know if one of the two filenames didn't exist
        $process->disableOutput();
        $this->getHelper('process')->run($this->output, $process);
    }

    /**
     * Returns whether the given path has any staged changes in it
     *
     * @param string $basePath
     * @return bool
     */
    protected function hasChanges($basePath)
    {
        $process = new Process(['/usr/bin/env', 'git', 'diff', '--staged']);
        $process->setWorkingDirectory($basePath);
        $process->run();
        $result = $process->getOutput();
        return trim($result) !== '';
    }

    /**
     * Commit stages changes
     *
     * @param string $basePath
     * @param string $filename
     */
    protected function commitChanges($basePath, $filename)
    {
        $process = new Process(['/usr/bin/env', 'git', 'commit', '-m', 'Update ' . $filename]);
        $process->setWorkingDirectory($basePath);
        $this->getHelper('process')->run($this->output, $process);
    }

    /**
     * Pushes any new commits to origin
     *
     * @param string $basePath
     */
    protected function pushChanges($basePath)
    {
        $process = new Process(['/usr/bin/env', 'git', 'push']);
        $process->setWorkingDirectory($basePath);
        $this->getHelper('process')->run($this->output, $process);
    }
}
