# LMS4WP AI Extension

AI integration extension for LMS4WP plugin. Supports multiple AI providers including Groq, RunPod, and OpenRouter.

## Features

- 🤖 Multiple AI Provider Support (Groq, RunPod, OpenRouter)
- 🎨 Modern Admin Interface
- 🔒 Secure API Key Management via .env
- ⚡ Fast and Lightweight
- 🔌 Easy to Extend

## Requirements

- WordPress 6.0+
- PHP 8.0+
- LMS4WP plugin (main plugin) must be installed and activated

## Installation

1. Clone this repository to `wp-content/plugins/lms4wp-ai/`
2. Copy `.env.example` to `.env`
3. Add your API keys to `.env` file
4. Run `composer install` to install dependencies
5. Activate the plugin in WordPress admin

## Configuration

Edit `.env` file with your API keys:

```env
# Groq (Default)
GROQ_API_KEY="your_groq_api_key_here"
GROQ_MODEL="llama-3.1-8b-instant"

# RunPod (Optional)
RUNPOD_API_KEY="your_runpod_api_key_here"
RUNPOD_ENDPOINT_ID="your_endpoint_id_here"

# OpenRouter (Optional)
OPENROUTER_API_KEY="your_openrouter_api_key_here"
```

## Usage

1. Go to **LMS4WP > AI Assistant** in WordPress admin
2. Select your preferred AI provider
3. Type your message and click "Send to AI"
4. View the AI response

## Supported Providers

### Groq (Default)
- Fast inference with Llama models
- Free tier available
- Models: llama-3.1-8b-instant, llama-3.1-70b-versatile, etc.

### RunPod
- Self-hosted GPU inference
- Custom endpoints
- Models: Llama, Mistral, etc.

### OpenRouter
- Unified API for multiple models
- Access to OpenAI, Anthropic, and more
- Models: GPT-4, Claude, Llama, etc.

## Development

```bash
# Install dependencies
composer install

# Development structure
includes/
├── AI/
│   ├── ProviderInterface.php
│   ├── AbstractProvider.php
│   ├── ProviderManager.php
│   └── Providers/
│       ├── GroqProvider.php
│       ├── RunPodProvider.php
│       └── OpenRouterProvider.php
├── Admin/
│   ├── Menu.php
│   └── TestPage.php
└── Core/
    ├── EnvLoader.php
    └── Extension.php
```

## License

MIT License - see LICENSE file for details

## Author

4wp.dev - https://4wp.dev

