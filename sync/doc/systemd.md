### SystemD

> This doc will show you how to get LibreMail running automatically whenever
> you start your computer, for Linux installations using SystemD.

Create a file `libremail.service` and place it in `~/.config/systemd/user/`.
Make sure to update the paths to the files in PIDFile and ExecStart.

```
[Unit]
Description=LibreMail IMAP Syncing Engine
Documentation=https://github.com/mikegioia/libremail
After=network.target network-online.target

[Service]
Type=simple
PIDFile=/path/to/home/.config/libremail/libremail.pid
ExecStart=/path/to/LibreMail/sync/libremail
ExecStop=/bin/kill -15 $MAINPID
Restart=always

[Install]
WantedBy=default.target
```

To enable and activate the service, run:

    $> systemctl --user daemon-reload
    $> systemctl --user start libremail