<?php

namespace App\Support;

use Illuminate\Support\Str;

class ClientAppDownloads
{
    /** @param  list<string>  $directories */
    public function __construct(
        private readonly array $directories,
        private readonly array $platforms,
    ) {}

    public static function make(): self
    {
        return new self(
            config('client_apps.directories', []),
            config('client_apps.platforms', []),
        );
    }

    /** @return array<string, array<string, mixed>|null> */
    public function all(): array
    {
        $out = [];

        foreach (array_keys($this->platforms) as $platform) {
            $out[$platform] = $this->resolve($platform);
        }

        return $out;
    }

    /** @return array<string, mixed>|null */
    public function resolve(string $platform): ?array
    {
        $config = $this->platforms[$platform] ?? null;

        if (! $config) {
            return null;
        }

        $candidates = [];

        foreach ($this->directories as $directory) {
            if (! is_dir($directory)) {
                continue;
            }

            $stable = $config['stable'] ?? null;
            if ($stable) {
                $path = $directory.DIRECTORY_SEPARATOR.$stable;
                if (is_file($path)) {
                    $candidates[] = $path;
                }
            }

            foreach ($config['patterns'] ?? [] as $pattern) {
                foreach (glob($directory.DIRECTORY_SEPARATOR.$pattern) ?: [] as $path) {
                    if (is_file($path) && ! preg_match('/\.part\d+$/', $path)) {
                        $candidates[] = $path;
                    }
                }
            }
        }

        $candidates = array_values(array_unique($candidates));

        if ($candidates === []) {
            return null;
        }

        usort($candidates, function (string $a, string $b): int {
            $versionCompare = version_compare(
                $this->extractVersion($b) ?? '0.0.0',
                $this->extractVersion($a) ?? '0.0.0',
            );

            if ($versionCompare !== 0) {
                return $versionCompare;
            }

            return filemtime($b) <=> filemtime($a);
        });

        return $this->fileInfo($candidates[0], $config);
    }

    /** @param  array<string, mixed>  $config */
    private function fileInfo(string $path, array $config): array
    {
        $filename = basename($path);
        $version = $this->extractVersion($path);

        return [
            'platform' => $config['label'] ?? Str::title($filename),
            'description' => $config['description'] ?? '',
            'path' => $path,
            'filename' => $filename,
            'version' => $version,
            'size' => filesize($path) ?: 0,
            'updated_at' => filemtime($path) ?: null,
        ];
    }

    private function extractVersion(string $path): ?string
    {
        if (preg_match('/POSMoon(?:-Offline)?-([\d.]+)/', basename($path), $matches)) {
            return $matches[1];
        }

        return null;
    }
}
