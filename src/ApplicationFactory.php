<?php

declare(strict_types=1);

namespace Silex\Web;

use Silex\Web\Documentation\DocumentRepository;
use Silex\Web\Http\Action\DocumentationAction;
use Silex\Web\Http\Action\HomeAction;
use Silex\Web\Http\Action\LocaleRedirectAction;
use Silex\Web\Http\Action\RegistryAction;
use Silex\Web\Http\LanguageNegotiator;
use Silex\Web\Http\Middleware\LocaleMiddleware;
use Silex\Web\Rendering\MarkdownRenderer;
use Slim\App;
use Slim\Factory\AppFactory as SlimAppFactory;
use Slim\Routing\RouteCollectorProxy;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class ApplicationFactory
{
    private const SUPPORTED_LOCALES = ['en', 'fr'];

    public static function create(string $root): App
    {
        $twig = new Environment(
            new FilesystemLoader($root . '/templates'),
            [
                'autoescape' => 'html',
                'cache' => false,
                'strict_variables' => true,
            ],
        );
        $twig->addGlobal('release', self::releaseIdentifier($root));

        $documents = new DocumentRepository($root . '/content');
        $markdown = new MarkdownRenderer();
        $languages = new LanguageNegotiator(self::SUPPORTED_LOCALES, 'en');

        $app = SlimAppFactory::create();
        $app->get('/', new LocaleRedirectAction($languages));

        $app->group('/{locale:en|fr}', function (RouteCollectorProxy $group) use ($twig, $documents, $markdown): void {
            $home = new HomeAction($twig);
            $documentation = new DocumentationAction($twig, $documents, $markdown);
            $registry = new RegistryAction($twig);

            $group->get('', $home);
            $group->get('/', $home);
            $group->get('/docs', $documentation);
            $group->get('/docs/', $documentation);
            $group->get('/registry', $registry);
            $group->get('/registry/', $registry);
        })->add(new LocaleMiddleware(self::SUPPORTED_LOCALES));

        $app->addRoutingMiddleware();
        $app->addErrorMiddleware(self::debugEnabled(), true, true);

        return $app;
    }

    private static function debugEnabled(): bool
    {
        return filter_var(getenv('SILEX_WEB_DEBUG') ?: false, FILTER_VALIDATE_BOOL);
    }

    private static function releaseIdentifier(string $root): string
    {
        $path = $root . '/release.txt';

        if (!is_file($path)) {
            return 'development';
        }

        $release = file_get_contents($path);

        if ($release === false || trim($release) === '') {
            return 'development';
        }

        return trim($release);
    }
}
