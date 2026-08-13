<?php

namespace NickDeKruijk\Leap\Classes;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use NickDeKruijk\Leap\Resource;
use Throwable;

/**
 * The editor's form values on a model, without saving anything.
 *
 * Two callers share this. The editor uses apply() on its way to save(), and the
 * preview controller uses applyStash() to show what is in the form right now. Both
 * go through the same code on purpose: a preview that filled fields in its own way
 * would drift from what saving does, and the drift would show up as "the preview
 * lied", which is the one thing a preview may not do.
 *
 * Media and pivot values are left alone here, as they always were: those live in
 * their own tables and only exist once syncMedia()/syncPivot() have written them.
 * So a preview shows new text beside saved images, and says so.
 */
final class RecordDraft
{
    /**
     * The session key the editor stashes its form under.
     */
    private const SESSION_KEY = 'leap.preview';

    /**
     * How long a stashed form stays usable. Long enough to click through to the tab and
     * read the page, short enough that a tab left open overnight shows the record rather
     * than yesterday's typing.
     */
    private const STASH_MINUTES = 30;

    /**
     * Write the editor's values onto the model without saving it.
     *
     * @param  Collection<int, Attribute>  $attributes  The editable attributes, indexOnly ones already dropped
     * @param  array<string, mixed>  $data  The editor's data array; normalised in place
     * @param  bool  $passwords  False to leave password fields alone, for a preview that would only hash a value nothing reads
     */
    public static function apply(Model $model, Collection $attributes, array &$data, bool $passwords = true): void
    {
        // Update each attribute
        foreach ($attributes as $attribute) {
            if ($attribute->type == 'password' && (! $passwords || ! ($data[$attribute->name] ?? null))) {
                // Ignore empty passwords
            } elseif ($attribute->type == 'password') {
                // The panel is where an administrator sets someone's password, so it
                // cannot depend on the application's model remembering to cast it.
                // A stock Laravel user model casts 'password' => 'hashed', and that
                // cast is idempotent; leave those to the model so nothing changes for
                // them, and hash here for a model that would otherwise have stored
                // the value as typed.
                $model->{$attribute->name} = ($model->getCasts()[$attribute->name] ?? null) === 'hashed'
                    ? $data[$attribute->name]
                    : Hash::make($data[$attribute->name]);
            } elseif ($attribute->type == 'media') {
                // Ignore media files
            } elseif ($attribute->type == 'pivot') {
                // Ignore pivot data
            } elseif ($attribute->type == 'sortable') {
                // Set sort value to highest current sort + 1
                if ($model->{$attribute->name} === null) {
                    $model->{$attribute->name} = $model::max($attribute->name) + 1;
                }
            } elseif ($attribute->isAccessor) {
                // Ignore accessors
            } elseif ($attribute->input == 'ace' && $attribute->options['mode'] == 'ace/mode/json') {
                $data[$attribute->name] = $data[$attribute->name] ? json_encode(json_decode($data[$attribute->name]), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : null;
                $model->{$attribute->name} = $data[$attribute->name];
            } else {
                if ($attribute->type == 'sections') {
                    // Extra treatment for each section
                    foreach ($data[$attribute->name] ?? [] as $key => $section) {
                        // Update section _view values
                        $view = collect($attribute->sections)->where('name', $section['_name'])->first()?->view;
                        if ($view) {
                            $data[$attribute->name][$key]['_view'] = $view;
                        }
                        // Set empty values to null (use strict check to preserve boolean false)
                        $data[$attribute->name][$key] = array_map(fn ($value) => $value === '' || $value === [] ? null : $value, $data[$attribute->name][$key]);
                    }
                }
                $model->{$attribute->name} = $data[$attribute->name] ?: ($attribute->type == 'checkbox' ? false : null);
            }
        }
    }

    /**
     * Stash the editor's current form for the preview tab to pick up.
     *
     * The preview is a separate request in a separate tab, so the values have to
     * travel somehow; the user's own session is the one place that is already
     * private to them and needs no key of its own. One slot, overwritten on every
     * preview, because only the record you are looking at is worth keeping.
     *
     * @param  array<string, mixed>  $data
     */
    public static function stash(string $module, int $id, array $data): void
    {
        session([self::SESSION_KEY => [
            'module' => $module,
            'id' => $id,
            'data' => $data,
            'at' => now()->timestamp,
        ]]);
    }

    /**
     * Forget the stashed form, if any.
     */
    public static function forgetStash(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    /**
     * Apply a stashed form to the record it belongs to. True if anything was applied.
     *
     * Nothing here is validated — the whole point is to show a page that is still
     * being written, and a half-typed date would fail validation while being exactly
     * what the editor wants to look at. So a value the model refuses is not an error
     * but a fallback: the saved record renders instead, and the page says the preview
     * is of the saved version.
     */
    public static function applyStash(Model $model, Resource $resource, string $module, int $id): bool
    {
        $stash = session(self::SESSION_KEY);

        if (! is_array($stash) || ($stash['module'] ?? null) !== $module || ($stash['id'] ?? null) !== $id) {
            return false;
        }

        // An expired stash is a tab left open since yesterday. Show the record as it
        // is now rather than typing nobody remembers doing.
        if (now()->timestamp - ($stash['at'] ?? 0) > self::STASH_MINUTES * 60) {
            return false;
        }

        $data = $stash['data'] ?? [];

        if (! is_array($data) || $data === []) {
            return false;
        }

        try {
            // Only the fields the stash actually carries. The editor always sends its
            // whole form, but a stash written by an older version — or by a module
            // whose attributes have changed since — must not read keys that are not
            // there and turn a preview into an error page.
            $attributes = collect($resource->attributes())
                ->where('indexOnly', false)
                ->filter(fn ($attribute): bool => array_key_exists($attribute->name, $data));

            self::apply($model, $attributes, $data, false);
        } catch (Throwable) {
            // Whatever was half applied cannot be trusted, so start over from the database.
            $model->refresh();

            return false;
        }

        return true;
    }
}
