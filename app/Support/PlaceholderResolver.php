<?php

namespace App\Support;

class PlaceholderResolver
{
    protected static ?array $config = null;

    protected static function init(): void
    {
        if (self::$config !== null) {
            return;
        }

        $paths = [
            base_path('storage/export/final-config.json'),
            base_path('storage/export/final-config.json.example'),
        ];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                $content = file_get_contents($path);
                self::$config = json_decode($content, true);
                if (is_array(self::$config)) {
                    return;
                }
            }
        }

        $envOverrides = [];
        foreach ($_ENV as $key => $value) {
            if (str_starts_with($key, 'PANDOARA_')) {
                $placeholder = substr($key, strlen('PANDOARA_'));
                $envOverrides[$placeholder] = $value;
            }
        }

        self::$config = array_merge([
            'BRAND_NAME' => 'Pandoara',
            'BRAND_BUSINESS_SERVICES' => 'Pandoara for Business',
            'BRAND_NEWSLETTER' => 'Pandoara Post',
            'BRAND_MASCOT' => 'Pando',
            'BRAND_PROMISE' => 'The Pandoara Promise',
            'FEATURE_MIXED_DESIGNS' => 'Mix & Match',
            'BRAND_SIZE_NAME' => 'Pandoara Size',
            'BRAND_MINICARD_NAME' => 'Mini Cards',
            'PAPER_BRAND' => '',
            'HELP_DOMAIN' => 'https://help.pandoara.com',
            'MAIN_DOMAIN' => 'https://www.pandoara.com',
            'CONTACT_URL' => 'https://www.pandoara.com/contact',
            'QUOTE_URL' => 'https://www.pandoara.com/quote',
            'TEMPLATE_DOWNLOAD_URL' => 'https://www.pandoara.com/templates',
            'TEMPLATE_ASSET_DOMAIN' => 'https://assets.pandoara.com',
            'EMAIL_INQUIRIES' => 'hello@pandoara.com',
            'EMAIL_SUPPORT' => 'help@pandoara.com',
            'EMAIL_PRIVACY' => 'privacy@pandoara.com',
            'EMAIL_PR' => 'press@pandoara.com',
            'EMAIL_TAX' => 'tax@pandoara.com',
            'ICC_PROFILE' => 'Coated GRACoL 2006'
        ], $envOverrides);
    }

    public static function resolve(string $text): string
    {
        if (!str_contains($text, '{{')) {
            return $text;
        }

        self::init();

        foreach (self::$config as $key => $value) {
            $text = str_replace('{{' . $key . '}}', $value, $text);
        }

        return $text;
    }
}
