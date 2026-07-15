# SymPress Maker Bundle

Package-aware Symfony MakerBundle integration for the multi-repository
SymPress workspace. Read `docs/maker-matrix.md` before changing a maker or
skeleton.

## Key paths

- `src/Maker/`: command inputs and generated-file orchestration.
- `Resources/skeleton/`: local PHP skeletons.
- `src/Util/PackageAwareGenerator.php`: PSR-4-aware output placement.
- `Resources/config/services.php`: maker registration and dependencies.
- `tests/Util/PackageAwareGeneratorTest.php`: generated-output contracts.

## Verification

- Focused: `vendor/bin/phpunit --configuration phpunit.xml.dist --filter PackageAwareGeneratorTest`
- Full: `composer qa`

## Invariants

- Resolve output paths from Composer PSR-4 metadata, never a hard-coded
  workspace layout.
- Preserve upstream MakerBundle behavior unless package-aware placement needs
  an override.
- A maker change must update its skeletons, service wiring, matrix entry, and
  golden expectation together when applicable.
- Generated service and Webpack edits must remain idempotent.

## Cross-repository impact

Generated packages target `sympress/kernel` conventions. Changes to bundle
metadata, plugin entries, service tags, or QA scripts must be checked against a
freshly generated package before release.

## Definition of done

The relevant golden test passes, `docs/maker-matrix.md` matches the registered
commands, `composer qa` passes, and generated fixture output is not committed.
