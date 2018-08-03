/**
 * Accounts Component
 */
LibreMail.Components.Accounts = (function (Const, Socket, Emitter, Mustache) {
// Returns a new instance
return function ($root) {
    'use strict';
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
}}(LibreMail.Const, LibreMail.Socket, LibreMail.Emitter, Mustache));