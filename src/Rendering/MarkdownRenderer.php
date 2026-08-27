<?php

declare(strict_types=1);

namespace Silex\Web\Rendering;

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

    public function toHtml(string $markdown): string
    {
        return (string) $this->converter->convert($markdown);
    }
}
