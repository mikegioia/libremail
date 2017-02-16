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
        Emitter.on( Const.EV.STATS, render );
        Emitter.on( Const.EV.LOG_DATA, logData );
        Emitter.on( Const.EV.WS_CLOSE, offline );
        Emitter.on( Const.EV.ACCOUNT, accountUpdated );
        Emitter.on( Const.EV.STOP_UPDATE, stopUpdate );
        Emitter.on( Const.EV.START_UPDATE, startUpdate );
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
    function render ( _data ) {
        data = _data;

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

    function offline () {
        Header.tearDown();
        Folders.tearDown();
    }

    /**
     * Triggered when a save account task is completed. If the
     * task is successful, then tear down the folders so that
     * they are triggered to be re-rendered.
     */
    function accountUpdated ( data ) {
        if ( data.updated ) {
            Folders.tearDown();
        }
    }

    return {
        load: load
    };
}}(
    LibreMail.Const,
    LibreMail.Emitter,
    LibreMail.Components
));