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
     * @param string $name Name of dependency
     */
    public function __construct($constraint, $parentVersion = null, $name = null)
    {
        $this->setValue($constraint);

        if ($constraint === 'self.version') {
            if (!$parentVersion instanceof Version) {
                throw new InvalidArgumentException(
                    "$name dependency self.version given with missing parent version"
                );
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
                "Composer constraint {$constraint} for {$name} is not semver-compatible. "
                . "E.g. ^4.0, self.version, or a fixed version"
            );
        }

        // Parse from stability
        if (empty($parsed['minStability'])) {
            $fromStability = '';
        } else {
            $fromStabilityVersion = isset($parsed['stabilityVersion']) ? $parsed['stabilityVersion'] : '';
            $fromStability = '-' . $parsed['minStability'] . $fromStabilityVersion;
        }
        $from = sprintf(
            "%d.%d.%d%s",
            $parsed['major'],
            isset($parsed['minor']) ? $parsed['minor'] : '0',
            isset($parsed['patch']) ? $parsed['patch'] : '0',
            $fromStability
        );

        // Semver to
        if (empty($parsed['type'])) {
            // x.y.z@stable
            $to = $from;
        } elseif ($parsed['type'] === '^') {
            // ^x.y.z
            $to = $parsed['major'] . '.99999.99999';
        } elseif (isset($parsed['patch']) && strlen($parsed['patch'])) {
            // ~x.y.z
            $to = $parsed['major'] . '.' . $parsed['minor'] . '.99999';
        } elseif (isset($parsed['minor']) && strlen($parsed['minor'])) {
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
            '/^(?<major>\\d+)(\\.(?<minor>\\d+))?(?<dev>\.[x\\*]\\-dev)$/',
            $version,
            $matches
        );
        if ($valid) {
            $minor = (isset($matches['minor']) && strlen($matches['minor'])) ? $matches['minor'] : '0';
            $patch = (isset($matches['minor']) && $matches['minor']) ? '0' : null;
            return [
                'type' => '~',
                'major' => $matches['major'],
                'minor' => $minor,
                'patch' => $patch,
                'stability' => '',
                'minStability' => 'alpha',  // treat x-dev dependencies as matching min-alpha1 stability (lowest)
                'stabilityVersion' => '1',
                'dev' => $matches['dev']
            ];
        }

        // Match semver constraint
        $valid = preg_match(
            '/^(?<type>[~^]?)(?<major>\d+)(\\.(?<minor>\d+)(\\.(?<patch>\\d+))?)?'
            . '([-@](?<stability>rc|alpha|beta|stable|dev)(?<stabilityVersion>\d+)?)?$/',
            $version,
            $matches
        );
        if (!$valid) {
            return false;
        }

        // map literal 'stabilty' to effective 'minStability'
        $minStability = isset($matches['stability']) ? $matches['stability'] : 'alpha';
        switch ($minStability) {
            case 'stable':
                $minStability = '';
                break;
            case 'dev':
                $minStability = 'alpha';
                break;
        }
        $stabilityVersion = ($minStability && isset($matches['stabilityVersion']))
            ? $matches['stabilityVersion']
            : '1';
        return array_merge($matches, [
            'minStability' => $minStability,
            'stabilityVersion' => $stabilityVersion,
        ]);
    }

    /**
     * Filter a list of tags
     * Note: Only works with stable tags.
     *
     * @param Version[] $tags
     * @return Version[]
     */
    public function filterVersions($tags)
    {
        $matches = [];
        foreach ($tags as $tagName => $tag) {
            // Check lower and upper bounds
            if (!$this->compareTo($tag)) {
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

    /**
     * Compare this range to this version
     *
     * @param Version $version
     * @return int Negative if this range is below this version, positive if above this version, or 0
     */
    public function compareTo(Version $version)
    {
        if ($this->getMinVersion()->compareTo($version) > 0) {
            return 1;
        }
        if ($this->getMaxVersion()->compareTo($version) < 0) {
            return -1;
        }
        return 0;
    }

    /**
     * Rewrite this constraint to support the given version.
     * Useful when we have chosen a new release version that the underlying
     * composer constraint doesn't support, so bump it up automatically.
     * (Note: Normally only bumps up, not down, but supports both).
     *
     * @param Version $version
     * @return ComposerConstraint|null The new constraint, or null if this cannot be automatically done.
     */
    public function rewriteToSupport(Version $version)
    {
        // If it already supports this version there is no need to rewrite
        if ($this->compareTo($version) === 0) {
            return $this;
        }

        if ($this->isSelfVersion()) {
            return null;
        }

        $parts = static::parse($this->getValue());

        // Match dev dependency
        if (isset($parts['dev'])) {
            // Rewrite to a.b.x-dev
            $value = $version->getMajor() . '.' . $version->getMinor() . $parts['dev'];
            return new ComposerConstraint($value);
        }

        // Can't rewrite non-semver constraints for dev versions (since it'll end up only matching tags)
        if (empty($parts['type'])) {
            return null;
        }

        // If major version is different for semver constraints then simplify by removing patch constraint
        $hasPatch = isset($parts['patch']) && strlen($parts['patch']);
        if (($parts['major'] !== $version->getMajor()) || !$hasPatch || $parts['type'] === '^') {
            // e.g. ~3.1.0 -> ~4.1, ~4.0 -> ~4.1
            $value = $parts['type'] . $version->getMajor() . '.' . $version->getMinor();
        } else {
            // e.g. ~4.0.1 -> ~4.1.0
            $value = $parts['type'] . $version->getMajor() . '.' . $version->getMinor() . '.0';
        }
        // Maintain stability
        if (!empty($parts['stability'])) {
            $value .= '@' . $parts['stability'];
        }
        return new ComposerConstraint($value);
    }
}
