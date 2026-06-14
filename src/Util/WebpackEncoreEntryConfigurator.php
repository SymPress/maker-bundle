<?php

declare(strict_types=1);

namespace SymPress\MakerBundle\Util;

final class WebpackEncoreEntryConfigurator
{
    public function entry(
        PackageContext $context,
        string $handle,
        string $sourcePath,
        bool $style,
    ): ?FileUpdate {

        $file = sprintf('%s/webpack.config.js', $context->packagePath);
        $contents = is_file($file) ? file_get_contents($file) : null;
        $contents = is_string($contents) && $contents !== ''
            ? $contents
            : $this->defaultWebpackConfig();
        $method = $style ? 'addStyleEntry' : 'addEntry';

        if (
            str_contains($contents, sprintf(".addEntry('%s'", $handle))
            || str_contains($contents, sprintf('.addEntry("%s"', $handle))
            || str_contains($contents, sprintf(".addStyleEntry('%s'", $handle))
            || str_contains($contents, sprintf('.addStyleEntry("%s"', $handle))
        ) {
            return null;
        }

        $line = sprintf("    .%s('%s', '%s')", $method, $handle, $sourcePath);
        $updated = $this->insertEntry($contents, $line);

        return new FileUpdate($context->relativePath($file), $updated);
    }

    private function insertEntry(string $contents, string $line): string
    {
        $matches = [];

        if (preg_match_all('/^([ \t]*)\\.add(?:Style)?Entry\\([^\\n]+$/m', $contents, $matches, \PREG_OFFSET_CAPTURE)) {
            $last = end($matches[0]);

            if (is_array($last)) {
                $position = $last[1] + strlen($last[0]);

                return substr($contents, 0, $position) . "\n" . $line . substr($contents, $position);
            }
        }

        $position = strpos($contents, "\n    .enable");

        if ($position !== false) {
            return substr($contents, 0, $position) . "\n" . $line . substr($contents, $position);
        }

        $position = strpos($contents, "\nmodule.exports");

        if ($position !== false) {
            return substr($contents, 0, $position) . "\n" . $line . substr($contents, $position);
        }

        return rtrim($contents) . "\n" . $line . "\n";
    }

    private function defaultWebpackConfig(): string
    {
        return <<<'JS'
const Encore = require('@symfony/webpack-encore');
const DependencyExtractionWebpackPlugin = require('@wordpress/dependency-extraction-webpack-plugin');

Encore
    .setOutputPath('assets/')
    .setPublicPath('./')
    .setManifestKeyPrefix('./')
    .enableSassLoader()
    .enableTypeScriptLoader((options) => {
        options.transpileOnly = true;
    })
    .enableSourceMaps(!Encore.isProduction())
    .enablePostCssLoader()
    .addPlugin(new DependencyExtractionWebpackPlugin({
        outputFormat: 'json',
    }))
    .cleanupOutputBeforeBuild(!Encore.isProduction ? ['*.js', '*.css'] : [])
    .disableSingleRuntimeChunk();

module.exports = Encore.getWebpackConfig();
JS . "\n";
    }
}
