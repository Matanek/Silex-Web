<?php

declare(strict_types=1);

namespace Silex\Web\Rendering;

use DOMDocument;
use DOMElement;
use League\CommonMark\GithubFlavoredMarkdownConverter;

final readonly class MarkdownRenderer
{
    private GithubFlavoredMarkdownConverter $converter;

    public function __construct()
    {
        $this->converter = new GithubFlavoredMarkdownConverter([
            'allow_unsafe_links' => false,
            'html_input' => 'strip',
            'max_nesting_level' => 100,
        ]);
    }

    public function toHtml(
        string $markdown,
        string $documentPath = '',
        string $routeBase = '',
        string $sourceBase = '',
    ): string
    {
        $html = (string) $this->converter->convert($markdown);
        if ($documentPath === '' || $routeBase === '') {
            return $html;
        }

        return $this->rewriteDocumentLinks($html, $documentPath, $routeBase, $sourceBase);
    }

    private function rewriteDocumentLinks(string $html, string $documentPath, string $routeBase, string $sourceBase): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="markdown-root">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        foreach ($document->getElementsByTagName('a') as $link) {
            $href = $link->getAttribute('href');
            $resolved = $this->resolveMarkdownHref($href, $documentPath, $routeBase, $sourceBase);
            if ($resolved !== null) {
                $link->setAttribute('href', $resolved);
            }
        }

        $root = $document->getElementById('markdown-root');
        if (!$root instanceof DOMElement) {
            return $html;
        }

        $rewritten = '';
        foreach ($root->childNodes as $child) {
            $rewritten .= $document->saveHTML($child);
        }

        return $rewritten;
    }

    private function resolveMarkdownHref(string $href, string $documentPath, string $routeBase, string $sourceBase): ?string
    {
        if ($href === '' || str_starts_with($href, '#') || str_starts_with($href, '/') || preg_match('/^[a-z][a-z0-9+.-]*:/i', $href) === 1) {
            return null;
        }

        $fragment = '';
        if (str_contains($href, '#')) {
            [$href, $anchor] = explode('#', $href, 2);
            $fragment = '#' . $anchor;
        }
        $directory = dirname($documentPath);
        $candidate = ($directory === '.' ? '' : $directory . '/') . $href;
        $segments = [];
        foreach (explode('/', $candidate) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($segments);
                continue;
            }
            $segments[] = $segment;
        }
        if ($segments === []) {
            return null;
        }

        $encoded = implode('/', array_map('rawurlencode', $segments));

        if (str_ends_with(strtolower($href), '.md')) {
            return rtrim($routeBase, '/') . '/' . $encoded . $fragment;
        }

        return $sourceBase === '' ? null : rtrim($sourceBase, '/') . '/' . $encoded . $fragment;
    }
}
