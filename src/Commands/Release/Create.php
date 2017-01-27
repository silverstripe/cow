<?php

namespace SilverStripe\Cow\Commands\Release;

use SilverStripe\Cow\Steps\Release\CreateProject;

/**
 * Description of Create
 *
 * @author dmooyman
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
        $directory = $this->getInputDirectory($version);
        $security = $this->getInputSecurity();
        $repo = $this->input->getOption('repository');

        // Steps
        $step = new CreateProject($this, $version, $directory, $repo);
        $step->run($this->input, $this->output);
    }
}
