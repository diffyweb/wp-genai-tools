# GenAI Tools for WordPress by Diffyweb

A toolkit for integrating various Generative AI services (like Google Gemini and OpenAI DALL-E) into the WordPress content workflow.

## Description

GenAI Tools provides a simple, integrated way to generate featured images for your blog posts directly from the WordPress editor. Using the post's title, content, and tags, the plugin creates a detailed prompt and sends it to your chosen AI provider to generate a relevant, high-quality image.

This plugin is designed to be lightweight, extensible, and developer-friendly.

## Features

*   **AI-Powered Featured Images**: Automatically generate a featured image based on your post's content.
*   **Multiple AI Providers**: Supports both Google Gemini and OpenAI (DALL-E 3).
*   **Seamless Integration**: A simple "Generate Image" button is added to a meta box in the post editor.
*   **Self-Hosted Updates**: Get update notifications directly from the official GitHub repository.
*   **Multisite Compatible**: Configure once on your network and use it across all your sites.
*   **Accessibility-Ready**: Automatically sets the image's alt text from the post title.

## Installation

1.  Download the latest `wp-genai-tools.zip` file from the GitHub Releases page.
2.  In your WordPress admin dashboard, go to **Plugins > Add New > Upload Plugin**.
3.  Upload the `.zip` file and activate the plugin.
4.  **For Multisite**: Network Activate the plugin from the Network Admin > Plugins screen.

## Configuration

1.  After activation, go to **Settings > GenAI Tools** (or **Network Admin > Settings > GenAI Tools** on multisite).
2.  Select the tab for the AI provider you want to use (Gemini or OpenAI).
3.  Enter your API key for the selected service. You can find instructions for obtaining keys on the settings page.
4.  Click "Save Settings".

## Usage

1.  Create or edit a post.
2.  In the editor sidebar, find the **GenAI Tools** meta box.
3.  Select your desired AI provider from the dropdown menu.
4.  Click the **Generate Image** button.
5.  The plugin will generate an image, upload it to your Media Library, and set it as the post's featured image.

## Contributing

Contributions are welcome! If you find a bug or have a feature request, please open an issue on the GitHub repository.