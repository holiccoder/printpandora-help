<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\Section;
use Database\Seeders\HelpCenterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class HelpCenterSeederTest extends TestCase
{
    use RefreshDatabase;

    protected string $fixturesPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixturesPath = 'tests/fixtures/helpcenter_test';
        $this->cleanUpFixtures();

        // Ensure directories exist
        File::makeDirectory(base_path($this->fixturesPath), 0755, true, true);
        
        // Set environment variable for the Seeder
        putenv("HELPCENTER_CONTENT_PATH={$this->fixturesPath}");
    }

    protected function tearDown(): void
    {
        $this->cleanUpFixtures();
        putenv('HELPCENTER_CONTENT_PATH'); // Reset env
        parent::tearDown();
    }

    protected function cleanUpFixtures(): void
    {
        if (File::isDirectory(base_path($this->fixturesPath))) {
            File::deleteDirectory(base_path($this->fixturesPath));
        }
    }

    protected function createMockMarkdown(string $relativePath, array $frontMatter, string $body): void
    {
        $fullPath = base_path($this->fixturesPath . '/' . $relativePath);
        $directory = dirname($fullPath);

        if (!File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true, true);
        }

        $fmString = "---\n";
        foreach ($frontMatter as $key => $val) {
            $fmString .= "{$key}: '{$val}'\n";
        }
        $fmString .= "---\n";

        File::put($fullPath, $fmString . "\n" . $body);
    }

    protected function setupStandardFixtures(): void
    {
        // 2 categories / 3 sections / 5 articles
        
        // Category 1, Section 1, Articles 1 & 2
        $this->createMockMarkdown(
            '1001-Category-One/2001-Section-One/3001-Article-One.md',
            [
                'title' => 'Article One',
                'slug' => '3001-Article-One',
                'external_id' => 3001,
                'locale' => 'en-us',
                'position' => 1,
                'category' => 'Category One',
                'section' => 'Section One',
            ],
            'Body of **Article One**.'
        );

        $this->createMockMarkdown(
            '1001-Category-One/2001-Section-One/3002-Article-Two.md',
            [
                'title' => 'Article Two',
                'slug' => '3002-Article-Two',
                'external_id' => 3002,
                'locale' => 'en-us',
                'position' => 2,
                'category' => 'Category One',
                'section' => 'Section One',
            ],
            'Body of Article Two.'
        );

        // Category 1, Section 2, Article 3
        $this->createMockMarkdown(
            '1001-Category-One/2002-Section-Two/3003-Article-Three.md',
            [
                'title' => 'Article Three',
                'slug' => '3003-Article-Three',
                'external_id' => 3003,
                'locale' => 'en-us',
                'position' => 1,
                'category' => 'Category One',
                'section' => 'Section Two',
            ],
            'Body of Article Three.'
        );

        // Category 2, Section 3, Articles 4 & 5
        $this->createMockMarkdown(
            '1002-Category-Two/2003-Section-Three/3004-Article-Four.md',
            [
                'title' => 'Article Four',
                'slug' => '3004-Article-Four',
                'external_id' => 3004,
                'locale' => 'en-us',
                'position' => 1,
                'category' => 'Category Two',
                'section' => 'Section Three',
            ],
            'Body of Article Four.'
        );

        $this->createMockMarkdown(
            '1002-Category-Two/2003-Section-Three/3005-Article-Five.md',
            [
                'title' => 'Article Five',
                'slug' => '3005-Article-Five',
                'external_id' => 3005,
                'locale' => 'en-us',
                'position' => 2,
                'category' => 'Category Two',
                'section' => 'Section Three',
            ],
            'Body of Article Five.'
        );
    }

    public function test_seeding_creates_categories_sections_and_articles(): void
    {
        $this->setupStandardFixtures();

        $this->seed(HelpCenterSeeder::class);

        // Assert database counts
        $this->assertEquals(2, Category::count());
        $this->assertEquals(3, Section::count());
        $this->assertEquals(5, Article::count());

        // Assert a specific category structure
        $category = Category::where('external_id', 1001)->first();
        $this->assertNotNull($category);
        $this->assertEquals('Category One', $category->name);
        $this->assertEquals('category-one', $category->slug);

        // Assert section relations
        $section = Section::where('external_id', 2001)->first();
        $this->assertNotNull($section);
        $this->assertEquals('Section One', $section->name);
        $this->assertEquals($category->id, $section->category_id);

        // Assert article content and rendering
        $article = Article::where('external_id', 3001)->first();
        $this->assertNotNull($article);
        $this->assertEquals($section->id, $article->section_id);
        $this->assertEquals('Article One', $article->title);
        $this->assertEquals('3001-Article-One', $article->slug);
        $this->assertStringContainsString('<p>Body of <strong>Article One</strong>.</p>', $article->body);
        $this->assertEquals('Body of Article One.', $article->body_text);
    }

    public function test_seeding_is_idempotent(): void
    {
        $this->setupStandardFixtures();

        // Run first time
        $this->seed(HelpCenterSeeder::class);
        $this->assertEquals(2, Category::count());
        $this->assertEquals(3, Section::count());
        $this->assertEquals(5, Article::count());

        // Run second time
        $this->seed(HelpCenterSeeder::class);
        $this->assertEquals(2, Category::count());
        $this->assertEquals(3, Section::count());
        $this->assertEquals(5, Article::count());
    }

    public function test_seeding_with_prune_removes_orphans(): void
    {
        $this->setupStandardFixtures();

        // Initial seed
        $this->seed(HelpCenterSeeder::class);
        $this->assertEquals(5, Article::count());

        // Remove article 5 from disk
        File::delete(base_path("{$this->fixturesPath}/1002-Category-Two/2003-Section-Three/3005-Article-Five.md"));

        // Run seed without prune (default false)
        putenv('HELPCENTER_SEED_PRUNE=false');
        $this->seed(HelpCenterSeeder::class);
        $this->assertEquals(5, Article::count(), 'Article should not be pruned when HELPCENTER_SEED_PRUNE is false');

        // Run seed with prune enabled
        putenv('HELPCENTER_SEED_PRUNE=true');
        $this->seed(HelpCenterSeeder::class);
        $this->assertEquals(4, Article::count(), 'Article should be pruned when HELPCENTER_SEED_PRUNE is true');
        $this->assertFalse(Article::where('external_id', 3005)->exists());
    }
}
