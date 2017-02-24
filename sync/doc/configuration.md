## Configuration Options

Here are the configuration options you can overwrite in `config/local.ini`. Please follow the same format; your configuration file would look like this:

```
[app]

memory="512M"
```

If you just wanted to increase the memory of your application. This is a good idea if you have large file attachments.

#### [app]

* `memory`

  String, defaults to `"256M"`. This is the PHP memory size limit for the
  sync script. The application will use whatever your default memory limit
  is for your PHP install, but you can override that here. It's best to
  leave this at 256 MB as a minimum, otherwise some of your emails may not
  download. Emails with large (or many) attachments require an excess of
  memory to parse in PHP, due do how large strings are parsed. I set mine
  as high as 1 GB but 256 MB has been found to get all attachments.

* `db[sleep_minutes]`

  Integer, defaults to `10`. This is the number of monutes the script will
  sleep after a non-recoverable database error is encountered, before
  starting back up again.

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

#### [daemonlog]

* `level[cli]`

  Integer, defaults to `7`. See the `[log]` section above for more info.
  This corresponds to the log file written by the master `libremail`
  process.

* `level[file]`

  Integer, defaults to `7`. See the `[log]` section above for more info.

* `stacktrace`

  Defaults to `true`. See the `[log]` section above for more info.

* `path`

  Defaults to `logs/daemon.log`. See the `[log]` section above for more
  info.

#### [server]

* `port`

  The port for accessing the Web client. This defaults to `9898`, so to
  access the client you would navigate to
  [http://localhost:9898]([http://localhost:9898]).

#### [serverlog]

* `level[cli]`

  Integer, defaults to `7`. See the `[log]` section above for more info.
  This corresponds to the log file written by the web server process.

* `level[file]`

  Integer, defaults to `5`. See the `[log]` section above for more info.

* `stacktrace`

  Defaults to `true`. See the `[log]` section above for more info.

* `path`

  Defaults to `logs/server.log`. See the `[log]` section above for more
  info.