/**
 * Notifications Component
 */
LibreMail.Components.Notifications = (function (Const, Socket, Mustache) {
  // Returns a new instance
  return function ($root) {
    'use strict';

    // DOM template nodes
    var $notification = document.getElementById('notification');
    // Templates
    var tpl = {
      notification: $notification.innerHTML
    };

    // Parse the templates
    Mustache.parse(tpl.notification);

    /**
     * Triggered from Global Page when socket opened.
     */
    function insert (data) {
      var newNode = document.createElement('div');

      newNode.className = 'notification';
      newNode.innerHTML = Mustache.render(tpl.notification, data);
      newNode.querySelector('.close').onclick = function () {
        $root.removeChild(newNode);
      };

      $root.appendChild(newNode);
    }

    function closeAll () {
      var i;
      var notifications = $root.querySelectorAll('.notification');

      for (i = 0; i < notifications.length; i++) {
        $root.removeChild(notifications[ i ]);
      }
    }

    return {
      insert: insert,
      closeAll: closeAll
    };
  };
}(LibreMail.Const, LibreMail.Socket, Mustache));
