# BizManager Update Package Format

An update package is a ZIP file with this structure:

```
manifest.json
files/
    app/...
    bootstrap/app.php
    config/...
    database/migrations/...
    public/...
    resources/...
    routes/...
    vendor/...
    composer.json
    composer.lock
    artisan
    index.php
    .htaccess
    VERSION
```

## `manifest.json`

```json
{
  "type": "bizmanager-update",
  "version": "1.1.0",
  "min_from_version": "1.0.0",
  "release_notes": "Fixes the POS checkout button on tablets; adds multi-branch support.",
  "generated_at": "2026-08-30T12:00:00Z"
}
```

| Field | Required | Meaning |
|---|---|---|
| `type` | yes | Must be exactly `"bizmanager-update"`. Anything else is rejected before the ZIP is even opened for extraction. |
| `version` | yes | The version this package upgrades the installation to. Compared against the installation's own `VERSION` file with `version_compare()` — the package is rejected unless it's strictly newer. |
| `min_from_version` | no | The oldest installed version this package can be applied on top of. If the installation is older than this, the update is rejected with a message asking for an intermediate update first (no attempt to "catch up" multiple versions in one package). |
| `release_notes` | no | Freeform text shown to the Platform Admin before they confirm the install. |
| `generated_at` | no | Informational only; not validated. |

## `files/`

Mirrors the application's own directory structure exactly. Every file under
`files/` is copied to the same relative path in the live installation,
**except**:

- Anything under `.env`, `storage/`, `public/storage`, `bootstrap/cache/`,
  or `database/database.sqlite` — always skipped, regardless of what the
  package contains. This is enforced in code
  (`PlatformUpdateService::PROTECTED_PREFIXES`), not just convention, so a
  malformed or malicious package can't overwrite tenant data, the local
  environment config, or framework cache.
- A file under `database/migrations/` that already exists at the
  destination — always skipped. A fix to already-shipped behavior belongs
  in a **new** migration file, never an edit to one that may have already
  run against a real installation's database.

Building a full-application `files/` payload (rather than a partial diff)
keeps the updater simple and avoids any patch/merge logic — it's the exact
same `vendor/`-included, `composer install`-already-run payload the
project's own release zip pipeline produces (see `CLAUDE.md`), just placed
under a `files/` prefix alongside a `manifest.json`.

## Safety guarantees

- **Path traversal**: every entry name in the ZIP is checked for `..`,
  a leading `/`, or a Windows drive prefix before anything is extracted;
  the whole package is rejected if any entry fails this check.
- **Backup before overwrite**: any existing file a package is about to
  replace is moved (not copied — fast, since it's a same-filesystem
  rename rather than a bulk copy) into
  `storage/app/update-backups/{timestamp}/` first.
- **Rollback on failure**: if applying files, running migrations, or
  clearing caches throws at any point, every file that was already backed
  up is restored and every newly-created file is deleted, then the error
  is reported. A partially-run migration batch is the one thing rollback
  cannot undo automatically — the failure message says so explicitly and
  the Platform Admin is expected to check the database before retrying.
- **No shell access assumed**: applying an update never calls `exec()`,
  `shell_exec()`, or similar — only PHP's own filesystem functions
  (wrapped by Laravel's `File` facade) and `Illuminate\Support\Facades\Artisan::call()`
  for migrations/cache-clearing, all of which work identically to a
  normal web request on shared hosting.
