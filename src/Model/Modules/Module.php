<?php

namespace SilverStripe\Cow\Model\Modules;

/**
 * A module installed in a project of type silverstripe-module
 */
class Module extends Library
{
    /**
     * Gets the module lang dir
     *
     * @return string
     */
    public function getLangDirectory()
    {
        $config = $this->getCowData();
        if (isset($config['directories']['lang'])) {
            $dir = $config['directories']['lang'];
        } else {
            $dir = 'lang';
        }
        return $this->getMainDirectory() . '/' . $dir;
    }

    /**
     * Gets the directories of the JS lang folder.
     *
     * @return array
     */
    public function getJSLangDirectories()
    {
        $config = $this->getCowData();
        if (empty($config['directories']['jslang'])) {
            return [];
        }
        $dir = (array)$config['directories']['jslang'];
        return array_map(function($dir) {
            return $this->getDirectory() . '/' . $dir;
        }, $dir);
    }

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
     * Determine if this project has a .tx configured
     *
     * @return bool
     */
    public function isTranslatable()
    {
        return $this->directory && realpath($this->directory . '/.tx/config');
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
