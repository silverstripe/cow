<?php


namespace SilverStripe\Cow\Steps\Release;

use Exception;
use SilverStripe\Cow\Model\Modules\Library;
use SilverStripe\Cow\Model\Release\LibraryRelease;
use SilverStripe\Cow\Model\Release\Version;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Automatically checkout the correct branches / tags for any module being released.
 */
class CreateBranches extends ReleaseStep
{

    public function getStepName()
    {
        return 'branch';
    }

    public function run(InputInterface $input, OutputInterface $output)
    {
        $this->log($output, "Updating branches and aliases");

        $release = $this->getReleasePlan();
        $this->recursiveBranchLibrary($output, $release);

        $this->log($output, "Branches updated");
    }

    /**
     * Update branches for the given library recursively
     *
     * @param OutputInterface $output
     * @param LibraryRelease $libraryRelease
     */
    protected function recursiveBranchLibrary(OutputInterface $output, LibraryRelease $libraryRelease)
    {
        // Skip if not tagging this library (upgrade only)
        if ($libraryRelease->getIsNewRelease()) {
            // Update this library
            $this->branchLibrary($output, $libraryRelease);
        } else {
            $this->checkoutLibrary($output, $libraryRelease->getLibrary(), $libraryRelease->getVersion());
        }

        // Recursie
        foreach($libraryRelease->getItems() as $childLibrary) {
            $this->recursiveBranchLibrary($output, $childLibrary);
        }
    }

    /**
     * Updated branches for the given library
     *
     * @param OutputInterface $output
     * @param LibraryRelease $libraryRelease
     * @throws Exception
     */
    protected function branchLibrary(OutputInterface $output, LibraryRelease $libraryRelease)
    {
        // Get info on current status
        $library = $libraryRelease->getLibrary();
        $currentBranch = $library->getBranch();
        $libraryName = $library->getName();

        // Guess branches to use
        $version = $libraryRelease->getVersion();
        $newBranch = $this->getNewBranch($currentBranch, $version);
        if ($newBranch) {
            $this->log($output, "Branching library <info>{$libraryName}</info> to <info>{$newBranch}</info> (new branch)");
            $library->checkout($output, $newBranch, 'origin', true);
            $this->removeComposerAlias($library);
        } else {
            $this->log($output, "Releasing library <info>{$libraryName}</info> from branch <info>{$currentBranch}</info>");
        }
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
     * Determine if the current branch should be changed
     * @param string $currentBranch Note, this can be empty
     * @param Version $version
     * @return string|null Name of new branch, or null if already on best branch
     */
    protected function getNewBranch($currentBranch, Version $version) {
        // Get expected major and minor branches
        $majorBranch = $version->getMajor();
        $minorBranch = $version->getMajor() . "." . $version->getMinor();

        // Already on ideal branch
        if ($currentBranch === $minorBranch) {
            return null;
        }

        // Branch from 3 -> 3.1
        if (empty($currentBranch) || $currentBranch === $majorBranch) {
            return $minorBranch;
        }

        // Branch from master -> 3 and 3.1 if doing beta / rc / stable release
        if ($version->isStable() || in_array($version->getStability(), ['beta', 'rc'])) {
            return $minorBranch;
        }

        return null;
    }

    /**
     * Remove composer alias from composer.json
     * @param Library $library
     */
    protected function removeComposerAlias(Library $library)
    {
        // Update
        $composerData = $library->getComposerData();
        $newData = $composerData;
        unset($newData['extra']['branch-alias']);
        if ($newData === $composerData) {
            return;
        }

        // Write changes
        $path = $library->getComposerPath();
        $library->setComposerData($newData);

        // Commit to git
        $repo = $library->getRepository();
        $repo->run("add", array($path));
        $status = $repo->run("status");
        if (stripos($status, 'Changes to be committed:')) {
            $repo->run("commit", array("-m", "Remove obsolete branch-alias"));
        }
    }

}
