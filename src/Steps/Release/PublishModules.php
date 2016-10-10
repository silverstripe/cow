<?php

namespace SilverStripe\Cow\Steps\Release;

use Exception;
use Github\Api\Repo;
use InvalidArgumentException;
use SilverStripe\Cow\Model\Modules\Library;
use SilverStripe\Cow\Model\Release\LibraryRelease;
use SilverStripe\Cow\Utility\Composer;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Github\Client as GithubClient;
use Http\Adapter\Guzzle6\Client as GuzzleClient;

class PublishModules extends ReleaseStep
{
    /**
     * Github API client
     *
     * @var GithubClient
     */
    protected $githubClient = null;

    public function getStepName()
    {
        return 'publish';
    }

    public function run(InputInterface $input, OutputInterface $output)
    {
        $this->log($output, "Running release of all modules");

        $this->releaseRecursive($output, $this->getReleasePlan());

        $this->log($output, "All releases published");
    }

    /**
     * Release a library
     *
     * @param OutputInterface $output
     * @param LibraryRelease $releasePlan The library to tag
     */
    protected function releaseRecursive(OutputInterface $output, LibraryRelease $releasePlan)
    {
        // Skip upgrade-only modules
        if (!$releasePlan->getIsNewRelease()) {
            return;
        }

        // Before releasing a version, make sure to tag all nested dependencies
        foreach ($releasePlan->getItems() as $item) {
            $this->releaseRecursive($output, $item);
        }

        // Release this library
        $this->releaseLibrary($output, $releasePlan);
    }

    /**
     * Performs a release of a single library
     *
     * @param OutputInterface $output
     * @param LibraryRelease $releasePlan
     */
    protected function releaseLibrary(OutputInterface $output, LibraryRelease $releasePlan)
    {
        // Release this library
        $library = $releasePlan->getLibrary();
        $branch = $library->getBranch();
        $name = $library->getName();
        $versionName = $releasePlan->getVersion()->getValue();
        $this->log($output, "Releasing library <info>{$name}</info> at version <info>{$versionName}</info>");

        // Step 1: Push development branch to origin before tagging
        $library->pushTo('origin');

        // Step 2: Detatch head from current branch before modifying
        $this->detachBranch($output, $library);

        // Step 3: Rewrite composer.json on this head to all tagged versions only
        $this->stabiliseRequirements($output, $releasePlan);

        // Step 4: Tag and push this tag
        $this->publishTag($output, $releasePlan);

        // Step 5: Restore back to dev branch
        $library->checkout($output, $branch);
    }

    /**
     * Change the current head to a detached state to prevent un-pushable
     * modifications affecting the development branch.
     *
     * @param OutputInterface $output
     * @param $library
     */
    protected function detachBranch(OutputInterface $output, Library $library)
    {
        $repository = $library->getRepository($output);
        $repository->run('checkout', 'HEAD~0');
        if ($library->getBranch()) {
            throw new \LogicException("Error checking out detached HEAD~0 of " . $library->getName());
        }
    }

    /**
     * Rewrite all composer dependencies for this tag
     *
     * @param OutputInterface $output
     * @param LibraryRelease $releasePlan
     */
    protected function stabiliseRequirements(OutputInterface $output, LibraryRelease $releasePlan)
    {
        $parentLibrary = $releasePlan->getLibrary();
        $originalData = $composerData = $parentLibrary->getComposerData();
        $constraintType = $parentLibrary->getDependencyConstraint();

        // Inspect all dependencies
        foreach ($releasePlan->getItems() as $item) {
            $childName = $item->getLibrary()->getName();
            $stabiliseDependencyRequirement = $this->stabiliseDependencyRequirement($output, $item, $constraintType);
            $composerData['require'][$childName] = $stabiliseDependencyRequirement;
        }

        // Save modifications to the composer.json for this module
        if ($composerData !== $originalData) {
            $this->updateComposerData($output, $parentLibrary, $composerData);
        }
    }

    /**
     * @param OutputInterface $output
     * @param LibraryRelease $item
     * @param string $constraintType
     * @return string
     */
    protected function stabiliseDependencyRequirement(OutputInterface $output, LibraryRelease $item, $constraintType)
    {
        // Rewrite requirement for tag
        $childRequirement = $item->getVersion()->getValue();
        switch ($constraintType) {
            case Library::DEPENDENCY_LOOSE:
                $childRequirement = "~{$childRequirement}";
                break;
            case Library::DEPENDENCY_SEMVER:
                $childRequirement = "^{$childRequirement}";
                break;
        }

        // Notify of change
        $childName = $item->getLibrary()->getName();
        $this->log(
            $output,
            "Fixing tagged dependency <info>{$childName}</info> to <info>{$childRequirement}</info>"
        );
        return $childRequirement;
    }

    /**
     * @param OutputInterface $output
     * @param Library $parentLibrary
     * @param array $composerData
     */
    protected function updateComposerData(OutputInterface $output, Library $parentLibrary, array $composerData)
    {
        $parentName = $parentLibrary->getName();
        $this->log($output, "Rewriting composer.json for <info>$parentName</info>");

        // Write to filesystem
        $parentLibrary->setComposerData($composerData);

        // Commit to git
        $path = $parentLibrary->getComposerPath();
        $repo = $parentLibrary->getRepository();
        $repo->run("add", array($path));
        $status = $repo->run("status");
        if (stripos($status, 'Changes to be committed:')) {
            $repo->run("commit", array("-m", "Update development dependencies"));
        }
    }

    /**
     * @param OutputInterface $output
     * @param LibraryRelease $releasePlan
     */
    protected function publishTag(OutputInterface $output, LibraryRelease $releasePlan)
    {
        $library = $releasePlan->getLibrary();
        $libraryName = $library->getName();
        $tag = $releasePlan->getVersion()->getValue();
        $this->log($output, "Tagging <info>{$libraryName}</info> library as <info>{$tag}</info>");

        // Create new tag
        $library->addTag($tag);

        // Push tag to github
        $library->pushTag($tag);

        // Push up changelog to github API
        $this->updateGithubChangelog($output, $releasePlan);

        $this->log($output, 'Tagging complete');
    }


    /**
     * Update github release notes via github API
     *
     * @param OutputInterface $output
     * @param LibraryRelease $release
     */
    protected function updateGithubChangelog(OutputInterface $output, LibraryRelease $release)
    {
        $library = $release->getLibrary();
        if (!$library->hasGithubChangelog()) {
            return;
        }

        // Check github slug is available
        $slug = $release->getLibrary()->getGithubSlug();
        if (empty($slug)) {
            throw new InvalidArgumentException("Could not find github slug for " . $library->getName());
        }
        list($org, $repo) = explode('/', $slug);

        // Log release
        $version = $release->getVersion();
        $tag = $version->getValue();
        $this->log($output, "Creating github release <info>{$org}/{$repo} v{$tag}</info>");

        /** @var Repo $reposAPI */
        $client = $this->getGithubClient($output);
        $reposAPI = $client->api('repo');
        $releasesAPI = $reposAPI->releases();

        // Build release payload
        $releaseData = [
            'tag_name' => $tag,
            'target_commitish' => $tag,
            'name' => $tag,
            'body' => $release->getChangelog(),
            'prerelease' => !$version->isStable(),
            'draft' => false,
        ];

        // Determine if editing or creating a release
        $existing = $releasesAPI->tag($org, $repo, $tag);
        if ($existing && !empty($existing['id'])) {
            $result = $releasesAPI->edit($org, $repo, $existing['id'], $releaseData);
            if (isset($result['created_at'])) {
                $this->log($output, "Successfully updated github release");
                return;
            }
        } else {
            $result = $releasesAPI->create($org, $repo, $releaseData);
            if (isset($result['created_at'])) {
                $this->log($output, "Successfully created at <info>" . $result['created_at'] . "</info>");
                return;
            }
        }

        $this->log($output, "Github API update failed for <info>" . $library->getName() . "</info>", "error");
    }

    /**
     * Return or generate github client on-demand
     *
     * @param OutputInterface $output
     * @return GithubClient
     */
    protected function getGithubClient(OutputInterface $output)
    {
        if ($this->githubClient) {
            return $this->githubClient;
        }

        // Create authenticated github client
        $token = $this->getOAUTHToken($output);
        $client = new GithubClient();
        $httpClient = new GuzzleClient();
        $client->setHttpClient($httpClient);
        $client->authenticate($token, null, GithubClient::AUTH_HTTP_TOKEN);

        // Cache
        $this->githubClient = $client;
        return $client;
    }

    /**
     * @param OutputInterface $output
     * @return string
     * @throws Exception
     */
    protected function getOAUTHToken(OutputInterface $output)
    {
        $token = getenv('GITHUB_API_TOKEN');
        if (empty($token)) {
            $token = Composer::getOAUTHToken($this->getCommandRunner($output));
        }
        if (empty($token)) {
            throw new Exception("Couldn't determine GitHub oAuth token. Please set GITHUB_API_TOKEN");
        }
        return $token;
    }
}
