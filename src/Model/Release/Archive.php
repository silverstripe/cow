<?php

namespace SilverStripe\Cow\Model\Release;

/**
 * A recipe being archived to a tar.gz or zip
 */
class Archive
{
    /**
     * @var LibraryRelease
     */
    protected $release = null;

    /**
     * List of files to export this archive to
     *
     * @var array
     */
    protected $files = [];

    /**
     * Version to use for naming this release
     * Note: Prefer to use root version (e.g. installer 4.0) instead of library specific version
     * (recipe-core 1.0)
     *
     * @var Version
     */
    protected $version = null;

    /**
     * Build a new archive
     *
     * @param LibraryRelease $release
     * @param string[] $files
     * @param Version $rootVersion Root version to use for naming the file
     */
    public function __construct(LibraryRelease $release, array $files, Version $rootVersion = null)
    {
        $this->release = $release;
        $this->files = $files;
        $this->setVersion($rootVersion ?: $release->getVersion());
    }

    /**
     * @return LibraryRelease
     */
    public function getRelease()
    {
        return $this->release;
    }

    /**
     * Get temp directory to install this archive
     *
     * @return string
     */
    public function getTempDir()
    {
        // Pick temp directory specific to this library / version
        $name = $this->getRelease()->getLibrary()->getName();
        $version = $this->getVersion()->getValue();
        return sys_get_temp_dir() . '/cowArchive/' . sha1($name . '-' . $version) . '/';
    }

    /**
     * Get list of file names to export to
     *
     * @return string[]
     */
    public function getFiles()
    {
        $files = [];
        foreach ($this->files as $filePattern) {
            // Inject version wildcards
            $files[] = $this
                ->getVersion()
                ->injectPattern($filePattern);
        }
        return $files;
    }

    /**
     * @param Version $version
     * @return Archive
     */
    public function setVersion(Version $version)
    {
        $this->version = $version;
        return $this;
    }

    /**
     * @return Version
     */
    public function getVersion()
    {
        return $this->version;
    }
}
