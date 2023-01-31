<?php

namespace SilverStripe\Cow;

use SilverStripe\Cow\Commands;
use SilverStripe\Cow\Utility\SupportedModuleLoader;
use SilverStripe\Cow\Utility\Config;
use SilverStripe\Cow\Utility\GitHubApi;
use SilverStripe\Cow\Utility\Twig;
use Symfony\Component\Console;
use Symfony\Component\Dotenv\Dotenv;

class Application extends Console\Application
{
    private static $hasLoadedDotEnv = false;

    public static function isDevMode()
    {
        if (!self::$hasLoadedDotEnv) {
            (new Dotenv())->load(__DIR__ . '/../.env');
            self::$hasLoadedDotEnv = true;
        }
        // $_ENV is populated with the contents of .env by symfony/dotenv
        return isset($_ENV['DEV_MODE']) && $_ENV['DEV_MODE'];
    }

    public function createTwigEnvironment(): Twig\Environment
    {
        return new Twig\Environment(new Twig\Loader($this));
    }

    /**
     * Get version of this module
     *
     * @param string $directory
     * @return string
     */
    protected function getVersionInDir($directory)
    {
        if (!$directory || dirname($directory) === $directory) {
            return null;
        }
        $installed = $directory . '/vendor/composer/installed.json';
        if (file_exists($installed)) {
            $content = Config::loadFromFile($installed);
            foreach ($content as $library) {
                if ($library['name'] == 'silverstripe/cow') {
                    return $library['version'];
                }
            }
        } else {
            return $this->getVersionInDir(dirname($directory));
        }
    }

    /**
     * Returns the folder of Cow Twig templates
     */
    public function getTwigTemplateDir(): string
    {
        return realpath(__DIR__ . '/../templates');
    }

    /**
     * Get the name of the application used to run the command, eg: cow or bin/cow
     *
     * @return string
     */
    public function getBinName()
    {
        return isset($_SERVER['argv'][0]) ? $_SERVER['argv'][0] : 'cow';
    }

    public function getLongVersion()
    {
        $version = $this->getVersionInDir(__DIR__);
        return "<comment>cow release tool</comment> <info>{$version}</info>";
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultCommands(): array
    {
        if (Application::isDevMode()) {
            echo "\nDEV_MODE is enabled, no changes will be pushed\n\n";
        } else {
            echo "\nDEV_MODE is NOT enabled, changes will be pushed!\n\n";
        }

        $commands = parent::getDefaultCommands();

        // Create dependencies
        $githubApi = new GitHubApi();
        $supportedModuleLoader = new SupportedModuleLoader();

        // What is this cow doing in here, stop it, get out
        $commands[] = new Commands\MooCommand();

        // Release sub-commands
        $commands[] = new Commands\Release\Create();
        $commands[] = new Commands\Release\Plan();
        $commands[] = new Commands\Release\Branch();
        $commands[] = new Commands\Release\Translate();
        $commands[] = new Commands\Release\Test();
        $commands[] = new Commands\Release\Changelog($this);
        $commands[] = new Commands\Release\MergeUp();

        // Publish sub-commands
        $commands[] = new Commands\Release\Tag();

        // Base release commands
        $commands[] = new Commands\Release\Release();
        $commands[] = new Commands\Release\Publish();

        // Module commands
        $commands[] = new Commands\Module\TranslateBuild();
        $commands[] = new Commands\Module\Sync\Metadata($supportedModuleLoader);

        // Schema commands
        $commands[] = new Commands\Schema\Validate();

        // GitHub commands
        $commands[] = new Commands\GitHub\RateLimit($githubApi);
        $commands[] = new Commands\GitHub\SyncLabels($supportedModuleLoader, $githubApi);

        return $commands;
    }
}
