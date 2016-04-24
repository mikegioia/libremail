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
        $accountInfoForm.onsubmit = save;
    }

    function tearDown () {
        isRendered = false;
    }

    /**
     * Saves the account info form. This expects to be called in the
     * context of a DOM event.
     */
    function save ( e ) {
        e.preventDefault();
        Socket.sendTask({
            host: $accountInfoForm.host.value,
            port: $accountInfoForm.port.value,
            email: $accountInfoForm.email.value,
            password: $accountInfoForm.password.value
        });
    }

    return {
        render: render,
        tearDown: tearDown
    };
}}( LibreMail.Const, LibreMail.Socket, Mustache ));