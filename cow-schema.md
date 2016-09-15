## Cow schema

Schema of a `.cow.json` file:

Recipe example.

Note that "child-stability-inherit" means "when tagging a recipe at a stability,
tag all children at the same stability". If omitted, all children will be tagged as stable only.

* `link` is used for all module types. Will be guessed from git remote if omitted.
* `github-slug` is used for making github-api calls. Will be guessed from git remote if omitted.
* `options` are array of options for this module. These are flags which may include:
  - `child-stability-inherit` Child modules will be released with the same stability (e.g. -alpha1) as this
  - `unstable-branch` Temporary branches (e.g. 4.0.1) will be used/created for unstable releases.

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
