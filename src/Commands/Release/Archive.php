<?php

namespace SilverStripe\Cow\Commands\Release;

use SilverStripe\Cow\Steps\Release\BuildArchive;
use SilverStripe\Cow\Steps\Release\WaitStep;

/**
 * Create local archives for this release to upload later to s3
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
        $project = $this->getProject();
        $releasePlan = $this->getReleasePlan();
        $repository = $this->getInputRepository();

        // Ensure we wait, even if just building archive
        $wait = new WaitStep($this, $project, $releasePlan);
        $wait->run($this->input, $this->output);

        // Create packages
        $package = new BuildArchive($this, $project, $releasePlan, $repository);
        $package->run($this->input, $this->output);
    }
}
