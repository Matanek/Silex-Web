<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface;
use Silex\Web\ApplicationFactory;
use Silex\Web\Documentation\DocumentRepository;
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

$home = $handle(
    'https://silex.test/',
    ['Accept-Language' => 'fr-FR, en;q=0.8'],
    ['silex_locale' => 'fr'],
);
$homeBody = (string) $home->getBody();
$releaseFile = $root . '/release.txt';
$expectedRelease = is_file($releaseFile) ? trim((string) file_get_contents($releaseFile)) : 'development';
$assert($home->getStatusCode() === 200, 'The direct home page must respond with HTTP 200.');
$assert($home->getHeaderLine('Location') === '', 'The home page must not redirect by browser language.');
$assert(!str_contains($home->getHeaderLine('Set-Cookie'), 'silex_locale'), 'The website must not set a locale cookie.');
$assert(str_contains($homeBody, 'Modern Code'), 'The English home page content is missing.');
$assert(str_contains($homeBody, 'silex run Main.sx'), 'The canonical quickstart command is missing.');
$assert(str_contains($homeBody, 'v9.8.7'), 'The configured Silex version is missing.');
$assert(
    str_contains($homeBody, '<link rel="icon" href="/icon.svg" type="image/svg+xml">')
        && str_contains($homeBody, '<img class="brand-mark" src="/icon.svg" alt="">'),
    'The Silex icon is missing from the page shell.',
);
$assert(str_contains($homeBody, 'data-package-count="1"'), 'The home page package list is missing.');
$assert(str_contains($homeBody, 'class="package-card"'), 'The home page must use the shared package card.');
$assert(str_contains($homeBody, 'href="/packages/Example"'), 'The home page must link to package documentation.');
$assert(
    str_contains($homeBody, 'href="https://github.com/Matanek/Silex-Lib-Example"'),
    'The home page package list must link directly to the package repository.',
);
$assert(
    str_contains($homeBody, 'Silex Web release: ' . $expectedRelease),
    'The deployment marker does not match release.txt.',
);
$assert(
    str_contains($homeBody, 'href="https://github.com/Matanek/Silex">Silex language</a>')
        && str_contains($homeBody, 'href="https://github.com/Matanek">Matanek</a>'),
    'The footer must credit Matanek and link to the Silex repository.',
);

$documentation = $handle('https://silex.test/docs');
$documentationBody = (string) $documentation->getBody();
$assert($documentation->getStatusCode() === 200, 'The documentation must respond with HTTP 200.');
$assert(str_contains($documentationBody, '<h1>Documentation</h1>'), 'Canonical Markdown headings must be rendered.');
$assert(str_contains($documentationBody, '<code class="language-silex">'), 'Silex code fences must keep their language.');
$assert(str_contains($documentationBody, 'func main()'), 'The documentation must use the current Silex function syntax.');
$assert(
    str_contains($documentationBody, 'href="/docs/Language/README.md"'),
    'Relative canonical documentation links must be routed through the website.',
);
$assert(
    str_contains($documentationBody, 'href="/packages/Example"'),
    'Language documentation navigation must expose package documentation.',
);

$languageGuide = $handle('https://silex.test/docs/Language/README.md');
$languageGuideBody = (string) $languageGuide->getBody();
$assert($languageGuide->getStatusCode() === 200, 'A nested language guide must respond with HTTP 200.');
$assert(str_contains($languageGuideBody, '<h1>Silex language</h1>'), 'The nested language guide is missing.');

$packages = $handle('https://silex.test/packages');
$packagesBody = (string) $packages->getBody();
$assert($packages->getStatusCode() === 200, 'The package catalog must respond with HTTP 200.');
$assert(str_contains($packagesBody, 'data-package-count="1"'), 'The package catalog must expose its package count.');
$assert(str_contains($packagesBody, 'class="package-card"'), 'The package catalog must use the shared package card.');
$assert(str_contains($packagesBody, 'href="/packages/Example"'), 'The package catalog entry is missing.');
$assert(
    str_contains($packagesBody, 'href="https://github.com/Matanek/Silex-Lib-Example"'),
    'The package catalog must link directly to the package repository.',
);

$package = $handle('https://silex.test/packages/Example');
$packageBody = (string) $package->getBody();
$assert($package->getStatusCode() === 200, 'Package documentation must respond with HTTP 200.');
$assert(str_contains($packageBody, '<h1>Example package</h1>'), 'The package README must be rendered.');
$assert(
    str_contains($packageBody, 'href="/packages/Example/docs/Docs/Guide.md"'),
    'Relative package documentation links must stay inside the package route.',
);
$assert(
    str_contains($packageBody, 'href="https://github.com/Matanek/Silex-Lib-Example/blob/main/Tests/Example.sx"'),
    'Relative package source links must resolve to the canonical repository.',
);
$assert(
    str_contains($packageBody, '>Repository ↗</a>'),
    'Package documentation must expose the package repository independently from its canonical document source.',
);
$missingDocumentation = $handle('https://silex.test/docs/Missing.md');
$assert($missingDocumentation->getStatusCode() === 404, 'Missing canonical documentation must respond with HTTP 404.');
$missingPackageDocument = $handle('https://silex.test/packages/Example/docs/Package.json');
$assert($missingPackageDocument->getStatusCode() === 404, 'Files outside package documentation must not be exposed.');

$registry = $handle('https://silex.test/registry');
$registryBody = (string) $registry->getBody();
$assert($registry->getStatusCode() === 200, 'The registry page must respond with HTTP 200.');
$assert(str_contains($registryBody, 'Build it.'), 'The registry page content is missing.');
$assert(
    str_contains($registryBody, 'https://registry.silex-lang.org/v1/index.json'),
    'The website registry page must link to the autonomous registry API.',
);
$assert(!str_contains($registryBody, 'locale-link'), 'The single-language site must not expose a locale switch.');

$assert($handle('https://silex.test/fr/')->getStatusCode() === 404, 'Legacy localized home routes must not redirect.');
$assert($handle('https://silex.test/en/docs')->getStatusCode() === 404, 'Legacy localized documentation routes must not redirect.');

$unsafeHtml = (new MarkdownRenderer())->toHtml(
    '<script>alert("xss")</script>' . "\n\n" . '[unsafe](javascript:alert(1))',
);
$assert(!str_contains($unsafeHtml, '<script'), 'Raw HTML must not survive Markdown rendering.');
$assert(!str_contains($unsafeHtml, 'javascript:'), 'Unsafe Markdown links must be removed.');

$snapshotDocuments = new DocumentRepository(
    $root . '/content',
    $root . '/tests/fixtures/Silex/Docs',
    $root . '/tests/fixtures/Packages',
    $root . '/tests/fixtures/Silex-Registry',
    $root . '/tests/fixtures/snapshot.json',
);
$snapshotLanguageDocument = $snapshotDocuments->languageDocument('README.md');
$snapshotPackageDocument = $snapshotDocuments->packageDocument('Example', 'README.md');
$assert(
    $snapshotLanguageDocument !== null && str_contains($snapshotLanguageDocument['source_url'], '/blob/v1.2.3/'),
    'Release documentation must link to the exact published Silex tag.',
);
$assert(
    $snapshotPackageDocument !== null && str_contains((string) $snapshotPackageDocument['source_url'], '/blob/v9.8.7/'),
    'Release package documentation must link to the exact published package tag.',
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
