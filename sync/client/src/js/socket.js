/**
 * Socket Manager
 */
LibreMail.Socket = (function ( ReconnectingWebSocket, JSON, Const, Emitter ) {
    'use strict';

    var ws = new ReconnectingWebSocket( Const.WS_URL );

    ws.onopen = function () {
        Emitter.fire( Const.EV_WS_OPEN );
    };

    ws.onclose = function () {
        Emitter.fire( Const.EV_WS_CLOSE );
    };

    ws.onmessage = function ( evt ) {
        Emitter.fire( Const.EV_STATS, JSON.parse( evt.data ) );
    };

    return ws;
}( ReconnectingWebSocket, JSON, LibreMail.Const, LibreMail.Emitter ));