<?php

namespace SilverStripe\Cow\Steps\Release;

use Gitonomy\Git\Exception\ProcessException;
use SilverStripe\Cow\Model\Release\LibraryRelease;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Merge up released commits to the major branch and optionally remove x.y-release branch.
 * E.g. if you just released 4.12.0, this will merge 4.12-release up to 4.12, and then 4.12
 * up to 4.
 */
class MergeUpRelease extends ReleaseStep
{
    public function getStepName()
    {
        return 'mergeup';
    }

    public function run(InputInterface $input, OutputInterface $output)
    {
        $this->log($output, "Merging up all modules");

        $this->mergeRecursive(
            $output,
            $this->getReleasePlan(),
            !$input->getOption('no-push')
        );

        $this->log($output, "All modules merged up");
    }

    /**
     * Merge a library and its children up to their major branches
     *
     * @param OutputInterface $output
     * @param LibraryRelease $releasePlanNode Node in release plan being released
     */
    protected function mergeRecursive(
        OutputInterface $output,
        LibraryRelease $releasePlanNode,
        bool $pushToRemote
    ) {
        $currentBranch = $releasePlanNode->getLibrary()->getBranch();

        // Skip upgrade-only modules and anything not on an actual branch (e.g. using a pre-existing tag)
        if ($releasePlanNode->getLibrary()->isUpgradeOnly() || !$currentBranch) {
            return;
        }
        $currentLibrary = $releasePlanNode->getLibrary()->getName();

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

        // Do the merge up recursively
        foreach ($releasePlanNode->getItems() as $item) {
            $this->mergeRecursive($output, $item, $pushToRemote);
        }
        $this->mergeLibrary($output, $releasePlanNode, $pushToRemote);
    }

    /**
     * Performs a merge up for a single library
     *
     * @param OutputInterface $output
     * @param LibraryRelease $releasePlanNode Node in release plan being released
     */
    protected function mergeLibrary(OutputInterface $output, LibraryRelease $releasePlanNode, bool $pushToRemote)
    {
        $library = $releasePlanNode->getLibrary();
        $releaseBranch = $library->getBranch();
        $minorBranch = str_replace(LibraryRelease::RELEASE_BRANCH_SUFFIX, '', $releaseBranch);
        $majorBranch = $releasePlanNode->getVersion()->getMajor();

        // Merge up to minor branch, then to major branch
        $this->mergeUp($output, $releasePlanNode, $releaseBranch, $releaseBranch, $minorBranch, $pushToRemote, false);
        $this->mergeUp($output, $releasePlanNode, $releaseBranch, $minorBranch, $majorBranch, $pushToRemote, true);

        // Always check out the release branch at the end
        $library->checkout($output, $releaseBranch);
    }

    /**
     * Merge up from one branch to another, setting the appropriate constraints for released dependencies as we go.
     */
    protected function mergeUp(
        OutputInterface $output,
        LibraryRelease $releasePlanNode,
        string $releaseBranchName,
        string $mergeFrom,
        string $mergeInto,
        bool $pushToRemote,
        bool $isMajorBranch
    ) {
        $library = $releasePlanNode->getLibrary();
        $name = $library->getName();

        // Step 1. Checkout branch to merge into
        $library->checkout($output, $mergeInto);

        $this->log(
            $output,
            "Merging library <info>{$name}</info> from <info>{$mergeFrom}</info> to <info>{$mergeInto}</info>"
        );

        // Step 2. Merge up if there's anything to merge
        if ($library->hasDiff($output, $mergeFrom)) {
            try {
                $library->merge($output, $mergeFrom);
            } catch (ProcessException $e) {
                if (stripos($e->getMessage(), 'Automatic merge failed') !== false) {
                    // Get the vendor/someOrg/someRepo portion of the repo path
                    $repoDir = implode(
                        DIRECTORY_SEPARATOR,
                        array_slice(explode(DIRECTORY_SEPARATOR, $library->getDirectory()), -3, 3)
                    );
                    // Output the exception message - it has useful info (e.g. what file has the conflict)
                    $this->log($output, $e->getMessage(), 'error');
                    $this->log(
                        $output,
                        "A merge conflict has occurred. Please change to the $repoDir directory, "
                            . 'resolve the conflict, and then run the following commands:' . PHP_EOL
                            . "\tgit add ." . PHP_EOL
                            . "\tgit merge --continue" . PHP_EOL
                            . "\tgit checkout $releaseBranchName" . PHP_EOL
                            . 'Afterward, run the release:mergeup command again.',
                        'error'
                    );
                    die();
                }
                throw $e;
            }
        } else {
            $this->log($output, 'Nothing to merge');
        }

        // Step 3. Convert constraints to the appropriate dev format
        $this->destabiliseConstraints($output, $releasePlanNode, $isMajorBranch);

        // Step 4. Push branch
        if ($pushToRemote) {
            $library->pushTo('origin');
        }
    }

    /**
     * Set constraints for released dependencies to "1.2.x-dev" or "1.x-dev" format.
     */
    protected function destabiliseConstraints(
        OutputInterface $output,
        LibraryRelease $releasePlanNode,
        bool $isMajorBranch
    ) {
        $parentLibrary = $releasePlanNode->getLibrary();
        $originalData = $composerData = $parentLibrary->getComposerData();

        // Rewrite all dependencies.
        // Note: only rewrite dependencies for anything that was included in the release.
        // This mirrors functionality in PublishRelease::stabiliseRequirements()
        $items = $this->getReleasePlan()->getAllItems();
        foreach ($items as $item) {
            $childName = $item->getLibrary()->getName();

            // Ensure this library is allowed to release this dependency (even if shared)
            if (!isset($composerData['require'][$childName]) || !$parentLibrary->isChildLibrary($childName)) {
                continue;
            }

            $version = $item->getVersion();
            $target = $version->getMajor();
            if (!$isMajorBranch) {
                $target .= '.' . $version->getMinor();
            }
            $constraint =  $target . '.x-dev';
            $this->log(
                $output,
                "Reverting dependency for <info>{$childName}</info> to <info>{$constraint}</info>"
            );

            // Update dependency
            $composerData['require'][$childName] = $constraint;
        }

        // Save modifications to the composer.json for this module
        if ($composerData !== $originalData) {
            $parentName = $parentLibrary->getName();
            $this->log($output, "Rewriting composer.json for <info>$parentName</info>");
            $parentLibrary->setComposerData($composerData, true, 'MNT Update development dependencies');
        }
    }
}
