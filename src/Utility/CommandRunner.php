<?php

namespace SilverStripe\Cow\Utility;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/**
 * Interface for exposing the ability to run console commands
 */
interface CommandRunner
{

    /**
     * Run the command
     *
     * @param string|array|Process $command An instance of Process or an array of arguments to escape and run
     * or a command to run
     * @param string|null $error An error message that must be displayed if something went wrong
     * @param bool $exceptionOnError If an error occurs, this message is an exception rather than a notice
     * @return bool|string Output, or false if error
     * @throw Exception
     */
    public function runCommand($command, $error = null, $exceptionOnError = true);

    /*
     * Log a message with an optional format wrapper
     *
     * @param string $message
     * @param string $format
     * @param int $verbosity Min verbosity
     */
    public function log($message, $format = '', $verbosity = OutputInterface::VERBOSITY_NORMAL);
}
