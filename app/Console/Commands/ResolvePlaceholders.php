<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Finder\Finder;

class ResolvePlaceholders extends Command
{
    protected $signature = 'articles:resolve-placeholders
                            {--input=storage/export/articles : Input directory relative to project root}
                            {--config=storage/export/final-config.json : JSON file with placeholder values}
                            {--dry-run : Show what would change without modifying files}';

    protected $description = 'Resolve placeholders in Markdown articles using final values.';

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

        $values = json_decode(file_get_contents($configPath), true);
        if (!is_array($values)) {
            $this->error("Invalid config file: {$configPath}");
            return self::FAILURE;
        }

        $finder = new Finder;
        $finder->files()->in($input)->name('*.md')->sortByName();

        $processed = 0;
        $changed = 0;
        $errors = 0;
        $unresolved = [];

        foreach ($finder as $file) {
            $processed++;
            $relative = $file->getRelativePathname();

            try {
                $content = file_get_contents($file->getPathname());
                $newContent = $content;

                foreach ($values as $key => $value) {
                    $newContent = str_replace('{{' . $key . '}}', $value, $newContent);
                }

                $remaining = [];
                if (preg_match_all('/\{\{([A-Z_]+)(?::([^}]+))?\}\}/', $newContent, $matches, PREG_SET_ORDER)) {
                    foreach ($matches as $match) {
                        $full = $match[0];
                        $remaining[$full] = true;
                        $unresolved[$full][] = $relative;
                    }
                }

                if ($newContent !== $content) {
                    $changed++;
                    if ($this->option('dry-run')) {
                        $this->line("[dry-run] Would resolve placeholders: {$relative}");
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

        if (!empty($unresolved)) {
            $this->warn("Unresolved placeholders after resolution:");
            foreach ($unresolved as $placeholder => $files) {
                $uniqueFiles = array_unique($files);
                $this->line("  {$placeholder} in " . count($uniqueFiles) . " file(s)");
            }
        }

        if ($errors > 0) {
            $this->error("{$errors} error(s) occurred.");
            return self::FAILURE;
        }

        if (!empty($unresolved)) {
            return self::FAILURE;
        }

        return self::SUCCESS;
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
