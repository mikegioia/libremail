# LibreMail

This project encompasses three parts:

1. JavaScript-less HTML Email Client
2. Kanban-style Email Client
3. IMAP to SQL Syncing Engine

All of which are licensed under the GNU GPLv3. The goal of this project is to
provide a fully free, modern, and extremely usable email client as well as an
easy to use tool for storing your remote email in a local SQL database.

Additionally, I set out to create a Kanban-style email client to interact with
your email in a much more intuitive card-based interface.

Currently parts 1 and 3 are under active development, with part 2 coming later.
Read below for more information about each application.

### 1. JavaScript-less HTML Email Client

As both a case-study and for usability's sake, one of the primary goals of this
project is to create a web-based email client and server application that's
completely devoid of JavaScript. Modern web development has fallen into a
pattern of not only requiring JavaScript to function, but also bundling large
script files on every page request.

This has implications for anyone who disables JavaScript by default, anyone who
may not want to run JavaScript on their Internet connection, or anyone who may
be using a mobile or antique browser, or a browser over a slow Internet
connection.

Web applications and pages can not only function, but thrive in a JavaScript-
less environment. The `webmail` application in this project provides a rich
GMail-style interface for interacting with the local email saved to a SQL
database from the `sync` app. It's mobile-friendly, extremely light-weight, and
provides almost full parity to GMail's email client.

Please see the `webmail` directory for full documentation on running the app.
This requires your remote mail to be stored in a local SQL database. The `sync`
app does this for you, but you're free to use anything that saves data in the
way outlined in `DATAFORMAT.md`.

### 2. Kanban-style Email Client

TBD

### 3. IMAP to SQL Sycning Engine

Both email clients in this project utilise the syncing engine provided in the
`sync` app. This application is designed to continually archive emails from any
number of IMAP servers and accounts. The data is saved in a format outlined in
the `DATAFORMAT.md` file. Any application that saves data in the format outlined
in that document can be used, but the `sync` app here is a PHP version that you
can use.

Please see the `sync` directory for full documentation on setting up the sync
engine, connecting accounts, populating test data, and the different ways with
which you can run the application continuously (supervisor, cron, etc).