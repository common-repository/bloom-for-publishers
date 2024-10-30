<?php //Post: post.php

add_action('wp_head', 'blm_post_head');

if ( 'true' === get_option('blm_setting_amp') ) {
    add_action('amp_post_template_data', 'blm_post_js_amp');
    add_action('amp_post_template_css', 'blm_post_css_amp');
} else {
    add_action('wp_enqueue_scripts', 'blm_post_scripts');
}

add_shortcode('bloom', 'blm_post_map_shortcode');
add_filter('the_content', 'blm_post_map_append');
add_filter('the_content', 'blm_post_feed_append');

$blm_post_map_height_default = 300;
$blm_post_map_zoom_options = array( 'close', 'far' );

/*
* blm_post_head
* Add post's metadata to head section
*/
function blm_post_head()
{
    // Retrieves the stored value from the database
    $blm_post_location_formatted = get_post_meta(get_the_ID(), 'blm_post_location_formatted', true);
    $blm_post_location_longitude = get_post_meta(get_the_ID(), 'blm_post_location_longitude', true);
    $blm_post_location_latitude  = get_post_meta(get_the_ID(), 'blm_post_location_latitude', true);
    $blm_post_key                = get_post_meta(get_the_ID(), 'blm_post_key', true);

    // Only show tags if inside post
    if (is_single() && $blm_post_location_formatted && $blm_post_location_latitude && $blm_post_location_longitude) {
        // Checks and displays the retrieved value
        echo '<meta property="geo:formatted_address" content="' . esc_attr(htmlentities($blm_post_location_formatted, ENT_QUOTES)) . '" />' . "\n";
        echo '<meta property="geo:latitude" content="' . esc_attr($blm_post_location_latitude) . '" />' . "\n";
        echo '<meta property="geo:longitude" content="' . esc_attr($blm_post_location_longitude) . '" />' . "\n";

        // If was posted to Bloom, display its key
        if ($blm_post_key) {
            echo '<meta property="bloom:key" content="' . esc_attr($blm_post_key) . '" />' . "\n";
        }

        // If AMP-compatibility requested
        if ('true' === get_option('blm_setting_amp')) {
            echo '<meta property="bloom:amp" content="true" />' . "\n";
        }
    }
}// blm_post_head

/*
*blm_post_map_shortcode
*Get a map shortcode and translate it to display a map
*/
function blm_post_map_shortcode($attr)
{

    // Check requirements
    if (empty($attr) ||
    ! isset($attr['type']) ||
    ! get_post_meta(get_the_ID(), 'blm_post_location_longitude', true) ||
    ! get_post_meta(get_the_ID(), 'blm_post_location_latitude', true) ) {
        return '';
    }

    // Initialize shortcode settings

    // Determine height
    global $blm_post_map_height_default;
    $attr_height = $blm_post_map_height_default;
    if (isset($attr['height']) && is_numeric($attr['height'])) {
        $attr_height = $attr['height'];
    }

    // Determine URL parameters
    $url_params = array();

    // URL parameter: Post
    $post_key = get_post_meta(get_the_ID(), 'blm_post_key', true);
    if ($post_key) {
        $url_params[] = 'post_key=' . rawurlencode($post_key);
    } else {
        $url_params[] = 'url=' . rawurlencode(get_permalink());
    }

    // Handle shortcode type
    switch ($attr['type']) {
        case 'map':
            // URL parameter: Zoom
            global $blm_post_map_zoom_options;
            if (isset($attr['zoom']) && in_array($attr['zoom'], $blm_post_map_zoom_options, true)) {
                $url_params[] = 'zoom=' . $attr['zoom'];
            }

            // Define the URL
            $url = 'article/map?' . implode('&', $url_params);

            break;

        case 'interactive':
            // Define the URL
            $url = 'plugin/interactive-map?' . implode('&', $url_params);

            break;
    }

    return '<iframe src="' . esc_url('https://embed.bloom.li/' . $url) . '" title="Story map" style="display:block;border:none;visibility:visible;width:100% !important;height:' . esc_attr($attr_height).'px;" allow="geolocation"></iframe>';
}// blm_post_map_shortcode

/*
*blm_post_map_append
*Append the article's map image to the end of its content
*/
function blm_post_map_append($blm_content)
{

    // Check page requirements
    if ('true' !== get_option('blm_setting_map_append_enabled') ||
    has_shortcode($blm_content, 'bloom') ||
    ! is_single() ||
    ! in_the_loop() ||
    ! is_main_query() ||
    ! get_post_meta(get_the_ID(), 'blm_post_location_longitude', true) ||
    ! get_post_meta(get_the_ID(), 'blm_post_location_latitude', true) ) {
        return $blm_content;
    }

    // Check for Reblex Widget (Reusable Block) class
    $blm_backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
    foreach($blm_backtrace as $bt) {
        if(isset($bt['class']) && $bt['class'] === 'Reblex_Widget') {
            return $blm_content;
        }
    }

    // Get append settings
    $blm_append_settings = array(
        'zoom'     => get_option('blm_setting_map_append_zoom'),
        'height'     => get_option('blm_setting_map_append_height'),
        'position' => get_option('blm_setting_map_append_position'),
        'margin'   => ''
    );

    // Determine height
    global $blm_post_map_height_default;
    $blm_append_settings['height'] = ( is_numeric($blm_append_settings['height']) ? $blm_append_settings['height'] : $blm_post_map_height_default );

    // Determine position
    if ('top' === $blm_append_settings['position']) {
        $blm_append_settings['margin'] = 'margin-bottom: 20px;';
    }

    // Determine URL parameters
    $url_params = array();

    // URL parameter: Zoom
    global $blm_post_map_zoom_options;
    if ($blm_append_settings['zoom'] && in_array($blm_append_settings['zoom'], $blm_post_map_zoom_options, true)) {
        $url_params[] = 'zoom=' . $blm_append_settings['zoom'];
    }

    // URL parameter: Post
    $post_key = get_post_meta(get_the_ID(), 'blm_post_key', true);
    if ($post_key) {
        $url_params[] = 'post_key=' . rawurlencode($post_key);
    } else {
        $url_params[] = 'url=' . rawurlencode(get_permalink());
    }

    // Format HTML
    $blm_append_map = '<iframe src="' . esc_url('https://embed.bloom.li/article/map?' . implode('&', $url_params)) . '" title="Story map" style="display:block;border:none;visibility:visible;width:100% !important;height:' . esc_attr($blm_append_settings['height']) . 'px;' . esc_attr($blm_append_settings['margin']) . '"></iframe>';

    // Add to correct position
    switch ($blm_append_settings['position']) {
        case 'top':
            return $blm_append_map . $blm_content;
            break;

        case 'bottom':
        default:
            return $blm_content . $blm_append_map;
            break;
    }
}// blm_post_map_append

/*
*blm_post_feed_append
*Append a news feed of content nearby the article's location content
*/
function blm_post_feed_append($blm_content)
{

    // Check page requirements
    if ('true' !== get_option('blm_setting_feed_append_enabled') ||
    ! get_option('blm_setting_feed_id') ||
    ! is_single() ||
    ! in_the_loop() ||
    ! is_main_query() ||
    ! get_post_meta(get_the_ID(), 'blm_post_location_longitude', true) ||
    ! get_post_meta(get_the_ID(), 'blm_post_location_latitude', true) ) {
        return $blm_content;
    }

    $blm_append_feed = '<iframe src="' . esc_url('https://embed.bloom.li/plugin/' . get_option('blm_setting_feed_id') . '?url=' . get_permalink(get_the_ID())) . '" title="Nearby Feed" style="border:none;visibility:visible;width:100% !important;height:350px"></iframe>';

    return $blm_content . $blm_append_feed;
}// blm_post_feed_append

/*
* blm_post_scripts
* Add CSS and JS for the post
*/
function blm_post_scripts()
{
    // Set scripts
    global $blm_version;
    wp_enqueue_style('blm_post_css', plugins_url('css/post.css', __FILE__), array(), $blm_version, 'all');
    wp_enqueue_script('blm_lib_js', plugins_url('lib/js/global.js', __DIR__), array(), $blm_version);
    wp_enqueue_script('blm_post_js', plugins_url('js/post.js', __FILE__), array( 'jquery' ), $blm_version, true);
}// blm_post_scripts

/*
* blm_post_js_amp
* Add JS for the post for AMP websites
*/
function blm_post_js_amp( $data )
{
    // Determine whether to include scripts
    if ( 'true' !== get_option('blm_setting_search_enabled') ) {
        return true;
    }

    // Set scripts
    $data['amp_component_scripts']['blm-global'] = plugins_url('lib/js/global.js', __DIR__);
    $data['amp_component_scripts']['blm-post'] = plugins_url('js/post.js', __FILE__);

    return $data;
}// blm_post_js_amp

/*
* blm_post_css_amp
* Add CSS for the post for AMP websites
*/
function blm_post_css_amp( $amp_template )
{
    //Set scripts
    global $blm_version;
    wp_enqueue_style('blm_post_css', plugins_url('css/post.css', __FILE__), array(), $blm_version, 'all');
    wp_print_styles('blm_post_css');
}// blm_post_css_amp
