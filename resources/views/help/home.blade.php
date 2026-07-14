@extends('layouts.help')

@section('title', 'Pandoara | Support')

@section('hero')
<section class="hero">
    <div class="hero-inner">
        <div class="text-hero">How can we help you?</div>
        <form class="search-form" action="{{ route('help.search') }}" method="get">
            <input type="search" name="query" placeholder="Search" aria-label="Search">
        </form>
    </div>
</section>
@endsection

@section('content')
    <section class="categories">
        <div class="section-heading">All topics</div>
        <ul class="blocks-list">
            @foreach ($categories as $category)
                <li class="blocks-item">
                    <a href="{{ route('help.category', $category) }}">
                        <div class="icon">{{ strtoupper(substr($category->name, 0, 1)) }}</div>
                        <h4>{{ $category->name }}</h4>
                        <p class="desc">{{ $category->description }}</p>
                    </a>
                </li>
            @endforeach
        </ul>
    </section>

    <section class="popular">
        <h1>Popular Articles</h1>
        <ul class="articleList">
            @foreach ($popular as $article)
                <li>
                    <a href="{{ route('help.article', $article) }}">{{ $article->title }}</a>
                </li>
            @endforeach
        </ul>
    </section>
@endsection
