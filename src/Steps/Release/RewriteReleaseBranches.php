<?php

namespace SilverStripe\Cow\Steps\Release;

use Exception;
use InvalidArgumentException;
use SilverStripe\Cow\Commands\Release\Branch;
use SilverStripe\Cow\Model\Modules\Library;
use SilverStripe\Cow\Model\Release\LibraryRelease;
use SilverStripe\Cow\Model\Release\Version;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * This step performs the below tasks on the checked out project:
 * - Recursively checks out the correct release branch matching the selected version. E.g. the dev
 *   branch may be 3.2, but if doing a 3.3 release we need to check out this branch instead. This
 *   could either be a new branch and needs to be branched from 3 / 3.2, or it could be another
 *   branch that needs pulling from origin.
 * - On any new branches remove any branch-alias.
 * - Modify all development dependencies so that if the selected version of any dependency
 *   doesn't match the parent constraint, we rewrite the constraint to support it.
 */
class RewriteReleaseBranches extends ReleaseStep
{

    public function getStepName()
    {
        return 'branch';
    }

    public function run(InputInterface $input, OutputInterface $output)
    {
        $this->log($output, "Updating branches and aliases");

        $release = $this->getReleasePlan();
        $branching = $release->getBranching();
        $this->recursiveBranchLibrary($output, $release, $branching);

        $this->log($output, "Branches updated");
    }

    /**
     * Update branches for the given library recursively
     *
     * @param OutputInterface $output
     * @param LibraryRelease $libraryRelease
     * @param string $branching Branching strategy
     */
    protected function recursiveBranchLibrary(OutputInterface $output, LibraryRelease $libraryRelease, $branching)
    {
        // Recursively rewrite and branch child dependencies first
        foreach ($libraryRelease->getItems() as $childLibrary) {
            $this->recursiveBranchLibrary($output, $childLibrary, $branching);
        }

        // Skip if not tagging this library (upgrade only)
        if ($libraryRelease->getIsNewRelease()) {
            // Update this library
            $this->branchLibrary($output, $libraryRelease, $branching);

            // Update dev dependencies for the given module
            $this->incrementDevDependencies($output, $libraryRelease);
        } else {
            $this->checkoutLibrary($output, $libraryRelease->getLibrary(), $libraryRelease->getVersion());
        }
    }

    /**
     * Updated branches for the given library
     *
     * @param OutputInterface $output
     * @param LibraryRelease $libraryRelease
     * @param string $branching Branching strategy
     * @throws Exception
     */
    protected function branchLibrary(OutputInterface $output, LibraryRelease $libraryRelease, $branching)
    {
        // Get info on current status
        $library = $libraryRelease->getLibrary();
        $currentBranch = $library->getBranch();
        $libraryName = $library->getName();

        // Calculate candidate branch names
        $version = $libraryRelease->getVersion();

        // Calculate branch to switch to
        $target = $this->getTargetBranch($version, $branching, $currentBranch);

        // Either branch, or simply log current branch
        if (empty($target) || $target === $currentBranch) {
            $this->log(
                $output,
                "Releasing library <info>{$libraryName}</info> from branch <info>{$currentBranch}</info>"
            );
        } else {
            // Check versions to checkout
            $this->log(
                $output,
                "Branching library <info>{$libraryName}</info> as <info>{$target}</info> "
                . "(from <comment>{$currentBranch}</comment>)"
            );

            // If branching minor version, checkout major as well along the way.
            // If switching master -> 1.0 it can be better to branch from an existing 1
            // instead of master
            $majorBranch = $version->getMajor();
            $minorBranch = $majorBranch . "." . $version->getMinor();
            if ($target === $minorBranch) {
                $library->checkout($output, $majorBranch, 'origin', true);
            }

            // Checkout branch
            $library->checkout($output, $target, 'origin', true);

            // If branching to minor version, remove alias
            if ($target === $minorBranch) {
                $this->removeComposerAlias($output, $library);
            }
        }

        // Synchronise local branch with upstream
        $library->rebase($output, 'origin');
    }

    /**
     * Checkout the given tag for this library
     *
     * @param OutputInterface $output
     * @param Library $library
     * @param Version $version
     */
    protected function checkoutLibrary(OutputInterface $output, Library $library, Version $version)
    {
        $libraryName = $library->getName();
        $tagName = $version->getValue();
        $this->log($output, "Checking out library <info>{$libraryName}</info> at existing tag <info>{$tagName}</info>");
        $library->resetToTag($output, $version);
    }

    /**
     * Remove composer alias from composer.json
     *
     * @param OutputInterface $output
     * @param Library $library
     */
    protected function removeComposerAlias(OutputInterface $output, Library $library)
    {
        // Check if there is any alias to remove
        $composerData = $library->getComposerData();
        if (empty($composerData['extra']['branch-alias'])) {
            return;
        }

        $this->log($output, "Removing branch alias from <info>" . $library->getName() . "</info>");
        unset($composerData['extra']['branch-alias']);

        // Write changes
        $path = $library->getComposerPath();
        $library->setComposerData($composerData);

        // Commit to git
        $repo = $library->getRepository();
        $repo->run("add", array($path));
        $status = $repo->run("status");
        if (stripos($status, 'Changes to be committed:')) {
            $repo->run("commit", array("-m", "Remove obsolete branch-alias"));
        }
    }

    /**
     * Increment any dependencies on x-dev versions that need updating
     *
     * @param OutputInterface $output
     * @param LibraryRelease $releasePlan
     */
    protected function incrementDevDependencies(OutputInterface $output, LibraryRelease $releasePlan)
    {
        $parentLibrary = $releasePlan->getLibrary();
        $parentName = $parentLibrary->getName();
        $originalData = $composerData = $parentLibrary->getComposerData();

        // Inspect all dependencies
        foreach ($releasePlan->getItems() as $item) {
            $childName = $item->getLibrary()->getName();
            $childVersion = $item->getVersion();
            $childConstraint = $parentLibrary->getChildConstraint($childName, $releasePlan->getVersion());

            // Compare installed dependency vs version we plan to tag
            $comparison = $childConstraint->compareTo($childVersion);
            if (!$comparison) {
                // Dev dependency is acceptable, no rewrite needed.
                continue;
            }

            // Warn if downgrading constraint, but rewrite anyway.
            // E.g. installed with 3.1.x-dev, but tagging as 3.0.0
            if ($comparison > 0) {
                $this->log(
                    $output,
                    "Warning: Dependency <info>{$childName}</info> of parent <info>{$parentName}</info> "
                    . "was installed with constraint <info>" . $childConstraint->getValue() . "</info> but is "
                    . "being released at a lower version <info>" . $childVersion->getValue() . "</info>",
                    "error"
                );
            }

            // Check if this constraint supports rewriting
            $newConstraint = $childConstraint->rewriteToSupport($childVersion);
            if (!$newConstraint || ($newConstraint->getValue() === $childConstraint->getValue())) {
                continue;
            }
            $this->log(
                $output,
                "Rewriting installation constraint of <info>{$childName}</info> from <info>"
                . $childConstraint->getValue() . "</info> to <info>" . $newConstraint->getValue() . "</info>"
            );

            $composerData['require'][$childName] = $newConstraint->getValue();
        }

        // Save modifications to the composer.json for this module
        if ($composerData !== $originalData) {
            $this->log($output, "Rewriting composer.json for <info>$parentName</info>");
            $parentLibrary->setComposerData($composerData);

            // Commit to git
            $path = $parentLibrary->getComposerPath();
            $repo = $parentLibrary->getRepository();
            $repo->run("add", array($path));
            $status = $repo->run("status");
            if (stripos($status, 'Changes to be committed:')) {
                $repo->run("commit", array("-m", "Update development dependencies"));
            }
        }
    }

    /**
     * Get branch to branch to, or null if no branching should occur
     *
     * @param Version $version
     * @param string $branching Branching strategy
     * @param string $currentBranch
     * @return string Branch target name
     */
    protected function getTargetBranch($version, $branching, $currentBranch)
    {
        $majorBranch = $version->getMajor();
        $minorBranch = $majorBranch . "." . $version->getMinor();

        // If already on minor branch stay on this in all situations
        if ($currentBranch === $minorBranch) {
            return null;
        }

        // Don't branch on pre-1.0 in any situation
        if ($majorBranch < 1) {
            return null;
        }

        // Determine destination branch
        switch ($branching) {
            case Branch::NONE:
                return null;
            case Branch::MINOR:
                return $minorBranch;
            case Branch::MAJOR:
                return $majorBranch;
            case Branch::AUTO:
                // Auto disables branching for unstable tags
                if (!$version->isStable()) {
                    return null;
                }
                return $minorBranch;
            default:
                throw new InvalidArgumentException("Invalid branching strategy $branching");
        }
    }
}
