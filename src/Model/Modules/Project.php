<?php

namespace SilverStripe\Cow\Model\Modules;

use Generator;
use InvalidArgumentException;

/**
 * Represents information about a project in a given directory
 *
 * Is also the 'silverstripe-installer' module
 */
class Project extends Module
{
    public function __construct($directory)
    {
        parent::__construct($directory);

        if (!self::isProjectPath($this->directory)) {
            throw new InvalidArgumentException("No installer found in \"{$this->directory}\"");
        }
    }

    /**
     * Is there a project in the given directory?
     *
     * @param string $directory
     * @return bool
     */
    public static function isProjectPath($directory)
    {
        return static::isLibraryPath($directory) && file_exists($directory . '/mysite');
    }

    /**
     * Return list of installed modules in this project.
     *
     * Note; `installer` always refers to project root.
     * Themes are named `themes/<themename>`.
     * Vendor modules / recipes in vendor/<vendor>/<name> are just called `<name>`
     *
     * See {@see i18nTextCollector::getModules} in framework for mirrored logic.
     *
     * @param array $filter Optional list of modules to filter
     * @param bool $listIsExclusive Set to true if this list is exclusive
     * Ignored if $listIsExclusive is set to true and $filter contains modules.
     * @return LibraryList
     */
    public function getFilteredModules($filter = array(), $listIsExclusive = false)
    {
        return $this
            ->getInstalledLibraries()
            ->filter($filter, $listIsExclusive);
    }

    /**
     * Cache of installed library objects
     *
     * @var LibraryList
     */
    protected $installedLibraries = null;

    /**
     * Find all installed modules.
     * Note; Does not check inclusion / exclusion rules.
     *
     * @return LibraryList
     */
    public function getInstalledLibraries() {
        if ($this->installedLibraries) {
            return $this->installedLibraries;
        }
        $this->installedLibraries = new LibraryList();

        // Search all directories
        foreach ($this->getDirectories() as $dir) {
            $module = $this->createModule($dir);
            if ($module) {
                $this->installedLibraries->add($module);
            }
        }
        return $this->installedLibraries;
    }

    /**
     * Find all candidate module dirs
     *
     * @return Generator
     */
    protected function getDirectories() {
        // Search all directories
        foreach (glob($this->directory."/*", GLOB_ONLYDIR) as $baseDir) {
            yield $baseDir;
        }

        // Add vendor modules
        foreach (glob($this->directory."/vendor/*", GLOB_ONLYDIR) as $vendorDir) {
            foreach (glob($vendorDir."/*", GLOB_ONLYDIR) as $moduleDir) {
                yield $moduleDir;
            }
        }

        // Add themes
        foreach (glob($this->directory."/themes/*", GLOB_ONLYDIR) as $themeDir) {
            yield $themeDir;
        }
    }

    /**
     * Get a module by name
     *
     * @param string $path
     * @return Library
     */
    protected function createModule($path)
    {
        if (Module::isModulePath($path)) {
            return new Module($path, $this);
        }

        if (Library::isLibraryPath($path)) {
            return new Library($path, $this);
        }

        return null;
    }

    public function getMainDirectory()
    {
        // Look in mysite for main content
        return $this->getDirectory() . '/mysite';
    }
}
