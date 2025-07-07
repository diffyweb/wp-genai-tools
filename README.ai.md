# Project: GenAI Tools for WordPress - Conversational Changelog &amp; Context
# Version: 2.7.2
# Last Updated: 2024-07-08
# Purpose: This document serves as a comprehensive development history for the GenAI Tools WordPress plugin. It is intended for use by AI development assistants (e.g., Gemini for VS Code, GitHub Copilot) to provide full context on the project&#39;s evolution, technical decisions, and debugging history.
# INSTRUCTIONS FOR AI: Periodically update this file to reflect new development iterations, user feedback, and bug fixes to maintain a continuous context log.

## Phase 1: Initial Concept & n8n Workflow

- **Objective:** Generate an image using a Google Gemini model from within an n8n workflow.
- **Problem:** Received `400 Bad Request` error due to incorrect prompt structure for the image model.
- **Decision Point:** Pivoted from an n8n workflow to a dedicated WordPress plugin for better control and user experience.

-----

## Phase 2: WordPress Plugin - Initial Development & Debugging

- **Objective:** Create a WordPress plugin to generate a featured image.
- **Initial Approach:** Hooked into `save_post`. This proved unreliable.
- **Solution:** Switched to a manual trigger via an AJAX-powered "Generate Image" button in a post meta box.
- **Key Debugging:** Resolved a `400 Bad Request` by switching to the correct image generation model (`gemini-2.0-flash-preview-image-generation`) and refining the prompt to be more explicit.

-----

## Phase 3: Refactoring into an Extensible "AI Kit"

- **Objective:** Future-proof the plugin for multiple AI providers and establish a formal project structure.
- **Actions:**
  - Renamed to "GenAI Tools" and created the `diffyweb/wp-genai-tools` GitHub repository.
  - Rebuilt the settings page with a tabbed interface for different providers.
  - Added a provider selection dropdown to the meta box.
  - Implemented a self-hosted updater class to pull updates from the GitHub repository.

-----

## Phase 4: Final UI/UX Polish

- **Objective:** Improve the user experience after image generation.
- **Problem:** The featured image thumbnail in the editor did not update automatically.
- **Solution:** The AJAX success callback was updated to use `wp.media.featuredImage.set(attachmentId)` to instantly refresh the thumbnail without a page reload.

-----

## Phase 5: Image Optimization (Feature Added and Removed)

- **Objective:** Reduce the file size of generated images before they are saved to the Media Library.
- **Iteration 1 (pngquant):** Added `pngquant` utility support via command-line `exec()`.
- **Iteration 2 (Imagick):** Added support for the `imagick` PHP extension as an alternative optimizer with configurable settings.
- **Iteration 3 (Feedback & Removal):** Added UI feedback to show optimization results.
- **Decision Point:** The user decided that image optimization was outside the core scope of the plugin.
- **Solution:** All image optimization code, settings, and UI elements were completely removed to simplify the plugin.

-----

## Phase 6: Architectural Improvements & DALL-E 3 Integration

- **Objective:** Implement the OpenAI provider and improve the code architecture for better maintainability.
- **Refactoring:**
  - **Problem:** All provider logic was mixed in the main plugin class.
  - **Solution:** The code was refactored to be more modular. A new `includes/providers` directory was created. An `Image_Helper` class was created for shared upload logic. `Diffyweb_GenAI_Gemini_Provider` and `Diffyweb_GenAI_OpenAI_Provider` classes were created to encapsulate all API-specific logic, implementing a common `Diffyweb_GenAI_Provider_Interface`.
- **DALL-E 3 Implementation:** The "OpenAI (DALL-E)" option was enabled, and the `Diffyweb_GenAI_OpenAI_Provider` class was implemented to handle API requests to the `dall-e-3` model.
- **Accessibility:** Added functionality to automatically set the `alt` text for the generated featured image using the format: `<Post Title> featured image.`.

-----

## Phase 7: Deployment and Debugging

- **Objective:** Stabilize the plugin, particularly the self-hosted updater, and add multisite support.
- **Debugging Iterations:**
  - **Problem:** Fatal error `Cannot use object of type stdClass as array` during update checks.
  - **Solution:** The updater was modified to force `json_decode` to return an associative array.
  - **Problem:** `_load_textdomain_just_in_time` notice, indicating code running too early.
  - **Solution:** Plugin initialization was moved to the `init` hook, and then further refined by moving updater logic to `admin_init`.
  - **Problem:** "Plugin not found" error when viewing changelog or running updates.
  - **Solution:** The updater was fixed to correctly generate the plugin slug from its directory name and to implement the `plugins_api` filter.
  - **Problem:** "Download failed. Not Found" during updates.
  - **Diagnosis:** The GitHub Actions release workflow was misconfigured.
  - **Solution:** The `release.yml` workflow was corrected.
  - **Problem:** Fatal error on plugin activation.
  - **Diagnosis:** Multisite checks were calling admin-only functions too early.
  - **Solution:** The updater and multisite checks were moved to the `admin_init` hook.
- **Multisite Support:**
  - Added logic to detect multisite environments, add the settings page to the Network Admin menu, and use network-wide options.
  - An admin notice now prompts users to network-activate the plugin if required.