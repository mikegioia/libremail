/**
 * Stats Page Controller
 */
LibreMail.Pages.Stats = (function (Const, Emitter, Components) {
  // Returns a new instance
  return function () {
    'use strict';

    // Components used
    var Header;
    var Folders;
    // Private state
    var data = {};
    var flagStop = false;

    /**
     * Attach events and instantiate Components
     */
    function load () {
      events();
      components();
    }

    function events () {
      Emitter.on(Const.EV.STATS, render);
      Emitter.on(Const.EV.ERROR, offline);
      Emitter.on(Const.EV.LOG_DATA, logData);
      Emitter.on(Const.EV.WS_CLOSE, offline);
      Emitter.on(Const.EV.SHOW_FOLDERS, update);
      Emitter.on(Const.EV.ACCOUNT, accountUpdated);
      Emitter.on(Const.EV.STOP_UPDATE, stopUpdate);
      Emitter.on(Const.EV.START_UPDATE, startUpdate);
    }

    function components () {
      Header = new Components.Header(
        document.querySelector('header')
      );

      Folders = new Components.Folders(
        document.querySelector('main')
      );
    }

    /**
     * Render the components.
     */
    function render (_data) {
      data = _data;

      if (data.account && !flagStop) {
        Header.render(data);
        Folders.render(data);
      }
    }

    function update () {
      if (!data || !Object.keys(data || {}).length) {
        return;
      }

      Folders.tearDown();
      Header.render(data);
      Folders.render(data);
    }

    function stopUpdate () {
      flagStop = true;
    }

    function startUpdate () {
      flagStop = false;
    }

    function logData () {
      console.log(data);
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
    function accountUpdated (data) {
      if (data.updated) {
        Folders.tearDown();
      }
    }

    return {
      load: load
    };
  };
}(
  LibreMail.Const,
  LibreMail.Emitter,
  LibreMail.Components
));
