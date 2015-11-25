<?php

namespace SilverStripe\Cow\Steps\Release;

use SilverStripe\Cow\Commands\Command;
use SilverStripe\Cow\Model\ReleaseVersion;
use SilverStripe\Cow\Steps\ModuleStep;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Tag all modules
 *
 * @author dmooyman
 */
class TagModules extends ModuleStep
{
    /**
     * @var ReleaseVersion
     */
    protected $version;

    /**
     * Create module tag step
     *
     * @param Command $command
     * @param ReleaseVersion $version
     * @param string $directory
     * @param array $modules Optional list of modules to limit tagging to
     * @param bool $listIsExclusive If this list is exclusive. If false, this is inclusive
     */
    public function __construct(
        Command $command, ReleaseVersion $version, $directory = '.', $modules = array(), $listIsExclusive = false
    ) {
        parent::__construct($command, $directory, $modules, $listIsExclusive);
        $this->version = $version;
    }

    /**
     *
     * @return ReleaseVersion
     */
    public function getVersion()
    {
        return $this->version;
    }

    public function run(InputInterface $input, OutputInterface $output)
    {
        $tag = $this->getVersion()->getValue();
        $this->log($output, "Tagging modules as " . $tag);

        foreach ($this->getModules() as $module) {
            $this->log($output, "Tagging module " . $module->getName());
            $tags = $module->getTags();
            if(in_array($tag, $tags)) {
                $this->log($output, "Skipping existing tag: <info>{$tag}</info>");
            } else {
                $module->addTag($tag);
            }
        }
        
        $this->log($output, 'Tagging complete');
    }


    public function getStepName()
    {
        return 'tag';
    }
}
