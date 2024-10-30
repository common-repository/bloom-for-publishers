<?php //Admin Settings: admin-settings.php

// Initialize
global $blm_posttypes;
$blm_posttypes = array();

// Create custom plugin settings menu
add_action('admin_menu', 'blm_settings_menu');

// Call register settings function
add_action('admin_init', 'blm_settings_register');

// Register scripts
add_action('admin_enqueue_scripts', 'blm_settings_scripts');

// Add note if settings not complete
add_action('admin_notices', 'blm_settings_notice');

// Add custom column option
add_action('manage_posts_custom_column' , 'blm_list_post_column', 10, 2);
add_filter('manage_post_posts_columns', function($columns) {
    return array_merge($columns, ['blm_geotagged' => __('Geotagged', 'textdomain')]);
});

/*
* blm_settings_menu
* Add Bloom to the settings menu
*/
function blm_settings_menu()
{
    // Create new top-level menu
    add_menu_page('Bloom for Publishers', 'Bloom', 'administrator', 'bloom-for-publishers/admin/admin-settings.php', 'blm_settings_page', 'https://assets.bloom.li/images/logo-jelly-white-small-wordpress.png');
}// blm_settings_menu

/*
* blm_settings_register
* Register settings page with WordPress
*/
function blm_settings_register($additional = null)
{

    // Register settings with WordPress
    register_setting('blm_options_group', 'blm_setting_bloom_api_key');
    register_setting('blm_options_group', 'blm_setting_bloom_publisher_key');
    register_setting('blm_options_group', 'blm_setting_google_api_key');
    register_setting('blm_options_group', 'blm_setting_amp');
    register_setting('blm_options_group', 'blm_setting_tab');

    register_setting('blm_options_group', 'blm_setting_search_enabled');
    register_setting('blm_options_group', 'blm_setting_search_preview');
    register_setting('blm_options_group', 'blm_setting_search_settings');
    register_setting('blm_options_group', 'blm_setting_search_shortlink');
    register_setting('blm_options_group', 'blm_setting_search_icon_display');
    register_setting('blm_options_group', 'blm_setting_search_icon_delay_seconds');
    register_setting('blm_options_group', 'blm_setting_search_open_bottom');
    register_setting('blm_options_group', 'blm_setting_search_open_seconds');
    register_setting('blm_options_group', 'blm_setting_search_cookies_enabled');

    register_setting('blm_options_group', 'blm_setting_map_append_enabled');
    register_setting('blm_options_group', 'blm_setting_map_append_height');
    register_setting('blm_options_group', 'blm_setting_map_append_zoom');
    register_setting('blm_options_group', 'blm_setting_map_append_position');

    register_setting('blm_options_group', 'blm_setting_feed_append_enabled');
    register_setting('blm_options_group', 'blm_setting_feed_id');

    // Get post type settings
    $custom_settings = blm_get_posttypes();
    if ($custom_settings) {
        foreach ($custom_settings as $a) {
            register_setting('blm_options_group', $a);
        }
    }
}// blm_settings_register

/*
* blm_settings_notice
* Display notice to reminder user of incorrect Bloom settings
*/
function blm_settings_notice()
{
    //Gather plugin settings for validation
    $keys = array(
        get_option('blm_setting_bloom_api_key'),
        get_option('blm_setting_bloom_publisher_key'),
        get_option('blm_setting_google_api_key'),
    );

    //Filter insufficient input
    $keys = array_filter($keys);

    //Confirm validation success
    if (count($keys) === 3 || ( isset($_SERVER['REQUEST_URI']) && strpos(sanitize_text_field($_SERVER['REQUEST_URI']), 'bloom-for-publishers') )) {
        return true;
    }

    //Show notice to complete settings
    echo '<div class="updated"><p><strong>Bloom:</strong> Complete your Bloom plugin setup so you can begin geotagging posts.<a href="' . esc_url(get_admin_url()) . 'options-general.php?page=bloom-for-publishers%2Fadmin%2Fadmin-settings.php" class="button-primary" style="display: inline-block; margin-left: 10px;">Complete Setup</a></p></div>';
}// blm_settings_notice

/*
* blm_settings_page
* Code for the Bloom settings page
*/
function blm_settings_page()
{
    // Get current tab
    $blm_current_tab = 'general';
    if (get_option('blm_setting_tab')) {
        $blm_current_tab = sanitize_text_field(get_option('blm_setting_tab'));
    }

    // Action request
    if (isset($_GET['blm_action'])) {
        // Validate nonce
        if (! isset($_GET['blm_action_n']) || ! wp_verify_nonce(sanitize_key($_GET['blm_action_n']), 'blm_action_nonce')) {
            return false;
        }

        switch ($_GET['blm_action']) {
            case 'refresh_settings':
                $blm_current_tab = 'search';
                update_option('blm_setting_tab', $blm_current_tab);

                // Sync plugin settings file
                blm_plugin_settings_update();

                echo '<script>window.location.replace("' . esc_url(get_admin_url()) . 'options-general.php?page=bloom-for-publishers/admin/admin-settings.php");</script>';

                break;
        }
    }

    // Enqueue WordPress media scripts
    wp_enqueue_media();

    // Validate application key
    $api_key = get_option('blm_setting_bloom_api_key');

    if ($api_key) {
        // Process Bloom API call
        $api_response = blm_lib_api_process(
            'info',
            array(
                'app_key'    => $api_key,
                'app_action' => 'validate_key',
                'key'        => $api_key
            )
        );

        if ($api_response->success) {
            $api_key_valid = $api_response;
        }
    }

    // Validate publisher key
    $pub_key = get_option('blm_setting_bloom_publisher_key');
    if ($pub_key) {
        // Process Bloom API call
        $api_response = blm_lib_api_process(
            'publisher',
            array(
                'app_key'    => $api_key,
                'app_action' => 'validate_key',
                'key'        => $pub_key
            )
        );

        if ($api_response->success) {
            $pub_key_valid = $api_response;
        }
    }

    // Validate Google API key
    $google_key = get_option('blm_setting_google_api_key');

    // Set scripts
    if ($google_key) {
        wp_enqueue_script('blm_js_google', 'https://maps.googleapis.com/maps/api/js?language=en&key=' . $google_key);
        wp_enqueue_script('blm_js_geocode', plugin_dir_url(__FILE__) . 'js/geocode.js', null, blm_lib_get_version());
    }

    // Check for search plugin settings
    $hasSearchPlugin = false;
    $settings        = blm_plugin_settings_read();

    if ($settings && isset($settings['key'])) {
        $hasSearchPlugin = true;
    }

    $blm_setting_map_height_default = 300;
    $blm_setting_map_height_value = is_numeric(get_option('blm_setting_map_append_height')) ? get_option('blm_setting_map_append_height') : $blm_setting_map_height_default;

    // Post type options
    $type_options = array(
        'news'           => 'News',
        'event'          => 'Event',
        'emergency'      => 'Emergency',
        'recommendation' => 'Recommendation',
        'none'           => 'Don\'t post to Bloom',
    );
    ?>

    <div class="wrap">

        <h2>Bloom</h2>
        <p>The following settings only apply to publishers who are registered on <a href="https://www.bloom.li" title="Bloom" target="_blank">Bloom</a>.<br /><a href="https://www.youtube.com/embed/bvHdmyMzK40" title="Watch video tutorial" target="_blank">Watch our video tutorial</a> on how to configure these settings.</p>

        <form method="post" action="options.php" id="blm-settings-form">

            <input type="hidden" name="blm_setting_tab" value="<?php echo esc_attr($blm_current_tab); ?>" />

            <?php
            // Identify which settings this page will handle
            settings_fields('blm_options_group');
            do_settings_sections('blm_options_group');
            add_thickbox();
            blm_settings_tabs();
            ?>

            <div class="blm-settings-section" data-tab="general">

                <h2>General Plugin Settings</h2>
                <p>These keys connect to your accounts on Bloom and Google.  They will allow this plugin to provide geotagging for your posts, and local search and maps on your website pages.</p>

                <table class="form-table">

                    <tr>
                        <th scope="row">
                            <strong>Bloom API Key</strong>
                            <a href="#TB_inline?width=600&inlineId=blm-tb-bloomapikey" class="thickbox"></a>
                        </th>
                        <td data-field="bloom-api-key">
                            <input type="text" name="blm_setting_bloom_api_key" value="<?php echo esc_attr(get_option('blm_setting_bloom_api_key')); ?>" />

                            <?php if (isset($api_key_valid)) { ?>
                                <div data-code="<?php echo esc_attr($api_key_valid->code); ?>" class="blm-field-note">
                                    <div class="blm-field-message">
                                        <span><?php echo esc_html($api_key_valid->message); ?></span>
                                        <a href="https://www.bloom.li/account/publisher" title="Bloom Account" target="_blank" class="blm-field-link">Get your API key</a>
                                    </div>
                                </div>
                            <?php } ?>

                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <strong>Bloom Publisher Key</strong>
                            <a href="#TB_inline?width=600&inlineId=blm-tb-bloompublisherkey" class="thickbox"></a>
                        </th>
                        <td data-field="bloom-publisher-key">
                            <input type="text" name="blm_setting_bloom_publisher_key" value="<?php echo esc_attr(get_option('blm_setting_bloom_publisher_key')); ?>" />

                            <?php if (isset($pub_key_valid)) { ?>
                                <div data-code="<?php echo esc_attr($pub_key_valid->code); ?>" class="blm-field-note">
                                    <div class="blm-field-message">
                                        <span><?php echo esc_html($pub_key_valid->message); ?></span>
                                        <a href="https://www.bloom.li/account/publishers" title="Bloom Account" target="_blank" class="blm-field-link">Get your publisher key</a>
                                    </div>
                                </div>
                            <?php } ?>

                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <strong>Google API Key</strong>
                            <a href="#TB_inline?width=600&inlineId=blm-tb-googleapikey" class="thickbox"></a>
                        </th>   
                        <td data-field="google-api-key">
                            <input type="text" name="blm_setting_google_api_key" value="<?php echo esc_attr(get_option('blm_setting_google_api_key')); ?>" />
                            <div data-code="3" class="blm-field-note" id="blm-field-note-container">
                                <div class="blm-field-message">

                                    <span id="blm-field-note-message">
                                        <?php if ($google_key) { ?>
                                            <span id="blm-field-note-message-validating">Validating</span>
                                            <span id="blm-field-note-message-invalid">Invalid: <a href="#TB_inline?width=600&inlineId=blm-tb-googleapikey" class="thickbox">Check your Google API Key</a></span>
                                            <span id="blm-field-note-message-valid">Valid</span>
                                        <?php } else { ?>
                                            <span id="blm-field-note-message-empty"><a href="#TB_inline?width=600&inlineId=blm-
tb-googleapikey" class="thickbox">Get your Google API Key</a></span>
                                        <?php } ?>
                                    </span>

                                </div>
                            </div>

                            <?php if ($google_key) { ?>
                                <input type="hidden" id="blm-location-input" value="1600 Pennsylvania Ave NW, Washington, DC 20500" />
                                <div id="blm-location-search-results">
                                    <div id="blm-location-search-results-list">
                                </div>
                            <?php } ?>

                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <strong>Google AMP Compatibility</strong>
                            <a href="#TB_inline?width=600&inlineId=blm-tb-googleamp" class="thickbox"></a>
                        </th>
                        <td data-field="google-amp">
                            <select name="blm_setting_amp">
                                <option value="false"<?php echo ( false === get_option('blm_setting_amp') || 'false' === get_option('blm_setting_amp') ? ' selected' : '' ); ?>>Disabled</option>
                                <option value="true"<?php echo ( 'true' === get_option('blm_setting_amp') ? ' selected' : '' ); ?>>Enabled</option>
                            </select>
                        </td>
                    </tr>

                </table>

                <?php submit_button('Save Settings'); ?>

                <div class="blm-settings-section">
                    <h3>Getting started: How to install and configure Bloom</h3>
                    <iframe width="560" height="315" src="https://www.youtube.com/embed/bvHdmyMzK40" frameborder="0" allowfullscreen></iframe>
                </div>

                <div id="blm-tb-bloomapikey" style="display: none;">
                    <h3>Bloom API Key</h3>
                    <p>This is your private key that gives you access to Bloom's geotagging and search tools.  For security purposes, please keep this to yourself.</p>
                    <ol>
                        <li>Go to your list of registered Publishers on Bloom: <a href="https://www.bloom.li/account/publishers" title="List of publishers" target="_blank">bloom.li/account/publishers</a></li>
                        <li>Select a Publisher</li>
                        <li>Select the "Service Settings" tab</li>
                        <li>Copy the API Key</li>
                    </ol>
                </div>

                <div id="blm-tb-bloompublisherkey" style="display: none;">
                    <h3>Bloom Publisher Key</h3>
                    <p>This is your key for your publisher account that allows you to send requests to Bloom for geotagging and search.</p>
                    <ol>
                        <li>Go to your list of registered Publishers on Bloom: <a href="https://www.bloom.li/account/publishers" title="List of publishers" target="_blank">bloom.li/account/publishers</a></li>
                        <li>Select a Publisher</li>
                        <li>Select the "Service Settings" tab</li>                   
                        <li>Copy the Publisher Key</li>
                    </ol>
                </div>

                <div id="blm-tb-googleapikey" style="display: none;">
                    <h3>Google API Key</h3>
                    <p>This is required in order to run the geocoding feature.</p>
                    <ol>
                        <li>Visit the Google API page: <a href="https://console.developers.google.com/apis" title="Google Developer API" target="_blank">https://console.developers.google.com/apis</a>.</li>
                        <li>Select or Create a Project.</li>
                        <li>In the <a href="https://console.developers.google.com/apis/library" title="Google API Library" target="_blank">Google API Library</a>, search for "Google Maps JavaScript API" and "Google Maps Geocoding API" and enable these APIs.</li>
                        <li>In <a href="https://console.developers.google.com/billing/" title="Google API Billing" target="_blank">Google API Billing</a>, add a billing account. It is required to add this before the APIs can be used <em>(<a href="https://support.google.com/googleapi/answer/6158867?hl=en" title="Google billing requirement" target="_blank">More information</a>)</em>.</li>
                        <li>Go to the <a href="https://console.developers.google.com/apis/credentials" title="Google API Credentials" target="_blank">Google API Credentials</a> page and follow the steps to create your API key.</li>
                        <li>Copy and paste the API key into the Google API Key field on this page.</li>
                    </ol>
                    <h4>Troubleshooting</h4>
                    <p>If you've followed the instructions above but the Google API Key field above is either saying "Invalid" or no locations are showing in the post geotagging form, follow these instructions to learn what the issue may be:</p>
                    <ol>
                        <li>Open Console: This is likely an error with the Google account, which you can read about in your browser's "Javascript console" or "Developer console".  To open the console, <a href="https://documentation.concrete5.org/tutorials/how-open-browser-console-view-errors" title="Browser console instructions" target="_blank">follow these instructions</a> based on the browser you're using.</li>
                        <li>Diagnose Error: In the console, you should see a message about the Google API. This message is usually descriptive and will guide to you correcting the issue.</li>
            <li>If there was no error in the browser console, then there may be an issue with this WordPress plugin. Make sure you have the most up-to-date version of this plugin by going to the Plugins page here in your Wordpress account and looking for an "update" option for <em>Bloom for Publishers</em>.</li>
                    </ol>
                </div>

                <div id="blm-tb-googleamp" style="display: none;">
                    <h3>Google AMP Compatibility</h3>
                    <p>Enable this setting if your website requires compatibility with <a href="https://amp.dev" title="Google AMP" target="_blank">Google AMP</a>. By doing so, all public-facing features generated by the Bloom for Publishers plugin will be coded to meet Google AMP standards automatically.</p>
                    <p>Due to code restrictions for AMP, the following features are only compatible and displayed when in Reader Mode:</p>
                    <ol>
                        <li><strong>Search & Map Buttons:</strong> These include buttons mentioned in the "Links & Buttons" tab of this plugin's settings.</li>
                        <li><strong>Local Search</strong></li>
                    </ol>
                </div>

            </div>

            <div class="blm-settings-section" data-tab="search">

                <h2>Local Search Settings</h2>
                <p>Local Search gives your website the capability to allow users to explore content by their current location or by typing a location.</p>

                <?php if (! $hasSearchPlugin) { ?>
                    <p>Steps to begin using this tool:</p>
                    <ol>
                        <li>Create the plugin on Bloom by following the instructions in our tutorial video below.
                        <li>Click this button to sync your website settings: <a href="<?php echo esc_url(get_admin_url()); ?>options-general.php?page=bloom-for-publishers/admin/admin-settings.php&blm_action=refresh_settings&blm_action_n=<?php echo esc_attr(wp_create_nonce('blm_action_nonce')); ?>" class="button blm-settings-refresh">Sync Local Search Settings</a></li>
                    </ol>
                    <br /><br />
                    <h3>Getting started: How to install and configure Local Search</h3>
                    <iframe width="560" height="315" src="https://www.youtube.com/embed/bvHdmyMzK40" frameborder="0" allowfullscreen></iframe>

                <?php } else { ?>
                    <p>When enabled, if you open the Local Search tool and it is blank, then it means there is no content geotagged on your Bloom account</p>

                    <table class="form-table">

                        <tr>
                            <th scope="row">
                                <strong>Local Search</strong>
                                <a href="https://www.bloom.li/publisher/plugins/search" title="News Nearby Search plugin" target="_blank" class="blm-external-link"></a>
                            </th>
                            <td data-field="search-enabled">
                                <select name="blm_setting_search_enabled">
                                    <option value="true"<?php echo ( 'true' === get_option('blm_setting_search_enabled') ? ' selected' : '' ); ?>>Enabled</option>
                                    <option value="false"<?php echo ( 'false' === get_option('blm_setting_search_enabled') ? ' selected' : '' ); ?>>Disabled</option>
                                </select>
                            </td>
                        </tr>

                        <tr>

                            <th scope="row">
                                <strong>Local Search Preview</strong>
                                <a href="#TB_inline?width=600&inlineId=blm-tb-localsearchpreview" class="thickbox"></a>
                            </th>
                            <td data-field="search-preview">
                                <select name="blm_setting_search_preview">
                                    <option value="true"<?php echo ( 'true' === get_option('blm_setting_search_preview') ? ' selected' : '' ); ?>>On</option>
                                    <option value="false"<?php echo ( 'false' === get_option('blm_setting_search_preview') ? ' selected' : '' ); ?>>Off</option>
                                </select>
                            </td>

                        </tr>

                    </table>

                    <?php submit_button('Save Settings'); ?>

                    <div class="blm-settings-section">

                        <h3>Custom Branding</h3>
                        <p>The Local Search plugin has certain areas that can use a color from your website's branding. First, you will have to choose a color for your publisher while logged into your <a href="https://www.bloom.li/account/publishers" title="Bloom account" target="_blank">Bloom account</a>. Then, click the "Sync plugin color" button below to update the plugin's color here on your website.</p>
                        <p><a href="<?php echo esc_url(get_admin_url()); ?>options-general.php?page=bloom-for-publishers/admin/admin-settings.php&blm_action=refresh_settings&blm_action_n=<?php echo esc_attr(wp_create_nonce('blm_action_nonce')); ?>" class="button blm-settings-refresh">Sync plugin color</a></p>

                        <?php if (isset($settings['color']) && $settings['color']) { ?>
                            <div id="blm-settings-color-preview">
                                <span>Current plugin color is #<?php echo esc_html($settings['color']); ?>:</span>
                                <div style="background: #<?php echo esc_html($settings['color']); ?>"></div>
                            </div>
                        <?php } ?>

                                                <div id="blm-tb-localsearchpreview" style="display: none;">
                                                        <h3>Local Search Preview</h3>
                                                        <p>This option allows for certain users who are logged into your website to view Bloom's Local Search plugin. It will only display the plugin preview if the user has one of the following roles: administrator, editor, contributor, or author. Non-administrative users and other visitors to your website will not see the plugin unless you have selected "Enabled" for the option above.</p>
                                                        <p>The preview will include all of the features and functionality so that you can test before enabling it for all users.</p>
                                                </div>

                    </div>

                    <div class="blm-settings-section">

                        <h3>User Experience Options</h3>
                        <p>You can blend the search plugin into your current website experience by adding timers, buttons, and custom URLs.</p>

                        <h4>Custom Links</h4>
                        <p>Click the "Links & Buttons" tab above to get instructions on how to use custom links for the plugin.</p>

                        <h4>Show Plugin Icon</h4>
                        <p>The plugin's circle icon can be displayed immediately, after a few seconds, or never.</p>

                        <table class="form-table">
                            <tr>

                                <th scope="row">
                                    <strong>Icon Display</strong>
                                </th>
                                <td data-field="search-icon-display">
                                    <select name="blm_setting_search_icon_display">
                                        <option value="true"<?php echo ( 'true' === get_option('blm_setting_search_icon_display') ? ' selected' : '' ); ?>>On</option>
                                        <option value="false"<?php echo ( 'false' === get_option('blm_setting_search_icon_display') ? ' selected' : '' ); ?>>Off</option>
                                    </select>
                                </td>

                            </tr>
                            <tr data-field="search-icon-delay" data-displayed="<?php echo esc_attr(get_option('blm_setting_search_icon_display')); ?>">

                                <th scope="row">
                                    <strong>Seconds Until Icon Display</strong>
                                </th>
                                <td data-field="search-icon-delay">
                                    <input type="text" name="blm_setting_search_icon_delay_seconds" value="<?php echo esc_attr(get_option('blm_setting_search_icon_delay_seconds')); ?>" />
                                </td>

                            </tr>
                        </table>

                        <h4>Open Plugin Window On Scroll</h4>
                        <p>When a user scrolls to the bottom of the page, would you like the plugin window to automatically open to give them the options to explore more content on your website?</p>

                        <table class="form-table">
                            <tr>

                                <th scope="row">
                                    <strong>Open when Reach Bottom</strong>
                                </th>
                                <td data-field="search-open-bottom">
                                    <select name="blm_setting_search_open_bottom">
                                        <option value="true"<?php echo ( 'true' === get_option('blm_setting_search_open_bottom') ? ' selected' : '' ); ?>>On</option>
                                        <option value="false"<?php echo ( 'false' === get_option('blm_setting_search_open_bottom') ? ' selected' : '' ); ?>>Off</option>
                                    </select>
                                </td>

                            </tr>
                        </table>

                        <h4>Auto-Open Plugin Window</h4>
                        <p>You can set this timer to automatically open the plugin window after a certain number of seconds, as long as the user has not interacted with the plugin yet. Leave blank to ignore this auto-open setting.</p>

                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <strong>Seconds Until Auto-Open</strong>
                                </th>
                                <td data-field="search-timer-seconds">
                                    <input type="text" name="blm_setting_search_open_seconds" value="<?php echo esc_attr(get_option('blm_setting_search_open_seconds')); ?>" />
                                </td>
                            </tr>
                        </table>

                        <?php submit_button('Save Settings'); ?>

                    </div>

                    <div class="blm-settings-section">

                        <h3>Data Options</h3>
                        <p>When users interact with the Local Search plugin, the following data points may be stored as cookies in their browser. You may utilize this data for your own purposes by creating a custom script or query that retrieves their cookie data when they visit your website. The data below includes the cookie name (<strong>bolded</strong>) and description.</p>

                        <h4>Enable Cookie Storage</h4>
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <strong>Store data in cookies</strong>
                                </th>
                                <td data-field="search-cookies-enabled">
                                    <select name="blm_setting_search_cookies_enabled">
                                        <option value="false"<?php echo ( 'false' === get_option('blm_setting_search_cookies_enabled') ? ' selected' : '' ); ?>>Off</option>
                                        <option value="true"<?php echo ( 'true' === get_option('blm_setting_search_cookies_enabled') ? ' selected' : '' ); ?>>On</option>
                                    </select>
                                </td>
                            </tr>
                        </table>

                        <?php submit_button('Save Settings'); ?>

                        <h4>User's Current Location</h4>
                        <ul>
                            <li><strong>bloom_user_location_latlon</strong>: The latitude and longitude geographic coordinates (separated by a comma)</li>
                            <li><strong>bloom_user_location_address</strong>: The full address of the location</li>
                            <li><strong>bloom_user_distance_miles</strong>: The distance (in miles) between the user's location and the current page location</li>
                        </ul>

                        <h4>Page's Geotagged Location</h4>
                        <p>These cookies are only accessible on geotagged pages. Note that the following data are also available as meta tags within the page HTML. Learn more about <a href="https://www.bloom.li/advocacy/metadata" title="Geo Metadata" target="_blank">Geo Metadata</a>.</p>
                        <ul>
                            <li><strong>bloom_page_location_latlon</strong>: The latitude and longitude geographic coordinates (separated by a comma)</li>
                            <li><strong>bloom_page_location_address</strong>: The full address of the location</li>
                        </ul>

                    </div>

                <?php } ?>

            </div>

            <div class="blm-settings-section" id="blm-settings-section-map" data-tab="map" data-enabled="<?php echo esc_attr(get_option('blm_setting_map_append_enabled')); ?>">

                <table class="form-table">

                    <tr>
                        <th scope="row">
                            <h2>Append Static Map Image To Posts</h2>
                        </th>
                        <td>
                            <select name="blm_setting_map_append_enabled" id="blm-setting-map-append-enabled">
                                <option value="false"<?php echo ( 'false' === get_option('blm_setting_map_append_enabled') ? ' selected' : '' ); ?>>Disabled</option>
                                <option value="true"<?php echo ( 'true' === get_option('blm_setting_map_append_enabled') ? ' selected' : '' ); ?>>Enabled</option>
                            </select>
                            <a href="#TB_inline?width600&inlineId=blm-tb-mapappend" class="thickbox"></a>
                        </td>
                    </tr>

                    <tr class="blm-append-map-field">
                        <th scope="row">
                            <strong>Map Zoom</strong>
                        </th>
                        <td>
                            <select name="blm_setting_map_append_zoom">
                                <option value="close"<?php echo ( 'close' === get_option('blm_setting_map_append_zoom') ? ' selected' : '' ); ?>>Close (block-level view)</option>
                                <option value="far"<?php echo ( 'far' === get_option('blm_setting_map_append_zoom') ? ' selected' : '' ); ?>>Far (neighborhood-level view)</option>
                            </select>
                        </td>
                    </tr>

                    <tr class="blm-append-map-field">
                        <th scope="row">
                            <strong>Map Height (in pixels)</strong>
                        </th>
                        <td>
                            <input type="text" name="blm_setting_map_append_height" value="<?php echo esc_attr($blm_setting_map_height_value); ?>" />
                        </td>
                    </tr>

                    <tr class="blm-append-map-field">
                        <th scope="row">
                            <strong>Map Position</strong>
                        </th>
                        <td>
                            <select name="blm_setting_map_append_position">
                                <option value="top"<?php echo ( 'top' === get_option('blm_setting_map_append_position') ? ' selected' : '' ); ?>>Above article</option>
                                <option value="bottom"<?php echo ( 'bottom' === get_option('blm_setting_map_append_position') ? ' selected' : '' ); ?>>Below article</option>
                            </select>
                        </td>
                    </tr>

                </table>

                <?php submit_button('Save Settings'); ?>

                <div class="blm-settings-section">
                    <?php include plugin_dir_path(__FILE__) . 'info-shortcodes.php'; ?>
                </div>

                <div id="blm-tb-mapappend" style="display: none;">
                    <h3>Append Map To Posts</h3>
                    <p>This option will automatically display a map at the end of every geotagged article on your website.  The map replaces the need to copy/paste the shortcode option (below this field) into each individual post.  Each map accurately labels the street address of the article with the address written in text and as a pin on the map.</p>
                    <p>The map will only display on the post's individual page and if the map shortcode (below this field) is not being used already.</p>
                </div>

            </div>

            <div class="blm-settings-section" id="blm-settings-section-feed" data-tab="feed" data-enabled="<?php echo esc_attr(get_option('blm_setting_feed_append_enabled')); ?>">

                <table class="form-table">

                    <tr>
                        <th scope="row">
                            <h2>Append Feed To Posts</h2>
                        </th>
                        <td>
                            <select name="blm_setting_feed_append_enabled" id="blm-setting-feel-append-enabled">
                                <option value="false"<?php echo ( 'false' === get_option('blm_setting_feed_append_enabled') ? ' selected' : '' ); ?>>Disabled</option>
                                <option value="true"<?php echo ( 'true' === get_option('blm_setting_feed_append_enabled') ? ' selected' : '' ); ?>>Enabled</option>
                            </select>
                            <a href="#TB_inline?width600&inlineId=blm-tb-feedappend" class="thickbox"></a>
                        </td>
                    </tr>

                    <tr class="blm-append-feed-field">
                        <th scope="row">
                            <strong>Feed ID</strong>
                        </th>
                        <td>
                            <input type="text" name="blm_setting_feed_id" value="<?php echo esc_attr(get_option('blm_setting_feed_id')); ?>" />
                            <a href="#TB_inline?width600&inlineId=blm-tb-feedid" class="thickbox"></a>
                        </td>
                    </tr>

                </table>

                <?php submit_button('Save Settings'); ?>

                <div id="blm-tb-feedappend" style="display: none;">
                    <h3>Append Nearby Feed To Posts</h3>
                    <p>This option will automatically display a news feed at the end of every geotagged article on your website. The feed will recommend other content of yours that's happening near the current article. The feed will organize the other articles by most recent and allow the reader to see nearby trends where they can select a category to filter the feed.</p>
                </div>

                <div id="blm-tb-feedid" style="display: none;">
                    <h3>Nearby Feed ID</h3>
                    <p>This ID can be found in your Bloom account by going to the <a href="<?php echo esc_url('https://www.bloom.li/account/publishers/' . ( $pub_key ? $pub_key . '/plugins/nearby-channel' : '' )); ?>" target="_blank" title="Nearby Channel page">Nearby Channel plugin page</a>.</p>
                    <p>You will have to create a Nearby Channel plugin and copy/paste the ID into this field.</p>
                </div>

            </div>

            <div class="blm-settings-section" data-tab="buttons">

                <h2>Pre-designed Search and Map Buttons (with shortcode)</h2>

                <p>Add these buttons anywhere on your website to help encourage your readers to use the local search plugin and view the article's map.  When clicked, the search button will open your local search plugin, and the map button will open a popup with the article's map and address.</p>
                <p>Simply copy and customize the code below into the single article template file (ex. single.php), preferably near the page title or where your social network buttons are displayed.</p>

                <div class="blm-shortcode">
                    <h4>Options</h4>
                    <div class="blm-shortcode-code">
                        <?php echo esc_html('<blm-button data-type="search" data-style="dark"></blm-button>'); ?>
                    </div>
                    <div class="blm-shortcode-options">
                        <ul>
                            <li>Type: "search" or "map"</li>
                            <li>Style: "light" or "dark"</li>
                        </ul>
                    </div>
                </div>

                <div class="blm-shortcode-preview">
                    <blm-button data-type="search" data-style="dark"></blm-button>
                    <blm-button data-type="map" data-style="light"></blm-button>
                </div>

                <div class="blm-settings-section">

                    <h2>Links to open Search and Map (with shortcode)</h2>

                    <p>Similar to the buttons, this custom link option can encourage your readers to use Bloom's plugins.  The code can be inserted anywhere on your website and will automatically open the Local Search plugin or Map popup for geotagged articles.</p>

                    <div class="blm-shortcode">
                        <h4>Options</h4>
                        <div class="blm-shortcode-code" id="blm-shortcode-code-banner">
                            <?php echo esc_html('<blm-link data-type="search"><img src="YOUR IMAGE" /></blm-link>'); ?>
                        </div>
                        <div class="blm-shortcode-options">
                            <ul>
                                <li>Type: "search" or "map"</li>
                                <li>Content: Text or &lt;img&gt; tag</li>
                            </ul>
                            <div id="blm-banner-premade">
                                <p>Try one of our pre-made designs:</p>
                                <select id="blm-banner-premade-options">
                                    <option>Select one</option>
                                    <option value="banner-01">Banner: Green</option>
                                    <option value="banner-02">Banner: Blue</option>
                                    <option value="square-01">Square: Green</option>
                                    <option value="square-02">Square: Blue</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="blm-shortcode-preview" id="blm-banner-premade-preview"></div>

                </div>

                <div class="blm-settings-section">

                    <h2>Custom link to open Search (without shortcode)</h2>
                    <p>Use these options to add a direct link to the Search plugin on your website, emails, or other websites such as social networks. When someone visits the URL and you have Local Search enabled, the search will open automatically.</p>

                    <h4>Option 1: Friendly Readable Shortlink</h4>
                    <p>URL: <a href="<?php echo esc_url(get_site_url()); ?>/nearby" title="Link to local search" target="_blank"><?php echo esc_url(get_site_url()); ?>/nearby</a></p>
                    <p>This option will open the plugin and immediately get content near the user's current location. The option will only work if it is enabled below and if a page on your website does not already use that URL name.</p>
                    <p>
                        <select name="blm_setting_search_shortlink">
                            <option value="true"<?php echo ( 'true' === get_option('blm_setting_search_shortlink') ? ' selected' : '' ); ?>>Enabled</option>
                            <option value="false"<?php echo ( 'false' === get_option('blm_setting_search_shortlink') ? ' selected' : '' ); ?>>Disabled</option>
                        </select>
                        <?php submit_button('Save', 'primary', 'submit', false); ?>
                    </p>

                    <h4 style="margin-top: 40px;">Option 2: URL Parameter</h4>
                    <p>Example URL: <a href="<?php echo esc_url(get_site_url()); ?>?bloom_search=open" title="Link to local search" target="_blank"><?php echo esc_url(get_site_url()); ?>?bloom_search=open</a></p>
                    <p>Append <em>?bloom_search=ACTION</em> to the end of any URL for your website. Replace "ACTION" with any of the following:</p>
                    <ul>
                        <li><em>prompt</em>: Open the plugin with a set of search options for the user to choose from.</li>
                        <li><em>open</em>: Open the plugin and immediately retrieve hyperlocal content.</li>
                        <li><em>nearby</em>: Open the plugin and immediately get content near the user's current location.</li>
                    </ul>

                </div>

            </div>

            <?php
            global $blm_posttypes;
            if (! empty($blm_posttypes)) {
                ?>
                <div class="blm-settings-section" data-tab="post_types">

                    <h2>Post Type Settings</h2>

                    <table class="form-table">

                        <tr class="blm-settings-header">
                            <th>WordPress Post Type</th>
                            <th>Bloom Post Type<a href="#TB_inline?width600&inlineId=blm-tb-posttype" class="thickbox"></a></th>
                            <th>Publish Date Field<a href="#TB_inline?width600&inlineId=blm-tb-posttypepublished" class="thickbox"></a></th>
                            <th>Days Until Archived<a href="#TB_inline?width600&inlineId=blm-tb-posttypearchived" class="thickbox"></a></th>
                        </tr>

                        <?php foreach ($blm_posttypes as $k => $t) { ?>
                            <tr data-type="<?php echo esc_attr(get_option('blm_setting_posttype_' . $k) ? get_option('blm_setting_posttype_' . $k) : 'news'); ?>">

                                <th scope="row">
                                    <strong><?php echo esc_html($t['type']) . '<span>' . esc_html($k !== 'post' ? '(' . $k . ')' : ''); ?></span></strong>
                                </th>

                                <td data-field="posttype">
                                    <select name="blm_setting_posttype_<?php echo esc_attr($k); ?>" class="blm_setting_posttype">
                                        <?php
                                        foreach ($type_options as $tk => $tv) {
                                            echo '<option value="' . esc_attr($tk) . '"' . ( get_option('blm_setting_posttype_' . $k) === $tk ? ' selected' : '' ) . '>' . esc_html($tv) . '</option>';
                                        }
                                        ?>
                                    </select>
                                </td>

                                <td data-field="posttype_publishdate">
                                    <select name="blm_setting_posttype_publishdate_<?php echo esc_attr($k); ?>" class="">
                                        <option value="default">Use default publish date</option>
                                        <?php
                                        foreach ($t['fields'] as $f) {
                                            echo '<option value="' . esc_attr($f) . '"' . ( get_option('blm_setting_posttype_publishdate_' . $k) === $f ? ' selected' : '' ) . '>' . esc_html($f) . '</option>';
                                        }
                                        ?>
                                    </select>

                                    <div class="blm-settings-enddate">

                                        <p>Event End Date Field</p>

                                        <select name="blm_setting_posttype_enddate_<?php echo esc_attr($k); ?>">
                                            <?php
                                            foreach ($t['fields'] as $f) {
                                                echo '<option value="' . esc_attr($f) . '"' . ( get_option('blm_setting_posttype_enddate_' . $k) === $f ? ' selected' : '' ) . '>' . esc_html($f) . '</option>';
                                            }
                                            ?>
                                        </select>

                                    </div>

                                </td>

                                <td data-field="posttype_expiration">
                                    <?php
                                    if (get_option('blm_setting_posttype_expiration_' . $k)) {
                                        $expiration_days = get_option('blm_setting_posttype_expiration_' . $k);
                                    } else {
                                        $expiration_days = 20;
                                    }
                                    ?>
                                    <input name="blm_setting_posttype_expiration_<?php echo esc_attr($k); ?>" value="<?php echo esc_attr($expiration_days); ?>" />
                                </td>

                            </tr>

                        <?php } ?>

                    </table>

                    <?php submit_button('Save Settings'); ?>

                    <div id="blm-tb-posttype" style="display: none;">
                        <h3>Post Type</h3>
                        <p>By default, Bloom marks each post you geotag as a "news article".  In this section, you can choose a post type that better matches its content.</p>
                    </div>

                    <div id="blm-tb-posttypepublished" style="display: none;">
                        <h3>Publish Date Field</h3>
                        <p>By default, Bloom uses a post's "Publish Date" to label when it took place.</p>
                        <p>However, if you are using a custom post type, an Event for example, it is likely that the date of the event is not necessarily the "Publish Date".  In this case, we've provided options below for you to specify the correct field we should use.</p>
                    </div>

                    <div id="blm-tb-posttypearchived" style="display: none;">
                        <h3>Days Until Archived</h3>
                        <p>Posts submitted on Bloom each have an archive date to respect news and events that you may want to archive after a specific number of days.</p>
                        <p>In this column, you can change the default number of days until a specific post type is archived.  The number of days begins from the published date of the post.  The value must be an integer that is less than or equal to 30.</p>
                    </div>

                </div>

            <?php } ?>

        </form>

    </div>

    <?php
}// blm_settings_page

/*
* blm_settings_scripts
* Add scripts for settings page
*/
function blm_settings_scripts($hook)
{

    //Add scripts for post list page
    if (strpos($hook, 'edit') === 0) {
        wp_enqueue_style('blm_css_adminsettingslist', plugins_url('css/admin-settings-list.css', __FILE__), array(), blm_lib_get_version(), 'all');

    //Add scripts for Bloom for Publishers settings pages
    } elseif (strpos($hook, 'toplevel_page_bloom-for-publishers') === 0) {
        wp_enqueue_style('blm_css_adminsettings', plugins_url('css/admin-settings.css', __FILE__), array(), blm_lib_get_version(), 'all');
        wp_enqueue_style('blm_css_adminglobal', plugins_url('css/global.css', __FILE__), array(), blm_lib_get_version(), 'all');
        wp_enqueue_script('blm_js_libsettings', plugins_url('lib/js/global.js', __DIR__), array(), blm_lib_get_version());
        wp_enqueue_script('blm_js_adminsettings', plugins_url('js/admin-settings.js', __FILE__), array( 'jquery' ), blm_lib_get_version());

    }

}// blm_settings_scripts

/*
* blm_settings_tabs
* Add tabs for each group of settings
*/

function blm_settings_tabs()
{
    // Initialize
    $tabs = array(
        'general' => 'General',
        'search'  => 'Local Search',
        'map'     => 'Map',
        'feed'    => 'Feed',
        'buttons' => 'Links & Buttons',
    );

    // Add Post Type if has custom fields
    global $blm_posttypes;
    if (! empty($blm_posttypes)) {
        $tabs['post_types'] = 'Post Type Settings';
    }

    echo '<div id="icon-themes" class="icon32"><br></div>';
    echo '<h2 class="nav-tab-wrapper">';

    // Generate tabs
    foreach ($tabs as $tab => $name) {
        echo '<a class="nav-tab" href="javascript:;" data-tab="' . esc_attr($tab) . '">' . esc_html($name) . '</a>';
    }

    echo '</h2>';
}//blm_settings_tabs

/*
* blm_list_post_column
* Display the geotagged status of a post in the admin post list
*/
function blm_list_post_column( $column, $post_id ) {

    //Ignore if not 'geotagged' column
    if ( 'blm_geotagged' !== $column ) {
        return false;
    }

    //Determine if geotagged
    if ( get_post_meta( $post_id, 'blm_post_key', true ) ) {
        echo '<div aria-hidden="true" title="Yes" class="blm-geotagged-column yes"></div>';
        echo '<span class="screen-reader-text blm-geotagged-column-text">Geotagged</span>';
    } else {
        echo '<div aria-hidden="true" title="No" class="blm-geotagged-column no"></div>';
        echo '<span class="screen-reader-text blm-geotagged-column-text">Not geotagged</span>';
    }

    return true;
}// blm_list_post_column

/*
* blm_get_posttypes
* Get custom post types
*/
function blm_get_posttypes()
{
    // Initialize
    global $blm_posttypes;
    $types_exclude = array(
        'page',
        'attachment',
        'revision',
        'nav_menu_item',
    );

    // Get post types
    $post_types             = get_post_types();
    $blm_posttypes_settings = array();
    foreach ($post_types as $t) {
        // Exclude non-custom post types
        if (in_array($t, $types_exclude, true)) {
            continue;
        }

        // Get post type
        $type = get_post_type_object($t);
        if (! $type) {
            continue;
        }

        // Get posts by post type
        $get_posts = new WP_Query();
        $query     = $get_posts->query(
            array(
                'post_type'           => $t,
                'post_status'         => 'publish',
                'posts_per_page'      => 1,
                'ignore_sticky_posts' => true,
                'no_found_rows'       => true,
            )
        );

        // Handle if has no posts
        if (empty($query)) {
            continue;
        }

        // Get custom fields
        $fields = array();
        foreach ($query as $p) {
            // Get custom fields, if any
            $meta = get_post_custom($p->ID);

            if ($meta) {
                $fields = array_keys($meta);
            }

            break;
        }

        // Store if custom fields were found or if default post type
        if ($fields || 'post' === $t) {
            $blm_posttypes[ $t ] = array(
                'type'   => $type->labels->singular_name,
                'fields' => $fields,
            );

            $blm_posttypes_settings[] = 'blm_setting_posttype_' . $t;
            $blm_posttypes_settings[] = 'blm_setting_posttype_publishdate_' . $t;
            $blm_posttypes_settings[] = 'blm_setting_posttype_enddate_' . $t;
            $blm_posttypes_settings[] = 'blm_setting_posttype_expiration_' . $t;
        }
    }

    return $blm_posttypes_settings;
}//blm_get_posttypes
