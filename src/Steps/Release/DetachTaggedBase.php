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

            $priorVersion = $libVersion->getPriorVersionFromTags($lib->getTags(), false);

            $headCommitHash = trim($repo->getHeadCommit()->getHash());

            $base = trim($repo->run('merge-base', [$priorVersion->getOriginalString(), $headCommitHash]));

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
