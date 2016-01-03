# IMAP Sync

This application synchronises IMAP accounts to a local MySQL database. You can
use this as a standalone method to periodically download your email, or you can
use it with the two clients bundled with this tool.

![Sync Screenshot](http://mikegioia.github.io/libremail/images/sync_screenshot.png)

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

* `stacktrace`

  Defaults to `true` but set to `false` if you want to suppress stack traces
  from showing in the logs or the CLI.

* `sync[wait_seconds]`

  Integer, defaults to `10`. This is the number of seconds to wait before
  retrying a failed action while syncing.

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

### 3. Running SQL Migration Scripts

Before you can start syncing, run the SQL database scripts:

    $> ./sync --update

This will create all the SQL tables and run any other database operations.

### 4. Install Composer Dependencies

Download the vendor packages via composer:

    $> composer install

This will create a `vendor` directory with all of the project's PHP
dependencies.

## Run the Sync

Before you begin, you can run `./sync --help` to see a list of what options you
have. Below is an explanation of the options you can specify when running this
script:

* `--update | -u`

  Updates the database by running the migration scripts in `db/`. These scripts
  will only run once so you can run this as many times as you'd like.

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

* `--help | -h`

  Print the help message.

To get started, run:

    $> ./sync

And follow the onscreen instructions!

## Using an Init Script or Supervisor

If you'd like to run this sync script in the background all the time, then it
is recommended to use some sort of supervisor or watchdog program to monitor if
the script fails for any reason.

@todo -- Include example SysV Init script and SystemD unit file
