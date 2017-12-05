<?php

namespace SilverStripe\Cow\Commands\Release;

use Exception;
use SilverStripe\Cow\Model\Release\LibraryRelease;
use SilverStripe\Cow\Steps\Release\BuildArchive;
use SilverStripe\Cow\Steps\Release\PublishRelease;
use SilverStripe\Cow\Steps\Release\UploadArchive;
use SilverStripe\Cow\Steps\Release\WaitStep;
use Symfony\Component\Console\Input\InputOption;

/**
 * Top level publish command
 */
class Publish extends Release
{
    protected $name = 'release:publish';

    protected $description = 'Publish results of this release';

    protected function configureOptions()
    {
        parent::configureOptions();
        $this
            ->addOption('skip-archive-upload', null, InputOption::VALUE_NONE, 'Skip archive & upload to AWS')
            ->addOption(
                'skip-upload',
                null,
                InputOption::VALUE_NONE,
                'Skip uploading to AWS, but still generates archives'
            )
            ->addOption(
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
        $awsProfile = $this->getInputAWSProfile();
        $repository = $this->getInputRepository();

        // Does bulk of module publishing, rewrite of dev branches, rewrite of tags, and actual tagging
        $publish = new PublishRelease($this, $project, $releasePlan);
        $publish->run($this->input, $this->output);

        // Once pushed, wait until installable
        $wait = new WaitStep($this, $project, $releasePlan);
        $wait->run($this->input, $this->output);

        // Create packages
        if (!$this->skipArchive()) {
            $package = new BuildArchive($this, $project, $releasePlan, $repository);
            $package->run($this->input, $this->output);
        }

        // Upload
        if (!$this->skipUpload()) {
            $upload = new UploadArchive($this, $project, $releasePlan, $awsProfile);
            $upload->run($this->input, $this->output);
        }
    }

    /**
     * Is archive skipped?
     *
     * @return mixed
     */
    protected function skipArchive()
    {
        return $this->input->getOption('skip-archive-upload');
    }

    /**
     * Is uploading skipped?
     *
     * @return bool
     */
    protected function skipUpload()
    {
        return $this->input->getOption('skip-upload') || $this->input->getOption('skip-archive-upload');
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
    protected function getReleasePlan()
    {
        $plan = $this->getProject()->loadCachedPlan();
        if (empty($plan)) {
            throw new Exception("Please run 'cow release' before 'cow release:publish'");
        }
        return $plan;
    }
}
