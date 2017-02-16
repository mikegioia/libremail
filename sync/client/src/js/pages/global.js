/**
 * Global Page Controller
 */
LibreMail.Pages.Global = (function ( Const, Emitter, Components ) {
'use strict';
// Returns a new instance
return function () {
    // Components used
    var Accounts;
    var StatusMessage;
    var Notifications;

    /**
     * Attach events and instantiate Components
     */
    function load () {
        events();
        components();
    }

    function events () {
        Emitter.on( Const.EV.ERROR, error );
        Emitter.on( Const.EV.HEALTH, health );
        Emitter.on( Const.EV.WS_OPEN, online );
        Emitter.on( Const.EV.ACCOUNT, account );
        Emitter.on( Const.EV.WS_CLOSE, offline );
        Emitter.on( Const.EV.NOTIFICATION, notification );
    }

    function components () {
        var main = document.querySelector( 'main' );
        var notifications = document.getElementById( 'notifications' );

        Accounts = new Components.Accounts( main );
        StatusMessage = new Components.StatusMessage( main );
        Notifications = new Components.Notifications( notifications );
    }

    /**
     * The socket connection re-opened.
     */
    function online () {
        StatusMessage.renderOnline();
    }

    /**
     * The socket closed, show an offline message.
     */
    function offline () {
        StatusMessage.renderOffline();
    }

    /**
     * Parse the health report and show a message if something
     * went wrong.
     */
    function health ( data ) {
        var i;
        var error;
        var suggestion;
        var tests = Object.keys( data.tests ).map( function ( k ) {
            return data.tests[ k ];
        });

        // Sort by code first
        tests.sort( function ( a, b ) {
            return ( a.code > b.code)
                ? 1
                : (( b.code > a.code ) ? -1 : 0);
            });

        // Check the response for any error messages
        for ( i in tests ) {
            if ( tests[ i ].status === Const.STATUS.error ) {
                error = Const.LANG.sprintf(
                    Const.LANG.health_error_message,
                    tests[ i ].name,
                    tests[ i ].message );
                suggestion = tests[ i ].suggestion;
                break;
            }
        }

        if ( error ) {
            StatusMessage.renderError( error, suggestion );
        }

        // Check the response for a noAccounts flag
        if ( data.no_accounts === true ) {
            Accounts.render();
        }
        else {
            Accounts.tearDown();
        }
    }

    /**
     * System encountered an error, probably from the sync process.
     */
    function error ( data ) {
        StatusMessage.renderError(
            Const.LANG.sprintf(
                Const.LANG.system_error_message,
                data.message ),
            data.suggestion );
    }

    /**
     * Renders a notification to the main element.
     */
    function notification ( data ) {
        Notifications.insert( data );
    }

    /**
     * Updates the account form with data from the server.
     */
    function account ( data ) {
console.log( 'account update' );
        Accounts.update( data );
    }

    return {
        load: load
    };
}}(
    LibreMail.Const,
    LibreMail.Emitter,
    LibreMail.Components
));