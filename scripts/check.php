<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface;
use Silex\Web\ApplicationFactory;
use Silex\Web\Documentation\DocumentRepository;
use Silex\Web\Ecosystem\SilexVersionResolver;
use Silex\Web\Rendering\MarkdownRenderer;
use Slim\Psr7\Factory\ServerRequestFactory;

$root = dirname(__DIR__);
putenv('SILEX_DOCUMENTATION_ROOT=' . $root . '/tests/fixtures/Silex-Documentation');
putenv('SILEX_REGISTRY_ROOT=' . $root . '/tests/fixtures/Silex-Registry');
putenv('SILEX_PACKAGES_ROOT=' . $root . '/tests/fixtures/Packages');
putenv('SILEX_VERSION=9.8.7');

$phpFiles = [];
foreach ([$root . '/public', $root . '/scripts', $root . '/src'] as $directory) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $phpFiles[] = $file->getPathname();
        }
    }
}
sort($phpFiles);
foreach ($phpFiles as $file) {
    passthru(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file), $status);
    if ($status !== 0) {
        exit($status);
    }
}

require $root . '/vendor/autoload.php';

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
};

$requestFactory = new ServerRequestFactory();
$handle = static function (string $uri, array $headers = [], array $cookies = []) use ($root, $requestFactory): ResponseInterface {
    $request = $requestFactory->createServerRequest('GET', $uri);
    foreach ($headers as $name => $value) {
        $request = $request->withHeader($name, $value);
    }
    if ($cookies !== []) {
        $request = $request->withCookieParams($cookies);
    }

    return ApplicationFactory::create($root)->handle($request);
};

$frenchRedirect = $handle('https://silex.test/', ['Accept-Language' => 'fr-FR, en;q=0.8']);
$assert($frenchRedirect->getStatusCode() === 302, 'The root must redirect to the negotiated language.');
$assert($frenchRedirect->getHeaderLine('Location') === '/fr/', 'French browser preferences must select /fr/.');

$cookieRedirect = $handle(
    'https://silex.test/',
    ['Accept-Language' => 'fr-FR'],
    ['silex_locale' => 'en'],
);
$assert($cookieRedirect->getHeaderLine('Location') === '/en/', 'The saved language must override browser preferences.');

$home = $handle('https://silex.test/fr/');
$homeBody = (string) $home->getBody();
$releaseFile = $root . '/release.txt';
$expectedRelease = is_file($releaseFile) ? trim((string) file_get_contents($releaseFile)) : 'development';
$assert($home->getStatusCode() === 200, 'The French home page must respond with HTTP 200.');
$assert(str_contains($homeBody, '<html lang="fr">'), 'The French page language is missing.');
$assert(str_contains($homeBody, 'Code moderne'), 'The French home page content is missing.');
$assert(str_contains($homeBody, 'href="/en/"'), 'The home page language switch must preserve the page.');
$assert(str_contains($home->getHeaderLine('Set-Cookie'), 'silex_locale=fr'), 'Localized routes must save the selected language.');
$assert(str_contains($home->getHeaderLine('Set-Cookie'), 'Secure'), 'HTTPS locale cookies must be secure.');
$assert(str_contains($homeBody, 'silex run Main.sx'), 'The canonical quickstart command is missing.');
$assert(str_contains($homeBody, 'id="zed"'), 'The Zed installation section is missing.');
$assert(str_contains($homeBody, 'Installer l’extension Silex pour Zed.'), 'The French Zed installation heading is missing.');
$assert(str_contains($homeBody, 'rustup target add wasm32-wasip2'), 'The Zed WebAssembly target setup is missing.');
$assert(str_contains($homeBody, 'git clone https://github.com/Matanek/Silex-Extension-Zed.git'), 'The Zed extension clone command is missing.');
$assert(str_contains($homeBody, 'zed: install dev extension'), 'The Zed development installation command is missing.');
$assert(str_contains($homeBody, 'href="https://github.com/zed-industries/extensions/pull/7190"'), 'The Zed catalog pull request is missing.');
$assert(str_contains($homeBody, 'v9.8.7'), 'The configured Silex version is missing.');
$assert(str_contains($homeBody, 'data-package-count="2"'), 'The home page package catalog is missing.');
$assert(str_contains($homeBody, 'href="https://github.com/Matanek/Silex-Lib-Example"'), 'Package cards must link to their canonical repository.');
$assert(str_contains($homeBody, 'Présente des métadonnées réutilisables pour un package Silex.'), 'French package cards must display their localized manifest description.');
$assert(str_contains($homeBody, 'Uses one package description for every locale.'), 'Plain package descriptions must remain valid.');
$assert(str_contains($homeBody, '<span>v1.0.0</span>'), 'Package cards must display their manifest version.');
$assert(!str_contains($homeBody, '<span>GitHub</span>'), 'Package cards must not repeat their repository host.');
$assert(!str_contains($homeBody, 'Consultez le code, les versions'), 'Package cards must not repeat generic repository guidance.');
$assert(!str_contains($homeBody, '/fr/packages/Example'), 'Package cards must not link to local package documentation.');
$assert(str_contains($homeBody, 'Silex Web release: ' . $expectedRelease), 'The deployment marker does not match release.txt.');
$assert(str_contains($homeBody, '<link rel="icon" href="/icon.svg" type="image/svg+xml">'), 'The Silex icon is missing.');

$englishHome = $handle('https://silex.test/en/');
$englishHomeBody = (string) $englishHome->getBody();
$assert($englishHome->getStatusCode() === 200, 'The English home page must respond with HTTP 200.');
$assert(str_contains($englishHomeBody, '<html lang="en">') && str_contains($englishHomeBody, 'Modern Code'), 'The English home page is missing.');
$assert(str_contains($englishHomeBody, 'href="/fr/"'), 'The English language switch is missing.');
$assert(str_contains($englishHomeBody, 'Install the Silex extension for Zed.'), 'The English Zed installation heading is missing.');
$assert(str_contains($englishHomeBody, 'Zed gallery publication tracked in PR #7190'), 'The English Zed publication status is missing.');
$assert(str_contains($englishHomeBody, 'Demonstrates reusable Silex package metadata.'), 'The English package card must display the canonical manifest description.');
$assert(!str_contains($englishHomeBody, 'Présente des métadonnées réutilisables'), 'The English package card must not display the French translation.');

$frenchDocumentation = $handle('https://silex.test/fr/docs');
$frenchDocumentationBody = (string) $frenchDocumentation->getBody();
$assert($frenchDocumentation->getStatusCode() === 200, 'French documentation must respond with HTTP 200.');
$assert(str_contains($frenchDocumentationBody, '<h1>Documentation Silex</h1>'), 'French Markdown must be rendered from FR/.');
$assert(str_contains($frenchDocumentationBody, 'Bonjour depuis Silex'), 'The French code example is missing.');
$assert(str_contains($frenchDocumentationBody, 'href="/fr/docs/Language/README.md"'), 'French relative links must stay in the French route tree.');
$assert(str_contains($frenchDocumentationBody, 'href="/en/docs"'), 'The documentation switch must preserve the document path.');
$assert(str_contains($frenchDocumentationBody, '/Silex-Documentation/blob/main/FR/README.md'), 'The French canonical source URL is wrong.');
$assert(!str_contains($frenchDocumentationBody, 'docs-package-index'), 'Language navigation must not include package documentation.');
$assert(
    preg_match('/<aside class="docs-sidebar">(.*?)<\/aside>/s', $frenchDocumentationBody, $sidebarMatch) === 1
        && !str_contains($sidebarMatch[1], 'README.md')
        && !str_contains($sidebarMatch[1], 'href="/fr/docs"'),
    'README index pages must not appear in documentation navigation.',
);

$englishGuide = $handle('https://silex.test/en/docs/Language/README.md');
$englishGuideBody = (string) $englishGuide->getBody();
$assert($englishGuide->getStatusCode() === 200, 'An English nested guide must respond with HTTP 200.');
$assert(str_contains($englishGuideBody, '<h1>Understand the Silex language</h1>'), 'English Markdown must be rendered from EN/.');
$assert(str_contains($englishGuideBody, 'href="/fr/docs/Language/README.md"'), 'The nested documentation switch must preserve the path.');

$packages = $handle('https://silex.test/fr/packages');
$packagesBody = (string) $packages->getBody();
$assert($packages->getStatusCode() === 200, 'The French package catalog must respond with HTTP 200.');
$assert(str_contains($packagesBody, 'Packages enregistrés.'), 'The French package catalog content is missing.');
$assert(str_contains($packagesBody, 'data-package-count="2"'), 'The package count is missing.');
$assert(str_contains($packagesBody, 'href="https://github.com/Matanek/Silex-Lib-Example"'), 'The package repository link is missing.');
$assert(str_contains($packagesBody, 'Présente des métadonnées réutilisables pour un package Silex.'), 'The localized package description is missing.');
$assert(str_contains($packagesBody, '<span>v1.0.0</span>'), 'The package version is missing.');
$assert(!str_contains($packagesBody, 'Docs <span'), 'The catalog must not advertise aggregated package documentation.');

$registry = $handle('https://silex.test/fr/registry');
$registryBody = (string) $registry->getBody();
$assert($registry->getStatusCode() === 200, 'The French registry page must respond with HTTP 200.');
$assert(str_contains($registryBody, 'Construisez.'), 'The French registry content is missing.');
$assert(str_contains($registryBody, 'href="/en/registry"'), 'The registry language switch must preserve the page.');
$assert(str_contains($registryBody, 'https://registry.silex-lang.org/v1/index.json'), 'The registry API link is missing.');
$assert(str_contains($registryBody, 'https://github.com/Matanek/Silex-Lib-STD'), 'Official package links must point to repositories.');

$assert($handle('https://silex.test/fr/docs/Missing.md')->getStatusCode() === 404, 'Missing documentation must respond with HTTP 404.');

$unsafeHtml = (new MarkdownRenderer())->toHtml('<script>alert("xss")</script>' . "\n\n" . '[unsafe](javascript:alert(1))');
$assert(!str_contains($unsafeHtml, '<script'), 'Raw HTML must not survive Markdown rendering.');
$assert(!str_contains($unsafeHtml, 'javascript:'), 'Unsafe Markdown links must be removed.');

$snapshotDocuments = new DocumentRepository(
    $root . '/tests/fixtures/Silex-Documentation',
    $root . '/tests/fixtures/Silex-Registry',
    $root . '/tests/fixtures/Packages',
    $root . '/tests/fixtures/snapshot.json',
);
$snapshotDocument = $snapshotDocuments->languageDocument('en', 'README.md');
$assert(
    $snapshotDocument !== null && str_contains($snapshotDocument['source_url'], '/blob/3333333333333333333333333333333333333333/EN/'),
    'Release documentation must link to the exact documentation commit.',
);
$canadianPackages = $snapshotDocuments->packages('fr-CA');
$assert(
    isset($canadianPackages[0]['description'])
        && str_contains(implode(' ', array_column($canadianPackages, 'description')), 'Présente des métadonnées réutilisables'),
    'A regional locale must fall back to its primary package-description language.',
);
$fallbackPackages = $snapshotDocuments->packages('de');
$assert(
    str_contains(implode(' ', array_column($fallbackPackages, 'description')), 'Demonstrates reusable Silex package metadata.'),
    'An unavailable package-description language must fall back to English.',
);

putenv('SILEX_VERSION');
$assert(
    SilexVersionResolver::resolve($root, $root . '/tests/fixtures/Workspace') === '1.2.3',
    'Local development must resolve the Silex version from the neighboring toolchain manifest.',
);

$cssPath = $root . '/public/assets/app.css';
$assert(is_file($cssPath) && filesize($cssPath) > 1_000, 'The compiled Tailwind stylesheet is missing.');
$iconPath = $root . '/public/icon.svg';
$assert(is_file($iconPath) && str_contains((string) file_get_contents($iconPath), '#e6ff55'), 'The Silex icon is missing or uses the wrong accent.');

echo "Silex Web checks passed.\n";
