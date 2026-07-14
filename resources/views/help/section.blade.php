@extends('layouts.help')

@section('title', $section->name . ' | Pandoara Support')

@section('content')
    <nav class="breadcrumbs">
        <a href="{{ route('help.home') }}">Help Center</a>
        <span>›</span>
        <a href="{{ route('help.category', $section->category) }}">{{ $section->category->name }}</a>
        <span>›</span>
        <span>{{ $section->name }}</span>
    </nav>

    <div class="hc-two-col">
        <aside class="hc-sidebar">
            <h3>{{ $section->category->name }}</h3>
            <ul>
                @foreach ($section->category->sections as $sib)
                    @if (! $sib->parent_external_id)
                        <li>
                            <a class="{{ $sib->id === $section->id ? 'active' : '' }}"
                               href="{{ route('help.section', $sib) }}">{{ $sib->name }}</a>
                        </li>
                    @endif
                @endforeach
            </ul>
        </aside>

        <div class="hc-main">
            <h1>{{ $section->name }}</h1>
            @if ($section->description)
                <p class="lead">{{ $section->description }}</p>
            @endif

            @if ($section->children->count())
                <div class="section-block">
                    <h2>Subsections</h2>
                    <ul class="article-list">
                        @foreach ($section->children as $child)
                            <li><a href="{{ route('help.section', $child) }}">{{ $child->name }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="section-block">
                <ul class="article-list">
                    @foreach ($section->articles as $article)
                        <li><a href="{{ route('help.article', $article) }}">{{ $article->title }}</a></li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
@endsection
