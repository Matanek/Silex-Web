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
        $locale = (string) $request->getAttribute('locale');
        $path = (string) ($request->getAttribute('document') ?? 'README.md');
        $document = $this->documents->languageDocument($locale, $path);
        if ($document === null) {
            $response->getBody()->write($locale === 'fr' ? 'Page de documentation introuvable.' : 'Documentation page not found.');
            return $response->withStatus(404)->withHeader('Content-Type', 'text/plain; charset=utf-8');
        }

        $sourceBase = substr($document['source_url'], 0, -strlen('/' . $document['path']));
        $documentation = $this->markdown->toHtml(
            $document['markdown'],
            $document['path'],
            '/' . $locale . '/docs',
            $sourceBase,
        );
        $alternateLocale = $locale === 'fr' ? 'en' : 'fr';
        $suffix = $document['path'] === 'README.md' ? '' : '/' . $document['path'];

        $response->getBody()->write($this->twig->render('documentation.twig', [
            'locale' => $locale,
            'alternate_locale' => $alternateLocale,
            'alternate_label' => $locale === 'fr' ? 'English' : 'Français',
            'alternate_path' => '/' . $alternateLocale . '/docs' . $suffix,
            'current_path' => $document['path'],
            'document_title' => $document['title'],
            'navigation' => $this->documents->languageNavigation($locale),
            'source_url' => $document['source_url'],
            'documentation_html' => $documentation,
        ]));

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
}
