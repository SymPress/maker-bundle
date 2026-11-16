# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html)
where applicable.

## Unreleased

- Initial Maker Bundle package implementation, documentation, and QA workflow.

## 1.0.4 — 2026-11-16

- Validate the `make:data-provider --method` option as a PHP identifier so it can no longer inject code into the generated class.
- Reject `make:asset-entry --path` values that leave the package (traversal segments, absolute paths, symlink escapes).
- Restrict `make:asset-entry --handle` to lowercase letters, digits, `-` and `_` so it cannot break out of the generated source comment.
- `make:block` now fails instead of silently overwriting an existing `block.json`, editor/frontend entry or view template.
- CI: pin `sympress/workflows` to v1.3.2.

## 1.0.3 — 2026-11-06

- Use the Symfony 8.1.11 security patches released on November 4.
