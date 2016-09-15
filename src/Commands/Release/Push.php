<?php

namespace SilverStripe\Cow\Commands\Release;

use SilverStripe\Cow\Steps\Release\PushRelease;

/**
 * Create a new branch
 *
 * @author dmooyman
 */
class Push extends Publish
{
    /**
     * @var string
     */
    protected $name = 'release:push';

    protected $description = 'Push up changes to origin (including tags)';

    protected function fire()
    {
        // Get arguments
        $version = $this->getInputVersion();
        $recipe = $this->getInputRecipe();
        $directory = $this->getInputDirectory($version, $recipe);
        $modules = $this->getReleaseModules($directory);

        // Steps
        $step = new PushRelease($this, $directory, $modules);
        $step->run($this->input, $this->output);
    }
}
