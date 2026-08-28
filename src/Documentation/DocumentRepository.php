<?php

declare(strict_types=1);

namespace Silex\Web\Documentation;

use JsonException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

final readonly class DocumentRepository
{
    public function __construct(
        private string $fallbackContentRoot,
        private string $silexDocsRoot,
        private string $packagesRoot,
        private string $registryRoot,
    ) {
    }

    /** @return array{path: string, title: string, markdown: string, source_url: string}|null */
    public function languageDocument(string $path): ?array
    {
        if (!is_dir($this->silexDocsRoot)) {
            if ($path !== '' && $path !== 'README.md') {
                return null;
            }
            $markdown = $this->readFile($this->fallbackContentRoot . '/overview.md');

            return [
                'path' => 'README.md',
                'title' => $this->extractTitle($markdown, 'Silex documentation'),
                'markdown' => $markdown,
                'source_url' => 'https://github.com/Matanek/Silex/tree/main/Docs',
            ];
        }

        $document = $this->readMarkdown($this->silexDocsRoot, $path);
        if ($document === null) {
            return null;
        }

        return $document + [
            'source_url' => 'https://github.com/Matanek/Silex/blob/main/Docs/' . $document['path'],
        ];
    }

    /** @return list<array{label: string, items: list<array{path: string, label: string}>}> */
    public function languageNavigation(): array
    {
        if (!is_dir($this->silexDocsRoot)) {
            return [['label' => 'Documentation', 'items' => [['path' => 'README.md', 'label' => 'Overview']]]];
        }

        $groups = [];
        foreach ($this->markdownFiles($this->silexDocsRoot) as $path) {
            $parts = explode('/', $path);
            $group = match (true) {
                count($parts) === 1 => 'Overview',
                $parts[0] === 'Language' && count($parts) >= 3 => $this->humanize($parts[1]),
                $parts[0] === 'Language' => 'Language',
                default => $this->humanize($parts[0]),
            };

            $groups[$group][] = [
                'path' => $path,
                'label' => $this->documentLabel($path),
            ];
        }

        $priority = ['Overview', 'Language', 'Getting started', 'Functions', 'Modules', 'Data types', 'Collections', 'Ownership', 'Reference'];
        uksort($groups, static function (string $left, string $right) use ($priority): int {
            $leftIndex = array_search($left, $priority, true);
            $rightIndex = array_search($right, $priority, true);
            $leftRank = $leftIndex === false ? PHP_INT_MAX : $leftIndex;
            $rightRank = $rightIndex === false ? PHP_INT_MAX : $rightIndex;

            return $leftRank <=> $rightRank ?: strcasecmp($left, $right);
        });

        $navigation = [];
        foreach ($groups as $label => $items) {
            usort($items, static function (array $left, array $right): int {
                if ($left['path'] === 'README.md') {
                    return -1;
                }
                if ($right['path'] === 'README.md') {
                    return 1;
                }

                return strcasecmp($left['label'], $right['label']);
            });
            $navigation[] = ['label' => $label, 'items' => $items];
        }

        return $navigation;
    }

    /** @return list<array{name: string, version: string, description: string, repository: ?string, document_count: int}> */
    public function packages(): array
    {
        if (!is_dir($this->packagesRoot)) {
            return [];
        }

        $directories = glob($this->packagesRoot . '/*', GLOB_ONLYDIR);
        if ($directories === false) {
            return [];
        }

        $packages = [];
        foreach ($directories as $directory) {
            $manifestPath = $directory . '/Package.json';
            if (!is_file($manifestPath)) {
                continue;
            }

            $manifest = $this->decodeManifest($manifestPath);
            $name = $manifest['name'] ?? null;
            if (!is_string($name) || !$this->validPackageName($name)) {
                throw new RuntimeException(sprintf('Package manifest "%s" has an invalid name.', $manifestPath));
            }

            $packages[] = [
                'name' => $name,
                'version' => is_string($manifest['version'] ?? null) ? $manifest['version'] : '',
                'description' => is_string($manifest['description'] ?? null) ? $manifest['description'] : '',
                'repository' => $this->packageRepository($name),
                'document_count' => count($this->packageMarkdownFiles($directory)),
            ];
        }

        usort($packages, static fn (array $left, array $right): int => strcasecmp($left['name'], $right['name']));

        return $packages;
    }

    /** @return array{name: string, version: string, description: string, repository: ?string, dependencies: list<string>, document_count: int}|null */
    public function package(string $name): ?array
    {
        if (!$this->validPackageName($name)) {
            return null;
        }

        $root = $this->packagesRoot . '/' . $name;
        $manifestPath = $root . '/Package.json';
        if (!is_file($manifestPath)) {
            return null;
        }

        $manifest = $this->decodeManifest($manifestPath);
        if (($manifest['name'] ?? null) !== $name) {
            return null;
        }

        $dependencies = array_keys(is_array($manifest['dependencies'] ?? null) ? $manifest['dependencies'] : []);
        sort($dependencies, SORT_NATURAL | SORT_FLAG_CASE);

        return [
            'name' => $name,
            'version' => is_string($manifest['version'] ?? null) ? $manifest['version'] : '',
            'description' => is_string($manifest['description'] ?? null) ? $manifest['description'] : '',
            'repository' => $this->packageRepository($name),
            'dependencies' => $dependencies,
            'document_count' => count($this->packageMarkdownFiles($root)),
        ];
    }

    /** @return array{path: string, title: string, markdown: string, source_url: ?string}|null */
    public function packageDocument(string $name, string $path): ?array
    {
        $package = $this->package($name);
        if ($package === null) {
            return null;
        }

        $document = $this->readMarkdown($this->packagesRoot . '/' . $name, $path);
        if ($document === null || ($document['path'] !== 'README.md' && !str_starts_with($document['path'], 'Docs/'))) {
            return null;
        }

        $sourceUrl = $package['repository'];
        if ($sourceUrl !== null) {
            $sourceUrl .= '/blob/main/' . $document['path'];
        }

        return $document + ['source_url' => $sourceUrl];
    }

    /** @return list<array{label: string, items: list<array{path: string, label: string}>}> */
    public function packageNavigation(string $name): array
    {
        if (!$this->validPackageName($name)) {
            return [];
        }

        $root = $this->packagesRoot . '/' . $name;
        $files = $this->packageMarkdownFiles($root);
        if ($files === []) {
            return [];
        }

        $overview = [];
        $guides = [];
        foreach ($files as $path) {
            $item = ['path' => $path, 'label' => $this->documentLabel($path)];
            if ($path === 'README.md') {
                $overview[] = $item;
            } else {
                $guides[] = $item;
            }
        }

        usort($guides, static fn (array $left, array $right): int => strcasecmp($left['label'], $right['label']));
        $navigation = [];
        if ($overview !== []) {
            $navigation[] = ['label' => 'Package', 'items' => $overview];
        }
        if ($guides !== []) {
            $navigation[] = ['label' => 'Guides', 'items' => $guides];
        }

        return $navigation;
    }

    /** @return array{path: string, title: string, markdown: string}|null */
    private function readMarkdown(string $root, string $path): ?array
    {
        $path = $path === '' ? 'README.md' : rawurldecode($path);
        if (!str_ends_with(strtolower($path), '.md')) {
            $path .= '.md';
        }
        if (!$this->validDocumentPath($path)) {
            return null;
        }

        $rootPath = realpath($root);
        $filePath = realpath($root . '/' . $path);
        if ($rootPath === false || $filePath === false || !str_starts_with($filePath, $rootPath . DIRECTORY_SEPARATOR)) {
            return null;
        }
        if (!is_file($filePath)) {
            return null;
        }

        $markdown = $this->readFile($filePath);

        return [
            'path' => str_replace(DIRECTORY_SEPARATOR, '/', substr($filePath, strlen($rootPath) + 1)),
            'title' => $this->extractTitle($markdown, $this->documentLabel($path)),
            'markdown' => $markdown,
        ];
    }

    /** @return list<string> */
    private function markdownFiles(string $root): array
    {
        if (!is_dir($root)) {
            return [];
        }

        $rootPath = realpath($root);
        if ($rootPath === false) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($rootPath, RecursiveDirectoryIterator::SKIP_DOTS));
        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'md') {
                $files[] = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($rootPath) + 1));
            }
        }
        sort($files, SORT_NATURAL | SORT_FLAG_CASE);

        return $files;
    }

    /** @return list<string> */
    private function packageMarkdownFiles(string $root): array
    {
        $files = [];
        if (is_file($root . '/README.md')) {
            $files[] = 'README.md';
        }
        foreach ($this->markdownFiles($root . '/Docs') as $path) {
            $files[] = 'Docs/' . $path;
        }

        return $files;
    }

    /** @return array<string, mixed> */
    private function decodeManifest(string $path): array
    {
        try {
            $manifest = json_decode($this->readFile($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new RuntimeException(sprintf('Package manifest "%s" is invalid: %s', $path, $error->getMessage()), 0, $error);
        }

        if (!is_array($manifest)) {
            throw new RuntimeException(sprintf('Package manifest "%s" must contain an object.', $path));
        }

        return $manifest;
    }

    private function packageRepository(string $name): ?string
    {
        $path = $this->registryRoot . '/registry/v1/packages/' . $name . '.json';
        if (!is_file($path)) {
            return null;
        }

        try {
            $registration = json_decode($this->readFile($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }
        $repository = is_array($registration) ? ($registration['repository'] ?? null) : null;

        return is_string($repository) ? preg_replace('/\.git$/', '', $repository) : null;
    }

    private function readFile(string $path): string
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException(sprintf('Unable to read documentation file "%s".', $path));
        }

        return $contents;
    }

    private function extractTitle(string $markdown, string $fallback): string
    {
        return preg_match('/^#\s+(.+)$/m', $markdown, $match) === 1 ? trim($match[1]) : $fallback;
    }

    private function documentLabel(string $path): string
    {
        $filename = pathinfo($path, PATHINFO_FILENAME);
        if (strcasecmp($filename, 'README') === 0) {
            $parent = basename(dirname($path));
            return $parent === '.' || strcasecmp($parent, 'Language') === 0 || strcasecmp($parent, 'Docs') === 0
                ? 'Overview'
                : $this->humanize($parent);
        }

        return $this->humanize($filename);
    }

    private function humanize(string $value): string
    {
        return ucfirst(str_replace(['-', '_'], ' ', $value));
    }

    private function validPackageName(string $name): bool
    {
        return preg_match('/^[A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)*$/', $name) === 1;
    }

    private function validDocumentPath(string $path): bool
    {
        return !str_contains($path, '..')
            && !str_starts_with($path, '/')
            && preg_match('/^[A-Za-z0-9][A-Za-z0-9._\/-]*\.md$/i', $path) === 1;
    }
}
