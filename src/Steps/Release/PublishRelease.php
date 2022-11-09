<?php

namespace SilverStripe\Cow\Steps\Release;

use Exception;
use Github\Api\Repo;
use Github\Exception\RuntimeException;
use InvalidArgumentException;
use SilverStripe\Cow\Model\Modules\Library;
use SilverStripe\Cow\Model\Release\LibraryRelease;
use SilverStripe\Cow\Model\Release\Version;
use SilverStripe\Cow\Utility\Composer;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Github\Client as GithubClient;
use Github\HttpClient\Message\ResponseMediator;
use Http\Adapter\Guzzle7\Client as GuzzleClient;
use LogicException;

class PublishRelease extends ReleaseStep
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
     * @param LibraryRelease $releasePlanNode Node in release plan being released
     */
    protected function releaseRecursive(OutputInterface $output, LibraryRelease $releasePlanNode)
    {
        // Skip upgrade-only modules
        if (!$releasePlanNode->getIsNewRelease()) {
            return;
        }

        // Before releasing a version, make sure to tag all nested dependencies
        foreach ($releasePlanNode->getItems() as $item) {
            $this->releaseRecursive($output, $item);
        }

        // Release this library
        $this->releaseLibrary($output, $releasePlanNode);
    }

    /**
     * Performs a release of a single library
     *
     * @param OutputInterface $output
     * @param LibraryRelease $releasePlanNode Node in release plan being released
     */
    protected function releaseLibrary(OutputInterface $output, LibraryRelease $releasePlanNode)
    {
        // Release this library
        $library = $releasePlanNode->getLibrary();
        $branch = $library->getBranch();
        $name = $library->getName();

        // Confirm we're on a release branch and exit if not
        if (!$releasePlanNode->isOnReleaseBranch()) {
            $this->log(
                $output,
                "Library $name is on a non-release branch '$branch'. "
                    . "Please checkout the release branch and then run the command again.",
                'error'
            );
            die();
        }

        $versionName = $releasePlanNode->getVersion()->getValue();
        $this->log($output, "Releasing library <info>{$name}</info> at version <info>{$versionName}</info>");

        // Step 1: Rewrite composer.json to all tagged versions only
        $this->stabiliseRequirements($output, $releasePlanNode);

        // Step 2: Push development branch to origin
        $this->log($output, "Pushing branch <info>{$branch}</info>");
        $library->pushTo('origin');

        // Step 3. Make sure the patch release branch is pushed up to origin
        $devBranch = str_replace(LibraryRelease::RELEASE_BRANCH_SUFFIX, '', $branch);
        if ($devBranch !== $branch) {
            $library->checkout($output, $devBranch);
            $this->log($output, "Pushing branch <info>{$devBranch}</info>");
            $library->pushTo('origin');
            $library->checkout($output, $branch);
        }

        // Step 4: Tag and push this tag
        $this->publishTag($output, $releasePlanNode);

        // Step 5: Create release in github
        $this->createGitHubRelease($output, $releasePlanNode);
    }

    /**
     * Rewrite all composer dependencies for this tag
     *
     * @param OutputInterface $output
     * @param LibraryRelease $releasePlanNode Current node in release plan being released
     */
    protected function stabiliseRequirements(OutputInterface $output, LibraryRelease $releasePlanNode)
    {
        $parentLibrary = $releasePlanNode->getLibrary();
        $originalData = $composerData = $parentLibrary->getComposerData();
        $constraintType = $parentLibrary->getDependencyConstraint();

        // Rewrite all dependencies.
        // Note: rewrite dependencies even if non-exclusive children, so do a global search
        // through the entire tree of the plan to get the new tag
        $items = $this->getReleasePlan()->getAllItems();
        foreach ($items as $item) {
            $childName = $item->getLibrary()->getName();

            // Ensure this library is allowed to release this dependency (even if shared)
            if (!isset($composerData['require'][$childName]) || !$parentLibrary->isChildLibrary($childName)) {
                continue;
            }

            // Update dependency
            $composerData['require'][$childName] = $this->stabiliseDependencyRequirement(
                $output,
                $item,
                $constraintType
            );
        }

        // Save modifications to the composer.json for this module
        if ($composerData !== $originalData) {
            $parentName = $parentLibrary->getName();
            $this->log($output, "Rewriting composer.json for <info>$parentName</info>");
            $parentLibrary->setComposerData($composerData, true, 'MNT Update release dependencies');
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
        // Get constraint for this version
        $childRequirement = $item->getVersion()->getConstraint($constraintType);

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

        $this->log($output, 'Tagging complete');
    }


    /**
     * Update github release notes via github API
     *
     * @param OutputInterface $output
     * @param LibraryRelease $release
     */
    protected function createGitHubRelease(OutputInterface $output, LibraryRelease $release)
    {
        $library = $release->getLibrary();
        $libraryName = $library->getName();
        $tag = $release->getVersion()->getValue();
        $this->log($output, "Creating GitHub release for <info>{$libraryName}</info> library as <info>{$tag}</info>");

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

        $client = $this->getGithubClient($output);
        /** @var Repo $reposAPI */
        $reposAPI = $client->api('repo');
        $releasesAPI = $reposAPI->releases();

        // Build release payload
        // Note target_commitish is omitted since the tag already exists
        $releaseData = [
            'tag_name' => $tag,
            'name' => $tag,
            'prerelease' => !$version->isStable(),
            'draft' => false,
        ];
        $notes = $this->getReleaseNotes($output, $client, $release);
        if ($notes) {
            $releaseData['body'] = $notes;
        }

        // Determine if editing or creating a release
        $existing = null;
        try {
            sleep(1); // Ensure non-zero period between tagging and searching for this tag
            $existing = $releasesAPI->tag($org, $repo, $tag);
        } catch (RuntimeException $ex) {
            if ($ex->getCode() !== 404) {
                throw $ex;
            }
        }

        // Create or update
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
     * Use the GitHub API to get the release notes for this release
     */
    private function getReleaseNotes(OutputInterface $output, GithubClient $client, LibraryRelease $release): string
    {
        $this->log($output, 'Getting release notes from GitHub API');

        // Get arguments to send to GitHub API
        $version = $release->getVersion();
        $args = [
            'tag_name' => $version->getValue(),
            'target_commitish' => $release->getLibrary()->getBranch(),
            'previous_tag_name' => $this->getPreviousReleaseTag($output, $release),
        ];

        // Get release notes from GitHub API
        $slug = $release->getLibrary()->getGithubSlug();
        list($org, $repo) = explode('/', $slug);
        $reponse = $client->getHttpClient()->post(
            '/repos/' . rawurlencode($org) . '/' . rawurlencode($repo) . '/releases/generate-notes',
            [],
            json_encode($args)
        );

        $notes = ResponseMediator::getContent($reponse);
        if (isset($notes['body'])) {
            return $notes['body'];
        }

        throw new LogicException('Could not get release notes for ' . $release->getLibrary()->getName());
    }

    /**
     * Get the name of the tag to use as the previous version for generating release notes
     */
    private function getPreviousReleaseTag(
        OutputInterface $output,
        LibraryRelease $release
    ): string {
        $version = $release->getVersion();
        $major = $version->getMajor();
        $minor = $version->getMinor();
        $patch = $version->getPatch();

        // Patch release (e.g. 1.2.3)
        // Previous tag is x.y.z-1 (e.g. 1.2.2)
        if ($patch > 0) {
            $previousPatch = $patch - 1;
            $previousTag = "$major.$minor.$previousPatch";
            if ($output->isVeryVerbose()) {
                $this->log($output, "Release is a patch. Previous tag is $previousTag");
            }
            return $previousTag;
        }

        // Minor release (e.g. 1.3.0)
        // Previous tag is x.y-1.* where * is the latest patch available (e.g. 1.2.3)
        if ($minor > 0) {
            $previousMinor = $minor - 1;
            // Find most recent tag for previous minor
            $previousTag = $this->discoverMostRecentTag($major, $previousMinor, $release->getLibrary()->getTags());
            if (!$previousTag) {
                // If we couldn't find the tag to use as the previous version, just let GitHub infer it
                $this->log($output, 'Could not find a reliable latest tag for the previous minor');
            } elseif ($output->isVeryVerbose()) {
                $this->log($output, "Release is a minor. Previous tag is $previousTag");
            }
            return $previousTag;
        }

        // Major release (e.g. 2.0.0)
        // Previous tag is x-1.*.* where * is the latest minor/patch available (e.g. 1.2.3)
        $previousMajor = $major - 1;
        if ($previousMajor > 0) {
            # Find most recent tag for previous major
            $previousTag = $this->discoverMostRecentTag($previousMajor, null, $release->getLibrary()->getTags());
            if (!$previousTag) {
                // If we couldn't find the tag to use as the previous version, just let GitHub infer it
                $this->log($output, 'Could not find a reliable latest tag for the previous major');
            } elseif ($output->isVeryVerbose()) {
                $this->log($output, "Release is a major. Previous tag is $previousTag");
            }
            return $previousTag;
        }

        // For major 1.0.0 releases, there is no suitable previous version to use.
        $this->log($output, 'This is the first stable major version. No suitable previous tag to choose from');
        return '';
    }

    /**
     * Discover the most recent stable tag for a given major and minor.
     *
     * @param int|null $minor If null, get the most recent minor for the given major.
     * @param Version[] $tags
     */
    private function discoverMostRecentTag(int $major, ?int $minor, array $tags): string
    {
        $detectMinor = $minor === null;
        $highestMinor = -1;
        $highestPatch = -1;
        foreach ($tags as $tag) {
            // Ignore non-stable tags or tags for other majors/minors
            if (!$tag->isStable() || $tag->getMajor() !== $major || (!$detectMinor && $tag->getMinor() !== $minor)) {
                continue;
            }
            // Find the highest version matching the given major and maybe minor
            $tagMinor = $tag->getMinor();
            $tagPatch = $tag->getPatch();
            if ($detectMinor && $tagMinor > $highestMinor) {
                $highestMinor = $tagMinor;
                $highestPatch = $tagPatch;
            } elseif ((!$detectMinor || $tagMinor == $highestMinor) && $tagPatch > $highestPatch) {
                $highestPatch = $tagPatch;
            }
        }
        // Return the highest matching version
        if ($highestPatch > -1) {
            if ($detectMinor) {
                $minor = $highestMinor;
            }
            return "$major.$minor.$highestPatch";
        }
        return '';
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
        $httpClient = GuzzleClient::createWithConfig([
            'http_errors' => false // http errors are not runtime errors
        ]);
        $client = GithubClient::createWithHttpClient($httpClient);
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
