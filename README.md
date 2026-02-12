# AiWire — AI Integration Module for ProcessWire

Connect your ProcessWire site to AI providers: **Anthropic (Claude)**, **OpenAI (GPT)**, **Google (Gemini)**, **xAI (Grok)**, and **OpenRouter** (400+ models).

Manage multiple API keys per provider, test connections from admin, and use AI in your templates with a clean PHP API.

> **📖 Full documentation with 25 real-world examples → [DOCUMENTATION.md](DOCUMENTATION.md)**

---

## Features

- **5 providers** — Anthropic, OpenAI, Google, xAI, OpenRouter
- **Multiple API keys** per provider with enable/disable toggle
- **Automatic fallback** — if one key/provider fails, the next one takes over
- **Connection testing** — verify each key with one click in admin
- **Test Chat** — interactive chat with parameter controls (temperature, tokens, model)
- **File cache** — TTL-based caching (day/week/month/year) with page context
- **Field storage** — save AI content to page fields for permanent, editable storage
- **Content blocks** — `generate()` method for multi-block pages (overview, FAQ, pairings…)
- **Conversation history** — multi-turn chat support
- **Cost optimization** — route tasks to different providers/models by complexity
- **Logging** — standard + debug logging via ProcessWire log system

---

## Requirements

- PHP 8.1+
- ProcessWire 3.0.210+
- cURL extension enabled

---

## Installation

1. Download or clone into `site/modules/AiWire/`
2. Admin → Modules → Refresh → Install **AiWire**
3. Configure → add API keys → click test button
4. Use `$modules->get('AiWire')` in your templates

```
site/modules/AiWire/
├── AiWire.module.php       # Main module
├── AiWireProvider.php       # API client for all providers
├── AiWireCache.php          # File-based cache system
├── README.md                # This file
├── DOCUMENTATION.md         # Full API reference + 25 examples
└── LICENSE
```

### Getting API Keys

| Provider   | Where to get a key |
|------------|-------------------|
| Anthropic  | [console.anthropic.com](https://console.anthropic.com/) |
| OpenAI     | [platform.openai.com/api-keys](https://platform.openai.com/api-keys) |
| Google     | [aistudio.google.com/apikey](https://aistudio.google.com/apikey) |
| xAI        | [console.x.ai](https://console.x.ai/) |
| OpenRouter | [openrouter.ai/keys](https://openrouter.ai/keys) |

---

## Quick Start

```php
$ai = $modules->get('AiWire');

// Simple — returns text only
echo $ai->chat('What is ProcessWire CMS?');

// Full response with metadata
$result = $ai->ask('Explain quantum computing in simple terms');
if ($result['success']) {
    echo $result['content'];              // AI response text
    echo $result['usage']['total_tokens']; // tokens used
}

// Fallback — tries Anthropic → OpenAI → Google
$result = $ai->askWithFallback('Summarize this article...', [
    'provider'          => 'anthropic',
    'fallbackProviders' => ['openai', 'google'],
]);

// Generate multiple AI blocks for a product page
$results = $ai->generate($page, [
    ['field' => 'ai_overview',    'prompt' => "Write overview of {$page->title}..."],
    ['field' => 'ai_food_pairing','prompt' => "Suggest pairings for {$page->title}..."],
    ['field' => 'ai_brand_story', 'prompt' => "Share 3 facts about {$page->brand->title}..."],
], ['cache' => 'M', 'temperature' => 0.7]);
```

---

## API Methods

| Method | Returns | Description |
|--------|---------|-------------|
| `chat($msg, $opts)` | `string` | Simple text response, empty on error |
| `ask($msg, $opts)` | `array` | Full response with `success`, `content`, `usage`, `raw` |
| `askWithFallback($msg, $opts)` | `array` | Tries all keys/providers until success |
| `askMultiple($msg, $providers)` | `array` | Same prompt to multiple providers |
| `askAndSave($page, $fields, $msg)` | `array` | Ask AI + save to page field (skip if exists) |
| `generate($page, $blocks, $opts)` | `array` | Multi-block generation with per-block settings |
| `saveTo($page, $field, $content)` | `bool` | Save content to page field |
| `loadFrom($page, $field)` | `?string` | Load content from page field |
| `getProvider($key, $specific, $idx)` | `?Provider` | Get raw provider instance |
| `getProvidersStatus()` | `array` | Status of all providers and keys |
| `clearCache($page)` | `int` | Clear cache for a page |
| `clearAllCache()` | `int` | Clear entire cache |
| `cacheStats()` | `array` | Cache files count, size, etc. |

→ Full API reference: **[DOCUMENTATION.md](DOCUMENTATION.md#api-reference)**

---

## Result Format

```php
// Successful response from ask(), askWithFallback(), askAndSave(), generate()
[
    'success' => true,
    'content' => 'The AI response text...',
    'message' => 'OK',
    'usage'   => ['input_tokens' => 25, 'output_tokens' => 148, 'total_tokens' => 173],
    'raw'     => [ /* full API response */ ],
    'cached'  => false,       // true if served from file cache
    'source'  => 'ai',        // askAndSave/generate only: 'ai', 'field', or 'error'
]
```

---

## Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `provider` | string | Module default | `anthropic`, `openai`, `google`, `xai`, `openrouter` |
| `model` | string | Key's model | Override model for this call |
| `systemPrompt` | string | Module default | System instructions for the AI |
| `maxTokens` | int | 1024 | Max tokens in response |
| `temperature` | float | 0.7 | 0.0 = precise, 1.0+ = creative |
| `history` | array | `[]` | Previous messages for multi-turn chat |
| `keyIndex` | int | — | Use a specific key by index (0-based) |
| `fallbackProviders` | array | — | Fallback providers for `askWithFallback` |
| `cache` | string\|bool | `false` | TTL: `'D'`, `'W'`, `'M'`, `'Y'`, `'2W'`, `'3M'` |
| `pageId` | int\|Page | 0 | Page context for cache scoping |
| `timeout` | int | 30 | Request timeout in seconds |
| `overwrite` | bool | false | `askAndSave`: always call AI even if field has content |
| `quiet` | bool | true | `askAndSave`: save without triggering PW hooks |

---

## Supported Models (February 2026)

| Provider | Models |
|----------|--------|
| **Anthropic** | `claude-opus-4-6`, `claude-sonnet-4-5-20250929`, `claude-haiku-4-5-20251001` |
| **OpenAI** | `gpt-5.2`, `gpt-5-mini`, `gpt-5-nano`, `gpt-4.1` |
| **Google** | `gemini-3-pro-preview`, `gemini-flash-latest`, `gemini-flash-lite-latest` |
| **xAI** | `grok-4-1-fast-reasoning`, `grok-4-1-fast-non-reasoning`, `grok-3-mini` |
| **OpenRouter** | `deepseek/deepseek-v3.2`, `qwen/qwen3-max-thinking`, `meta-llama/llama-4-maverick`, and 400+ more |

---

## Documentation & Examples

Full documentation with detailed API reference, 25 real-world examples (with ProcessWire setup, code, and expected output), cache strategies, field storage, multi-provider pipelines, and best practices:

### → [DOCUMENTATION.md](DOCUMENTATION.md)

**Content generation** — product pages, brand enrichment, category descriptions, cocktail recipes, region guides, tasting notes, review summaries

**SEO & translations** — auto-generate meta descriptions, OG titles, multi-language content

**Chatbots & APIs** — AI sommelier, gift recommendations, product comparison, form routing

**Infrastructure** — multi-provider cost optimization, fallback chains, key rotation, cache strategies, provider monitoring, team key separation, bulk LazyCron generation

---

## License

MIT — free for personal and commercial use.

## Author

**Maxim Alex** — [smnv.org](https://smnv.org) — maxim@smnv.org

Built for the [ProcessWire](https://processwire.com/) community.