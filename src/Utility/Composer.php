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
    public static function getLibraryVersions(CommandRunner $runner, $library) {
        $error = "Could not parse available versions from command \"composer show {$library}\"";
        $result = $runner->runCommand( ["composer", "show", $library, "--all"], $error);

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
     */
    public static function createProject(CommandRunner $runner, $recipe, $directory, $version)
    {
        $command = [
            "composer", "create-project", "--prefer-source", "--keep-vcs",
            $recipe, $directory, $version
        ];
        $runner->runCommand($command, "Could not create project with version {$version}");
    }
}