# GitHub Actions

Date: 2026-03-21

This project includes two GitHub Actions workflows:

1. `CI` – runs code quality checks and the Laravel test suite.
2. `Release` – verifies the code on a version tag and publishes a GitHub Release.

## Workflow files

- `.github/workflows/ci.yml`
- `.github/workflows/release.yml`

## 1) CI workflow

File: `.github/workflows/ci.yml`

### Triggers

- `push` to:
  - `master`
  - `feature/**`
  - `bugfix/**`
  - `hotfix/**`
  - `chore/**`
- any `pull_request`

### What it does

For each configured PHP version (`8.3`, `8.4`), the workflow:

1. checks out the repository,
2. installs PHP and Composer,
3. restores/caches Composer dependencies,
4. runs `composer install`,
5. prepares `.env` and generates an app key,
6. runs code style checks with `vendor/bin/pint --test`,
7. runs the full Laravel test suite with `composer test`.

### Why this fits the current app

- Tests already use in-memory SQLite via `phpunit.xml`.
- No production deployment target is configured yet.
- Frontend build is intentionally omitted from CI because this repo is currently backend-first and does not rely on a real frontend build pipeline.

## 2) Release workflow

File: `.github/workflows/release.yml`

### Triggers

- `push` on version tags matching `v*`
- manual run via `workflow_dispatch`

### What it does

The workflow:

1. checks out the repository,
2. installs PHP 8.3 and Composer,
3. restores/caches Composer dependencies,
4. runs `composer install`,
5. prepares `.env` and generates an app key,
6. runs Pint style checks,
7. runs the Laravel test suite,
8. publishes a GitHub Release with generated release notes.

### Why this is "CD" for now

There is no deployment target configured yet (server, container registry, Vapor, Forge, etc.).
So the current CD step is release automation:

- a version tag is pushed,
- code is verified,
- a GitHub Release is created automatically.

This gives you a clean release process now and can be extended later to real deployment.

## Branch and tag conventions

### Recommended feature branch flow

```bash
git checkout -b chore/github-actions-cicd
```

### Recommended release tagging

```bash
git tag -a v0.1.1 -m "v0.1.1 - short release note"
git push origin master
git push origin v0.1.1
```

Pushing the tag triggers the `Release` workflow.

## Important GitHub token note

If you push workflow files under `.github/workflows/` using HTTPS and a Personal Access Token, GitHub may reject the push unless the token has the `workflow` scope.

Typical error:

- `refusing to allow a Personal Access Token to create or update workflow ... without workflow scope`

If that happens:

1. edit your PAT in GitHub,
2. add the `workflow` scope,
3. update the cached credential locally,
4. push again.

## Local commands mirrored by CI

These are the core commands the workflows run:

```bash
composer install --prefer-dist --no-interaction --no-progress
cp .env.example .env
php artisan key:generate
vendor/bin/pint --test
composer test
```

## Future improvements

Later, you can extend GitHub Actions with:

- deployment to a real environment,
- database service containers (MySQL/Redis) for integration tests,
- Docker image build/publish,
- security scanning,
- dependency update automation,
- release asset packaging.

