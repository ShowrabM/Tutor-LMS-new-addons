<?php
/**
 * STM Tutor Membership Dashboard
 * Adds clean direct links in the Tutor LMS sidebar pointing straight
 * to native Paid Memberships Pro pages.
 *
 * @package Tutor_LMS_Customization
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class STM_Tutor_Membership_Dashboard {

    public function __construct() {
        // Register direct sidebar links in Tutor LMS Dashboard for students and instructors
        add_filter( 'tutor_dashboard/nav_items', array( $this, 'register_membership_nav_items' ), 20 );
        add_filter( 'tutor_dashboard/instructor_nav_items', array( $this, 'register_membership_nav_items' ), 20 );
    }

    /**
     * Get direct URLs for native Paid Memberships Pro pages
     */
    public function get_pmpro_urls() {
        $account_url = function_exists( 'pmpro_url' ) ? pmpro_url( 'account' ) : home_url( '/membership-account/' );
        $billing_url = function_exists( 'pmpro_url' ) ? pmpro_url( 'billing' ) : home_url( '/membership-billing/' );
        $orders_url  = function_exists( 'pmpro_url' ) ? pmpro_url( 'invoices' ) : '';
        if ( empty( $orders_url ) ) {
            $orders_url = get_permalink( 2488 ) ?: home_url( '/membership-orders/' );
        }
        $pricing_url = home_url( '/membership-pricing/' );

        return array(
            'account' => $account_url,
            'billing' => $billing_url,
            'orders'  => $orders_url,
            'pricing' => $pricing_url,
        );
    }

    /**
     * Add clean direct links to native PMPro pages in the dashboard sidebar
     */
    public function register_membership_nav_items( $items ) {
        if ( ! is_user_logged_in() ) {
            return $items;
        }

        $urls = $this->get_pmpro_urls();

        $sub_icon   = class_exists( '\TUTOR\Icon' ) && defined( '\TUTOR\Icon::SUBSCRIPTION' ) ? \TUTOR\Icon::SUBSCRIPTION : 'subscription';
        $bill_icon  = class_exists( '\TUTOR\Icon' ) && defined( '\TUTOR\Icon::BILLING' ) ? \TUTOR\Icon::BILLING : 'billing';
        $order_icon = class_exists( '\TUTOR\Icon' ) && defined( '\TUTOR\Icon::BOX_PRICE' ) ? \TUTOR\Icon::BOX_PRICE : 'box-price';
        $plan_icon  = class_exists( '\TUTOR\Icon' ) && defined( '\TUTOR\Icon::LEVEL' ) ? \TUTOR\Icon::LEVEL : 'level';

        $membership_items = array(
            'membership-account' => array(
                'title'       => __( 'Membership Account', 'tutor' ),
                'icon'        => $sub_icon,
                'active_icon' => $sub_icon,
                'url'         => esc_url( $urls['account'] ),
            ),
            'membership-billing' => array(
                'title'       => __( 'Membership Billing', 'tutor' ),
                'icon'        => $bill_icon,
                'active_icon' => $bill_icon,
                'url'         => esc_url( $urls['billing'] ),
            ),
            'membership-orders' => array(
                'title'       => __( 'Invoices & Receipts', 'tutor' ),
                'icon'        => $order_icon,
                'active_icon' => $order_icon,
                'url'         => esc_url( $urls['orders'] ),
            ),
            'membership-plans' => array(
                'title'       => __( 'Membership Plans', 'tutor' ),
                'icon'        => $plan_icon,
                'active_icon' => $plan_icon,
                'url'         => esc_url( $urls['pricing'] ),
            ),
        );

        // Insert before 'settings' or 'logout' if available, otherwise append
        $new_items = array();
        $inserted  = false;

        foreach ( $items as $key => $data ) {
            if ( ! $inserted && in_array( $key, array( 'settings', 'logout' ), true ) ) {
                foreach ( $membership_items as $m_key => $m_data ) {
                    $new_items[ $m_key ] = $m_data;
                }
                $inserted = true;
            }
            $new_items[ $key ] = $data;
        }

        if ( ! $inserted ) {
            foreach ( $membership_items as $m_key => $m_data ) {
                $new_items[ $m_key ] = $m_data;
            }
        }

        return $new_items;
    }
}
