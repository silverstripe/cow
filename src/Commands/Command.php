<?php

namespace SilverStripe\Cow\Commands;

use Symfony\Component\Console;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class Command extends Console\Command\Command
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $description;

    /**
     * @var InputInterface
     */
    protected $input;

    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * @var Console\Helper\ProgressBar
     */
    protected $progressBar;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName($this->name);
        $this->setDescription($this->description);
        $this->configureOptions();
    }

    /**
     * Setup custom options for this command
     */
    abstract protected function configureOptions();

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input = $input;
        $this->output = $output;
        $this->progressBar = new Console\Helper\ProgressBar($this->output);

        // Configure extra output formats
        $this->output->getFormatter()->setStyle('bold', new OutputFormatterStyle('blue'));

        $ret = $this->fire();
        // returning a default value here to satisfy
        // symfony/console/Command/Command.php::run()
        return is_int($ret) ? $ret : Console\Command\Command::SUCCESS;
    }

    /**
     * Defers to the subclass functionality.
     */
    abstract protected function fire();
}
