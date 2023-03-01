# Cow

[![CI](https://github.com/silverstripe/cow/actions/workflows/ci.yml/badge.svg)](https://github.com/silverstripe/cow/actions/workflows/ci.yml)

The ineptly named tool which may one day supercede the older [build tools](https://github.com/silverstripe/silverstripe-buildtools).

![moo](https://media.giphy.com/media/8AsXV477ls6LS/giphy.gif)

## DEV_MODE
Enable DEV_MODE by adding DEV_MODE=1 to a .env file. This will prevent pushing to github.

Do not perform a release on a directory if you previously used DEV_MODE on that directory because the steps will be out of order. Instead, delete the directory that had DEV_MODE run on it and start the entire release again with DEV_MODE disabled.

The .env file needs to be in the /cow directory, not the releases directory. E.g. if your cow directory is in ~/Releases/cow, then create the .env file as ~/Releases/cow/.env

## Install

Requires Docker.

*You do not need to know how Docker works*, but you need to have it [installed and running](https://www.docker.com/get-started).

For production purposes Cow should be run via the Docker-based scripts included in the `./docker/bin` folder, rather than via direct invocation on your machine. That ensures use of consistent versions of all 3rd party libraries and tools and is the only supported installation option.

Tested on `Linux` and `macOS`, but should work on Windows too (with either `Cygwin` or `WSL`).

When it's installed, to run Cow launch `./docker/bin/run` script.
That will automatically download the latest released version of Cow docker image and launch it
transparently in your terminal.

When publishing a release, use the `./docker/bin/release` script. This is similar to `run`, but runs an SSH-Agent and performs some extra checks.

At the time of writing this readme (2020-02-03) you will need a [GITHUB_TOKEN (scopes: `repo` and `read:packages`)](https://github.com/settings/tokens/new?scopes=repo,read:packages).

Then you need to configure `composer` of your Cow distribution by running

```sh
./docker/bin/composer config -g github-oauth.github.com TOKEN
```

For more details on these and other available scripts, see [docker/README.md](./docker/README.md).

### Adding Cow to your $PATH (optional)

If you want to run Cow globally, you can create a symlink to `docker/bin/release` in a folder that is included in your `$PATH`.

Here's an example (feel free to reuse any of your existing folders from yor $PATH)
```sh
mkdir -p ~/.local/bin;  # ensure existing ~/.local/bin
ln -s ./docker/bin/release ~/.local/bin/cow  # create symlink to the launcher and name it cow
echo 'export PATH=$PATH:~/.local/bin' >> ~/.bashrc  # add ~/.local/bin to the $PATH
```

After you reload your session (e.g. reopen the terminal) you may launch `cow` from anywhere on your system:

```sh
cd ./my-project;
cow moo
```

### Native

To run Cow without Docker, reference the `Dockerfile` for system requirements.

## Extra setup

* When publishing to GitHub make sure you have `ssh-agent` running and your SSH keys loaded (run `ssh-add`)

## Commands

Cow is a collection of different tools (steps) grouped by top level commands. It is helpful to think about
not only the commands available but each of the steps each command contains.

It is normally recommended that you run with `-vvv` verbose flag so that errors can be viewed during release.

For example, this is what I would run to release `3.1.14-rc1`.

```
cow release 3.1.14-rc1 -vvv
```

And once I've checked that all is fine, and am 100% sure that this code is ready to go.

```
cow release:publish 3.1.14-rc1 -vvv
```

## Release

`cow release <version> <recipe>` will perform the first part of the release tasks.

* `<version>` is mandatory and must be the exact tag name to release.
* `<recipe>` will allow you to release a recipe other than 'silverstripe/installer'

This command has these options:

* `-vvv` to ensure all underlying commands are echoed
* `--directory <directory>` to specify the folder to create or look for this project in. If you don't specify this,
it will install to the path specified by `./release-<version>` in the current directory.
* `--repository <repository>` will allow a custom composer package url to be specified. E.g. `http://packages.cwp.govt.nz`
  Note: If you specify the repository during setup it will be re-used for subsquent commands
  unless the `.cow.repository` file is deleted.
* `--branching <type>` will specify a branching strategy. This allows these options:
  * `auto` - Default option, will branch to the minor version (e.g. 1.1) unless doing a non-stable tag (e.g. rc1)
  * `major` - Branch all repos to the major version (e.g. 1) unless already on a more-specific minor version.
  * `minor` - Branch all repos to the minor semver branch (e.g. 1.1)
  * `none` - Release from the current branch and do no branching.
* `--skip-tests` to skip tests
* `--skip-i18n` to skip updating localisations

`release` actually has several sub-commands which can be run independently. These are as below:

* `release:create` creates the project folder
* `release:plan` Initiates release planning tool to preview release dependency versions
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

The release process, as with the initial `cow release` command, will actually be composed of several sub-commands,
each of which could be run separately.

* `release:tag` Add annotated tags to each module and pushes

After the push step, `release:publish` will automatically wait for this version to be available in packagist.org
before continuing.

## Creating changelogs

`cow release:changelog` will create a changelog which is categorised into various sets of change types, e.g.
enhancements, bug fixes, API changes and security fixes.

The changelog command takes the follow arguments and options:

* `version` The version you're releasing the project as
* `recipe` The recipe you're releasing
* `--include-other-changes` If provided, uncategorised commits will also be included in an "Other changes" section.
  Note that commits which match `ChangelogItem::isIgnored()` will still be excluded, e.g. merge commits.
* `--changelog--use-legacy-format` If provided, falls back to the old changelog format (used before Oct 2020)
* `--changelog--audit-mode` swaps changelog format to Audit Mode, which turns on `include-upgrade-only` flag
  and uses audit template for changelog logs generation (including every single change).

**Pro-tip:** Part of this command involves plan generation and/or confirmation, and you can provide the
`--skip-fetch-tags` option to prevent Cow from re-fetching all tags from origin if you have already done this
and only want to make a quick change.

### Changelog Templates

You can specify a file to use as the template for generating fresh changelogs via the `changelog-template` configuration
in `.cow.json`. This template can use [Twig](https://twig.symfony.com/doc/2.x/templates.html) syntax to inject relevant
information:

- `{{ version }}` will inject the version that the changelog is being generated for (e.g. `1.2.3`)
- `{{ logs }}` will inject the commit logs with before/after delimiters, so they can be updated later without destroying
  any other changes to the contents.

## Synchronising data to supported modules

Cow includes commands to help synchronise standardised data to all
[commercially supported modules](https://www.silverstripe.org/software/addons/silverstripe-commercially-supported-module-list/):

* `cow github:synclabels` Pushes a centralised list of labels to all supported module GitHub repositories
* `cow github:ratelimit` Check your current GitHub API rate limiting status (sync commands can use this up quickly)

**Note:** All GitHub API commands require a `GITHUB_ACCESS_TOKEN` environment variable to be set before they can be
used.

### Labels

[Centralised label configuration](https://github.com/silverstripe/supported-modules/blob/gh-pages/labels.json) can be
pushed out to all [supported modules](https://github.com/silverstripe/supported-modules/blob/gh-pages/modules.json)
using the `cow github:synclabels` command.

This command takes an optional argument to specify which module(s) to update:

* `modules` Optionally sync to specific modules (comma delimited)

If the `modules` argument is not provided, the list of supported modules will be loaded from the `supported-modules`
GitHub repository. You can then confirm the list, before you will be shown a list of the labels that will be sync'd
and finally all labels will either be created or updated on the target repositories.

This command can max out your GitHub API rate limiting credits, so use it sparingly. If you exceed the limit you may
need to go and make a coffee and come back in an hour (check current rate limits with `cow github:ratelimit`).

### Metadata files

[File templates](https://github.com/silverstripe/supported-modules/tree/gh-pages/templates) for supported modules can
be synchronised out to all supported modules using the `module:sync:metadata` command.

This command will pull the latest version from the supported-modules repository, write the contents to each repository
then stage, commit and push directly to the default branch.

This command takes an optional argument to skip the clone/pull of each repository beforehand:

* `--skip-update` Optionally skip the clone/fetch/pull for each repository before running the sync

You will need `git` available in your system path, as well as write permission to push to each repository.

## Schema

The [cow schema file](cow.schema.json) is in the root of this project.

You can run `cow schema:validate` to check the `.cow.json` configuration file in your project or module to
ensure it matches against the Cow schema.
