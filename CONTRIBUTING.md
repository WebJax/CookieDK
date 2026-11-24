# Contributing / Bidrag

## Dansk
1. Fork og opret feature-branch.
2. Følg WordPress Coding Standards.
3. Kør tests og statisk analyse:
   - `composer test`
   - `composer phpstan`
   - `composer phpcs`
4. Åbn pull request med beskrivelse, testnoter og risikovurdering.
5. Bump ikke versionsnummeret i feature-PRs. Releases laves som beskrevet nedenfor.

## English
1. Fork and create a feature branch.
2. Follow WordPress Coding Standards.
3. Run checks:
   - `composer test`
   - `composer phpstan`
   - `composer phpcs`
4. Open a PR with scope, tests, and risk notes.
5. Do not bump the plugin version in feature PRs. Use the release flow below.

## Release / Udgivelse

Display-versionen står i `COOKIEDK_VERSION` og plugin-headeren i `cookiedk.php`. Samme nummer kopieres til `readme.txt` (`Stable tag`) og README-badget. `COOKIEDK_DB_VERSION` er schema-version og bumper ikke her.

1. Hold `[Unreleased]` i `CHANGELOG.md` opdateret, når features merges.
2. I GitHub: **Actions → Release → Run workflow**, og vælg `patch`, `minor` eller `major`.
3. Det åbner en `release/vX.Y.Z` pull request. Review og merge.
4. Merge til `main` opretter tag `vX.Y.Z` og en GitHub Release.
5. Hent bump-commit'et i Cursor:

   `git fetch origin && git checkout main && git pull`

Lokalt (samme filer, intet tag):

```bash
php scripts/bump-version.php --check
php scripts/bump-version.php --dry-run patch
php scripts/bump-version.php patch
```

Valgfrit: sæt repo-secret `RELEASE_TOKEN` (classic PAT med `repo`-scope), så Tests og Security kører på release-PR'en. `GITHUB_TOKEN` kan stadig åbne PR'en; GitHub starter ikke de workflows fra bot-tokenet.
