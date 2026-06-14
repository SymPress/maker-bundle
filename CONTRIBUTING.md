# Contributing

Thanks for taking the time to improve SymPress Maker Bundle.

## Local Setup

```bash
composer install
composer tests
composer cs
```

The package uses PHP 8.5, Symfony MakerBundle, SymPress Kernel, PHPUnit, and
PHPCS with the SymPress coding standards.

## Pull Requests

- Keep pull requests focused on one maker, resolver, or documentation change.
- Add or update tests for package resolution, generated file placement, or maker behavior.
- Run the available checks before opening a pull request.
- Use Conventional Commits for commit messages, for example
  `feat(maker-bundle): add package-aware hook maker`.

## Coding Guidelines

- Keep generated files aligned with the package's own PSR-4 namespace mapping.
- Prefer Composer metadata and package context over hard-coded workspace paths.
- Preserve Symfony MakerBundle command behavior unless a package-aware override is required.
