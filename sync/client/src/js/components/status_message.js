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