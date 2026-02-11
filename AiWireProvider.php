<?php namespace ProcessWire;

/**
 * AiWireProvider - Handles API communication with AI providers
 *
 * Supports Anthropic (Claude), OpenAI (GPT), xAI (Grok), and OpenRouter.
 *
 * @author Maxim Alex
 * @license MIT
 */

class AiWireProvider {

    /** @var string Provider key (anthropic, openai, google, xai, openrouter) */
    protected string $providerKey;

    /** @var array Provider configuration from AiWire::PROVIDERS */
    protected array $config;

    /** @var string API key */
    protected string $apiKey;

    /** @var string Selected model */
    protected string $model;

    /** @var array Options (timeout, etc.) */
    protected array $options;

    public function __construct(string $providerKey, array $config, string $apiKey, string $model, array $options = []) {
        $this->providerKey = $providerKey;
        $this->config      = $config;
        $this->apiKey      = $apiKey;
        $this->model       = $model;
        $this->options     = array_merge([
            'timeout' => 30,
        ], $options);
    }

    /**
     * Get the selected model
     */
    public function getModel(): string {
        return $this->model;
    }

    /**
     * Set request timeout
     */
    public function setTimeout(int $seconds): void {
        $this->options['timeout'] = max(5, $seconds);
    }

    /**
     * Test connection by sending a minimal request
     *
     * @return array ['success' => bool, 'message' => string]
     */
    public function testConnection(): array {
        try {
            $result = $this->sendMessage('Hi', [
                'maxTokens'    => 10,
                'temperature'  => 0,
                'systemPrompt' => '',
            ]);

            if ($result['success']) {
                return [
                    'success' => true,
                    'message' => "Connected! Model: {$this->model}",
                ];
            }

            return [
                'success' => false,
                'message' => $result['message'] ?? 'Unknown error',
            ];

        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send a message and get a response
     *
     * @param string $message
     * @param array $options model, systemPrompt, maxTokens, temperature, history
     * @return array ['success', 'content', 'message', 'usage', 'raw']
     */
    public function sendMessage(string $message, array $options = []): array {
        $model       = $options['model'] ?? $this->model;
        $systemPrompt = $options['systemPrompt'] ?? '';
        $maxTokens   = (int)($options['maxTokens'] ?? 1024);
        $temperature = (float)($options['temperature'] ?? 0.7);
        $history     = $options['history'] ?? [];

        // Build request based on provider type
        if ($this->providerKey === 'anthropic') {
            return $this->sendAnthropic($message, $model, $systemPrompt, $maxTokens, $temperature, $history);
        }

        // OpenAI-compatible: openai, google, xai, openrouter
        return $this->sendOpenAICompatible($message, $model, $systemPrompt, $maxTokens, $temperature, $history);
    }

    /**
     * Send request to Anthropic Messages API
     */
    protected function sendAnthropic(string $message, string $model, string $systemPrompt, int $maxTokens, float $temperature, array $history): array {
        $messages = [];

        // Add history
        foreach ($history as $h) {
            $messages[] = [
                'role'    => $h['role'] ?? 'user',
                'content' => $h['content'] ?? '',
            ];
        }

        // Add current message
        $messages[] = [
            'role'    => 'user',
            'content' => $message,
        ];

        $body = [
            'model'      => $model,
            'max_tokens' => $maxTokens,
            'messages'   => $messages,
        ];

        // Anthropic uses 'system' as a top-level parameter
        if ($systemPrompt) {
            $body['system'] = $systemPrompt;
        }

        // temperature for Anthropic
        if ($temperature > 0) {
            $body['temperature'] = $temperature;
        }

        $headers = [
            'Content-Type: application/json',
            'x-api-key: ' . $this->apiKey,
        ];

        // Add extra headers
        foreach ($this->config['extraHeaders'] ?? [] as $k => $v) {
            if ($v !== '') $headers[] = "{$k}: {$v}";
        }

        $response = $this->curlRequest($this->config['url'], $body, $headers);

        if (!$response['success']) {
            return $response;
        }

        $data = $response['data'];

        // Check for API error
        if (isset($data['error'])) {
            return [
                'success' => false,
                'content' => '',
                'message' => $data['error']['message'] ?? 'API error',
                'usage'   => [],
                'raw'     => $data,
            ];
        }

        // Extract content
        $content = '';
        if (isset($data['content']) && is_array($data['content'])) {
            foreach ($data['content'] as $block) {
                if (($block['type'] ?? '') === 'text') {
                    $content .= $block['text'];
                }
            }
        }

        // Extract usage
        $usage = [];
        if (isset($data['usage'])) {
            $usage = [
                'input_tokens'  => $data['usage']['input_tokens'] ?? 0,
                'output_tokens' => $data['usage']['output_tokens'] ?? 0,
                'total_tokens'  => ($data['usage']['input_tokens'] ?? 0) + ($data['usage']['output_tokens'] ?? 0),
            ];
        }

        return [
            'success' => true,
            'content' => $content,
            'message' => 'OK',
            'usage'   => $usage,
            'raw'     => $data,
        ];
    }

    /**
     * Send request to OpenAI-compatible API (OpenAI, Google, xAI, OpenRouter)
     */
    protected function sendOpenAICompatible(string $message, string $model, string $systemPrompt, int $maxTokens, float $temperature, array $history): array {
        $messages = [];

        // System message
        if ($systemPrompt) {
            $messages[] = [
                'role'    => 'system',
                'content' => $systemPrompt,
            ];
        }

        // History
        foreach ($history as $h) {
            $messages[] = [
                'role'    => $h['role'] ?? 'user',
                'content' => $h['content'] ?? '',
            ];
        }

        // Current message
        $messages[] = [
            'role'    => 'user',
            'content' => $message,
        ];

        $body = [
            'model'       => $model,
            'temperature' => $temperature,
            'messages'    => $messages,
        ];

        // OpenAI GPT-5+ and o-series require max_completion_tokens
        // Others (xAI, Google, OpenRouter, older OpenAI) use max_tokens
        if ($this->providerKey === 'openai') {
            $body['max_completion_tokens'] = $maxTokens;
        } else {
            $body['max_tokens'] = $maxTokens;
        }

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey,
        ];

        // Extra headers (e.g., OpenRouter HTTP-Referer)
        foreach ($this->config['extraHeaders'] ?? [] as $k => $v) {
            if ($v !== '') $headers[] = "{$k}: {$v}";
        }

        $response = $this->curlRequest($this->config['url'], $body, $headers);

        if (!$response['success']) {
            return $response;
        }

        $data = $response['data'];

        // Check for API error
        if (isset($data['error'])) {
            $errorMsg = is_array($data['error'])
                ? ($data['error']['message'] ?? json_encode($data['error']))
                : (string)$data['error'];
            return [
                'success' => false,
                'content' => '',
                'message' => $errorMsg,
                'usage'   => [],
                'raw'     => $data,
            ];
        }

        // Extract content
        $content = $data['choices'][0]['message']['content'] ?? '';

        // Extract usage
        $usage = [];
        if (isset($data['usage'])) {
            $usage = [
                'input_tokens'  => $data['usage']['prompt_tokens'] ?? 0,
                'output_tokens' => $data['usage']['completion_tokens'] ?? 0,
                'total_tokens'  => $data['usage']['total_tokens'] ?? 0,
            ];
        }

        return [
            'success' => true,
            'content' => $content,
            'message' => 'OK',
            'usage'   => $usage,
            'raw'     => $data,
        ];
    }

    /**
     * Make a cURL request
     *
     * @param string $url
     * @param array $body
     * @param array $headers
     * @return array ['success', 'data', 'message', 'httpCode']
     */
    protected function curlRequest(string $url, array $body, array $headers): array {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($body),
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->options['timeout'],
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => true,
        ]);

        $responseBody = curl_exec($ch);
        $httpCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error        = curl_error($ch);
        $errno        = curl_errno($ch);

        curl_close($ch);

        // cURL error
        if ($errno) {
            return [
                'success'  => false,
                'data'     => [],
                'content'  => '',
                'message'  => "cURL error ({$errno}): {$error}",
                'usage'    => [],
                'raw'      => [],
                'httpCode' => 0,
            ];
        }

        // Parse response
        $data = json_decode($responseBody, true);

        if ($data === null && $responseBody !== '') {
            return [
                'success'  => false,
                'data'     => [],
                'content'  => '',
                'message'  => "Invalid JSON response (HTTP {$httpCode})",
                'usage'    => [],
                'raw'      => ['body' => substr($responseBody, 0, 500)],
                'httpCode' => $httpCode,
            ];
        }

        // HTTP error
        if ($httpCode >= 400) {
            $errorMsg = 'HTTP ' . $httpCode;
            if (isset($data['error']['message'])) {
                $errorMsg .= ': ' . $data['error']['message'];
            } elseif (isset($data['error']) && is_string($data['error'])) {
                $errorMsg .= ': ' . $data['error'];
            }

            return [
                'success'  => false,
                'data'     => $data ?: [],
                'content'  => '',
                'message'  => $errorMsg,
                'usage'    => [],
                'raw'      => $data ?: [],
                'httpCode' => $httpCode,
            ];
        }

        return [
            'success'  => true,
            'data'     => $data,
            'message'  => 'OK',
            'httpCode' => $httpCode,
        ];
    }
}