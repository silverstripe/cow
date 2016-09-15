<?php

namespace SilverStripe\Cow\Model;

use Gitonomy\Git\Exception\ReferenceNotFoundException;
use SilverStripe\Cow\Model\Modules\Module;
use SilverStripe\Cow\Model\Release\Version;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * A changelog which can be generated from a project
 */
class Changelog
{
    /**
     * List of source modules
     *
     * @var Module[]
     */
    protected $modules;

    /**
     * @var Version
     */
    protected $fromVersion;

    /**
     * Groups changes by type (e.g. bug, enhancement, etc)
     */
    const FORMAT_GROUPED = 'grouped';

    /**
     * Formats list as flat list
     */
    const FORMAT_FLAT = 'flat';

    /**
     * Create a new changelog
     *
     * @param Module[] $modules Source of modules to generate changelog from
     * @param Version $fromVersion
     */
    public function __construct(array $modules, Version $fromVersion)
    {
        $this->modules = $modules;
        $this->fromVersion = $fromVersion;
    }

    /**
     * Get the list of changes for this module
     *
     * @param OutputInterface $output
     * @param Module $module
     * @return array
     */
    protected function getModuleLog(OutputInterface $output, Module $module)
    {
        $items = array();

        // Get raw log
        $fromVersion = $this->fromVersion->getValue();
        $range = $fromVersion."..HEAD";
        try {
            $log = $module->getRepository()->getLog($range);

            foreach ($log->getCommits() as $commit) {
                $change = new ChangelogItem($module, $commit);

                // Skip ignored items
                if (!$change->isIgnored()) {
                    $items[] = $change;
                }
            }
        } catch (ReferenceNotFoundException $ex) {
            $moduleName = $module->getInstalledName();
            $output->writeln(
                "<error>Module {$moduleName} does not have from-version {$fromVersion}; "
                    . "Skipping changelog for this module</error>"
            );
        }
        return $items;
    }

    /**
     * Get all changes grouped by type
     *
     * @param OutputInterface $output
     * @return ChangelogItem[]
     */
    protected function getGroupedChanges(OutputInterface $output)
    {
        // Sort by type
        $changes = $this->getChanges($output);
        return $this->sortByType($changes);
    }

    /**
     * Gets all changes in a flat list
     *
     * @param OutputInterface $output
     * @return ChangelogItem[]
     */
    protected function getChanges(OutputInterface $output)
    {
        $changes = array();
        foreach ($this->getModules() as $module) {
            $moduleChanges = $this->getModuleLog($output, $module);
            $changes = array_merge($changes, $moduleChanges);
        }

        return $this->sortByDate($changes);
    }

    /**
     * Generate output in markdown format
     *
     * @param OutputInterface $output
     * @param string $formatType A format specified by a FORMAT_* constant
     * @return string
     */
    public function getMarkdown(OutputInterface $output, $formatType)
    {
        switch ($formatType) {
            case self::FORMAT_GROUPED:
                return $this->getMarkdownGrouped($output);
            case self::FORMAT_FLAT:
                return $this->getMarkdownFlat($output);
            default:
                throw new \InvalidArgumentException("Unknown changelog format $formatType");
        }
    }

    /**
     * Generates grouped markdown
     *
     * @param OutputInterface $output
     * @return string
     */
    protected function getMarkdownGrouped(OutputInterface $output)
    {
        $groupedLog = $this->getGroupedChanges($output);

        // Convert to string and generate markdown (add list to beginning of each item)
        $output = "\n\n## Change Log\n";
        foreach ($groupedLog as $groupName => $commits) {
            if (empty($commits)) {
                continue;
            }

            $output .= "\n### $groupName\n\n";
            foreach ($commits as $commit) {
                /** @var ChangelogItem $commit */
                $output .= $commit->getMarkdown($this->getLineFormat(), $this->getSecurityFormat());
            }
        }

        return $output;
    }

    /**
     * Custom format string for line items
     *
     * @var string
     */
    protected $lineFormat = null;

    /**
     * @return Version
     */
    public function getFromVersion()
    {
        return $this->fromVersion;
    }

    /**
     * @param Version $fromVersion
     * @return $this
     */
    public function setFromVersion(Version $fromVersion)
    {
        $this->fromVersion = $fromVersion;
        return $this;
    }

    /**
     * @return string
     */
    public function getLineFormat()
    {
        return $this->lineFormat;
    }

    /**
     * @param string $lineFormat
     * @return $this
     */
    public function setLineFormat($lineFormat)
    {
        $this->lineFormat = $lineFormat;
        return $this;
    }

    /**
     * @return string
     */
    public function getSecurityFormat()
    {
        return $this->securityFormat;
    }

    /**
     * @param string $securityFormat
     * @return Changelog
     */
    public function setSecurityFormat($securityFormat)
    {
        $this->securityFormat = $securityFormat;
        return $this;
    }

    /**
     * Custom format string for security details
     *
     * @var string
     */
    protected $securityFormat = null;

    /**
     * Generates markdown as a flat list
     *
     * @param OutputInterface $output
     * @return string
     */
    protected function getMarkdownFlat(OutputInterface $output)
    {
        $commits = $this->getChanges($output);

        $output = '';
        foreach ($commits as $commit) {
            // Skip untyped commits
            if (!$commit->getType()) {
                continue;
            }
            /** @var ChangelogItem $commit */
            $output .= $commit->getMarkdown($this->getLineFormat(), $this->getSecurityFormat());
        }

        return $output;
    }

    /**
     * Sort and filter this list of commits into a grouped array of commits
     *
     * @param array $commits Flat list of commits
     * @return array Nested list of commit categories, each of which is a list of commits in that category.
     * Empty categories are still returned
     */
    protected function sortByType($commits)
    {
        // List types
        $groupedByType = array();
        foreach (ChangelogItem::getTypes() as $type) {
            $groupedByType[$type] = array();
        }

        // Group into type
        foreach ($commits as $commit) {
            $type = $commit->getType();
            if ($type) {
                $groupedByType[$type][] = $commit;
            }
        }

        return $groupedByType;
    }

    /**
     * @param array $commits
     * @return array
     */
    protected function sortByDate($commits)
    {
        // sort by timestamp newest to oldest
        usort($commits, function (ChangelogItem $a, ChangelogItem $b) {
            $aTime = $a->getDate();
            $bTime = $b->getDate();
            if ($bTime == $aTime) {
                return 0;
            } elseif ($bTime < $aTime) {
                return -1;
            } else {
                return 1;
            }
        });
        return $commits;
    }

    /**
     * Get modules for this changelog
     *
     * @return Module[]
     */
    protected function getModules()
    {
        return $this->modules;
    }
}
