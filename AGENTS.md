# AGENTS.md

This repo is released via GitHub Actions on pushes to `main`. The plugin header in `bp-cleanup.php` is authoritative for versions. `composer.json` must match it.

## Keep the repo updated

- Document all new features and instructions in `README.md`.
- Keep basic user instructions in `readme.txt` (installation, usage, and FAQs).
- Ensure `readme.txt` includes release notes under `== Changelog ==` for each version.

## Release checklist

1. Update versions in all versioned files:
   - `bp-cleanup.php` (`Version:` header)
   - `composer.json` (`version`)
   - `readme.txt` (`Stable tag:`)
2. Add release notes to `readme.txt` under `== Changelog ==`.
3. Update `README.md` with new features and instructions.
4. Update basic instructions and FAQs in `readme.txt`.
5. Push to `main`; the release workflow will tag and publish `vX.Y.Z` if versions match and are greater than the latest tag.

## Documentation usage

- `readme.txt` `== Changelog ==`: full, version-by-version list of changes.
- `readme.txt` `== Upgrade Notice ==`: short, user-facing highlight of why to update.
- `README.md`: detailed feature and usage documentation for the repo.

## Versioning guidance

- If a requested version is less than or equal to the current version, ask for confirmation and a corrected version.
- Suggest the next version based on change impact (SemVer):
  - Patch (`x.y.z+1`) for fixes, small updates, or documentation-only changes.
  - Minor (`x.y+1.0`) for new features that are backward-compatible.
  - Major (`x+1.0.0`) for breaking changes or minimum requirement bumps.
