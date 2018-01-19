jQuery(document).ready(function ($) {
    var string_locator_search_active = false;

    var add_notice = function( message, format ) {
        $(".notices").append( '<div class="notice notice-' + format + '">' + message + '</div>' );
    };

    var reset_search = function() {
        // Reset the progress-bar
        $("#string-locator-search-progress").attr( 'max', 100 ).val('');

        // Reset the notices
        $(".notices").html('');

        // Reset the results table
        $("tbody tr", ".tools_page_string-locator").html('');
    };

    var finalize_string_locator_search = function() {
        var search_finalized = {
            action : 'string-locator-clean',
            nonce : string_locator.search_nonce
        };

        $.post(
            string_locator.ajax_url,
            search_finalized,
            function( response ) {
                $(".string-locator-feedback").hide();
            }
        );
    };

    var perform_string_locator_single_search = function( maxCount, thisCount ) {
        if ( thisCount >= maxCount || ! string_locator_search_active ) {
            $("#string-locator-feedback-text").html( string_locator.saving_results_string );
            string_locator_search_active = false;
            finalize_string_locator_search();
            return false;
        }

        var search_request = {
            action  : 'string-locator-search',
            filenum : thisCount,
            nonce   : string_locator.search_nonce
        };

        $.post(
            string_locator.ajax_url,
            search_request,
            function ( response ) {
                if ( ! response.success ) {
                    add_notice( response.data, 'warning' );
                }

                if ( undefined !== response.data.search ) {
                    $("#string-locator-search-progress").val( response.data.filenum );
                    $("#string-locator-feedback-text").html( response.data.next_file );

                    string_locator_append_result( response.data.search );
                }
                var nextCount = thisCount + 1;
                perform_string_locator_single_search( maxCount, nextCount );
            },
            'json'
        )
    };

    var string_locator_append_result = function( entries ) {
        if ( $(".no-items", ".tools_page_string-locator").is(':visible') ) {
            $(".no-items", ".tools_page_string-locator").hide();
        }

        for ( var i = 0, amount = entries.length; i < amount; i++ ) {
            var entry = entries[ i ];

            if ( undefined !== entry.stringresult ) {
                var builtHTML = '<tr>' +
                    '<td>' + entry.stringresult + '<div class="row-actions"><span class="edit"><a href="' + entry.editurl + '" aria-label="Edit">Edit</a></span></div></td>' +
                    '<td>' + entry.filename + '</td>' +
                    '<td>' + entry.linenum + '</td>' +
                    '</tr>';

                $("tbody", ".tools_page_string-locator").append(builtHTML);
            }
        }
    };


    $("#string-locator-search-form").on( 'submit', function (e) {
        e.preventDefault();
        reset_search();
        $(".string-locator-feedback").show();
        string_locator_search_active = true;

        var directory_request = {
            action    : 'string-locator-get-directory-structure',
            directory : $("#string-locator-search").val(),
            search    : $("#string-locator-string").val(),
            nonce     : string_locator.search_nonce
        };

        $("table.tools_page_string-locator").show();

        $.post(
            string_locator.ajax_url,
            directory_request,
            function ( response ) {
                if ( ! response.success ) {
                    add_notice( response.data, 'alert' );
                    return;
                }
                $("#string-locator-search-progress").attr( 'max', response.data.total ).val( response.data.current );
                perform_string_locator_single_search( response.data.total, 0 );
            },
            'json'
        );
    });
});