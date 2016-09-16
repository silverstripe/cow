<?php


namespace SilverStripe\Cow\Steps\Release;


use Exception;
use SilverStripe\Cow\Commands\Command;
use SilverStripe\Cow\Model\Modules\Library;
use SilverStripe\Cow\Model\Release\LibraryRelease;
use SilverStripe\Cow\Model\Modules\Project;
use SilverStripe\Cow\Model\Release\Release;
use SilverStripe\Cow\Model\Release\Version;
use SilverStripe\Cow\Steps\Step;
use Symfony\Component\Console\Input\InputInterface;
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
     * @param LibraryRelease $parent Parent releas object
     */
    protected function generateChildReleases(Release $plan, LibraryRelease $parent) {
        // Get children
        $childModules = $parent->getLibrary()->getChildren();
        foreach($childModules as $childModule) {
            // Get constraint and existing tags

            // Guess next version
            if ($parent->getLibrary()->isChildUpgradeOnly($childModule->getName())) {
                $version = $this->findBestUpgradeVersion($parent, $childModule);
            } else {
                $version = $this->findBestNextVersion($parent, $childModule);
            }
        }

        // Based on how each child is included in the parent, guess the before / after version

        // @todo finish this
        throw new Exception("Not implemented");
    }

    /**
     * Given a parent release and child library, determine the best pre-existing tag to upgrade to
     *
     * @param LibraryRelease $parentRelease
     * @param Library $childModule
     */
    protected function findBestUpgradeVersion(LibraryRelease $parentRelease, Library $childModule) {
        $tags = $childModule->getTags();
        $constraint = $parentRelease->getLibrary()->getChildConstraint(
            $childModule->getName(),
            $parentRelease->getVersion()
        );

        // Upgrade to self.version
        if ($constraint->isSelfVersion()) {
            $candidate = $parentRelease->getVersion();
            if (!array_key_exists($candidate->getValue(), $tags)) {
                throw new Exception(
                    "Library " . $childModule->getName() . " cannot be upgraded to version "
                    . $candidate->getValue() . " without a new release"
                );
            }
            return $candidate;
        }

        // Filter versions
        $candidates = $constraint->filterVersions($tags);
    }

    protected function findBestNextVersion(LibraryRelease $parentReleas, Library $childModule) {
        throw new Exception("Not implemented");
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
     * @param Version $version
     * @return $this
     */
    public function setVersion($version)
    {
        $this->version = $version;
        return $this;
    }

    /**
     * @return Version
     */
    public function getVersion()
    {
        return $this->version;
    }
}