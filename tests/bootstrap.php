<?php

declare(strict_types=1);

$autoloads = [
    dirname(__DIR__) . '/vendor/autoload.php',
    dirname(__DIR__, 3) . '/vendor/autoload.php',
];

foreach ($autoloads as $autoload) {
    if (is_readable($autoload)) {
        require $autoload;

        return;
    }
}

fwrite(STDERR, "Composer autoload file not found. Run composer install first.\n");
exit(1);
