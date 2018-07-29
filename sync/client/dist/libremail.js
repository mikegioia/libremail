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
    // @TODO this should be based off config file
    WS: {
        URL: 'ws://localhost:9898/stats'
    },
    // Statuses
    STATUS: {
        error: 'error',
        success: 'success'
    },
    // Language used in app
    LANG: {
        sprintf: function () {
            var args = Array.prototype.slice.call( arguments );
            return args.shift().replace( /%s/g, function () {
                return args.shift();
            });
        },
        server_offline_heading: "Server Offline",
        server_offline_message:
            "There was a problem making a connection to the server. " +
            "The application is probably offline.",
        error_heading: "Uh oh...",
        health_error_message: "An error was found when testing if the %s. %s",
        system_error_message: "An error was encountered! %s"
    }
};

// Containers
LibreMail.Pages = {};
LibreMail.Components = {};

// Vendor dependencies follow this message!
// --------------------------------------------------------------------
;
// MIT License:
//
// Copyright (c) 2010-2012, Joe Walnes
//
// Permission is hereby granted, free of charge, to any person obtaining a copy
// of this software and associated documentation files (the "Software"), to deal
// in the Software without restriction, including without limitation the rights
// to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
// copies of the Software, and to permit persons to whom the Software is
// furnished to do so, subject to the following conditions:
//
// The above copyright notice and this permission notice shall be included in
// all copies or substantial portions of the Software.
//
// THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
// IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
// FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
// AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
// LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
// OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
// THE SOFTWARE.

/**
 * This behaves like a WebSocket in every way, except if it fails to connect,
 * or it gets disconnected, it will repeatedly poll until it successfully connects
 * again.
 *
 * It is API compatible, so when you have:
 *   ws = new WebSocket('ws://....');
 * you can replace with:
 *   ws = new ReconnectingWebSocket('ws://....');
 *
 * The event stream will typically look like:
 *  onconnecting
 *  onopen
 *  onmessage
 *  onmessage
 *  onclose // lost connection
 *  onconnecting
 *  onopen  // sometime later...
 *  onmessage
 *  onmessage
 *  etc...
 *
 * It is API compatible with the standard WebSocket API, apart from the following members:
 *
 * - `bufferedAmount`
 * - `extensions`
 * - `binaryType`
 *
 * Latest version: https://github.com/joewalnes/reconnecting-websocket/
 * - Joe Walnes
 *
 * Syntax
 * ======
 * var socket = new ReconnectingWebSocket(url, protocols, options);
 *
 * Parameters
 * ==========
 * url - The url you are connecting to.
 * protocols - Optional string or array of protocols.
 * options - See below
 *
 * Options
 * =======
 * Options can either be passed upon instantiation or set after instantiation:
 *
 * var socket = new ReconnectingWebSocket(url, null, { debug: true, reconnectInterval: 4000 });
 *
 * or
 *
 * var socket = new ReconnectingWebSocket(url);
 * socket.debug = true;
 * socket.reconnectInterval = 4000;
 *
 * debug
 * - Whether this instance should log debug messages. Accepts true or false. Default: false.
 *
 * automaticOpen
 * - Whether or not the websocket should attempt to connect immediately upon instantiation. The socket can be manually opened or closed at any time using ws.open() and ws.close().
 *
 * reconnectInterval
 * - The number of milliseconds to delay before attempting to reconnect. Accepts integer. Default: 1000.
 *
 * maxReconnectInterval
 * - The maximum number of milliseconds to delay a reconnection attempt. Accepts integer. Default: 30000.
 *
 * reconnectDecay
 * - The rate of increase of the reconnect delay. Allows reconnect attempts to back off when problems persist. Accepts integer or float. Default: 1.5.
 *
 * timeoutInterval
 * - The maximum time in milliseconds to wait for a connection to succeed before closing and retrying. Accepts integer. Default: 2000.
 *
 */
(function (global, factory) {
    if (typeof define === 'function' && define.amd) {
        define([], factory);
    } else if (typeof module !== 'undefined' && module.exports){
        module.exports = factory();
    } else {
        global.ReconnectingWebSocket = factory();
    }
})(this, function () {

    if (!('WebSocket' in window)) {
        return;
    }

    function ReconnectingWebSocket(url, protocols, options) {

        // Default settings
        var settings = {

            /** Whether this instance should log debug messages. */
            debug: false,

            /** Whether or not the websocket should attempt to connect immediately upon instantiation. */
            automaticOpen: true,

            /** The number of milliseconds to delay before attempting to reconnect. */
            reconnectInterval: 1000,
            /** The maximum number of milliseconds to delay a reconnection attempt. */
            maxReconnectInterval: 30000,
            /** The rate of increase of the reconnect delay. Allows reconnect attempts to back off when problems persist. */
            reconnectDecay: 1.5,

            /** The maximum time in milliseconds to wait for a connection to succeed before closing and retrying. */
            timeoutInterval: 2000,

            /** The maximum number of reconnection attempts to make. Unlimited if null. */
            maxReconnectAttempts: null,

            /** The binary type, possible values 'blob' or 'arraybuffer', default 'blob'. */
            binaryType: 'blob'
        }
        if (!options) { options = {}; }

        // Overwrite and define settings with options if they exist.
        for (var key in settings) {
            if (typeof options[key] !== 'undefined') {
                this[key] = options[key];
            } else {
                this[key] = settings[key];
            }
        }

        // These should be treated as read-only properties

        /** The URL as resolved by the constructor. This is always an absolute URL. Read only. */
        this.url = url;

        /** The number of attempted reconnects since starting, or the last successful connection. Read only. */
        this.reconnectAttempts = 0;

        /**
         * The current state of the connection.
         * Can be one of: WebSocket.CONNECTING, WebSocket.OPEN, WebSocket.CLOSING, WebSocket.CLOSED
         * Read only.
         */
        this.readyState = WebSocket.CONNECTING;

        /**
         * A string indicating the name of the sub-protocol the server selected; this will be one of
         * the strings specified in the protocols parameter when creating the WebSocket object.
         * Read only.
         */
        this.protocol = null;

        // Private state variables

        var self = this;
        var ws;
        var forcedClose = false;
        var timedOut = false;
        var eventTarget = document.createElement('div');

        // Wire up "on*" properties as event handlers

        eventTarget.addEventListener('open',       function(event) { self.onopen(event); });
        eventTarget.addEventListener('close',      function(event) { self.onclose(event); });
        eventTarget.addEventListener('connecting', function(event) { self.onconnecting(event); });
        eventTarget.addEventListener('message',    function(event) { self.onmessage(event); });
        eventTarget.addEventListener('error',      function(event) { self.onerror(event); });

        // Expose the API required by EventTarget

        this.addEventListener = eventTarget.addEventListener.bind(eventTarget);
        this.removeEventListener = eventTarget.removeEventListener.bind(eventTarget);
        this.dispatchEvent = eventTarget.dispatchEvent.bind(eventTarget);

        /**
         * This function generates an event that is compatible with standard
         * compliant browsers and IE9 - IE11
         *
         * This will prevent the error:
         * Object doesn't support this action
         *
         * http://stackoverflow.com/questions/19345392/why-arent-my-parameters-getting-passed-through-to-a-dispatched-event/19345563#19345563
         * @param s String The name that the event should use
         * @param args Object an optional object that the event will use
         */
        function generateEvent(s, args) {
            var evt = document.createEvent("CustomEvent");
            evt.initCustomEvent(s, false, false, args);
            return evt;
        };

        this.open = function (reconnectAttempt) {
            ws = new WebSocket(self.url, protocols || []);
            ws.binaryType = this.binaryType;

            if (reconnectAttempt) {
                if (this.maxReconnectAttempts && this.reconnectAttempts > this.maxReconnectAttempts) {
                    return;
                }
            } else {
                eventTarget.dispatchEvent(generateEvent('connecting'));
                this.reconnectAttempts = 0;
            }

            if (self.debug || ReconnectingWebSocket.debugAll) {
                console.debug('ReconnectingWebSocket', 'attempt-connect', self.url);
            }

            var localWs = ws;
            var timeout = setTimeout(function() {
                if (self.debug || ReconnectingWebSocket.debugAll) {
                    console.debug('ReconnectingWebSocket', 'connection-timeout', self.url);
                }
                timedOut = true;
                localWs.close();
                timedOut = false;
            }, self.timeoutInterval);

            ws.onopen = function(event) {
                clearTimeout(timeout);
                if (self.debug || ReconnectingWebSocket.debugAll) {
                    console.debug('ReconnectingWebSocket', 'onopen', self.url);
                }
                self.protocol = ws.protocol;
                self.readyState = WebSocket.OPEN;
                self.reconnectAttempts = 0;
                var e = generateEvent('open');
                e.isReconnect = reconnectAttempt;
                reconnectAttempt = false;
                eventTarget.dispatchEvent(e);
            };

            ws.onclose = function(event) {
                clearTimeout(timeout);
                ws = null;
                if (forcedClose) {
                    self.readyState = WebSocket.CLOSED;
                    eventTarget.dispatchEvent(generateEvent('close'));
                } else {
                    self.readyState = WebSocket.CONNECTING;
                    var e = generateEvent('connecting');
                    e.code = event.code;
                    e.reason = event.reason;
                    e.wasClean = event.wasClean;
                    eventTarget.dispatchEvent(e);
                    if (!reconnectAttempt && !timedOut) {
                        if (self.debug || ReconnectingWebSocket.debugAll) {
                            console.debug('ReconnectingWebSocket', 'onclose', self.url);
                        }
                        eventTarget.dispatchEvent(generateEvent('close'));
                    }

                    var timeout = self.reconnectInterval * Math.pow(self.reconnectDecay, self.reconnectAttempts);
                    setTimeout(function() {
                        self.reconnectAttempts++;
                        self.open(true);
                    }, timeout > self.maxReconnectInterval ? self.maxReconnectInterval : timeout);
                }
            };
            ws.onmessage = function(event) {
                if (self.debug || ReconnectingWebSocket.debugAll) {
                    console.debug('ReconnectingWebSocket', 'onmessage', self.url, event.data);
                }
                var e = generateEvent('message');
                e.data = event.data;
                eventTarget.dispatchEvent(e);
            };
            ws.onerror = function(event) {
                if (self.debug || ReconnectingWebSocket.debugAll) {
                    console.debug('ReconnectingWebSocket', 'onerror', self.url, event);
                }
                eventTarget.dispatchEvent(generateEvent('error'));
            };
        }

        // Whether or not to create a websocket upon instantiation
        if (this.automaticOpen == true) {
            this.open(false);
        }

        /**
         * Transmits data to the server over the WebSocket connection.
         *
         * @param data a text string, ArrayBuffer or Blob to send to the server.
         */
        this.send = function(data) {
            if (ws) {
                if (self.debug || ReconnectingWebSocket.debugAll) {
                    console.debug('ReconnectingWebSocket', 'send', self.url, data);
                }
                return ws.send(data);
            } else {
                throw 'INVALID_STATE_ERR : Pausing to reconnect websocket';
            }
        };

        /**
         * Closes the WebSocket connection or connection attempt, if any.
         * If the connection is already CLOSED, this method does nothing.
         */
        this.close = function(code, reason) {
            // Default CLOSE_NORMAL code
            if (typeof code == 'undefined') {
                code = 1000;
            }
            forcedClose = true;
            if (ws) {
                ws.close(code, reason);
            }
        };

        /**
         * Additional public API method to refresh the connection if still open (close, re-open).
         * For example, if the app suspects bad data / missed heart beats, it can try to refresh.
         */
        this.refresh = function() {
            if (ws) {
                ws.close();
            }
        };
    }

    /**
     * An event listener to be called when the WebSocket connection's readyState changes to OPEN;
     * this indicates that the connection is ready to send and receive data.
     */
    ReconnectingWebSocket.prototype.onopen = function(event) {};
    /** An event listener to be called when the WebSocket connection's readyState changes to CLOSED. */
    ReconnectingWebSocket.prototype.onclose = function(event) {};
    /** An event listener to be called when a connection begins being attempted. */
    ReconnectingWebSocket.prototype.onconnecting = function(event) {};
    /** An event listener to be called when a message is received from the server. */
    ReconnectingWebSocket.prototype.onmessage = function(event) {};
    /** An event listener to be called when an error occurs. */
    ReconnectingWebSocket.prototype.onerror = function(event) {};

    /**
     * Whether all instances of ReconnectingWebSocket should log debug messages.
     * Setting this to true is the equivalent of setting all instances of ReconnectingWebSocket.debug to true.
     */
    ReconnectingWebSocket.debugAll = false;

    ReconnectingWebSocket.CONNECTING = WebSocket.CONNECTING;
    ReconnectingWebSocket.OPEN = WebSocket.OPEN;
    ReconnectingWebSocket.CLOSING = WebSocket.CLOSING;
    ReconnectingWebSocket.CLOSED = WebSocket.CLOSED;

    return ReconnectingWebSocket;
});;
/*!
 * mustache.js - Logic-less {{mustache}} templates with JavaScript
 * http://github.com/janl/mustache.js
 * MIT License
 */

/*global define: false Mustache: true*/

(function defineMustache (global, factory) {
  if (typeof exports === 'object' && exports && typeof exports.nodeName !== 'string') {
    factory(exports); // CommonJS
  } else if (typeof define === 'function' && define.amd) {
    define(['exports'], factory); // AMD
  } else {
    global.Mustache = {};
    factory(global.Mustache); // script, wsh, asp
  }
}(this, function mustacheFactory (mustache) {

  var objectToString = Object.prototype.toString;
  var isArray = Array.isArray || function isArrayPolyfill (object) {
    return objectToString.call(object) === '[object Array]';
  };

  function isFunction (object) {
    return typeof object === 'function';
  }

  /**
   * More correct typeof string handling array
   * which normally returns typeof 'object'
   */
  function typeStr (obj) {
    return isArray(obj) ? 'array' : typeof obj;
  }

  function escapeRegExp (string) {
    return string.replace(/[\-\[\]{}()*+?.,\\\^$|#\s]/g, '\\$&');
  }

  /**
   * Null safe way of checking whether or not an object,
   * including its prototype, has a given property
   */
  function hasProperty (obj, propName) {
    return obj != null && typeof obj === 'object' && (propName in obj);
  }

  // Workaround for https://issues.apache.org/jira/browse/COUCHDB-577
  // See https://github.com/janl/mustache.js/issues/189
  var regExpTest = RegExp.prototype.test;
  function testRegExp (re, string) {
    return regExpTest.call(re, string);
  }

  var nonSpaceRe = /\S/;
  function isWhitespace (string) {
    return !testRegExp(nonSpaceRe, string);
  }

  var entityMap = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;',
    '/': '&#x2F;',
    '`': '&#x60;',
    '=': '&#x3D;'
  };

  function escapeHtml (string) {
    return String(string).replace(/[&<>"'`=\/]/g, function fromEntityMap (s) {
      return entityMap[s];
    });
  }

  var whiteRe = /\s*/;
  var spaceRe = /\s+/;
  var equalsRe = /\s*=/;
  var curlyRe = /\s*\}/;
  var tagRe = /#|\^|\/|>|\{|&|=|!/;

  /**
   * Breaks up the given `template` string into a tree of tokens. If the `tags`
   * argument is given here it must be an array with two string values: the
   * opening and closing tags used in the template (e.g. [ "<%", "%>" ]). Of
   * course, the default is to use mustaches (i.e. mustache.tags).
   *
   * A token is an array with at least 4 elements. The first element is the
   * mustache symbol that was used inside the tag, e.g. "#" or "&". If the tag
   * did not contain a symbol (i.e. {{myValue}}) this element is "name". For
   * all text that appears outside a symbol this element is "text".
   *
   * The second element of a token is its "value". For mustache tags this is
   * whatever else was inside the tag besides the opening symbol. For text tokens
   * this is the text itself.
   *
   * The third and fourth elements of the token are the start and end indices,
   * respectively, of the token in the original template.
   *
   * Tokens that are the root node of a subtree contain two more elements: 1) an
   * array of tokens in the subtree and 2) the index in the original template at
   * which the closing tag for that section begins.
   */
  function parseTemplate (template, tags) {
    if (!template)
      return [];

    var sections = [];     // Stack to hold section tokens
    var tokens = [];       // Buffer to hold the tokens
    var spaces = [];       // Indices of whitespace tokens on the current line
    var hasTag = false;    // Is there a {{tag}} on the current line?
    var nonSpace = false;  // Is there a non-space char on the current line?

    // Strips all whitespace tokens array for the current line
    // if there was a {{#tag}} on it and otherwise only space.
    function stripSpace () {
      if (hasTag && !nonSpace) {
        while (spaces.length)
          delete tokens[spaces.pop()];
      } else {
        spaces = [];
      }

      hasTag = false;
      nonSpace = false;
    }

    var openingTagRe, closingTagRe, closingCurlyRe;
    function compileTags (tagsToCompile) {
      if (typeof tagsToCompile === 'string')
        tagsToCompile = tagsToCompile.split(spaceRe, 2);

      if (!isArray(tagsToCompile) || tagsToCompile.length !== 2)
        throw new Error('Invalid tags: ' + tagsToCompile);

      openingTagRe = new RegExp(escapeRegExp(tagsToCompile[0]) + '\\s*');
      closingTagRe = new RegExp('\\s*' + escapeRegExp(tagsToCompile[1]));
      closingCurlyRe = new RegExp('\\s*' + escapeRegExp('}' + tagsToCompile[1]));
    }

    compileTags(tags || mustache.tags);

    var scanner = new Scanner(template);

    var start, type, value, chr, token, openSection;
    while (!scanner.eos()) {
      start = scanner.pos;

      // Match any text between tags.
      value = scanner.scanUntil(openingTagRe);

      if (value) {
        for (var i = 0, valueLength = value.length; i < valueLength; ++i) {
          chr = value.charAt(i);

          if (isWhitespace(chr)) {
            spaces.push(tokens.length);
          } else {
            nonSpace = true;
          }

          tokens.push([ 'text', chr, start, start + 1 ]);
          start += 1;

          // Check for whitespace on the current line.
          if (chr === '\n')
            stripSpace();
        }
      }

      // Match the opening tag.
      if (!scanner.scan(openingTagRe))
        break;

      hasTag = true;

      // Get the tag type.
      type = scanner.scan(tagRe) || 'name';
      scanner.scan(whiteRe);

      // Get the tag value.
      if (type === '=') {
        value = scanner.scanUntil(equalsRe);
        scanner.scan(equalsRe);
        scanner.scanUntil(closingTagRe);
      } else if (type === '{') {
        value = scanner.scanUntil(closingCurlyRe);
        scanner.scan(curlyRe);
        scanner.scanUntil(closingTagRe);
        type = '&';
      } else {
        value = scanner.scanUntil(closingTagRe);
      }

      // Match the closing tag.
      if (!scanner.scan(closingTagRe))
        throw new Error('Unclosed tag at ' + scanner.pos);

      token = [ type, value, start, scanner.pos ];
      tokens.push(token);

      if (type === '#' || type === '^') {
        sections.push(token);
      } else if (type === '/') {
        // Check section nesting.
        openSection = sections.pop();

        if (!openSection)
          throw new Error('Unopened section "' + value + '" at ' + start);

        if (openSection[1] !== value)
          throw new Error('Unclosed section "' + openSection[1] + '" at ' + start);
      } else if (type === 'name' || type === '{' || type === '&') {
        nonSpace = true;
      } else if (type === '=') {
        // Set the tags for the next time around.
        compileTags(value);
      }
    }

    // Make sure there are no open sections when we're done.
    openSection = sections.pop();

    if (openSection)
      throw new Error('Unclosed section "' + openSection[1] + '" at ' + scanner.pos);

    return nestTokens(squashTokens(tokens));
  }

  /**
   * Combines the values of consecutive text tokens in the given `tokens` array
   * to a single token.
   */
  function squashTokens (tokens) {
    var squashedTokens = [];

    var token, lastToken;
    for (var i = 0, numTokens = tokens.length; i < numTokens; ++i) {
      token = tokens[i];

      if (token) {
        if (token[0] === 'text' && lastToken && lastToken[0] === 'text') {
          lastToken[1] += token[1];
          lastToken[3] = token[3];
        } else {
          squashedTokens.push(token);
          lastToken = token;
        }
      }
    }

    return squashedTokens;
  }

  /**
   * Forms the given array of `tokens` into a nested tree structure where
   * tokens that represent a section have two additional items: 1) an array of
   * all tokens that appear in that section and 2) the index in the original
   * template that represents the end of that section.
   */
  function nestTokens (tokens) {
    var nestedTokens = [];
    var collector = nestedTokens;
    var sections = [];

    var token, section;
    for (var i = 0, numTokens = tokens.length; i < numTokens; ++i) {
      token = tokens[i];

      switch (token[0]) {
        case '#':
        case '^':
          collector.push(token);
          sections.push(token);
          collector = token[4] = [];
          break;
        case '/':
          section = sections.pop();
          section[5] = token[2];
          collector = sections.length > 0 ? sections[sections.length - 1][4] : nestedTokens;
          break;
        default:
          collector.push(token);
      }
    }

    return nestedTokens;
  }

  /**
   * A simple string scanner that is used by the template parser to find
   * tokens in template strings.
   */
  function Scanner (string) {
    this.string = string;
    this.tail = string;
    this.pos = 0;
  }

  /**
   * Returns `true` if the tail is empty (end of string).
   */
  Scanner.prototype.eos = function eos () {
    return this.tail === '';
  };

  /**
   * Tries to match the given regular expression at the current position.
   * Returns the matched text if it can match, the empty string otherwise.
   */
  Scanner.prototype.scan = function scan (re) {
    var match = this.tail.match(re);

    if (!match || match.index !== 0)
      return '';

    var string = match[0];

    this.tail = this.tail.substring(string.length);
    this.pos += string.length;

    return string;
  };

  /**
   * Skips all text until the given regular expression can be matched. Returns
   * the skipped string, which is the entire tail if no match can be made.
   */
  Scanner.prototype.scanUntil = function scanUntil (re) {
    var index = this.tail.search(re), match;

    switch (index) {
      case -1:
        match = this.tail;
        this.tail = '';
        break;
      case 0:
        match = '';
        break;
      default:
        match = this.tail.substring(0, index);
        this.tail = this.tail.substring(index);
    }

    this.pos += match.length;

    return match;
  };

  /**
   * Represents a rendering context by wrapping a view object and
   * maintaining a reference to the parent context.
   */
  function Context (view, parentContext) {
    this.view = view;
    this.cache = { '.': this.view };
    this.parent = parentContext;
  }

  /**
   * Creates a new context using the given view with this context
   * as the parent.
   */
  Context.prototype.push = function push (view) {
    return new Context(view, this);
  };

  /**
   * Returns the value of the given name in this context, traversing
   * up the context hierarchy if the value is absent in this context's view.
   */
  Context.prototype.lookup = function lookup (name) {
    var cache = this.cache;

    var value;
    if (cache.hasOwnProperty(name)) {
      value = cache[name];
    } else {
      var context = this, names, index, lookupHit = false;

      while (context) {
        if (name.indexOf('.') > 0) {
          value = context.view;
          names = name.split('.');
          index = 0;

          /**
           * Using the dot notion path in `name`, we descend through the
           * nested objects.
           *
           * To be certain that the lookup has been successful, we have to
           * check if the last object in the path actually has the property
           * we are looking for. We store the result in `lookupHit`.
           *
           * This is specially necessary for when the value has been set to
           * `undefined` and we want to avoid looking up parent contexts.
           **/
          while (value != null && index < names.length) {
            if (index === names.length - 1)
              lookupHit = hasProperty(value, names[index]);

            value = value[names[index++]];
          }
        } else {
          value = context.view[name];
          lookupHit = hasProperty(context.view, name);
        }

        if (lookupHit)
          break;

        context = context.parent;
      }

      cache[name] = value;
    }

    if (isFunction(value))
      value = value.call(this.view);

    return value;
  };

  /**
   * A Writer knows how to take a stream of tokens and render them to a
   * string, given a context. It also maintains a cache of templates to
   * avoid the need to parse the same template twice.
   */
  function Writer () {
    this.cache = {};
  }

  /**
   * Clears all cached templates in this writer.
   */
  Writer.prototype.clearCache = function clearCache () {
    this.cache = {};
  };

  /**
   * Parses and caches the given `template` and returns the array of tokens
   * that is generated from the parse.
   */
  Writer.prototype.parse = function parse (template, tags) {
    var cache = this.cache;
    var tokens = cache[template];

    if (tokens == null)
      tokens = cache[template] = parseTemplate(template, tags);

    return tokens;
  };

  /**
   * High-level method that is used to render the given `template` with
   * the given `view`.
   *
   * The optional `partials` argument may be an object that contains the
   * names and templates of partials that are used in the template. It may
   * also be a function that is used to load partial templates on the fly
   * that takes a single argument: the name of the partial.
   */
  Writer.prototype.render = function render (template, view, partials) {
    var tokens = this.parse(template);
    var context = (view instanceof Context) ? view : new Context(view);
    return this.renderTokens(tokens, context, partials, template);
  };

  /**
   * Low-level method that renders the given array of `tokens` using
   * the given `context` and `partials`.
   *
   * Note: The `originalTemplate` is only ever used to extract the portion
   * of the original template that was contained in a higher-order section.
   * If the template doesn't use higher-order sections, this argument may
   * be omitted.
   */
  Writer.prototype.renderTokens = function renderTokens (tokens, context, partials, originalTemplate) {
    var buffer = '';

    var token, symbol, value;
    for (var i = 0, numTokens = tokens.length; i < numTokens; ++i) {
      value = undefined;
      token = tokens[i];
      symbol = token[0];

      if (symbol === '#') value = this.renderSection(token, context, partials, originalTemplate);
      else if (symbol === '^') value = this.renderInverted(token, context, partials, originalTemplate);
      else if (symbol === '>') value = this.renderPartial(token, context, partials, originalTemplate);
      else if (symbol === '&') value = this.unescapedValue(token, context);
      else if (symbol === 'name') value = this.escapedValue(token, context);
      else if (symbol === 'text') value = this.rawValue(token);

      if (value !== undefined)
        buffer += value;
    }

    return buffer;
  };

  Writer.prototype.renderSection = function renderSection (token, context, partials, originalTemplate) {
    var self = this;
    var buffer = '';
    var value = context.lookup(token[1]);

    // This function is used to render an arbitrary template
    // in the current context by higher-order sections.
    function subRender (template) {
      return self.render(template, context, partials);
    }

    if (!value) return;

    if (isArray(value)) {
      for (var j = 0, valueLength = value.length; j < valueLength; ++j) {
        buffer += this.renderTokens(token[4], context.push(value[j]), partials, originalTemplate);
      }
    } else if (typeof value === 'object' || typeof value === 'string' || typeof value === 'number') {
      buffer += this.renderTokens(token[4], context.push(value), partials, originalTemplate);
    } else if (isFunction(value)) {
      if (typeof originalTemplate !== 'string')
        throw new Error('Cannot use higher-order sections without the original template');

      // Extract the portion of the original template that the section contains.
      value = value.call(context.view, originalTemplate.slice(token[3], token[5]), subRender);

      if (value != null)
        buffer += value;
    } else {
      buffer += this.renderTokens(token[4], context, partials, originalTemplate);
    }
    return buffer;
  };

  Writer.prototype.renderInverted = function renderInverted (token, context, partials, originalTemplate) {
    var value = context.lookup(token[1]);

    // Use JavaScript's definition of falsy. Include empty arrays.
    // See https://github.com/janl/mustache.js/issues/186
    if (!value || (isArray(value) && value.length === 0))
      return this.renderTokens(token[4], context, partials, originalTemplate);
  };

  Writer.prototype.renderPartial = function renderPartial (token, context, partials) {
    if (!partials) return;

    var value = isFunction(partials) ? partials(token[1]) : partials[token[1]];
    if (value != null)
      return this.renderTokens(this.parse(value), context, partials, value);
  };

  Writer.prototype.unescapedValue = function unescapedValue (token, context) {
    var value = context.lookup(token[1]);
    if (value != null)
      return value;
  };

  Writer.prototype.escapedValue = function escapedValue (token, context) {
    var value = context.lookup(token[1]);
    if (value != null)
      return mustache.escape(value);
  };

  Writer.prototype.rawValue = function rawValue (token) {
    return token[1];
  };

  mustache.name = 'mustache.js';
  mustache.version = '2.3.0';
  mustache.tags = [ '{{', '}}' ];

  // All high-level mustache.* functions use this writer.
  var defaultWriter = new Writer();

  /**
   * Clears all cached templates in the default writer.
   */
  mustache.clearCache = function clearCache () {
    return defaultWriter.clearCache();
  };

  /**
   * Parses and caches the given template in the default writer and returns the
   * array of tokens it contains. Doing this ahead of time avoids the need to
   * parse templates on the fly as they are rendered.
   */
  mustache.parse = function parse (template, tags) {
    return defaultWriter.parse(template, tags);
  };

  /**
   * Renders the `template` with the given `view` and `partials` using the
   * default writer.
   */
  mustache.render = function render (template, view, partials) {
    if (typeof template !== 'string') {
      throw new TypeError('Invalid template! Template should be a "string" ' +
                          'but "' + typeStr(template) + '" was given as the first ' +
                          'argument for mustache#render(template, view, partials)');
    }

    return defaultWriter.render(template, view, partials);
  };

  // This is here for backwards compatibility with 0.4.x.,
  /*eslint-disable */ // eslint wants camel cased function name
  mustache.to_html = function to_html (template, view, partials, send) {
    /*eslint-enable*/

    var result = mustache.render(template, view, partials);

    if (isFunction(send)) {
      send(result);
    } else {
      return result;
    }
  };

  // Export the escaping function so that the user may override it.
  // See https://github.com/janl/mustache.js/issues/244
  mustache.escape = escapeHtml;

  // Export these mainly for testing, but also for advanced usage.
  mustache.Scanner = Scanner;
  mustache.Context = Context;
  mustache.Writer = Writer;

  return mustache;
}));;
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
}());;
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

    /**
     * Sends a message object for a task. This is just a helper and
     * a wrapper around send().
     */
    ws.sendTask = function ( task, data ) {
        ws.send( JSON.stringify({
            data: data,
            task: task,
            type: 'task'
        }));
    };

    return ws;
}( ReconnectingWebSocket, JSON, LibreMail.Const, LibreMail.Emitter ));;
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
        Emitter.on( Const.EV.ACCOUNT_INFO, editAccount );
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
        Accounts.update( data );
    }

    /**
     * Opens the account edit screen and prevents stats from
     * over-writing until the user closes it.
     */
    function editAccount ( data ) {
        Accounts.render( data );
    }

    return {
        load: load
    };
}}(
    LibreMail.Const,
    LibreMail.Emitter,
    LibreMail.Components
));;
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
        Emitter.on( Const.EV.ERROR, offline );
        Emitter.on( Const.EV.LOG_DATA, logData );
        Emitter.on( Const.EV.WS_CLOSE, offline );
        Emitter.on( Const.EV.SHOW_FOLDERS, update );
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

    function update () {
        if ( ! data || ! Object.keys( data ? data : {} ).length ) {
            return;
        }

        Folders.tearDown();
        Header.render( data );
        Folders.render( data );
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
        Header.reset();
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
));;
/**
 * Header Component
 */
LibreMail.Components.Accounts = (function ( Const, Socket, Emitter, Mustache ) {
'use strict';
// Returns a new instance
return function ( $root ) {
    // Event namespace
    var namespace = '.accounts';
    // DOM template nodes
    var $accountForm = document.getElementById( 'account-form' );
    // Templates
    var tpl = {
        account_form: $accountForm.innerHTML
    };
    // DOM nodes
    var $cancelButton;
    var $accountInfoForm;
    // To prevent re-rendering the form the user might be editing
    var isRendered = false;

    // Parse the templates
    Mustache.parse( tpl.account_form );

    /**
     * Load the account info edit form. This will let the user create
     * an account or edit their existing account.
     * @param Object data Optional account config to load into form
     */
    function render ( /* data */ ) {
        var data = ( arguments.length )
            ? arguments[ 0 ]
            : {};

        if ( isRendered && ! data ) {
            return;
        }

        isRendered = true;
        $root.innerHTML = Mustache.render( tpl.account_form, data );
        $accountInfoForm = $root.querySelector( 'form#account-info' );
        $cancelButton = $accountInfoForm.querySelector( '#account-cancel' );
        $accountInfoForm.onsubmit = save;

        if ( $cancelButton ) {
            $cancelButton.onclick = cancel;
        }
    }

    function tearDown () {
        isRendered = false;
        $cancelButton = null;
        $accountInfoForm = null;
    }

    /**
     * Saves the account info form. This expects to be called in the
     * context of a DOM event.
     */
    function save ( e ) {
        e.preventDefault();
        showAltTitle();
        lockForm( true );
        Socket.sendTask(
            Const.TASK.SAVE_ACCOUNT, {
                host: $accountInfoForm.host.value,
                port: $accountInfoForm.port.value,
                email: $accountInfoForm.email.value,
                password: $accountInfoForm.password.value
            });
    }

    function cancel ( e ) {
        Emitter.fire( Const.EV.SHOW_FOLDERS );
    }

    /**
     * Update the state of the account form.
     */
    function update ( data ) {
        if ( ! isRendered ) {
            return;
        }

        lockForm( false );

        if ( data.updated ) {
            Socket.send( Const.MSG.RESTART );
        }
        else {
            showTitle();
        }
    }

    function lockForm ( disabled ) {
        var i;
        var elements = $accountInfoForm.elements;

        for ( i = 0; i < elements.length; i++ ) {
            elements[ i ].disabled = disabled;
        }
    }

    function showAltTitle () {
        $accountInfoForm
            .querySelector( 'h1.title' )
            .style
            .display = 'none';
        $accountInfoForm
            .querySelector( 'h1.alt-title' )
            .style
            .display = 'block';
    }

    function showTitle () {
        $accountInfoForm
            .querySelector( 'h1.title' )
            .style
            .display = 'block';
        $accountInfoForm
            .querySelector( 'h1.alt-title' )
            .style
            .display = 'none';
    }

    return {
        render: render,
        update: update,
        tearDown: tearDown
    };
}}( LibreMail.Const, LibreMail.Socket, LibreMail.Emitter, Mustache ));;
/**
 * Folders Component
 */
LibreMail.Components.Folders = (function ( Mustache ) {
'use strict';
// Returns a new instance
return function ( $root ) {
    // Event namespace
    var namespace = '.folders';
    // Flag if the system is "auto-scrolling"
    var syncActive;
    // Flag to reset scroll
    var hasScrolled;
    // Used for comparison during render
    var folderList = [];
    // Used for throttling full re-draws. While a sync is in
    // progress we'll get a stream of update messages. Inter-
    // mixed in those will be messages without an active folder.
    // The absence of the active folder will cause the update
    // to re-draw every folder and jitter the screen. We can
    // prevent this with a throttle timer.
    var redrawTimer;
    // Used for crawling stale folders that may still have an
    // incomplete flag on them.
    var spiderTimer;
    var spiderStore = {};
    // This is not used, see crawlFolders
    var spiderWaitMs = 0;
    var activeFlag = false;
    var spiderDelayMs = 5000;
    var spiderTimeoutMs = 2000;
    var redrawTimeoutMs = 10000;
    // DOM template nodes
    var $folder = document.getElementById( 'folder' );
    var $folders = document.getElementById( 'folders' );
    // Templates
    var tpl = {
        folder: $folder.innerHTML,
        folders: $folders.innerHTML
    };

    // Parse the templates
    Mustache.parse( tpl.folder );
    Mustache.parse( tpl.folders );

    function render ( d ) {
        var i;
        var folders;
        var folderNames;

        if ( ! d.account
            || ! d.accounts
            || ! Object.keys( d.accounts ).length )
        {
            return;
        }

        folderNames = Object.keys( d.accounts[ d.account ] );
        folders = formatFolders( d.accounts[ d.account ], d.active );

        // If we already rendered the folders, just perform
        // an update on the folder meta.
        if ( folderList.length
            && arraysEqual( folderList, folderNames ) )
        {
            update( folders, d.active );
        }
        else {
            draw( folders );
        }

        folderList = folderNames;
        startSpiderCrawl( spiderDelayMs );

        if ( d.asleep || ( ! d.active && ! activeFlag ) ) {
            if ( hasScrolled === true && syncActive === true ) {
                window.scrollTo( 0, 0 );
                syncActive = false;
            }

            return;
        }

        for ( i in folders ) {
            if ( folders[ i ].active ) {
                syncActive = true;
                scrollTo( folders[ i ].id );
                break;
            }
        }
    }

    function draw ( folders ) {
        $root.innerHTML = Mustache.render(
            tpl.folders, {
                folders: folders
            }, {
                folder: tpl.folder
            });
    }

    function update( folders, active ) {
        var i;
        var node;
        var activeNode;
        var activeNodes;

        // If there's an active folder, just update the active one
        if ( active ) {
            extendRedrawTimer();
            activeNodes = document.querySelectorAll( '.folder.active' );

            for ( i = 0; activeNode = activeNodes[ i ]; i++ ) {
                activeNode.className = activeNode
                    .className
                    .replace( "active", "" );
            }
        }

        for ( i in folders ) {
            node = document.getElementById( folders[ i ].id );

            if ( ! node ) {
                continue;
            }

            node.innerHTML = Mustache.render( tpl.folder, folders[ i ] );

            if ( ( ! active && ! activeFlag )
                || ( active && folders[ i ].path == active )
                || ( ! folders[ i ].incomplete
                    && node.className.indexOf( "incomplete" ) !== -1 )
                || ( folders[ i ].incomplete
                    && node.className.indexOf( "incomplete" ) === -1 ) )
            {
                updateFolderClasses( node, folders[ i ] );
            }

            node = null;
        }
    }

    function updateFolderClasses ( node, folder ) {
        var classes = [ "folder" ];

        if ( folder.active ) {
            classes.push( "active" );
        }

        if ( folder.incomplete ) {
            classes.push( "incomplete" );
        }

        node.className = classes.join( " " );
        spiderStore[ folder.id ] = (new Date).getTime();
    }

    function cleanupFolderClasses ( node ) {
        var count;
        var synced;
        var classes;

        if ( ! node
            || ! node.className
            || node.className.indexOf( "active" ) !== -1 )
        {
            return;
        }

        count = parseInt(
            node.querySelector( 'input.count' ).value,
            10 );
        synced = parseInt(
            node.querySelector( 'input.synced' ).value,
            10 );

        if ( synced >= count ) {
            node.className = node.className.replace( "incomplete", "" );
        }
        else if ( node.className.indexOf( "incomplete" ) === -1 ) {
            node.className = node.className + " incomplete";
        }
    }

    function tearDown () {
        folderList = [];
        syncActive = false;
        activeFlag = false;
        hasScrolled = false;
    }

    /**
     * Reads in a collection of accounts with folder metadata
     * and prepares it into a format for Mustache.
     * @param Object accounts
     * @param String active Active folder being synced
     * @return Array
     */
    function formatFolders ( folders, active ) {
        var i;
        var formatted = [];

        for ( i in folders ) {
            formatted.push({
                path: i,
                active: ( active === i ),
                count: folders[ i ].count,
                name: i.split( '/' ).pop(),
                synced: folders[ i ].synced,
                percent: folders[ i ].percent,
                id: 'folder-' + i.split( '/' ).join( '-' ),
                incomplete: folders[ i ].synced < folders[ i ].count,
                crumbs: function () {
                    var crumbs = this.path.split( '/' ).slice( 0, -1 );
                    return ( crumbs.length > 0 )
                        ? crumbs.join( '&nbsp;&rsaquo;&nbsp;' )
                        : '&nbsp;';
                }
            });
        }

        return formatted;
    }

    function yPosition ( node ) {
        var elt = node;
        var y = elt.offsetTop;

        while ( elt.offsetParent && elt.offsetParent != document.body ) {
            elt = elt.offsetParent;
            y += elt.offsetTop;
        }

        return y;
    }

    function scrollTo ( id ) {
        var yPos;
        var node = document.getElementById( id );

        if ( ! node ) {
            return;
        }

        yPos = yPosition( node );

        // If the element is fully visible, then don't scroll
        if ( yPos + node.clientHeight < window.innerHeight + window.scrollY
            && yPos > window.scrollY )
        {
            return;
        }

        window.scrollTo( 0, node.offsetTop );
        hasScrolled = true;
    }

    function arraysEqual ( a, b ) {
        var i;
        if ( a === b ) return true;
        if ( a == null || b == null ) return false;
        if ( a.length != b.length ) return false;

        a.sort();
        b.sort();

        for ( i = 0; i < a.length; i++ ) {
            if ( a[ i ] !== b[ i ] ) return false;
        }

        return true;
    }

    function extendRedrawTimer () {
        activeFlag = true;
        clearTimeout( redrawTimer );
        redrawTimer = setTimeout( function () {
            activeFlag = false;
        }, redrawTimeoutMs );
    }

    /**
     * Crawls the folders on a timer, looking for any that
     * should have their classname cleaned up.
     */
    function startSpiderCrawl ( timeout ) {
        clearTimeout( spiderTimer );
        spiderTimer = setTimeout( crawlFolders, spiderDelayMs );
    }

    function crawlFolders () {
        var i;
        var folders;
        var time = (new Date).getTime();

        if ( ! activeFlag ) {
            startSpiderCrawl( spiderTimeoutMs );
            return;
        }

        folders = document.querySelectorAll( '.folder:not(.active)' );

        for ( i in folders ) {
            // If it's been active within a wait period, ignore it
            if ( spiderWaitMs
                && spiderStore[ folders[ i ].id ]
                && time - spiderStore[ folders[ i ].id ] < spiderWaitMs )
            {
                continue;
            }

            cleanupFolderClasses( folders[ i ] );
        }

        startSpiderCrawl( spiderTimeoutMs );
    }

    return {
        render: render,
        tearDown: tearDown
    };
}}( Mustache ));;
/**
 * Header Component
 */
LibreMail.Components.Header = (function ( Const, Socket, Emitter, Mustache ) {
'use strict';
// Returns a new instance
return function ( $root ) {
    // Event namespace
    var namespace = '.header';
    // DOM template nodes
    var $header = document.getElementById( 'header' );
    var $status = document.getElementById( 'status' );
    var $accounts = document.getElementById( 'accounts' );
    // Templates
    var tpl = {
        header: $header.innerHTML,
        status: $status.innerHTML,
        accounts: $accounts.innerHTML
    };
    // Account email
    var account;
    // State for restart command
    var isAsleep = false;
    // State for rendering the parent template
    var rootIsRendered = false;
    // DOM nodes for updating
    var $optionsButton;
    var $statusSection;
    var $restartButton;
    var $accountsSection;
    var $editAccountButton;
    var $removeAccountButton;

    // Parse the templates
    Mustache.parse( tpl.header );
    Mustache.parse( tpl.status );
    Mustache.parse( tpl.accounts );

    /**
     * Triggered from Stats Page
     * @param Object data
     */
    function render ( data ) {
        account = data.account;
        // Store this for the restart button
        isAsleep = data.asleep;

        if ( rootIsRendered === true ) {
            update( data );
            return;
        }

        $root.innerHTML = Mustache.render(
            tpl.header, {
                asleep: data.asleep,
                uptime: data.uptime,
                account: data.account,
                running: data.running,
                runningTime: function () {
                    return formatTime( this.uptime )
                },
                accounts: Object.keys( data.accounts )
            }, {
                status: tpl.status,
                accounts: tpl.accounts
            });
        rootIsRendered = true;
        loadDomElements();
        // Attach event handlers to DOM elements.
        $restartButton.onclick = restart;
        $editAccountButton.onclick = editAccount;
        $removeAccountButton.onclick = removeAccount;
    }

    function tearDown () {
        // Disable the buttons
        if ( rootIsRendered ) {
            $restartButton.className = 'disabled';
            $optionsButton.className = 'disabled';
            $accountsSection.className += ' disabled';
        }

        // Release memory
        account = null;
        isAsleep = false;
        $statusSection = null;
        $restartButton = null;
        $optionsButton = null;
        rootIsRendered = false;
        $accountsSection = null;
        $editAccountButton = null
        $removeAccountButton = null;
    }

    function reset () {
        $root.innerHTML = Mustache.render(
            tpl.header, {
                asleep: true,
                accounts: [],
                offline: true,
                running: false,
                account: account,
            }, {
                status: tpl.status,
                accounts: tpl.accounts
            });
        rootIsRendered = true;
        loadDomElements();
    }

    function restart () {
        if ( ! isAsleep ) {
            return;
        }

        Socket.send( Const.MSG.RESTART );
    }

    function editAccount () {
        Socket.sendTask(
            Const.TASK.ACCOUNT_INFO, {
                email: account
            });
    }

    function removeAccount () {
        var email = window.prompt(
            "If you're sure you want to remove this account, " +
            "then please type the full email address. " );

        if ( ! email ) {
            return;
        }

        if ( email === account ) {
            Socket.sendTask(
                Const.TASK.REMOVE_ACCOUNT, {
                    email: account
                });
        }
        else {
            Emitter.fire(
                Const.EV.NOTIFICATION, {
                    status: Const.STATUS.error,
                    message: "You didn't type in the correct address."
                });
        }
    }

    function update ( data ) {
        $statusSection.innerHTML = Mustache.render(
            tpl.status, {
                asleep: data.asleep,
                uptime: data.uptime,
                running: data.running,
                runningTime: function () {
                    return formatTime( this.uptime )
                }
            });
        $accountsSection.innerHTML = Mustache.render(
            tpl.accounts, {
                account: data.account,
                accounts: Object.keys( data.accounts )
            });

        // Mark button disabled if the sync is running
        $restartButton.className = ( ! isAsleep )
            ? 'disabled'
            : '';
    }

    function loadDomElements () {
        $statusSection = $root.querySelector( 'section.status' );
        $restartButton = $root.querySelector( 'button#restart' );
        $accountsSection = $root.querySelector( 'section.accounts' );
        $editAccountButton = $root.querySelector( 'a#account-edit' );
        $removeAccountButton = $root.querySelector( 'a#account-remove' );
        $optionsButton = $root.querySelector( 'button#account-options' );
    }

    function formatTime ( seconds ) {
        var days;
        var hours;
        var minutes;

        if ( seconds < 60 ) {
            return seconds + "s";
        }
        else if ( seconds < 3600 ) {
            return Math.floor( seconds / 60 ) + "m";
        }
        else if ( seconds < 86400 ) {
            minutes = Math.floor( (seconds % 3600) / 60 );

            return Math.floor( seconds / 3600 ) + "h"
                + ( minutes ? " " + minutes + "m" : "" );
        }
        else if ( seconds < 31536000 ) {
            hours = Math.floor( (seconds / 3600) % 24 );

            return Math.floor( (seconds / 3600) / 24 ) + "d"
                + ( hours ? " " + hours + "h" : "" );
        }
        else {
            days = Math.floor( seconds % 31536000 );

            return Math.floor( seconds / 31536000 ) + "y"
                + ( days ? " " + days + "d" : "" );
        }
    }

    return {
        reset: reset,
        render: render,
        tearDown: tearDown
    };
}}( LibreMail.Const, LibreMail.Socket, LibreMail.Emitter, Mustache ));;
/**
 * Header Component
 */
LibreMail.Components.Notifications = (function ( Const, Socket, Mustache ) {
'use strict';
// Returns a new instance
return function ( $root ) {
    // Event namespace
    var namespace = '.notifications';
    // DOM template nodes
    var $notification = document.getElementById( 'notification' );
    // Templates
    var tpl = {
        notification: $notification.innerHTML
    };

    // Parse the templates
    Mustache.parse( tpl.notification );

    /**
     * Triggered from Global Page when socket opened.
     */
    function insert ( data ) {
        var newNode = document.createElement( 'div' );

        newNode.className = 'notification';
        newNode.innerHTML = Mustache.render( tpl.notification, data );
        newNode.querySelector( '.close' ).onclick = function ( e ) {
            $root.removeChild( newNode );
        };
        $root.appendChild( newNode );
    }

    function closeAll () {
        var i;
        var notifications = $root.querySelectorAll( '.notification' );

        for ( i = 0; i < notifications.length; i++ ) {
            $root.removeChild( notifications[ i ] );
        }
    }

    return {
        insert: insert,
        closeAll: closeAll
    };
}}( LibreMail.Const, LibreMail.Socket, Mustache ));;
/**
 * Header Component
 */
LibreMail.Components.StatusMessage = (function ( Const, Socket, Mustache ) {
'use strict';
// Returns a new instance
return function ( $root ) {
    // Event namespace
    var namespace = '.statusmessage';
    // DOM template nodes
    var $warmup = document.getElementById( 'warmup' );
    var $statusMessage = document.getElementById( 'status-message' );
    // Templates
    var tpl = {
        warmup: $warmup.innerHTML,
        status_message: $statusMessage.innerHTML
    };
    // DOM nodes for updating
    var $recheckButton;

    // Parse the templates
    Mustache.parse( tpl.status_message );

    /**
     * Triggered from Global Page when socket opened.
     */
    function renderOnline () {
        $root.innerHTML = Mustache.render( tpl.warmup );
    }

    /**
     * Triggered from Global Page
     * @param Object data
     */
    function renderOffline () {
        tearDown();
        draw({
            restart: false,
            suggestion: "",
            heading: Const.LANG.server_offline_heading,
            message: Const.LANG.server_offline_message
        });
    }

     /**
     * Handles diagnostic error messages from Global Page.
     */
    function renderDiagnosticError ( message, suggestion ) {
        tearDown();
        draw({
            restart: true,
            message: message,
            suggestion: suggestion,
            heading: Const.LANG.error_heading
        });

        $recheckButton = $root.querySelector( 'button#recheck' );
        $recheckButton.onclick = recheck;
    }

    /**
     * Triggered from Global Page when an error hits.
     */
    function renderError ( message, suggestion ) {
        tearDown();
        draw({
            restart: false,
            message: message,
            suggestion: suggestion,
            heading: Const.LANG.error_heading
        });
    }

    function draw ( data ) {
        $root.innerHTML = Mustache.render( tpl.status_message, data );
    }

    function recheck () {
        Socket.send( Const.MSG.START );
    }

    function tearDown () {
        $recheckButton = null;
    }

    return {
        renderError: renderError,
        renderOnline: renderOnline,
        renderOffline: renderOffline,
        renderDiagnosticError: renderDiagnosticError
    };
}}( LibreMail.Const, LibreMail.Socket, Mustache ));
//# sourceMappingURL=libremail.js.map