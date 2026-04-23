# Fonts for PNG label export

PNG label generation uses GD's `imagettftext()`, which requires a TrueType
font file on disk.

**Since v1.4.1, the plugin bundles [Noto Sans](https://fonts.google.com/noto/specimen/Noto+Sans)
(Apache 2.0 license) directly in this directory** so PNG export works
out-of-the-box on any GLPI install, including hosted / sandboxed environments
with no system fonts.

## Font lookup order

The plugin searches for a usable TTF in this order:

1. **Plugin bundled** (this directory) — `NotoSans-Regular.ttf`, `LiberationSans-Regular.ttf`, `DejaVuSans.ttf` (and their `-Bold` counterparts).
2. **Common Linux system paths** — `/usr/share/fonts/truetype/liberation/`, `/usr/share/fonts/truetype/dejavu/`, etc.
3. **Windows** — `C:\Windows\Fonts\arial.ttf`.

## Overriding the bundled font

Drop your own `LiberationSans-Regular.ttf` or `DejaVuSans.ttf` (+ matching
`-Bold.ttf`) in this directory — the plugin uses the first hit in the list
above, so a named file takes precedence over fallbacks only if it comes
earlier in the search order.

The simplest way to switch to another font: delete `NotoSans-*.ttf` from this
directory and drop your preferred TTF in its place (keeping the naming
convention, or patch `Label::findFont()` to recognise your filename).

## License notice

- **Noto Sans** — Apache License 2.0, © Google LLC. See `OFL.txt` upstream.
- **Liberation Sans** — SIL Open Font License 1.1 (if used).
- **DejaVu Sans** — Bitstream Vera / Arev derivative license (if used).

All three are free to redistribute with this plugin.
