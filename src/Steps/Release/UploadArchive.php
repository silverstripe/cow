<?php

namespace SilverStripe\Cow\Steps\Release;

use BadMethodCallException;
use SilverStripe\Cow\Commands\Command;
use SilverStripe\Cow\Model\Modules\Project;
use SilverStripe\Cow\Model\Release\LibraryRelease;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Upload tar.gz / zip release archives to ss.org
 *
 * @author dmooyman
 */
class UploadArchive extends ReleaseStep
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
     * Construct new upload command
     *
     * @param Command $command
     * @param Project $project
     * @param LibraryRelease $releasePlan
     * @param string $awsProfile
     */
    public function __construct(
        Command $command,
        Project $project,
        LibraryRelease $releasePlan = null,
        $awsProfile = null
    ) {
        parent::__construct($command, $project, $releasePlan);
        $this->setAwsProfile($awsProfile);
    }

    public function getStepName()
    {
        return 'upload';
    }

    public function run(InputInterface $input, OutputInterface $output)
    {
        // Get recipes and their versions to wait for
        $archives = $this->getArchives($output);
        if (empty($archives)) {
            $this->log($output, "No recipes configured for archive");
            return;
        };

        // Genreate all archives
        $count = count($archives);
        $this->log($output, "Uploading releases for {$count} recipes to AWS s3 bucket");

        foreach ($archives as $archive) {
            foreach ($archive->getFiles() as $file) {
                $this->log($output, "Uploading <info>{$file}</info>");
                $from = $this->getProject()->getDirectory() . '/' . $file;
                if (!file_exists($from)) {
                    throw new BadMethodCallException("Please run cow release:archive before uploading");
                }

                // Cop file
                $to = $this->basePath . '/' . $file;
                $awsProfile = $this->getAwsProfile();

                // Run this
                $arguments = ["aws", "s3", "cp", $from, $to, "--acl", "public-read"];
                if ($awsProfile) {
                    $arguments[] = "--profile";
                    $arguments[] = $awsProfile;
                }
                $this->runCommand($output, $arguments, "Error copying release {$file} to s3");
            }
        }
        $this->log($output, 'Upload complete');
    }

    /**
     * @return string
     */
    public function getAwsProfile()
    {
        return $this->awsProfile;
    }

    /**
     * @param string $awsProfile
     * @return $this
     */
    public function setAwsProfile($awsProfile)
    {
        $this->awsProfile = $awsProfile;
        return $this;
    }
}
