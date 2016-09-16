## Cow schema

Schema of a `.cow.json` file:

Recipe example.



* `link` is used for all module types. Will be guessed from git remote if omitted.
* `github-slug` is used for making github-api calls. Will be guessed from git remote if omitted.
* `options` are array of options for this module. These are flags which may include:
  - `child-stability-inherit` Child modules will be released with the same stability (e.g. -alpha1) as the parent.
  - `use-unstable-branch` Temporary branches (e.g. 4.0.1) will be used/created for unstable releases.
* `vendors` Declare list of child requirement library vendors that will be released. A vendor must be declared,
  otherwise no child dependencies will be released.
* `exclude` Declares the list of requirements to not include. Requirements for these modules will be omitted,
  and changes to these modules will not appear in any changelog.
* `upgrade-only` Declare the list of requirements that should be upgraded. However, no releases to these modules
  will be made directly. If a module is not in 'upgrade-only', but matches any of the above `vendor` whitelists,
  then a new release of this module will be made. This can be overridden via the interactive release blueprint
  confirmation interface.

```json
{
  "link": "https://github.com/silverstripe/silverstripe-installer",
  "github-slug": "silverstripe/silverstripe-installer",
  "changelog": "framework/docs/en/04_Changelogs/{stability}/{version}.md",
  "tagging": "normal",
  "options": [
    "child-stability-inherit",
    "use-unstable-branch"
  ],
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

Framework example. If omitted, lang directories default to `lang`.

```json
{
  "tagging": "normal",
  "directories": {
      "lang": "lang",
      "jslang": [
        "admin/client/lang",
        "client/lang"
      ]
  }
}
```

Default config

```json
{
  "tagging": "normal",
  "directories": {
    "lang": "lang"
  }
}
```
