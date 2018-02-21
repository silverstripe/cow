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
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;

        // Configure extra output formats
        $this->output->getFormatter()->setStyle('bold', new OutputFormatterStyle('blue'));

        return $this->fire();
    }

    /**
     * Defers to the subclass functionality.
     */
    abstract protected function fire();
}
