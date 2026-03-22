<?php
/**
 * Admin Settings Handler
 *
 * Handles plugin settings and admin menu.
 *
 * @package AIContentMaster
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AI_Content_Master_Admin_Settings
 */
class AI_Content_Master_Admin_Settings {

    /**
     * Initialize the admin settings
     */
    public function init() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_options_page(
            __('AI Content Master Settings', 'ai-content-master'),
            __('AI Content Master', 'ai-content-master'),
            'manage_options',
            'ai-content-master',
            array($this, 'settings_page_html')
        );
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {

        // ── Active Provider ────────────────────────────────────────────────
        register_setting( 'ai_content_master_settings_group', 'ai_content_master_active_provider', array(
            'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => 'openrouter',
        ) );

        // ── OpenRouter ─────────────────────────────────────────────────────
        register_setting( 'ai_content_master_settings_group', 'ai_content_master_openrouter_api_key', array(
            'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => '',
        ) );
        register_setting( 'ai_content_master_settings_group', 'ai_content_master_openrouter_model', array(
            'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => 'meta-llama/llama-3.3-70b-instruct:free',
        ) );

        // ── Gemini ─────────────────────────────────────────────────────────
        register_setting( 'ai_content_master_settings_group', 'ai_content_master_gemini_api_key', array(
            'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => '',
        ) );
        register_setting( 'ai_content_master_settings_group', 'ai_content_master_gemini_model', array(
            'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => 'gemini-2.5-flash',
        ) );

        // ── Section: Provider Selector ─────────────────────────────────────
        add_settings_section( 'ai_cm_provider_section', __( 'Active AI Provider', 'ai-content-master' ),
            array( $this, 'provider_section_callback' ), 'ai-content-master' );
        add_settings_field( 'ai_cm_active_provider', __( 'Use Provider', 'ai-content-master' ),
            array( $this, 'active_provider_field_render' ), 'ai-content-master', 'ai_cm_provider_section' );

        // ── Section: OpenRouter ────────────────────────────────────────────
        add_settings_section( 'ai_content_master_api_settings_section',
            __( 'OpenRouter', 'ai-content-master' ),
            array( $this, 'api_settings_section_callback' ), 'ai-content-master' );
        add_settings_field( 'ai_content_master_api_key_field', __( 'API Key', 'ai-content-master' ),
            array( $this, 'api_key_field_render' ), 'ai-content-master', 'ai_content_master_api_settings_section' );
        add_settings_field( 'ai_content_master_model_field', __( 'Model', 'ai-content-master' ),
            array( $this, 'model_field_render' ), 'ai-content-master', 'ai_content_master_api_settings_section' );

        // ── Section: Gemini ────────────────────────────────────────────────
        add_settings_section( 'ai_cm_gemini_section', __( 'Google Gemini (AI Studio)', 'ai-content-master' ),
            array( $this, 'gemini_section_callback' ), 'ai-content-master' );
        add_settings_field( 'ai_cm_gemini_api_key', __( 'API Key', 'ai-content-master' ),
            array( $this, 'gemini_api_key_field_render' ), 'ai-content-master', 'ai_cm_gemini_section' );
        add_settings_field( 'ai_cm_gemini_model', __( 'Model', 'ai-content-master' ),
            array( $this, 'gemini_model_field_render' ), 'ai-content-master', 'ai_cm_gemini_section' );
    }

    // ── Section Callbacks ──────────────────────────────────────────────────

    public function provider_section_callback() {
        echo '<p>' . esc_html__( 'Choose which AI provider to use. If the active provider is rate-limited, the plugin will automatically fall back to the other configured provider.', 'ai-content-master' ) . '</p>';
    }

    public function active_provider_field_render() {
        $active = get_option( 'ai_content_master_active_provider', 'openrouter' );
        ?>
        <label style="margin-right:20px;">
            <input type="radio" name="ai_content_master_active_provider" value="openrouter" <?php checked( $active, 'openrouter' ); ?>>
            <?php esc_html_e( 'OpenRouter', 'ai-content-master' ); ?>
        </label>
        <label>
            <input type="radio" name="ai_content_master_active_provider" value="gemini" <?php checked( $active, 'gemini' ); ?>>
            <?php esc_html_e( 'Google Gemini (AI Studio)', 'ai-content-master' ); ?>
        </label>
        <p class="description"><?php esc_html_e( 'Both providers can be configured simultaneously for automatic fallback.', 'ai-content-master' ); ?></p>
        <?php
    }

    /**
     * API settings section callback
     */
    public function api_settings_section_callback() {
        echo '<p>' . esc_html__( 'Access hundreds of AI models through a single API key.', 'ai-content-master' ) .
             ' <a href="https://openrouter.ai/keys" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Get your free API key →', 'ai-content-master' ) . '</a></p>';
    }

    public function gemini_section_callback() {
        echo '<p>' . esc_html__( 'Google AI Studio offers a generous free tier: 1,500 requests/day with Gemini 2.0 Flash. No billing required.', 'ai-content-master' ) .
             ' <a href="https://aistudio.google.com/apikey" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Get your free API key →', 'ai-content-master' ) . '</a></p>';
    }

    /**
     * API key field render
     */
    public function api_key_field_render() {
        $api_key = get_option('ai_content_master_openrouter_api_key');
        ?>
        <input type='password' name='ai_content_master_openrouter_api_key' value='<?php echo esc_attr($api_key); ?>' class='regular-text'>
        <p class="description"><?php esc_html_e('Your API key is stored securely and used only for communicating with the OpenRouter API.', 'ai-content-master'); ?></p>
        <?php
    }

    /**
     * Model field render — dynamic dropdown with free-first sorting and Refresh button.
     */
    public function model_field_render() {
        $selected_model = get_option( 'ai_content_master_openrouter_model', 'meta-llama/llama-3.3-70b-instruct:free' );
        // Ambil OpenRouter provider langsung — bukan Provider_Manager.
        $openrouter  = AI_Content_Master::get_instance()->get_component( 'api' )->get_provider( 'openrouter' );
        $models_data = $openrouter->fetch_available_models();
        $has_error   = is_wp_error( $models_data );

        // Build free/paid split.
        $free_models = array();
        $paid_models = array();
        if ( ! $has_error ) {
            foreach ( $models_data as $model_id => $model_info ) {
                if ( $openrouter->is_model_free( $model_info ) ) {
                    $free_models[ $model_id ] = $model_info;
                } else {
                    $paid_models[ $model_id ] = $model_info;
                }
            }
        }
        ?>
        <div id="ai-content-master-model-selector-wrap">
            <select id="ai_content_master_openrouter_model" name="ai_content_master_openrouter_model">

                <?php if ( $has_error ) : ?>
                    <option value="meta-llama/llama-3.3-70b-instruct:free">Meta Llama 3.3 70B Instruct (Free)</option>

                <?php else : ?>

                    <?php if ( ! empty( $free_models ) ) : ?>
                        <optgroup label="✅ <?php esc_attr_e( 'Free Models', 'ai-content-master' ); ?>">
                            <?php foreach ( $free_models as $model_id => $model_info ) : ?>
                                <option
                                    value="<?php echo esc_attr( $model_id ); ?>"
                                    data-free="1"
                                    data-ctx="<?php echo esc_attr( (int) ( $model_info['context_length'] ?? 0 ) ); ?>"
                                    <?php selected( $selected_model, $model_id ); ?>>
                                    <?php echo esc_html( $model_info['name'] ); ?> — FREE
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endif; ?>

                    <?php if ( ! empty( $paid_models ) ) : ?>
                        <optgroup label="💳 <?php esc_attr_e( 'Paid Models', 'ai-content-master' ); ?>">
                            <?php foreach ( $paid_models as $model_id => $model_info ) : ?>
                                <option
                                    value="<?php echo esc_attr( $model_id ); ?>"
                                    data-free="0"
                                    data-ctx="<?php echo esc_attr( (int) ( $model_info['context_length'] ?? 0 ) ); ?>"
                                    <?php selected( $selected_model, $model_id ); ?>>
                                    <?php echo esc_html( $model_info['name'] ); ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endif; ?>

                <?php endif; ?>
            </select>

            <button type="button" id="ai-cm-refresh-models-btn" class="button button-secondary">
                <span class="dashicons dashicons-update"></span>
                <?php esc_html_e( 'Refresh Models', 'ai-content-master' ); ?>
            </button>

            <button type="button" id="ai-cm-ping-test-btn" class="button button-secondary">
                <span class="dashicons dashicons-networking"></span>
                <?php esc_html_e( 'Test Connection', 'ai-content-master' ); ?>
            </button>
        </div>

        <!-- Model info bar (shown dynamically via JS) -->
        <div id="ai-cm-model-info">
            <span id="ai-cm-model-badge" class="ai-cm-badge"></span>
            <span id="ai-cm-model-ctx" class="ai-cm-ctx"></span>
            <span id="ai-cm-model-id-display" style="color:#9ca3af; font-size:11px;"></span>
        </div>

        <p id="ai-cm-model-status"></p>

        <p class="description">
            <?php esc_html_e( 'Free models are listed first and cost nothing to use. Click "Refresh Models" to fetch the latest list from OpenRouter.', 'ai-content-master' ); ?>
        </p>
        <?php
    }

    // ── Gemini Field Renders ───────────────────────────────────────────────

    public function gemini_api_key_field_render() {
        $key = get_option( 'ai_content_master_gemini_api_key' );
        ?>
        <input type="password" name="ai_content_master_gemini_api_key"
               value="<?php echo esc_attr( $key ); ?>" class="regular-text">
        <button type="button" id="ai-cm-gemini-ping-btn" class="button button-secondary" style="margin-left:8px;">
            <span class="dashicons dashicons-networking"></span>
            <?php esc_html_e( 'Test Connection', 'ai-content-master' ); ?>
        </button>
        <p class="description">
            <?php esc_html_e( 'Get your free API key from', 'ai-content-master' ); ?>
            <a href="https://aistudio.google.com/apikey" target="_blank" rel="noopener noreferrer">Google AI Studio</a>.
            <?php esc_html_e( 'Free tier: 1,500 requests/day, no credit card required.', 'ai-content-master' ); ?>
        </p>
        <p id="ai-cm-gemini-ping-status" style="font-size:12px;min-height:18px;"></p>
        <?php
    }

    public function gemini_model_field_render() {
        $selected = get_option( 'ai_content_master_gemini_model', 'gemini-2.0-flash' );
        $api_key  = get_option( 'ai_content_master_gemini_api_key' );
        $api      = AI_Content_Master::get_instance()->get_component( 'api' )->get_provider( 'gemini' );
        $models   = ! empty( $api_key ) ? $api->fetch_available_models() : $api->fetch_available_models();
        ?>
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
            <select name="ai_content_master_gemini_model" id="ai-cm-gemini-model" style="min-width:280px;font-size:13px;">
                <?php foreach ( ( is_wp_error( $models ) ? array() : $models ) as $id => $info ) : ?>
                    <option value="<?php echo esc_attr( $id ); ?>" <?php selected( $selected, $id ); ?>>
                        <?php echo esc_html( $info['name'] ); ?>
                    </option>
                <?php endforeach; ?>
                <?php if ( is_wp_error( $models ) ) : ?>
                    <option value="gemini-3-flash-preview" selected>Gemini 3 Flash Preview</option>
                    <option value="gemini-2.5-flash">Gemini 2.5 Flash</option>
                    <option value="gemini-2.5-flash-lite">Gemini 2.5 Flash Lite</option>
                    <option value="gemini-2.5-pro">Gemini 2.5 Pro</option>
                <?php endif; ?>
            </select>
        </div>
        <p class="description"><?php esc_html_e( 'Recommended: Gemini 3 Flash Preview or Gemini 2.5 Flash — both fast with 1M token context. Note: gemini-2.0-flash is deprecated and will be shut down March 31, 2026.', 'ai-content-master' ); ?></p>
        <?php
    }

    /**
     * Settings page HTML
     */
    public function settings_page_html() {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap" id="ai-content-master-settings-wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('ai_content_master_settings_group');
                do_settings_sections('ai-content-master');
                submit_button(__('Save Settings', 'ai-content-master'));
                ?>
            </form>
        </div>
        <?php
    }
}
