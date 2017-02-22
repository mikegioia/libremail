# IMAP Sync

This application synchronises IMAP accounts to a local MySQL database. You can
use this as a standalone method to periodically download your email, or you can
use it with the two clients bundled with this tool.

![Sync Web Screenshot](http://mikegioia.github.io/libremail/images/sync_web_screenshot.png)

> Web Client admin interface


![Sync Screenshot](http://mikegioia.github.io/libremail/images/sync_screenshot.png)

> Command Line interface

---

## Contents

- [Installation](https://github.com/mikegioia/libremail/tree/master/sync#installation)
    1. [Create Database](https://github.com/mikegioia/libremail/tree/master/sync#1-create-database)
    2. [Configure the Application](https://github.com/mikegioia/libremail/tree/master/sync#2-configure-the-application)
    3. [Install Composer Dependencies](https://github.com/mikegioia/libremail/tree/master/sync#5-install-composer-dependencies)
    4. [Running the Diagnostic Tests](https://github.com/mikegioia/libremail/tree/master/sync#3-running-the-diagnostic-tests)
    5. [Running SQL Migration Scripts](https://github.com/mikegioia/libremail/tree/master/sync#4-running-sql-migration-scripts)
- [Run the IMAP Sync](https://github.com/mikegioia/libremail/tree/master/sync#run-the-sync)
- [Using an Init Script or Supervisor](https://github.com/mikegioia/libremail/tree/master/sync#using-an-init-script-or-supervisor)
- [Submitting Bugs](https://github.com/mikegioia/libremail/tree/master/sync#submitting-bugs)

## Installation

### 1. Create Database

To get started, make sure you have MariaDB or MySQL running and issue the
following command:

    MariaDB [(none)]> CREATE DATABASE `libremail`;

The database name is a configuration option, so if you'd like to change this to
be something other than `libremail`, make sure you
[update the config setting](https://github.com/mikegioia/libremail/tree/master/sync/doc/configuration.md#sql).

#### 1.1 `max_allowed_packet` (MariaDB/MySQL)

Because of the amount of data that may be written to SQL (long email message
text) a configuration setting needs to be enabled for MySQL databases allowing a
larger packet size to be sent in a query. To do this, add the following line to
your SQL config file:

    [mysqld]
    max_allowed_pack = 500M

You don't have to use 500MB as your packet size, but anything 16MB or higher is
advised.

### 2. Configure the Application

Configuration options are saved in `config/default.ini`. Do not modify this file
or anything in it. **All of your changes should go into `config/local.ini`** and
should be in the [INI file format](https://en.wikipedia.org/wiki/INI_file), just
like the default file. If you would like to make changes to `config/local.ini`
but not ever commit them, run the following:

    $> git update-index --assume-unchanged config/local.ini

View all possible configuration options:
[Configuration Options](https://github.com/mikegioia/libremail/tree/master/sync/doc/configuration.md#configuration-options)

### 3. Install Composer Dependencies

Download the vendor packages via
[Composer](https://getcomposer.org):

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

If you'd like, you can run `./sync --help` to see a list of what options you
have. View all possible configuration options and their details:
[Configuration Options](https://github.com/mikegioia/libremail/tree/master/sync/doc/sync-options.md#sync-options)

To use the **CLI** tool (debug mode), run:

    $> ./sync

and follow the onscreen instructions.

To use the **Web** tool, run:

    $> ./libremail

This tool will run silently and write to `/logs`. Open your browser and go to
[localhost:9898](http://localhost:9898) to view the Web client.

## Using an Init Script or Supervisor

If you'd like to run this sync script in the background all the time, then it
is recommended to use some sort of supervisor or watchdog program to monitor if
the script fails for any reason. Here are some guides for Linux and MacOS:

 - [SystemD](https://github.com/mikegioia/libremail/tree/master/sync/doc/systemd.md#systemd)
 - [SysV Init](https://github.com/mikegioia/libremail/tree/master/sync/doc/sysv-init.md#sysv-init)
 - [MacOS LaunchD](https://github.com/mikegioia/libremail/tree/master/sync/doc/macos-launchd.md#macos-launchd)

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