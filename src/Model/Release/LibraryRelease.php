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
    protected $childReleases;

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
    public function getFromVersion()
    {
        return $this->fromVersion;
    }

    /**
     * @param Version $fromVersion
     * @return $this
     */
    public function setFromVersion($fromVersion)
    {
        $this->fromVersion = $fromVersion;
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
     * The previous version to generate a changelog from.
     * This can be null if not generating a changelog.
     *
     * @var Version
     */
    protected $fromVersion;

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
     * @param Version|null $from Optional "from" version if creating a new tag
     */
    public function __construct(Library $library, Version $version, Version $from = null)
    {
        $this->setLibrary($library);
        $this->setVersion($version);
        if ($from) {
            $this->setFromVersion($from);
        }
    }

    /**
     * Add release for child object
     *
     * @param LibraryRelease $release
     */
    public function addChildItem(LibraryRelease $release) {
        $this->childReleases[] = $release;
    }
}