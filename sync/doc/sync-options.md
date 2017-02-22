## Sync Options

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

* `--diagnostics | -d`

  Runs through the diagnostic tests and reports any errors. Use this to start
  debugging any failures.

* `--folder <folder> | -f <folder>`

  Runs the sync script for the specified folder only, and then halts. This is
  useful when running in interactive mode to download one specific folder if
  you're testing something, or if you want to just have an external script
  download, say, your Inbox separately from the entire mailbox. The `<folder>`
  argument is the full name of the IMAP folder: 'INBOX', or 'Accounts/Support'
  are examples.

* `--create | -c`

  Run the sync script but only prompt for adding a new IMAP account.

* `--daemon | -e`

  Run the sync script in daemon mode. This supresses output to the console
  and instead writes everything to the log files.

* `--updatedb | -u`

  Updates the database by running the migration scripts in `db/`. These scripts
  will only run once so you can run this as many times as you'd like.

* `--sleep | -s`

  Run the sync script in sleep mode. This keeps the entire sync disabled and will
  not make any IMAP connections or do anything but respond to signals. It can be
  helpful for signal testing.

* `--help | -h`

  Print the help message.