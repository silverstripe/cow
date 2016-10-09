<?php

namespace SilverStripe\Cow\Steps\Release;

use SilverStripe\Cow\Model\Release\LibraryRelease;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PublishModules extends ReleaseStep
{

    public function getStepName()
    {
        return 'publish';
    }

    public function run(InputInterface $input, OutputInterface $output)
    {
        $this->log($output, "Running release of all modules");

        $this->releaseRecursive($output, $this->getReleasePlan());

        $this->log($output, "All releases published");
    }

    /**
     * Release a library
     *
     * @param OutputInterface $output
     * @param LibraryRelease $releasePlan The library to tag
     */
    protected function releaseRecursive(OutputInterface $output, LibraryRelease $releasePlan)
    {
        // Before releasing a version, make sure to tag all nested dependencies
        foreach ($releasePlan->getItems() as $item) {
            $this->releaseRecursive($output, $item);
        }

        // Release this library
        $name = $releasePlan->getLibrary()->getName();
        $versionName = $releasePlan->getVersion()->getValue();
        $this->log($output, "Releasing library <info>{$name}</info> at version <info>{$versionName}</info>");
    }
}