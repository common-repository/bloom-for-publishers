var $, bloom_meta, bloom_domain;
  
document.addEventListener(
    'DOMContentLoaded',
    function ( event ) {
        // Enable jQuery $ assignment
        try {
            $ = jQuery.noConflict();
        } catch (e) {
            // Error may occur if $ assignment was previously given constant declaration
        }

        blmInit();
    }
);

/**
 * Function: blmInit
 * Load javascript when document is ready
 */
function blmInit()
{

    //Initialize Bloom settings
    bloom_domain = 'https://embed.bloom.li';
    bloom_meta = {
        lat : {
            key : 'lat',
            val : ''
        },
        lng : {
            key : 'lng',
            val : ''
        },
        address : {
            key : 'address',
            val_encoded : '',
            val : ''
        },
        key : {
            key : 'post_key',
            val : ''
        },
        amp : {
            val : false
        }
    };

    //Retrieve metadata settings
    blmGetMetadata();

    //Link listeners
    $(document).on(
        'click touchend', 'blm-link', function (e) {
            e.stopPropagation();
            e.preventDefault();
            blmTrigger(this, 'BP-001-03');
        }
    );

    //Button listeners
    if($('blm-button').length > 0 ) {

        if($('bloom').length === 0 && $('blm-button[data-type="search"]').length > 0 ) {
            $('blm-button[data-type="search"]').remove();
        }

        if(bloom_meta.key.val ) {
            //Add SVG to buttons
            $('blm-button').each(
                function () {
                    button_this = document.createElement('span');
                    button_this.innerHTML = blmGetIcon('geotag');
                    $(this).append(button_this);
                }
            );


            $(document).on(
                'click touchend', 'blm-button', function (e) {
                    e.stopPropagation();
                    e.preventDefault();
                    blmTrigger(this, 'BP-001-02');
                }
            );
        } else if($('blm-button[data-type="map"]').length > 0 ) {
            $('blm-button[data-type="map"]').remove();
        }
    }

    //Key up listener
    document.addEventListener('keyup', blmKeyUp);

}//blmInit

/**
 * Function: blmKeyUp
 * Handle close window request
 */
function blmKeyUp( e )
{

    e = e || window.event;

    //Close requests
    if (e.keyCode === 27 ) {

        if (document.getElementById('blm-map-screen') !== null && document.getElementById('blm-map-screen').getAttribute('data-display') === '1' ) {
            blmMapClose();
        }
    }

}//blmKeyUp

/**
 * Function: blmTrigger
 * Event listener for custom links
 */
function blmTrigger( item, search_code )
{

    //Ignore if no trigger type set
    if (! $(item).attr('data-type') ) {
        return false;
    }

    switch( $(item).attr('data-type') ) {
    //Launch requested feature
    case 'search':
        if(typeof blmSearchLaunch === 'function') {
            blmSearchLaunch(2, search_code);
        }
        break;

    //Launch map
    case 'map':
        blmMapLaunch();
        break;

    default:
        break;

    }

}//blmTrigger

/**
 * Function: blmMapLaunch
 * Launch a map
 */
function blmMapLaunch()
{

    //Handle if map was previously launched
    if (document.getElementById('blm-map-screen') !== null ) {

        //Display map
        document.getElementById('blm-map-screen').setAttribute('data-display', '1');

    } else {

        //Ignore if no article key
        if (! bloom_meta.key.val ) {
            return false;
        }

        //Create and display map
        var bloom_map = document.createElement('div');
        bloom_map.setAttribute('id', 'blm-map-screen');
        bloom_map.setAttribute('data-display', '1');
        var bloom_map_content = document.createElement('div');
        bloom_map_content.setAttribute('id', 'blm-map-screen-content');
        var bloom_map_close = document.createElement('div');
        bloom_map_close.setAttribute('id', 'blm-map-screen-close');
        bloom_map_close.innerHTML = blmGetIcon('times');
        bloom_map_content.appendChild(bloom_map_close);
        bloom_map_frame = document.createElement(bloom_meta.amp ? 'amp-iframe' : 'iframe');
        bloom_map_frame.setAttribute('src', bloom_domain + '/article/map?' + bloom_meta.key.key + '=' + bloom_meta.key.val + '&size=rect&zoom=16&source=BP-007-01');
        bloom_map_frame.setAttribute('title', 'Story Map');
        bloom_map_frame.style.cssText = 'border:none;visibility:visible;width:100% !important;max-width:600px;height:235px;';
        bloom_map_content.appendChild(bloom_map_frame);
        bloom_map.appendChild(bloom_map_content);
        document.getElementsByTagName('body')[0].appendChild(bloom_map);

        //Close map listener
        bloom_map_close.addEventListener('click', blmMapClose);

    }

}//blmMapLaunch

/**
 * Function: blmMapClose
 * Close a map
 */
function blmMapClose()
{

    //Get map screen
    var bloom_map_screen = document.getElementById('blm-map-screen');

    //Check requirements
    if (bloom_map_screen === null ) {
        return false;
    }

    //Close map
    bloom_map_screen.setAttribute('data-display', '0');

}//blmMapClose

/**
 * Function: blmGetMetadata
 * Get the location of the page
 */
function blmGetMetadata()
{
    var p, c;

    //Get metadata properties
    $('meta').each(
        function () {

            p = $(this).attr('property');
            c = $(this).attr('content');

            //Check requirements
            if (! p || ! c ) {
                return true;
            }

            //Store geo metadata
            switch(p){
            case 'geo:latitude':
                bloom_meta.lat.val = c;
                break;

            case 'geo:longitude':
                bloom_meta.lng.val = c;
                break;

            case 'geo:formatted_address':
                bloom_meta.address.val = c;
                bloom_meta.address.val_encoded = encodeURIComponent(c);
                break;

            case 'bloom:key':
                bloom_meta.key.val = c;
                break;

            case 'bloom:amp':
                bloom_meta.amp.val = ('true' === c);
                break;

            default:
                break;

            }

            return true;

        }
    );

    //Handle requirements
    if (! bloom_meta.lat.val || ! bloom_meta.lng.val ) {
        bloom_meta.lat.val = '';
        bloom_meta.lng.val = '';
        bloom_meta.address.val = '';
        return true;
    }

    //Format and add parameters
    bloom_meta.lat.val = encodeURI(bloom_meta.lat.val);
    bloom_meta.lng.val = encodeURI(bloom_meta.lng.val);

}//blmGetMetadata
