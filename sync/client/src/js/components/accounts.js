/**
 * Header Component
 */
LibreMail.Components.Accounts = (function ( Const, Socket, Mustache ) {
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
    var $saveButton;
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

        $root.innerHTML = Mustache.render( tpl.account_form, data );
        isRendered = true;
    }

    function tearDown () {
        isRendered = false;
    }

    return {
        render: render,
        tearDown: tearDown
    };
}}( LibreMail.Const, LibreMail.Socket, Mustache ));