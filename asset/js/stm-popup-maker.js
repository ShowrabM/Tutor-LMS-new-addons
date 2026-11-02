/**
 * STM Tutor Popup Maker JavaScript
 * Handles Admin Configurator Modal & Frontend Responsive Live Opt-in Popup
 */
(function($) {
    'use strict';

    var config = window.stmPopupMakerConfig || {};

    /* ==========================================================================
       1. Admin Configurator Logic
       ========================================================================== */
    function initAdminModal() {
        if (!config.isAdmin) return;

        var $modal       = $('#stm-popup-maker-modal');
        var $form        = $('#stm-pm-config-form');
        var $fileInput   = $('#stm_pm_image_file');
        var $dropzone    = $('#stm_pm_dropzone');
        var $previewWrap = $('#stm_pm_image_preview');
        var $previewImg  = $('#stm_pm_preview_img');
        var $previewName = $('#stm_pm_preview_name');
        var $alert       = $('#stm_pm_alert');
        var $saveBtn     = $('#stm_pm_save_btn');

        // Open Modal
        $(document).on('click', '.stm-popup-maker-btn, #stm-popup-maker-trigger-btn', function(e) {
            e.preventDefault();
            $modal.addClass('is-open').attr('aria-hidden', 'false');
            $('body').addClass('stm-modal-open');
        });

        // Close Modal
        $(document).on('click', '[data-stm-pm-close="1"]', function(e) {
            e.preventDefault();
            $modal.removeClass('is-open').attr('aria-hidden', 'true');
            $('body').removeClass('stm-modal-open');
        });

        // Tabs
        $(document).on('click', '.stm-pm-tab-btn', function(e) {
            e.preventDefault();
            var target = $(this).data('tab');
            $('.stm-pm-tab-btn').removeClass('is-active');
            $(this).addClass('is-active');
            $('.stm-pm-tab-content').removeClass('is-active');
            $('#' + target).addClass('is-active');
        });

        // Image Selection & Preview
        function handleFile(file) {
            if (!file) return;

            // Strict Image Only validation
            var validTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            if (file.type && validTypes.indexOf(file.type) === -1 && file.type.indexOf('image/') !== 0) {
                showAlert('Invalid file! Strictly images only (JPG, PNG, WEBP, GIF).', 'error');
                $fileInput.val('');
                return;
            }

            var reader = new FileReader();
            reader.onload = function(e) {
                $previewImg.attr('src', e.target.result);
                $previewName.text(file.name);
                $previewWrap.addClass('has-image');
            };
            reader.readAsDataURL(file);
        }

        $(document).on('change', '#stm_pm_image_file', function() {
            if (this.files && this.files[0]) {
                handleFile(this.files[0]);
            }
        });

        $(document).on('click', '#stm_pm_preview_clear', function(e) {
            e.preventDefault();
            $fileInput.val('');
            $('#stm_pm_existing_image_url').val('');
            $previewWrap.removeClass('has-image');
            $previewImg.attr('src', '');
        });

        // Drag & Drop
        $(document).on('dragover dragenter', '#stm_pm_dropzone', function(e) {
            e.preventDefault();
            $(this).addClass('is-dragover');
        });
        $(document).on('dragleave dragend drop', '#stm_pm_dropzone', function(e) {
            e.preventDefault();
            $(this).removeClass('is-dragover');
        });
        $(document).on('drop', '#stm_pm_dropzone', function(e) {
            var dt = e.originalEvent.dataTransfer;
            if (dt && dt.files && dt.files[0]) {
                document.getElementById('stm_pm_image_file').files = dt.files;
                handleFile(dt.files[0]);
            }
        });

        function showAlert(msg, type) {
            $alert.removeClass('stm-alert-success stm-alert-error')
                  .addClass(type === 'success' ? 'stm-alert-success' : 'stm-alert-error')
                  .html(msg).show();
        }

        // Save Settings via AJAX
        $form.on('submit', function(e) {
            e.preventDefault();
            $alert.hide();
            $saveBtn.prop('disabled', true).text('Saving...');

            var formData = new FormData(this);
            formData.append('action', 'stm_save_popup_maker_settings');
            formData.append('nonce', config.adminNonce || '');

            $.ajax({
                url: config.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(res) {
                    $saveBtn.prop('disabled', false).text('Save Popup Settings');
                    if (res && res.success) {
                        showAlert('<strong>Success!</strong> Popup Maker settings saved successfully.', 'success');
                    } else {
                        showAlert(res && res.data && res.data.message ? res.data.message : 'Error saving settings.', 'error');
                    }
                },
                error: function() {
                    $saveBtn.prop('disabled', false).text('Save Popup Settings');
                    showAlert('Server communication error. Please try again.', 'error');
                }
            });
        });
    }

    /* ==========================================================================
       2. Frontend Live Popup Logic
       ========================================================================== */
    function initLivePopup() {
        var $livePopup = $('#stm-live-popup');
        if (!$livePopup.length) return;

        // Check delay
        var delaySec = parseInt($livePopup.data('delay'), 10) || 2;

        setTimeout(function() {
            // Check session/localStorage if already closed if desired, or show
            $livePopup.addClass('is-visible').attr('aria-hidden', 'false');
        }, delaySec * 1000);

        // Close Live Popup
        $(document).on('click', '[data-stm-live-close="1"]', function(e) {
            e.preventDefault();
            $livePopup.removeClass('is-visible').attr('aria-hidden', 'true');
        });

        $(document).on('keydown', function(e) {
            if (e.key === 'Escape' || e.keyCode === 27) {
                $livePopup.removeClass('is-visible').attr('aria-hidden', 'true');
            }
        });

        // Live Lead Submission
        $('#stm-live-lead-form').on('submit', function(e) {
            e.preventDefault();
            var $f = $(this);
            var $submit = $f.find('.stm-live-submit');
            var email = $.trim($f.find('input[name="lead_email"]').val());
            var phone = $.trim($f.find('input[name="lead_phone"]').val());

            if (!email && !phone) {
                alert('Please enter your email or phone number.');
                return;
            }

            $submit.prop('disabled', true).text('Submitting...');

            $.ajax({
                url: config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'stm_submit_popup_lead',
                    nonce: config.leadNonce,
                    lead_email: email,
                    lead_phone: phone
                },
                dataType: 'json',
                success: function(res) {
                    if (res && res.success) {
                        $f.hide();
                        $('#stm-live-thankyou').fadeIn();
                        setTimeout(function() {
                            $livePopup.removeClass('is-visible');
                        }, 3500);
                    } else {
                        $submit.prop('disabled', false).text('Submit');
                        alert(res && res.data && res.data.message ? res.data.message : 'Error submitting form.');
                    }
                },
                error: function() {
                    $submit.prop('disabled', false).text('Submit');
                    alert('Submission failed. Please check your connection.');
                }
            });
        });
    }

    $(document).ready(function() {
        initAdminModal();
        initLivePopup();
    });

})(jQuery);
