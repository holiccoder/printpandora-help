<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Finder\Finder;

class RewriteLinks extends Command
{
    protected $signature = 'articles:rewrite-links
                            {--input=storage/export/articles : Input directory relative to project root}
                            {--map=storage/export/link-map.json : JSON file with link rewriting rules}
                            {--dry-run : Show what would change without modifying files}';

    protected $description = 'Rewrite links and email addresses in Markdown articles.';

    public function handle(): int
    {
        $input = $this->resolvePath($this->option('input'));
        $mapPath = $this->resolvePath($this->option('map'));

        if (!is_dir($input)) {
            $this->error("Input directory does not exist: {$input}");
            return self::FAILURE;
        }

        if (!file_exists($mapPath)) {
            $this->error("Map file does not exist: {$mapPath}");
            return self::FAILURE;
        }

        $config = json_decode(file_get_contents($mapPath), true);
        if (!is_array($config)) {
            $this->error("Invalid map file: {$mapPath}");
            return self::FAILURE;
        }

        $emailReplacements = $config['email_replacements'] ?? [];
        $pathPatterns = $config['path_patterns'] ?? [];
        $rawDomainReplacements = $config['raw_domain_replacements'] ?? [];
        $reportPatterns = $config['report_unmatched']['patterns'] ?? [];

        $finder = new Finder;
        $finder->files()->in($input)->name('*.md')->sortByName();

        $processed = 0;
        $changed = 0;
        $errors = 0;
        $emailCounts = [];
        $pathCounts = [];
        $unmatched = [];

        foreach ($finder as $file) {
            $processed++;
            $relative = $file->getRelativePathname();

            try {
                $content = file_get_contents($file->getPathname());
                $newContent = $content;

                foreach ($emailReplacements as $email => $placeholder) {
                    $count = 0;
                    $newContent = str_ireplace($email, $placeholder, $newContent, $count);
                    if ($count > 0) {
                        $emailCounts["{$email} => {$placeholder}"] = ($emailCounts["{$email} => {$placeholder}"] ?? 0) + $count;
                    }
                }

                foreach ($pathPatterns as $pattern) {
                    $find = $pattern['pattern'];
                    $replace = $pattern['replace'];
                    $count = 0;
                    $newContent = str_replace($find, $replace, $newContent, $count);
                    if ($count > 0) {
                        $pathCounts["{$find} => {$replace}"] = ($pathCounts["{$find} => {$replace}"] ?? 0) + $count;
                    }
                }

                foreach ($rawDomainReplacements as $replacement) {
                    $find = $replacement['find'];
                    $replace = $replacement['replace'];
                    $count = 0;
                    $newContent = str_ireplace($find, $replace, $newContent, $count);
                    if ($count > 0) {
                        $pathCounts["{$find} => {$replace}"] = ($pathCounts["{$find} => {$replace}"] ?? 0) + $count;
                    }
                }

                $fileUnmatched = $this->findUnmatched($newContent, $reportPatterns);
                if (!empty($fileUnmatched)) {
                    $unmatched[$relative] = $fileUnmatched;
                }

                if ($newContent !== $content) {
                    $changed++;
                    if ($this->option('dry-run')) {
                        $this->line("[dry-run] Would rewrite links: {$relative}");
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

        if (!empty($emailCounts)) {
            $this->info("Email replacements:");
            foreach ($emailCounts as $key => $count) {
                $this->line("  {$key}: {$count}");
            }
        }

        if (!empty($pathCounts)) {
            $this->info("Path/domain replacements:");
            foreach ($pathCounts as $key => $count) {
                $this->line("  {$key}: {$count}");
            }
        }

        if (!empty($unmatched)) {
            $this->warn("Unmatched links remain in " . count($unmatched) . " file(s).");
            foreach ($unmatched as $file => $items) {
                foreach ($items as $item) {
                    $this->line("  {$file}: {$item}");
                }
            }
        }

        $report = [
            'timestamp' => now()->toIso8601String(),
            'email_counts' => $emailCounts,
            'path_counts' => $pathCounts,
            'unmatched' => $unmatched,
        ];

        $reportDir = storage_path('export/reports');
        if (!is_dir($reportDir)) {
            mkdir($reportDir, 0755, true);
        }
        $reportPath = $reportDir . '/link-rewrite-report-' . now()->format('Ymd-His') . '.json';
        file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->info("Report saved to: {$reportPath}");

        if ($errors > 0) {
            $this->error("{$errors} error(s) occurred.");
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    protected function findUnmatched(string $content, array $patterns): array
    {
        $findings = [];
        foreach ($patterns as $pattern) {
            if (preg_match_all('/' . $pattern . '/i', $content, $matches)) {
                foreach ($matches[0] as $match) {
                    $findings[] = $match;
                }
            }
        }

        return array_values(array_unique($findings));
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
