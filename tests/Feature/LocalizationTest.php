<?php

namespace Tests\Feature;

use App\Support\Translator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Pre-seed some translations in the cache to avoid real network requests during testing
        $cachePath = storage_path('app/translations_cache.json');
        $dir = dirname($cachePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $cache = [
            md5('Hello') => '你好',
            md5('How can we help you?') => '我们能为您做些什么？',
        ];

        file_put_contents($cachePath, json_encode($cache, JSON_UNESCAPED_UNICODE));
    }

    public function test_language_switcher_route_works(): void
    {
        $response = $this->get('/lang/zh-cn');
        $response->assertStatus(302); // Redirect back
        $this->assertEquals('zh-cn', session('locale'));
    }

    public function test_ui_labels_translate_on_chinese_locale(): void
    {
        $response = $this->withSession(['locale' => 'zh-cn'])->get('/');
        $response->assertStatus(200);
        $response->assertSee('帮助中心');
        $response->assertSee('我们能为您做些什么？');
        $response->assertSee('热门文章');
    }

    public function test_translator_caches_and_translates_text(): void
    {
        $translated = Translator::translate('Hello');
        $this->assertEquals('你好', $translated);
    }
}
