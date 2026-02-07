<?php
/**
 * OpenRouter API Handler
 *
 * Handles all communication with the OpenRouter API.
 *
 * @package AIContentMaster
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AI_Content_Master_OpenRouter_API
 */
class AI_Content_Master_OpenRouter_API {

    /**
     * API endpoint URL
     *
     * @var string
     */
    const API_URL = 'https://openrouter.ai/api/v1/chat/completions';

    /**
     * Default model to use for API calls
     *
     * @var string
     */
    const DEFAULT_MODEL = 'openai/gpt-oss-120b:free';

    /**
     * Send prompt to OpenRouter API
     *
     * @param string $prompt The text prompt to send.
     * @return string|WP_Error The generated text on success, or WP_Error on failure.
     */
    public function send_prompt($prompt) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('AI Content Master API: Entered send_prompt function.');
        }

        // Rate limiting: a user can only make one request every 30 seconds.
        $user_id = get_current_user_id();
        if ($user_id > 0) {
            $transient_key = 'ai_content_master_user_lock_' . $user_id;
            if (get_transient($transient_key)) {
                return new WP_Error(
                    'too_many_requests',
                    __('You are making requests too quickly. Please wait 30 seconds before trying again.', 'ai-content-master')
                );
            }
            set_transient($transient_key, true, 30);
        }

        // Get API key
        $api_key = $this->get_api_key();
        if (is_wp_error($api_key)) {
            return $api_key;
        }

        // Prepare request data
        $request_data = $this->prepare_request_data($prompt);

        // Make API call
        $response = $this->make_api_call($request_data, $api_key);

        // Process response
        return $this->process_response($response);
    }

    /**
     * Get API key from settings
     *
     * @return string|WP_Error API key or error.
     */
    private function get_api_key() {
        $api_key = get_option('ai_content_master_openrouter_api_key');

        if (empty($api_key)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('AI Content Master API: API key is empty or not found.');
            }
            return new WP_Error('api_key_missing', __('OpenRouter API Key is not configured.', 'ai-content-master'));
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('AI Content Master API: API key retrieved successfully.');
        }
        return $api_key;
    }

    /**
     * Prepare request data for API call
     *
     * @param string $prompt The prompt to send.
     * @return array Request data.
     */
    private function prepare_request_data($prompt) {
        $model = get_option('ai_content_master_openrouter_model', self::DEFAULT_MODEL);

        return array(
            'model' => $model,
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            )
        );
    }

    /**
     * Make API call to OpenRouter
     *
     * @param array  $request_data Request data.
     * @param string $api_key API key.
     * @return array|WP_Error Response data or error.
     */
    private function make_api_call($request_data, $api_key) {
        $args = array(
            'method'      => 'POST',
            'headers'     => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ),
            'body'        => wp_json_encode($request_data),
            'timeout'     => 180,
            'redirection' => 5,
            'blocking'    => true,
            'httpversion' => '1.0',
            'sslverify'   => true,
            'data_format' => 'body',
        );

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('AI Content Master API: Making API call to ' . self::API_URL);
        }

        $response = wp_remote_post(self::API_URL, $args);

        if (is_wp_error($response)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('AI Content Master API: wp_remote_post returned WP_Error: ' . $response->get_error_message());
            }
            return $response;
        }

        return $response;
    }

    /**
     * Process API response
     *
     * @param array|WP_Error $response API response.
     * @return string|WP_Error Processed response or error.
     */
    private function process_response($response) {
        if (is_wp_error($response)) {
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('AI Content Master API: Response code: ' . $response_code);
            // Log response for debugging (truncated)
            error_log('AI Content Master API: Response body (start): ' . substr($response_body, 0, 200));
        }

		// Check for HTTP errors.
		if ( $response_code >= 400 ) {
			$error_message = $this->extract_error_message( $response_body );
			if ( defined( 'WP_DEBUG' ) && true === WP_DEBUG ) {
				error_log( 'AI Content Master API: API returned error ' . $response_code . ': ' . $error_message );
			}
			return new WP_Error( 'api_error_' . $response_code, sprintf( __( 'OpenRouter API Error (%d): %s', 'ai-content-master' ), $response_code, $error_message ) );
		}

        // Check response size to prevent memory exhaustion
        $response_size = strlen($response_body);
        if ($response_size > 10485760) { // 10MB limit
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('AI Content Master API: Response too large: ' . $response_size . ' bytes');
            }
            return new WP_Error('response_too_large', __('API response is too large to process.', 'ai-content-master'));
        }

        // Decode JSON response with memory-efficient approach
        $decoded_body = json_decode($response_body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('AI Content Master API: JSON decode error: ' . json_last_error_msg());
            }
            return new WP_Error('api_json_decode_error', __('Failed to decode API response.', 'ai-content-master'));
        }

        // Clear response body from memory as soon as possible
        unset($response_body);

        // Extract generated text
        $generated_text = $this->extract_generated_text($decoded_body);
        if (is_wp_error($generated_text)) {
            return $generated_text;
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('AI Content Master API: Generated text extracted successfully.');
        }
        return trim($generated_text);
    }

    /**
     * Extract error message from response body
     *
     * @param string $response_body Response body.
     * @return string Error message.
     */
	private function extract_error_message( $response_body ) {
		$decoded = json_decode( $response_body, true );
		if ( JSON_ERROR_NONE === json_last_error() && isset( $decoded['error']['message'] ) ) {
			return $decoded['error']['message'];
		}
		return __( 'Unknown API error occurred.', 'ai-content-master' );
	}

    /**
     * Extract generated text from decoded response
     *
     * @param array $decoded_body Decoded response body.
     * @return string|WP_Error Generated text or error.
     */
    private function extract_generated_text($decoded_body) {
        if (!isset($decoded_body['choices'][0]['message']['content'])) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('AI Content Master API: Failed to find generated text in expected path.');
            }
            return new WP_Error('api_unexpected_response', __('Could not parse generated text from OpenRouter API response.', 'ai-content-master'));
        }

        return $decoded_body['choices'][0]['message']['content'];
    }

    /**
     * Fetch available models from OpenRouter API
     *
     * @return array|WP_Error Array of models or error.
     */
    public function fetch_available_models() {
        // Clear cache for debugging (remove this line in production)
        delete_transient('ai_content_master_models');

        // Check cache first
        $cached_models = get_transient('ai_content_master_models');
        if ($cached_models !== false) {
            return $cached_models;
        }

        // Get API key
        $api_key = get_option('ai_content_master_openrouter_api_key');
        if (empty($api_key)) {
            // Return fallback models if no API key
            return $this->get_fallback_models();
        }

        // Try without authentication first (some endpoints don't require it)
        $args = array(
            'method'      => 'GET',
            'timeout'     => 30,
            'redirection' => 5,
            'blocking'    => true,
            'httpversion' => '1.0',
            'sslverify'   => true,
        );

        $response = wp_remote_get('https://openrouter.ai/api/v1/models', $args);

        // If that fails, try alternative endpoint
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            $response = wp_remote_get('https://openrouter.ai/api/models', $args);
        }

        // If that also fails, try with authentication
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            $args['headers'] = array(
                'Authorization' => 'Bearer ' . $api_key,
            );
            $response = wp_remote_get('https://openrouter.ai/api/v1/models', $args);
        }

        if (is_wp_error($response)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('AI Content Master API: Failed to fetch models: ' . $response->get_error_message());
            }
            return $this->get_fallback_models();
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($response_code !== 200) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('AI Content Master API: Models API returned error ' . $response_code);
            }
            return $this->get_fallback_models();
        }

        $decoded_body = json_decode($response_body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('AI Content Master API: Failed to decode models response');
            }
            return $this->get_fallback_models();
        }

        if (!isset($decoded_body['data']) || !is_array($decoded_body['data'])) {
            return $this->get_fallback_models();
        }

        $models = array();
        foreach ($decoded_body['data'] as $model) {
            if (isset($model['id']) && isset($model['name'])) {
                $models[$model['id']] = array(
                    'name' => $model['name'],
                    'pricing' => $model['pricing'] ?? array(),
                    'context_length' => $model['context_length'] ?? 0,
                );
            }
        }

        // Cache for 1 hour
        set_transient('ai_content_master_models', $models, HOUR_IN_SECONDS);

        return $models;
    }

    /**
     * Get fallback models when API is unavailable
     *
     * @return array Fallback models.
     */
    private function get_fallback_models() {
        return array(
            'openai/gpt-oss-120b:free' => array(
                'name' => 'OpenAI GPT-OSS 120B (Free)',
                'pricing' => array('prompt' => '0', 'completion' => '0'),
                'context_length' => 128000,
            ),
            'google/gemini-2.5-pro' => array(
                'name' => 'Google Gemini 2.5 Pro',
                'pricing' => array('prompt' => '0', 'completion' => '0'),
                'context_length' => 1048576,
            ),
            'google/gemini-flash-1.5' => array(
                'name' => 'Google Gemini Flash 1.5',
                'pricing' => array('prompt' => '0', 'completion' => '0'),
                'context_length' => 1048576,
            ),
            'openai/gpt-4o' => array(
                'name' => 'OpenAI GPT-4o',
                'pricing' => array('prompt' => '0.0000025', 'completion' => '0.00001'),
                'context_length' => 128000,
            ),
            'openai/gpt-4o-mini' => array(
                'name' => 'OpenAI GPT-4o Mini',
                'pricing' => array('prompt' => '0.00000015', 'completion' => '0.0000006'),
                'context_length' => 128000,
            ),
            'anthropic/claude-3.5-sonnet' => array(
                'name' => 'Anthropic Claude 3.5 Sonnet',
                'pricing' => array('prompt' => '0.000003', 'completion' => '0.000015'),
                'context_length' => 200000,
            ),
            'meta-llama/llama-3.1-70b-instruct' => array(
                'name' => 'Meta Llama 3.1 70B',
                'pricing' => array('prompt' => '0.00000059', 'completion' => '0.00000079'),
                'context_length' => 131072,
            ),
            'meta-llama/llama-3.1-8b-instruct' => array(
                'name' => 'Meta Llama 3.1 8B',
                'pricing' => array('prompt' => '0.000000055', 'completion' => '0.000000055'),
                'context_length' => 131072,
            ),
            'mistralai/mistral-7b-instruct' => array(
                'name' => 'Mistral 7B Instruct',
                'pricing' => array('prompt' => '0.000000055', 'completion' => '0.000000055'),
                'context_length' => 32768,
            ),
        );
    }

    /**
     * Check if a model is free (has zero pricing)
     *
     * @param array $model Model data.
     * @return bool True if free.
     */
    public function is_model_free($model) {
        if (!isset($model['pricing'])) {
            return false;
        }

        $pricing = $model['pricing'];
        $prompt_price = isset($pricing['prompt']) ? floatval($pricing['prompt']) : 0;
        $completion_price = isset($pricing['completion']) ? floatval($pricing['completion']) : 0;

        return $prompt_price == 0 && $completion_price == 0;
    }
}
