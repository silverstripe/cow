<?php


namespace SilverStripe\Cow\Steps\Release;


use SilverStripe\Cow\Commands\Command;
use SilverStripe\Cow\Model\Release\LibraryRelease;
use SilverStripe\Cow\Model\Modules\Project;
use SilverStripe\Cow\Model\Release\Release;
use SilverStripe\Cow\Model\Release\Version;
use SilverStripe\Cow\Steps\Step;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;

class PlanRelease extends Step
{
    /**
     * @var Project
     */
    protected $project;

    /**
     * @var Version
     */
    protected $version;

    public function __construct(Command $command, Project $project, Version $version)
    {
        parent::__construct($command);
        $this->setProject($project);
        $this->setVersion($version);
    }

    public function getStepName()
    {
        return 'plan';
    }

    public function run(InputInterface $input, OutputInterface $output)
    {
        $name = $this->getProject()->getName();
        $version = $this->getVersion()->getValue();
        $this->log($output, "Planning release for project {$name} version {$version}");

        // Build initial plan
        $plan = $this->generatePlan($output);

        // Review with author
    }

    protected function generatePlan(OutputInterface $output) {
        $plan = new Release();

        // Build root release version
        $from = $this->getProject()->getFromVersion($this->getVersion());
        $moduleRelease = new LibraryRelease($this->getProject(), $this->getVersion(), $from);
        $plan->addRootItem($moduleRelease);

        // Recursively build child releases
        $this->generateChildReleases($plan, $moduleRelease);
    }

    /**
     * Recursively generate a plan for this parent recipe
     *
     * @param Release $plan
     * @param LibraryRelease $parent
     */
    protected function generateChildReleases(Release $plan, LibraryRelease $parent) {
        // Get children
        $childModules = $parent->getLibrary()->getDirectDependencies();
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
     * @param \SilverStripe\Cow\Model\Version $version
     * @return $this
     */
    public function setVersion($version)
    {
        $this->version = $version;
        return $this;
    }

    /**
     * @return \SilverStripe\Cow\Model\Versions\\SilverStripe\Cow\Model\\SilverStripe\Cow\Model\Release\Version
     */
    public function getVersion()
    {
        return $this->version;
    }
}