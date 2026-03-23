# wp-genai-tools

WordPress plugin that generates AI-powered featured images for posts using Google Gemini or OpenAI DALL-E 3.

## What this is

GenAI Tools is a WordPress admin plugin by Diffyweb. It adds a meta box to the post/page editor with a "Generate Image" button. When clicked, it builds a prompt from the post's title, tags, and content, sends it to the selected AI provider, and uploads the returned image to the Media Library as the post's featured image. It also auto-sets alt text for accessibility.

The plugin supports WordPress multisite (network activation required) and includes a self-hosted updater that pulls new versions from the GitHub repo's `info.json` manifest.

## Tech stack

- **Language:** PHP 7.4+
- **Platform:** WordPress 6.0+ (admin-only; no frontend code)
- **JavaScript:** Inline jQuery in the meta box for AJAX calls
- **APIs:** Google Generative Language API (Gemini), OpenAI Images API (DALL-E 3)
- **CI:** GitHub Actions (`release.yml`) -- creates a zip on version tags
- **No build step, no Composer, no npm.** Pure PHP plugin with no external dependencies.

## Directory structure

```
wp-genai-tools/
├── wp-genai-tools.php              # Main plugin entry point (bootstrap + requires)
├── info.json                       # Self-hosted update manifest (version, download URL, changelog)
├── README.md                       # User-facing documentation
├── README.ai.md                    # Conversational development changelog / AI context log
├── .distignore                     # Files excluded from release zip
├── .github/
│   └── workflows/
│       └── release.yml             # GitHub Actions: build zip + create GitHub Release on tag push
└── includes/
    ├── class-diffyweb-genai-tools.php          # Core plugin class (singleton, settings UI, AJAX handler, meta box)
    ├── class-diffyweb-genai-tools-updater.php  # Self-hosted updater (checks info.json for new versions)
    ├── class-diffyweb-genai-image-helper.php   # Static helper: upload base64 image to Media Library, set as featured
    └── providers/
        ├── interface-diffyweb-genai-provider.php          # Provider interface (single method: generate)
        ├── class-diffyweb-genai-gemini-provider.php       # Gemini provider implementation
        └── class-diffyweb-genai-openai-provider.php       # OpenAI/DALL-E 3 provider implementation
```

## How it works

1. **Bootstrap** (`wp-genai-tools.php`): Defines constants, requires all class files, hooks `diffyweb_run_wp_genai_tools()` on `init`.
2. **Singleton** (`Diffyweb_GenAI_Tools::instance()`): Only runs in `is_admin()` context. Registers settings, meta box, AJAX handler, and updater.
3. **Settings page**: Tabbed UI under Settings > GenAI Tools (or Network Admin on multisite). Each provider tab stores an API key, a customizable prompt template, and (for OpenAI) an image size.
4. **Prompt templates**: Use `{{post_title}}`, `{{keywords_string}}`, and `{{post_content_summary}}` placeholders. Content summary is truncated to 1000 chars.
5. **Image generation flow**:
   - User clicks "Generate Image" in the editor meta box.
   - jQuery AJAX POST fires `wp_ajax_diffyweb_genai_generate_image`.
   - `handle_ajax_image_generation()` validates nonce + permissions, instantiates the selected provider, calls `$provider->generate($post_id)`.
   - The provider builds the prompt, calls the external API, extracts base64 image data from the response.
   - `Diffyweb_GenAI_Image_Helper::upload_and_set_featured_image()` decodes the image, uploads via `wp_upload_bits()`, creates an attachment, generates metadata, sets it as featured image, and writes alt text.
   - AJAX response triggers `wp.media.featuredImage.set()` to refresh the thumbnail in the editor without a page reload.
6. **Self-hosted updater**: On `admin_init`, the updater hooks into `pre_set_site_transient_update_plugins` and `plugins_api`. It fetches `info.json` from the repo's `main` branch on GitHub and compares versions.

## Key files

| File | Purpose |
|------|---------|
| `wp-genai-tools.php` | Plugin header, constants, file requires, init hook |
| `includes/class-diffyweb-genai-tools.php` | Core class: settings registration, settings page HTML, meta box rendering (including inline JS), AJAX dispatcher, multisite logic |
| `includes/class-diffyweb-genai-image-helper.php` | `upload_and_set_featured_image()` -- shared by all providers |
| `includes/providers/interface-diffyweb-genai-provider.php` | `Diffyweb_GenAI_Provider_Interface` with single `generate($post_id)` method |
| `includes/providers/class-diffyweb-genai-gemini-provider.php` | Gemini API call (model: `gemini-2.0-flash-preview-image-generation`, 60s timeout) |
| `includes/providers/class-diffyweb-genai-openai-provider.php` | OpenAI API call (model: `dall-e-3`, 90s timeout, configurable size) |
| `includes/class-diffyweb-genai-tools-updater.php` | Self-hosted updater checking `info.json` on GitHub |
| `info.json` | Update manifest: version, download URL, requirements, changelog HTML |
| `.github/workflows/release.yml` | Triggered on `v*` tag push; builds zip (excluding `.distignore` entries), creates GitHub Release |

## Provider architecture

All providers implement `Diffyweb_GenAI_Provider_Interface` which requires a single method:

```php
public function generate( $post_id ): array;
```

Return value is always `['status' => 'success'|'error', ...]` with either `attachment_id` (on success) or `message` (on error).

To add a new provider:
1. Create `includes/providers/class-diffyweb-genai-{name}-provider.php`.
2. Implement `Diffyweb_GenAI_Provider_Interface`.
3. `require_once` it in `wp-genai-tools.php`.
4. Add settings fields in `register_settings()` and a new tab in `render_settings_page()`.
5. Add an `elseif` branch in `handle_ajax_image_generation()` and a `<option>` in the meta box dropdown.

## Key conventions

- **Class naming:** `Diffyweb_GenAI_*` prefix, one class per file, filename mirrors class name in lowercase with hyphens.
- **Option naming:** `diffyweb_genai_tools_{provider}_{setting}` (e.g., `diffyweb_genai_tools_gemini_api_key`).
- **Multisite:** Uses `get_site_option()` / `get_option()` branching throughout. Settings page registers under `network_admin_menu` on multisite.
- **Security:** Every AJAX handler checks nonce (`diffyweb_genai_generate_image_nonce`) and `current_user_can('edit_post')`. All inputs are sanitized.
- **Direct access guard:** Every PHP file starts with `if ( ! defined( 'WPINC' ) ) { die; }`.
- **Text domain:** `diffyweb-genai-tools` (used in all translatable strings).
- **Version tracking:** Version string appears in three places that must stay in sync: the plugin header in `wp-genai-tools.php`, the `DIFFYWEB_GENAI_TOOLS_VERSION` constant, and `info.json`.
- **No frontend code.** Everything is gated behind `is_admin()`.

## Build / test

There is no build step. The plugin is plain PHP with no transpilation or bundling.

**Local development:**
1. Symlink or copy the repo into `wp-content/plugins/wp-genai-tools/`.
2. Activate the plugin in WP Admin (or Network Activate on multisite).
3. Configure an API key under Settings > GenAI Tools.
4. Test by editing a post and clicking "Generate Image".

**Creating a release:**
1. Update the version in all three locations: `wp-genai-tools.php` header + constant, and `info.json`.
2. Commit, then push a tag: `git tag v{X.Y.Z} && git push origin v{X.Y.Z}`.
3. GitHub Actions builds the zip and creates a GitHub Release automatically.

**No automated tests exist.** Testing is manual: verify the AJAX round-trip, check that the featured image is set, confirm the updater detects new versions.
