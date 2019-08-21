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
     * The library that this version resolver should be providing a version for
     *
     * @var Library
     */
    private $library;

    /**
     * The parent release that has a constraint on the given library that's being released
     *
     * @var LibraryRelease
     */
    private $parentRelease;

    /**
     * VersionResolver constructor.
     * @param Library $library
     * @param LibraryRelease $parentRelease
     */
    public function __construct(Library $library, LibraryRelease $parentRelease)
    {
        $this->library = $library;
        $this->parentRelease = $parentRelease;
    }

    /**
     * Resolve a new version for the library given the parent release constraints
     *
     * @return LibraryRelease
     * @throws Exception
     */
    public function createRelease()
    {
        $priorRelease = $this->parentRelease->getPriorVersionForChild($this->library);

        return new LibraryRelease($this->library, $this->proposeVersion(), $priorRelease);
    }

    /**
     * Propose a new version for the library
     *
     * @throws Exception
     */
    public function proposeVersion()
    {
        // Check if the library should inherit the version of the parent release
        $parent = $this->parentRelease;
        $childModule = $this->library;
        $upgradeOnly = $parent->getLibrary()->isChildUpgradeOnly($childModule->getName());

        if ($this->getConstraint()->isSelfVersion()) {
            $candidateVersion = $parent->getVersion();

            // If we aren't allowed to release ("upgrade-only") and there's no release matching the parent then we're
            // in an invalid state
            if ($upgradeOnly && !array_key_exists($candidateVersion->getValue(), $this->library->getTags())) {
                throw new Exception(
                    "Library " . $this->library->getName() . " cannot be upgraded to version "
                    . $candidateVersion->getValue() . " without a new release"
                );
            }

            return $candidateVersion;
        }

        // Get the latest existing version (tag)
        $existingVersion = $this->getLatestExistingVersion();

        // If we're "upgrade only" then use the latest existing version
        if ($upgradeOnly) {
            if (!$existingVersion) {
                throw new Exception(
                    "Library " . $this->library->getName() . " has no available tags that matches "
                    . $this->getConstraint()->getValue()
                    . ". Please remove upgrade-only for this module, or tag a new release."
                );
            }
            return $existingVersion;
        }

        // Check if the head commit is already tagged with the existing version (if available)
        if ($existingVersion && $childModule->getRepository()->getReferences()->hasTag((string) $existingVersion)) {
            return $existingVersion;
        }

        return $this->proposeNewReleaseVersion();
    }

    /**
     * Get the latest existing version for the module that's relevant given the constraint on the parent release
     *
     * @return null|Version
     */
    public function getLatestExistingVersion()
    {
        // Get all stable tags that match the given composer constraint
        $tags = $this->library->getTags();
        $constraint = $this->getConstraint();
        $candidates = $constraint->filterVersions($tags);

        // If releasing a stable version, remove all unstable dependencies
        if ($this->parentRelease->getVersion()->isStable()) {
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
     * @return Version
     * @throws Exception
     */
    public function proposeNewReleaseVersion()
    {
        $childModule = $this->library;
        $parentRelease = $this->parentRelease;

        // Get tags and composer constraint to filter by
        $tags = $childModule->getTags();
        $constraint = $parentRelease->getLibrary()->getChildConstraint(
            $childModule->getName(),
            $parentRelease->getVersion()
        );

        // Get stability to use for the new tag
        $useSameStability = $parentRelease->getLibrary()->isStabilityInherited($childModule);
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
     * @return ComposerConstraint
     */
    protected function getConstraint()
    {
        return $this->parentRelease->getLibrary()->getChildConstraint(
            $this->library->getName(),
            $this->parentRelease->getVersion()
        );
    }
}
