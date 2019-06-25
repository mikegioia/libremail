# LibreMail

> **Under Active Development**
>
> [`Sync`](sync) is ready to use!

This project consists of three parts:

1. [IMAP to SQL Syncing Engine](#1-imap-to-sql-syncing-engine)
2. [JavaScript-free HTML Email Client](#2-javascript-free-html-email-client)
3. Kanban-style Email Client

All of which are licensed under the GNU GPLv3. The goal of this project is to
provide a fully free, modern, and extremely usable email client as well as an
easy to use tool for storing your remote email in a local SQL database.

Additionally, I set out to create a Kanban-style email client to interact with
your email in a much more intuitive, card-based interface. Currently, part 1 is
finished and part 2 has started. Read below for more information about each
application.

### 1. IMAP to SQL Syncing Engine

![Ready](https://img.shields.io/badge/status-ready-brightgreen.svg?style=flat-square)

Both email clients in this project utilise the syncing engine provided in the
[`sync`](sync) app. This application is designed to continually archive emails
from any number of IMAP servers and accounts. The data is saved in a format
outlined in the [`DATA_FORMAT.md`](DATA_FORMAT.md) file. Any application that
saves data in the format outlined in that document can be used, but the
[`sync`](sync) app here is a PHP version that you can use.

Please see the `sync` directory for full documentation on setting up the sync
engine as a stand-alone, i.e. connecting accounts, populating test data, and
the different ways with which you can run the application continuously
(supervisor, cron, etc).

### 2. JavaScript-free HTML Email Client

![Very Soon](https://img.shields.io/badge/status-very%20soon-green.svg?style=flat-square)

One of the primary goals of this project is to create a web-based email client
that is completely free of JavaScript or browser code. Modern web development
has fallen into a pattern of using JavaScript to do everything. It can be very
advantageous, from a security and privacy perspective, to disable JavaScript for
any email web applications. Having a mail client that works without JavaScript
can be a huge blessing!

It's recommended and encouraged to disable JavaScript when using LibreMail. This
can avoid a very large class of security vulnerabilities. While most problems
are encountered upon clicking external links or downloading and opening files,
disabling JavaScript for LibreMail can prevent malicious activity from other
websites you have open, browser extensions, and more.


Web applications and pages can not only function, but thrive in a JavaScript-
free environment. The [`webmail`](webmail) application in this project provides
a rich GMail-style interface for interacting with the local email saved in a SQL
database from the [`sync`](sync) app (this is a pre-requisite). It's
mobile-friendly, extremely light-weight, and provides almost full parity to
GMail's email client.

### 3. Kanban-style Email Client

![Not Started](https://img.shields.io/badge/status-not%20started-lightgrey.svg?style=flat-square)

The final goal of LibreMail is to create a modern and safe email client that
works _with_ and _for_ the user, not against them. The Kanban client is meant to
help organize and manage email for anyone who thinks of email as a "todo".
