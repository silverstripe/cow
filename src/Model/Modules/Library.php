<?php

namespace SilverStripe\Cow\Model\Modules;

use Exception;
use Generator;
use Gitonomy\Git\Exception\ProcessException;
use Gitonomy\Git\Reference\Branch;
use Gitonomy\Git\Repository;
use InvalidArgumentException;
use LogicException;
use SilverStripe\Cow\Model\Changelog\Changelog;
use SilverStripe\Cow\Model\Release\ComposerConstraint;
use SilverStripe\Cow\Model\Release\LibraryRelease;
use SilverStripe\Cow\Model\Release\Version;
use SilverStripe\Cow\Utility\Config;
use SilverStripe\Cow\Utility\Format;
use SilverStripe\Cow\Utility\SchemaValidator;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use SilverStripe\Cow\Application;

/**
 * Represents a library which may not be a module
 */
class Library
{
    /**
     * Dependencies are tagged at exact version (1.0.0)
     */
    public const DEPENDENCY_EXACT = 'exact';

    /**
     * Dependencies allow loose-compatible upgrade (~1.0.0)
     */
    public const DEPENDENCY_LOOSE = 'loose';

    /**
     * Dependencies allow any semver-compatible upgrade (^1.0.0)
     */
    public const DEPENDENCY_SEMVER = 'semver';

    /**
     * Parent project (installer module)
     *
     * @var Library
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
     * @var Library[]
     */
    protected $children;

    /**
     * Create new library
     *
     * @param string $directory
     * @param Library|null $parent Parent library
     */
    public function __construct($directory, Library $parent = null)
    {
        $this->directory = realpath($directory);
        $this->parent = $parent;

        if (!$this->isValid()) {
            throw new InvalidArgumentException(
                "No library in directory \"{$this->directory}\". Did you forget to run cow release:create?"
            );
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
            if (preg_match('#github.com(/|:)(?<slug>[^\\s/\\.]+/[^\\s/\\.]+)#', $remote, $matches)) {
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
        if ($this->getProject()->getFetchTags()) {
            // Fetch remote tags from origin first
            $repo->run('fetch', ['--tags']);
        }
        $result = $repo->run('tag');
        $tags = preg_split('~\R~u', $result);
        $tags = array_filter($tags);

        // Objectify tags
        $this->tags = [];
        foreach ($tags as $tag) {
            // Skip invalid tags
            if (Version::parse($tag)) {
                $version = new Version($tag);
                $this->tags[$version->getValue()] = $version;
            }
        }
        return $this->tags;
    }

    /**
     * Check if a tag exists in the repository
     */
    public function hasTag(string $tag): bool
    {
        $repo = $this->getRepository();
        if ($this->getProject()->getFetchTags()) {
            // Fetch remote tags from origin first
            $repo->run('fetch', ['--tags']);
        }

        return (bool) $repo->run('tag', ['--list', $tag]);
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
     * Merge commits from another branch into the current branch
     */
    public function merge(OutputInterface $output = null, string $branch)
    {
        // Validate branch
        $currentBranch = $this->getBranch();
        if (!$currentBranch) {
            throw new Exception('Module ' . $this->getName() . ' cannot merge without a current branch');
        }

        $this->getRepository($output)->run('merge', ['--no-ff', $branch]);
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
        if (Application::isDevMode()) {
            echo "Not pushing changes because DEV_MODE is enabled\n";
        } else {
            $repo->run('push', $args);
        }
    }

    /**
     * Do automatic rebase with remote in case it's out of date. Can be useful if things
     * have been merged upstream during the release.
     *
     * @param OutputInterface $output
     * @param string $remote
     * @throws Exception
     */
    public function rebase(OutputInterface $output = null, $remote = 'origin')
    {
        // Validate branch
        $branch = $this->getBranch();
        if (!$branch) {
            throw new Exception("Module " . $this->getName() . " cannot rebase without a current branch");
        }

        // Check if remote branch exists
        $remoteBranches = $this->getBranches($remote);
        if (!in_array($branch, $remoteBranches, true)) {
            // No remote branch to rebase with
            return;
        }
        $repo = $this->getRepository($output);

        // Set CWD to work-around git crashing while stashing
        $dir = getcwd();
        chdir($this->getDirectory());

        // Stash changes
        $status = $repo->run('status');
        $hasChanges = stripos($status, 'Changes to be committed:')
            || stripos($status, 'Changes not staged for commit:');
        if ($hasChanges) {
            $repo->run('stash', ['-u']);
        }

        // Pull
        try {
            $repo->run('pull', ['--rebase', $remote, $branch]);
        } finally {
            // Restore locale changes
            if ($hasChanges) {
                $repo->run('stash', ['pop']);
            }
            chdir($dir);
        }
    }

    /**
     * Push a single tag
     *
     * @param string $tag Name of tag
     * @param string $remote
     */
    public function pushTag($tag, $remote = 'origin')
    {
        if (Application::isDevMode()) {
            echo "Not pushing tag because DEV_MODE is enabled\n";
            return;
        }
        $repo = $this->getRepository();
        $args = array($remote, "refs/tags/{$tag}");
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
     * @param string $branch Name of branch to checkout or create
     * @param string $remote name of remote to look for branch in. Set to empty to disable remote checkout.
     * @param bool $canCreate Set to true to allow creation of new branches
     * if not found. Branch will be created from current head.
     */
    public function checkout(OutputInterface $output, $branch, $remote = 'origin', $canCreate = false)
    {
        // Check if local branch exists
        $localBranches = $this->getBranches();
        $remoteBranches = $remote ? $this->getBranches($remote) : [];
        $repository = $this->getRepository($output);
        $isLocalBranch = in_array($branch, $localBranches, true);
        $isRemoteBranch = in_array($branch, $remoteBranches, true);

        // Make sure branch exists somewhere
        if (!$isLocalBranch && !$isRemoteBranch) {
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
            if (!$isLocalBranch) {
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
        if ($isLocalBranch && $isRemoteBranch) {
            $repository->run('pull', [$remote, $branch]);
        }
    }

    /**
     * Checkout the given tag
     *
     * @param OutputInterface $output
     * @param Version $version
     * @throws ProcessException
     */
    public function resetToTag(OutputInterface $output, Version $version)
    {
        $repository = $this->getRepository($output);
        $tag = $version->getValue();
        try {
            $repository->run('checkout', ["refs/tags/{$tag}"]);
        } catch (ProcessException $ex) {
            // Fall back to `v` prefixed tag. E.g. gridfield-bulk-editing-tools uses this prefix
            try {
                $repository->run('checkout', ["refs/tags/v{$tag}"]);
            } catch (ProcessException $vex) {
                // Throw original exception
                throw $ex;
            }
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
        $path = $this->getComposerPath();
        if (!file_exists($path)) {
            throw new Exception("No composer.json found in module " . $this->getName());
        }
        return Config::loadFromFile($path);
    }

    /**
     * Write changes to composer.json
     *
     * @param array $data
     * @throws Exception
     */
    public function setComposerData($data, $commitChanges = false, $commitMessage = '')
    {
        $path = $this->getComposerPath();
        if (!file_exists($path)) {
            throw new Exception("No composer.json found in module " . $this->getName());
        }
        Config::saveToFile($path, $data);
        if ($commitChanges) {
            if (!$commitMessage) {
                throw new InvalidArgumentException('A commit message must be passed in to commit changes');
            }
            // Commit to git
            $path = $this->getComposerPath();
            $repo = $this->getRepository();
            $repo->run("add", [$path]);
            $status = $repo->run("status");
            if (stripos($status, 'Changes to be committed:') !== false) {
                $repo->run("commit", ["-m", $commitMessage]);
            }
        }
    }

    /**
     * Get path to composer.json
     *
     * @return string
     */
    public function getComposerPath()
    {
        return $this->getDirectory() . '/composer.json';
    }

    /**
     * Get historic composer.json content from git recursively
     *
     * @param string $ref Git ref (tag / branch / SHA)
     * @param bool $recursive Search recursively
     * @param int $maxDepth Safeguard against infinite loops
     * @param bool $stableOnly Check if we only want to check stable tags
     * @return array|null
     */
    public function getHistoryComposerData($ref, $recursive = true, $maxDepth = 6, $stableOnly = false)
    {
        if ($maxDepth <= 0) {
            return null;
        }

        // Get composer data for this library
        try {
            $content = $this->getRepository()->run('show', ["{$ref}:composer.json"]);
        } catch (ProcessException $ex) {
            // Skip missing composer.json in legacy releases
            return null;
        }
        if (!$content) {
            return null;
        }
        $results = Config::parseContent($content);
        if (empty($results['require']) || !$recursive) {
            return $results;
        }

        // Get all recursive changes
        foreach ($results['require'] as $libraryName => $libraryConstraint) {
            // If this library belongs to this module, recurse
            $library = $this->getLibrary($libraryName);
            if (!$library) {
                continue;
            }
            // Get oldest version for this library and constraint
            if (!ComposerConstraint::parse($libraryConstraint)) {
                continue;
            }
            $constraint = new ComposerConstraint($libraryConstraint, $ref, $libraryName);
            $version = $library->getOldestVersionMatching($constraint, $stableOnly);
            if (!$version) {
                continue;
            }
            // With this history version in hand, recurse
            $nextResults = $library->getHistoryComposerData(
                $version->getOriginalString(),
                true,
                $maxDepth - 1,
                $stableOnly
            );
            if (isset($nextResults['require'])) {
                $results['require'] = array_merge(
                    $nextResults['require'],
                    $results['require']
                );
            }
        }
        return $results;
    }

    /**
     * Returns the oldest version that matches the given version constraint
     *
     * @param ComposerConstraint $composerConstraint
     * @param bool $stableOnly Check if we only want to check stable tags
     * @return Version
     */
    public function getOldestVersionMatching(ComposerConstraint $composerConstraint, $stableOnly = false)
    {
        // Filter known tags by the constraint first
        $childVersions = $composerConstraint->filterVersions($this->getTags());

        // Check if we want to filter out stable tags only
        // E.g. prefer "3.4.0..3.4.1" over "3.4.0-rc1..3.4.1"
        if ($stableOnly) {
            $stableChildVersions = Version::filter($childVersions, function (Version $nextTag) {
                return $nextTag->isStable();
            });
            // Use stable versions if available, or fall back to unstable
            if (!empty($stableChildVersions)) {
                $childVersions = $stableChildVersions;
            }
        }

        // Get smallest matching version
        $childVersions = Version::sort($childVersions, Version::ASC);
        if (!empty($childVersions)) {
            return reset($childVersions);
        }
        return null;
    }

    /**
     * @return array List of test commands
     */
    public function getTests()
    {
        $data = $this->getCowData();
        if (isset($data['tests'])) {
            return $data['tests'];
        }
        return [];
    }

    /**
     * Gets cow config
     *
     * @array
     */
    public function getCowData()
    {
        $path = $this->getDirectory() . '/.cow.json';
        $schemaPath = dirname(dirname(dirname(__DIR__))) . '/' . SchemaValidator::SCHEMA_FILENAME;
        return Config::loadFromFile($path, $schemaPath);
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
     * Get type of this library
     */
    public function getType(): string
    {
        $data = $this->getComposerData();
        return $data['type'] ?? '';
    }

    public function isRecipe(): bool
    {
        return $this->getType() === 'silverstripe-recipe';
    }

    /**
     * Get link to github module
     *
     * @return string
     */
    public function getLink()
    {

        return null;
    }

    /**
     * Get web-accessible link to the given commit
     *
     * @param string $sha
     * @return null|string
     */
    public function getCommitLink($sha)
    {
        $format = $this->getCommitLinkFormat();
        if ($format) {
            return Format::formatString($format, ['sha' => $sha]);
        }
        return null;
    }

    /**
     * Get link for commit format
     *
     * @return string
     */
    protected function getCommitLinkFormat()
    {
        $data = $this->getCowData();
        if (isset($data['commit-link'])) {
            return $data['commit-link'];
        }

        // Get from hithub slug
        if ($name = $this->getGithubSlug()) {
            return "https://github.com/{$name}/commit/{sha}";
        }

        // Fallback to checking remotes. E.g. gitlab remotes
        $remotes = $this->getRemotes();
        foreach ($remotes as $name => $remote) {
            // Handle https or ssh endpoints
            if (preg_match('/^(http(s)?:|git@)/', $remote)) {
                // Replace ssh protocol with https
                $remote = preg_replace('/git@(.+):/', 'https://$1/', $remote);
                // Remove trailing .git
                $remote = preg_replace('/\\.git$/', '', $remote);
                $remote = rtrim($remote, '/') . '/commit/{sha}';
                return $remote;
            }
        }

        return null;
    }

    /**
     * Gets all children, including recursive children
     *
     * @return Generator|Library[]
     */
    public function getAllChildren()
    {
        foreach ($this->getChildren() as $child) {
            yield $child;
            foreach ($child->getAllChildren() as $nested) {
                yield $nested;
            }
        }
    }

    /**
     * Gets direct child dependencies
     *
     * @return Library[]
     */
    public function getChildren()
    {
        if (isset($this->children)) {
            return $this->children;
        }

        $data = $this->getComposerData();
        $this->children = [];
        if (empty($data['require'])) {
            return $this->children;
        }

        // Check logical rules to pull out any direct dependencies
        foreach ($data['require'] as $name => $version) {
            // Skip non-child libraries
            if ($this->isChildLibrary($name)) {
                // Defer to parent project for creating and registering libraries
                // The project will prevent duplicate libraries appearing twice in the tree
                $this->children[] = $this
                    ->getProject()
                    ->getOrCreateLibrary($name, $this);
            }
        }

        return $this->children;
    }

    /**
     * Get list of exclusive children (where this is the first parent)
     *
     * @return Library[]
     */
    public function getChildrenExclusive()
    {
        return array_filter($this->getChildren(), function (Library $child) {
            return $child->getParent() === $this;
        });
    }

    /**
     * Find library in the tree by name.
     * May return self, a direct child, or a nested child.
     *
     * @param string $name
     * @return Library
     */
    public function getLibrary($name)
    {
        if ($this->getName() === $name) {
            return $this;
        }

        foreach ($this->getAllChildren() as $child) {
            if ($child->getName() === $name) {
                return $child;
            }
        }
        return null;
    }

    /**
     * Given a child repo name, return the version constraint declared. E.g. `^4`, `~1.0.0` or `4.x-dev`
     *
     * @param string $name
     * @param Version $thisVersion Value of self.version
     * @return ComposerConstraint Composer constraint
     */
    public function getChildConstraint($name, Version $thisVersion = null)
    {
        $data = $this->getComposerData();
        if (isset($data['require'][$name])) {
            return new ComposerConstraint($data['require'][$name], $thisVersion, $name);
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
    public function isChildLibrary($name)
    {
        // Ensure name is valid
        if (strstr($name, '/') === false) {
            return false;
        }

        // Upgrade-only is considered a child library
        if ($this->isChildUpgradeOnly($name)) {
            return true;
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
    public function isChildUpgradeOnly($name)
    {
        $cowData = $this->getCowData();
        return isset($cowData['upgrade-only']) && in_array($name, $cowData['upgrade-only']);
    }

    /**
     * Is this module restricted to upgrade only?
     *
     * @return bool
     */
    public function isUpgradeOnly()
    {
        $parent = $this->getParent();
        return $parent && $parent->isChildUpgradeOnly($this->getName());
    }

    /**
     * Do child releases inherit stability for the given library?
     *
     * @param Library $childLibrary Library to test for stability inheritance for
     * @return bool
     */
    public function isStabilityInherited(Library $childLibrary)
    {
        $cowData = $this->getCowData();
        if (empty($cowData['child-stability-inherit'])) {
            return false;
        }

        // Check if only some modules inherit stability
        if (is_array($cowData['child-stability-inherit'])) {
            return in_array($childLibrary->getName(), $cowData['child-stability-inherit']);
        }

        return true;
    }

    /**
     * Get the top level project
     *
     * @return Project
     */
    public function getProject()
    {
        return $this->getParent()->getProject();
    }

    /**
     * Get first parent library.
     * Note: Even though a library can be included multiple times, prioritise the first parent only
     *
     * @return Library
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * Gets library depth, where 0 = project
     *
     * @return int
     */
    public function getDepth()
    {
        $parent = $this->getParent();
        if (empty($parent)) {
            return 0;
        }
        return $parent->getDepth() + 1;
    }

    /**
     * Get dependency tagging behaviour.
     *
     * @return string
     * @throws InvalidArgumentException
     */
    public function getDependencyConstraint()
    {
        $data = $this->getCowData();
        if (empty($data['dependency-constraint'])) {
            return self::DEPENDENCY_EXACT;
        }

        $dependencyconstraint = $data['dependency-constraint'];
        switch ($dependencyconstraint) {
            case self::DEPENDENCY_LOOSE:
            case self::DEPENDENCY_SEMVER:
            case self::DEPENDENCY_EXACT:
                return $dependencyconstraint;
            default:
                throw new InvalidArgumentException("Invalid dependency-constraint: {$dependencyconstraint}");
        }
    }

    /**
     * Determine if this library has a cached release plan
     *
     * @return LibraryRelease|null The root release, or null if not cached
     */
    public function loadCachedPlan()
    {
        // Check cached plan file
        $path = $this->getDirectory() . '/.cow.pat.json';
        if (!file_exists($path)) {
            // Note: .cow.pat.json used to be called .cow.plan.json
            // Automatically detect legacy files and load as a fallback
            $path = $this->getDirectory() . '/.cow.plan.json';
            if (!file_exists($path)) {
                return null;
            }
        }
        $serialisedPlan = Config::loadFromFile($path);
        $result = $this->unserialisePlan($serialisedPlan);
        return $result[$this->getName()];
    }

    /**
     * Write the release plan to the standard cache location
     *
     * @param LibraryRelease $plan
     */
    public function saveCachedPlan(LibraryRelease $plan)
    {
        $path = $this->getDirectory() . '/.cow.pat.json';
        $data = $this->serialisePlan($plan);
        Config::saveToFile($path, $data);
    }

    /**
     * Unserialise a plan
     *
     * @param array $serialisedPlan Decoded json data
     * @return LibraryRelease[]
     * @throws Exception
     */
    protected function unserialisePlan($serialisedPlan, ?LibraryRelease $parentRelease = null)
    {
        $releases = [];
        foreach ($serialisedPlan as $name => $data) {
            // Unserialise this node
            $library = $this->getLibrary($name);
            if (!$library) {
                throw new Exception("Missing library $name");
            }
            $version = new Version($data['Version']);
            $libraryRelease = new LibraryRelease($library, $version);
            if ($parentRelease) {
                $libraryRelease->setParentRelease($parentRelease);
            }

            // Set the previous version if specified
            if (!empty($data['PriorVersion'])) {
                $priorVersion = new Version($data['PriorVersion']);
                $libraryRelease->setPriorVersion($priorVersion);
            }

            // Merge with unserialised children
            if (!empty($data['Items'])) {
                $libraryRelease->addItems($this->unserialisePlan($data['Items'], $libraryRelease));
            }

            // Set branching
            if (!empty($data['Branching'])) {
                $libraryRelease->setBranching($data['Branching']);
            }
            $releases[$name] = $libraryRelease;
        }
        return $releases;
    }

    /**
     * Gather an array of data for the current plan (and sub-items), which will be encoded as JSON
     * when written to file.
     *
     * @param LibraryRelease $plan
     * @return array Data to be later JSON encoded
     */
    public function serialisePlan(LibraryRelease $plan)
    {
        $content = [];
        $name = $plan->getLibrary()->getName();
        $priorVersion = $plan->getPriorVersion(false);
        $content[$name] = [
            'PriorVersion' => $priorVersion ? $priorVersion->getValue() : null,
            'Version' => $plan->getVersion()->getValue(),
            'Items' => [],
            'Branching' => $plan->getBranching(null), // Only store internal value don't failover
            'UpgradeOnly' => $plan->getLibrary()->isUpgradeOnly(), // Stored for reference, never unserialized
        ];
        foreach ($plan->getItems() as $item) {
            $content[$name]['Items'] = array_merge(
                $content[$name]['Items'],
                $item->getLibrary()->serialisePlan($item)
            );
        }
        return $content;
    }

    /**
     * Check if this module should have a changelog
     *
     * @return bool
     */
    public function hasChangelog()
    {
        $cowData = $this->getCowData();

        // Has changelog if generating via markdown committed to source control
        return !empty($cowData['changelog-path']);
    }

    /**
     * Get changelog path
     *
     * @param Version $version
     * @return string
     */
    public function getChangelogPath(Version $version)
    {
        $cowData = $this->getCowData();

        // If generating via markdown committed to source control
        if (empty($cowData['changelog-path'])) {
            return null;
        }
        $pattern = $cowData['changelog-path'];

        // Substitue version parameters
        return $version->injectPattern($pattern);
    }

    public function getChangelogTemplatePath()
    {
        return $this->getCowData()['changelog-template'] ?? null;
    }

    /**
     * Get Changelog format type
     *
     * @return string one of FORMAT_GROUPED or FORMAT_FLAT
     * @throws Exception
     */
    public function getChangelogFormat()
    {
        $data = $this->getCowData();
        // Default tagging
        if (empty($data['changelog-type'])) {
            return Changelog::FORMAT_GROUPED;
        }
        // Validate tagging type
        switch ($data['changelog-type']) {
            case Changelog::FORMAT_GROUPED:
            case Changelog::FORMAT_FLAT:
                return $data['changelog-type'];
            default:
                throw new Exception("Invalid changelog format type " . $data['changelog-type']);
        }
    }

    /**
     * Find library to hold the changelog for this library. Defaults to self.
     *
     * @return Library
     */
    public function getChangelogHolder()
    {
        $data = $this->getCowData();
        if (empty($data['changelog-holder'])) {
            return $this;
        }

        $library = $this->getLibrary($data['changelog-holder']);
        if (empty($library)) {
            throw new LogicException(
                "changelog-holder library " . $data['changelog-holder'] . " is not a valid library"
            );
        }
        return $library;
    }

    /**
     * Whether to include "other changes" (commits that are not tagged with a Cow friendly prefix) in changelogs
     * or not. Can return null if no project configuration is found.
     *
     * @return bool|null
     */
    public function getChangelogIncludeOtherChanges()
    {
        $data = $this->getCowData();
        if (!isset($data['changelog-include-other-changes'])) {
            return null;
        }
        return (bool) $data['changelog-include-other-changes'];
    }

    /**
     * Check if there is a difference between the current branch and some other branch
     */
    public function hasDiff(OutputInterface $output, string $branch)
    {
        return !empty($this->getRepository($output)->getDiff($branch)->getRawDiff());
    }
}
