<?php
/**
 * Admin Scripts Handler
 *
 * Handles JavaScript and CSS enqueuing for admin area.
 *
 * @package AIContentMaster
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AI_Content_Master_Admin_Scripts
 */
class AI_Content_Master_Admin_Scripts {

    /**
     * Initialize the admin scripts
     */
    public function init() {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    /**
     * Enqueue admin scripts and styles.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_admin_scripts( $hook ) {
        $is_post_screen     = in_array( $hook, array( 'post.php', 'post-new.php' ), true );
        $is_settings_screen = ( 'settings_page_ai-content-master' === $hook );

        if ( ! $is_post_screen && ! $is_settings_screen ) {
            return;
        }

        $version = AI_Content_Master::get_instance()->get_version();

        // Always enqueue shared CSS.
        wp_enqueue_style(
            'ai-content-master-admin-css',
            AI_CONTENT_MASTER_URL . 'css/admin.css',
            array( 'dashicons' ),
            $version
        );

        // Shared JS data.
        $localize_data = array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'ai_content_master_ajax_nonce' ),
            'post_id'  => get_the_ID(),
            'strings'  => array(
                'analyzing'          => __( 'Analyzing...', 'ai-content-master' ),
                'generating'         => __( 'Generating...', 'ai-content-master' ),
                'rephrasing'         => __( 'Rephrasing...', 'ai-content-master' ),
                'rewriting'          => __( 'Rewriting article...', 'ai-content-master' ),
                'confirm_rewrite'    => __( 'This will replace your entire article content with a rewritten version. Are you sure?', 'ai-content-master' ),
                'error_generic'      => __( 'An error occurred. Please try again.', 'ai-content-master' ),
                'success_rewrite'    => __( 'Article rewritten successfully!', 'ai-content-master' ),
                'success_rephrase'   => __( 'Text rephrased successfully!', 'ai-content-master' ),
                'refreshing_models'  => __( 'Fetching latest models…', 'ai-content-master' ),
                'models_refreshed'   => __( 'Model list updated!', 'ai-content-master' ),
                'models_error'       => __( 'Could not fetch models. Using cached list.', 'ai-content-master' ),
                'ctx_tokens'         => __( 'Context:', 'ai-content-master' ),
            ),
        );

        if ( $is_post_screen ) {
            wp_enqueue_script(
                'ai-content-master-admin-js',
                AI_CONTENT_MASTER_URL . 'js/admin.js',
                array( 'jquery', 'wp-data', 'wp-blocks', 'wp-editor' ),
                $version,
                true
            );
            wp_localize_script( 'ai-content-master-admin-js', 'aiContentMasterAjax', $localize_data );
        }

        if ( $is_settings_screen ) {
            // dashicons adalah stylesheet, bukan script — tidak boleh jadi JS dependency.
            // WordPress 6.9.1 sekarang strict soal ini.
            wp_enqueue_script(
                'ai-content-master-settings-js',
                AI_CONTENT_MASTER_URL . 'js/settings.js',
                array( 'jquery' ),
                $version,
                true
            );
            // Pastikan dashicons stylesheet ter-enqueue terpisah.
            wp_enqueue_style( 'dashicons' );
            wp_localize_script( 'ai-content-master-settings-js', 'aiContentMasterAjax', $localize_data );
        }
    }
}