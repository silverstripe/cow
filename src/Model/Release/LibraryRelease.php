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
     * Raw markdown content of cached changelog for this release (to be pushed to github)
     *
     * @var string
     */
    protected $changelog;

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

    /**
     * The version being released
     *
     * @var Version
     */
    protected $version;

    /**
     * LibraryRelease constructor.
     *
     * @param Library $library
     * @param Version $version
     */
    public function __construct(Library $library, Version $version)
    {
        $this->setLibrary($library);
        $this->setVersion($version);
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
     * @return Version
     */
    public function getPriorVersion()
    {
        $tags = $this->getLibrary()->getTags();
        return $this->getVersion()->getPriorVersionFromTags($tags, $this->getLibrary()->getName());
    }

    /**
     * @return string
     */
    public function getChangelog()
    {
        return $this->changelog;
    }

    /**
     * @param string $changelog
     * @return $this
     */
    public function setChangelog($changelog)
    {
        $this->changelog = $changelog;
        return $this;
    }

    /**
     * Get branching strategy
     *
     * @param string $default Default value to get if no value found
     * @return string
     */
    public function getBranching($default = Branch::DEFAULT_OPTION)
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
}
