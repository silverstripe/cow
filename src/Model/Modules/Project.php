<?php

namespace SilverStripe\Cow\Model\Modules;

use BadMethodCallException;
use Generator;
use InvalidArgumentException;
use LogicException;

/**
 * Represents information about a project in a given directory
 *
 * Is also the 'silverstripe-installer' module
 */
class Project extends Module
{
    /**
     * List of all libraries registered for this project
     *
     * @var array
     */
    protected $libraries = [];

    /**
     * Cache of module paths
     */
    protected $modulePaths = null;

    /**
     * Whether to fetch tags during plan generation
     *
     * @var bool
     */
    protected $fetchTags = true;

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
        // Note: Support modules without mysite
        return static::isLibraryPath($directory);
    }

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
    protected function cacheModulePaths()
    {
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
    protected function getDirectories()
    {
        // Search all directories
        foreach (glob($this->directory . "/*", GLOB_ONLYDIR) as $baseDir) {
            yield $baseDir;
        }

        // Add vendor modules
        foreach (glob($this->directory . "/vendor/*", GLOB_ONLYDIR) as $vendorDir) {
            foreach (glob($vendorDir . "/*", GLOB_ONLYDIR) as $moduleDir) {
                yield $moduleDir;
            }
        }

        // Add themes
        foreach (glob($this->directory . "/themes/*", GLOB_ONLYDIR) as $themeDir) {
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

    /**
     * Get path to sake executable
     *
     * @return string
     */
    public function getSakePath()
    {
        $candidates = [
            $this->getDirectory() . '/vendor/bin/sake', // New standard location
            $this->getDirectory() . '/vendor/silverstripe/framework/sake',
            $this->getDirectory() . '/framework/sake',
        ];
        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                return $candidate;
            }
        }
        throw new BadMethodCallException("sake bin could not be found in this project");
    }

    /**
     * Return or register new library
     *
     * @param string $name
     * @param Library $parent Main parent for this library
     * @return Library
     */
    public function getOrCreateLibrary($name, Library $parent)
    {
        // Check global store
        if (isset($this->libraries[$name])) {
            return $this->libraries[$name];
        }

        // Create new
        $path = $this->getProject()->findModulePath($name);
        if (empty($path)) {
            throw new LogicException("Required dependency $name is not installed");
        }

        // Build library
        $library = $this->createLibrary($path, $parent);

        // Register and return
        $this->libraries[$name] = $library;
        return $library;
    }

    /**
     * Set whether to fetch tags during plan generation
     *
     * @param bool $fetchTags
     * @return $this
     */
    public function setFetchTags($fetchTags)
    {
        $this->fetchTags = (bool) $fetchTags;
        return $this;
    }

    /**
     * Whether to fetch tags during plan generation
     *
     * @return bool
     */
    public function getFetchTags()
    {
        return $this->fetchTags;
    }

    /**
     * Create a child library
     *
     * @param string $path
     * @param Library $parent
     * @return Library
     */
    protected function createLibrary($path, $parent)
    {
        if (Module::isModulePath($path)) {
            return new Module($path, $parent);
        }

        if (Library::isLibraryPath($path)) {
            return new Library($path, $parent);
        }

        throw new InvalidArgumentException("No module at {$path}");
    }
}
