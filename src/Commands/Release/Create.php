<?php

namespace SilverStripe\Cow\Commands\Release;

use SilverStripe\Cow\Steps\Release\CreateProject;

/**
 * Creates a new release project
 */
class Create extends Release
{
    /**
     * @var string
     */
    protected $name = 'release:create';

    protected $description = 'Setup a new release';

    protected function fire()
    {
        $version = $this->getInputVersion();
        $recipe = $this->getInputRecipe();
        $directory = $this->getInputDirectory();
        $repository = $this->getInputRepository();

        // Steps
        $step = new CreateProject($this, $version, $recipe, $directory, $repository);
        $step->run($this->input, $this->output);
    }
}
