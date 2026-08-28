<?php

declare(strict_types=1);

namespace Silex\Web\Http\Action;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Silex\Web\Documentation\DocumentRepository;
use Twig\Environment;

final readonly class PackagesAction
{
    public function __construct(
        private Environment $twig,
        private DocumentRepository $documents,
    ) {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $locale = (string) $request->getAttribute('locale');
        $alternateLocale = $locale === 'fr' ? 'en' : 'fr';
        $response->getBody()->write($this->twig->render('packages.twig', [
            'locale' => $locale,
            'alternate_locale' => $alternateLocale,
            'alternate_label' => $locale === 'fr' ? 'English' : 'Français',
            'alternate_path' => '/' . $alternateLocale . '/packages',
            'packages' => $this->documents->packages($locale),
        ]));

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
}
