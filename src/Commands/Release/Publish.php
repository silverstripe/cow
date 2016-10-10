<?php

namespace SilverStripe\Cow\Commands\Release;

use Exception;
use SilverStripe\Cow\Model\Release\LibraryRelease;
use SilverStripe\Cow\Steps\Release\PublishRelease;
use Symfony\Component\Console\Input\InputOption;

/**
 * Description of Create
 *
 * @author dmooyman
 */
class Publish extends Release
{
    protected $name = 'release:publish';

    protected $description = 'Publish results of this release';

    protected function configureOptions()
    {
        parent::configureOptions();
        $this->addOption(
            'aws-profile',
            null,
            InputOption::VALUE_REQUIRED,
            "AWS profile to use for upload",
            "silverstripe"
        );
    }

    protected function fire()
    {
        // Get arguments
        $project = $this->getProject();
        $releasePlan = $this->getReleasePlan();

        // Does bulk of module publishing, rewrite of dev branches, rewrite of tags, and actual tagging
        $publish = new PublishRelease($this, $project, $releasePlan);
        $publish->run($this->input, $this->output);

        /*
        // Once pushed, wait until installable
        $wait = new Wait($this, $version);
        $wait->run($this->input, $this->output);

        // Create packages
        $package = new BuildArchive($this, $version, $directory);
        $package->run($this->input, $this->output);

        // Upload
        $upload = new UploadArchive($this, $version, $directory, $awsProfile);
        $upload->run($this->input, $this->output);
        */
    }

    /**
     * Get the aws profile to use
     *
     * @return string
     */
    public function getInputAWSProfile()
    {
        return $this->input->getOption('aws-profile');
    }

    /**
     * @return LibraryRelease
     * @throws Exception
     */
    protected function getReleasePlan() {
        $plan = $this->getProject()->loadCachedPlan();
        if (empty($plan)) {
            throw new Exception("Please run 'cow release' before 'cow release:publish'");
        }
        return $plan;
    }
}
