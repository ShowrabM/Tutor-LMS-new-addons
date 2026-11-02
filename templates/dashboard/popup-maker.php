<?php
/**
 * Tutor LMS Dashboard - Popup Maker Management Page
 * 
 * Specifically designed for ease of use, with large typography, clear controls,
 * and zero HTML needed. Accessible directly within the Tutor LMS Dashboard.
 */

defined( 'ABSPATH' ) || exit;

if ( ! current_user_can( 'manage_options' ) ) {
    ?>
    <div class="tutor-dashboard-content-inner">
        <div class="tutor-alert tutor-alert-warning">
            <?php esc_html_e( 'You do not have permission to access the Popup Maker settings.', 'tutor' ); ?>
        </div>
    </div>
    <?php
    return;
}

// Ensure all media modal styles and dashboard admin styles are properly loaded
if ( function_exists( 'wp_enqueue_media' ) ) {
    wp_enqueue_media();
    wp_enqueue_style( 'media-views' );
    wp_enqueue_style( 'buttons' );
    wp_enqueue_style( 'dashicons' );
    wp_enqueue_style( 'common' );
    wp_enqueue_style( 'forms' );
}

global $wpdb;

// Retrieve primary popup settings
$popup = STM_Tutor_Popup_Maker::get_primary_popup_data();

// Retrieve leads stats and recent records
$table_name = $wpdb->prefix . STM_Tutor_Popup_Maker::TABLE_NAME;
$total_leads = 0;
$recent_leads = array();

if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) ) === $table_name ) {
    $total_leads  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );
    $recent_leads = $wpdb->get_results( "SELECT * FROM {$table_name} ORDER BY id DESC LIMIT 100" );
}

$csv_download_url = wp_nonce_url(
    admin_url( 'admin-post.php?action=download_popup_leads_csv' ),
    'download_popup_leads_csv'
);
?>

<div class="tutor-dashboard-content-inner stm-tutor-pm-dashboard">

    <!-- Header Section -->
    <div class="tutor-flex tutor-items-center tutor-justify-between tutor-mb-24 stm-pm-dash-header">
        <div>
            <h3 class="tutor-h4 tutor-text-primary" style="margin:0 0 6px 0; font-size: 24px; font-weight: 700;">
                <span class="dashicons dashicons-slides" style="font-size:26px; width:26px; height:26px; vertical-align:middle; margin-right:6px; color:#0284c7;"></span>
                <?php esc_html_e( 'Popup Maker', 'tutor' ); ?>
            </h3>
            <p style="margin:0; color:#64748b; font-size:15px;">
                <?php esc_html_e( 'Easily configure your website opt-in popup and view customer inquiries without technical knowledge.', 'tutor' ); ?>
            </p>
        </div>
        <div class="stm-pm-header-action">
            <a href="<?php echo esc_url( $csv_download_url ); ?>" class="tutor-btn tutor-btn-primary tutor-btn-md stm-pm-download-btn">
                <span class="dashicons dashicons-download" style="margin-right:4px; vertical-align:middle;"></span>
                <?php esc_html_e( 'Download Leads (.CSV)', 'tutor' ); ?>
            </a>
        </div>
    </div>

    <!-- Friendly Navigation Tabs -->
    <div class="stm-pm-tabs-nav tutor-mb-24">
        <button type="button" class="stm-pm-tab-trigger is-active" data-target="stm-pm-tab-config">
            <span class="dashicons dashicons-admin-generic"></span>
            <?php esc_html_e( '1. Popup Settings & Design', 'tutor' ); ?>
        </button>
        <button type="button" class="stm-pm-tab-trigger" data-target="stm-pm-tab-leads">
            <span class="dashicons dashicons-email-alt"></span>
            <?php esc_html_e( '2. Captured Leads', 'tutor' ); ?>
            <span class="stm-pm-pill-badge"><?php echo esc_html( number_format_i18n( $total_leads ) ); ?></span>
        </button>
    </div>

    <!-- Alert Message Container -->
    <div id="stm_pm_dash_alert" class="stm-pm-dash-alert" style="display:none;"></div>

    <!-- =========================================================================
         TAB 1: POPUP SETTINGS
         ========================================================================= -->
    <div id="stm-pm-tab-config" class="stm-pm-tab-panel is-active">
        <form id="stm-pm-dashboard-form" method="post">
            <input type="hidden" name="popup_id" value="<?php echo esc_attr( $popup['id'] ); ?>">
            <input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'stm_popup_dashboard_nonce' ) ); ?>">

            <!-- Card 1: Active Status Toggle -->
            <div class="stm-pm-card tutor-mb-24">
                <div class="stm-pm-card-header">
                    <h4 class="stm-pm-card-title"><?php esc_html_e( 'Popup Status', 'tutor' ); ?></h4>
                    <p class="stm-pm-card-subtitle"><?php esc_html_e( 'Choose whether the popup should be active on your website or temporarily paused.', 'tutor' ); ?></p>
                </div>
                <div class="stm-pm-card-body">
                    <div class="stm-pm-status-toggle-wrap">
                        <label class="stm-pm-radio-card <?php echo ( 'publish' === $popup['status'] ) ? 'is-selected' : ''; ?>">
                            <input type="radio" name="enabled" value="1" <?php checked( 'publish', $popup['status'] ); ?>>
                            <span class="stm-pm-radio-icon">&#9679;</span>
                            <div>
                                <strong><?php esc_html_e( 'Active (Showing on Website)', 'tutor' ); ?></strong>
                                <p><?php esc_html_e( 'The popup will appear to visitors according to your delay and frequency settings.', 'tutor' ); ?></p>
                            </div>
                        </label>
                        <label class="stm-pm-radio-card <?php echo ( 'publish' !== $popup['status'] ) ? 'is-selected' : ''; ?>">
                            <input type="radio" name="enabled" value="0" <?php checked( 'draft', $popup['status'] ); ?>>
                            <span class="stm-pm-radio-icon" style="color:#94a3b8;">&#9675;</span>
                            <div>
                                <strong><?php esc_html_e( 'Paused (Hidden from Visitors)', 'tutor' ); ?></strong>
                                <p><?php esc_html_e( 'The popup is temporarily disabled and will not show to any visitors.', 'tutor' ); ?></p>
                            </div>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Card 2: Headline & Subtitle -->
            <div class="stm-pm-card tutor-mb-24">
                <div class="stm-pm-card-header">
                    <h4 class="stm-pm-card-title"><?php esc_html_e( 'Popup Text & Offer', 'tutor' ); ?></h4>
                    <p class="stm-pm-card-subtitle"><?php esc_html_e( 'Enter the main title and short offer message that visitors will see.', 'tutor' ); ?></p>
                </div>
                <div class="stm-pm-card-body">
                    <div class="stm-pm-form-group tutor-mb-16">
                        <label for="stm_pm_title" class="stm-pm-label"><?php esc_html_e( 'Popup Headline / Title', 'tutor' ); ?> <span class="stm-pm-req">*</span></label>
                        <input type="text" name="title" id="stm_pm_title" class="stm-pm-dash-input" value="<?php echo esc_attr( $popup['title'] ); ?>" required placeholder="<?php esc_attr_e( 'e.g. Exclusive Tennis Coaching Offer', 'tutor' ); ?>">
                        <span class="stm-pm-help"><?php esc_html_e( 'This is the main headline displayed at the top of the popup.', 'tutor' ); ?></span>
                    </div>

                    <div class="stm-pm-form-group">
                        <label for="stm_pm_subtitle" class="stm-pm-label"><?php esc_html_e( 'Description / Offer Subtitle', 'tutor' ); ?></label>
                        <textarea name="subtitle" id="stm_pm_subtitle" class="stm-pm-dash-textarea" rows="2" placeholder="<?php esc_attr_e( 'e.g. Enter your email to receive a personalized stroke assessment and lesson discounts.', 'tutor' ); ?>"><?php echo esc_textarea( $popup['subtitle'] ); ?></textarea>
                        <span class="stm-pm-help"><?php esc_html_e( 'A brief sentence explaining what the visitor receives when submitting.', 'tutor' ); ?></span>
                    </div>
                </div>
            </div>

            <!-- Card 3: Banner Image Uploader (Direct In-Page Upload + Media Library) -->
            <div class="stm-pm-card tutor-mb-24">
                <div class="stm-pm-card-header">
                    <h4 class="stm-pm-card-title"><?php esc_html_e( 'Popup Banner Image', 'tutor' ); ?></h4>
                    <p class="stm-pm-card-subtitle"><?php esc_html_e( 'Upload an attractive banner picture to show at the top of your popup.', 'tutor' ); ?></p>
                </div>
                <div class="stm-pm-card-body">
                    <input type="hidden" name="banner_url" id="stm_pm_dash_banner_url" value="<?php echo esc_url( $popup['banner_url'] ); ?>">
                    <input type="hidden" name="banner_id" id="stm_pm_dash_banner_id" value="<?php echo esc_attr( $popup['banner_id'] ); ?>">
                    <input type="file" name="banner_file" id="stm_pm_dash_file_input" accept="image/jpeg,image/png,image/webp,image/gif" style="display:none;">

                    <!-- Direct Dropzone & File Chooser Box -->
                    <div class="stm-pm-dropzone-box" id="stm_pm_dash_dropzone" style="<?php echo ! empty( $popup['banner_url'] ) ? 'display:none;' : ''; ?>">
                        <div class="stm-pm-dropzone-icon">
                            <svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="#0284c7" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                <polyline points="21 15 16 10 5 21"></polyline>
                            </svg>
                        </div>
                        <div class="stm-pm-dropzone-title"><?php esc_html_e( 'Click to Choose Photo from Computer', 'tutor' ); ?></div>
                        <div class="stm-pm-dropzone-desc"><?php esc_html_e( 'or drag and drop your image file here (JPG, PNG, WEBP, GIF)', 'tutor' ); ?></div>
                        <div class="tutor-flex tutor-items-center tutor-justify-center" style="gap:12px; flex-wrap:wrap;">
                            <button type="button" class="tutor-btn tutor-btn-primary tutor-btn-md" id="stm_pm_dash_choose_file_btn">
                                <span class="dashicons dashicons-upload" style="margin-right:6px; vertical-align:middle;"></span>
                                <?php esc_html_e( 'Choose Photo from Computer', 'tutor' ); ?>
                            </button>
                            <button type="button" class="tutor-btn tutor-btn-outline-primary tutor-btn-md" id="stm_pm_dash_media_lib_btn">
                                <span class="dashicons dashicons-admin-media" style="margin-right:6px; vertical-align:middle;"></span>
                                <?php esc_html_e( 'Select from Media Library', 'tutor' ); ?>
                            </button>
                        </div>
                    </div>

                    <!-- Upload Progress Indicator -->
                    <div id="stm_pm_dash_upload_status" class="stm-pm-upload-progress" style="display:none;">
                        <span class="stm-pm-btn-spinner" style="border-color: rgba(2, 132, 199, 0.25); border-top-color: #0284c7;"></span>
                        <span id="stm_pm_dash_upload_status_text"><?php esc_html_e( 'Uploading photo... Please wait a moment.', 'tutor' ); ?></span>
                    </div>

                    <!-- Preview Container -->
                    <div id="stm_pm_dash_banner_preview" class="stm-pm-dash-banner-preview" style="<?php echo empty( $popup['banner_url'] ) ? 'display:none;' : ''; ?>">
                        <img src="<?php echo esc_url( $popup['banner_url'] ); ?>" id="stm_pm_dash_banner_img" alt="Banner Preview">
                        <div class="tutor-flex tutor-items-center tutor-justify-between tutor-mt-12" style="flex-wrap:wrap; gap:10px;">
                            <span id="stm_pm_dash_img_name" style="font-size:13px; color:#64748b; font-weight:600;">
                                <?php echo ! empty( $popup['banner_url'] ) ? esc_html( basename( $popup['banner_url'] ) ) : ''; ?>
                            </span>
                            <div style="display:flex; gap:8px;">
                                <button type="button" class="tutor-btn tutor-btn-outline-primary tutor-btn-sm" id="stm_pm_dash_change_btn">
                                    <span class="dashicons dashicons-update" style="vertical-align:middle; margin-right:4px;"></span>
                                    <?php esc_html_e( 'Change Photo', 'tutor' ); ?>
                                </button>
                                <button type="button" class="tutor-btn tutor-btn-danger tutor-btn-sm" id="stm_pm_dash_remove_banner_btn">
                                    <span class="dashicons dashicons-trash" style="vertical-align:middle; margin-right:4px;"></span>
                                    <?php esc_html_e( 'Remove Photo', 'tutor' ); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card 4: Form Fields Checklist -->
            <div class="stm-pm-card tutor-mb-24">
                <div class="stm-pm-card-header">
                    <h4 class="stm-pm-card-title"><?php esc_html_e( 'Form Fields Checklist', 'tutor' ); ?></h4>
                    <p class="stm-pm-card-subtitle"><?php esc_html_e( 'Check the boxes for the details you want visitors to fill out. Email is always required.', 'tutor' ); ?></p>
                </div>
                <div class="stm-pm-card-body">
                    <div class="stm-pm-checklist-grid">
                        
                        <!-- Name Field -->
                        <label class="stm-pm-field-box">
                            <input type="checkbox" name="field_name" value="1" <?php checked( 1, $popup['field_name'] ); ?>>
                            <div class="stm-pm-field-info">
                                <strong><?php esc_html_e( 'Visitor Name', 'tutor' ); ?></strong>
                                <span><?php esc_html_e( 'Asks for full name', 'tutor' ); ?></span>
                            </div>
                        </label>

                        <!-- Email Field (Strictly Required) -->
                        <label class="stm-pm-field-box is-required">
                            <input type="checkbox" name="field_email" value="1" checked onclick="return false;">
                            <div class="stm-pm-field-info">
                                <strong><?php esc_html_e( 'Email Address', 'tutor' ); ?></strong>
                                <span><?php esc_html_e( 'Always required for sending offer', 'tutor' ); ?></span>
                            </div>
                            <span class="stm-pm-req-badge"><?php esc_html_e( 'Required', 'tutor' ); ?></span>
                        </label>

                        <!-- Phone Field -->
                        <label class="stm-pm-field-box">
                            <input type="checkbox" name="field_phone" value="1" <?php checked( 1, $popup['field_phone'] ); ?>>
                            <div class="stm-pm-field-info">
                                <strong><?php esc_html_e( 'Phone Number', 'tutor' ); ?></strong>
                                <span><?php esc_html_e( 'Asks for telephone contact', 'tutor' ); ?></span>
                            </div>
                        </label>

                        <!-- Message Field -->
                        <label class="stm-pm-field-box">
                            <input type="checkbox" name="field_message" value="1" <?php checked( 1, $popup['field_message'] ); ?>>
                            <div class="stm-pm-field-info">
                                <strong><?php esc_html_e( 'Message / Note', 'tutor' ); ?></strong>
                                <span><?php esc_html_e( 'Optional inquiry comment box', 'tutor' ); ?></span>
                            </div>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Card 5: Timing & Button Controls -->
            <div class="stm-pm-card tutor-mb-24">
                <div class="stm-pm-card-header">
                    <h4 class="stm-pm-card-title"><?php esc_html_e( 'Display Timing & Button', 'tutor' ); ?></h4>
                    <p class="stm-pm-card-subtitle"><?php esc_html_e( 'Control when the popup appears and what the main button says.', 'tutor' ); ?></p>
                </div>
                <div class="stm-pm-card-body">
                    <div class="stm-pm-grid-row">
                        <div class="stm-pm-form-group">
                            <label for="stm_pm_delay_sec" class="stm-pm-label"><?php esc_html_e( 'Popup Delay (Seconds)', 'tutor' ); ?></label>
                            <input type="number" name="delay_sec" id="stm_pm_delay_sec" class="stm-pm-dash-input" value="<?php echo esc_attr( $popup['delay_sec'] ); ?>" min="0" max="60" step="1">
                            <span class="stm-pm-help"><?php esc_html_e( 'Wait this many seconds before popup opens (e.g. 2, 5).', 'tutor' ); ?></span>
                        </div>

                        <div class="stm-pm-form-group">
                            <label for="stm_pm_frequency_days" class="stm-pm-label"><?php esc_html_e( 'Frequency Cap (Days)', 'tutor' ); ?></label>
                            <input type="number" name="frequency_days" id="stm_pm_frequency_days" class="stm-pm-dash-input" value="<?php echo esc_attr( $popup['frequency_days'] ); ?>" min="0" max="365" step="1">
                            <span class="stm-pm-help"><?php esc_html_e( 'If closed, do not show again for this many days (e.g. 7).', 'tutor' ); ?></span>
                        </div>
                    </div>

                    <div class="stm-pm-form-group tutor-mt-16">
                        <label for="stm_pm_cta_text" class="stm-pm-label"><?php esc_html_e( 'Submit Button Label', 'tutor' ); ?></label>
                        <input type="text" name="cta_text" id="stm_pm_cta_text" class="stm-pm-dash-input" value="<?php echo esc_attr( $popup['cta_text'] ); ?>" placeholder="<?php esc_attr_e( 'Submit', 'tutor' ); ?>">
                        <span class="stm-pm-help"><?php esc_html_e( 'The text written on the button visitors click (e.g. Submit, Claim My Offer, Get Started).', 'tutor' ); ?></span>
                    </div>
                </div>
            </div>

            <!-- Sticky / Prominent Save Bar -->
            <div class="stm-pm-save-bar">
                <button type="submit" id="stm_pm_dash_save_btn" class="tutor-btn tutor-btn-primary tutor-btn-lg" style="font-size: 16px; padding: 14px 32px; font-weight: 700;">
                    <span class="dashicons dashicons-yes-alt" style="vertical-align:middle; margin-right:6px;"></span>
                    <span id="stm_pm_dash_save_label"><?php esc_html_e( 'Save All Popup Settings', 'tutor' ); ?></span>
                </button>
            </div>
        </form>
    </div>

    <!-- =========================================================================
         TAB 2: CAPTURED LEADS
         ========================================================================= -->
    <div id="stm-pm-tab-leads" class="stm-pm-tab-panel" style="display:none;">
        <div class="stm-pm-card">
            <div class="stm-pm-card-header tutor-flex tutor-items-center tutor-justify-between">
                <div>
                    <h4 class="stm-pm-card-title"><?php esc_html_e( 'Captured Leads List', 'tutor' ); ?></h4>
                    <p class="stm-pm-card-subtitle"><?php esc_html_e( 'All customer inquiries submitted through your website popup modal.', 'tutor' ); ?></p>
                </div>
                <div>
                    <a href="<?php echo esc_url( $csv_download_url ); ?>" class="tutor-btn tutor-btn-primary tutor-btn-md">
                        <span class="dashicons dashicons-download" style="margin-right:4px; vertical-align:middle;"></span>
                        <?php esc_html_e( 'Download All Leads (.CSV)', 'tutor' ); ?>
                    </a>
                </div>
            </div>
            <div class="stm-pm-card-body" style="padding:0;">
                <?php if ( empty( $recent_leads ) ) : ?>
                    <div style="text-align:center; padding: 60px 20px; color:#64748b;">
                        <span class="dashicons dashicons-email-alt" style="font-size:48px; width:48px; height:48px; color:#cbd5e1; margin-bottom:12px;"></span>
                        <h4 style="margin:0 0 6px 0; color:#334155; font-size:18px;"><?php esc_html_e( 'No leads captured yet', 'tutor' ); ?></h4>
                        <p style="margin:0;"><?php esc_html_e( 'When visitors submit the popup on your website, their contact details will appear here automatically.', 'tutor' ); ?></p>
                    </div>
                <?php else : ?>
                    <div class="stm-pm-table-scroll">
                        <table class="stm-pm-dash-table">
                            <thead>
                                <tr>
                                    <th>#ID</th>
                                    <th><?php esc_html_e( 'Name', 'tutor' ); ?></th>
                                    <th><?php esc_html_e( 'Email Address', 'tutor' ); ?></th>
                                    <th><?php esc_html_e( 'Phone', 'tutor' ); ?></th>
                                    <th><?php esc_html_e( 'Message / Note', 'tutor' ); ?></th>
                                    <th><?php esc_html_e( 'Page URL', 'tutor' ); ?></th>
                                    <th><?php esc_html_e( 'Date & Time', 'tutor' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $recent_leads as $lead ) : ?>
                                    <tr>
                                        <td><strong>#<?php echo esc_html( $lead->id ); ?></strong></td>
                                        <td><strong><?php echo ! empty( $lead->name ) ? esc_html( $lead->name ) : '&mdash;'; ?></strong></td>
                                        <td>
                                            <a href="mailto:<?php echo esc_attr( $lead->email ); ?>" style="color:#0284c7; font-weight:600;">
                                                <?php echo esc_html( $lead->email ); ?>
                                            </a>
                                        </td>
                                        <td>
                                            <?php if ( ! empty( $lead->phone ) ) : ?>
                                                <a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9\+]/', '', $lead->phone ) ); ?>" style="color:#0284c7;">
                                                    <?php echo esc_html( $lead->phone ); ?>
                                                </a>
                                            <?php else : ?>
                                                &mdash;
                                            <?php endif; ?>
                                        </td>
                                        <td style="max-width:260px;"><?php echo ! empty( $lead->message ) ? esc_html( $lead->message ) : '&mdash;'; ?></td>
                                        <td>
                                            <?php if ( ! empty( $lead->page_url ) ) : ?>
                                                <a href="<?php echo esc_url( $lead->page_url ); ?>" target="_blank" rel="noopener noreferrer" style="color:#64748b; font-size:12px;">
                                                    <?php echo esc_html( wp_trim_words( $lead->page_url, 4, '...' ) ); ?>
                                                </a>
                                            <?php else : ?>
                                                &mdash;
                                            <?php endif; ?>
                                        </td>
                                        <td style="white-space:nowrap; color:#64748b; font-size:13px;">
                                            <?php echo esc_html( mysql2date( 'M j, Y g:i a', $lead->created_at ) ); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>

<!-- Inline Styles for Tutor LMS Dashboard Popup Maker (Elderly-Friendly & Modern) -->
<style>
.stm-tutor-pm-dashboard {
    font-size: 15px;
    color: #1e293b;
}
.stm-pm-dash-header {
    flex-wrap: wrap;
    gap: 16px;
}
.stm-pm-tabs-nav {
    display: flex;
    gap: 12px;
    border-bottom: 2px solid #e2e8f0;
    padding-bottom: 8px;
}
.stm-pm-tab-trigger {
    background: transparent;
    border: none;
    font-size: 16px;
    font-weight: 700;
    color: #64748b;
    padding: 10px 18px;
    border-radius: 8px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s ease;
}
.stm-pm-tab-trigger:hover {
    color: #0f172a;
    background: #f1f5f9;
}
.stm-pm-tab-trigger.is-active {
    color: #0284c7;
    background: #e0f2fe;
}
.stm-pm-pill-badge {
    background: #0284c7;
    color: #ffffff;
    font-size: 12px;
    padding: 2px 8px;
    border-radius: 12px;
    font-weight: 700;
}
.stm-pm-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.03);
    overflow: hidden;
}
.stm-pm-card-header {
    padding: 18px 24px;
    border-bottom: 1px solid #f1f5f9;
    background: #fafafa;
}
.stm-pm-card-title {
    margin: 0 0 4px 0;
    font-size: 18px;
    font-weight: 700;
    color: #0f172a;
}
.stm-pm-card-subtitle {
    margin: 0;
    font-size: 14px;
    color: #64748b;
}
.stm-pm-card-body {
    padding: 24px;
}
.stm-pm-status-toggle-wrap {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}
@media (max-width: 640px) {
    .stm-pm-status-toggle-wrap {
        grid-template-columns: 1fr;
    }
}
.stm-pm-radio-card {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    padding: 16px 20px;
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    cursor: pointer;
    transition: border-color 0.2s, background 0.2s;
}
.stm-pm-radio-card:hover {
    background: #f8fafc;
}
.stm-pm-radio-card.is-selected {
    border-color: #0284c7;
    background: #f0f9ff;
}
.stm-pm-radio-card input {
    margin-top: 4px;
}
.stm-pm-radio-card strong {
    display: block;
    font-size: 16px;
    color: #0f172a;
    margin-bottom: 4px;
}
.stm-pm-radio-card p {
    margin: 0;
    font-size: 13px;
    color: #64748b;
    line-height: 1.4;
}
.stm-pm-radio-icon {
    font-size: 20px;
    color: #0284c7;
    line-height: 1;
}
.stm-pm-label {
    display: block;
    font-size: 15px;
    font-weight: 700;
    color: #334155;
    margin-bottom: 6px;
}
.stm-pm-req {
    color: #ef4444;
}
.stm-pm-dash-input,
.stm-pm-dash-textarea {
    width: 100%;
    padding: 12px 16px;
    font-size: 15px;
    border: 1.5px solid #cbd5e1;
    border-radius: 8px;
    background: #ffffff;
    box-sizing: border-box;
    transition: border-color 0.2s, box-shadow 0.2s;
}
.stm-pm-dash-input:focus,
.stm-pm-dash-textarea:focus {
    outline: none;
    border-color: #0284c7;
    box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.15);
}
.stm-pm-help {
    display: block;
    font-size: 13px;
    color: #64748b;
    margin-top: 5px;
}
.stm-pm-checklist-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
}
@media (max-width: 640px) {
    .stm-pm-checklist-grid {
        grid-template-columns: 1fr;
    }
}
.stm-pm-field-box {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 18px;
    border: 1.5px solid #e2e8f0;
    border-radius: 8px;
    cursor: pointer;
    background: #ffffff;
    transition: background 0.15s;
}
.stm-pm-field-box:hover {
    background: #f8fafc;
}
.stm-pm-field-box.is-required {
    background: #f0fdf4;
    border-color: #86efac;
    cursor: default;
}
.stm-pm-field-info strong {
    display: block;
    font-size: 15px;
    color: #0f172a;
}
.stm-pm-field-info span {
    font-size: 12px;
    color: #64748b;
}
.stm-pm-req-badge {
    margin-left: auto;
    font-size: 11px;
    font-weight: 700;
    background: #bbf7d0;
    color: #166534;
    padding: 3px 8px;
    border-radius: 4px;
}
.stm-pm-dash-banner-preview {
    max-width: 440px;
    background: #f8fafc;
    border: 2px dashed #cbd5e1;
    border-radius: 8px;
    padding: 12px;
    box-sizing: border-box;
}
.stm-pm-dash-banner-preview img {
    width: 100%;
    max-height: 220px;
    object-fit: cover;
    border-radius: 6px;
    display: block;
}
.stm-pm-grid-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}
@media (max-width: 640px) {
    .stm-pm-grid-row {
        grid-template-columns: 1fr;
    }
}
.stm-pm-save-bar {
    padding: 20px 0 40px;
}
.stm-pm-dash-alert {
    padding: 16px 20px;
    border-radius: 8px;
    font-size: 15px;
    font-weight: 600;
    margin-bottom: 24px;
    animation: stm-pm-fadeIn 0.2s ease-in;
}
.stm-pm-dash-alert.is-success {
    background: #ecfdf5;
    border: 1.5px solid #34d399;
    color: #065f46;
}
.stm-pm-dash-alert.is-error {
    background: #fef2f2;
    border: 1.5px solid #f87171;
    color: #991b1b;
}
.stm-pm-table-scroll {
    overflow-x: auto;
}
.stm-pm-dash-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}
.stm-pm-dash-table th {
    background: #f8fafc;
    color: #475569;
    font-weight: 700;
    text-align: left;
    padding: 14px 18px;
    border-bottom: 1.5px solid #e2e8f0;
}
.stm-pm-dash-table td {
    padding: 14px 18px;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
}
.stm-pm-dash-table tr:hover td {
    background: #f8fafc;
}
@keyframes stm-pm-fadeIn {
    from { opacity: 0; transform: translateY(-6px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>

<!-- Dashboard JavaScript Logic -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Tab Switching
    var tabTriggers = document.querySelectorAll('.stm-pm-tab-trigger');
    var tabPanels   = document.querySelectorAll('.stm-pm-tab-panel');

    tabTriggers.forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var target = this.getAttribute('data-target');

            tabTriggers.forEach(function(b) { b.classList.remove('is-active'); });
            this.classList.add('is-active');

            tabPanels.forEach(function(p) { p.style.display = 'none'; });
            var activePanel = document.getElementById(target);
            if (activePanel) {
                activePanel.style.display = 'block';
            }
        });
    });

    // 2. Status Radio Highlight
    var statusRadios = document.querySelectorAll('input[name="enabled"]');
    statusRadios.forEach(function(radio) {
        radio.addEventListener('change', function() {
            document.querySelectorAll('.stm-pm-radio-card').forEach(function(c) {
                c.classList.remove('is-selected');
            });
            if (this.closest('.stm-pm-radio-card')) {
                this.closest('.stm-pm-radio-card').classList.add('is-selected');
            }
        });
    });

    // 3. Direct In-Page Photo Uploader & Media Library Fallback
    var dropzone       = document.getElementById('stm_pm_dash_dropzone');
    var fileInput      = document.getElementById('stm_pm_dash_file_input');
    var chooseFileBtn  = document.getElementById('stm_pm_dash_choose_file_btn');
    var mediaLibBtn    = document.getElementById('stm_pm_dash_media_lib_btn');
    var changeBtn      = document.getElementById('stm_pm_dash_change_btn');
    var removeBtn      = document.getElementById('stm_pm_dash_remove_banner_btn');
    var bannerUrl      = document.getElementById('stm_pm_dash_banner_url');
    var bannerId       = document.getElementById('stm_pm_dash_banner_id');
    var previewBox     = document.getElementById('stm_pm_dash_banner_preview');
    var previewImg     = document.getElementById('stm_pm_dash_banner_img');
    var imgNameSpan    = document.getElementById('stm_pm_dash_img_name');
    var uploadStatus   = document.getElementById('stm_pm_dash_upload_status');
    var uploadStatusTxt= document.getElementById('stm_pm_dash_upload_status_text');
    var ajaxEndpoint   = <?php echo json_encode( admin_url( 'admin-ajax.php' ) ); ?>;

    function handleFileSelection(file) {
        if (!file) return;

        if (!file.type.match(/^image\//i)) {
            alert('Please select a valid image file (JPG, PNG, WEBP, or GIF).');
            return;
        }

        // 1. Instant local preview for immediate visual feedback
        var reader = new FileReader();
        reader.onload = function(e) {
            if (previewImg) {
                previewImg.src = e.target.result;
            }
            if (imgNameSpan) {
                imgNameSpan.textContent = file.name;
            }
            if (previewBox) {
                previewBox.style.display = 'block';
            }
            if (dropzone) {
                dropzone.style.display = 'none';
            }
        };
        reader.readAsDataURL(file);

        // 2. Immediate asynchronous upload to WordPress Media Library
        if (uploadStatus && uploadStatusTxt) {
            uploadStatus.style.display = 'flex';
            uploadStatus.style.color = '#0284c7';
            uploadStatusTxt.textContent = 'Uploading photo... Please wait a moment.';
        }

        var nonceInput = document.querySelector('input[name="nonce"]');
        var nonceVal = nonceInput ? nonceInput.value : '';

        var uploadData = new FormData();
        uploadData.append('action', 'stm_pm_dashboard_upload_image');
        uploadData.append('nonce', nonceVal);
        uploadData.append('banner_file', file);

        fetch(ajaxEndpoint, {
            method: 'POST',
            body: uploadData
        })
        .then(function(res) { return res.json(); })
        .then(function(res) {
            if (res && res.success && res.data) {
                if (bannerUrl) {
                    bannerUrl.value = res.data.url;
                }
                if (bannerId) {
                    bannerId.value = res.data.id || 0;
                }
                if (previewImg) {
                    previewImg.src = res.data.url;
                }
                if (imgNameSpan) {
                    imgNameSpan.textContent = res.data.url.split('/').pop();
                }
                if (uploadStatus && uploadStatusTxt) {
                    uploadStatus.style.color = '#16a34a';
                    uploadStatusTxt.innerHTML = '&#10003; ' + (res.data.message || 'Photo uploaded successfully!');
                    setTimeout(function() {
                        uploadStatus.style.display = 'none';
                    }, 3500);
                }
            } else {
                var err = (res && res.data && res.data.message) ? res.data.message : 'Photo upload failed. Please try again.';
                if (uploadStatus && uploadStatusTxt) {
                    uploadStatus.style.color = '#dc2626';
                    uploadStatusTxt.textContent = err;
                } else {
                    alert(err);
                }
            }
        })
        .catch(function(err) {
            if (uploadStatus && uploadStatusTxt) {
                uploadStatus.style.color = '#0284c7';
                uploadStatusTxt.textContent = 'Photo selected! It will be saved when you click "Save All Popup Settings".';
            }
        });
    }

    if (chooseFileBtn && fileInput) {
        chooseFileBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            fileInput.click();
        });
    }

    if (changeBtn && fileInput) {
        changeBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            fileInput.click();
        });
    }

    if (fileInput) {
        fileInput.addEventListener('change', function(e) {
            if (this.files && this.files.length > 0) {
                handleFileSelection(this.files[0]);
            }
        });
    }

    if (dropzone && fileInput) {
        dropzone.addEventListener('click', function(e) {
            if (e.target.closest('#stm_pm_dash_media_lib_btn')) {
                return;
            }
            fileInput.click();
        });

        ['dragenter', 'dragover'].forEach(function(eventName) {
            dropzone.addEventListener(eventName, function(e) {
                e.preventDefault();
                e.stopPropagation();
                dropzone.classList.add('is-dragover');
            }, false);
        });

        ['dragleave', 'drop'].forEach(function(eventName) {
            dropzone.addEventListener(eventName, function(e) {
                e.preventDefault();
                e.stopPropagation();
                dropzone.classList.remove('is-dragover');
            }, false);
        });

        dropzone.addEventListener('drop', function(e) {
            var dt = e.dataTransfer;
            if (dt && dt.files && dt.files.length > 0) {
                handleFileSelection(dt.files[0]);
            }
        });
    }

    if (removeBtn) {
        removeBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if (bannerUrl) bannerUrl.value = '';
            if (bannerId) bannerId.value = '0';
            if (previewImg) previewImg.src = '';
            if (imgNameSpan) imgNameSpan.textContent = '';
            if (fileInput) fileInput.value = '';
            if (uploadStatus) uploadStatus.style.display = 'none';
            if (previewBox) previewBox.style.display = 'none';
            if (dropzone) dropzone.style.display = 'block';
        });
    }

    // WordPress Media Library modal (secondary fallback)
    var mediaFrame;
    if (mediaLibBtn) {
        mediaLibBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            if (typeof wp === 'undefined' || !wp.media) {
                alert('WordPress Media Library is unavailable. Please click "Choose Photo from Computer" to select a file.');
                return;
            }

            if (mediaFrame) {
                mediaFrame.open();
                return;
            }

            mediaFrame = wp.media({
                title: 'Select or Upload Banner Image',
                button: { text: 'Use this Image' },
                multiple: false,
                library: { type: 'image' }
            });

            mediaFrame.on('select', function() {
                var attachment = mediaFrame.state().get('selection').first().toJSON();
                if (attachment && attachment.url) {
                    if (bannerUrl) bannerUrl.value = attachment.url;
                    if (bannerId) bannerId.value = attachment.id || 0;
                    if (previewImg) previewImg.src = attachment.url;
                    if (imgNameSpan) {
                        imgNameSpan.textContent = attachment.filename || attachment.url.split('/').pop();
                    }
                    if (previewBox) previewBox.style.display = 'block';
                    if (dropzone) dropzone.style.display = 'none';
                    if (uploadStatus) uploadStatus.style.display = 'none';
                }
            });

            mediaFrame.open();
        });
    }

    // 4. AJAX Form Submission
    var form     = document.getElementById('stm-pm-dashboard-form');
    var alertBox = document.getElementById('stm_pm_dash_alert');
    var saveBtn  = document.getElementById('stm_pm_dash_save_btn');
    var saveLbl  = document.getElementById('stm_pm_dash_save_label');

    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();

            if (alertBox) {
                alertBox.style.display = 'none';
                alertBox.className = 'stm-pm-dash-alert';
                alertBox.textContent = '';
            }

            if (saveBtn) {
                saveBtn.disabled = true;
            }
            if (saveLbl) {
                saveLbl.textContent = 'Saving Settings...';
            }

            var formData = new FormData(form);
            formData.append('action', 'stm_save_dashboard_popup_settings');

            fetch(ajaxEndpoint, {
                method: 'POST',
                body: formData
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (saveBtn) {
                    saveBtn.disabled = false;
                }
                if (saveLbl) {
                    saveLbl.textContent = 'Save All Popup Settings';
                }

                if (data && data.success) {
                    if (alertBox) {
                        alertBox.textContent = data.data && data.data.message ? data.data.message : 'Popup settings saved successfully!';
                        alertBox.className = 'stm-pm-dash-alert is-success';
                        alertBox.style.display = 'block';
                        alertBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                } else {
                    var errorMsg = data && data.data && data.data.message ? data.data.message : 'Failed to save settings. Please try again.';
                    if (alertBox) {
                        alertBox.textContent = errorMsg;
                        alertBox.className = 'stm-pm-dash-alert is-error';
                        alertBox.style.display = 'block';
                        alertBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    } else {
                        alert(errorMsg);
                    }
                }
            })
            .catch(function(err) {
                if (saveBtn) {
                    saveBtn.disabled = false;
                }
                if (saveLbl) {
                    saveLbl.textContent = 'Save All Popup Settings';
                }
                if (alertBox) {
                    alertBox.textContent = 'Network communication error. Please try again.';
                    alertBox.className = 'stm-pm-dash-alert is-error';
                    alertBox.style.display = 'block';
                }
            });
        });
    }
});
</script>
