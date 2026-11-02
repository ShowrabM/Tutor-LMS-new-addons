/**
 * STM Tutor Popup Maker - Admin Meta Box JavaScript
 * Handles WordPress native media uploader (wp.media), preview rendering, and form constraints.
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        var mediaFrame;
        var $uploadBtn  = $('#stm_pm_upload_banner_btn');
        var $removeBtn  = $('#stm_pm_remove_banner_btn');
        var $bannerUrl  = $('#stm_pm_banner_url');
        var $bannerId   = $('#stm_pm_banner_id');
        var $previewBox = $('#stm_pm_banner_preview_box');
        var $previewImg = $('#stm_pm_banner_preview_img');

        // Open Native WordPress Media Modal
        $uploadBtn.on('click', function(e) {
            e.preventDefault();

            if (mediaFrame) {
                mediaFrame.open();
                return;
            }

            mediaFrame = wp.media({
                title: 'Select or Upload Banner Image',
                button: {
                    text: 'Use this Image'
                },
                multiple: false,
                library: {
                    type: 'image'
                }
            });

            mediaFrame.on('select', function() {
                var attachment = mediaFrame.state().get('selection').first().toJSON();
                if (attachment && attachment.url) {
                    $bannerUrl.val(attachment.url);
                    $bannerId.val(attachment.id || 0);
                    $previewImg.attr('src', attachment.url);
                    $previewBox.show();
                    $uploadBtn.text('Change Banner Image');
                }
            });

            mediaFrame.open();
        });

        // Remove Image
        $removeBtn.on('click', function(e) {
            e.preventDefault();
            $bannerUrl.val('');
            $bannerId.val('0');
            $previewImg.attr('src', '');
            $previewBox.hide();
            $uploadBtn.text('Upload / Select Banner Image');
        });

        // Enforce Email Checkbox is always checked and required
        var $emailCheckbox = $('#stm_pm_field_email');
        if ($emailCheckbox.length) {
            $emailCheckbox.prop('checked', true);
            $emailCheckbox.on('change', function() {
                if (!$(this).is(':checked')) {
                    $(this).prop('checked', true);
                    alert('Email is enforced as a required field for this opt-in popup.');
                }
            });
        }
    });
})(jQuery);
