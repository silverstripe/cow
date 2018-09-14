<?php

namespace SilverStripe\Cow\Commands\Release;

use SilverStripe\Cow\Steps\Release\CreateChangelog;
use SilverStripe\Cow\Steps\Release\PlanRelease;
use Symfony\Component\Console\Input\InputOption;

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

    protected function configureOptions()
    {
        parent::configureOptions();

        $this->addOption(
            'include-other-changes',
            null,
            InputOption::VALUE_NONE,
            'Include other changes in the changelog (default: false)'
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
        $releasePlan = $buildPlan->getReleasePlan();

        // Generate changelog
        $changelog = new CreateChangelog($this, $project, $releasePlan);
        $changelog->run($this->input, $this->output);
    }

    /**
     * Whether to include all commits in the changelog
     *
     * @return bool
     */
    public function getIncludeOtherChanges()
    {
        return (bool) $this->input->getOption('include-other-changes');
    }
}
