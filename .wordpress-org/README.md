# WordPress.org listing assets

The art for the plugin's page on [WordPress.org](https://wordpress.org/plugins/zero-spam/). Nothing here ships in the plugin (`.distignore`).

## Files

| File | Size | Used for |
|---|---|---|
| `icon.svg` | 256 viewBox | Plugin icon, vector. Flat fills on a solid tile, on the same brick grid as the PNGs. |
| `icon-256x256.png`, `icon-128x128.png` | 256, 128 | Plugin icon: the tile with the mark, treatment B "Live core". |
| `banner-1544x500.png`, `banner-772x250.png` | 1544 x 500, 772 x 250 | Plugin page header, retina and standard. |
| `banner-1544x500-rtl.png`, `banner-772x250-rtl.png` | the same | The header on right-to-left locales: the layout is mirrored, the mark and the words are not flipped. |
| `screenshots.md` | | The readme screenshots to capture after the 6.0 cutover, with their captions. |

## Sources

- `src/icon.html`: the PNG icon. Every brick edge is an even number, so it stays on the pixel grid at 128 px.
- `src/banner.html`: the banner, drawn at 772 x 250; 1544 x 500 is the same page at a device scale of 2. `banner.html?dir=rtl` is the right-to-left twin. Open either in a browser to preview it.
- `icon.svg` is written by hand, not rendered.

What follows what:

- **The banners** read `assets/src/admin/styles/tokens.css` (hero gradient, text and halo colours), `assets/fonts/` (Plus Jakarta Sans) and `assets/img/zero-spam-mark.svg`, so a change to one of those needs a re-render. The wall's brick colours and the faint brick lines are written out in `src/banner.html` and do not follow the tokens.
- **The icons** follow nothing: `src/icon.html` and `icon.svg` hold every colour and brick themselves, and the mark's darkest colour is lifted one step in them (`#63000D` to `#840012`) so it stands out from the tile. A change to the tokens or the mark has to be carried over by hand.

## Render

```bash
nvm use && npm ci && npx playwright install chromium   # once
node bin/render-listing-assets.mjs                     # writes the PNGs here
node bin/render-listing-assets.mjs --measure           # also prints the mark's and each text line's position, and the contrast
node bin/render-listing-assets.mjs --check             # writes nothing: exit 1 unless a fresh render equals all six PNGs byte for byte
```

The output is byte-identical between runs on one machine: no timestamps, sRGB, no LCD text or hinting, local files only (a request for anything outside the checkout stops the render). Text is drawn differently from one platform to another, so the committed files come from macOS, and `--check` is the strict comparison for that machine: run it before committing art.

`assets/src/admin/test/listing-assets.test.js` runs in `npm run test:js` and so in the pre-push gate, on any machine, so it is less strict for the banners:

- **Icons** (no text): byte for byte.
- **Banners:** pixel by pixel (`compareBanner()` and `TOLERANCE` in the render script). Outside the text, at most 0.05% of the pixels may move by more than 6 of 255 in a channel. Inside each text line, up to 25% of the box may (glyph edges), but at most 3% of the glyph interiors, and the text colour must still be there.
- **Everything:** dimensions, under 500 KB, the mark and the text inside the central 1200 px, at least 4.5:1 behind the text, and `icon.svg` flat, with the same bricks and colours as `src/icon.html`.

If the icons fail after a Playwright upgrade, or the art fails after a macOS upgrade, re-render, look at the result and commit it.

## Publish to WordPress.org (maintainer, by hand)

The art goes in the SVN working copy's top-level `assets/` (`/Users/benmarshall/Documents/Projects/zero-spam`, not `trunk/`). It is live as soon as it is committed, with no release, so do this at the 6.0 release. `$PLUGIN` is a checkout of the released tag.

```bash
cd /Users/benmarshall/Documents/Projects/zero-spam
svn update
cp "$PLUGIN"/.wordpress-org/icon.svg "$PLUGIN"/.wordpress-org/icon-*.png "$PLUGIN"/.wordpress-org/banner-*.png assets/
svn add --force assets
svn propset svn:mime-type image/png assets/icon-*.png assets/banner-*.png
svn propset svn:mime-type image/svg+xml assets/icon.svg
svn status assets     # the -rtl banners are new; icons and banners are replaced
svn commit assets -m "Update the plugin icon and banners for 6.0"
```

Only the listed files go up: not `src/`, `README.md` or `screenshots.md`. The directory's cache can take a few hours to show new art. Screenshots follow `screenshots.md`.
