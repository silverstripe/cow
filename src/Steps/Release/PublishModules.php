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

        // First step: Ensure that dev-branch dependencies are current.
        // E.g. 3.2.x-dev dependency with a tag 3.3.0 needs to be bumped to 3.3.x-dev
        $this->incrementDevDependencies($output, $releasePlan);
    }

    /**
     * Increment any dependencies on x-dev versions that need updating
     *
     * @param OutputInterface $output
     * @param LibraryRelease $releasePlan
     */
    protected function incrementDevDependencies(OutputInterface $output, LibraryRelease $releasePlan) {
        $originalData = $composerData = $releasePlan->getLibrary()->getComposerData();
        foreach ($releasePlan->getItems() as $item) {
            $childName = $item->getLibrary()->getName();
            $childVersion = $item->getVersion();
            $childConstraint = $releasePlan->getLibrary()->getChildConstraint($childName, $releasePlan->getVersion());

            // Check if this version matches
            if ($childConstraint->matchesVersion($childVersion)) {
                continue;
            }

            // Check if this constraint supports rewriting
            $newConstraint = $childConstraint->rewriteToSupport($childVersion);

            // @todo
        }

    }
}