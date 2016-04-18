/**
 * Socket Manager
 */
LibreMail.Socket = (function ( ReconnectingWebSocket, JSON, Const, Emitter ) {
    'use strict';

    var ws = new ReconnectingWebSocket( Const.WS.URL );

    ws.onopen = function () {
        Emitter.fire( Const.EV.WS_OPEN );
    };

    ws.onclose = function () {
        Emitter.fire( Const.EV.WS_CLOSE );
    };

    /**
     * Expects a data object with at least a "type" field. This
     * event type is emitted to the application.
     */
    ws.onmessage = function ( evt ) {
        var data = JSON.parse( evt.data );

        // Check if the type field is present
        if ( ! data.hasOwnProperty( 'type' )
            || Const.EV.hasOwnProperty( data.type ) )
        {
            data.type = Const.EV.ERROR;
        }

        Emitter.fire( data.type, data );
    };

    return ws;
}( ReconnectingWebSocket, JSON, LibreMail.Const, LibreMail.Emitter ));