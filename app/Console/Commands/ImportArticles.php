<?php

namespace App\Console\Commands;

use App\Models\Article;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

class ImportArticles extends Command
{
    protected $signature = 'articles:import
                            {--input=storage/export/articles : Input directory relative to project root}
                            {--dry-run : Show what would change without modifying the database}';

    protected $description = 'Import articles from Markdown files with YAML frontmatter.';

    public function handle(): int
    {
        $input = $this->resolveInputPath();

        if (!is_dir($input)) {
            $this->error("Input directory does not exist: {$input}");
            return self::FAILURE;
        }

        $files = $this->findMarkdownFiles($input);

        if (count($files) === 0) {
            $this->warn("No Markdown files found in {$input}");
            return self::SUCCESS;
        }

        $processed = 0;
        $updated = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($files as $file) {
            $processed++;
            $relative = str_replace($input . DIRECTORY_SEPARATOR, '', $file->getPathname());

            try {
                $result = $this->processFile($file->getPathname());
                if (!$result) {
                    $skipped++;
                    continue;
                }

                [$article, $changes] = $result;

                if (empty($changes)) {
                    $this->line("[up-to-date] {$relative}");
                    continue;
                }

                $this->info("[update] {$relative}");
                foreach ($changes as $field => $diff) {
                    $this->line("  - {$field}");
                }

                $updated++;

                if (!$this->option('dry-run')) {
                    $article->save();
                }
            } catch (\Throwable $e) {
                $this->error("[error] {$relative}: {$e->getMessage()}");
                $errors++;
            }
        }

        $this->newLine();
        $this->info("Processed {$processed} file(s).");

        if ($this->option('dry-run')) {
            $this->info("Dry run: {$updated} change(s) would be applied.");
        } else {
            $this->info("Updated {$updated} article(s).");
        }

        if ($skipped > 0) {
            $this->warn("Skipped {$skipped} file(s) (article not found).");
        }

        if ($errors > 0) {
            $this->error("{$errors} error(s) occurred.");
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    protected function resolveInputPath(): string
    {
        $path = (string) $this->option('input');

        if (!$this->isAbsolutePath($path)) {
            $path = base_path($path);
        }

        return rtrim($path, '\\/');
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

    protected function findMarkdownFiles(string $path): Finder
    {
        $finder = new Finder;
        $finder->files()
            ->in($path)
            ->name('*.md')
            ->sortByName();

        return $finder;
    }

    protected function processFile(string $path): ?array
    {
        $content = file_get_contents($path);
        $parsed = $this->parseFile($content);

        $externalId = $parsed['front_matter']['external_id'] ?? null;

        if (!$externalId) {
            throw new \RuntimeException('Missing external_id in frontmatter');
        }

        $article = Article::where('external_id', $externalId)->first();

        if (!$article) {
            $this->warn("Article not found for external_id {$externalId}, skipping.");
            return null;
        }

        $markdown = $parsed['body'];
        $html = Str::markdown($markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
        $bodyText = trim(preg_replace('/\s+/', ' ', strip_tags((string) $html)));

        $changes = [];

        $this->applyString($article, 'title', $parsed['front_matter']['title'] ?? $article->title, $changes);
        $this->applyString($article, 'slug', $parsed['front_matter']['slug'] ?? $article->slug, $changes);
        $this->applyString($article, 'locale', $parsed['front_matter']['locale'] ?? $article->locale, $changes);
        $this->applyString($article, 'source_url', $parsed['front_matter']['source_url'] ?? $article->source_url, $changes);
        $this->applyInteger($article, 'position', $parsed['front_matter']['position'] ?? $article->position, $changes);
        $this->applyInteger($article, 'vote_sum', $parsed['front_matter']['vote_sum'] ?? $article->vote_sum, $changes);
        $this->applyBoolean($article, 'promoted', $parsed['front_matter']['promoted'] ?? $article->promoted, $changes);

        $this->applyString($article, 'body', $html, $changes);
        $this->applyString($article, 'body_text', $bodyText, $changes);

        return [$article, $changes];
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

    protected function applyString(Article $article, string $field, mixed $value, array &$changes): void
    {
        if ($value === null) {
            return;
        }

        $value = (string) $value;
        $current = (string) $article->getAttribute($field);

        if ($current !== $value) {
            $changes[$field] = true;
            $article->setAttribute($field, $value);
        }
    }

    protected function applyInteger(Article $article, string $field, mixed $value, array &$changes): void
    {
        if ($value === null) {
            return;
        }

        $value = (int) $value;
        $current = (int) $article->getAttribute($field);

        if ($current !== $value) {
            $changes[$field] = true;
            $article->setAttribute($field, $value);
        }
    }

    protected function applyBoolean(Article $article, string $field, mixed $value, array &$changes): void
    {
        if ($value === null) {
            return;
        }

        $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
        $current = (bool) $article->getAttribute($field);

        if ($current !== $value) {
            $changes[$field] = true;
            $article->setAttribute($field, $value);
        }
    }
}
