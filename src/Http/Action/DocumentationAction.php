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
        $documentation = $this->markdown->toHtml($this->documents->overview($locale));

        $response->getBody()->write($this->twig->render('documentation.twig', [
            'locale' => $locale,
            'alternate_locale' => $locale === 'fr' ? 'en' : 'fr',
            'alternate_label' => $locale === 'fr' ? 'English' : 'Français',
            'alternate_path' => '/' . ($locale === 'fr' ? 'en' : 'fr') . '/docs',
            'documentation_html' => $documentation,
        ]));

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
}
