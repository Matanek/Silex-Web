<?php

declare(strict_types=1);

namespace Silex\Web;

use Silex\Web\Documentation\DocumentRepository;
use Silex\Web\Ecosystem\SilexVersionResolver;
use Silex\Web\Http\Action\DocumentationAction;
use Silex\Web\Http\Action\HomeAction;
use Silex\Web\Http\Action\PackageDocumentationAction;
use Silex\Web\Http\Action\PackagesAction;
use Silex\Web\Http\Action\RegistryAction;
use Silex\Web\Rendering\MarkdownRenderer;
use Slim\App;
use Slim\Factory\AppFactory as SlimAppFactory;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class ApplicationFactory
{
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

        $app = SlimAppFactory::create();
        $home = new HomeAction($twig, $documents);
        $documentation = new DocumentationAction($twig, $documents, $markdown);
        $packages = new PackagesAction($twig, $documents);
        $packageDocumentation = new PackageDocumentationAction($twig, $documents, $markdown);
        $registry = new RegistryAction($twig);

        $app->get('/', $home);
        $app->get('/docs', $documentation);
        $app->get('/docs/', $documentation);
        $app->get('/docs/{document:.+}', $documentation);
        $app->get('/packages', $packages);
        $app->get('/packages/', $packages);
        $app->get('/packages/{package:[A-Za-z_][A-Za-z0-9_.]*}', $packageDocumentation);
        $app->get('/packages/{package:[A-Za-z_][A-Za-z0-9_.]*}/docs/{document:.+}', $packageDocumentation);
        $app->get('/registry', $registry);
        $app->get('/registry/', $registry);

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
