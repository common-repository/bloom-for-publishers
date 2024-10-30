var blm_location_json; //Decoded copy of location data
var blm_reminder_has_notified = false; //Whether the user has been notified to geotag
const blm_location_item_limit = 31; //Limit of 1 primary and 30 secondary locations
const blm_location_label_limit = 130; //Number of characters the label is limited to
const blm_location_label_display_limit = 75; //Number of characters the label display is limited to
const blm_location_text_limit = 500;
const blm_location_item_sortable_config = {
    disabled: false,
    items: '.blm-location-item',
    forcePlaceholderSize: true,
    placeholder: 'blm-location-item-placeholder',
    containment: 'parent',
    axis: 'y',
    update: blmLocationIndex
};

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

        // Add user agent
        document.getElementById('blm-ua').value = navigator.userAgent;

        // Initialize container status
        document.getElementById('blm_location_form').setAttribute('data-has-locations', 'false');

        $('.blm-location-label-limit span').attr('data-max', blm_location_label_limit);
        $('.blm-location-text-limit span').attr('data-max', blm_location_text_limit);

        // Initialize the location list
        blmLocationInit();

        // Listener for location input
        $('#blm-location-body').on(
            'keyup', '#blm-location-search-input',
            function (e) {
                if (13 === e.keyCode ) {
                    e.preventDefault();
                    e.stopPropagation();
                    blmGeocode('blm-location-search-input', 'blm-location-search-results');
                    return false;
                }
            }
        );

        // Listeners for location modifications
        $('#blm-location-add').on(
            'click', function () {

                // Check for location item limit
                if(document.getElementById('blm-location-body').querySelectorAll('.blm-location-item').length >= blm_location_item_limit) {
                    alert('You have reached the limit of ' + blm_location_item_limit + ' geotagged locations');
                    return false;
                }

                // Check for unsaved location items
                if(document.getElementById('blm-location-body').querySelectorAll('.blm-location-item[data-layout="open"]').length > 0) {
                    blmLocationItemClose();
                }

                // Create new location item
                blmLocationItemInit();
            }
        );

        $('#blm-location-body').on(
            'click', '.blm-button[data-option="edit"]', function () {
                blmLocationItemOpen($(this).closest('.blm-location-item'));
            }
        );

        $('#blm-location-body').on(
            'click', '.blm-button[data-option="save"], .blm-button[data-option="cancel"]', function () {
                blmLocationItemClose($(this).closest('.blm-location-item'));
            }
        );

        $('#blm-location-body').on(
            'keyup', 'input#blm-label-input, textarea#blm-text-input', function () {
                blmLocationItemSave($(this).closest('.blm-location-item'));
            }
        );

        $('#blm-location-body').on(
            'click', '.blm-button[data-option="delete"]', function () {

                // Confirm deletion
                if(!confirm('Are you sure you want to delete this location?')) {
                    return false;
                }

                // Remove location from data
                var index = $(this).closest('.blm-location-item').attr('data-index');
                blm_location_json.splice(index, 1);

                // Remove location from list
                $(this).closest('.blm-location-item').remove();

                // Re-index locations
                blmLocationIndex();

                // Set new location data
                blmLocationSetData(blm_location_json);

            }
        );

        $('#blm-location-body').on(
            'click', '.blm-button[data-option="components"]', function () {

                // Get location item data
                var index = $(this).closest('.blm-location-item').attr('data-index');

                // Define component names
                var components = {
                    'place':null,
                    'address':null,
                    'premise':null,
                    'neighborhood':null,
                    'postal_code':null,
                    'city':null,
                    'county':null,
                    'state':null,
                    'country':null
                };

                // Get location item component types
                var blm_location_component_types = blmLocationGetComponentTypes(blm_location_json[index].location);

                // Populate components list
                for(c in components){

                    // Get component value
                    components[c] = blmLocationGetComponent(blm_location_component_types, c);

                    // Handle component availability
                    if(components[c]) {

                        //Show component
                        document.getElementById('blm-location-components-item-' + c).style.display = 'block';

                        //Add component data
                        document.getElementById('blm-location-components-item-' + c).querySelector('.blm-location-components-item-label').innerText = blmUnslugify(c);
                        document.getElementById('blm-location-components-item-' + c).querySelector('.blm-location-components-item-value').innerText = components[c];

                    }else{

                        // Hide component
                        document.getElementById('blm-location-components-item-' + c).style.display = 'none';
                    }

                }

            }
        );

        //Implement reminder if Gutenberg editor available
        if (wp && wp.data && wp.data.select('core/editor')) {
            var wpEditor = wp.data.select('core/editor');

            // Launch geotag reminder
            setTimeout(
                function () {
                    blmReminderDisplay();
                },
                1000
            );

            // Add geotag reminder listener
            wp.data.subscribe(
                function () {
                    // Ignore reminder if triggered previously
                    if(blm_reminder_has_notified) {
                        return false;
                    }

                    // Reinitialize wpEditor if becomes unset
                    if ('undefined' === typeof wpEditor || null === wpEditor) {
                        var wpEditor = wp.data.select('core/editor');
                    }

                    // Avoid reminding during auto-save
                    if (wpEditor.isAutosavingPost()) {
                        return true;
                    }

                    // Trigger reminder if saving published post
                    if (wpEditor.isSavingPost() && wpEditor.didPostSaveRequestSucceed() && 'publish' === wpEditor.getEditedPostAttribute('status')) {
                        blmReminderDisplay(true);
                    }
                }
            );

        }

    }
);// Onload

/**
 * Function: blmLocationInit
 * Initialize the existing location items
 */
function blmLocationInit()
{

    // Get location data
    blm_location_json = blmLocationGetData();

    // Append each location item
    for(l in blm_location_json){
        blmLocationItemInit(parseInt(l), blm_location_json[l]);
    }

    // Set location data
    blmLocationSetData(blm_location_json);

    // Initialize sort settings
    $('#blm-location-body').sortable(blm_location_item_sortable_config);
    if($('#blm-location-body').find('.blm-location-item').length > 0) {
        $(".blm-location-item").disableSelection();
    }

}// blmLocationInit

/**
 * Function: blmLocationItemInit
 * Initialize a single location item
 */
function blmLocationItemInit(index, item)
{

    // Get location item HTML
    var location_template = $('#blm-location-templates [data-template="item_container"]').html();
    var location_template_dom = new DOMParser().parseFromString(location_template, 'text/html');
    var location_item = location_template_dom.body.firstChild;

    // Initialize index if needed; Is new location
    if(index === undefined) {

        // Determine index
        index = document.getElementById('blm-location-body').querySelectorAll('.blm-location-item').length;
        if(index < 0) {
            index = 0;
        }

        // Mark as new, unsaved
        location_item.setAttribute('data-saved', 'false');

    }

    // Fill basic data
    location_item.setAttribute('data-layout', 'closed');
    location_item.setAttribute('data-index', index);
    location_item.querySelector('.blm-location-item-index span').innerText = index + 1;

    if(item) {

        // Fill error data
        if(item.error) {
            location_item.setAttribute('data-error', 'true');
        }

        // Fill location-specific data
        if(item.location) {
            var location_is_specific = blmLocationCheckSpecificity(item.location, 'hyperlocal');
            location_item.setAttribute('data-specific', location_is_specific ? 'true' : 'false');

            var location_name_1 = null;
            var location_name_2 = null;

            if(location_is_specific) {
                location_name_1 = blmLocationFormatted(item.location, 1);
                location_name_2 = blmLocationFormatted(item.location, 2);
                location_item.querySelector('.blm-location-item-title-location-main').innerText = location_name_1;
                location_item.querySelector('.blm-location-item-title-location-main').setAttribute('title', location_name_1);
                location_item.querySelector('.blm-location-item-title-location-sub').innerText = location_name_2;
                location_item.querySelector('.blm-location-item-title-location-sub').setAttribute('title', location_name_2);
            }else{
                var location_name_2_parts = [];
                var location_types = blmLocationGetComponentTypes(item.location);
                var location_neighborhood = blmLocationGetComponent(location_types, 'neighborhood');
                var location_postalcode = blmLocationGetComponent(location_types, 'postal_code');
                var location_city = blmLocationGetComponent(location_types, 'city');
                var location_county = blmLocationGetComponent(location_types, 'county');
                var location_state = blmLocationGetComponent(location_types, 'state');

                if(location_neighborhood) {
                    location_name_1 = location_neighborhood;
                    location_name_2_parts.push(location_city, location_state); 
                }else if(location_postalcode) {
                    location_name_1 = location_postalcode;
                    location_name_2_parts.push(location_city, location_state);
                }else if(location_city) {
                    location_name_1 = location_city;
                    location_name_2_parts.push(location_state);
                }else if(location_county) {
                    location_name_1 = location_county;
                    location_name_2_parts.push(location_state);
                }else{
                    location_name_1 = blmLocationFormatted(item.location, 2);
                }

                location_item.querySelector('.blm-location-item-title-location-main').innerText = location_name_1;
                location_item.querySelector('.blm-location-item-title-location-main').setAttribute('title', location_name_1);
                if(location_name_2_parts.length !== 0) {
                    location_item.querySelector('.blm-location-item-title-location-sub').innerText = location_name_2_parts.join(', ');
                    location_item.querySelector('.blm-location-item-title-location-sub').setAttribute('title', location_name_2_parts.join(', '));
                }
            }
        }else{
            location_item.setAttribute('data-location', 'false');
            location_item.querySelector('.blm-location-item-title-location-main').innerText = 'None';
        }

        var item_label_display = item.label;
        if(!item_label_display) {
            item_label_display = 'None';
            location_item.querySelector('.blm-location-item-title-label').setAttribute('data-empty', 'true');
        }

        location_item.querySelector('.blm-location-item-title-label-content').innerText = (item_label_display.length < blm_location_label_display_limit ? item_label_display : item_label_display.substring(0, (blm_location_label_display_limit - 3) + '...'));
        location_item.querySelector('.blm-location-item-title-label-content').setAttribute('title', item.label);

        location_item.querySelector('.blm-location-item-data').setAttribute('value', window.btoa(JSON.stringify(item)));
    }else{
        location_item.querySelector('.blm-location-item-title-location-main').innerText = 'New Location';
    }

    // Append location item
    document.getElementById('blm-location-body').appendChild(location_item);

    // Open if new
    if(!item) {
        setTimeout(
            function () {
                blmLocationItemOpen($('.blm-location-item[data-index="' + index + '"]'));
            }, 100
        );
    }

    // Update container status
    document.getElementById('blm_location_form').setAttribute('data-has-locations', 'true');

}// blmLocationItemInit

/**
 * Function: blmLocationItemOpen
 * Open a location item
 */
function blmLocationItemOpen(item)
{

    // Ignore if already opened
    if(item.attr('data-layout') === 'open') {
        return true;
    }

    // Disable sortable settings
    $('#blm-location-body').sortable('disable');

    // Close any opened items
    $('#blm-location-body .blm-location-item[data-layout="open"]').each(
        function () {
            blmLocationItemClose($(this));
        }
    );

    // Get location item data
    var blm_location_item_data = item.find('.blm-location-item-data').val();
    if(blm_location_item_data) {
        blm_location_item_data = JSON.parse(window.atob(blm_location_item_data));
    }

    // Add body
    item.append($('#blm-location-templates [data-template="item_body"]').html());

    // Handle input
    setTimeout(
        function () {

            // Auto-select location input
            item.find('#blm-location-search-input').focus();

            // Fill input if data available
            if(blm_location_item_data) {

                if(blm_location_item_data.error) {
                    item.find('.blm-location-item-error').html(blm_location_item_data.error);
                    item.find('.blm-location-item-error').append(' <em>You may need to refresh this page after saving a new location to remove this error message.</em>');
                }

                if(blm_location_item_data.label) {
                    if(blm_location_item_data.label) {
                        item.find('#blm-label-input').val(blm_location_item_data.label);
                        item.find('.blm-location-label-limit span[data-max]').text(blm_location_item_data.label.length);
                    }
                    if(blm_location_item_data.text) {
                        item.find('#blm-text-input').val(blm_location_item_data.text);
                        item.find('.blm-location-text-limit span[data-max]').text(blm_location_item_data.text.length);
                    }

                }

                if(blm_location_item_data.location) {
                    item.find('#blm-location-search-input').val(blmLocationFormatted(blm_location_item_data.location));
                }

            }
        }, 100
    );

    // Update location layout
    item.attr('data-layout', 'open');

}// blmLocationItemOpen

/**
 * Function: blmLocationItemSave
 * Save a location item
 */
function blmLocationItemSave(item)
{
    // Hide past confirmation message
    if($('#blm-location-message').length) {
        $('#blm-location-message').remove();
    }

    // Initialize item if not provided
    if(item === undefined) {
        item = $('.blm-location-item[data-layout="open"]');
    }

    // Get current location item data
    var blm_location_item_data = item.find('.blm-location-item-data').val();

    // Decode or initialize data
    if(blm_location_item_data) {
        blm_location_item_data = JSON.parse(window.atob(blm_location_item_data));
    }else{
        blm_location_item_data = {};
    }

    // Get updated location data
    var blm_location_search_result_active = document.querySelector('.blm-location-search-results-item[data-active="true"]');

    // Update location data with selected location
    if(blm_location_search_result_active) {

        // Get location item data
        var blm_location_search_result_data = JSON.parse(window.atob(blm_location_search_result_active.querySelector('input').value));

        // Update location item data
        blm_location_item_data.location = blm_location_search_result_data;
        blm_location_item_data.location.formatted_address = blmLocationFormatted(blm_location_search_result_data);

        // Update location item labels
        var location_is_specific = blmLocationCheckSpecificity(blm_location_search_result_data, 'hyperlocal');
        var location_name_1 = null;
        var location_name_2 = null;
        if(location_is_specific) {
            location_name_1 = blmLocationFormatted(blm_location_search_result_data, 1);
            location_name_2 = blmLocationFormatted(blm_location_search_result_data, 2);
            item.find('.blm-location-item-title-location-main').text(location_name_1).attr('title', location_name_1);
            item.find('.blm-location-item-title-location-sub').text(location_name_2).attr('title', location_name_2);
        }else{
            var location_name_2_parts = [];
            var blm_location_types = blmLocationGetComponentTypes(blm_location_search_result_data);
            var location_neighborhood = blmLocationGetComponent(blm_location_types, 'neighborhood');
            var location_postalcode = blmLocationGetComponent(blm_location_types, 'postal_code');
            var location_city = blmLocationGetComponent(blm_location_types, 'city');
            var location_county = blmLocationGetComponent(blm_location_types, 'county');
            var location_state = blmLocationGetComponent(blm_location_types, 'state');

            if(location_neighborhood) {
                location_name_1 = location_neighborhood;
                location_name_2_parts.push(location_city, location_state);
            }else if(location_postalcode) {
                location_name_1 = location_postalcode;
                location_name_2_parts.push(location_city, location_state);
            }else if(location_city) {
                location_name_1 = location_city;
                location_name_2_parts.push(location_state);
            }else if(location_county) {
                location_name_1 = location_county;
                location_name_2_parts.push(location_state);
            }else{
                location_name_1 = blmLocationFormatted(blm_location_search_result_data, 2);
            }

            item.find('.blm-location-item-title-location-main').text(location_name_1).attr('title', location_name_1);
            if(location_name_2_parts.length !== 0) {
                item.find('.blm-location-item-title-location-sub').text(location_name_2_parts.join(', ')).attr('title', location_name_2_parts.join(', '));
            }
        }

        // Mark location item as valid location
        item.attr('data-location', 'true');

    }else if(!blm_location_item_data.location) {
        item.attr('data-location', 'false');
        item.find('.blm-location-item-title-location-main').text('Unknown location');
    }

    // Get label input
    var blm_location_item_label = item.find('#blm-label-input').val();
    item.find('.blm-location-label-limit span[data-max]').text(blm_location_item_label.length);

    // Trim label input
    if(blm_location_item_label.length > blm_location_label_limit) {
        item.find('.blm-location-label-limit').attr('data-over', 'true');
        blm_location_item_label = blm_location_item_label.substring(0, blm_location_label_limit);
    }else{
        item.find('.blm-location-label-limit').attr('data-over', 'false');
    }

    // Validate label input
    var blm_location_item_label_display = blm_location_item_label;
    if(blm_location_item_label) {
        item.find('.blm-location-item-title-label').attr('data-empty', 'false');
    }else{
        blm_location_item_label_display = 'None';
        item.find('.blm-location-item-title-label').attr('data-empty', 'true');
    }

    // Display label input
    item.find('.blm-location-item-title-label-content').text(blm_location_item_label_display.length < blm_location_label_display_limit ? blm_location_item_label_display : blm_location_item_label_display.substring(0, (blm_location_label_display_limit - 3)) + '...');
    item.find('.blm-location-item-title-label-content').attr('title', blm_location_item_label_display);

    // Save label input
    blm_location_item_data.label = blm_location_item_label;

    // Get text input
    var blm_location_item_text = item.find('#blm-text-input').val();
    item.find('.blm-location-text-limit span[data-max]').text(blm_location_item_text.length);

    // Trim text input
    if(blm_location_item_text.length > blm_location_text_limit) {
        item.find('.blm-location-text-limit').attr('data-over', 'true');
        blm_location_item_text = blm_location_item_text.substring(0, blm_location_text_limit);
    }else{
        item.find('.blm-location-text-limit').attr('data-over', 'false');
    }

    // Save text input
    blm_location_item_data.text = blm_location_item_text;

    // Update location item data
    item.find('.blm-location-item-data').val(window.btoa(JSON.stringify(blm_location_item_data)));

    // Update location data
    blm_location_json[item.index()] = blm_location_item_data;

    // Set first location item as primary
    blm_location_json[item.index()].rank = (item.index() === 0 ? 1 : 2);

    blmLocationSetData(blm_location_json);

    // Mark as saved
    item.attr('data-saved', 'true');

    // Update sort settings
    if($('#blm-location-body').find('.blm-location-item').length > 1) {
        $('#blm-location-body').sortable(blm_location_item_sortable_config);
        $(".blm-location-item").disableSelection();
    }else{
        $('#blm-location-body').sortable('disable');
    }

}// blmLocationItemSave

/**
 * Function: blmLocationItemClose
 * Close a location item
 */
function blmLocationItemClose(item)
{
    // Update sort settings
    if($('#blm-location-body').find('.blm-location-item').length > 1) {
        $('#blm-location-body').sortable(blm_location_item_sortable_config);
        $(".blm-location-item").disableSelection();
    }else{
        $('#blm-location-body').sortable('disable');
    }

    // Initialize item if not provided
    if(item === undefined) {
        item = $('.blm-location-item[data-layout="open"]');
    }

    // Get current location item data
    var blm_location_item_data = item.find('.blm-location-item-data').val();

    // Decode or initialize data
    if(blm_location_item_data) {
        blm_location_item_data = JSON.parse(window.atob(blm_location_item_data));
    }else{
        blm_location_item_data = {};
    }

    // Handle no data or input and coming from 'Done' button click
    if(!blm_location_item_data.location && !blm_location_item_data.label) {
        // Remove location item
        item.remove();

        // Re-index list
        blmLocationIndex();

        return true;
    }

    // Save the location item
    blmLocationItemSave(item);

    // Update location layout
    item.attr('data-layout', 'closed');

    // Remove body
    setTimeout(
        function () {
            item.find('.blm-location-item-body').remove();
        }, 500
    );

}// blmLocationItemClose

/**
 * Function: blmLocationIndex
 * Index the location items
 */
function blmLocationIndex()
{
    // Get all location items
    var blm_location_items = document.getElementById('blm-location-body').querySelectorAll('.blm-location-item');

    // Ignore reindexing if empty list
    if(blm_location_items.length === 0) {
        // Update container status
        document.getElementById('blm_location_form').setAttribute('data-has-locations', 'false');

        return false;
    }

    // Update sort settings
    if(blm_location_items.length > 1) {
        $('#blm-location-body').sortable(blm_location_item_sortable_config);
        $(".blm-location-item").disableSelection();
    }else{
        $('#blm-location-body').sortable('disable');
    }

    // Initialize new JSON data object
    var blm_location_json_new = [];

    // Re-index each location item
    for(i in blm_location_items){

        i = parseInt(i);

        // Ignore non-object elements
        if(typeof blm_location_items[i] !== 'object') {
            continue;
        }

        // Set this item's location data
        blm_location_json_new.push(JSON.parse(window.atob(blm_location_items[i].querySelector('.blm-location-item-data').value)));

        // Set first location item as primary
        blm_location_json_new[i].rank = (i === 0 ? 1 : 2);

        // Re-index element
        blm_location_items[i].querySelector('.blm-location-item-index span').innerText = parseInt(i) + 1;
        blm_location_items[i].setAttribute('data-index', parseInt(i));

    }

    // Set new data
    blm_location_json = blm_location_json_new;
    blmLocationSetData(blm_location_json);

}// blmLocationIndex

/**
 * Function: blmLocationGetData
 * Get and decode the current location data
 */
function blmLocationGetData()
{
    // Get location data
    var blm_location_data = $('#blm-location-data').val();

    // Handle no data
    if(!blm_location_data) {
        return false;
    }

    // Decode location data
    blm_location_data = window.atob(blm_location_data);

    try {
        blm_location_json = JSON.parse(blm_location_data);
    } catch(e) {
        alert('An error occurred with the geotagged locations. You may need to save these again.');
        blm_location_json = [];
    }

    return blm_location_json;
}// blmLocationGetData

/**
 * Function: blmLocationSetData
 * Encode and set the new location data
 */
function blmLocationSetData(data)
{
    // Encode and set new data
    $('#blm-location-data').val(window.btoa(JSON.stringify(data)));

    return true;
}// blmLocationSetData

/**
 * Function: blmUnslugify
 * Unslug the given string and camelcase it
 */
function blmUnslugify(s)
{

    // Unslug
    s = s.replace('_', ' ');

    // Camelcase
    return s.replace(
        /\w\S*/g, function (txt) {
            return txt.charAt(0).toUpperCase() + txt.substring(1).toLowerCase();
        }
    );

}// blmUnslugify

/**
 * Function: blmReminderDisplay
 * Display a reminder to geotag this post
 */
function blmReminderDisplay(saving)
{

    //Determine if reminder is necessary
    var isGeotaggedLocal = $('#blm-location-data').val().length;
    var isGeotaggedBloom = $('#blm-key').val();
    var savedPostStatus = wp.data.select('core/editor').getEditedPostAttribute('status');
    var savedPostContent = wp.data.select('core/editor').getEditedPostAttribute('content');
    if ((true !== saving && 100 > savedPostContent.length) || (saving && 'publish' !== savedPostStatus) || (isGeotaggedLocal && isGeotaggedBloom)) {
        return false;
    }

    //Mark user as notified if post is saving
    if (saving) {
        blm_reminder_has_notified = true;
    }

    // Determine reminder message
    var geotag_message = null;
    if (!isGeotaggedLocal) {
        geotag_message = 'Geotag Reminder: Add a location below to save on Bloom';
    } else if(!isGeotaggedBloom && 'publish' === savedPostStatus) {
        geotag_message = "Geotag Reminder: Refresh this page or click Update to save location on Bloom";
    }

    // Ignore if no message
    if (!geotag_message) {
        return false;
    }

    //Display reminder
    (
        function ( wp ) {
            wp.data.dispatch('core/notices').createNotice(
                'warning',
                geotag_message,
                {
                    isDismissible: true,
                    type: 'snackbar'
                }
            );
        }
    )(window.wp);

}// blmReminderDisplay
