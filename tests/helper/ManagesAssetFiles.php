<?php

declare(strict_types=1);

namespace Tests\Unit\helper;

/**
 * Deterministic, non-destructive management of the (git-ignored, build-only)
 * asset files that source guards check via file_exists().
 *
 * Tests can force an asset to be present (creating only missing files) or
 * absent (moving existing files aside), then restore the original state in
 * tearDown so a developer's built assets are never destroyed.
 */
trait ManagesAssetFiles
{
    /**
     * @var string[] Asset files created by a test, to be removed on cleanup
     */
    private $createdAssetFiles = [];

    /**
     * @var string[] Asset files moved aside by a test, to be restored on cleanup
     */
    private $movedAssetFiles = [];

    /**
     * Ensure the given asset files exist on disk (creating only missing ones).
     *
     * @param string[] $paths Absolute asset paths
     */
    private function makeAssetsAvailable(array $paths): void
    {
        foreach ($paths as $path) {
            if (\file_exists($path)) {
                continue;
            }

            if (!\is_dir(\dirname($path))) {
                \mkdir(\dirname($path), 0755, true);
            }

            \file_put_contents($path, '');
            $this->createdAssetFiles[] = $path;
        }
    }

    /**
     * Ensure the given asset files are absent (moving any existing ones aside).
     *
     * @param string[] $paths Absolute asset paths
     */
    private function makeAssetsUnavailable(array $paths): void
    {
        foreach ($paths as $path) {
            if (!\file_exists($path)) {
                continue;
            }

            \rename($path, $path . '.epbak');
            $this->movedAssetFiles[] = $path;
        }
    }

    /**
     * Restore the filesystem: remove created files and move aside files back.
     */
    private function restoreAssetFiles(): void
    {
        foreach ($this->createdAssetFiles as $path) {
            if (\file_exists($path)) {
                \unlink($path);
            }
        }

        foreach ($this->movedAssetFiles as $path) {
            if (\file_exists($path . '.epbak')) {
                \rename($path . '.epbak', $path);
            }
        }

        $this->createdAssetFiles = [];
        $this->movedAssetFiles = [];
    }
}
