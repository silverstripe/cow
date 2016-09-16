<?php


namespace SilverStripe\Cow\Model\Release;

use InvalidArgumentException;

/**
 * Represents a composer constraint
 *
 * Supports the following format:
 * - self.version (deprecated)
 * - ^0(.0(.0)?)?
 * - ~0(.0(.0)?)?
 * - 0(.0(.0)?)?
 */
class ComposerConstraint
{
    /**
     * From version
     *
     * @var Version
     */
    protected $from;

    /**
     * @return Version
     */
    public function getFrom()
    {
        return $this->from;
    }

    /**
     * @param Version $from
     * @return ComposerConstraint
     */
    public function setFrom($from)
    {
        $this->from = $from;
        return $this;
    }

    /**
     * @return Version
     */
    public function getTo()
    {
        return $this->to;
    }

    /**
     * @param mixed $to
     * @return ComposerConstraint
     */
    public function setTo($to)
    {
        $this->to = $to;
        return $this;
    }

    /**
     * To version
     *
     * @var Version
     */
    protected $to;

    /**
     * Flag if self.version
     *
     * @var bool
     */
    protected $isSelfVersion = false;

    /**
     * ComposerConstraint constructor.
     * @param string $constraint
     * @param Version $parentVersion
     */
    public function __construct($constraint, $parentVersion = null)
    {
        if ($constraint === 'self.version') {
             if (!$parentVersion instanceof Version) {
                throw new InvalidArgumentException("self.version given with missing parent version");
            }

            $this->isSelfVersion = true;
            $this->from = $parentVersion;
            $this->to = $parentVersion;
            return;
        }

        // Check if matches explicit version (4.0.0, no semver flags)
        if (Version::parse($constraint)) {
            $this->from = new Version($constraint);
            $this->to = new Version($constraint);
            return;
        }

        // Parse type
        $parsed = static::parse($constraint);
        if (!$parsed) {
            throw new InvalidArgumentException(
                "Composer constraint {$constraint} is not semver-compatible. E.g. ^4.0, self.version, or a fixed version"
            );
        }

        // From version ignores modifier, includes alpha1 tag. :)
        $from = sprintf("%d.%d.%d-alpha1",
            $parsed['major'],
            isset($parsed['minor']) ? $parsed['minor'] : '0',
            isset($parsed['patch']) ? $parsed['patch'] : '0'
        );


        // Semver to
        if ($parsed['type'] === '^') {
            $to = $parsed['major'] . '.99999.99999';
        } elseif (isset($parsed['patch'])) {
            // ~x.y.z
            $to = $parsed['major'] . '.' . $parsed['minor'] . '.99999';
        } elseif (isset($parsed['minor'])) {
            // ~x.y
            $to = $parsed['major'] . '.99999.99999';
        } else {
            // ~y
            $to = '99999.99999.99999';
        }

        $this->from = new Version($from);
        $this->to = new Version($to);
    }

    /**
     * Helper method to parse a semver constraint
     *
     * @param string $version
     * @return bool|array Either false, or an array of parts. Parts are type, major, minor, patch
     */
    public static function parse($version)
    {
        $valid = preg_match(
            '/^(?<type>[~^])(?<major>\d+)(\.(?<minor>\d+)(\.(?<patch>\d+))?)?$/',
            $version,
            $matches
        );
        if (!$valid) {
            return false;
        }
        return $matches;
    }

    /**
     * Filter a list of tags
     * Note: Only works with stable tags.
     *
     * @param Version[] $tags
     * @return Version[]
     */
    public function filterVersions($tags) {
        $matches = [];
        foreach($tags as $tagName => $tag) {
            // Check lower and upper bounds
            if ($this->getFrom()->compareTo($tag) <= 0
                && $this->getTo()->compareTo($tag) >= 0
            ) {
                $matches[$tagName] = $tag;
            }
        }

        return $matches;
    }

    /**
     * Determine if this constraint matches a given version.
     * Note: Only works with stable tags.
     *
     * @param string $version
     * @return bool
     */
    public function matches($version) {
        if (!Version::parse($version)) {
            return false;
        }
    }

    /**
     * @return boolean
     */
    public function isSelfVersion()
    {
        return $this->isSelfVersion;
    }
}