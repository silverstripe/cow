<?php

namespace SilverStripe\Cow\Steps\Release;

use Exception;
use Generator;
use InvalidArgumentException;
use SilverStripe\Cow\Commands\Command;
use SilverStripe\Cow\Model\Modules\Module;
use SilverStripe\Cow\Model\Modules\Project;
use SilverStripe\Cow\Model\Release\LibraryRelease;
use SilverStripe\Cow\Utility\Translations;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Synchronise all translations with transifex, merging these with strings detected in code files
 *
 * Basic process follows:
 *  - Set mtime on all local files to long ago (1 year in the past?) because tx pull breaks on new files and
 *    won't update them
 *  - Pull all source files from transifex with the below:
 *      `tx pull -a -s -f --minimum-perc=10`
 *  - Detect all new translations, making sure to merge in changes
 *      `./framework/sake dev/tasks/i18nTextCollectorTask "flush=all" "merge=1"
 *  - Detect all new JS translations in a similar way (todo)
 *  - Generate javascript from js source files
 *  - Push up all source translations
 *      `tx push -s`
 *  - Commit changes to source control (without push)
 */
class UpdateTranslations extends ReleaseStep
{
    /**
     * Min tx client version

     * @var string
     */
    protected $txVersion = '0.12';

    /**
     * Min % difference required for tx updates
     *
     * @var int
     */
    protected $txMinimumPerc = 10;

    /**
     * Flag whether we should do push on each git repo
     *
     * @var bool
     */
    protected $doPush;

    /**
     * Map of file paths to original JS master files.
     * This is necessary prior to pulling master translations, since we need to do a
     * post-pull merge locally, before pushing up back to transifex. Unlike PHP
     * translations, text collector is unable to re-generate javascript translations, so
     * instead we back them up here.
     *
     * @var array
     */
    protected $originalJSMasters = array();

    /**
     * Create a new translation step
     *
     * @param Command $command Parent command
     * @param Project $project Root project
     * @param LibraryRelease $plan
     * @param bool $doPush Do git push at end
     */
    public function __construct(Command $command, Project $project, LibraryRelease $plan, $doPush = false)
    {
        parent::__construct($command, $project, $plan);
        $this->setDoPush($doPush);
    }

    public function getStepName()
    {
        return 'translations';
    }

    public function run(InputInterface $input, OutputInterface $output)
    {
        $modules = iterator_to_array($this->getTranslatableModules(), false);
        $count = count($modules);
        if ($count === 0) {
            $this->log($output, "No modules require translation: skipping");
            return;
        }

        $this->log($output, "Updating translations for {$count} module(s)");
        $this->storeJavascript($output, $modules);
        $this->pullSource($output, $modules);
        $this->cleanYaml($output, $modules);
        $this->mergeJavascriptMasters($output);
        $this->collectStrings($output, $modules);
        $this->generateJavascript($output, $modules);
        $this->pushSource($output, $modules);
        $this->commitChanges($output, $modules);
        $this->log($output, 'Translations complete');
    }

    /**
     * @deprecated 2.3..3.0
     * @param OutputInterface $output
     */
    protected function checkTransifexVersion(OutputInterface $output)
    {
        // noop
    }

    /**
     * Backup local javascript masters
     *
     * @param OutputInterface $output
     * @param Module[] $modules
     */
    protected function storeJavascript(OutputInterface $output, $modules)
    {
        $this->log($output, "Backing up local javascript masters");
        // Backup files prior to replacing local copies with transifex master
        $this->originalJSMasters = [];
        foreach ($modules as $module) {
            $jsPath = $module->getJSLangDirectories();
            foreach ((array)$jsPath as $path) {
                $masterPath = "{$path}/src/en.js";
                $this->log($output, "Backing up <info>$masterPath</info>");
                if (file_exists($masterPath)) {
                    $masterJSON = $this->decodeJSONFile($masterPath);
                    $this->originalJSMasters[$masterPath] = $masterJSON;
                }
            }
        }
        $this->log($output, "Finished backing up " . count($this->originalJSMasters) . " javascript masters");
    }

    /**
     * Check for errors in the last json_decode
     *
     * @param string $path
     * @throws \Exception
     */
    protected function checkJsonDecode($path)
    {
        if (json_last_error()) {
            $message = json_last_error_msg();
            throw new Exception("Error json decoding file {$path}: {$message}");
        }
    }

    /**
     * Merge back master files with any local contents
     *
     * @param OutputInterface $output
     */
    protected function mergeJavascriptMasters(OutputInterface $output)
    {
        // skip if no translations for this module
        if (empty($this->originalJSMasters)) {
            return;
        }
        $this->log($output, "Merging local javascript masters");
        foreach ($this->originalJSMasters as $path => $contentJSON) {
            if (file_exists($path)) {
                $masterJSON = $this->decodeJSONFile($path);
                $contentJSON = array_merge($masterJSON, $contentJSON);
            }
            // Re-order values
            ksort($contentJSON);

            // Write back to local
            file_put_contents($path, json_encode($contentJSON, JSON_PRETTY_PRINT));
        }
        $this->log($output, "Finished merging " . count($this->originalJSMasters) . " javascript masters");
    }

    /**
     * Update sources from transifex
     *
     * @param OutputInterface $output
     * @param Module[] $modules List of modules
     */
    protected function pullSource(OutputInterface $output, $modules)
    {
        foreach ($modules as $module) {
            $name = $module->getName();
            $this->log(
                $output,
                "Pulling sources from transifex for <info>{$name}</info> (min %{$this->txMinimumPerc} delta)"
            );

            // Set mtime to a year ago so that transifex will see these as obsolete
            $ymlLang = $module->getLangDirectory();
            if ($ymlLang) {
                $touchCommand = sprintf(
                    'find %s -type f \( -name "*.yml" \) -exec touch -t %s {} \;',
                    $ymlLang,
                    date('YmdHi.s', strtotime('-1 year'))
                );
                $this->runCommand($output, $touchCommand);
            }
            $jsLangDirs = $module->getJSLangDirectories();
            foreach ($jsLangDirs as $jsLangDir) {
                $touchCommand = sprintf(
                    'find %s -type f \( -name "*.js*" \) -exec touch -t %s {} \;',
                    $jsLangDir,
                    date('YmdHi.s', strtotime('-1 year'))
                );
                $this->runCommand($output, $touchCommand);
            }

            // Run tx pull
            $pullCommand = sprintf(
                '(cd %s && tx pull -a -s -f --minimum-perc=%d)',
                $module->getDirectory(),
                $this->txMinimumPerc
            );
            $this->runCommand($output, $pullCommand);
        }
    }

    /**
     * Tidy yaml files using symfony yaml
     *
     * @param OutputInterface $output
     * @param Module[] $modules List of modules
     */
    protected function cleanYaml(OutputInterface $output, $modules)
    {
        foreach ($modules as $module) {
            $name = $module->getName();
            $this->log(
                $output,
                "Cleaning YAML sources for <info>{$name}</info>"
            );

            $num = 0;
            foreach (glob($module->getLangDirectory() . "/*.yml") as $sourceFile) {
                $dirty = file_get_contents($sourceFile);
                $sourceData = Yaml::parse($dirty);
                $cleaned = Yaml::dump($sourceData, 9999, 2);
                if ($dirty !== $cleaned) {
                    $num++;
                    file_put_contents($sourceFile, $cleaned);
                }
            }

            $this->log($output, "<info>{$num}</info> yml files cleaned");
        }
    }

    /**
     * Run text collector on the given modules
     *
     * @param OutputInterface $output
     * @param Module[] $modules List of modules
     */
    protected function collectStrings(OutputInterface $output, $modules)
    {
        $this->log($output, "Running i18nTextCollectorTask");

        // Get code dirs for each module
        $dirs = array();
        foreach ($modules as $module) {
            $dirs[] = $module->getI18nTextCollectorName();
        }

        $sakeCommand = sprintf(
            '(cd %s && %s dev/tasks/i18nTextCollectorTask "flush=all" "merge=1" "module=%s")',
            $this->getProject()->getDirectory(),
            $this->getProject()->getSakePath(),
            implode(',', $dirs)
        );
        $this->runCommand($output, $sakeCommand, "Error encountered running i18nTextCollectorTask");
    }

    /**
     * Generate javascript for all modules
     *
     * @param OutputInterface $output
     * @param Module[] $modules
     */
    protected function generateJavascript(OutputInterface $output, $modules)
    {
        Translations::generateJavascript($this->getCommandRunner($output), $modules);
    }

    /**
     * Push source updates to transifex
     *
     * @param OutputInterface $output
     * @param Module[] $modules
     */
    public function pushSource(OutputInterface $output, $modules)
    {
        $this->log($output, "Pushing updated sources to transifex");

        foreach ($modules as $module) {
            // Run tx pull
            $pushCommand = sprintf(
                '(cd %s && tx push -s)',
                $module->getDirectory()
            );
            $moduleName = $module->getName();
            $this->runCommand($output, $pushCommand, "Error pushing module {$moduleName} to origin");
        }
    }

    /**
     * Commit changes for all modules
     *
     * @param OutputInterface $output
     * @param Module[] $modules
     */
    public function commitChanges(OutputInterface $output, $modules)
    {
        $this->log($output, 'Committing translations to git');

        foreach ($modules as $module) {
            $repo = $module->getRepository();

            // Add all changes
            $jsPath = $module->getJSLangDirectories();
            $langPath = $module->getLangDirectory();
            foreach (array_merge((array)$jsPath, (array)$langPath) as $path) {
                if (is_dir($path)) {
                    $repo->run("add", array($path . "/*"));
                }
            }

            // Commit changes if any exist
            $status = $repo->run("status");
            if (stripos($status, 'Changes to be committed:')) {
                $this->log($output, "Comitting changes for module " . $module->getName());
                $repo->run("commit", array("-m", "ENH Update translations"));
            }

            // Do push if selected
            if ($this->doPush) {
                $this->log($output, "Pushing upstream for module " . $module->getName());
                $repo->run("push", array("origin"));
            }
        }
    }

    /**
     * @return bool
     */
    public function isDoPush()
    {
        return $this->doPush;
    }

    /**
     * @param bool $doPush
     * @return $this
     */
    public function setDoPush($doPush)
    {
        $this->doPush = $doPush;
        return $this;
    }

    /**
     * @return Module[]|Generator
     */
    public function getTranslatableModules()
    {
        // Don't translate upgrade-only
        foreach ($this->getNewReleases() as $release) {
            // Only translate modules with .tx directories
            $library = $release->getLibrary();
            if ($library instanceof Module && $library->isTranslatable()) {
                yield $library;
            }
        }
    }

    /**
     * Decode json file
     *
     * @param string $path
     * @return array
     */
    protected function decodeJSONFile($path)
    {
        $masterJSON = json_decode(file_get_contents($path), true);
        $this->checkJsonDecode($path);
        return $masterJSON;
    }
}
