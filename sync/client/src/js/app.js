/**
 * --------------------------------------------------------------------
 *                    LibreMail Client Application
 * --------------------------------------------------------------------
 *
 * @author   Mike Gioia <mikegioia@gmail.com>
 * @license  GPLv3
 *
 * This is how the app works:
 *    1. A websocket connection is opened to the stats endpoint and
 *       every 10 seconds or so, stats are sent to this client.
 *    2. When a stats message comes in, it triggers events to the
 *       folders and header components to update their state.
 *
 * This app exposes the following global:
 *    Libremail
 *      .Const         Constants used in application
 *      .Pages         Container for all pages/controllers
 *      .Socket        Manages websocket connection to stats server
 *      .Emitter       All event emitting runs through this instance
 *      .Components    Container for all web components
 *
 * The vendor dependencies used are:
 *    Mustache
 *    ReconnectingWebSocket
 */
var LibreMail = {};

// Constants used in application
LibreMail.Const = {
    // Message types
    'MSG': {
        'RESTART': '!RESTART\n'
    },
    // Events
    'EV': {
        'STATS': 'stats',
        'ERROR': 'error',
        'WS_OPEN': 'ws_open',
        'ACCOUNTS': 'accounts',
        'WS_CLOSE': 'ws_close',
        'LOG_DATA': 'log_data',
        'STOP_UPDATE': 'stop_update',
        'START_UPDATE': 'start_update'
    },
    // @TODO this should be based off config file
    'WS': {
        'URL': 'ws://localhost:9898/stats'
    }
};

// Containers
LibreMail.Pages = {};
LibreMail.Components = {};

// Vendor dependencies follow this message!
// --------------------------------------------------------------------
