/**
 * Stats Page Controller
 */
LibreMail.Pages.Stats = (function ( Const, Emitter, Components ) {
'use strict';
// Returns a new instance
return function () {
    // Components used
    var Header;
    var Folders;
    // Private state
    var data = {};
    var account = null;
    var flagStop = false;

    /**
     * Attach events and instantiate Components
     */
    function load () {
        events();
        components();
    }

    function events () {
        Emitter.on( Const.EV_STATS, render );
        Emitter.on( Const.EV_LOG_DATA, logData );
        Emitter.on( Const.EV_WS_CLOSE, offline );
        Emitter.on( Const.EV_STOP_UPDATE, stopUpdate );
        Emitter.on( Const.EV_START_UPDATE, startUpdate );
    }

    function components () {
        Header = new Components.Header(
            document.querySelector( 'header' ));
        Folders = new Components.Folders(
            document.querySelector( 'main' ));
    }

    /**
     * Render the components.
     */
    function render ( data ) {
        if ( data.account && ! flagStop ) {
            Header.render( data );
            Folders.render( data );
        }
    }

    function stopUpdate () {
        flagStop = true;
    }

    function startUpdate () {
        flagStop = false;
    }

    function logData () {
        console.log( data );
    }

    /**
     * The socket closed, show an offline message.
     */
    function offline () {
        alert( 'offline' );
    }

    return {
        load: load
    };
}}(
    LibreMail.Const,
    LibreMail.Emitter,
    LibreMail.Components
));