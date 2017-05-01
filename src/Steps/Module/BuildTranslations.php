<?php


namespace SilverStripe\Cow\Steps\Module;

use SilverStripe\Cow\Commands\Command;
use SilverStripe\Cow\Model\Modules\Module;
use SilverStripe\Cow\Steps\Step;
use SilverStripe\Cow\Utility\Config;
use SilverStripe\Cow\Utility\Translations;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class BuildTranslations extends Step
{
    /**
     * @var Module
     */
    protected $module;

    public function __construct(Command $command, Module $module)
    {
        parent::__construct($command);
        $this->module = $module;
    }

    public function getStepName()
    {
        return 'module:translations:build';
    }

    public function run(InputInterface $input, OutputInterface $output)
    {
        $modules = [ $this->module ];
        Translations::generateJavascript($this->getCommandRunner($output), $modules);
    }
}
