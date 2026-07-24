<?php

namespace App\Support;

use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;

class ArticleMarkdown
{
    /**
     * 解析 front matter + 正文
     */
    public static function parse(string $content): array
    {
        $content = preg_replace('/\r\n?/', "\n", $content);

        if (!str_starts_with($content, "---\n")) {
            return ['front_matter' => [], 'body' => $content];
        }

        $end = strpos($content, "\n---", 4);
        if ($end === false) {
            return ['front_matter' => [], 'body' => $content];
        }

        return [
            'front_matter' => Yaml::parse(substr($content, 4, $end - 4)) ?: [],
            'body' => ltrim(substr($content, $end + 5), "\n"),
        ];
    }

    /**
     * Markdown → HTML + 纯文本（与 ImportArticles 渲染口径一致）
     */
    public static function render(string $markdown): array
    {
        $html = Str::markdown($markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
        $bodyText = trim(preg_replace('/\s+/', ' ', strip_tags((string) $html)));

        return [$html, $bodyText];
    }

    /**
     * 从目录名解析 external_id："200202604-About-MOO-products" → 200202604
     */
    public static function externalIdFromDirName(string $dirName): ?int
    {
        return preg_match('/^(\d+)-/', $dirName, $m) ? (int) $m[1] : null;
    }
}
