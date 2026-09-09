<?php

namespace NickDeKruijk\Leap\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An address that was asked for and is not there, kept until somebody says where it
 * should go.
 *
 * Its own table rather than a redirect without a destination, which is what 1.15 did.
 * The two look alike and behave nothing alike: a rule is written by a person and stays,
 * while this is written by whoever knocked on the door and is disposable. Sharing a
 * table meant a screen where the handful of rules drowned in a week of crawler noise,
 * and columns that only meant something for half the rows.
 *
 * Filling in a destination is what promotes one: on save the rule is created and this
 * row is finished. See booted().
 */
class NotFound extends Model
{
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('leap.table_prefix').'not_founds');
    }

    protected $fillable = [
        'path',
        'destination',
        'hits',
        'last_used_at',
        'referers',
        'user_agents',
        'ip_addresses',
    ];

    protected $casts = [
        'hits' => 'integer',
        'last_used_at' => 'datetime',
        'referers' => 'array',
        'user_agents' => 'array',
        'ip_addresses' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (NotFound $notFound): void {
            $notFound->path = Redirect::normalizePath((string) $notFound->path);
            $notFound->destination = Redirect::normalizeDestination($notFound->destination);
        });

        // Answering the question is what ends it. The rule takes over from here, and
        // leaving the row behind would only ask again.
        static::saved(function (NotFound $notFound): void {
            if (blank($notFound->destination)) {
                return;
            }

            Redirect::updateOrCreate(
                ['path' => $notFound->path],
                ['destination' => $notFound->destination, 'active' => true],
            );

            $notFound->deleteQuietly();
        });
    }

    /**
     * The pages that linked here, most followed first, one per line.
     *
     * The columns behind these are maps of value to count, which is the right shape to
     * merge into and the wrong one to read. This is what the panel shows.
     */
    public function getRefererListAttribute(): string
    {
        return static::tallyToLines($this->referers);
    }

    public function getUserAgentListAttribute(): string
    {
        return static::tallyToLines($this->user_agents);
    }

    public function getIpAddressListAttribute(): string
    {
        return static::tallyToLines($this->ip_addresses);
    }

    /**
     * @param  array<string, int>|null  $tally
     */
    protected static function tallyToLines(?array $tally): string
    {
        $lines = [];

        foreach ($tally ?? [] as $value => $count) {
            $lines[] = $count > 1 ? $value.' ('.$count.'x)' : $value;
        }

        return implode("\n", $lines);
    }
}
