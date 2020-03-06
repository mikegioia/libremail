### CAVE CANEM

This is under active development but very close to being done! Read below if
you want to check it out :D

I'll soon be using this as my daily driver, which should shore up a lot of bugs.

#### Web Client Interface

![Webmail Client Screenshot](http://mikegioia.github.io/libremail/images/webmail_screenshot.png)

#### Status

 - [x] ~Inbox and folder message display~
 - [x] ~Message thread view~
 - [x] ~Archive, mark read/unread, delete, move (label), copy,
       star/unstar actions for messages and threads~
 - [x] ~Edit account credentials and settings~
 - [x] ~Sync all actions with IMAP/SMTP server~
 - [x] ~Search and display search results~
 - [ ] Compose new message *[Started]*
 - [ ] Reply to and forward messages *[Started]*

#### Installation (Developers only)

1. Copy `config/nginx.conf` to your nginx config directory. Make sure
   to update the `root` directive to point to the `www` folder. Update
   log paths and other directives as necessary!
2. Copy `.env.example` to `.env` and update the database settings. Feel
   free to change any other settings, like the app's URL, too.

This application is set to run over localhost on port 9899 by default.

#### Software Licenses

The Web Client includes the following 3rd party packages:

1. Fonts
    1. [Open Sans Web Font](https://en.wikipedia.org/wiki/Open_Sans),
       _Steve Matteson_
        * [Apache License Version 2.0](https://www.apache.org/licenses/LICENSE-2.0)
    2. [Font Awesome 5 Free Web Font](https://fontawesome.com), _Fort Awesome_
        * SVG Icons — [CC BY 4.0 License](https://creativecommons.org/licenses/by/4.0/)
        * Web and Desktop Fonts — [SIL OFL 1.1 License](https://scripts.sil.org/OFL)
    3. [Fira Code Font](https://en.wikipedia.org/wiki/Fira_Sans#Fira_Code),
       _Mozilla Foundation_
        * [SIL OFL 1.1 License](https://scripts.sil.org/OFL)
    4. [Nanum Gothic Coding Font](https://en.wikipedia.org/wiki/Nanum_font),
       _Sandoll Communication_
        * [SIL OFL 1.1 License](https://scripts.sil.org/OFL)
2. CSS
    1. [Skeleton CSS Boilerplate](https://github.com/dhg/Skeleton),
       _Dave Gamache_
        * [MIT License](https://opensource.org/licenses/MIT)
3. PHP
    1. [Linkify](https://github.com/misd-service-development/php-linkify),
       _MISD Service Development, University of Cambridge_
        * [MIT License](https://opensource.org/licenses/MIT)
    2. [HTML Purifier](http://htmlpurifier.org), _Edward Z. Yang_
        * [MIT License](https://opensource.org/licenses/MIT)
    3. [PDO](https://github.com/ParticleBits/pdo), _Mike Gioia, ParticleBits_
        * [MIT License](https://opensource.org/licenses/MIT)
    4. [Zend Mail](https://github.com/zendframework/zend-mail),
       _Zend Technologies USA, Inc._
        * [BSD 3-Clause](https://opensource.org/licenses/BSD-3-Clause)
    5. [Zend Escaper](https://github.com/zendframework/zend-escaper),
       _Zend Technologies USA, Inc._
        * [BSD 3-Clause](https://opensource.org/licenses/BSD-3-Clause)
    6. [Parsedown](https://parsedown.org), _Emanuil Rusev_
        * [MIT License](https://opensource.org/licenses/MIT)
