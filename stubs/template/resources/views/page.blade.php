@extends('layouts.app')

@section('content')
    <nav aria-label="breadcrumb" class="main-width">
        <ul>
            <li><a href="">Home</a></li>
            <li><a href="">About</a></li>
            <li>Welcome</li>
        </ul>
    </nav>
    <main class="main-width">
        <a name="main"></a>
        @isset($page->body)
            <article class="article">
                {!! $page->body !!}
            </article>
        @endisset
        @foreach ($page->sections() as $section)
            @include('sections.' . $section->_name)
        @endforeach
    </main>
@endsection
