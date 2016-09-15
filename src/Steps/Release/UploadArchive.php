<?php

namespace SilverStripe\Cow\Steps\Release;

use SilverStripe\Cow\Commands\Command;
use SilverStripe\Cow\Model\Modules\Project;
use SilverStripe\Cow\Model\Release\Version;
use SilverStripe\Cow\Steps\Step;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Upload tar.gz / zip release archives to ss.org
 *
 * @author dmooyman
 */
class UploadArchive extends Step
{
    /**
     * Path to upload to
     *
     * @var string
     */
    protected $basePath = "s3://silverstripe-ssorg-releases/sssites-ssorg-prod/assets/releases";

    /**
     * AWS profile name
     *
     * @var string
     */
    protected $awsProfile;

    /**
     * @var \SilverStripe\Cow\Model\Versions\Version
     */
    protected $version;

    /**
     * @var Project
     */
    protected $project;

    /**
     * Upload archives
     *
     * @param Command $command
     * @param Version $version
     * @param string $directory Where to translate
     * @param string $awsProfile Name of aws profile to use
     */
    public function __construct(
        Command $command,
        Version $version,
        $directory = '.',
        $awsProfile = 'silverstripe'
    ) {
        parent::__construct($command);

        $this->version = $version;
        $this->awsProfile = $awsProfile;
        $this->project = new Project($directory);
    }

    /**
     * @return Project
     */
    public function getProject()
    {
        return $this->project;
    }

    /**
     * @return \SilverStripe\Cow\Model\Versions\\SilverStripe\Cow\Model\Version
     */
    public function getVersion()
    {
        return $this->version;
    }

    public function getStepName()
    {
        return 'upload';
    }

    public function run(InputInterface $input, OutputInterface $output)
    {
        $this->log($output, "Uploading releases to ss.org");
        foreach ($this->getVersion()->getReleaseFilenames() as $filename) {
            // Build paths
            $this->log($output, "Uploading <info>{$filename}</info>");
            $from = $this->getProject()->getDirectory() . '/' . $filename;
            $to = $this->basePath . '/' . $filename;
            $awsProfile = $this->awsProfile;

            // Run this
            $this->runCommand(
                $output,
                array("aws", "s3", "cp", $from, $to, "--acl", "public-read", "--profile", $awsProfile),
                "Error copying release {$filename} to s3"
            );
        }
        $this->log($output, 'Upload complete');
    }
}
