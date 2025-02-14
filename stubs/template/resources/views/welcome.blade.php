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
        <article class="article">
            <h1>nickdekruijk/leap-template</h1>
            <p>
                This is my minimalistic <a href="https://laravel.com" target="_blank" rel="noopener">Laravel</a> blade template I use when I start a new project.
            </p>

            <h2>Goals</h2>
            <ul>
                <li>Clean semantic html code;</li>
                <li>Only a few css classes to keep it readable;</li>
                <li>Follow accessibility best practices;</li>
                <li>Easy to understand and implement.</li>
            </ul>

            <h3>Alpine JS</h3>
            <p>
                <a href="https://alpinejs.dev" target="_blank" rel="noopener">Alpine JS</a> is used for the navigation menu. Since my projects usually require livewire it's included anyway. It does make the navigation html part a bit less readable but I prefer this over separate custom javascript. And while it can be done without javascript for accessibility reasons it's mandatory.
            </p>

            <h4>Hope you enjoy it!</h4>
            <p>
                Greetings,<br>
                <a href="https://nickdekruijk.nl" target="_blank" rel="noopener">Nick de Kruijk</a>
                <a href="https://amphora.nl" target="_blank" rel="noopener">amphora.interactive</a>
            </p>

            <h5>Some demos</h5>

            <h6>Buttons</h6>
            <p>
                <button>Button</button>
                <button class="outline">Outline</button>
                <a href="" role="button" class="secondary">Secondary</a>
                <a href="" role="button" class="secondary outline">Secondary outline</a>
                <button class="contrast">Contrast</button>
                <button class="contrast outline">Contrast outline</button>
            </p>

            <h6>Accordions</h6>
            <details name="faq" open>
                <summary>Accordion 1</summary>
                <p>Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry's standard dummy text ever since the 1500s, when an unknown printer took a galley of type and scrambled it to make a type specimen book. It has survived not only five centuries, but also the leap into electronic typesetting, remaining essentially unchanged. It was popularised in the 1960s with the release of Letraset sheets containing Lorem Ipsum passages, and more recently with desktop publishing software like Aldus PageMaker including versions of Lorem Ipsum.</p>
                <p>Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry's standard dummy text ever since the 1500s, when an unknown printer took a galley of type and scrambled it to make a type specimen book. It has survived not only five centuries, but also the leap into electronic typesetting, remaining essentially unchanged. It was popularised in the 1960s with the release of Letraset sheets containing Lorem Ipsum passages, and more recently with desktop publishing software like Aldus PageMaker including versions of Lorem Ipsum.</p>
                <ul>
                    <li>Lorum ipsum dolor sit amet</li>
                    <li>Lorum ipsum dolor sit amet <ol>
                            <li>Lorum ipsum dolor sit amet</li>
                            <li>Lorum ipsum dolor sit amet</li>
                        </ol>
                    </li>
                </ul>
                <ol>
                    <li>Lorum ipsum dolor sit amet</li>
                    <li>Lorum ipsum dolor sit amet</li>
                </ol>
            </details>
            <hr />
            <details name="faq">
                <summary>Accordion 2</summary>
                <ul>
                    <li>Lorum ipsum dolor sit amet</li>
                    <li>Lorum ipsum dolor sit amet</li>
                </ul>
            </details>
            <hr />
            <details name="faq">
                <summary>Accordion 3</summary>
                <p>Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry's standard dummy text ever since the 1500s, when an unknown printer took a galley of type and scrambled it to make a type specimen book. It has survived not only five centuries, but also the leap into electronic typesetting, remaining essentially unchanged. It was popularised in the 1960s with the release of Letraset sheets containing Lorem Ipsum passages, and more recently with desktop publishing software like Aldus PageMaker including versions of Lorem Ipsum.</p>
            </details>

        </article>
    </main>
@endsection
