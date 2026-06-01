# Typography plugin for DokuWiki — local fork

Local fork of the [Typography plugin](https://www.dokuwiki.org/plugin:typography) (ssahara/dw-plugin-typography), tracking upstream's `2020-07-31` release. Gives Word-style inline font control via `<typo>` and the short tags `<ff>` `<fs>` `<fc>` `<bg>` `<fw>` `<wf>` `<smallcaps>`.

This fork modernizes the code, fixes two real bugs, adds two short-name aliases, makes it clean under PHP 8, and pins the version so the Extension Manager won't overwrite it.

## Why a fork

The upstream plugin was last released in 2020 and its dokuwiki.org page lists compatibility only up to Hogfather (2018) — four DokuWiki releases before Librarian. It still runs, but it carries latent bugs and emits PHP 8 deprecation warnings in places. Rather than depend on an unmaintained upstream, this is a local fork with the fixes applied and the version pinned.

A third-party "PHP 8.2 support via rector" PR was reviewed and **not used as-is**. It was a shallow mechanical pass: it converted `array()` to `[]` (good idea) but collapsed cleanly-formatted arrays onto unreadable single lines, added several unnecessary defensive `(string)` casts, and — critically — **did not fix either of the real bugs**. The good ideas from it (array modernization, `__DIR__`, `static::class`, targeted null-safety) were applied here properly and by hand.

## What changed in the local fork

### Bug fixes

**1. helper/parser.php — build_attributes() boolean-assignment bug.** Upstream had:

    $elem['classes'] = isset($elem['classes']) ?: array();

The Elvis operator `?:` operates on the *result* of `isset()`, which is a boolean. So when `classes` was already set, this line replaced the array with the boolean `true`. The next line, `array_unique($elem['classes'] + $addClasses)`, would then attempt `true + array` — a `TypeError` on PHP 8. Fixed to the intended null-coalesce:

    $elem['classes'] = $elem['classes'] ?? [];

This path is only reached when the helper API's `build_attributes()` is called with a non-empty `$addClasses` argument (the plugin's own syntax classes never do), so it was latent — but it was still wrong, and it would bite any other plugin using the helper.

The same `build_attributes()` block had a second latent bug: it combined the two class lists with the `+` array operator (`$elem['classes'] + $addClasses`). `+` unions arrays *by key*, so two numerically-indexed lists silently drop the right-hand side's colliding elements — `['a'] + ['b']` is just `['a']`. Replaced with `array_merge()`, which actually concatenates the lists.

**2. syntax/base.php — uninitialized string offset.** `handle()` did:

    $params = strtolower(ltrim(substr($match, strlen($markup)+1, -1)));
    if ($this->styler->is_short_property($markup)) {
        $params = $markup.(($params[0] == ':') ? '' : ':').$params;
    }

For a bare short tag with no parameters — e.g. `<fs></fs>` — `$params` is an empty string, and `$params[0]` raises an "Uninitialized string offset 0" warning on PHP 8. Replaced the `$params[0]` access with `substr($params, 0, 1)`, which is safe on an empty string and returns `''`.

### ts / tt short names

Upstream registered `text-shadow` and `text-transform` in the property table with **numeric keys**:

      0  => 'text-shadow',
      1  => 'text-transform',

That was a hack: it made the two full property names *recognised* (via the `in_array()` check) but gave them no short alias, unlike every other property (`fs`, `fw`, `ff`, ...). This fork gives them proper short names:

    'ts' => 'text-shadow',
    'tt' => 'text-transform',

So you can now write `<typo ts:1px 1px 2px gray>` and `<typo tt:uppercase>`. The full names `text-shadow:` / `text-transform:` still work exactly as before — this only *adds* the short forms. (This is the change proposed in the upstream `ts`/`tt` pull request.)

### Modernization

| Change | Where |
| --- | --- |
| `array()` to `[]` short syntax, kept readably formatted (not collapsed) | all PHP files |
| `list(...)` to `[...]` destructuring | base.php, odt.php, color-icon.php |
| `get_class($this)` to `static::class` | base.php and all syntax/*.php |
| `dirname(__FILE__)` to `__DIR__` | all syntax/*.php |
| Explicit `public` visibility on the parser's constructor | helper/parser.php |

### PHP 8 hardening of color-icon.php

`images/fontcolor/color-icon.php` is a standalone endpoint (hit directly as an `<img>` URL by the colour picker). Upstream fed `$_GET['color']` straight into `str_split()` and `hexdec()`. On PHP 8 a non-string value (`?color[]=x`) causes a `TypeError`, and unvalidated input reached `hexdec()`. This fork validates the parameter strictly — it must be exactly six hexadecimal digits — before use, and makes the same-host referer check null-safe (`HTTP_HOST` may be unset; `parse_url()` may return null host). This is both PHP-8-safe and more robust than the rector PR's blind `(string)` casts.

### Update suppression

`plugin.info.txt` `date` set to `2077-07-31` (original day/month, year bumped to 2077). The Extension Manager's `isUpdateAvailable()` compares the installed date against upstream's as a string, so an Update is never offered — clicking it would otherwise replace this fork with the unmodified, unmaintained upstream. Matches the convention used by the other forks in this collection.

## What did NOT change

- All wiki syntax — `<typo>`, `<ff>`, `<fs>`, `<fc>`, `<bg>`, `<fw>`, `<wf>`, `<smallcaps>` — behaves identically. The `ts`/`tt` change only *adds* syntax.
- The 4 language files (en, de, fr, ru), the toolbar picker images, `deleted.files`.
- The CSS-property validation rules and the ODT export logic — unchanged.
- `text-shadow` / `text-transform` are still passed through without a validation regex (escaped only) — same as upstream. Unchanged to keep behavior identical.

## Notes on usage

This plugin gives *inline, ad-hoc* font control. For consistent, maintainable styling, the [Wrap plugin](https://www.dokuwiki.org/plugin:wrap) and its semantic CSS classes are the better tool — the two are complementary: Wrap for structure/layout/alignment, Typography for inline character-level font tweaks.

## Install

Drop the folder into `lib/plugins/typography/`, or use Admin -> Extension Manager -> Manual Install to upload the zip.

## Review changes (2026-05-28)

The following additional fixes were applied during a full plugin review:

- **DOKU_INC guards** added to `action.php` and all `syntax/*.php` files (they were missing; the two `helper/*.php` files already had them).
- **odt.php null safety**: `foreach ($tag_data['declarations'] …)` now uses `?? []` so it is safe when `parse_inlineCSS()` returns a result with no declarations key (e.g. a webfont-class-only tag).
- **Lang key typo fixed**: `fs_larger_sampel` → `fs_larger_sample` in all four language files (`en`, `de`, `fr`, `ru`). The toolbar picker was emitting an empty tooltip string for the "larger" size button because the action handler referenced the correctly-spelled key.
- **Japanese translation added**: `lang/ja/lang.php` created with all strings.
- **Docblocks**: `@param`/`@return` annotations added to all public/protected methods in `action.php`, `syntax/base.php`, and `helper/odt.php`.

## Review changes (2026-06-01)

Three bugs found by re-running the full review checklist against the live container:

**1. helper/parser.php — fc/bg/ff validation regexes were dead code.** The `$specifications` table used to key the `color`, `background-color`, and `font-family` validators by their *short* names (`fc`, `bg`, `ff`). But `parse_inlineCSS()` resolves the short name to its full CSS property name *before* looking it up in `$specifications`, so those three regexes were never reached — any value (e.g. `fc:99`, `bg:bogus`) was accepted. Confirmed empirically by bootstrapping the real helper in the container. Fixed by re-keying to `'color'`, `'background-color'`, and `'font-family'`. Behavior change: values that previously slipped through the intended validator are now rejected. All first-party values produced by the toolbar (hex `#rrggbb`, named families `serif`/`sans-serif`) still pass. `'wf'` is unaffected — its short and resolved names are the same.

**2. helper/odt.php — line-height paragraph branch was dead.** The ODT renderer's `DOKU_LEXER_ENTER` handler tested `isset($data['line-height'])`, but `$data` is the two-element numeric array `[$state, $tag_data]` — that string key can never exist there. The correct variable is `$tag_data['declarations']['line-height']`. Effect: `<typo lh:…>` in ODT export always rendered as a span (line-height silently ignored) instead of the intended styled paragraph. Fixed to `isset($tag_data['declarations']['line-height'])`.

**3. lang — ff_*_sample keys missing from all language files.** `action.php` calls `getLang('ff_serif_sample')` and `getLang('ff_sans-serif_sample')` for the font-family toolbar picker's preview text, but these keys were defined in no language file (same pattern as the `fs_larger_sampel` typo fixed in the 2026-05-28 pass). Added to all five language files (`en`, `de`, `fr`, `ru`, `ja`).

## Tested against

DokuWiki `2025-05-14b "Librarian"` — PHP lint clean on all files under PHP 8.3, render tests for every tag, the `ts`/`tt` short names, the empty-tag edge case, and the `build_attributes()` bug fix, all passing under `error_reporting=E_ALL`.

## License

GPL 2, matching the original plugin.
