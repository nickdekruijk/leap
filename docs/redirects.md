# Redirects

The old addresses of a site and where they go now, in a table you edit in the panel.

A site that moves leaves its previous addresses behind: in Google's index, in other
people's links, in the newsletter that went out two years ago. Without redirects each
of those is a visitor who arrives at an error page, and a page whose standing in search
results is thrown away rather than moved.

## How it works

Redirects are resolved from the 404 handler, not from middleware. A request that finds
its page never asks the database whether it should have been a redirect, so a working
site pays nothing at all for having this switched on. The lookup runs only once
something has already failed to be found.

It is registered before the [404 log](configuration.md), and that order is deliberate:
Laravel walks its render callbacks in order and stops at the first one that returns a
response, so an address with a rule is sent on its way instead of being written down as
a broken link. What redirects is not a 404.

## Writing a rule

A rule is an old address and a new one. The old address is a path, without the domain:

```
mogelijkheden-bij-faw/fysiotherapie   ->   /behandelingen/fysiotherapie
```

Leading and trailing slashes do not matter: the path is normalized on save, so
`/Praktijk/` and `praktijk` are the same rule and the unique index says so. An accented
address matches whether the browser sent it encoded or not.

**Rules are matched without regard to case, and paths are stored lowercased.** A URL
path is case-sensitive — RFC 3986 makes only the scheme and the host insensitive, and
Laravel matches routes that way too — so this is a deliberate departure, for two
reasons.

The first is that the alternative is not case-sensitivity, it is whatever the database
happens to do. A plain `where('path', ...)` compares under the column's collation:
MySQL's default is case-insensitive, PostgreSQL's is not, SQLite's is not. Leaving it
alone means the same rule behaves differently per database, which is worse than either
answer. Lowercasing puts the decision in the code, where it can be read.

The second is that this table exists to catch what is already out there. Old links and
old search results carry whatever casing the previous site used, and a redirect that
insists on matching it exactly helps nobody.

**Destinations are left exactly as written**, which matters — the address being
redirected *to* may well be case-sensitive.

### When two addresses differ only in case

`/test` and `/TEST` cannot be two rules with two destinations. Entering the second one
is refused with a field error naming the rule that already exists, so this fails where
you can see it rather than by one of them quietly winning.

It stays a limitation rather than becoming a feature because case-sensitive matching is
not portable: on MySQL a plain comparison is insensitive whatever PHP does, so the
column would need a binary collation, spelled differently on every driver, plus a
matching order that tries exact before insensitive and a rule for when both exist. That
is a good deal of machinery for a case that is rare and is itself a problem — two URLs
differing only in case are duplicate content.

If a site genuinely needs it, Laravel's own routes match case-sensitively and run before
anything 404s:

```php
Route::redirect('/TEST', '/somewhere-else', 301);
```

That address never reaches the table, which leaves `/test` free to be a rule.

The destination is made absolute. Without the leading slash a browser resolves it
against the directory the old address was in, so `a/b/old : c` would land on `/a/b/c`
rather than `/c` — the kind of mistake that only shows up on the one rule that happened
to be nested. A whole URL is left alone, so an address can be sent to another site.

The query string comes along, so a campaign parameter or a page number survives.

**301 or 302.** 301 says the move is permanent: search engines drop the old address and
move its standing to the new one, which is what a migration wants. 302 says temporary,
and keeps the old address indexed. Reach for 302 when a rule might be reversed —
browsers cache a 301 hard, sometimes indefinitely, so a mistaken one stays in a
visitor's browser after you have corrected it.

### Wildcards

A path ending in `/*` matches everything below it:

```
mogelijkheden-bij-faw/*   ->   /behandelingen
```

An exact rule always beats a wildcard, and between wildcards the longest prefix wins,
so `oud/diep/*` decides a path that `oud/*` would also have matched.

### Importing

The screen takes a CSV, which is how a redirect map usually arrives — an export from
Search Console, or a list the previous site's owner had lying about. The columns are
`path`, `destination` and `status`. Fifty rows typed by hand is how a map ends up half
finished.

## Catching what is missing

Switch on `leap.redirects.capture` and an address that was asked for and matched
nothing is written into the same table, with no destination and switched off: an
unfinished redirect. Someone says where it should go, switches it on, and it starts
working. That turns "which links are broken" from something you read out of a log into
a list you can work down until it is empty.

It is off by default, because on a site with nothing to fix it collects other people's
wordlists. Four things keep it usable when it is on:

- **The panel's own addresses are never written down.** A module answers a role without
  read permission with a 404 rather than a 403, so the module's existence stays hidden;
  capturing those would fill the table with the panel's own screens and undo that.
- **`ignore`** holds glob patterns for what nobody is going to redirect: `*.php`,
  `wp-*`, `.well-known/*` and the rest of the scanner's vocabulary.
- **`throttle_minutes`** is how long the same path stays quiet after it has been noted,
  so a scanner writes one row rather than one per guess.
- **`max`** is the ceiling on captured rows. Delete what you have dealt with and the
  room comes back.

### Who asked, and from where

A captured address keeps three sets, each a value with a count.

**`referer`** is the pages that carried the dead link, and it is on by default. That is
the half that says where to go and fix the link rather than only papering over it with a
redirect, and the count separates the newsletter that went to five thousand people from
one stale link on a forum.

**`user_agent`** and **`ip_address`** are the pair that says whether an address still has
people on it or only a crawler, which decides whether there is anything to fix at all.
Both are **off by default**: they describe the visitor rather than the site, and this
table is open to everyone with panel access. The address is anonymized to
`198.51.100.xxx` unless `ip_address_anonymized` is switched off too.

Each set is capped at `values_max`, a hundred by default, and the last slot always goes to the
most recent value. Keeping purely the most seen looks right and is not: once the slots
are full a new value arrives on a count of one, is dropped in the same breath, and can
never climb, so the link that broke this week is the one you never see.

Each value is trimmed to 200 characters, so a full set is some 20kB and a row with all
three around 63kB. Together with `max` that is the ceiling on what the capture can
occupy. Set `values_max` to 0 for no cap, knowing that all three are chosen by whoever
made the request: uncapped, one visitor sending a different one each time decides how
large those columns get.

## Next to the 404 log

Both write down missing pages, and they answer different questions.

| | `not_found_log` | `redirects.capture` |
| --- | --- | --- |
| Writes to | a logging channel | the `leap_redirects` table |
| Records | every missing address | what passes the ignore list, throttle and ceiling |
| Also keeps | referer, anonymized IP, user agent | the same three, with counts; the last two off by default |
| Answers | was this a visitor or a bot | which addresses still need a destination |

The overlap is real, and the differences are what decide which you want. The log sees
every missing address, where the table sees only what passes the ignore list, the
throttle and the ceiling — that filtering is what makes the table a worklist and what
makes it useless for spotting someone probing `/wp-admin` four hundred times. The log
goes to a logging channel, so it lands wherever the site already sends logs, alerting
included. And it writes no rows, so it costs nothing to leave on and log rotation cleans
up after it, where the table is pruned by hand.

Most sites want the capture alone. Add the log when 404s should reach an existing log
pipeline, or when you want the unfiltered picture.

## Configuration

```php
'redirects' => [
    'enabled' => env('LEAP_REDIRECTS', true),
    'count_hits' => true,      // How often a rule is used, and when it last was
    'cache_minutes' => 1440,   // Wildcards only; exact rules are found on an index
    'capture' => [
        'enabled' => env('LEAP_REDIRECTS_CAPTURE', false),
        'throttle_minutes' => 60,
        'max' => 1000,
        'referer' => true,
        'user_agent' => false,           // A visitor or a bot
        'ip_address' => false,
        'ip_address_anonymized' => true, // 198.51.100.xxx
        'values_max' => 100,             // Per set; 0 is no limit
        'ignore' => ['*.php', 'wp-*', '.well-known/*', /* ... */],
    ],
],
```

`count_hits` records how often a rule was used and when it last was, which is what says
whether an old address still has anyone on it or the rule can go. It costs one write per
redirect — the number of people following an old link, which is small by definition and
shrinks over time. Switch it off for a site where that is not true.
