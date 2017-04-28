<?php

namespace SilverStripe\Cow;

use SilverStripe\Cow\Commands;
use Symfony\Component\Console;

class Application extends Console\Application
{
    public function getLongVersion()
    {
        return '<info>cow release tool</info>';
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultCommands()
    {
        $commands = parent::getDefaultCommands();

        // What is this cow doing in here, stop it, get out
        $commands[] = new Commands\MooCommand();

        // Release sub-commands
        $commands[] = new Commands\Release\Create();
        $commands[] = new Commands\Release\Plan();
        $commands[] = new Commands\Release\Branch();
        $commands[] = new Commands\Release\Translate();
        $commands[] = new Commands\Release\Test();
        $commands[] = new Commands\Release\Changelog();
        $commands[] = new Commands\Release\Tag();
        $commands[] = new Commands\Release\Archive();
        $commands[] = new Commands\Release\Upload();

        // Base release commands
        $commands[] = new Commands\Release\Release();
        $commands[] = new Commands\Release\Publish();

        // Module commands
        $commands[] = new Commands\Module\TranslateBuild();

        return $commands;
    }
}
