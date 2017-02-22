## MacOS LaunchD

> This doc will show you how to get LibreMail running automatically whenever
> you start your computer, for MacOS installations using LaunchD.

Create a file at `~/Library/LaunchAgents/com.user.libremail.plist` with the
following contents. Please make sure to change the path to your actual file
location for the program.

```
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple Computer//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
   <key>Label</key>
   <string>com.user.libremail</string>
   <key>Program</key>
   <string>/path/to/LibreMail/sync/libremail</string>
   <key>RunAtLoad</key>
   <true/>
</dict>
</plist>
```

To enable and activate the service, run:

    $> launchctl load ~/Library/LaunchAgents/com.user.libremail.plist
