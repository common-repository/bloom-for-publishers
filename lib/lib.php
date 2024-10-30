<?php //Library: lib.php

/*
* blm_lib_api_process
* Call the initial API request
*/
function blm_lib_api_process( $method_url, $query, $decode_to_array = false ) {

    // Initialize API domain URL
    $url = 'https://api.bloom.li/' . $method_url;

    // Execute the API call
    $response = wp_safe_remote_get(
        $url,
        array(
            'method' => 'POST',
            'timeout' => apply_filters( 'http_request_timeout', 30, $url ),
            'redirection' => apply_filters( 'http_request_redirection_count', 5, $url ),
            'httpversion' => apply_filters( 'http_request_version', '1.1', $url ),
            'user-agent' => apply_filters( 'http_headers_useragent', 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ), $url ),
            'blocking' => true,
            'body' => $query,
            'compress' => false,
            'decompress' => true,
            'sslverify' => false,
            'stream' => false,
            'limit_response_size' => null,
        )
    );

    // Handle API error
    if ( ! is_array( $response ) || is_wp_error( $response ) ) {
        return wp_json_encode(
            array(
                'code'    => 0,
                'message' => 'An error occurred',
                'data' => $response
            )
        );
    }

    // Decode response
    if ( $response['body'] !== false ) {
        $response['body'] = json_decode( utf8_encode( wp_strip_all_tags( $response['body'] ) ), $decode_to_array );
    }

    //Check successful and return result
    if ( $decode_to_array ) {
        if ( isset( $response['body']['success'] ) && $response['body']['success'] ) {
            return $response['body']['data'];
        }
    } elseif ( isset( $response['body']->success ) && $response['body']->success ) {
        return $response['body']->data;
    }

    return wp_json_encode(
        array(
            'code'    => 0,
            'message' => 'An error occurred',
            'data' => $response
        )
    );
}// blm_lib_api_process

/*
* blm_lib_keyword_strpad
* Pad the number of a keyword's count
*/
function blm_lib_keyword_strpad( $count ) {

    return str_pad( $count, 5, 0, STR_PAD_LEFT );
}// blm_lib_keyword_strpad

/*
* blm_lib_keyword_format
* Update the keyword to an acceptable format
*/
function blm_lib_keyword_format( $keyword ) {

    $result = str_replace( ' ', '-', $keyword );

    return preg_replace( '/[^\w-]/', '', $result );
}// blm_lib_keyword_format

/*
* blm_lib_error
* Error reporting
*/
function blm_lib_error( $type, $data ) {

    // Process Bloom API call
    blm_lib_api_process(
        'info',
        array(
            'app_key'    => get_option( 'blm_setting_bloom_api_key' ),
            'app_user'   => get_option( 'blm_setting_bloom_publisher_key' ),
            'app_action' => 'error_wp_api',
            'data'       => array(
                'type'    => $type,
                'content' => $data,
            )
        )
    );
}// blm_lib_error

/*
* blm_lib_get_version
* Get version of the Bloom for Publishers plugin
*/
function blm_lib_get_version() {
    //Handle if called outside of admin
    if( !function_exists('get_plugin_data') ){
        require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    }

    $plugin_data = get_plugin_data( plugin_dir_path( __DIR__ ) . 'bloom.php' );

    return $plugin_data['Version'];
}// blm_lib_get_version

/*
* blm_lib_convert_hex_rgb
* Convert hexidecimal color code to RGB color code
*/
function blm_lib_convert_hex_rgb( $hex ) {

    if ($hex[0] === '#') {
        $hex = substr($hex, 1);
    }

    if (strlen($hex) === 6) {
        list($r, $g, $b) = array($hex[0].$hex[1], $hex[2].$hex[3], $hex[4].$hex[5]);
    } else if(strlen($hex) === 3) {
        list($r, $g, $b) = array($hex[0].$hex[0], $hex[1].$hex[1], $hex[2].$hex[2]);
    } else {
        return null;
    }

    $r = hexdec($r);
    $g = hexdec($g);
    $b = hexdec($b);

    return array($r, $g, $b);
}// blm_lib_convert_hex_rgb

/*
* blm_lib_convert_rgb_hsl
* Calculate HSL levels from RGB color code
*/
function blm_lib_convert_rgb_hsl( $rgb ) {

    $rgb[0] /= 255;
    $rgb[1] /= 255;
    $rgb[2] /= 255;

    $max = max( $rgb[0], $rgb[1], $rgb[2] );
    $min = min( $rgb[0], $rgb[1], $rgb[2] );

    $l = ( $max + $min ) / 2;
    $d = $max - $min;

    if( $d === 0 ){
        $h = $s = 0;
    } else {
        $s = $d / ( 1 - abs( 2 * $l - 1 ) );

        switch( $max ) {
            case $rgb[0]:
                $h = 60 * fmod( ( ( $rgb[1] - $rgb[2] ) / $d ), 6 );
                if ($rgb[2] > $rgb[1]) {
                    $h += 360;
                }
                break;

            case $rgb[1]:
                $h = 60 * ( ( $rgb[2] - $rgb[0] ) / $d + 2 );
                break;

            case $rgb[2]:
                $h = 60 * ( ( $rgb[0] - $rgb[1] ) / $d + 4 );
                break;
        }
    }

    return array(
        round( $h, 2 ),
        round( $s, 2 ),
        round( $l, 2 )
    );
}// blm_lib_convert_rgb_hsl

/*
* blm_lib_convert_hex_hsl
* Calculate HSL levels from hexidecimal color code
*/
function blm_lib_convert_hex_hsl( $hex ) {
        return blm_lib_convert_rgb_hsl( blm_lib_convert_hex_rgb( $hex ) );
}// blm_lib_convert_hex_hsl
