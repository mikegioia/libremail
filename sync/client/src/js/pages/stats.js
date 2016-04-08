/**
 * Stats Page Controller
 */
LibreMail.Pages.Stats = (function ( Mustache, Emitter, Const ) {
'use strict';
// Returns a new instance
return function () {
    // Components used
    var Header;
    var Folders;

    /**
     * Attach events and instantiate Components
     */
    function load () {
        events();
        components();
    }

    function events () {
        Emitter.on( Const.EV_STATS, stats );
    }

    function components () {

    }

    /**
     * Emit an event to the components.
     */
    function stats ( data ) {
        
    }

    return {
        load: load
    };
}}( Mustache, LibreMail.Emitter, LibreMail.Const ));