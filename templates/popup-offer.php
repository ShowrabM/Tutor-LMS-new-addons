<?php
defined( 'ABSPATH' ) || exit;
$uid = 'stm-offer-' . $popup['id'];
?>
<section class="stm-offer" id="<?php echo esc_attr( $uid ); ?>" data-popup-id="<?php echo esc_attr( $popup['id'] ); ?>" data-delay="<?php echo esc_attr( $popup['delay_sec'] ); ?>" data-frequency="<?php echo esc_attr( $popup['frequency_days'] ); ?>" data-preview="<?php echo $preview ? 'true' : 'false'; ?>" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="<?php echo esc_attr( $uid ); ?>-title" hidden>
    <div class="stm-offer-backdrop" data-offer-close></div>
    <div class="stm-offer-dialog" tabindex="-1">
        <button class="stm-offer-close" type="button" data-offer-close aria-label="Close offer"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg></button>
        <div class="stm-offer-visual <?php echo $popup['banner_url'] ? 'has-image' : ''; ?>">
            <?php if ( $popup['banner_url'] ) : ?><img data-src="<?php echo esc_url( $popup['banner_url'] ); ?>" alt="" decoding="async"><?php endif; ?>
            <span class="stm-offer-brand">MYTENNISCLOUD<span>BETTER TOGETHER. ON COURT.</span></span>
            <div class="stm-offer-visual-note"><span class="stm-offer-pill"><?php echo $popup['gift_id'] ? 'A GIFT FOR YOUR GAME' : 'YOUR NEXT STEP'; ?></span><p>More confidence.<br>More possibilities.</p><span class="stm-offer-rule"></span></div>
        </div>
        <div class="stm-offer-body">
            <div class="stm-offer-intro"><span class="stm-offer-eyebrow"><?php echo $popup['gift_id'] ? 'YOUR COMPLIMENTARY RESOURCE' : 'LET’S GET STARTED'; ?></span><h2 id="<?php echo esc_attr( $uid ); ?>-title"><?php echo esc_html( $popup['title'] ); ?></h2><?php if ( $popup['subtitle'] ) : ?><p><?php echo nl2br( esc_html( $popup['subtitle'] ) ); ?></p><?php endif; ?></div>
            <form class="stm-offer-form" method="post">
                <input type="hidden" name="popup_id" value="<?php echo esc_attr( $popup['id'] ); ?>">
                <input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( self::LEAD_NONCE ) ); ?>">
                <div class="stm-offer-honey" aria-hidden="true"><label>Leave this empty<input name="company_website" tabindex="-1" autocomplete="off"></label></div>
                <?php if ( $popup['field_name'] ) : ?><label for="<?php echo esc_attr( $uid ); ?>-name">Your name<input id="<?php echo esc_attr( $uid ); ?>-name" name="lead_name" autocomplete="name" maxlength="120" placeholder="Alex Morgan"></label><?php endif; ?>
                <label for="<?php echo esc_attr( $uid ); ?>-email"><span class="stm-offer-label">Email address <em aria-hidden="true">*</em></span><input id="<?php echo esc_attr( $uid ); ?>-email" type="email" name="lead_email" autocomplete="email" maxlength="120" placeholder="you@example.com" required></label>
                <?php if ( $popup['field_phone'] ) : ?><label for="<?php echo esc_attr( $uid ); ?>-phone">Phone number <small>Optional</small><input id="<?php echo esc_attr( $uid ); ?>-phone" type="tel" name="lead_phone" autocomplete="tel" maxlength="50" placeholder="Your contact number"></label><?php endif; ?>
                <?php if ( $popup['field_message'] ) : ?><label for="<?php echo esc_attr( $uid ); ?>-message">A note for us <small>Optional</small><textarea id="<?php echo esc_attr( $uid ); ?>-message" name="lead_message" rows="2" maxlength="5000" placeholder="What would you like to work on?"></textarea></label><?php endif; ?>
                <p class="stm-offer-error" role="alert" hidden></p>
                <button type="submit" class="stm-offer-submit"><span><?php echo esc_html( $popup['cta_text'] ); ?></span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 12h15m-6-6 6 6-6 6"/></svg></button>
                <p class="stm-offer-fine"><?php echo $popup['gift_id'] ? 'Your document will be ready immediately after you submit.' : 'By submitting, you request this offer from MyTennisCloud.'; ?></p>
            </form>
            <div class="stm-offer-success" role="status" tabindex="-1" hidden><span class="stm-offer-check" aria-hidden="true">✓</span><h3>You’re all set.</h3><p class="stm-offer-success-message"></p><a class="stm-offer-gift" href="#" hidden><span></span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12m-5-5 5 5 5-5M5 16v5h14v-5"/></svg></a><small class="stm-offer-gift-note" hidden>Your download link is available for 24 hours.</small><button type="button" class="stm-offer-done" data-offer-close>Done</button></div>
        </div>
    </div>
</section>
