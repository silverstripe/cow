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
     * Build a new archive
     *
     * @param LibraryRelease $release
     * @param string[] $files
     */
    public function __construct(LibraryRelease $release, array $files)
    {
        $this->release = $release;
        $this->files = $files;
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
        $version = $this->getRelease()->getVersion()->getValue();
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
                ->getRelease()
                ->getVersion()
                ->injectPattern($filePattern);
        }
        return $files;
    }
}
