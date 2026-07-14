@extends('layouts.help')

@section('title', 'Search: ' . $q)

@section('hero')
<section class="hero" style="padding:40px 20px;">
    <div class="hero-inner">
        <form class="search-form" action="{{ route('help.search') }}" method="get">
            <input type="search" name="query" value="{{ $q }}" placeholder="Search" aria-label="Search">
        </form>
    </div>
</section>
@endsection

@section('content')
    <nav class="breadcrumbs">
        <a href="{{ route('help.home') }}">Help Center</a>
        <span>›</span>
        <span>Search results</span>
    </nav>

    @if ($q === '')
        <p class="search-summary">Type a query above to search the help center.</p>
    @else
        <p class="search-summary">{{ $results->count() }} result{{ $results->count() === 1 ? '' : 's' }} for <strong>“{{ $q }}”</strong></p>

        @if ($results->isEmpty())
            <p>No articles matched your search. Try different keywords.</p>
        @else
            <ul class="search-results">
                @foreach ($results as $article)
                    <li>
                        <div class="crumb">
                            {{ $article->section->category->name }} · {{ $article->section->name }}
                        </div>
                        <h4><a href="{{ route('help.article', $article) }}">{{ $article->title }}</a></h4>
                        <p class="snippet">{{ \Illuminate\Support\Str::limit($article->body_text, 220) }}</p>
                    </li>
                @endforeach
            </ul>
        @endif
    @endif
@endsection
