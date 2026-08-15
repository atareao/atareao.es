# Timestamp Helper Block — Implementation Plan

> **For agentic workers:** Each task is self-contained and independently testable. Steps use checkbox (`- [ ]`) syntax. Implement in order.

**Goal:** Build a Gutenberg block `atareao/timestamp-helper` that converts Unix timestamps to/from human-readable dates, with bidirectional conversion, timezone selection, relative time, history via localStorage, URL sharing, and real-time updates — usable both as a Gutenberg block and as the standalone `/tools/timestamp/` page.

**Architecture:** Follow the exact pattern of `CrontabBlock` — a PHP class (`TimestampBlock`) registers assets and a dynamic block with `render_callback`. The render callback outputs an HTML container with `data-*` attributes and a `<noscript>` fallback. A frontend vanilla JS file hydrates the container on page load. The Gutenberg editor component (`index.js`) provides the full interactive UI using `wp.element.createElement` (no JSX). The existing `templates/tools-timestamp.php` is refactored to use `do_blocks()` with a hardcoded block comment, replacing the current inline JS+CSS.

**Tech Stack:** WordPress 6.0+, PHP 7.4+, Gutenberg `apiVersion: 3`, vanilla JS (no build tools), PSR-12, `atareao-functionality` plugin textdomain.

## Global Constraints

- All new files go under `assets/blocks/timestamp-helper/` and `includes/`
- Class name: `TimestampBlock` in namespace `Atareao`
- Block name: `atareao/timestamp-helper`
- Handle prefix: `atareao-timestamp-block-` / `atareao-timestamp-frontend`
- CSS class prefix: `atareao-timestamp-` (container), `ts-` (inner elements)
- No build tools — vanilla JS using `wp.element.createElement` in editor, vanilla JS (IIFE) for frontend
- No i18n `.po`/`.mo` files — use `__()` with `'atareao-functionality'` textdomain
- PSR-12 enforced for PHP; `// phpcs:ignore` comments allowed where PSR-12 conflicts with WP coding standards
- All attributes stored in `block.json` — no PHP-side attribute defaults beyond what `block.json` defines
- Version matches plugin: `ATAREAO_PLUGIN_VERSION` (currently `1.6.14`)
- `ABSPATH` guard at the top of every PHP file

---

## File Structure

### New files (6)

| File | Responsibility |
|---|---|
| `includes/class-timestamp-block.php` | PHP class: register assets, register block, render callback |
| `assets/blocks/timestamp-helper/block.json` | Block metadata: apiVersion 3, attributes, supports |
| `assets/blocks/timestamp-helper/index.js` | Gutenberg editor: full interactive UI using `wp.element.createElement` |
| `assets/blocks/timestamp-helper/timestamp-frontend.js` | Frontend vanilla JS: hydrates block on page load (IIFE) |
| `assets/blocks/timestamp-helper/style.css` | Shared styles: frontend + editor, dark mode |
| `assets/blocks/timestamp-helper/editor.css` | Editor-only styles: dashed border, placeholder, inspector panel |

### Modified files (2)

| File | Change |
|---|---|
| `atareao-functionality.php` | Add `require_once` + `::init()` call |
| `templates/tools-timestamp.php` | Replace inline JS+CSS with `do_blocks()` + static block comment |

---

## Tasks

### Task 1: Create block.json metadata

**Files:**
- Create: `assets/blocks/timestamp-helper/block.json`

- [ ] **Step 1: Write block.json**

Create the file with apiVersion 3, attributes for all configurable state, and supports matching the crontab pattern.

```json
{
    "$schema": "https://schemas.wp.org/trunk/block.json",
    "apiVersion": 3,
    "name": "atareao/timestamp-helper",
    "title": "Timestamp Helper",
    "category": "widget",
    "icon": "clock",
    "description": "Convierte Unix timestamp a fecha legible y viceversa. Soporta UTC, local y zonas horarias. Comparte resultados por URL.",
    "keywords": [
        "timestamp",
        "epoch",
        "unix",
        "fecha",
        "conversor"
    ],
    "version": "1.0.0",
    "textdomain": "atareao-functionality",
    "supports": {
        "html": false,
        "align": [
            "wide",
            "full"
        ],
        "spacing": {
            "margin": true,
            "padding": true
        },
        "color": {
            "background": true,
            "text": true
        }
    },
    "attributes": {
        "timestamp": {
            "type": "string",
            "default": ""
        },
        "unit": {
            "type": "string",
            "default": "s",
            "enum": ["s", "ms"]
        },
        "timezone": {
            "type": "string",
            "default": "local"
        },
        "showHistory": {
            "type": "boolean",
            "default": true
        }
    }
}
```

- [ ] **Step 2: Verify file exists and JSON is valid**

```bash
php -r "echo json_last_error_msg();" < /dev/null; python3 -m json.tool /data/php/atareao.es/wp-content/plugins/atareao-functionality/assets/blocks/timestamp-helper/block.json > /dev/null && echo "VALID JSON"
```

- [ ] **Step 3: Commit**

```bash
git add wp-content/plugins/atareao-functionality/assets/blocks/timestamp-helper/block.json
git commit -m "feat(timestamp-block): add block.json metadata"
```

---

### Task 2: Create shared styles (style.css) with dark mode

**Files:**
- Create: `assets/blocks/timestamp-helper/style.css`

**Produces:** Full style sheet used by both frontend and editor. Later tasks reference classes `.atareao-timestamp-helper`, `.ts-grid`, `.ts-results-table`, `.ts-action`, `.ts-example`, `.ts-history-list`.

- [ ] **Step 1: Write style.css**

```css
/**
 * Style CSS - Timestamp Helper Block
 * Estilos compartidos: frontend + editor
 */

/* ====== Contenedor principal ====== */

.atareao-timestamp-helper {
    max-width: 100%;
    padding: 1.5rem;
    border-radius: 12px;
    background: var(--ts-bg, #ffffff);
    border: 1px solid var(--ts-border, #e5e7eb);
    box-shadow: 0 1px 3px rgba(0,0,0,0.06);
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
    line-height: 1.6;
    color: var(--ts-text, #1f2937);
}

.atareao-timestamp-helper * {
    box-sizing: border-box;
}

.atareao-timestamp-helper p {
    margin: 0 0 0.75rem;
}

/* ====== Grid de dos columnas ====== */

.ts-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.8rem;
    margin-bottom: 0.75rem;
}

@media (max-width: 600px) {
    .ts-grid {
        grid-template-columns: 1fr;
    }
}

/* ====== Form elements ====== */

.atareao-timestamp-helper label {
    display: block;
    font-weight: 600;
    font-size: 0.85rem;
    margin-bottom: 0.3rem;
    color: var(--ts-text, #1f2937);
}

.atareao-timestamp-helper input[type="text"],
.atareao-timestamp-helper input[type="datetime-local"],
.atareao-timestamp-helper select {
    width: 100%;
    padding: 0.45rem 0.55rem;
    border: 1.5px solid var(--ts-border-input, #c3cfe2);
    border-radius: 8px;
    background: var(--ts-bg-input, #f7fafd);
    color: var(--ts-text, #1f2937);
    font-size: 0.95rem;
    line-height: 1.4;
    transition: border-color 0.15s ease;
}

.atareao-timestamp-helper input[type="text"]:focus,
.atareao-timestamp-helper input[type="datetime-local"]:focus,
.atareao-timestamp-helper select:focus {
    border-color: var(--ts-primary, #0073aa);
    outline: none;
    box-shadow: 0 0 0 2px rgba(0,115,170,0.15);
}

/* ====== Buttons ====== */

.ts-action,
.ts-example {
    display: inline-block;
    border: 0;
    border-radius: 6px;
    background: var(--ts-primary, #0073aa);
    color: #fff;
    cursor: pointer;
    font-size: 0.9rem;
    line-height: 1.2;
    padding: 0.35rem 0.6rem;
    margin: 0 0.25rem 0.25rem 0;
    transition: filter 0.15s ease;
}

.ts-action:hover,
.ts-example:hover {
    filter: brightness(0.94);
}

.ts-action:active,
.ts-example:active {
    filter: brightness(0.88);
}

.ts-actions-bar {
    text-align: center;
    margin-bottom: 1rem;
}

/* ====== Results table ====== */

.ts-results-table-wrap {
    overflow-x: auto;
    margin-top: 0.75rem;
}

.ts-results-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 0.5rem;
}

.ts-results-table th,
.ts-results-table td {
    padding: 0.5rem 0.55rem;
    border-bottom: 1px solid var(--ts-border-row, #e5e7eb);
    text-align: left;
    vertical-align: top;
    color: var(--ts-text, #1f2937);
}

.ts-results-table th {
    font-weight: 700;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.ts-results-value {
    display: inline-block;
    padding: 0.15rem 0.45rem;
    border-radius: 0.4rem;
    font-size: 0.92em;
    font-family: 'Courier New', Courier, monospace;
    border: 1px solid var(--ts-border-code, #d9d9d9);
    background: var(--ts-bg-code, #f5f5f5);
    color: var(--ts-text-code, #1f2937);
    word-break: break-all;
}

/* ====== Summary ====== */

.ts-summary {
    font-style: italic;
    color: var(--ts-text-muted, #666);
    padding: 0.5rem 0;
}

/* ====== Relative time badge ====== */

.ts-relative-badge {
    display: inline-block;
    padding: 0.2rem 0.6rem;
    border-radius: 999px;
    font-size: 0.8rem;
    font-weight: 600;
    background: var(--ts-bg-badge, #e8f4fd);
    color: var(--ts-text-badge, #005a87);
}

/* ====== History ====== */

.ts-history-section {
    margin-top: 1.25rem;
    padding-top: 1rem;
    border-top: 1px solid var(--ts-border, #e5e7eb);
}

.ts-history-section h4 {
    margin: 0 0 0.5rem;
    font-size: 0.95rem;
    color: var(--ts-text, #1f2937);
}

.ts-history-list {
    list-style: none;
    padding: 0;
    margin: 0;
    max-height: 200px;
    overflow-y: auto;
}

.ts-history-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.35rem 0.5rem;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.85rem;
    font-family: 'Courier New', Courier, monospace;
    transition: background 0.12s ease;
}

.ts-history-item:hover {
    background: var(--ts-bg-hover, #f0f4f8);
}

.ts-history-item .ts-history-ts {
    font-weight: 600;
}

.ts-history-item .ts-history-date {
    font-size: 0.8rem;
    opacity: 0.7;
}

.ts-history-clear {
    font-size: 0.8rem;
    color: var(--ts-primary, #0073aa);
    cursor: pointer;
    border: 0;
    background: none;
    padding: 0.25rem 0;
    text-decoration: underline;
}

/* ====== Error message ====== */

.ts-error {
    display: none;
    padding: 0.6rem 0.8rem;
    border-radius: 8px;
    background: #fef0f0;
    color: #cc1818;
    border-left: 3px solid #cc1818;
    margin-bottom: 0.75rem;
    font-size: 0.9rem;
}

.ts-error.visible {
    display: block;
}

/* ====== Status notice ====== */

.ts-notice {
    padding: 0.5rem 0.75rem;
    border-radius: 8px;
    font-size: 0.85rem;
    margin-bottom: 0.75rem;
}

.ts-notice-success {
    background: #f0fef0;
    color: #007017;
    border-left: 3px solid #007017;
}

.ts-notice-info {
    background: #e8f4fd;
    color: #005a87;
    border-left: 3px solid #005a87;
}

/* ====== Loading spinner ====== */

.atareao-timestamp-loading {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 2rem;
    color: var(--ts-text-muted, #666);
}

.atareao-timestamp-spinner {
    display: inline-block;
    width: 20px;
    height: 20px;
    border: 3px solid var(--ts-border, #e5e7eb);
    border-top-color: var(--ts-primary, #0073aa);
    border-radius: 50%;
    animation: atareao-spin 0.6s linear infinite;
}

@keyframes atareao-spin {
    to { transform: rotate(360deg); }
}

/* ====== Noscript fallback ====== */

.atareao-timestamp-noscript {
    padding: 1.5rem;
    text-align: center;
    background: var(--ts-bg-warning, #fff8e6);
    border: 1px solid #f0d78e;
    border-radius: 8px;
    color: #8a6d00;
}

/* ====== Dark mode (data-theme attribute) ====== */

[data-theme="dark"] .atareao-timestamp-helper {
    --ts-bg: #1e1e2e;
    --ts-border: #334155;
    --ts-text: #e5e7eb;
    --ts-border-input: #44506a;
    --ts-bg-input: #151617;
    --ts-border-row: #334155;
    --ts-border-code: #44506a;
    --ts-bg-code: #1d2538;
    --ts-text-code: #e5e7eb;
    --ts-text-muted: #9ca3af;
    --ts-bg-badge: #1e3a5f;
    --ts-text-badge: #8bc3e6;
    --ts-bg-hover: #2a2a3e;
    --ts-bg-warning: #2a2510;
}

/* ====== Dark mode (prefers-color-scheme) ====== */

@media (prefers-color-scheme: dark) {
    html:not([data-theme="light"]) .atareao-timestamp-helper {
        --ts-bg: #1e1e2e;
        --ts-border: #334155;
        --ts-text: #e5e7eb;
        --ts-border-input: #44506a;
        --ts-bg-input: #151617;
        --ts-border-row: #334155;
        --ts-border-code: #44506a;
        --ts-bg-code: #1d2538;
        --ts-text-code: #e5e7eb;
        --ts-text-muted: #9ca3af;
        --ts-bg-badge: #1e3a5f;
        --ts-text-badge: #8bc3e6;
        --ts-bg-hover: #2a2a3e;
        --ts-bg-warning: #2a2510;
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add wp-content/plugins/atareao-functionality/assets/blocks/timestamp-helper/style.css
git commit -m "feat(timestamp-block): add shared styles with dark mode"
```

---

### Task 3: Create editor-only styles (editor.css)

**Files:**
- Create: `assets/blocks/timestamp-helper/editor.css`

**Produces:** Editor placeholder appearance and inspector control styling.

- [ ] **Step 1: Write editor.css**

```css
/**
 * Editor CSS - Timestamp Helper Block
 * Estilos exclusivos del editor de WordPress
 */

.atareao-timestamp-helper-editor {
    border: 2px dashed var(--ts-border, #c3cfe2);
    border-radius: 12px;
    padding: 2rem 1.5rem;
    text-align: center;
    background: var(--ts-bg-editor, #f7fafd);
    min-height: 200px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
}

.atareao-timestamp-helper-editor .ts-editor-icon {
    font-size: 3rem;
    width: 3rem;
    height: 3rem;
    color: var(--ts-primary, #0073aa);
    opacity: 0.6;
}

.atareao-timestamp-helper-editor .ts-editor-icon .dashicons-clock {
    font-size: 3rem;
    width: 3rem;
    height: 3rem;
}

.atareao-timestamp-helper-editor h3 {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 600;
    color: #1e1e1e;
}

.atareao-timestamp-helper-editor p {
    margin: 0;
    color: #666;
    font-size: 0.9rem;
}

.atareao-timestamp-helper-editor .ts-editor-preview {
    margin-top: 0.5rem;
    padding: 0.5rem 1rem;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-family: 'Courier New', monospace;
    font-size: 0.95rem;
    color: #333;
    max-width: 100%;
    overflow-x: auto;
}

/* Dark mode en editor */
.wp-block.is-dark-theme .atareao-timestamp-helper-editor {
    background: #1e1e2e;
    border-color: #44506a;
}

.wp-block.is-dark-theme .atareao-timestamp-helper-editor h3 {
    color: #e5e7eb;
}

.wp-block.is-dark-theme .atareao-timestamp-helper-editor p {
    color: #9ca3af;
}

.wp-block.is-dark-theme .atareao-timestamp-helper-editor .ts-editor-preview {
    background: #1a1a2e;
    border-color: #44506a;
    color: #e5e7eb;
}

/* Inspector controls */
.ts-inspector-input {
    font-family: 'Courier New', monospace;
    font-size: 0.95rem;
    padding: 0.4rem;
    width: 100%;
    box-sizing: border-box;
}

.ts-inspector-examples {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
    margin-top: 0.5rem;
}

.ts-inspector-examples button {
    font-size: 0.8rem;
    padding: 0.2rem 0.5rem;
    border: 1px solid #ddd;
    border-radius: 4px;
    background: #f0f0f1;
    cursor: pointer;
    transition: all 0.15s ease;
}

.ts-inspector-examples button:hover {
    background: #e0e0e1;
    border-color: #0073aa;
    color: #0073aa;
}

.ts-inspector-preview {
    font-style: italic;
    color: #555;
    margin-top: 0.35rem;
    font-size: 0.85rem;
}
```

- [ ] **Step 2: Commit**

```bash
git add wp-content/plugins/atareao-functionality/assets/blocks/timestamp-helper/editor.css
git commit -m "feat(timestamp-block): add editor-only styles"
```

---

### Task 4: Create PHP class TimestampBlock

**Files:**
- Create: `includes/class-timestamp-block.php`

**Interfaces:**
- Consumes: `block.json` (Task 1) — reads block metadata from the same directory
- Produces: `\Atareao\TimestampBlock::init()` — called from `atareao_functionality_init()`
- Produces: `renderTimestampHelper($attributes)` — returns HTML string with `data-*` attributes

- [ ] **Step 1: Write class-timestamp-block.php**

This mirrors `class-crontab-block.php` exactly in structure. The render callback outputs the container div, loading spinner, and `<noscript>` fallback.

```php
<?php
/**
 * Clase para el bloque de Timestamp Helper
 *
 * Bloque Gutenberg dinamico para convertir Unix timestamps
 * a fecha legible y viceversa, con soporte de zonas horarias,
 * historial local y URLs compartibles.
 *
 * @package Atareao_Functionality
 */

namespace Atareao;

if (!defined('ABSPATH')) {
    exit;
}

class TimestampBlock
{

    /**
     * Inicializar el bloque
     */
    public static function init()
    {
        self::registerAssets();
        self::registerBlock();
    }

    /**
     * Registrar assets del bloque
     */
    public static function registerAssets()
    {
        wp_register_script(
            'atareao-timestamp-block-editor',
            ATAREAO_PLUGIN_URL . 'assets/blocks/timestamp-helper/index.js',
            array('wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-data', 'wp-api-fetch'),
            ATAREAO_PLUGIN_VERSION,
            false
        );

        wp_register_style(
            'atareao-timestamp-block-editor',
            ATAREAO_PLUGIN_URL . 'assets/blocks/timestamp-helper/editor.css',
            array('wp-edit-blocks'),
            ATAREAO_PLUGIN_VERSION
        );

        wp_register_style(
            'atareao-timestamp-block-style',
            ATAREAO_PLUGIN_URL . 'assets/blocks/timestamp-helper/style.css',
            array(),
            ATAREAO_PLUGIN_VERSION
        );

        wp_register_script(
            'atareao-timestamp-frontend',
            ATAREAO_PLUGIN_URL . 'assets/blocks/timestamp-helper/timestamp-frontend.js',
            array(),
            ATAREAO_PLUGIN_VERSION,
            true
        );
    }

    /**
     * Registrar el bloque de Timestamp Helper
     */
    public static function registerBlock()
    {
        if (!function_exists('register_block_type')) {
            return;
        }

        register_block_type(
            ATAREAO_PLUGIN_DIR . 'assets/blocks/timestamp-helper',
            array(
                'editor_script' => 'atareao-timestamp-block-editor',
                'editor_style' => 'atareao-timestamp-block-editor',
                'style' => 'atareao-timestamp-block-style',
                'render_callback' => array(__CLASS__, 'renderTimestampHelper'),
            )
        );
    }

    /**
     * Renderizar el bloque en el frontend
     *
     * @param array $attributes Atributos del bloque.
     * @return string HTML del bloque.
     */
    public static function renderTimestampHelper($attributes)
    {
        $timestamp = isset($attributes['timestamp']) ? esc_attr($attributes['timestamp']) : '';
        $unit = isset($attributes['unit']) ? esc_attr($attributes['unit']) : 's';
        $timezone = isset($attributes['timezone']) ? esc_attr($attributes['timezone']) : 'local';
        $show_history = !empty($attributes['showHistory']) ? '1' : '0';

        $container_id = 'atareao-timestamp-' . uniqid();

        // Enqueue frontend script
        wp_enqueue_script('atareao-timestamp-frontend');

        ob_start();
        ?>
        <div class="atareao-timestamp-helper"
             id="<?php echo esc_attr($container_id); ?>"
             data-timestamp="<?php echo $timestamp; ?>"
             data-unit="<?php echo $unit; ?>"
             data-timezone="<?php echo $timezone; ?>"
             data-show-history="<?php echo $show_history; ?>">
            <div class="atareao-timestamp-loading">
                <span class="atareao-timestamp-spinner"></span>
                <?php esc_html_e('Cargando Timestamp Helper...', 'atareao-functionality'); ?>
            </div>
        </div>
        <noscript>
            <div class="atareao-timestamp-noscript">
                <p><?php esc_html_e('El Timestamp Helper necesita JavaScript para funcionar.', 'atareao-functionality'); ?></p>
                <?php if (!empty($timestamp)) : ?>
                    <p><?php esc_html_e('Timestamp:', 'atareao-functionality'); ?>
                    <code><?php echo $timestamp; ?></code></p>
                <?php endif; ?>
            </div>
        </noscript>
        <?php
        return ob_get_clean();
    }
}
```

- [ ] **Step 2: Verify syntax**

```bash
php -l includes/class-timestamp-block.php
```

Expected:
```
No syntax errors detected in includes/class-timestamp-block.php
```

- [ ] **Step 3: Commit**

```bash
git add wp-content/plugins/atareao-functionality/includes/class-timestamp-block.php
git commit -m "feat(timestamp-block): add PHP class with asset registration and render callback"
```

---

### Task 5: Register the class in the main plugin file

**Files:**
- Modify: `atareao-functionality.php:30` (add `require_once` after line 30, after crontab-block)
- Modify: `atareao-functionality.php:47` (add `::init()` call after CrontabBlock)

- [ ] **Step 1: Add require_once**

Edit line ~30 area to add the `require_once` for the new class:

```php
require_once ATAREAO_PLUGIN_DIR . 'includes/class-crontab-block.php';
require_once ATAREAO_PLUGIN_DIR . 'includes/class-timestamp-block.php';
```

- [ ] **Step 2: Add init() call**

Edit the `atareao_functionality_init()` function to add:

```php
\Atareao\CrontabBlock::init();
\Atareao\TimestampBlock::init();
```

- [ ] **Step 3: Verify syntax**

```bash
php -l atareao-functionality.php
```

Expected:
```
No syntax errors detected in atareao-functionality.php
```

- [ ] **Step 4: Commit**

```bash
git add wp-content/plugins/atareao-functionality/atareao-functionality.php
git commit -m "feat(timestamp-block): register TimestampBlock class in plugin bootstrap"
```

---

### Task 6: Create the Gutenberg editor component (index.js)

**Files:**
- Create: `assets/blocks/timestamp-helper/index.js`

**Interfaces:**
- Consumes: `block.json` attributes: `timestamp`, `unit`, `timezone`, `showHistory`
- Produces: Registered block type `atareao/timestamp-helper` with Edit and Save functions
- Produces: Frontend data attributes matching `renderTimestampHelper()` expectations

This is the largest file (~600-800 lines). It uses `wp.element.createElement` (no JSX) following the exact pattern from the crontab block's `index.js`.

**Features in editor:**
1. Text input for Unix timestamp with auto-detect seconds/milliseconds
2. Unit selector (seconds/milliseconds)
3. Timezone selector (local, UTC, Europe/Madrid, America/Bogota, America/Mexico_City)
4. Datetime-local input for reverse conversion (date→epoch)
5. Quick example buttons (epoch 0, Y2K, 2024-01-01, ms example)
6. Action buttons: From Unix, From Date, Now, Copy Link
7. Results table: Unix seconds, Unix ms, ISO 8601, RFC 2822, UTC, local, selected timezone, relative time
8. History section (localStorage) with click-to-restore and clear
9. URL parameter pre-fill on mount
10. Real-time conversion on input change (debounced)

- [ ] **Step 1: Write index.js**

```javascript
/**
 * Bloque de Timestamp Helper
 *
 * Editor Gutenberg con:
 * - Conversion bidireccional Unix timestamp <-> fecha legible
 * - Auto-deteccion de segundos (10 digitos) vs milisegundos (13 digitos)
 * - Resultados en ISO 8601, RFC 2822, UTC, local y zona seleccionada
 * - Tiempo relativo ("hace 2 horas", "en 3 dias")
 * - Historial en localStorage
 * - URLs compartibles via query params
 * - Actualizacion en tiempo real
 * - Botones de ejemplo rapido
 */
(function (wp) {
    'use strict';

    var registerBlockType = wp.blocks.registerBlockType;
    var InspectorControls = wp.blockEditor.InspectorControls;
    var useBlockProps = wp.blockEditor.useBlockProps;
    var PanelBody = wp.components.PanelBody;
    var TextControl = wp.components.TextControl;
    var SelectControl = wp.components.SelectControl;
    var ToggleControl = wp.components.ToggleControl;
    var Button = wp.components.Button;
    var Notice = wp.components.Notice;
    var el = wp.element.createElement;
    var useState = wp.element.useState;
    var useEffect = wp.element.useEffect;
    var useRef = wp.element.useRef;
    var useCallback = wp.element.useCallback;
    var __ = wp.i18n.__;

    /* ====================================================================
     * TIMEZONE LIST
     * ==================================================================== */

    var TIMEZONES = [
        { label: 'Local del navegador', value: 'local' },
        { label: 'UTC', value: 'UTC' },
        { label: 'Europe/Madrid', value: 'Europe/Madrid' },
        { label: 'America/Bogota', value: 'America/Bogota' },
        { label: 'America/Mexico_City', value: 'America/Mexico_City' },
        { label: 'America/Argentina/Buenos_Aires', value: 'America/Argentina/Buenos_Aires' },
        { label: 'America/Santiago', value: 'America/Santiago' },
        { label: 'America/Lima', value: 'America/Lima' },
        { label: 'America/Sao_Paulo', value: 'America/Sao_Paulo' },
        { label: 'Europe/London', value: 'Europe/London' },
        { label: 'Europe/Berlin', value: 'Europe/Berlin' },
        { label: 'Europe/Paris', value: 'Europe/Paris' },
        { label: 'Asia/Tokyo', value: 'Asia/Tokyo' },
        { label: 'Asia/Shanghai', value: 'Asia/Shanghai' },
        { label: 'Asia/Kolkata', value: 'Asia/Kolkata' },
        { label: 'Australia/Sydney', value: 'Australia/Sydney' },
        { label: 'Pacific/Auckland', value: 'Pacific/Auckland' },
    ];

    var UNITS = [
        { label: 'Segundos (10 digitos)', value: 's' },
        { label: 'Milisegundos (13 digitos)', value: 'ms' },
    ];

    var QUICK_EXAMPLES = [
        { ts: '0', unit: 's', label: 'epoch 0' },
        { ts: '946684800', unit: 's', label: 'Y2K' },
        { ts: '1704067200', unit: 's', label: '2024-01-01' },
        { ts: '1714132800000', unit: 'ms', label: 'ms example' },
    ];

    var HISTORY_KEY = 'atareao_timestamp_history';
    var MAX_HISTORY = 20;

    /* ====================================================================
     * UTILITY FUNCTIONS
     * ==================================================================== */

    function getTimestamp(millis, unit) {
        return unit === 'ms' ? millis : Math.floor(millis / 1000);
    }

    function getMilliseconds(value, unit) {
        return unit === 'ms' ? value : value * 1000;
    }

    function autoDetectUnit(value) {
        var str = String(value).replace(/^-/, '');
        return str.length >= 12 ? 'ms' : 's';
    }

    function isValidNumeric(value) {
        return /^[-]?\d+$/.test(String(value).trim());
    }

    function formatForTimezone(date, timezone) {
        try {
            if (timezone === 'local') {
                return date.toLocaleString('es-ES', { hour12: false });
            }
            return new Intl.DateTimeFormat('es-ES', {
                timeZone: timezone,
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: false,
            }).format(date);
        } catch (e) {
            return date.toLocaleString('es-ES', { hour12: false });
        }
    }

    function formatRelativeTime(date) {
        var now = new Date();
        var diffMs = date.getTime() - now.getTime();
        var absMs = Math.abs(diffMs);
        var seconds = Math.floor(absMs / 1000);
        var minutes = Math.floor(seconds / 60);
        var hours = Math.floor(minutes / 60);
        var days = Math.floor(hours / 24);
        var weeks = Math.floor(days / 7);
        var months = Math.floor(days / 30);
        var years = Math.floor(days / 365);

        var prefix = diffMs < 0 ? 'hace ' : 'en ';
        var suffix = diffMs < 0 ? '' : '';

        if (seconds < 60) return prefix + 'unos segundos';
        if (minutes < 60) return prefix + minutes + ' minuto' + (minutes !== 1 ? 's' : '');
        if (hours < 24) return prefix + hours + ' hora' + (hours !== 1 ? 's' : '');
        if (days < 7) return prefix + days + ' dia' + (days !== 1 ? 's' : '');
        if (weeks < 5) return prefix + weeks + ' semana' + (weeks !== 1 ? 's' : '');
        if (months < 12) return prefix + months + ' mes' + (months !== 1 ? 'es' : '');
        return prefix + years + ' año' + (years !== 1 ? 's' : '');
    }

    function pad(n) {
        return String(n).padStart(2, '0');
    }

    function dateToDatetimeLocal(date) {
        var local = new Date(date.getTime() - date.getTimezoneOffset() * 60000);
        return local.getFullYear() + '-' +
            pad(local.getMonth() + 1) + '-' +
            pad(local.getDate()) + 'T' +
            pad(local.getHours()) + ':' +
            pad(local.getMinutes());
    }

    function getTimezoneOffsetName() {
        var offset = -new Date().getTimezoneOffset();
        var sign = offset >= 0 ? '+' : '-';
        var h = pad(Math.floor(Math.abs(offset) / 60));
        var m = pad(Math.abs(offset) % 60);
        return 'UTC' + sign + h + ':' + m;
    }

    function loadHistory() {
        try {
            var raw = localStorage.getItem(HISTORY_KEY);
            return raw ? JSON.parse(raw) : [];
        } catch (e) {
            return [];
        }
    }

    function saveToHistory(timestamp, unit, results) {
        var history = loadHistory();
        var entry = {
            id: Date.now(),
            timestamp: timestamp,
            unit: unit,
            iso: results.iso,
            timezone: results.timezone,
        };
        // Remove duplicate if exists
        history = history.filter(function (h) {
            return !(h.timestamp === entry.timestamp && h.unit === entry.unit);
        });
        history.unshift(entry);
        if (history.length > MAX_HISTORY) {
            history = history.slice(0, MAX_HISTORY);
        }
        try {
            localStorage.setItem(HISTORY_KEY, JSON.stringify(history));
        } catch (e) {
            // localStorage full or unavailable
        }
        return history;
    }

    function clearHistory() {
        try {
            localStorage.removeItem(HISTORY_KEY);
        } catch (e) {
            // ignore
        }
    }

    /* ====================================================================
     * CONVERSION ENGINE
     * ==================================================================== */

    function convert(timestamp, unit, timezone) {
        var raw = String(timestamp).trim();
        if (!isValidNumeric(raw)) {
            return { error: 'El timestamp debe ser numerico.' };
        }

        var parsed = Number(raw);
        if (!isFinite(parsed)) {
            return { error: 'Timestamp invalido.' };
        }

        var millis = getMilliseconds(parsed, unit);
        var date = new Date(millis);

        if (isNaN(date.getTime())) {
            return { error: 'No se pudo convertir el timestamp.' };
        }

        var unixSeconds = Math.floor(millis / 1000);
        var tzFormatted = formatForTimezone(date, timezone);

        return {
            error: null,
            unixSeconds: String(unixSeconds),
            unixMillis: String(millis),
            iso8601: date.toISOString(),
            rfc2822: date.toUTCString(),
            utc: date.toUTCString(),
            local: date.toString(),
            timezoneFormatted: tzFormatted + ' (' + timezone + ')',
            timezone: timezone,
            relative: formatRelativeTime(date),
            date: date,
            iso: date.toISOString(),
        };
    }

    function convertFromDate(dateValue, unit, timezone) {
        if (!dateValue) {
            return { error: 'Selecciona una fecha y hora para convertir.' };
        }
        var date = new Date(dateValue);
        if (isNaN(date.getTime())) {
            return { error: 'Fecha invalida.' };
        }
        var millis = date.getTime();
        var ts = unit === 'ms' ? String(millis) : String(Math.floor(millis / 1000));
        return { timestamp: ts, results: convert(ts, unit, timezone) };
    }

    /* ====================================================================
     * RESULT ROWS
     * ==================================================================== */

    var RESULT_FIELDS = [
        { key: 'unixSeconds', label: 'Unix (segundos)' },
        { key: 'unixMillis', label: 'Unix (milisegundos)' },
        { key: 'iso8601', label: 'ISO 8601' },
        { key: 'rfc2822', label: 'RFC 2822' },
        { key: 'utc', label: 'UTC' },
        { key: 'local', label: 'Local navegador' },
        { key: 'timezoneFormatted', label: 'Zona seleccionada' },
    ];

    /* ====================================================================
     * EDIT COMPONENT
     * ==================================================================== */

    function TimestampEditor(props) {
        var attributes = props.attributes;
        var setAttributes = props.setAttributes;

        var timestamp = attributes.timestamp || '';
        var unit = attributes.unit || 's';
        var timezone = attributes.timezone || 'local';
        var showHistory = attributes.showHistory !== false;

        var _useState = useState(timestamp);
        var inputValue = _useState[0];
        var setInputValue = _useState[1];

        var _useState2 = useState('');
        var dateInputValue = _useState2[0];
        var setDateInputValue = _useState2[1];

        var _useState3 = useState(null);
        var results = _useState3[0];
        var setResults = _useState3[1];

        var _useState4 = useState('');
        var summary = _useState4[0];
        var setSummary = _useState4[1];

        var _useState5 = useState('');
        var errorMessage = _useState5[0];
        var setErrorMessage = _useState5[1];

        var _useState6 = useState([]);
        var history = _useState6[0];
        var setHistory = _useState6[1];

        var _useState7 = useState('');
        var copyButtonText = _useState7[0];
        var setCopyButtonText = _useState7[1];

        var debounceRef = useRef(null);
        var hasInitialized = useRef(false);

        // Load history on mount
        useEffect(function () {
            setHistory(loadHistory());
        }, []);

        // Initial conversion on mount (from URL params or default)
        useEffect(function () {
            if (hasInitialized.current) return;
            hasInitialized.current = true;

            var urlParams = new URLSearchParams(window.location.search);
            var tsParam = urlParams.get('ts');
            var unitParam = urlParams.get('unit');
            var tzParam = urlParams.get('tz');

            var initialTs = tsParam || timestamp || '1714132800';
            var initialUnit = (unitParam === 's' || unitParam === 'ms') ? unitParam : unit;
            var initialTz = tzParam || timezone;

            if (tsParam || timestamp) {
                setInputValue(initialTs);
                setAttributes({ timestamp: initialTs });
            }
            if (unitParam) {
                setAttributes({ unit: initialUnit });
            }
            if (tzParam) {
                setAttributes({ timezone: initialTz });
            }

            var conv = convert(initialTs, initialUnit, initialTz);
            if (conv && !conv.error) {
                setResults(conv);
                setSummary('Timestamp convertido correctamente.');
                setDateInputValue(dateToDatetimeLocal(conv.date));
                var updated = saveToHistory(initialTs, initialUnit, conv);
                setHistory(updated);
            } else if (conv) {
                setErrorMessage(conv.error);
            }
        }, []);

        // Auto-detect unit when input changes
        useEffect(function () {
            if (!inputValue) return;
            if (!isValidNumeric(inputValue)) return;
            var detected = autoDetectUnit(inputValue);
            if (detected !== unit) {
                setAttributes({ unit: detected });
            }
        }, [inputValue]);

        // Debounced real-time conversion
        useEffect(function () {
            if (!inputValue) return;
            if (!isValidNumeric(inputValue)) return;

            if (debounceRef.current) {
                clearTimeout(debounceRef.current);
            }

            debounceRef.current = setTimeout(function () {
                var detectedUnit = autoDetectUnit(inputValue);
                var conv = convert(inputValue, detectedUnit, timezone);
                if (conv && !conv.error) {
                    setResults(conv);
                    setErrorMessage('');
                    setSummary('Conversion en tiempo real.');
                    setDateInputValue(dateToDatetimeLocal(conv.date));
                }
            }, 300);

            return function () {
                if (debounceRef.current) {
                    clearTimeout(debounceRef.current);
                }
            };
        }, [inputValue, timezone]);

        function handleConvertFromUnix() {
            setErrorMessage('');
            if (!inputValue || !isValidNumeric(inputValue)) {
                setErrorMessage('Introduce un timestamp numerico.');
                return;
            }
            var detectedUnit = autoDetectUnit(inputValue);
            var conv = convert(inputValue, detectedUnit, timezone);
            if (conv && !conv.error) {
                setResults(conv);
                setSummary('Timestamp convertido correctamente.');
                setDateInputValue(dateToDatetimeLocal(conv.date));
                setAttributes({ timestamp: inputValue, unit: detectedUnit });
                var updated = saveToHistory(inputValue, detectedUnit, conv);
                setHistory(updated);
                updateShareUrl(inputValue, detectedUnit, timezone);
            } else if (conv) {
                setErrorMessage(conv.error);
            }
        }

        function handleConvertFromDate() {
            setErrorMessage('');
            var result = convertFromDate(dateInputValue, unit, timezone);
            if (result.error) {
                setErrorMessage(result.error);
                return;
            }
            setInputValue(result.timestamp);
            setAttributes({ timestamp: result.timestamp });
            setResults(result.results);
            setSummary('Fecha convertida a Unix correctamente.');
            if (result.results && !result.results.error) {
                var updated = saveToHistory(result.timestamp, unit, result.results);
                setHistory(updated);
                updateShareUrl(result.timestamp, unit, timezone);
            }
        }

        function handleNow() {
            var now = new Date();
            var nowMs = now.getTime();
            var nowTs = unit === 'ms' ? String(nowMs) : String(Math.floor(nowMs / 1000));
            setInputValue(nowTs);
            setAttributes({ timestamp: nowTs });
            setDateInputValue(dateToDatetimeLocal(now));
            var conv = convert(nowTs, unit, timezone);
            if (conv && !conv.error) {
                setResults(conv);
                setSummary('Timestamp actual.');
                var updated = saveToHistory(nowTs, unit, conv);
                setHistory(updated);
                updateShareUrl(nowTs, unit, timezone);
            }
        }

        function handleExample(ts, exUnit) {
            setInputValue(ts);
            setAttributes({ timestamp: ts, unit: exUnit });
            setDateInputValue('');
            var conv = convert(ts, exUnit, timezone);
            if (conv && !conv.error) {
                setResults(conv);
                setErrorMessage('');
                setSummary('Ejemplo cargado.');
                setDateInputValue(dateToDatetimeLocal(conv.date));
                var updated = saveToHistory(ts, exUnit, conv);
                setHistory(updated);
                updateShareUrl(ts, exUnit, timezone);
            }
        }

        function handleCopyLink() {
            var url = updateShareUrl(inputValue, unit, timezone);
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url).then(function () {
                    setCopyButtonText('Enlace copiado');
                    setTimeout(function () { setCopyButtonText(''); }, 1500);
                }).catch(function () {
                    setErrorMessage('No se pudo copiar: ' + url);
                });
            } else {
                setErrorMessage('Copia manual: ' + url);
            }
        }

        function handleHistoryClick(entry) {
            setInputValue(entry.timestamp);
            setAttributes({ timestamp: entry.timestamp, unit: entry.unit });
            var conv = convert(entry.timestamp, entry.unit, timezone);
            if (conv && !conv.error) {
                setResults(conv);
                setErrorMessage('');
                setSummary('Restaurado del historial.');
                setDateInputValue(dateToDatetimeLocal(conv.date));
            }
        }

        function handleClearHistory() {
            clearHistory();
            setHistory([]);
        }

        function updateShareUrl(ts, u, tz) {
            var url = new URL(window.location.href);
            url.searchParams.set('ts', String(ts));
            url.searchParams.set('unit', u);
            url.searchParams.set('tz', tz);
            window.history.replaceState({}, '', url.toString());
            return url.toString();
        }

        function getRelativeBadge(relative) {
            return el('span', { className: 'ts-relative-badge' }, relative);
        }

        /* ====== BUILD UI ====== */

        var blockProps = useBlockProps();

        // Editor placeholder when no conversion done yet
        if (!results) {
            return el('div', Object.assign({}, blockProps, { className: blockProps.className + ' atareao-timestamp-helper-editor' }),
                el('div', { className: 'ts-editor-icon' },
                    el('span', { className: 'dashicons dashicons-clock' })
                ),
                el('h3', {}, __('Timestamp Helper', 'atareao-functionality')),
                el('p', {}, __('Convierte Unix timestamp a fecha legible y viceversa.', 'atareao-functionality')),
                el('div', { className: 'ts-editor-preview' },
                    inputValue ? inputValue + ' (' + unit + ')' : __('Introduce un timestamp en el panel lateral', 'atareao-functionality')
                )
            );
        }

        // Inspector controls (sidebar)
        var inspectorControls = el(InspectorControls, {},
            el(PanelBody, { title: __('Configuracion', 'atareao-functionality'), initialOpen: true },
                el(TextControl, {
                    label: __('Timestamp', 'atareao-functionality'),
                    value: inputValue,
                    className: 'ts-inspector-input',
                    onChange: function (val) {
                        setInputValue(val);
                        setAttributes({ timestamp: val });
                    },
                }),
                el(SelectControl, {
                    label: __('Unidad', 'atareao-functionality'),
                    value: unit,
                    options: UNITS,
                    onChange: function (val) {
                        setAttributes({ unit: val });
                        if (inputValue && isValidNumeric(inputValue)) {
                            var conv = convert(inputValue, val, timezone);
                            if (conv && !conv.error) {
                                setResults(conv);
                            }
                        }
                    },
                }),
                el('div', { className: 'ts-inspector-examples' },
                    QUICK_EXAMPLES.map(function (ex) {
                        return el('button', {
                            key: ex.ts + ex.unit,
                            onClick: function () { handleExample(ex.ts, ex.unit); },
                        }, ex.label);
                    })
                ),
                results && !results.error
                    ? el('div', { className: 'ts-inspector-preview' }, results.iso8601)
                    : null,
            ),
            el(PanelBody, { title: __('Zona horaria', 'atareao-functionality'), initialOpen: false },
                el(SelectControl, {
                    label: __('Zona de salida', 'atareao-functionality'),
                    value: timezone,
                    options: TIMEZONES,
                    onChange: function (val) {
                        setAttributes({ timezone: val });
                        if (inputValue && isValidNumeric(inputValue)) {
                            var conv = convert(inputValue, unit, val);
                            if (conv && !conv.error) {
                                setResults(conv);
                            }
                        }
                    },
                }),
            ),
            el(PanelBody, { title: __('Historial', 'atareao-functionality'), initialOpen: false },
                el(ToggleControl, {
                    label: __('Mostrar historial', 'atareao-functionality'),
                    checked: showHistory,
                    onChange: function (val) {
                        setAttributes({ showHistory: val });
                    },
                }),
            ),
        );

        // Main UI
        var mainContent = el('div', { className: 'atareao-timestamp-helper' },
            // Error message
            errorMessage ? el('div', { className: 'ts-error visible' }, errorMessage) : null,

            // Summary
            summary ? el('div', { className: 'ts-summary' }, summary) : null,

            // Input grid
            el('div', { className: 'ts-grid' },
                el('div', {},
                    el('label', { htmlFor: 'ts_input_' + props.clientId }, __('Unix timestamp', 'atareao-functionality')),
                    el('input', {
                        id: 'ts_input_' + props.clientId,
                        type: 'text',
                        value: inputValue,
                        placeholder: '1714132800',
                        inputMode: 'numeric',
                        autocomplete: 'off',
                        spellcheck: false,
                        className: 'atareao-contact-input',
                        onChange: function (e) {
                            setInputValue(e.target.value);
                            setAttributes({ timestamp: e.target.value });
                        },
                    })
                ),
                el('div', {},
                    el('label', { htmlFor: 'ts_date_' + props.clientId }, __('Fecha y hora (local)', 'atareao-functionality')),
                    el('input', {
                        id: 'ts_date_' + props.clientId,
                        type: 'datetime-local',
                        value: dateInputValue,
                        className: 'atareao-contact-input',
                        onChange: function (e) { setDateInputValue(e.target.value); },
                    })
                ),
            ),

            // Second grid row: unit + timezone
            el('div', { className: 'ts-grid' },
                el('div', {},
                    el('label', { htmlFor: 'ts_unit_' + props.clientId }, __('Unidad', 'atareao-functionality')),
                    el('select', {
                        id: 'ts_unit_' + props.clientId,
                        value: unit,
                        onChange: function (e) {
                            setAttributes({ unit: e.target.value });
                            if (inputValue && isValidNumeric(inputValue)) {
                                var conv = convert(inputValue, e.target.value, timezone);
                                if (conv && !conv.error) {
                                    setResults(conv);
                                }
                            }
                        },
                    },
                        UNITS.map(function (u) {
                            return el('option', { key: u.value, value: u.value }, u.label);
                        })
                    ),
                ),
                el('div', {},
                    el('label', { htmlFor: 'ts_tz_' + props.clientId }, __('Zona horaria de salida', 'atareao-functionality')),
                    el('select', {
                        id: 'ts_tz_' + props.clientId,
                        value: timezone,
                        onChange: function (e) {
                            setAttributes({ timezone: e.target.value });
                            if (inputValue && isValidNumeric(inputValue)) {
                                var conv = convert(inputValue, unit, e.target.value);
                                if (conv && !conv.error) {
                                    setResults(conv);
                                }
                            }
                        },
                    },
                        TIMEZONES.map(function (tz) {
                            return el('option', { key: tz.value, value: tz.value }, tz.label);
                        })
                    ),
                ),
            ),

            // Quick examples
            el('div', {},
                el('label', {}, __('Ejemplos rapidos', 'atareao-functionality')),
                el('div', {},
                    QUICK_EXAMPLES.map(function (ex) {
                        return el('button', {
                            key: ex.ts + ex.unit,
                            type: 'button',
                            className: 'ts-example',
                            onClick: function () { handleExample(ex.ts, ex.unit); },
                        }, ex.label);
                    })
                ),
            ),

            // Action buttons
            el('div', { className: 'ts-actions-bar', style: { marginTop: '0.75rem' } },
                el('button', { type: 'button', className: 'ts-action', onClick: handleConvertFromUnix },
                    __('Desde Unix', 'atareao-functionality')
                ),
                el('button', { type: 'button', className: 'ts-action', onClick: handleConvertFromDate },
                    __('Desde fecha', 'atareao-functionality')
                ),
                el('button', { type: 'button', className: 'ts-action', onClick: handleNow },
                    __('Ahora', 'atareao-functionality')
                ),
                el('button', { type: 'button', className: 'ts-action', onClick: handleCopyLink },
                    copyButtonText || __('Copiar enlace', 'atareao-functionality')
                ),
            ),

            // Results table
            results && !results.error ? el('div', { className: 'ts-results-table-wrap' },
                el('table', { className: 'ts-results-table' },
                    el('thead', {},
                        el('tr', {},
                            el('th', { scope: 'col' }, __('Campo', 'atareao-functionality')),
                            el('th', { scope: 'col' }, __('Valor', 'atareao-functionality')),
                        ),
                    ),
                    el('tbody', {},
                        RESULT_FIELDS.map(function (field) {
                            return el('tr', { key: field.key },
                                el('td', {}, field.label),
                                el('td', {},
                                    el('code', { className: 'ts-results-value' }, results[field.key])
                                ),
                            );
                        }),
                        // Relative time row
                        el('tr', { key: 'relative' },
                            el('td', {}, __('Tiempo relativo', 'atareao-functionality')),
                            el('td', {}, getRelativeBadge(results.relative)),
                        ),
                    ),
                ),
            ) : null,

            // History section
            showHistory && history.length > 0 ? el('div', { className: 'ts-history-section' },
                el('h4', {},
                    __('Historial', 'atareao-functionality'),
                    ' ',
                    el('button', {
                        className: 'ts-history-clear',
                        onClick: handleClearHistory,
                    }, __('Limpiar', 'atareao-functionality')),
                ),
                el('ul', { className: 'ts-history-list' },
                    history.map(function (entry) {
                        return el('li', {
                            key: entry.id,
                            className: 'ts-history-item',
                            onClick: function () { handleHistoryClick(entry); },
                        },
                            el('span', { className: 'ts-history-ts' }, entry.timestamp),
                            el('span', { className: 'ts-history-date' },
                                new Date(entry.iso).toLocaleDateString('es-ES')
                            ),
                        );
                    }),
                ),
            ) : null,
        );

        return el('div', Object.assign({}, blockProps, { key: props.clientId }),
            inspectorControls,
            mainContent
        );
    }

    /* ====================================================================
     * SAVE FUNCTION (dynamic block — renders via PHP)
     * ==================================================================== */

    function save() {
        // Dynamic block: render_callback handles frontend HTML
        return null;
    }

    /* ====================================================================
     * REGISTER BLOCK
     * ==================================================================== */

    registerBlockType('atareao/timestamp-helper', {
        edit: TimestampEditor,
        save: save,
    });
})(window.wp);
```

- [ ] **Step 2: Verify no syntax errors (basic check)**

```bash
node -e "try { new Function(require('fs').readFileSync('assets/blocks/timestamp-helper/index.js', 'utf8')); console.log('SYNTAX OK'); } catch(e) { console.log('SYNTAX ERROR:', e.message); }" --cwd /data/php/atareao.es/wp-content/plugins/atareao-functionality
```

Note: This is an IIFE that expects `window.wp`. The syntax check verifies no JS parse errors — it will NOT execute correctly outside a browser.

- [ ] **Step 3: Commit**

```bash
git add wp-content/plugins/atareao-functionality/assets/blocks/timestamp-helper/index.js
git commit -m "feat(timestamp-block): add Gutenberg editor component (index.js)"
```

---

### Task 7: Create frontend JavaScript (timestamp-frontend.js)

**Files:**
- Create: `assets/blocks/timestamp-helper/timestamp-frontend.js`

**Interfaces:**
- Consumes: `data-timestamp`, `data-unit`, `data-timezone`, `data-show-history` from the container div
- Produces: Hydrated interactive converter in the browser on page load

This file provides the same conversion engine as `index.js` but as a standalone IIFE that reads `data-*` attributes and renders the full UI. It mirrors the existing inline JS from `templates/tools-timestamp.php` but with enhanced features (relative time, history, more formats, more timezones).

- [ ] **Step 1: Write timestamp-frontend.js**

```javascript
/**
 * Timestamp Helper - Frontend
 *
 * Hidrata el bloque Timestamp Helper en el frontend publico.
 * Proporciona conversion bidireccional Unix timestamp <-> fecha legible,
 * seleccion de zona horaria, historial local y URLs compartibles.
 */
(function () {
    'use strict';

    /* ====================================================================
     * CONSTANTS
     * ==================================================================== */

    var TIMEZONES = [
        { label: 'Local del navegador', value: 'local' },
        { label: 'UTC', value: 'UTC' },
        { label: 'Europe/Madrid', value: 'Europe/Madrid' },
        { label: 'America/Bogota', value: 'America/Bogota' },
        { label: 'America/Mexico_City', value: 'America/Mexico_City' },
        { label: 'America/Argentina/Buenos_Aires', value: 'America/Argentina/Buenos_Aires' },
        { label: 'America/Santiago', value: 'America/Santiago' },
        { label: 'America/Lima', value: 'America/Lima' },
        { label: 'America/Sao_Paulo', value: 'America/Sao_Paulo' },
        { label: 'Europe/London', value: 'Europe/London' },
        { label: 'Europe/Berlin', value: 'Europe/Berlin' },
        { label: 'Europe/Paris', value: 'Europe/Paris' },
        { label: 'Asia/Tokyo', value: 'Asia/Tokyo' },
        { label: 'Asia/Shanghai', value: 'Asia/Shanghai' },
        { label: 'Asia/Kolkata', value: 'Asia/Kolkata' },
        { label: 'Australia/Sydney', value: 'Australia/Sydney' },
        { label: 'Pacific/Auckland', value: 'Pacific/Auckland' },
    ];

    var UNITS = [
        { label: 'Segundos (10 digitos)', value: 's' },
        { label: 'Milisegundos (13 digitos)', value: 'ms' },
    ];

    var QUICK_EXAMPLES = [
        { ts: '0', unit: 's', label: 'epoch 0' },
        { ts: '946684800', unit: 's', label: 'Y2K' },
        { ts: '1704067200', unit: 's', label: '2024-01-01' },
        { ts: '1714132800000', unit: 'ms', label: 'ms example' },
    ];

    var RESULT_FIELDS = [
        { key: 'unixSeconds', label: 'Unix (segundos)' },
        { key: 'unixMillis', label: 'Unix (milisegundos)' },
        { key: 'iso8601', label: 'ISO 8601' },
        { key: 'rfc2822', label: 'RFC 2822' },
        { key: 'utc', label: 'UTC' },
        { key: 'local', label: 'Local navegador' },
        { key: 'timezoneFormatted', label: 'Zona seleccionada' },
    ];

    var HISTORY_KEY = 'atareao_timestamp_history';
    var MAX_HISTORY = 20;

    /* ====================================================================
     * UTILITY FUNCTIONS
     * ==================================================================== */

    function getMilliseconds(value, unit) {
        return unit === 'ms' ? value : value * 1000;
    }

    function autoDetectUnit(value) {
        var str = String(value).replace(/^-/, '');
        return str.length >= 12 ? 'ms' : 's';
    }

    function isValidNumeric(value) {
        return /^[-]?\d+$/.test(String(value).trim());
    }

    function pad(n) {
        return String(n).padStart(2, '0');
    }

    function formatForTimezone(date, timezone) {
        try {
            if (timezone === 'local') {
                return date.toLocaleString('es-ES', { hour12: false });
            }
            return new Intl.DateTimeFormat('es-ES', {
                timeZone: timezone,
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: false,
            }).format(date);
        } catch (e) {
            return date.toLocaleString('es-ES', { hour12: false });
        }
    }

    function formatRelativeTime(date) {
        var now = new Date();
        var diffMs = date.getTime() - now.getTime();
        var absMs = Math.abs(diffMs);
        var seconds = Math.floor(absMs / 1000);
        var minutes = Math.floor(seconds / 60);
        var hours = Math.floor(minutes / 60);
        var days = Math.floor(hours / 24);
        var weeks = Math.floor(days / 7);
        var months = Math.floor(days / 30);
        var years = Math.floor(days / 365);

        var prefix = diffMs < 0 ? 'hace ' : 'en ';

        if (seconds < 60) return prefix + 'unos segundos';
        if (minutes < 60) return prefix + minutes + ' minuto' + (minutes !== 1 ? 's' : '');
        if (hours < 24) return prefix + hours + ' hora' + (hours !== 1 ? 's' : '');
        if (days < 7) return prefix + days + ' dia' + (days !== 1 ? 's' : '');
        if (weeks < 5) return prefix + weeks + ' semana' + (weeks !== 1 ? 's' : '');
        if (months < 12) return prefix + months + ' mes' + (months !== 1 ? 'es' : '');
        return prefix + years + ' año' + (years !== 1 ? 's' : '');
    }

    function dateToDatetimeLocal(date) {
        var local = new Date(date.getTime() - date.getTimezoneOffset() * 60000);
        return local.getFullYear() + '-' +
            pad(local.getMonth() + 1) + '-' +
            pad(local.getDate()) + 'T' +
            pad(local.getHours()) + ':' +
            pad(local.getMinutes());
    }

    function loadHistory() {
        try {
            var raw = localStorage.getItem(HISTORY_KEY);
            return raw ? JSON.parse(raw) : [];
        } catch (e) {
            return [];
        }
    }

    function saveToHistory(timestamp, unit, iso, timezone) {
        var history = loadHistory();
        var entry = {
            id: Date.now(),
            timestamp: timestamp,
            unit: unit,
            iso: iso,
            timezone: timezone,
        };
        history = history.filter(function (h) {
            return !(h.timestamp === entry.timestamp && h.unit === entry.unit);
        });
        history.unshift(entry);
        if (history.length > MAX_HISTORY) {
            history = history.slice(0, MAX_HISTORY);
        }
        try {
            localStorage.setItem(HISTORY_KEY, JSON.stringify(history));
        } catch (e) {}
        return history;
    }

    function clearHistory() {
        try {
            localStorage.removeItem(HISTORY_KEY);
        } catch (e) {}
    }

    /* ====================================================================
     * CONVERSION ENGINE
     * ==================================================================== */

    function convert(timestamp, unit, timezone) {
        var raw = String(timestamp).trim();
        if (!isValidNumeric(raw)) {
            return { error: 'El timestamp debe ser numerico.' };
        }

        var parsed = Number(raw);
        if (!isFinite(parsed)) {
            return { error: 'Timestamp invalido.' };
        }

        var millis = getMilliseconds(parsed, unit);
        var date = new Date(millis);

        if (isNaN(date.getTime())) {
            return { error: 'No se pudo convertir el timestamp.' };
        }

        var unixSeconds = Math.floor(millis / 1000);

        return {
            error: null,
            unixSeconds: String(unixSeconds),
            unixMillis: String(millis),
            iso8601: date.toISOString(),
            rfc2822: date.toUTCString(),
            utc: date.toUTCString(),
            local: date.toString(),
            timezoneFormatted: formatForTimezone(date, timezone) + ' (' + timezone + ')',
            timezone: timezone,
            relative: formatRelativeTime(date),
            date: date,
            iso: date.toISOString(),
        };
    }

    /* ====================================================================
     * DOM HELPERS
     * ==================================================================== */

    function createElement(tag, attrs, children) {
        var el = document.createElement(tag);
        if (attrs) {
            for (var key in attrs) {
                if (attrs.hasOwnProperty(key)) {
                    if (key === 'className') {
                        el.className = attrs[key];
                    } else if (key === 'innerHTML') {
                        el.innerHTML = attrs[key];
                    } else if (key.startsWith('on')) {
                        el.addEventListener(key.slice(2).toLowerCase(), attrs[key]);
                    } else if (key === 'style' && typeof attrs[key] === 'object') {
                        for (var s in attrs[key]) {
                            if (attrs[key].hasOwnProperty(s)) {
                                el.style[s] = attrs[key][s];
                            }
                        }
                    } else {
                        el.setAttribute(key, attrs[key]);
                    }
                }
            }
        }
        if (children) {
            for (var i = 0; i < children.length; i++) {
                if (typeof children[i] === 'string') {
                    el.appendChild(document.createTextNode(children[i]));
                } else if (children[i]) {
                    el.appendChild(children[i]);
                }
            }
        }
        return el;
    }

    /* ====================================================================
     * BLOCK HYDRATOR
     * ==================================================================== */

    function hydrateBlock(container) {
        // Read data attributes
        var initialTs = container.getAttribute('data-timestamp') || '1714132800';
        var initialUnit = container.getAttribute('data-unit') || 's';
        var initialTz = container.getAttribute('data-timezone') || 'local';
        var showHistory = container.getAttribute('data-show-history') !== '0';

        // Current state
        var currentTs = initialTs;
        var currentUnit = initialUnit;
        var currentTz = initialTz;
        var currentResults = null;

        // Refs
        var tsInput, tsUnit, tzSelect, dateInput, resultsBody, summaryEl, errorEl, historyContainer;

        /* ====== Build UI ====== */

        function buildUI() {
            container.innerHTML = '';

            // Error box
            errorEl = createElement('div', { className: 'ts-error' });
            container.appendChild(errorEl);

            // Summary
            summaryEl = createElement('div', { className: 'ts-summary' });
            container.appendChild(summaryEl);

            // Grid row 1: timestamp input + date input
            var grid1 = createElement('div', { className: 'ts-grid' });
            var tsField = createElement('div', {});
            tsField.appendChild(createElement('label', { for: 'ts_input' }, ['Unix timestamp']));
            tsInput = createElement('input', {
                id: 'ts_input',
                type: 'text',
                value: currentTs,
                placeholder: '1714132800',
                inputmode: 'numeric',
                autocomplete: 'off',
                spellcheck: 'false',
            });
            tsField.appendChild(tsInput);
            grid1.appendChild(tsField);

            var dateField = createElement('div', {});
            dateField.appendChild(createElement('label', { for: 'ts_date_input' }, ['Fecha y hora (local)']));
            dateInput = createElement('input', {
                id: 'ts_date_input',
                type: 'datetime-local',
            });
            dateField.appendChild(dateInput);
            grid1.appendChild(dateField);
            container.appendChild(grid1);

            // Grid row 2: unit + timezone
            var grid2 = createElement('div', { className: 'ts-grid' });
            var unitField = createElement('div', {});
            unitField.appendChild(createElement('label', { for: 'ts_unit' }, ['Unidad']));
            tsUnit = createElement('select', { id: 'ts_unit' });
            UNITS.forEach(function (u) {
                var opt = createElement('option', { value: u.value }, [u.label]);
                if (u.value === currentUnit) opt.selected = true;
                tsUnit.appendChild(opt);
            });
            unitField.appendChild(tsUnit);
            grid2.appendChild(unitField);

            var tzField = createElement('div', {});
            tzField.appendChild(createElement('label', { for: 'ts_timezone' }, ['Zona horaria de salida']));
            tzSelect = createElement('select', { id: 'ts_timezone' });
            TIMEZONES.forEach(function (tz) {
                var opt = createElement('option', { value: tz.value }, [tz.label]);
                if (tz.value === currentTz) opt.selected = true;
                tzSelect.appendChild(opt);
            });
            tzField.appendChild(tzSelect);
            grid2.appendChild(tzField);
            container.appendChild(grid2);

            // Quick examples
            var exampleLabel = createElement('label', {}, ['Ejemplos rapidos']);
            container.appendChild(exampleLabel);
            var exampleRow = createElement('div', {});
            QUICK_EXAMPLES.forEach(function (ex) {
                exampleRow.appendChild(createElement('button', {
                    type: 'button',
                    className: 'ts-example',
                    onClick: function () {
                        currentTs = ex.ts;
                        currentUnit = ex.unit;
                        tsInput.value = ex.ts;
                        tsUnit.value = ex.unit;
                        doConvert();
                    },
                }, [ex.label]));
            });
            container.appendChild(exampleRow);

            // Action buttons
            var actionsBar = createElement('div', { className: 'ts-actions-bar' });
            actionsBar.appendChild(createElement('button', {
                type: 'button',
                className: 'ts-action',
                onClick: doConvertFromUnix,
            }, ['Desde Unix']));
            actionsBar.appendChild(createElement('button', {
                type: 'button',
                className: 'ts-action',
                onClick: doConvertFromDate,
            }, ['Desde fecha']));
            actionsBar.appendChild(createElement('button', {
                type: 'button',
                className: 'ts-action',
                onClick: doNow,
            }, ['Ahora']));
            actionsBar.appendChild(createElement('button', {
                type: 'button',
                className: 'ts-action',
                id: 'ts_copy_link',
            }, ['Copiar enlace']));
            container.appendChild(actionsBar);

            // Results table
            resultsBody = createElement('tbody', { id: 'ts_results_body' });
            var table = createElement('table', { className: 'ts-results-table' },
                [
                    createElement('thead', {},
                        [createElement('tr', {},
                            [
                                createElement('th', { scope: 'col' }, ['Campo']),
                                createElement('th', { scope: 'col' }, ['Valor']),
                            ]
                        )]
                    ),
                    resultsBody,
                ]
            );
            var tableWrap = createElement('div', { className: 'ts-results-table-wrap' }, [table]);
            container.appendChild(tableWrap);

            // History section
            historyContainer = createElement('div', { className: 'ts-history-section', id: 'ts_history_section' });
            container.appendChild(historyContainer);

            // Events
            tsInput.addEventListener('input', function () {
                currentTs = tsInput.value;
                var detected = autoDetectUnit(currentTs);
                if (detected !== currentUnit && isValidNumeric(currentTs)) {
                    currentUnit = detected;
                    tsUnit.value = detected;
                }
                debouncedConvert();
            });

            tsUnit.addEventListener('change', function () {
                currentUnit = tsUnit.value;
                if (isValidNumeric(currentTs)) doConvert();
            });

            tzSelect.addEventListener('change', function () {
                currentTz = tzSelect.value;
                if (isValidNumeric(currentTs)) doConvert();
            });

            dateInput.addEventListener('change', function () {
                // No auto-convert, user must click "Desde fecha"
            });

            document.getElementById('ts_copy_link').addEventListener('click', doCopyLink);

            // Load from URL params
            fillFromUrl();

            // Initial conversion
            doConvert();
        }

        /* ====== Debounce ====== */

        var debounceTimer = null;

        function debouncedConvert() {
            if (debounceTimer) clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function () {
                if (isValidNumeric(currentTs)) {
                    doConvert();
                }
            }, 300);
        }

        /* ====== Conversion actions ====== */

        function showError(msg) {
            errorEl.textContent = msg;
            errorEl.classList.add('visible');
        }

        function clearError() {
            errorEl.textContent = '';
            errorEl.classList.remove('visible');
        }

        function renderResults(conv) {
            resultsBody.innerHTML = '';
            RESULT_FIELDS.forEach(function (field) {
                var value = conv[field.key] || '';
                var tr = createElement('tr', {},
                    [
                        createElement('td', {}, [field.label]),
                        createElement('td', {},
                            [createElement('code', { className: 'ts-results-value' }, [value])]
                        ),
                    ]
                );
                resultsBody.appendChild(tr);
            });
            // Relative time row
            var relTr = createElement('tr', {},
                [
                    createElement('td', {}, ['Tiempo relativo']),
                    createElement('td', {},
                        [createElement('span', { className: 'ts-relative-badge' }, [conv.relative])]
                    ),
                ]
            );
            resultsBody.appendChild(relTr);
        }

        function renderHistory() {
            if (!showHistory) {
                historyContainer.innerHTML = '';
                return;
            }
            var history = loadHistory();
            if (history.length === 0) {
                historyContainer.innerHTML = '';
                return;
            }
            historyContainer.innerHTML = '';
            var titleRow = createElement('div', { style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center' } },
                [
                    createElement('h4', { style: { margin: '0 0 0.5rem' } }, ['Historial']),
                    createElement('button', { className: 'ts-history-clear', onClick: doClearHistory }, ['Limpiar']),
                ]
            );
            historyContainer.appendChild(titleRow);

            var list = createElement('ul', { className: 'ts-history-list' });
            history.forEach(function (entry) {
                var dateStr = new Date(entry.iso).toLocaleDateString('es-ES');
                var li = createElement('li', {
                    className: 'ts-history-item',
                    onClick: function () {
                        currentTs = entry.timestamp;
                        currentUnit = entry.unit;
                        tsInput.value = entry.timestamp;
                        tsUnit.value = entry.unit;
                        doConvert();
                    },
                },
                    [
                        createElement('span', { className: 'ts-history-ts' }, [entry.timestamp]),
                        createElement('span', { className: 'ts-history-date' }, [dateStr]),
                    ]
                );
                list.appendChild(li);
            });
            historyContainer.appendChild(list);
        }

        function doConvert() {
            clearError();
            if (!currentTs || !isValidNumeric(currentTs)) {
                showError('Introduce un timestamp numerico.');
                return;
            }
            var detected = autoDetectUnit(currentTs);
            if (detected !== currentUnit) {
                currentUnit = detected;
                tsUnit.value = detected;
            }
            var conv = convert(currentTs, currentUnit, currentTz);
            if (conv.error) {
                showError(conv.error);
                return;
            }
            currentResults = conv;
            summaryEl.textContent = 'Timestamp convertido correctamente.';
            dateInput.value = dateToDatetimeLocal(conv.date);
            renderResults(conv);
            updateShareUrl(currentTs, currentUnit, currentTz);
            saveToHistory(currentTs, currentUnit, conv.iso, currentTz);
            renderHistory();
        }

        function doConvertFromUnix() {
            doConvert();
        }

        function doConvertFromDate() {
            clearError();
            var val = dateInput.value;
            if (!val) {
                showError('Selecciona una fecha y hora para convertir.');
                return;
            }
            var date = new Date(val);
            if (isNaN(date.getTime())) {
                showError('Fecha invalida.');
                return;
            }
            var millis = date.getTime();
            currentTs = currentUnit === 'ms' ? String(millis) : String(Math.floor(millis / 1000));
            tsInput.value = currentTs;
            summaryEl.textContent = 'Fecha convertida a Unix correctamente.';
            doConvert();
        }

        function doNow() {
            var now = new Date();
            var nowMs = now.getTime();
            currentTs = currentUnit === 'ms' ? String(nowMs) : String(Math.floor(nowMs / 1000));
            tsInput.value = currentTs;
            dateInput.value = dateToDatetimeLocal(now);
            summaryEl.textContent = 'Timestamp actual.';
            doConvert();
        }

        function doCopyLink() {
            var url = updateShareUrl(currentTs, currentUnit, currentTz);
            var btn = document.getElementById('ts_copy_link');
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url).then(function () {
                    btn.textContent = 'Enlace copiado';
                    setTimeout(function () { btn.textContent = 'Copiar enlace'; }, 1500);
                }).catch(function () {
                    showError('No se pudo copiar. Enlace: ' + url);
                });
            } else {
                showError('Copia manual: ' + url);
            }
        }

        function doClearHistory() {
            clearHistory();
            renderHistory();
        }

        function updateShareUrl(ts, unit, tz) {
            var url = new URL(window.location.href);
            url.searchParams.set('ts', String(ts));
            url.searchParams.set('unit', unit);
            url.searchParams.set('tz', tz);
            window.history.replaceState({}, '', url.toString());
            return url.toString();
        }

        function fillFromUrl() {
            var url = new URL(window.location.href);
            var ts = url.searchParams.get('ts');
            var unit = url.searchParams.get('unit');
            var tz = url.searchParams.get('tz');

            if (ts && ts !== '') {
                currentTs = ts;
                tsInput.value = ts;
            }
            if (unit === 's' || unit === 'ms') {
                currentUnit = unit;
                tsUnit.value = unit;
            }
            if (tz) {
                var valid = false;
                for (var i = 0; i < TIMEZONES.length; i++) {
                    if (TIMEZONES[i].value === tz) {
                        valid = true;
                        break;
                    }
                }
                if (valid) {
                    currentTz = tz;
                    tzSelect.value = tz;
                }
            }
        }

        /* ====== Initialize ====== */
        buildUI();
    }

    /* ====================================================================
     * HYDRATE ALL BLOCKS ON PAGE
     * ==================================================================== */

    function hydrateAll() {
        var containers = document.querySelectorAll('.atareao-timestamp-helper[data-timestamp]');
        for (var i = 0; i < containers.length; i++) {
            hydrateBlock(containers[i]);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', hydrateAll);
    } else {
        hydrateAll();
    }
})();
```

- [ ] **Step 2: Verify syntax**

```bash
node -e "try { new Function(require('fs').readFileSync('assets/blocks/timestamp-helper/timestamp-frontend.js', 'utf8')); console.log('SYNTAX OK'); } catch(e) { console.log('SYNTAX ERROR:', e.message); }" --cwd /data/php/atareao.es/wp-content/plugins/atareao-functionality
```

- [ ] **Step 3: Commit**

```bash
git add wp-content/plugins/atareao-functionality/assets/blocks/timestamp-helper/timestamp-frontend.js
git commit -m "feat(timestamp-block): add frontend hydration script"
```

---

### Task 8: Refactor tools-timestamp.php to use the block

**Files:**
- Modify: `templates/tools-timestamp.php` (replace inline JS+CSS with `do_blocks()` and static block comment)

**Interfaces:**
- Consumes: `renderTimestampHelper()` from Task 4 — the block output at render time
- Consumes: `timestamp-frontend.js` from Task 7 — hydration of the block on page load

The approach: Replace the entire `<form>...</form>` section, inline `<style>`, and inline `<script>` with a single `do_blocks()` call that contains a hardcoded `<!-- wp:atareao/timestamp-helper -->` comment. This keeps the SEO/OG/schema/breadcrumb/header/footer wrapper intact.

- [ ] **Step 1: Replace the inline converter form with do_blocks()**

The SEO metadata, schema markup, breadcrumb, header, footer, and SEO content sections stay. Only the form + inline CSS + inline JS get replaced.

Edit `templates/tools-timestamp.php`:

1. Lines 108-220 (from `<main>` opening to the closing `</section>` before the `<style>` tag) — replace the form entirely
2. Lines 224-383 (the inline `<style>` block) — delete entirely
3. Lines 385-603 (the inline `<script>` block) — delete entirely

The replacement content:

```php
<main id="primary" class="site-main">
    <article id="post-tools-timestamp" class="post type-page status-publish hentry">
        <header class="entry-header">
            <h1 class="entry-title">Timestamp Converter</h1>
            <?php atareao_tools_render_breadcrumb('timestamp'); ?>
        </header>

        <div class="entry-content atareao-contact-wrapper">
            <div class="atareao-page-entry-content">
                <p>
                    Convierte Unix timestamp a fecha legible y transforma fecha/hora a epoch para depurar logs, APIs y eventos.
                </p>
            </div>

            <?php
            // Render the Timestamp Helper block
            echo do_blocks(
                '<!-- wp:atareao/timestamp-helper {"timestamp":"1714132800","unit":"s","timezone":"local","showHistory":true} /-->'
            );
            ?>

            <section class="atareao-tool-seo-content" aria-label="Guia rapida de timestamp">
                <h2>Guia rapida de uso</h2>
                <h3>1. Selecciona formato de entrada</h3>
                <p>Indica si tu epoch esta en segundos o milisegundos para evitar conversiones desplazadas.</p>

                <h3>2. Ajusta zona horaria de salida</h3>
                <p>Compara UTC con zona local para correlacionar eventos entre aplicaciones, servidores y monitorizacion.</p>

                <h3>3. Convierte en ambos sentidos</h3>
                <p>Pasa de Unix a fecha legible y de fecha a epoch para depurar APIs, logs y expiraciones.</p>
            </section>

            <section class="atareao-tool-seo-content" aria-label="Preguntas frecuentes de timestamp">
                <h2>Preguntas frecuentes</h2>
                <h3>Segundos o milisegundos</h3>
                <p>Como referencia rapida, 10 digitos suele indicar segundos y 13 digitos suele indicar milisegundos.</p>

                <h3>Usos habituales</h3>
                <p>Se usa para interpretar logs de backend, revisar expiraciones de JWT y validar eventos temporales en bases de datos y colas.</p>

                <h3>UTC frente a hora local</h3>
                <p>En sistemas distribuidos conviene trabajar en UTC y convertir a local solo para visualizacion y soporte.</p>

                <h3>Compartir conversiones</h3>
                <p>Con Copiar enlace puedes enviar el mismo caso a otro miembro del equipo para revisar resultados de forma consistente.</p>
            </section>
        </div>
    </article>
</main>
```

After replacement, delete lines 224-603 (the old `<style>` and `<script>` blocks). The file will end with `<?php get_footer();`.

- [ ] **Step 2: Verify syntax**

```bash
php -l templates/tools-timestamp.php
```

Expected: `No syntax errors detected in templates/tools-timestamp.php`

- [ ] **Step 3: Verify the block renders correctly (manual test)**

```bash
just wp -- eval 'echo do_blocks("<!-- wp:atareao/timestamp-helper /-->");'
```

Expected: The HTML container div with class `atareao-timestamp-helper` and `data-*` attributes.

- [ ] **Step 4: Commit**

```bash
git add wp-content/plugins/atareao-functionality/templates/tools-timestamp.php
git commit -m "refactor(timestamp-block): replace inline JS/CSS with do_blocks() in tools-timestamp.php"
```

---

### Task 9: Create directory structure and verify everything

**Files:**
- Verify: `assets/blocks/timestamp-helper/` directory exists with all 5 files
- Verify: `includes/class-timestamp-block.php` exists
- Verify: Plugin loads without fatal errors

- [ ] **Step 1: Verify directory structure**

```bash
ls -la wp-content/plugins/atareao-functionality/assets/blocks/timestamp-helper/
```

Expected:
```
block.json
editor.css
index.js
style.css
timestamp-frontend.js
```

- [ ] **Step 2: PHP syntax check all files**

```bash
find wp-content/plugins/atareao-functionality -name "*.php" -exec php -l {} \; | grep -v "No syntax errors"
```

Expected: No output (all files pass).

- [ ] **Step 3: Verify plugin loads in WordPress context**

```bash
just wp -- plugin list --status=active --field=name | grep atareao-functionality
```

Should return `atareao-functionality` (the plugin is active).

- [ ] **Step 4: Verify block is registered**

```bash
just wp -- block list --format=csv | grep timestamp-helper
```

Expected: `atareao/timestamp-helper` appears in the output.

- [ ] **Step 5: Verify frontend renders**

```bash
just wp -- eval '
$content = do_blocks("<!-- wp:atareao/timestamp-helper /-->");
if (strpos($content, "atareao-timestamp-helper") !== false) {
    echo "SUCCESS: Block renders with container class\n";
} else {
    echo "FAIL: Block did not render correctly\n";
}
'
```

Expected: `SUCCESS: Block renders with container class`

- [ ] **Step 6: Commit any remaining changes**

```bash
git status
```

Verify no dirty files remain. If clean, no commit needed.

---

## Verification Checklist

After all tasks are complete, run:

```bash
# Full PHP lint
just php-lint

# PSR12 check
just phpcs

# Verify block renders
just wp -- eval 'echo do_blocks("<!-- wp:atareao/timestamp-helper /-->");'

# Verify frontend JS loads
just wp -- eval '
wp_enqueue_script("atareao-timestamp-frontend");
do_action("wp_enqueue_scripts");
$handles = $GLOBALS["wp_scripts"]->queue;
echo in_array("atareao-timestamp-frontend", $handles) ? "SCRIPT ENQUEUED" : "SCRIPT MISSING";
'
```

## Rollback Plan

If any task causes a fatal error:

1. **PHP fatal error**: Immediately check `just logs service=atareao-wordpress` for the error trace. Fix the syntax or revert the file.
2. **Block not registering**: Verify `block.json` is valid JSON. Verify `register_block_type()` path exists. Verify class is loaded before `init` action fires.
3. **Frontend JS not hydrating**: Open browser console. Look for JS errors. Verify the `data-*` attributes match between PHP render callback and JS expectations.
4. **Tools page breaks**: Revert `templates/tools-timestamp.php` to restore inline JS. The old form still works independently.

To revert individual task files:

```bash
git checkout -- wp-content/plugins/atareao-functionality/includes/class-timestamp-block.php
git checkout -- wp-content/plugins/atareao-functionality/atareao-functionality.php
git checkout -- wp-content/plugins/atareao-functionality/templates/tools-timestamp.php
git clean -fd wp-content/plugins/atareao-functionality/assets/blocks/timestamp-helper/
```