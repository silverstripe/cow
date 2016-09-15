<?php

namespace SilverStripe\Cow\Commands\Module;

use SilverStripe\Cow\Commands\Command;
use SilverStripe\Cow\Model\Release\Version;
use SilverStripe\Cow\Steps\Module\TagAnnotatedModule;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

/**
 * Does an annoted module tag and realease.
 */
class Tag extends Command
{
    protected $name = 'module:tag';

    protected $description = 'Tag a module and push to github with annotated changelog';

    protected function configureOptions()
    {
        $this->addArgument('module', InputArgument::REQUIRED, 'Module name to release');
        $this->addArgument('version', InputArgument::REQUIRED, 'Version tag');
        $this->addOption('from', 'f', InputOption::VALUE_REQUIRED, 'Version to generate changelog from');
        $this->addOption('directory', 'd', InputOption::VALUE_REQUIRED, 'Project root directory');
        $this->addOption('message', 'm', InputOption::VALUE_REQUIRED, 'Release title to display on GitHub releases page');
    }

    /**
     * Defers to the subclass functionality.
     */
    protected function fire()
    {
        $version = $this->getInputVersion();
        $module = $this->getInputModule();
        $directory = $this->getInputDirectory();
        $fromVersion = $this->getInputFromVersion($version);
        $message = $this->getInputMessage();

        $step = new TagAnnotatedModule($this, $version, $fromVersion, $directory, $module, $message);
        $step->run($this->input, $this->output);
    }


    /**
     * Determine the 'from' version for generating changelogs
     *
     * @param \SilverStripe\Cow\Model\Release\Version $version
     * @return \SilverStripe\Cow\Model\\SilverStripe\Cow\Model\Release\Version
     */
    protected function getInputFromVersion(Version $version)
    {
        $value = $this->input->getOption('from');
        if ($value) {
            return new Version($value);
        } else {
            return $version->getPriorVersion();
        }
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
     * Get the directory the project is, or will be in
     *
     * @return string
     */
    protected function getInputDirectory()
    {
        $directory = $this->input->getOption('directory');
        if (!$directory) {
            $directory = getcwd();
        }
        return $directory;
    }

    /**
     * Module name to tag
     *
     * @return string
     */
    protected function getInputModule()
    {
        return $this->input->getArgument('module');
    }

    /**
     * Message title to use when tagging the release
     *
     * @return string
     */
    protected function getInputMessage()
    {
        return $this->input->getOption('message');
    }
}
