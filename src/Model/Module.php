<?php

namespace SilverStripe\Cow\Model;

use Exception;
use Gitonomy\Git\Reference\Branch;
use Gitonomy\Git\Repository;
use InvalidArgumentException;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * A module installed in a project
 */
class Module
{
    /**
     * Parent project (installer module)
     *
     * @var Project
     */
    protected $parent;

    /**
     * Module name
     *
     * @var string
     */
    protected $name;

    /**
     * Directory of this module
     * Doesn't always match name (e.g. installer)
     *
     * @var string
     */
    protected $directory;

    public function __construct($directory, $name, Project $parent = null)
    {
        $this->directory = realpath($directory);
        $this->name = $name;
        $this->parent = $parent;

        if (!$this->isValid()) {
            throw new InvalidArgumentException("No module in directory \"{$this->directory}\"");
        }
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
     * Gets the module lang dir
     *
     * @return string
     */
    public function getLangDirectory()
    {
        return $this->getMainDirectory() . '/lang';
    }

    /**
     * Gets the directory(s) of the JS lang folder.
     *
     * Can be a string or an array result
     *
     * @return string|array
     */
    public function getJSLangDirectory()
    {
        $langBasePath = 'client/lang'; // 4.x+ style
        if (!file_exists($this->getMainDirectory() . '/' . $langBasePath)) {
            $langBasePath = 'javascript/lang'; // 3.x style
        }

        $dir = $this->getMainDirectory() . '/' . $langBasePath;

        // Special case for framework which has a nested 'admin' submodule
        if ($this->getName() === 'framework') {
            return array(
                $this->getMainDirectory() . '/admin/' . $langBasePath,
                $dir
            );
        } else {
            return $dir;
        }
    }

    /**
     * Directory where module files exist; Usually the one that sits just below the top level project
     *
     * @return string
     */
    public function getMainDirectory()
    {
        return $this->getDirectory();
    }

    /**
     * A project is valid if it has a root composer.json
     */
    public function isValid()
    {
        return $this->directory && realpath($this->directory . '/composer.json');
    }

    /**
     * Determine if this project has a .tx configured
     *
     * @return bool
     */
    public function isTranslatable()
    {
        return $this->directory && realpath($this->directory . '/.tx/config');
    }

    /**
     * Get name of this module
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Cached link
     *
     * @var string
     */
    protected $link = null;

    /**
     * Get github slug. E.g. silverstripe/silverstripe-framework, or null
     * if not on github
     *
     * @return string
     */
    public function getGithubSlug()
    {
        $remotes = $this->getRemotes();
        foreach ($remotes as $remote) {
            if (preg_match('#github.com/(?<slug>[^\\s/\\.]+/[^\\s/\\.]+)#', $remote, $matches)) {
                return $matches['slug'];
            }
        }
        return null;
    }

    /**
     * Get link to github module
     *
     * @return string
     */
    public function getLink()
    {
        if ($this->link) {
            return $this->link;
        }

        $remotes = $this->getRemotes();
        foreach ($remotes as $name => $remote) {
            if (preg_match('/^http(s)?:/', $remote)) {
                // Remove trailing .git
                $remote = preg_replace('/\\.git$/', '', $remote);
                $remote = rtrim($remote, '/') . '/';
                return $this->link = $remote;
            }
        }

        // Fallback to looking for github slug
        if ($slug = $this->getGithubSlug()) {
            return $this->link = "https://github.com/{$slug}/";
        }

        // Fallback to best guess
        $name = $this->getName();
        return $this->link = "https://github.com/silverstripe/silverstripe-{$name}/";
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
     */
    public function getBranch()
    {
        $head = $this
            ->getRepository()
            ->getHead();
        if ($head instanceof Branch) {
            return $head->getName();
        }
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
     * Gets all tags that exist in the repository
     *
     * @return array
     */
    public function getTags()
    {
        $repo = $this->getRepository();
        $result = $repo->run('tag');
        $tags = preg_split('~\R~u', $result);
        return array_filter($tags);
    }

    /**
     * Tag this module
     *
     * @param string $tag
     */
    public function addTag($tag)
    {
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
        return json_decode(file_get_contents($path), true);
    }

    /**
     * Get composer name
     *
     * @return string
     */
    public function getComposerName()
    {
        $data = $this->getComposerData();
        return $data['name'];
    }
}