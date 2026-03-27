# QR Code Label — GLPI Plugin

Generate rich inventory labels with QR codes directly from GLPI. Designed for Brother label printers (PT-P950NW, PT-P910BT, etc.) and compatible with any printer via PDF output.

> Plugin GLPI pour générer des étiquettes d'inventaire avec QR codes. Compatible imprimantes Brother et export PDF.

## Features

- **QR codes identical to GLPI native** — uses the same `tc-lib-barcode` library with `QRCODE,H` as GLPI's built-in `BarcodeManager`
- **Rich labels** — QR code + asset name + type + serial number + location + inventory date + company logo + owner text
- **3 tape sizes** — 25mm, 36mm, 50mm with automatic layout adaptation
- **5 color modes** — Black & White, Monochrome, Color, Inverse (white on black), Inverse Mono
- **Single item** — "QR Label" tab on each asset page with copy count
- **Bulk generation** — Massive Action on asset lists to print labels for multiple items at once
- **Zero dependencies** — uses only GLPI's bundled libraries (TCPDF for PDF, tc-lib-barcode for QR)
- **GLPI 10 and 11** — fully compatible with both versions (Symfony LegacyFileLoadController support)
- **GLPI Cloud compatible** — no filesystem dependencies, ephemeral PDF with auto-cleanup
- **Multilingual** — French and English included, extensible via .po files

## Supported Asset Types

- Computer
- Monitor
- Peripheral
- Network Equipment
- Printer
- Phone

## Label Format

Each label contains:

```
┌──────────────────────────────────────┐
│  ┌─────────┐  │  [LOGO]             │
│  │ QR CODE │  │  PC-BUREAU-DG       │
│  │         │  │  Computer           │
│  │         │  │  S/N: ABC123DEF456  │
│  └─────────┘  │  Inv: 2024-01-10   │
│               │  Chambon            │
│               │  Property of: XXX   │
└──────────────────────────────────────┘
```

| Tape size | Label dimensions | QR code | Best for |
|-----------|-----------------|---------|----------|
| 25mm | 70 × 25 mm | 18 mm | Small devices, compact labels |
| 36mm | 80 × 36 mm | 26 mm | Standard use (default) |
| 50mm | 90 × 50 mm | 36 mm | Large labels, easy scanning |

## Screenshot

### Single item — "QR Label" tab on asset page
Open any Computer/Monitor/etc. → click the **QR Label** tab → choose options → Generate.

### Bulk generation — Massive Action
Select items in a list → Actions → **QR Code Label - Print QR labels** → configure → Generate.

### Configuration page
Setup → Plugins → QR Code Label (gear icon) → set defaults, upload logo.

## Installation

### GLPI Marketplace (recommended for GLPI Cloud)

1. Search for **QR Code Label** in the GLPI Marketplace (Setup → Plugins → Marketplace)
2. Click **Install**
3. Click **Enable**

### Manual installation (self-hosted)

1. Download the latest release from [Releases](https://github.com/tienou/glpi-qrcodelabel/releases)
2. Extract the `qrcodelabel/` folder into your GLPI `marketplace/` directory
3. Go to **Setup → Plugins** and click **Install** then **Enable**

## Configuration

After enabling the plugin:

1. Go to **Setup → Plugins** → click the gear icon next to "QR Code Label"
2. Configure defaults:
   - **Tape size** — 25mm, 36mm or 50mm
   - **Color mode** — Black & White, Monochrome, Color, Inverse, Inverse Mono
   - **Owner text** — e.g. "Property of: My Company" (displayed on each label)
   - **Show inventory date** — toggle first inventory date display
   - **Page size** — A4, A3, Letter, Legal
   - **Orientation** — Portrait or Landscape
3. **Upload a company logo** (PNG format, displayed top-right of each label)

## Usage

### Print a single label

1. Open any supported asset (Computer, Monitor, etc.)
2. Click the **QR Label** tab
3. Choose tape size, color mode, and number of copies
4. Click **Generate** → a download link appears

### Print labels in bulk

1. Go to any asset list (e.g. Assets → Computers)
2. Select the items you want labels for
3. Click **Actions** → choose **QR Code Label - Print QR labels**
4. Configure options (tape size, color, etc.)
5. Click **Generate** → download the PDF

## Technical Details

- **PDF engine**: GLPI's native TCPDF (`vendor/tecnickcom/tcpdf/`) — no external dependencies
- **QR rendering**: `Com\Tecnick\Barcode\Barcode` (`QRCODE,H`) — same library and params as GLPI's `BarcodeManager`, rendered as high-res PNG via GD (10px/module, 300 DPI)
- **PDF delivery**: Ephemeral token system — PDF exists only during download, auto-deleted after
- **GLPI 11 compatibility**: Respects Symfony's `LegacyFileLoadController` buffer (no `ob_end_clean`, no `exit`)
- **Database**: Single config table `glpi_plugin_qrcodelabel_configs`
- **Rights**: `plugin_qrcodelabel_label` (CREATE) + `plugin_qrcodelabel_config` (UPDATE)
- **Security**: Input whitelist validation, logo upload verification (`getimagesize`), no raw POST to DB
- **PHP**: 7.4 – 8.4

## Submitting to GLPI Marketplace

To make this plugin available on GLPI Cloud:

1. Create a release on GitHub with a `.zip` or `.tar.bz2` archive
2. Contact GLPI support to request marketplace validation
3. Provide: plugin name, GitHub URL, version number
4. The GLPI team will review and publish the plugin

## License

[AGPL v3.0](LICENSE)

## Author

**Etienne Gaillard**
