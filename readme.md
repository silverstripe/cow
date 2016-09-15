# Cow

The ineptly named tool which may one day supercede the older [build tools](https://github.com/silverstripe/silverstripe-buildtools).

## Install

You can install this globally with the following commands

```
composer global require silverstripe/cow dev-master
echo 'export PATH=$PATH:~/.composer/vendor/bin/'  >> ~/.bash_profile
```

Now you can run `cow` at any time, and `composer global update` to perform time-to-time upgrades.

Make sure that you setup your AWS credentials properly, and create a separate profile named `silverstripe`
for this. You'll also need the aws cli installed.

If you're feeling lonely, or just want to test your install, you can run `cow moo`.

## Commands

Cow is a collection of different tools (steps) grouped by top level commands. It is helpful to think about
not only the commands available but each of the steps each command contains.

It is normally recommended that you run with `-vvv` verbose flag so that errors can be viewed during release.

For example, this is what I would run to release `3.1.14-rc1`, assuming there wasn't a 3.1.14 branch and I wanted
to create one for the RC release.

```
cow release 3.1.14-rc1 -vvv --from=3.1.13 --branch-auto
```

And once I've checked that all is fine, and am 100% sure that this code is ready to go.

```
cow release:publish 3.1.14-rc1 -vvv
```

## Release

`cow release <version> <recipe>` will perform the first part of the release tasks.
<version> is mandatory and must be the exact tag name to release.
<recipe> will allow you to release a recipe other than 'silverstripe/installer'

This command has these options:

* `-vvv` to ensure all underlying commands are echoed
* `--from <fromversion>` when generating a changelog, it can be necessary at times to specify the last released version.
  cow will try to guess, but sometimes (e.g. when releasing 3.2.0) it's not clear where the changelog should start.
* `--directory <directory>` to specify the folder to create or look for this project in. If you don't specify this,
it will install to the path specified by `./release-<version>` in the current directory.
* `--branch <branch>` or just `--branch-auto` will automatically branch each module to a temp branch for this release.
  If omitted, no branching is performed. `--branch-auto` can be used to just default to the major.minor.patch
  version of the release. It's advisable to specify this, but not always necessary, when doing pre-releases.

`release` actually has several sub-commands which can be run independently. These are as below:

* `release:create` creates the project folder
* `release:branch` Will (if needed) branch all modules
* `release:translate` Updates translations and commits this to source control
* `release:test` Run unit tests
* `release:changelog` Just generates the changelog and commits this to source control.

## Publishing releases

`cow release` will only build the release itself. Once all of the above steps are complete, it is necessary
to take the finished release and push it out to the open source community. A second major command `cow release:publish`
is necessary to perform the final steps. The format for this command is:

`cow release:publish <version>`

This command has these options:

* `-vvv` to ensure all underlying commands are echoed
* `--directory <directory>` to specify the folder to look for the project created in the prior step. As with
  above, it will be guessed if omitted. You can run this command in the `./release-<version>` directory and 
  omit this option.
* `--aws-profile <profile>` to specify the AWS profile name for uploading releases to s3. Check with
  damian@silverstripe.com if you don't have an AWS key setup. 

The release process, as with the initial `cow release` command, will actually be composed of several sub-commands,
each of which could be run separately.

* `release:tag` Add annotated tags to each module
* `release:push` Push branch and tag up to origin
* `release:archive` Generate tar.gz and zip archives of this release
* `release:upload` Upload archived projects to silverstripe.org

After the push step, `release:publish` will automatically wait for this version to be available in packagist.org
before continuing.

## Module-level commands

Outside of doing core releases, you can use this for specific modules

* `module:translate <modules>` Updates translations for modules and commits this to source control. If you
don't specify a list of modules then all modules will be translated. Specify 'installer' for root module.
You can use `--push` option to push to origin at the end, or `--exclude` if your list of modules is the list
to exclude. By default all modules are included, unless whitelisted or excluded.

* `module:tag <module> <version> [--from=<from>] [--directory=<dir>] [-n] [-vvv]` Will do a basic module release,
pushing up the changelog to github via the github v3 API. In order for this to work you should setup a global
environment variable `GITHUB_API_TOKEN` with an API token authenticated against yourself.
See [these instructions](https://help.github.com/articles/creating-an-access-token-for-command-line-use/)
for generating this token.

## Branch helper

When a release is done, the laborious task of merging up all changes begins. This is where it
can be handy to use the `branch:merge` command. This command has this syntax:

`branch:merge <from> <to> [<module>, ..] [--interactive] [--push] [--exclude] [-vvv]`

This should be run in the project root, and will automatically merge each core module
from the `<from>` branch into the `<to>` branch. If either branches haven't yet been
pulled from upstream, then this command will automatically pull them down, and will also
refresh any existing branch before the merge.

By default all modules specified in the root composer.json with `self.version` will be merged.
You can specify a single module (or set of modules) by adding additional arguments, which will
instead choose those modules.

If you want the merged changes to be pushed up directly, then use the `--push` command to
trigger a push after the merge is complete.

If a merge fails, or has unresolved conflicts, then a message will be displayed at the end of
execution with the list of directories that should be manually resolved. Once resolved (and
committed), just run the command again and it should continue.

`--interactive` mode will pause before each commit, to allow you to review changes.

To push up branches once issues have been merged you can run this separately:

`cow branch:push [<module>, ..] [--exclude] [-vvv]`

If you simply want to checkout a specific branch on all modules, you can use this:

`cow branch:checkout <branch> [<module>, ..] [--exclude] [--remote=<remote>] [-vvv]

This will switch to the given branch, even if on the remote only, and pull down the
latest changes.
