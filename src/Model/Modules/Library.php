<?php

namespace SilverStripe\Cow\Model\Modules;

use Exception;
use Gitonomy\Git\Reference\Branch;
use Gitonomy\Git\Repository;
use InvalidArgumentException;
use LogicException;
use SilverStripe\Cow\Model\Release\ComposerConstraint;
use SilverStripe\Cow\Model\Release\Version;
use SilverStripe\Cow\Utility\Config;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Represents a library which may not be a module
 */
class Library
{

    /**
     * Parent project (installer module)
     *
     * @var Project
     */
    protected $parent;

    /**
     * Directory of this module
     * Doesn't always match name (e.g. installer)
     *
     * @var string
     */
    protected $directory;

    /**
     * Direct child list
     *
     * @var LibraryList
     */
    protected $children;

    public function __construct($directory, Project $parent = null)
    {
        $this->directory = realpath($directory);
        $this->parent = $parent;

        if (!$this->isValid()) {
            throw new InvalidArgumentException("No library in directory \"{$this->directory}\"");
        }
    }

    /**
     * A project is valid if it has a root composer.json
     *
     * @return bool
     */
    public function isValid()
    {
        return $this->directory && realpath($this->directory . '/composer.json');
    }

    /**
     * Get the base directory this module is saved in
     *
     * @return string
     */
    public function getDirectory()
    {
        return $this->directory;
    }

    /**
     * Check if the given directory contains a library
     *
     * @param string $path
     * @return bool
     */
    public static function isLibraryPath($path)
    {
        return is_file("$path/composer.json");
    }

    /**
     * Get github slug. E.g. silverstripe/silverstripe-framework, or null
     * if not on github
     *
     * @return string
     */
    public function getGithubSlug()
    {
        $data = $this->getCowData();
        if (isset($data['github-slug'])) {
            return $data['github-slug'];
        }

        // Guess from git remote
        $remotes = $this->getRemotes();
        foreach ($remotes as $remote) {
            if (preg_match('#github.com/(?<slug>[^\\s/\\.]+/[^\\s/\\.]+)#', $remote, $matches)) {
                return $matches['slug'];
            }
        }
        return null;
    }

    /**
     * Get git repo for this module
     *
     * @param OutputInterface $output Optional output to log to
     * @return Repository
     */
    public function getRepository(OutputInterface $output = null)
    {
        $repo = new Repository($this->directory, array(
            'environment_variables' => array(
                'HOME' => getenv('HOME'),
                'SSH_AUTH_SOCK' => getenv('SSH_AUTH_SOCK')
            )
        ));
        // Include logger if requested
        if ($output) {
            $logger = new ConsoleLogger($output);
            $repo->setLogger($logger);
        }
        return $repo;
    }

    /**
     * Figure out the branch this composer is installed against
     *
     * @return string
     */
    public function getBranch()
    {
        $head = $this
            ->getRepository()
            ->getHead();
        if ($head instanceof Branch) {
            return $head->getName();
        }
        return null;
    }

    /**
     * Gets local branches
     *
     * @param string $remote If specified, select from remote instead. If ignored, select local
     * @return array
     */
    public function getBranches($remote = null)
    {
        // Query remotes
        $result = $this->getRepository()->run('branch', $remote ? ['-r'] : []);

        // Filter output
        $branches = [];
        foreach (preg_split('/\R/u', $result) as $line) {
            $line = trim($line);

            // Strip "current branch" indicator. E.g. "* 3.3.3"
            $line = preg_replace('/\\*\s+/', '', $line);

            // Skip empty lines, or anything with whitespace in it
            if (empty($line) || preg_match('#\s#', $line)) {
                continue;
            }
            // Check remote prefix
            if ($remote) {
                $prefix = "{$remote}/";
                if (stripos($line, $prefix) === 0) {
                    $line = substr($line, strlen($prefix));
                } else {
                    // Skip if not a branch on this remote
                    continue;
                }
            }

            // Save branch
            $branches[] = $line;
        }
        return $branches;
    }

    /**
     * List of remotes as array in name => url format
     *
     * @return array
     */
    public function getRemotes()
    {
        // Query remotes
        $result = $this->getRepository()->run('remote', ['-v']);

        // Filter output
        $remotes = [];
        foreach (preg_split('/\R/u', $result) as $line) {
            $line = trim($line);
            if (preg_match('/^(?<name>\w+)\s+(?<url>\S+)/', $line, $matches)) {
                $remotes[$matches['name']] = $matches['url'];
            }
        }

        // Sort so that "origin" is first
        uksort($remotes, function ($left, $right) {
            if ($left === 'origin') {
                return -1;
            }
            if ($right === 'origin') {
                return 1;
            }
            return 0;
        });

        return $remotes;
    }

    /**
     * Cached list of tags
     *
     * @var Version[]
     */
    protected $tags = [];

    /**
     * Gets all tags that exist in the repository
     *
     * @return Version[] Array where the keys are the version tags, and the values are Version object instances
     */
    public function getTags()
    {
        // Return cached values
        if ($this->tags) {
            return $this->tags;
        }

        // Get tag strings
        $repo = $this->getRepository();
        $result = $repo->run('tag');
        $tags = preg_split('~\R~u', $result);
        $tags = array_filter($tags);

        // Objectify tags
        $this->tags = [];
        foreach($tags as $tag) {
            // Skip invalid tags
            if (Version::parse($tag)) {
                $this->tags[$tag] = new Version($tag);
            }
        }
        return $this->tags;
    }

    /**
     * Tag this module
     *
     * @param string $tag
     */
    public function addTag($tag)
    {
        // Flush tag cache
        $this->tags = [];
        $repo = $this->getRepository();
        $repo->run('tag', array('-a', $tag, '-m', "Release {$tag}"));
    }

    /**
     * Push this branch to the given remote
     *
     * @param string $remote
     * @param bool $tags Push tags?
     * @throws Exception
     */
    public function pushTo($remote = 'origin', $tags = false)
    {
        // Validate branch
        $branch = $this->getBranch();
        if (!$branch) {
            throw new Exception("Module " . $this->getName() . " cannot push without a current branch");
        }

        // Check options
        $repo = $this->getRepository();
        $args = array($remote, "refs/heads/{$branch}");
        if ($tags) {
            $args[] = '--follow-tags';
        }

        // Push
        $repo->run('push', $args);
    }

    /**
     * Fetch all upstream changes
     *
     * @param OutputInterface $output
     * @param string $remote
     */
    public function fetch(OutputInterface $output, $remote = 'origin')
    {
        $this->getRepository($output)
            ->run('fetch', array($remote));
    }

    /**
     * Checkout given branch name.
     *
     * Note that this method respects ambiguous branch names (e.g. 3.1.0 branch which
     * may have just been tagged as 3.1.0, and is about to get deleted).
     *
     * @param OutputInterface $output
     * @param string $branch
     * @param string $remote
     * @param bool $canCreate Set to true to allow creation of new branches
     * if not found. Branch will be created from current head.
     */
    public function checkout(OutputInterface $output, $branch, $remote = 'origin', $canCreate = false)
    {
        // Check if local branch exists
        $localBranches = $this->getBranches();
        $remoteBranches = $this->getBranches($remote);
        $repository = $this->getRepository($output);

        // Make sure branch exists somewhere
        if (!in_array($branch, $localBranches) && !in_array($branch, $remoteBranches)) {
            if (!$canCreate) {
                throw new InvalidArgumentException("Branch {$branch} is not a local or remote branch");
            }

            // Create branch
            $repository->run('checkout', ['-B', $branch]);
            return;
        }

        // Check if we need to switch branch
        if ($this->getBranch() !== $branch) {
            // Find source for branch to checkout from (must disambiguate from tags)
            if (!in_array($branch, $localBranches)) {
                $sourceRef = "{$remote}/{$branch}";
            } else {
                $sourceRef = "refs/heads/{$branch}";
            }

            // Checkout branch
            $repository->run('checkout', [
                '-B',
                $branch,
                $sourceRef,
            ]);
        }

        // If branch is on live and local, we need to synchronise changes on local
        // (but don't push!)
        if (in_array($branch, $localBranches) && in_array($branch, $remoteBranches)) {
            $repository->run('pull', [$remote, $branch]);
        }
    }

    /**
     * Get composer.json as array format
     *
     * @return array
     * @throws Exception
     */
    public function getComposerData()
    {
        $path = $this->getDirectory() . '/composer.json';
        if (!file_exists($path)) {
            throw new Exception("No composer.json found in module " . $this->getName());
        }
        return Config::loadFromFile($path);
    }

    /**
     * Gets cow config
     *
     * @array
     */
    public function getCowData()
    {
        $path = $this->getDirectory() . '/.cow.json';
        return Config::loadFromFile($path);
    }

    /**
     * Get name of this library
     *
     * @return string
     */
    public function getName()
    {
        $data = $this->getComposerData();
        return $data['name'];
    }


    /**
     * Get link to github module
     *
     * @return string
     */
    public function getLink()
    {
        $data = $this->getCowData();
        if (isset($data['link'])) {
            return $data['link'];
        }

        $remotes = $this->getRemotes();
        foreach ($remotes as $name => $remote) {
            if (preg_match('/^http(s)?:/', $remote)) {
                // Remove trailing .git
                $remote = preg_replace('/\\.git$/', '', $remote);
                $remote = rtrim($remote, '/') . '/';
                return $remote;
            }
        }

        // Fallback to looking for github slug
        if ($name = $this->getGithubSlug()) {
            return "https://github.com/{$name}/";
        }

        return null;
    }

    /**
     * Gets all children, including recursive children
     *
     * @return LibraryList
     */
    public function getAllChildren() {
        $children = $this->getChildren();
        $combined = $children;
        foreach ($children as $child) {
            $combined = $combined->merge($child->getAllChildren());
        }
        return $combined;
    }

    /**
     * Gets direct child dependencies
     *
     * @return LibraryList
     */
    public function getChildren() {
        if ($this->children) {
            return $this->children;
        }

        $data = $this->getComposerData();
        $this->children = new LibraryList();
        if (empty($data['require'])) {
            return $this->children;
        }

        // Check logical rules to pull out any direct dependencies
        foreach ($data['require'] as $name => $version) {
            if ($this->isChildLibrary($name)) {
                $path = $this->getProject()->findModulePath($name);
                if (empty($path)) {
                    throw new LogicException("Required dependency $name is not installed");
                }
                $childLibrary = $this->createChildLibrary($path);
                $this->children->add($childLibrary);
            }
        }

        return $this->children;
    }

    /**
     * Given a child repo name, return the version constraint declared. E.g. `^4`, `~1.0.0` or `4.x-dev`
     *
     * @param string $name
     * @param Version $thisVersion Value of self.version
     * @return ComposerConstraint Composer constraint
     */
    public function getChildConstraint($name, Version $thisVersion = null) {
        $data = $this->getComposerData();
        $this->children = new LibraryList();
        if (isset($data['require'][$name])) {
            new ComposerConstraint($data['require'][$name], $thisVersion);
        }
        throw new InvalidArgumentException("Library {$this->getName()} does not have child dependency {$name}");
    }

    /**
     * Determine if the module of a given name is a child library.
     * This module must have a vendor of a denoted vendor
     *
     * @param string $name
     * @return bool
     */
    public function isChildLibrary($name) {
        // Ensure name is valid
        if (strstr($name, '/') === false) {
            return false;
        }

        // Validate vs vendors. There must be at least one matching vendor.
        $cowData = $this->getCowData();
        if (empty($cowData['vendors'])) {
            return false;
        }
        $vendors = $cowData['vendors'];
        $vendor = strtok($name, '/');
        if (!in_array($vendor, $vendors)) {
            return false;
        }

        // validate exclusions
        if (empty($cowData['exclude'])) {
            return true;
        }
        return !in_array($name, $cowData['exclude']);
    }

    /**
     * Determine if this library is restricted to upgrade-only
     *
     * @param string $name
     * @return bool
     */
    public function isChildUpgradeOnly($name) {
        $cowData = $this->getCowData();
        return isset($cowData['upgrade-only']) && in_array($name, $cowData['upgrade-only']);
    }

    /**
     * Given a release version, determine the from version
     *
     * @param Version $version
     * @return Version Version to generate change log from
     */
    public function getFromVersion(Version $version) {
        // Get list of existing tags
        return $version->getPriorVersionFromTags($this->getTags(), $this->getName());
    }

    /**
     * Get the top level project
     *
     * @return Project
     */
    public function getProject() {
        return $this->getParent()->getProject();
    }

    /**
     * Get parent project
     *
     * @return Project
     */
    public function getParent() {
        return $this->parent;
    }

    /**
     * Gets library depth, where 0 = project
     *
     * @return int
     */
    public function getDepth() {
        $parent = $this->getParent();
        if (empty($parent)) {
            return 0;
        }
        return $parent->getDepth() + 1;
    }

    /**
     * Create a child library
     *
     * @param string $path
     * @return Library
     */
    protected function createChildLibrary($path)
    {
        if (Module::isModulePath($path)) {
            return new Module($path, $this);
        }

        if (Library::isLibraryPath($path)) {
            return new Library($path, $this);
        }

        return null;
    }
}
