jQuery( document ).ready( function () {

    jQuery( '.cpw-cryptocurrency-table-options input[name="cpw_options[mode]"]' ).on( 'change', function () {
        jQuery( '.cpw-cryptocurrency-table-options .mode' ).addClass( 'hide' );
        if ( jQuery( this ).prop( 'checked' ) ) {
            jQuery( '.cpw-cryptocurrency-table-options .mode-' + jQuery( this ).val() ).removeClass( 'hide' );
        }
    } );

    jQuery( '.cpw-cryptocurrency-table-options .add-more-button' ).on( 'click', function ( e ) {
        var elem = jQuery( this ).closest( '.forminp' ).find( '.address-item' ).first().clone( true );
        elem.find( 'input' ).val( '' );
        jQuery( this ).before( elem );
        return false;
    } );

    jQuery( '.cpw-cryptocurrency-table-options .remove-button' ).on( 'click', function ( e ) {
        if ( jQuery( this ).closest( '.forminp' ).find( '.address-item' ).length > 1 ) {
            jQuery( this ).closest( '.address-item' ).remove();
        }
        return false;
    } );

    var validMpk = function ( mpk ) {
        var mpkStart = mpk.substring( 0, 5 );

        if ( mpkStart === 'xpub6' || mpkStart === 'ypub6' || mpkStart === 'zpub6' ) {
            if ( mpk.length === 111 ) {
                return true;
            }
        }

        return false;
    }

    var getMpk = function () {
        return jQuery( '[name="cpw_options[hd_mpk]"]' ).val().trim();
    }

    var updateSampleText = function ( text ) {
        jQuery( 'input[name="cpw_options[hd_mpk_sample_addresses][]"]' ).each( function () {
            jQuery( this ).val( text );
        } );
    }

    var generateMpkAddresses = function ( cryptoId ) {
        let mpk = getMpk();

        var hdMode = '0';

        if ( !validMpk( mpk ) ) {
            updateSampleText( cpwAdminData.enter_valid_mpk_string );
            return;
        }

        console.log( 'hdMode: ' + hdMode );

        jQuery.ajax( {
            type: "POST",
            url: "admin-ajax.php",
            data:
                { action: 'cpw_firstmpkaddress',
                    mpk: mpk,
                    cryptoId: cryptoId,
                    hdMode: hdMode
                },
            beforeSend: function () {
                updateSampleText( cpwAdminData.generate_hd_addresses_string );

                jQuery( '[name="cpw_options[hd_mpk_sample_addresses][]"]' ).removeClass( 'flash-red' );
                jQuery( '[name="cpw_options[hd_mpk_sample_addresses][]"]' ).removeClass( 'flash-green' );
                jQuery( '[name="cpw_options[hd_mpk_sample_addresses][]"]' ).addClass( 'flash-yellow' );
            }
        } ).fail( function ( response ) {
            jQuery( '[name="cpw_options[hd_mpk_sample_addresses][]"]' ).removeClass( 'flash-yellow' );
            if ( response.status === 0 ) {
                return;
            }
            updateSampleText( cpwAdminData.addresses_creation_failed_string );

            jQuery( '[name="cpw_options[hd_mpk_sample_addresses][]"]' ).addClass( 'flash-red' );

        } ).done( function ( responseJson ) {
            jQuery( '[name="cpw_options[hd_mpk_sample_addresses][]"]' ).removeClass( 'flash-yellow' );
            jQuery( '[name="cpw_options[hd_mpk_sample_addresses][]"]' ).addClass( 'flash-green' );
            addresses = JSON.parse( responseJson );

            if ( addresses[0] === cpwAdminData.entered_segwit_mpk_string ) {
                updateSampleText( '' );
                jQuery( '[name="cpw_options[hd_mpk_sample_addresses][]"]' ).eq( 0 ).val( addresses[0] );
                if ( jQuery( '[name="cpw_options[hd_mpk_sample_addresses][]"]' ).parent().children().length === 2 ) {
                    jQuery( '[name="cpw_options[hd_mpk_sample_addresses][]"]' ).parent().append( addresses[1] );
                }
            } else {
                jQuery( '[name="cpw_options[hd_mpk_sample_addresses][]"]' ).eq( 0 ).val( addresses[0] );
                jQuery( '[name="cpw_options[hd_mpk_sample_addresses][]"]' ).eq( 1 ).val( addresses[1] );
                jQuery( '[name="cpw_options[hd_mpk_sample_addresses][]"]' ).eq( 2 ).val( addresses[2] );
            }
        } ); //close jQuery.ajax(
    }

    jQuery( '[name="cpw_options[hd_mpk]"]' ).on( 'change', function () {
        generateMpkAddresses( jQuery( '[name="cryptoId"]' ).val() );
    } );

    jQuery( '[name="cpw_options[addresses][]"]' ).on( 'change', function () {

        var masks = JSON.parse(jQuery( '[name="validMasks"]' ).val());
        var address = jQuery(this).val();

        if (!Array.isArray(masks)) {
            return true;
        }

        var valid = false;

        masks.forEach(function (item) {
            if (address.match(new RegExp(item.substring(1, item.length - 1), 'ig'))) {
                valid = true;
            }
        });

        if (!valid) {
            jQuery(this).closest('.address-item').addClass('address-error');
        } else {
            jQuery(this).closest('.address-item').removeClass('address-error');
        }
    } );

} );