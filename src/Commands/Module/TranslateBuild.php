<?php

namespace SilverStripe\Cow\Commands\Module;

use SilverStripe\Cow\Commands\Command;
use SilverStripe\Cow\Model\Modules\Module;
use SilverStripe\Cow\Model\Modules\Project;
use SilverStripe\Cow\Steps\Module\BuildTranslations;
use SilverStripe\Cow\Steps\Release\UpdateTranslations;

/**
 * Build translate js files from source
 */
class TranslateBuild extends Command
{
    protected $name = 'module:translate:build';

    protected $description = 'Rebuild JS files';

    /**
     * Setup custom options for this command
     */
    protected function configureOptions()
    {
    }

    protected function fire()
    {
        // Module is current dir
        // @todo Formalise this arg
        $module = new Module(getcwd());

        // Update all translations
        $translate = new BuildTranslations($this, $module);
        $translate->run($this->input, $this->output);
    }
}
