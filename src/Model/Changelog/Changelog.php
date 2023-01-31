<?php

namespace SilverStripe\Cow\Model\Changelog;

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
    public const FORMAT_GROUPED = 'grouped';

    /**
     * Formats list as flat list
     */
    public const FORMAT_FLAT = 'flat';

    /**
     * @var ChangelogLibrary
     */
    protected $rootLibrary;

    /**
     * @var bool
     */
    protected $includeOtherChanges = false;

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
    protected function getLibraryLog(
        OutputInterface $output,
        ChangelogLibrary $changelogLibrary,
        bool $changelogAuditMode = false
    ) {
        $items = array();

        // Get raw log
        $fromVersion = $changelogLibrary->getPriorVersion()->getValue();
        if ($changelogLibrary->getRelease()->getIsNewRelease()) {
            // Since it won't have been tagged yet, we use the current branch head
            $toVersion = 'HEAD';
        } elseif ($changelogAuditMode) {
            // we may have manually cherry-picked security commits after tagging
            $toVersion = 'HEAD';
        } else {
            $toVersion = $changelogLibrary->getRelease()->getVersion()->getValue();
        }
        $range = $fromVersion . ".." . $toVersion;
        try {
            $log = $changelogLibrary
                ->getRelease()
                ->getLibrary()
                ->getRepository()
                ->getLog($range);

            foreach ($log->getCommits() as $commit) {
                $change = new ChangelogItem($changelogLibrary, $commit, $this->getIncludeOtherChanges());

                // Detect duplicates and skip ignored items
                $key = $change->getDistinctDetails();
                if (!$change->isIgnored() && !isset($items[$key])) {
                    $items[$key] = $change;
                }
            }

            // Read commits from -rc1 of the prior release to the prior stable
            // This is done so that any commits that happend post-rc LAST release are still audited
            // in the CURRENT release audit
            if ($changelogAuditMode) {
                $dir = $changelogLibrary->getRelease()->getLibrary()->getDirectory();
                $fromVersionRc1 = preg_replace('#-(alpha|beta|rc)[0-9]+$#', '', $fromVersion) . '-rc1';
                $cmd = "cd $dir && git tag | grep $fromVersionRc1 && cd -";
                $hasRc1FromPreviousRelease = (bool) shell_exec($cmd);
                if ($hasRc1FromPreviousRelease) {
                    $range2 = $fromVersionRc1 . ".." . $fromVersion;
                    $log2 = $changelogLibrary->getRelease()->getLibrary()->getRepository()->getLog($range2);
                    foreach ($log2->getCommits() as $commit) {
                        $change = new ChangelogItem($changelogLibrary, $commit, $this->getIncludeOtherChanges());
                        $key = $change->getDistinctDetails();
                        // Discard any security commits, as they would have been audited last release
                        if (preg_match('#[0-9]{4}-[0-9]{2}-[0-9]{2}-\[?CVE#i', $key)) {
                            continue;
                        }
                        // Detect duplicates and skip ignored items
                        if (!$change->isIgnored() && !isset($items[$key])) {
                            $items[$key] = $change;
                        }
                    }
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
     * @param ?callable $filter function for array_filter on the list of changes
     * @return ChangelogItem[]
     */
    protected function getGroupedChanges(OutputInterface $output, ?callable $filter = null)
    {
        // Sort by type
        $changes = $this->getChanges($output);
        if ($filter) {
            $changes = array_filter($changes, $filter);
        }
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
     * Returns the data for rendering the changelogs.
     */
    public function getChangesRenderData(OutputInterface $output, bool $changelogAuditMode): array
    {
        $libraries = $this->getRootLibrary()->getAllItems(true);

        $libs = [];
        $logs = [];
        $commitToLibraryMap = [];

        foreach ($libraries as $library) {
            $libLogs = $this->sortByDate($this->getLibraryLog($output, $library, $changelogAuditMode));
            $composerData = $library->getRelease()->getLibrary()->getComposerData();
            $name = strtolower(trim($composerData['name']));

            $libs[$name] = [
                'name' => $name,
                'link' => 'https://packagist.org/packages/' . $name,
                'version' => [
                    'prior' => $library->getRelease()->getPriorVersion()->getValue(),
                    'release' => $library->getRelease()->getVersion()->getValue()
                ],
                'commits' => [
                    'all' => $libLogs,
                    'by_type' => $this->sortByType($libLogs)
                ]
            ];

            $logs = array_merge($logs, $libLogs);

            foreach ($libLogs as $log) {
                $commitToLibraryMap[$log->getShortHash()] = $name;
            }
        }

        $logs = $this->sortByDate($logs);

        $data = [
            'libs' => $libs,
            'commits' => [
                'all' => $logs,
                'by_type' => $this->sortByType($logs),
                'map_to_lib' => $commitToLibraryMap
            ]
        ];

        return $data;
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
     * Returns a function that filters the list of changes
     * to conform with the legacy changelog format
     */
    private function getLegacyChangelogCommitFilter(): callable
    {
        static $rules = [
            '/^Merge/',
            '/branch alias/',
            '/^Added(.*)changelog$/',
            '/^Blocked revisions/',
            '/^Initialized merge tracking /',
            '/^Created (branches|tags)/',
            '/^NOTFORMERGE/',
            '/^\s*$/'
        ];

        return static function ($commit) use ($rules) {
            $message = $commit->getRawMessage();
            foreach ($rules as $ignoreRule) {
                if (preg_match($ignoreRule, $message)) {
                    return false;
                }
            }
            return true;
        };
    }

    /**
     * Generates grouped markdown
     *
     * @param OutputInterface $output
     * @return string
     */
    protected function getMarkdownGrouped(OutputInterface $output)
    {
        $groupedLog = $this->getGroupedChanges($output, $this->getLegacyChangelogCommitFilter());

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
        $commits = array_filter($commits, $this->getLegacyChangelogCommitFilter());

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
        $groupedByType = [null => []];
        foreach (ChangelogItem::getTypes() as $type) {
            $groupedByType[$type] = [];
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
    public function getIncludeOtherChanges()
    {
        return $this->includeOtherChanges;
    }

    /**
     * @param bool $includeOtherChanges
     * @return $this
     */
    public function setIncludeOtherChanges($includeOtherChanges)
    {
        $this->includeOtherChanges = (bool) $includeOtherChanges;
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
