var $;

/**
* Function: onload
* Actions to perform on window load
*/
jQuery(
    function () {

        // Enable jQuery $ assignment
        try {
            $ = jQuery.noConflict();
        } catch (e) {
            // Error may occur if $ assignment was previously given constant declaration
        }

        // Tab menu
        $('.nav-tab-wrapper a[data-tab]').on(
            'click',
            function () {

                // Get tab request
                var tab = $(this).attr('data-tab');

                // Check requirements
                if (! $('.blm-settings-section[data-tab="' + tab + '"]').length || $('.blm-settings-section[data-tab="' + tab + '"]').hasClass('active') ) {
                    return false;
                }

                // Deactivate current tab
                $('.nav-tab-wrapper a[data-tab].nav-tab-active').removeClass('nav-tab-active');
                $('.blm-settings-section').removeClass('active');

                // Activate requested tab
                $(this).addClass('nav-tab-active');
                $('.blm-settings-section[data-tab="' + tab + '"]').addClass('active');
                $('#blm-settings-form input[name="blm_setting_tab"]').val(tab);

            }
        );

        // Initialize tabs
        $('.nav-tab-wrapper a[data-tab="' + $('#blm-settings-form input[name="blm_setting_tab"]').val() + '"]').trigger('click');

        // Validate Google Key
        if ($('#blm-location-input').length ) {
            blmGeocode(
                'blm-location-input',
                'blm-location-search-results',
                function ( result ) {

                    // If a geocoding result was found, mark key as valid
                    if (result.length ) {
                        $('#blm-field-note-container').attr('data-code', '1');
                    }

                }
            );

            setTimeout(
                function () {

                    if ($('#blm-field-note-container').attr('data-code') === '3' ) {
                        $('#blm-field-note-container').attr('data-code', '2');
                    }

                },
                3000
            );
        }

        // Listen for map append setting
        $('#blm-setting-map-append-enabled').on(
            'change',
            function () {
                $('#blm-settings-section-map').attr('data-enabled', $(this).val());
            }
        );

        // Listen for icon display setting
        $('select[name="blm_setting_search_icon_display"]').on(
            'change',
            function () {
                $('tr[data-field="search-icon-delay"]').attr('data-displayed', $(this).val());
            }
        );

        // Banner preview
        $('#blm-banner-premade-options').on(
            'change',
            function () {

                if ($(this).find('option[value="' + $(this).val() + '"]').length === 0 ) {
                    return false;
                }

                var img_element = document.createElement('img');
                img_element.setAttribute('src', 'https://assets.bloom.li/plugins/nearby/images/buttons/ad-' + $(this).val() + '.jpg');

                var img_preview       = document.getElementById('blm-banner-premade-preview');
                img_preview.innerHTML = '';
                img_preview.appendChild(img_element);

                var link = document.createElement('blm-link');
                link.setAttribute('data-type', 'search');
                link.appendChild(img_element.cloneNode(false));

                var code_banner = document.getElementById('blm-shortcode-code-banner');
                code_banner.innerHTML = '';
                code_banner.appendChild(document.createTextNode(link.outerHTML));

            }
        );

        // Post type selection
        $('.blm_setting_posttype').on(
            'change',
            function () {
                $(this).parent().parent().attr('data-type', $(this).val());
            }
        );

        //Button listeners
        if($('blm-button').length > 0 ) {
            //Add SVG to buttons
            $('blm-button').each(
                function () {
                    button_this = document.createElement('span');
                    button_this.innerHTML = blmGetIcon('geotag');
                    $(this).append(button_this);
                }
            );
        }
    }
);// Onload
