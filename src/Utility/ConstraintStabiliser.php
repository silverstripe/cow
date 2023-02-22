<?php

namespace SilverStripe\Cow\Utility;

use Symfony\Component\Console\Output\OutputInterface;
use SilverStripe\Cow\Model\Release\LibraryRelease;
use SilverStripe\Cow\Utility\Logger;

class ConstraintStabiliser
{
    /**
     * Used to hack in dual support for graphql on CMS 4
     * Update this whenever a new version of graphql 3 is released
     */
    private const GRAPHQL_3_VERSION_DEV = '3.8.x-dev';
    private const GRAPHQL_3_VERSION_STABLE = '~3.8.1@stable';

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

        // HACK - update graphql version to dual support for CMS 4
        $composerData = self::hackInCms4GraphqlContraint($composerData);

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

        // HACK update graphql version to dual support for CMS 4
        $composerData = self::hackInCms4GraphqlContraint($composerData);

        // Save modifications to the composer.json for this module
        if ($composerData !== $originalData) {
            $parentName = $parentLibrary->getName();
            Logger::log($output, "Rewriting composer.json for <info>$parentName</info>");
            $parentLibrary->setComposerData($composerData, true, 'MNT Update development dependencies');
        }
    }

    /**
     * Add in dual support for graphql for CMS 4 only
     */
    private static function hackInCms4GraphqlContraint(array $composerData): array
    {
        if (!isset($composerData['require']["silverstripe/graphql"])) {
            return $composerData;
        }
        $constraint = $composerData['require']["silverstripe/graphql"];
        // CMS 4 constraint can be: 4.x-dev / 4.3.x-dev / ~4.3.0@stable
        // CMS 5 uses graphql 5, so it will never match this
        if (preg_match('#^~?4#', $constraint)) {
            if ($constraint === '4.x-dev') {
                $constraint = "3.x-dev || " . $constraint;
            } elseif (strpos($constraint, '.x-dev') !== false) {
                $constraint = self::GRAPHQL_3_VERSION_DEV . ' || ' . $constraint;
            } else {
                $constraint = self::GRAPHQL_3_VERSION_STABLE . ' || ' . $constraint;
            }
            $composerData['require']["silverstripe/graphql"] = $constraint;
        }
        return $composerData;
    }
}
