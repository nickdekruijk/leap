# Images

Leap can serve resized copies of the images on the filemanager disk: a `srcset` that hands
a phone a 600px file and a desktop a 1920px one, in webp, without anything being generated
until it is asked for.

It is **off by default**. Turn it on in `config/leap.php`:

```php
'images' => [
    'enabled' => true,
],
```

That is enough — leap defines its own `leap-images` disk (rooted at `public/img`), so
nothing has to be added to `config/filesystems.php`.

## Using it

```blade
<x-leap::responsive-image :media="$page->mediaFor('header')->first()" sizes="100vw" eager />

<x-leap::responsive-image
    :media="$photo"
    :widths="[600, 900, 1200]"
    :fallback="900"
    sizes="(max-width: 550px) 100vw, 50vw"
/>
```

The component prints the `srcset`, the `sizes` you give it, the intrinsic `width`/`height`
(so the layout does not jump when the picture arrives), the alt text, and
`object-position` when a focus point is set in the file manager. An SVG — or anything else
that is not a bitmap — is served as it is, without a srcset.

Or address it yourself:

```php
$media->url(1200);           // /img/1200/photos/office-a1b2c3d4.jpg.webp
$media->url();               // the original
$media->srcset([600, 1200]); // "…600w, …1200w"

$model->mediaImage('header', 1200);
$model->mediaSrcset('header', [600, 1200]);

Leap::image($path, 1200);    // by path, for images with no Media row
```

Everything falls back to the URL of the file itself when there is nothing to resize — the
feature turned off, an SVG, a preset that does not exist. Printing one of these is always
safe.

## How a URL works

```
/img/1200/photos/office-a1b2c3d4.jpg.webp
     │    │             │        │   └── the preset's output format, appended
     │    │             │        └── the original's own extension
     │    │             └── the first 8 characters of the file's sha256
     │    └── the path of the original on the filemanager disk
     └── the preset
```

The source tree is mirrored once per preset, with the hash on the file rather than around
it. A directory per hash would be a directory per file per preset, and an empty one left
behind every time a copy goes.

The first request for that URL finds no file, falls through to PHP, and leap generates the
copy, writes it to `public/img/…` and returns it. Every request after that is answered by
the web server straight off disk — PHP is not involved at all.

**The hash is the point.** Replace an image with a different one under the same name and
every URL pointing at it changes, so nothing anywhere is still holding the old picture:
not the file on disk the web server serves without asking, not the browser, not a CDN.
There is no cache to clear, no invalidation to remember, no `--force` to run after an
editor swaps a photo.

What that costs is copies of files that no longer exist, since nothing is ever overwritten
in place. Delete, rename or replace an image through leap and its copies are taken along
at once — the path and the hash they were made under are known exactly at that moment.
`php artisan leap:images --prune` sweeps up what happened out of leap's sight; monthly in
the scheduler is plenty.

A URL whose hash does not match the file gets a 302 to the one that does, which covers a
page rendered in the moment before a replacement.

Uploading a new version of an image is what `leap.filemanager.upload_replace` is for. By
default an upload with an existing name lands beside the old one as `name-1.jpg`, which is
a habit from when a resized copy was addressed by file name alone and replacing a file
therefore could not be seen. Turn it on and the upload writes over the old file, keeping
the same Media row, so every page already showing that image shows the new one.

**Files written from outside leap need `leap:images --sync`.** Upload or crop through the
file manager and leap keeps up on its own. Overwrite a file with an rsync, a deploy script
or a database import and nothing re-reads the Media row, so the pages go on printing the
old address — and the web server goes on answering it off disk without asking PHP anything
that would give it the chance to notice. `--sync` re-reads every image; run it after any
such write.

## Presets

Every width in `leap.images.widths` is a preset named after itself, which is why
`$media->url(1200)` works with no further configuration. It is also an allowlist: a URL
asking for a size that is not configured is a 404, so nobody can fill the disk one pixel
value at a time.

For anything the ladder does not cover, name it:

```php
'presets' => [
    'square' => ['width' => 600, 'height' => 600, 'fit' => 'cover'],
    'og' => ['width' => 1200, 'height' => 630, 'fit' => 'cover', 'format' => 'jpg'],
    'blurred' => ['width' => 1200, 'blur' => 40],
],
```

| Option | Default | Meaning |
| --- | --- | --- |
| `width` / `height` | — | The box. `height` may be omitted for a width-only constraint. |
| `fit` | `contain` | `contain` fits within the box keeping the ratio; `cover` crops to exactly it. |
| `quality` | `80` | 1–100. |
| `format` | `webp` | `null` keeps the source format. |
| `lossless_from` | `['png']` | Source formats encoded losslessly — a screenshot full of text turns to mush at quality 80. |
| `upscale` | `false` | An original smaller than the preset is passed through, not blown up. |
| `blur` | `null` | 1–100. |
| `grayscale` | `false` | |

Two things are never resized: an **SVG**, which already scales, and an **animated GIF**,
which GD would flatten to a single frame. Both are served as they are. So is an original
above `max_source_pixels` (40 megapixels by default) — decoding it would want roughly
`width × height × 4` bytes and take the worker down with it.

## Deploying

The generated copies live in `public/img`, so:

- Add `/public/img` to `.gitignore`.
- If you deploy to a fresh release directory, either symlink `public/img` to shared
  storage or run `php artisan leap:images --warm` after deploying — otherwise the first
  visitors after every deploy pay for every image on the site.
- Serve them with a long cache. Apache:

  ```apache
  <IfModule mod_expires.c>
      <LocationMatch "^/img/">
          ExpiresActive On
          ExpiresDefault "access plus 1 year"
          Header set Cache-Control "public, max-age=31536000, immutable"
      </LocationMatch>
  </IfModule>
  ```

  Safe by construction: the URL changes whenever the file does.

## leap:images

```bash
php artisan leap:images --sync              # re-read files written outside leap
php artisan leap:images --warm              # generate everything that is missing
php artisan leap:images --warm --preset=600 # one preset only
php artisan leap:images --prune             # delete copies nothing points at
php artisan leap:images --clear             # delete every generated copy
php artisan leap:images --prune --dry-run   # report without touching anything
```

`--warm` also re-measures dimensions, which corrects rows stored before leap read the EXIF
orientation of photos off a phone.

## Remote disks

Point `leap.images.disk` at an s3 disk you define yourself in `config/filesystems.php` and
set `leap.images.eager` to `true`. Generating on the first request only works when the web
server can fail to find a file and hand the request to PHP; nothing can do that for a
bucket. Eager mode instead makes every copy in a queued job as soon as a file is stored or
changed, so it needs a running queue worker.

On a local disk, eager is a preference rather than a requirement: it moves the cost of the
first view off the first visitor, at the price of making sizes that may never be asked
for.

## Coming from nickdekruijk/imageresize

Step by step in [upgrading.md](upgrading.md#13--resized-images). The short of it: turn
`leap.images.enabled` on, copy the widths over from `config/imageresize.php`, convert
every `asset_resized()` in the views — that has to happen *before* `composer remove`, or
each one is a fatal error — and then drop the package, its config and `public/resized`.

The reason to bother: imageresize builds its cache path from the file's name alone, so a
replaced image keeps serving the old one until someone deletes the whole directory by
hand. That is the one thing it cannot be taught.
