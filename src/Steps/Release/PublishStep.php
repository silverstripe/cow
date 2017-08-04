<?php

namespace SilverStripe\Cow\Steps\Release;

use SilverStripe\Cow\Model\Release\Archive;
use Symfony\Component\Console\Output\OutputInterface;

abstract class PublishStep extends ReleaseStep
{
    /**
     * Get all archive files to publish
     *
     * @param OutputInterface $output
     * @return Archive[] List of archives to generate, key is recipe name
     */
    protected function getArchives(OutputInterface $output)
    {
        $archives = [];
        foreach ($this->getProject()->getArchives() as $archive) {
            // Get release from release plan
            $release = $this->getReleasePlan()->getItem($archive['recipe']);
            if ($release) {
                $archives[$archive['recipe']] = new Archive($release, $archive['files']);
            } else {
                $this->log(
                    $output,
                    "<error>Warning: Archive recipe {$archive['recipe']} is not a part of this release</error>"
                );
            }
        }
        return $archives;
    }
}