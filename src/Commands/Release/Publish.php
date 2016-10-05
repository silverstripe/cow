<?php

namespace SilverStripe\Cow\Commands\Release;

use Exception;
use SilverStripe\Cow\Model\Release\LibraryRelease;
use SilverStripe\Cow\Steps\Release\BuildArchive;
use SilverStripe\Cow\Steps\Release\PushRelease;
use SilverStripe\Cow\Steps\Release\TagModules;
use SilverStripe\Cow\Steps\Release\CreateBranches;
use SilverStripe\Cow\Steps\Release\UploadArchive;
use SilverStripe\Cow\Steps\Release\Wait;
use Symfony\Component\Console\Input\InputArgument;
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
        /*
        $version = $this->getInputVersion();
        $recipe = $this->getInputRecipe();
        $directory = $this->getInputDirectory($version, $recipe);
        $awsProfile = $this->getInputAWSProfile();
        $modules = $this->getReleaseModules($directory);
        */

        $project = $this->getProject();
        $releasePlan = $this->getReleasePlan();

        /*

        // Tag
        $tag = new TagModules($this, $version, $directory, $modules);
        $tag->run($this->input, $this->output);

        // Push tag & branch
        $push = new PushRelease($this, $directory, $modules);
        $push->run($this->input, $this->output);

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
