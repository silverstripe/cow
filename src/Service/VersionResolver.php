<?php declare(strict_types=1);

namespace SilverStripe\Cow\Service;

use Exception;
use SilverStripe\Cow\Model\Modules\Library;
use SilverStripe\Cow\Model\Release\ComposerConstraint;
use SilverStripe\Cow\Model\Release\LibraryRelease;
use SilverStripe\Cow\Model\Release\Version;

class VersionResolver
{
    /**
     * Cached proposed versions preventing multiple scans of a composer dependency tree
     *
     * @var array
     */
    private $proposedVersionCache = [];

    /**
     * Propose a new version for the library
     *
     * @param Library $library The library that this resolver should be providing a version for
     * @param LibraryRelease $parentRelease The parent release that has the constraint on the library
     * @return Version|null
     * @throws Exception
     */
    public function proposeVersion(Library $library, LibraryRelease $parentRelease)
    {
        // Generate a key for the cache
        $cacheKey = sprintf(
            '%s||%s@%s',
            $library->getName(),
            $parentRelease->getLibrary()->getName(),
            $parentRelease->getVersion()->getValue()
        );

        if (isset($this->proposedVersionCache[$cacheKey])) {
            $this->proposedVersionCache[$cacheKey];
        }

        // Check if the library should inherit the version of the parent release
        $upgradeOnly = $parentRelease->getLibrary()->isChildUpgradeOnly($library->getName());
        $constraint = $this->getConstraint($library, $parentRelease);

        if ($constraint->isSelfVersion()) {
            $candidateVersion = $parentRelease->getVersion();

            // If we aren't allowed to release ("upgrade-only") and there's no release matching the parent then we're
            // in an invalid state
            if ($upgradeOnly && !array_key_exists($candidateVersion->getValue(), $library->getTags())) {
                throw new Exception(
                    "Library " . $library->getName() . " cannot be upgraded to version "
                    . $candidateVersion->getValue() . " without a new release"
                );
            }

            return $this->proposedVersionCache[$cacheKey] = $candidateVersion;
        }

        // Get the latest existing version (tag)
        $existingVersion = $this->getLatestExistingVersion($library, $parentRelease);

        // If we're "upgrade only" then use the latest existing version
        if ($upgradeOnly) {
            if (!$existingVersion) {
                throw new Exception(
                    "Library " . $library->getName() . " has no available tags that matches "
                    . $constraint->getValue()
                    . ". Please remove upgrade-only for this module, or tag a new release."
                );
            }
            return $this->proposedVersionCache[$cacheKey] = $existingVersion;
        }

        // Check to see if:
        // - We don't have an existing version that makes sense, or
        // - There are any commits on the HEAD that aren't part of the existing version, or
        if (!$existingVersion
            || (int) $library->getRepository()->run('rev-list', ['--count', $existingVersion.'..HEAD']) > 0
        ) {
            return $this->proposedVersionCache[$cacheKey] = $this->proposeNewReleaseVersion($library, $parentRelease);
        }

        // Check to see if there are no children of this library. This means we can say "no release" if we've got this
        // far
        if (empty($library->getChildrenExclusive())) {
            return $this->proposedVersionCache[$cacheKey] = $existingVersion;
        }

        // The version is indeterminate. Some of the children of this library might need to be released, which means
        // this library should be released too. It will have to be deferred.
        return null;
    }

    /**
     * Get the latest existing version for the module that's relevant given the constraint on the parent release
     *
     * @param Library $library The library that this version resolver should be providing a version for
     * @param LibraryRelease $parentRelease The parent release that has the constraint on the library
     * @return null|Version
     */
    public function getLatestExistingVersion(Library $library, LibraryRelease $parentRelease)
    {
        // Get all stable tags that match the given composer constraint
        $tags = $library->getTags();
        $candidates = $this->getConstraint($library, $parentRelease)->filterVersions($tags);

        // If releasing a stable version, remove all unstable dependencies
        if ($parentRelease->getVersion()->isStable()) {
            foreach ($candidates as $tag => $version) {
                if (!$version->isStable()) {
                    unset($candidates[$tag]);
                }
            }
        }

        // Check if we have any candidates left
        if (empty($candidates)) {
            return null;
        }

        // Upgrade to highest version
        $tags = Version::sort($candidates, 'descending');
        return reset($tags);
    }

    /**
     * Propose a new version to tag for a given dependency
     *
     * @param Library $library The library that this version resolver should be providing a version for
     * @param LibraryRelease $parentRelease The parent release that has the constraint on the library
     * @return Version
     */
    public function proposeNewReleaseVersion(Library $library, LibraryRelease $parentRelease)
    {
        // Get tags and composer constraint to filter by
        $tags = $library->getTags();
        $constraint = $parentRelease->getLibrary()->getChildConstraint(
            $library->getName(),
            $parentRelease->getVersion()
        );

        // Get stability to use for the new tag
        $useSameStability = $parentRelease->getLibrary()->isStabilityInherited($library);
        if ($useSameStability) {
            $stability = $parentRelease->getVersion()->getStability();
            $stabilityVersion = $parentRelease->getVersion()->getStabilityVersion();
        } else {
            $stability = '';
            $stabilityVersion = null;
        }

        // Filter versions
        $candidates = $constraint->filterVersions($tags);
        $tags = Version::sort($candidates, 'descending');

        // Determine which best tag to create (with the correct stability)
        $existingTag = reset($tags);
        if ($existingTag) {
            // Increment from to guess next version
            return $existingTag->getNextVersion($stability, $stabilityVersion);
        }
        // In this case, the lower bounds of the constraint isn't a valid tag,
        // so this is our new candidate
        $version = clone $constraint->getMinVersion();
        $version->setStability($stability);
        $version->setStabilityVersion($stabilityVersion);

        return $version;
    }

    /**
     * Get the constraint for the module that's defined by the parent release
     *
     * @param Library $library The library to get the constraint for
     * @param LibraryRelease $parentRelease The parent release that has the constraint on the library
     * @return ComposerConstraint
     */
    protected function getConstraint(Library $library, LibraryRelease $parentRelease)
    {
        return $parentRelease->getLibrary()->getChildConstraint(
            $library->getName(),
            $parentRelease->getVersion()
        );
    }
}
