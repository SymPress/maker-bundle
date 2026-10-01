<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $namespace; ?>;

<?php if ($localizable) { ?>
use SymPress\WordPress\Contracts\Gutenberg\LocalizableBlockInterface;
<?php } else { ?>
use SymPress\WordPress\Contracts\Gutenberg\BlockInterface;
<?php } ?>

final class <?= $class_name; ?> implements <?= $localizable ? 'LocalizableBlockInterface' : 'BlockInterface'; ?>

{
<?php if ($localizable) { ?>
    public const JS_CONFIG_VAR = <?= var_export($js_config_var, true); ?>;

<?php } ?>
    public function name(): string
    {
        return <?= var_export($block_name, true); ?>;
    }

    /**
     * @return array<string, mixed>
     */
    public function args(): array
    {
        return [
            'api_version' => 2,
            'title' => __(<?= var_export($title, true); ?>, <?= var_export($text_domain, true); ?>),
            'description' => __(<?= var_export($description, true); ?>, <?= var_export($text_domain, true); ?>),
            'category' => <?= var_export($category, true); ?>,
            'icon' => <?= var_export($icon, true); ?>,
            'supports' => [
                'html' => false,
            ],
            'attributes' => [],
            'editor_script' => <?= var_export($editor_handle, true); ?>,
<?php if ($with_frontend) { ?>
            'script' => <?= var_export($frontend_handle, true); ?>,
<?php } ?>
            'render_callback' => [$this, 'render'],
        ];
    }

<?php if ($localizable) { ?>
    /**
     * @return array<string, mixed>
     */
    public function localize(): array
    {
        return [];
    }

<?php } ?>
    /**
     * @param array<string, mixed> $attributes
     */
    public function render(array $attributes): string
    {
<?php if ($with_view) { ?>
        $template = __DIR__ . <?= var_export('/' . $view_path, true); ?>;

        if (!is_file($template)) {
            return '';
        }

        ob_start();
        extract($attributes, EXTR_SKIP);
        require $template;

        return (string) ob_get_clean();
<?php } else { ?>
        return <?= var_export('<div data-block="' . htmlspecialchars($block_name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"></div>', true); ?>;
<?php } ?>
    }
}
