<?php

use App\Models\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public string $query = '';

    #[Computed]
    public function results(): Collection
    {
        if (strlen($this->query) < 2) {
            return collect();
        }

        $locale = app()->getLocale();
        $term = mb_strtolower($this->query);
        $titleExpr = 'LOWER('.$this->jsonExtract('title', $locale).')';
        $descExpr = 'LOWER('.$this->jsonExtract('description', $locale).')';

        return Page::active()
            ->where(function ($q) use ($titleExpr, $descExpr, $term) {
                $q->whereRaw("{$titleExpr} LIKE ?", ["%{$term}%"])
                    ->orWhereRaw("{$descExpr} LIKE ?", ["%{$term}%"])
                    ->orWhereRaw('LOWER(sections) LIKE ?', ["%{$term}%"]);
            })
            ->limit(10)
            ->get()
            ->map(fn (Page $page): array => [
                'title' => $page->getTranslation('title', $locale, false),
                'url' => $this->resolvePageUrl($page->id),
                'excerpt' => Str::limit($page->getTranslation('description', $locale, false), 120),
            ]);
    }

    private function jsonExtract(string $column, string $locale): string
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return "json_extract(`{$column}`, '$.".$locale."')";
        }

        return "JSON_UNQUOTE(JSON_EXTRACT(`{$column}`, '$.".$locale."'))";
    }

    private function resolvePageUrl(int $pageId): string
    {
        static $cache = [];

        if (isset($cache[$pageId])) {
            return $cache[$pageId];
        }

        $page = Page::find($pageId, ['id', 'slug', 'parent']);
        if (! $page) {
            return '/';
        }

        $slug = $page->getTranslation('slug', app()->getLocale(), false);
        $url = $slug === '/'
            ? '/'
            : ($page->parent ? rtrim($this->resolvePageUrl($page->parent), '/').'/'.$slug : '/'.$slug);

        return $cache[$pageId] = $url;
    }
}; ?>

<div class="search-inner">
    <div class="search-input-row">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="11" cy="11" r="8" />
            <line x1="21" y1="21" x2="16.65" y2="16.65" />
        </svg>
        <input
            id="search-input"
            type="search"
            placeholder="{{ __('Zoeken...') }}"
            wire:model.live.debounce.300ms="query"
            @keydown.slash.stop
            autocomplete="off"
            spellcheck="false">
        <button class="search-close" x-on:click="searchOpen = false" aria-label="{{ __('Zoeken sluiten') }}">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <line x1="18" y1="6" x2="6" y2="18" />
                <line x1="6" y1="6" x2="18" y2="18" />
            </svg>
        </button>
    </div>

    @if (strlen($query) >= 2)
        @if ($this->results->count())
            <ul class="search-results">
                @foreach ($this->results as $result)
                    <li wire:key="{{ $loop->index }}">
                        <a href="{{ $result['url'] }}" x-on:click="searchOpen = false">
                            <strong class="search-result-title">{{ $result['title'] }}</strong>
                            @if ($result['excerpt'])
                                <span class="search-result-excerpt">{{ $result['excerpt'] }}</span>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="search-no-results">{{ __('Geen resultaten gevonden.') }}</p>
        @endif
    @endif
</div>
