<?php

declare(strict_types=1);

namespace Silex\Web\Documentation;

use RuntimeException;

final readonly class DocumentRepository
{
    public function __construct(private string $contentRoot)
    {
    }

    public function overview(string $locale): string
    {
        if (!in_array($locale, ['en', 'fr'], true)) {
            throw new RuntimeException('Unsupported documentation locale.');
        }

        $path = $this->contentRoot . '/' . $locale . '/overview.md';
        $markdown = file_get_contents($path);

        if ($markdown === false) {
            throw new RuntimeException(sprintf('Unable to read documentation file "%s".', $path));
        }

        return $markdown;
    }
}
