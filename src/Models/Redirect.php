<?php

namespace NickDeKruijk\Leap\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use NickDeKruijk\Leap\Classes\Redirects;

class Redirect extends Model
{
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('leap.table_prefix').'redirects');
    }

    protected $fillable = [
        'path',
        'destination',
        'status',
        'active',
        'hits',
        'last_used_at',
    ];

    protected $casts = [
        'status' => 'integer',
        'active' => 'boolean',
        'wildcard' => 'boolean',
        'hits' => 'integer',
        'last_used_at' => 'datetime',
    ];

    /**
     * Normalize the path and derive the wildcard flag from it, wherever the row
     * came from: the editor, a CSV import, or the 404 capture. Matching compares
     * against the stored value, so this is what makes "/Oud/" and "oud" the same
     * rule instead of two.
     */
    protected static function booted(): void
    {
        static::saving(function (Redirect $redirect): void {
            $redirect->path = static::normalizePath((string) $redirect->path);
            $redirect->wildcard = str_ends_with($redirect->path, '/*');
            $redirect->destination = static::normalizeDestination($redirect->destination);

            // The panel validates uniqueness against what was typed, which is not yet
            // what will be stored: /Praktijk/ and praktijk pass that check separately
            // and then meet each other on the unique index. Said here it is a field
            // error on the form; left to the database it is a 500.
            $clash = static::query()
                ->where('path', $redirect->path)
                ->when($redirect->exists, fn ($query) => $query->whereKeyNot($redirect->getKey()))
                ->exists();

            if ($clash) {
                throw ValidationException::withMessages([
                    'data.path' => __('leap::redirects.path_taken', ['path' => $redirect->path]),
                ]);
            }
        });

        // The wildcard rules are the only ones held between requests, so anything
        // that writes one has to say so. Cheaper to drop the set than to work out
        // whether this particular row was in it.
        static::saved(fn () => Redirects::forgetWildcards());
        static::deleted(fn () => Redirects::forgetWildcards());

        // However the rule arrived — typed, imported, or promoted from the worklist —
        // the question it answers is settled, so it stops being asked.
        static::saved(function (Redirect $redirect): void {
            NotFound::where('path', $redirect->path)->delete();
        });
    }

    /**
     * A rule that is switched on. Every rule has a destination now: one without was the
     * captured address, and those live in leap_not_founds.
     */
    public function scopeUsable(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    /**
     * Paths are compared without a domain, without the slashes at either end and
     * without regard to case. Percent-encoding is undone so an accented address
     * matches whether the browser sent it encoded or not.
     *
     * The lowercasing is the one that needs defending, because a URL path is
     * case-sensitive: RFC 3986 makes only the scheme and the host insensitive. But
     * the alternative here is not case-sensitivity, it is whatever the database
     * does — a plain where() compares under the column's collation, and MySQL's
     * default is insensitive where PostgreSQL's and SQLite's are not. Left alone,
     * the same rule behaves differently per database. Doing it here puts the answer
     * in the code, and it is the forgiving one, which is what a table of other
     * people's old links wants.
     *
     * The cost is that two rules differing only in case cannot both exist. The
     * destination is deliberately left alone, since what is redirected *to* may
     * well be case-sensitive.
     */
    public static function normalizePath(?string $path): string
    {
        $path = trim((string) $path);

        // Tolerate a whole URL being pasted in as the old address.
        if (preg_match('#^https?://#i', $path)) {
            $path = (string) parse_url($path, PHP_URL_PATH);
        }

        return mb_strtolower(trim(self::withoutControlCharacters(rawurldecode($path)), '/'));
    }

    /**
     * Control characters out, whatever they were encoded as.
     *
     * Decoding is what makes an accented address match whether the browser sent it
     * encoded or not, and it is also how %00 and %0A get in. A scanner probing for
     * /kinderfysiotherapie%00sftp-config.json is the ordinary case: capture wrote that
     * down as one path with a null byte in the middle of it, which reads in the panel
     * as a word nobody typed. A newline is the one that matters beyond tidiness, since
     * these values are written back out into headers and logs.
     */
    private static function withoutControlCharacters(string $value): string
    {
        return preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? '';
    }

    /**
     * A destination is made absolute. Without the leading slash a browser resolves
     * it against the directory the old address was in, so /a/b/c : d lands on
     * /a/b/d rather than /d — which is the kind of thing that only shows up in
     * production, on the one rule that happened to be nested.
     */
    public static function normalizeDestination(?string $destination): ?string
    {
        $destination = trim(self::withoutControlCharacters((string) $destination));

        if ($destination === '') {
            return null;
        }

        if (preg_match('#^([a-z][a-z0-9+.-]*:)?//#i', $destination) || str_starts_with($destination, '#')) {
            return $destination;
        }

        return '/'.ltrim($destination, '/');
    }
}
