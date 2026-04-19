<p align="center">
  <img src="logo.png" width="128" alt="Phone Background Plugin Logo">
</p>

<h1 align="center">Phone Background </h1>

<p align="center">
  <strong>GLPI plugin — Generates personalized PNG wallpapers for corporate phones registered in GLPI</strong>
</p>

<p align="center">
  <a href="https://github.com/glpi-project/glpi" target="_blank"><img src="https://img.shields.io/badge/GLPI-11.0%2B-blue?style=flat-square" alt="GLPI compatibility"></a>
  <a href="https://www.gnu.org/licenses/gpl-3.0.html" target="_blank"><img src="https://img.shields.io/badge/License-GPL%20v3%2B-green?style=flat-square" alt="License"></a>
  <a href="https://php.net/" target="_blank"><img src="https://img.shields.io/badge/PHP-%3E%3D8.2-purple?style=flat-square" alt="PHP"></a>
  <a href="https://github.com/monta990/phonebg/releases" target="_blank"><img alt="GitHub Downloads (all assets, all releases)" src="https://img.shields.io/github/downloads/monta990/phonebg/total"></a>
</p>

---

## Overview

Phone Background generates personalized PNG wallpapers for corporate phones registered in GLPI. It overlays the phone's name, assigned line number, and up to two configurable custom text labels onto a custom PNG template. Each phone record owner can download the wallpaper directly from the Phone asset tab.

---

## Requirements

| Requirement     | Minimum version        |
|-----------------|------------------------|
| GLPI            | ≥ 11.0                 |
| PHP             | ≥ 8.2                  |
| PHP extension   | GD (image processing)  |

## Installation

1. Download the latest release `.zip` from [Releases](../../releases)
2. Copy the `phonebg/` folder into your GLPI `marketplace/` or `plugins/` directory.
3. In GLPI, go to **Setup → Plugins** and click **Install**, then **Enable**.
4. Go to **Setup → Plugins → Phone Background** to upload your PNG template.

## Configuration

### Template tab

Upload a PNG image (max 500 KB) that will serve as the wallpaper background. The image can be any resolution and the plugin adapts to it automatically. For server security and stability, template dimensions are limited to a maximum of 8000x8000 pixels (which covers all standard devices, including 8K resolutions).

### Fonts tab

The **Fonts** tab lets you upload custom TrueType (TTF) or OpenType (OTF) fonts that will be available for the wallpaper text. Uploaded fonts are stored in `files/_plugins/phonebg/fonts/` and survive plugin upgrades. The bundled `DejaVuSans.ttf` is always available as a fallback.

### Positions tab

The **Positions** tab (visible once a template has been uploaded) provides a full-size visual drag-and-drop editor:

- Drag the **Device name**, **Line number**, **Label 1**, and **Label 2** handles directly over the template to position them.
- Adjust font size (px) and font color per field using the table inputs.
- Select the **font** to use from the dropdown (populated from uploaded fonts).
- **X = 0** centers the text horizontally regardless of image width.
- **Label 1 / Label 2**: enter custom static text, enable the toggle, and position the label. Disabled labels are not rendered.
- Click **Save** to persist the settings, or **Reset to defaults** to restore the original values.

Positions are stored in the `glpi_plugin_phonebg_config` database table and survive plugin upgrades.

## Usage

1. Open any **Phone** asset in GLPI.
2. Click the **Background** tab.
   Note on permissions: For security and privacy reasons, only GLPI Administrators, Technicians, or the specific User assigned to the phone can generate and preview/download the background.
3. Click **Preview** to see the generated wallpaper inline, or **Download background** to save the PNG file.

## File structure

```
phonebg/
├── fonts/
│   └── DejaVuSans.ttf          # Bundled TrueType font
├── front/
│   ├── config.form.php         # Admin settings page (tabbed)
│   ├── download.php            # PNG generation, download & preview endpoint
│   └── resource.send.php       # Authenticated template image server
├── inc/
│   ├── background.class.php    # GD image generation logic
│   ├── config.class.php        # DB-backed layout configuration
│   ├── paths.class.php         # Centralized paths & URLs
│   └── phone.class.php         # Phone asset tab integration
├── locales/                    # i18n: es_MX, en_US, en_GB, fr_FR
├── logo.png                    # Plugin icon (128×128, transparent background)
├── setup.php                   # Registration, hooks, install/uninstall
├── LICENSE                     # GPLv3
└── README.md                   # This file
```

## Uninstallation

Disabling and uninstalling via **Setup → Plugins** drops the `glpi_plugin_phonebg_config` table. The uploaded template and generated files in `files/_plugins/phonebg/` are intentionally preserved.

---

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

---

## Author

**Edwin Elias Alvarez** — [GitHub](https://github.com/monta990)

---

## Buy me a coffee :)
If you like my work, you can support me by a donate here:

<a href="https://www.buymeacoffee.com/monta990" target="_blank"><img src="https://cdn.buymeacoffee.com/buttons/default-yellow.png" alt="Buy Me A Coffee" height="51px" width="210px"></a>

---

## License

GPL v3 or later. See [LICENSE](https://www.gnu.org/licenses/gpl-3.0.html).

## Issues

Report bugs or request features on the [issue tracker](https://github.com/monta990/phonebg/issues).
