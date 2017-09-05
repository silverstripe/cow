<?php

namespace SilverStripe\Cow\Steps\Release;

use Exception;
use SilverStripe\Cow\Commands\Command;
use SilverStripe\Cow\Model\Project;
use SilverStripe\Cow\Model\ReleaseVersion;
use SilverStripe\Cow\Steps\Step;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\ProcessBuilder;

/**
 * Generate a new archive file for cms, framework in tar.gz and zip formats
 *
 * @author dmooyman
 */
class BuildArchive extends Step
{
    /**
     * @var ReleaseVersion
     */
    protected $version;

    /**
     * @var Project
     */
    protected $project;

    /**
     * Build archives
     *
     * @param Command $command
     * @param ReleaseVersion $version
     * @param string $directory Where to translate
     * @param string $awsProfile Name of aws profile to use
     */
    public function __construct(Command $command, ReleaseVersion $version, $directory = '.')
    {
        parent::__construct($command);

        $this->version = $version;
        $this->project = new Project($directory);
    }

    /**
     * @return Project
     */
    public function getProject()
    {
        return $this->project;
    }

    /**
     * @return ReleaseVersion
     */
    public function getVersion()
    {
        return $this->version;
    }

    public function getStepName()
    {
        return 'archive';
    }

    public function run(InputInterface $input, OutputInterface $output)
    {
        $this->log($output, "Generating new archive files");
        $path = $this->createProject($input, $output);
        $this->buildFiles($output, $path);
        $this->log($output, 'Archive complete');
    }

    /**
     * Remove a directory and all subdirectories and files.
     *
     * @param string $folder Absolute folder path
     */
    protected function unlink($folder)
    {
        if (!file_exists($folder)) {
            return;
        }

        // remove a file encountered by a recursive call.
        if (is_file($folder) || is_link($folder)) {
            unlink($folder);
            return;
        }

        // Remove folder
        $dir = opendir($folder);
        while ($file = readdir($dir)) {
            if ($file == '.' || $file == '..') {
                continue;
            }
            $this->unlink($folder . '/' . $file);
        }
        closedir($dir);
        rmdir($folder);
    }

    /**
     * Copy file
     *
     * @param string $from
     * @param string $to
     * @throws Exception
     */
    protected function copy($from, $to)
    {
        $this->unlink($to);

        // Copy file if not a folder
        if (!is_dir($from)) {
            if (copy($from, $to) === false) {
                throw new Exception("Could not copy from {$from} to {$to}");
            }
            return;
        }

        // Create destination
        if (mkdir($to) === false) {
            throw new Exception("Could not create destination folder {$to}");
        }

        // Iterate files
        $dir = opendir($from);
        while (false !== ($file = readdir($dir))) {
            if ($file == '.' || $file === '..') {
                continue;
            }
            $this->copy("{$from}/{$file}", "{$to}/{$file}");
        }
        closedir($dir);
    }

    /**
     * Write content to file
     *
     * @param string $path
     * @param string $content
     * @throws Exception
     */
    protected function write($path, $content)
    {
        $result = file_put_contents($path, $content);
        if ($result === false) {
            throw new Exception("Could not write to {$path}");
        }
    }

    /**
     * Build a project of the given version in a temporary folder, and return the path to this
     *
     * @param OutputInterface $output
     * @return string Path to temporary project
     */
    protected function createProject(InputInterface $input, OutputInterface $output)
    {
        // Get files
        $version = $this->getVersion()->getValue();
        $cmsArchive = "SilverStripe-cms-v{$version}";
        $frameworkArchive = "SilverStripe-framework-v{$version}";

        // Check path exists and is empty
        $path = sys_get_temp_dir() . '/archiveTask';
        $this->log($output, "Creating temporary project at {$path}");
        $this->unlink($path);
        mkdir($path);

        // Copy composer.phar
        $this->log($output, "Getting composer.phar");
        $this->copy('http://getcomposer.org/composer.phar', "{$path}/composer.phar");

        // Install to this location
        $version = $this->version->getValue();
        $this->log($output, "Installing version {$version}");
        $pathArg = escapeshellarg($path);
        $command = array(
            'php',
            'composer.phar',
            'create-project',
            'silverstripe/installer',
            $cmsArchive,
            $version,
            '--prefer-dist',
            '--no-dev',
            '--ignore-platform-reqs',
        );
        if ($repo = $input->getOption('repository')) {
            $command[] = sprintf('--repository=%s', $repo);
            $command[] = '--no-install';
        }
        $process = ProcessBuilder::create($command)->setWorkingDirectory($path)->getProcess();
        try {
            $this->runCommand(
                $output,
                $process,
                "Could not install version {$version} from composer"
            );
        } catch (Exception $e) {
            if (!$repo) {
                throw $e;
            }
        }

        if ($repo) {
            $composerJSON = file_get_contents("{$path}/{$cmsArchive}/composer.json");
            $composerJSON = str_replace('"self.version"', sprintf('"%s"', $version), $composerJSON);
            $this->unlink("{$path}/{$cmsArchive}/composer.json");
            $this->write("{$path}/{$cmsArchive}/composer.json", $composerJSON);
            $this->copy("{$path}/{$cmsArchive}/composer.json", "{$path}/{$cmsArchive}/composer.json.bu");
            if (file_exists($repo)) {
                $repo = 'file://' . $repo;
            }
            $this->runCommand(
                $output,
                array('composer', 'config', 'repositories.repo', 'composer', $repo, '-d', "{$path}/{$cmsArchive}")
            );
            $this->runCommand(
                $output,
                array('composer', 'update', '--ignore-platform-reqs', '--prefer-dist', '--no-dev', '-d', "{$path}/{$cmsArchive}")
            );
        }

        // Copy composer.phar to the project
        // Write version info to the core folders (shouldn't be in version control)
        $this->log($output, "Copying additional files");
        $this->copy("{$path}/composer.phar", "{$path}/{$cmsArchive}/composer.phar");
        $this->write("{$path}/{$cmsArchive}/framework/silverstripe_version", $version);
        $this->write("{$path}/{$cmsArchive}/cms/silverstripe_version", $version);

        // Copy to framework folder
        $this->log($output, "Create framework-only project");
        $this->copy("{$path}/{$cmsArchive}/", "{$path}/{$frameworkArchive}/");
        $pathArg = escapeshellarg("{$path}/{$frameworkArchive}");
        $remove = [
            'silverstripe/cms',
            'silverstripe/siteconfig',
            'silverstripe/reports',
            'silverstripe/asset-admin',
            'silverstripe/campaign-admin',
            'silverstripe/admin',
            'silverstripe/graphql',
            'silverstripe/errorpage'
        ];
        $this->runCommand(
            $output,
            "cd {$pathArg} && php composer.phar remove " . implode(' ', $remove) . " --update-no-dev",
            "Could not generate framework only version"
        );

        // Remove development files not needed in the archive package
        $this->log($output, "Remove development files");
        foreach (array("{$path}/{$cmsArchive}", "{$path}/{$frameworkArchive}") as $archivePath) {
            $this->unlink("{$archivePath}/cms/tests/");
            $this->unlink("{$archivePath}/framework/tests/");
            $this->unlink("{$archivePath}/framework/admin/tests/");
            $this->unlink("{$archivePath}/reports/tests/");
            $this->unlink("{$archivePath}/siteconfig/tests/");
            $this->unlink("{$archivePath}/framework/docs/");
            if ($repo) {
                $this->unlink("{$archivePath}/composer.json");
                $this->copy("{$archivePath}/composer.json.bu", "{$archivePath}/composer.json");
                $this->unlink("{$archivePath}/composer.json.bu");
            }
        }

        // Remove Page.php from framework-only module
        $this->unlink("{$path}/{$frameworkArchive}/mysite/code/Page.php");

        // Done
        return $path;
    }

    /**
     * Generate archives in each of the specified types from the temporary folder
     *
     * @param OutputInterface $output
     * @param string $path Location of project to archive
     */
    protected function buildFiles(OutputInterface $output, $path)
    {
        $version = $this->getVersion()->getValue();
        $cmsArchive = "SilverStripe-cms-v{$version}";
        $frameworkArchive = "SilverStripe-framework-v{$version}";
        $destination = $this->getProject()->getDirectory();

        // Build each version
        foreach (array($cmsArchive, $frameworkArchive) as $archive) {
            $sourceDirArg = escapeshellarg("{$path}/{$archive}/");

            // Build tar files
            $tarFile = "{$destination}/{$archive}.tar.gz";
            $this->log($output, "Building <info>$tarFile</info>");
            $tarFileArg = escapeshellarg($tarFile);
            $this->runCommand($output, "cd {$sourceDirArg} && tar -cvzf {$tarFileArg} .");

            // Build zip files
            $zipFile = "{$destination}/{$archive}.zip";
            $this->log($output, "Building <info>{$zipFile}</info>");
            $zipFileArg = escapeshellarg($zipFile);
            $this->runCommand($output, "cd {$sourceDirArg} && zip -rv {$zipFileArg} .");
        }
    }
}
