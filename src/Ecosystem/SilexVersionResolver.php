<?php

declare(strict_types=1);

namespace Silex\Web\Ecosystem;

final class SilexVersionResolver
{
    public static function resolve(string $root, string $workspaceRoot): string
    {
        $environmentVersion = trim((string) getenv('SILEX_VERSION'));
        if (self::valid($environmentVersion)) {
            return $environmentVersion;
        }

        $publishedVersion = self::readVersionFile($root . '/var/content/silex-version.txt');
        if ($publishedVersion !== null) {
            return $publishedVersion;
        }

        $manifest = self::readFile($workspaceRoot . '/Silex/Toolchain/build.zig.zon');
        if ($manifest !== null && preg_match('/\.version\s*=\s*"([^"]+)"/', $manifest, $match) === 1 && self::valid($match[1])) {
            return $match[1];
        }

        return 'development';
    }

    private static function readVersionFile(string $path): ?string
    {
        $version = self::readFile($path);
        if ($version === null) {
            return null;
        }

        $version = trim($version);

        return self::valid($version) ? $version : null;
    }

    private static function readFile(string $path): ?string
    {
        if (!is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        return $contents === false ? null : $contents;
    }

    private static function valid(string $version): bool
    {
        return preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $version) === 1;
    }
}
