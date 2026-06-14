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
    public const JS_CONFIG_VAR = '<?= $js_config_var; ?>';

<?php } ?>
    public function name(): string
    {
        return '<?= $block_name; ?>';
    }

    /**
     * @return array<string, mixed>
     */
    public function args(): array
    {
        return [
            'api_version' => 2,
            'title' => __('<?= $title; ?>', '<?= $text_domain; ?>'),
            'description' => __('<?= $description; ?>', '<?= $text_domain; ?>'),
            'category' => '<?= $category; ?>',
            'icon' => '<?= $icon; ?>',
            'supports' => [
                'html' => false,
            ],
            'attributes' => [],
            'editor_script' => '<?= $editor_handle; ?>',
<?php if ($with_frontend) { ?>
            'script' => '<?= $frontend_handle; ?>',
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
        $template = __DIR__ . '/<?= $view_path; ?>';

        if (!is_file($template)) {
            return '';
        }

        ob_start();
        extract($attributes, EXTR_SKIP);
        require $template;

        return (string) ob_get_clean();
<?php } else { ?>
        return '<div data-block="<?= $block_name; ?>"></div>';
<?php } ?>
    }
}
