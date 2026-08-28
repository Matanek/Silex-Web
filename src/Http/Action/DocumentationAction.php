<?php

declare(strict_types=1);

namespace Silex\Web\Http\Action;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Silex\Web\Documentation\DocumentRepository;
use Silex\Web\Rendering\MarkdownRenderer;
use Twig\Environment;

final readonly class DocumentationAction
{
    public function __construct(
        private Environment $twig,
        private DocumentRepository $documents,
        private MarkdownRenderer $markdown,
    ) {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $path = (string) ($request->getAttribute('document') ?? 'README.md');
        $document = $this->documents->languageDocument($path);
        if ($document === null) {
            $response->getBody()->write('Documentation page not found.');
            return $response->withStatus(404)->withHeader('Content-Type', 'text/plain; charset=utf-8');
        }

        $documentation = $this->markdown->toHtml(
            $document['markdown'],
            $document['path'],
            '/docs',
            'https://github.com/Matanek/Silex/blob/main/Docs',
        );

        $response->getBody()->write($this->twig->render('documentation.twig', [
            'current_path' => $document['path'],
            'document_title' => $document['title'],
            'navigation' => $this->documents->languageNavigation(),
            'packages' => $this->documents->packages(),
            'source_url' => $document['source_url'],
            'documentation_html' => $documentation,
        ]));

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
}
