# WordPress.org listing assets

The art for the plugin's page on [WordPress.org](https://wordpress.org/plugins/zero-spam/). Nothing here ships in the plugin (`.distignore`).

## Files

| File | Size | Used for |
|---|---|---|
| `icon.svg` | 256 viewBox | Plugin icon, vector. Flat fills on a solid tile, on the same brick grid as the PNGs. |
| `icon-256x256.png`, `icon-128x128.png` | 256, 128 | Plugin icon: the tile with the mark, treatment B "Live core". |
| `banner-1544x500.png`, `banner-772x250.png` | 1544 x 500, 772 x 250 | Plugin page header, retina and standard. |
| `banner-1544x500-rtl.png`, `banner-772x250-rtl.png` | the same | The header on right-to-left locales: the layout is mirrored, the mark and the words are not flipped. |
| `screenshots/screenshot-1.png` … `screenshot-9.png` | 1280 wide | The readme screenshots, captured from wp-env for 6.0.0. |
| `screenshots.md` | | How the readme screenshots are captured, with their captions. |

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

The output is byte-identical between runs on one machine: no timestamps, sRGB, no LCD text or hinting, local files only (a request for anything outside the checkout stops the render). The bytes depend on the machine, through its text rasteriser and the browser's PNG encoder, so `--check` only means something for art rendered on the machine that runs it.

Two checks guard the art:

1. **Pixels, on every run of the unit tests.** `assets/src/admin/test/listing-assets.test.js` renders all six and allows up to 3% of a file's pixels to move by more than 16 of 255, enough for another machine's text. It only catches art that is clearly stale or broken. The same test checks what needs no comparison: dimensions, under 500 KB, the mark and text inside the central 1200 px, at least 4.5:1 behind the text, and `icon.svg` flat with the same bricks and colours as `src/icon.html`.
2. **Bytes, only when the art or its inputs changed.** `bin/check-dist.sh` (so `composer check` and the pre-push hook) runs `--check` when `.wordpress-org/**` (except its Markdown files), `bin/render-listing-assets.mjs`, `assets/img/zero-spam-mark.svg`, `assets/src/admin/styles/tokens.css` or `assets/fonts/**` changed against its base. This is what catches a small change such as an underline. Unrelated front-end pushes never meet it.

**When to re-render:** whenever `--check` fails, which is whenever you change one of those paths and the art is not byte-identical on your machine. That includes a change that does not alter the art, if the PNGs were last rendered on another machine or browser version. Run `node bin/render-listing-assets.mjs`, look at the PNGs and commit them. A Playwright or macOS upgrade alone does not trigger the byte check; the next change to an input will, and may need a re-render.

## Publish to WordPress.org (maintainer, by hand)

The art goes in the SVN working copy's top-level `assets/` (`/Users/benmarshall/Documents/Projects/zero-spam`, not `trunk/`). It is live as soon as it is committed, with no release, so do it when the release is committed to SVN. `$PLUGIN` is a checkout of the released tag.

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
