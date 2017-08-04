<?php

namespace SilverStripe\Cow\Steps\Release;

use Generator;
use SilverStripe\Cow\Commands\Command;
use SilverStripe\Cow\Model\Modules\Project;
use SilverStripe\Cow\Model\Release\Archive;
use SilverStripe\Cow\Model\Release\LibraryRelease;
use SilverStripe\Cow\Steps\Step;
use Symfony\Component\Console\Output\OutputInterface;

abstract class ReleaseStep extends Step
{
    /**
     * @var LibraryRelease
     */
    protected $releasePlan;

    /**
     * @var Project
     */
    protected $project;

    /**
     * Create release step
     *
     * @param Command $command
     * @param Project $project
     * @param LibraryRelease $releasePlan
     */
    public function __construct(Command $command, Project $project, LibraryRelease $releasePlan = null)
    {
        parent::__construct($command);
        $this->setProject($project);
        $this->setReleasePlan($releasePlan);
    }

    /**
     * @return LibraryRelease
     */
    public function getReleasePlan()
    {
        return $this->releasePlan;
    }

    /**
     * @param LibraryRelease $releasePlan
     * @return $this
     */
    public function setReleasePlan($releasePlan)
    {
        $this->releasePlan = $releasePlan;
        return $this;
    }

    /**
     * @return Project
     */
    public function getProject()
    {
        return $this->project;
    }

    /**
     * @param Project $project
     * @return $this
     */
    public function setProject($project)
    {
        $this->project = $project;
        return $this;
    }

    /**
     * Get all libraries to be tagged in this release. Excludes upgrade-only items
     *
     * @return LibraryRelease[]|Generator
     */
    public function getNewReleases()
    {
        foreach ($this->getReleases() as $item) {
            if ($item->getIsNewRelease()) {
                yield $item;
            }
        }
    }

    /**
     * Get all releases
     *
     * @return LibraryRelease[]|Generator
     */
    public function getReleases()
    {
        // Add root item
        yield $this->getReleasePlan();
        foreach ($this->getReleasePlan()->getAllItems() as $item) {
            yield $item;
        }
    }
    /**
     * Get all archive files to publish
     *
     * @param OutputInterface $output
     * @return Archive[] List of archives to generate, key is recipe name
     */
    protected function getArchives(OutputInterface $output)
    {
        $archives = [];
        foreach ($this->getProject()->getArchives() as $archive) {
            // Get release from release plan
            $release = $this->getReleasePlan()->getItem($archive['recipe']);
            if ($release) {
                $archives[$archive['recipe']] = new Archive($release, $archive['files']);
            } else {
                $this->log(
                    $output,
                    "<error>Warning: Archive recipe {$archive['recipe']} is not a part of this release</error>"
                );
            }
        }
        return $archives;
    }
}
