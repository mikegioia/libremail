### CAVE CANEM

This is under active development and not ready for public use yet!
Read below of you want to check it out.

#### Status

 - [x] ~Inbox and folder message display~
 - [x] ~Message thread view~
 - [x] ~Archive, mark read/unread, delete, move (label), copy,
       star/unstar actions for messages and threads.~
 - [ ] Reply to and forward messages
 - [ ] Compose new message
 - [ ] Edit account credentials and settings
 - [ ] Search and display search results
 - [ ] Sync all actions with IMAP/SMTP server

#### Installation (Developers only)

1. Copy `config/nginx.conf` to your nginx config directory. Make sure
   to update the `root` directive to point to the `www` folder. Update
   log paths and other directives as necessary!
2. Copy `.env.example` to `.env` and update the database settings. Feel
   free to change any other settings, like the app's URL, too.

This application is set to run over localhost on port 9899 by default. 
