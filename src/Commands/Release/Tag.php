<?php

namespace SilverStripe\Cow\Commands\Release;

use SilverStripe\Cow\Steps\Release\PublishRelease;

/**
 * Perform tagging and push of release
 */
class Tag extends Publish
{
    /**
     * @var string
     */
    protected $name = 'release:tag';

    protected $description = 'Tag modules and push';

    protected function fire()
    {
        // Get arguments
        $project = $this->getProject();
        $releasePlan = $this->getReleasePlan();

        // Does bulk of module publishing, rewrite of dev branches, rewrite of tags, and actual tagging
        $publish = new PublishRelease($this, $project, $releasePlan);
        $publish->run($this->input, $this->output);
    }
}
