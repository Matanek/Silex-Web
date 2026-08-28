<?php

declare(strict_types=1);

namespace Silex\Web\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Routing\RouteContext;
use UnexpectedValueException;

final readonly class LocaleMiddleware implements MiddlewareInterface
{
    /** @param list<string> $supportedLocales */
    public function __construct(private array $supportedLocales)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $locale = RouteContext::fromRequest($request)->getRoute()?->getArgument('locale');
        if ($locale === null || !in_array($locale, $this->supportedLocales, true)) {
            throw new UnexpectedValueException('The route did not provide a supported locale.');
        }

        $response = $handler->handle($request->withAttribute('locale', $locale));
        $cookie = sprintf(
            'silex_locale=%s; Path=/; Max-Age=31536000; SameSite=Lax; HttpOnly%s',
            rawurlencode($locale),
            $request->getUri()->getScheme() === 'https' ? '; Secure' : '',
        );

        return $response->withAddedHeader('Set-Cookie', $cookie);
    }
}
