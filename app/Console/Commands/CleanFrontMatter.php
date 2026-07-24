<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

class CleanFrontMatter extends Command
{
    protected $signature = 'articles:clean-frontmatter
                            {--input=storage/export/articles : Input directory relative to project root}
                            {--keep=title,slug,external_id,locale,position,category,section : Comma-separated frontmatter keys to keep}
                            {--dry-run : Show what would change without modifying files}';

    protected $description = 'Clean YAML frontmatter in Markdown articles, keeping only specified fields.';

    public function handle(): int
    {
        $input = $this->resolvePath($this->option('input'));
        $keep = array_map('trim', explode(',', (string) $this->option('keep')));

        if (!is_dir($input)) {
            $this->error("Input directory does not exist: {$input}");
            return self::FAILURE;
        }

        $finder = new Finder;
        $finder->files()->in($input)->name('*.md')->sortByName();

        $processed = 0;
        $changed = 0;
        $errors = 0;

        foreach ($finder as $file) {
            $processed++;
            $relative = $file->getRelativePathname();

            try {
                $content = file_get_contents($file->getPathname());
                $parsed = $this->parseFile($content);

                $newFrontMatter = [];
                foreach ($keep as $key) {
                    if (array_key_exists($key, $parsed['front_matter'])) {
                        $newFrontMatter[$key] = $parsed['front_matter'][$key];
                    }
                }

                $newYaml = Yaml::dump($newFrontMatter, 2, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
                $newContent = "---\n" . $newYaml . "---\n\n" . $parsed['body'];
                $newContent = $this->normalizeMarkdown($newContent);

                if ($newContent !== $this->normalizeMarkdown($content)) {
                    $changed++;
                    if ($this->option('dry-run')) {
                        $this->line("[dry-run] Would clean frontmatter: {$relative}");
                    } else {
                        file_put_contents($file->getPathname(), $newContent);
                    }
                }
            } catch (\Throwable $e) {
                $this->error("[error] {$relative}: {$e->getMessage()}");
                $errors++;
            }
        }

        $this->newLine();
        $this->info("Processed {$processed} file(s), cleaned {$changed} frontmatter block(s).");

        if ($errors > 0) {
            $this->error("{$errors} error(s) occurred.");
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    protected function parseFile(string $content): array
    {
        $content = preg_replace('/\r\n?/', "\n", $content);

        if (!str_starts_with($content, "---\n")) {
            return ['front_matter' => [], 'body' => $content];
        }

        $end = strpos($content, "\n---", 4);
        if ($end === false) {
            return ['front_matter' => [], 'body' => $content];
        }

        $yaml = substr($content, 4, $end - 4);
        $body = substr($content, $end + 5);
        $body = ltrim($body, "\n");

        return [
            'front_matter' => Yaml::parse($yaml) ?: [],
            'body' => $body,
        ];
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
