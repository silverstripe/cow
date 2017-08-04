<?php

namespace SilverStripe\Cow\Commands\Release;

use SilverStripe\Cow\Steps\Release\UploadArchive;

/**
 * Uploads to silverstripe.org
 *
 * @author dmooyman
 */
class Upload extends Publish
{
    /**
     * @var string
     */
    protected $name = 'release:upload';

    protected $description = 'Uploads archiveds to silverstripe.org';

    protected function fire()
    {
        // Get arguments
        $project = $this->getProject();
        $releasePlan = $this->getReleasePlan();
        $awsProfile = $this->getInputAWSProfile();

        // Steps
        $upload = new UploadArchive($this, $project, $releasePlan, $awsProfile);
        $upload->run($this->input, $this->output);
    }
}
