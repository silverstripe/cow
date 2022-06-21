<?php

namespace SilverStripe\Cow\Model\Release;

use Generator;
use SilverStripe\Cow\Commands\Release\Branch;
use SilverStripe\Cow\Model\Modules\Library;

/**
 * Represents a single release of a module
 */
class LibraryRelease
{
    /**
     * List of child release dependencies
     *
     * @var LibraryRelease[]
     */
    protected $items = [];

    /**
     * Default branching strategy (only valid on root release)
     *
     * @var string
     */
    protected $branching = null;

    /**
     * The module being released
     *
     * @var Library
     */
    protected $library;

    /**
     * The version being released
     *
     * @var Version
     */
    protected $version;

    /**
     * The previous version of the module being released
     *
     * @var Version
     */
    protected $priorVersion;

    /**
     * Cached prior versions for children calculated from composer data from the prior release against this library
     *
     * @var array|null
     */
    private $childPriorVersions;

    /**
     * LibraryRelease constructor.
     *
     * @param Library $library
     * @param Version $version
     * @param Version|null $priorVersion
     */
    public function __construct(Library $library, Version $version, Version $priorVersion = null)
    {
        $this->setLibrary($library);
        $this->setVersion($version);
        if ($priorVersion) {
            $this->setPriorVersion($priorVersion);
        }
    }

    /**
     * Add or replace release for child object
     *
     * @param LibraryRelease $release
     * @return $this
     */
    public function addItem(LibraryRelease $release)
    {
        $name = $release->getLibrary()->getName();
        $this->items[$name] = $release;
        return $this;
    }

    /**
     * Add a list of items to this release
     *
     * @param LibraryRelease[] $releases
     * @return $this
     */
    public function addItems($releases)
    {
        foreach ($releases as $release) {
            $this->addItem($release);
        }
        return $this;
    }

    /**
     * Remove an item by name
     *
     * @param string $name
     * @return $this
     */
    public function removeItem($name)
    {
        unset($this->items[$name]);
        return $this;
    }

    /**
     * Find library in the tree by name.
     * May return self, a direct child, or a nested child.
     *
     * @param string $name Library name
     * @return null|LibraryRelease
     */
    public function getItem($name)
    {
        // Identity check
        if ($this->getLibrary()->getName() === $name) {
            return $this;
        }

        // Check children
        foreach ($this->items as $child) {
            if ($nested = $child->getItem($name)) {
                return $nested;
            }
        }

        return null;
    }

    /**
     * Clear all child releases
     *
     * @return $this
     */
    public function clearItems()
    {
        $this->items = [];
        return $this;
    }

    /**
     * Get direct child items
     *
     * @return LibraryRelease[]
     */
    public function getItems()
    {
        return $this->items;
    }

    /**
     * Get recursive items
     *
     * @param bool $includeSelf
     * @return Generator|LibraryRelease[]
     */
    public function getAllItems($includeSelf = false)
    {
        if ($includeSelf) {
            yield $this;
        }
        $items = $this->getItems();
        foreach ($items as $child) {
            yield $child;
            foreach ($child->getAllItems() as $nested) {
                yield $nested;
            }
        }
    }

    /**
     * Recursively get the total number of items
     *
     * @param bool $includeSelf
     * @return int
     */
    public function countAllItems($includeSelf = false)
    {
        $count = 0;
        foreach ($this->getAllItems($includeSelf) as $item) {
            $count++;
        }
        return $count;
    }

    /**
     * Is this a new release?
     *
     * @return bool True if a new tag will be created, false if using existing tag
     */
    public function getIsNewRelease()
    {
        $tags = $this->getLibrary()->getTags();
        return !array_key_exists($this->getVersion()->getValue(), $tags);
    }

    /**
     * Determine "from" version for this version
     *
     * @param bool $fallback Whether to fall back to guessing from tags
     * @return Version
     */
    public function getPriorVersion($fallback = true)
    {
        // If it has been explicitly provided, or we shouldn't fall back to using tags, return the prop
        if ($this->priorVersion || !$fallback) {
            return $this->priorVersion;
        }

        // Otherwise, guess it from the constraint and existing tags
        $tags = $this->getLibrary()->getTags();
        return $this->getVersion()->getPriorVersionFromTags($tags);
    }

    /**
     * Explicitly set the "from" version for this version
     *
     * @param Version $version
     * @return $this
     */
    public function setPriorVersion(Version $version)
    {
        $this->priorVersion = $version;

        // Clear the child prior versions known as this now might change
        $this->childPriorVersions = null;

        return $this;
    }

    /**
     * Attempt to determine what prior versions of child modules were released by looking up the composer information
     * for the prior release of this module
     *
     * @return array
     */
    public function getChildPriorVersions()
    {
        if ($this->childPriorVersions !== null) {
            return $this->childPriorVersions;
        }

        $priorVersion = $this->getPriorVersion();

        if (!$priorVersion) {
            return [];
        }

        $composerData = $this->getLibrary()->getHistoryComposerData($priorVersion);
        $childReleases = $this->getLibrary()->getChildren();
        $childPriorVersions = [];

        foreach ($childReleases as $child) {
            $childName = $child->getName();
            if (!isset($composerData['require'][$childName])) {
                continue;
            }

            $constraint = $composerData['require'][$childName];

            // Attempt to resolve a specific version from the constraint
            if (!Version::parse($constraint)) {
                continue;
            }

            $childPriorVersions[$childName] = new Version($constraint);
        }

        return $this->childPriorVersions = $childPriorVersions;
    }

    /**
     * Given a child library - attempt to resolve what version was specified in the constraints for the prior release
     *
     * @param Library $childLibrary
     * @return Version|null
     */
    public function getPriorVersionForChild(Library $childLibrary)
    {
        return $this->getChildPriorVersions()[$childLibrary->getName()] ?? null;
    }

    /**
     * Get branching strategy
     *
     * @param string $default Default value to get if no value found
     * @return string
     */
    public function getBranching($default = Branch::AUTO)
    {
        return $this->branching ?: $default;
    }

    /**
     * Set branching strategy
     *
     * @param string $branching
     * @return $this
     */
    public function setBranching($branching)
    {
        $this->branching = $branching;
        return $this;
    }

    /**
     * @return Library
     */
    public function getLibrary()
    {
        return $this->library;
    }

    /**
     * @param Library $library
     * @return $this
     */
    public function setLibrary($library)
    {
        $this->library = $library;
        return $this;
    }

    /**
     * @return Version
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * @param Version $version
     * @return $this
     */
    public function setVersion($version)
    {
        $this->version = $version;
        return $this;
    }
}
