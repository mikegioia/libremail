/**
 * Event Emitter
 */
LibreMail.Emitter = (function () {
    'use strict';

    // Private array of listeners
    var _listeners = {};

    function on ( type, listener ) {
        if ( typeof _listeners[ type ] == "undefined" ) {
            _listeners[ type ] = [];
        }

        _listeners[ type ].push( listener );
    }

    function fire ( /* event, arg1, arg2, ... */ ) {
        var event = ( arguments.length > 0 )
            ? arguments[ 0 ]
            : {};
        var args = Array.prototype.slice.call( arguments, 1 );

        if ( typeof event == "string" ) {
            event = { type: event };
        }

        if ( ! event.target ) {
            event.target = this;
        }

        if ( ! event.type ) {  // falsy
            throw new Error( "Event object missing 'type' property." );
        }

        args.push( event );

        if ( _listeners[ event.type ] instanceof Array ) {
            var listeners = _listeners[ event.type ];

            for ( var i = 0, len = listeners.length; i < len; i++ ) {
                listeners[ i ].apply( this, args );
            }
        }
    }

    function off ( type, listener ) {
        if ( _listeners[ type ] instanceof Array ) {
            var listeners = _listeners[ type ];

            for ( var i = 0, len = listeners.length; i < len; i++ ) {
                if ( listeners[ i ] === listener ) {
                    listeners.splice( i, 1 );
                    break;
                }
            }
        }
    }

    return {
        on: on,
        off: off,
        fire: fire
    };
}());