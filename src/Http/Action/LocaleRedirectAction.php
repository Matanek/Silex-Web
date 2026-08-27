<?php

declare(strict_types=1);

namespace Silex\Web\Http\Action;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Silex\Web\Http\LanguageNegotiator;

final readonly class LocaleRedirectAction
{
    public function __construct(private LanguageNegotiator $languages)
    {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $locale = $this->languages->resolve(
            $request->getCookieParams()['silex_locale'] ?? null,
            $request->getHeaderLine('Accept-Language'),
        );

        return $response
            ->withHeader('Location', '/' . $locale . '/')
            ->withStatus(302);
    }
}
