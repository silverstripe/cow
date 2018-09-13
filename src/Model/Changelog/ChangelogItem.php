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
     * @var Commit
     */
    protected $commit;

    /**
     * Rules for ignoring commits
     *
     * @var array
     */
    protected $ignoreRules = array(
        '/^Merge/',
        '/^Blocked revisions/',
        '/^Initialized merge tracking /',
        '/^Created (branches|tags)/',
        '/^NOTFORMERGE/',
        '/^\s*$/'
    );

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
    protected static $types = array(
        'Security' => array(
            // E.g. "[ss-2015-016]: Security fix"
            '/^(\[SS-2(\d){3}-(\d){3}\])\s?:?/i'
        ),
        'API Changes' => array(
            '/^(APICHANGE|API-CHANGE|API CHANGE|API)\s?:?/i'
        ),
        'Features and Enhancements' => array(
            '/^(ENHANCEMENT|ENHNACEMENT|ENH|FEATURE|NEW)\s?:?/i'
        ),
        'Bugfixes' => array(
            '/^(BUGFIX|BUGFUX|BUG|FIX|FIXED|FIXING)\s?:?/i',
            '/^(BUG FIX)\s?:?/i'
        )
    );

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
    public function __construct(ChangelogLibrary $changelogLibrary, Commit $commit)
    {
        $this->setChangelogLibrary($changelogLibrary);
        $this->setCommit($commit);
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

        // Strip categorisation tags (API, BUG FIX, etc)
        foreach (self::$types as $rules) {
            foreach ($rules as $rule) {
                $message = trim(preg_replace($rule, '', $message));
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

        // Encode HTML tags
        $message = str_replace(array('<', '>'), array('&lt;', '&gt;'), $message);
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
                if (preg_match($rule, $message)) {
                    return $type;
                }
            }
        }

        // Fallback check for CVE (not at start of string)
        if ($this->getSecurityCVE()) {
            return 'Security';
        }

        return null;
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
     * If this is a security fix, get the CVP (in 'ss-2015-016' fomat)
     *
     * @return string|null cvp, or null if not
     */
    public function getSecurityCVE()
    {
        if (preg_match('/^\[(?<cve>SS-2(\d){3}-(\d){3})\]/i', $this->getRawMessage(), $matches)) {
            return strtolower($matches['cve']);
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
        $content = Format::formatString($format, [
            'type' => $this->getType(),
            'link' => $this->getLink(),
            'shortHash' => $this->getShortHash(),
            'date' => $this->getDate()->format('Y-m-d'),
            'rawMessage' => $this->getRawMessage(), // Probably not safe to use
            'message' => $this->getMessage(),
            'shortMessage' => $this->getShortMessage(),
            'author' => $this->getAuthor(),
        ]);

        // Append security identifier
        if ($cve = $this->getSecurityCVE()) {
            if (!isset($securityFormat)) {
                $securityFormat = ' - See [{cve}]({cveURL})';
            }
            $content .= Format::formatString($securityFormat, [
                'cve' => $cve,
                'cveURL' => $this->cveURL . $cve
            ]);
        }

        return $content . "\n";
    }
}
