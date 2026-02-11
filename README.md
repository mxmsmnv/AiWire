# AiWire — AI Integration Module for ProcessWire

Connect your ProcessWire site to AI providers: **Anthropic (Claude)**, **OpenAI (GPT)**, **Google (Gemini)**, **xAI (Grok)**, and **OpenRouter** (400+ models).

Manage multiple API keys per provider, test connections from admin, and use AI in your templates with a clean PHP API.

---

## Features

- **5 providers** — Anthropic, OpenAI, Google, xAI, OpenRouter
- **Multiple API keys** per provider with enable/disable toggle
- **Automatic fallback** — if one key fails, the next one takes over
- **Connection testing** — verify each key with one click
- **Status indicators** — green/red/gray icons show key health
- **Test Chat** — send test messages from admin with key/model selection
- **Conversation history** — multi-turn chat support
- **System prompt** — set a default, override per call
- **Logging** — standard + debug logging via ProcessWire logs
- **AJAX admin UI** — save keys and test without page reloads

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

### File structure

```
site/modules/AiWire/
├── AiWire.module.php       # Main module
├── AiWireProvider.php       # API client for all providers
├── README.md
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

// One-liner — returns text only
echo $ai->chat('What is ProcessWire CMS?');

// Full response with metadata
$result = $ai->ask('Explain quantum computing in simple terms');

if ($result['success']) {
    echo $result['content'];        // AI response text
    echo $result['usage']['total_tokens']; // tokens used
}
```

---

## API Reference

### `chat(string $message, array $options = []): string`

Returns just the AI response text. Empty string on error. Use this for simple cases where you just need the answer.

```php
$ai = $modules->get('AiWire');

$text = $ai->chat('Suggest a tagline for a bakery website');
// "Fresh from our oven to your table — taste the difference."
```

### `ask(string $message, array $options = []): array`

Returns the full structured response with metadata.

```php
$result = $ai->ask('Translate "hello" to 10 languages');

// Response structure:
[
    'success' => true,           // bool — did it work?
    'content' => '...',          // string — AI response text
    'message' => 'OK',           // string — status message or error
    'usage'   => [
        'input_tokens'  => 15,   // tokens in your prompt
        'output_tokens' => 230,  // tokens in the response
        'total_tokens'  => 245,  // total tokens consumed
    ],
    'raw' => [ ... ],            // full raw API response
]
```

### `askWithFallback(string $message, array $options = []): array`

Tries all enabled keys for the primary provider, then falls back to other providers. Returns extra fields: `usedProvider`, `usedKeyIndex`, `usedKeyLabel`.

```php
$result = $ai->askWithFallback('Summarize this article...', [
    'provider'          => 'anthropic',
    'fallbackProviders' => ['openai', 'google'],
]);

// $result['usedProvider'] tells you which provider actually responded
```

### `askMultiple(string $message, array $providers, array $options = []): array`

Sends the same message to multiple providers. Returns an associative array keyed by provider name.

```php
$results = $ai->askMultiple('What is love?', ['anthropic', 'openai', 'xai']);
// $results['anthropic'] => [...], $results['openai'] => [...], etc.
```

### `getProvider(string $providerKey, ?string $specificKey, ?int $keyIndex): ?AiWireProvider`

Get a raw provider instance for advanced usage.

```php
$provider = $ai->getProvider('anthropic');
$testResult = $provider->testConnection();
```

### `getProvidersStatus(): array`

Get status overview of all providers and their key counts.

```php
$status = $ai->getProvidersStatus();
// ['anthropic' => ['label' => 'Anthropic (Claude)', 'active' => true, 'keyCount' => 2], ...]
```

### `cache(): AiWireCache`

Get the cache instance for direct access.

### `clearCache(int|Page $page = 0): int`

Clear all cached responses for a specific page. Returns number of files deleted.

```php
$ai->clearCache($page);     // clear cache for this page
$ai->clearCache(1042);      // clear by page ID
$ai->clearCache(0);         // clear global cache (no page context)
```

### `clearAllCache(): int`

Clear all AiWire cached responses across all pages.

### `cacheStats(): array`

Get cache statistics: total files, total size, pages count, expired count.

### `saveTo(Page $page, string $fieldName, string|array $content, bool $quiet = true): bool`

Save AI content to a page field. Accepts a string or a full `ask()` result array.

### `loadFrom(Page $page, string $fieldName): ?string`

Load content from a page field. Returns `null` if empty.

### `askAndSave(Page $page, string|array $fields, ?string $message, array $options = []): array`

Ask AI only if the field is empty — otherwise return existing content. Three calling modes:

```php
// Single field
$ai->askAndSave($page, 'seo_desc', 'Write SEO for: ...');

// Multiple fields, same prompt (AI called once, saved to all empty fields)
$ai->askAndSave($page, ['seo_desc', 'og_description'], 'Write SEO for: ...');

// Batch: each field gets its own prompt
$ai->askAndSave($page, [
    'seo_desc'    => 'Write SEO description for: ...',
    'ai_summary'  => 'Summarize: ...',
    'ai_keywords' => 'Extract 5 keywords from: ...',
]);
```

Single field returns one result with `'source' => 'field'|'ai'`. Multi/batch returns `['field_name' => result, ...]`.

### `generate(Page $page, array $blocks, array $globalOptions = []): array`

Generate multiple AI content blocks for a page. Each block has its own prompt, field, and optional per-block settings (provider, model, temperature, etc.). Global options apply unless overridden per block.

```php
$ai->generate($page, [
    ['field' => 'ai_overview', 'prompt' => '...'],
    ['field' => 'ai_facts',   'prompt' => '...', 'options' => ['provider' => 'openai']],
], ['temperature' => 0.5, 'cache' => 'W']);
```

Returns `['field_name' => result, ...]` with `source: 'field'|'ai'|'error'`.

---

## Options

Every method that accepts `$options` supports these parameters:

| Option              | Type        | Default         | Description |
|---------------------|-------------|-----------------|-------------|
| `provider`          | string      | Module default  | `anthropic`, `openai`, `google`, `xai`, `openrouter` |
| `model`             | string      | Key's model     | Override model for this call |
| `systemPrompt`      | string      | Module default  | System instructions for the AI |
| `maxTokens`         | int         | 1024            | Max tokens in response |
| `temperature`       | float       | 0.7             | Creativity: 0.0 = precise, 1.0+ = creative |
| `history`           | array       | `[]`            | Previous messages for multi-turn |
| `key`               | string      | —               | Use a specific API key string |
| `keyIndex`          | int         | —               | Use a specific key by its index (0-based) |
| `fallbackProviders` | array       | —               | For `askWithFallback` — list of fallback providers |
| `cache`             | string\|int | `false`         | Cache TTL: `'D'`, `'W'`, `'M'`, `'Y'`, `'2W'`, `'3M'`, or seconds |
| `pageId`            | int\|Page   | 0               | Page context for cache (groups cache files by page) |
| `timeout`           | int         | 30              | Request timeout in seconds |
| `overwrite`         | bool        | false           | For `askAndSave` — always call AI even if field has content |
| `quiet`             | bool        | true            | For `askAndSave` — save without triggering PW hooks |

---

## Supported Models (February 2026)

### Anthropic (Claude)

| Model ID | Name |
|----------|------|
| `claude-opus-4-6` | Claude Opus 4.6 |
| `claude-sonnet-4-5-20250929` | Claude Sonnet 4.5 |
| `claude-haiku-4-5-20251001` | Claude Haiku 4.5 |

### OpenAI (GPT)

| Model ID | Name |
|----------|------|
| `gpt-5.2` | GPT-5.2 |
| `gpt-5-mini` | GPT-5 Mini |
| `gpt-5-nano` | GPT-5 Nano |
| `gpt-4.1` | GPT-4.1 |

### Google (Gemini)

| Model ID | Name |
|----------|------|
| `gemini-3-pro-preview` | Gemini 3 Pro Preview |
| `gemini-flash-latest` | Gemini Flash |
| `gemini-flash-lite-latest` | Gemini Flash Lite |

### xAI (Grok)

| Model ID | Name |
|----------|------|
| `grok-4-1-fast-reasoning` | Grok 4.1 Fast (Reasoning) |
| `grok-4-1-fast-non-reasoning` | Grok 4.1 Fast |
| `grok-3-mini` | Grok 3 Mini |

### OpenRouter (400+ models)

| Model ID | Name |
|----------|------|
| `deepseek/deepseek-v3.2` | DeepSeek V3.2 |
| `qwen/qwen3-max-thinking` | Qwen 3 Max Thinking |
| `google/gemini-3-flash-preview` | Gemini 3 Flash Preview |
| `google/gemini-2.5-flash` | Gemini 2.5 Flash |
| `minimax/minimax-m2.1` | MiniMax M2.1 |
| `z-ai/glm-4.7` | GLM 4.7 |
| `mistralai/devstral-2512` | Devstral 2512 |
| `mistralai/mistral-small-3.2-24b-instruct` | Mistral Small 3.2 24B |
| `meta-llama/llama-4-maverick` | Llama 4 Maverick |
| `nvidia/nemotron-3-nano-30b-a3b` | Nemotron 3 Nano 30B |
| `meta-llama/llama-3.3-70b-instruct` | Llama 3.3 70B |
| `openai/gpt-5.2` | GPT-5.2 (via OR) |
| `anthropic/claude-sonnet-4.5` | Claude Sonnet 4.5 (via OR) |
| `x-ai/grok-4-1-fast` | Grok 4.1 Fast (via OR) |

> **Tip:** OpenRouter gives you access to all providers through a single API key. Useful if you want to test different models without managing separate accounts.

---

## Usage Examples

All examples below are based on a real-world alcohol/spirits catalog site ([lqrs.com](https://lqrs.com)) built with ProcessWire. The site has templates like `product`, `brand`, `category`, `cocktail`, `region`, and `article`. Adapt field names and templates to your own project.

---

### 1. Product page AI content blocks

> **Problem:** Product pages need rich content (overview, brand story, food pairings, serving guide) but writing it manually for hundreds of products is impossible.
> `generate()` creates all blocks at once — each with its own prompt and settings — saving content to page fields so AI only runs once per product.

**ProcessWire setup:**

| Item | Details |
|------|---------|
| Template | `product` |
| Fields | `title` (Text), `body` (Textarea), `brand` (Page ref → `brand`), `region` (Page ref → `region`), `abv` (Float), `volume` (Integer), `tasting_notes` (Textarea) |
| AI fields | `ai_overview` (Textarea), `ai_brand_story` (Textarea), `ai_food_pairing` (Textarea), `ai_serving_guide` (Textarea) |
| File | `site/templates/product.php` |

```php
// site/templates/product.php — e.g. "2015 Louis Roederer Cristal"
$ai = $modules->get('AiWire');
$body = strip_tags($page->body);

$results = $ai->generate($page, [
    [
        'field'  => 'ai_overview',
        'prompt' => "Write a detailed overview of {$page->title}. "
                  . "Include flavor profile, aging process, and what makes this product special. "
                  . "Category: {$page->parent->title}. "
                  . "Brand: {$page->brand->title}. "
                  . "Region: {$page->region->title}. "
                  . "ABV: {$page->abv}%. Volume: {$page->volume}ml.",
        'options' => ['maxTokens' => 600, 'temperature' => 0.6],
    ],
    [
        'field'        => 'ai_brand_story',
        'prompt'       => "Share 3 interesting facts about {$page->brand->title} that most people don't know. "
                        . "Be engaging, surprising. Start each fact with a bold statement.",
        'systemPrompt' => 'You are a spirits historian. Write in a friendly, conversational tone. '
                        . 'Focus on heritage, craftsmanship, and unique traditions.',
        'options'      => ['maxTokens' => 500],
    ],
    [
        'field'  => 'ai_food_pairing',
        'prompt' => "Suggest 5 specific food pairings for {$page->title} ({$page->parent->title}). "
                  . "For each pairing explain WHY it works in one sentence. "
                  . "Consider the flavor profile: {$page->tasting_notes}.",
        'options' => ['temperature' => 0.5, 'maxTokens' => 400],
    ],
    [
        'field'  => 'ai_serving_guide',
        'prompt' => "Write a brief serving guide for {$page->title}. "
                  . "Cover: ideal temperature, glassware, decanting (if applicable), "
                  . "and the best occasion to enjoy it.",
        'options' => ['provider' => 'google', 'model' => 'gemini-flash-lite-latest', 'maxTokens' => 300],
    ],
], [
    'cache'       => 'M',
    'temperature' => 0.7,
]);

// Output in template
foreach (['ai_overview', 'ai_brand_story', 'ai_food_pairing', 'ai_serving_guide'] as $field) {
    if (isset($results[$field]) && $results[$field]['success']) {
        echo "<section class='{$field}'>{$results[$field]['content']}</section>";
    }
}
```

---

### 2. Auto-generate SEO on page save

> **Problem:** Every product needs a unique meta description and OG title for search engines and social sharing, but editors skip this step.
> This hook auto-generates SEO fields whenever a product is saved, so every page is search-ready without manual effort.

**ProcessWire setup:**

| Item | Details |
|------|---------|
| Template | `product` |
| Fields | `title` (Text), `body` (Textarea), `seo_description` (Text, maxlength=160), `og_title` (Text, maxlength=60) |
| File | `site/ready.php` |

```php
// site/ready.php
$wire->addHookAfter('Pages::saved', function(HookEvent $event) {
    $page = $event->arguments(0);
    if ($page->template->name !== 'product') return;
    if (!$page->isChanged('title') && !$page->isChanged('body')) return;

    $ai = $this->modules->get('AiWire');

    $ai->askAndSave($page, [
        'seo_description' => "Write an SEO meta description (max 155 chars) for this product listing. "
                           . "Include the product name and one compelling selling point.\n\n"
                           . "Product: {$page->title}\n"
                           . "Category: {$page->parent->title}\n"
                           . "Content: " . mb_substr(strip_tags($page->body), 0, 1000),
        'og_title'        => "Write a compelling social media title (max 60 chars) for: {$page->title}. "
                           . "Make it enticing and shareable. Return ONLY the title text.",
    ], null, [
        'overwrite'   => true,
        'maxTokens'   => 100,
        'temperature' => 0.4,
    ]);
});
```

---

### 3. Brand page enrichment

> **Problem:** Brand pages feel thin — just a logo and product list. Writing history, highlights, and FAQs for every brand is a huge content effort.
> `generate()` fills brand pages with rich AI content that references their actual product lineup.

**ProcessWire setup:**

| Item | Details |
|------|---------|
| Template | `brand` (parent of `product` pages) |
| Fields | `title` (Text), `country` (Page ref → `country`) |
| AI fields | `ai_brand_history` (Textarea), `ai_brand_highlights` (Textarea), `ai_brand_faq` (Textarea) |
| File | `site/templates/brand.php` |

```php
// site/templates/brand.php — e.g. "Chivas Regal", "Louis Roederer"
$ai = $modules->get('AiWire');

$productList = '';
foreach ($page->children("limit=20") as $p) {
    $productList .= "- {$p->title} ({$p->parent->title}, {$p->abv}%)\n";
}

$results = $ai->generate($page, [
    [
        'field'  => 'ai_brand_history',
        'prompt' => "Write a concise history of {$page->title} (alcohol brand). "
                  . "Cover founding, key milestones, and what defines their style. "
                  . "Country: {$page->country->title}. 2-3 paragraphs.",
        'options' => ['maxTokens' => 600, 'temperature' => 0.5],
    ],
    [
        'field'  => 'ai_brand_highlights',
        'prompt' => "Based on this product lineup, write 3 reasons why {$page->title} stands out:\n\n"
                  . $productList . "\n"
                  . "Be specific. Reference actual products from the list.",
        'options' => ['maxTokens' => 400],
    ],
    [
        'field'        => 'ai_brand_faq',
        'prompt'       => "Write 5 frequently asked questions about {$page->title} with short answers. "
                        . "Include: origin, flagship product, best for beginners, price range, how to drink.",
        'systemPrompt' => 'Format each Q&A as: **Q: question**\nA: answer\n',
        'options'      => ['maxTokens' => 500, 'temperature' => 0.4],
    ],
], ['cache' => 'M']);
```

---

### 4. Category page descriptions

> **Problem:** Category pages (Whiskey, Vodka, Red Wine…) need SEO-friendly descriptions, but they rarely get written because there are dozens of categories.
> This hook generates a description automatically the first time a category is saved.

**ProcessWire setup:**

| Item | Details |
|------|---------|
| Template | `category` (parent of `product` pages) |
| Fields | `title` (Text), `ai_description` (Textarea) |
| File | `site/ready.php` |

```php
// site/ready.php — auto-generate descriptions for category pages (Whiskey, Vodka, Red Wine, etc.)
$wire->addHookAfter('Pages::saved', function(HookEvent $event) {
    $page = $event->arguments(0);
    if ($page->template->name !== 'category') return;
    if ($page->ai_description) return; // already has content

    $ai = $this->modules->get('AiWire');
    $productCount = $page->children->count();
    $topProducts = $page->children("sort=-views, limit=5")->implode(', ', 'title');

    $ai->askAndSave($page, 'ai_description',
        "Write an engaging category description for a '{$page->title}' section "
        . "of an online spirits and wine store. "
        . "We have {$productCount} products including: {$topProducts}. "
        . "Write 2 paragraphs: first about the category in general, "
        . "second about what makes our selection special. "
        . "Do NOT list products. Do NOT use bullet points.",
        ['maxTokens' => 400, 'temperature' => 0.6]
    );
});
```

---

### 5. Cocktail recipe generator

> **Problem:** Each cocktail page needs an intro, step-by-step instructions, tips, and variations — too much manual writing for a cocktail database.
> `generate()` produces all recipe sections from the ingredient list, giving each cocktail a complete write-up.

**ProcessWire setup:**

| Item | Details |
|------|---------|
| Template | `cocktail` |
| Fields | `title` (Text), `ingredients` (Page ref multiple → `ingredient`, or RepeaterMatrix) |
| AI fields | `ai_recipe_intro` (Textarea), `ai_recipe_steps` (Textarea), `ai_recipe_tips` (Textarea), `ai_recipe_variations` (Textarea) |
| File | `site/templates/cocktail.php` |

```php
// site/templates/cocktail.php — e.g. "Freddie Bartholomew", "Arnold Palmer Mocktail"
$ai = $modules->get('AiWire');

$ingredients = $page->ingredients->implode(', ', 'title'); // RepeaterMatrix or PageArray

$results = $ai->generate($page, [
    [
        'field'  => 'ai_recipe_intro',
        'prompt' => "Write a 2-sentence intro for the '{$page->title}' cocktail. "
                  . "Ingredients: {$ingredients}. "
                  . "Mention the origin or inspiration behind this drink if known.",
        'options' => ['maxTokens' => 150, 'temperature' => 0.7],
    ],
    [
        'field'  => 'ai_recipe_steps',
        'prompt' => "Write step-by-step mixing instructions for '{$page->title}'. "
                  . "Ingredients: {$ingredients}. "
                  . "Include preparation, mixing technique, garnish, and serving glass. "
                  . "Number each step.",
        'options' => ['maxTokens' => 400, 'temperature' => 0.3],
    ],
    [
        'field'  => 'ai_recipe_tips',
        'prompt' => "Write 3 pro tips for making the perfect '{$page->title}'. "
                  . "Include: a substitution idea, a presentation trick, and a common mistake to avoid.",
        'options' => ['maxTokens' => 300],
    ],
    [
        'field'  => 'ai_recipe_variations',
        'prompt' => "Suggest 3 creative variations of '{$page->title}'. "
                  . "Original ingredients: {$ingredients}. "
                  . "For each variation give a fun name and what to change.",
        'options' => ['maxTokens' => 300, 'temperature' => 0.8],
    ],
], ['cache' => 'W']);
```

---

### 6. Region / terroir guide

> **Problem:** Wine region pages need educational content about geography, climate, and traditions — plus personalized recommendations from your actual catalog.
> This generates a complete region guide enriched with products you actually sell.

**ProcessWire setup:**

| Item | Details |
|------|---------|
| Template | `region` (referenced by products via Page ref) |
| Fields | `title` (Text), `ai_region_overview` (Textarea), `ai_region_recommendations` (Textarea) |
| Relation | `product` template has `region` (Page ref → `region`) |
| File | `site/templates/region.php` |

```php
// site/templates/region.php — e.g. "Champagne", "Tuscany", "Islay"
$ai = $modules->get('AiWire');

$products = $pages->find("template=product, region={$page->id}, limit=30");
$productList = $products->implode("\n", function($p) {
    return "- {$p->title} by {$p->brand->title} ({$p->parent->title})";
});

$results = $ai->generate($page, [
    [
        'field'  => 'ai_region_overview',
        'prompt' => "Write a guide to {$page->title} as a wine/spirits region. "
                  . "Country: {$page->parent->title}. "
                  . "Cover: geography, climate, key grape varieties or distillation traditions, "
                  . "and what makes products from this region distinctive. 3 paragraphs.",
        'options' => ['maxTokens' => 600, 'temperature' => 0.5],
    ],
    [
        'field'  => 'ai_region_recommendations',
        'prompt' => "From this product list, pick 5 standout products and explain "
                  . "why each one is worth trying. Be specific about flavors and occasions.\n\n"
                  . $productList,
        'options' => ['maxTokens' => 500, 'temperature' => 0.6],
    ],
], ['cache' => 'M']);
```

---

### 7. Review summarizer

> **Problem:** A product with 50+ reviews is hard to scan. Customers want a quick summary — what people love, what they don't, and who this product is best for.
> AI summarizes all reviews into a concise paragraph, saved to the product page.

**ProcessWire setup:**

| Item | Details |
|------|---------|
| Template | `product` |
| Fields | `reviews` (Repeater/RepeaterMatrix: `rating` Integer, `body` Textarea, `author` Text), `ai_review_summary` (Textarea) |
| File | `site/templates/product.php` |

```php
// site/templates/product.php — summarize user reviews
$ai = $modules->get('AiWire');

$reviews = $page->reviews; // RepeaterMatrix: rating, body, author
if ($reviews->count() >= 3) {
    $reviewText = '';
    foreach ($reviews as $r) {
        $reviewText .= "Rating: {$r->rating}/5 by {$r->author}: {$r->body}\n---\n";
    }

    $ai->askAndSave($page, 'ai_review_summary',
        "Analyze these {$reviews->count()} customer reviews and write a summary. "
        . "Include: average sentiment, most praised qualities, any common complaints, "
        . "and who this product is best for. 2-3 sentences.\n\n"
        . $reviewText,
        [
            'maxTokens'   => 250,
            'temperature' => 0.3,
            'cache'       => 'W',
        ]
    );
}
```

---

### 8. Content moderation for user reviews

> **Problem:** User-submitted reviews can contain spam, hate speech, or fake content — manual moderation doesn't scale.
> AI checks every review before publication, auto-unpublishing flagged content with a reason for the moderator.

**ProcessWire setup:**

| Item | Details |
|------|---------|
| Template | `review` (child of `product`) |
| Fields | `body` (Textarea), `moderation_note` (Text, hidden from frontend) |
| File | `site/ready.php` |

```php
// site/ready.php
$wire->addHookBefore('Pages::saveReady', function(HookEvent $event) {
    $page = $event->arguments(0);
    if ($page->template->name !== 'review') return;
    if (!$page->isChanged('body')) return;

    $ai = $this->modules->get('AiWire');

    $result = $ai->ask(
        "Analyze this product review for an alcohol/spirits store. Check for:\n"
        . "1. Spam or irrelevant content\n"
        . "2. Hate speech or harassment\n"
        . "3. Fake review patterns\n"
        . "4. References to underage drinking\n"
        . "5. Personal information (phone numbers, addresses)\n\n"
        . "Reply ONLY with JSON: {\"safe\": true/false, \"reason\": \"...\"}\n\n"
        . "Review: {$page->body}",
        [
            'maxTokens'   => 100,
            'temperature' => 0,
            'provider'    => 'openai',
            'model'       => 'gpt-5-nano',
        ]
    );

    if ($result['success']) {
        $analysis = json_decode($result['content'], true);
        if ($analysis && !$analysis['safe']) {
            $page->addStatus(Page::statusUnpublished);
            $page->moderation_note = $analysis['reason'];
            $this->warning("Review flagged by AI: {$analysis['reason']}");
        }
    }
});
```

---

### 9. Multi-language product descriptions

> **Problem:** Expanding to international markets means translating hundreds of product descriptions — too expensive for human translators on every product.
> LazyCron finds products with empty translation fields and fills them automatically using Google Gemini.

**ProcessWire setup:**

| Item | Details |
|------|---------|
| Template | `product` |
| Fields | `body` (Textarea, source language), `body_es` (Textarea), `body_fr` (Textarea), `body_ja` (Textarea), `body_zh` (Textarea) |
| File | `site/ready.php` (LazyCron) |

```php
// site/ready.php — translate product descriptions into multiple languages
function translateProducts() {
    $ai = wire('modules')->get('AiWire');
    $products = wire('pages')->find("template=product, body!='', body_es='', limit=20");

    $languages = [
        'body_es' => 'Spanish',
        'body_fr' => 'French',
        'body_ja' => 'Japanese',
        'body_zh' => 'Chinese (Simplified)',
    ];

    foreach ($products as $product) {
        foreach ($languages as $field => $langName) {
            if ($product->$field) continue; // already translated

            $ai->askAndSave($product, $field,
                "Translate this product description to {$langName}. "
                . "Keep all product names, brand names, and technical terms in English. "
                . "Preserve the marketing tone. Return ONLY the translation.\n\n"
                . $product->body,
                [
                    'provider'    => 'google',
                    'model'       => 'gemini-flash-latest',
                    'maxTokens'   => 2000,
                    'temperature' => 0.2,
                ]
            );
        }
    }
}

// Run via LazyCron
$wire->addHook('LazyCron::everyHour', function() { translateProducts(); });
```

---

### 10. AI sommelier chatbot

> **Problem:** Customers browsing a spirits store don't know what to buy — they need a knowledgeable advisor who knows your actual catalog.
> This chatbot searches your products, feeds context to the AI, and gives personalized recommendations with links to real product pages.

**ProcessWire setup:**

| Item | Details |
|------|---------|
| Template | `api-sommelier` (URL segment: `/api/ai-sommelier/`) |
| Dependencies | `product` template with `title`, `body`, `tasting_notes`, `brand` (Page ref), `region` (Page ref), `abv` (Float) |
| File | `site/templates/api-sommelier.php` |
| Frontend | AJAX POST with `question` parameter |

```php
// site/templates/api/ai-sommelier.php
header('Content-Type: application/json');

$ai       = $modules->get('AiWire');
$question = $input->post->text('question');
$history  = $session->get('sommelier_history') ?: [];

if (!$question) {
    echo json_encode(['error' => 'No question']);
    return;
}

// Find relevant products
$clean = $sanitizer->selectorValue($question);
$products = $pages->find("template=product, title|body|tasting_notes%={$clean}, limit=8");

$context = "Available products:\n";
foreach ($products as $p) {
    $context .= "- {$p->title} ({$p->parent->title}) — {$p->brand->title}, "
              . "{$p->region->title}, {$p->abv}%, {$p->url}\n";
}

$result = $ai->askWithFallback($question, [
    'provider'          => 'anthropic',
    'fallbackProviders' => ['openai', 'google'],
    'systemPrompt'      => "You are an expert sommelier and spirits advisor for LQRS, "
        . "an online wine and spirits store. Help customers find the right drink. "
        . "Always recommend specific products from our catalog when relevant. "
        . "Include product URLs in your recommendations. "
        . "If asked about cocktails, suggest recipes using our products. "
        . "Be warm, knowledgeable, and never condescending. "
        . "Reply in the customer's language.",
    'maxTokens'    => 600,
    'temperature'  => 0.6,
    'history'      => array_merge(
        [['role' => 'user', 'content' => $context],
         ['role' => 'assistant', 'content' => "I've reviewed our catalog. How can I help you today?"]],
        $history
    ),
]);

if ($result['success']) {
    $history[] = ['role' => 'user', 'content' => $question];
    $history[] = ['role' => 'assistant', 'content' => $result['content']];
    if (count($history) > 20) $history = array_slice($history, -20);
    $session->set('sommelier_history', $history);
}

echo json_encode([
    'success'  => $result['success'],
    'reply'    => $result['content'] ?? '',
    'products' => $products->explode(['title', 'url', 'parent' => 'title']),
]);
```

---

### 11. Tasting notes generator

> **Problem:** Professional tasting notes (appearance, nose, palate, finish) require expert knowledge — most product pages ship without them.
> LazyCron finds products missing tasting notes and generates industry-standard descriptions every 6 hours.

**ProcessWire setup:**

| Item | Details |
|------|---------|
| Template | `product` |
| Fields | `tasting_notes` (Textarea), `brand` (Page ref), `region` (Page ref), `abv` (Float) |
| File | `site/ready.php` (LazyCron) |

```php
// site/ready.php — generate tasting notes for products that don't have them
$wire->addHook('LazyCron::every6Hours', function() {
    $ai = wire('modules')->get('AiWire');
    $products = wire('pages')->find("template=product, tasting_notes='', limit=10");

    foreach ($products as $p) {
        $ai->askAndSave($p, 'tasting_notes',
            "Write professional tasting notes for {$p->title}. "
            . "Type: {$p->parent->title}. Brand: {$p->brand->title}. "
            . "Region: {$p->region->title}. ABV: {$p->abv}%. "
            . "Cover: appearance, nose (aroma), palate (taste), finish. "
            . "Use industry-standard terminology. 3-4 sentences.",
            [
                'maxTokens'   => 250,
                'temperature' => 0.4,
                'cache'       => 'M',
            ]
        );
    }
});
```

---

### 12. Gift recommendation engine

> **Problem:** "What should I buy for my dad's birthday under $80?" — your site can't answer this with standard filtering.
> This API endpoint takes customer preferences, cross-references your catalog, and returns AI-picked recommendations with explanations.

**ProcessWire setup:**

| Item | Details |
|------|---------|
| Template | `api-gift-finder` (URL segment: `/api/gift-finder/`) |
| Dependencies | `product` template with `title`, `price` (Float), `brand` (Page ref) |
| File | `site/templates/api-gift-finder.php` |
| Frontend | AJAX POST with `occasion`, `budget`, `taste`, `type` parameters |

```php
// site/templates/api/gift-finder.php
header('Content-Type: application/json');

$ai = $modules->get('AiWire');

$occasion = $input->post->text('occasion');  // birthday, anniversary, holiday...
$budget   = $input->post->text('budget');     // 30-50, 50-100, 100+
$taste    = $input->post->text('taste');      // sweet, dry, smoky, fruity...
$type     = $input->post->text('type');       // wine, whiskey, any...

$selector = "template=product";
if ($type && $type !== 'any') $selector .= ", parent.name={$type}";

$catalog = $pages->find("{$selector}, limit=50");
$productList = '';
foreach ($catalog as $p) {
    $productList .= "- {$p->title} | {$p->parent->title} | {$p->brand->title} | \${$p->price}\n";
}

$result = $ai->ask(
    "A customer needs a gift recommendation.\n"
    . "Occasion: {$occasion}\n"
    . "Budget: \${$budget}\n"
    . "Taste preference: {$taste}\n"
    . "Category preference: {$type}\n\n"
    . "Here are our available products:\n{$productList}\n\n"
    . "Recommend 3 products from the list above. For each one explain "
    . "why it's perfect for this occasion and taste. "
    . "Reply as JSON array: [{\"product\": \"...\", \"reason\": \"...\"}]",
    [
        'maxTokens'   => 500,
        'temperature' => 0.6,
        'cache'       => 'D',
    ]
);

echo json_encode([
    'success'         => $result['success'],
    'recommendations' => $result['success'] ? json_decode($result['content'], true) : [],
]);
```

---

### 13. Compare products with AI

> **Problem:** Your comparison page only shows raw specs — no context about flavor differences or value for money.
> AI reads product data and writes a natural-language comparison with specific recommendations for different preferences.

**ProcessWire setup:**

| Item | Details |
|------|---------|
| Template | `compare` (URL: `/compare/?products=1042,1043,1044`) |
| Dependencies | `product` template with `title`, `brand` (Page ref), `region` (Page ref), `abv` (Float), `price` (Float), `tasting_notes` (Textarea) |
| File | `site/templates/compare.php` |

```php
// site/templates/compare.php
$ai = $modules->get('AiWire');

$ids = $input->get->intArray('products'); // ?products=1042,1043,1044
$products = $pages->getById($ids);

$productData = '';
foreach ($products as $p) {
    $productData .= "### {$p->title}\n"
        . "Category: {$p->parent->title}\n"
        . "Brand: {$p->brand->title}\n"
        . "Region: {$p->region->title}\n"
        . "ABV: {$p->abv}%\n"
        . "Price: \${$p->price}\n"
        . "Tasting: {$p->tasting_notes}\n\n";
}

$result = $ai->ask(
    "Compare these {$products->count()} products for a customer who wants to make an informed choice:\n\n"
    . $productData
    . "Write a comparison covering: flavor profiles, value for money, best occasions, "
    . "and a clear recommendation for different preferences (e.g. 'If you prefer bold flavors, go with X').",
    [
        'maxTokens'   => 800,
        'temperature' => 0.5,
        'cache'       => 'W',
        'pageId'      => $products->first()->id,
    ]
);
```

---

### 14. Auto-tag products with AI

> **Problem:** Products need filter tags (smoky, premium, gift-worthy, after-dinner…) for faceted search, but nobody tags 500+ products manually.
> AI analyzes each product and assigns relevant tags, creating new tag pages if they don't exist.

**ProcessWire setup:**

| Item | Details |
|------|---------|
| Template | `product` with `ai_tags` (Page ref multiple → `tag`) |
| Template | `tag` (under `/tags/` parent) with `title` (Text) |
| File | `site/ready.php` |

```php
// site/ready.php — assign tags to products based on AI analysis
$wire->addHookAfter('Pages::saved', function(HookEvent $event) {
    $page = $event->arguments(0);
    if ($page->template->name !== 'product') return;
    if ($page->ai_tags->count()) return; // already tagged

    $ai = $this->modules->get('AiWire');

    $result = $ai->ask(
        "Analyze this product and assign relevant tags for filtering in an online store.\n\n"
        . "Product: {$page->title}\n"
        . "Type: {$page->parent->title}\n"
        . "Description: " . mb_substr(strip_tags($page->body), 0, 1000) . "\n\n"
        . "Return a JSON array of 5-10 tags. Use lowercase. "
        . "Include: flavor profile, occasion, gift suitability, price tier, style.\n"
        . "Example: [\"smoky\",\"premium\",\"gift-worthy\",\"after-dinner\",\"aged\"]",
        [
            'maxTokens'   => 100,
            'temperature' => 0.2,
            'provider'    => 'openai',
            'model'       => 'gpt-5-nano',
        ]
    );

    if ($result['success']) {
        $tags = json_decode($result['content'], true);
        if (is_array($tags)) {
            foreach ($tags as $tagName) {
                $tag = wire('pages')->get("template=tag, name=" . wire('sanitizer')->pageName($tagName));
                if (!$tag->id) {
                    $tag = new Page();
                    $tag->template = 'tag';
                    $tag->parent = wire('pages')->get('/tags/');
                    $tag->title = ucfirst($tagName);
                    $tag->name = wire('sanitizer')->pageName($tagName);
                    $tag->save();
                }
                $page->ai_tags->add($tag);
            }
            $page->save('ai_tags', ['quiet' => true]);
        }
    }
});
```

---

### 15. Weekly newsletter with AI summary

> **Problem:** Sending a weekly email digest requires someone to write an engaging intro about new arrivals and trending products — every single week.
> AI writes the newsletter body based on actual new products and view statistics from your catalog.

**ProcessWire setup:**

| Item | Details |
|------|---------|
| Dependencies | `product` template with `views` (Integer) and `brand` (Page ref), `wireMail()` configured |
| File | `site/ready.php` (LazyCron) |

```php
// site/ready.php — weekly digest email with AI-generated summary
$wire->addHook('LazyCron::everyWeek', function() {
    $ai    = wire('modules')->get('AiWire');
    $since = date('Y-m-d', strtotime('-7 days'));

    $newProducts = wire('pages')->find("template=product, created>={$since}");
    $topViewed   = wire('pages')->find("template=product, sort=-views, limit=5");

    $productList = $newProducts->implode("\n", function($p) {
        return "- {$p->title} ({$p->parent->title}) by {$p->brand->title}";
    });

    $topList = $topViewed->implode("\n", function($p) {
        return "- {$p->title} ({$p->views} views)";
    });

    $summary = $ai->chat(
        "Write a friendly weekly newsletter intro for an online spirits store.\n\n"
        . "New arrivals this week ({$newProducts->count()} products):\n{$productList}\n\n"
        . "Most popular products:\n{$topList}\n\n"
        . "Write 2-3 engaging paragraphs. Highlight interesting new arrivals "
        . "and mention trending products. Keep it under 200 words.",
        ['maxTokens' => 400, 'temperature' => 0.7]
    );

    if ($summary) {
        $mail = wireMail();
        $mail->to('subscribers@lqrs.com');
        $mail->subject("🥂 LQRS Weekly: {$newProducts->count()} New Arrivals");
        $mail->body($summary);
        $mail->send();
    }
});
```

---

### 16. Multi-turn chatbot with session history

> **Problem:** A simple Q&A endpoint forgets previous messages — users can't have a natural conversation with follow-up questions.
> Session-based history keeps the last 20 messages, letting the AI reference earlier context for coherent multi-turn dialogue.

**ProcessWire setup:**

| Item | Details |
|------|---------|
| Template | `api-chatbot` (URL segment: `/api/chatbot/`) |
| File | `site/templates/api-chatbot.php` |
| Frontend | AJAX POST with `message` and optional `action=reset` |

```php
// site/templates/api/chatbot.php
header('Content-Type: application/json');

$ai      = $modules->get('AiWire');
$message = $input->post->text('message');
$action  = $input->post->text('action');

if ($action === 'reset' || !$session->get('chat_history')) {
    $session->set('chat_history', []);
}

if (!$message) {
    echo json_encode(['error' => 'No message']);
    return;
}

$history = $session->get('chat_history');

$result = $ai->askWithFallback($message, [
    'provider'          => 'anthropic',
    'fallbackProviders' => ['openai', 'google'],
    'systemPrompt'      => 'You are a helpful assistant for LQRS, an online wine and spirits store. '
        . 'Help customers find products, suggest pairings, and answer questions. '
        . 'Be concise and reply in the user\'s language.',
    'maxTokens'    => 500,
    'temperature'  => 0.6,
    'history'      => $history,
]);

if ($result['success']) {
    $history[] = ['role' => 'user', 'content' => $message];
    $history[] = ['role' => 'assistant', 'content' => $result['content']];
    if (count($history) > 20) $history = array_slice($history, -20);
    $session->set('chat_history', $history);
}

echo json_encode([
    'success' => $result['success'],
    'reply'   => $result['content'] ?? '',
]);
```

---

### 17. Compare AI providers (A/B testing)

> **Problem:** Not sure which AI provider writes the best product descriptions for your niche? Test them all side-by-side before committing.
> `askMultiple()` sends the same prompt to all providers and shows results in a comparison table.

**ProcessWire setup:**

| Item | Details |
|------|---------|
| Template | any (admin-only or debug page) |
| File | any template file or Tracy Debugger console |

```php
$ai = $modules->get('AiWire');

$prompt = 'Describe the flavor profile of a 12-year-old single malt Scotch in 3 sentences.';
$results = $ai->askMultiple($prompt, ['anthropic', 'openai', 'google', 'xai']);

echo "<table><tr><th>Provider</th><th>Response</th><th>Tokens</th></tr>";
foreach ($results as $provider => $result) {
    $content = $result['success'] ? htmlspecialchars($result['content']) : 'Error: ' . $result['message'];
    $tokens  = $result['usage']['total_tokens'] ?? '—';
    echo "<tr><td>{$provider}</td><td>{$content}</td><td>{$tokens}</td></tr>";
}
echo "</table>";
```

---

### 18. Bulk content generation with LazyCron

> **Problem:** You just imported 500 products but most are missing descriptions, tasting notes, and category text. Generating it all at once would overwhelm the API.
> LazyCron processes a small batch every hour — products, brands, and categories — filling gaps gradually without hitting rate limits.

**ProcessWire setup:**

| Item | Details |
|------|---------|
| Templates | `product`, `brand`, `category` |
| Fields | `tasting_notes` (Textarea), `abv` (Float) on product; `ai_brand_history` (Textarea), `country` (Page ref) on brand; `ai_description` (Textarea) on category |
| File | `site/ready.php` (LazyCron) |

```php
// site/ready.php — generate missing AI content across the entire catalog
$wire->addHook('LazyCron::everyHour', function() {
    $ai = wire('modules')->get('AiWire');

    // Products without tasting notes
    $products = wire('pages')->find("template=product, tasting_notes='', limit=10");
    foreach ($products as $p) {
        $ai->askAndSave($p, 'tasting_notes',
            "Write professional tasting notes for {$p->title}. "
            . "Type: {$p->parent->title}. ABV: {$p->abv}%. "
            . "Cover: appearance, nose, palate, finish. 3-4 sentences.",
            ['maxTokens' => 250, 'temperature' => 0.4]
        );
    }

    // Brands without history
    $brands = wire('pages')->find("template=brand, ai_brand_history='', limit=5");
    foreach ($brands as $b) {
        $ai->askAndSave($b, 'ai_brand_history',
            "Write a 2-paragraph history of {$b->title} (alcohol brand). "
            . "Country: {$b->country->title}.",
            ['maxTokens' => 400, 'temperature' => 0.5]
        );
    }

    // Categories without descriptions
    $cats = wire('pages')->find("template=category, ai_description='', limit=5");
    foreach ($cats as $c) {
        $count = $c->children->count();
        $ai->askAndSave($c, 'ai_description',
            "Write a 2-paragraph description for the '{$c->title}' category page "
            . "of an online spirits store. We carry {$count} products.",
            ['maxTokens' => 300, 'temperature' => 0.6]
        );
    }
});
```

---

### 19. Image alt-text generator

> **Problem:** Product images need descriptive alt text for SEO and accessibility, but editors upload images without filling in the description.
> This hook generates alt text for every image that's missing one, using the cheapest/fastest model since the task is simple.

**ProcessWire setup:**

| Item | Details |
|------|---------|
| Template | `product` |
| Fields | `images` (Images field, uses built-in `description` property) |
| File | `site/ready.php` |

```php
$wire->addHookAfter('Pages::saved', function(HookEvent $event) {
    $page = $event->arguments(0);
    if ($page->template->name !== 'product') return;
    if (!$page->hasField('images')) return;

    $ai = $this->modules->get('AiWire');

    foreach ($page->images as $image) {
        if ($image->description) continue;

        $altText = $ai->chat(
            "Generate a descriptive alt text (max 125 chars) for a product image. "
            . "Product: {$page->title}\n"
            . "Category: {$page->parent->title}\n"
            . "Filename: {$image->basename}\n"
            . "Return ONLY the alt text.",
            [
                'maxTokens'   => 50,
                'temperature' => 0.3,
                'provider'    => 'google',
                'model'       => 'gemini-flash-lite-latest',
            ]
        );

        if ($altText) {
            $image->description = mb_substr($altText, 0, 125);
        }
    }

    $page->save('images', ['quiet' => true]);
});
```

---

### 20. Form submission analysis and routing

> **Problem:** Contact form submissions pile up in one inbox — sales inquiries, support tickets, and wholesale requests all go to the same person.
> AI classifies each message and routes it to the right department email with a priority level.

**ProcessWire setup:**

| Item | Details |
|------|---------|
| Dependencies | FormBuilder module (or any form handler), `wireMail()` configured |
| File | `site/ready.php` |

```php
$wire->addHookAfter('FormBuilder::processReady', function(HookEvent $event) {
    $form  = $event->arguments(0);
    $ai    = wire('modules')->get('AiWire');

    $message = $form->get('message')->value;
    $email   = $form->get('email')->value;

    $result = $ai->ask(
        "Analyze this customer inquiry for a wine and spirits store.\n"
        . "Reply ONLY with JSON:\n"
        . '{"department":"sales|support|wholesale|general",'
        . '"priority":"low|medium|high",'
        . '"summary":"one-sentence summary"}'
        . "\n\nMessage: {$message}",
        [
            'maxTokens'   => 100,
            'temperature' => 0,
            'provider'    => 'openai',
            'model'       => 'gpt-5-nano',
        ]
    );

    if ($result['success']) {
        $analysis = json_decode($result['content'], true);
        if ($analysis) {
            $emails = [
                'sales'     => 'sales@lqrs.com',
                'support'   => 'support@lqrs.com',
                'wholesale' => 'wholesale@lqrs.com',
                'general'   => 'info@lqrs.com',
            ];
            $to = $emails[$analysis['department']] ?? $emails['general'];

            $mail = wireMail();
            $mail->to($to);
            $mail->subject("[{$analysis['priority']}] {$analysis['summary']}");
            $mail->body("From: {$email}\n\n{$message}");
            $mail->send();
        }
    }
});
```

---

## Multiple Keys & Fallback

Add multiple API keys per provider in the admin panel. This enables load distribution, quota management, and automatic failover.

### Why use multiple keys?

- **Rate limit avoidance** — distribute requests across keys
- **Quota management** — when one key runs out, the next takes over
- **Team usage** — separate keys for different projects or environments
- **Redundancy** — if a provider has issues, fall back to another

### Use a specific key by index

```php
$ai = $modules->get('AiWire');

// Keys are 0-indexed in the order they appear in admin
$result = $ai->ask('Hello', [
    'provider' => 'anthropic',
    'keyIndex' => 1, // second key
]);
```

### Automatic fallback

```php
$ai = $modules->get('AiWire');

// Tries all Anthropic keys → all OpenAI keys → all Google keys
$result = $ai->askWithFallback('Summarize this document...', [
    'provider'          => 'anthropic',
    'fallbackProviders' => ['openai', 'google'],
]);

if ($result['success']) {
    echo $result['content'];
    echo "Answered by: {$result['usedProvider']}";     // e.g. 'openai'
    echo "Key index: {$result['usedKeyIndex']}";       // e.g. 0
    echo "Key label: {$result['usedKeyLabel']}";       // e.g. 'Production key'
}
```

---

## Admin Interface

The configuration page (`Modules → Configure → AiWire`) includes:

- **API Keys & Providers** — add, remove, enable/disable keys per provider with AJAX save
- **Connection Test** — one-click test button per key shows green ✅ / red ❌ status
- **Model Selection** — choose a default model for each key
- **Default Settings** — system prompt, temperature, max tokens, timeout
- **Logging** — enable standard and/or debug logging
- **Cache** — view stats (files, size, pages) and clear all cache with one click
- **Test Chat** — select a provider, key, model, type a message and get a response

---

## Cache

AiWire includes a file-based cache that stores AI responses to avoid repeated API calls. This is essential for page rendering — without cache, every page load would wait for an AI response.

Cache files are stored in `site/assets/cache/AiWire/` organized by page ID:

```
site/assets/cache/AiWire/
├── 0/              # global (no page context)
│   └── a1b2c3.json
├── 1042/           # page ID 1042
│   └── f7a8b9.json
└── 1085/           # page ID 1085
    └── c0d1e2.json
```

### TTL formats

| Value   | Duration |
|---------|----------|
| `'D'`   | 1 day    |
| `'W'`   | 1 week   |
| `'M'`   | 1 month (30 days) |
| `'Y'`   | 1 year   |
| `'2D'`  | 2 days   |
| `'3W'`  | 3 weeks  |
| `'6M'`  | 6 months |
| `3600`  | 3600 seconds (1 hour) |

### Basic usage

```php
$ai = $modules->get('AiWire');

// Cache for 1 week — identical request returns instantly from cache
$result = $ai->ask('Write a tagline for our bakery', [
    'cache' => 'W',
]);

echo $result['content'];   // AI response
echo $result['cached'];    // true if served from cache, false if fresh
```

### Cache with page context

When used in page templates, pass `pageId` so each page gets its own cache:

```php
// site/templates/article.php
$ai = $modules->get('AiWire');

$summary = $ai->ask("Summarize this article in 2 sentences:\n\n" . $page->body, [
    'cache'       => 'M',        // cache for 1 month
    'pageId'      => $page,       // or $page->id — both work
    'maxTokens'   => 200,
    'temperature' => 0.3,
]);

if ($summary['success']) {
    echo "<div class='ai-summary'>{$summary['content']}</div>";
}
```

First visit: AI processes the text (~2-3 seconds). Every subsequent visit for the next month: instant from cache.

### Cache with chat() shortcut

```php
$meta = $ai->chat("Write SEO meta description for: {$page->title}", [
    'cache'  => 'W',
    'pageId' => $page,
]);
```

### Clear cache on page save

```php
// site/ready.php
$wire->addHookAfter('Pages::saved', function(HookEvent $event) {
    $page = $event->arguments(0);
    if (!$page->isChanged('body')) return;

    $ai = $this->modules->get('AiWire');
    $cleared = $ai->clearCache($page);

    if ($cleared) {
        $this->message("Cleared {$cleared} AI cache files for this page");
    }
});
```

### AI suggestions on every page (cached)

```php
// site/templates/_main.php
$ai = $modules->get('AiWire');

$suggestions = $ai->ask(
    "Suggest 3 related topics for this page. Reply as JSON array of strings.\n\n"
    . "Title: {$page->title}\n"
    . "Content: " . mb_substr(strip_tags($page->body), 0, 1000),
    [
        'cache'       => 'W',
        'pageId'      => $page,
        'maxTokens'   => 150,
        'temperature' => 0.5,
        'provider'    => 'google',
        'model'       => 'gemini-flash-lite-latest',
    ]
);

if ($suggestions['success']) {
    $topics = json_decode($suggestions['content'], true);
    if ($topics) {
        echo "<aside><h4>You might also like</h4><ul>";
        foreach ($topics as $topic) {
            echo "<li>" . htmlspecialchars($topic) . "</li>";
        }
        echo "</ul></aside>";
    }
}
```

### Cache management

```php
$ai = $modules->get('AiWire');

$ai->clearCache($page);       // clear cache for one page
$ai->clearAllCache();          // clear everything

$stats = $ai->cacheStats();
// ['total_files' => 42, 'total_size' => 128400, 'pages' => 12, 'expired' => 3]
```

### Automatic cleanup

Expired cache files are cleaned up automatically once per day via ProcessWire's LazyCron. You can also clear all cache from admin at `Modules → Configure → AiWire → Cache`.

---

## Field Storage

AiWire can save AI responses directly into page fields. Unlike cache (temporary files), field storage is permanent — content survives cache expiry, is editable by users in the admin, and is searchable via PW selectors.

**Cache vs Field**: use cache for repeated runtime calls (rendering). Use field storage when AI content becomes part of the page data (SEO descriptions, summaries, translations).

### `saveTo(Page $page, string $fieldName, string|array $content, bool $quiet = true): bool`

Save content to a page field. Accepts a string or a full `ask()` result array.

```php
$ai = $modules->get('AiWire');

// Save a string
$ai->saveTo($page, 'ai_summary', 'This is a great article about cats.');

// Save directly from ask() result
$result = $ai->ask("Summarize: {$page->body}");
$ai->saveTo($page, 'ai_summary', $result);
```

### `loadFrom(Page $page, string $fieldName): ?string`

Load content from a page field. Returns `null` if the field is empty.

```php
$summary = $ai->loadFrom($page, 'ai_summary');
if ($summary) {
    echo $summary; // already generated
}
```

### `askAndSave(Page $page, string $fieldName, string $message, array $options = []): array`

The main convenience method. Checks the field first — if content exists, returns it instantly. If empty, calls AI, saves the result to the field, and returns it.

```php
$ai = $modules->get('AiWire');

$result = $ai->askAndSave($page, 'ai_summary',
    "Write a 2-sentence summary of this article:\n\n" . $page->body,
    [
        'maxTokens'   => 200,
        'temperature' => 0.3,
    ]
);

echo $result['content'];  // AI-generated or from field
echo $result['source'];   // 'field' or 'ai'
```

**Extra options:**

| Option      | Type | Default | Description |
|-------------|------|---------|-------------|
| `overwrite` | bool | false   | If true, always call AI even if the field has content |
| `quiet`     | bool | true    | Save without triggering PW hooks |

### Auto-generate SEO on page save

```php
// site/ready.php
$wire->addHookAfter('Pages::saved', function(HookEvent $event) {
    $page = $event->arguments(0);
    if ($page->template->name !== 'article') return;
    if (!$page->isChanged('body')) return;

    $ai = $this->modules->get('AiWire');

    // Always regenerate when body changes
    $ai->askAndSave($page, 'seo_description',
        "Write an SEO meta description (max 160 chars) for:\n\n{$page->title}\n{$page->body}",
        [
            'overwrite'   => true,  // body changed, regenerate
            'maxTokens'   => 100,
            'temperature' => 0.3,
        ]
    );
});
```

### Same prompt → multiple fields

```php
// One AI call, result saved to both fields
$ai->askAndSave($page, ['seo_description', 'og_description'],
    "Write a compelling description (max 160 chars) for:\n{$page->title}",
    ['maxTokens' => 100]
);
```

### Batch: each field gets its own prompt

```php
// site/ready.php — generate all AI content for a page at once
$wire->addHookAfter('Pages::saved', function(HookEvent $event) {
    $page = $event->arguments(0);
    if ($page->template->name !== 'article') return;
    if (!$page->isChanged('body')) return;

    $ai = $this->modules->get('AiWire');
    $body = mb_substr(strip_tags($page->body), 0, 2000);

    $results = $ai->askAndSave($page, [
        'seo_description' => "Write SEO meta description (max 160 chars) for:\n{$page->title}\n{$body}",
        'ai_summary'      => "Summarize this article in 3 sentences:\n{$body}",
        'ai_keywords'     => "Extract 5 SEO keywords as comma-separated list:\n{$body}",
    ], null, [
        'overwrite'   => true,
        'maxTokens'   => 200,
        'temperature' => 0.3,
    ]);

    // $results['seo_description']['source'] => 'ai'
    // $results['ai_summary']['source'] => 'ai'
    // $results['ai_keywords']['source'] => 'ai'
});
```

### Lazy generation in templates

```php
// site/templates/article.php
$ai = $modules->get('AiWire');

// First visit: AI generates and saves. Every visit after: instant from field.
$result = $ai->askAndSave($page, 'ai_summary',
    "Summarize in 3 sentences:\n\n" . $page->body,
    ['maxTokens' => 200, 'cache' => 'W']  // cache also active as double layer
);

echo "<div class='summary'>{$result['content']}</div>";
```

### Bulk generation with LazyCron

```php
// site/ready.php
$wire->addHook('LazyCron::everyHour', function() {
    $ai = wire('modules')->get('AiWire');
    $pages = wire('pages')->find("template=product, ai_description=''");

    foreach ($pages as $p) {
        $ai->askAndSave($p, [
            'ai_description' => "Write a product description for: {$p->title}\nFeatures: {$p->features}",
            'ai_keywords'    => "Extract 5 keywords for: {$p->title}",
        ], null, ['maxTokens' => 300, 'temperature' => 0.7]);
    }
});
```

### `generate()` — product page with multiple AI blocks

For pages where each AI block needs its own prompt, provider, or settings:

```php
// site/templates/product.php — e.g. "2015 Louis Roederer Cristal"
$ai = $modules->get('AiWire');

$results = $ai->generate($page, [
    [
        'field'  => 'ai_overview',
        'prompt' => "Write a detailed overview of {$page->title}. "
                  . "Include flavor profile, food pairings, and ideal serving temperature. "
                  . "Vintage: {$page->vintage}. Region: {$page->region}.",
        'options' => ['maxTokens' => 600, 'temperature' => 0.6],
    ],
    [
        'field'       => 'ai_brand_facts',
        'prompt'      => "Share 3 interesting facts about {$page->brand->title} "
                       . "that most people don't know. Be engaging and surprising.",
        'systemPrompt' => 'You are a wine historian. Write in a friendly, conversational tone.',
        'options'      => ['maxTokens' => 400],
    ],
    [
        'field'  => 'ai_review_summary',
        'prompt' => "Summarize these customer reviews in 3 sentences. "
                  . "Mention common praise and any complaints:\n\n"
                  . $page->reviews->implode("\n---\n", 'body'),
        'options' => ['temperature' => 0.3, 'maxTokens' => 300],
    ],
    [
        'field'  => 'ai_food_pairing',
        'prompt' => "Suggest 5 specific food pairings for {$page->title}. "
                  . "Format as a simple list.",
        'options' => ['provider' => 'google', 'model' => 'gemini-flash-lite-latest'],
    ],
], [
    // Global options — apply to all blocks unless overridden
    'cache'       => 'M',
    'temperature' => 0.7,
]);

// Use in template
if ($results['ai_overview']['success']) {
    echo "<section class='ai-overview'>{$results['ai_overview']['content']}</section>";
}
if ($results['ai_brand_facts']['success']) {
    echo "<aside class='did-you-know'>";
    echo "<h3>Did you know?</h3>";
    echo $results['ai_brand_facts']['content'];
    echo "</aside>";
}
```

Each block checks its field first — if content exists, returns instantly from the field without calling AI. Blocks can use different providers (e.g. cheap model for simple tasks, powerful model for detailed analysis).

**Block structure:**

| Key            | Type   | Required | Description |
|----------------|--------|----------|-------------|
| `field`        | string | ✅       | Page field to save to |
| `prompt`       | string | ✅       | AI prompt for this block |
| `options`      | array  | —        | Per-block overrides (provider, model, maxTokens, temperature, cache) |
| `systemPrompt` | string | —        | Shortcut for `options['systemPrompt']` |
| `overwrite`    | bool   | —        | Per-block overwrite (overrides global) |

### Regenerate on page save

```php
// site/ready.php — regenerate all AI blocks when product data changes
$wire->addHookAfter('Pages::saved', function(HookEvent $event) {
    $page = $event->arguments(0);
    if ($page->template->name !== 'product') return;
    if (!$page->isChanged()) return;

    $ai = $this->modules->get('AiWire');

    $ai->generate($page, [
        ['field' => 'ai_overview',       'prompt' => "Write overview for: {$page->title}..."],
        ['field' => 'ai_brand_facts',    'prompt' => "Facts about {$page->brand->title}..."],
        ['field' => 'ai_review_summary', 'prompt' => "Summarize reviews..."],
    ], ['overwrite' => true, 'temperature' => 0.5]);
});
```

### When to use what

| Method | Use case |
|--------|----------|
| `ask()` | One-off AI calls, no persistence needed |
| `ask()` + `cache` | Runtime rendering, temporary storage |
| `askAndSave()` | Single field, simple prompt, permanent storage |
| `generate()` | Product pages, articles — multiple AI blocks with individual settings |

---

## Logging

AiWire writes to ProcessWire's log system. View logs at **Setup → Logs**.

| Log file | Content |
|----------|---------|
| `aiwire` | Successful responses with provider/model/token info |
| `aiwire-errors` | Failed requests, API errors |
| `aiwire-debug` | Detailed request/response data (when debug enabled) |

Enable debug logging in module config for troubleshooting. Disable it in production.

---

## Tips & Best Practices

- **Temperature 0** for classification, moderation, data extraction — deterministic output
- **Temperature 0.3–0.5** for summaries, translations, factual responses
- **Temperature 0.7–1.0** for creative writing, brainstorming, descriptions
- Use **`gpt-5-nano`** or **`gemini-flash-lite-latest`** for high-volume, low-cost tasks
- Use **`askWithFallback()`** in production to ensure uptime
- Set **maxTokens** as low as practical — saves money and speeds up responses
- Keep **system prompts** focused — shorter prompts mean lower token costs
- Use **conversation history** sparingly — each message adds to input token count
- Cache AI responses when possible (save to a page field, use LazyCron)

---

## License

MIT — free for personal and commercial use.

## Author

**Maxim Alex** — [smnv.org](https://smnv.org) — maxim@smnv.org

Built for the [ProcessWire](https://processwire.com/) community.