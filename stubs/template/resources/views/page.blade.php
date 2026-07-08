@extends('layouts.app')

@section('content')
    <main id="main">
        @foreach ($page->sections()->where('active', true) as $section)
            @include($section->_view ?? 'sections.' . $section->_name)
        @endforeach
    </main>
@endsection
