<?php

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;

/**
 * Every translation file under lang/ar and lang/en (vendor overrides excluded), keyed by its path relative to the locale folder.
 *
 * @return array<string, array<string, mixed>>
 */
function translationFiles(string $locale): array
{
    return collect(File::allFiles(lang_path($locale)))
        ->filter(fn (SplFileInfo $file): bool => $file->getExtension() === 'php')
        ->mapWithKeys(fn (SplFileInfo $file): array => [
            str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen(lang_path($locale)) + 1)) => require $file->getPathname(),
        ])
        ->all();
}

it('has the same translation files and keys in Arabic and English', function () {
    $arabic = translationFiles('ar');
    $english = translationFiles('en');

    expect(array_keys($arabic))->toEqualCanonicalizing(array_keys($english));

    foreach ($arabic as $file => $lines) {
        $arabicKeys = array_keys(Arr::dot($lines));
        $englishKeys = array_keys(Arr::dot($english[$file]));

        expect(array_diff($arabicKeys, $englishKeys))->toBe([], "Missing in en/{$file}")
            ->and(array_diff($englishKeys, $arabicKeys))->toBe([], "Missing in ar/{$file}");
    }
});
