@extends('layouts.help')

@section('title', $article->title . ' | Pandoara Support')

@section('content')
    <nav class="breadcrumbs">
        <a href="{{ route('help.home') }}">Help Center</a>
        <span>›</span>
        <a href="{{ route('help.category', $article->section->category) }}">{{ $article->section->category->name }}</a>
        <span>›</span>
        <a href="{{ route('help.section', $article->section) }}">{{ $article->section->name }}</a>
    </nav>

    <div class="hc-two-col">
        <aside class="hc-sidebar">
            <h3>{{ $article->section->name }}</h3>
            <ul>
                @foreach ($article->section->articles as $sib)
                    <li>
                        <a class="{{ $sib->id === $article->id ? 'active' : '' }}"
                           href="{{ route('help.article', $sib) }}">{{ $sib->title }}</a>
                    </li>
                @endforeach
            </ul>
        </aside>

        <div class="hc-main">
            <article class="article-body">
                <h1>{{ $article->title }}</h1>
                {!! $article->body !!}
            </article>

            @if ($related->count())
                <div class="related-articles">
                    <h3>Related articles</h3>
                    <ul>
                        @foreach ($related as $r)
                            <li><a href="{{ route('help.article', $r) }}">{{ $r->title }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>
@endsection
