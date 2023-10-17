<?php

namespace SilverStripe\Cow\Steps\Release;

use Exception;
use LogicException;
use SilverStripe\Cow\Application;
use SilverStripe\Cow\Commands\Command;
use SilverStripe\Cow\Model\Changelog\Changelog;
use SilverStripe\Cow\Model\Changelog\ChangelogLibrary;
use SilverStripe\Cow\Model\Modules\Library;
use SilverStripe\Cow\Model\Modules\Project;
use SilverStripe\Cow\Model\Release\ComposerConstraint;
use SilverStripe\Cow\Model\Release\LibraryRelease;
use SilverStripe\Cow\Model\Release\Version;
use SilverStripe\Cow\Utility\ChangelogRenderer;
use SilverStripe\Cow\Utility\Template;
use SilverStripe\Cow\Utility\Twig;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Creates a new changelog
 */
class CreateChangelog extends ReleaseStep
{
    /**
     * @var bool
     */
    protected $includeUpgradeOnly = false;

    /**
     * @var Twig\Environment
     */
    protected $twig;

    /**
     * Use legacy changelog format, hardcoded in the Changelog model
     *
     * @var bool
     */
    protected $useLegacyChangelogFormat = false;

    public function __construct(
        Command $command,
        Project $project,
        LibraryRelease $releasePlan = null,
        Twig\Environment $twig
    ) {
        parent::__construct($command, $project, $releasePlan);
        $this->twig = $twig;
    }

    public function run(InputInterface $input, OutputInterface $output)
    {
        $this->includeUpgradeOnly = $this->getCommand()->getIncludeUpgradeOnly();
        $this->useLegacyChangelogFormat = $this->getCommand()->getChangelogUseLegacyFormat();

        $this->log($output, "Generating changelog content for all releases in this plan");

        // Generate changelogs for each element in this plan
        $this->recursiveGenerateChangelog($output, $this->getReleasePlan());

        $this->log($output, "All changelog generation complete!");
    }

    /**
     * Generate changelogs for this release, and all child nodes
     *
     * @param OutputInterface $output
     * @param LibraryRelease $release
     */
    protected function recursiveGenerateChangelog(OutputInterface $output, LibraryRelease $release)
    {
        // Generate changelog for this library only
        $this->generateChangelog($output, $release);

        // Recurse
        foreach ($release->getItems() as $child) {
            $this->recursiveGenerateChangelog($output, $child);
        }
    }

    /**
     * Generate changelog for this node only
     *
     * @param OutputInterface $output
     * @param LibraryRelease $release
     * @throws Exception
     */
    protected function generateChangelog(OutputInterface $output, LibraryRelease $release)
    {
        // Determine if this library has a changelog configured
        if (!$release->getLibrary()->hasChangelog()) {
            return;
        }

        // Get from version
        $fromVersion = $release->getPriorVersion();
        if (!$fromVersion) {
            $this->log(
                $output,
                "No prior version for library <info>" . $release->getLibrary()->getName() . '</info>, '
                . "skipping changelog for initial release"
            );
            return;
        }

        $this->log(
            $output,
            "Generating changelog for library <info>" . $release->getLibrary()->getName() . '</info>'
            . ' (<comment>' . $fromVersion->getValue() . '</comment> to '
            . '<info>' . $release->getVersion()->getValue() . '</info>)'
        );

        if ($this->includeUpgradeOnly) {
            $this->log(
                $output,
                'Including "upgrade-only" modules in changelog'
            );
        }

        // Given a from version for this release, determine the "from" version for all child dependencies.
        // This does a deep search through composer dependencies and recursively checks out old versions
        // of composer.json to determine historic version information. It will filter any modules marked
        // as upgrade-only unless the --include-upgrade-only option is passed.
        $changelogLibrary = $this->getChangelogLibrary($release, $fromVersion, $this->includeUpgradeOnly);

        // Preview diffs to generate for this changelog
        $count = $changelogLibrary->count();
        $this->log($output, "Found changes in <info>{$count}</info> modules:");
        foreach ($changelogLibrary->getAllItems(true) as $item) {
            $prior = $item->getPriorVersion()->getValue();
            $version = $item->getRelease()->getVersion()->getValue();
            $name = $item->getRelease()->getLibrary()->getName();
            $this->log($output, " * <info>{$name}</info> from <info>{$prior}</info> to <info>{$version}</info>");
        }

        // Generate markdown from plan
        $changelog = new Changelog($changelogLibrary);
        /** @var \SilverStripe\Cow\Commands\Release\Changelog $command */
        $command = $this->getCommand();
        $changelog->setIncludeOtherChanges($command->getIncludeOtherChanges());

        if ($since = $command->getChangelogSince())
        {
            $changelog->setForceDateRange($since);
        }

        if ($changelog->getIncludeOtherChanges()) {
            $this->log($output, 'Including "other changes" in changelog');
        }

        if ($this->useLegacyChangelogFormat) {
            $content = $changelog->getMarkdown(
                $output,
                $changelog->getRootLibrary()->getRelease()->getLibrary()->getChangelogFormat()
            );
        } else if ($command->getChangelogGroupByContributor()) {
            $content = $changelog->getMarkdown(
                $output,
                Changelog::FORMAT_GROUPED_BY_CONTRIBUTOR
            );
        } else {
            $content = $this->renderChangelogLogs($output, $changelog);
        }

        // Store this changelog
        $this->storeChangelog($output, $changelogLibrary, $content);
    }

    /**
     * @param OutputInterface $output
     * @param ChangelogLibrary $changelogLibrary Changelog details
     * @param string $content content to save
     */
    protected function storeChangelog(OutputInterface $output, ChangelogLibrary $changelogLibrary, $content)
    {
        // Determine saving mechanism
        $version = $changelogLibrary->getRelease()->getVersion();
        $library = $changelogLibrary->getRelease()->getLibrary();
        $changelogHolder = $library->getChangelogHolder();
        $changelogTemplatePath = $library->getChangelogTemplatePath();

        // Store in local path
        $path = $library->getChangelogPath($version);

        if ($path) {
            // Generate header
            $fullPath = $changelogHolder->getDirectory() . '/' . $path;
            $existingContent = file_exists($fullPath) ? file_get_contents($fullPath) : null;

            if ($existingContent) {
                $this->log($output, "Updating existing changelog with current logs");
                $fullContent = $this->patchChangelog($output, $existingContent, $content);
            } else {
                $fullContent = $this->renderChangelog($output, $version, $content, $changelogTemplatePath);
            }

            // Write and commit changes
            $this->log($output, "Writing changelog to <info>{$fullPath}</info>");
            $dirname = dirname($fullPath);
            if (!is_dir($dirname)) {
                mkdir($dirname, 0777, true);
            }
            file_put_contents($fullPath, $fullContent);
            $this->commitChanges($output, $changelogHolder, $version, $fullPath);
        }
    }

    /**
     * Determine historic release plan from a past composer constraint
     *
     * @param LibraryRelease $newRelease
     * @param Version $historicVersion
     * @param bool $includeUpgradeOnly
     * @return ChangelogLibrary Changelog information for a library
     */
    protected function getChangelogLibrary(
        LibraryRelease $newRelease,
        Version $historicVersion,
        bool $includeUpgradeOnly = false
    ): ChangelogLibrary {
        // Build root release node
        $historicRelease = new ChangelogLibrary($newRelease, $historicVersion);

        // Check if "stable only" mode
        $preferStable = $newRelease->getVersion()->isStable();

        // Check all dependencies from this past commit.
        // Note that we flatten both current and historic dependency trees in case dependencies
        // have been re-arranged since the prior tag.
        $pastComposer = null;
        foreach ($newRelease->getAllItems() as $childNewRelease) {
            $childNewReleaseLibrary = $childNewRelease->getLibrary();

            // Unless specifically requested, we need to skip upgrade-only dependencies for changelog generation
            $skipLibrary = !$includeUpgradeOnly && (
                    $childNewReleaseLibrary->isUpgradeOnly() ||
                    $childNewReleaseLibrary->getParent()->isUpgradeOnly()
                );

            if ($skipLibrary) {
                continue;
            }

            /** LibraryRelease $childNewRelease */
            // Lazy-load historic composer content as needed
            if (!isset($pastComposer)) {
                // Get flat composer history up to 6 levels deep
                $pastComposer = $newRelease
                    ->getLibrary()
                    ->getHistoryComposerData($historicVersion->getValue(), true, 6, $preferStable);
            }

            // Check if this release has a historic tag.
            $childReleaseName = $childNewReleaseLibrary->getName();

            // Use an explicitly specified previous version
            $childHistoricVersion = $childNewRelease->getPriorVersion(false);
            if (!$childHistoricVersion) {
                $historicConstraintName = $pastComposer['require'][$childReleaseName];

                if ($childNewRelease->getPriorVersion()) {
                    $childHistoricVersion = $childNewRelease->getPriorVersion();
                } else {
                    // Get oldest existing tag that matches the given constraint as the "from" for changelog purposes.
                    $historicConstraint = new ComposerConstraint(
                        $historicConstraintName,
                        $historicVersion,
                        $childReleaseName
                    );

                    $childHistoricVersion = $childNewReleaseLibrary->getOldestVersionMatching(
                        $historicConstraint,
                        $childNewRelease->getVersion()->isStable()
                    );
                }
            }

            if (!$childHistoricVersion) {
                throw new LogicException(
                    "No historic version for library {$childReleaseName} matches constraint {$historicConstraintName}"
                );
            }

            // Check if to == from version
            if ($childHistoricVersion->getValue() === $childNewRelease->getVersion()->getValue()) {
                continue;
            }

            // Add this module as a changelog
            $childChangelog = new ChangelogLibrary($childNewRelease, $childHistoricVersion);
            $historicRelease->addItem($childChangelog);
        }

        return $historicRelease;
    }

    /**
     * Takes an existing changelog and patches in new logs via the delimiters
     *
     * @param OutputInterface $output
     * @param string $existingContent
     * @param string $logs
     * @return string
     */
    protected function patchChangelog(OutputInterface $output, string $existingContent, string $logs): string
    {
        $renderer = new ChangelogRenderer();

        // Warn when logs will be appended
        if (strpos($existingContent, ChangelogRenderer::TOP_DELIMITER) === false) {
            $this->log(
                $output,
                "Warning: Log regeneration delimiters not found. Logs will be appended to existing content.",
                "error"
            );
        }

        return $renderer->updateChangelog($existingContent, $logs);
    }

    protected function renderChangelogLogs(OutputInterface $output, Changelog $changelog): string
    {
        $changelogLibrary = $changelog->getRootLibrary();
        $release = $changelogLibrary->getRelease();
        $library = $release->getLibrary();
        $version = $release->getVersion();

        $changelogAuditMode = $this->getCommand()->getChangelogAuditMode();
        if ($changelogAuditMode) {
            $template = 'changelog/logs/audit_mode.md.twig';
        } elseif ($library->getChangelogPath($version)) {
            // the library has changelog-path defined in `.cow.json`
            // if it is set, then we assume it's the recipe we're releasing from
            // (e.g. silverstripe/installer or cwp/kitchen-sink)
            $template = 'changelog/logs/by_module.md.twig';
        } else {
            // otherwise, that's just a single library
            $template = 'changelog/logs/plain.md.twig';
        }

        $changelogData = $changelog->getChangesRenderData($output, $changelogAuditMode);
        $content = $this->twig->render($template, $changelogData);

        // use &apos; because it's a little prettier
        $content = str_replace('&#039;', "&apos;", $content);

        return $content;
    }

    /**
     * Generates a new changelog, using a template if provided
     *
     * @param OutputInterface $output
     * @param Version $version
     * @param string $logs
     * @param string|null $templatePath
     * @return string
     */
    protected function renderChangelog(
        OutputInterface $output,
        Version $version,
        string $logs = '',
        ?string $templatePath = ''
    ): string {
        $renderer = new ChangelogRenderer();

        $fullTemplatePath = implode([
            $this->getProject()->getDirectory(),
            DIRECTORY_SEPARATOR,
            $templatePath
        ]);

        // Fall back to basic output if no template is specified or if the template is missing
        if (is_null($templatePath) || !file_exists($fullTemplatePath)) {
            $this->log($output, "No changelog template found, falling back to basic output");

            return $renderer->renderChangelog($version, $logs);
        }

        // Load the template into memory and render it with context
        $template = file_get_contents($fullTemplatePath);
        $content = $renderer->renderChangelogWithTemplate($template, $version, $logs);

        if (strpos($content, ChangelogRenderer::TOP_DELIMITER) === false) {
            $this->log($output, "Warning: Logs not included in changelog template", "error");
        }

        return $content;
    }

    public function getStepName()
    {
        return 'changelog';
    }

    /**
     * Commit changes to git
     *
     * @param OutputInterface $output
     * @param Library $library
     * @param Version $version
     * @param string $path
     */
    public function commitChanges(OutputInterface $output, Library $library, Version $version, $path)
    {
        $repo = $library->getRepository();
        $versionName = $version->getValue();
        $repo->run("add", array($path));
        $status = $repo->run("status");
        if (stripos($status, 'Changes to be committed:')) {
            $repo->run("commit", array("-m", "MNT Added {$versionName} changelog"));
        }
    }
}
