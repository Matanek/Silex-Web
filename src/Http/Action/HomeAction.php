<?php

declare(strict_types=1);

namespace Silex\Web\Http\Action;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Twig\Environment;

final readonly class HomeAction
{
    public function __construct(private Environment $twig)
    {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $locale = (string) $request->getAttribute('locale');
        $response->getBody()->write($this->twig->render('home.html.twig', [
            'locale' => $locale,
            'alternate_locale' => $locale === 'fr' ? 'en' : 'fr',
            'alternate_label' => $locale === 'fr' ? 'English' : 'Français',
            'alternate_path' => '/' . ($locale === 'fr' ? 'en' : 'fr') . '/',
        ]));

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
}
