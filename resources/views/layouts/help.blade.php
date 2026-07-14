<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Pandoara | Support')</title>
    <link rel="stylesheet" href="{{ asset('css/help.css') }}?v=1">
</head>
<body>
    <header class="header">
        <div class="logo">
            <a href="{{ route('help.home') }}"><span class="brand-mark">pandoara</span></a>
            <div class="header-separator"></div>
            <a href="{{ route('help.home') }}" class="header-title">Help Center</a>
        </div>
        <nav class="user-nav">
            <a href="#">Submit a request</a>
            <div class="cart">Cart</div>
        </nav>
    </header>

    @yield('hero')

    <main class="container">
        @yield('content')

        <section class="need-help">
            <h3>Still need help?</h3>
            <a class="button" href="#">Contact us</a>
        </section>
    </main>

    <footer class="footer">
        <span class="brand-mark">pandoara</span>
        <span>Pandoara Inc. — Content mirrored from support.moo.com for demo purposes.</span>
    </footer>
</body>
</html>
