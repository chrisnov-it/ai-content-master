<?php
/**
 * AI Provider Manager
 *
 * Router yang memilih provider aktif berdasarkan settings,
 * dan menjadi single entry point untuk semua AI calls.
 *
 * @package AIContentMaster
 * @since 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AI_Content_Master_Provider_Manager {

    /**
     * Daftar provider yang terdaftar.
     *
     * @var array<string, AI_Content_Master_Provider_Base>
     */
    private $providers = array();

    /**
     * Provider aktif yang sedang digunakan.
     *
     * @var AI_Content_Master_Provider_Base|null
     */
    private $active_provider = null;

    /**
     * Register semua provider yang tersedia.
     */
    public function __construct() {
        $this->providers = array(
            'openrouter' => new AI_Content_Master_OpenRouter_API(),
            'gemini'     => new AI_Content_Master_Gemini_API(),
        );

        $this->active_provider = $this->resolve_active_provider();
    }

    /**
     * Kirim prompt ke provider aktif.
     * Jika provider aktif gagal karena rate limit (429),
     * otomatis fallback ke provider lain yang sudah dikonfigurasi.
     *
     * @param string $prompt
     * @return string|WP_Error
     */
    public function send_prompt( $prompt ) {
        $result = $this->active_provider->send_prompt( $prompt );

        // Auto-fallback jika rate limited.
        if ( is_wp_error( $result ) && in_array( $result->get_error_code(), array( 'rate_limited', 'api_error_429' ), true ) ) {
            $fallback = $this->get_fallback_provider();
            if ( $fallback ) {
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( '[AI Content Master][ProviderManager] Rate limited on ' . $this->active_provider->get_provider_name() . ', falling back to ' . $fallback->get_provider_name() );
                }
                return $fallback->send_prompt( $prompt );
            }
        }

        return $result;
    }

    /**
     * Ambil provider aktif.
     *
     * @return AI_Content_Master_Provider_Base
     */
    public function get_active_provider() {
        return $this->active_provider;
    }

    /**
     * Ambil provider berdasarkan slug.
     *
     * @param string $slug 'openrouter' | 'gemini'
     * @return AI_Content_Master_Provider_Base|null
     */
    public function get_provider( $slug ) {
        return $this->providers[ $slug ] ?? null;
    }

    /**
     * Ambil semua provider yang terdaftar.
     *
     * @return array
     */
    public function get_all_providers() {
        return $this->providers;
    }

    /**
     * Ambil nama provider aktif.
     *
     * @return string
     */
    public function get_active_provider_name() {
        return $this->active_provider->get_provider_name();
    }

    /**
     * Ambil slug provider aktif dari settings.
     *
     * @return string
     */
    public static function get_active_provider_slug() {
        return get_option( 'ai_content_master_active_provider', 'openrouter' );
    }

    // ─── Private ──────────────────────────────────────────────────────────

    /**
     * Resolve provider aktif berdasarkan settings.
     * Jika provider yang dipilih belum dikonfigurasi, fallback ke yang lain.
     *
     * @return AI_Content_Master_Provider_Base
     */
    private function resolve_active_provider() {
        $slug = self::get_active_provider_slug();

        // Provider yang dipilih user.
        if ( isset( $this->providers[ $slug ] ) && $this->providers[ $slug ]->is_configured() ) {
            return $this->providers[ $slug ];
        }

        // Fallback: cari provider lain yang sudah dikonfigurasi.
        foreach ( $this->providers as $provider ) {
            if ( $provider->is_configured() ) {
                return $provider;
            }
        }

        // Last resort: kembalikan OpenRouter (akan error dengan pesan API key missing).
        return $this->providers['openrouter'];
    }

    /**
     * Cari provider lain sebagai fallback saat rate limited.
     *
     * @return AI_Content_Master_Provider_Base|null
     */
    private function get_fallback_provider() {
        foreach ( $this->providers as $slug => $provider ) {
            if ( $provider === $this->active_provider ) {
                continue;
            }
            if ( $provider->is_configured() ) {
                return $provider;
            }
        }
        return null;
    }
}
