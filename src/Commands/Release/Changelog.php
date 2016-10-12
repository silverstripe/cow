<?php

namespace SilverStripe\Cow\Commands\Release;

use SilverStripe\Cow\Steps\Release\CreateChangelog;
use SilverStripe\Cow\Steps\Release\PlanRelease;

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
        $project = $this->getProject();

        // Build and confirm release plan
        $buildPlan = new PlanRelease($this, $project, $version);
        $buildPlan->run($this->input, $this->output);
        $releasePlan = $buildPlan->getReleasePlan();

        // Generate changelog
        $changelogs = new CreateChangelog($this, $project, $releasePlan);
        $changelogs->run($this->input, $this->output);
    }
}
