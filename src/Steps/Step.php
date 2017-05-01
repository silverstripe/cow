<?php

namespace SilverStripe\Cow\Steps;

use Exception;
use SilverStripe\Cow\Commands\Command;
use SilverStripe\Cow\Utility\CommandRunner;
use SilverStripe\Cow\Utility\StepCommandRunner;
use Symfony\Component\Console\Helper\ProcessHelper;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\ProcessBuilder;

abstract class Step
{
    /**
     * @var Command
     */
    protected $command;

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
        $name = $this->getStepName();
        $text = "<bold>[{$name}]</bold> ";
        if ($format) {
            $text .= "<{$format}>{$message}</{$format}>";
        } else {
            $text .= $message;
        }
        $output->writeln($text);
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
     * @return bool|string Output, or false if error
     * @throws Exception
     */
    public function runCommand(OutputInterface $output, $command, $error = null, $exceptionOnError = true)
    {
        $helper = $this->getProcessHelper();

        // Prepare unbound command
        if (is_array($command)) {
            $process = ProcessBuilder::create($command)->getProcess();
        } elseif ($command instanceof Process) {
            $process = $command;
        } else {
            $process = new Process($command);
        }

        // Run it
        $process->setTimeout(null);
        $result = $helper->run($output, $process, $error);

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
