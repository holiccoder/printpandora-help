<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Finder\Finder;

class ReplaceImagePlaceholders extends Command
{
    protected $signature = 'articles:replace-image-placeholders
                            {--input=storage/export/articles : Input directory relative to project root}
                            {--dry-run : Show what would change without modifying files}';

    protected $description = 'Replace MOO image and asset URLs with placeholders.';

    public function handle(): int
    {
        $input = $this->resolvePath($this->option('input'));

        if (!is_dir($input)) {
            $this->error("Input directory does not exist: {$input}");
            return self::FAILURE;
        }

        $finder = new Finder;
        $finder->files()->in($input)->name('*.md')->sortByName();

        $processed = 0;
        $changed = 0;
        $errors = 0;
        $attachmentCount = 0;
        $templateCount = 0;

        foreach ($finder as $file) {
            $processed++;
            $relative = $file->getRelativePathname();

            try {
                $content = file_get_contents($file->getPathname());
                $newContent = $content;

                $newContent = preg_replace_callback(
                    '/https:\/\/\{\{HELP_DOMAIN\}\}\/hc\/article_attachments\/(\d+)(?:\/[^\s\)\"\'\]]+)?/',
                    function ($matches) use (&$attachmentCount) {
                        $attachmentCount++;
                        return '{{IMAGE_PENDING:' . $matches[1] . '}}';
                    },
                    $newContent,
                    -1,
                );

                $newContent = preg_replace_callback(
                    '/https:\/\/\{\{TEMPLATE_ASSET_DOMAIN\}\}(\/v3\/assets\/[^\s\)\"\'\]]+)/',
                    function ($matches) use (&$templateCount) {
                        $templateCount++;
                        $path = urldecode($matches[1]);
                        $filename = basename($path);
                        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);

                        return '{{TEMPLATE_PENDING:' . $safeName . '}}';
                    },
                    $newContent,
                    -1,
                );

                $newContent = preg_replace(
                    '/\{\{IMAGE_PENDING:(\d+)\}\}\/[^\s\)\"\'\]]+/',
                    '{{IMAGE_PENDING:$1}}',
                    $newContent,
                    -1,
                    $cleanupCount,
                );
                $attachmentCount += $cleanupCount;

                if ($newContent !== $content) {
                    $changed++;
                    if ($this->option('dry-run')) {
                        $this->line("[dry-run] Would replace images: {$relative}");
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
        $this->info("Article attachment placeholders: {$attachmentCount}");
        $this->info("Template download placeholders: {$templateCount}");

        if ($errors > 0) {
            $this->error("{$errors} error(s) occurred.");
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
