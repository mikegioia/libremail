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
  MSG: {
    HEALTH: '!HEALTH\n',
    RESTART: '!RESTART\n'
  },
  // Events
  EV: {
    STATS: 'stats',
    START: 'start',
    ERROR: 'error',
    HEALTH: 'health',
    WS_OPEN: 'ws_open',
    ACCOUNT: 'account',
    WS_CLOSE: 'ws_close',
    LOG_DATA: 'log_data',
    STOP_UPDATE: 'stop_update',
    START_UPDATE: 'start_update',
    ACCOUNT_INFO: 'account_info',
    NOTIFICATION: 'notification',
    SHOW_FOLDERS: 'show_folders'
  },
  TASK: {
    SAVE_ACCOUNT: 'save_account',
    ACCOUNT_INFO: 'account_info',
    REMOVE_ACCOUNT: 'remove_account'
  },
  // Config loaded from config.json
  CONFIG: {
    WS_URL: '%WEBSOCKET_URL%'
  },
  // Statuses
  STATUS: {
    error: 'error',
    success: 'success'
  },
  // Language used in app
  LANG: {
    sprintf: function () {
      var args = Array.prototype.slice.call(arguments);

      return args.shift().replace(/%s/g, function () {
        return args.shift();
      });
    },
    server_offline_heading: 'Server Offline',
    server_offline_message:
      'There was a problem making a connection to the server. ' +
      'The application is probably offline.',
    error_heading: 'Uh oh...',
    health_error_message: 'An error was found when testing if the %s. %s',
    system_error_message: 'An error was encountered! %s'
  }
};

// Containers
LibreMail.Pages = {};
LibreMail.Components = {};

// Vendor dependencies follow this message!
// --------------------------------------------------------------------
