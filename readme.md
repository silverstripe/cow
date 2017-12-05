# Cow

The ineptly named tool which may one day supercede the older [build tools](https://github.com/silverstripe/silverstripe-buildtools).

![moo](https://media.giphy.com/media/A5pcWMMIEO95S/giphy.gif)

## Install

You can install this globally with the following commands

```
composer global require silverstripe/cow:dev-master
echo 'export PATH=$PATH:~/.composer/vendor/bin/'  >> ~/.bash_profile
```

Now you can run `cow` at any time, and `composer global update` to perform time-to-time upgrades.

Make sure that you setup your AWS credentials properly, and create a separate profile named `silverstripe`
for this. You'll also need the aws cli installed.

If you're feeling lonely, or just want to test your install, you can run `cow moo`.

## Dependencies

* See [the transifex client docs](https://github.com/transifex/transifex-client) for instructions on
  installing transifex-client. Cow requires at least version 0.12.
* The yamlclean ruby gem is also required for localisation. Install yamlclean gem using `gem install yamlclean`.
* For uploading of archive to s3 you must also install the
  [AWS CLI tools](http://docs.aws.amazon.com/cli/latest/userguide/installing.html).

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
* `--aws-profile <profile>` to specify the AWS profile name for uploading releases to s3. Check with
  damian@silverstripe.com if you don't have an AWS key setup.
* `--skip-archive-upload` to disable both "archive" and "upload". This is useful if doing a private release and
  you don't want to upload this file to AWS.
* `--skip-upload` to disable the "upload" command (but not archive)

The release process, as with the initial `cow release` command, will actually be composed of several sub-commands,
each of which could be run separately.

* `release:tag` Add annotated tags to each module and pushes
* `release:archive` Generate tar.gz and zip archives of this release
* `release:upload` Upload archived projects to silverstripe.org

After the push step, `release:publish` will automatically wait for this version to be available in packagist.org
before continuing.
