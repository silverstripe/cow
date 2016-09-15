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
     * Cache of module paths
     */
    protected $modulePaths = null;

    /**
     * Find directory for given module name
     *
     * @param string $name
     * @return string
     */
    public function findModulePath($name)
    {
        $paths = $this->cacheModulePaths();
        if (isset($paths[$name])) {
            return $paths[$name];
        }
        return null;
    }

    /**
     * Cache all module paths
     *
     * @return array Map of module names to path
     */
    protected function cacheModulePaths() {
        if (isset($this->modulePaths)) {
            return $this->modulePaths;
        }

        // Cache all paths
        $this->modulePaths = [];
        foreach ($this->getDirectories() as $dir) {
            if ($this->isLibraryPath($dir)) {
                $composerData = json_decode(file_get_contents($dir . '/composer.json'), true);
                $this->modulePaths[$composerData['name']] = $dir;
            }
        }
        return $this->modulePaths;
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

    public function getMainDirectory()
    {
        // Look in mysite for main content
        return $this->getDirectory() . '/mysite';
    }

    public function getProject()
    {
        return $this;
    }
}
