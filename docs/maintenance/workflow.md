# Release workflow

Versions are not changed manually during normal development. Conventional Commits on `main` are analyzed by Semantic Release.

The release transaction:

1. calculates the next semantic version;
2. writes it to `composer.json` and `package.json`;
3. refreshes `composer.lock` metadata;
4. runs `composer release:check`;
5. updates `CHANGELOG.md`;
6. commits the synchronized release files and creates the GitHub tag/release.

Before pushing a release-triggering commit, run:

```bash
composer run setup
pnpm run setup
pnpm run verify:all
git status --short
```

`verify:all` already runs PHP coding style, Psalm, all PHPUnit suites,
release readiness, generated assets, documentation, repository checks and both
browser configurations. Use the individual Composer or pnpm scripts only for a
targeted rerun after the compact verifier reports the first failure.

The manual installation download is GitHub's automatic archive for the immutable release tag. `.gitattributes` filters development files from that archive; no custom ZIP artifact is built or uploaded.
