/**
 * AI Content Master Admin Script
 *
 * Handles AJAX calls for the meta box features.
 */
(function ($) {
    'use strict';

    $(function () {
        // --- Common Helper Functions ---
        function showSpinner(buttonId) {
            $('#' + buttonId).next('.spinner').css('visibility', 'visible').show();
        }

        function hideSpinner(buttonId) {
            $('#' + buttonId).next('.spinner').css('visibility', 'hidden').hide();
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
            var $status = $('#ai-content-master-generate-article-status');
            var topic = $('#ai-content-master-article-topic').val();

            if (!topic) {
                $status.css('color', 'red').text('Please enter a topic.');
                return;
            }

            // Pastikan aiContentMasterAjax sudah di-localize
            if (typeof aiContentMasterAjax === 'undefined') {
                console.error('aiContentMasterAjax is not defined');
                $status.css('color', 'red').text('Configuration error. Please refresh the page.');
                return;
            }

            // Confirm with user before proceeding
            if (!confirm('This will replace your entire article content with a newly generated article. Are you sure?')) {
                return;
            }

            showSpinner(buttonId);
            $status.css('color', 'inherit').text('Generating article... This may take a few moments.');

            $.ajax({
                url: aiContentMasterAjax.ajax_url,
                type: 'POST',
                data: {
                    action: 'ai_content_master_generate_article',
                    security: aiContentMasterAjax.nonce,
                    topic: topic
                },
                success: function (response) {
                    console.log('Generate Article AJAX Success:', response); // Debug log
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
                error: function (jqXHR, textStatus, errorThrown) {
                    console.error('Generate Article AJAX Error Details:', {
                        status: jqXHR.status,
                        statusText: jqXHR.statusText,
                        responseText: jqXHR.responseText,
                        textStatus: textStatus,
                        errorThrown: errorThrown
                    });
                    $status.css('color', 'red').text('AJAX request failed: ' + textStatus + ' (' + jqXHR.status + ')');
                },
                complete: function () {
                    hideSpinner(buttonId);
                }
            });
        });

        // --- SEO Analysis Feature ---
        $('#ai-content-master-analyze-seo-btn').on('click', function () {
            var buttonId = 'ai-content-master-analyze-seo-btn';
            var $resultsWrapper = $('#ai-content-master-seo-results-wrapper');
            var $resultsContent = $('#ai-content-master-seo-results-content');

            // Pastikan aiContentMasterAjax sudah di-localize
            if (typeof aiContentMasterAjax === 'undefined') {
                console.error('aiContentMasterAjax is not defined');
                $resultsContent.html('<p style="color: red;">Configuration error. Please refresh the page.</p>');
                $resultsWrapper.show();
                return;
            }

            showSpinner(buttonId);
            $resultsContent.html('<p>Analyzing...</p>');
            $resultsWrapper.show();

            $.ajax({
                url: aiContentMasterAjax.ajax_url,
                type: 'POST',
                data: {
                    action: 'ai_content_master_analyze_seo',
                    security: aiContentMasterAjax.nonce,
                    post_id: aiContentMasterAjax.post_id
                },
                success: function (response) {
                    console.log('SEO Analysis AJAX Success:', response); // Debug log
                    if (response.success) {
                        // The response from the API is already HTML formatted
                        $resultsContent.html(response.data.analysis_result);
                    } else {
                        console.error('API Error:', response.data);
                        $resultsContent.html('<p style="color: red;"><strong>Error:</strong> ' + (response.data.message || 'Unknown error') + '</p>');
                    }
                },
                error: function (jqXHR, textStatus, errorThrown) {
                    console.error('SEO Analysis AJAX Error Details:', {
                        status: jqXHR.status,
                        statusText: jqXHR.statusText,
                        responseText: jqXHR.responseText,
                        textStatus: textStatus,
                        errorThrown: errorThrown
                    });
                    $resultsContent.html('<p style="color: red;"><strong>AJAX Error:</strong> Unable to connect to server. (' + textStatus + ' - ' + jqXHR.status + ')</p>');
                },
                complete: function () {
                    hideSpinner(buttonId);
                }
            });
        });

        // --- Meta Description Generator ---
        $('#ai-content-master-generate-meta-btn').on('click', function () {
            var buttonId = 'ai-content-master-generate-meta-btn';

            // Pastikan aiContentMasterAjax sudah di-localize
            if (typeof aiContentMasterAjax === 'undefined') {
                console.error('aiContentMasterAjax is not defined');
                alert('Configuration error. Please refresh the page.');
                return;
            }

            showSpinner(buttonId);

            $.ajax({
                url: aiContentMasterAjax.ajax_url,
                type: 'POST',
                data: {
                    action: 'ai_content_master_generate_meta',
                    security: aiContentMasterAjax.nonce,
                    post_id: aiContentMasterAjax.post_id
                },
                success: function (response) {
                    console.log('Meta Gen AJAX Success:', response); // Debug log
                    if (response.success) {
                        $('#ai-content-master-meta-result').val(response.data.meta_description);
                    } else {
                        console.error('API Error:', response.data);
                        alert('Error: ' + (response.data.message || 'Unknown error'));
                    }
                },
                error: function (jqXHR, textStatus, errorThrown) {
                    console.error('Meta Gen AJAX Error Details:', {
                        status: jqXHR.status,
                        statusText: jqXHR.statusText,
                        responseText: jqXHR.responseText,
                        textStatus: textStatus,
                        errorThrown: errorThrown
                    });
                    alert('AJAX request failed: ' + textStatus + ' (' + jqXHR.status + ')');
                },
                complete: function () {
                    hideSpinner(buttonId);
                }
            });
        });

        // --- Rephrase Text Feature (Gutenberg Integration) ---
        $('#ai-content-master-rephrase-btn').on('click', function () {
            var buttonId = 'ai-content-master-rephrase-btn';
            var $status = $('#ai-content-master-rephrase-status');

            // Pastikan aiContentMasterAjax sudah di-localize
            if (typeof aiContentMasterAjax === 'undefined') {
                console.error('aiContentMasterAjax is not defined');
                $status.css('color', 'red').text('Configuration error. Please refresh the page.');
                return;
            }

            // Get selected text from Gutenberg
            var selectedBlock = wp.data.select('core/block-editor').getSelectedBlock();
            if (!selectedBlock || !selectedBlock.attributes.content) {
                $status.css('color', 'red').text('Please select a block with text.');
                return;
            }
            var selectedText = selectedBlock.attributes.content;

            showSpinner(buttonId);
            $status.css('color', 'inherit').text('Rephrasing...');

            $.ajax({
                url: aiContentMasterAjax.ajax_url,
                type: 'POST',
                data: {
                    action: 'ai_content_master_rephrase_text',
                    security: aiContentMasterAjax.nonce,
                    selected_text: selectedText
                },
                success: function (response) {
                    console.log('Rephrase AJAX Success:', response); // Debug log
                    if (response.success) {
                        // Replace the content of the selected block
                        wp.data.dispatch('core/block-editor').updateBlockAttributes(selectedBlock.clientId, { content: response.data.rephrased_text });
                        $status.css('color', 'green').text('Text rephrased successfully!');
                    } else {
                        console.error('API Error:', response.data);
                        $status.css('color', 'red').text('Error: ' + (response.data.message || 'Unknown error'));
                    }
                },
                error: function (jqXHR, textStatus, errorThrown) {
                    console.error('Rephrase AJAX Error Details:', {
                        status: jqXHR.status,
                        statusText: jqXHR.statusText,
                        responseText: jqXHR.responseText,
                        textStatus: textStatus,
                        errorThrown: errorThrown
                    });
                    $status.css('color', 'red').text('AJAX request failed: ' + textStatus + ' (' + jqXHR.status + ')');
                },
                complete: function () {
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
            $status.css('color', 'inherit').text('Rewriting article...');

            $.ajax({
                url: aiContentMasterAjax.ajax_url,
                type: 'POST',
                data: {
                    action: 'ai_content_master_rewrite_article',
                    security: aiContentMasterAjax.nonce,
                    post_id: aiContentMasterAjax.post_id
                },
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
                error: function (jqXHR, textStatus, errorThrown) {
                    console.error('Rewrite Article AJAX Error Details:', {
                        status: jqXHR.status,
                        statusText: jqXHR.statusText,
                        responseText: jqXHR.responseText,
                        textStatus: textStatus,
                        errorThrown: errorThrown
                    });
                    $status.css('color', 'red').text('AJAX request failed: ' + textStatus + ' (' + jqXHR.status + ')');
                },
                complete: function () {
                    hideSpinner(buttonId);
                }
            });
        });
    });

})(jQuery);