<?php
/**
 * Meta Description Generator Feature
 *
 * Handles meta description generation functionality.
 *
 * @package AIContentMaster
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AI_Content_Master_Meta_Generator
 */
class AI_Content_Master_Meta_Generator {

    /**
     * Lazy API getter — resolve hanya saat pertama kali dibutuhkan.
     *
     * @return AI_Content_Master_OpenRouter_API
     */
    private function get_api() {
        return AI_Content_Master::get_instance()->get_component( 'api' );
    }

    /**
     * Initialize the feature
     */
    public function init() {
        add_action('wp_ajax_ai_content_master_generate_meta', array($this, 'handle_ajax_request'));
    }

    /**
     * Handle AJAX request for meta description generation
     */
    public function handle_ajax_request() {
        // Debug log for troubleshooting
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('AI Content Master: Meta description generation AJAX request received');
            error_log('POST data: ' . print_r($_POST, true));
        }

        // Security check - PENTING: pastikan nonce action sama dengan yang di JavaScript
        if (!check_ajax_referer('ai_content_master_ajax_nonce', 'security', false)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('AI Content Master: Nonce verification failed for meta description generation');
            }
            wp_send_json_error(array('message' => __('Security check failed.', 'ai-content-master')), 403);
            return;
        }

        // Capability check
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-content-master')), 403);
            return;
        }

        // Get and validate post data
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        if (!$post_id) {
            wp_send_json_error(array('message' => __('Invalid Post ID.', 'ai-content-master')), 400);
            return;
        }

        // Get post content
        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error(array('message' => __('Post not found.', 'ai-content-master')), 404);
            return;
        }

        // Prepare content for meta generation
        $content = $this->prepare_content($post);
        $title = $post->post_title;

        // Generate meta description
        $meta_description = $this->generate_meta_description($title, $content);

        if (is_wp_error($meta_description)) {
            wp_send_json_error(array('message' => $meta_description->get_error_message()), 500);
        } else {
            $clean      = $this->extract_meta_text( $meta_description );
            $seo_plugin = $this->detect_seo_plugin();
            $saved      = $this->save_meta_to_post( $post_id, $clean, $seo_plugin );

            wp_send_json_success(array(
                'meta_description' => $clean,
                'seo_plugin'       => $seo_plugin,
                'auto_saved'       => $saved,
            ));
        }
    }

    /**
     * Generate meta description for given content
     *
     * @param string $title Post title.
     * @param string $content Post content.
     * @return string|WP_Error Meta description or error.
     */
    public function generate_meta_description($title, $content) {
        // Prepare meta description prompt
        $prompt = $this->prepare_meta_prompt($title, $content);

        // Send to API
        return $this->get_api()->send_prompt( $prompt );
    }

    /**
     * Prepare content for meta description generation
     *
     * @param WP_Post $post Post object.
     * @return string Prepared content.
     */
    private function prepare_content($post) {
        $content = wp_strip_all_tags( strip_shortcodes( $post->post_content ) );
        // Truncate ke 1500 karakter — meta desc hanya butuh konteks singkat.
        $content = mb_substr( $content, 0, 1500 );
        if ( empty( trim( $content ) ) ) {
            $content = $post->post_title;
        }
        return $content;
    }

    /**
     * Prepare meta description prompt
     *
     * @param string $title Post title.
     * @param string $content Post content.
     * @return string Meta description prompt.
     */
    /**
     * Extract just the meta description text from AI output.
     * Models often append character counts, explanations, or commentary
     * after the actual meta description — strip all of that.
     *
     * @param string $raw Raw model output.
     * @return string Clean meta description, max 160 chars.
     */
    /**
     * Detect which SEO plugin is active on this WordPress installation.
     *
     * @return string 'yoast' | 'rankmath' | 'seopress' | 'aioseo' | 'none'
     */
    private function detect_seo_plugin() {
        if ( defined( 'WPSEO_VERSION' ) || class_exists( 'WPSEO_Options' ) ) {
            return 'yoast';
        }
        if ( defined( 'RANK_MATH_VERSION' ) || class_exists( 'RankMath' ) ) {
            return 'rankmath';
        }
        if ( defined( 'SEOPRESS_VERSION' ) || class_exists( 'SeoPress' ) ) {
            return 'seopress';
        }
        if ( defined( 'AIOSEO_VERSION' ) || class_exists( 'AIOSEO\Plugin\AIOSEO' ) ) {
            return 'aioseo';
        }
        return 'none';
    }

    /**
     * Save the generated meta description directly to the correct post_meta key
     * based on the active SEO plugin. Falls back to a custom key if none detected.
     *
     * This writes to the SAME key that each SEO plugin reads — so the value
     * appears natively in their UI without any conflict or duplication.
     *
     * @param int    $post_id    The post ID.
     * @param string $meta       Clean meta description text.
     * @param string $seo_plugin Slug of the detected SEO plugin.
     * @return bool True on success.
     */
    private function save_meta_to_post( $post_id, $meta, $seo_plugin ) {
        $meta_keys = array(
            'yoast'    => '_yoast_wpseo_metadesc',
            'rankmath' => 'rank_math_description',
            'seopress' => '_seopress_titles_desc',
            'aioseo'   => '_aioseo_description',
            'none'     => '_ai_content_master_meta_desc', // standalone fallback key
        );

        $key = $meta_keys[ $seo_plugin ] ?? $meta_keys['none'];

        return (bool) update_post_meta( $post_id, $key, sanitize_text_field( $meta ) );
    }

    private function extract_meta_text( $raw ) {
        $text = trim( $raw );

        // Remove surrounding quotes if model wrapped the description.
        $text = trim( $text, '"\'' );

        // Strip anything after a newline (character count notes, explanations, etc.)
        if ( strpos( $text, "\n" ) !== false ) {
            $lines = explode( "\n", $text );
            // Take first non-empty line that looks like a meta description (>30 chars).
            foreach ( $lines as $line ) {
                $line = trim( $line, '"\' ' );
                if ( strlen( $line ) > 30 ) {
                    $text = $line;
                    break;
                }
            }
        }

        // Strip Markdown bold/italic artefacts.
        $text = preg_replace( '/\*\*([^*]+)\*\*/', '$1', $text );
        $text = preg_replace( '/\*([^*]+)\*/', '$1', $text );

        // Hard cap at 160 characters.
        if ( mb_strlen( $text ) > 160 ) {
            $text = mb_substr( $text, 0, 157 ) . '...';
        }

        return trim( $text );
    }

    private function prepare_meta_prompt($title, $content) {
        return sprintf(
            "Generate a concise and compelling SEO meta description (maximum 160 characters) for a blog post titled '%s'. The main content is:\n\n%s",
            esc_html($title),
            $content
        );
    }
}