<?php

namespace SilverStripe\Cow\Utility;

use InvalidArgumentException;

/**
 * Utility class for running composer commands
 */
class Composer
{
    /**
     * Get list of versions for a library
     *
     * @param CommandRunner $runner
     * @param string $library
     * @return array List of versions
     */
    public static function getLibraryVersions(CommandRunner $runner, $library)
    {
        $error = "Could not parse available versions from command \"composer show {$library}\"";
        $result = $runner->runCommand(["composer", "global", "show", $library, "--all", "--no-ansi"], $error);

        if (empty($result) || !preg_match('/^versions\s*:\s*(?<versions>(\S.+\S))\s*$/m', $result, $matches)) {
            throw new InvalidArgumentException($error);
        }

        return preg_split('/\s*,\s*/', $matches['versions']);
    }

    /**
     * Create a given version skeleton (aka composer create-project)
     *
     * @param CommandRunner $runner
     * @param string $recipe
     * @param string $directory
     * @param string $version
     * @param string $repository Optional custom repository
     * @param bool $preferDist Set to true to use dist
     * @param bool $ignorePlatform Set to true to ignore platform deps
     */
    public static function createProject(
        CommandRunner $runner,
        $recipe,
        $directory,
        $version,
        $repository = null,
        $preferDist = false,
        $ignorePlatform = true
    ) {
        $createOptions = self::getCreateOptions($repository, $preferDist, $ignorePlatform);
        $runner->runCommand(array_merge([
            "composer",
            "create-project",
            $recipe,
            $directory,
            $version,
        ], $createOptions), "Could not create project with version {$version}");
    }

    /**
     * @param CommandRunner $runner
     * @param string $recipe
     * @param string $directory
     * @param string $version
     * @param string $repository Optional custom repository
     * @param bool $preferDist Set to true to use dist
     * @param bool $ignorePlatform Set to true to ignore platform deps
     */
    public static function update(
        CommandRunner $runner,
        $directory,
        $repository = null,
        $preferDist = false,
        $ignorePlatform = false,
        $emulateRequirements = true
    ) {
        // Set composer config
        $customConfig = self::getUpdateConfig($directory, $repository, $emulateRequirements);

        foreach ($customConfig as $option => $arguments) {
            $runner->runCommand(array_merge(
                ['composer', 'config', $option],
                $arguments,
                ['--working-dir', $directory]
            ));
        }

        try {
            // Update with the given repository
            $updateOptions = self::getUpdateOptions($preferDist, $ignorePlatform);
            $runner->runCommand(array_merge([
                'composer',
                'update',
                '--working-dir',
                $directory,
            ], $updateOptions), "Could not update project");
        } finally {
            // Revert all custom config
            foreach ($customConfig as $option => $arguments) {
                $runner->runCommand([
                    'composer',
                    'config',
                    '--unset',
                    $option,
                    '--working-dir',
                    $directory,
                ]);
            }
        }
    }

    /**
     * get oauth token from github
     *
     * @param CommandRunner $runner
     * @return string
     */
    public static function getOAUTHToken(CommandRunner $runner)
    {
        // try composer stored oauth token
        $command = ['composer', 'config', '-g', 'github-oauth.github.com'];
        $error = "Couldn't determine GitHub oAuth token. Please set GITHUB_API_TOKEN";
        $result = $runner->runCommand($command, $error, true, false);
        return trim($result);
    }

    /**
     * Get list of custom config to use for `composer update` when creating a project
     *
     * @param string $directory
     * @param string $repository
     * @param bool $emulateRequirements
     * @return array
     */
    protected static function getUpdateConfig($directory, $repository, $emulateRequirements)
    {
        // Register all custom options to temporarily set
        $customConfig = [];

        if ($emulateRequirements) {
            $composerData = Config::loadFromFile($directory . '/composer.json');
            $customConfig = array_merge(
                $customConfig,
                static::requirementsConfig($composerData)
            );
        }

        // Update un-installed project with custom repository
        if ($repository) {
            $customConfig['repositories.temp'] = ['composer', $repository];
            $customConfig['secure-http'] = ['false'];
        }

        return $customConfig;
    }

    /**
     * Build platform configuration from composer requirements
     * Does not return the requirements for which the original
     * composer.json has already got "config.platform.*" defined
     *
     * @param array $composerData current composer.json config
     *
     * @return array associative array of composer platform config values
     */
    private static function requirementsConfig(array $composerData)
    {
        $config = [];

        if (!isset($composerData['require'])) {
            return $config;
        }

        $requirements = $composerData['require'];

        foreach ($requirements as $package => $version) {
            if ($package === 'php') {
                if (isset($composerData['config']['platform']['php'])) {
                    continue;
                }

                $versions = static::parseVersions($version);

                if (count($versions)) {
                    $config['platform.php'] = [$versions[0]];
                }
            } elseif (strpos($package, '/') === false && substr($package, 0, 4) === 'ext-') {
                if (isset($composerData['config']['platform'][$package])) {
                    continue;
                }

                $packageKey = sprintf('platform.%s', $package);

                if ($version === '*') {
                    $config[$packageKey] = ['1'];
                } else {
                    $versions = static::parseVersions($version);

                    if (count($versions)) {
                        $config[$packageKey] = [$versions[0]];
                    }
                }
            }
        }

        return $config;
    }

    /**
     * Read the allowed versions defined in the composer format and return them ordered
     *
     * @param string $versionDefinition version definition
     * @return array ordered list of versions as strings
     *
     * @link https://getcomposer.org/doc/articles/versions.md Composer version format documentation
     *
     */
    private static function parseVersions($versionDefinition)
    {
        static $regex = '/
            (?<!(?:<|>|\.|\d))  # Does not start with "<", ">", "." or a number
                                # that means it may be ">=7.1", "<=7.1", "7.1" but cannot be ">7.1" nor "<7.1"
                                # We also eliminate partial matches ignoring stuff starting with
                                # a number or a dot ".", so that ">7.1.25" does not become "1.25" nor "5"

            (?<version>\d+(?:\.\d+(?:\.\d+)?)?)
        /x';

        if (
            preg_match_all($regex, $versionDefinition, $matches)
            && isset($matches['version'])
            && count($matches['version'])
        ) {
            $versions = $matches['version'];

            usort($versions, static function ($a, $b) {
                $af = floatval($a);
                $bf = floatval($b);

                if ($af < $bf) {
                    return -1;
                } elseif ($af > $bf) {
                    return 1;
                } else {
                    if ($a < $b) {
                        return -1;
                    } elseif ($a > $b) {
                        return 1;
                    } else {
                        return 0;
                    }
                }
            });

            return $versions;
        } else {
            return [];
        }
    }

    /**
     * Get all extra options to use with `composer create-project` when creating a project
     *
     * @param string $repository
     * @param string $preferDist
     * @param bool $ignorePlatform
     * @return array
     */
    protected static function getCreateOptions($repository, $preferDist, $ignorePlatform)
    {
        // Create-options
        $createOptions = [
            '--no-secure-http',
            '--no-interaction',
            '--no-install',
        ];

        // Set ignore platform reqs
        if ($ignorePlatform) {
            $createOptions[] = '--ignore-platform-reqs';
        }

        // Set dev / stable options
        if ($preferDist) {
            $createOptions[] = "--prefer-dist";
            $createOptions[] = "--no-dev";
        } else {
            $createOptions[] = "--prefer-source";
            $createOptions[] = "--keep-vcs"; // create only
        }

        // Add repository
        if ($repository) {
            $createOptions[] = '--repository';
            $createOptions[] = $repository;
        }
        return $createOptions;
    }

    /**
     * Get all extra composer cli options to use with `composer update` when creating a project
     *
     * @param bool $preferDist
     * @param bool $ignorePlatform
     * @return array
     */
    protected static function getUpdateOptions($preferDist, $ignorePlatform)
    {
        // update options
        $updateOptions = [ "--no-interaction" ];

        // Set ignore platform reqs
        if ($ignorePlatform) {
            $updateOptions[] = '--ignore-platform-reqs';
        }

        // Set dev / stable options
        if ($preferDist) {
            $updateOptions[] = "--prefer-dist";
            $updateOptions[] = "--no-dev";
        } else {
            $updateOptions[] = "--prefer-source";
        }
        return $updateOptions;
    }
}
