## Cow schema

Schema of a `.cow.json` file:

Basic options are:

* `github-slug` is used for making github-api calls. Will be guessed from git remote if omitted.
* `commit-link` will be used to generate changelog links to commits. Can be guessed from github-slug
  for github projects.
* `changelog-holder` Specify a child library as the "holder" for the changelog. Defaults to the same module.
* `changelog-path` location of changelog to build for this project.
  Relative to the root directory of the changelog-holder.
* `changelog-template` location of Twig template to use when generating changelogs. See [readme.md](readme.md).
  Relative to the root directory of the project.
* `changelog-github` (bool) Push up changelog to github up via github release API (v3)
* `changelog-format` Either 'flat' (single list of items) or 'grouped' (grouped by standard groups).\
  Defaults to 'grouped' if left out.
* `changelog-include-other-changes` Whether to include commits that are not tagged with Cow friendly prefixes. Default
  is false.
* `child-stability-inherit` (bool|array) If set to true, child modules will be released with the same stability
  (e.g. -alpha1) as the parent. This can also be set to an array of modules to limit child stability inheritance to.
* `vendors` Declare list of child requirement library vendors that will be released. A vendor must be declared,
  otherwise no child dependencies will be released.
* `exclude` Declares the list of requirements to not include. Requirements for these modules will be omitted,
  and changes to these modules will not appear in any changelog.
* `upgrade-only` Declare the list of requirements that should be upgraded. However, no releases to these modules
  will be made directly. If a module is not in 'upgrade-only', but matches any of the above `vendor` whitelists,
  then a new release of this module will be made. This can be overridden via the interactive release blueprint
  confirmation interface. Note that this will ONLY match stable tags! (not pre-release tags or dev branches)
* `dependency-constraint` declares how loose to allow dependencies when tagging. Can be one of the below:
   - `exact` Dependencies will be locked at exact tag. E.g. `4.0.0-alpha2` (default)
   - `loose` Dependencies will allow patch version upgrades. E.g. `~4.0.0-alpha2`
   - `semver` Dependencies will allow minor version upgrades. E.g. `^4.0.0-alpha2`

```json
{
  "github-slug": "silverstripe/silverstripe-installer",
  "commit-link": "https://github.com/silverstripe/silverstripe-installer/commit/{sha}",
  "changelog-holder": "silverstripe/framework",
  "changelog-path": "docs/en/08_Changelogs/{stability}/{version}.md",
  "changelog-template": "changelog.md.twig",
  "changelog-type": "grouped",
  "changelog-github": true,
  "child-stability-inherit": true,
  "dependency-constraint": "loose",
  "vendors": [
    "silverstripe",
    "silverstripe-australia"
  ],
  "exclude": [
    "silverstripe-themes/simple"
  ],
  "upgrade-only": [
    "silverstripe/some-module"
  ],
  "tests": [
    "vendor/bin/phpunit framework/admin/tests",
    "vendor/bin/phpunit framework/tests",
    "vendor/bin/phpunit cms/tests"
  ],
  "archives": [
    {
      "recipe": "silverstripe/recipe-core",
      "files": [
        "SilverStripe-framework-v{version}.zip",
        "SilverStripe-framework-v{version}.tar.gz",
      ]
    },
    {
      "recipe": "silverstripe/installer",
      "files": [
        "SilverStripe-cms-v{version}.zip",
        "SilverStripe-cms-v{version}.tar.gz",
      ]
    }
  ]
}
```
