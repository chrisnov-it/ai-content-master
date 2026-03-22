<?php
/**
 * Google Gemini API Handler (via Google AI Studio)
 *
 * Implementasi provider untuk Google AI Studio API.
 * Free tier: 1,500 req/hari, 1 juta token/menit untuk Gemini 2.0 Flash.
 * Tidak memerlukan billing — cukup API key dari aistudio.google.com.
 *
 * @package AIContentMaster
 * @since 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AI_Content_Master_Gemini_API extends AI_Content_Master_Provider_Base {

    /**
     * Base URL Google AI Studio API.
     */
    const API_BASE = 'https://generativelanguage.googleapis.com/v1beta/models';

    /**
     * Default model.
     */
    const DEFAULT_MODEL = 'gemini-2.5-flash';

    /**
     * Cache key untuk daftar model.
     */
    const MODELS_CACHE_KEY = 'ai_content_master_gemini_models';

    // ─── Provider Interface ────────────────────────────────────────────────

    public function get_provider_name() {
        return 'Google Gemini (AI Studio)';
    }

    public function is_configured() {
        return ! empty( get_option( 'ai_content_master_gemini_api_key' ) );
    }

    /**
     * Kirim prompt ke Gemini API.
     *
     * @param string $prompt
     * @return string|WP_Error
     */
    public function send_prompt( $prompt ) {
        $this->prepare_execution_environment();

        $this->log( 'Entered send_prompt.' );

        // Rate limiting.
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

        $model    = get_option( 'ai_content_master_gemini_model', self::DEFAULT_MODEL );
        $endpoint = self::API_BASE . '/' . $model . ':generateContent?key=' . $api_key;

        $body = wp_json_encode( array(
            'contents' => array(
                array(
                    'parts' => array(
                        array( 'text' => $prompt ),
                    ),
                ),
            ),
            'generationConfig' => array(
                'temperature'     => 0.7,
                'topK'            => 40,
                'topP'            => 0.95,
                'maxOutputTokens' => 8192,
            ),
            'safetySettings' => array(
                array( 'category' => 'HARM_CATEGORY_HARASSMENT',        'threshold' => 'BLOCK_NONE' ),
                array( 'category' => 'HARM_CATEGORY_HATE_SPEECH',       'threshold' => 'BLOCK_NONE' ),
                array( 'category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_NONE' ),
                array( 'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_NONE' ),
            ),
        ) );

        $args = $this->build_request_args(
            array( 'Content-Type' => 'application/json' ),
            $body
        );

        $this->log( 'Calling model: ' . $model );
        $start    = microtime( true );
        $response = wp_remote_post( $endpoint, $args );
        $elapsed  = round( microtime( true ) - $start, 2 );
        $this->log( 'Call finished in ' . $elapsed . 's' );

        $this->restore_execution_environment();

        return $this->process_response( $response );
    }

    /**
     * Fetch model list dari Google AI Studio.
     *
     * @param bool $force_refresh
     * @return array|WP_Error
     */
    public function fetch_available_models( $force_refresh = false ) {
        if ( ! $force_refresh ) {
            $cached = get_transient( self::MODELS_CACHE_KEY );
            if ( false !== $cached ) {
                return $cached;
            }
        }

        $api_key = get_option( 'ai_content_master_gemini_api_key' );
        if ( empty( $api_key ) ) {
            return $this->get_fallback_models();
        }

        $response = wp_remote_get(
            'https://generativelanguage.googleapis.com/v1beta/models?key=' . $api_key,
            array(
                'timeout'     => 15,
                'httpversion' => '1.1',
                'sslverify'   => true,
            )
        );

        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            return $this->get_fallback_models();
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( empty( $data['models'] ) ) {
            return $this->get_fallback_models();
        }

        $models = array();
        foreach ( $data['models'] as $model ) {
            // Hanya tampilkan model yang support generateContent.
            $methods = $model['supportedGenerationMethods'] ?? array();
            if ( ! in_array( 'generateContent', $methods, true ) ) {
                continue;
            }

            $id = str_replace( 'models/', '', $model['name'] );
            $models[ $id ] = array(
                'name'           => $model['displayName'] ?? $id,
                'pricing'        => array( 'prompt' => '0', 'completion' => '0' ),
                'context_length' => $model['inputTokenLimit'] ?? 0,
                'description'    => $model['description'] ?? '',
            );
        }

        if ( empty( $models ) ) {
            return $this->get_fallback_models();
        }

        // Sort by name.
        uasort( $models, fn( $a, $b ) => strcmp( $a['name'], $b['name'] ) );

        set_transient( self::MODELS_CACHE_KEY, $models, HOUR_IN_SECONDS );

        return $models;
    }

    // ─── AJAX Handlers ────────────────────────────────────────────────────

    /**
     * AJAX: fetch/refresh Gemini model list.
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
            delete_transient( self::MODELS_CACHE_KEY );
        }

        $models = $this->fetch_available_models( $force );

        if ( is_wp_error( $models ) ) {
            wp_send_json_error( array( 'message' => $models->get_error_message() ) );
            return;
        }

        $list = array();
        foreach ( $models as $id => $info ) {
            $list[] = array(
                'id'             => $id,
                'name'           => $info['name'],
                'context_length' => (int) ( $info['context_length'] ?? 0 ),
                'is_free'        => true, // Semua Gemini AI Studio model gratis
            );
        }

        wp_send_json_success( array(
            'free'   => $list,
            'paid'   => array(),
            'total'  => count( $list ),
            'cached' => ! $force,
        ) );
    }

    /**
     * AJAX: ping test ke Gemini API.
     */
    public function ajax_ping_test() {
        if ( ! check_ajax_referer( 'ai_content_master_ajax_nonce', 'security', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'ai-content-master' ) ), 403 );
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ai-content-master' ) ), 403 );
            return;
        }

        $api_key = get_option( 'ai_content_master_gemini_api_key' );
        if ( empty( $api_key ) ) {
            wp_send_json_error( array( 'message' => __( 'Gemini API Key is not configured.', 'ai-content-master' ) ) );
            return;
        }

        $model    = get_option( 'ai_content_master_gemini_model', self::DEFAULT_MODEL );
        $endpoint = self::API_BASE . '/' . $model . ':generateContent?key=' . $api_key;

        $start = microtime( true );
        $response = wp_remote_post( $endpoint, array(
            'headers'     => array( 'Content-Type' => 'application/json' ),
            'body'        => wp_json_encode( array(
                'contents' => array(
                    array( 'parts' => array( array( 'text' => 'Reply with just the word OK.' ) ) ),
                ),
                'generationConfig' => array( 'maxOutputTokens' => 5 ),
            ) ),
            'timeout'     => 20,
            'httpversion' => '1.1',
            'sslverify'   => true,
        ) );
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
        $reply = $body['candidates'][0]['content']['parts'][0]['text'] ?? '(no content)';

        if ( $code !== 200 ) {
            $error_msg = $body['error']['message'] ?? 'HTTP ' . $code;
            wp_send_json_error( array(
                'message' => $error_msg,
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

    // ─── Private Helpers ──────────────────────────────────────────────────

    private function get_api_key() {
        $key = get_option( 'ai_content_master_gemini_api_key' );
        if ( empty( $key ) ) {
            return new WP_Error(
                'api_key_missing',
                __( 'Google Gemini API Key is not configured. Please add it in Settings → AI Content Master.', 'ai-content-master' )
            );
        }
        return $key;
    }

    private function process_response( $response ) {
        if ( is_wp_error( $response ) ) {
            $msg = $response->get_error_message();
            $this->log( 'WP_Error: ' . $msg );

            if ( stripos( $msg, 'timed out' ) !== false || stripos( $msg, 'timeout' ) !== false ) {
                return new WP_Error(
                    'api_timeout',
                    __( 'Gemini API timed out. Please try again.', 'ai-content-master' )
                );
            }
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $this->log( 'Response code: ' . $code );

        if ( $code >= 400 ) {
            $decoded  = json_decode( $body, true );
            $err_msg  = $decoded['error']['message'] ?? 'Unknown Gemini API error.';

            // Pesan ramah untuk rate limit.
            if ( $code === 429 ) {
                return new WP_Error(
                    'rate_limited',
                    __( 'Gemini API rate limit reached. Please wait a moment and try again.', 'ai-content-master' )
                );
            }

            return new WP_Error( 'gemini_api_error_' . $code, sprintf(
                __( 'Gemini API Error (%d): %s', 'ai-content-master' ),
                $code, $err_msg
            ) );
        }

        $decoded = json_decode( $body, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return new WP_Error( 'json_decode_error', __( 'Failed to decode Gemini API response.', 'ai-content-master' ) );
        }

        $text = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if ( null === $text ) {
            // Cek apakah diblokir safety filter.
            $finish_reason = $decoded['candidates'][0]['finishReason'] ?? '';
            if ( 'SAFETY' === $finish_reason ) {
                return new WP_Error(
                    'safety_blocked',
                    __( 'Response was blocked by Gemini safety filters. Try rephrasing your topic.', 'ai-content-master' )
                );
            }
            return new WP_Error( 'empty_response', __( 'Gemini returned an empty response.', 'ai-content-master' ) );
        }

        $this->log( 'Response extracted successfully.' );
        return trim( $text );
    }

    private function get_fallback_models() {
        // Updated March 2026 based on official Gemini API changelog.
        // gemini-2.0-flash* deprecated (limit:0 on free tier, shutdown March 31 2026).
        // gemini-1.5-* shut down September 2025.
        return array(
            'gemini-3-flash-preview'  => array( 'name' => 'Gemini 3 Flash Preview',  'pricing' => array( 'prompt' => '0', 'completion' => '0' ), 'context_length' => 1048576 ),
            'gemini-2.5-flash'        => array( 'name' => 'Gemini 2.5 Flash',        'pricing' => array( 'prompt' => '0', 'completion' => '0' ), 'context_length' => 1048576 ),
            'gemini-2.5-flash-lite'   => array( 'name' => 'Gemini 2.5 Flash Lite',   'pricing' => array( 'prompt' => '0', 'completion' => '0' ), 'context_length' => 1048576 ),
            'gemini-2.5-pro'          => array( 'name' => 'Gemini 2.5 Pro',          'pricing' => array( 'prompt' => '0', 'completion' => '0' ), 'context_length' => 2097152 ),
            'gemini-3-pro-preview'    => array( 'name' => 'Gemini 3 Pro Preview',    'pricing' => array( 'prompt' => '0', 'completion' => '0' ), 'context_length' => 2097152 ),
        );
    }
}
