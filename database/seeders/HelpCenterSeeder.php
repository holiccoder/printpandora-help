<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\Category;
use App\Models\Section;
use App\Support\ArticleMarkdown;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;

class HelpCenterSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $root = base_path(env('HELPCENTER_CONTENT_PATH', 'storage/export/articles'));
        if (!is_dir($root)) {
            $this->command->error("Content dir not found: {$root}");
            return;
        }

        $seen = ['categories' => [], 'sections' => [], 'articles' => []];

        DB::transaction(function () use ($root, &$seen) {
            $catPos = 0;
            foreach (glob($root . '/*', GLOB_ONLYDIR) as $catDir) {
                $catExtId = ArticleMarkdown::externalIdFromDirName(basename($catDir));
                if (!$catExtId) {
                    $this->command->warn("Skip category dir (no external_id): " . basename($catDir));
                    continue;
                }
                $catPos++;

                $secPos = 0;
                foreach (glob($catDir . '/*', GLOB_ONLYDIR) as $secDir) {
                    $secExtId = ArticleMarkdown::externalIdFromDirName(basename($secDir));
                    if (!$secExtId) {
                        $this->command->warn("Skip section dir (no external_id): " . basename($secDir));
                        continue;
                    }

                    $files = (new Finder)->files()->in($secDir)->name('*.md')->sortByName();
                    if (iterator_count($files) === 0) {
                        continue;
                    }
                    $secPos++;

                    $category = null;
                    $section = null;
                    $i = 0;

                    foreach ($files as $file) {
                        $parsed = ArticleMarkdown::parse($file->getContents());
                        $fm = $parsed['front_matter'];

                        if ($i === 0) {
                            $categoryName = $fm['category'] ?? basename($catDir);
                            $sectionName = $fm['section'] ?? basename($secDir);

                            $category = Category::updateOrCreate(
                                ['external_id' => $catExtId],
                                [
                                    'name' => $categoryName,
                                    'slug' => $this->uniqueSlug($categoryName, $catExtId),
                                    'locale' => $fm['locale'] ?? 'en-us',
                                    'position' => $catPos
                                ]
                            );

                            $section = Section::updateOrCreate(
                                ['external_id' => $secExtId],
                                [
                                    'category_id' => $category->id,
                                    'name' => $sectionName,
                                    'slug' => Str::slug($sectionName),
                                    'locale' => $fm['locale'] ?? 'en-us',
                                    'position' => $secPos
                                ]
                            );

                            $seen['categories'][] = $catExtId;
                            $seen['sections'][] = $secExtId;
                        }

                        $artExtId = $fm['external_id'] ?? null;
                        if (!$artExtId) {
                            $this->command->warn("Skip file: missing external_id in " . $file->getFilename());
                            continue;
                        }

                        [$html, $text] = ArticleMarkdown::render($parsed['body']);
                        Article::updateOrCreate(
                            ['external_id' => $artExtId],
                            [
                                'section_id' => $section->id,
                                'title' => $fm['title'] ?? '',
                                'slug' => $fm['slug'] ?? Str::slug($fm['title'] ?? ''),
                                'body' => $html,
                                'body_text' => $text,
                                'locale' => $fm['locale'] ?? 'en-us',
                                'position' => (int)($fm['position'] ?? 0),
                                'promoted' => false,
                                'vote_sum' => 0
                            ]
                        );
                        $seen['articles'][] = $artExtId;
                        $i++;
                    }
                }
            }
        });

        $uniqueCategories = array_unique($seen['categories']);
        $uniqueSections = array_unique($seen['sections']);
        $uniqueArticles = array_unique($seen['articles']);

        if ($this->pruneEnabled()) {
            Article::whereNotIn('external_id', $uniqueArticles)->delete();
            Section::whereNotIn('external_id', $uniqueSections)->delete();
            Category::whereNotIn('external_id', $uniqueCategories)->delete();
            $this->command->info('Pruning completed.');
        }

        $this->command->info(sprintf(
            'Seeded: %d categories, %d sections, %d articles',
            count($uniqueCategories),
            count($uniqueSections),
            count($uniqueArticles)
        ));
    }

    protected function uniqueSlug(string $name, int $externalId): string
    {
        $slug = Str::slug($name);
        if (Category::where('slug', $slug)->where('external_id', '!=', $externalId)->exists()) {
            return $slug . '-' . $externalId;
        }
        return $slug;
    }

    protected function pruneEnabled(): bool
    {
        return filter_var(env('HELPCENTER_SEED_PRUNE', false), FILTER_VALIDATE_BOOLEAN);
    }
}
