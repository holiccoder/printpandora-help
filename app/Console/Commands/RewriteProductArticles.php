<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Finder\Finder;

class RewriteProductArticles extends Command
{
    protected $signature = 'articles:rewrite-product
                            {--input=storage/export/articles : Input directory relative to project root}
                            {--specs=storage/export/product-specs.json : JSON file with product specifications}
                            {--dry-run : Show what would change without modifying files}';

    protected $description = 'Apply product specifications to core product articles.';

    public function handle(): int
    {
        $input = $this->resolvePath($this->option('input'));
        $specsPath = $this->resolvePath($this->option('specs'));

        if (!is_dir($input)) {
            $this->error("Input directory does not exist: {$input}");
            return self::FAILURE;
        }

        if (!file_exists($specsPath)) {
            $this->error("Specs file does not exist: {$specsPath}");
            return self::FAILURE;
        }

        $specs = json_decode(file_get_contents($specsPath), true);
        if (!is_array($specs)) {
            $this->error("Invalid specs file: {$specsPath}");
            return self::FAILURE;
        }

        $replacements = $this->buildReplacements($specs);

        $finder = new Finder;
        $finder->files()->in($input)->name('*.md')->sortByName();

        $processed = 0;
        $changed = 0;
        $errors = 0;
        $counts = [];

        foreach ($finder as $file) {
            $processed++;
            $relative = $file->getRelativePathname();

            try {
                $content = file_get_contents($file->getPathname());
                $newContent = $content;

                foreach ($replacements as $rule) {
                    $find = $rule['find'];
                    $replace = $rule['replace'];
                    $count = 0;
                    $newContent = str_replace($find, $replace, $newContent, $count);
                    if ($count > 0) {
                        $counts["{$find} => {$replace}"] = ($counts["{$find} => {$replace}"] ?? 0) + $count;
                    }
                }

                if ($newContent !== $content) {
                    $changed++;
                    if ($this->option('dry-run')) {
                        $this->line("[dry-run] Would rewrite product specs: {$relative}");
                    } else {
                        file_put_contents($file->getPathname(), $this->normalizeMarkdown($newContent));
                    }
                }
            } catch (\Throwable $e) {
                $this->error("[error] {$relative}: {$e->getMessage()}");
                $errors++;
            }
        }

        $this->newLine();
        $this->info("Processed {$processed} file(s), changed {$changed} file(s).");

        if (!empty($counts)) {
            $this->info("Replacement counts:");
            foreach ($counts as $key => $count) {
                $this->line("  {$key}: {$count}");
            }
        }

        if ($errors > 0) {
            $this->error("{$errors} error(s) occurred.");
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    protected function buildReplacements(array $specs): array
    {
        $replacements = [];

        if (isset($specs['brand_name'])) {
            $replacements[] = ['find' => '{{BRAND_NAME}}', 'replace' => $specs['brand_name']];
        }

        if (isset($specs['business_cards']['papers'])) {
            foreach ($specs['business_cards']['papers'] as $paper) {
                $key = $paper['key'];
                $replacements[] = ['find' => "{{PAPER_{$key}_NAME}}", 'replace' => $paper['name'] ?? ''];
                $replacements[] = ['find' => "{{PAPER_{$key}_WEIGHT}}", 'replace' => ($paper['weight_grams'] ?? '') . 'gsm'];
            }
        }

        if (isset($specs['preflight'])) {
            $preflight = $specs['preflight'];
            $replacements[] = ['find' => '{{BLEED_MM}}', 'replace' => ($preflight['bleed_mm'] ?? '') . 'mm'];
            $replacements[] = ['find' => '{{SAFE_AREA_MM}}', 'replace' => ($preflight['safe_area_mm'] ?? '') . 'mm'];
            $replacements[] = ['find' => '{{MAX_FILE_SIZE_MB}}', 'replace' => ($preflight['max_file_size_mb'] ?? '') . 'MB'];
        }

        return $replacements;
    }

    protected function normalizeMarkdown(string $markdown): string
    {
        $markdown = preg_replace('/\r\n?/', "\n", $markdown);

        return rtrim($markdown) . "\n";
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
