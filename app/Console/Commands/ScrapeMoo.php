<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\Category;
use App\Models\Section;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ScrapeMoo extends Command
{
    protected $signature = 'scrape:moo {--locale=en-us} {--base=https://support.moo.com} {--pause=200 : Milliseconds pause between requests}';

    protected $description = 'Scrape MOO Help Center (Zendesk) categories, sections and articles into the local database.';

    public function handle(): int
    {
        $base = rtrim((string) $this->option('base'), '/');
        $locale = (string) $this->option('locale');
        $pause = (int) $this->option('pause');

        $this->info("Scraping {$base} ({$locale})");

        $this->scrapeCategories($base, $locale);
        $this->scrapeSections($base, $locale, $pause);
        $this->scrapeArticles($base, $locale, $pause);

        $this->info('Done.');
        return self::SUCCESS;
    }

    protected function scrapeCategories(string $base, string $locale): void
    {
        $url = "{$base}/api/v2/help_center/{$locale}/categories.json?per_page=100";
        $this->line("GET {$url}");

        $data = $this->getJson($url);
        foreach ($data['categories'] ?? [] as $cat) {
            Category::updateOrCreate(
                ['external_id' => $cat['id']],
                [
                    'name' => $cat['name'],
                    'slug' => $this->slugFromUrl($cat['html_url']) ?: Str::slug($cat['name']),
                    'description' => $cat['description'] ?? null,
                    'locale' => $cat['locale'] ?? $locale,
                    'position' => $cat['position'] ?? 0,
                    'source_url' => $cat['html_url'] ?? null,
                ]
            );
        }
        $this->info('Categories: ' . Category::count());
    }

    protected function scrapeSections(string $base, string $locale, int $pause): void
    {
        $url = "{$base}/api/v2/help_center/{$locale}/sections.json?per_page=100";
        $page = 1;
        do {
            $pageUrl = $url . "&page={$page}";
            $this->line("GET {$pageUrl}");
            $data = $this->getJson($pageUrl);

            foreach ($data['sections'] ?? [] as $sec) {
                $category = Category::where('external_id', $sec['category_id'])->first();
                if (!$category) {
                    continue;
                }
                Section::updateOrCreate(
                    ['external_id' => $sec['id']],
                    [
                        'category_id' => $category->id,
                        'parent_external_id' => $sec['parent_section_id'] ?? null,
                        'name' => $sec['name'],
                        'slug' => $this->slugFromUrl($sec['html_url']) ?: Str::slug($sec['name']),
                        'description' => $sec['description'] ?? null,
                        'locale' => $sec['locale'] ?? $locale,
                        'position' => $sec['position'] ?? 0,
                        'source_url' => $sec['html_url'] ?? null,
                    ]
                );
            }
            usleep($pause * 1000);
            $page++;
        } while (!empty($data['next_page']));

        $this->info('Sections: ' . Section::count());
    }

    protected function scrapeArticles(string $base, string $locale, int $pause): void
    {
        $url = "{$base}/api/v2/help_center/{$locale}/articles.json?per_page=100";
        $page = 1;
        $total = 0;
        do {
            $pageUrl = $url . "&page={$page}";
            $this->line("GET {$pageUrl}");
            $data = $this->getJson($pageUrl);

            foreach ($data['articles'] ?? [] as $art) {
                $section = Section::where('external_id', $art['section_id'])->first();
                if (!$section) {
                    continue;
                }
                $body = $art['body'] ?? '';
                Article::updateOrCreate(
                    ['external_id' => $art['id']],
                    [
                        'section_id' => $section->id,
                        'title' => $art['title'] ?? '',
                        'slug' => $this->slugFromUrl($art['html_url']) ?: Str::slug($art['title'] ?? ''),
                        'body' => $body,
                        'body_text' => trim(preg_replace('/\s+/', ' ', strip_tags((string) $body))),
                        'locale' => $art['locale'] ?? $locale,
                        'position' => $art['position'] ?? 0,
                        'promoted' => (bool) ($art['promoted'] ?? false),
                        'vote_sum' => (int) ($art['vote_sum'] ?? 0),
                        'source_url' => $art['html_url'] ?? null,
                        'remote_created_at' => $art['created_at'] ?? null,
                        'remote_updated_at' => $art['updated_at'] ?? null,
                    ]
                );
                $total++;
            }
            usleep($pause * 1000);
            $page++;
        } while (!empty($data['next_page']));

        $this->info("Articles imported: {$total}");
    }

    protected function getJson(string $url): array
    {
        $res = Http::withHeaders([
            'Accept' => 'application/json',
            'User-Agent' => 'PandoaraHelpBot/1.0 (+local)',
        ])->timeout(30)->retry(3, 500)->get($url);

        if (!$res->successful()) {
            $this->error("Request failed: {$url} => HTTP {$res->status()}");
            return [];
        }
        return $res->json() ?? [];
    }

    protected function slugFromUrl(?string $url): ?string
    {
        if (!$url) return null;
        $path = parse_url($url, PHP_URL_PATH) ?: '';
        $segments = array_values(array_filter(explode('/', $path)));
        return end($segments) ?: null;
    }
}
