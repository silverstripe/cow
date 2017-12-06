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
     */
    public static function createProject(
        CommandRunner $runner,
        $recipe,
        $directory,
        $version,
        $repository = null,
        $preferDist = false
    ) {
        // Create-options
        $createOptions = [
            "--no-secure-http",
            "--no-interaction",
            "--ignore-platform-reqs",
        ];

        // Set dev / stable options
        if ($preferDist) {
            $createOptions[] = "--prefer-dist";
            $createOptions[] = "--no-dev";
        } else {
            $createOptions[] = "--prefer-source";
            $createOptions[] = "--keep-vcs"; // create only
        }

        // If using a repository, we must delay for a later update
        if ($repository) {
            $createOptions[] = '--repository';
            $createOptions[] = $repository;
            $createOptions[] = '--no-install';
        }

        // Create comand
        $runner->runCommand(array_merge([
            "composer",
            "create-project",
            $recipe,
            $directory,
            $version
        ], $createOptions), "Could not create project with version {$version}");

        // Update un-installed project with custom repository
        if ($repository) {
            // Add repository temporarily
            $runner->runCommand([
                'composer',
                'config',
                'repositories.temp',
                'composer',
                $repository,
                '--working-dir',
                $directory,
            ]);
            // Enable http:// local repositories
            $runner->runCommand([
                'composer',
                'config',
                'secure-http',
                'false',
                '--working-dir',
                $directory,
            ]);

            // update options
            $updateOptions = [
                "--no-interaction",
                "--ignore-platform-reqs",
            ];

            // Set dev / stable options
            if ($preferDist) {
                $updateOptions[] = "--prefer-dist";
                $updateOptions[] = "--no-dev";
            } else {
                $updateOptions[] = "--prefer-source";
            }

            // Update with the given repository
            $runner->runCommand(array_merge([
                'composer',
                'update',
                '--working-dir',
                $directory,
            ], $updateOptions), "Could not update project");

            // Revert changes made above
            $runner->runCommand([
                'composer',
                'config',
                '--unset',
                'repositories.temp',
                '--working-dir',
                $directory,
            ]);
            $runner->runCommand([
                'composer',
                'config',
                '--unset',
                'secure-http',
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
}
