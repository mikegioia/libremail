/**
 * Header Component
 */
LibreMail.Components.Header = (function ( Mustache ) {
'use strict';
// Returns a new instance
return function ( $root ) {
    // Event namespace
    var namespace = '.header';
    // DOM template nodes
    var $header = document.getElementById( 'header' );
    // Templates
    var tpl = {
        header: $header.innerHTML
    };

    // Parse the templates
    Mustache.parse( tpl.header );

    /**
     * Triggered from Stats Page
     * @param Object data
     */
    function render ( data ) {
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
            });
    }

    function formatTime ( seconds ) {
        if ( seconds < 60 ) {
            return seconds + "s";
        }
        else if ( seconds < 3600 ) {
            return Math.floor( seconds / 60 ) + "m";
        }
        else if ( seconds < 86400 ) {
            return Math.floor( seconds / 3600 ) + "h"
                + " " + Math.floor( (seconds % 3600) / 60 ) + "m";
        }
        else {
            return Math.floor( seconds / 86400 ) + "d"
                + " " + Math.floor( (seconds / 86400) % 3600 ) + "h";
        }
    }

    return {
        render: render
    };
}}( Mustache ));