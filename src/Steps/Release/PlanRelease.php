<?php

namespace SilverStripe\Cow\Steps\Release;

use Exception;
use SilverStripe\Cow\Commands\Command;
use SilverStripe\Cow\Commands\Release\Branch;
use SilverStripe\Cow\Model\Changelog\Changelog;
use SilverStripe\Cow\Model\Changelog\ChangelogItem;
use SilverStripe\Cow\Model\Changelog\ChangelogLibrary;
use SilverStripe\Cow\Model\Modules\Project;
use SilverStripe\Cow\Model\Release\LibraryRelease;
use SilverStripe\Cow\Model\Release\Version;
use SilverStripe\Cow\Service\VersionResolver;
use SilverStripe\Cow\Steps\Step;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;

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

    /**
     * Generated release plan
     *
     * @var LibraryRelease
     */
    protected $releasePlan;

    /**
     * Branching strategy
     *
     * @var string
     */
    protected $branching = null;

    /**
     * @var ProgressBar
     */
    protected $progressBar;

    /**
     * @return LibraryRelease
     */
    public function getReleasePlan()
    {
        return $this->releasePlan;
    }

    /**
     * @param LibraryRelease $releasePlan
     * @return PlanRelease
     */
    public function setReleasePlan($releasePlan)
    {
        $this->releasePlan = $releasePlan;
        return $this;
    }

    /**
     * Build new plan step
     *
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
        $branching,
        ProgressBar $progressBar
    ) {
        parent::__construct($command);
        $this->setProject($project);
        $this->setVersion($version);
        $this->setBranching($branching);
        $this->setProgressBar($progressBar);
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
        $this->buildInitialPlan($output);

        // Review with author
        $this->reviewPlan($output, $input);
    }

    /**
     * Generate a draft plan for the current project based on configuration and automatic best-guess
     *
     * @param OutputInterface $output
     */
    protected function buildInitialPlan(OutputInterface $output)
    {
        // Load cached value
        $moduleRelease = $this->getProject()->loadCachedPlan();
        $branching = $this->getBranching();
        if ($moduleRelease) {
            $this->log($output, 'Loading cached release plan from prior session');
            // Note: Branching can be overridden on the CLI. Save this to cached plan in this case
            if ($branching && $branching !== $moduleRelease->getBranching()) {
                $this->log($output, "Updating branching to <info>{$branching}</info>");
                $moduleRelease->setBranching($branching);
                $this->getProject()->saveCachedPlan($moduleRelease);
            }
            $this->setReleasePlan($moduleRelease);
            return;
        }

        // Generate a suggested release
        $this->log($output, 'Automatically building a suggested release plan');
        $moduleRelease = new LibraryRelease($this->getProject(), $this->getVersion());
        $this->generateChildReleases($moduleRelease);

        // Set branching if specified on CLI
        if ($branching) {
            $moduleRelease->setBranching($branching);
        }

        // Save plan
        $this->getProject()->saveCachedPlan($moduleRelease);
        $this->setReleasePlan($moduleRelease);
    }

    /**
     * Recursively generate a plan for this parent recipe
     *
     * @param LibraryRelease $parent Parent release object
     * @throws Exception
     */
    protected function generateChildReleases(LibraryRelease $parent)
    {
        // Get children
        $childModules = $parent->getLibrary()->getChildrenExclusive();
        foreach ($childModules as $childModule) {
            // For the given child module, guess the upgrade mechanism (upgrade or new tag)
            $resolver = new VersionResolver($childModule, $parent);
            $release = $resolver->createRelease();
            $parent->addItem($release);

            // If this release tag doesn't match an existing tag, then recurse.
            // If the tag exists, then we are simply updating the dependency to
            // an existing tag, so there's no need to recursie.
            $tags = $childModule->getTags();
            if (!array_key_exists($release->getVersion()->getValue(), $tags)) {
                $this->generateChildReleases($release);
            }
        }
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

    /**
     * Get branching strategy
     *
     * @return string
     */
    public function getBranching()
    {
        return $this->branching;
    }

    /**
     * Set branching strategy
     *
     * @param string $branching
     * @return $this
     */
    public function setBranching($branching)
    {
        $this->branching = $branching;
        return $this;
    }

    /**
     * Interactively confirm a plan with the user
     *
     * @param OutputInterface $output
     * @param InputInterface $input
     */
    protected function reviewPlan(OutputInterface $output, InputInterface $input)
    {
        // Get user-descriptive output for plan
        $libraryRelease = $this->getReleasePlan();
        $branching = $libraryRelease->getBranching();
        $releaseLines = $this->getReleaseOptions($libraryRelease);

        // If not interactive, simply output read-only list of versions
        $message = "The below release plan has been generated for this project";
        if (!$input->isInteractive()) {
            $this->log($output, $message);
            $this->log($output, "branching (<info>{$branching}</info>)");
            foreach ($releaseLines as $line) {
                $this->log($output, $line);
            }
            return;
        }

        // Prompt user with query to modify this plan
        $question = new ChoiceQuestion(
            "{$message}; Please confirm any manual changes below, or type a module name to edit the tag:",
            array_merge(
                [
                    "continue" => "continue",
                    "branching" => "modify branching strategy (<info>{$branching}</info>)",
                ],
                $releaseLines
            ),
            "continue"
        );
        $selectedLibrary = $this->getQuestionHelper()->ask($input, $output, $question);

        // Break if plan is accepted
        switch ($selectedLibrary) {
            case 'continue':
                // Good job!
                return;
            case 'branching':
                // Modify branching strategy
                $this->reviewBranching($output, $input);
                break;
            default:
                // Modify selected dependency
                $selectedRelease = $libraryRelease->getItem($selectedLibrary);
                $this->reviewLibraryVersion($output, $input, $selectedRelease);
                break;
        }

        // Recursively update plan
        $this->reviewPlan($output, $input);
    }

    /**
     * Update the version of a selected library
     *
     * @param OutputInterface $output
     * @param InputInterface $input
     * @param LibraryRelease $selectedVersion
     */
    protected function reviewLibraryVersion(
        OutputInterface $output,
        InputInterface $input,
        LibraryRelease $selectedVersion
    ) {
        $versions = [
            'new_version' => [
                'text' => $this->generateReleaseSummary($selectedVersion)
                    . 'Please enter a new version to release for <info>%s</info>: ',
                'default' => $selectedVersion->getVersion(),
            ],
            'prior_version' => [
                'text' => 'Optional: modify the prior version ('
                    . $selectedVersion->getPriorVersion()
                    . ') for <info>%s</info>: ',
                'default' => $selectedVersion->getPriorVersion(),
            ],
        ];

        foreach ($versions as $key => $options) {
            $question = new Question(
                sprintf($options['text'], $selectedVersion->getLibrary()->getName()),
                $options['default']
            );
            $result = $this->getQuestionHelper()->ask($input, $output, $question);

            // Nothing was entered (just enter pressed) so take user back to the plan
            if ($result instanceof Version) {
                // If pressing enter on prior version (optional), finish the loop but don't return
                if ($key === 'prior_version') {
                    continue;
                }
                return;
            }

            // If version is invalid, show an error message
            if (!Version::parse($result)) {
                $this->log(
                    $output,
                    "Invalid version {$result}; Please enter a tag in w.x.y(-[rc|alpha|beta][z]) format",
                    "error"
                );
                $this->reviewLibraryVersion($output, $input, $selectedVersion);
                return;
            }

            // Warn if upgrade-only OR specifying a previous version and selected version isn't an existing tag
            $upgradeOnly = $selectedVersion->getLibrary()->isUpgradeOnly();
            if ($upgradeOnly || $key === 'prior_version') {
                $tags = $selectedVersion->getLibrary()->getTags();
                if (!array_key_exists($result, $tags)) {
                    $this->log(
                        $output,
                        ($upgradeOnly ? 'This library is marked as upgrade-only; ' : '')
                        . "$result is not an existing tag",
                        'error'
                    );
                    $this->reviewLibraryVersion($output, $input, $selectedVersion);
                    return;
                }
            }

            $versions[$key]['result'] = $result;
        }

        // Update release version
        $newVersion = new Version($versions['new_version']['result']);
        $priorVersion = null;
        if (!empty($versions['prior_version']['result'])) {
            $priorVersion = new Version($versions['prior_version']['result']);

            // Validate that the requested previous version is lower than the new version. It is allowed
            // to be the same though.
            if ($priorVersion->compareTo($newVersion) > 0) {
                $this->log(
                    $output,
                    'Prior version ' . $priorVersion->getValue() . ' is not actually lower than '
                    . 'new version ' . $newVersion->getValue(),
                    'error'
                );
                $this->reviewLibraryVersion($output, $input, $selectedVersion);
                return;
            }
        }

        $this->modifyLibraryReleaseVersion($selectedVersion, $newVersion, $priorVersion);

        // Save modified plan to cache immediately
        $this->getProject()->saveCachedPlan($this->getReleasePlan());
    }

    /**
     * Select new branching strategy
     *
     * @param OutputInterface $output
     * @param InputInterface $input
     */
    protected function reviewBranching(OutputInterface $output, InputInterface $input)
    {
        $current = $this->getReleasePlan()->getBranching();
        $question = new ChoiceQuestion(
            "Select branching strategy (current: <info>{$current}</info>)",
            Branch::OPTIONS,
            $current
        );
        $branching = $this->getQuestionHelper()->ask($input, $output, $question);

        // Update and save update
        $this->getReleasePlan()->setBranching($branching);
        $this->setBranching($branching);
        $this->getProject()->saveCachedPlan($this->getReleasePlan());
    }

    /**
     * Build user-visible option selection list based on a prepared plan
     *
     * @param LibraryRelease $node
     * @param int $depth
     * @return array List of options
     */
    protected function getReleaseOptions(LibraryRelease $node, $depth = 0)
    {
        // Start a progress indicator at the top level
        if ($depth === 0) {
            $totalItems = $node->countAllItems(true);
            $this->getProgressBar()->start($totalItems);
            $this->getProgressBar()->advance();
        }

        $options = [];
        // Format / indent this line
        $formatting
            = str_repeat(' ', $depth) . ($depth ? html_entity_decode('&#x2514;', ENT_NOQUOTES, 'UTF-8') . ' ' : '');

        // Get version release information
        if ($node->getIsNewRelease()) {
            $version = ' (<info>' . $node->getVersion()->getValue() . '</info>) new tag';
        } else {
            $version = ' (<comment>' . $node->getVersion()->getValue() . '</comment> existing tag)';
        }

        // Show previous version if it's different from the new version
        $previous = $node->getPriorVersion();
        if ($previous && $previous->getValue() !== $node->getVersion()->getValue()) {
            $version .= ', prior version <comment>' . $previous->getValue() . '</comment>';
        }

        // Build string
        $options[$node->getLibrary()->getName()] = $formatting . $node->getLibrary()->getName() . $version;

        // Build child version options
        foreach ($node->getItems() as $child) {
            $this->getProgressBar()->advance();

            $options = array_merge(
                $options,
                $this->getReleaseOptions($child, $depth ? $depth + 3 : 1)
            );
        }

        if ($depth === 0) {
            $this->getProgressBar()->finish();
            $this->getProgressBar()->clear();
        }

        return $options;
    }

    /**
     * Update selected version of a given library
     *
     * @param LibraryRelease $selectedVersion
     * @param Version $newVersion New version
     * @param Version|null $priorVersion Prior version (optional)
     */
    protected function modifyLibraryReleaseVersion(LibraryRelease $selectedVersion, $newVersion, $priorVersion = null)
    {
        $wasNewRelease = $selectedVersion->getIsNewRelease();

        // Replace tag
        $selectedVersion->setVersion($newVersion);
        if ($priorVersion) {
            $selectedVersion->setPriorVersion($priorVersion);
        }

        // If the "create new release" tag changes, we need to re-generate all child dependencies
        $isNewRelease = $selectedVersion->getIsNewRelease();
        if ($wasNewRelease !== $isNewRelease) {
            // Need to either clear, or regenerate all children
            $selectedVersion->clearItems();

            // Changing to require a new tag will populate children again from scratch
            if ($isNewRelease) {
                $this->generateChildReleases($selectedVersion);
            }
        }
    }

    /**
     * @return ProgressBar
     */
    public function getProgressBar()
    {
        return $this->progressBar;
    }

    /**
     * @param ProgressBar $progressBar
     * @return $this
     */
    public function setProgressBar($progressBar)
    {
        $this->progressBar = $progressBar;
        return $this;
    }

    /**
     * @param LibraryRelease $selectedVersion
     * @return array|string
     */
    protected function generateReleaseSummary(LibraryRelease $selectedVersion)
    {
        // If the release has already been set to not change then there won't be changes
        $priorVersion = $selectedVersion->getPriorVersion();
        if ((string) $selectedVersion->getVersion() === (string)$priorVersion) {
            return '';
        }

        // Create a changelog for just the chosen package
        $changelog = new Changelog(new ChangelogLibrary($selectedVersion, $priorVersion));

        // Prep an output that will contain exception messages (which we can discard)
        $buffer = new BufferedOutput();
        $changes = $changelog->getChanges($buffer);

        // Prep a GitHub link for comparing changes
        $repository = $selectedVersion->getLibrary()->getRepository();
        $commit = $repository->getHeadCommit();
        $githubCompareLink = sprintf(
            'Compare on GitHub: https://github.com/%s/compare/%s...%s',
            $selectedVersion->getLibrary()->getGithubSlug(),
            $priorVersion,
            $commit->getHash()
        );

        // No changes means we will give a no changes message
        if (empty($changes)) {
            return '<comment>Could not find changes to release</comment>' . PHP_EOL .
                $githubCompareLink . PHP_EOL;
        }

        // Prep the counts of the various types of changes
        $typeCount = [
            ChangelogItem::TYPE_SECURITY => 0,
            ChangelogItem::TYPE_API => 0,
            ChangelogItem::TYPE_ENHANCEMENT => 0,
            ChangelogItem::TYPE_BUGFIX => 0,
            ChangelogItem::TYPE_OTHER_CHANGES => 0,
        ];

        // Initialise what will be the array of lines in this summary
        $summary = [];

        // Get the list of "versions" that exist from the given prior version
        $tags = Version::sort(array_map(function ($version) {
            return new Version($version);
        }, array_filter(explode(PHP_EOL, $repository->run(
            'tag', ['--contains', (string)$priorVersion]
        )))));

        // Cull everything that's between the prior version and the latest existing version (based on the constraint)
        $latestExistingVersion = (new VersionResolver(
            $selectedVersion->getLibrary(),
            $this->getReleasePlan()->getParentOfItem($selectedVersion->getLibrary()->getName())
        ))->getLatestExistingVersion();

        $tags = array_filter($tags, function (Version $version) use ($latestExistingVersion, $priorVersion) {
            return $version->compareTo($latestExistingVersion) <= 0 && $version->compareTo($priorVersion) >= 0;
        });

        $nextExpectedTag = array_shift($tags);
        // Loop the changes to get a message for each
        foreach ($changes as $change) {
            $typeCount[$change->getType()]++;

            $tagsWithCommit = array_filter(explode(PHP_EOL, $repository->run(
                'tag', ['--contains', (string) $change->getCommit()->getHash()]
            )));

            $tagDetail = [];
            while ($nextExpectedTag && in_array((string) $nextExpectedTag, $tagsWithCommit)) {
                $tagDetail[] = $nextExpectedTag;
                $nextExpectedTag = array_shift($tags);
            }
            if (count($tagDetail)) {
                $tagDetail = implode(' ', $tagDetail);
                $summary[] = "      <comment>-> {$tagDetail}</comment>";
            }

            $summary[] = '    <info>*</info> ' . $change->getMessage();
        }

        // Exclude categories that have no changes
        $typeCount = array_filter($typeCount);

        // Prep strings that summarise the count of each type of changes
        $countStrings = [];
        foreach ($typeCount as $type => $count) {
            $countStrings[] = $count . ' ' . strtolower($type);
        }

        // Prepend a lead-in
        array_unshift($summary, sprintf(
            'Since prior version (%s): <comment>%s changes (%s)</comment>',
            $priorVersion,
            array_sum($typeCount),
            implode(', ', $countStrings)
        ));

        // And append a link to compare with
        $summary[] = $githubCompareLink;

        return implode(PHP_EOL, $summary) . PHP_EOL;
    }
}
