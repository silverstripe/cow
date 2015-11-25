<?php

namespace SilverStripe\Cow\Steps;

use SilverStripe\Cow\Commands\Command;
use SilverStripe\Cow\Model\Module;
use SilverStripe\Cow\Model\Project;
use SilverStripe\Cow\Steps\Step;

/**
 * Represents a step which iterates across one or more module
 */
abstract class ModuleStep extends Step
{
    /**
     * Parent project which contains the modules
     *
     * @var Project
     */
    protected $project;

    /**
     * List of module names to run on. 'installer' specifies the core project
     *
     * @var array
     */
    protected $modules;

    /**
     * If true, then $modules is the list of modules that should NOT be translated
     * rather than translated.
     *
     * @var bool
     */
    protected $listIsExclusive;

    /**
     * Set version constraint for filtering modules in this step.
     * Will be ignored if $modules is non-empty and $listIsExclusive is false.
     *
     * @var string
     */
    protected $versionConstraint = 'self.version';

    /**
     * Create a step
     *
     * @param Command $command
     * @param string $directory
     * @param array $modules List of module names
     * @param bool $listIsExclusive True if this module list is exclusive, rather than inclusive list
     */
    public function __construct(Command $command, $directory = '.', $modules = array(), $listIsExclusive = false)
    {
        parent::__construct($command);

        $this->project = new Project($directory);
        $this->modules = $modules;
        $this->listIsExclusive = $listIsExclusive;
    }

    /**
     * Get instances of all modules this step should run on
     *
     * @return Module[]
     */
    protected function getModules()
    {
        return $this
            ->getProject()
            ->getModules($this->modules, $this->listIsExclusive, $this->getVersionConstraint());
    }

    /**
     * Get project record
     *
     * @return Project
     */
    public function getProject()
    {
        return $this->project;
    }

    /**
     * @return string
     */
    public function getVersionConstraint()
    {
        return $this->versionConstraint;
    }

    /**
     * @param string $versionConstraint
     * @return $this
     */
    public function setVersionConstraint($versionConstraint)
    {
        $this->versionConstraint = $versionConstraint;
        return $this;
    }
}
