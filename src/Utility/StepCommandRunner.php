<?php

namespace SilverStripe\Cow\Utility;

use Exception;
use SilverStripe\Cow\Steps\Step;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/**
 * Expose step capabilities to models
 */
class StepCommandRunner implements CommandRunner
{
    protected $output;
    protected $step;

    public function __construct(Step $step, OutputInterface $output)
    {
        $this->step = $step;
        $this->output = $output;
    }

    /**
     * Run the command
     *
     * @param string|array|Process $command An instance of Process or an array of arguments to escape and run
     * or a command to run
     * @param string|null $error An error message that must be displayed if something went wrong
     * @param bool $exceptionOnError If an error occurs, this message is an exception rather than a notice
     * @param bool $allowDebugVerbosity If false temporarily change -vvv to -vv so command results are not echoed
     * @return bool|string Output, or false if error
     * @throws Exception
     */
    public function runCommand($command, $error = null, $exceptionOnError = true, $allowDebugVerbosity = true)
    {
        return $this->step->runCommand($this->output, $command, $error, $exceptionOnError, $allowDebugVerbosity);
    }

    /*
     * Log a message with an optional format wrapper
     *
     * @param string $message
     * @param string $format
     * @param int $verbosity Min verbosity
     */
    public function log($message, $format = '', $verbosity = OutputInterface::VERBOSITY_NORMAL)
    {
        if ($this->output->getVerbosity() >= $verbosity) {
            $this->step->log($this->output, $message, $format);
        }
    }
}
