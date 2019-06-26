### CAVE CANEM

This is under active development but very close to being done! Read below if
you want to check it out :D

I'll soon be using this as my daily driver, which should shore up a lot of bugs.

#### Web Client Admin Interface

![Webmail Client Screenshot](http://mikegioia.github.io/libremail/images/webmail_screenshot.png)

#### Status

 - [x] ~Inbox and folder message display~
 - [x] ~Message thread view~
 - [x] ~Archive, mark read/unread, delete, move (label), copy,
       star/unstar actions for messages and threads.~
 - [x] ~Edit account credentials and settings~
 - [x] ~Sync all actions with IMAP/SMTP server~
 - [x] Search and display search results
 - [ ] Compose new message
 - [ ] Reply to and forward messages

#### Installation (Developers only)

1. Copy `config/nginx.conf` to your nginx config directory. Make sure
   to update the `root` directive to point to the `www` folder. Update
   log paths and other directives as necessary!
2. Copy `.env.example` to `.env` and update the database settings. Feel
   free to change any other settings, like the app's URL, too.

This application is set to run over localhost on port 9899 by default.
