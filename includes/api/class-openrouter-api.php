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
    const DEFAULT_MODEL = 'meta-llama/llama-3.3-70b-instruct:free';

    /**
     * Send prompt to OpenRouter API
     *
     * @param string $prompt The text prompt to send.
     * @return string|WP_Error The generated text on success, or WP_Error on failure.
     */
    public function send_prompt($prompt) {
        // Extend PHP execution time for this request only.
        // Free models can take 30-90s; default PHP timeout is often 30s.
        $original_time_limit = (int) ini_get('max_execution_time');
        if ( $original_time_limit > 0 && $original_time_limit < self::REQUEST_TIMEOUT + 30 ) {
            @set_time_limit( self::REQUEST_TIMEOUT + 30 );
        }

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
    /**
     * Timeout in seconds for API calls.
     * High value needed because free models on OpenRouter can be slow.
     */
    const REQUEST_TIMEOUT = 90;

    private function make_api_call($request_data, $api_key) {
        // Force WordPress to respect our timeout value.
        // The default WP HTTP timeout filter can override the 'timeout' arg,
        // so we hook in to guarantee our value wins for this request only.
        $timeout_filter = function() { return self::REQUEST_TIMEOUT; };
        add_filter( 'http_request_timeout', $timeout_filter );

        // Override default_socket_timeout (PHP ini, default 60s) for this request.
        // Without this, PHP kills the socket before WP timeout fires on long AI responses.
        $prev_socket_timeout = ini_get( 'default_socket_timeout' );
        ini_set( 'default_socket_timeout', self::REQUEST_TIMEOUT + 10 );

        $args = array(
            'method'      => 'POST',
            'headers'     => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
                'HTTP-Referer'  => get_site_url(),
                'X-Title'       => get_bloginfo( 'name' ),
            ),
            'body'        => wp_json_encode( $request_data ),
            'timeout'     => self::REQUEST_TIMEOUT,
            'redirection' => 3,
            'blocking'    => true,
            'httpversion' => '1.1',
            'sslverify'   => true,
            'data_format' => 'body',
        );

        if ( defined('WP_DEBUG') && WP_DEBUG ) {
            error_log( 'AI Content Master API: Making API call. Model: ' . ( $request_data['model'] ?? 'unknown' ) . ', Timeout: ' . self::REQUEST_TIMEOUT . 's, Socket timeout: ' . ini_get('default_socket_timeout') . 's' );
            error_log( 'AI Content Master API: Call started at: ' . date('H:i:s') );
        }

        $response = wp_remote_post( self::API_URL, $args );

        // Always restore everything immediately after the request.
        remove_filter( 'http_request_timeout', $timeout_filter );
        ini_set( 'default_socket_timeout', $prev_socket_timeout );

        if ( defined('WP_DEBUG') && WP_DEBUG ) {
            error_log( 'AI Content Master API: Call finished at: ' . date('H:i:s') );
        }

        if ( is_wp_error( $response ) ) {
            $error_msg = $response->get_error_message();
            if ( defined('WP_DEBUG') && WP_DEBUG ) {
                error_log( 'AI Content Master API: wp_remote_post WP_Error: ' . $error_msg );
            }
            // Give user a friendlier message for timeout errors.
            if ( strpos( strtolower( $error_msg ), 'timed out' ) !== false
                || strpos( strtolower( $error_msg ), 'timeout' ) !== false ) {
                return new WP_Error(
                    'api_timeout',
                    __( 'The AI model took too long to respond. Please try again, or switch to a faster model (e.g. Gemini Flash).', 'ai-content-master' )
                );
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
     * Fetch available models from OpenRouter API (with caching).
     *
     * @param bool $force_refresh Skip cache and re-fetch from API.
     * @return array|WP_Error Array of models sorted free-first, or error.
     */
    public function fetch_available_models( $force_refresh = false ) {
        if ( ! $force_refresh ) {
            $cached = get_transient( 'ai_content_master_models' );
            if ( false !== $cached ) {
                return $cached;
            }
        }

        $api_key = get_option( 'ai_content_master_openrouter_api_key' );
        if ( empty( $api_key ) ) {
            return $this->get_fallback_models();
        }

        $args = array(
            'method'      => 'GET',
            'headers'     => array( 'Authorization' => 'Bearer ' . $api_key ),
            'timeout'     => 30,
            'redirection' => 5,
            'blocking'    => true,
            'httpversion' => '1.0',
            'sslverify'   => true,
        );

        $response = wp_remote_get( 'https://openrouter.ai/api/v1/models', $args );

        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                $msg = is_wp_error( $response ) ? $response->get_error_message() : wp_remote_retrieve_response_code( $response );
                error_log( 'AI Content Master API: Failed to fetch models: ' . $msg );
            }
            return $this->get_fallback_models();
        }

        $decoded_body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( json_last_error() !== JSON_ERROR_NONE || ! isset( $decoded_body['data'] ) || ! is_array( $decoded_body['data'] ) ) {
            return $this->get_fallback_models();
        }

        $models = array();
        foreach ( $decoded_body['data'] as $model ) {
            if ( empty( $model['id'] ) || empty( $model['name'] ) ) {
                continue;
            }
            $models[ $model['id'] ] = array(
                'name'           => $model['name'],
                'pricing'        => $model['pricing'] ?? array(),
                'context_length' => $model['context_length'] ?? 0,
                'description'    => $model['description'] ?? '',
            );
        }

        $models = $this->sort_models_free_first( $models );

        // Cache for 1 hour.
        set_transient( 'ai_content_master_models', $models, HOUR_IN_SECONDS );

        return $models;
    }

    /**
     * Sort models so that free models come first, then paid, each group sorted by name.
     *
     * @param array $models Unsorted models array.
     * @return array Sorted models array.
     */
    public function sort_models_free_first( $models ) {
        $free = array();
        $paid = array();

        foreach ( $models as $id => $info ) {
            if ( $this->is_model_free( $info ) ) {
                $free[ $id ] = $info;
            } else {
                $paid[ $id ] = $info;
            }
        }

        // Sort each group alphabetically by display name.
        uasort( $free, function( $a, $b ) { return strcmp( $a['name'], $b['name'] ); } );
        uasort( $paid, function( $a, $b ) { return strcmp( $a['name'], $b['name'] ); } );

        return array_merge( $free, $paid );
    }

    /**
     * AJAX handler: quick connectivity test to OpenRouter.
     * Tests a minimal API call with a tiny prompt to check reachability & auth.
     */
    public function ajax_ping_test() {
        if ( ! check_ajax_referer( 'ai_content_master_ajax_nonce', 'security', false ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed.' ), 403 );
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ), 403 );
            return;
        }

        $start = microtime( true );

        // Temporarily bump socket timeout for this test.
        $prev = ini_get( 'default_socket_timeout' );
        ini_set( 'default_socket_timeout', 30 );
        add_filter( 'http_request_timeout', function() { return 25; } );

        $api_key = get_option( 'ai_content_master_openrouter_api_key' );
        $model   = get_option( 'ai_content_master_openrouter_model', self::DEFAULT_MODEL );

        $response = wp_remote_post( self::API_URL, array(
            'method'      => 'POST',
            'headers'     => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ),
            'body'        => wp_json_encode( array(
                'model'      => $model,
                'max_tokens' => 5,
                'messages'   => array(
                    array( 'role' => 'user', 'content' => 'Reply with just the word OK.' ),
                ),
            ) ),
            'timeout'     => 25,
            'httpversion' => '1.1',
            'sslverify'   => true,
        ) );

        ini_set( 'default_socket_timeout', $prev );
        remove_all_filters( 'http_request_timeout' );

        $elapsed = round( microtime( true ) - $start, 2 );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( array(
                'message' => $response->get_error_message(),
                'elapsed' => $elapsed . 's',
                'model'   => $model,
            ) );
            return;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $reply = $body['choices'][0]['message']['content'] ?? '(no content)';

        wp_send_json_success( array(
            'http_code' => $code,
            'model'     => $model,
            'reply'     => trim( $reply ),
            'elapsed'   => $elapsed . 's',
        ) );
    }

    /**
     * AJAX handler: refresh model list (clears cache, re-fetches, returns sorted JSON).
     */
    public function ajax_fetch_models() {
        if ( ! check_ajax_referer( 'ai_content_master_ajax_nonce', 'security', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'ai-content-master' ) ), 403 );
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ai-content-master' ) ), 403 );
            return;
        }

        $force = isset( $_POST['force_refresh'] ) && '1' === $_POST['force_refresh'];

        if ( $force ) {
            delete_transient( 'ai_content_master_models' );
        }

        $models = $this->fetch_available_models( $force );

        if ( is_wp_error( $models ) ) {
            wp_send_json_error( array( 'message' => $models->get_error_message() ) );
            return;
        }

        // Build a clean payload for JS: separate free/paid, include context_length.
        $free = array();
        $paid = array();

        foreach ( $models as $id => $info ) {
            $entry = array(
                'id'             => $id,
                'name'           => $info['name'],
                'context_length' => (int) ( $info['context_length'] ?? 0 ),
                'is_free'        => $this->is_model_free( $info ),
            );
            if ( $entry['is_free'] ) {
                $free[] = $entry;
            } else {
                $paid[] = $entry;
            }
        }

        wp_send_json_success( array(
            'free'    => $free,
            'paid'    => $paid,
            'total'   => count( $free ) + count( $paid ),
            'cached'  => ! $force,
        ) );
    }

    /**
     * Get fallback models when API is unavailable
     *
     * @return array Fallback models.
     */
    private function get_fallback_models() {
        // Fallback list: fast & reliable free models per March 2026 OpenRouter data.
        // Free models first (sorted by reliability), then popular paid options.
        return array(
            // --- FREE ---
            'meta-llama/llama-3.3-70b-instruct:free' => array(
                'name'           => 'Meta Llama 3.3 70B Instruct',
                'pricing'        => array( 'prompt' => '0', 'completion' => '0' ),
                'context_length' => 131072,
            ),
            'google/gemma-3-27b-it:free' => array(
                'name'           => 'Google Gemma 3 27B',
                'pricing'        => array( 'prompt' => '0', 'completion' => '0' ),
                'context_length' => 131072,
            ),
            'mistralai/mistral-small-3.1-24b-instruct:free' => array(
                'name'           => 'Mistral Small 3.1 24B',
                'pricing'        => array( 'prompt' => '0', 'completion' => '0' ),
                'context_length' => 128000,
            ),
            'deepseek/deepseek-v3:free' => array(
                'name'           => 'DeepSeek V3',
                'pricing'        => array( 'prompt' => '0', 'completion' => '0' ),
                'context_length' => 163840,
            ),
            'microsoft/phi-4:free' => array(
                'name'           => 'Microsoft Phi-4',
                'pricing'        => array( 'prompt' => '0', 'completion' => '0' ),
                'context_length' => 16384,
            ),
            // --- PAID ---
            'openai/gpt-4o-mini' => array(
                'name'           => 'OpenAI GPT-4o Mini',
                'pricing'        => array( 'prompt' => '0.00000015', 'completion' => '0.0000006' ),
                'context_length' => 128000,
            ),
            'openai/gpt-4o' => array(
                'name'           => 'OpenAI GPT-4o',
                'pricing'        => array( 'prompt' => '0.0000025', 'completion' => '0.00001' ),
                'context_length' => 128000,
            ),
            'anthropic/claude-sonnet-4-6' => array(
                'name'           => 'Anthropic Claude Sonnet 4.6',
                'pricing'        => array( 'prompt' => '0.000003', 'completion' => '0.000015' ),
                'context_length' => 200000,
            ),
            'google/gemini-2.5-pro' => array(
                'name'           => 'Google Gemini 2.5 Pro',
                'pricing'        => array( 'prompt' => '0.00000125', 'completion' => '0.000010' ),
                'context_length' => 1048576,
            ),
            'deepseek/deepseek-v3' => array(
                'name'           => 'DeepSeek V3',
                'pricing'        => array( 'prompt' => '0.00000028', 'completion' => '0.00000089' ),
                'context_length' => 163840,
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
