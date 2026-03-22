<?php
/**
 * Admin Meta Box Handler
 *
 * Handles the meta box display and functionality.
 *
 * @package AIContentMaster
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AI_Content_Master_Admin_Meta_Box
 */
class AI_Content_Master_Admin_Meta_Box {

	/**
	 * Initialize the meta box
	 */
	public function init() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
	}

	/**
	 * Add meta box to post editor
	 */
	public function add_meta_box() {
		$post_types = apply_filters( 'ai_content_master_post_types', array( 'post', 'page' ) );

		foreach ( $post_types as $post_type ) {
			add_meta_box(
				'ai_content_master_meta_box',
				__( 'AI Content Master (SGE Enabled)', 'ai-content-master' ),
				array( $this, 'render_meta_box' ),
				$post_type,
				'side',
				'default'
			);
		}
	}

	/**
	 * Render meta box HTML
	 *
	 * @param WP_Post $post Current post object.
	 */
	public function render_meta_box( $post ) {
		// Add nonce for security.
		wp_nonce_field( 'ai_content_master_ajax_nonce', 'ai_content_master_nonce_field' );

		$api_key = get_option( 'ai_content_master_openrouter_api_key' );

		?>
		<div id="ai-content-master-helper">

			<?php if ( empty( $api_key ) ) : ?>
				<p style="color: #d63638; background: #fff8f8; padding: 10px; border-left: 4px solid #d63638; border-radius: 4px;">
					<?php
					printf(
						/* translators: %s: Link to settings page */
						wp_kses_post( __( 'Please <a href="%s">configure your OpenRouter API Key</a> to use the AI Content Master features.', 'ai-content-master' ) ),
						esc_url( admin_url( 'options-general.php?page=ai-content-master' ) )
					);
					?>
				</p>
			<?php else : ?>

				<?php // Article Generator Feature. ?>
				<div style="background-color: #eef5ff; padding: 12px; border-radius: 6px; margin-bottom: 15px; border: 1px solid #c2dbff;">
					<h4 style="margin-top:0; color: #0056b3;"><span class="dashicons dashicons-admin-page"></span> <?php esc_html_e( 'SGE Article Generator', 'ai-content-master' ); ?></h4>
					<p style="font-size: 12px; margin-bottom: 10px;"><?php esc_html_e( 'Generate a full article optimized for AI Overviews and Google Search AI Mode.', 'ai-content-master' ); ?></p>
					<input type="text" id="ai-content-master-article-topic" placeholder="<?php esc_attr_e( 'Enter topic or heading...', 'ai-content-master' ); ?>" style="width: 100%; margin-bottom: 10px; border-radius: 4px;">
					<button type="button" id="ai-content-master-generate-article-btn" class="button button-primary" style="width: 100%; text-align: center; border-radius: 4px;">
						<?php esc_html_e( 'Generate Article', 'ai-content-master' ); ?>
					</button>
					<span id="ai-content-master-generate-article-spinner" class="spinner" style="float: none; vertical-align: middle; display: none; margin-top: 5px;"></span>
					<p id="ai-content-master-generate-article-status" style="margin-top: 5px; font-size: 11px;"></p>
				</div>

				<?php // SEO Analysis Feature. ?>
				<div style="background-color: #f6f7f7; padding: 12px; border-radius: 6px; margin-bottom: 15px; border: 1px solid #dcdcde;">
					<h4 style="margin-top:0; color: #2c3338;"><span class="dashicons dashicons-chart-area"></span> <?php esc_html_e( 'AI Search Analysis', 'ai-content-master' ); ?></h4>
					<p style="font-size: 12px; margin-bottom: 10px;"><?php esc_html_e( 'Analyze content for SGE featured snippet potential and E-E-A-T.', 'ai-content-master' ); ?></p>
					<button type="button" id="ai-content-master-analyze-seo-btn" class="button button-large" style="width: 100%; text-align: center; border-radius: 4px;">
						<?php esc_html_e( 'Analyze for AI Search', 'ai-content-master' ); ?>
					</button>
					<span id="ai-content-master-seo-spinner" class="spinner" style="float: none; vertical-align: middle; display: none; margin-top: 5px;"></span>

					<div id="ai-content-master-seo-results-wrapper" style="border: 1px solid #ccd0d4; padding: 12px; margin-top: 15px; max-height: 450px; overflow-y: auto; display: none; background-color: #fff; border-radius: 4px; font-size: 13px;">
						<h5 style="margin-top:0; border-bottom: 1px solid #eee; padding-bottom: 8px;"><?php esc_html_e( 'Optimization Report:', 'ai-content-master' ); ?></h5>
						<div id="ai-content-master-seo-results-content"></div>
					</div>
				</div>

				<div style="margin-bottom: 20px;">
					<?php // Meta Description Generator. ?>
					<h4 style="margin-bottom: 5px; font-size: 13px;"><?php esc_html_e( 'High-CTR Meta Description', 'ai-content-master' ); ?></h4>
					<button type="button" id="ai-content-master-generate-meta-btn" class="button button-small" style="margin-bottom: 8px;">
						<?php esc_html_e( 'Generate Meta', 'ai-content-master' ); ?>
					</button>
					<span id="ai-content-master-meta-spinner" class="spinner" style="float: none; vertical-align: middle;"></span>
					<textarea id="ai-content-master-meta-result" rows="3" style="width:100%; font-size: 12px; border-radius: 4px;" placeholder="<?php esc_attr_e( 'AI-generated meta description...', 'ai-content-master' ); ?>"></textarea>
				<p id="ai-content-master-meta-status" style="margin-top:4px; font-size:11px; min-height:16px;"></p>
				</div>

				<div style="padding-top: 10px; border-top: 1px solid #eee;">
					<h4 style="margin-bottom: 5px; font-size: 13px;"><?php esc_html_e( 'Content Refinement', 'ai-content-master' ); ?></h4>
					<div style="display: flex; gap: 5px;">
						<button type="button" id="ai-content-master-rewrite-article-btn" class="button button-link-delete" style="font-size: 11px; text-decoration: none;">
							<?php esc_html_e( 'Rewrite Whole Post', 'ai-content-master' ); ?>
						</button>
						<span style="color: #ccc;">|</span>
						<button type="button" id="ai-content-master-rephrase-btn" class="button button-link" style="font-size: 11px; text-decoration: none;">
							<?php esc_html_e( 'Rephrase Selected', 'ai-content-master' ); ?>
						</button>
					</div>
					<span id="ai-content-master-rephrase-spinner" class="spinner" style="float: none; vertical-align: middle;"></span>
					<span id="ai-content-master-rewrite-spinner" class="spinner" style="float: none; vertical-align: middle;"></span>
					<p id="ai-content-master-rephrase-status" style="margin-top: 5px; font-size: 11px; color: green;"></p>
					<p id="ai-content-master-rewrite-status" style="margin-top: 5px; font-size: 11px; color: green;"></p>
				</div>

			<?php endif; ?>

		</div>
		<?php
	}
}
