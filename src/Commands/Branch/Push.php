<?php

namespace SilverStripe\Cow\Commands\Branch;

use SilverStripe\Cow\Commands\Module\Module;
use SilverStripe\Cow\Steps\Release\PushRelease;

/**
 * Pushes up changes to a branch
 */
class Push extends Module
{
    /**
     * @var string
     */
    protected $name = 'branch:push';

    protected $description = 'Push branches';

    protected function fire()
    {
        $directory = $this->getInputDirectory();
        $modules = $this->getInputModules();
        $listIsExclusive = $this->getInputExclude();

        $merge = new PushRelease($this, $directory, $modules, $listIsExclusive);
        $merge->run($this->input, $this->output);
    }
}
