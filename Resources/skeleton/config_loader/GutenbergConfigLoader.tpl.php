<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $namespace; ?>;

use SymPress\Assets\Asset;
use SymPress\Assets\Script;
use SymPress\WordPress\Contracts\Gutenberg\LocalizableBlockInterface;

final class <?= $class_name; ?> implements ConfigLoaderInterface

{
    public const EDITOR = '<?= $editor_handle; ?>';

    /** @var list<LocalizableBlockInterface> */
    private array $blocks = [];

    /**
     * @param iterable<LocalizableBlockInterface> $blocks
     */
    public function __construct(iterable $blocks = [])
    {
        foreach ($blocks as $block) {
            if (!$block instanceof LocalizableBlockInterface) {
                continue;
            }

            $this->blocks[] = $block;
        }
    }

    public function accepts(Asset $asset): bool
    {
        return $asset->handle() === self::EDITOR;
    }

    public function process(Asset $asset): Asset
    {
        $localization = [];

        foreach ($this->blocks as $block) {
            $localization[$block::JS_CONFIG_VAR] = $block->localize();
        }

        if ($asset instanceof Script) {
            $asset
                ->withLocalize('<?= $localize_var; ?>', $localization)
                ->withDependencies('wp-blocks');
        }

        return $asset->forLocation(Asset::BLOCK_EDITOR_ASSETS);
    }
}
