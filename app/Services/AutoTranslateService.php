<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AutoTranslateService
{
    /** @return string|null Translated text or null on failure */
    public function translate(string $text, string $from, string $to): ?string
    {
        $text = trim($text);
        if ($text === '' || $from === $to) {
            return $text;
        }

        $driver = config('translation.auto_translate_driver', 'mymemory');

        return match ($driver) {
            'google' => $this->viaGoogle($text, $from, $to),
            default  => $this->viaMyMemory($text, $from, $to),
        };
    }

    private function viaMyMemory(string $text, string $from, string $to): ?string
    {
        try {
            $response = Http::timeout(10)->get('https://api.mymemory.translated.net/get', [
                'q'        => $text,
                'langpair' => "{$from}|{$to}",
            ]);

            if (! $response->successful()) {
                return null;
            }

            $translated = data_get($response->json(), 'responseData.translatedText');

            return is_string($translated) && $translated !== '' ? $translated : null;
        } catch (\Throwable $e) {
            Log::warning('Auto-translate failed: ' . $e->getMessage());

            return null;
        }
    }

    private function viaGoogle(string $text, string $from, string $to): ?string
    {
        $key = config('translation.google_api_key');
        if (! $key) {
            return null;
        }

        try {
            $response = Http::timeout(10)->post('https://translation.googleapis.com/language/translate/v2', [
                'q'      => $text,
                'source' => $from,
                'target' => $to,
                'format' => 'text',
                'key'    => $key,
            ]);

            if (! $response->successful()) {
                return null;
            }

            return data_get($response->json(), 'data.translations.0.translatedText');
        } catch (\Throwable $e) {
            Log::warning('Google translate failed: ' . $e->getMessage());

            return null;
        }
    }
}
