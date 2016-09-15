<?php

namespace SilverStripe\Cow\Steps\Release;

use SilverStripe\Cow\Commands\Command;
use SilverStripe\Cow\Model\Modules\LibraryList;
use SilverStripe\Cow\Model\Modules\Module;
use SilverStripe\Cow\Model\Modules\Project;
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
     * @return bool
     */
    public function isListIsExclusive()
    {
        return $this->listIsExclusive;
    }

    /**
     * @param bool $listIsExclusive
     * @return $this
     */
    public function setListIsExclusive($listIsExclusive)
    {
        $this->listIsExclusive = $listIsExclusive;
        return $this;
    }

    /**
     * If true, then $modules is the list of modules that should NOT be translated
     * rather than translated.
     *
     * @var bool
     */
    protected $listIsExclusive;

    /**
     * Create a step
     *
     * @param Command $command
     * @param Project $project
     * @param array $modules List of module names
     * @param bool $listIsExclusive True if this module list is exclusive, rather than inclusive list
     */
    public function __construct(Command $command, $project, $modules = array(), $listIsExclusive = false)
    {
        parent::__construct($command);
        $this->setProject($project);
        $this->modules = $modules;
        $this->listIsExclusive = $listIsExclusive;
    }

    /**
     * Get instances of all modules this step should run on
     *
     * @return LibraryList
     */
    protected function getModules()
    {
        return $this
            ->getProject()
            ->getFilteredModules($this->modules, $this->listIsExclusive);
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
     * @param Project $project
     * @return $this
     */
    public function setProject(Project $project) {
        $this->project = $project;
        return $this;
    }
}
