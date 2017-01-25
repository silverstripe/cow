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
        throw new \Exception("Not implemented");

        // @todo - Implement archive / upload steps

        /*
        // Get arguments
        $version = $this->getInputVersion();
        $directory = $this->getInputDirectory($version);
        $awsProfile = $this->getInputAWSProfile();

        // Steps
        $step = new UploadArchive($this, $version, $directory, $awsProfile);
        $step->run($this->input, $this->output);*/
    }
}
