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
        $sources = $this->getTransifexSources();
        foreach ($sources as $source) {
            if (preg_match('#^(?<dir>.+)\\/(?<file>[^\\/]+)\\.yml$#', $source, $matches)) {
                return $this->getDirectory() . '/' . $matches['dir'];
            }
        }
        return null;
    }

    /**
     * Gets the directories of the JS lang folder.
     *
     * @return array
     */
    public function getJSLangDirectories()
    {
        $sources = $this->getTransifexSources();
        $dirs = [];
        foreach ($sources as $source) {
            // Strip out /src/ dir and trailing file.js
            if (preg_match('#^(?<dir>.+)\\/src\\/(?<file>[^\\/]+)\\.js(on)?$#', $source, $matches)) {
                $dirs[] = $this->getDirectory() . '/' . $matches['dir'];
            }
        }
        return $dirs;
    }

    /**
     * Get list of transifex source files. E.g. lang/en.yml
     *
     * @return string[]
     */
    public function getTransifexSources()
    {
        if (!$this->isTranslatable()) {
            return [];
        }

        $path = $this->getDirectory() . '/.tx/config';
        $content = file_get_contents($path);
        $sources = [];
        foreach (preg_split('~\R~u', $content) as $line) {
            if (preg_match('#source_file\s=\s(?<path>\S+)#', $line, $matches)) {
                $sources[] = $matches['path'];
            }
        }
        return $sources;
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
     * Get path of main directory (with sources, lang, etc) relative to BASE_DIR.
     * This is necessary for i18nTextCollector
     *
     * @return string
     */
    public function getRelativeMainDirectory()
    {
        $dir = $this->getMainDirectory();
        $base = $this->getProject()->getDirectory();

        // Remove base dir (plus /) from main directory
        return substr($dir, strlen($base) + 1);
    }

    /**
     * Determine if this project has a .tx configured
     *
     * @return bool
     */
    public function isTranslatable()
    {
        return $this->getDirectory() && realpath($this->getDirectory() . '/.tx/config');
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
