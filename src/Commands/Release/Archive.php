<?php

namespace SilverStripe\Cow\Commands\Release;

use SilverStripe\Cow\Steps\Release\BuildArchive;

/**
 * Create archives
 *
 * @author dmooyman
 */
class Archive extends Publish
{
    /**
     * @var string
     */
    protected $name = 'release:archive';

    protected $description = 'Create archives for the release in tar.gz and zip formats';

    protected function fire()
    {
        // Get arguments
        $version = $this->getInputVersion();
        $recipe = $this->getInputRecipe();
        $directory = $this->getInputDirectory($version, $recipe);

        // Steps
        $step = new BuildArchive($this, $version, $directory);
        $step->run($this->input, $this->output);
    }
}
