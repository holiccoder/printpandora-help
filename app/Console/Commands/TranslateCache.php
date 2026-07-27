<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\Category;
use App\Models\Section;
use App\Support\Translator;
use Illuminate\Console\Command;

class TranslateCache extends Command
{
    protected $signature = 'articles:translate-cache';

    protected $description = 'Pre-translate and warm up the Chinese translation cache for all categories, sections, and articles.';

    public function handle(): int
    {
        set_time_limit(0);
        $this->info('Starting translation cache warmup for Chinese (zh-cn)...');

        // 1. Categories
        $categories = Category::all();
        $this->info('Translating ' . $categories->count() . ' categories...');
        foreach ($categories as $index => $category) {
            $name = $category->getRawOriginal('name');
            $desc = $category->getRawOriginal('description');
            
            $this->line('  Category ' . ($index + 1) . '/' . $categories->count() . ': ' . ($name ?? ''));
            
            if ($name !== null && $name !== '') {
                Translator::translate($name);
            }
            if ($desc !== null && $desc !== '') {
                Translator::translate($desc);
            }
        }

        // 2. Sections
        $sections = Section::all();
        $this->info('Translating ' . $sections->count() . ' sections...');
        foreach ($sections as $index => $section) {
            $name = $section->getRawOriginal('name');
            $desc = $section->getRawOriginal('description');

            $this->line('  Section ' . ($index + 1) . '/' . $sections->count() . ': ' . ($name ?? ''));
            
            if ($name !== null && $name !== '') {
                Translator::translate($name);
            }
            if ($desc !== null && $desc !== '') {
                Translator::translate($desc);
            }
        }

        // 3. Articles
        $articles = Article::all();
        $this->info('Translating ' . $articles->count() . ' articles (this might take a few minutes as we safely space out API calls to respect rate limits)...');
        foreach ($articles as $index => $article) {
            $title = $article->getRawOriginal('title');
            $body = $article->getRawOriginal('body');
            $body_text = $article->getRawOriginal('body_text');

            $this->line('  Article ' . ($index + 1) . '/' . $articles->count() . ': ' . ($title ?? ''));
            
            if ($title !== null && $title !== '') {
                Translator::translate($title);
            }
            if ($body !== null && $body !== '') {
                Translator::translate($body);
            }
            if ($body_text !== null && $body_text !== '') {
                Translator::translate($body_text);
            }

            // Sleep 100ms to respect rate limits
            usleep(100000);
        }

        $this->info('Successfully warmed up all Chinese translations!');
        return self::SUCCESS;
    }
}
