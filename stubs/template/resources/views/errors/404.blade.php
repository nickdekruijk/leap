@extends('layouts.app')

@section('content')
    <main>
        <a name="main"></a>
        <div class="main-width">
            <article class="article">
                <br><br><br>
                <h1>4 oh 4</h1>
                <p>Helaas, deze pagina bestaat niet (meer).</p>
                <a href="/" role="button">Terug naar homepage</a>
            </article>
        </div>
    </main>
@endsection

@php(Log::warning('404: ' . request()->method() . ' ' . request()->url(), ['referer' => request()->headers->get('referer'), 'previous' => url()->previous(), 'agent' => request()->header('user-agent'), 'ip' => request()->ip()]))
