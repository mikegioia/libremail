# IMAP Sync

This application synchronises IMAP accounts to a local MySQL database. You can
use this as a standalone method to periodically download your email, or you can
use it with the two clients bundled with this tool.

![Sync Screenshot](http://mikegioia.github.io/libremail/images/sync_screenshot.png)

---

## Contents

- [Installation](https://github.com/mikegioia/libremail/tree/master/sync#installation)
- [Run the IMAP Sync](https://github.com/mikegioia/libremail/tree/master/sync#run-the-sync)
    1. [Create Database](https://github.com/mikegioia/libremail/tree/master/sync#1-create-database)
    2. [Configure the Application](https://github.com/mikegioia/libremail/tree/master/sync#2-configure-the-application)
    3. [Install Composer Dependencies](https://github.com/mikegioia/libremail/tree/master/sync#5-install-composer-dependencies)
    4. [Running the Diagnostic Tests](https://github.com/mikegioia/libremail/tree/master/sync#3-running-the-diagnostic-tests)
    5. [Running SQL Migration Scripts](https://github.com/mikegioia/libremail/tree/master/sync#4-running-sql-migration-scripts)
- [Using an Init Script or Supervisor](https://github.com/mikegioia/libremail/tree/master/sync#using-an-init-script-or-supervisor)
- [Submitting Bugs](https://github.com/mikegioia/libremail/tree/master/sync#submitting-bugs)

## Installation

### 1. Create Database

To get started, make sure you have MariaDB or MySQL running and issue the
following command:

    MariaDB [(none)]> CREATE DATABASE `libremail`;

The database name is a configuration option, so if you'd like to change this to
be something other than `libremail`, feel free to use anything.

##### 1.1 `max_allowed_packet` (MariaDB/MySQL)

Because of the amount of data that may be written to SQL (long email message text)
a configuration setting needs to be enabled for MySQL databases allowing a larger
packet size to be sent in a query. To do this, add the following line to your SQL
config file:

    [mysqld]
    max_allowed_pack = 500M

You don't have to use 500MB as your packet size, but anything 16MB or higher is advised.

### 2. Configure the Application

Configuration options are saved in `config/default.ini`. **Do not modify this
file or anything in it**. All of your modifications should go into
`config/local.ini` and should be in the
[INI file format](https://en.wikipedia.org/wiki/INI_file), just like the default
file. Here's an explanation of the options you can overwrite:

#### [app]

* `memory`

  String, defaults to `"128M"`. This is the PHP memory size limit for the
  sync script. The application will use whatever your default memory limit
  is for your PHP install, but you can override that here. It's best to
  leave this at 128 MB as a minimum, otherwise some of your emails may not
  download. Emails with large (or many) attachments require an excess of
  memory to parse in PHP, due do how large strings are parsed. 256M is a
  better, safer limit but I set mine as high as 1GB.

* `sync[wait_seconds]`

  Integer, defaults to `10`. This is the number of seconds to wait before
  retrying a failed action while syncing.

* `sync[sleep_minutes]`

  Integer, defaults to `15`. This is the number of minutes the script will
  sleep after each sync attempt, before running the next.

#### [log]

* `level[cli]`

  Integer, defaults to `7`. Enter a number between `0` and `7` corresponding to
the minimum level you want to capture when logs are written to the CLI. This
happens when the app is run in `interactive mode`. These are the following log
levels:

        0: Emergency -- system is unusable
        1: Alert -- action must be taken immediately
        2: Critical -- severe error
        3: Error -- standard error
        4: Warning -- something unusual happened
        5: Notice -- normal but significant condition
        6: Info -- informational messages
        7: Debug -- noisy, debug-level messages

* `level[file]`

  Integer, defaults to `5`. Enter a number between `0` and `7` corresponding to
the minimum level you want to capture when logs are written to disk. This
happens when the app is running in the background.

* `stacktrace`

  Defaults to `true` but set to `false` if you want to suppress stack traces
  from showing in the logs or the CLI.

* `name`

  String, any name for the application. Defaults to `libremail`.

* `path`

  Relative or absolute path for saving log files. This needs a filename at the
  end which will be used as a stem for creating timestamped log files. Default
  value is `logs/sync.log`.

#### [sql]

* `database`

  Name of the database, defaults to `libremail`. If you've changed the name of
  the database in the `CREATE DATABASE` command at the top, then change this
  config setting too.

* `hostname`

  Hostname for MySQL connection, defaults to `localhost`.

* `port`

  Port for MySQL connection, defaults to `3306`.

* `username`

  Username for MySQL connection, defaults to `root`.

* `password`

  Password for MySQL connection, defaults to `root`.

* `charset`

  Character set for MySQL connection, defaults to `utf8`.

#### [email]

* `attachments[path]`

  Relative or absolute path for saving email attachments. Defaults to a local
directory named `attachments`.

### 3. Install Composer Dependencies

Download the vendor packages via composer:

    $> composer install

This will create a `vendor` directory with all of the project's PHP
dependencies.

### 4. Running the Diagnostic Tests

You can run a test to see if the application is installed correctly, and that
all dependencies and pre-requisites are met. To do that, run:

    $> ./sync --diagnostics

You can also use `-d` as a short flag. This will go through and check the
database connection, that all paths are writeable, and some other tests and see
if the sync script will run correctly. These tests are run in the background
before any sync happens, but you can access them this way for more detailed
information in the case that something is failing.

### 5. Running SQL Migration Scripts

Before you can start syncing, run the SQL database scripts:

    $> ./sync --updatedb

You can also use `-u` as a short flag. This will create all the SQL tables and
run any other database operations.

## Run the Sync

Before you begin, you can run `./sync --help` to see a list of what options you
have. Below is an explanation of the options you can specify when running this
script:

* `--interactive | -i`

  **Defaults to enabled**. This runs the application interactively, or in a mode
  designed for the CLI (command line interface). Messages are printed to the
  screen and you could be prompted to enter data for certain actions (like
  creating a new account).

* `--background | -b`

  Defaults disabled. This runs the application in the background as a daemon.
  Use this if you'd like to run the syncing engine from an init script, a cron,
  a systemd service, managed via a hypervisor, or any other method of
  watchdogging or monitoring a PHP script.

* `--folder <folder> | -f <folder>`

  Runs the sync script for the specified folder only, and then halts. This is
  useful when running in interactive mode to download one specific folder if
  you're testing something, or if you want to just have an external script
  download, say, your Inbox separately from the entire mailbox. The `<folder>`
  argument is the full name of the IMAP folder: 'INBOX', or 'Accounts/Support'
  are examples.

* `--updatedb | -u`

  Updates the database by running the migration scripts in `db/`. These scripts
  will only run once so you can run this as many times as you'd like.

* `--diagnostics | -d`

  Runs through the diagnostic tests and reports any errors. Use this to start
  debugging any failures.

* `--help | -h`

  Print the help message.

To get started, run:

    $> ./sync

And follow the onscreen instructions!

## Using an Init Script or Supervisor

If you'd like to run this sync script in the background all the time, then it
is recommended to use some sort of supervisor or watchdog program to monitor if
the script fails for any reason.

### SystemD

Create a file `libremail.service` and place it in `~/.config/systemd/user/`.
Make sure to update the paths to the files in PIDFile and ExecStart.

```
[Unit]
Description=LibreMail IMAP Syncing Engine
Documentation=https://github.com/mikegioia/libremail
After=network.target network-online.target

[Service]
Type=simple
PIDFile=/path/to/home/.config/libremail/sync.pid
ExecStart=/path/to/LibreMail/sync/sync -b
ExecStop=/bin/kill -15 $MAINPID
Restart=always

[Install]
WantedBy=default.target
```

To enable and activate the service, run:

    $> systemctl --user daemon-reload
    $> systemctl --user start libremail

### SysV Init

Create a file at `/etc/init.d/libremail` with executable permissions owned
by `root:root`. Make sure to update the paths to the files in EXEC and
possibly PIDFILE. Also change the user and group in RUNAS.

```
#!/bin/sh

### BEGIN INIT INFO
# Provides:           libremail
# Required-Start:
# Required-Stop:
# Default-Start:      2 3 4 5
# Default-Stop:       0 1 6
# Short-Description:  libremail
# Description: LibreMail IMAP Syncing Engine
### END INIT INFO

. /lib/lsb/init-functions

OPTS="-b"
RUNAS="user:group"
PIDFILE=/var/run/libremail.pid
EXEC=/path/to/LibreMail/sync/sync

case "$1" in
    start)
        if [ -f $PIDFILE ]
        then
            log_begin_msg "$PIDFILE exists, process is already running or crashed"
        else
            log_begin_msg "Starting LibreMail..."
            start-stop-daemon --start --chuid $RUNAS --pidfile $PIDFILE \
                --make-pidfile --background --exec $EXEC -- $OPTS
        fi
        log_end_msg 0
        ;;
    stop)
        log_begin_msg "Stopping LibreMail"
        if [ ! -f $PIDFILE ]
        then
            echo "$PIDFILE does not exist, process is not running"
        else
            PID=$(cat $PIDFILE)
            start-stop-daemon --stop --quiet --pidfile $PIDFILE
            rm -f $PIDFILE
        fi
        log_end_msg 0
        ;;
    *)
        echo "Please use start or stop as first argument"
        ;;
esac
```

## Submitting Bugs

All bugs and requests are tracked in the Github Issues for this repository. See
the [Issues Page](https://github.com/mikegioia/libremail/issues) for a listing
of the open and closed tickets. **Please search the closed issues before
reporting anything** to see if it has been resolved :)

This is an open source project that is worked on in spare time, so there is no
guarantee that anything that you report will be looked at or fixed! However I
will make a personal effort to resolve everything in a timely manner, and odds
are good that I'll check it out quickly as I'm personally using this project to
manage my own email.
