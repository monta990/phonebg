<p align="center">
  <img src="logo.png" width="128" alt="Phone Background Plugin Logo">
</p>

<h1 align="center">Phone Background </h1>

<p align="center">
  <strong>GLPI plugin — Generates personalized PNG wallpapers for corporate phones registered in GLPI</strong>
</p>

<p align="center">
  <a href="https://github.com/glpi-project/glpi" target="_blank"><img src="https://img.shields.io/badge/GLPI-11.0%2B-blue?style=flat-square" alt="GLPI compatibility"></a>
  <a href="https://www.gnu.org/licenses/old-licenses/gpl-2.0.html" target="_blank"><img src="https://img.shields.io/badge/License-GPL%20v2%2B-green?style=flat-square" alt="License"></a>
  <a href="https://php.net/" target="_blank"><img src="https://img.shields.io/badge/PHP-%3E%3D8.2-purple?style=flat-square" alt="PHP"></a>
</p>

---

## Overview

Phone Background generates personalized PNG wallpapers for corporate phones registered in GLPI. It overlays the phone's name and assigned line number onto a custom PNG template, and lets each phone record owner download the wallpaper directly from the Phone asset tab.

---

## Requirements

| Requirement     | Minimum version        |
|-----------------|------------------------|
| GLPI            | ≥ 11.0                 |
| PHP             | ≥ 8.1                  |
| PHP extension   | GD (image processing)  |

## Installation

1. Copy the `phonebg/` folder into your GLPI `marketplace/` or `plugins/` directory.
2. In GLPI, go to **Setup → Plugins** and click **Install**, then **Enable**.
3. Go to **Setup → Plugins → Phone Background** to upload your PNG template.

## Configuration

### Template tab

Upload a PNG image (max 500 KB) that will serve as the wallpaper background. The image can be any resolution — the plugin adapts to it automatically.

### Positions tab

The **Positions** tab (visible once a template has been uploaded) provides a full-size visual drag-and-drop editor:

- Drag the **Device name** and **Line number** labels directly over the template to position them.
- Adjust font size (px) and font color per field using the table inputs.
- **X = 0** centers the text horizontally regardless of image width.
- Click **Save** to persist the settings, or **Reset to defaults** to restore the original values.

Positions are stored in the `glpi_plugin_phonebg_config` database table and survive plugin upgrades.

## Usage

1. Open any **Phone** asset in GLPI.
2. Click the **Background** tab.
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
├── LICENSE                     # GPLv2
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

GPL v2 or later. See [LICENSE](https://www.gnu.org/licenses/old-licenses/gpl-2.0.html).

## Issues

Report bugs or request features on the [issue tracker](https://github.com/monta990/phonebg/issues).

---

<p align="center">
  <img src="logo.png" width="128" alt="Logo Plugin Phone Background">
</p>

<h1 align="center">Phone Background — Plugin para GLPI</h1>

<p align="center">
  <strong>Plugin para GLPI — genera fondos de pantalla PNG personalizados para los teléfonos corporativos registrados en GLPI</strong>
</p>

<p align="center">
  <a href="https://github.com/glpi-project/glpi" target="_blank"><img src="https://img.shields.io/badge/GLPI-11.0%2B-blue?style=flat-square" alt="GLPI compatibility"></a>
  <a href="https://www.gnu.org/licenses/old-licenses/gpl-2.0.html" target="_blank"><img src="https://img.shields.io/badge/License-GPL%20v2%2B-green?style=flat-square" alt="License"></a>
  <a href="https://php.net/" target="_blank"><img src="https://img.shields.io/badge/PHP-%3E%3D8.2-purple?style=flat-square" alt="PHP"></a>
</p>

---

## Descripción

Phone Background genera fondos de pantalla PNG personalizados para los teléfonos corporativos registrados en GLPI. Superpone el nombre del teléfono y el número de línea asignado sobre una plantilla PNG personalizable, y permite descargar el fondo directamente desde la pestaña del activo de tipo Teléfono.

---

## Requisitos

| Requisito       | Versión mínima                  |
|-----------------|---------------------------------|
| GLPI            | ≥ 11.0                          |
| PHP             | ≥ 8.2                           |
| Extensión PHP   | GD (procesamiento de imágenes)  |

## Instalación

1. Copia la carpeta `phonebg/` en el directorio `marketplace/` o `plugins/` de tu instalación de GLPI.
2. En GLPI ve a **Configuración → Complementos** y haz clic en **Instalar** y luego en **Activar**.
3. Ve a **Configuración → Complementos → Phone Background** para subir tu plantilla PNG.

## Configuración

### Pestaña Plantilla

Sube una imagen PNG (máx 500 KB) que servirá como fondo del wallpaper. La imagen puede ser de cualquier resolución; el plugin se adapta automáticamente.

### Pestaña Posiciones

La pestaña **Posiciones** (visible una vez que existe una plantilla) ofrece un editor visual completo con arrastrar y soltar:

- Arrastra las etiquetas **Nombre del equipo** y **Número de línea** directamente sobre la plantilla para posicionarlas.
- Ajusta el tamaño de fuente (px) y el color de fuente por campo usando los inputs de la tabla.
- **X = 0** centra el texto horizontalmente sin importar el ancho de la imagen.
- Haz clic en **Guardar** para persistir la configuración, o en **Restaurar valores por defecto** para regresar a los valores originales.

Las posiciones se almacenan en la tabla `glpi_plugin_phonebg_config` y sobreviven actualizaciones del plugin.

## Uso

1. Abre cualquier activo de tipo **Teléfono** en GLPI.
2. Haz clic en la pestaña **Fondo**.
3. Haz clic en **Vista previa** para ver el fondo generado en pantalla, o en **Descargar fondo** para guardar el PNG.

## Estructura de archivos

```
phonebg/
├── fonts/
│   └── DejaVuSans.ttf          # Fuente TrueType incluida
├── front/
│   ├── config.form.php         # Página de configuración con pestañas
│   ├── download.php            # Endpoint de generación, descarga y vista previa
│   └── resource.send.php       # Servidor autenticado de la imagen plantilla
├── inc/
│   ├── background.class.php    # Lógica de generación de imagen con GD
│   ├── config.class.php        # Configuración de diseño respaldada en BD
│   ├── paths.class.php         # Rutas y URLs centralizadas
│   └── phone.class.php         # Integración de la pestaña en el activo Teléfono
├── locales/                    # i18n: es_MX, en_US, en_GB, fr_FR
├── logo.png                    # Ícono del plugin (128×128, fondo transparente)
├── setup.php                   # Registro, hooks, instalación/desinstalación
├── LICENSE                     # GPLv2
└── README.md                   # Este archivo
```

## Desinstalación

Desactivar y desinstalar desde **Configuración → Complementos** elimina la tabla `glpi_plugin_phonebg_config`. Los archivos de plantilla y los fondos generados en `files/_plugins/phonebg/` se conservan intencionalmente.

---

## Cambios

Ver [CHANGELOG.md](CHANGELOG.md).

---

## Autor

**Edwin Elias Alvarez** — [GitHub](https://github.com/monta990)

---

## Comprame un cafe :)
Si te gusta mi trabajo, me puedes apoyar con una donación:

<a href="https://www.buymeacoffee.com/monta990" target="_blank"><img src="https://cdn.buymeacoffee.com/buttons/default-yellow.png" alt="Buy Me A Coffee" height="51px" width="210px"></a>

---

## Licencia

GPL v2 o posterior. Ver [LICENSE](https://www.gnu.org/licenses/old-licenses/gpl-2.0.html).

## Problemas

Reporta errores o solicita funcionalidades en el [issue tracker](https://github.com/monta990/phonebg/issues).
