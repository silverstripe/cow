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
                $release = $this->generateUpgradeRelease($parent, $childModule);
            } else {
                $release = $this->proposeNewReleaseVersion($parent, $childModule);
            }
            $plan->addChildItem($parent, $release);

            // If this release tag doesn't match an existing tag, then recurse

            // @todo finish this
            throw new Exception("Not implemented");
        }
    }

    /**
     * Determine the best existing stable tag to upgrade a dependency to
     *
     * @param LibraryRelease $parentRelease
     * @param Library $childModule
     * @return LibraryRelease
     * @throws Exception
     */
    protected function generateUpgradeRelease(LibraryRelease $parentRelease, Library $childModule) {
        // Get tags and composer constraint to filter by
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
            return new LibraryRelease($childModule, $candidate);
        }

        // Get all stable tags that match the given composer constraint
        $candidates = $constraint->filterVersions($tags);
        foreach($candidates as $tag => $version) {
            if (!$version->isStable()) {
                unset($candidates[$tag]);
            }
        }

        // Check if we have any candidates left
        if (empty($candidates)) {
            throw new Exception(
                "Library " . $childModule->getName() . " has no available stable tags that matches "
                . $constraint->getValue()
                . ". Please remove upgrade-only for this module, or tag a new release."
            );
        }

        // Upgrade to highest version
        $tags = Version::sort($candidates, 'descending');
        $candidate = reset($tags);
        return new LibraryRelease($childModule, $candidate);
    }

    /**
     * Propose a new version to tag for a given dependency
     *
     * @param LibraryRelease $parentRelease
     * @param Library $childModule
     * @return mixed|Version
     * @throws Exception
     */
    protected function proposeNewReleaseVersion(LibraryRelease $parentRelease, Library $childModule) {
        // Get tags and composer constraint to filter by
        $tags = $childModule->getTags();
        $constraint = $parentRelease->getLibrary()->getChildConstraint(
            $childModule->getName(),
            $parentRelease->getVersion()
        );

        // Upgrade to self.version
        if ($constraint->isSelfVersion()) {
            $candidate = $parentRelease->getVersion();

            // If this is already tagged, just upgrade without a new release
            if (array_key_exists($candidate->getValue(), $tags)) {
                return new LibraryRelease($childModule, $candidate);
            }

            // Get prior version to generate changelog from
            $priorVersion = $candidate->getPriorVersionFromTags($tags, $childModule->getName());
            return new LibraryRelease($childModule, $candidate, $priorVersion);
        }

        // Get stability to use for the new tag
        $useSameStability = $parentRelease->getLibrary()->isStabilityInherited();
        if($useSameStability) {
            $stability = $parentRelease->getVersion()->getStability();
            $stabilityVersion = $parentRelease->getVersion()->getStabilityVersion();
        } else {
            $stability = '';
            $stabilityVersion = null;
        }

        // Filter versions
        $candidates = $constraint->filterVersions($tags);
        $tags = Version::sort($candidates, 'descending');

        // Determine which best tag to create (with the correct stability)
        $existingTag = reset($tags);
        if($existingTag) {
            // Increment from to guess next version
            $version = $existingTag->getNextVersion($stability, $stabilityVersion);
        } else {
            // In this case, the lower bounds of the constraint isn't a valid tag,
            // so this is our new candidate
            $version = clone $constraint->getMinVersion();
            $version->setStability($stability);
            $version->setStabilityVersion($stabilityVersion);
        }

        // Report new tag
        $from = $version->getPriorVersionFromTags($tags, $childModule->getName());
        return new LibraryRelease($childModule, $version, $from);
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