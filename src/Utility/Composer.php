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
     * Install a given version
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
        $ignorePlatform = false
    ) {
        if ($ignorePlatform !== false) {
            user_error(
                '$ingorePlatform argument is now deprecated, set up the correct platform via composer config',
                E_USER_DEPRECATED
            );
        }

        // Create comand
        $createOptions = self::getCreateOptions($repository, $preferDist);
        $runner->runCommand(array_merge([
            "composer",
            "create-project",
            $recipe,
            $directory,
            $version,
        ], $createOptions), "Could not create project with version {$version}");

        // Set composer config
        $customConfig = self::getUpdateConfig($directory, $repository);
        foreach ($customConfig as $option => $arguments) {
            $runner->runCommand(array_merge(
                ['composer', 'config', $option],
                $arguments,
                ['--working-dir', $directory]
            ));
        }

        // Update with the given repository
        $updateOptions = self::getUpdateOptions($preferDist);
        $runner->runCommand(array_merge([
            'composer',
            'update',
            '--working-dir',
            $directory,
        ], $updateOptions), "Could not update project");

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
        $result = $runner->runCommand($command, $error);
        return trim($result);
    }

    /**
     * Get list of custom config to use for `composer update` when creating a project
     *
     * @param string $directory
     * @param string $repository
     * @return array
     */
    protected static function getUpdateConfig($directory, $repository)
    {
        // Register all custom options to temporarily set
        $customConfig = [];

        // If `requirements.php` is specified, set platform to lowest platform version
        $composerData = Config::loadFromFile($directory . '/composer.json');
        if (isset($composerData['require']['php'])
            && preg_match('/^[\\D]*(?<version>[\\d.]+)/', $composerData['require']['php'], $matches)
        ) {
            $customConfig['platform.php'] = [$matches['version']];
        }

        // Update un-installed project with custom repository
        if ($repository) {
            $customConfig['repositories.temp'] = ['composer', $repository];
            $customConfig['secure-http'] = ['false'];
        }

        return $customConfig;
    }

    /**
     * Get all extra options to use with `composer create-project` when creating a project
     *
     * @param string $repository
     * @param string $preferDist
     * @param bool $ignorePlatform
     * @return array
     */
    protected static function getCreateOptions($repository, $preferDist, $ignorePlatform = null)
    {
        // Create-options
        $createOptions = [
            '--no-secure-http',
            '--no-interaction',
            '--no-install',
        ];

        if ($ignorePlatform !== null) {
            user_error(
                '$ingorePlatform argument is now deprecated, set up the correct platform via composer config',
                E_USER_DEPRECATED
            );
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
    protected static function getUpdateOptions($preferDist, $ignorePlatform = null)
    {
        // update options
        $updateOptions = [ "--no-interaction" ];

        if ($ignorePlatform !== null) {
            user_error(
                '$ingorePlatform argument is now deprecated, set up the correct platform via composer config',
                E_USER_DEPRECATED
            );
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
