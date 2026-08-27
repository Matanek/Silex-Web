<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface;
use Silex\Web\ApplicationFactory;
use Silex\Web\Rendering\MarkdownRenderer;
use Slim\Psr7\Factory\ServerRequestFactory;

$root = dirname(__DIR__);
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
$assert($frenchHome->getStatusCode() === 200, 'The French home page must respond with HTTP 200.');
$assert(str_contains($frenchBody, 'Construire clairement.'), 'The French home page content is missing.');
$assert(str_contains($frenchBody, 'Automated VPS deployment is working.'), 'The deployment marker is missing.');
$assert(str_contains($frenchHome->getHeaderLine('Set-Cookie'), 'silex_locale=fr'), 'The selected locale must be remembered.');
$assert(str_contains($frenchHome->getHeaderLine('Set-Cookie'), 'Secure'), 'HTTPS locale cookies must be secure.');

$englishDocumentation = $handle('https://silex.test/en/docs');
$documentationBody = (string) $englishDocumentation->getBody();
$assert($englishDocumentation->getStatusCode() === 200, 'The English documentation must respond with HTTP 200.');
$assert(str_contains($documentationBody, '<h1>Silex documentation</h1>'), 'Markdown headings must be rendered.');
$assert(str_contains($documentationBody, '<code class="language-silex">'), 'Silex code fences must keep their language.');

$unsafeHtml = (new MarkdownRenderer())->toHtml(
    '<script>alert("xss")</script>' . "\n\n" . '[unsafe](javascript:alert(1))',
);
$assert(!str_contains($unsafeHtml, '<script'), 'Raw HTML must not survive Markdown rendering.');
$assert(!str_contains($unsafeHtml, 'javascript:'), 'Unsafe Markdown links must be removed.');

$cssPath = $root . '/public/assets/app.css';
$assert(is_file($cssPath) && filesize($cssPath) > 1_000, 'The compiled Tailwind stylesheet is missing.');

echo "Silex Web checks passed.\n";
