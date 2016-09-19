<?php


namespace SilverStripe\Cow\Model\Release;

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
     * The module being released
     *
     * @var Library
     */
    protected $library;

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
    public function addItem(LibraryRelease $release) {
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
    public function addItems($releases) {
        foreach($releases as $release) {
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
    public function removeItem($name) {
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
    public function getItem($name) {
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
    public function clearItems() {
        $this->items = [];
        return $this;
    }

    /**
     * Get direct child items
     *
     * @return LibraryRelease[]
     */
    public function getItems() {
        return $this->items;
    }

    /**
     * Is this a new release?
     *
     * @return bool True if a new tag will be created, false if using existing tag
     */
    public function getIsNewRelease() {
        $tags = $this->getLibrary()->getTags();
        return !array_key_exists($this->getVersion()->getValue(), $tags);
    }
}