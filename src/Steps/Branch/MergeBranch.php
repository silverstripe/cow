<?php

namespace SilverStripe\Cow\Steps\Branch;

use Gitonomy\Git\Exception\ProcessException;
use SilverStripe\Cow\Commands\Command;
use SilverStripe\Cow\Model\Modules\Module;
use SilverStripe\Cow\Steps\Release\ModuleStep;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

/**
 * Class MergeBranch
 *
 * @package SilverStripe\Cow\Steps\Branch
 */
class MergeBranch extends ModuleStep
{
    /**
     * From branch
     *
     * @var string
     */
    protected $from = null;

    /**
     * To branch
     *
     * @var string
     */
    protected $to = null;

    /**
     * @var bool
     */
    protected $push = false;

    /**
     * @var bool
     */
    protected $interactive = false;

    /**
     * List of repos with conflicts
     *
     * @var array
     */
    protected $conflicts = [];

    public function __construct(
        Command $command,
        $directory,
        $modules,
        $listIsExclusive,
        $from,
        $to,
        $push,
        $interactive
    ) {
        parent::__construct($command, $directory, $modules, $listIsExclusive);
        $this->setFrom($from);
        $this->setTo($to);
        $this->setPush($push);
        $this->setInteractive($interactive);
    }

    public function getStepName()
    {
        return 'merge';
    }

    public function run(InputInterface $input, OutputInterface $output)
    {
        $this->log(
            $output,
            "Merging from <info>" . $this->getFrom() . "</info> to <info>" . $this->getTo() . "</info>"
        );
        if ($this->getPush()) {
            $this->log($output, "Successful merges will be pushed to origin");
        }

        $this->conflicts = [];
        foreach ($this->getModules() as $module) {
            $this->mergeModule($input, $output, $module);
        }

        // Display output
        if ($this->conflicts) {
            $this->log($output, "Merge conflicts exist which must be resolved manually:");
            foreach ($this->conflicts as $module) {
                /** @var \SilverStripe\Cow\Model\Modules\Module $module */
                $this->log($output, "<comment>" . $module->getDirectory() . "</comment>");
            }
        } else {
            $this->log($output, "All modules were merged without any conflicts");
        }
    }

    /**
     * Merge the given branches on this module
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param \SilverStripe\Cow\Model\Modules\Module $module
     * @throws \Exception
     */
    protected function mergeModule(InputInterface $input, OutputInterface $output, Module $module)
    {
        $this->log($output, "Merging module <info>" . $module->getName() . "</info>");

        $module->fetch($output);

        try {
            $module->checkout($output, $this->getFrom());
            $module->checkout($output, $this->getTo());
        } catch (\Exception $ex) {
            // Sometimes modules don't exist in either source or destination branch;
            // Treat this as a warning rather than an error.
            $this->log($output, "Skipping module with error: <error>" . $ex->getMessage() . "</error>");
            return;
        }

        try {
            // Create merge
            $repository = $module->getRepository($output);
            $message = sprintf("Merge %s into %s", $this->getFrom(), $this->getTo());
            $result = $repository->run('merge', [
                $this->getFrom(),
                '--no-commit',
                '--no-ff',
                '-m',
                $message
            ]);

            // Skip if there is nothing to merge
            if (stripos($result, "Already up-to-date.") === 0) {
                $this->log($output, "No changes to merge, skipping");
                return;
            }

            // check interactive mode
            if ($this->getInteractive()) {
                $helper = $this->getQuestionHelper();
                $this->log($output, "Changes pending review in <info>" . $module->getDirectory() . "</info>");
                $question = new ChoiceQuestion(
                    "Please review changes and confirm (defaults to continue): ",
                    array("continue", "skip", "abort"),
                    "continue"
                );
                $answer = $helper->ask($input, $output, $question);
                if ($answer !== "continue") {
                    $this->log($output, "Reverting merge...");
                    $repository->run('merge', ['--abort']);
                }

                // Let's get out of here!
                if ($answer === 'abort') {
                    die();
                }
                if ($answer === 'skip') {
                    return;
                }
            }

            // Commit merge
            $repository->run('commit', ['-m', $message]);
            $this->log($output, "Merge successful!");

            // Do upstream push
            if ($this->getPush()) {
                $this->log($output, "Pushing upstream");
                $module->pushTo();
            }
        } catch (ProcessException $ex) {
            // Module has conflicts; Please merge!
            $this->log($output, "<error>Merge conflict in module " . $module->getInstalledName() . "</error>");
            $this->conflicts[] = $module;
        }
    }

    /**
     * @return string
     */
    public function getFrom()
    {
        return $this->from;
    }

    /**
     * @param string $from
     * @return $this
     */
    public function setFrom($from)
    {
        $this->from = $from;
        return $this;
    }

    /**
     * @return string
     */
    public function getTo()
    {
        return $this->to;
    }

    /**
     * @param string $to
     * @return $this
     */
    public function setTo($to)
    {
        $this->to = $to;
        return $this;
    }

    /**
     * @return boolean
     */
    public function getPush()
    {
        return $this->push;
    }

    /**
     * @param boolean $push
     * @return $this
     */
    public function setPush($push)
    {
        $this->push = $push;
        return $this;
    }

    /**
     * @return bool
     */
    public function getInteractive()
    {
        return $this->interactive;
    }

    /**
     * @param bool $interactive
     * @return $this
     */
    public function setInteractive($interactive)
    {
        $this->interactive = $interactive;
        return $this;
    }
}
