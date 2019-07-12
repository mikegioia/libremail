/**
 * Header Component
 */
LibreMail.Components.Header = (function (Const, Socket, Emitter, Mustache) {
  // Returns a new instance
  return function ($root) {
    'use strict';

    // DOM template nodes
    var $header = document.getElementById('header');
    var $status = document.getElementById('status');
    var $accounts = document.getElementById('accounts');
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
    Mustache.parse(tpl.header);
    Mustache.parse(tpl.status);
    Mustache.parse(tpl.accounts);

    /**
     * Triggered from Stats Page
     *
     * @param Object data
     */
    function render (data) {
      account = data.account;
      isAsleep = data.asleep; // store this for the restart button

      if (rootIsRendered === true) {
        update(data);
        return;
      }

      $root.innerHTML = Mustache.render(
        tpl.header, {
          uptime: data.uptime,
          account: data.account,
          running: data.running,
          online: data.uptime && data.uptime > 0,
          asleep: data.asleep || !data.running,
          runningTime: function () {
            return formatTime(this.uptime);
          },
          accounts: Object.keys(data.accounts)
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
      if (rootIsRendered) {
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
      $editAccountButton = null;
      $removeAccountButton = null;
    }

    function reset () {
      $root.innerHTML = Mustache.render(
        tpl.header, {
          asleep: true,
          accounts: [],
          offline: true,
          running: false,
          account: account
        }, {
          status: tpl.status,
          accounts: tpl.accounts
        });
      rootIsRendered = true;

      loadDomElements();
    }

    function restart () {
      if (!isAsleep) {
        return;
      }

      Socket.send(Const.MSG.RESTART);
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
        'then please type the full email address. '
      );

      if (!email) {
        return;
      }

      if (email === account) {
        Socket.sendTask(
          Const.TASK.REMOVE_ACCOUNT, {
            email: account
          });
      } else {
        Emitter.fire(
          Const.EV.NOTIFICATION, {
            status: Const.STATUS.error,
            message: "You didn't type in the correct address."
          });
      }
    }

    function update (data) {
      $statusSection.innerHTML = Mustache.render(
        tpl.status, {
          uptime: data.uptime,
          running: data.running,
          online: data.uptime && data.uptime > 0,
          asleep: data.asleep || !data.running,
          runningTime: function () {
            return formatTime(this.uptime);
          }
        });
      $accountsSection.innerHTML = Mustache.render(
        tpl.accounts, {
          account: data.account,
          accounts: Object.keys(data.accounts)
        });

      // Mark button disabled if the sync is running
      $restartButton.className = isAsleep
        ? ''
        : 'disabled';
    }

    function loadDomElements () {
      $statusSection = $root.querySelector('section.status');
      $restartButton = $root.querySelector('button#restart');
      $accountsSection = $root.querySelector('section.accounts');
      $editAccountButton = $root.querySelector('a#account-edit');
      $removeAccountButton = $root.querySelector('a#account-remove');
      $optionsButton = $root.querySelector('button#account-options');
    }

    function formatTime (seconds) {
      var days;
      var hours;
      var minutes;

      if (seconds < 60) {
        return seconds + 's';
      } else if (seconds < 3600) {
        return Math.floor(seconds / 60) + 'm';
      } else if (seconds < 86400) {
        minutes = Math.floor((seconds % 3600) / 60);

        return Math.floor(seconds / 3600) + 'h' +
        (minutes ? ' ' + minutes + 'm' : '');
      } else if (seconds < 31536000) {
        hours = Math.floor((seconds / 3600) % 24);

        return Math.floor((seconds / 3600) / 24) + 'd' +
        (hours ? ' ' + hours + 'h' : '');
      }

      days = Math.floor(seconds % 31536000);

      return Math.floor(seconds / 31536000) + 'y' +
        (days ? ' ' + days + 'd' : '');
    }

    return {
      reset: reset,
      render: render,
      tearDown: tearDown
    };
  };
}(LibreMail.Const, LibreMail.Socket, LibreMail.Emitter, Mustache));
