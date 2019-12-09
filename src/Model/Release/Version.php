<?php

namespace SilverStripe\Cow\Model\Release;

use InvalidArgumentException;
use LogicException;
use SilverStripe\Cow\Model\Modules\Library;
use SilverStripe\Cow\Utility\Format;

/**
 * Represents a version for a release
 */
class Version
{
    public const ASC = 'ascending';

    public const DESC = 'descending';

    /**
     * Original string value (may have `v` prefix)
     *
     * @var string
     */
    protected $original;

    /**
     * @var int
     */
    protected $major;

    /**
     * @var int
     */
    protected $minor;

    /**
     * @var int|null
     */
    protected $patch;

    /**
     * Null if stable, or a stability string otherwise (rc, beta, alpha)
     *
     * @var string|null
     */
    protected $stability;

    /**
     *
     * @var int|null
     */
    protected $stabilityVersion;

    /**
     * Helper method to parse a version
     *
     * @param string $version
     * @return bool|array Either false, or an array of parts
     */
    public static function parse($version)
    {
        // Note: Ignore leading 'v'
        $valid = preg_match(
            '/^(v?)(?<major>\d+)\.(?<minor>\d+)\.(?<patch>\d+)'
            . '(\-(?<stability>rc|alpha|beta)(?<stabilityVersion>\d+)?)?$/',
            $version,
            $matches
        );
        if (!$valid) {
            return false;
        }
        return $matches;
    }

    public function __construct($version)
    {
        $matches = static::parse($version);
        if ($matches === false) {
            throw new InvalidArgumentException(
                "Invalid version $version. Expect full version (3.1.13) with optional rc|alpha|beta suffix"
            );
        }
        $this->major = $matches['major'];
        $this->minor = $matches['minor'];
        $this->patch = $matches['patch'];
        $this->stabilityVersion = null;
        if (empty($matches['stability'])) {
            $this->stability = null;
        } else {
            $this->stability = $matches['stability'];
            if (!empty($matches['stabilityVersion'])) {
                $this->stabilityVersion = $matches['stabilityVersion'];
            }
        }
        $this->original = $version;
    }

    public function __toString()
    {
        return $this->getValue();
    }

    /**
     * Get original tag value
     *
     * @return string
     */
    public function getOriginalString()
    {
        return $this->original;
    }

    public function getStability()
    {
        return $this->stability ?: ''; // Default to '' which is stable
    }

    /**
     * Is this version stable?
     *
     * @return bool
     */
    public function isStable()
    {
        return empty($this->stability);
    }

    public function setStability($stability)
    {
        $this->stability = $stability;
        return $this;
    }

    public function getStabilityVersion()
    {
        if ($this->isStable()) {
            return null;
        }
        return (int)$this->stabilityVersion;
    }

    public function setStabilityVersion($stabilityVersion)
    {
        $this->stabilityVersion = $stabilityVersion;
        return $this;
    }

    public function getMajor()
    {
        return $this->major;
    }

    public function setMajor($major)
    {
        $this->major = $major;
        return $this;
    }

    public function getMinor()
    {
        return $this->minor;
    }

    public function setMinor($minor)
    {
        $this->minor = $minor;
        return $this;
    }

    public function getPatch()
    {
        return $this->patch;
    }

    public function setPatch($patch)
    {
        $this->patch = $patch;
        return $this;
    }

    /**
     * Get stable version this version is targetting (ignoring rc, beta, etc)
     *
     * @return string
     */
    public function getValueStable()
    {
        return implode('.', array($this->major, $this->minor, $this->patch));
    }

    /**
     * Get version string
     *
     * @return string
     */
    public function getValue()
    {
        $value = $this->getValueStable();
        if ($this->stability) {
            $value .= "-{$this->stability}{$this->stabilityVersion}";
        }
        return $value;
    }

    /**
     * Get list of preferred versions for installing this release
     *
     * @array List of composer versions from best to worst
     */
    public function getComposerVersions()
    {
        $versions = array();

        // Prefer exact version (e.g. 3.1.13-rc1)
        $versions[] = $this->getValue();

        // Fall back to patch branch (e.g. 3.1.13.x-dev, 3.1.x-dev, 3.x-dev)
        $parts = array($this->major, $this->minor, $this->patch);
        while ($parts) {
            $versions[] = implode('.', $parts) . '.x-dev';
            array_pop($parts);
        }

        // If we need to fallback to dev-master we probably have done something wrong

        return $versions;
    }

    /**
     * Guess the best prior version to release as changelog.
     * E.g. 4.1.1 -> 4.1.0, or 4.1.1-alpha2 -> 4.1.1-alpha1
     *
     * Returns null if this cannot be determined programmatically.
     * E.g. 4.0.0
     *
     * @return Version
     */
    public function getPriorVersion()
    {
        $prior = clone $this;

        // If beta2 or above, guess prior version to be beta1
        $stabilityVersion = $prior->getStabilityVersion();
        if ($stabilityVersion > 1) {
            $prior->setStabilityVersion($stabilityVersion - 1);
            return $prior;
        }

        // Set prior version to stable only
        $prior->setStability(null);
        $prior->setStabilityVersion(null);

        // If patch version is > 0 we can decrement it to get prior
        $patch = $prior->getPatch();
        if ($patch) {
            // Select prior patch version (e.g. 3.1.14 -> 3.1.13)
            $prior->setPatch($patch - 1);
            return $prior;
        }

        // Will need to guess from composer. E.g. 3.1.0 has an ambiguous prior version
        return null;
    }

    /**
     * Compare versions.
     *
     *  (4.0.0 > 4.0.0-alpha1, 4.0.0 < 4.0.1)
     *
     * @param Version $other
     * @return int negative for smaller version, 0 for equal, positive for later version
     */
    public function compareTo(Version $other)
    {
        $diff = $this->getMajor() - $other->getMajor();
        if ($diff) {
            return $diff;
        }
        $diff = $this->getMinor() - $other->getMinor();
        if ($diff) {
            return $diff;
        }
        $diff = $this->getPatch() - $other->getPatch();
        if ($diff) {
            return $diff;
        }
        // Compare stability
        $diff = $this->compareStabliity($this->getStability(), $other->getStability());
        if ($diff) {
            return $diff;
        }
        // Fall back to stability type (e.g. alpha1 vs alpha2)
        $diff = $this->getStabilityVersion() - $other->getStabilityVersion();
        return $diff;
    }

    /**
     * Compare stability strings
     *
     * @param string $left
     * @param string $right
     * @return int
     */
    protected function compareStabliity($left, $right)
    {
        $precedence = [
            '',
            'rc',
            'beta',
            'alpha'
        ];
        if (!in_array($left, $precedence)) {
            throw new InvalidArgumentException("Invalid stability $left");
        }
        if (!in_array($right, $precedence)) {
            throw new InvalidArgumentException("Invalid stability $left");
        }
        if ($left === $right) {
            return 0;
        }
        foreach ($precedence as $type) {
            if ($left === $type) {
                return 1;
            }
            if ($right === $type) {
                return -1;
            }
        }
        throw new LogicException("Internal error");
    }

    /**
     * Given a list of tags, determine which is the best "from" version
     *
     * @param Version[] $tags List of tags to search
     * @return Version
     */
    public function getPriorVersionFromTags($tags)
    {
        // If we can programmatically detect a prior version, then use this
        $prior = $this->getPriorVersion();
        if ($prior && array_key_exists($prior->getValue(), $tags)) {
            return $prior;
        }

        // Search all tags to find best prior version
        $best = null;
        foreach ($tags as $tag) {
            // Skip pre-releases
            if ($tag->getStability()) {
                continue;
            }

            // Skip newer versions
            if ($tag->compareTo($this) >= 0) {
                continue;
            }

            // Skip if we found a better tag
            if ($best && $tag->compareTo($best) < 0) {
                continue;
            }

            $best = $tag;
        }

        return $best;
    }

    /**
     * Sort a list of tags, by default newest first
     *
     * @param Version[] $tags
     * @param string $dir ASC or DESC constant values.
     * @return Version[]
     */
    public static function sort($tags, $dir = self::DESC)
    {
        uasort($tags, function (Version $left, Version $right) use ($dir) {
            switch ($dir) {
                case self::ASC:
                    return $left->compareTo($right);
                case self::DESC:
                    return $right->compareTo($left);
                default:
                    throw new InvalidArgumentException("Invalid dir $dir");
            }
        });
        return $tags;
    }

    /**
     * Filter out by callback
     *
     * @param Version[] $tags
     * @param callable $callback
     * @return Version[]
     */
    public static function filter($tags, $callback)
    {
        $filtered = [];
        foreach ($tags as $tag) {
            if ($callback($tag)) {
                $filtered[$tag->getValue()] = $tag;
            }
        }
        return $filtered;
    }

    /**
     * Guess the next version to release from this version
     *
     * @param string $stability Stability of the next version to use
     * @param int $stabilityVersion Version of stability (e.g. 2 for -rc2)
     * @return Version
     */
    public function getNextVersion($stability = '', $stabilityVersion = 0)
    {
        // Create new version with specified stability
        $canditate = clone $this;
        $canditate->setStability($stability);
        $canditate->setStabilityVersion($stabilityVersion);

        // If last release is stable, or new version isn't never, bump patch version
        // This ensures the new version is always a new version
        // E.g. 1.0.0 -> 1.0.1-rc1, 1.0.1-rc1 -> 1.0.1-rc2
        if ($this->isStable() || $this->compareTo($canditate) >= 0) {
            $canditate->setPatch($this->getPatch() + 1);
        }

        return $canditate;
    }

    /**
     * Get composer constraint for this version with the given constraint type
     *
     * @param string $constraintType
     * @return string
     */
    public function getConstraint($constraintType)
    {
        // Rewrite requirement for tag
        $numericVersion = $this->getValueStable();
        $stability = $this->getStability() ?: 'stable';

        // Add stability variability
        switch ($constraintType) {
            case Library::DEPENDENCY_LOOSE:
                $childRequirement = "~{$numericVersion}@{$stability}";
                break;
            case Library::DEPENDENCY_SEMVER:
                $childRequirement = "^{$numericVersion}@{$stability}";
                break;
            case Library::DEPENDENCY_EXACT:
            default:
                $childRequirement = $this->getValue();
                if ($this->isStable()) {
                    $childRequirement .= '@stable';
                }
                break;
        }
        return $childRequirement;
    }



    /**
     * Inject pattern for the given string
     *
     * @param $pattern
     * @return mixed
     */
    public function injectPattern($pattern)
    {
        $path = Format::formatString($pattern, [
            'stability' => $this->getStability(),
            'stabilityVersion' => $this->getStabilityVersion(),
            'major' => $this->getMajor(),
            'minor' => $this->getMinor(),
            'patch' => $this->getPatch(),
            'version' => $this->getValue(),
            'versionStable' => $this->getValueStable(),
        ]);
        // Collapse duplicate //
        return str_replace('//', '/', $path);
    }
}
