# SymPress Maker Bundle

[![Checks](https://img.shields.io/github/actions/workflow/status/SymPress/maker-bundle/qa.yml?branch=main&label=checks)](https://github.com/SymPress/maker-bundle/actions/workflows/qa.yml) [![Release](https://img.shields.io/github/v/release/SymPress/maker-bundle?label=release)](https://github.com/SymPress/maker-bundle/releases) [![PHP](https://img.shields.io/packagist/dependency-v/sympress/maker-bundle/php.svg?label=php)](https://packagist.org/packages/sympress/maker-bundle) [![Downloads](https://img.shields.io/packagist/dt/sympress/maker-bundle.svg?label=downloads)](https://packagist.org/packages/sympress/maker-bundle/stats) [![License: GPL-2.0-or-later](https://img.shields.io/badge/license-GPL--2.0--or--later-blue.svg)](LICENSE) [![Security Policy](https://img.shields.io/badge/security-policy-2ea44f.svg)](SECURITY.md)

Package-aware integration for `symfony/maker-bundle` in SymPress website workspaces.

## Installation

```bash
composer require --dev sympress/maker-bundle
```

The bundle imports Symfony MakerBundle's own services and maker registrations, then replaces the MakerBundle autoload resolver with a resolver that understands the local `packages/*` workspace and Composer-installed packages.

That keeps the MakerBundle command surface intact while letting generated PHP classes land in the package that owns their namespace:

```bash
./bin/console make:command 'Brianvarskonst\Theme\Command\RebuildEditorialIndexCommand'
./bin/console make:validator 'Brianvarskonst\Booking\Validator\BookingSlot'
```

If a command receives a fully-qualified class name, the resolver maps the longest matching PSR-4 namespace prefix to its package path.
MakerBundle code paths that use Symfony's newer `ClassData` API, such as controllers and validators, are normalized to the same package-aware namespace rules.

Project-specific makers:

```bash
./bin/console make:sympress-package sympress/example
./bin/console make:sympress-package sympress/framework-addon --type=library
./bin/console make:sympress-package sympress/contracts --type=package
./bin/console make:sympress-package sympress/bootstrap --type=wordpress-mu-plugin
./bin/console make:sympress-package brianvarskonst/site-theme --type=wordpress-theme
./bin/console make:hook 'Brianvarskonst\Theme\Hook\ExampleHook' --hook init
./bin/console make:block 'Brianvarskonst\Booking\Block\ExampleBlock'
./bin/console make:config-loader brianvarskonst/booking
./bin/console make:asset-entry brianvarskonst/booking frontend --location=frontend
./bin/console make:data-provider 'Brianvarskonst\Theme\DataProvider\Templating\ExampleDataProvider'
```

`make:sympress-package` mirrors the package types already used in this workspace:
`library` creates a kernel bundle library with a package-name entry, `package` creates a
plain contracts/utility package without kernel metadata, `wordpress-plugin` and
`wordpress-muplugin` create WordPress entry files, and `wordpress-theme` creates a
theme bundle with `functions.php` and `style.css`. The human-friendly
`wordpress-mu-plugin` alias is normalized to Composer's `wordpress-muplugin` type.

Optional configuration:

```yaml
sympress_maker:
    root_namespace: 'Brianvarskonst\Theme'
    entity_namespace: 'Brianvarskonst\Theme\Entity'
    generate_final_classes: true
    generate_final_entities: false
```

Without explicit configuration, the bundle prefers the first WordPress theme namespace as the MakerBundle root namespace and falls back to the first discovered production PSR-4 namespace.

## Development

```bash
composer install
composer tests
composer cs
```

The package repository ships its own QA workflow. Organization-wide issue
templates and community defaults are provided by the SymPress `.github`
repository.

## License

This package is licensed under `GPL-2.0-or-later`.
