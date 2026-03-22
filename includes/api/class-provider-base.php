<?php
/**
 * AI Provider Interface
 *
 * Abstract base class yang harus diimplementasikan oleh semua AI provider.
 * Setiap provider wajib implement send_prompt() dengan signature yang sama.
 *
 * @package AIContentMaster
 * @since 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

abstract class AI_Content_Master_Provider_Base {

    /**
     * Timeout untuk semua API call (detik).
     */
    const REQUEST_TIMEOUT = 90;

    /**
     * Kirim prompt ke AI provider dan kembalikan hasilnya.
     *
     * @param string $prompt Prompt yang akan dikirim.
     * @return string|WP_Error Teks hasil generate, atau WP_Error jika gagal.
     */
    abstract public function send_prompt( $prompt );

    /**
     * Ambil daftar model yang tersedia dari provider ini.
     *
     * @param bool $force_refresh Bypass cache dan fetch ulang.
     * @return array|WP_Error Array model, atau WP_Error jika gagal.
     */
    abstract public function fetch_available_models( $force_refresh = false );

    /**
     * Nama provider ini untuk ditampilkan di UI.
     *
     * @return string
     */
    abstract public function get_provider_name();

    /**
     * Cek apakah provider ini sudah dikonfigurasi (API key tersedia).
     *
     * @return bool
     */
    abstract public function is_configured();

    /**
     * Helper: extend PHP execution time dan override socket timeout.
     * Dipanggil di awal send_prompt() oleh semua provider.
     */
    /**
     * Stores the original socket timeout before overriding.
     * Declared explicitly to avoid PHP 8.2+ dynamic property deprecation.
     *
     * @var string|false
     */
    protected $prev_socket_timeout = false;

    protected function prepare_execution_environment() {
        $limit = (int) ini_get( 'max_execution_time' );
        if ( $limit > 0 && $limit < self::REQUEST_TIMEOUT + 30 ) {
            @set_time_limit( self::REQUEST_TIMEOUT + 30 );
        }
        $this->prev_socket_timeout = ini_get( 'default_socket_timeout' );
        ini_set( 'default_socket_timeout', self::REQUEST_TIMEOUT + 10 );
    }

    protected function restore_execution_environment() {
        if ( false !== $this->prev_socket_timeout ) {
            ini_set( 'default_socket_timeout', $this->prev_socket_timeout );
            $this->prev_socket_timeout = false;
        }
    }

    /**
     * Helper: apply rate limiting per user (30 detik antar request).
     *
     * @return bool True jika request boleh dilanjutkan, false jika rate limited.
     */
    protected function check_rate_limit() {
        $user_id = get_current_user_id();
        if ( $user_id <= 0 ) {
            return true;
        }

        $key = 'ai_content_master_user_lock_' . $user_id;
        if ( get_transient( $key ) ) {
            return false;
        }

        set_transient( $key, true, 30 );
        return true;
    }

    /**
     * Helper: build args untuk wp_remote_post dengan timeout yang benar.
     *
     * @param array  $headers Request headers.
     * @param string $body    JSON-encoded request body.
     * @return array WP HTTP args.
     */
    protected function build_request_args( $headers, $body ) {
        return array(
            'method'      => 'POST',
            'headers'     => $headers,
            'body'        => $body,
            'timeout'     => self::REQUEST_TIMEOUT,
            'redirection' => 3,
            'blocking'    => true,
            'httpversion' => '1.1',
            'sslverify'   => true,
            'data_format' => 'body',
        );
    }

    /**
     * Helper: log debug message jika WP_DEBUG aktif.
     *
     * @param string $message Pesan log.
     */
    protected function log( $message ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[AI Content Master][' . $this->get_provider_name() . '] ' . $message );
        }
    }
}
