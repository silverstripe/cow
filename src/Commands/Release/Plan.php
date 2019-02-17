<?php


namespace SilverStripe\Cow\Commands\Release;

use SilverStripe\Cow\Steps\Release\PlanRelease;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

/**
 * Builds a release plan
 */
class Plan extends Release
{
    protected $name = 'release:plan';

    protected $description = 'Plan release dependencies';

    protected function configureOptions()
    {
        parent::configureOptions();

        $this->addOption(
            'assume-no-releases',
            null,
            InputOption::VALUE_NONE,
            'Indicate that the plan should not assume that a release will be made for each library (except recipes)'
        );

        $this->addOption(
            'release',
            null,
            InputOption::VALUE_REQUIRED,
            'Comma delimited list of modules to release when used in combination with --no-releases'
        );
    }

    protected function fire()
    {
        // Get arguments
        $version = $this->getInputVersion();
        $project = $this->getProject();
        $branching = $this->getBranching();

        // Build and confirm release plan
        $buildPlan = new PlanRelease($this, $project, $version, $branching);
        $buildPlan->run($this->input, $this->output);
    }
}
