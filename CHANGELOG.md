# Changelog

All notable changes to the AiWire module are documented in this file.

Format follows [Keep a Changelog](https://keepachangelog.com/). Versions follow [Semantic Versioning](https://semver.org/).

---

## [1.2.0] — 2026-04-23

### Changed
- **Anthropic models:** added Claude Opus 4.7, Claude Sonnet 4.6; default changed to `claude-sonnet-4-6-20260217`; removed deprecated Claude Sonnet 4.5
- **OpenAI models:** added GPT-5.4 family (`gpt-5.4`, `gpt-5.4-mini`, `gpt-5.4-nano`); default changed to `gpt-5.4`; removed deprecated `gpt-5-mini`, `gpt-5-nano`
- **Google models:** added Gemini 3.1 Pro Preview, Gemini 3 Flash, Gemini 3.1 Flash Lite, Gemini 2.5 Flash; default changed to `gemini-3-flash`; removed deprecated `gemini-flash-latest`, `gemini-flash-lite-latest`, `gemini-3-pro-preview`
- **xAI models:** added Grok 4.20, Grok Code Fast 1; removed deprecated Grok 3 Mini
- **OpenRouter models:** added Amazon Nova Micro/Lite, ByteDance Seed 1.6, Xiaomi MiMo V2 Flash, Zhipu AI GLM 5; sorted by company A-Z; updated Anthropic/Google/OpenAI refs to latest; total 19 models from 13 companies

### Improved
- Documentation split into README.md (overview) and DOCUMENTATION.md (full reference)
- All 25 examples now include Problem description, ProcessWire setup table, code, and Result output
- Added Table of Contents with anchor links in both README and DOCUMENTATION
- Added Result Format section explaining return arrays for all methods

---

## [1.1.0] — 2026-02-19

### Added
- **`generate()` method** — multi-block AI content generation with per-block settings (provider, model, temperature, systemPrompt, cache per block)
- Global options with per-block overrides: `generate($page, [['field' => '...', 'prompt' => '...', 'options' => [...]]], $globalOptions)`
- Each block checks field first (skip if content exists), calls AI only when needed
- Returns array keyed by field name with `source: 'ai'|'field'|'error'`
- 25 real-world usage examples based on lqrs.com (spirits/wine catalog)

---

## [1.0.0] — 2026-02-11

### Added

#### Core API
- `chat()` — simple text response, returns string
- `ask()` — full response with `success`, `content`, `usage`, `raw`, `cached`
- `askWithFallback()` — automatic fallback across keys and providers
- `askMultiple()` — same prompt to multiple providers for comparison
- `askAndSave()` — ask AI and save to page field (single or batch)
- `saveTo()` / `loadFrom()` — manual field storage

#### Providers
- 5 providers: Anthropic (Claude), OpenAI (GPT), Google (Gemini), xAI (Grok), OpenRouter (400+ models)
- Unified API across all providers via OpenAI-compatible Chat Completions endpoint
- Multiple API keys per provider with enable/disable toggle
- Default key selector per provider
- `getProvider()` — direct provider instance access
- `getProvidersStatus()` — status of all providers and keys

#### Cache
- File-based cache system (`AiWireCache`)
- TTL support: `'D'` (day), `'W'` (week), `'M'` (month), `'Y'` (year), custom like `'2W'`, `'3M'`
- Page-scoped cache keys via `pageId` option
- `clearCache($page)` — clear cache for specific page
- `clearAllCache()` — clear entire cache
- `cacheStats()` — files count, total size
- Auto-cleanup of expired cache on `LazyCron::everyHour`

#### Field Storage
- Save AI content to any ProcessWire Textarea/Text field
- Skip generation if field already has content (unless `overwrite: true`)
- Quiet save mode (no PW hooks triggered)
- Batch mode: multiple fields from one prompt or field-to-prompt mapping

#### Admin Interface
- AJAX-powered admin UI with per-provider key management
- Connection test button for each key (one-click verify)
- Test Chat panel with parameter controls (provider, model, temperature, maxTokens, timeout)
- Real-time provider status display
- Cache management UI with stats and clear buttons

#### Options
- `provider` — select provider per call
- `model` — override model per call
- `systemPrompt` — system instructions
- `maxTokens` — response length limit
- `temperature` — creativity control (0.0–2.0)
- `history` — conversation history for multi-turn chat
- `keyIndex` — use specific key by index
- `fallbackProviders` — fallback chain
- `cache` — TTL-based caching
- `pageId` — page context for cache scoping
- `timeout` — request timeout
- `overwrite` — force regeneration
- `quiet` — save without triggering hooks

#### Logging
- Standard logging via ProcessWire `wire('log')`
- Debug logging (enable in module config)
- Logs: API calls, errors, cache hits/misses, field saves

---

*← Back to [README.md](README.md) | [DOCUMENTATION.md](DOCUMENTATION.md)*