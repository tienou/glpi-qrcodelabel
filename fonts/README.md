# Fonts for PNG label export

PNG label generation (Brother P-Touch Cube workflow) uses GD's
`imagettftext()`, which requires a TrueType font file on disk.

The plugin looks for fonts in this order:

1. **This directory** — drop a TTF here to override system fonts.
2. **Common Linux paths** — `/usr/share/fonts/truetype/liberation/`,
   `/usr/share/fonts/truetype/dejavu/`, etc.
3. **Windows** — `C:\Windows\Fonts\arial.ttf`.

On Debian/Ubuntu GLPI servers, install one of:

```
sudo apt install fonts-liberation
sudo apt install fonts-dejavu
```

Otherwise, download `LiberationSans-Regular.ttf` + `LiberationSans-Bold.ttf`
(or `DejaVuSans.ttf` + `DejaVuSans-Bold.ttf`) from the Liberation / DejaVu
upstream projects and drop them in this folder.

Recognized filenames (any pair works):
- `LiberationSans-Regular.ttf` + `LiberationSans-Bold.ttf`
- `DejaVuSans.ttf` + `DejaVuSans-Bold.ttf`
