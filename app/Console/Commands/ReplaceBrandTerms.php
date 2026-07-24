<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Finder\Finder;

class ReplaceBrandTerms extends Command
{
    protected $signature = 'articles:replace-brands
                            {--input=storage/export/articles : Input directory relative to project root}
                            {--config=storage/export/replacement-config.json : JSON file with replacement rules}
                            {--dry-run : Show what would change without modifying files}';

    protected $description = 'Replace brand terms across Markdown articles using ordered rules.';

    public function handle(): int
    {
        $input = $this->resolvePath($this->option('input'));
        $configPath = $this->resolvePath($this->option('config'));

        if (!is_dir($input)) {
            $this->error("Input directory does not exist: {$input}");
            return self::FAILURE;
        }

        if (!file_exists($configPath)) {
            $this->error("Config file does not exist: {$configPath}");
            return self::FAILURE;
        }

        $config = json_decode(file_get_contents($configPath), true);
        if (!is_array($config) || !isset($config['replacements']) || !is_array($config['replacements'])) {
            $this->error("Invalid config file: {$configPath}");
            return self::FAILURE;
        }

        $globalCaseSensitive = $config['case_sensitive'] ?? false;
        $replacements = $config['replacements'];

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
                    $wholeWord = $rule['whole_word'] ?? false;
                    $caseSensitive = $rule['case_sensitive'] ?? $globalCaseSensitive;

                    $count = 0;
                    if ($wholeWord) {
                        $newContent = $this->regexReplace($newContent, $find, $replace, $caseSensitive, $count);
                    } else {
                        $newContent = $this->stringReplace($newContent, $find, $replace, $caseSensitive, $count);
                    }

                    if ($count > 0) {
                        $key = $find . ' => ' . $replace;
                        $counts[$key] = ($counts[$key] ?? 0) + $count;
                    }
                }

                if ($newContent !== $content) {
                    $changed++;
                    if ($this->option('dry-run')) {
                        $this->line("[dry-run] Would replace brands: {$relative}");
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
        $this->info("Replacement counts:");
        foreach ($counts as $key => $count) {
            $this->line("  {$key}: {$count}");
        }

        if ($errors > 0) {
            $this->error("{$errors} error(s) occurred.");
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    protected function regexReplace(string $content, string $find, string $replace, bool $caseSensitive, int &$count): string
    {
        $delimiter = '/';
        $escaped = preg_quote($find, $delimiter);
        $pattern = $delimiter . '\b' . $escaped . '\b' . $delimiter . ($caseSensitive ? '' : 'i') . 'u';

        return preg_replace($pattern, $replace, $content, -1, $count);
    }

    protected function stringReplace(string $content, string $find, string $replace, bool $caseSensitive, int &$count): string
    {
        if ($caseSensitive) {
            return str_replace($find, $replace, $content, $count);
        }

        return str_ireplace($find, $replace, $content, $count);
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
