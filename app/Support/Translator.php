<?php

namespace App\Support;

class Translator
{
    protected static ?array $cache = null;
    protected static string $cachePath = '';

    protected static function init(): void
    {
        if (self::$cache !== null) {
            return;
        }

        self::$cachePath = storage_path('app/translations_cache.json');
        
        if (file_exists(self::$cachePath)) {
            $content = file_get_contents(self::$cachePath);
            self::$cache = json_decode($content, true) ?: [];
        } else {
            self::$cache = [];
        }
    }

    protected static function save(): void
    {
        if (self::$cache === null) {
            return;
        }

        $dir = dirname(self::$cachePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(self::$cachePath, json_encode(self::$cache, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    public static function translate(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        self::init();

        $hash = md5($text);
        if (isset(self::$cache[$hash])) {
            return self::$cache[$hash];
        }

        if (strlen($text) > 4000) {
            $translated = self::translateLongText($text);
        } else {
            $translated = self::fetchTranslation($text);
        }

        if ($translated !== '') {
            self::$cache[$hash] = $translated;
            self::save();
            return $translated;
        }

        return $text;
    }

    protected static function translateLongText(string $text): string
    {
        if (str_contains($text, '</p>')) {
            $parts = explode('</p>', $text);
            if (count($parts) > 1) {
                $translatedParts = [];
                foreach ($parts as $part) {
                    $trimmed = trim($part);
                    if ($trimmed === '') {
                        continue;
                    }
                    $translatedParts[] = self::translate($trimmed) . '</p>';
                }
                return implode("\n", $translatedParts);
            }
        }

        if (str_contains($text, "\n")) {
            $parts = explode("\n", $text);
            if (count($parts) > 1) {
                $translatedParts = [];
                foreach ($parts as $part) {
                    $translatedParts[] = self::translate($part);
                }
                return implode("\n", $translatedParts);
            }
        }

        if (str_contains($text, '. ')) {
            $parts = explode('. ', $text);
            if (count($parts) > 1) {
                $translatedParts = [];
                foreach ($parts as $part) {
                    $translatedParts[] = self::translate($part);
                }
                return implode('. ', $translatedParts);
            }
        }

        // Fallback: split into 2000-character chunks to avoid infinite recursion
        $chunks = str_split($text, 2000);
        $translatedParts = [];
        foreach ($chunks as $chunk) {
            $translatedParts[] = self::fetchTranslation($chunk);
        }
        return implode('', $translatedParts);
    }

    protected static function fetchTranslation(string $text): string
    {
        $url = 'https://translate.googleapis.com/translate_a/single?client=gtx&sl=en&tl=zh-CN&dt=t&q=' . urlencode($text);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 2);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        $response = curl_exec($ch);
        curl_close($ch);

        if ($response === false || $response === '') {
            return '';
        }

        $json = json_decode($response, true);
        $translated = '';

        if (isset($json[0]) && is_array($json[0])) {
            foreach ($json[0] as $item) {
                if (isset($item[0])) {
                    $translated .= $item[0];
                }
            }
        }

        // Clean up translated brand terms to be consistent with "Pandoara"
        $translated = str_replace(
            ['潘朵拉', '盘多拉', '潘多拉科技', '慕数码', '慕卡', 'Moo卡', 'MOO'],
            ['Pandoara', 'Pandoara', 'Pandoara', 'Pandoara', 'Pandoara', 'Pandoara', 'Pandoara'],
            $translated
        );

        return $translated;
    }

    public static function translateToEnglish(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        if (preg_match('/^[a-zA-Z0-9\s\-\_\.\,\!\?\@\#\$\%\^\&\*\(\)\+]*$/', $text)) {
            return $text;
        }

        $url = 'https://translate.googleapis.com/translate_a/single?client=gtx&sl=zh-CN&tl=en&dt=t&q=' . urlencode($text);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        $response = curl_exec($ch);
        curl_close($ch);

        if ($response === false || $response === '') {
            return $text;
        }

        $json = json_decode($response, true);
        $translated = '';

        if (isset($json[0]) && is_array($json[0])) {
            foreach ($json[0] as $item) {
                if (isset($item[0])) {
                    $translated .= $item[0];
                }
            }
        }

        // Make sure we convert any translated brand names back to original if needed
        $translated = str_replace(
            ['Moo', 'MOO', 'Pandora', 'pandora'],
            ['Pandoara', 'Pandoara', 'Pandoara', 'pandoara'],
            $translated
        );

        return $translated ?: $text;
    }
}
