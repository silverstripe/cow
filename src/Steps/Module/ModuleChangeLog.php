<?php

namespace SilverStripe\Cow\Steps\Module;

use Exception;
use SilverStripe\Cow\Model\ChangeLog;
use SilverStripe\Cow\Steps\ChangeLogStep;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Creates a new changelog, but will ask you for the version for each module to generate diffs from
 */
class ModuleChangeLog extends ChangeLogStep
{
    
    protected $title = null;
    
    public function run(InputInterface $input, OutputInterface $output)
    {
        $this->log($output, "Generating changelog content");

        // Generate changelog content
        $changelog = new ChangeLog($this->getModules());
        $content = $changelog->getMarkdown($output, $this);

        // Now we need to merge this content with the file, or otherwise create it
        $path = $this->getChangelogPath();
        $this->writeChangelog($output, $content, $path);

        $this->log($output, "Changelog successfully saved to <info>$path</info>!");
    }

    /**
     * Get full path to this changelog
     *
     * @return string
     */
    protected function getChangelogPath()
    {
        return $this->getProject()->getDirectory() . DIRECTORY_SEPARATOR . 'changelog.md';
    }


    protected function getChangelogTitle(OutputInterface $output) {
        if($this->title) {
            return $this->title;
        }
        $dialog = $this->getDialogHelper();
        $this->title = $dialog->ask(
            $output,
            'Enter a title for this changelog',
            'changelog'
        );
        return $this->title;
    }
}
