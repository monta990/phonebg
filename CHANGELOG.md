
# Changelog — Phone Background (phonebg)

All notable changes to this project are documented in this file.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).
Versioning follows [Semantic Versioning](https://semver.org/).

---

## [1.4.1] — 2026-04-05

### Added
**Security**
- Strict access control on download: Added permission validation in front/download.php. Only administrators, technicians (with access to the asset's entity), or the specific user assigned to the phone can now generate and download the wallpaper. (Fixes potential unauthorized cross-entity access / IDOR).
- DoS prevention on image processing: Implemented dimension validation in inc/background.class.php using getimagesize() before loading the template into memory with GD. Images exceeding 8000x8000 pixels are now rejected (a generous limit for any real device), preventing Denial of Service attacks via RAM exhaustion caused by highly compressed malicious PNG files.

---

## [1.4.0] — 2026-03-27

### Added
- **Custom font support.** Users can upload TrueType (TTF) or OpenType (OTF) fonts from a new **Fonts** tab in the plugin settings. Uploaded fonts are stored in `files/_plugins/phonebg/fonts/` and survive plugin upgrades.
- Font selector in the **Positions** tab — choose any installed font from a dropdown; the selection is applied to both text fields when generating the wallpaper.
- Font validation: file size (max 2 MB), extension allowlist (`.ttf`, `.otf`), and magic-byte check (`00 01 00 00`, `true`, `OTTO`) to reject non-font binaries regardless of filename.
- Font delete button in the Fonts tab. If the active font is deleted, the config automatically falls back to `DejaVuSans.ttf`.
- `plugin_phonebg_install()` now creates the `fonts/` storage directory and copies the bundled `DejaVuSans.ttf` there so it always appears in the selector.
- 13 new translatable strings across all locales (es_MX full, fr_FR full, en_US/en_GB base).

### Changed
- `PluginPhonebgPaths`: added `fontsDir()`, `listFonts()`, and `getFontPath(string $filename)`. The old `getFontDejaVuSans()` is replaced — font resolution now goes through `getFontPath()` which checks the user fonts directory first and falls back to the bundled font.
- `PluginPhonebgConfig`: added `font_file` key with default `DejaVuSans.ttf`.
- Positions tab dirty-change detection now also watches `<select>` elements.

---

## [1.3.0] — 2026-03-23

### Changed
- **Base language changed from Spanish to English.** All translatable strings (`__()` calls) now use English as the msgid. GLPI installations without a matching locale now display English instead of Spanish.
- `es_MX.po`: updated to translate English msgids → Spanish msgstr (full coverage, 51 strings).
- `fr_FR.po`: updated to translate English msgids → French msgstr (full coverage, 51 strings).
- `en_US.po` / `en_GB.po`: msgstr intentionally left empty — English msgid is the display string.
- **License upgraded from GPLv2+ to GPLv3+** to align with GLPI 11's own license. `LICENSE` file replaced with the full GPL v3 text. References updated in `setup.php`, `plugin.xml`, README, and file structure comments.
- README: added GitHub Downloads badge; installation section now references the Releases page; license links updated to GPL v3.
- `setup.php`: added `php.min = 8.2` requirement to match GLPI 11's minimum PHP version.
- All `.mo` files recompiled from updated `.po` sources.

---

## [1.2.2] — 2026-03-22

### Fixed
- README: removed GLPI maximum version (12.0) — GLPI 12 does not exist; compatibility is declared as 11.0+.
- README (ES): removed leftover `Versión: 1.2.0` badge from the Spanish section.
- `setup.php`: removed `max` GLPI version requirement — plugin is no longer artificially capped at 12.0.
- POST handler: `name_size` and `mobile_size` are now saved with `max(8, ...)` instead of `max(0, ...)`, preventing zero or sub-minimum font sizes if the HTML `min=8` attribute is bypassed.
- `resource.send.php`: `Content-Disposition` changed from `attachment` to `inline` — the endpoint serves the template as an `<img>` source in the editor, not a download.
- `resource.send.php`: `Cache-Control` changed from `private, max-age=3600` to `no-cache, must-revalidate` — the template can change at any time and should not be served stale.

---

## [1.2.1] — 2026-03-13

### Fixed
- Position editor: draggable labels now render at the actual configured font size instead of a hardcoded 12 px, giving an accurate visual preview of text proportions on the template.
- Position editor: changing the font size input now immediately updates the label size in the editor and recalculates its position, keeping X/Y values in sync.
- Config page: hint text ("PNG · Máx 500 KB") was invisible on dark themes; now inherits the theme text color and is always readable.
- `resource.send.php`: normalized line endings from CRLF to LF for consistency with the rest of the codebase.
- Position editor: labels now initialize at their correct saved coordinates when the Posiciones tab is opened (previously they appeared stacked at the top-left corner due to zero dimensions while the tab was hidden).
- Color picker: replaced GLPI's custom `form-control-color` widget (whose RGB slider did not update values) with a native browser color swatch paired with a hex text input; both stay in sync in real time.

### Improved
- Image generation: auto-shrink now also applies to the line number text, not just the phone name — prevents long numbers from overflowing the image border.
- Image generation: distinguished between a phone with no line assigned (`null`) and a phone whose line number is stored but empty, providing a more descriptive warning message in each case.
- Position editor UX: switching to the Template tab while there are unsaved position or font changes triggers a confirmation dialog.

---

## [1.2.0] — 2026-03-08

### Added
- `plugin.xml` following the GLPI marketplace schema — enables listing in the official GLPI plugin directory. Includes multilingual descriptions (en/es), version history, download URLs pointing to GitHub releases, and language/tag metadata.
- Configuration page now uses Bootstrap tabs: **Plantilla** (upload/delete) and **Posiciones** (position editor), matching GLPI's native tab style.
- Preview button in Phone asset tab opens a modal with the generated wallpaper inline before downloading.
- `download.php` supports `?preview=1` parameter to serve the PNG inline (`Content-Disposition: inline`).

### Fixed
- Drag-and-drop position editor: labels are now fully draggable without accidentally selecting or dragging the background image (`pointer-events:none` on `<img>`, `draggable="false"`, `user-select:none` on container).
- Position editor image is now displayed at its real pixel dimensions (no `max-height` constraint); the outer container scrolls if needed.
- Draggable labels initialize at the correct position on page load, reading saved X/Y values from the plugin configuration.

### Changed
- Folder distributed as `phonebg` (no suffix).
- Plugin logo redesigned: phone icon with a landscape wallpaper in the foreground, transparent background, no text. Replaces the previous blue-background version.
- README updated to display the logo as a centered header in both English and Spanish sections, and reflects all v1.2.0 features.

---

## [1.1.0] — 2025-03-08 *(unreleased — changes merged into v1.2.0)*

### Added
- **Visual position editor** in the settings page: drag-and-drop labels directly over the template preview to position device name and line number text.
- **`PluginPhonebgConfig` class** (`inc/config.class.php`): DB-backed layout configuration stored in `glpi_plugin_phonebg_config`. Survives plugin upgrades.
- **Per-field controls**: font size (px), X coordinate, Y coordinate, and font color (`<input type="color">`) for each text field.
- **X = 0 auto-center**: setting X to 0 horizontally centers the text regardless of image width (previous default behavior is now explicitly configurable).
- **Reset to defaults** button: restores all position and style settings to factory values without deleting the template.
- Positions and font color are now read from the database at generation time instead of being hard-coded.
- Inputs in the position table stay in sync with drag movements in real time.
- `plugin_phonebg_uninstall()` now drops the `glpi_plugin_phonebg_config` table on uninstall.
- `imagepng()` now uses compression level 6 for consistently smaller generated files (~30 % size reduction).
- README rewritten in English and Spanish (both in the same file).
- New `CHANGELOG.md`.

### Changed
- `background.class.php`: `drawCenteredText()` replaced by `drawText()` which accepts an explicit X coordinate (0 = auto-center).
- `setup.php`: version bumped to `1.1.0`; `plugin_phonebg_install()` calls `PluginPhonebgConfig::createTable()`.
- `phone.class.php`: removed stale commented-out `require_once` line.
- `config.form.php`: removed unused `global $CFG_GLPI` declaration.

### Locales
- 12 new translatable strings added across all four locales (es_MX, en_US, en_GB, fr_FR).
- All `.po` and `.mo` files updated to version 1.1.0 with 43 strings total.

---

## [1.0.1] — 2025-03-08

### Added
- Official plugin logo `logo.png` (128 × 128 px) for display in the GLPI Marketplace.

### Changed
- Version bumped to `1.0.1` in `setup.php` and `README.md`.

---

## [1.0.0] — 2025-02-26

### Added
- Initial release.
- Generates personalized PNG wallpapers for GLPI Phone assets using a custom PNG template.
- Overlays phone name and assigned line number (from `glpi_items_lines`) onto the template using GD + DejaVu Sans TTF.
- **Phone asset tab** ("Background") with a download button; button is disabled if no template has been uploaded.
- **Admin settings page** (`front/config.form.php`): upload template (PNG, max 500 KB), preview before saving, delete current template.
- `resource.send.php`: authenticated endpoint that serves the template image; sends HTTP cache headers (`ETag`, `Last-Modified`, `Cache-Control`) and handles `304 Not Modified`.
- `download.php`: generates the wallpaper PNG on demand and streams it as a file download; temp file is always deleted in a `finally` block.
- `PluginPhonebgPaths` class: centralized path and URL resolution supporting both `plugins/` and `marketplace/` installation directories.
- `PluginPhonebgBackground::checkRequirements()`: validates GD extension, template file, and TTF font before attempting generation.
- Auto-shrink logic: font size starts at 60 px and decreases until the device name fits within the image width minus 40 px margin.
- CSRF protection on all POST forms (`$PLUGIN_HOOKS['csrf_compliant']`).
- MIME type validation via `finfo` (not file extension).
- File size limit: 500 KB for template uploads.
- Filename sanitization in `Content-Disposition` headers (`preg_replace`).
- XSS escaping with `htmlspecialchars` for all user-visible phone names.
- Concurrent download safety: temp file names include `uniqid()` suffix.
- `Session::checkLoginUser()` on all front endpoints; `Session::checkRight('config', UPDATE)` on the settings page.
- Full i18n support: es_MX, en_US, en_GB, fr_FR — POT template + compiled `.mo` files.
- `README.md` and `LICENSE` (GPLv2).
