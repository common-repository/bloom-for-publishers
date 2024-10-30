<?php //Search: search.php

global $blm_settings, $blm_version;
$blm_settings = array();
$blm_version = blm_lib_get_version();

add_action('init', 'blm_redirect');
add_action('wp_footer', 'blm_search_footer', 100);
if ( 'true' === get_option('blm_setting_amp') ) {
    add_action('amp_post_template_data', 'blm_search_js_amp');
    add_action('amp_post_template_css', 'blm_search_css_amp');
} else {
    add_action('wp_enqueue_scripts', 'blm_search_scripts');
}

/*
* blm_redirect
* Apply redirect if settings are applicable and current URL is shortlink
*/
function blm_redirect()
{

    //Check for applicable redirect settings
    if (isset($_SERVER) &&
    isset($_SERVER['REQUEST_URI']) &&
    ( '/nearby' === sanitize_text_field($_SERVER['REQUEST_URI']) || '/nearby/' === sanitize_text_field($_SERVER['REQUEST_URI']) ) &&
    'true' === get_option('blm_setting_search_shortlink') ) {
        //Handle nearby redirect
        if (wp_redirect(esc_url(get_site_url()) . '?bloom_search=nearby', '301')) {
            exit;
        }
    }
}//blm_redirect

/*
* blm_search_footer
* Add search to the footer of the website
*/
function blm_search_footer()
{
    // Check requirements
    if (! blm_search_auth()) {
        return true;
    }

    // Get plugin settings
    $blm_settings = blm_plugin_settings_read();

    if (! $blm_settings) {
        return false;
    }

    // Set bloom tag attributes
    $bloom_tag_attributes = array(
        'plugin' => $blm_settings['key'],
        'google-key' => get_option('blm_setting_google_api_key'),
        'color' => $blm_settings['color'],
        'color-light' => (blm_lib_convert_hex_hsl($blm_settings['color'])[2] > 0.6 ? 'true' : 'false'),
        'scroll-bottom' => ( get_option('blm_setting_search_open_bottom') === 'false' ? 'false' : 'true'),
        'icon-display' => ( get_option('blm_setting_search_icon_display') === 'false' ? 'false' : 'true'),
    );

    // Get setting: auto-open seconds
    $auto_seconds_setting = get_option('blm_setting_search_open_seconds');
    if ($auto_seconds_setting !== false && is_numeric($auto_seconds_setting)) {
        $bloom_tag_attributes['auto-seconds'] = $auto_seconds_setting;
    }

    // Get setting: icon display seconds
    $icon_display_seconds_attr = 1; //Define default delay
    $icon_display_seconds_setting = get_option('blm_setting_search_icon_delay_seconds');
    if ($icon_display_seconds_setting !== false && is_numeric($icon_display_seconds_setting)) {
        $icon_display_seconds_attr = (int)$icon_display_seconds_setting;
    }
    $bloom_tag_attributes['icon-display-seconds'] = $icon_display_seconds_attr;

    // Get setting: cookies enabled
    $cookies_enabled = false;
    if (get_option('blm_setting_search_cookies_enabled') === 'true') {
        $cookies_enabled = true;
    }
    $bloom_tag_attributes['cookies-enabled'] = (int)$cookies_enabled;

    //Determine bloom tag
    $bloom_tag = 'bloom';
    if ( 'true' === get_option('blm_setting_amp') ) {
       $bloom_tag = 'div';
       $bloom_tag_attributes['id'] = 'blm-s-tag';
    }

    // Add bloom tag
    echo '<' . esc_attr($bloom_tag);
    array_walk($bloom_tag_attributes, 'blm_search_attributes');
    echo '></' . esc_attr($bloom_tag) . '>';
}// blm_search_footer

/*
* blm_search_attributes
* Display given HTML attributes
*/
function blm_search_attributes($v, $k)
{
    if ( 'id' === $k ) {
        echo ' ' . esc_attr($k) . '="' . esc_attr($v) . '"';
    } else {
        echo ' data-' . esc_attr($k) . '="' . esc_attr($v) . '"';
    }
}//blm_search_attributes

/*
* blm_plugin_settings_update
* Save plugin settings
*/
function blm_plugin_settings_update()
{
    // Get application style
    global $blm_settings;
    $api_response = blm_lib_api_process(
        'info',
        array(
            'app_key'       => get_option('blm_setting_bloom_api_key'),
            'publisher_key' => get_option('blm_setting_bloom_publisher_key'),
            'app_action'    => 'get_search_plugin',
        )
    );

    // Handle error
    if (! $api_response || ! $api_response->success) {
        return false;
    }

    // Further decode response
    $blm_settings = $api_response->message;
    $blm_settings = (array) $blm_settings;
    $blm_settings = wp_json_encode($blm_settings);

    // Handle error
    if (! $blm_settings) {
        return false;
    }

    // Save settings
    update_option('blm_setting_search_settings', $blm_settings);

    return $blm_settings;
}// blm_plugin_settings_update

/*
* blm_plugin_settings_read
* Get the plugin settings
*/
function blm_plugin_settings_read()
{
    // Get settings
    $blm_settings = get_option('blm_setting_search_settings');

    // Handle if settings don't exist
    if (! $blm_settings) {
        // Get settings from Bloom
        $blm_settings_new = blm_plugin_settings_update();

        // Handle valid settings
        if ($blm_settings_new) {
            $blm_settings = $blm_settings_new;
        }
    }

    // Decode settings
    $blm_settings = json_decode($blm_settings, true);

    return $blm_settings;
}// blm_plugin_settings_read

/*
* blm_search_scripts
* Add CSS and JS for the search feature
*/
function blm_search_scripts()
{
    // Check requirements
    if (! blm_search_auth()) {
        return true;
    }

    // Set Google script if key provided
    $blm_setting_google_api_key = esc_attr(get_option('blm_setting_google_api_key'));
    if ($blm_setting_google_api_key) {
        wp_enqueue_script('blm_search_js_geo', esc_url('https://maps.googleapis.com/maps/api/js?key=' . $blm_setting_google_api_key . '&language=en'));
    }

    // Set other scripts
    global $blm_version;
    wp_enqueue_script('blm_search_js', plugins_url('js/search.js', __FILE__), array( 'jquery' ), $blm_version, true);
    wp_enqueue_style('blm_search_css', plugins_url('css/search.css', __FILE__), array(), $blm_version, 'all');
}// blm_search_scripts

/*
* blm_search_js_amp
* Add JS for the search feature for AMP websites
*/
function blm_search_js_amp( $data )
{
    // Check requirements
    if (! blm_search_auth()) {
        return true;
    }

    // Set scripts
    // Set Google script if key provided
    $blm_setting_google_api_key = esc_attr(get_option('blm_setting_google_api_key'));
    if ($blm_setting_google_api_key) {
        $data['amp_component_scripts']['blm-search-js-geo'] = esc_url('https://maps.googleapis.com/maps/api/js?key=' . $blm_setting_google_api_key . '&language=en');
    }
    $data['amp_component_scripts']['blm-search'] = plugins_url('js/search.js', __FILE__);

    return $data;
}// blm_search_js_amp

/*
* blm_search_css_amp
* Add CSS for the search feature for AMP websites
*/
function blm_search_css_amp( $amp_template )
{
    global $blm_version;
    wp_enqueue_style('blm_search_css', plugins_url('css/search.css', __FILE__), array(), $blm_version, 'all');
    wp_print_styles('blm_search_css');
}// blm_search_css_amp

/*
*blm_search_auth
*Authenticate the display of the plugin
*/
function blm_search_auth()
{
    //Ignore if key is not provided
    if (! get_option('blm_setting_bloom_api_key')) {
        return false;
    }

    //If search is enabled
    if ('true' === get_option('blm_setting_search_enabled')) {
        return true;
    }

    //If preview is enabled and user is logged in
    if ('true' === get_option('blm_setting_search_preview') && is_user_logged_in()) {
        //Get user details
        $user = wp_get_current_user();

        //Compare against allowed user roles
        $role_diff = array_udiff(
            $user->roles,
            array( 'administrator', 'editor', 'contributor', 'author' ),
            'strcasecmp'
        );

        //Handle if user is in allowed role
        if (empty($role_diff)) {
            return true;
        }
    }

    return false;
}//blm_search_auth
