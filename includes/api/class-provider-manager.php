<?php
/**
 * AI Provider Manager
 *
 * Single entry point for all AI calls.
 * Routes requests to the active provider (OpenRouter or Gemini).
 * OpenRouter handles its own multi-model fallback internally.
 *
 * @package AIContentMaster
 * @since 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AI_Content_Master_Provider_Manager {

    /** @var array<string, AI_Content_Master_Provider_Base> */
    private $providers = array();

    /** @var AI_Content_Master_Provider_Base */
    private $active_provider;

    public function __construct() {
        $this->providers = array(
            'openrouter' => new AI_Content_Master_OpenRouter_API(),
            'gemini'     => new AI_Content_Master_Gemini_API(),
        );
        $this->active_provider = $this->resolve_active_provider();
    }

    /**
     * Send prompt to the active provider.
     *
     * @param string $prompt
     * @return string|WP_Error
     */
    public function send_prompt( $prompt ) {
        return $this->active_provider->send_prompt( $prompt );
    }

    /**
     * Get a specific provider by slug.
     *
     * @param string $slug 'openrouter' | 'gemini'
     * @return AI_Content_Master_Provider_Base|null
     */
    public function get_provider( $slug ) {
        return $this->providers[ $slug ] ?? null;
    }

    public function get_active_provider() {
        return $this->active_provider;
    }

    public function get_active_provider_name() {
        return $this->active_provider->get_provider_name();
    }

    public static function get_active_provider_slug() {
        return get_option( 'ai_content_master_active_provider', 'openrouter' );
    }

    // ─── Private ────────────────────────────────────────────────────────

    /**
     * Resolve which provider to use based on settings.
     * Falls back to any configured provider if the chosen one is unconfigured.
     *
     * @return AI_Content_Master_Provider_Base
     */
    private function resolve_active_provider() {
        $slug = self::get_active_provider_slug();

        if ( isset( $this->providers[ $slug ] ) && $this->providers[ $slug ]->is_configured() ) {
            return $this->providers[ $slug ];
        }

        // Fallback: use any configured provider.
        foreach ( $this->providers as $provider ) {
            if ( $provider->is_configured() ) {
                return $provider;
            }
        }

        // Last resort: OpenRouter (will surface "API key missing" error).
        return $this->providers['openrouter'];
    }
}
