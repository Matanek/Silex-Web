<?php

declare(strict_types=1);

namespace Silex\Web;

use Silex\Web\Documentation\DocumentRepository;
use Silex\Web\Ecosystem\SilexVersionResolver;
use Silex\Web\Http\Action\DocumentationAction;
use Silex\Web\Http\Action\HomeAction;
use Silex\Web\Http\Action\LocaleRedirectAction;
use Silex\Web\Http\Action\PackageDocumentationAction;
use Silex\Web\Http\Action\PackagesAction;
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
        $workspaceRoot = dirname($root);
        $twig = new Environment(
            new FilesystemLoader($root . '/templates'),
            [
                'autoescape' => 'html',
                'cache' => false,
                'strict_variables' => true,
            ],
        );
        $twig->addGlobal('release', self::releaseIdentifier($root));
        $twig->addGlobal('silex_version', SilexVersionResolver::resolve($root, $workspaceRoot));

        $documents = new DocumentRepository(
            $root . '/content',
            self::sourceRoot('SILEX_DOCS_ROOT', $workspaceRoot . '/Silex/Docs'),
            self::sourceRoot('SILEX_PACKAGES_ROOT', $workspaceRoot . '/Packages'),
            self::sourceRoot('SILEX_REGISTRY_ROOT', $workspaceRoot . '/Silex-Registry'),
        );
        $markdown = new MarkdownRenderer();
        $languages = new LanguageNegotiator(self::SUPPORTED_LOCALES, 'en');

        $app = SlimAppFactory::create();
        $app->get('/', new LocaleRedirectAction($languages));

        $app->group('/{locale:en|fr}', function (RouteCollectorProxy $group) use ($twig, $documents, $markdown): void {
            $home = new HomeAction($twig, $documents);
            $documentation = new DocumentationAction($twig, $documents, $markdown);
            $packages = new PackagesAction($twig, $documents);
            $packageDocumentation = new PackageDocumentationAction($twig, $documents, $markdown);
            $registry = new RegistryAction($twig);

            $group->get('', $home);
            $group->get('/', $home);
            $group->get('/docs', $documentation);
            $group->get('/docs/', $documentation);
            $group->get('/docs/{document:.+}', $documentation);
            $group->get('/packages', $packages);
            $group->get('/packages/', $packages);
            $group->get('/packages/{package:[A-Za-z_][A-Za-z0-9_.]*}', $packageDocumentation);
            $group->get('/packages/{package:[A-Za-z_][A-Za-z0-9_.]*}/docs/{document:.+}', $packageDocumentation);
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

    private static function sourceRoot(string $environmentName, string $fallback): string
    {
        $configured = trim((string) getenv($environmentName));

        return $configured !== '' ? $configured : $fallback;
    }
}
