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
 * - 0(.0).x-dev
 */
class ComposerConstraint
{
    /**
     * From version
     *
     * @var Version
     */
    protected $minVersion;

    /**
     * @return Version
     */
    public function getMinVersion()
    {
        return $this->minVersion;
    }

    /**
     * @param Version $minVersion
     * @return ComposerConstraint
     */
    public function setMinVersion($minVersion)
    {
        $this->minVersion = $minVersion;
        return $this;
    }

    /**
     * @return Version
     */
    public function getMaxVersion()
    {
        return $this->maxVersion;
    }

    /**
     * @param mixed $maxVersion
     * @return ComposerConstraint
     */
    public function setMaxVersion($maxVersion)
    {
        $this->maxVersion = $maxVersion;
        return $this;
    }

    /**
     * To version
     *
     * @var Version
     */
    protected $maxVersion;

    /**
     * Flag if self.version
     *
     * @var bool
     */
    protected $isSelfVersion = false;

    /**
     * Original value of the constraint
     *
     * @var string
     */
    protected $value;

    /**
     * @return string
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @param string $value
     * @return $this
     */
    public function setValue($value)
    {
        $this->value = $value;
        return $this;
    }

    /**
     * ComposerConstraint constructor.
     * @param string $constraint
     * @param Version $parentVersion
     */
    public function __construct($constraint, $parentVersion = null)
    {
        $this->value = $constraint;

        if ($constraint === 'self.version') {
             if (!$parentVersion instanceof Version) {
                throw new InvalidArgumentException("self.version given with missing parent version");
            }

            $this->isSelfVersion = true;
            $this->minVersion = $parentVersion;
            $this->maxVersion = $parentVersion;
            return;
        }

        // Check if matches explicit version (4.0.0, no semver flags)
        if (Version::parse($constraint)) {
            $this->minVersion = new Version($constraint);
            $this->maxVersion = new Version($constraint);
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

        $this->minVersion = new Version($from);
        $this->maxVersion = new Version($to);
    }

    /**
     * Helper method to parse a semver constraint
     *
     * @param string $version
     * @return bool|array Either false, or an array of parts. Parts are type, major, minor, patch
     */
    public static function parse($version)
    {
        // Match dev constraint
        $valid = preg_match(
            '/^(?<major>\\d+)(\\.(?<minor>\\d+))?\.[x\\*]\\-dev?$/',
            $version,
            $matches
        );
        if ($valid) {
            return array_merge(
                $matches,
                [
                    'type' => '~',
                    'major' => $matches['major'],
                    'minor' => isset($matches['minor']) ? $matches['minor'] : '0',
                    'patch' => isset($matches['minor']) ? '0' : null,
                ]
            );
        }

        // Match semver constraint
        $valid = preg_match(
            '/^(?<type>[~^])(?<major>\d+)(\\.(?<minor>\d+)(\\.(?<patch>\\d+))?)?$/',
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
            if ($this->getMinVersion()->compareTo($tag) <= 0
                && $this->getMaxVersion()->compareTo($tag) >= 0
            ) {
                $matches[$tagName] = $tag;
            }
        }

        return $matches;
    }

    /**
     * @return boolean
     */
    public function isSelfVersion()
    {
        return $this->isSelfVersion;
    }
}