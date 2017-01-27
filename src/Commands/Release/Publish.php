<?php

namespace SilverStripe\Cow\Commands\Release;

use SilverStripe\Cow\Steps\Release\BuildArchive;
use SilverStripe\Cow\Steps\Release\PushRelease;
use SilverStripe\Cow\Steps\Release\TagModules;
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
        $this
            ->addArgument('version', InputArgument::REQUIRED, 'Exact version tag to release this project as')
            ->addOption('directory', 'd', InputOption::VALUE_REQUIRED, 'Optional directory to release project from')
			->addOption('repository', 'r', InputOption::VALUE_REQUIRED, 'Use a custom repository for the composer project')
            ->addOption('skip-archive-upload', 's', InputOption::VALUE_NONE, 'Skip uploading to AWS')
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
        $version = $this->getInputVersion();
        $directory = $this->getInputDirectory($version);
        $awsProfile = $this->getInputAWSProfile();
        $modules = $this->getReleaseModules($directory);

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
        if (!$this->input->getOption('skip-archive-upload')) {
            $upload = new UploadArchive($this, $version, $directory, $awsProfile);
            $upload->run($this->input, $this->output);
        }
    }

    /**
     * Get the aws profile to use
     *
     * @return silverstripe
     */
    public function getInputAWSProfile()
    {
        return $this->input->getOption('aws-profile');
    }
}
