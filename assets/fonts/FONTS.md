# Fonts

Self-hosted fonts for the Zero Spam admin screens, so no screen requests a
third-party font CDN. `fonts.css` declares them; it is shipped as is (not
built by webpack). Both families are licensed under the SIL Open Font License
1.1; the licence travels with the files (`OFL.txt` in each directory).

| File | Family, version | Axis in file | Declared weights | Subset | Bytes | SHA-256 |
|---|---|---|---|---|---|---|
| `plus-jakarta-sans/plus-jakarta-sans-latin.woff2` | Plus Jakarta Sans 2.071 | wght 200–800 | 400–800 | Latin | 27,272 | `cd8db90cd950e26bc8761f65d323588bd5cd112d326d6d322bc7c8ea86771215` |
| `plus-jakarta-sans/plus-jakarta-sans-latin-ext.woff2` | Plus Jakarta Sans 2.071 | wght 200–800 | 400–800 | Latin Extended | 21,688 | `0303e02b53b0298fb26a0193941a2e8754f0f9a9fce50d90b178d8da3424873f` |
| `jetbrains-mono/jetbrains-mono-latin.woff2` | JetBrains Mono 2.211 | wght 400–800 | 400–700 | Latin | 31,340 | `2c32b9b3ee358c119e210f6f5195f9bd34894d78a785ff2e95d60e718e400af4` |
| `jetbrains-mono/jetbrains-mono-latin-ext.woff2` | JetBrains Mono 2.211 | wght 400–800 | 400–700 | Latin Extended | 11,596 | `9c38cb2d0d2d93c1ee6e21fa78db76f13ea7e15e15cc64214c7ca89b6aaa35c4` |

Total: 91,896 bytes (89.7 KB), inside the 110 KB budget that
`bin/check-dist.sh` enforces.

Only the Latin and Latin Extended subsets ship. Google also serves Vietnamese
and Cyrillic Extended files for Plus Jakarta Sans, and Cyrillic, Cyrillic
Extended, Greek and Vietnamese files for JetBrains Mono; they are left out to
stay within the budget. Text in those scripts falls back to the system fonts
in `--zs-font-ui` and `--zs-font-data`.

## Source

Downloaded on 2026-10-23 from Google Fonts, which builds these files from the
upstream repositories and serves each family as a variable font split into
per-script subsets with matching `unicode-range` values (copied into
`fonts.css` unchanged). The files are byte-for-byte what Google serves; nothing
was re-subset or re-compressed here.

- CSS request (with a current Chrome user agent, which selects variable woff2):
  `https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400..800&family=JetBrains+Mono:wght@400..700&display=swap`
- Plus Jakarta Sans, Google Fonts `v12`:
  - latin: `https://fonts.gstatic.com/s/plusjakartasans/v12/LDIoaomQNQcsA88c7O9yZ4KMCoOg4Ko20yygg_vb.woff2`
  - latin-ext: `https://fonts.gstatic.com/s/plusjakartasans/v12/LDIoaomQNQcsA88c7O9yZ4KMCoOg4Ko40yygg_vbd-E.woff2`
  - upstream: https://github.com/tokotype/PlusJakartaSans (commit `18d1cd2f7ea10481919d2f05c1f7064b7307fc26`, `fonts/variable/PlusJakartaSans[wght].ttf`, per `ofl/plusjakartasans/METADATA.pb` in https://github.com/google/fonts)
- JetBrains Mono, Google Fonts `v24`:
  - latin: `https://fonts.gstatic.com/s/jetbrainsmono/v24/tDbv2o-flEEny0FZhsfKu5WU4zr3E_BX0PnT8RD8yKwBNntkaToggR7BYRbKPxDcwgknk-4.woff2`
  - latin-ext: `https://fonts.gstatic.com/s/jetbrainsmono/v24/tDbv2o-flEEny0FZhsfKu5WU4zr3E_BX0PnT8RD8yKwBNntkaToggR7BYRbKPx7cwgknk-6nFg.woff2`
  - upstream: https://github.com/JetBrains/JetBrainsMono (commit `19371302b95d218af43299bce79ddbddd0bc364d`, `fonts/variable/JetBrainsMono[wght].ttf`, per `ofl/jetbrainsmono/METADATA.pb` in https://github.com/google/fonts)
- Licences: `https://raw.githubusercontent.com/google/fonts/main/ofl/plusjakartasans/OFL.txt`
  and `https://raw.githubusercontent.com/google/fonts/main/ofl/jetbrainsmono/OFL.txt`.

Versions above are the `name` table version strings (read with fontTools).

## Updating

1. Request the CSS URL above with a current Chrome user agent and download the
   `latin` and `latin-ext` files of each family (keep the file names above).
2. Copy any changed `unicode-range` values into `fonts.css`.
3. Update the table (version, bytes, SHA-256: `shasum -a 256 */*.woff2`) and
   the date, then run `npm run check:budget`. If the total goes over 110 KB,
   drop the `latin-ext` files and their `@font-face` rules and say so in the PR.
