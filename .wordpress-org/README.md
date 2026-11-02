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

The sources read the plugin's own files, so the art follows them: `assets/src/admin/styles/tokens.css` (colours, hero gradient), `assets/fonts/` (Plus Jakarta Sans) and `assets/img/zero-spam-mark.svg`.

## Render

```bash
nvm use && npm ci && npx playwright install chromium   # once
node bin/render-listing-assets.mjs                     # writes the PNGs here
node bin/render-listing-assets.mjs --measure           # also prints each text line's position and contrast
```

The output is byte-identical between runs on one machine: no timestamps, sRGB, no LCD text or hinting, local files only (a request for anything outside the checkout stops the render). `assets/src/admin/test/listing-assets.test.js` (`npm run test:js`) compares a fresh render with the committed PNGs on macOS, where they are rendered, and checks dimensions, under 500 KB, text inside the central 1200 px and at least 4.5:1 contrast everywhere behind the text. If it fails after a macOS or Playwright upgrade, re-render, look at the result and commit it.

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
