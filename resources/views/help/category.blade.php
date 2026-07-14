@extends('layouts.help')

@section('title', $category->name . ' | Pandoara Support')

@section('content')
    <nav class="breadcrumbs">
        <a href="{{ route('help.home') }}">Help Center</a>
        <span>›</span>
        <span>{{ $category->name }}</span>
    </nav>

    <div class="hc-two-col">
        <aside class="hc-sidebar">
            <h3>Categories</h3>
            <ul>
                @foreach ($categories as $cat)
                    <li>
                        <a class="{{ $cat->id === $category->id ? 'active' : '' }}"
                           href="{{ route('help.category', $cat) }}">{{ $cat->name }}</a>
                    </li>
                @endforeach
            </ul>
        </aside>

        <div class="hc-main">
            <h1>{{ $category->name }}</h1>
            @if ($category->description)
                <p class="lead">{{ $category->description }}</p>
            @endif

            @foreach ($category->rootSections as $section)
                <div class="section-block">
                    <h2><a href="{{ route('help.section', $section) }}">{{ $section->name }}</a></h2>
                    <ul class="article-list">
                        @foreach ($section->articles->take(6) as $article)
                            <li><a href="{{ route('help.article', $article) }}">{{ $article->title }}</a></li>
                        @endforeach
                        @foreach ($section->children as $child)
                            <li><a href="{{ route('help.section', $child) }}"><strong>{{ $child->name }}</strong></a></li>
                        @endforeach
                    </ul>
                    @if ($section->articles->count() > 6)
                        <p><a href="{{ route('help.section', $section) }}">See all {{ $section->articles->count() }} articles →</a></p>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
@endsection
