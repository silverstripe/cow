<?php

namespace SilverStripe\Cow\Steps;

use Exception;
use SilverStripe\Cow\Commands\Command;
use SilverStripe\Cow\Utility\CommandRunner;
use SilverStripe\Cow\Utility\Logger;
use SilverStripe\Cow\Utility\StepCommandRunner;
use Symfony\Component\Console\Helper\ProcessHelper;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

abstract class Step
{
    /**
     * @var Command
     */
    protected $command;

    /**
     * Default env vars to set
     * @var array
     */
    protected $envs = [
        'SS_VENDOR_METHOD' => 'copy', // Ensure that vendor copies, rather than symlinks, assets
    ];

    public function __construct(Command $command)
    {
        $this->setCommand($command);
    }

    abstract public function getStepName();

    abstract public function run(InputInterface $input, OutputInterface $output);

    /*
     * Log a message with an optional format wrapper
     *
     * @param OutputInterface $output
     * @param string $message
     * @param string $format
     */
    public function log(OutputInterface $output, $message, $format = '')
    {
        Logger::log($output, $message, $this->getStepName(), $format);
    }

    /**
     * @return Command
     */
    public function getCommand()
    {
        return $this->command;
    }

    /**
     * @param Command $command
     * @return $this
     */
    public function setCommand($command)
    {
        $this->command = $command;
        return $this;
    }

    /**
     * @return ProcessHelper
     */
    protected function getProcessHelper()
    {
        return $this->getCommand()->getHelper('process');
    }

    /**
     * @return QuestionHelper
     */
    protected function getQuestionHelper()
    {
        return $this->getCommand()->getHelper('question');
    }

    /**
     * Run an arbitrary command
     *
     * To display errors/output make sure to run with -vvv
     *
     * @param OutputInterface $output
     * @param string|array|Process $command An instance of Process or an array of arguments to escape and run
     * or a command to run
     * @param string|null $error An error message that must be displayed if something went wrong
     * @param bool $exceptionOnError If an error occurs, this message is an exception rather than a notice
     * @param bool $allowDebugVerbosity If false temporarily change -vvv to -vv so command results are not echoed
     * @return bool|string Output, or false if error
     * @throws Exception
     */
    public function runCommand(
        OutputInterface $output,
        $command,
        $error = null,
        $exceptionOnError = true,
        $allowDebugVerbosity = true
    ) {
        $helper = $this->getProcessHelper();

        // Prepare unbound command
        if (is_array($command)) {
            $process = new Process($command);
        } elseif ($command instanceof Process) {
            $process = $command;
        } else {
            $process = new Process([$command]);
        }

        // Set all default env vars
        $process->setEnv($this->envs);

        // Run it
        $process->setTimeout(null);

        $verbosity = $output->getVerbosity();
        if (!$allowDebugVerbosity && $output->isDebug()) {
            $output->setVerbosity(OutputInterface::VERBOSITY_VERY_VERBOSE);
        }
        $result = $helper->run($output, $process, $error);
        $output->setVerbosity($verbosity);

        // And cleanup
        if ($result->isSuccessful()) {
            return $result->getOutput();
        } else {
            if ($exceptionOnError) {
                $error = $error ?: "Command did not run successfully";
                throw new Exception($error);
            }
            return false;
        }
    }

    /**
     * @param OutputInterface $output
     * @return CommandRunner
     */
    public function getCommandRunner(OutputInterface $output)
    {
        return new StepCommandRunner($this, $output);
    }
}
