<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $namespace; ?>;

final class <?= $class_name; ?>

{
<?php if ($type === 'filter') { ?>
    public function <?= $method_name; ?>(mixed $value): mixed
    {
        return $value;
    }
<?php } else { ?>
    public function <?= $method_name; ?>(): void
    {
    }
<?php } ?>
}
