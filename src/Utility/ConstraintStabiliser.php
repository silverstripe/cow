<?php

namespace SilverStripe\Cow\Utility;

use Symfony\Component\Console\Output\OutputInterface;
use SilverStripe\Cow\Model\Release\LibraryRelease;
use SilverStripe\Cow\Utility\Logger;

class ConstraintStabiliser
{
    /**
     * Rewrite all composer constraints for this tag
     *
     * @param OutputInterface $output
     * @param LibraryRelease $releasePlanNode Current node in release plan being released
     */
    public static function stabiliseConstraints(OutputInterface $output, LibraryRelease $releasePlanNode)
    {
        $parentLibrary = $releasePlanNode->getLibrary();
        $originalData = $composerData = $parentLibrary->getComposerData();
        $constraintType = $parentLibrary->getDependencyConstraint();
        $parentName = $parentLibrary->getName();

        // Rewrite all dependencies.
        // Note: rewrite dependencies even if non-exclusive children, so do a global search
        // through the entire tree of the plan to get the new tag
        foreach ($releasePlanNode->getRootLibraryRelease()->getAllItems() as $item) {
            $childName = $item->getLibrary()->getName();

            // Ensure this library is allowed to release this dependency (even if shared)
            if (!isset($composerData['require'][$childName])) {
                continue;
            }

            // Update dependency
            $composerData['require'][$childName] = self::stabiliseDependencyConstraints(
                $output,
                $item,
                $constraintType
            );
        }

        // Save modifications to the composer.json for this module
        if ($composerData !== $originalData) {
            $parentName = $parentLibrary->getName();
            Logger::log($output, "Rewriting composer.json for <info>$parentName</info>");
            $parentLibrary->setComposerData($composerData, true, 'MNT Update release dependencies');
        }
    }

    /**
     * @param OutputInterface $output
     * @param LibraryRelease $item
     * @param string $constraintType
     * @return string
     */
    private static function stabiliseDependencyConstraints(
        OutputInterface $output,
        LibraryRelease $item,
        $constraintType
    ) {
        // Get constraint for this version
        $childConstraint = $item->getVersion()->getConstraint($constraintType);

        // Notify of change
        $childName = $item->getLibrary()->getName();
        Logger::log(
            $output,
            "Fixing tagged dependency <info>{$childName}</info> to <info>{$childConstraint}</info>"
        );
        return $childConstraint;
    }

    /**
     * Set constraints for released dependencies to "1.2.x-dev" or "1.x-dev" format.
     */
    public static function destabiliseConstraints(
        OutputInterface $output,
        LibraryRelease $releasePlanNode,
        bool $isMajorBranch
    ) {
        $parentLibrary = $releasePlanNode->getLibrary();
        $originalData = $composerData = $parentLibrary->getComposerData();

        // Rewrite all dependencies.
        // Note: only rewrite dependencies for anything that was included in the release.
        // This mirrors functionality in PublishRelease::stabiliseConstraints()
        foreach ($releasePlanNode->getRootLibraryRelease()->getAllItems() as $item) {
            $childName = $item->getLibrary()->getName();

            // Ensure this library is allowed to release this dependency (even if shared)
            if (!isset($composerData['require'][$childName])) {
                continue;
            }

            $version = $item->getVersion();
            $target = $version->getMajor();
            if (!$isMajorBranch) {
                $target .= '.' . $version->getMinor();
            }
            $constraint = $target . '.x-dev';
            Logger::log($output, "Updating dependency for <info>{$childName}</info> to <info>{$constraint}</info>");

            // Update dependency
            $composerData['require'][$childName] = $constraint;
        }

        // Save modifications to the composer.json for this module
        if ($composerData !== $originalData) {
            $parentName = $parentLibrary->getName();
            Logger::log($output, "Rewriting composer.json for <info>$parentName</info>");
            $parentLibrary->setComposerData($composerData, true, 'MNT Update development dependencies');
        }
    }
}
