// Initialize Google geocoder
var blmGeocoder = null;
var blmGeocoding = false;
if (typeof google  !== 'undefined' ) {
    blmGeocoder = new google.maps.Geocoder();
}

/**
 * Function: blmGeocode
 * Geocode an input
 */
function blmGeocode(blmLocationField, blmLocationResults, blmCallback)
{

    // Check requirements
    if (blmGeocoding || ! blmLocationField || ! blmLocationResults ) {
        return false;
    }

    // Initialize
    blmLocationField   = document.getElementById(blmLocationField);
    blmLocationResults = document.getElementById(blmLocationResults);
    blmGeocoding = true;

    // Get address json results
    blmGeocoder.geocode(
        { 'address': blmLocationField.value },
        function ( blmGeocoderResults, blmGeocoderStatus ) {

            blmLocationResultsList = document.getElementById('blm-location-search-results-list');
            blmLocationResultsList.innerHTML = '';
            blmLocationResultsRetrieved = false;

            if (blmGeocoderStatus === google.maps.GeocoderStatus.OK ) {
                // Calculate results length
                var blmResultsLimit = 5;

                // Iterate through results
                if (blmGeocoderResults.length > 0 ) {
                    var blmResultsCount = 0;
                    var blmIndex = 0;
                    // Confirm that the next json result is defined and blmResultsCount under limit
                    while ( typeof blmGeocoderResults[ blmIndex ] !== 'undefined' && blmResultsCount < blmResultsLimit ) {
                        // Confirm that the result meets specificity requirements
                        if (blmLocationCheckSpecificity(blmGeocoderResults[ blmIndex ], 'regional')) {
                            blmGeocoderResults[ blmIndex ].geometry = {
                                location: {
                                    lat: blmGeocoderResults[ blmIndex ].geometry.location.lat(),
                                    lng: blmGeocoderResults[ blmIndex ].geometry.location.lng()
                                }
                            };

                            // Add result item to list
                            var li_data = document.createElement('input');
                            li_data.setAttribute('type', 'hidden');
                            li_data.setAttribute('id', 'blm-result-select-' + blmIndex);
                            li_data.value = btoa(JSON.stringify(blmGeocoderResults[ blmIndex ]));

                            var li_radio = document.createElement('div');
                            li_radio.setAttribute('class', 'blm-location-search-results-item-radio');

                            var li_text = document.createElement('div');
                            li_text.setAttribute('class', 'blm-location-search-results-item-text');
                            li_text.appendChild(document.createTextNode(blmGeocoderResults[ blmIndex ].formatted_address));

                            var li = document.createElement('div');
                            li.setAttribute('class', 'blm-location-search-results-item');
                            li.setAttribute('onClick', 'blmSelectInput(' + blmIndex + ')');
                            li.appendChild(li_radio);
                            li.appendChild(li_text);
                            li.appendChild(li_data);
                            blmLocationResultsList.appendChild(li);

                            // Increment count
                            blmResultsCount++;
                        }

                        blmLocationResultsRetrieved = true;

                        // Increment iteration
                        blmIndex++;
                    }
                }else{
                    blmLocationResultsRetrieved = true;
                }
            }else{
                blmLocationResultsRetrieved = true;
            }

            // Show results
            blmLocationResults.setAttribute('data-display', 1);

            //Handle results once retrieved
            var blmLocationResultsProcessed = false;
            while (blmLocationResultsProcessed === false ) {

                //Wait until results retrieved
                if (blmLocationResultsRetrieved === false ) {
                    continue;
                }

                // Check if results are found
                if (blmGeocoderResults === null ) {
                    blmLocationResults.setAttribute('data-error', 'true');
                    blmLocationResultsList.innerHTML = '<p>The geocoder could not process the location provided. Please check your internet connection and that your Google API Key in Bloom\'s plugin settings is valid.</p>';
                } else if (! blmLocationResultsList.firstChild ) {
                    blmLocationResults.setAttribute('data-error', 'true');
                    blmLocationResultsList.innerHTML = '<p>No specific location found. Please try to include more details.</p>';
                } else {
                    blmLocationResults.setAttribute('data-error', 'false');
                }

                // Handle callback
                if (blmCallback) {
                    blmCallback(blmGeocoderResults);
                }

                blmLocationResultsProcessed = true;
                blmGeocoding = false;

            }

        }
    );

}// blmGeocode

/**
 * Function: blmSelectInput
 * Select a location search result
 */
function blmSelectInput( blmResultSelectId )
{
    // Deselect previously active location
    var blmResultActive = document.querySelector('.blm-location-search-results-item[data-active="true"]');
    if(blmResultActive) {
        blmResultActive.setAttribute('data-active', 'false');
    }

    // Select location
    var blmResultData = document.getElementById('blm-result-select-' + blmResultSelectId);
    blmResultData.parentNode.setAttribute('data-active', 'true');

    // Save the location item with the selected location
    blmLocationItemSave();

}// blmSelectInput

/**
 * blmLocationGetComponentTypes
 * Get the given locations component types
 */
function blmLocationGetComponentTypes(blmLocation)
{

    // Check requirements
    if(!blmLocation.address_components) {
        return false;
    }

    var blmLocationComponents = blmLocation.address_components;
    var blmComponentNames = null;

    //Organize components by type
    var blmLocationComponentsTypes = {};
    for(c in blmLocationComponents) {
        if(blmLocationComponents[c].types[0] == 'political') {
            blmLocationComponents[c].types.shift();
        }

        blmLocationComponentsTypes[blmLocationComponents[c].types[0]] = blmLocationComponents[c].long_name;
        blmLocationComponentsTypes[blmLocationComponents[c].types[0] + '_short'] = blmLocationComponents[c].short_name;
    }

    return blmLocationComponentsTypes;

}//blmLocationGetComponentTypes

/**
 * Function: blmLocationGetComponent
 * Determine if given location is of given component
 */
function blmLocationGetComponent( blmLocationComponentsTypes, blmLocationComponent, blmLocationComponentFormat )
{
    //Return best value for requested component
    switch ( blmLocationComponent ) {
    case 'address':

        if(blmLocationComponentsTypes.street_number && blmLocationComponentsTypes.route) {
            return blmLocationComponentsTypes.street_number + ' ' + blmLocationComponentsTypes.route;
        }

        //Ignore if address does not have city, county, or postal code
        if(!blmLocationGetComponent(blmLocationComponentsTypes, 'city') 
            && !blmLocationGetComponent(blmLocationComponentsTypes, 'county') 
            && !blmLocationGetComponent(blmLocationComponentsTypes, 'postal_code')
        ) {
            return false;
        }

        blmComponentNames = {
            'route': blmLocationComponentsTypes.route,
            'intersection': blmLocationComponentsTypes.intersection
        };

            break;

    case 'premise':

        blmComponentNames = {
            'premise': blmLocationComponentsTypes.premise
        };

            break;

    case 'place':

        blmComponentNames = {
            'point_of_interest': blmLocationComponentsTypes.point_of_interest,
            'natural_feature': blmLocationComponentsTypes.natural_feature,
            'park': blmLocationComponentsTypes.park,
            'airport': blmLocationComponentsTypes.airport,
            'premise': blmLocationComponentsTypes.premise,
            'establishment': blmLocationComponentsTypes.establishment
        };

            break;

    case 'neighborhood':

        blmComponentNames = {
            'neighborhood': blmLocationComponentsTypes.neighborhood,
            'natural_feature': blmLocationComponentsTypes.natural_feature
        };

            break;

    case 'postal_code':

        blmComponentNames = {
            'postal_code': blmLocationComponentsTypes.postal_code
        };

            break;

    case 'city':

        if(blmLocationComponentsTypes.locality && blmLocationComponentsTypes.locality !== 'New York') {
            return blmLocationComponentsTypes.locality;
        }

        blmComponentNames = {
            'sublocality': blmLocationComponentsTypes.sublocality,
            'sublocality_level_1': blmLocationComponentsTypes.sublocality_level_1,
            'sublocality_level_2': blmLocationComponentsTypes.sublocality_level_2,
            'sublocality_level_3': blmLocationComponentsTypes.sublocality_level_3,
            'sublocality_level_4': blmLocationComponentsTypes.sublocality_level_4,
            'sublocality_level_5': blmLocationComponentsTypes.sublocality_level_5,
            'locality': blmLocationComponentsTypes.locality,
            'postal_town': blmLocationComponentsTypes.postal_town,
            'colloquial_area': blmLocationComponentsTypes.colloquial_area
        };

            break;

    case 'county':

        blmComponentNames = {
            'administrative_area_level_2': blmLocationComponentsTypes.administrative_area_level_2
        };

            break;

    case 'state':

        if(blmLocationComponentFormat === 'short') {

            blmComponentNames = {
                'administrative_area_level_1': blmLocationComponentsTypes.administrative_area_level_1_short
            };

        }else{

            blmComponentNames = {
                'administrative_area_level_1': blmLocationComponentsTypes.administrative_area_level_1
            };

        }

            break;

    case 'country':

        blmComponentNames = {
            'country': blmLocationComponentsTypes.country
        };

            break;

    default:

            return false;

    }

    // Return first populated property in component array
    return Object.values(blmComponentNames).filter(
        function (el) {
            return el != null;
        }
    )[0];

}//blmLocationGetComponent

/**
 * Function: blmLocationCheckSpecificity
 * Determine if given location is specific
 */
function blmLocationCheckSpecificity(location, specificity, types)
{

    // Get location component types
    if(typeof types === 'undefined') {
        var blmLocationComponentTypesGathered = blmLocationGetComponentTypes(location);
    }else{
        var blmLocationComponentTypesGathered = types;
    }

    //When types have been gathered
    var typesRetrieved = false;
    while(!typesRetrieved){
        //Process specificity check if types have not been gathered
        if(typeof blmLocationComponentTypesGathered !== 'undefined') {
            typesRetrieved = true;

            // Determine if location has specific components
            switch(specificity) {
            case 'regional':
                if(blmLocationGetComponent(blmLocationComponentTypesGathered, 'neighborhood')
                    || blmLocationGetComponent(blmLocationComponentTypesGathered, 'postal_code')
                    || blmLocationGetComponent(blmLocationComponentTypesGathered, 'city')
                    || blmLocationGetComponent(blmLocationComponentTypesGathered, 'county')
                ) {
                    return true;
                }

            case 'hyperlocal':
                if(blmLocationGetComponent(blmLocationComponentTypesGathered, 'address')
                    || blmLocationGetComponent(blmLocationComponentTypesGathered, 'place')
                    || blmLocationGetComponent(blmLocationComponentTypesGathered, 'premise')
                ) {
                    return true;
                }
                
            default:
                return false;
            }
        }
    }
}//blmLocationCheckSpecificity

/**
 * Function: blmLocationFormatted
 * Get the requested formatted string of the given location
 */
function blmLocationFormatted(location, specificity)
{

    // Get location component types
    var blmLocationComponentTypes = blmLocationGetComponentTypes(location);

    //Get location specificity
    var blmLocationIsSpecific = blmLocationCheckSpecificity(location, 'hyperlocal', blmLocationComponentTypes);

    // Initialize
    var blmLocationPartOne = [];
    var blmLocationPartTwo = [];

    // Get address-based formatted name
    if(specificity !== 2 && blmLocationIsSpecific) {

        var name = blmLocationGetComponent(blmLocationComponentTypes, 'place');

        if(name) {
            blmLocationPartOne.push(name);
        }

        var address = null;

        if(blmLocationComponentTypes.intersection) {
            address = blmLocationComponentTypes.intersection;
        }

        if(!address) {
            address = blmLocationGetComponent(blmLocationComponentTypes, 'address');
        }

        if(address) {
            blmLocationPartOne.push(address);
        }

    }

    // Get region-based formatted name
    if(specificity !== 1 || blmLocationPartOne.length === 0) {

        var neighborhood = blmLocationGetComponent(blmLocationComponentTypes, 'neighborhood');
        if(neighborhood) {
            blmLocationPartTwo.push(neighborhood);
        }

        var city = blmLocationGetComponent(blmLocationComponentTypes, 'city');
        if(city) {
            blmLocationPartTwo.push(city);
        }

        if(!blmLocationIsSpecific && blmLocationComponentTypes.postal_code) {
            blmLocationPartTwo.unshift(blmLocationComponentTypes.postal_code);
        }

        var state = blmLocationGetComponent(blmLocationComponentTypes, 'state', (specificity === 0 || specificity === undefined ? 'short' : 'long'));
        if(state) {
            blmLocationPartTwo.push(state);
        }

    }

    var blmLocationPartOneJoined = '';
    if(blmLocationPartOne.length > 0) {
        blmLocationPartOneJoined = blmLocationPartOne.join(', ');
    }

    var blmLocationPartTwoJoined = '';
    if(blmLocationPartTwo.length > 0) {
        blmLocationPartTwoJoined = blmLocationPartTwo.join(', ');
    }
    
    //Fallback on formatted address if no values provided
    if(!blmLocationPartOneJoined && !blmLocationPartTwoJoined) {
        if(location.formatted_address) {
            return location.formatted_address
        }else{
            return '';
        }
    }

    if(blmLocationPartOneJoined && blmLocationPartTwoJoined) {
        blmLocationPartOneJoined += ', ';
    }

    return (blmLocationPartOneJoined + blmLocationPartTwoJoined).trim();

}// blmLocationFormatted
