<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $namespace; ?>;

use SymPress\Assets\Asset;

interface <?= $class_name; ?>
{
    public function accepts(Asset $asset): bool;

    public function process(Asset $asset): Asset;
}
