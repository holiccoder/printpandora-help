<?php

namespace App\Console\Commands;

use App\Models\Article;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use League\HTMLToMarkdown\HtmlConverter;
use Symfony\Component\Yaml\Yaml;

class ExportArticles extends Command
{
    protected $signature = 'articles:export
                            {--output=storage/export/articles : Output directory relative to project root}';

    protected $description = 'Export all articles to Markdown files with YAML frontmatter.';

    public function handle(): int
    {
        $output = $this->resolveOutputPath();

        if (!$this->prepareOutputDirectory($output)) {
            return self::FAILURE;
        }

        $converter = new HtmlConverter([
            'strip_tags' => true,
            'suppress_errors' => true,
            'hard_break' => false,
        ]);

        $exported = 0;
        $errors = 0;

        Article::with('section.category')
            ->orderBy('id')
            ->chunk(100, function ($articles) use ($output, $converter, &$exported, &$errors) {
                foreach ($articles as $article) {
                    try {
                        $this->exportArticle($article, $output, $converter);
                        $exported++;
                    } catch (\Throwable $e) {
                        $this->error("Failed to export article {$article->id}: {$e->getMessage()}");
                        $errors++;
                    }
                }
            });

        $this->info("Exported {$exported} article(s) to {$output}");

        if ($errors > 0) {
            $this->error("{$errors} error(s) occurred.");
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    protected function resolveOutputPath(): string
    {
        $path = (string) $this->option('output');

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

    protected function prepareOutputDirectory(string $path): bool
    {
        $fs = new Filesystem;

        if ($fs->isDirectory($path)) {
            $existing = $fs->allFiles($path);
            if (count($existing) > 0) {
                $this->warn('Output directory is not empty.');
                if (!$this->confirm('Delete existing files before exporting?', true)) {
                    $this->line('Export cancelled.');
                    return false;
                }
                $fs->cleanDirectory($path);
            }
        } else {
            $fs->makeDirectory($path, 0755, true);
        }

        return true;
    }

    protected function exportArticle(Article $article, string $basePath, HtmlConverter $converter): void
    {
        $category = $article->section->category;
        $section = $article->section;

        $directory = implode(DIRECTORY_SEPARATOR, [
            $basePath,
            $this->safeFilename($category->slug ?: Str::slug($category->name)),
            $this->safeFilename($section->slug ?: Str::slug($section->name)),
        ]);

        $filename = $this->safeFilename($article->slug) . '.md';
        $path = $directory . DIRECTORY_SEPARATOR . $filename;

        $fs = new Filesystem;
        if (!$fs->isDirectory($directory)) {
            $fs->makeDirectory($directory, 0755, true);
        }

        $frontMatter = $this->buildFrontMatter($article);
        $markdown = $converter->convert((string) $article->body);
        $markdown = $this->normalizeMarkdown($markdown);

        $content = "---\n" . $frontMatter . "---\n\n" . $markdown . "\n";

        $fs->put($path, $content);
    }

    protected function buildFrontMatter(Article $article): string
    {
        $data = [
            'external_id' => (int) $article->external_id,
            'title' => $article->title,
            'slug' => $article->slug,
            'section_external_id' => $article->section ? (int) $article->section->external_id : null,
            'category' => $article->section?->category?->name,
            'section' => $article->section?->name,
            'locale' => $article->locale,
            'position' => (int) $article->position,
            'promoted' => (bool) $article->promoted,
            'vote_sum' => (int) $article->vote_sum,
            'source_url' => $article->source_url,
            'remote_created_at' => $article->remote_created_at?->toIso8601String(),
            'remote_updated_at' => $article->remote_updated_at?->toIso8601String(),
        ];

        return Yaml::dump($data, 2, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
    }

    protected function normalizeMarkdown(string $markdown): string
    {
        $markdown = preg_replace('/\r\n?/', "\n", $markdown);

        return rtrim($markdown) . "\n";
    }

    protected function safeFilename(string $name): string
    {
        $name = preg_replace('/[^\w\-_.~\s]/u', '-', $name);
        $name = preg_replace('/\-+/', '-', $name);

        return trim($name, '-');
    }
}
