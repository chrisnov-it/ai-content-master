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
        register_setting(
            'ai_content_master_settings_group',
            'ai_content_master_openrouter_api_key',
            array(
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => '',
            )
        );

        register_setting(
            'ai_content_master_settings_group',
            'ai_content_master_openrouter_model',
            array(
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => 'openai/gpt-oss-120b:free',
            )
        );

        add_settings_section(
            'ai_content_master_api_settings_section',
            __('API Configuration', 'ai-content-master'),
            array($this, 'api_settings_section_callback'),
            'ai-content-master'
        );

        add_settings_field(
            'ai_content_master_api_key_field',
            __('OpenRouter API Key', 'ai-content-master'),
            array($this, 'api_key_field_render'),
            'ai-content-master',
            'ai_content_master_api_settings_section'
        );

        add_settings_field(
            'ai_content_master_model_field',
            __('AI Model', 'ai-content-master'),
            array($this, 'model_field_render'),
            'ai-content-master',
            'ai_content_master_api_settings_section'
        );
    }

    /**
     * API settings section callback
     */
    public function api_settings_section_callback() {
        echo '<p>' . esc_html__('Enter your OpenRouter API Key below. You can generate one from', 'ai-content-master') .
             ' <a href="https://openrouter.ai/keys" target="_blank" rel="noopener noreferrer">OpenRouter</a>.</p>';
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
     * Model field render
     */
    public function model_field_render() {
        $selected_model = get_option('ai_content_master_openrouter_model', 'openai/gpt-oss-120b:free');

        // Get API instance
        $api = AI_Content_Master::get_instance()->get_component('api');
		$models_data = $api->fetch_available_models();

		if ( is_wp_error( $models_data ) ) {
			// Fallback to basic message if API fails.
			?>
			<p><?php esc_html_e( 'Unable to load models. Please save your API key first and refresh this page.', 'ai-content-master' ); ?></p>
			<select name='ai_content_master_openrouter_model' class='regular-text'>
				<option value='openai/gpt-oss-120b:free' <?php selected( $selected_model, 'openai/gpt-oss-120b:free' ); ?>>
					OpenAI GPT-OSS 120B (Free)
				</option>
			</select>
			<?php
		} else {
			// Separate free and paid models.
			$free_models = array();
			$paid_models = array();

			foreach ( $models_data as $model_id => $model_info ) {
				if ( $api->is_model_free( $model_info ) ) {
					$free_models[ $model_id ] = $model_info;
				} else {
					$paid_models[ $model_id ] = $model_info;
				}
			}

            ?>
            <select name='ai_content_master_openrouter_model' class='regular-text'>
                <?php if (!empty($free_models)) : ?>
                    <optgroup label="<?php esc_attr_e('Free Models', 'ai-content-master'); ?>">
                        <?php foreach ($free_models as $model_id => $model_info) : ?>
                            <option value='<?php echo esc_attr($model_id); ?>' <?php selected($selected_model, $model_id); ?>>
                                <?php echo esc_html($model_info['name'] . ' (Free)'); ?>
                            </option>
                        <?php endforeach; ?>
                    </optgroup>
                <?php endif; ?>

                <?php if (!empty($paid_models)) : ?>
                    <optgroup label="<?php esc_attr_e('Paid Models', 'ai-content-master'); ?>">
                        <?php foreach ($paid_models as $model_id => $model_info) : ?>
                            <option value='<?php echo esc_attr($model_id); ?>' <?php selected($selected_model, $model_id); ?>>
                                <?php echo esc_html($model_info['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </optgroup>
                <?php endif; ?>
            </select>
            <?php
        }
        ?>
        <p class="description">
            <?php esc_html_e('Select the AI model to use for content generation.', 'ai-content-master'); ?>
            <?php if (!is_wp_error($models_data)) : ?>
                <br><?php esc_html_e('Free models are highlighted and have no usage costs.', 'ai-content-master'); ?>
            <?php endif; ?>
        </p>
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
        <div class="wrap">
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
