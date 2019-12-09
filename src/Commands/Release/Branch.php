<?php

namespace SilverStripe\Cow\Commands\Release;

use SilverStripe\Cow\Steps\Release\PlanRelease;
use SilverStripe\Cow\Steps\Release\RewriteReleaseBranches;

/**
 * Create branches for this release
 */
class Branch extends Release
{
    /**
     * @var string
     */
    protected $name = 'release:branch';

    protected $description = 'Branch all modules';

    /**
     * Will branch to minor version (e.g. 1.1) for all stable releases only
     */
    public const AUTO = 'auto'; // Automatic branching

    /**
     * Auto description
     */
    public const AUTO_DESCRIPTION = 'Uses <info>minor</info> for stable tags, <info>none</info> for unstable tags';

    /**
     * Branch to major version (e.g. 1) unless already on specific minor branch
     */
    public const MAJOR = 'major';

    /**
     * Major description
     */
    public const MAJOR_DESCRIPTION = 'Branch to major version (e.g. 1) unless already on specific minor branch';

    /**
     * Branch to minor version (E.g. 1.1)
     */
    public const MINOR = 'minor';

    /**
     * Minor description
     */
    public const MINOR_DESCRIPTION = 'Branch to minor version (E.g. 1.1)';

    /**
     * No branching will occur
     */
    public const NONE = 'none';

    /**
     * None description
     */
    public const NONE_DESCRIPTION = 'No branching will occur';

    /**
     * List of valid options
     */
    public const OPTIONS = [
        self::AUTO => self::AUTO_DESCRIPTION,
        self::MAJOR => self::MAJOR_DESCRIPTION,
        self::MINOR => self::MINOR_DESCRIPTION,
        self::NONE => self::NONE_DESCRIPTION,
    ];

    protected function fire()
    {
        // Get arguments
        $version = $this->getInputVersion();
        $project = $this->getProject();
        $branching = $this->getBranching();

        // Build and confirm release plan
        $buildPlan = new PlanRelease($this, $project, $version, $branching, $this->progressBar);
        $buildPlan->run($this->input, $this->output);
        $releasePlan = $buildPlan->getReleasePlan();

        // Branch all modules properly
        $branchAlias = new RewriteReleaseBranches($this, $project, $releasePlan);
        $branchAlias->run($this->input, $this->output);
    }
}
