<?php

namespace SilverStripe\Cow\Commands\Release;

use SilverStripe\Cow\Application;
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
    protected $name = 'release:changelog';

    protected $description = 'Generate changelog';

    private $app;

    public function __construct(Application $app, ...$args)
    {
        parent::__construct(...$args);
        $this->app = $app;
    }

    protected function fire()
    {
        // Get arguments
        $version = $this->getInputVersion();
        $project = $this->getProject();
        $branching = $this->getBranching();

        // Build and confirm release plan
        $buildPlan = new PlanRelease($this, $project, $version, $branching, $this->progressBar);
        $buildPlan->run($this->input, $this->output);
        $releasePlan = $buildPlan->getReleasePlan();

        // Generate changelog
        $changelog = new CreateChangelog($this, $project, $releasePlan, $this->app->createTwigEnvironment());
        $changelog->run($this->input, $this->output);
    }
}
