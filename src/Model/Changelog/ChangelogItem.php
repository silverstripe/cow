<?php

namespace SilverStripe\Cow\Model\Changelog;

use DateTime;
use Gitonomy\Git\Commit;
use SilverStripe\Cow\Utility\Format;

/**
 * Represents a line-item in a changelog
 */
class ChangelogItem
{
    /**
     * Changelog library reference this item belongs to
     *
     * @var ChangelogLibrary
     */
    protected $changelogLibrary;

    /**
     * @var Commit
     */
    protected $commit;

    /**
     * @var bool
     */
    protected $includeOtherChanges = false;

    /**
     * Rules for ignoring commits
     *
     * @var array
     */
    protected $ignoreRules = [];

    /**
     * Url for CVE release notes
     *
     * @var string
     */
    protected $cveURL = "https://www.silverstripe.org/download/security-releases/";

    /**
     * Order of the array keys determines order of the lists.
     *
     * @var array
     */
    protected static $types = [
        'Security' => [
            // E.g. "[CVE-2019-12345]: Security fix"
            '/^(\[?CVE-(\d){4}-(\d){4,}\]?):?/i',
        ],
        'API Changes' => [
            '/^API\b:?/'
        ],
        'Features and Enhancements' => [
            '/^(ENH(ANCEMENT)?|NEW)\b:?/'
        ],
        'Bugfixes' => [
            '/^(FIX|BUG)\b:?/',
        ],
        'Documentation' => [
            '/^(DOCS?)\b:?/',
        ],
        'Merge' => [
            '/^Merge/',
        ],
        'Dependencies' => [
            '/^(DEP)\b:?/',
        ],
        'Maintenance' => [
            '/^(MNT)\b:?/',
            '/\btravis\b/'
        ],
    ];

    /**
     * Get list of categorisations of commit types
     *
     * @return array
     */
    public static function getTypes()
    {
        return array_keys(self::$types);
    }

    /**
     * Create a changelog item
     *
     * @param ChangelogLibrary $changelogLibrary
     * @param Commit $commit
     */
    public function __construct(ChangelogLibrary $changelogLibrary, Commit $commit, $includeAllCommits = false)
    {
        $this->setChangelogLibrary($changelogLibrary);
        $this->setCommit($commit);
        $this->setIncludeOtherChanges($includeAllCommits);
    }

    public function getRenderData()
    {
        return [
            'type' => $this->getType(),
            'link' => $this->getLink(),
            'shortHash' => $this->getShortHash(),
            'date' => $this->getDate()->format('Y-m-d'),
            'rawMessage' => $this->getRawMessage(),
            'message' => $this->getMessage(),
            'shortMessage' => $this->getShortMessage(),
            'author' => $this->getAuthor(),
            'cve' => $this->getSecurityCVE(),
            'cveURL' => $this->getSecurityCVE() ? $this->cveURL . $this->getSecurityCVE() : ''
        ];
    }

    /**
     * Get details this commit uses to distinguish itself from other duplicate commits.
     * Used to prevent duplicates of the same commit being added from multiple merges, which
     * typically only differ based on SHA.
     *
     * @return string
     */
    public function getDistinctDetails()
    {
        // Date, author, and message
        return $this->getAuthor() . '-' . $this->getDate()->format('Y-m-d') . '-' . $this->getRawMessage();
    }

    /**
     * Get the raw commit
     *
     * @return Commit
     */
    public function getCommit()
    {
        return $this->commit;
    }

    /**
     *
     * @param Commit $commit
     * @return $this
     */
    public function setCommit(Commit $commit)
    {
        $this->commit = $commit;
        return $this;
    }

    /**
     * Should this commit be ignored?
     *
     * @return boolean
     */
    public function isIgnored()
    {
        $message = $this->getRawMessage();
        foreach ($this->ignoreRules as $ignoreRule) {
            if (preg_match($ignoreRule, $message)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get the commit date
     *
     * @return DateTime
     */
    public function getDate()
    {
        // Ignore linting error; invalid phpdoc in gitlib
        return $this->getCommit()->getAuthorDate();
    }

    /**
     * Get author name
     *
     * @return string
     */
    public function getAuthor()
    {
        return $this->getCommit()->getAuthorName();
    }

    /**
     * Get unsanitised commit message
     *
     * @return string
     */
    public function getRawMessage()
    {
        return $this->getCommit()->getSubjectMessage();
    }

    /**
     * Gets message with type tag stripped
     *
     * @return string markdown safe string
     */
    public function getShortMessage()
    {
        $message = $this->getMessage();

        foreach (self::$types as $rules) {
            // Strip categorisation tags (API, BUG FIX, etc) where they are uppercase. If they match but are
            // lowercase then we'll include them in the commit message, e.g. "Fixing regex rules" as opposed to
            // "FIX Regex rules now work"
            foreach ($rules as $rule) {
                if (substr($rule, 0, 2) === '/^') {
                    $processed = trim(preg_replace($rule, '', $message));
                    while ($processed != $message) {
                        $message = $processed;
                        $processed = trim(preg_replace($rule, '', $message));
                    }
                }
            }
        }

        return $message;
    }

    /**
     * Gets message with only minimal sanitisation
     *
     * @return string
     */
    public function getMessage()
    {
        $message = $this->getRawMessage();

        // Strip emails
        $message = preg_replace('/(<?[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,4}>?)/mi', '', $message);

        // Condense git-style "From:" messages (remove preceding newline)
        if (preg_match('/^From\:/mi', $message)) {
            $message = preg_replace('/\n\n^(From\:)/mi', ' $1', $message);
        }

        return $message;
    }

    /**
     * Get category for this type
     *
     * @return string|null Return the category of this commit, or null if uncategorised
     */
    public function getType()
    {
        $message = $this->getRawMessage();
        foreach (self::$types as $type => $rules) {
            foreach ($rules as $rule) {
                // Add case insensitivity modifier
                if (preg_match($rule . 'i', $message)) {
                    return $type;
                }
            }
        }

        // Check for security identifier (not at start of string)
        if ($this->getSecurityCVE()) {
            return 'Security';
        }

        if ($this->getAuthor() === 'dependabot[bot]') {
            return 'Dependencies';
        }

        return 'Other changes';
    }

    /**
     * Get the URl where this link should be on open source
     *
     * @return string
     */
    public function getLink()
    {
        $library = $this->getChangelogLibrary()->getRelease()->getLibrary();
        $sha = $this->getCommit()->getHash();
        return $library->getCommitLink($sha);
    }

    /**
     * Get short hash for this commit
     *
     * @return string
     */
    public function getShortHash()
    {
        return $this->getCommit()->getShortHash();
    }

    /**
     * If this is a security fix, get the CVE/identifier (in 'ss-2015-016' or 'CVE-2019-12345' format)
     *
     * @return string|null CVE/identifier, or null if not
     */
    public function getSecurityCVE()
    {
        // New CVE style identifiers (e.g. CVE-2023-32302)
        if (preg_match('/^\[(?<cve>CVE-(\d){4}-(\d){4,})\]/i', $this->getRawMessage(), $matches)) {
            return strtolower($matches['cve']);
        }
        // Non-CVE style identifiers (e.g. SS-2023-001)
        if (preg_match('/^\[(?<ss>SS-(\d){4}-(\d){3})\]/i', $this->getRawMessage(), $matches)) {
            return strtolower($matches['ss']);
        }
    }

    /**
     * Get markdown content for this line item, including end of line
     *
     * @param string $format Format for line
     * @param string $securityFormat Format for security CVE link
     * @return string
     */
    public function getMarkdown($format = null, $securityFormat = null)
    {
        if (!isset($format)) {
            $format = ' * {date} [{shortHash}]({link}) {shortMessage} ({author})';
        }
        $data = $this->getRenderData();

        $content = Format::formatString($format, $data);

        // Append security identifier
        if (!empty($data['cve'])) {
            if (!isset($securityFormat)) {
                $securityFormat = ' - See [{cve}]({cveURL})';
            }
            $content .= Format::formatString($securityFormat, $data);
        }

        return $content . "\n";
    }

    /**
     * @return ChangelogLibrary
     */
    public function getChangelogLibrary()
    {
        return $this->changelogLibrary;
    }

    /**
     * @param ChangelogLibrary $changelogLibrary
     * @return $this
     */
    public function setChangelogLibrary($changelogLibrary)
    {
        $this->changelogLibrary = $changelogLibrary;
        return $this;
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
}
