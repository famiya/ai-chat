/**
 * AI Chat Admin JavaScript
 */
(function($) {
    'use strict';
    
    let currentPlatform = 'ai-chat';
    
    $(document).ready(function() {
        // 移除可能的錯誤訊息
        setTimeout(function() {
            $('.notice-error, .ai-chat-error, .error').each(function() {
                var text = $(this).text();
                if (text.includes('API') && (text.includes('金鑰') || text.includes('key'))) {
                    $(this).fadeOut().remove();
                }
            });
        }, 100);

        initPlatformTabs();
        initFormControls();
        initPreview();
        initApiTesting();
        initSettingsSave();
        initMediaUpload();
        initSaveButtonEffects();
    });
      /**
     * Initialize platform tabs
     */
    function initPlatformTabs() {
        // Ensure at least ai-chat is active
        if ($('.platform-tab.active').length === 0) {
            $('.platform-tab[data-platform="ai-chat"]').addClass('active');
            $('.platform-tab[data-platform="ai-chat"] input[type="checkbox"]').prop('checked', true);
        }
        
        // Set initial active platform
        $('.platform-tab.active').first().each(function() {
            currentPlatform = $(this).data('platform') || 'ai-chat';
            showPlatformConfig(currentPlatform);
            showPlatformHelp(currentPlatform);
        });
          // Platform tab click handler
        $('.platform-tab').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const platform = $(this).data('platform');
            const isCurrentlyActive = $(this).hasClass('active');
            
            // Toggle the active state
            if (isCurrentlyActive) {
                $(this).removeClass('active');
                // Uncheck the hidden checkbox
                $(this).find('input[type="checkbox"]').prop('checked', false);
                
                // If this was the current platform, switch to first remaining active one
                if (platform === currentPlatform) {
                    const firstActive = $('.platform-tab.active').first();
                    if (firstActive.length) {
                        currentPlatform = firstActive.data('platform');
                        showPlatformConfig(currentPlatform);
                        showPlatformHelp(currentPlatform);
                    } else {
                        currentPlatform = 'ai-chat'; // Default to ai-chat
                        $('.platform-tab[data-platform="ai-chat"]').addClass('active');
                        $('.platform-tab[data-platform="ai-chat"] input[type="checkbox"]').prop('checked', true);
                        showPlatformConfig(currentPlatform);
                        showPlatformHelp(currentPlatform);
                    }
                }
            } else {
                $(this).addClass('active');
                // Check the hidden checkbox
                $(this).find('input[type="checkbox"]').prop('checked', true);
                
                // Show this platform's config
                currentPlatform = platform;
                showPlatformConfig(platform);
                showPlatformHelp(platform);
            }
            
            updatePreview();
        });
    }
    
    /**
     * Show platform configuration
     */
    function showPlatformConfig(platform) {
        $('.platform-config').removeClass('active').hide();
        $(`.platform-config[data-platform="${platform}"]`).addClass('active').show();
    }
    
    /**
     * Show platform help
     */
    function showPlatformHelp(platform) {
        $('.help-section').removeClass('active').hide();
        $(`.help-section[data-platform="${platform}"]`).addClass('active').show();
    }
    
    /**
     * Initialize form controls
     */
    function initFormControls() {
        // API provider change handler
        $('#ai_api_provider').on('change', function() {
            const provider = $(this).val();
            if (provider === 'custom') {
                $('.custom-api-field').addClass('show').show();
            } else {
                $('.custom-api-field').removeClass('show').hide();
            }
        }).trigger('change');
        
        // Color picker change handler
        $('input[name="ai_chat_settings[chat_color]"]').on('change', function() {
            updatePreview();
        });
        
        // Position and size change handlers
        $('select[name="ai_chat_settings[chat_position]"], select[name="ai_chat_settings[chat_size]"]').on('change', function() {
            updatePreview();
        });
    }
    
    /**
     * Initialize preview
     */
    function initPreview() {
        updatePreview();
    }
    
    /**
     * Update preview
     */
    function updatePreview() {
        const color = $('input[name="ai_chat_settings[chat_color]"]').val() || '#007cba';
        const position = $('select[name="ai_chat_settings[chat_position]"]').val() || 'bottom-right';
        const size = $('select[name="ai_chat_settings[chat_size]"]').val() || 'medium';
        
        // Update CSS custom property
        $('#chat-preview').css('--preview-color', color);
        
        // Update preview position classes
        const $preview = $('.chat-widget-preview');
        $preview.removeClass('bottom-right bottom-left top-right top-left small medium large');
        $preview.addClass(position + ' ' + size);
        
        // Update icon color
        $('.chat-widget-preview .chat-icon').css('background', color);
    }
    
    /**
     * Initialize API testing
     */
    function initApiTesting() {
        // Add test button if it doesn't exist
        if (!$('#test-api-btn').length) {
            const testBtn = $('<button type="button" id="test-api-btn" class="button button-secondary" style="margin-left: 10px;">測試連接</button>');
            $('input[name="ai_chat_settings[ai_api_key]"]').parent().append(testBtn);
        }
        
        // Test API connection
        $('#test-api-btn').on('click', function(e) {
            e.preventDefault();
            
            const $btn = $(this);
            const originalText = $btn.text();
            
            const data = {
                action: 'ai_chat_test_api',
                nonce: aiChatAdmin.nonce,
                api_provider: $('#ai_api_provider').val(),
                api_key: $('input[name="ai_chat_settings[ai_api_key]"]').val(),
                api_url: $('input[name="ai_chat_settings[ai_api_url]"]').val(),
                model: $('input[name="ai_chat_settings[ai_model]"]').val()
            };
            
            $btn.text('測試中...').prop('disabled', true);
            
            $.post(aiChatAdmin.ajaxUrl, data)
                .done(function(response) {
                    if (response.success) {
                        showToast(response.data, 'success');
                    } else {
                        showToast(response.data || 'Test failed', 'error');
                    }
                })
                .fail(function() {
                    showToast('連接測試失敗', 'error');
                })
                .always(function() {
                    $btn.text(originalText).prop('disabled', false);
                });
        });
    }
    
    /**
     * Initialize settings save
     */
    function initSettingsSave() {
        $('#ai-chat-settings-form').on('submit', function(e) {
            const $form = $(this);
            const $submitBtn = $form.find('input[type="submit"]');
            const originalText = $submitBtn.val();
            
            $submitBtn.val(aiChatAdmin.strings.saving).prop('disabled', true);
            $form.addClass('saving');
            
            // Let the form submit naturally, but add visual feedback
            setTimeout(function() {
                $submitBtn.val(originalText).prop('disabled', false);
                $form.removeClass('saving');
            }, 2000);
        });
        
        // AJAX save (alternative method)
        $('#ajax-save-btn').on('click', function(e) {
            e.preventDefault();
            saveSettingsAjax();
        });
    }
    
    /**
     * Save settings via AJAX
     */
    function saveSettingsAjax() {
        const $form = $('#ai-chat-settings-form');
        const formData = $form.serializeArray();
        const settings = {};
        
        // Convert form data to settings object
        $.each(formData, function(i, field) {
            if (field.name.startsWith('ai_chat_settings[')) {
                const key = field.name.replace('ai_chat_settings[', '').replace(']', '');
                if (key.endsWith('[]')) {
                    const arrayKey = key.replace('[]', '');
                    if (!settings[arrayKey]) {
                        settings[arrayKey] = [];
                    }
                    settings[arrayKey].push(field.value);
                } else {
                    settings[key] = field.value;
                }
            }
        });
        
        const data = {
            action: 'ai_chat_save_settings',
            nonce: aiChatAdmin.nonce,
            settings: settings
        };
        
        $.post(aiChatAdmin.ajaxUrl, data)
            .done(function(response) {
                if (response.success) {
                    showToast(response.data, 'success');
                } else {
                    showToast(response.data || 'Save failed', 'error');
                }
            })
            .fail(function() {
                showToast('Save failed', 'error');
            });
    }
    
    /**
     * Show toast notification
     */
    function showToast(message, type = 'success') {
        const toast = $(`<div class="ai-chat-toast ${type}">${message}</div>`);
        $('body').append(toast);
        
        setTimeout(function() {
            toast.fadeOut(function() {
                toast.remove();
            });
        }, 3000);
    }
    
    /**
     * Platform configuration validation
     */
    function validatePlatformConfig(platform) {
        const validators = {
            'whatsapp': function() {
                const phone = $('input[name="ai_chat_settings[whatsapp_phone]"]').val();
                return phone && phone.match(/^\+?[1-9]\d{1,14}$/);
            },
            'facebook': function() {
                const pageId = $('input[name="ai_chat_settings[facebook_page_id]"]').val();
                return pageId && pageId.length > 0;
            },
            'ai-chat': function() {
                const apiKey = $('input[name="ai_chat_settings[ai_api_key]"]').val();
                const provider = $('#ai_api_provider').val();
                
                if (!apiKey) return false;
                
                if (provider === 'custom') {
                    const apiUrl = $('input[name="ai_chat_settings[ai_api_url]"]').val();
                    return apiUrl && apiUrl.match(/^https?:\/\/.+/);
                }
                
                return true;
            }
        };
        
        if (validators[platform]) {
            return validators[platform]();
        }
        
        return true;
    }
    
    /**
     * Add validation indicators
     */
    function updateValidationIndicators() {
        $('.platform-tab').each(function() {
            const platform = $(this).data('platform');
            const isValid = validatePlatformConfig(platform);
            const isEnabled = $(this).hasClass('active');
            
            if (isEnabled && !isValid) {
                $(this).addClass('invalid');
            } else {
                $(this).removeClass('invalid');
            }
        });
    }
    
    // Run validation on form changes (disabled to prevent error messages)
    // $(document).on('input change', 'input, select, textarea', function() {
    //     setTimeout(updateValidationIndicators, 100);
    // });

    // Initial validation (disabled to prevent error messages)
    // updateValidationIndicators();

    /**
     * Initialize media upload functionality
     */
    function initMediaUpload() {
        console.log('AI Chat: Initializing media upload functionality');

        // Wait for wp.media to be available
        function waitForMedia() {
            if (typeof wp !== 'undefined' && typeof wp.media !== 'undefined') {
                console.log('AI Chat: wp.media is available, setting up upload handlers');
                setupMediaUpload();
            } else {
                console.log('AI Chat: wp.media not ready, waiting...');
                setTimeout(waitForMedia, 100);
            }
        }

        waitForMedia();
    }

    /**
     * Setup media upload handlers
     */
    function setupMediaUpload() {
        // WeChat QR Code upload
        $(document).on('click', '#upload_wechat_qr', function(e) {
            e.preventDefault();
            console.log('AI Chat: WeChat QR upload button clicked');

            var mediaUploader = wp.media({
                title: 'Select WeChat QR Code',
                button: {
                    text: 'Use this image'
                },
                multiple: false,
                library: {
                    type: 'image'
                }
            });

            mediaUploader.on('select', function() {
                console.log('AI Chat: Image selected');
                var attachment = mediaUploader.state().get('selection').first().toJSON();
                console.log('AI Chat: Selected image URL:', attachment.url);

                $('#wechat_qr_url').val(attachment.url);
                $('#wechat_qr_preview').html('<img src="' + attachment.url + '" style="max-width: 200px; max-height: 200px; border: 1px solid #ddd; border-radius: 4px;">');
                $('#remove_wechat_qr').show();
            });

            mediaUploader.open();
        });

        // Remove WeChat QR Code
        $(document).on('click', '#remove_wechat_qr', function(e) {
            e.preventDefault();
            console.log('AI Chat: Remove QR button clicked');
            $('#wechat_qr_url').val('');
            $('#wechat_qr_preview').html('');
            $(this).hide();
        });

        console.log('AI Chat: Media upload functionality initialized successfully');
    }
    
    /**
     * Chat History Management
     */
    const AIChatHistory = {
        
        init: function() {
            this.bindEvents();
            this.setupModal();
        },

        bindEvents: function() {
            // View conversation details
            $(document).on('click', '.view-conversation', this.viewConversation);
            
            // Export data
            $(document).on('click', '.export-data', this.exportData);
            
            // Search functionality
            $('#conversation-search').on('input', this.debounce(this.searchConversations, 300));
            
            // Filter functionality  
            $('#filter-platform, #filter-status, #filter-date-from, #filter-date-to').on('change', this.filterConversations);
            
            // Bulk actions
            $('#bulk-action-apply').on('click', this.applyBulkAction);
            
            // Modal close
            $(document).on('click', '.ai-chat-modal-close, .ai-chat-modal', function(e) {
                if (e.target === this) {
                    AIChatHistory.closeModal();
                }
            });
        },

        setupModal: function() {
            // Create modal if it doesn't exist
            if ($('#conversation-modal').length === 0) {
                var modalHtml = `
                    <div id="conversation-modal" class="ai-chat-modal">
                        <div class="ai-chat-modal-content">
                            <div class="ai-chat-modal-header">
                                <h2>對話詳情</h2>
                                <button class="ai-chat-modal-close">&times;</button>
                            </div>
                            <div class="ai-chat-modal-body">
                                <div id="conversation-details"></div>
                            </div>
                        </div>
                    </div>
                `;
                $('body').append(modalHtml);
            }
        },

        viewConversation: function(e) {
            e.preventDefault();
            var conversationId = $(this).data('conversation-id');
            
            // Show loading state
            $('#conversation-details').html('<div class="ai-chat-loading">載入中...</div>');
            AIChatHistory.openModal();
            
            // Fetch conversation details
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ai_chat_get_conversation_details',
                    conversation_id: conversationId,
                    nonce: ai_chat_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        AIChatHistory.renderConversationDetails(response.data);
                    } else {
                        $('#conversation-details').html('<div class="ai-chat-error">' + response.data + '</div>');
                    }
                },
                error: function() {
                    $('#conversation-details').html('<div class="ai-chat-error">發生錯誤，請稍後再試。</div>');
                }
            });
        },

        renderConversationDetails: function(data) {
            // 使用 WordPress 本地化的字符串
            var strings = window.aiChatAdmin && window.aiChatAdmin.strings ? window.aiChatAdmin.strings : {
                'conversation_id': '對話ID:',
                'platform': '平台:',
                'status': '狀態:',
                'started_at': '開始時間:',
                'last_activity': '最後活動:',
                'message_count': '訊息數量:',
                'message_records': '訊息記錄'
            };

            var html = `
                <div class="ai-chat-conversation-info">
                    <dl>
                        <dt>${strings.conversation_id}</dt>
                        <dd>${data.conversation.id}</dd>
                        <dt>${strings.platform}</dt>
                        <dd><span class="ai-chat-platform-badge ${data.conversation.platform}">${data.conversation.platform.toUpperCase()}</span></dd>
                        <dt>${strings.status}</dt>
                        <dd><span class="ai-chat-status ${data.conversation.status}">${data.conversation.status}</span></dd>
                        <dt>${strings.started_at}</dt>
                        <dd>${data.conversation.started_at}</dd>
                        <dt>${strings.last_activity}</dt>
                        <dd>${data.conversation.last_activity}</dd>
                        <dt>${strings.message_count}</dt>
                        <dd>${data.conversation.message_count}</dd>
                    </dl>
                </div>
                <h4>${strings.message_records}</h4>
                <div class="ai-chat-messages">
            `;
            
            if (data.messages && data.messages.length > 0) {
                data.messages.forEach(function(message) {
                    html += `
                        <div class="ai-chat-message ${message.sender_type}">
                            <div class="ai-chat-message-header">
                                ${message.sender_type === 'user' ? aiChatAdmin.strings.user : aiChatAdmin.strings.ai} - ${message.created_at}
                            </div>
                            <div class="ai-chat-message-content">${message.content}</div>
                        </div>
                    `;
                });
            } else {
                html += '<div class="ai-chat-no-data">' + aiChatAdmin.strings.no_messages + '</div>';
            }
            
            html += '</div>';
            $('#conversation-details').html(html);
        },

        openModal: function() {
            $('#conversation-modal').show();
            $('body').addClass('modal-open');
        },

        closeModal: function() {
            $('#conversation-modal').hide();
            $('body').removeClass('modal-open');
        },

        exportData: function(e) {
            e.preventDefault();
            
            if (!confirm('確定要匯出聊天數據嗎？')) {
                return;
            }
            
            // Create form and submit
            var form = $('<form>', {
                method: 'POST',
                action: ajaxurl
            });
            
            form.append($('<input>', {
                type: 'hidden',
                name: 'action',
                value: 'ai_chat_export_chat_data'
            }));
            
            form.append($('<input>', {
                type: 'hidden',
                name: 'nonce',
                value: ai_chat_admin.nonce
            }));
            
            // Add current filters
            form.append($('<input>', {
                type: 'hidden',
                name: 'platform',
                value: $('#filter-platform').val()
            }));
            
            form.append($('<input>', {
                type: 'hidden',
                name: 'status',
                value: $('#filter-status').val()
            }));
            
            form.append($('<input>', {
                type: 'hidden',
                name: 'date_from',
                value: $('#filter-date-from').val()
            }));
            
            form.append($('<input>', {
                type: 'hidden',
                name: 'date_to',
                value: $('#filter-date-to').val()
            }));
            
            $('body').append(form);
            form.submit();
            form.remove();
        },

        searchConversations: function() {
            AIChatHistory.filterConversations();
        },

        filterConversations: function() {
            var platform = $('#filter-platform').val();
            var status = $('#filter-status').val();
            var dateFrom = $('#filter-date-from').val();
            var dateTo = $('#filter-date-to').val();
            var search = $('#conversation-search').val();
            
            // Build query string
            var params = {
                platform: platform,
                status: status,
                date_from: dateFrom,
                date_to: dateTo,
                search: search
            };
            
            // Remove empty parameters
            Object.keys(params).forEach(key => {
                if (!params[key]) delete params[key];
            });
            
            // Reload page with filters
            var url = new URL(window.location);
            Object.keys(params).forEach(key => {
                url.searchParams.set(key, params[key]);
            });
            
            // Clear empty parameters
            var emptyParams = ['platform', 'status', 'date_from', 'date_to', 'search'];
            emptyParams.forEach(param => {
                if (!params[param]) {
                    url.searchParams.delete(param);
                }
            });
            
            window.location = url;
        },

        applyBulkAction: function(e) {
            e.preventDefault();
            
            var action = $('#bulk-action-selector').val();
            var selectedIds = [];
            
            $('input[name="conversation_ids[]"]:checked').each(function() {
                selectedIds.push($(this).val());
            });
            
            if (!action || selectedIds.length === 0) {
                alert('請選擇項目和操作');
                return;
            }
            
            if (!confirm('確定要執行批量操作嗎？')) {
                return;
            }
            
            // Implement bulk actions here
            console.log('Bulk action:', action, 'IDs:', selectedIds);
        },

        debounce: function(func, wait) {
            var timeout;
            return function executedFunction(...args) {
                var later = function() {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
    };

    /**
     * Analytics Dashboard
     */
    const AIChatAnalytics = {
        
        init: function() {
            this.initCharts();
            this.bindEvents();
        },

        bindEvents: function() {
            // Export analytics data
            $(document).on('click', '.export-analytics', this.exportAnalytics);
            
            // Refresh charts
            $(document).on('click', '.refresh-charts', this.refreshCharts);
        },

        initCharts: function() {
            // Initialize daily activity chart
            if ($('#daily-activity-chart').length) {
                this.createDailyActivityChart();
            }
            
            // Initialize platform distribution chart
            if ($('#platform-distribution-chart').length) {
                this.createPlatformDistributionChart();
            }
        },

        createDailyActivityChart: function() {
            var canvas = document.getElementById('daily-activity-chart');
            if (!canvas) return;
            
            // Get data from PHP (should be localized)
            var data = window.aiChatAnalytics?.dailyActivity || [];
            
            var labels = data.map(item => item.date);
            var values = data.map(item => item.count);
            
            // Simple canvas-based chart
            this.drawLineChart(canvas, labels, values, {
                title: '每日活動',
                color: '#007cba'
            });
        },

        createPlatformDistributionChart: function() {
            var canvas = document.getElementById('platform-distribution-chart');
            if (!canvas) return;
            
            // Get data from PHP
            var data = window.aiChatAnalytics?.platformDistribution || [];
            
            // Simple pie chart
            this.drawPieChart(canvas, data);
        },

        drawLineChart: function(canvas, labels, data, options) {
            var ctx = canvas.getContext('2d');
            var width = canvas.width = canvas.offsetWidth;
            var height = canvas.height = canvas.offsetHeight;
            var padding = 40;
            
            // Clear canvas
            ctx.clearRect(0, 0, width, height);
            
            if (data.length === 0) {
                ctx.fillStyle = '#666';
                ctx.font = '16px Arial';
                ctx.textAlign = 'center';
                ctx.fillText('暫無數據', width / 2, height / 2);
                return;
            }
            
            var maxValue = Math.max(...data);
            var minValue = Math.min(...data);
            var range = maxValue - minValue;
            
            if (range === 0) range = 1;
            
            var stepX = (width - 2 * padding) / Math.max(1, labels.length - 1);
            var stepY = (height - 2 * padding) / range;
            
            // Draw axes
            ctx.strokeStyle = '#ddd';
            ctx.lineWidth = 1;
            ctx.beginPath();
            ctx.moveTo(padding, padding);
            ctx.lineTo(padding, height - padding);
            ctx.lineTo(width - padding, height - padding);
            ctx.stroke();
            
            // Draw data line
            ctx.strokeStyle = options.color || '#007cba';
            ctx.lineWidth = 2;
            ctx.beginPath();
            
            for (var i = 0; i < data.length; i++) {
                var x = padding + i * stepX;
                var y = height - padding - (data[i] - minValue) * stepY;
                
                if (i === 0) {
                    ctx.moveTo(x, y);
                } else {
                    ctx.lineTo(x, y);
                }
                
                // Draw data points
                ctx.fillStyle = options.color || '#007cba';
                ctx.beginPath();
                ctx.arc(x, y, 3, 0, 2 * Math.PI);
                ctx.fill();
            }
            ctx.stroke();
            
            // Draw labels (simplified)
            ctx.fillStyle = '#666';
            ctx.font = '12px Arial';
            ctx.textAlign = 'center';
            
            for (var i = 0; i < labels.length; i += Math.ceil(labels.length / 5)) {
                var x = padding + i * stepX;
                var label = labels[i];
                if (label && label.length > 10) {
                    label = label.substring(5); // Show only month-day
                }
                ctx.fillText(label || '', x, height - padding + 15);
            }
        },

        drawPieChart: function(canvas, data) {
            var ctx = canvas.getContext('2d');
            var width = canvas.width = canvas.offsetWidth;
            var height = canvas.height = canvas.offsetHeight;
            var centerX = width / 2;
            var centerY = height / 2;
            var radius = Math.min(width, height) / 2 - 40;
            
            // Clear canvas
            ctx.clearRect(0, 0, width, height);
            
            if (data.length === 0) {
                ctx.fillStyle = '#666';
                ctx.font = '16px Arial';
                ctx.textAlign = 'center';
                ctx.fillText('暫無數據', centerX, centerY);
                return;
            }
            
            var total = data.reduce((sum, item) => sum + parseInt(item.count), 0);
            var currentAngle = -Math.PI / 2; // Start from top
            
            var colors = ['#1877f2', '#25d366', '#00c300', '#7bb32e', '#e4405f', '#12b7f5', '#007cba'];
            
            data.forEach(function(item, index) {
                var sliceAngle = (item.count / total) * 2 * Math.PI;
                
                // Draw slice
                ctx.fillStyle = colors[index % colors.length];
                ctx.beginPath();
                ctx.moveTo(centerX, centerY);
                ctx.arc(centerX, centerY, radius, currentAngle, currentAngle + sliceAngle);
                ctx.closePath();
                ctx.fill();
                
                // Draw label
                var labelAngle = currentAngle + sliceAngle / 2;
                var labelX = centerX + Math.cos(labelAngle) * (radius + 30);
                var labelY = centerY + Math.sin(labelAngle) * (radius + 30);
                
                ctx.fillStyle = '#333';
                ctx.font = '12px Arial';
                ctx.textAlign = 'center';
                ctx.fillText(item.platform.toUpperCase(), labelX, labelY);
                ctx.fillText('(' + item.count + ')', labelX, labelY + 15);
                
                currentAngle += sliceAngle;
            });
        },

        exportAnalytics: function(e) {
            e.preventDefault();
            
            if (!confirm('確定要匯出分析數據嗎？')) {
                return;
            }
            
            // Create download link for analytics export
            var form = $('<form>', {
                method: 'POST',
                action: ajaxurl
            });
            
            form.append($('<input>', {
                type: 'hidden',
                name: 'action',
                value: 'ai_chat_export_analytics'
            }));
            
            form.append($('<input>', {
                type: 'hidden',
                name: 'nonce',
                value: ai_chat_admin.nonce
            }));
            
            $('body').append(form);
            form.submit();
            form.remove();
        },

        refreshCharts: function(e) {
            e.preventDefault();
            location.reload();
        }
    };

    // Initialize page-specific functionality
    $(document).ready(function() {
        if ($('.ai-chat-history_page').length) {
            AIChatHistory.init();
        }
        
        if ($('.ai-chat-analytics_page').length) {
            AIChatAnalytics.init();
        }
    });

    /**
     * Initialize save button effects
     */
    function initSaveButtonEffects() {
        // 添加旋轉動畫 CSS
        if (!$('#ai-chat-spin-animation').length) {
            $('head').append(`
                <style id="ai-chat-spin-animation">
                    @keyframes spin {
                        0% { transform: rotate(0deg); }
                        100% { transform: rotate(360deg); }
                    }
                </style>
            `);
        }
        // 聊天設置保存按鈕效果
        $('#ai-chat-save-settings-btn').on('click', function() {
            var $btn = $(this);
            var originalText = $btn.html();
            var originalStyle = $btn.attr('style');

            // 變成灰色並顯示保存中
            $btn.prop('disabled', true)
                .css({
                    'background': '#ccc',
                    'cursor': 'not-allowed',
                    'opacity': '0.7'
                })
                .html('<span style="margin-right: 8px;">⏳</span>保存中...');

            // 監聽表單提交完成
            setTimeout(function() {
                // 恢復原始狀態
                $btn.prop('disabled', false)
                    .attr('style', originalStyle)
                    .html(originalText);

                // 顯示成功提示
                showToast('設置保存成功！', 'success');
            }, 1500);
        });

        // AI 數據源保存按鈕效果
        $('#ai-chat-save-custom-text-btn').on('click', function() {
            var $btn = $(this);
            var originalText = $btn.html();
            var originalStyle = $btn.attr('style');

            // 變成灰色並顯示保存中
            $btn.prop('disabled', true)
                .css({
                    'background': '#ccc',
                    'cursor': 'not-allowed',
                    'opacity': '0.7'
                })
                .html('<i class="dashicons dashicons-update" style="margin-right: 5px; animation: spin 1s linear infinite;"></i>保存中...');

            // 監聽表單提交完成
            setTimeout(function() {
                // 恢復原始狀態
                $btn.prop('disabled', false)
                    .attr('style', originalStyle)
                    .html(originalText);

                // 顯示成功提示
                showToast('AI 指導文字保存成功！', 'success');
            }, 1500);
        });
    }

})(jQuery);
