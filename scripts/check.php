<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface;
use Silex\Web\ApplicationFactory;
use Silex\Web\Ecosystem\SilexVersionResolver;
use Silex\Web\Rendering\MarkdownRenderer;
use Slim\Psr7\Factory\ServerRequestFactory;

$root = dirname(__DIR__);
putenv('SILEX_DOCS_ROOT=' . $root . '/tests/fixtures/Silex/Docs');
putenv('SILEX_PACKAGES_ROOT=' . $root . '/tests/fixtures/Packages');
putenv('SILEX_REGISTRY_ROOT=' . $root . '/tests/fixtures/Silex-Registry');
putenv('SILEX_VERSION=9.8.7');
$directories = [
    $root . '/public',
    $root . '/scripts',
    $root . '/src',
];
$phpFiles = [];

foreach ($directories as $directory) {
    if (!is_dir($directory)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $phpFiles[] = $file->getPathname();
        }
    }
}

sort($phpFiles);

if ($phpFiles === []) {
    fwrite(STDERR, "No PHP source files were found.\n");
    exit(1);
}

foreach ($phpFiles as $file) {
    $command = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file);
    passthru($command, $status);
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

$redirect = $handle('https://silex.test/', ['Accept-Language' => 'de, fr-FR;q=0.9, en;q=0.8']);
$assert($redirect->getStatusCode() === 302, 'The locale entry point must redirect.');
$assert($redirect->getHeaderLine('Location') === '/fr/', 'The browser language should select French.');

$remembered = $handle(
    'https://silex.test/',
    ['Accept-Language' => 'fr-FR, en;q=0.8'],
    ['silex_locale' => 'en'],
);
$assert($remembered->getHeaderLine('Location') === '/en/', 'The locale cookie should take precedence.');

$frenchHome = $handle('https://silex.test/fr/');
$frenchBody = (string) $frenchHome->getBody();
$releaseFile = $root . '/release.txt';
$expectedRelease = is_file($releaseFile) ? trim((string) file_get_contents($releaseFile)) : 'development';
$assert($frenchHome->getStatusCode() === 200, 'The French home page must respond with HTTP 200.');
$assert(str_contains($frenchBody, 'Code moderne'), 'The French home page content is missing.');
$assert(str_contains($frenchBody, 'silex run Main.sx'), 'The canonical quickstart command is missing.');
$assert(str_contains($frenchBody, 'v9.8.7'), 'The configured Silex version is missing.');
$assert(str_contains($frenchBody, 'data-package-count="1"'), 'The home page package list is missing.');
$assert(str_contains($frenchBody, 'href="/fr/packages/Example"'), 'The home page must link to package documentation.');
$assert(
    str_contains($frenchBody, 'Silex Web release: ' . $expectedRelease),
    'The deployment marker does not match release.txt.',
);
$assert(str_contains($frenchHome->getHeaderLine('Set-Cookie'), 'silex_locale=fr'), 'The selected locale must be remembered.');
$assert(str_contains($frenchHome->getHeaderLine('Set-Cookie'), 'Secure'), 'HTTPS locale cookies must be secure.');

$englishDocumentation = $handle('https://silex.test/en/docs');
$documentationBody = (string) $englishDocumentation->getBody();
$assert($englishDocumentation->getStatusCode() === 200, 'The English documentation must respond with HTTP 200.');
$assert(str_contains($documentationBody, '<h1>Documentation</h1>'), 'Canonical Markdown headings must be rendered.');
$assert(str_contains($documentationBody, '<code class="language-silex">'), 'Silex code fences must keep their language.');
$assert(str_contains($documentationBody, 'func main()'), 'The documentation must use the current Silex function syntax.');
$assert(
    str_contains($documentationBody, 'href="/en/docs/Language/README.md"'),
    'Relative canonical documentation links must be routed through the website.',
);
$assert(
    str_contains($documentationBody, 'href="/en/packages/Example"'),
    'Language documentation navigation must expose package documentation.',
);

$frenchLanguageGuide = $handle('https://silex.test/fr/docs/Language/README.md');
$languageGuideBody = (string) $frenchLanguageGuide->getBody();
$assert($frenchLanguageGuide->getStatusCode() === 200, 'A nested language guide must respond with HTTP 200.');
$assert(str_contains($languageGuideBody, '<h1>Silex language</h1>'), 'The nested language guide is missing.');
$assert(str_contains($languageGuideBody, 'Contenu source en anglais'), 'French documentation must identify its English source.');

$frenchPackages = $handle('https://silex.test/fr/packages');
$packagesBody = (string) $frenchPackages->getBody();
$assert($frenchPackages->getStatusCode() === 200, 'The package catalog must respond with HTTP 200.');
$assert(str_contains($packagesBody, 'data-package-count="1"'), 'The package catalog must expose its package count.');
$assert(str_contains($packagesBody, 'href="/fr/packages/Example"'), 'The package catalog entry is missing.');

$frenchPackage = $handle('https://silex.test/fr/packages/Example');
$packageBody = (string) $frenchPackage->getBody();
$assert($frenchPackage->getStatusCode() === 200, 'Package documentation must respond with HTTP 200.');
$assert(str_contains($packageBody, '<h1>Example package</h1>'), 'The package README must be rendered.');
$assert(
    str_contains($packageBody, 'href="/fr/packages/Example/docs/Docs/Guide.md"'),
    'Relative package documentation links must stay inside the package route.',
);
$assert(
    str_contains($packageBody, 'href="https://github.com/Matanek/Silex-Lib-Example/blob/main/Tests/Example.sx"'),
    'Relative package source links must resolve to the canonical repository.',
);
$assert(str_contains($packageBody, 'Documentation en anglais'), 'French package pages must identify their English source.');

$missingDocumentation = $handle('https://silex.test/en/docs/Missing.md');
$assert($missingDocumentation->getStatusCode() === 404, 'Missing canonical documentation must respond with HTTP 404.');
$missingPackageDocument = $handle('https://silex.test/en/packages/Example/docs/Package.json');
$assert($missingPackageDocument->getStatusCode() === 404, 'Files outside package documentation must not be exposed.');

$frenchRegistry = $handle('https://silex.test/fr/registry');
$registryBody = (string) $frenchRegistry->getBody();
$assert($frenchRegistry->getStatusCode() === 200, 'The French registry page must respond with HTTP 200.');
$assert(str_contains($registryBody, 'Construisez.'), 'The French registry page content is missing.');
$assert(
    str_contains($registryBody, 'https://registry.silex-lang.org/v1/index.json'),
    'The website registry page must link to the autonomous registry API.',
);
$assert(str_contains($registryBody, 'href="/en/registry"'), 'The registry language switch must preserve the page.');

$unsafeHtml = (new MarkdownRenderer())->toHtml(
    '<script>alert("xss")</script>' . "\n\n" . '[unsafe](javascript:alert(1))',
);
$assert(!str_contains($unsafeHtml, '<script'), 'Raw HTML must not survive Markdown rendering.');
$assert(!str_contains($unsafeHtml, 'javascript:'), 'Unsafe Markdown links must be removed.');

putenv('SILEX_VERSION');
$assert(
    SilexVersionResolver::resolve($root, $root . '/tests/fixtures/Workspace') === '1.2.3',
    'Local development must resolve the Silex version from the neighboring toolchain manifest.',
);

$cssPath = $root . '/public/assets/app.css';
$assert(is_file($cssPath) && filesize($cssPath) > 1_000, 'The compiled Tailwind stylesheet is missing.');

echo "Silex Web checks passed.\n";
