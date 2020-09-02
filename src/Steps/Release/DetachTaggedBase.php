<?php

namespace SilverStripe\Cow\Steps\Release;

use Exception;
use SilverStripe\Cow\Commands\Command;
use SilverStripe\Cow\Model\Modules\Project;
use SilverStripe\Cow\Model\Release\LibraryRelease;
use SilverStripe\Cow\Model\Release\Version;
use SilverStripe\Cow\Steps\Step;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Go through all the modules and checkout merge-base between
 * the current branch and the last released tag (stable or unstable).
 *
 * Simply speaking, this command checks out the last released commit within current branch,
 * avoiding anything that has been merged into the branch since the tag.
 * This may be helpful for releasing "audited" versions with some
 * cherry-picked patches on top of it.
 */
class DetachTaggedBase extends Step
{
    public const MODULE_RESULT_UNCHANGED = 1;

    public const MODULE_RESULT_SKIPPED = 2;

    public const MODULE_RESULT_DETACHED = 3;

    /**
     * @var Project
     */
    protected $project;

    /**
     * @var Version
     */
    protected $version;

    /**
     * @var ProgressBar
     */
    protected $progressBar;

    /**
     * @var LibraryRelease|null
     */
    protected $plan;

    /**
     * @param Command $command
     * @param Project $project
     * @param Version $version
     * @param ProgressBar $progressBar
     * @throws Exception
     */
    public function __construct(
        Command $command,
        Project $project,
        Version $version,
        ProgressBar $progressBar
    ) {
        parent::__construct($command);
        $this->project = $project;
        $this->version = $version;
        $this->progressBar = $progressBar;
        $this->plan = $this->project->loadCachedPlan();

        if (!$this->plan) {
            throw new Exception('Release Plan has not been generated yet');
        }
    }

    public function getStepName()
    {
        return 'detach tagged base';
    }

    public function run(InputInterface $input, OutputInterface $output)
    {
        $scannedModulePaths = [];

        foreach ($this->project->getAllChildren() as $lib) {
            $repo = $lib->getRepository($output);

            $path = $repo->getPath();

            if (isset($scannedModulePaths[$path])) {
                continue;
            }

            $planItem = $this->plan->getItem($lib->getName());

            if (!$planItem) {
                $this->log($output, "Skipped module '<fg=green>{$lib->getName()}</>' (couldn't find in the Plan)");
                $scannedModulePaths[$path] = self::MODULE_RESULT_SKIPPED;
                continue;
            }

            $libVersion = $planItem->getVersion();

            if (!$libVersion) {
                $this->log($output, "Skipped module '<fg=green>{$lib->getName()}</>' (couldn't determine the version)");
                $scannedModulePaths[$path] = self::MODULE_RESULT_SKIPPED;
                continue;
            }

            // Get the current HEAD of the module
            $headCommitHash = trim($repo->getHeadCommit()->getHash());

            // Figure out if it's upgrade-only (or it's in a recipe that is upgrade-only)
            $isUpgradeOnly = $planItem->getLibrary()->isUpgradeOnly();
            if (!$isUpgradeOnly) {
                $parentRef = $planItem->getLibrary()->getParent();

                while (!$isUpgradeOnly && $parentRef) {
                    $isUpgradeOnly = $parentRef->isUpgradeOnly();
                    $parentRef = $parentRef->getParent();
                }
            }

            // find the latest released version equal or less than libVersion
            $versions = array_filter($lib->getTags(), static function ($version) use ($libVersion) {
                return $libVersion->compareTo($version) >= 0;
            });

            // sort the versions in descending order
            usort($versions, static function ($versionA, $versionB) {
                return $versionB->compareTo($versionA);
            });


            // Identify the priorVersion.
            // Beware, this is NOT the priorVersion from the release plan (.cow.pat.json).
            // Instead, THIS priorVersion is what has already been tagged and what we want
            // to be released as the new module version.
            // Optionally, after running this "detach-tagged-base" command, some security
            // patches may be applied to the module on top of the "priorVersion".
            // One of the use cases is promoting RC1 to Stable. In that case
            // THIS priorVersion could be "2.6.0-RC1", whereas the release plan priorVersion would be "2.5.2".
            if (count($versions)) {
                $priorVersion = $versions[0];
            } else {
                // if there are no versions, assuming the current version might be not tagged
                // this is a "safe" default, but maybe we should "continue" the loop here
                $priorVersion = $libVersion;
            }

            // for Upgrade-Only modules we're aiming at the planned version
            // otherwise, revert to the latest tagged version planned (priorVersion)
            $baseVersion = $isUpgradeOnly ? $libVersion->getValue() : $priorVersion->getValue();

            $base = trim($repo->run('merge-base', [$baseVersion, $headCommitHash]));

            if ($base !== $headCommitHash) {
                $commitsCount = trim($repo->run('rev-list', ['--count', "$base...HEAD"]));
                $this->log(
                    $output,
                    "Detaching '<fg=green>{$lib->getName()}</>', " .
                    "'<fg=yellow>{$base}</>' (<fg=yellow>$commitsCount</> commits behind)"
                );
                $repo->run('checkout', [$base]);

                $scannedModulePaths[$path] = self::MODULE_RESULT_DETACHED;
                continue;
            }

            $this->log($output, "Module '<fg=green>{$lib->getName()}</>' already in correct position");
            $scannedModulePaths[$path] = self::MODULE_RESULT_UNCHANGED;
        }
    }
}
