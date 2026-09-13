# Mandatory Release Protocol for phpBB SEO Framework

Whenever a new version of the extension is released, the agent MUST perform the following end-to-end release workflow:

1. **Verification & Tests**:
   - Run the complete test runner (`scratch/run_full_test_suite.php`) and ensure 100% tests pass.
2. **Version Bump & Changelog**:
   - Bump version and date in `composer.json`.
   - Update `CHANGELOG.md` with complete, detailed release notes following Keep a Changelog.
3. **Git Commit & Tag**:
   - Commit changes to `main`.
   - Create annotated git tag `vX.Y.Z`.
   - Push commit and tag to GitHub origin.
4. **Package Distribution Archive**:
   - Package clean zip archive `phpbbseo_framework_X.Y.Z.zip` (structure: `phpbbseo/framework/*` excluding `.git`, `tests/`, etc.).
5. **Publish GitHub Release**:
   - Create GitHub release with tag `vX.Y.Z` and upload the zip distribution asset.
6. **Deploy to phpbbseo.com**:
   - Deploy extension files to `public_html/ext/phpbbseo/framework/`.
   - Update `public_html/ext/phpbbseo/framework.zip`.
   - Update `public_html/updatecheck/framework.json` with new version, download link, and announcement URL.
   - Purge phpBB container cache (`public_html/cache/production/`).
7. **Post Official Announcement Topic**:
   - Publish a comprehensive, SEO-optimized announcement topic in Forum ID 2 (`https://www.phpbbseo.com/forum/announcements-news-2/`) under user `admin`.
   - Include clear feature explanations, problem/solution background, international usability, compatibility matrix, and direct upgrade instructions.
