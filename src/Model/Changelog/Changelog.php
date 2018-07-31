<?php

namespace SilverStripe\Cow\Model\Changelog;

use Generator;
use Gitonomy\Git\Exception\ReferenceNotFoundException;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * A changelog which can be generated from a project
 */
class Changelog
{
    /**
     * Groups changes by type (e.g. bug, enhancement, etc)
     */
    const FORMAT_GROUPED = 'grouped';

    /**
     * Formats list as flat list
     */
    const FORMAT_FLAT = 'flat';

    /**
     * @var ChangelogLibrary
     */
    protected $rootLibrary;

    /**
     * @var bool
     */
    protected $includeAllCommits = false;

    /**
     * Create a new changelog
     *
     * @param ChangelogLibrary $rootLibrary Root library to generate changelog for
     */
    public function __construct(ChangelogLibrary $rootLibrary)
    {
        $this->rootLibrary = $rootLibrary;
    }

    /**
     * Get the list of changes for this module
     *
     * @param OutputInterface $output
     * @param ChangelogLibrary $changelogLibrary
     * @return array
     */
    protected function getLibraryLog(OutputInterface $output, ChangelogLibrary $changelogLibrary)
    {
        $items = array();

        // Get raw log
        $fromVersion = $changelogLibrary->getPriorVersion()->getValue();
        $toVersion = $changelogLibrary->getRelease()->getIsNewRelease()
            ? 'HEAD' // Since it won't have been tagged yet, we use the current branch head
            : $changelogLibrary->getRelease()->getVersion()->getValue();
        $range = $fromVersion."..".$toVersion;
        try {
            $log = $changelogLibrary
                ->getRelease()
                ->getLibrary()
                ->getRepository()
                ->getLog($range);

            foreach ($log->getCommits() as $commit) {
                $change = new ChangelogItem($changelogLibrary, $commit, $this->getIncludeAllCommits());

                // Detect duplicates and skip ignored items
                $key = $change->getDistinctDetails();
                if (!$change->isIgnored() && !isset($items[$key])) {
                    $items[$key] = $change;
                }
            }
        } catch (ReferenceNotFoundException $ex) {
            $moduleName = $changelogLibrary
                ->getRelease()
                ->getLibrary()
                ->getName();
            $output->writeln(
                "<error>Could not generate git diff for {$moduleName} for range {$range}; "
                    . "Skipping changelog for this module</error>"
            );
        }
        return array_values($items);
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
        $libraries = $this->getRootLibrary()->getAllItems(true);
        foreach ($libraries as $library) {
            $moduleChanges = $this->getLibraryLog($output, $library);
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
     * @param ChangelogItem[] $commits Flat list of commits
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
     * @return bool
     */
    public function getIncludeAllCommits()
    {
        return $this->includeAllCommits;
    }

    /**
     * @param bool $includeAllCommits
     * @return $this
     */
    public function setIncludeAllCommits($includeAllCommits)
    {
        $this->includeAllCommits = (bool) $includeAllCommits;
        return $this;
    }

    /**
     * @return ChangelogLibrary
     */
    public function getRootLibrary()
    {
        return $this->rootLibrary;
    }

    /**
     * @param ChangelogLibrary $rootLibrary
     * @return $this
     */
    public function setRootLibrary($rootLibrary)
    {
        $this->rootLibrary = $rootLibrary;
        return $this;
    }
}
