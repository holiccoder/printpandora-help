<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class DeduplicateArticles extends Command
{
    protected $signature = 'articles:deduplicate
                            {--source=storage/export/articles : Source directory relative to project root}
                            {--target=storage/export/articles_archived : Archive directory}
                            {--config=storage/export/dedup-rules.json : JSON file with deduplication rules}
                            {--dry-run : Show what would change without moving files}';

    protected $description = 'Archive duplicate articles, keeping one canonical copy.';

    public function handle(): int
    {
        $source = $this->resolvePath($this->option('source'));
        $target = $this->resolvePath($this->option('target'));
        $configPath = $this->resolvePath($this->option('config'));

        if (!is_dir($source)) {
            $this->error("Source directory does not exist: {$source}");
            return self::FAILURE;
        }

        if (!file_exists($configPath)) {
            $this->error("Config file does not exist: {$configPath}");
            return self::FAILURE;
        }

        $rules = json_decode(file_get_contents($configPath), true);
        if (!is_array($rules)) {
            $this->error("Invalid config file: {$configPath}");
            return self::FAILURE;
        }

        $fs = new Filesystem;
        if (!$this->option('dry-run') && !$fs->isDirectory($target)) {
            $fs->makeDirectory($target, 0755, true);
        }

        $archived = [];

        foreach ($rules as $rule) {
            $keepPath = $source . DIRECTORY_SEPARATOR . $rule['keep'];
            if (!file_exists($keepPath)) {
                $this->warn("Keep file not found: {$rule['keep']}");
                continue;
            }

            foreach ($rule['archive'] as $archiveFile) {
                $srcPath = $source . DIRECTORY_SEPARATOR . $archiveFile;
                if (!file_exists($srcPath)) {
                    $this->warn("Archive file not found: {$archiveFile}");
                    continue;
                }

                $destPath = $target . DIRECTORY_SEPARATOR . $archiveFile;
                if ($this->option('dry-run')) {
                    $this->line("[dry-run] Would archive duplicate: {$archiveFile} (keep: {$rule['keep']})");
                } else {
                    $parent = dirname($destPath);
                    if (!$fs->isDirectory($parent)) {
                        $fs->makeDirectory($parent, 0755, true);
                    }
                    $fs->move($srcPath, $destPath);
                }
                $archived[] = $archiveFile;
            }
        }

        $this->newLine();
        $this->info("Archived " . count($archived) . " duplicate file(s).");

        return self::SUCCESS;
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
