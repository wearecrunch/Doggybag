<?php
/**
 * Flatsome functions and definitions
 *
 * @package flatsome
 */

require get_template_directory() . '/inc/init.php';

add_filter ( 'woocommerce_default_address_fields' , 'custom_override_default_address_fields' );
function custom_override_default_address_fields( $address_fields ) {
    unset($address_fields['company']);
    return $address_fields;
}
add_filter('woocommerce_currency_symbol', 'change_existing_currency_symbol', 10, 2);

function change_existing_currency_symbol( $currency_symbol, $currency ) {
     switch( $currency ) {
          case 'SEK': $currency_symbol = 'SEK'; break;
     }
     return $currency_symbol;
}
/**
add_filter('add_to_cart_redirect', 'redirect_to_checkout');
 
function redirect_to_checkout() {
    global $woocommerce;
    $checkout_url = $woocommerce->cart->get_checkout_url();
    $curlang = get_locale();
    $result = substr($curlang, 0, 2);
    return $checkout_url."/?lang=".$result;
}
*/
/**
 * Note: Do not add any custom code here. Please use a child theme so that your customizations aren't lost during updates.
 * http://codex.wordpress.org/Child_Themes
 */