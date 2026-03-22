/**
 * AI Content Master - Settings Page Script
 *
 * Handles the dynamic model selector on the Settings page:
 *  - Shows a live info bar (FREE/PAID badge + context length) when a model is selected.
 *  - "Refresh Models" button: calls AJAX, invalidates cache, rebuilds the <select> in-place.
 *
 * @package AIContentMaster
 * @since 1.1.0
 */
(function ($) {
    'use strict';

    /* ─── Helpers ─────────────────────────────────────────────────────────── */

    var $select  = null;
    var $info    = null;
    var $badge   = null;
    var $ctx     = null;
    var $idLabel = null;
    var $status  = null;
    var $refresh = null;

    /**
     * Format a raw context-length integer to a human-readable string.
     * e.g.  131072 → "128K tokens"   1048576 → "1M tokens"
     */
    function formatCtx(tokens) {
        if (!tokens || tokens <= 0) return '';
        if (tokens >= 1000000) {
            return (tokens / 1000000).toFixed(tokens % 1000000 === 0 ? 0 : 1) + 'M tokens';
        }
        if (tokens >= 1000) {
            return Math.round(tokens / 1024) + 'K tokens';
        }
        return tokens + ' tokens';
    }

    /**
     * Update the model info bar beneath the dropdown based on the currently
     * selected <option>.
     */
    function updateInfoBar() {
        if (!$select || !$select.length) return;

        var $opt    = $select.find('option:selected');
        var modelId = $opt.val();

        if (!modelId) {
            $info.removeClass('visible');
            return;
        }

        var isFree = $opt.data('free') == '1';   // data-free attribute set in PHP
        var ctx    = parseInt($opt.data('ctx'), 10) || 0;

        // Badge
        if (isFree) {
            $badge.removeClass('paid').addClass('free').text('FREE');
        } else {
            $badge.removeClass('free').addClass('paid').text('PAID');
        }

        // Context length
        var ctxText = formatCtx(ctx);
        $ctx.text(ctxText ? (aiContentMasterAjax.strings.ctx_tokens + ' ' + ctxText) : '');

        // Model ID in small print
        $idLabel.text(modelId);

        $info.addClass('visible');
    }

    /**
     * Rebuild the <select> in-place from the AJAX response payload.
     *
     * @param {Object} data  The `data` object from wp_send_json_success:
     *                       { free: [{id, name, context_length}…], paid: […] }
     * @param {string} currentVal  The model ID that should remain selected.
     */
    function rebuildSelect(data, currentVal) {
        $select.empty();

        function makeOption(model) {
            return $('<option>')
                .val(model.id)
                .attr('data-free', model.is_free ? '1' : '0')
                .attr('data-ctx', model.context_length || 0)
                .text(model.name + (model.is_free ? ' — FREE' : ''));
        }

        if (data.free && data.free.length) {
            var $freeGroup = $('<optgroup>').attr('label', '✅ Free Models');
            $.each(data.free, function (i, m) { $freeGroup.append(makeOption(m)); });
            $select.append($freeGroup);
        }

        if (data.paid && data.paid.length) {
            var $paidGroup = $('<optgroup>').attr('label', '💳 Paid Models');
            $.each(data.paid, function (i, m) { $paidGroup.append(makeOption(m)); });
            $select.append($paidGroup);
        }

        // Restore selection: keep current if it still exists, else pick first free model.
        if (currentVal && $select.find('option[value="' + currentVal + '"]').length) {
            $select.val(currentVal);
        } else if (data.free && data.free.length) {
            $select.val(data.free[0].id);
        }

        updateInfoBar();
    }

    /* ─── Init ────────────────────────────────────────────────────────────── */

    $(function () {
        $select  = $('#ai_content_master_openrouter_model');
        $info    = $('#ai-cm-model-info');
        $badge   = $('#ai-cm-model-badge');
        $ctx     = $('#ai-cm-model-ctx');
        $idLabel = $('#ai-cm-model-id-display');
        $status  = $('#ai-cm-model-status');
        $refresh = $('#ai-cm-refresh-models-btn');

        if (!$select.length) return;  // not on the settings page

        // Show info bar for the currently selected model on page load.
        updateInfoBar();

        // Update info bar whenever the user changes selection.
        $select.on('change', function () {
            updateInfoBar();
            $status.text('').removeClass('error success info');
        });

        /* ── Refresh Models button ─────────────────────────────────────── */
        $refresh.on('click', function () {
            if ($refresh.prop('disabled')) return;

            var previousVal = $select.val();

            // UI: spinning state
            $refresh.prop('disabled', true).addClass('spinning');
            $status
                .removeClass('error success')
                .addClass('info')
                .text(aiContentMasterAjax.strings.refreshing_models);

            $.ajax({
                url:  aiContentMasterAjax.ajax_url,
                type: 'POST',
                data: {
                    action:        'ai_content_master_fetch_models',
                    security:      aiContentMasterAjax.nonce,
                    force_refresh: '1',
                },
                success: function (response) {
                    if (response.success && response.data) {
                        rebuildSelect(response.data, previousVal);
                        var total = (response.data.total || 0);
                        $status
                            .removeClass('error info')
                            .addClass('success')
                            .text(
                                aiContentMasterAjax.strings.models_refreshed +
                                (total ? ' (' + total + ' models)' : '')
                            );
                    } else {
                        var msg = (response.data && response.data.message)
                            ? response.data.message
                            : aiContentMasterAjax.strings.models_error;
                        $status.removeClass('info success').addClass('error').text(msg);
                    }
                },
                error: function () {
                    $status
                        .removeClass('info success')
                        .addClass('error')
                        .text(aiContentMasterAjax.strings.models_error);
                },
                complete: function () {
                    $refresh.prop('disabled', false).removeClass('spinning');
                    // Auto-clear status after 5s
                    setTimeout(function () {
                        $status.fadeOut(400, function () {
                            $(this).text('').show().removeClass('error success info');
                        });
                    }, 5000);
                },
            });
        });
    });

})(jQuery);
