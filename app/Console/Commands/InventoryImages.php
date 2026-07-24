<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Finder\Finder;

class InventoryImages extends Command
{
    protected $signature = 'articles:inventory-images
                            {--input=storage/export/articles : Input directory relative to project root}
                            {--output=storage/export/image-inventory.json : Output JSON file}';

    protected $description = 'Inventory all image and asset URLs in Markdown articles.';

    public function handle(): int
    {
        $input = $this->resolvePath($this->option('input'));
        $output = $this->resolvePath($this->option('output'));

        if (!is_dir($input)) {
            $this->error("Input directory does not exist: {$input}");
            return self::FAILURE;
        }

        $finder = new Finder;
        $finder->files()->in($input)->name('*.md')->sortByName();

        $byUrl = [];
        $byFile = [];

        foreach ($finder as $file) {
            $relative = $file->getRelativePathname();
            $content = file_get_contents($file->getPathname());
            $lineNumber = 0;

            foreach (explode("\n", $content) as $line) {
                $lineNumber++;
                $urls = $this->extractUrls($line);

                foreach ($urls as $url) {
                    $type = $this->classifyUrl($url);
                    $entry = [
                        'file' => $relative,
                        'line' => $lineNumber,
                        'url' => $url,
                        'type' => $type,
                    ];

                    $byFile[$relative][] = $entry;

                    if (!isset($byUrl[$url])) {
                        $byUrl[$url] = [
                            'url' => $url,
                            'type' => $type,
                            'count' => 0,
                            'files' => [],
                        ];
                    }
                    $byUrl[$url]['count']++;
                    $byUrl[$url]['files'][$relative] = true;
                }
            }
        }

        foreach ($byUrl as $url => &$data) {
            $data['files'] = array_keys($data['files']);
        }

        $inventory = [
            'generated_at' => now()->toIso8601String(),
            'total_files_scanned' => $finder->count(),
            'total_unique_urls' => count($byUrl),
            'by_url' => array_values($byUrl),
            'by_file' => $byFile,
        ];

        $outputDir = dirname($output);
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        file_put_contents($output, json_encode($inventory, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->info("Scanned {$inventory['total_files_scanned']} file(s).");
        $this->info("Found {$inventory['total_unique_urls']} unique image/asset URL(s).");
        $this->info("Inventory saved to: {$output}");

        return self::SUCCESS;
    }

    protected function extractUrls(string $line): array
    {
        $urls = [];

        preg_match_all('/!\[([^\]]*)\]\(([^)]+)\)/', $line, $markdownMatches, PREG_SET_ORDER);
        foreach ($markdownMatches as $match) {
            $urls[] = $match[2];
        }

        preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $line, $htmlMatches, PREG_SET_ORDER);
        foreach ($htmlMatches as $match) {
            $urls[] = $match[1];
        }

        preg_match_all('/\[([^\]]+)\]\((https?:\/\/[^)]+)\)/', $line, $linkMatches, PREG_SET_ORDER);
        foreach ($linkMatches as $match) {
            $url = $match[2];
            if ($this->isAssetUrl($url)) {
                $urls[] = $url;
            }
        }

        return array_values(array_unique($urls));
    }

    protected function isAssetUrl(string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH) ?? '';
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return in_array($extension, ['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'pdf', 'ai', 'psd', 'indd', 'idml', 'zip'], true);
    }

    protected function classifyUrl(string $url): string
    {
        if (str_contains($url, '{{HELP_DOMAIN}}/hc/article_attachments')) {
            return 'article_attachment';
        }
        if (str_contains($url, '{{TEMPLATE_ASSET_DOMAIN}}')) {
            return 'template_download';
        }
        if (str_contains($url, '{{IMAGE_')) {
            return 'placeholder';
        }

        $path = parse_url($url, PHP_URL_PATH) ?? '';
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (in_array($extension, ['ai', 'psd', 'indd', 'idml', 'pdf', 'zip'], true)) {
            return 'template_download';
        }

        return 'image';
    }

    protected function resolvePath(string $path): string
    {
        if ($this->isAbsolutePath($path)) {
            return rtrim($path, '\\/');
        }

        return rtrim(base_path($path), '\\/');
    }

    protected function isAbsolutePath(string $path): bool
    {
        if (str_starts_with($path, '/') || str_starts_with($path, '\\')) {
            return true;
        }

        return strlen($path) >= 3
            && ctype_alpha($path[0])
            && $path[1] === ':'
            && ($path[2] === '\\' || $path[2] === '/');
    }
}
