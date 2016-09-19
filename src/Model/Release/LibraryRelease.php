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
    protected $childReleases = [];

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
     * Add release for child object
     *
     * @param LibraryRelease $release
     */
    public function addChildItem(LibraryRelease $release) {
        $this->childReleases[] = $release;
    }

    /**
     * @return LibraryRelease[]
     */
    public function getChildren() {
        return $this->childReleases;
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