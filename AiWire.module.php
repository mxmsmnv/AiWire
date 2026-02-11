<?php namespace ProcessWire;

/**
 * AiWire - AI Integration Module for ProcessWire
 *
 * Connect your ProcessWire site to AI providers: Anthropic, OpenAI, Google, xAI, OpenRouter.
 * Manage multiple API keys, test connections, and use AI in your templates.
 *
 * @author Maxim Alex
 * @license MIT
 * @version 1.0.0
 * @see https://github.com/mxmsmnv/AiWire
 */

require_once(__DIR__ . '/AiWireProvider.php');
require_once(__DIR__ . '/AiWireCache.php');

class AiWire extends WireData implements Module, ConfigurableModule {

    /**
     * Module information
     */
    public static function getModuleInfo() {
        return [
            'title'    => 'AiWire',
            'version'  => '1.0.0',
            'summary'  => __('AI integration for ProcessWire. Supports Anthropic, OpenAI, Google, xAI, and OpenRouter.'),
            'author'   => 'Maxim Alex',
            'icon'     => 'brain',
            'singular' => true,
            'autoload' => true,
            'requires' => ['ProcessWire>=3.0.210', 'PHP>=8.1'],
        ];
    }

    /**
     * Supported providers configuration
     */
    const PROVIDERS = [
        'anthropic' => [
            'label'       => 'Anthropic (Claude)',
            'icon'        => 'comment',
            'url'         => 'https://api.anthropic.com/v1/messages',
            'testUrl'     => 'https://api.anthropic.com/v1/messages',
            'docsUrl'     => 'https://docs.anthropic.com/',
            'keyPrefix'   => 'sk-ant-',
            'headerType'  => 'x-api-key',
            'extraHeaders' => [
                'anthropic-version' => '2023-06-01',
            ],
            'defaultModel' => 'claude-sonnet-4-5-20250929',
            'models' => [
                'claude-opus-4-6'            => 'Claude Opus 4.6',
                'claude-sonnet-4-5-20250929' => 'Claude Sonnet 4.5',
                'claude-haiku-4-5-20251001'  => 'Claude Haiku 4.5',
            ],
        ],
        'openai' => [
            'label'       => 'OpenAI (GPT)',
            'icon'        => 'bolt',
            'url'         => 'https://api.openai.com/v1/chat/completions',
            'testUrl'     => 'https://api.openai.com/v1/chat/completions',
            'docsUrl'     => 'https://platform.openai.com/docs/',
            'keyPrefix'   => 'sk-',
            'headerType'  => 'bearer',
            'extraHeaders' => [],
            'defaultModel' => 'gpt-4.1',
            'models' => [
                'gpt-5.2'     => 'GPT-5.2',
                'gpt-5-mini'  => 'GPT-5 Mini',
                'gpt-5-nano'  => 'GPT-5 Nano',
                'gpt-4.1'     => 'GPT-4.1',
            ],
        ],
        'google' => [
            'label'       => 'Google (Gemini)',
            'icon'        => 'google',
            'url'         => 'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions',
            'testUrl'     => 'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions',
            'docsUrl'     => 'https://ai.google.dev/docs',
            'keyPrefix'   => '',
            'headerType'  => 'bearer',
            'extraHeaders' => [],
            'defaultModel' => 'gemini-flash-latest',
            'models' => [
                'gemini-3-pro-preview'  => 'Gemini 3 Pro Preview',
                'gemini-flash-latest'   => 'Gemini Flash',
                'gemini-flash-lite-latest' => 'Gemini Flash Lite',
            ],
        ],
        'xai' => [
            'label'       => 'xAI (Grok)',
            'icon'        => 'rocket',
            'url'         => 'https://api.x.ai/v1/chat/completions',
            'testUrl'     => 'https://api.x.ai/v1/chat/completions',
            'docsUrl'     => 'https://docs.x.ai/',
            'keyPrefix'   => 'xai-',
            'headerType'  => 'bearer',
            'extraHeaders' => [],
            'defaultModel' => 'grok-4-1-fast-non-reasoning',
            'models' => [
                'grok-4-1-fast-reasoning'     => 'Grok 4.1 Fast (Reasoning)',
                'grok-4-1-fast-non-reasoning' => 'Grok 4.1 Fast',
                'grok-3-mini'                 => 'Grok 3 Mini',
            ],
        ],
        'openrouter' => [
            'label'       => 'OpenRouter',
            'icon'        => 'exchange',
            'url'         => 'https://openrouter.ai/api/v1/chat/completions',
            'testUrl'     => 'https://openrouter.ai/api/v1/chat/completions',
            'docsUrl'     => 'https://openrouter.ai/docs',
            'keyPrefix'   => 'sk-or-',
            'headerType'  => 'bearer',
            'extraHeaders' => [
                'HTTP-Referer' => '',
            ],
            'defaultModel' => 'deepseek/deepseek-v3.2',
            'models' => [
                'deepseek/deepseek-v3.2'                    => 'DeepSeek V3.2',
                'qwen/qwen3-max-thinking'                   => 'Qwen 3 Max Thinking',
                'google/gemini-3-flash-preview'             => 'Gemini 3 Flash Preview',
                'google/gemini-2.5-flash'                   => 'Gemini 2.5 Flash',
                'minimax/minimax-m2.1'                      => 'MiniMax M2.1',
                'z-ai/glm-4.7'                              => 'GLM 4.7',
                'mistralai/devstral-2512'                   => 'Devstral 2512',
                'mistralai/mistral-small-3.2-24b-instruct'  => 'Mistral Small 3.2 24B',
                'meta-llama/llama-4-maverick'               => 'Llama 4 Maverick',
                'nvidia/nemotron-3-nano-30b-a3b'            => 'Nemotron 3 Nano 30B',
                'meta-llama/llama-3.3-70b-instruct'         => 'Llama 3.3 70B',
                'openai/gpt-5.2'                            => 'GPT-5.2 (via OR)',
                'anthropic/claude-sonnet-4.5'               => 'Claude Sonnet 4.5 (via OR)',
                'x-ai/grok-4-1-fast'                        => 'Grok 4.1 Fast (via OR)',
            ],
        ],
    ];

    /**
     * Default configuration
     */
    protected static $defaultConfig = [
        'providers'          => '{}', // JSON: {provider: [{key, label, model, enabled, status}]}
        'defaultProvider'    => 'anthropic',
        'defaultKeyIndex'    => '',
        'defaultModel'       => '',
        'systemPrompt'       => '',
        'maxTokens'          => 1024,
        'temperature'        => 0.7,
        'timeout'            => 30,
        'enableCache'        => false,
        'defaultCacheTtl'    => 'D',
        'enableLogging'      => true,
        'enableDebugLogging' => false,
        'logName'            => 'aiwire',
    ];

    /** @var AiWireProvider[] cached provider instances */
    protected $providerInstances = [];

    /** @var AiWireCache cache instance */
    protected $cache = null;

    /**
     * Initialize the module
     */
    public function init() {
        foreach (self::$defaultConfig as $key => $value) {
            if ($this->$key === null) $this->set($key, $value);
        }

        // Initialize cache
        $this->cache = new AiWireCache(null, !empty($this->enableDebugLogging));
    }

    /**
     * Ready - handle AJAX requests and schedule cache cleanup
     */
    public function ready() {
        if ($this->wire('config')->ajax && $this->wire('input')->post('aiwire_action')) {
            $this->handleAjaxRequest();
        }

        // Auto-clean expired cache once per day via LazyCron
        $this->addHook('LazyCron::everyDay', $this, 'hookCleanExpiredCache');
    }

    /**
     * LazyCron hook: clean expired cache files
     */
    public function hookCleanExpiredCache(HookEvent $event) {
        $count = $this->cache->cleanExpired();
        if ($count) {
            $this->log("Cache cleanup: removed {$count} expired files");
        }
    }

    // =========================================================================
    // PUBLIC API
    // =========================================================================

    /**
     * Send a message to AI and get a response
     *
     * Cache priority:
     *   - 'cache' explicitly in $options → that value wins (TTL string/int = ON, false = OFF)
     *   - 'cache' NOT in $options → global enableCache setting applies
     *   Global OFF + code 'W' = cached. Global ON + code false = not cached.
     *
     * @param string $message User message
     * @param array $options Optional overrides:
     *   provider, model, systemPrompt, maxTokens, temperature, history, key, keyIndex
     *   cache: true|'D'|'W'|'M'|'Y'|'2D'|'3W'|int(seconds)|false — cache TTL
     *   pageId: int|Page — page context for cache (0 = global)
     * @return array ['success', 'content', 'usage', 'raw', 'cached']
     */
    public function ask(string $message, array $options = []): array {
        // ── Resolve cache: code-level override > global default ──
        if (array_key_exists('cache', $options)) {
            $cacheOption = $options['cache'];
        } elseif ($this->enableCache) {
            $cacheOption = $this->defaultCacheTtl ?: 'D';
        } else {
            $cacheOption = false;
        }

        // true → use default TTL
        if ($cacheOption === true) {
            $cacheOption = $this->defaultCacheTtl ?: 'D';
        }

        $useCache = ($cacheOption !== false && $cacheOption !== null);

        // ── Resolve page ID ──
        $pageId = $options['pageId'] ?? ($options['page'] ?? null);
        if ($pageId instanceof Page) {
            $pageId = $pageId->id;
        }
        $pageId = $pageId ? (int)$pageId : 0;

        // ── Check cache ──
        if ($useCache && $this->cache) {
            $cached = $this->cache->get($message, $options, $pageId);
            if ($cached !== null) {
                $cached['cached'] = true;
                $this->debugLog("ask() cache hit, page={$pageId}");
                return $cached;
            }
        }

        // ── Send request ──
        $providerKey = $options['provider'] ?? $this->getDefaultProviderKey();
        $provider = $this->getProvider($providerKey, $options['key'] ?? null, $options['keyIndex'] ?? null);

        if (!$provider) {
            return $this->errorResponse("No active provider found for '{$providerKey}'");
        }

        $model       = $options['model'] ?? $provider->getModel();
        $systemPrompt = $options['systemPrompt'] ?? $this->systemPrompt;
        $maxTokens   = (int)($options['maxTokens'] ?? $this->maxTokens);
        $temperature = (float)($options['temperature'] ?? $this->temperature);
        $timeout     = isset($options['timeout']) ? (int)$options['timeout'] : null;
        $history     = $options['history'] ?? [];

        // Apply per-request timeout if specified
        if ($timeout) {
            $provider->setTimeout($timeout);
        }

        $this->debugLog("ask() provider={$providerKey} model={$model} cache=" . ($useCache ? $cacheOption : 'off'));

        try {
            $result = $provider->sendMessage($message, [
                'model'        => $model,
                'systemPrompt' => $systemPrompt,
                'maxTokens'    => $maxTokens,
                'temperature'  => $temperature,
                'history'      => $history,
            ]);

            if ($result['success']) {
                $this->log("Response from {$providerKey}/{$model} — " .
                    ($result['usage']['total_tokens'] ?? '?') . " tokens");

                // Save to cache
                if ($useCache && $this->cache) {
                    $this->cache->set($message, $options, $result, $cacheOption, $pageId);
                }
            }

            $result['cached'] = false;
            return $result;

        } catch (\Throwable $e) {
            $this->logError("ask() error: " . $e->getMessage());
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Send a message with automatic fallback through all enabled keys
     *
     * If the first key fails (rate limit, quota, error), tries the next enabled key.
     * Optionally falls back to other providers.
     *
     * @param string $message
     * @param array $options Same as ask(), plus 'fallbackProviders' => ['openai', 'xai']
     * @return array Same as ask(), with extra 'usedProvider' and 'usedKeyIndex'
     */
    public function askWithFallback(string $message, array $options = []): array {
        $providerKey = $options['provider'] ?? $this->getDefaultProviderKey();
        $fallbackProviders = $options['fallbackProviders'] ?? [];

        // Try all keys for the primary provider
        $result = $this->tryAllKeys($providerKey, $message, $options);
        if ($result['success']) return $result;

        // Try fallback providers
        foreach ($fallbackProviders as $fbProvider) {
            if ($fbProvider === $providerKey) continue;
            $this->debugLog("Falling back to provider: {$fbProvider}");
            $fbOptions = $options;
            $fbOptions['provider'] = $fbProvider;
            unset($fbOptions['key'], $fbOptions['keyIndex'], $fbOptions['model']);

            $result = $this->tryAllKeys($fbProvider, $message, $fbOptions);
            if ($result['success']) return $result;
        }

        return $this->errorResponse("All keys and fallback providers failed for message");
    }

    /**
     * Try all enabled keys for a provider
     *
     * @param string $providerKey
     * @param string $message
     * @param array $options
     * @return array
     */
    protected function tryAllKeys(string $providerKey, string $message, array $options): array {
        $keys = $this->getProviderKeys($providerKey);
        $lastError = '';

        foreach ($keys as $i => $keyData) {
            if (empty($keyData['enabled']) || empty($keyData['key'])) continue;

            $this->debugLog("Trying {$providerKey} key #{$i}" . ($keyData['label'] ? " ({$keyData['label']})" : ''));

            $keyOptions = $options;
            $keyOptions['provider'] = $providerKey;
            $keyOptions['key'] = $keyData['key'];
            if (!isset($keyOptions['model'])) {
                $keyOptions['model'] = $keyData['model'] ?? null;
            }

            $result = $this->ask($message, $keyOptions);

            if ($result['success']) {
                $result['usedProvider'] = $providerKey;
                $result['usedKeyIndex'] = $i;
                $result['usedKeyLabel'] = $keyData['label'] ?? '';
                return $result;
            }

            $lastError = $result['message'] ?? 'Unknown error';
            $this->debugLog("Key #{$i} failed: {$lastError}");
        }

        return $this->errorResponse("All keys failed for {$providerKey}: {$lastError}");
    }

    /**
     * Get responses from multiple providers in parallel (sequential, but tries all)
     *
     * @param string $message
     * @param array $providers List of provider keys ['anthropic', 'openai', 'xai']
     * @param array $options Shared options
     * @return array ['provider_key' => result, ...]
     */
    public function askMultiple(string $message, array $providers, array $options = []): array {
        $results = [];
        foreach ($providers as $pk) {
            $opts = array_merge($options, ['provider' => $pk]);
            $results[$pk] = $this->ask($message, $opts);
        }
        return $results;
    }

    /**
     * Quick shortcut: ask and return just the text
     *
     * @param string $message
     * @param array $options
     * @return string
     */
    public function chat(string $message, array $options = []): string {
        $result = $this->ask($message, $options);
        return $result['success'] ? $result['content'] : '';
    }

    /**
     * Get a provider instance
     *
     * @param string $providerKey
     * @param string|null $specificKey Use a specific API key string
     * @param int|null $keyIndex Use a specific key by index
     * @return AiWireProvider|null
     */
    public function getProvider(string $providerKey, ?string $specificKey = null, ?int $keyIndex = null): ?AiWireProvider {
        if (!isset(self::PROVIDERS[$providerKey])) return null;

        $config = self::PROVIDERS[$providerKey];
        $keys = $this->getProviderKeys($providerKey);

        if ($specificKey) {
            $apiKey = $specificKey;
            $model  = $config['defaultModel'];
        } elseif ($keyIndex !== null && isset($keys[$keyIndex])) {
            $apiKey = $keys[$keyIndex]['key'] ?? '';
            $model  = $keys[$keyIndex]['model'] ?? $config['defaultModel'];
            if (!$apiKey) return null;
        } else {
            // Use default key index if set for this provider
            $defaultIdx = $this->defaultKeyIndex;
            if ($defaultIdx !== '' && $defaultIdx !== null && $providerKey === ($this->defaultProvider ?: 'anthropic')) {
                $idx = (int)$defaultIdx;
                if (isset($keys[$idx]) && !empty($keys[$idx]['enabled']) && !empty($keys[$idx]['key'])) {
                    $apiKey = $keys[$idx]['key'];
                    $model  = $keys[$idx]['model'] ?? $config['defaultModel'];
                } else {
                    // Fallback to first enabled
                    $defaultIdx = '';
                }
            }

            // Find first enabled key (fallback)
            if ($defaultIdx === '' || $defaultIdx === null) {
                $activeKey = null;
                foreach ($keys as $k) {
                    if (!empty($k['enabled'])) {
                        $activeKey = $k;
                        break;
                    }
                }
                if (!$activeKey) return null;
                $apiKey = $activeKey['key'];
                $model  = $activeKey['model'] ?? $config['defaultModel'];
            }
        }

        $cacheKey = $providerKey . ':' . md5($apiKey);
        if (!isset($this->providerInstances[$cacheKey])) {
            $this->providerInstances[$cacheKey] = new AiWireProvider($providerKey, $config, $apiKey, $model, [
                'timeout' => (int)$this->timeout,
            ]);
        }

        return $this->providerInstances[$cacheKey];
    }

    /**
     * Get all configured keys for a provider
     *
     * @param string $providerKey
     * @return array
     */
    public function getProviderKeys(string $providerKey): array {
        $providers = json_decode($this->providers ?: '{}', true) ?: [];
        return $providers[$providerKey] ?? [];
    }

    /**
     * Get the default provider key name
     *
     * @return string
     */
    public function getDefaultProviderKey(): string {
        return $this->defaultProvider ?: 'anthropic';
    }

    /**
     * Get list of all providers with their status
     *
     * @return array
     */
    public function getProvidersStatus(): array {
        $result = [];
        foreach (self::PROVIDERS as $key => $config) {
            $keys = $this->getProviderKeys($key);
            $hasActive = false;
            foreach ($keys as $k) {
                if (!empty($k['enabled'])) { $hasActive = true; break; }
            }
            $result[$key] = [
                'label'    => $config['label'],
                'active'   => $hasActive,
                'keyCount' => count($keys),
            ];
        }
        return $result;
    }

    // =========================================================================
    // CACHE MANAGEMENT
    // =========================================================================

    /**
     * Get the cache instance for direct access
     *
     * @return AiWireCache
     */
    public function cache(): AiWireCache {
        return $this->cache;
    }

    /**
     * Clear cache for a specific page
     *
     * @param int|Page $page Page ID or Page object
     * @return int Number of files deleted
     */
    public function clearCache(int|Page $page = 0): int {
        $pageId = ($page instanceof Page) ? $page->id : (int)$page;
        $count = $this->cache->clearPage($pageId);
        if ($count) $this->log("Cache cleared for page {$pageId}: {$count} files");
        return $count;
    }

    /**
     * Clear ALL AiWire cache
     *
     * @return int Number of files deleted
     */
    public function clearAllCache(): int {
        $count = $this->cache->clearAll();
        if ($count) $this->log("All cache cleared: {$count} files");
        return $count;
    }

    /**
     * Get cache statistics
     *
     * @return array ['total_files', 'total_size', 'pages', 'expired']
     */
    public function cacheStats(): array {
        return $this->cache->getStats();
    }

    // =========================================================================
    // FIELD STORAGE
    // =========================================================================

    /**
     * Save AI result to a page field
     *
     * Stores the AI response content directly into a page text field.
     * Useful for persisting AI-generated content (SEO descriptions, summaries, translations)
     * that should survive cache expiry and be editable by editors.
     *
     * @param Page $page The page to save to
     * @param string $fieldName Field name (must be text, textarea, or CKEditor)
     * @param string|array $content String content or full ask() result array
     * @param bool $quiet Save without triggering hooks (default: true)
     * @return bool
     */
    public function saveTo(Page $page, string $fieldName, string|array $content, bool $quiet = true): bool {
        if (!$page->id) {
            $this->logError("saveTo: page has no ID");
            return false;
        }

        if (!$page->template->hasField($fieldName)) {
            $this->logError("saveTo: field '{$fieldName}' not found on template '{$page->template->name}'");
            return false;
        }

        // Extract content from result array
        $text = is_array($content) ? ($content['content'] ?? '') : $content;

        if ($text === '') {
            $this->debugLog("saveTo: empty content, skipping");
            return false;
        }

        $page->of(false);
        $page->set($fieldName, $text);

        if ($quiet) {
            $page->save($fieldName, ['quiet' => true]);
        } else {
            $page->save($fieldName);
        }

        $this->debugLog("saveTo: saved " . mb_strlen($text) . " chars to {$page->id}->{$fieldName}");
        return true;
    }

    /**
     * Load AI content from a page field
     *
     * Returns the field value if not empty, or null if the field is empty.
     * Use this to check if AI content already exists before calling ask().
     *
     * @param Page $page
     * @param string $fieldName
     * @return string|null Field content or null if empty
     */
    public function loadFrom(Page $page, string $fieldName): ?string {
        if (!$page->id || !$page->template->hasField($fieldName)) {
            return null;
        }

        $value = $page->getFormatted($fieldName);
        return ($value !== null && $value !== '') ? (string)$value : null;
    }

    /**
     * Ask AI and save the result to a page field
     *
     * Supports single field or multiple fields with different prompts.
     *
     * Single field:
     *   askAndSave($page, 'seo_desc', 'Write SEO for: ...')
     *
     * Multiple fields (same prompt, same result saved to all):
     *   askAndSave($page, ['seo_desc', 'og_description'], 'Write SEO for: ...')
     *
     * Multiple fields with different prompts (batch):
     *   askAndSave($page, [
     *       'seo_desc'    => 'Write SEO description for: ...',
     *       'ai_summary'  => 'Summarize this article: ...',
     *       'ai_keywords' => 'Extract 5 keywords from: ...',
     *   ])
     *
     * @param Page $page
     * @param string|array $fields Field name, array of field names, or [field => prompt] map
     * @param string|null $message Message (required for single/multi field, omit for batch)
     * @param array $options Same as ask(), plus:
     *   overwrite: bool (false) — always call AI even if field has content
     *   quiet: bool (true) — save without triggering hooks
     * @return array Single: same as ask() + 'source'. Batch: [field => result, ...]
     */
    public function askAndSave(Page $page, string|array $fields, ?string $message = null, array $options = []): array {
        $overwrite = $options['overwrite'] ?? false;
        $quiet = $options['quiet'] ?? true;

        // Set page context for cache
        if (!isset($options['pageId']) && !isset($options['page'])) {
            $options['pageId'] = $page->id;
        }

        // ── Case 1: Single field ──
        if (is_string($fields)) {
            return $this->_askAndSaveOne($page, $fields, $message ?? '', $options, $overwrite, $quiet);
        }

        // ── Case 2: Array of field names (same prompt → all fields) ──
        if (array_is_list($fields)) {
            $result = null;
            $results = [];

            foreach ($fields as $fieldName) {
                if (!$overwrite) {
                    $existing = $this->loadFrom($page, $fieldName);
                    if ($existing !== null) {
                        $results[$fieldName] = [
                            'success' => true,
                            'content' => $existing,
                            'usage'   => [],
                            'raw'     => [],
                            'cached'  => false,
                            'source'  => 'field',
                        ];
                        continue;
                    }
                }

                // Call AI once, reuse result for all empty fields
                if ($result === null) {
                    $result = $this->ask($message ?? '', $options);
                }

                if ($result['success']) {
                    $this->saveTo($page, $fieldName, $result, $quiet);
                    $results[$fieldName] = array_merge($result, ['source' => 'ai']);
                } else {
                    $results[$fieldName] = $result;
                }
            }

            return $results;
        }

        // ── Case 3: Associative array [field => prompt] (batch, each field gets its own prompt) ──
        $results = [];
        foreach ($fields as $fieldName => $prompt) {
            $results[$fieldName] = $this->_askAndSaveOne($page, $fieldName, $prompt, $options, $overwrite, $quiet);
        }
        return $results;
    }

    /**
     * Internal: ask and save for a single field
     */
    protected function _askAndSaveOne(Page $page, string $fieldName, string $message, array $options, bool $overwrite, bool $quiet): array {
        // Check field first
        if (!$overwrite) {
            $existing = $this->loadFrom($page, $fieldName);
            if ($existing !== null) {
                $this->debugLog("askAndSave: '{$fieldName}' has content, returning from field");
                return [
                    'success' => true,
                    'content' => $existing,
                    'usage'   => [],
                    'raw'     => [],
                    'cached'  => false,
                    'source'  => 'field',
                ];
            }
        }

        $result = $this->ask($message, $options);

        if ($result['success']) {
            $this->saveTo($page, $fieldName, $result, $quiet);
            $result['source'] = 'ai';
        }

        return $result;
    }

    /**
     * Generate multiple AI content blocks for a page
     *
     * Each block has its own prompt, field, and optional per-block settings
     * (provider, model, temperature, maxTokens, systemPrompt, cache).
     * Global options apply to all blocks unless overridden per block.
     *
     * Example — wine product page:
     *
     *   $ai->generate($page, [
     *       [
     *           'field'   => 'ai_overview',
     *           'prompt'  => "Write a detailed overview of {$page->title}...",
     *           'options' => ['maxTokens' => 500, 'temperature' => 0.5],
     *       ],
     *       [
     *           'field'   => 'ai_brand_facts',
     *           'prompt'  => "Share 3 interesting facts about the brand...",
     *           'options' => ['provider' => 'openai', 'model' => 'gpt-5-nano'],
     *       ],
     *       [
     *           'field'        => 'ai_review_summary',
     *           'prompt'       => "Summarize these customer reviews:\n{$reviews}",
     *           'systemPrompt' => 'You are a wine critic. Be concise.',
     *       ],
     *   ], [
     *       'temperature' => 0.7,
     *       'cache'       => 'W',
     *   ]);
     *
     * Block structure:
     *   field        (string, required) — page field to save to
     *   prompt       (string, required) — AI prompt
     *   options      (array, optional)  — per-block ask() options override
     *   systemPrompt (string, optional) — shortcut for options['systemPrompt']
     *
     * @param Page $page
     * @param array $blocks Array of block definitions
     * @param array $globalOptions Shared options for all blocks (overwrite, quiet, cache, etc.)
     * @return array ['field_name' => result, ...] where result has 'source' => 'field'|'ai'|'error'
     */
    public function generate(Page $page, array $blocks, array $globalOptions = []): array {
        $overwrite = $globalOptions['overwrite'] ?? false;
        $quiet = $globalOptions['quiet'] ?? true;
        $results = [];

        foreach ($blocks as $block) {
            $fieldName = $block['field'] ?? null;
            $prompt    = $block['prompt'] ?? null;

            if (!$fieldName || !$prompt) {
                $this->logError("generate: block missing 'field' or 'prompt'");
                continue;
            }

            // Merge: global → per-block options → per-block shortcuts
            $blockOptions = array_merge($globalOptions, $block['options'] ?? []);

            if (isset($block['systemPrompt'])) {
                $blockOptions['systemPrompt'] = $block['systemPrompt'];
            }

            // Page context
            if (!isset($blockOptions['pageId']) && !isset($blockOptions['page'])) {
                $blockOptions['pageId'] = $page->id;
            }

            // Check field first (unless overwrite)
            $blockOverwrite = $block['overwrite'] ?? $overwrite;
            if (!$blockOverwrite) {
                $existing = $this->loadFrom($page, $fieldName);
                if ($existing !== null) {
                    $this->debugLog("generate: '{$fieldName}' has content, skipping");
                    $results[$fieldName] = [
                        'success' => true,
                        'content' => $existing,
                        'usage'   => [],
                        'raw'     => [],
                        'cached'  => false,
                        'source'  => 'field',
                    ];
                    continue;
                }
            }

            // Ask AI
            $result = $this->ask($prompt, $blockOptions);

            if ($result['success']) {
                $this->saveTo($page, $fieldName, $result, $quiet);
                $result['source'] = 'ai';
            } else {
                $result['source'] = 'error';
            }

            $results[$fieldName] = $result;
        }

        return $results;
    }

    // =========================================================================
    // AJAX HANDLER
    // =========================================================================

    protected function handleAjaxRequest() {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        // Prevent PW hooks from corrupting JSON output
        ob_start();

        header('Content-Type: application/json; charset=utf-8');

        if (!$this->wire('user')->isSuperuser()) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            exit;
        }

        $action = $this->wire('input')->post->name('aiwire_action');
        $result = ['success' => false, 'message' => 'Unknown action'];

        switch ($action) {
            case 'test_key':
                $result = $this->ajaxTestKey();
                break;
            case 'save_keys':
                $result = $this->ajaxSaveKeys();
                break;
            case 'test_chat':
                $result = $this->ajaxTestChat();
                break;
            case 'clear_cache':
                $count = $this->clearAllCache();
                $result = ['success' => true, 'message' => "Cleared {$count} cached files"];
                break;
        }

        ob_end_clean();
        echo json_encode($result);
        exit;
    }

    /**
     * Test a single API key
     */
    protected function ajaxTestKey(): array {
        $providerKey = $this->wire('input')->post->name('provider');
        $apiKey      = $_POST['api_key'] ?? '';

        if (!$providerKey || !$apiKey) {
            return ['success' => false, 'message' => 'Provider and API key are required'];
        }

        if (!isset(self::PROVIDERS[$providerKey])) {
            return ['success' => false, 'message' => "Unknown provider: {$providerKey}"];
        }

        $config   = self::PROVIDERS[$providerKey];
        $provider = new AiWireProvider($providerKey, $config, $apiKey, $config['defaultModel'], [
            'timeout' => 15,
        ]);

        $testResult = $provider->testConnection();

        $this->debugLog("Test key for {$providerKey}: " . ($testResult['success'] ? 'OK' : 'FAIL'));

        return $testResult;
    }

    /**
     * Save provider keys via AJAX
     */
    protected function ajaxSaveKeys(): array {
        // Use raw POST to avoid PW sanitizer corrupting JSON
        $data = $_POST['keys_data'] ?? '';
        if (!$data) {
            // Fallback: try reading raw input
            $raw = file_get_contents('php://input');
            parse_str($raw, $parsed);
            $data = $parsed['keys_data'] ?? '';
        }
        if (!$data) return ['success' => false, 'message' => 'No data received'];

        $decoded = json_decode($data, true);
        if (!is_array($decoded)) return ['success' => false, 'message' => 'Invalid JSON data: ' . json_last_error_msg()];

        // Validate structure
        $clean = [];
        foreach ($decoded as $providerKey => $keys) {
            if (!isset(self::PROVIDERS[$providerKey])) continue;
            $clean[$providerKey] = [];
            foreach ($keys as $k) {
                $clean[$providerKey][] = [
                    'key'     => trim($k['key'] ?? ''),
                    'label'   => trim($k['label'] ?? ''),
                    'model'   => trim($k['model'] ?? ''),
                    'enabled' => !empty($k['enabled']),
                    'status'  => $k['status'] ?? 'unknown',
                ];
            }
        }

        // Save to module config
        $configData = $this->wire('modules')->getModuleConfigData($this);
        $configData['providers'] = json_encode($clean);
        $this->wire('modules')->saveModuleConfigData($this, $configData);

        $this->log("Provider keys updated");

        return ['success' => true, 'message' => 'Keys saved successfully'];
    }

    /**
     * Test chat via AJAX
     */
    protected function ajaxTestChat(): array {
        $providerKey = $this->wire('input')->post->name('provider');
        $message     = $this->wire('input')->post->text('message') ?: 'What is the safest city in the United States and why?';
        $model       = $_POST['model'] ?? '';
        $keyIndex    = $_POST['key_index'] ?? null;
        $temperature = $_POST['temperature'] ?? null;
        $maxTokens   = $_POST['max_tokens'] ?? null;
        $timeout     = $_POST['timeout'] ?? null;

        if (!$providerKey || !isset(self::PROVIDERS[$providerKey])) {
            return ['success' => false, 'message' => 'Invalid provider'];
        }

        // Get the specific key by index
        $keys = $this->getProviderKeys($providerKey);
        $specificKey = null;

        if ($keyIndex !== null && $keyIndex !== '' && isset($keys[(int)$keyIndex])) {
            $keyData = $keys[(int)$keyIndex];
            $specificKey = $keyData['key'] ?? null;
            if (!$model) $model = $keyData['model'] ?? null;
        }

        $options = ['provider' => $providerKey];
        if ($model) $options['model'] = $model;
        if ($specificKey) $options['key'] = $specificKey;
        if ($temperature !== null && $temperature !== '') $options['temperature'] = (float)$temperature;
        if ($maxTokens !== null && $maxTokens !== '') $options['maxTokens'] = (int)$maxTokens;
        if ($timeout !== null && $timeout !== '') $options['timeout'] = (int)$timeout;

        $result = $this->ask($message, $options);

        // Add cache_saved flag for UI badge
        if ($result['success'] && !($result['cached'] ?? false) && $this->enableCache) {
            $result['cache_saved'] = true;
        }

        return $result;
    }

    // =========================================================================
    // CONFIGURATION UI
    // =========================================================================

    /**
     * Module configuration fields
     */
    public function getModuleConfigInputfields(InputfieldWrapper $inputfields) {
        $modules = $this->wire('modules');

        // ─── Provider Keys Management (main section) ─────────────────────
        $fieldset = $modules->get('InputfieldFieldset');
        $fieldset->label = $this->_('API Keys & Providers');
        $fieldset->icon = 'key';
        $fieldset->description = $this->_('Manage your AI provider API keys. You can add multiple keys per provider.');

        $f = $modules->get('InputfieldMarkup');
        $f->label = $this->_('Provider Configuration');
        $f->value = $this->renderProviderKeysUI();
        $f->collapsed = Inputfield::collapsedNever;
        $fieldset->add($f);

        $inputfields->add($fieldset);

        // ─── Default Settings ────────────────────────────────────────────
        $fieldset = $modules->get('InputfieldFieldset');
        $fieldset->label = $this->_('Default Settings');
        $fieldset->icon = 'cog';
        $fieldset->collapsed = Inputfield::collapsedYes;

        // Default provider
        $f = $modules->get('InputfieldSelect');
        $f->attr('name', 'defaultProvider');
        $f->label = $this->_('Default Provider');
        $f->description = $this->_('Provider to use when none is specified in API calls.');
        foreach (self::PROVIDERS as $key => $config) {
            $f->addOption($key, $config['label']);
        }
        $f->attr('value', $this->defaultProvider ?: 'anthropic');
        $f->columnWidth = 50;
        $fieldset->add($f);

        // Default key index — proper InputfieldSelect so PW saves it
        $f = $modules->get('InputfieldSelect');
        $f->attr('name', 'defaultKeyIndex');
        $f->label = $this->_('Default Key');
        $f->description = $this->_('Key to use by default. First active key is used if not set.');
        $f->columnWidth = 50;
        $f->addOption('', $this->_('— First active key —'));

        $providers = json_decode($this->providers ?: '{}', true) ?: [];
        $currentDefaultProvider = $this->defaultProvider ?: 'anthropic';
        $currentDefaultKey = $this->defaultKeyIndex ?? '';

        // Build JS data for all providers and populate current provider's keys
        $keyOptionsData = [];
        foreach (self::PROVIDERS as $pk => $config) {
            $keys = $providers[$pk] ?? [];
            $keyOptionsData[$pk] = [];
            foreach ($keys as $i => $k) {
                if (!empty($k['enabled']) && !empty($k['key'])) {
                    $maskedKey = substr($k['key'], 0, 8) . '...' . substr($k['key'], -4);
                    $label = !empty($k['label']) ? $k['label'] : ('Key #' . ($i + 1) . ' (' . $maskedKey . ')');
                    $keyOptionsData[$pk][] = ['index' => $i, 'label' => $label];
                    // Add option for current provider
                    if ($pk === $currentDefaultProvider) {
                        $f->addOption((string)$i, $label);
                    }
                }
            }
        }
        $f->attr('value', $currentDefaultKey);
        $fieldset->add($f);

        // Store key data for JS (will be output at the end of config form)
        $this->_keyOptionsJson = json_encode($keyOptionsData);

        // System prompt
        $f = $modules->get('InputfieldTextarea');
        $f->attr('name', 'systemPrompt');
        $f->label = $this->_('Default System Prompt');
        $f->description = $this->_('System prompt sent with every request (can be overridden per call).');
        $f->attr('value', $this->systemPrompt);
        $f->attr('rows', 4);
        $f->notes = $this->_('Example: You are a helpful assistant for our website. Be concise and friendly.');
        $fieldset->add($f);

        // Max tokens
        $f = $modules->get('InputfieldInteger');
        $f->attr('name', 'maxTokens');
        $f->label = $this->_('Max Tokens');
        $f->description = $this->_('Maximum number of tokens in the response.');
        $f->attr('value', (int)$this->maxTokens ?: 1024);
        $f->attr('min', 1);
        $f->attr('max', 128000);
        $f->columnWidth = 33;
        $fieldset->add($f);

        // Temperature
        $f = $modules->get('InputfieldText');
        $f->attr('name', 'temperature');
        $f->label = $this->_('Temperature');
        $f->description = $this->_('Creativity level (0.0 = deterministic, 1.0 = creative).');
        $f->attr('value', $this->temperature ?? '0.7');
        $f->attr('type', 'number');
        $f->attr('step', '0.1');
        $f->attr('min', '0');
        $f->attr('max', '2');
        $f->columnWidth = 33;
        $fieldset->add($f);

        // Timeout
        $f = $modules->get('InputfieldInteger');
        $f->attr('name', 'timeout');
        $f->label = $this->_('Timeout (seconds)');
        $f->description = $this->_('API request timeout.');
        $f->attr('value', (int)$this->timeout ?: 30);
        $f->attr('min', 5);
        $f->attr('max', 300);
        $f->columnWidth = 34;
        $fieldset->add($f);

        // Cache: enable
        $f = $modules->get('InputfieldCheckbox');
        $f->attr('name', 'enableCache');
        $f->label = $this->_('Enable Cache by Default');
        $f->description = $this->_('When ON, all ask()/chat() calls are cached automatically unless explicitly disabled in code with `\'cache\' => false`. When OFF, caching only works when enabled in code with `\'cache\' => \'W\'`.');
        $f->attr('checked', $this->enableCache ? 'checked' : '');
        $f->columnWidth = 50;
        $fieldset->add($f);

        // Cache: default TTL
        $f = $modules->get('InputfieldSelect');
        $f->attr('name', 'defaultCacheTtl');
        $f->label = $this->_('Default Cache Duration');
        $f->description = $this->_('Used when cache is enabled globally or when code passes `\'cache\' => true`.');
        $f->addOptions([
            'D'  => $this->_('1 Day'),
            'W'  => $this->_('1 Week'),
            '2W' => $this->_('2 Weeks'),
            'M'  => $this->_('1 Month'),
            '3M' => $this->_('3 Months'),
            '6M' => $this->_('6 Months'),
            'Y'  => $this->_('1 Year'),
        ]);
        $f->attr('value', $this->defaultCacheTtl ?: 'D');
        $f->columnWidth = 50;
        $fieldset->add($f);

        $inputfields->add($fieldset);

        // ─── Logging ─────────────────────────────────────────────────────
        $fieldset = $modules->get('InputfieldFieldset');
        $fieldset->label = $this->_('Logging');
        $fieldset->icon = 'file-text-o';
        $fieldset->collapsed = Inputfield::collapsedYes;

        $f = $modules->get('InputfieldCheckbox');
        $f->attr('name', 'enableLogging');
        $f->label = $this->_('Enable Logging');
        $f->attr('checked', $this->enableLogging ? 'checked' : '');
        $f->columnWidth = 50;
        $fieldset->add($f);

        $f = $modules->get('InputfieldCheckbox');
        $f->attr('name', 'enableDebugLogging');
        $f->label = $this->_('Enable Debug Logging');
        $f->description = $this->_('Log detailed request/response data (verbose).');
        $f->attr('checked', $this->enableDebugLogging ? 'checked' : '');
        $f->columnWidth = 50;
        $fieldset->add($f);

        $inputfields->add($fieldset);

        // ─── Cache Management ────────────────────────────────────────────
        $fieldset = $modules->get('InputfieldFieldset');
        $fieldset->label = $this->_('Cache');
        $fieldset->icon = 'database';
        $fieldset->collapsed = Inputfield::collapsedYes;

        $f = $modules->get('InputfieldMarkup');
        $f->label = $this->_('Cache Status');
        $f->value = $this->renderCacheUI();
        $fieldset->add($f);

        $inputfields->add($fieldset);

        // ─── Test Chat ───────────────────────────────────────────────────
        $fieldset = $modules->get('InputfieldFieldset');
        $fieldset->label = $this->_('Test Chat');
        $fieldset->icon = 'comments';
        $fieldset->collapsed = Inputfield::collapsedYes;

        $f = $modules->get('InputfieldMarkup');
        $f->label = $this->_('Quick Test');
        $f->value = $this->renderTestChatUI();
        $fieldset->add($f);

        $inputfields->add($fieldset);

        // Hidden field for providers JSON data
        $f = $modules->get('InputfieldHidden');
        $f->attr('name', 'providers');
        $f->attr('id', 'aiwire-providers-data');
        $f->attr('value', $this->providers ?: '{}');
        $inputfields->add($f);

        return $inputfields;
    }

    /**
     * Render the provider keys management UI
     */
    protected function renderProviderKeysUI(): string {
        $providers = json_decode($this->providers ?: '{}', true) ?: [];
        $moduleUrl = $this->wire('config')->urls->admin . 'module/edit?name=AiWire';
        $providersJson = json_encode(self::PROVIDERS);
        $savedKeysJson = json_encode($providers);

        $html = <<<HTML
<div id="aiwire-keys-app" data-providers='{$providersJson}' data-saved='{$savedKeysJson}' data-url='{$moduleUrl}'>
    <style>
        #aiwire-keys-app { margin: 10px 0; }
        .aiwire-provider-section {
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 6px;
            margin-bottom: 15px;
            overflow: hidden;
        }
        .aiwire-provider-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            background: #fff;
            border-bottom: 1px solid #ddd;
            cursor: pointer;
            user-select: none;
        }
        .aiwire-provider-header:hover { background: #f5f5f5; }
        .aiwire-provider-header h4 {
            margin: 0;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .aiwire-provider-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 11px;
            padding: 2px 8px;
            border-radius: 10px;
            font-weight: normal;
        }
        .aiwire-badge-active { background: #d4edda; color: #155724; }
        .aiwire-badge-inactive { background: #f8d7da; color: #721c24; }
        .aiwire-badge-nokeys { background: #e2e3e5; color: #383d41; }
        .aiwire-provider-body { padding: 16px; }
        .aiwire-provider-body.collapsed { display: none; }
        .aiwire-key-row {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 5px;
            margin-bottom: 8px;
        }
        .aiwire-key-row:hover { border-color: #999; }
        .aiwire-key-input {
            flex: 2;
            padding: 6px 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-family: monospace;
            font-size: 13px;
        }
        .aiwire-label-input {
            flex: 1;
            padding: 6px 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 13px;
        }
        .aiwire-model-select {
            flex: 1;
            padding: 6px 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 13px;
        }
        .aiwire-status-icon {
            width: 24px;
            text-align: center;
            font-size: 16px;
        }
        .aiwire-status-ok { color: #28a745; }
        .aiwire-status-fail { color: #dc3545; }
        .aiwire-status-unknown { color: #6c757d; }
        .aiwire-status-testing { color: #ffc107; }
        .aiwire-key-actions {
            display: flex;
            gap: 4px;
        }
        .aiwire-btn {
            padding: 5px 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            background: #fff;
            cursor: pointer;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .aiwire-btn:hover { background: #f0f0f0; border-color: #999; }
        .aiwire-btn-primary { background: #2196F3; color: #fff; border-color: #1976D2; }
        .aiwire-btn-primary:hover { background: #1976D2; }
        .aiwire-btn-danger { color: #dc3545; }
        .aiwire-btn-danger:hover { background: #dc3545; color: #fff; border-color: #dc3545; }
        .aiwire-btn-success { background: #28a745; color: #fff; border-color: #218838; }
        .aiwire-btn-success:hover { background: #218838; }
        .aiwire-add-key-row {
            padding: 8px 0;
        }
        .aiwire-enabled-toggle {
            cursor: pointer;
            font-size: 16px;
        }
        .aiwire-save-bar {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-top: 15px;
            padding: 12px 16px;
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 6px;
        }
        .aiwire-save-bar.hidden { display: none; }
        .aiwire-save-bar.saved {
            background: #d4edda;
            border-color: #28a745;
        }
        .aiwire-docs-link {
            font-size: 12px;
            color: #666;
            text-decoration: none;
        }
        .aiwire-docs-link:hover { color: #2196F3; }
    </style>

    <div id="aiwire-providers-container"></div>

    <div id="aiwire-save-bar" class="aiwire-save-bar hidden">
        <button type="button" id="aiwire-save-btn" class="aiwire-btn aiwire-btn-success" onclick="AiWireApp.saveAll()">
            <i class="fa fa-save"></i> Save All Keys
        </button>
        <span id="aiwire-save-status"></span>
    </div>

    <script>
    var AiWireApp = (function() {
        'use strict';

        var container, providers, savedKeys, moduleUrl;
        var currentKeys = {};
        var dirty = false;

        function init() {
            var app = document.getElementById('aiwire-keys-app');
            container = document.getElementById('aiwire-providers-container');
            providers = JSON.parse(app.getAttribute('data-providers'));
            savedKeys = JSON.parse(app.getAttribute('data-saved'));
            moduleUrl = app.getAttribute('data-url');

            // Initialize currentKeys from saved
            for (var pk in providers) {
                currentKeys[pk] = (savedKeys[pk] || []).map(function(k) {
                    return Object.assign({}, k);
                });
            }

            render();
        }

        function render() {
            var html = '';
            for (var pk in providers) {
                html += renderProvider(pk, providers[pk]);
            }
            container.innerHTML = html;
        }

        function renderProvider(pk, config) {
            var keys = currentKeys[pk] || [];
            var activeCount = keys.filter(function(k) { return k.enabled; }).length;
            var badge = '';

            if (keys.length === 0) {
                badge = '<span class="aiwire-provider-badge aiwire-badge-nokeys">No keys</span>';
            } else if (activeCount > 0) {
                badge = '<span class="aiwire-provider-badge aiwire-badge-active"><i class="fa fa-check"></i> ' + activeCount + ' active</span>';
            } else {
                badge = '<span class="aiwire-provider-badge aiwire-badge-inactive"><i class="fa fa-times"></i> Disabled</span>';
            }

            var collapsed = keys.length === 0 ? '' : ' collapsed';

            var html = '<div class="aiwire-provider-section" data-provider="' + pk + '">';
            html += '<div class="aiwire-provider-header" onclick="AiWireApp.toggleProvider(\'' + pk + '\')">';
            html += '  <h4><i class="fa fa-' + config.icon + '"></i> ' + config.label + ' ' + badge + '</h4>';
            html += '  <div><a href="' + config.docsUrl + '" target="_blank" class="aiwire-docs-link" onclick="event.stopPropagation()"><i class="fa fa-external-link"></i> Docs</a></div>';
            html += '</div>';
            html += '<div class="aiwire-provider-body' + collapsed + '" id="aiwire-body-' + pk + '">';

            // Render key rows
            for (var i = 0; i < keys.length; i++) {
                html += renderKeyRow(pk, i, keys[i], config);
            }

            // Add key button
            html += '<div class="aiwire-add-key-row">';
            html += '  <button type="button" class="aiwire-btn" onclick="AiWireApp.addKey(\'' + pk + '\')">';
            html += '    <i class="fa fa-plus"></i> Add API Key';
            html += '  </button>';
            html += '</div>';
            html += '</div></div>';
            return html;
        }

        function renderKeyRow(pk, index, keyData, config) {
            var statusClass = 'aiwire-status-unknown';
            var statusIcon = 'fa-question-circle';
            var statusTitle = 'Not tested';

            if (keyData.status === 'ok') {
                statusClass = 'aiwire-status-ok';
                statusIcon = 'fa-check-circle';
                statusTitle = 'Connected';
            } else if (keyData.status === 'fail') {
                statusClass = 'aiwire-status-fail';
                statusIcon = 'fa-times-circle';
                statusTitle = 'Failed';
            } else if (keyData.status === 'testing') {
                statusClass = 'aiwire-status-testing';
                statusIcon = 'fa-spinner fa-spin';
                statusTitle = 'Testing...';
            }

            var enabledIcon = keyData.enabled ? 'fa-toggle-on aiwire-status-ok' : 'fa-toggle-off aiwire-status-unknown';
            var enabledTitle = keyData.enabled ? 'Enabled (click to disable)' : 'Disabled (click to enable)';

            // Model options
            var modelOptions = '';
            for (var mk in config.models) {
                var selected = (keyData.model === mk) ? ' selected' : '';
                modelOptions += '<option value="' + mk + '"' + selected + '>' + config.models[mk] + '</option>';
            }

            var maskedKey = maskKey(keyData.key);

            var html = '<div class="aiwire-key-row" id="aiwire-row-' + pk + '-' + index + '">';
            html += '<span class="aiwire-enabled-toggle" title="' + enabledTitle + '" onclick="AiWireApp.toggleEnabled(\'' + pk + '\',' + index + ')">';
            html += '  <i class="fa ' + enabledIcon + '"></i>';
            html += '</span>';
            html += '<span class="aiwire-status-icon ' + statusClass + '" title="' + statusTitle + '"><i class="fa ' + statusIcon + '"></i></span>';
            html += '<input type="text" class="aiwire-label-input" placeholder="Label (optional)" value="' + escHtml(keyData.label || '') + '" onchange="AiWireApp.updateKey(\'' + pk + '\',' + index + ',\'label\',this.value)" />';
            html += '<input type="password" class="aiwire-key-input" placeholder="API Key" value="' + escHtml(keyData.key) + '" onchange="AiWireApp.updateKey(\'' + pk + '\',' + index + ',\'key\',this.value)" />';
            html += '<select class="aiwire-model-select" onchange="AiWireApp.updateKey(\'' + pk + '\',' + index + ',\'model\',this.value)">' + modelOptions + '</select>';
            html += '<div class="aiwire-key-actions">';
            html += '  <button type="button" class="aiwire-btn" title="Test this key" onclick="AiWireApp.testKey(\'' + pk + '\',' + index + ')"><i class="fa fa-plug"></i></button>';
            html += '  <button type="button" class="aiwire-btn aiwire-btn-danger" title="Remove" onclick="AiWireApp.removeKey(\'' + pk + '\',' + index + ')"><i class="fa fa-trash"></i></button>';
            html += '</div>';
            html += '</div>';
            return html;
        }

        function maskKey(key) {
            if (!key || key.length < 12) return key;
            return key.substring(0, 8) + '...' + key.substring(key.length - 4);
        }

        function escHtml(str) {
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(str));
            return div.innerHTML;
        }

        function toggleProvider(pk) {
            var body = document.getElementById('aiwire-body-' + pk);
            if (body) body.classList.toggle('collapsed');
        }

        function addKey(pk) {
            if (!currentKeys[pk]) currentKeys[pk] = [];
            currentKeys[pk].push({
                key: '',
                label: '',
                model: providers[pk].defaultModel,
                enabled: true,
                status: 'unknown'
            });
            setDirty();
            render();

            // Expand the section
            var body = document.getElementById('aiwire-body-' + pk);
            if (body) body.classList.remove('collapsed');

            // Focus the new key input
            var rows = document.querySelectorAll('[id^="aiwire-row-' + pk + '-"]');
            if (rows.length) {
                var lastRow = rows[rows.length - 1];
                var input = lastRow.querySelector('.aiwire-key-input');
                if (input) input.focus();
            }
        }

        function removeKey(pk, index) {
            if (!confirm('Remove this API key?')) return;
            currentKeys[pk].splice(index, 1);
            setDirty();
            render();
        }

        function updateKey(pk, index, field, value) {
            if (currentKeys[pk] && currentKeys[pk][index]) {
                currentKeys[pk][index][field] = value;
                if (field === 'key') currentKeys[pk][index].status = 'unknown';
                setDirty();
            }
        }

        function toggleEnabled(pk, index) {
            if (currentKeys[pk] && currentKeys[pk][index]) {
                currentKeys[pk][index].enabled = !currentKeys[pk][index].enabled;
                setDirty();
                render();
            }
        }

        function testKey(pk, index) {
            var keyData = currentKeys[pk][index];
            if (!keyData || !keyData.key) {
                alert('Please enter an API key first.');
                return;
            }

            keyData.status = 'testing';
            render();

            jQuery.ajax({
                url: moduleUrl,
                type: 'POST',
                data: {
                    aiwire_action: 'test_key',
                    provider: pk,
                    api_key: keyData.key
                },
                dataType: 'json',
                timeout: 20000
            })
            .done(function(response) {
                keyData.status = response.success ? 'ok' : 'fail';
                if (!response.success && response.message) {
                    alert(providers[pk].label + ': ' + response.message);
                }
                render();
            })
            .fail(function(xhr, status) {
                keyData.status = 'fail';
                render();
                alert('Connection test failed: ' + status);
            });
        }

        function setDirty() {
            dirty = true;
            var bar = document.getElementById('aiwire-save-bar');
            bar.classList.remove('hidden', 'saved');
            document.getElementById('aiwire-save-status').textContent = 'Unsaved changes';
            syncHiddenField();
        }

        function syncHiddenField() {
            var hidden = document.getElementById('aiwire-providers-data');
            if (hidden) hidden.value = JSON.stringify(currentKeys);
        }

        function saveAll() {
            var btn = document.getElementById('aiwire-save-btn');
            var status = document.getElementById('aiwire-save-status');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Saving...';

            jQuery.ajax({
                url: moduleUrl,
                type: 'POST',
                data: {
                    aiwire_action: 'save_keys',
                    keys_data: JSON.stringify(currentKeys)
                },
                dataType: 'json',
                timeout: 10000
            })
            .done(function(response) {
                if (response.success) {
                    dirty = false;
                    var bar = document.getElementById('aiwire-save-bar');
                    bar.classList.add('saved');
                    status.textContent = 'Saved!';
                    syncHiddenField();
                } else {
                    status.textContent = 'Error: ' + response.message;
                }
            })
            .fail(function() {
                status.textContent = 'Save failed — check your connection.';
            })
            .always(function() {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa fa-save"></i> Save All Keys';
            });
        }

        // Init on DOM ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
        } else {
            init();
        }

        return {
            toggleProvider: toggleProvider,
            addKey: addKey,
            removeKey: removeKey,
            updateKey: updateKey,
            toggleEnabled: toggleEnabled,
            testKey: testKey,
            saveAll: saveAll
        };
    })();
    </script>
</div>
HTML;

        return $html;
    }

    /**
     * Render Test Chat UI
     */
    protected function renderTestChatUI(): string {
        $moduleUrl = $this->wire('config')->urls->admin . 'module/edit?name=AiWire';
        $providers = json_decode($this->providers ?: '{}', true) ?: [];

        // Build provider -> keys mapping for JS
        $providerKeysMap = [];
        foreach (self::PROVIDERS as $pk => $config) {
            $providerKeysMap[$pk] = [
                'label'  => $config['label'],
                'models' => $config['models'],
                'defaultModel' => $config['defaultModel'],
                'keys'   => [],
            ];
            $keys = $providers[$pk] ?? [];
            foreach ($keys as $i => $k) {
                if (!empty($k['enabled']) && !empty($k['key'])) {
                    $maskedKey = substr($k['key'], 0, 8) . '...' . substr($k['key'], -4);
                    if (!empty($k['label'])) {
                        $displayLabel = $k['label'];
                    } else {
                        $displayLabel = 'Key #' . ($i + 1) . ' (' . $maskedKey . ')';
                    }
                    $model = $k['model'] ?? $config['defaultModel'];
                    $providerKeysMap[$pk]['keys'][] = [
                        'index'  => $i,
                        'label'  => $displayLabel,
                        'model'  => $model,
                    ];
                }
            }
        }
        $providerKeysJson = json_encode($providerKeysMap);

        $defaultProvider = $this->defaultProvider ?: 'anthropic';
        $defaultKeyJson = $this->_keyOptionsJson ?? '{}';

        return <<<HTML
<div id="aiwire-test-chat" style="margin: 10px 0;">
    <div style="display: flex; gap: 10px; margin-bottom: 10px; flex-wrap: wrap;">
        <select id="aiwire-test-provider" style="padding: 6px 10px; border: 1px solid #ccc; border-radius: 4px; min-width: 160px;" onchange="AiWireTestUpdateSelects()">
        </select>
        <select id="aiwire-test-key" style="padding: 6px 10px; border: 1px solid #ccc; border-radius: 4px; min-width: 200px;" onchange="AiWireTestUpdateModel()">
        </select>
        <select id="aiwire-test-model" style="padding: 6px 10px; border: 1px solid #ccc; border-radius: 4px; min-width: 200px;">
        </select>
    </div>
    <div style="display: flex; gap: 10px; margin-bottom: 10px;">
        <input type="text" id="aiwire-test-message" placeholder="Type a test message..." 
               value="What is the safest city in the United States and why?" 
               style="flex: 1; padding: 6px 10px; border: 1px solid #ccc; border-radius: 4px;" />
        <button type="button" class="aiwire-btn aiwire-btn-primary" id="aiwire-test-send-btn" onclick="AiWireTestChat()">
            <i class="fa fa-paper-plane"></i> Send
        </button>
    </div>
    <div style="display: flex; gap: 10px; margin-bottom: 10px; flex-wrap: wrap; align-items: center;">
        <label style="font-size: 12px; color: #666;">
            Temperature
            <input type="number" id="aiwire-test-temperature" value="1" min="0" max="2" step="0.1"
                   style="width: 60px; padding: 4px 6px; border: 1px solid #ccc; border-radius: 4px; font-size: 12px;" />
        </label>
        <label style="font-size: 12px; color: #666;">
            Max Tokens
            <input type="number" id="aiwire-test-tokens" value="1024" min="1" max="32000" step="1"
                   style="width: 75px; padding: 4px 6px; border: 1px solid #ccc; border-radius: 4px; font-size: 12px;" />
        </label>
        <label style="font-size: 12px; color: #666;">
            Timeout
            <input type="number" id="aiwire-test-timeout" value="30" min="5" max="300" step="1"
                   style="width: 55px; padding: 4px 6px; border: 1px solid #ccc; border-radius: 4px; font-size: 12px;" />
            <span style="font-size: 11px;">sec</span>
        </label>
    </div>
    <div id="aiwire-test-response" style="display:none; padding: 12px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 6px; white-space: pre-wrap; font-size: 13px;"></div>
    <div id="aiwire-test-cache-badge" style="display:none; margin-top: 8px; font-size: 12px; padding: 3px 10px; border-radius: 10px; font-weight: 600; width: fit-content;"></div>
</div>
<script>
var _aiwireTestData = {$providerKeysJson};
var _aiwireTestDefault = '{$defaultProvider}';

function AiWireTestUpdateSelects() {
    var providerSel = document.getElementById('aiwire-test-provider');
    var keySel = document.getElementById('aiwire-test-key');
    var modelSel = document.getElementById('aiwire-test-model');
    var pk = providerSel.value;
    var data = _aiwireTestData[pk];

    // Update keys dropdown
    keySel.innerHTML = '';
    if (data.keys.length === 0) {
        keySel.innerHTML = '<option value="">— No active keys —</option>';
        keySel.disabled = true;
    } else {
        keySel.disabled = false;
        for (var i = 0; i < data.keys.length; i++) {
            var opt = document.createElement('option');
            opt.value = data.keys[i].index;
            opt.textContent = data.keys[i].label;
            opt.dataset.model = data.keys[i].model;
            keySel.appendChild(opt);
        }
    }

    // Update models dropdown
    AiWireTestUpdateModel();
}

function AiWireTestUpdateModel() {
    var providerSel = document.getElementById('aiwire-test-provider');
    var keySel = document.getElementById('aiwire-test-key');
    var modelSel = document.getElementById('aiwire-test-model');
    var pk = providerSel.value;
    var data = _aiwireTestData[pk];

    // Get selected key's model as default
    var selectedOpt = keySel.options[keySel.selectedIndex];
    var keyModel = selectedOpt ? (selectedOpt.dataset.model || data.defaultModel) : data.defaultModel;

    modelSel.innerHTML = '';
    for (var mk in data.models) {
        var opt = document.createElement('option');
        opt.value = mk;
        opt.textContent = data.models[mk];
        if (mk === keyModel) opt.selected = true;
        modelSel.appendChild(opt);
    }
}

// Init provider select
(function() {
    var providerSel = document.getElementById('aiwire-test-provider');
    providerSel.innerHTML = '';
    for (var pk in _aiwireTestData) {
        var opt = document.createElement('option');
        opt.value = pk;
        opt.textContent = _aiwireTestData[pk].label;
        if (pk === _aiwireTestDefault) opt.selected = true;
        providerSel.appendChild(opt);
    }
    AiWireTestUpdateSelects();
})();

function AiWireTestChat() {
    var provider = document.getElementById('aiwire-test-provider').value;
    var keyIndex = document.getElementById('aiwire-test-key').value;
    var model = document.getElementById('aiwire-test-model').value;
    var message = document.getElementById('aiwire-test-message').value;
    var temperature = document.getElementById('aiwire-test-temperature').value;
    var maxTokens = document.getElementById('aiwire-test-tokens').value;
    var timeout = document.getElementById('aiwire-test-timeout').value;
    var resultEl = document.getElementById('aiwire-test-response');
    var badgeEl = document.getElementById('aiwire-test-cache-badge');
    var btn = document.getElementById('aiwire-test-send-btn');

    badgeEl.style.display = 'none';

    if (!keyIndex && keyIndex !== '0') {
        resultEl.style.display = 'block';
        resultEl.style.background = '#f8d7da';
        resultEl.style.borderColor = '#dc3545';
        resultEl.textContent = 'No active key for this provider. Add and enable a key first.';
        return;
    }

    resultEl.style.display = 'block';
    resultEl.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Waiting for response...';
    resultEl.style.background = '#fff3cd';
    resultEl.style.borderColor = '#ffc107';
    btn.disabled = true;

    var startTime = Date.now();
    var ajaxTimeout = (parseInt(timeout) || 30) * 1000 + 5000; // server timeout + 5s buffer

    jQuery.ajax({
        url: '{$moduleUrl}',
        type: 'POST',
        data: {
            aiwire_action: 'test_chat',
            provider: provider,
            key_index: keyIndex,
            model: model,
            message: message,
            temperature: temperature,
            max_tokens: maxTokens,
            timeout: timeout
        },
        dataType: 'json',
        timeout: ajaxTimeout
    })
    .done(function(r) {
        var elapsed = Date.now() - startTime;
        if (r.success) {
            resultEl.style.background = '#d4edda';
            resultEl.style.borderColor = '#28a745';
            var info = '\\n\\n--- ';
            if (r.usage && r.usage.total_tokens) info += 'tokens: ' + r.usage.total_tokens + ' | ';
            info += elapsed + 'ms ---';
            resultEl.textContent = r.content + info;

            if (r.cached) {
                badgeEl.textContent = '⚡ FROM CACHE (' + elapsed + 'ms)';
                badgeEl.style.background = '#d4edda';
                badgeEl.style.color = '#155724';
                badgeEl.style.display = 'inline-block';
            } else if (r.cache_saved) {
                badgeEl.textContent = '💾 SAVED TO CACHE';
                badgeEl.style.background = '#cce5ff';
                badgeEl.style.color = '#004085';
                badgeEl.style.display = 'inline-block';
            }
        } else {
            resultEl.style.background = '#f8d7da';
            resultEl.style.borderColor = '#dc3545';
            resultEl.textContent = 'Error: ' + (r.message || 'Unknown error');
        }
    })
    .fail(function(xhr, status) {
        resultEl.style.background = '#f8d7da';
        resultEl.style.borderColor = '#dc3545';
        resultEl.textContent = 'Request failed: ' + status;
    })
    .always(function() {
        btn.disabled = false;
    });
}

// Default Key selector: update options when Default Provider changes
(function() {
    var _dkData = {$defaultKeyJson};
    var _dkProvider = document.querySelector('[name=defaultProvider]');
    var _dkKey = document.querySelector('[name=defaultKeyIndex]');
    if (!_dkProvider || !_dkKey) return;
    _dkProvider.addEventListener('change', function() {
        var pk = this.value;
        var keys = _dkData[pk] || [];
        _dkKey.innerHTML = '<option value="">— First active key —</option>';
        for (var i = 0; i < keys.length; i++) {
            var opt = document.createElement('option');
            opt.value = keys[i].index;
            opt.textContent = keys[i].label;
            _dkKey.appendChild(opt);
        }
    });
})();
</script>
HTML;
    }

    /**
     * Render Cache Management UI
     */
    protected function renderCacheUI(): string {
        $stats = $this->cache->getStats();
        $moduleUrl = $this->wire('config')->urls->admin . 'module/edit?name=AiWire';

        $sizeFormatted = $this->formatBytes($stats['total_size']);

        return <<<HTML
<div style="margin: 10px 0;">
    <table class="AdminDataTable" style="width: auto;">
        <tr><td><strong>Cached responses</strong></td><td>{$stats['total_files']}</td></tr>
        <tr><td><strong>Cache size</strong></td><td>{$sizeFormatted}</td></tr>
        <tr><td><strong>Pages with cache</strong></td><td>{$stats['pages']}</td></tr>
        <tr><td><strong>Expired (pending cleanup)</strong></td><td>{$stats['expired']}</td></tr>
    </table>
    <div style="margin-top: 15px; display: flex; gap: 10px; align-items: center;">
        <button type="button" class="aiwire-btn aiwire-btn-danger" id="aiwire-clear-cache-btn" onclick="AiWireClearCache()">
            <i class="fa fa-trash"></i> Clear All Cache
        </button>
        <span id="aiwire-cache-status" style="font-size: 13px;"></span>
    </div>
</div>
<script>
function AiWireClearCache() {
    if (!confirm('Clear all AiWire cached responses?')) return;
    var btn = document.getElementById('aiwire-clear-cache-btn');
    var status = document.getElementById('aiwire-cache-status');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Clearing...';
    jQuery.ajax({
        url: '{$moduleUrl}',
        type: 'POST',
        data: { aiwire_action: 'clear_cache' },
        dataType: 'json',
        timeout: 10000
    })
    .done(function(r) {
        status.textContent = r.message || 'Done';
        status.style.color = r.success ? '#28a745' : '#dc3545';
    })
    .fail(function() { status.textContent = 'Failed'; status.style.color = '#dc3545'; })
    .always(function() {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa fa-trash"></i> Clear All Cache';
    });
}
</script>
HTML;
    }

    /**
     * Format bytes to human readable
     */
    protected function formatBytes(int $bytes): string {
        if ($bytes === 0) return '0 B';
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = floor(log($bytes, 1024));
        return round($bytes / pow(1024, $i), 1) . ' ' . $units[$i];
    }

    // =========================================================================
    // LOGGING HELPERS
    // =========================================================================

    /**
     * Log an informational message
     */
    public function log($message, array $options = []) {
        if (!$this->enableLogging) return;
        $this->wire('log')->save($this->logName ?: 'aiwire', $message, $options);
    }

    /**
     * Log an error
     */
    public function logError(string $message) {
        $this->wire('log')->save(($this->logName ?: 'aiwire') . '-errors', $message);
    }

    /**
     * Debug log
     */
    public function debugLog(string $message) {
        if (!$this->enableDebugLogging) return;
        $this->wire('log')->save(($this->logName ?: 'aiwire') . '-debug', $message);
    }

    /**
     * Return a standardized error response
     */
    protected function errorResponse(string $message): array {
        $this->logError($message);
        return [
            'success' => false,
            'content' => '',
            'message' => $message,
            'usage'   => [],
            'raw'     => [],
        ];
    }
}