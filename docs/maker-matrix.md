# Maker output matrix

Local makers and the output contracts that must change together:

| Command | Local skeletons | Additional output |
| --- | --- | --- |
| `make:controller` | Upstream MakerBundle class-data generation | Package-aware namespace and output placement |
| `make:validator` | Upstream MakerBundle class-data generation | Package-aware namespace and output placement |
| `make:voter` | Upstream MakerBundle class-data generation | Package-aware namespace and output placement |
| `make:sympress-package` | Inline generation | Composer metadata, README, test/QA config, optional bundle and WordPress entry |
| `make:hook` | `Resources/skeleton/hook/Hook.tpl.php` | Optional `kernel.hook` service tag |
| `make:block` | `Resources/skeleton/block/Block.tpl.php`, `Resources/skeleton/config_loader/ConfigLoaderInterface.tpl.php`, `Resources/skeleton/asset_entry/AssetConfigLoader.tpl.php` | `block.json`, TypeScript entries, optional view, Encore and service config |
| `make:config-loader` | `Resources/skeleton/config_loader/ConfigLoaderInterface.tpl.php`, `Resources/skeleton/config_loader/FrontendConfigLoader.tpl.php`, `Resources/skeleton/config_loader/GutenbergConfigLoader.tpl.php` | Optional service config |
| `make:asset-entry` | `Resources/skeleton/config_loader/ConfigLoaderInterface.tpl.php`, `Resources/skeleton/asset_entry/AssetConfigLoader.tpl.php` | TypeScript or CSS entry, Encore and service config |
| `make:data-provider` | `Resources/skeleton/data_provider/DataProvider.tpl.php` | Optional service config |

The package-aware controller, validator, and voter overrides use upstream
MakerBundle class-data generation and therefore have no local skeleton.

When a row changes, update the maker, `Resources/config/services.php`, this
matrix, and the focused generated-output test in the same commit.
