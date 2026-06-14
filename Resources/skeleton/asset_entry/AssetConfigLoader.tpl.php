<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $namespace; ?>;

use SymPress\Assets\Asset;

final class <?= $class_name; ?> implements ConfigLoaderInterface
{
    public const HANDLE = '<?= $asset_handle; ?>';

    public function accepts(Asset $asset): bool
    {
        return $asset->handle() === self::HANDLE;
    }

    public function process(Asset $asset): Asset
    {
        return $asset->forLocation(<?= $asset_location; ?>);
    }
}
