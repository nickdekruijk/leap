<?php

namespace App\Livewire;

use App\Models\Page;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Live site search. A plain Livewire class component (not a single-file/Volt
 * component) so it works on both Livewire 3 and 4. Mounted as <livewire:search />.
 */
class Search extends Component
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

    public function render(): View
    {
        return view('livewire.search');
    }
}
