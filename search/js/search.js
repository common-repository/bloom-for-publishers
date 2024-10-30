var $, bloom_nns, bloom_nns_cookies_enabled, bloom_nns_remote, bloom_nns_remote_delay, bloom_nns_iv_pulse, bloom_amp;  

document.addEventListener(
    'DOMContentLoaded',
    function ( event ) {
        // Enable jQuery $ assignment
        try {
            $ = jQuery.noConflict();
        } catch (e) {
            // Error may occur if $ assignment was previously given constant declaration
        }

        blmSearchInit();
    }
);

/**
 * Function: blmSearchInit
 * Initialize the search feature when document is ready
 */
function blmSearchInit()
{
    //Load Bloom container
    bloom_nns = document.getElementsByTagName('bloom');
    bloom_amp = false;

    //Check requirements
    if (! bloom_nns ) {
        bloom_nns = document.getElementById('blm-s-tag');

        //Handle no Bloom tag
        if (! bloom_nns ) {
            return false;
        }else{
            bloom_amp = true;
        }
    }else{
        bloom_nns = bloom_nns[0];
    }

    //Initialize search settings
    bloom_nns_remote_delay = 1000;
    bloom_nns_iv_pulse = null;
    bloom_nns_cookies_enabled = (bloom_nns.getAttribute('data-cookies-enabled') === '1' ? true : false);
    bloom_nns_remote = {
        params : [],
        synced : false
    };

    blmSearchUrlParamModify(bloom_meta.key.key, bloom_meta.key.val);
    blmSearchUrlParamModify(bloom_meta.lat.key, bloom_meta.lat.val);
    blmSearchUrlParamModify(bloom_meta.lng.key, bloom_meta.lng.val);
    blmSearchUrlParamModify(bloom_meta.address.key, bloom_meta.address.val_encoded);

    //Handle cookie settings

    //Reset page location cookies
    blmSearchDeleteCookie('bloom_user_distance_miles', 'current');
    blmSearchDeleteCookie('bloom_page_location_latlon', 'current');
    blmSearchDeleteCookie('bloom_page_location_address', 'current');

    //Delete other user cookies if not enabled
    if (!bloom_nns_cookies_enabled ) {
        blmSearchDeleteCookie('bloom_user_location_latlon');
        blmSearchDeleteCookie('bloom_user_location_address');
    } else {
        //Set cookies for current page
        blmSearchSetCookie('bloom_page_location_latlon', bloom_meta.lat.val + ',' + bloom_meta.lng.val, 'current');
        blmSearchSetCookie('bloom_page_location_address', bloom_meta.address.val, 'current');

        //Set cookies for user
        blmSearchGetUserDistance();
    }

    //Load plugin
    blmSearchLoad();

    $(document).on(
        'click touchend', '#blm-s-local-intro-button, #blm-i-current', function (e) {
            if (bloom_nns.getAttribute('data-geolocation') == '1' ) {
                blmSearchGeolocation(e);
                $(this).addClass('loading');

                if ($(this).attr('id') === 'blm-i-current' ) {
                    $(this).find('span').attr('data-has-message', 'true').text('Loading...');
                }

            } else {
                e.stopPropagation();
                e.preventDefault();
                blmSearchLaunch(2, 'BP-001-06');
            }
        }
    );

    $(document).on(
        'click touchend', '#blm-s-local-intro-search button', function (e) {

            e.stopPropagation();
            e.preventDefault();

            //Ignore if no input
            if (!$('[name="blm-s-local-intro-field"]').val() ) {
                return false;
            }

            //Determine if custom input (not meta default)
            var bloom_nns_custom_input = null;
            if ($('[name="blm-s-local-intro-field"]').val().trim().toLowerCase() !== bloom_meta.address.val.trim().toLowerCase() ) {
                bloom_nns_custom_input = $('[name="blm-s-local-intro-field"]').val();
            }

            //Submit location request
            $(this).parent().addClass('loading');
            blmSearchUrlParamModify('input', bloom_nns_custom_input);
            blmSearchLoadRemoteContent();
            setTimeout(
                function () {
                    if(parseInt(bloom_nns.getAttribute('data-open')) === 1) {
                        blmSearchLaunch(2, 'BP-001-07');
                    }
                }, bloom_nns_remote_delay
            );

        }
    );

    $(document).on(
        'click touchend', '#blm-s-local-banner-close', function (e) {
            e.stopPropagation();
            e.preventDefault();
            document.getElementById('blm-s-local-banner').removeAttribute('data-type');
            document.getElementById('blm-s-local-banner').setAttribute('data-active', 'false');
        }
    );

    $(document).on(
        'click touchend', '[href*="bloom_search="]', function (e) {
            e.stopPropagation();
            e.preventDefault();
            blmSearchUrlRequest($(this).attr('href'));
        }
    );

    //Key up listener
    document.addEventListener('keyup', blmSearchKeyUp);

    //Scroll listener
    if(bloom_nns.getAttribute('data-scroll-bottom') === false || bloom_nns.getAttribute('data-scroll-bottom') === 'true' ) {
        $(window).on(
            'scroll', function () {

                var bloom_nns_container = document.getElementById('blm-search');

                //Ignore if not initialized, open, or has scroll opened
                if (bloom_nns_container !== null && (bloom_nns_container.getAttribute('data-open') == '1' || bloom_nns_container.getAttribute('data-has-scrollopened') == 'true') ) {
                    return true;
                }

                var bloom_nns_scroll_position = (document.documentElement.scrollTop || document.body.scrollTop ) + window.innerHeight;
                var bloom_nns_scroll_height = document.documentElement.scrollHeight;

                //Ignore if not near bottom of page
                if (bloom_nns_scroll_position < bloom_nns_scroll_height || (bloom_nns_scroll_height - bloom_nns_scroll_position) > 200 ) {
                    return true;
                }

                //Open plugin
                blmSearchLaunch(1, 'BP-001-04');

                //Mark as opened by scrolling
                setTimeout(
                    function () {

                        //Get container if not yet initialized
                        if (bloom_nns_container === null ) {
                            bloom_nns_container = document.getElementById('blm-search');
                        }

                        //Ignore if container cannot be initialized
                        if (bloom_nns_container === null ) {
                            return false;
                        }

                        //Mark as has opened via scroll
                        bloom_nns_container.setAttribute('data-has-scrollopened', 'true');

                    },
                    ( bloom_nns_container ? 0 : bloom_nns_remote_delay )
                );

            }
        );
    }

}//blmSearchInit

/**
 * Function: blmSearchLoad
 * Load the search feature
 */
function blmSearchLoad()
{

    //Add icon
    var bloom_nns_el_icon = document.createElement('div');
    bloom_nns_el_icon.setAttribute('id', 'blm-icon');
    bloom_nns_el_icon.setAttribute('data-has-tooltip', 'true');
    bloom_nns_el_icon.setAttribute('data-pulse', 'true');

    if(bloom_nns.getAttribute('data-icon-display') === 'false' || bloom_nns.getAttribute('data-icon-display-seconds')) {
        bloom_nns_el_icon.setAttribute('data-hidden', 'true');
    }else{
        bloom_nns_el_icon.setAttribute('data-hidden', 'false');
    }

    bloom_nns_el_icon.style.cssText = 'background-color:#' + bloom_nns.getAttribute('data-color') + ' !important';
    var bloom_nns_el_tooltip = document.createElement('div');
    bloom_nns_el_tooltip.setAttribute('id', 'blm-i-tooltip');
    bloom_nns_el_tooltip.style.cssText = 'display: none;';
    bloom_nns_el_tooltip.innerText = 'Search Nearby';
    bloom_nns_el_tooltip.setAttribute('data-selectable', 'false');
    var bloom_nns_el_pulse = document.createElement('div');
    bloom_nns_el_pulse.setAttribute('id', 'blm-i-pulse');
    bloom_nns_el_pulse.style.cssText = 'border-color:#' + bloom_nns.getAttribute('data-color') + ' !important';
    var bloom_nns_el_circle = document.createElement('div');
    bloom_nns_el_circle.setAttribute('id', 'blm-i-circle');
    bloom_nns_el_circle.innerHTML = blmGetIcon('geotag') + blmGetIcon('times');
    bloom_nns_el_icon.appendChild(bloom_nns_el_tooltip);
    bloom_nns_el_icon.appendChild(bloom_nns_el_pulse);
    bloom_nns_el_icon.appendChild(bloom_nns_el_circle);
    bloom_nns.appendChild(bloom_nns_el_icon);
    bloom_nns_el_button_current = document.createElement('div');
    bloom_nns_el_button_current.setAttribute('id', 'blm-i-current');
    bloom_nns_el_button_current_label = document.createElement('span');
    bloom_nns_el_button_current_label.innerText = 'Get current location';
    bloom_nns_el_button_current.innerHTML = blmGetIcon('location_arrow');
    bloom_nns_el_button_current.appendChild(bloom_nns_el_button_current_label);
    bloom_nns.appendChild(bloom_nns_el_button_current);
    bloom_nns.setAttribute('data-open', '0');
    bloom_nns.setAttribute('data-has-opened', '0');

    //Wait until icon is added
    setTimeout(
        function () {

            //Set initial window settings
            bloom_nns.setAttribute('data-loaded', 'true');
            bloom_nns.setAttribute('data-has-opened-before', (blmSearchGetCookie('bloom_search_popup') ? '1' : '0'));

            //Icon listener
            document.getElementById('blm-icon').addEventListener('touchend', blmSearchIconAction, true);
            document.getElementById('blm-icon').addEventListener('click', blmSearchIconAction, true);

            //Handle auto-open if not yet opened
            if (bloom_nns.getAttribute('data-auto-seconds') && !blmSearchGetCookie('bloom_search_popup') ) {
                setTimeout(
                    function () {
                        if (document.getElementById('blm-search') === null ) {
                            blmSearchLaunch(1, 'BP-001-09');
                        }
                    }, parseInt(bloom_nns.getAttribute('data-auto-seconds')) * 1000
                );
            }

        }, 100
    );

    //Handle icon display delay
    if(bloom_nns.getAttribute('data-icon-display') === 'true' && bloom_nns.getAttribute('data-icon-display-seconds')) {
        setTimeout(
            function () {
                document.getElementById('blm-icon').setAttribute('data-hidden', 'false');
            }, parseInt(bloom_nns.getAttribute('data-icon-display-seconds')) * 1000
        );
    }

    //Handle pulse animation
    var bloom_nns_pulse_num = 3;
    var bloom_nns_pulse_duration = 2;
    var bloom_nns_pulse_delay = 30;
    var bloom_nns_pulse_active = bloom_nns_pulse_num * bloom_nns_pulse_duration;    

    setTimeout(
        function () {
            bloom_nns_el_icon.setAttribute('data-pulse', 'false');
        }, bloom_nns_pulse_active * 1000 
    );

    bloom_nns_iv_pulse = setInterval(
        function () {
            bloom_nns_el_icon.setAttribute('data-pulse', 'true');
            setTimeout(
                function () {
                    bloom_nns_el_icon.setAttribute('data-pulse', 'false');
                }, bloom_nns_pulse_active * 1000 
            );
        }, ( bloom_nns_pulse_active + bloom_nns_pulse_delay ) * 1000 
    );

    //Check for auto-open request
    if(!blmSearchUrlRequest($(location).attr('href'))) {
        //Initialize window
        blmSearchInitWindow();
    }

}//blmSearchLoad

/**
 * Function: blmSearchLaunch
 * Launch the search toolbar
 */
function blmSearchLaunch( layout, source )
{
    //Open plugin
    if (document.getElementById('blm-search') !== null ) {

        //Open active plugin based on request
        if (layout || document.getElementById('blm-search').getAttribute('data-open') === '0' ) {
            blmSearchOpen(layout);
        } else {
            blmSearchClose();
        }

    } else {

        //Initialize plugin
        blmSearchInitWindow(layout, source);

    }

    //Stop pulse animation
    if (bloom_nns_iv_pulse ) {
        clearInterval(bloom_nns_iv_pulse);
    }

}//blmSearchLaunch

/**
 * Function: blmSearchInitWindow
 * Initialize search window
 */
function blmSearchInitWindow( layout, source )
{
    //Check requirements
    if (! bloom_nns ) {
        return false;
    }

    //Add geolocation if supported by browser
    blmSearchSupports(
        'geolocation', function ( r ) {
            bloom_nns.setAttribute('data-geolocation', (r === true ? '1' : '0'));
        }
    );

    //Get source of event
    if (source ) {
        blmSearchUrlParamModify('source', source);
    }

    //Get color of plugin
    if (bloom_nns.getAttribute('data-color') ) {
        blmSearchUrlParamModify('color', bloom_nns.getAttribute('data-color'));
    }

    //Generate HTML
    bloom_nns_el_header = document.createElement('div');
    bloom_nns_el_header.setAttribute('id', 'blm-s-h');
    bloom_nns_el_header.style.cssText = 'background-color:#' + bloom_nns.getAttribute('data-color') + ' !important';
    bloom_nns_el_header_title = document.createElement('span');
    bloom_nns_el_header_title.innerHTML = blmGetIcon('bloom') + 'Explore Nearby';
    bloom_nns_el_header_title.setAttribute('data-selectable', 'false');
    bloom_nns_el_header_landscape = document.createElement('div');
    bloom_nns_el_header_landscape.setAttribute('id', 'blm-s-h-landscape');
    bloom_nns_el_header_landscape.style.cssText = 'background-color:#' + bloom_nns.getAttribute('data-color');
    bloom_nns_el_header_landscape_balls = document.createElement('div');
    bloom_nns_el_header_landscape_balls.setAttribute('id', 'blm-s-h-landscape-balls');
    bloom_nns_el_header_landscape_balls.style.cssText = 'background-color:#' + bloom_nns.getAttribute('data-color');
    bloom_nns_el_header_landscape_ball = document.createElement('div');
    bloom_nns_el_header_landscape_ball.setAttribute('class', 'blm-s-h-landscape-ball');
    bloom_nns_el_header.appendChild(bloom_nns_el_header_title);
    bloom_nns_el_header.appendChild(bloom_nns_el_header_landscape);
    bloom_nns_el_header_landscape_balls.appendChild(bloom_nns_el_header_landscape_ball);
    bloom_nns_el_header_landscape_balls.appendChild(bloom_nns_el_header_landscape_ball.cloneNode(true));
    bloom_nns_el_header_landscape_balls.appendChild(bloom_nns_el_header_landscape_ball.cloneNode(true));
    bloom_nns_el_header_landscape_balls.appendChild(bloom_nns_el_header_landscape_ball.cloneNode(true));
    bloom_nns_el_header.appendChild(bloom_nns_el_header_landscape_balls);

    bloom_nns_el_content = document.createElement('div');
    bloom_nns_el_content.setAttribute('class', 'blm-s-c');

    bloom_nns_el_content_local = document.createElement('div');
    bloom_nns_el_content_local.setAttribute('id', 'blm-s-local');
    bloom_nns_el_content_local_banner = document.createElement('div');
    bloom_nns_el_content_local_banner.setAttribute('id', 'blm-s-local-banner');
    bloom_nns_el_content_local_banner_text = document.createElement('div');
    bloom_nns_el_content_local_banner_close = document.createElement('a');
    bloom_nns_el_content_local_banner_close.setAttribute('title', 'Close');
    bloom_nns_el_content_local_banner_close.setAttribute('id', 'blm-s-local-banner-close');
    bloom_nns_el_content_local_banner_close.innerHTML = blmGetIcon('times');
    bloom_nns_el_content_local.appendChild(bloom_nns_el_content_local_banner_text);
    bloom_nns_el_content_local.appendChild(bloom_nns_el_content_local_banner_close);

    bloom_nns_el_content_remote = document.createElement('div');
    bloom_nns_el_content_remote.setAttribute('id', 'blm-s-remote');
    bloom_nns_el_content_remote.innerHTML = blmGetIcon('spinner', 'blm-s-remote-loading');
    bloom_nns_el_content.appendChild(bloom_nns_el_content_remote);

    //Load iframe if requested
    if (typeof layout === 'undefined' || layout === 1) {
        bloom_nns_el_content_local_intro = document.createElement('div');
        bloom_nns_el_content_local_intro.setAttribute('id', 'blm-s-local-intro');
        bloom_nns_el_content_local_intro_title = document.createElement('div');
        bloom_nns_el_content_local_intro_title.setAttribute('id', 'blm-s-local-intro-title');
        bloom_nns_el_content_local_intro_title.innerText = 'Discover news happening near places you care about';
        bloom_nns_el_content_local_intro_title.style.cssText = 'color:#' + bloom_nns.getAttribute('data-color');
        bloom_nns_el_content_local_intro_title.setAttribute('data-selectable', 'false');
        bloom_nns_el_content_local_intro_options = document.createElement('div');
        bloom_nns_el_content_local_intro_options.setAttribute('id', 'blm-s-local-intro-options');
        bloom_nns_el_content_local_intro_options_button = document.createElement('a');
        bloom_nns_el_content_local_intro_options_button.setAttribute('href', 'javascript:;');
        bloom_nns_el_content_local_intro_options_button.setAttribute('id', 'blm-s-local-intro-button');
        bloom_nns_el_content_local_intro_options_button.innerHTML = 'Your location' + blmGetIcon('location_arrow') + blmGetIcon('spinner');
        bloom_nns_el_content_local_intro_options_button.style.cssText = 'background-color:#' + bloom_nns.getAttribute('data-color');
        bloom_nns_el_content_local_intro_options_button.setAttribute('data-selectable', 'false');
        bloom_nns_el_content_local_intro_options_search = document.createElement('div');
        bloom_nns_el_content_local_intro_options_search.setAttribute('id', 'blm-s-local-intro-search');
        bloom_nns_el_content_local_intro_options_search.style.cssText = 'border-color:#' + bloom_nns.getAttribute('data-color');
        bloom_nns_el_content_local_intro_options_search_input = document.createElement('input');
        bloom_nns_el_content_local_intro_options_search_input.setAttribute('type', 'text');
        bloom_nns_el_content_local_intro_options_search_input.setAttribute('name', 'blm-s-local-intro-field');
        bloom_nns_el_content_local_intro_options_search_input.setAttribute('placeholder', 'Type an address or neighborhood');

        bloom_nns_el_content_local_intro_options_search_button = document.createElement('button');
        bloom_nns_el_content_local_intro_options_search_button.setAttribute('type', 'submit');
        bloom_nns_el_content_local_intro_options_search_button.innerHTML = blmGetIcon('search') + blmGetIcon('spinner');
        bloom_nns_el_content_local_intro_options_search_button.style.cssText = 'background-color:#' + bloom_nns.getAttribute('data-color');

        if (bloom_meta.address.val ) {
            bloom_nns_el_content_local_intro_options_search.setAttribute('data-has-meta', 'true');
            bloom_nns_el_content_local_intro_options_search.setAttribute('data-selectable', 'false');
            bloom_nns_el_content_local_intro_options_search_label = document.createElement('span');
            bloom_nns_el_content_local_intro_options_search_label.setAttribute('id', 'blm-s-local-intro-search-icon-label');
            bloom_nns_el_content_local_intro_options_search_label.innerText = 'Default: Location of this page';
            bloom_nns_el_content_local_intro_options_search.innerHTML = blmGetIcon('file');
            bloom_nns_el_content_local_intro_options_search.appendChild(bloom_nns_el_content_local_intro_options_search_label);
            bloom_nns_el_content_local_intro_options_search_input.setAttribute('value', bloom_meta.address.val);
        }

        bloom_nns_el_content_local_intro_options_search.appendChild(bloom_nns_el_content_local_intro_options_search_input);
        bloom_nns_el_content_local_intro_options_search.appendChild(bloom_nns_el_content_local_intro_options_search_button);
        bloom_nns_el_content_local_intro_options.appendChild(bloom_nns_el_content_local_intro_options_button);
        bloom_nns_el_content_local_intro_options.appendChild(bloom_nns_el_content_local_intro_options_search);
        bloom_nns_el_content_local_intro.appendChild(bloom_nns_el_content_local_intro_title);
        bloom_nns_el_content_local_intro.appendChild(bloom_nns_el_content_local_intro_options);
        bloom_nns_el_content_local.appendChild(bloom_nns_el_content_local_intro);
        bloom_nns_el_content.appendChild(bloom_nns_el_content_local);

    }

    bloom_nns_el_footer = document.createElement('div');
    bloom_nns_el_footer.setAttribute('id', 'blm-s-f');
    bloom_nns_el_footer.style.cssText = 'border-color: #' + bloom_nns.getAttribute('data-color');
    bloom_nns_el_footer_attribution = document.createElement('a');
    bloom_nns_el_footer_attribution.setAttribute('href', 'https://www.bloom.li');
    bloom_nns_el_footer_attribution.setAttribute('title', 'What\'s Bloom?');
    bloom_nns_el_footer_attribution.setAttribute('target', '_blank');
    bloom_nns_el_footer_attribution.setAttribute('data-selectable', 'false');
    bloom_nns_el_footer_attribution.innerText = 'Powered by Bloom';
    bloom_nns_el_footer_arrow = document.createElement('span');
    bloom_nns_el_footer_arrow_inner = document.createElement('span');
    bloom_nns_el_footer_arrow_inner.style.cssText = 'background-color: #' + bloom_nns.getAttribute('data-color');
    bloom_nns_el_footer_arrow.appendChild(bloom_nns_el_footer_arrow_inner);
    bloom_nns_el_footer.appendChild(bloom_nns_el_footer_attribution);
    bloom_nns_el_footer.appendChild(bloom_nns_el_footer_arrow);

    var bloom_nns_el_container = document.createElement('div');
    bloom_nns_el_container.setAttribute('id', 'blm-search');
    bloom_nns_el_container.setAttribute('data-color', bloom_nns.getAttribute('data-color'));
    bloom_nns_el_container.setAttribute('data-color-light', bloom_nns.getAttribute('data-color-light'));
    bloom_nns_el_container.appendChild(bloom_nns_el_header);
    bloom_nns_el_container.appendChild(bloom_nns_el_content);
    bloom_nns_el_container.appendChild(bloom_nns_el_footer);
    document.getElementsByTagName('body')[0].appendChild(bloom_nns_el_container);

    //Set layout and open if requested
    if(typeof layout !== 'undefined') {
        setTimeout(
            function () {
                blmSearchOpen(layout);
            }, 100
        );
    }

}//blmSearchInitWindow

/**
 * Function: blmSearchIconAction
 * Icon click listener
 */
function blmSearchIconAction(e)
{
    e.stopPropagation();
    e.preventDefault();

    //If plugin is not opened
    if (document.getElementById('blm-search') === null  
        || !document.getElementById('blm-search').getAttribute('data-open')  
        || document.getElementById('blm-search').getAttribute('data-open') === '0' 
    ) {
        //If has loaded remote layout, open remote
        if (document.getElementById('blm-s-iframe-content') ) {
            blmSearchLaunch(2, 'BP-001-01');

        } else { //Open local layout
            blmSearchLaunch(1);
        }

    } else {
        blmSearchClose();
    }

}//blmSearchIconAction

/**
 * Function: blmSearchKeyUp
 * Handle search close request
 */
function blmSearchKeyUp( e )
{

    //Ignore if plugin not initialized
    if (document.getElementById('blm-search') === null ) {
        return true;
    }

    e = e || window.event;

    //Close requests
    if (e.keyCode === 27 ) {

        if (document.getElementById('blm-search').getAttribute('data-open') === '1' ) {
            blmSearchClose();
        }

        //Input requests
    } else if (document.activeElement.getAttribute('name') === 'blm-s-local-intro-field' ) {

        //If enter key
        if (e.keyCode == 13 ) {

            //Ignore if no input
            if (! $('[name="blm-s-local-intro-field"]').val() ) {
                return false;
            }

            //Submit location request
            document.getElementById('blm-s-local-intro-search').classList.add('loading');
            blmSearchUrlParamModify('input', $('[name="blm-s-local-intro-field"]').val());
            blmSearchLoadRemoteContent();
            setTimeout(
                function () {
                    if(parseInt(bloom_nns.getAttribute('data-open')) === 1) {
                        blmSearchLaunch(2, 'BP-001-07');
                    }
                }, bloom_nns_remote_delay
            );

        } else {

            //Disable page meta address if changed
            setTimeout(
                function () {
                    var bloom_local_intro_field_value = $('[name="blm-s-local-intro-field"]').val().trim();
                    if (bloom_local_intro_field_value && bloom_local_intro_field_value.toLowerCase() === bloom_meta.address.val.trim().toLowerCase() ) {
                        document.getElementById('blm-s-local-intro-search').setAttribute('data-has-meta', 'true');
                    } else {
                        document.getElementById('blm-s-local-intro-search').setAttribute('data-has-meta', 'false');
                    }
                }, 300
            );

        }

    }

}//blmSearchKeyUp

/**
 * Function: blmSearchOpen
 * Open the search plugin
 */
function blmSearchOpen( layout )
{
    //Animation for initial open
    if(parseInt(bloom_nns.getAttribute('data-has-opened')) === 0) {
        setTimeout(
            function () {
                document.getElementById('blm-s-h-landscape-balls').setAttribute('data-formation', '1');
            },
            300
        );
    }

    document.getElementsByTagName('body')[0].setAttribute('data-bloom-open', '1');
    bloom_nns.setAttribute('data-open', '1');
    bloom_nns.setAttribute('data-has-opened', '1');
    document.getElementById('blm-search').setAttribute('data-open', '1');

    //Set cookie
    blmSearchSetCookie('bloom_search_popup', 'opened');

    if (layout ) {
        blmSearchSetLayout(layout);
    }

}//blmSearchOpen

/**
 * Function: blmSearchClose
 * Close the search plugin
 */
function blmSearchClose()
{

    //Update layout
    blmSearchSetLayout(0);

}//blmSearchClose

/**
 * Function: blmSearchGeolocation
 * Get the location of a user via web browser geolocation
 */
function blmSearchGeolocation(e)
{
    //Handle event's direct response
    if (e ) {
        e.stopPropagation();
        e.preventDefault();
    }

    //Return error if browser doesn't support geolocation
    if (! navigator.geolocation ) {
        blmSearchGeolocationResponse({ code : 4 });
        return false;
    }

    //Get current location
    navigator.geolocation.getCurrentPosition(
        function ( position ) {
            //Initialize geocoder
            var bloom_nns_geocoder = new google.maps.Geocoder();
            var bloom_nns_latlng = new google.maps.LatLng(position.coords.latitude, position.coords.longitude);

            //Geocode data
            bloom_nns_geocoder.geocode(
                { 'latLng': bloom_nns_latlng }, function ( results, status ) {
                    //Handle geocode result and respond
                    if (typeof results === 'object' && results.length > 0 ) {
                        blmSearchGeolocationResponse(
                            {
                                code : 5,
                                location : {
                                    latitude : results[0].geometry.location.lat(),
                                    longitude : results[0].geometry.location.lng(),
                                    address : results[0].formatted_address
                                }
                            }
                        );
                    } else {
                        blmSearchGeolocationResponse({ code : 0 });
                    }

                    //Update window based on current layout
                    if (blmSearchGetLayout() == '2' ) {

                        //Reload layout with new remote parameters
                        blmSearchSetLayout(2);

                        //Remove geolocation link
                        var bloom_nns_current = document.getElementById('blm-i-current');
                        if (bloom_nns_current !== null ) {
                            bloom_nns_current.parentNode.removeChild(bloom_nns_current);
                        }

                    } else {

                        //Remove loading message
                        document.getElementById('blm-s-local-intro-button').classList.remove('loading');

                        //If plugin is not already open
                        if(parseInt(bloom_nns.getAttribute('data-open')) === 1) {

                            //Open plugin
                            blmSearchSetLayout(2);

                        }

                    }

                } 
            );
        },
        function ( error ) {
            blmSearchGeolocationResponse({ code : error.code });
        },
        {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 600000
        }
    );

}//blmSearchGeolocation

/**
 * Function: blmSearchGeolocationResponse
 * Handle response from geolocation input
 */
function blmSearchGeolocationResponse( result )
{
    //Display message if error
    if (result.code !== 5 ) {

        //Handle message based on current layout
        if (blmSearchGetLayout() == '1' ) {
            document.getElementById('blm-s-local-banner').setAttribute('data-type', 'error');
            document.getElementById('blm-s-local-banner').setAttribute('data-active', 'true');
            document.getElementById('blm-s-local-banner-text').innerText = 'Could not get your location';
            document.getElementById('blm-s-local-intro-button').classList.remove('loading');
        } else {
            var bloom_nns_current = document.getElementById('blm-i-current');
            bloom_nns_current.setAttribute('data-status', 'error');
            bloom_nns_current.classList.remove('loading');
            bloom_nns_current.querySelectorAll('span')[0].textContent = 'Could not get your location';
            bloom_nns_current.querySelectorAll('span')[0].removeAttribute('data-has-message');
        }

        return true;
    }

    //If location provided
    if (result.location ) {

        if (bloom_nns_cookies_enabled ) {
            //Store location in cookie
            blmSearchSetCookie('bloom_user_location_latlon', result.location.latitude + ',' + result.location.longitude);
            blmSearchSetCookie('bloom_user_location_address', result.location.address);

            //Handle distance between user and current page
            blmSearchGetUserDistance();
        }

        //Update geolocation to remote window
        blmSearchUrlParamModify(
            null,
            [
                'geodata_lat=' + encodeURI(result.location.latitude),
                'geodata_lng=' + encodeURI(result.location.longitude),
                'geodata_address=' + encodeURIComponent(result.location.address)
            ]
        );
    }

    //Append geocode status code to remote window
    blmSearchUrlParamModify('geocode', result.code);

    //Update remote window
    blmSearchLoadRemoteContent();

    //Deactivate local geolocation to hide geolocation link since already retrieved
    bloom_nns.setAttribute('data-geolocation', '0');

    //Remove loading messages
    setTimeout(
        function () {
            if(document.getElementById('blm-i-current')) {
                document.getElementById('blm-i-current').classList.remove('loading');
            }
            document.getElementById('blm-s-local-intro-button').classList.remove('loading');
        }, 300
    );

}//blmSearchGeolocationResponse

/**
 * Function: blmSearchLoadRemoteContent
 * Load remote content
 */
function blmSearchLoadRemoteContent( container )
{
    //Ignore if remote is synced
    if (bloom_nns_remote.synced ) {
        return false;
    }

    //Append Google API key to URL
    if (bloom_nns.getAttribute('data-google-key') ) {
        blmSearchUrlParamModify('google_key', bloom_nns.getAttribute('data-google-key'));
    }

    //Get frame settings
    var bloom_nns_remote_frame = document.getElementById('blm-s-iframe-content');
    var bloom_nns_remote_frame_src = bloom_domain + '/plugin/' + bloom_nns.getAttribute('data-plugin') + '?' + bloom_nns_remote.params.join('&');

    //Display loading icon
    document.getElementById('blm-s-remote-loading').setAttribute('data-active', 'true');

    setTimeout(
        function () {
            document.getElementById('blm-s-remote-loading').setAttribute('data-active', 'false');
        }, bloom_nns_remote_delay
    );

    //Create or update frame
    if (bloom_nns_remote_frame ) {

        //Update frame
        bloom_nns_remote_frame.setAttribute('src', bloom_nns_remote_frame_src);

    } else {

        //Get remote container if needed
        if (! container ) {
            container = document.getElementById('blm-s-remote');
        }

        if (! container ) {
            return false;
        }

        //Create frame
        bloom_nns_remote_frame = document.createElement('iframe');
        bloom_nns_remote_frame.setAttribute('allowtransparency', 'true');
        bloom_nns_remote_frame.setAttribute('id', 'blm-s-iframe-content');
        bloom_nns_remote_frame.setAttribute('title', 'Nearby Search');
        bloom_nns_remote_frame.style.cssText = 'border:none;visibility:visible;width:100%;height:100%;';
        bloom_nns_remote_frame.setAttribute('src', bloom_nns_remote_frame_src);

        container.appendChild(bloom_nns_remote_frame);

    }

    //Set status as synced
    bloom_nns_remote.synced = true;

}//blmSearchLoadRemoteContent

/**
 * Function: blmSearchSetLayout
 * Resize the plugin layout
 */
function blmSearchSetLayout( layout )
{

    //Determine layout
    if (typeof layout === 'undefined' ) {
        if (blmSearchGetLayout() == '1' ) {
            layout = 2;
        } else {
            layout = 1;
        }
    }

    //Load remote content if remote layout (2) requested
    if (layout === 2 ) {
        blmSearchLoadRemoteContent();
    }

    //Set layout
    bloom_nns.setAttribute('data-layout', layout);
    document.getElementById('blm-search').setAttribute('data-layout', layout);

    //Set plugin setting
    bloom_nns.setAttribute('data-open', (layout === 0 ? '0' : '1'));
    document.getElementsByTagName('body')[0].setAttribute('data-bloom-open', (layout === 0 ? '0' : '1'));
    document.getElementById('blm-search').setAttribute('data-open', (layout === 0 ? '0' : '1'));

}//blmSearchSetLayout

/**
 * Function: blmSearchGetLayout
 * Get the plugin layout
 */
function blmSearchGetLayout()
{

    //Check requirements
    if (! document.getElementById('blm-search') ) {
        return null;
    }

    return document.getElementById('blm-search').getAttribute('data-layout');

}//blmSearchGetLayout

/**
 * Function: blmSearchUrlRequest
 * Perform a request provided with the given URL
 */
function blmSearchUrlRequest(url)
{

    //Handle requirements
    if (!url || url.indexOf('?') === -1 || url.indexOf('bloom_search=') === -1 ) {
        return null;
    }

    //Get URL parameters
    url_params = url.split('?');

    //Parse URL parameters
    url_params_list = blmSearchUrlParamParse(url_params[1]);

    //Check requirements
    if (!url_params_list.bloom_search ) {
        return null;
    }

    //Perform requested action
    switch(url_params_list.bloom_search ) {

    case 'prompt':
        blmSearchLaunch(1, 'BP-001-05');
        break;

    case 'open':
        blmSearchLaunch(2, 'BP-001-05');
        break;

    case 'nearby':
        blmSearchLaunch(1, 'BP-001-05');

        //Allow for plugin to display
        setTimeout(
            function () {

                //If plugin is not opened or remote layout not loaded
                if (document.getElementById('blm-search') === null || document.getElementById('blm-search').getAttribute('data-open') === '0' || document.getElementById('blm-s-iframe-content') === null) {
                    $('#blm-s-local-intro-button').trigger('click');
                }else{
                    $('#blm-i-current').trigger('click');
                }

            }, 300
        );

        break;

    }

    return true;

}//blmSearchUrlRequest

/**
 * Function: blmSearchUrlParamParse
 * Parse URL parameters
 */
function blmSearchUrlParamParse(query)
{

    var bloom_nns_query_vars = query.split('&');
    var bloom_nns_query_string = {};

    for (var i = 0; i < bloom_nns_query_vars.length; i++) {
        var bloom_nns_query_pair = bloom_nns_query_vars[i].split('=');
        var bloom_nns_query_key = decodeURIComponent(bloom_nns_query_pair[0]);
        var bloom_nns_query_value = decodeURIComponent(bloom_nns_query_pair[1]);

        if (typeof bloom_nns_query_string[bloom_nns_query_key] === 'undefined' ) {
            bloom_nns_query_string[bloom_nns_query_key] = decodeURIComponent(bloom_nns_query_value);
        } else if (typeof bloom_nns_query_string[bloom_nns_query_key] === 'string' ) {
            bloom_nns_query_string[bloom_nns_query_key] = [bloom_nns_query_string[bloom_nns_query_key], decodeURIComponent(bloom_nns_query_value)];
        } else {
            bloom_nns_query_string[bloom_nns_query_key].push(decodeURIComponent(bloom_nns_query_value));
        }
    }

    return bloom_nns_query_string;

}//blmSearchUrlParamParse

/**
 * Function: blmSearchUrlParamModify
 * Update the given parameter with the given value
 */
function blmSearchUrlParamModify(k, v)
{

    //Update URL parameter
    if (!v ) {
        if (!bloom_nns_remote.params[k] ) {
            return false;
        }
        delete bloom_nns_remote.params[k];
    }else if (typeof v !== 'function' && typeof v !== 'object' ) {
        if (bloom_nns_remote.params[k] ) {
            bloom_nns_remote.params[k] = v;
        }else{
            bloom_nns_remote.params.push(k + '=' + v);
        }
    }else if (v.constructor === Array ) {
        bloom_nns_remote.params = bloom_nns_remote.params.concat(v);
    }else{
        return false;
    }

    //Update URL sync
    bloom_nns_remote.synced = false;

    return true;

}//blmSearchUrlParamModify

/**
 * Function: blmSearchSupports
 * Check if the browser supports a function
 */
function blmSearchSupports( type, callback )
{

    //Get browser
    var bloom_nns_browser = navigator.userAgent.match(/(opera|chrome|safari|firefox|msie|trident(?=\/))\/?\s*(\d+)/i) || [];

    //Check if Chrome and client is not https
    var bloom_nns_browser_chromestrict = false;
    if (bloom_nns_browser[1] === 'Chrome' && bloom_nns_browser[2] > 49 && $(location).attr('protocol') !== 'https:' ) {
        bloom_nns_browser_chromestrict = true;
    }

    //If geolocation is not supported
    if (type === 'geolocation' && ( typeof google === 'undefined' || typeof navigator.geolocation === 'undefined' || bloom_nns_browser_chromestrict ) ) {
        return callback(false);
    }

    return callback(true);

}//blmSearchSupports

/**
 * Function: blmSearchGetUserDistance
 * Get the distance between the user and current page
 */
function blmSearchGetUserDistance()
{

    //Get user location
    var bloom_nns_user_location_latlon = blmSearchGetCookie('bloom_user_location_latlon');

    //Validate page and user location
    if (!bloom_nns_user_location_latlon || !bloom_meta.lat.val || !bloom_meta.lng.val) {
        return false;
    }

    //Decode user location
    bloom_nns_user_location_latlon = bloom_nns_user_location_latlon.split(',');

    //Convert degrees to radians first
    var pi = Math.PI;
    var a1 = parseFloat(bloom_nns_user_location_latlon[0]) * (pi / 180);
    var b1 = parseFloat(bloom_nns_user_location_latlon[1]) * (pi / 180);
    var a2 = bloom_meta.lat.val * (pi / 180);
    var b2 = bloom_meta.lng.val * (pi / 180);

    if (a1 === a2 && b1 === b2 ) {
        return 0;
    }

    //Calculate distance between latitude and longitude degrees
    var radians = Math.acos(Math.cos(a1) * Math.cos(b1) * Math.cos(a2) * Math.cos(b2) + Math.cos(a1) * Math.sin(b1) * Math.cos(a2) * Math.sin(b2) + Math.sin(a1) * Math.sin(a2));

    //Convert radians to kilometers
    var distance_kilometers = radians * 6378;
    var distance_miles = distance_kilometers * 0.621371;

    //Store distance in cookie
    blmSearchSetCookie('bloom_user_distance_miles', distance_miles.toFixed(4), 'current');

}//blmSearchGetUserDistance

/**
 * Function: blmSearchSetCookie
 * Set a cookie
 */
function blmSearchSetCookie( name, value, path_type)
{
    //Determine expiration date
    var bloom_nns_cookie_date = new Date();
    bloom_nns_cookie_date.setTime(bloom_nns_cookie_date.getTime() + ( 30 * 24 * 60 * 60 * 1000 ));

    //Determine path URL
    var path_url = '/';
    if (typeof path_type !== 'undefined' && path_type === 'current' ) {
        path_url = window.location.href.split(window.location.hostname)[1];
    }

    //Set cookie
    document.cookie = name + "=" + value + ";expires=" + bloom_nns_cookie_date.toUTCString() + ";path=" + path_url;

}//blmSearchSetCookie

/**
 * Function: blmSearchGetCookie
 * Get the requested cookie
 */
function blmSearchGetCookie( name )
{

    var bloom_nns_cookie_name = name + "=";
    var bloom_nns_cookie = decodeURIComponent(document.cookie).split(';');

    for( var i = 0; i < bloom_nns_cookie.length; i++ ) {
        var bloom_nns_cookie_part = bloom_nns_cookie[i].trimStart();
        if (bloom_nns_cookie_part.indexOf(bloom_nns_cookie_name) == 0 ) {
            return bloom_nns_cookie_part.substring(bloom_nns_cookie_name.length, bloom_nns_cookie_part.length);
        }
    }

    return false;

}//blmSearchGetCookie

/**
 * Function: blmSearchDeleteCookie
 * Delete a cookie
 */
function blmSearchDeleteCookie( name, path_type)
{

    //Determine path URL
    var path_url = '/';
    if (typeof path_type !== 'undefined' && path_type === 'current' ) {
        path_url = window.location.href.split(window.location.hostname)[1];
    }

    //Set cookie with old expiration date
    document.cookie = name + "= ;expires= Thu, 01 Jan 1970 00:00:00 GMT;path=" + path_url;

}//blmSearchDeleteCookie
