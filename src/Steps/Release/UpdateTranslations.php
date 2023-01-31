<?php

namespace SilverStripe\Cow\Steps\Release;

use Exception;
use Generator;
use SilverStripe\Cow\Commands\Command;
use SilverStripe\Cow\Model\Modules\Module;
use SilverStripe\Cow\Model\Modules\Project;
use SilverStripe\Cow\Model\Release\LibraryRelease;
use SilverStripe\Cow\Utility\Translations;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;
use SilverStripe\Cow\Application;

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
 *  - Generate javascript from json source files
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
    protected $doGitPush = false;

    /**
     * Flag whether we should run `tx pull`, i18nTextCollectorTask and js/json update`
     *
     * @var bool
     */
    protected $doTransifexPullAndUpdate = true;

    /**
     * Flag whether we should run `tx push -s`
     *
     * @var bool
     */
    protected $doTransifexPush = false;

    /**
     * Map of file paths to original json files.
     * This is necessary prior to pulling master translations, since we need to do a
     * post-pull merge locally, before pushing up back to transifex. This avoids
     * any new keys that hadn't yet been pushed to transifex from being deleted.
     *
     * @var array
     */
    protected $originalJson = array();

    /**
     * Map of file paths to original yaml files.
     * This is necessary prior to pulling translations, since we need to do a
     * post-pull merge locally, before pushing up back to transifex. This avoids
     * any new keys that hadn't yet been pushed to transifex from being deleted.
     *
     * @var array
     */
    protected $originalYaml = array();

    /**
     * Create a new translation step
     *
     * @param Command $command Parent command
     * @param Project $project Root project
     * @param LibraryRelease $plan
     * @param bool $doPush Do git push at end
     */
    public function __construct(Command $command, Project $project, LibraryRelease $plan)
    {
        parent::__construct($command, $project, $plan);
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
        if ($this->doTransifexPullAndUpdate) {
            $this->log($output, "Updating translations for {$count} module(s)");
            $this->storeJson($output, $modules);
            $this->storeYaml($output, $modules);
            $this->transifexPullSource($output, $modules);
            $this->mergeYaml($output);
            $this->cleanYaml($output, $modules);
            $this->mergeJson($output);
            $this->collectStrings($output, $modules);
            $this->generateJavascript($output, $modules);
        }
        if ($this->doTransifexPush) {
            $this->transifexPushSource($output, $modules);
        }
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
     * Backup local yaml files in memory
     *
     * @param OutputInterface $output
     * @param Module[] $modules
     */
    protected function storeYaml(OutputInterface $output, array $modules): void
    {
        $this->log($output, 'Backing up local yaml files');
        // Backup files prior to replacing local copies with transifex
        $this->originalYaml = [];
        foreach ($modules as $module) {
            foreach (glob($module->getLangDirectory() . '/*.yml') as $path) {
                if ($output->isVerbose()) {
                    $this->log($output, "Backing up <info>$path</info>");
                }
                $rawYaml = file_get_contents($path);
                $this->originalYaml[$path] = Yaml::parse($rawYaml);
            }
        }
        $this->log($output, 'Finished backing up ' . count($this->originalYaml) . ' yaml files');
    }

    /**
     * Merge any missing keys from old yaml content into yaml files
     *
     * @param OutputInterface $output
     */
    protected function mergeYaml(OutputInterface $output): void
    {
        // skip if no translations for this run
        if (empty($this->originalYaml)) {
            return;
        }
        $this->log($output, 'Merging local yaml files');
        foreach ($this->originalYaml as $path => $contentYaml) {
            if (file_exists($path)) {
                // If there are any keys in the original yaml that are missing now, add them back in.
                $rawYaml = file_get_contents($path);
                $parsedYaml = Yaml::parse($rawYaml);
                $contentYaml = $this->arrayMergeRecursive($contentYaml, $parsedYaml);
            }

            // Write back to local
            file_put_contents($path, Yaml::dump($contentYaml));
        }
        $this->log($output, 'Finished merging ' . count($this->originalYaml) . ' yaml files');
    }

    /**
     * Backup local json files
     *
     * @param OutputInterface $output
     * @param Module[] $modules
     */
    protected function storeJson(OutputInterface $output, $modules)
    {
        $this->log($output, 'Backing up local json files');
        // Backup files prior to replacing local copies with transifex
        $this->originalJson = [];
        foreach ($modules as $module) {
            $jsPath = $module->getJSLangDirectories();
            foreach ((array)$jsPath as $langDir) {
                foreach (glob($langDir . '/src/*.json') as $path) {
                    if ($output->isVerbose()) {
                        $this->log($output, "Backing up <info>$path</info>");
                    }
                    $this->originalJson[$path] = $this->decodeJSONFile($path);
                }
            }
        }
        $this->log($output, 'Finished backing up ' . count($this->originalJson) . ' json files');
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
     * Merge any missing keys from old json content into json files
     *
     * @param OutputInterface $output
     */
    protected function mergeJson(OutputInterface $output)
    {
        // skip if no translations for this run
        if (empty($this->originalJson)) {
            return;
        }
        $this->log($output, 'Merging local json files');
        foreach ($this->originalJson as $path => $contentJSON) {
            if (file_exists($path)) {
                // If there are any keys in the original json that are missing now, add them back in.
                $parsedJSON = $this->decodeJSONFile($path);
                $contentJSON = array_merge($contentJSON, $parsedJSON);
            }

            // Write back to local
            file_put_contents($path, json_encode($contentJSON, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        }
        $this->log($output, 'Finished merging ' . count($this->originalJson) . ' json files');
    }

    /**
     * Update sources from transifex
     *
     * @param OutputInterface $output
     * @param Module[] $modules List of modules
     */
    protected function transifexPullSource(OutputInterface $output, $modules)
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
                $jsLangDir = $jsLangDir . '/src';
                $touchCommand = sprintf(
                    'find %s -type f \( -name "*.json*" \) -exec touch -t %s {} \;',
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
    protected function transifexPushSource(OutputInterface $output, $modules)
    {
        $this->log($output, "Pushing updated sources to transifex");
        if (Application::isDevMode()) {
            echo "Not pushing to transifex because DEV_MODE is enabled\n";
            return;
        }
        foreach ($modules as $module) {
            $pushCommand = sprintf('(cd %s && tx push -s)', $module->getDirectory());
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
                $repo->run("commit", array("-m", "Update translations"));
            }

            // Do push if selected
            if (Application::isDevMode()) {
                echo "Not pushing changes because DEV_MODE is enabled\n";
            } else {
                if ($this->doGitPush) {
                    $this->log($output, "Pushing upstream for module " . $module->getName());
                    $repo->run("push", array("origin"));
                }
            }
        }
    }

    /**
     * @return bool
     */
    public function getDoGitPush()
    {
        return $this->doGitPush;
    }

    /**
     * @param bool $doGitPush
     * @return $this
     */
    public function setDoGitPush($doGitPush)
    {
        $this->doGitPush = $doGitPush;
        return $this;
    }

    /**
     * @return bool
     */
    public function getDoTransifexPullAndUpdate()
    {
        return $this->doTransifexPullAndUpdate;
    }

    /**
     * @param bool $doTransifexPullAndUpdate
     * @return $this
     */
    public function setDoTransifexPullAndUpdate($doTransifexPullAndUpdate)
    {
        $this->doTransifexPullAndUpdate = $doTransifexPullAndUpdate;
        return $this;
    }

    /**
     * @return bool
     */
    public function getDoTransifexPush()
    {
        return $this->doTransifexPush;
    }

    /**
     * @param bool $doTransifexPush
     * @return $this
     */
    public function setDoTransifexPush($doTransifexPush)
    {
        $this->doTransifexPush = $doTransifexPush;
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

    /**
     * Recursively merges two arrays.
     *
     * Behaves similar to array_merge_recursive(), however it only merges
     * values when both are arrays rather than creating a new array with
     * both values, as the PHP version does.
     *
     * @param array $array1
     * @param array $array2
     * @return array
     */
    private function arrayMergeRecursive(array $array1, array $array2): array
    {
        foreach ($array2 as $key => $value) {
            if (is_array($value) && array_key_exists($key, $array1) && is_array($array1[$key])) {
                $array1[$key] = $this->arrayMergeRecursive($array1[$key], $value);
            } else {
                $array1[$key] = $value;
            }
        }

        return $array1;
    }
}
