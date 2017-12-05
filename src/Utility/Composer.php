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
        $command = [
            "composer",
            "create-project",
            "--no-interaction",
            "--ignore-platform-reqs",
            $recipe,
            $directory,
            $version
        ];
        if ($preferDist) {
            $command[] = "--prefer-dist";
            $command[] = "--no-dev";
        } else {
            $command[] = "--prefer-source";
            $command[] = "--keep-vcs";
        }
        if ($repository) {
            $command[] = '--repository';
            $command[] = $repository;
        }
        $runner->runCommand($command, "Could not create project with version {$version}");
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
