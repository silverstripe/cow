<?php

namespace SilverStripe\Cow\Model\Modules;

/**
 * A module installed in a project of type silverstripe-module
 */
class Module extends Library
{
    /**
     * Directory where module files exist; Usually the one that sits just below the top level project
     *
     * @return string
     */
    public function getMainDirectory()
    {
        return $this->getDirectory();
    }

    /**
     * Cached link
     *
     * @var string
     */
    protected $link = null;

    /**
     * Check if this is a module
     * @param string $path
     * @return bool
     */
    public static function isModulePath($path)
    {
        if (!static::isLibraryPath($path)) {
            return false;
        }

        // Check for _config
        if (!is_file("$path/_config.php") && !is_dir("$path/_config")) {
            return false;
        }

        // Skip ignored modules
        $name = basename($path);
        $ignore = array('mysite', 'assets', 'vendor');
        return !in_array($name, $ignore);
    }
}
