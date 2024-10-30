<?php //Admin Post: admin-post.php

add_action('admin_notices', 'blm_admin_post_notice');
add_action('add_meta_boxes', 'blm_admin_post_location_display');
add_action('future_to_publish', 'blm_admin_post_status', 5, 1);
add_action('post_updated', 'blm_admin_post_update', 6, 3);

/*
* blm_admin_post_scripts
* Add CSS and JS to the admin post page
*/
function blm_admin_post_scripts($hook)
{

    // Check valid page type
    if (! blm_admin_post_validtype($hook)) {
        return true;
    }

    // Get current Google API key
    $blm_setting_google_api_key = esc_attr(get_option('blm_setting_google_api_key'));

    // Load scripts if key is available
    if ($blm_setting_google_api_key) {
        wp_enqueue_script('blm_js_google', 'https://maps.googleapis.com/maps/api/js?language=en&key=' . $blm_setting_google_api_key);
        wp_enqueue_script('blm_js_geocode', plugins_url('js/geocode.js', __FILE__), null, blm_lib_get_version());
        wp_enqueue_script('blm_js_adminpost', plugins_url('js/admin-post.js', __FILE__), array( 'jquery', 'jquery-ui-sortable' ), blm_lib_get_version());
        wp_enqueue_style('blm_css_adminpost', plugins_url('css/admin-post.css', __FILE__), null, blm_lib_get_version());
        wp_enqueue_style('blm_css_adminglobal', plugins_url('css/global.css', __FILE__), null, blm_lib_get_version());
        wp_enqueue_script('jquery-ui-core');
        wp_enqueue_script('jquery-ui-sortable');
    }
}// blm_admin_post_scripts

/*
* blm_admin_post_notice
* Add a message to the header of the admin
* Note: In block editor (Gutenberg), this doesn't show by default
* Note cont. A redundant message is displayed in the Post Location section
*/
function blm_admin_post_notice()
{
    // Initialize
    global $pagenow, $post;

    // Check valid and active post type
    $blm_post_type = get_option('blm_setting_posttype_' . get_post_type($post));
    if (! blm_admin_post_validtype($pagenow) || ! $blm_post_type || 'none' === $blm_post_type) {
        return false;
    }

    // Get API response notice if available
    $api_response = get_post_meta($post->ID, 'blm_savepost_response', true);

    // Handle if available
    if ($api_response) {
        
        //Decode current geotagging response
        $api_response = json_decode($api_response, true);
        
        //Display response if error
        if ($api_response['code'] !== 1) {
            echo '<div class="notice notice-error is-dismissible" data-code="' . esc_attr($api_response['code']) . '"><p>Bloom: ' . esc_html($api_response['message']) . '</p></div>';
        }
    
    } else if('publish' === $post->post_status) {
        
        // Check for published post status
        echo '<div class="notice notice-warning is-dismissible"><p>Geotag Reminder: Add a location below to save on Bloom</p></div>';
    
    }

    return true;
}// blm_admin_post_notice

/*
* blm_admin_post_location_display
* Adds a location input to post editor
*/
function blm_admin_post_location_display()
{
    // Initialize
    global $pagenow, $post;

    // Check valid and active post type
    $blm_post_type = get_option('blm_setting_posttype_' . get_post_type($post));
    if (! blm_admin_post_validtype($pagenow) || ! $blm_post_type || 'none' === $blm_post_type) {
        return false;
    }

    // Check for Google API key
    if (! get_option('blm_setting_google_api_key')) {
        return false;
    }

    // Display location form
    add_action('admin_enqueue_scripts', 'blm_admin_post_scripts');
    add_meta_box('blm_location_form', __('Geotag Locations', 'location-textdomain'), 'blm_admin_post_location_display_callback', null, 'advanced', 'high');
}// blm_admin_post_location_display

/*
* blm_admin_post_location_display_callback
* Displays the location input in post editor
*/
function blm_admin_post_location_display_callback($post)
{

    // Fetch currently-saved post location data
    $blm_setting_google_api_key   = esc_attr(get_option('blm_setting_google_api_key'));

    // Handle if no Google API key provided
    if (! $blm_setting_google_api_key) {
        echo '<p>Enter your Google API Key on the Bloom Settings page in order to use this geotagging feature.</p>';
        return true;
    }

    // Initialize
    add_thickbox();

    // Nonce field
    wp_nonce_field('blm_post_nonce', 'blm_post_n');

    // Handle API response notice if previously saved
    $api_response = get_post_meta($post->ID, 'blm_savepost_response', true);
    $has_api_response = false;

    if ($api_response) {
        //Decode current geotagging response
        $api_response = json_decode($api_response, true);

        if (isset($api_response['code']) && isset($api_response['message'])) {
            $has_api_response = true;
        }
    }

    // Initialize SVG icons for display
    $svg = array(
        'list' => array(
            'viewBox' => '0 0 512 512',
            'data' => 'M464 480H48c-26.51 0-48-21.49-48-48V80c0-26.51 21.49-48 48-48h416c26.51 0 48 21.49 48 48v352c0 26.51-21.49 48-48 48zM128 120c-22.091 0-40 17.909-40 40s17.909 40 40 40 40-17.909 40-40-17.909-40-40-40zm0 96c-22.091 0-40 17.909-40 40s17.909 40 40 40 40-17.909 40-40-17.909-40-40-40zm0 96c-22.091 0-40 17.909-40 40s17.909 40 40 40 40-17.909 40-40-17.909-40-40-40zm288-136v-32c0-6.627-5.373-12-12-12H204c-6.627 0-12 5.373-12 12v32c0 6.627 5.373 12 12 12h200c6.627 0 12-5.373 12-12zm0 96v-32c0-6.627-5.373-12-12-12H204c-6.627 0-12 5.373-12 12v32c0 6.627 5.373 12 12 12h200c6.627 0 12-5.373 12-12zm0 96v-32c0-6.627-5.373-12-12-12H204c-6.627 0-12 5.373-12 12v32c0 6.627 5.373 12 12 12h200c6.627 0 12-5.373 12-12z'
        ),
        'star' => array(
            'viewBox' => '0 0 576 512',
            'data' => 'M259.3 17.8L194 150.2 47.9 171.5c-26.2 3.8-36.7 36.1-17.7 54.6l105.7 103-25 145.5c-4.5 26.3 23.2 46 46.4 33.7L288 439.6l130.7 68.7c23.2 12.2 50.9-7.4 46.4-33.7l-25-145.5 105.7-103c19-18.5 8.5-50.8-17.7-54.6L382 150.2 316.7 17.8c-11.7-23.6-45.6-23.9-57.4 0z'
        ),
        'error' => array(
            'viewBox' => '0 0 576 512',
            'data' => 'M569.517 440.013C587.975 472.007 564.806 512 527.94 512H48.054c-36.937 0-59.999-40.055-41.577-71.987L246.423 23.985c18.467-32.009 64.72-31.951 83.154 0l239.94 416.028zM288 354c-25.405 0-46 20.595-46 46s20.595 46 46 46 46-20.595 46-46-20.595-46-46-46zm-43.673-165.346l7.418 136c.347 6.364 5.609 11.346 11.982 11.346h48.546c6.373 0 11.635-4.982 11.982-11.346l7.418-136c.375-6.874-5.098-12.654-11.982-12.654h-63.383c-6.884 0-12.356 5.78-11.981 12.654z'
        ),
        'pen' => array(
            'viewBox' => '0 0 512 512',
            'data' => 'M290.74 93.24l128.02 128.02-277.99 277.99-114.14 12.6C11.35 513.54-1.56 500.62.14 485.34l12.7-114.22 277.9-277.88zm207.2-19.06l-60.11-60.11c-18.75-18.75-49.16-18.75-67.91 0l-56.55 56.55 128.02 128.02 56.55-56.55c18.75-18.76 18.75-49.16 0-67.91z'
        ),
        'times' => array(
            'viewBox' => '0 0 352 512',
            'data' => 'M242.72 256l100.07-100.07c12.28-12.28 12.28-32.19 0-44.48l-22.24-22.24c-12.28-12.28-32.19-12.28-44.48 0L176 189.28 75.93 89.21c-12.28-12.28-32.19-12.28-44.48 0L9.21 111.45c-12.28 12.28-12.28 32.19 0 44.48L109.28 256 9.21 356.07c-12.28 12.28-12.28 32.19 0 44.48l22.24 22.24c12.28 12.28 32.2 12.28 44.48 0L176 322.72l100.07 100.07c12.28 12.28 32.2 12.28 44.48 0l22.24-22.24c12.28-12.28 12.28-32.19 0-44.48L242.72 256z'
        ),
        'check' => array(
            'viewBox' => '0 0 512 512',
            'data' => 'M173.898 439.404l-166.4-166.4c-9.997-9.997-9.997-26.206 0-36.204l36.203-36.204c9.997-9.998 26.207-9.998 36.204 0L192 312.69 432.095 72.596c9.997-9.997 26.207-9.997 36.204 0l36.203 36.204c9.997 9.997 9.997 26.206 0 36.204l-294.4 294.401c-9.998 9.997-26.207 9.997-36.204-.001z'
        ),
        'search' => array(
            'viewBox' => '0 0 512 512',
            'data' => 'M505 442.7L405.3 343c-4.5-4.5-10.6-7-17-7H372c27.6-35.3 44-79.7 44-128C416 93.1 322.9 0 208 0S0 93.1 0 208s93.1 208 208 208c48.3 0 92.7-16.4 128-44v16.3c0 6.4 2.5 12.5 7 17l99.7 99.7c9.4 9.4 24.6 9.4 33.9 0l28.3-28.3c9.4-9.4 9.4-24.6.1-34zM208 336c-70.7 0-128-57.2-128-128 0-70.7 57.2-128 128-128 70.7 0 128 57.2 128 128 0 70.7-57.2 128-128 128z'
        )
    );
    ?>

    <div id="blm-location-header">
        <?php if ($has_api_response) { ?>
            <div id="blm-location-message" data-code="<?php echo esc_attr($api_response['code']); ?>"><strong>Last Update Status:</strong> <?php echo esc_html($api_response['message']); ?></div>
        <?php } ?>
        <p>Add locations that are discussed in this story to make it accessible to nearby readers. <a href="#TB_inline?width=600&inlineId=blm-tb-geotagging" title="Learn more about Geotagging" class="thickbox">Learn more</a>
    </div>

    <div id="blm-location-body"></div>

    <div id="blm-location-footer">
        <a href="javascript:;" title="Add another location" id="blm-location-add" class="blm-button">
            <span data-has-locations="true">Add Another</span>
            <span data-has-locations="false">Add Location</span>
        </a>
    </div>

    <div id="blm-location-templates">

        <div data-template="item_container">

            <div class="blm-location-item">

                <div class="blm-location-item-header">

                    <div class="blm-location-item-index"><span></span></div>

                    <div class="blm-location-item-title">
                        <div class="blm-location-item-title-location">
                            <h4>Location</h4>
                            <div class="blm-location-item-title-location-main"></div>
                            <div class="blm-location-item-title-location-sub"></div>
                        </div>
                        <div class="blm-location-item-title-label">
                            <h4>Label</h4>
                            <div class="blm-location-item-title-label-content"></div>
                        </div>
                    </div>

                    <div class="blm-location-item-options">
                        <span title="Primary Location" data-option="primary" class="blm-button blm-button-transparent">
                            <svg aria-hidden="true" focusable="false" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="<?php echo esc_attr($svg['star']['viewBox']); ?>"><path fill="currentColor" d="<?php echo esc_attr($svg['star']['data']); ?>"></path></svg>
                            <span>PRIMARY</span>
                        </span>
                        <span title="Location Error" data-option="error" class="blm-button blm-button-transparent">
                            <svg aria-hidden="true" focusable="false" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="<?php echo esc_attr($svg['error']['viewBox']); ?>"><path fill="currentColor" d="<?php echo esc_attr($svg['error']['data']); ?>"></path></svg>
                        </span>
                        <a href="#TB_inline?width=600&inlineId=blm-tb-location-components" title="View Location Components" data-option="components" class="blm-button thickbox">
                            <svg aria-hidden="true" focusable="false" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="<?php echo esc_attr($svg['list']['viewBox']); ?>"><path fill="currentColor" d="<?php echo esc_attr($svg['list']['data']); ?>"></path></svg>
                        </a>
                        <a href="javascript:;" title="Edit Location" data-option="edit" class="blm-button">
                            <svg aria-hidden="true" focusable="false" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="<?php echo esc_attr($svg['pen']['viewBox']); ?>"><path fill="currentColor" d="<?php echo esc_attr($svg['pen']['data']); ?>"></path></svg>
                        </a>
                        <a href="javascript:;" title="Save Updates" data-option="save" class="blm-button">
                            <svg aria-hidden="true" focusable="false" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="<?php echo esc_attr($svg['check']['viewBox']); ?>"><path fill="currentColor" d="<?php echo esc_attr($svg['check']['data']); ?>"></path></svg>
                            <span>Done</span>
                        </a>
                        <a href="javascript:;" title="Cancel New Location" data-option="cancel" class="blm-button">
                            <svg aria-hidden="true" focusable="false" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="<?php echo esc_attr($svg['times']['viewBox']); ?>"><path fill="currentColor" d="<?php echo esc_attr($svg['times']['data']); ?>"></path></svg>
                            <span>Cancel</span>
                        </a>
                        <a href="javascript:;" title="Delete Location" data-option="delete" class="blm-button">
                            <svg aria-hidden="true" focusable="false" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="<?php echo esc_attr($svg['times']['viewBox']); ?>"><path fill="currentColor" d="<?php echo esc_attr($svg['times']['data']); ?>"></path></svg>
                        </a>
                    </div>

                </div>

                <input type="hidden" class="blm-location-item-data" />

            </div>

        </div>

        <div data-template="item_body">

            <div class="blm-location-item-body">

                <div class="blm-location-item-error"></div>

                <div class="blm-location-item-body-fieldset" data-type="search">
                    <h4>Type an address or place</h4>

                    <div class="blm-location-search-tip">
                        <a href="#TB_inline?width=600&inlineId=blm-tb-location-example" title="Location Example" class="thickbox">Must be specific; <em>See example</em></a>
                    </div>

                    <div class="blm-location-search-form">
                        <input type="text" id="blm-location-search-input" />
                        <button type="button" id="blm-location-search-button" class="blm-button" onClick="blmGeocode( 'blm-location-search-input', 'blm-location-search-results' );">
                            <svg aria-hidden="true" focusable="false" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="<?php echo esc_attr($svg['search']['viewBox']); ?>"><path fill="currentColor" d="<?php echo esc_attr($svg['search']['data']); ?>"></path></svg>
                            <span>Search</span>
                        </button>
                    </div>

                    <div id="blm-location-search-results">
                        <h4>Select to confirm</h4>
                        <div id="blm-location-search-results-list"></div>
                    </div>

                </div>

                <div class="blm-location-item-body-fieldset" data-type="label">
                    <h4>Location Label</h4>
                    <div class="blm-location-search-tip">Optional; Visible on this story's interactive map; <span class="blm-location-label-limit">Limit <span data-max="">0</span></span></div>
                    <div class="blm-label-form">
                        <input type="text" id="blm-label-input" />
                    </div>
                </div>

                <div class="blm-location-item-body-fieldset" data-type="text">
                    <h4>About this story location</h4>
                    <div class="blm-location-search-tip">Optional; Visible on this story's interactive map; <span class="blm-location-text-limit">Limit <span data-max="">0</span></span></div>
                    <div class="blm-label-form">
                        <textarea id="blm-text-input"></textarea>
                    </div>
                </div>

            </div>

        </div>

        <div data-template="item_components" id="blm-tb-location-components">
            <div class="blm-location-components-container">
                <p>Here is the location split into its individual address components.</p>
                <div class="blm-location-components-list">
                    <div class="blm-location-components-item" id="blm-location-components-item-place">
                        <span class="blm-location-components-item-label"></span>
                        <span class="blm-location-components-item-value"></span>
                    </div>
                    <div class="blm-location-components-item" id="blm-location-components-item-address">
                        <span class="blm-location-components-item-label"></span>
                        <span class="blm-location-components-item-value"></span>
                    </div>
                    <div class="blm-location-components-item" id="blm-location-components-item-premise">
                        <span class="blm-location-components-item-label"></span>
                        <span class="blm-location-components-item-value"></span>
                    </div>
                    <div class="blm-location-components-item" id="blm-location-components-item-neighborhood">
                        <span class="blm-location-components-item-label"></span>
                        <span class="blm-location-components-item-value"></span>
                    </div>
                    <div class="blm-location-components-item" id="blm-location-components-item-postal_code">
                        <span class="blm-location-components-item-label"></span>
                        <span class="blm-location-components-item-value"></span>
                    </div>
                    <div class="blm-location-components-item" id="blm-location-components-item-city">
                        <span class="blm-location-components-item-label"></span>
                        <span class="blm-location-components-item-value"></span>
                    </div>
                    <div class="blm-location-components-item" id="blm-location-components-item-county">
                        <span class="blm-location-components-item-label"></span>
                        <span class="blm-location-components-item-value"></span>
                    </div>
                    <div class="blm-location-components-item" id="blm-location-components-item-state">
                        <span class="blm-location-components-item-label"></span>
                        <span class="blm-location-components-item-value"></span>
                    </div>
                    <div class="blm-location-components-item" id="blm-location-components-item-country">
                        <span class="blm-location-components-item-label"></span>
                        <span class="blm-location-components-item-value"></span>
                    </div>
                </div>
            </div>
        </div>

        <div data-template="item_examples" id="blm-tb-location-example">
            <div class="blm-location-components-container">
                <p>Bloom's geotagging functionality makes it easy to capture multiple levels of geographic locations to allow for in-depth analysis and reader experiences with your stories. Since our tool was built for local communities, we limit the locations that you can select to as specific as a street address (i.e., not as specific as an apartment number or floor) and as broad as a county or district (i.e., not wider regions such as a state). Only accepted locations will appear in search results. If the results do not have the location you're looking for, please adjust your keywords or verify the location first on Google Maps.</p>
		<p>To be consistent across all story locations, Bloom organizes and verifies locations with Google's <a href="https://developers.google.com/maps/documentation/geocoding/intro#Types" title="Google Location Address Types" target="_blank">Address Types</a>. You're welcome to read more about these to learn about each type.</p>
                <p>Below is a list of acceptable location components and an example:<br /><br /></p>
                <div class="blm-location-components-list">
                    <div class="blm-location-components-item">
                        <span>Address</span>
                        <span>1600 Pennsylvania Avenue</span>
                    </div>
                    <div class="blm-location-components-item">
                        <span>Route</span>
                        <span>Pennsylvania Avenue</span>
                    </div>
                    <div class="blm-location-components-item">
                        <span>Intersection</span>
                        <span>Pennsylvania Avenue & 15th Street</span>
                    </div>
                    <div class="blm-location-components-item">
                        <span>Place</span>
                        <span>White House</span>
                    </div>
                    <div class="blm-location-components-item">
                        <span>Landmark or Point of Interest</span>
                        <span>Washington Monument</span>
                    </div>
                    <div class="blm-location-components-item">
                        <span>Natural Feature or Park</span>
                        <span>Theodore Roosevelt Island</span>
                    </div>
                    <div class="blm-location-components-item">
                        <span>Neighborhood</span>
                        <span>Northwest Washington</span>
                    </div>
                    <div class="blm-location-components-item">
                        <span>Postal Code</span>
                        <span>20500</span>
                    </div>
                    <div class="blm-location-components-item">
                        <span>City or Town</span>
                        <span>Washington</span>
                    </div>
                    <div class="blm-location-components-item">
                        <span>County or District</span>
                        <span>Arlington County</span>
                    </div>
                </div>
            </div>
        </div>

        <div data-template="item_shortcodes" id="blm-tb-geotagging">
            <?php include plugin_dir_path(__FILE__) . 'info-geotagging.php'; ?>
            <?php include plugin_dir_path(__FILE__) . 'info-shortcodes.php'; ?>
        </div>
    </div>

    <?php
    // Get location data
    $location_data = blm_admin_post_location_get($post->ID);

    // Handle no location data
    if (! $location_data) {
        // Initialize data structure
        $location_data = array();

        // Handle previous non-array data structure
        if (get_post_meta($post->ID, 'blm_post_location_formatted', true)) {
            $location_data[] = array(
                'rank' => 1,
                'label' => '',
                'text' => '',
                'location' => array(
                    'address_components' => array(),
                    'formatted_address' => get_post_meta($post->ID, 'blm_post_location_formatted', true),
                    'geometry' => array(
                        'location' => array(
                            'lat' => get_post_meta($post->ID, 'blm_post_location_latitude', true),
                            'lng' => get_post_meta($post->ID, 'blm_post_location_longitude', true)
                        )
                    )
                )
            );

            //Restructure old address components for new data structure
            $location_data_components = json_decode(get_post_meta($post->ID, 'blm_post_location_components', true));

            foreach ($location_data_components as $ldc_type => $ldc_value) {
                $location_data[0]['location']['address_components'][] = array(
                    'long_name' => $ldc_value,
                    'short_name' => $ldc_value,
                    'types' => array($ldc_type)
                );
            }
        }
    }

    //Decode data for input field
    $location_data = wp_json_encode($location_data);
    $location_data = base64_encode($location_data);
    ?>

    <input type="hidden" name="blm_location_data" id="blm-location-data" value="<?php echo esc_attr($location_data); ?>" />
    <input type="hidden" name="blm_key" id="blm-key" value="<?php echo esc_attr(get_post_meta($post->ID, 'blm_post_key', true)); ?>" />
    <input type="hidden" name="blm_ua" id="blm-ua" />

    <?php
}// blm_admin_post_location_display_callback

/*
* blm_admin_post_status
* Handle post status change
*/
function blm_admin_post_status($post)
{
    //Save to Bloom
    return blm_admin_post_bloom($post, 'status');
}// blm_admin_post_status

/*
* blm_admin_post_update
* Handle existing post updates
*/
function blm_admin_post_update($post_ID, $post_after, $post_before)
{
    // Ignore if changing from future to publish; handled in blm_admin_post_status()
    if ( $post_before->post_status === 'future' && $post_after->post_status === 'publish' ) {
        return true;
    }

    // Save to Bloom
    return blm_admin_post_bloom($post_after, 'update');
}// blm_admin_post_update

/*
* blm_admin_post_bloom
* Save the post on Bloom
*/
function blm_admin_post_bloom($post, $source)
{
    // Check admin update requirements
    if ($source === 'update') {

        // Validate nonce
        if (! isset($_POST['blm_post_n']) || ! wp_verify_nonce(sanitize_key($_POST['blm_post_n']), 'blm_post_nonce')) {
            return false;
        }

        // Validate page type
        global $pagenow;
        if (! blm_admin_post_validtype($pagenow)) {
            return true;
        }

    }

    // Validate post type
    $post_type = get_post_type($post);
    $blm_post_type = get_option('blm_setting_posttype_' . $post_type);
    if (! $blm_post_type || 'none' === $blm_post_type) {
        return false;
    }

    /*
    * Gather location input
    */

    // Gather current saved location data
    $had_location = false;
    $has_location = false;
    $saved_locations = blm_admin_post_location_get($post->ID);
    if ($saved_locations) {
        $had_location = true;
    }

    // Gather new location data if updating
    if ( $source === 'update' ) {

        $input = array(
           'blm_location_data',
           'blm_ua'
        );

        // Sanitize input
        foreach ($input as $k => $v) {
            // Check requirements
            if (! isset($_POST[ $v ]) ) {
                return false;
            }

            $input[ $v ] = sanitize_text_field($_POST[ $v ]);
            unset($input[ $k ]);

            if (! $input[ $v ] ) {
                return false;
            }
        }

        // Update location data; Before any potential errors occur
        update_post_meta($post->ID, 'blm_post_location_data', $input['blm_location_data']);
        $has_location = true;

        // Decode location input
        $input['blm_location_data'] = base64_decode($input['blm_location_data']);
        $input['blm_location_data'] = json_decode($input['blm_location_data'], true);
        $saved_locations = $input['blm_location_data'];

        // Validate primary location requirements
        if ( isset($input['blm_location_data'][0]) && (! $input['blm_location_data'][0]['location']['formatted_address'] ||
        ! $input['blm_location_data'][0]['location']['geometry']['location']['lat'] ||
        ! $input['blm_location_data'][0]['location']['geometry']['location']['lng'])) {
            // Update response
            update_post_meta(
                $post->ID,
                'blm_savepost_response',
                wp_json_encode(
                    array (
                        'code'    => 0,
                        'message' => 'The primary location is not valid'
                    )
                )
            );

            return true;
        }

        // Update primary location data
        update_post_meta($post->ID, 'blm_post_location_formatted', $input['blm_location_data'][0]['location']['formatted_address']);
        update_post_meta($post->ID, 'blm_post_location_components', $input['blm_location_data'][0]['location']['address_components']);
        update_post_meta($post->ID, 'blm_post_location_latitude', $input['blm_location_data'][0]['location']['geometry']['location']['lat']);
        update_post_meta($post->ID, 'blm_post_location_longitude', $input['blm_location_data'][0]['location']['geometry']['location']['lng']);
    } else {
        // If not updating (i.e., auto-publishing), verify has location
        $has_location = $had_location;
    }

    // Save to Bloom only if published with a location or if removing location
    if ('publish' !== $post->post_status || ($source === 'update' && wp_is_post_autosave($post->ID)) || (! $has_location && ! $had_location)) {
        return blm_admin_post_reset();
    }

    // Get Bloom keys
    $blm_setting_bloom_api_key = get_option('blm_setting_bloom_api_key');
    $blm_setting_bloom_publisher_key = get_option('blm_setting_bloom_publisher_key');
    $blm_setting_google_api_key = get_option('blm_setting_google_api_key');

    // Check Bloom key requirements
    if (! $blm_setting_bloom_api_key || ! $blm_setting_bloom_publisher_key || ! $blm_setting_google_api_key) {
        // Update response
        update_post_meta(
            $post->ID,
            'blm_savepost_response',
            wp_json_encode(
                array(
                    'code'    => 0,
                    'message' => 'Keys were not provided in Bloom settings',
                )
            )
        );

        return true;
    }

    // Gather post data
    setup_postdata($post);

    // Get excerpt
    $excerpt = get_the_excerpt($post);
    if (!$excerpt) {
        $excerpt = wp_trim_excerpt('', $post);
    }

    // Format publish date
    $blm_publish_date = get_the_date('Y-m-d H:i:sP', $post->ID);

    // Get image URL
    $image_url_size = array(1000, 1000);
    $image_url = get_the_post_thumbnail_url($post, $image_url_size);
    if (!$image_url) {
        //Get all post image attachments
        $images_query = new WP_Query;
        $images = $images_query->query( array(
            'post_parent' => $post->ID,
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'posts_per_page' => 1,
            'orderby' => 'date',
            'order' => 'ASC',
            'suppress_filters' => false,
            'ignore_sticky_posts' => true,
            'no_found_rows' => true
        ) );

        //Get first image attachment if available
        if (!empty($images)) {
            $image_url = wp_get_attachment_image_url(key($images), $image_url_size);
        }
    }

    // Check for custom publish date
    $blm_publish_date_custom = get_option('blm_setting_posttype_publishdate_' . $post_type);

    if ($blm_publish_date_custom) {
        $blm_publish_date_custom_value = get_post_meta($post->ID, $blm_publish_date_custom, true);

        if (! $blm_publish_date_custom_value && isset($_POST[ $blm_publish_date_custom ])) {
            $blm_publish_date_custom_value = sanitize_text_field($_POST[ $blm_publish_date_custom ]);
        }

        if ($blm_publish_date_custom_value) {
            // Format into date
            $blm_publish_date_custom_formatted = strtotime($blm_publish_date_custom_value);

            if ($blm_publish_date_custom_formatted) {
                $blm_publish_date_custom_formatted = date('Y-m-d H:i:sP', $blm_publish_date_custom_formatted);

                if ($blm_publish_date_custom_formatted) {
                    $blm_publish_date = $blm_publish_date_custom_formatted;
                }
            }
        }
    }

    // Validate all location data
    $locations_input = array();
    if ($saved_locations) {
        $locations_input_label_limit = 130;
        $locations_input_text_limit = 500;
        foreach ($saved_locations as $l) {
            $locations_input[] = array(
                'rank' => $l['rank'],
                'label' => substr($l['label'], 0, $locations_input_label_limit),
                'text' => substr($l['text'], 0, $locations_input_text_limit),
                'address' => $l['location']['formatted_address'],
                'latitude' => $l['location']['geometry']['location']['lat'],
                'longitude' => $l['location']['geometry']['location']['lng']
            );
        }
    }

    // Check for custom fields
    $custom_fields = array();

    switch ($blm_post_type) {
        case 'event':
            // Get event end date
            $blm_end_date = get_option('blm_setting_posttype_enddate_' . $post_type);

            if ($blm_end_date) {
                $blm_end_date_value = get_post_meta($post->ID, $blm_end_date, true);

                if ($blm_end_date) {
                    // Format into date
                    $blm_end_date_formatted = strtotime($blm_end_date_value);

                    if ($blm_end_date_formatted) {
                        $blm_end_date_formatted = date('Y-m-d H:i:sP', $blm_end_date_formatted);

                        if ($blm_end_date_formatted) {
                            $custom_fields['end_date'] = $blm_end_date_formatted;
                        }
                    }
                }
            }

            break;
    }

    $custom_fields = wp_json_encode($custom_fields);

    // Format expiration days
    $expiration_days        = 'default';
    $expiration_days_custom = get_option('blm_setting_posttype_expiration_' . $post_type);
    if ($expiration_days_custom) {
        $expiration_days = $expiration_days_custom;
    }

    // Get post key, if updating
    $post_key = get_post_meta($post->ID, 'blm_post_key', true);

    // Process Bloom API call
    $api_response = blm_lib_api_process(
        'post',
        array(
            'plugin_system'      => 'wordpress',
            'plugin_version'     => blm_lib_get_version(),
            'google_key'         => $blm_setting_google_api_key,
            'app_key'            => $blm_setting_bloom_api_key,
            'app_publisher'      => $blm_setting_bloom_publisher_key,
            'app_action'         => ($post_key ? 'post_update' : 'post_add'),
            'key'                => $post_key,
            'date'               => $blm_publish_date,
            'expiration_days'    => $expiration_days,
            'type'               => $blm_post_type,
            'title'              => get_the_title($post),
            'content'            => $excerpt,
            'content_full'       => wp_trim_words($post->post_content, 500),
            'url'                => get_permalink($post),
            'locations'          => $locations_input,
            'image_url'          => $image_url,
            'user_agent'         => ( isset($input['blm_ua']) ? $input['blm_ua'] : null ),
            'custom_fields'      => $custom_fields
        ),
        true
    );

    // Handle API error
    if (! $api_response || ! isset($api_response['code']) || ! isset($api_response['message'])) {
        blm_lib_error('api_jsondecode', $api_response);

        // Update response
        update_post_meta(
            $post->ID,
            'blm_savepost_response',
            wp_json_encode(
                array(
                    'code'    => 0,
                    'message' => 'Plugin system error'
                )
            )
        );

        return true;
    }

    // Handle successful response
    if (1 === (int)$api_response['code']) {
        // Save post key
        update_post_meta($post->ID, 'blm_post_key', $api_response['data']['key']);

        // Handle if post key is available to update despite the error
    } elseif (isset($api_response['data']) && isset($api_response['data']['key']) && $api_response['data']['key']) {
        // Save post key
        update_post_meta($post->ID, 'blm_post_key', $api_response['data']['key']);
    }

    //Handle location errors
    $location_errors = array();
    if (isset($api_response['data']['location_errors']) && is_array($api_response['data']['location_errors']) && !empty($api_response['data']['location_errors'])) {
        foreach ($api_response['data']['location_errors'] as $le) {
            $location_errors[$le['input']['label']] = array(
                'location_text' => $le['input']['text'],
                'message' => $le['message']
            );
        }
    }

    if ($saved_locations) {
        foreach ($saved_locations as $i => $l) {
            //Add error message to location
            if (isset($location_errors[$l['label']]) && $location_errors[$l['label']]['location_text'] === $l['text']) {
                $saved_locations[$i]['error'] = $location_errors[$l['label']]['message'];
            //Remove any error message from location
            } else {
                $saved_locations[$i]['error'] = null;
            }
        }
    }

    //Encode and save location data
    $saved_locations = wp_json_encode($saved_locations);
    $saved_locations = base64_encode($saved_locations);
    update_post_meta($post->ID, 'blm_post_location_data', $saved_locations);

    // Update response
    update_post_meta(
        $post->ID,
        'blm_savepost_response',
        wp_json_encode(
            array(
                'code'    => $api_response['code'],
                'message' => $api_response['message'],
            )
        )
    );

    return true;
}// blm_admin_post_bloom

/*
* blm_admin_post_location_get
* Get location data for the given post and decode into an array
*/
function blm_admin_post_location_get($post_id)
{   

    //Initialize 
    $data_result = null;

    //Get location data for post
    $data_raw = get_post_meta($post_id, 'blm_post_location_data', true);

    //Decode data
    if ( $data_raw ) {
        //If Base64 encoded, then decode 
        if ( 0 !== strpos($data_raw, '[')) {
            $data_decoded = base64_decode($data_raw);
        } else {
            $data_decoded = $data_raw;
        }

        if (! $data_decoded ) {
            return false;
        }

        //Decode JSON
        $data_result = json_decode($data_decoded, true);
    }

    return $data_result;
}//blm_admin_post_location_get

/*
* blm_admin_post_validtype
* Check for a valid page type
*/
function blm_admin_post_validtype($page)
{

    // Ignore if invalid page type
    if ('post.php' !== $page && 'post-new.php' !== $page) {
        return false;
    }

    return true;
}//blm_admin_post_validtype

/*
* blm_admin_post_reset
* Reset the post's Bloom status message
*/
function blm_admin_post_reset()
{
    global $post_ID;

    //Remove post's geotagging status
    delete_post_meta($post_ID, 'blm_savepost_response');

    return true;
}// blm_admin_post_reset
