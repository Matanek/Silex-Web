<?php

declare(strict_types=1);

namespace Silex\Web\Http;

final readonly class LanguageNegotiator
{
    /** @param list<string> $supportedLocales */
    public function __construct(
        private array $supportedLocales,
        private string $defaultLocale,
    ) {
    }

    public function resolve(?string $cookieLocale, string $acceptLanguage): string
    {
        if ($cookieLocale !== null && in_array($cookieLocale, $this->supportedLocales, true)) {
            return $cookieLocale;
        }

        $candidates = [];
        foreach (explode(',', $acceptLanguage) as $position => $entry) {
            $parts = array_map('trim', explode(';', $entry));
            $locale = strtolower(strtok($parts[0], '-_') ?: '');
            $quality = 1.0;

            foreach (array_slice($parts, 1) as $parameter) {
                if (preg_match('/^q=(0(?:\.\d+)?|1(?:\.0+)?)$/', $parameter, $matches) === 1) {
                    $quality = (float) $matches[1];
                }
            }

            if ($quality > 0 && in_array($locale, $this->supportedLocales, true)) {
                $candidates[] = ['locale' => $locale, 'quality' => $quality, 'position' => $position];
            }
        }

        usort($candidates, static fn (array $left, array $right): int =>
            $right['quality'] <=> $left['quality'] ?: $left['position'] <=> $right['position']);

        return $candidates[0]['locale'] ?? $this->defaultLocale;
    }
}
