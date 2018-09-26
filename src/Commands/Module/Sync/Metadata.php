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

                // Write the file
                file_put_contents($basePath . '/' . $baseFilename, $data);

                // Stage the file
                $process = new Process(['/usr/bin/env', 'git', 'add', $baseFilename, strtolower($baseFilename)]);
                $process->setWorkingDirectory($basePath);
                // We don't need to know if one of the two filenames didn't exist
                $process->disableOutput();
                $this->getHelper('process')->run($this->output, $process);

                // Get the staged changes
                $process = new Process(['/usr/bin/env', 'git', 'diff', '--staged']);
                $process->setWorkingDirectory($basePath);
                $process->run();
                $result = $process->getOutput();
                if (trim($result) === '') {
                    // No changes, skip
                    continue;
                }

                // Commit the changes
                $process = new Process(['/usr/bin/env', 'git', 'commit', '-m', 'Update ' . $baseFilename]);
                $process->setWorkingDirectory($basePath);
                $this->getHelper('process')->run($this->output, $process);

                // Push the changes
                $process = new Process(['/usr/bin/env', 'git', 'push']);
                $process->setWorkingDirectory($basePath);
                $this->getHelper('process')->run($this->output, $process);
            }
        }

        $this->output->writeln('<info>Done</info>');
    }
}
