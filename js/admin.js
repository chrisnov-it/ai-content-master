/**
 * AI Content Master Admin Script
 *
 * Handles AJAX calls for the meta box features.
 */
(function ($) {
    'use strict';

    $(function () {
        // --- Common Helper Functions ---

        // Track in-flight requests to prevent duplicate submissions.
        var activeRequests = {};

        function isRequesting(key) {
            return !!activeRequests[key];
        }

        function setRequesting(key, jqxhr) {
            activeRequests[key] = jqxhr || true;
        }

        function clearRequesting(key) {
            delete activeRequests[key];
        }

        function showSpinner(buttonId) {
            var $btn = $('#' + buttonId);
            $btn.prop('disabled', true);
            $btn.next('.spinner').css('visibility', 'visible').show();
        }

        function hideSpinner(buttonId) {
            var $btn = $('#' + buttonId);
            $btn.prop('disabled', false);
            $btn.next('.spinner').css('visibility', 'hidden').hide();
        }

        function getEditorContent() {
            if (wp.data && wp.data.select('core/editor')) {
                return wp.data.select('core/editor').getEditedPostContent();
            }
            // Fallback for classic editor
            if (typeof tinymce !== 'undefined' && tinymce.get('content')) {
                return tinymce.get('content').getContent();
            }
            return $('#content').val();
        }

        // --- Article Generator Feature ---
        $('#ai-content-master-generate-article-btn').on('click', function () {
            var buttonId = 'ai-content-master-generate-article-btn';
            var reqKey   = 'generate_article';
            var $status  = $('#ai-content-master-generate-article-status');
            var topic    = $('#ai-content-master-article-topic').val();

            if (isRequesting(reqKey)) {
                $status.css('color', 'orange').text('Already generating — please wait...');
                return;
            }

            if (!topic) {
                $status.css('color', 'red').text('Please enter a topic.');
                return;
            }

            if (typeof aiContentMasterAjax === 'undefined') {
                $status.css('color', 'red').text('Configuration error. Please refresh the page.');
                return;
            }

            if (!confirm('This will replace your entire article content with a newly generated article. Are you sure?')) {
                return;
            }

            showSpinner(buttonId);
            $status.css('color', '#2271b1').text('⏳ Generating article... Free models may take up to 60 seconds.');

            var jqxhr = $.ajax({
                url:     aiContentMasterAjax.ajax_url,
                type:    'POST',
                timeout: 130000, // 130s — slightly above PHP timeout
                data: {
                    action:   'ai_content_master_generate_article',
                    security: aiContentMasterAjax.nonce,
                    topic:    topic
                },
                beforeSend: function () {
                    setRequesting(reqKey, jqxhr);
                },
                success: function (response) {
                    if (response.success) {
                        var generatedContent = response.data.generated_article;
                        var articleTitle = '';

                        // Attempt to extract H1 tag for the title
                        var titleMatch = generatedContent.match(/<h1[^>]*>([^<]+)<\/h1>/i);
                        if (titleMatch && titleMatch[1]) {
                            articleTitle = titleMatch[1];
                            // Remove the H1 tag from the content to avoid duplicate titles
                            generatedContent = generatedContent.replace(/<h1[^>]*>.*?<\/h1>/i, '').trim();
                        }

                        // Update post title
                        if (articleTitle && wp.data && wp.data.dispatch('core/editor')) {
                            wp.data.dispatch('core/editor').editPost({ title: articleTitle });
                        } else if (articleTitle) {
                            $('#title').val(articleTitle); // Classic editor
                        }

                        // Update post content
                        if (wp.data && wp.data.dispatch('core/editor')) {
                            // Gutenberg editor
                            wp.data.dispatch('core/editor').editPost({ content: generatedContent });
                        } else if (typeof tinymce !== 'undefined' && tinymce.get('content')) {
                            // Classic editor
                            tinymce.get('content').setContent(generatedContent);
                        } else {
                            // Fallback
                            $('#content').val(generatedContent);
                        }

                        $status.css('color', 'green').text('Article generated successfully!');
                    } else {
                        console.error('API Error:', response.data);
                        $status.css('color', 'red').text('Error: ' + (response.data.message || 'Unknown error'));
                    }
                },
                error: function (jqXHR, textStatus) {
                    var msg = textStatus === 'timeout'
                        ? 'Request timed out. The model may be overloaded — try again or switch to a faster model.'
                        : 'AJAX error: ' + textStatus + ' (' + jqXHR.status + ')';
                    $status.css('color', 'red').text(msg);
                },
                complete: function () {
                    clearRequesting(reqKey);
                    hideSpinner(buttonId);
                }
            });
        });

        // --- SEO Analysis Feature ---
        $('#ai-content-master-analyze-seo-btn').on('click', function () {
            var buttonId = 'ai-content-master-analyze-seo-btn';
            var reqKey   = 'analyze_seo';
            var $resultsWrapper = $('#ai-content-master-seo-results-wrapper');
            var $resultsContent = $('#ai-content-master-seo-results-content');

            if (isRequesting(reqKey)) {
                $resultsContent.html('<p style="color:orange;">Analysis in progress — please wait...</p>');
                $resultsWrapper.show();
                return;
            }

            if (typeof aiContentMasterAjax === 'undefined') {
                $resultsContent.html('<p style="color:red;">Configuration error. Please refresh the page.</p>');
                $resultsWrapper.show();
                return;
            }

            showSpinner(buttonId);
            $resultsContent.html('<p>⏳ Analyzing content for AI Search & SGE... This may take up to 60 seconds.</p>');
            $resultsWrapper.show();

            $.ajax({
                url:     aiContentMasterAjax.ajax_url,
                type:    'POST',
                timeout: 130000,
                data: {
                    action:   'ai_content_master_analyze_seo',
                    security: aiContentMasterAjax.nonce,
                    post_id:  aiContentMasterAjax.post_id
                },
                beforeSend: function () { setRequesting(reqKey); },
                success: function (response) {
                    if (response.success) {
                        $resultsContent.html(response.data.analysis_result);
                    } else {
                        $resultsContent.html('<p style="color:red;"><strong>Error:</strong> ' + (response.data.message || 'Unknown error') + '</p>');
                    }
                },
                error: function (jqXHR, textStatus) {
                    var msg = textStatus === 'timeout'
                        ? 'Request timed out. Try switching to a faster model.'
                        : 'AJAX error: ' + textStatus + ' (' + jqXHR.status + ')';
                    $resultsContent.html('<p style="color:red;"><strong>Error:</strong> ' + msg + '</p>');
                },
                complete: function () {
                    clearRequesting(reqKey);
                    hideSpinner(buttonId);
                }
            });
        });

        // --- Meta Description Generator ---
        $('#ai-content-master-generate-meta-btn').on('click', function () {
            var buttonId = 'ai-content-master-generate-meta-btn';
            var reqKey   = 'generate_meta';
            var $result  = $('#ai-content-master-meta-result');

            if (isRequesting(reqKey)) return;

            if (typeof aiContentMasterAjax === 'undefined') {
                $result.val('Configuration error. Please refresh the page.');
                return;
            }

            showSpinner(buttonId);
            $result.val('⏳ Generating meta description...');

            $.ajax({
                url:     aiContentMasterAjax.ajax_url,
                type:    'POST',
                timeout: 130000,
                data: {
                    action:   'ai_content_master_generate_meta',
                    security: aiContentMasterAjax.nonce,
                    post_id:  aiContentMasterAjax.post_id
                },
                beforeSend: function () { setRequesting(reqKey); },
                success: function (response) {
                    if (response.success) {
                        var meta       = response.data.meta_description;
                        var seoPlugin  = response.data.seo_plugin  || 'none';
                        var autoSaved  = response.data.auto_saved  || false;

                        // 1. Always show in our textarea.
                        $result.val(meta);

                        // 2. Update the SEO plugin UI field in real-time (if present).
                        updateSeoPluginField(seoPlugin, meta);

                        // 3. Show save confirmation.
                        var savedMsg = autoSaved
                            ? ' ✅ Auto-saved to ' + seoPluginLabel(seoPlugin) + '.'
                            : '';
                        $('#ai-content-master-meta-status').css('color', 'green')
                            .text('Meta description generated!' + savedMsg);
                    } else {
                        $result.val('Error: ' + (response.data.message || 'Unknown error'));
                    }
                },
                error: function (jqXHR, textStatus) {
                    var msg = textStatus === 'timeout' ? 'Timed out — try a faster model.' : 'Error: ' + textStatus;
                    $result.val(msg);
                },
                complete: function () {
                    clearRequesting(reqKey);
                    hideSpinner(buttonId);
                }
            });
        });

        // --- SGE Optimize Feature (One-shot: analyze + rewrite in one API call) ---
        // Debug: confirm SGE Optimize button found in DOM.
        if ($('#ai-content-master-sge-optimize-btn').length === 0) {
            console.warn('AI Content Master: #ai-content-master-sge-optimize-btn not found in DOM.');
        }

        $('#ai-content-master-sge-optimize-btn').on('click', function () {
            var buttonId = 'ai-content-master-sge-optimize-btn';
            var reqKey   = 'sge_optimize';
            var $btn     = $(this);
            var $status  = $('#ai-content-master-sge-status');
            var $spinner = $('#ai-content-master-seo-spinner');

            if (isRequesting(reqKey)) {
                $status.css('color', 'orange').text('Optimization in progress — please wait...');
                return;
            }

            // Use button state instead of confirm() dialog.
            // confirm() is blocked inside Gutenberg iframe context (WP 6.7+).
            if ( $btn.data('confirm-pending') ) {
                // Second click = confirmed, proceed.
                $btn.removeData('confirm-pending').text('SGE Optimize');
            } else {
                // First click = ask for confirmation via button text.
                $btn.data('confirm-pending', true)
                    .text('⚠️ Click again to confirm');
                setTimeout(function() {
                    $btn.removeData('confirm-pending').text('SGE Optimize');
                }, 4000);
                return;
            }

            // Disable both SEO section buttons during request.
            $('#ai-content-master-analyze-seo-btn, #ai-content-master-sge-optimize-btn').prop('disabled', true);
            $spinner.css('visibility', 'visible').show();
            $status.css('color', '#2271b1').text('⏳ Optimizing for AI Search & SGE... This may take up to 60 seconds.');

            $.ajax({
                url:     aiContentMasterAjax.ajax_url,
                type:    'POST',
                timeout: 130000,
                data: {
                    action:   'ai_content_master_sge_optimize',
                    security: aiContentMasterAjax.nonce,
                    post_id:  aiContentMasterAjax.post_id
                },
                beforeSend: function () { setRequesting(reqKey); },
                success: function (response) {
                    if (response.success) {
                        var optimized = response.data.optimized_content;

                        // Update editor — same pattern as Article Generator.
                        if (wp.data && wp.data.dispatch('core/editor')) {
                            wp.data.dispatch('core/editor').editPost({ content: optimized });
                        } else if (typeof tinymce !== 'undefined' && tinymce.get('content')) {
                            tinymce.get('content').setContent(optimized);
                        } else {
                            $('#content').val(optimized);
                        }

                        $status.css('color', 'green').text('✅ Article optimized for SGE! Review changes before publishing.');
                    } else {
                        $status.css('color', 'red').text('Error: ' + (response.data.message || 'Unknown error'));
                    }
                },
                error: function (jqXHR, textStatus) {
                    var msg = textStatus === 'timeout'
                        ? 'Timed out — try a faster model.'
                        : 'AJAX error: ' + textStatus;
                    $status.css('color', 'red').text(msg);
                },
                complete: function () {
                    clearRequesting(reqKey);
                    $('#ai-content-master-analyze-seo-btn').prop('disabled', false);
                    $btn.prop('disabled', false).text('SGE Optimize');
                    $spinner.css('visibility', 'hidden').hide();
                }
            });
        });

        // --- SEO Plugin UI Sync Helpers ---

        function seoPluginLabel(slug) {
            var labels = {
                'yoast'    : 'Yoast SEO',
                'rankmath' : 'Rank Math',
                'seopress' : 'SEOPress',
                'aioseo'   : 'All in One SEO',
                'none'     : 'post meta'
            };
            return labels[slug] || 'post meta';
        }

        function updateSeoPluginField(seoPlugin, meta) {
            switch (seoPlugin) {

                case 'yoast':
                    // Yoast stores meta in Redux (Gutenberg) or a hidden textarea (Classic).
                    if (wp.data && wp.data.dispatch('yoast-seo/editor')) {
                        // Gutenberg + Yoast: dispatch to Yoast Redux store.
                        wp.data.dispatch('yoast-seo/editor').updateData({ description: meta });
                    } else {
                        // Classic Editor: Yoast renders a textarea with id #yoast_wpseo_metadesc.
                        var $field = $('#yoast_wpseo_metadesc');
                        if ($field.length) {
                            $field.val(meta).trigger('input');
                        }
                    }
                    break;

                case 'rankmath':
                    // Rank Math uses different store names across versions.
                    // Try each approach in order until one works.
                    var rmDispatched = false;

                    // v1.0.54+ store name
                    if ( ! rmDispatched && wp.data && wp.data.dispatch('rank-math') ) {
                        try {
                            wp.data.dispatch('rank-math').updateMeta('description', meta);
                            rmDispatched = true;
                        } catch(e) {}
                    }
                    // Older versions may use 'rank-math/post'
                    if ( ! rmDispatched && wp.data && wp.data.dispatch('rank-math/post') ) {
                        try {
                            wp.data.dispatch('rank-math/post').updateMeta('description', meta);
                            rmDispatched = true;
                        } catch(e) {}
                    }
                    // Classic Editor fallback: Rank Math renders a textarea
                    if ( ! rmDispatched ) {
                        var $rm = $('textarea#rank-math-description, input#rank-math-description, #rank_math_description');
                        if ( $rm.length ) {
                            $rm.val(meta).trigger('input').trigger('change');
                            rmDispatched = true;
                        }
                    }
                    // Gutenberg: Rank Math contenteditable div fallback
                    if ( ! rmDispatched ) {
                        var $rmDiv = $('.rank-math-description .components-textarea-control__input, [data-cy="description"]');
                        if ( $rmDiv.length ) {
                            $rmDiv.val(meta).trigger('input').trigger('change');
                        }
                    }
                    break;

                case 'seopress':
                    var $sp = $('#seopress_titles_desc');
                    if ($sp.length) {
                        $sp.val(meta).trigger('change');
                    }
                    break;

                case 'aioseo':
                    // AIOSEO uses a custom React component — best effort via input event.
                    var $aio = $('textarea[name="aioseo_description"], #aioseo-description');
                    if ($aio.length) {
                        $aio.val(meta).trigger('input').trigger('change');
                    }
                    break;

                default:
                    // No SEO plugin — nothing extra to update, post_meta already saved.
                    break;
            }
        }

        // --- Rephrase Text Feature (Gutenberg + Classic Editor) ---
        $('#ai-content-master-rephrase-btn').on('click', function () {
            var buttonId = 'ai-content-master-rephrase-btn';
            var $status = $('#ai-content-master-rephrase-status');

            // Pastikan aiContentMasterAjax sudah di-localize
            if (typeof aiContentMasterAjax === 'undefined') {
                console.error('aiContentMasterAjax is not defined');
                $status.css('color', 'red').text('Configuration error. Please refresh the page.');
                return;
            }

            var selectedText = '';
            var selectedBlock = null;

            // Try Gutenberg first
            if (wp.data && wp.data.select('core/block-editor')) {
                selectedBlock = wp.data.select('core/block-editor').getSelectedBlock();
                if (selectedBlock && selectedBlock.attributes.content) {
                    selectedText = selectedBlock.attributes.content;
                }
            }

            // Fallback: Classic Editor selected text
            if (!selectedText) {
                if (typeof tinymce !== 'undefined' && tinymce.get('content') && tinymce.get('content').selection) {
                    selectedText = tinymce.get('content').selection.getContent({ format: 'html' });
                }
            }

            if (!selectedText) {
                $status.css('color', 'red').text('Please select a paragraph block (Gutenberg) or highlight text (Classic Editor).');
                return;
            }

            showSpinner(buttonId);
            $status.css('color', '#2271b1').text('⏳ Rephrasing...');

            $.ajax({
                url:     aiContentMasterAjax.ajax_url,
                type:    'POST',
                timeout: 130000,
                data: {
                    action:        'ai_content_master_rephrase_text',
                    security:      aiContentMasterAjax.nonce,
                    selected_text: selectedText
                },
                beforeSend: function () { setRequesting('rephrase'); },
                success: function (response) {
                    console.log('Rephrase AJAX Success:', response); // Debug log
                    if (response.success) {
                        // Replace content: Gutenberg block or Classic Editor selection
                        if (selectedBlock && wp.data && wp.data.dispatch('core/block-editor')) {
                            wp.data.dispatch('core/block-editor').updateBlockAttributes(selectedBlock.clientId, { content: response.data.rephrased_text });
                        } else if (typeof tinymce !== 'undefined' && tinymce.get('content')) {
                            tinymce.get('content').selection.setContent(response.data.rephrased_text);
                        }
                        $status.css('color', 'green').text('Text rephrased successfully!');
                    } else {
                        console.error('API Error:', response.data);
                        $status.css('color', 'red').text('Error: ' + (response.data.message || 'Unknown error'));
                    }
                },
                error: function (jqXHR, textStatus) {
                    var msg = textStatus === 'timeout' ? 'Timed out — try a faster model.' : 'Error: ' + textStatus;
                    $status.css('color', 'red').text(msg);
                },
                complete: function () {
                    clearRequesting('rephrase');
                    hideSpinner(buttonId);
                }
            });
        });

        // --- Rewrite Full Article Feature ---
        $('#ai-content-master-rewrite-article-btn').on('click', function () {
            var buttonId = 'ai-content-master-rewrite-article-btn';
            var $status = $('#ai-content-master-rewrite-status');

            // Pastikan aiContentMasterAjax sudah di-localize
            if (typeof aiContentMasterAjax === 'undefined') {
                console.error('aiContentMasterAjax is not defined');
                $status.css('color', 'red').text('Configuration error. Please refresh the page.');
                return;
            }

            // Confirm with user before proceeding
            if (!confirm('This will replace your entire article content with a rewritten version. Are you sure you want to continue?')) {
                return;
            }

            showSpinner(buttonId);
            $status.css('color', '#2271b1').text('⏳ Rewriting article... Free models may take up to 60 seconds.');

            $.ajax({
                url:     aiContentMasterAjax.ajax_url,
                type:    'POST',
                timeout: 130000,
                data: {
                    action:   'ai_content_master_rewrite_article',
                    security: aiContentMasterAjax.nonce,
                    post_id:  aiContentMasterAjax.post_id
                },
                beforeSend: function () { setRequesting('rewrite'); },
                success: function (response) {
                    console.log('Rewrite Article AJAX Success:', response); // Debug log
                    if (response.success) {
                        // Replace the entire editor content with the rewritten content
                        if (wp.data && wp.data.dispatch('core/editor')) {
                            // Gutenberg editor
                            wp.data.dispatch('core/editor').editPost({ content: response.data.rewritten_content });
                        } else if (typeof tinymce !== 'undefined' && tinymce.get('content')) {
                            // Classic editor
                            tinymce.get('content').setContent(response.data.rewritten_content);
                        } else {
                            // Fallback
                            $('#content').val(response.data.rewritten_content);
                        }

                        $status.css('color', 'green').text('Article rewritten successfully!');
                    } else {
                        console.error('API Error:', response.data);
                        $status.css('color', 'red').text('Error: ' + (response.data.message || 'Unknown error'));
                    }
                },
                error: function (jqXHR, textStatus) {
                    var msg = textStatus === 'timeout' ? 'Timed out — try a faster model.' : 'Error: ' + textStatus;
                    $status.css('color', 'red').text(msg);
                },
                complete: function () {
                    clearRequesting('rewrite');
                    hideSpinner(buttonId);
                }
            });
        });
    });

})(jQuery);