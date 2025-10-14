/**
 * AUTO IMAGE TAGS - Admin JavaScript
 * Version: 2.0.0
 */

(function($) {
    'use strict';

    // Глобальные переменные
    let processing = false;
    let translating = false;
    let removing = false;

    /**
     * Инициализация при загрузке DOM
     */
    $(document).ready(function() {
        initializeSettings();
        initializeProcessTab();
        initializePreviewTab();
        initializeToolsTab();
        initializeTranslationTab();
    });

    /**
     * ========================================
     * НАСТРОЙКИ
     * ========================================
     */
    function initializeSettings() {
        // Показ/скрытие кастомных полей
        $('#alt_format, #title_format, #caption_format, #description_format').on('change', function() {
            const format = $(this).val();
            const field = $(this).attr('id').replace('_format', '');
            const customRow = $('#' + field + '_custom_row');
            
            if (format === 'custom') {
                customRow.show();
            } else {
                customRow.hide();
            }
        });

        // Переключение сервисов перевода
        $('#translation_service').on('change', function() {
            const service = $(this).val();
            $('.translation-service-settings').hide();
            $('#' + service + '-settings').show();
        });
    }

    /**
     * ========================================
     * ОБРАБОТКА ИЗОБРАЖЕНИЙ
     * ========================================
     */
    function initializeProcessTab() {
        // Загрузка опций фильтра постов
        loadFilterOptions();

        // Загрузка статистики при изменении фильтров
        $('#date_filter, #status_filter, #post_filter').on('change', function() {
            loadImageStats();
        });

        // Кнопка обработки
        $('#autoimta-process-btn').on('click', function() {
            if (processing) return;

            const testMode = $(this).data('test-mode');
            const confirmMsg = testMode ? 
                autoimtaData.strings.confirm_test : 
                autoimtaData.strings.confirm_process;

            if (!confirm(confirmMsg)) return;

            startProcessing();
        });

        // Загрузка начальной статистики
        loadImageStats();
    }

    /**
     * Загрузка опций фильтра постов
     */
    function loadFilterOptions() {
        $.ajax({
            url: autoimtaData.ajaxurl,
            type: 'POST',
            data: {
                action: 'autoimta_get_filter_options',
                nonce: autoimtaData.nonce
            },
            success: function(response) {
                if (response.success && response.data.posts) {
                    const $select = $('#post_filter');
                    $select.empty().append('<option value="all">' + autoimtaData.strings.all + '</option>');
                    
                    $.each(response.data.posts, function(i, post) {
                        $select.append('<option value="' + post.ID + '">' + escapeHtml(post.post_title) + '</option>');
                    });
                }
            }
        });
    }

    /**
     * Загрузка статистики изображений
     */
    function loadImageStats() {
        const filters = {
            date: $('#date_filter').val(),
            status: $('#status_filter').val(),
            post: $('#post_filter').val()
        };

        $('#autoimta-stats').html('<p>' + autoimtaData.strings.loading + '</p>');
        $('#autoimta-process-btn').prop('disabled', true);

        $.ajax({
            url: autoimtaData.ajaxurl,
            type: 'POST',
            data: {
                action: 'autoimta_get_images_count',
                nonce: autoimtaData.nonce,
                filters: filters
            },
            success: function(response) {
                if (response.success) {
                    const data = response.data;
                    
                    if (data.needs_processing === 0) {
                        $('#autoimta-stats').html(
                            '<div class="autoimta-notice autoimta-notice-info">' +
                            '<p>' + autoimtaData.strings.no_images + '</p>' +
                            '</div>'
                        );
                        $('#autoimta-process-btn').prop('disabled', true);
                    } else {
                        $('#autoimta-stats').html(
                            '<div class="autoimta-stats-grid">' +
                            '<div class="autoimta-stat-item">' +
                            '<span class="autoimta-stat-label">' + autoimtaData.strings.total_images + '</span>' +
                            '<span class="autoimta-stat-value">' + data.total + '</span>' +
                            '</div>' +
                            '<div class="autoimta-stat-item">' +
                            '<span class="autoimta-stat-label">' + autoimtaData.strings.without_alt + '</span>' +
                            '<span class="autoimta-stat-value">' + data.without_alt + '</span>' +
                            '</div>' +
                            '<div class="autoimta-stat-item">' +
                            '<span class="autoimta-stat-label">' + autoimtaData.strings.without_title + '</span>' +
                            '<span class="autoimta-stat-value">' + data.without_title + '</span>' +
                            '</div>' +
                            '<div class="autoimta-stat-item">' +
                            '<span class="autoimta-stat-label">' + autoimtaData.strings.will_be_processed + '</span>' +
                            '<span class="autoimta-stat-value">' + data.needs_processing + '</span>' +
                            '</div>' +
                            '</div>'
                        );
                        $('#autoimta-process-btn').prop('disabled', false);
                    }
                } else {
                    showError($('#autoimta-stats'), response.data.message);
                }
            },
            error: function() {
                showError($('#autoimta-stats'), autoimtaData.strings.connection_error);
            }
        });
    }

    /**
     * Запуск обработки изображений
     */
    function startProcessing() {
        processing = true;
        let offset = 0;
        let totalProcessed = 0;
        let totalSuccess = 0;
        let totalErrors = 0;
        let totalSkipped = 0;

        $('#autoimta-process-btn').prop('disabled', true);
        $('#autoimta-progress').show();
        $('#autoimta-results').hide();

        const filters = {
            date: $('#date_filter').val(),
            status: $('#status_filter').val(),
            post: $('#post_filter').val()
        };

        function processBatch() {
            $.ajax({
                url: autoimtaData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'autoimta_process_existing_images',
                    nonce: autoimtaData.nonce,
                    offset: offset,
                    filters: filters
                },
                success: function(response) {
                    if (response.success) {
                        const data = response.data;
                        
                        totalProcessed += data.processed;
                        totalSuccess += data.success;
                        totalErrors += data.errors;
                        totalSkipped += data.skipped;

                        const progress = Math.min(100, Math.round((offset / 100) * 100));
                        updateProgress(progress);

                        $('#autoimta-status-text').html(
                            autoimtaData.strings.processed + ' ' + totalProcessed + '<br>' +
                            autoimtaData.strings.success + ' ' + totalSuccess + '<br>' +
                            autoimtaData.strings.skipped + ' ' + totalSkipped + '<br>' +
                            autoimtaData.strings.errors + ' ' + totalErrors
                        );

                        if (data.has_more) {
                            offset += data.processed;
                            processBatch();
                        } else {
                            processingComplete(totalProcessed, totalSuccess, totalSkipped, totalErrors, data.test_mode);
                        }
                    } else {
                        processingError(response.data.message);
                    }
                },
                error: function() {
                    processingError(autoimtaData.strings.connection_error);
                }
            });
        }

        processBatch();
    }

    function processingComplete(processed, success, skipped, errors, testMode) {
        processing = false;
        updateProgress(100);

        let resultHtml = '<div class="success-message">';
        resultHtml += '<h3>' + autoimtaData.strings.completed + '</h3>';
        
        if (testMode) {
            resultHtml += '<p><strong>' + autoimtaData.strings.test_mode + '</strong></p>';
            resultHtml += '<p>' + autoimtaData.strings.test_run + '</p>';
        }
        
        resultHtml += '<p>' + autoimtaData.strings.images_processed + ' <strong>' + processed + '</strong></p>';
        resultHtml += '<p>' + autoimtaData.strings.successfully_processed + ' <strong>' + success + '</strong></p>';
        resultHtml += '<p>' + autoimtaData.strings.skipped + ' <strong>' + skipped + '</strong></p>';
        
        if (errors > 0) {
            resultHtml += '<p>' + autoimtaData.strings.errors + ' <strong>' + errors + '</strong></p>';
        }
        
        resultHtml += '</div>';

        $('#autoimta-results-content').html(resultHtml);
        $('#autoimta-results').show();
        $('#autoimta-process-btn').prop('disabled', false).text(autoimtaData.strings.process_again);
    }

    function processingError(message) {
        processing = false;
        showError($('#autoimta-results-content'), message);
        $('#autoimta-results').show();
        $('#autoimta-process-btn').prop('disabled', false);
    }

    function initializePreviewTab() {
        $('#preview_load_btn').on('click', function() {
            loadPreview();
        });
    }

    function loadPreview() {
        const limit = $('#preview_limit').val();
        const filter = $('#preview_filter').val();

        $('#preview_results').hide();
        $('#preview_content').html('<p>' + autoimtaData.strings.loading + '</p>');

        $.ajax({
            url: autoimtaData.ajaxurl,
            type: 'POST',
            data: {
                action: 'autoimta_preview_changes',
                nonce: autoimtaData.nonce,
                limit: limit,
                filter: filter
            },
            success: function(response) {
                if (response.success && response.data.images) {
                    displayPreview(response.data.images);
                    $('#preview_results').show();
                } else {
                    showError($('#preview_content'), response.data.message || autoimtaData.strings.connection_error);
                }
            },
            error: function() {
                showError($('#preview_content'), autoimtaData.strings.connection_error);
            }
        });
    }

    function displayPreview(images) {
        if (images.length === 0) {
            $('#preview_content').html('<p>' + autoimtaData.strings.no_images + '</p>');
            return;
        }

        let html = '';

        $.each(images, function(i, img) {
            html += '<div class="preview-item">';
            html += '<div class="preview-header">';
            
            if (img.thumb) {
                html += '<img src="' + img.thumb + '" alt="" class="preview-thumb">';
            }
            
            html += '<div>';
            html += '<div class="preview-filename">' + escapeHtml(img.filename) + '</div>';
            html += '<small>ID: ' + img.id + '</small>';
            html += '</div>';
            html += '</div>';

            html += '<div class="preview-comparison">';
            
            html += '<div class="preview-column">';
            html += '<h4>' + autoimtaData.strings.original + '</h4>';
            html += renderPreviewField('ALT', img.current.alt);
            html += renderPreviewField('TITLE', img.current.title);
            html += renderPreviewField('Caption', img.current.caption);
            html += renderPreviewField('Description', img.current.description);
            html += '</div>';

            html += '<div class="preview-column">';
            html += '<h4>' + autoimtaData.strings.no_changes + '</h4>';
            html += renderPreviewField('ALT', img.new.alt, img.current.alt !== img.new.alt);
            html += renderPreviewField('TITLE', img.new.title, img.current.title !== img.new.title);
            html += renderPreviewField('Caption', img.new.caption, img.current.caption !== img.new.caption);
            html += renderPreviewField('Description', img.new.description, img.current.description !== img.new.description);
            html += '</div>';

            html += '</div>';
            html += '</div>';
        });

        $('#preview_content').html(html);
    }

    function renderPreviewField(label, value, changed) {
        let html = '<div class="preview-field">';
        html += '<span class="preview-field-label">' + label + '</span>';
        
        const isEmpty = !value || value.trim() === '';
        const changedClass = changed ? ' changed' : '';
        const emptyClass = isEmpty ? ' empty' : '';
        
        html += '<div class="preview-field-value' + changedClass + emptyClass + '">';
        html += isEmpty ? autoimtaData.strings.empty : escapeHtml(value);
        html += '</div>';
        html += '</div>';
        
        return html;
    }

    function initializeToolsTab() {
        initializeRemovalTool();
        initializeExportImport();
    }

    function initializeRemovalTool() {
        $('#remove_date_filter').on('change', function() {
            loadRemovalStats();
        });

        $('#remove-tags-btn').on('click', function() {
            if (removing) return;

            const removeTypes = [];
            if ($('#remove_alt').is(':checked')) removeTypes.push('alt');
            if ($('#remove_title').is(':checked')) removeTypes.push('title');
            if ($('#remove_caption').is(':checked')) removeTypes.push('caption');
            if ($('#remove_description').is(':checked')) removeTypes.push('description');

            if (removeTypes.length === 0) {
                alert(autoimtaData.strings.select_at_least_one);
                return;
            }

            if (!confirm(autoimtaData.strings.confirm_remove)) return;

            startRemoval(removeTypes);
        });

        loadRemovalStats();
    }

    function loadRemovalStats() {
        const dateFilter = $('#remove_date_filter').val();

        $('#remove-stats').html('<p>' + autoimtaData.strings.loading + '</p>');
        $('#remove-tags-btn').prop('disabled', true);

        $.ajax({
            url: autoimtaData.ajaxurl,
            type: 'POST',
            data: {
                action: 'autoimta_get_remove_stats',
                nonce: autoimtaData.nonce,
                date_filter: dateFilter
            },
            success: function(response) {
                if (response.success) {
                    $('#remove-stats').html(
                        '<p>' + autoimtaData.strings.found_images + ' <strong>' + response.data.total + '</strong></p>'
                    );
                    $('#remove-tags-btn').prop('disabled', false);
                } else {
                    showError($('#remove-stats'), response.data.message);
                }
            },
            error: function() {
                showError($('#remove-stats'), autoimtaData.strings.connection_error);
            }
        });
    }

    function startRemoval(removeTypes) {
        removing = true;
        let offset = 0;
        let totalProcessed = 0;

        $('#remove-tags-btn').prop('disabled', true);
        $('#remove-progress').show();
        $('#remove-results').hide();

        const dateFilter = $('#remove_date_filter').val();

        function removeBatch() {
            $.ajax({
                url: autoimtaData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'autoimta_remove_tags',
                    nonce: autoimtaData.nonce,
                    offset: offset,
                    remove_types: removeTypes,
                    date_filter: dateFilter
                },
                success: function(response) {
                    if (response.success) {
                        const data = response.data;
                        totalProcessed = data.total_processed;

                        const progress = data.has_more ? 50 : 100;
                        updateProgress(progress, '#remove-progress');

                        $('#remove-status-text').html(
                            autoimtaData.strings.images_processed + ' ' + totalProcessed
                        );

                        if (data.has_more) {
                            offset += data.processed;
                            removeBatch();
                        } else {
                            removalComplete(totalProcessed);
                        }
                    } else {
                        removalError(response.data.message);
                    }
                },
                error: function() {
                    removalError(autoimtaData.strings.connection_error);
                }
            });
        }

        removeBatch();
    }

    function removalComplete(processed) {
        removing = false;
        updateProgress(100, '#remove-progress');

        const resultHtml = '<div class="success-message">' +
            '<h3>' + autoimtaData.strings.removal_completed + '</h3>' +
            '<p>' + autoimtaData.strings.removed + ' <strong>' + processed + '</strong></p>' +
            '</div>';

        $('#remove-results-content').html(resultHtml);
        $('#remove-results').show();
        $('#remove-tags-btn').prop('disabled', false);
    }

    function removalError(message) {
        removing = false;
        showError($('#remove-results-content'), message);
        $('#remove-results').show();
        $('#remove-tags-btn').prop('disabled', false);
    }

    function initializeExportImport() {
        $('#export-settings-btn').on('click', function() {
            $.ajax({
                url: autoimtaData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'autoimta_export_settings',
                    nonce: autoimtaData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        const dataStr = JSON.stringify(response.data.settings, null, 2);
                        const dataBlob = new Blob([dataStr], {type: 'application/json'});
                        const url = URL.createObjectURL(dataBlob);
                        const link = document.createElement('a');
                        link.href = url;
                        link.download = 'autoimta-settings-' + Date.now() + '.json';
                        link.click();
                        URL.revokeObjectURL(url);
                    }
                }
            });
        });

        $('#import-settings-btn').on('click', function() {
            $('#import-settings-file').click();
        });

        $('#import-settings-file').on('change', function(e) {
            const file = e.target.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = function(e) {
                try {
                    const settings = JSON.parse(e.target.result);
                    
                    $.ajax({
                        url: autoimtaData.ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'autoimta_import_settings',
                            nonce: autoimtaData.nonce,
                            settings: settings
                        },
                        success: function(response) {
                            if (response.success) {
                                $('#import-result').html(
                                    '<div class="success-message">' + 
                                    autoimtaData.strings.settings_imported + 
                                    '</div>'
                                );
                                setTimeout(function() {
                                    location.reload();
                                }, 1500);
                            } else {
                                showError($('#import-result'), response.data.message);
                            }
                        }
                    });
                } catch(err) {
                    showError($('#import-result'), autoimtaData.strings.invalid_file);
                }
            };
            reader.readAsText(file);
        });
    }

    function initializeTranslationTab() {
        $('#test-translation-btn').on('click', function() {
            testTranslation();
        });

        $('#start-translation-btn').on('click', function() {
            if (translating) return;
            if (!confirm(autoimtaData.strings.confirm_translate)) return;
            startTranslation();
        });

        loadTranslationStats();
    }

    function testTranslation() {
        const text = $('#test_text').val().trim();
        
        if (!text) {
            alert(autoimtaData.strings.enter_text);
            return;
        }

        $('#test-translation-btn').prop('disabled', true).text(autoimtaData.strings.translating);
        $('#test-translation-result').html('');

        $.ajax({
            url: autoimtaData.ajaxurl,
            type: 'POST',
            data: {
                action: 'autoimta_test_translation',
                nonce: autoimtaData.nonce,
                text: text
            },
            success: function(response) {
                if (response.success) {
                    const html = '<div class="success-message">' +
                        '<div class="translation-result-item">' +
                        '<span class="translation-result-label">' + autoimtaData.strings.original + '</span>' +
                        '<div class="translation-result-value">' + escapeHtml(text) + '</div>' +
                        '</div>' +
                        '<div class="translation-result-item">' +
                        '<span class="translation-result-label">' + autoimtaData.strings.translation + ' (' + response.data.service + '):</span>' +
                        '<div class="translation-result-value">' + escapeHtml(response.data.translation) + '</div>' +
                        '</div>' +
                        '</div>';
                    $('#test-translation-result').html(html);
                } else {
                    showError($('#test-translation-result'), response.data.message);
                }
            },
            error: function() {
                showError($('#test-translation-result'), autoimtaData.strings.connection_error);
            },
            complete: function() {
                $('#test-translation-btn').prop('disabled', false).text(autoimtaData.strings.test_translation);
            }
        });
    }

    function loadTranslationStats() {
        $('#translation-stats').html('<p>' + autoimtaData.strings.loading + '</p>');
        $('#start-translation-btn').prop('disabled', true);

        $.ajax({
            url: autoimtaData.ajaxurl,
            type: 'POST',
            data: {
                action: 'autoimta_get_translation_stats',
                nonce: autoimtaData.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#translation-stats').html(
                        '<p>' + autoimtaData.strings.found_images_with_tags + ' <strong>' + response.data.total + '</strong></p>'
                    );
                    if (response.data.total > 0) {
                        $('#start-translation-btn').prop('disabled', false);
                    }
                } else {
                    showError($('#translation-stats'), response.data.message);
                }
            },
            error: function() {
                showError($('#translation-stats'), autoimtaData.strings.connection_error);
            }
        });
    }

    function startTranslation() {
        translating = true;
        let offset = 0;
        let totalProcessed = 0;
        let totalSuccess = 0;
        let totalErrors = 0;

        $('#start-translation-btn').prop('disabled', true);
        $('#translation-progress').show();
        $('#translation-results').hide();

        function translateBatch() {
            $.ajax({
                url: autoimtaData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'autoimta_translate_batch',
                    nonce: autoimtaData.nonce,
                    offset: offset
                },
                success: function(response) {
                    if (response.success) {
                        const data = response.data;
                        
                        totalProcessed += data.processed;
                        totalSuccess += data.success;
                        totalErrors += data.errors;

                        const progress = data.has_more ? 50 : 100;
                        updateProgress(progress, '#translation-progress');

                        $('#translation-status-text').html(
                            autoimtaData.strings.processed + ' ' + totalProcessed + '<br>' +
                            autoimtaData.strings.successfully_translated + ' ' + totalSuccess + '<br>' +
                            autoimtaData.strings.errors + ' ' + totalErrors
                        );

                        if (data.has_more) {
                            offset += data.processed;
                            translateBatch();
                        } else {
                            translationComplete(totalProcessed, totalSuccess, totalErrors);
                        }
                    } else {
                        translationError(response.data.message);
                    }
                },
                error: function() {
                    translationError(autoimtaData.strings.connection_error);
                }
            });
        }

        translateBatch();
    }

    function translationComplete(processed, success, errors) {
        translating = false;
        updateProgress(100, '#translation-progress');

        let resultHtml = '<div class="success-message">';
        resultHtml += '<h3>' + autoimtaData.strings.translation_completed + '</h3>';
        resultHtml += '<p>' + autoimtaData.strings.images_processed + ' <strong>' + processed + '</strong></p>';
        resultHtml += '<p>' + autoimtaData.strings.successfully_translated + ' <strong>' + success + '</strong></p>';
        
        if (errors > 0) {
            resultHtml += '<p>' + autoimtaData.strings.errors + ' <strong>' + errors + '</strong></p>';
        }
        
        resultHtml += '</div>';

        $('#translation-results-content').html(resultHtml);
        $('#translation-results').show();
        $('#start-translation-btn').prop('disabled', false);
    }

    function translationError(message) {
        translating = false;
        showError($('#translation-results-content'), message);
        $('#translation-results').show();
        $('#start-translation-btn').prop('disabled', false);
    }

    function updateProgress(percent, container) {
        container = container || '#autoimta-progress';
        $(container + ' .progress-bar-fill').css('width', percent + '%');
        $(container + ' .progress-text').text(percent + '%');
    }

    function showError($element, message) {
        $element.html(
            '<div class="error-message">' +
            '<p><strong>Error:</strong> ' + escapeHtml(message) + '</p>' +
            '</div>'
        );
    }

    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }

})(jQuery);
