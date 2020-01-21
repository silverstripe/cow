<?php

namespace SilverStripe\Cow\Steps\Release;

use Exception;
use SilverStripe\Cow\Commands\Command;
use SilverStripe\Cow\Model\Modules\Project;
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
     * @param Command $command
     * @param Project $project
     * @param Version $version
     * @param string $branching Override branching strategy
     * @param ProgressBar $progressBar
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
        $name = $this->project->getName();
        $version = $this->version->getValue();

        foreach ($this->project->getAllChildren() as $lib) {
            $repo = $lib->getRepository($output);

            $path = $repo->getPath();

            $planItem = $this->plan->getItem($lib->getName());

            if (!$planItem) {
                $this->log($output, "Skipped module {$lib->getName()} (couldn't find in the Plan)");
                continue;
            }

            $libVersion = $planItem->getVersion();

            if (!$libVersion) {
                $this->log($output, "Skipped module {$lib->getName()} (couldn't determine the version)");
                continue;
            }

            $priorVersion = $libVersion->getPriorVersionFromTags($lib->getTags(), false);

            $headCommitHash = trim($repo->getHeadCommit()->getHash());

            $base = trim($repo->run('merge-base', [$priorVersion->getValue(), $headCommitHash]));

            if ($base !== $headCommitHash) {
                $commitsCount = trim($repo->run('rev-list', ['--count', "$base...HEAD"]));
                $this->log(
                    $output,
                    "Detaching '<fg=green>{$lib->getName()}</>', " .
                    "'<fg=yellow>{$base}</>' (<fg=yellow>$commitsCount</> commits behind)"
                );
                $repo->run('checkout', [$base]);
            }
        }
    }
}
