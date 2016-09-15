<?php

namespace SilverStripe\Cow\Commands\Release;

use SilverStripe\Cow\Steps\Release\CreateChangelog;

/**
 * Description of Create
 *
 * @author dmooyman
 */
class Changelog extends Release
{
    /**
     *
     * @var string
     */
    protected $name = 'release:changelog';

    protected $description = 'Generate changelog';

    protected function fire()
    {
        // Get arguments
        $version = $this->getInputVersion();
        $fromVersion = $this->getInputFromVersion($version);
        $recipe = $this->getInputRecipe();
        $directory = $this->getInputDirectory($version, $recipe);
        $modules = $this->getReleaseModules($directory);

        // Steps
        $step = new CreateChangelog($this, $version, $fromVersion, $directory, $modules);
        $step->run($this->input, $this->output);
    }
}
