<?php

declare(strict_types=1);

namespace Silex\Web\Http\Action;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Twig\Environment;

final readonly class RegistryAction
{
    public function __construct(private Environment $twig)
    {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $locale = (string) $request->getAttribute('locale');
        $alternateLocale = $locale === 'fr' ? 'en' : 'fr';
        $response->getBody()->write($this->twig->render('registry.twig', [
            'locale' => $locale,
            'alternate_locale' => $alternateLocale,
            'alternate_label' => $locale === 'fr' ? 'English' : 'Français',
            'alternate_path' => '/' . $alternateLocale . '/registry',
        ]));

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
}
