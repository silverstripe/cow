## Cow schema

Schema of a `.cow.json` file:

Basic options are:

* `github-slug` is used for making github-api calls. Will be guessed from git remote if omitted.
* `commit-link` will be used to generate changelog links to commits. Can be guessed from github-slug for github projects.
* `changelog` location of changelog to build for this project.
* `github-tagging` Instead of using annotated tags, use github API to push up release with change notes automatically generated.
* `child-stability-inherit` If set to true, child modules will be released with the same stability (e.g. -alpha1) as the parent.
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
   - `allow-patch` Dependencies will allow patch version upgrades. E.g. `~4.0.0-alpha2`
   - `allow-minor` Dependencies will allow minor version upgrades. E.g. `^4.0.0-alpha2`

```json
{
  "github-slug": "silverstripe/silverstripe-installer",
  "commit-link": "https://github.com/silverstripe/silverstripe-installer/commit/{sha}",
  "changelog": "framework/docs/en/04_Changelogs/{stability}/{version}.md",
  "github-tagging": true,
  "child-stability-inherit": true,
  "dependency-constraint": "allow-patch",
  "vendors": [
    "silverstripe",
    "silverstripe-australia"
  ],
  "exclude": [
    "silverstripe-themes/simple"
  ],
  "upgrade-only": [
    "silverstripe/some-module"
  ]
}
```

Module example. Changes are pushed up via github instead. 

```json
{
  "tagging": "github"
}
```

Default config

```json
{
  "tagging": "normal"
}
```
