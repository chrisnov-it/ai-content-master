<?php
/**
 * OpenRouter API Handler
 *
 * Handles all communication with the OpenRouter API,
 * including automatic multi-model fallback when a model
 * is rate-limited (429) or times out.
 *
 * @package AIContentMaster
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AI_Content_Master_OpenRouter_API extends AI_Content_Master_Provider_Base {

    const API_URL         = 'https://openrouter.ai/api/v1/chat/completions';
    const DEFAULT_MODEL   = 'meta-llama/llama-3.3-70b-instruct:free';
    const REQUEST_TIMEOUT = 90;

    // How long (seconds) to blacklist a rate-limited model before retrying.
    const BLACKLIST_TTL = 900; // 15 minutes

    /**
     * Ordered list of free models to try when the primary model fails.
     * Fastest / most reliable first.
     */
    private static $fallback_chain = array(
        'meta-llama/llama-3.3-70b-instruct:free',
        'google/gemma-3-27b-it:free',
        'mistralai/mistral-small-3.1-24b-instruct:free',
        'deepseek/deepseek-v3:free',
        'microsoft/phi-4:free',
        'qwen/qwq-32b:free',
    );

    // ─── Provider Interface ────────────────────────────────────────────

    public function get_provider_name() {
        return 'OpenRouter';
    }

    public function is_configured() {
        return ! empty( get_option( 'ai_content_master_openrouter_api_key' ) );
    }

    /**
     * Send a prompt to OpenRouter, with automatic fallback across
     * multiple free models if the primary model returns 429 or times out.
     *
     * @param string $prompt
     * @return string|WP_Error
     */
    public function send_prompt( $prompt ) {
        $this->prepare_execution_environment();

        if ( ! $this->check_rate_limit() ) {
            $this->restore_execution_environment();
            return new WP_Error(
                'too_many_requests',
                __( 'You are making requests too quickly. Please wait 30 seconds before trying again.', 'ai-content-master' )
            );
        }

        $api_key = $this->get_api_key();
        if ( is_wp_error( $api_key ) ) {
            $this->restore_execution_environment();
            return $api_key;
        }

        // Build the ordered list of models to try.
        $models_to_try = $this->build_model_queue();

        $last_error = null;

        foreach ( $models_to_try as $model_id ) {
            $this->log( 'Trying model: ' . $model_id );

            $result = $this->call_model( $model_id, $prompt, $api_key );

            // Success — return immediately.
            if ( ! is_wp_error( $result ) ) {
                $this->restore_execution_environment();
                return $result;
            }

            $error_code = $result->get_error_code();
            $last_error = $result;

            // Rate limited — blacklist this model and try the next one.
            if ( 'api_error_429' === $error_code ) {
                $this->blacklist_model( $model_id );
                $this->log( 'Model ' . $model_id . ' rate-limited (429), blacklisted for ' . self::BLACKLIST_TTL . 's. Trying next.' );
                continue;
            }

            // Timeout — also move on to the next model.
            if ( 'api_timeout' === $error_code ) {
                $this->log( 'Model ' . $model_id . ' timed out, trying next.' );
                continue;
            }

            // Any other error (auth, malformed, etc.) — stop immediately,
            // retrying a different model won't help.
            break;
        }

        $this->restore_execution_environment();

        // All models failed — return the last error with a helpful message.
        if ( is_wp_error( $last_error ) && 'api_error_429' === $last_error->get_error_code() ) {
            return new WP_Error(
                'all_models_rate_limited',
                __( 'All available free models are currently rate-limited. Please wait a few minutes and try again, or switch to a paid model.', 'ai-content-master' )
            );
        }

        return $last_error ?? new WP_Error( 'unknown_error', __( 'An unknown error occurred.', 'ai-content-master' ) );
    }

    // ─── Model Queue & Blacklist ────────────────────────────────────────

    /**
     * Build the ordered queue of models to attempt.
     * - Primary model (from settings) goes first if not blacklisted.
     * - Fallback chain follows, skipping blacklisted models.
     *
     * @return array<string> Model IDs in order of preference.
     */
    private function build_model_queue() {
        $primary  = get_option( 'ai_content_master_openrouter_model', self::DEFAULT_MODEL );
        $chain    = self::$fallback_chain;
        $queue    = array();

        // Primary model first (if not blacklisted).
        if ( ! $this->is_blacklisted( $primary ) ) {
            $queue[] = $primary;
        }

        // Add fallback models, skipping already-queued or blacklisted ones.
        foreach ( $chain as $model_id ) {
            if ( $model_id !== $primary && ! $this->is_blacklisted( $model_id ) && ! in_array( $model_id, $queue, true ) ) {
                $queue[] = $model_id;
            }
        }

        // If everything is blacklisted (edge case), force-add primary anyway.
        if ( empty( $queue ) ) {
            $queue[] = $primary;
        }

        return $queue;
    }

    /**
     * Blacklist a model for BLACKLIST_TTL seconds.
     *
     * @param string $model_id
     */
    private function blacklist_model( $model_id ) {
        $key = 'ai_cm_bl_' . md5( $model_id );
        set_transient( $key, 1, self::BLACKLIST_TTL );
    }

    /**
     * Check if a model is currently blacklisted.
     *
     * @param string $model_id
     * @return bool
     */
    private function is_blacklisted( $model_id ) {
        return (bool) get_transient( 'ai_cm_bl_' . md5( $model_id ) );
    }

    // ─── Single Model Call ──────────────────────────────────────────────

    /**
     * Execute a single API call to a specific model.
     *
     * @param string $model_id
     * @param string $prompt
     * @param string $api_key
     * @return string|WP_Error
     */
    private function call_model( $model_id, $prompt, $api_key ) {
        $timeout_filter = function() { return self::REQUEST_TIMEOUT; };
        add_filter( 'http_request_timeout', $timeout_filter );

        $body = wp_json_encode( array(
            'model'    => $model_id,
            'messages' => array(
                array( 'role' => 'user', 'content' => $prompt ),
            ),
        ) );

        $args = array(
            'method'      => 'POST',
            'headers'     => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
                'HTTP-Referer'  => get_site_url(),
                'X-Title'       => get_bloginfo( 'name' ),
            ),
            'body'        => $body,
            'timeout'     => self::REQUEST_TIMEOUT,
            'redirection' => 2,
            'blocking'    => true,
            'httpversion' => '1.1',
            'sslverify'   => true,
            'data_format' => 'body',
        );

        $this->log( 'Calling ' . $model_id . ' at ' . date( 'H:i:s' ) );
        $response = wp_remote_post( self::API_URL, $args );
        remove_filter( 'http_request_timeout', $timeout_filter );
        $this->log( 'Response from ' . $model_id . ' at ' . date( 'H:i:s' ) );

        if ( is_wp_error( $response ) ) {
            $msg = $response->get_error_message();
            if ( stripos( $msg, 'timed out' ) !== false || stripos( $msg, 'timeout' ) !== false ) {
                return new WP_Error( 'api_timeout', $msg );
            }
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $raw  = wp_remote_retrieve_body( $response );

        if ( $code >= 400 ) {
            $decoded  = json_decode( $raw, true );
            $err_msg  = $decoded['error']['message'] ?? 'HTTP ' . $code;
            $this->log( 'Model ' . $model_id . ' returned HTTP ' . $code . ': ' . $err_msg );
            return new WP_Error( 'api_error_' . $code, sprintf(
                __( 'OpenRouter API Error (%d): %s', 'ai-content-master' ),
                $code, $err_msg
            ) );
        }

        // Guard against memory issues from huge responses.
        if ( strlen( $raw ) > 10485760 ) {
            return new WP_Error( 'response_too_large', __( 'API response is too large to process.', 'ai-content-master' ) );
        }

        $decoded = json_decode( $raw, true );
        unset( $raw );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return new WP_Error( 'api_json_decode_error', __( 'Failed to decode API response.', 'ai-content-master' ) );
        }

        $text = $decoded['choices'][0]['message']['content'] ?? null;
        if ( null === $text ) {
            return new WP_Error( 'api_unexpected_response', __( 'Could not parse generated text from OpenRouter API response.', 'ai-content-master' ) );
        }

        return trim( $text );
    }

    // ─── Model List ─────────────────────────────────────────────────────

    /**
     * Fetch available models from OpenRouter API (cached 1 hour).
     *
     * @param bool $force_refresh
     * @return array|WP_Error
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

        $response = wp_remote_get( 'https://openrouter.ai/api/v1/models', array(
            'headers'     => array( 'Authorization' => 'Bearer ' . $api_key ),
            'timeout'     => 20,
            'httpversion' => '1.1',
            'sslverify'   => true,
        ) );

        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            return $this->get_fallback_models();
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( json_last_error() !== JSON_ERROR_NONE || empty( $data['data'] ) ) {
            return $this->get_fallback_models();
        }

        $models = array();
        foreach ( $data['data'] as $m ) {
            if ( empty( $m['id'] ) || empty( $m['name'] ) ) {
                continue;
            }
            $models[ $m['id'] ] = array(
                'name'           => $m['name'],
                'pricing'        => $m['pricing'] ?? array(),
                'context_length' => $m['context_length'] ?? 0,
            );
        }

        $models = $this->sort_models_free_first( $models );
        set_transient( 'ai_content_master_models', $models, HOUR_IN_SECONDS );

        return $models;
    }

    /**
     * Sort models: free first (alpha), then paid (alpha).
     *
     * @param array $models
     * @return array
     */
    public function sort_models_free_first( $models ) {
        $free = $paid = array();
        foreach ( $models as $id => $info ) {
            if ( $this->is_model_free( $info ) ) {
                $free[ $id ] = $info;
            } else {
                $paid[ $id ] = $info;
            }
        }
        uasort( $free, fn( $a, $b ) => strcmp( $a['name'], $b['name'] ) );
        uasort( $paid, fn( $a, $b ) => strcmp( $a['name'], $b['name'] ) );
        return array_merge( $free, $paid );
    }

    /**
     * Check if a model is free (zero prompt + completion price).
     *
     * @param array $model
     * @return bool
     */
    public function is_model_free( $model ) {
        if ( empty( $model['pricing'] ) ) {
            return false;
        }
        return floatval( $model['pricing']['prompt'] ?? 1 ) == 0
            && floatval( $model['pricing']['completion'] ?? 1 ) == 0;
    }

    // ─── AJAX Handlers ──────────────────────────────────────────────────

    /**
     * AJAX: refresh model list.
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

        $force = ! empty( $_POST['force_refresh'] ) && '1' === $_POST['force_refresh'];
        if ( $force ) {
            delete_transient( 'ai_content_master_models' );
        }

        $models = $this->fetch_available_models( $force );
        if ( is_wp_error( $models ) ) {
            wp_send_json_error( array( 'message' => $models->get_error_message() ) );
            return;
        }

        $free = $paid = array();
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
            'free'   => $free,
            'paid'   => $paid,
            'total'  => count( $free ) + count( $paid ),
            'cached' => ! $force,
        ) );
    }

    /**
     * AJAX: quick ping test — minimal prompt, max_tokens=5.
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

        $api_key = get_option( 'ai_content_master_openrouter_api_key' );
        $model   = get_option( 'ai_content_master_openrouter_model', self::DEFAULT_MODEL );

        $prev_socket = ini_get( 'default_socket_timeout' );
        ini_set( 'default_socket_timeout', 30 );
        add_filter( 'http_request_timeout', fn() => 25 );

        $start = microtime( true );
        $response = wp_remote_post( self::API_URL, array(
            'headers'     => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ),
            'body'        => wp_json_encode( array(
                'model'      => $model,
                'max_tokens' => 5,
                'messages'   => array( array( 'role' => 'user', 'content' => 'Reply OK.' ) ),
            ) ),
            'timeout'     => 25,
            'httpversion' => '1.1',
            'sslverify'   => true,
        ) );
        $elapsed = round( microtime( true ) - $start, 2 );

        ini_set( 'default_socket_timeout', $prev_socket );
        remove_all_filters( 'http_request_timeout' );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( array(
                'message' => $response->get_error_message(),
                'elapsed' => $elapsed . 's',
                'model'   => $model,
            ) );
            return;
        }

        $code  = wp_remote_retrieve_response_code( $response );
        $body  = json_decode( wp_remote_retrieve_body( $response ), true );
        $reply = $body['choices'][0]['message']['content'] ?? '(no content)';

        if ( $code !== 200 ) {
            wp_send_json_error( array(
                'message' => $body['error']['message'] ?? 'HTTP ' . $code,
                'elapsed' => $elapsed . 's',
                'model'   => $model,
            ) );
            return;
        }

        wp_send_json_success( array(
            'http_code' => $code,
            'model'     => $model,
            'reply'     => trim( $reply ),
            'elapsed'   => $elapsed . 's',
        ) );
    }

    // ─── Helpers ────────────────────────────────────────────────────────

    /**
     * @return string|WP_Error
     */
    private function get_api_key() {
        $key = get_option( 'ai_content_master_openrouter_api_key' );
        if ( empty( $key ) ) {
            return new WP_Error( 'api_key_missing', __( 'OpenRouter API Key is not configured.', 'ai-content-master' ) );
        }
        return $key;
    }

    /**
     * Curated fallback list shown when API is unreachable.
     *
     * @return array
     */
    private function get_fallback_models() {
        return array(
            'meta-llama/llama-3.3-70b-instruct:free' => array(
                'name' => 'Meta Llama 3.3 70B Instruct', 'pricing' => array( 'prompt' => '0', 'completion' => '0' ), 'context_length' => 131072,
            ),
            'google/gemma-3-27b-it:free' => array(
                'name' => 'Google Gemma 3 27B', 'pricing' => array( 'prompt' => '0', 'completion' => '0' ), 'context_length' => 131072,
            ),
            'mistralai/mistral-small-3.1-24b-instruct:free' => array(
                'name' => 'Mistral Small 3.1 24B', 'pricing' => array( 'prompt' => '0', 'completion' => '0' ), 'context_length' => 128000,
            ),
            'deepseek/deepseek-v3:free' => array(
                'name' => 'DeepSeek V3', 'pricing' => array( 'prompt' => '0', 'completion' => '0' ), 'context_length' => 163840,
            ),
            'qwen/qwq-32b:free' => array(
                'name' => 'Qwen QwQ 32B', 'pricing' => array( 'prompt' => '0', 'completion' => '0' ), 'context_length' => 131072,
            ),
            'microsoft/phi-4:free' => array(
                'name' => 'Microsoft Phi-4', 'pricing' => array( 'prompt' => '0', 'completion' => '0' ), 'context_length' => 16384,
            ),
            'openai/gpt-4o-mini' => array(
                'name' => 'OpenAI GPT-4o Mini', 'pricing' => array( 'prompt' => '0.00000015', 'completion' => '0.0000006' ), 'context_length' => 128000,
            ),
            'openai/gpt-4o' => array(
                'name' => 'OpenAI GPT-4o', 'pricing' => array( 'prompt' => '0.0000025', 'completion' => '0.00001' ), 'context_length' => 128000,
            ),
            'anthropic/claude-sonnet-4-6' => array(
                'name' => 'Anthropic Claude Sonnet 4.6', 'pricing' => array( 'prompt' => '0.000003', 'completion' => '0.000015' ), 'context_length' => 200000,
            ),
            'google/gemini-2.5-pro' => array(
                'name' => 'Google Gemini 2.5 Pro', 'pricing' => array( 'prompt' => '0.00000125', 'completion' => '0.000010' ), 'context_length' => 1048576,
            ),
        );
    }
}
