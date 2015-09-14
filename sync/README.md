# IMAP Sync

This application synchronises IMAP accounts to a local MySQL database. You can
use this as a standalone method to periodically download your email, or you can
use it with the two clients bundled with this tool.

## Installation

### 1. Create Database

To get started, make sure you have MariaDB or MySQL running and issue the
following command:

    > CREATE DATABASE `libremail`;

The database name is a configuration option, so if you'd like to change this to
be something other than `libremail`, feel free to use anything.

### 2. Configure the Application

Configuration options are saved in `config/default.ini`. **Do not modify this
file or anything in it**. All of your modifications should go into
`config/local.ini` and should be in the
[https://en.wikipedia.org/wiki/INI_file](INI file format), just like the default
file. Here's an explanation of the options you can overwrite:

#### [app]

`stacktrace`
Defaults to `true` but set to `false` if you want to suppress stack traces from
showing in the logs or the CLI.

`sync[wait_seconds]`
Integer, defaults to `10`. This is the number of seconds to wait before retrying
a failed action while syncing.

#### [log]

`level[cli]`
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

`level[file]`
Integer, defaults to `5`. Enter a number between `0` and `7` corresponding to
the minimum level you want to capture when logs are written to disk. This
happens when the app is running in the background.

`name`
String, any name for the application. Defaults to `libremail`.

`path`
Relative or absolute path for saving log files. This needs a filename at the end
which will be used as a stem for creating timestamped log files. Default value
is `logs/sync.log`.

#### [sql]

`database`
Name of the database, defaults to `libremail`.

`hostname`
Hostname for MySQL connection, defaults to `localhost`.

`port`
Port for MySQL connection, defaults to `3306`.

`username`
Username for MySQL connection, defaults to `root`.

`password`
Password for MySQL connection, defaults to `root`.

`charset`
Character set for MySQL connection, defaults to `utf8`.

#### [email]

`attachments[path]`
Relative or absolute path for saving email attachments. Defaults to a local
directory named `attachments`.

### 3. Running SQL Migration Scripts

Before you can start syncing, run the SQL database scripts:

    $> ./sync --update

This will create all the SQL tables and run any other database operations.

#### 4. Run the Sync

