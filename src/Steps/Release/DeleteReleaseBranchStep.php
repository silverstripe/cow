<?php

namespace SilverStripe\Cow\Steps\Release;

use SilverStripe\Cow\Model\Release\LibraryRelease;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Delete the x.y-release branches
 */
class DeleteReleaseBranchStep extends ReleaseStep
{
    public function getStepName()
    {
        return 'deletereleasebranch';
    }

    public function run(InputInterface $input, OutputInterface $output)
    {
        $this->log($output, "Deleting release branch for all modules");

        $this->deleteBranchRecursive($output, $this->getReleasePlan());

        $this->log($output, "All modules processed");
    }

    /**
     * Merge a library and its children up to their major branches
     *
     * @param OutputInterface $output
     * @param LibraryRelease $releasePlanNode Node in release plan being released
     */
    protected function deleteBranchRecursive(OutputInterface $output, LibraryRelease $releasePlanNode)
    {
        $currentBranch = $releasePlanNode->getLibrary()->getBranch();
        $currentLibrary = $releasePlanNode->getLibrary()->getName();

        // Skip upgrade-only modules and anything not on an actual branch (e.g. using a pre-existing tag)
        if ($releasePlanNode->getLibrary()->isUpgradeOnly() || !$currentBranch) {
            return;
        }

        // Skip if it's not a proper release branch.
        if (!$releasePlanNode->isOnReleaseBranch()) {
            $this->log(
                $output,
                "Library $currentLibrary is on a non-release branch '$currentBranch'"
                    . "Please checkout the release branch and then run the command again.",
                'error'
            );
            die();
        }

        // Do the deletion recursively
        foreach ($releasePlanNode->getItems() as $item) {
            $this->deleteBranchRecursive($output, $item);
        }
        $this->deleteBranch($output, $releasePlanNode);
    }

    /**
     * Deletes the release branch for a given repo
     */
    protected function deleteBranch(OutputInterface $output, LibraryRelease $releasePlanNode)
    {
        $currentBranch = $releasePlanNode->getLibrary()->getBranch();
        $currentLibrary = $releasePlanNode->getLibrary()->getName();
        $this->log(
            $output,
            "Deleting branch <info>$currentBranch</info> from remote for <info>{$currentLibrary}</info>"
        );
        $releasePlanNode->getLibrary()->getRepository()->run('push', ['--delete', $currentBranch]);
    }
}
