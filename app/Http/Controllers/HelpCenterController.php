<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Category;
use App\Models\Section;
use Illuminate\Http\Request;

class HelpCenterController extends Controller
{
    public function home()
    {
        $categories = Category::orderBy('position')->get();
        $popular = Article::orderByDesc('vote_sum')
            ->orderByDesc('promoted')
            ->limit(8)
            ->get();

        return view('help.home', compact('categories', 'popular'));
    }

    public function category(Category $category)
    {
        $category->load(['rootSections.articles', 'rootSections.children.articles']);
        $categories = Category::orderBy('position')->get();

        return view('help.category', compact('category', 'categories'));
    }

    public function section(Section $section)
    {
        $section->load(['category', 'articles', 'children.articles']);
        $categories = Category::orderBy('position')->get();

        return view('help.section', compact('section', 'categories'));
    }

    public function article(Article $article)
    {
        $article->load('section.category');
        $related = Article::where('section_id', $article->section_id)
            ->where('id', '!=', $article->id)
            ->orderBy('position')
            ->limit(6)
            ->get();

        return view('help.article', compact('article', 'related'));
    }

    public function search(Request $request)
    {
        $q = trim((string) $request->query('query', ''));
        $results = collect();
        if ($q !== '') {
            $results = Article::search($q)
                ->with('section.category')
                ->limit(50)
                ->get();
        }

        return view('help.search', compact('q', 'results'));
    }
}
