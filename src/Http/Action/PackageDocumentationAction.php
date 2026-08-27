<?php

declare(strict_types=1);

namespace Silex\Web\Http\Action;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Silex\Web\Documentation\DocumentRepository;
use Silex\Web\Rendering\MarkdownRenderer;
use Twig\Environment;

final readonly class PackageDocumentationAction
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
        $name = (string) $request->getAttribute('package');
        $path = (string) ($request->getAttribute('document') ?? 'README.md');
        $package = $this->documents->package($name);
        $document = $this->documents->packageDocument($name, $path);
        if ($package === null || $document === null) {
            $response->getBody()->write('Package documentation page not found.');
            return $response->withStatus(404)->withHeader('Content-Type', 'text/plain; charset=utf-8');
        }

        $routeBase = '/' . $locale . '/packages/' . rawurlencode($name) . '/docs';
        $sourceBase = $package['repository'] === null ? '' : $package['repository'] . '/blob/main';
        $documentation = $this->markdown->toHtml($document['markdown'], $document['path'], $routeBase, $sourceBase);
        $alternateLocale = $locale === 'fr' ? 'en' : 'fr';
        $suffix = $document['path'] === 'README.md' ? '' : '/docs/' . $document['path'];

        $response->getBody()->write($this->twig->render('package-documentation.twig', [
            'locale' => $locale,
            'alternate_locale' => $alternateLocale,
            'alternate_label' => $locale === 'fr' ? 'English' : 'Français',
            'alternate_path' => '/' . $alternateLocale . '/packages/' . rawurlencode($name) . $suffix,
            'package' => $package,
            'current_path' => $document['path'],
            'document_title' => $document['title'],
            'navigation' => $this->documents->packageNavigation($name),
            'source_url' => $document['source_url'],
            'documentation_html' => $documentation,
        ]));

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
}
