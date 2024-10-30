<?php
if (!isset($blm_setting_map_height_value)) {
    $blm_setting_map_height_default = 300;
    $blm_setting_map_height_value = is_numeric(get_option('blm_setting_map_append_height')) ? get_option('blm_setting_map_append_height') : $blm_setting_map_height_default;
}
?>
<div class="blm-thickbox" id="blm-thickbox-shortcodes">
    <h3>Map Shortcodes</h3>
    <p>Each article geotagged with Bloom automatically comes with static map images and interactive map capabilities that you can display with a simple shortcode. You can copy the code below to paste into any geotagged article page.</p>

    <div class="blm-shortcode">
        <h4>Static Map Image</h4>
        <div class="blm-shortcode-example">
            <a href="https://www.bloom.li/publisher/plugins/?plugin=static-map" title="Static Map Image" target="_blank">Example</a>
        </div>
        <div class="blm-shortcode-code">[bloom type="map" height="<?php echo esc_attr($blm_setting_map_height_value); ?>" zoom="close"]</div>
        <div class="blm-shortcode-options">
            <ul>
                <li>Zoom: "close" (block-level view), "far" (neighborhood-level view)</li>
                <li>Height: Number of pixels (default: 300)</li>
                <li>Width: 100% (Cannot be customized with shortcode)</li>
            </ul>
        </div>
    </div>

    <div class="blm-shortcode">
        <h4>Interactive Map</h4>
        <p>This is best used for posts that have multiple geotagged locations</p>
        <div class="blm-shortcode-example">
            <a href="https://www.bloom.li/publisher/plugins/?plugin=interactive-map" title="Interactive Map" target="_blank">Example</a>
        </div>
        <div class="blm-shortcode-code">[bloom type="interactive" height="<?php echo esc_attr($blm_setting_map_height_value); ?>"]</div>
        <div class="blm-shortcode-options">
            <ul>
                <li>Height: Number of pixels (default: 300)</li>
                <li>Width: 100% (Cannot be customized with shortcode)</li>
            </ul>
        </div>
    </div>
</div>
