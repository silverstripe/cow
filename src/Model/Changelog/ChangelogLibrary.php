<?php


namespace SilverStripe\Cow\Model\Changelog;

use Generator;
use SilverStripe\Cow\Model\Release\LibraryRelease;
use SilverStripe\Cow\Model\Release\Version;

/**
 * Represents a library to be included within a changelog.
 *
 * Contains a release plan for this library, as well as the historic version to
 * generate the changelog from.
 */
class ChangelogLibrary
{
    /**
     * List of child release dependencies
     *
     * @var ChangelogLibrary[]
     */
    protected $items = [];

    /**
     * The release being tagged
     *
     * @var LibraryRelease
     */
    protected $release;

    /**
     * Prior version version
     *
     * @var Version
     */
    protected $priorVersion;

    /**
     * @return LibraryRelease
     */
    public function getRelease()
    {
        return $this->release;
    }

    /**
     * @param LibraryRelease $release
     * @return $this
     */
    public function setRelease($release)
    {
        $this->release = $release;
        return $this;
    }

    /**
     * @return Version
     */
    public function getPriorVersion()
    {
        return $this->priorVersion;
    }

    /**
     * @param Version $priorVersion
     * @return $this
     */
    public function setPriorVersion($priorVersion)
    {
        $this->priorVersion = $priorVersion;
        return $this;
    }

    /**
     * LibraryRelease constructor.
     *
     * @param LibraryRelease $libraryRelease
     * @param Version $priorVersion
     * @internal param Version $version
     */
    public function __construct(LibraryRelease $libraryRelease, Version $priorVersion)
    {
        $this->setRelease($libraryRelease);
        $this->setPriorVersion($priorVersion);
    }

    /**
     * Add or replace release for child object
     *
     * @param ChangelogLibrary $changelogLibrary
     * @return $this
     */
    public function addItem(ChangelogLibrary $changelogLibrary) {
        $name = $changelogLibrary->getRelease()->getLibrary()->getName();
        $this->items[$name] = $changelogLibrary;
        return $this;
    }

    /**
     * Get direct child items
     *
     * @return ChangelogLibrary[]
     */
    public function getItems() {
        return $this->items;
    }



    /**
     * Get recursive items
     *
     * @return Generator|ChangelogLibrary[]
     */
    public function getAllItems() {
        $items = $this->getItems();
        foreach ($items as $child) {
            yield $child;
            foreach($child->getAllItems() as $nested) {
                yield $nested;
            }
        }
    }

}
