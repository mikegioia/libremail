## SysV Init

> This doc will show you how to get LibreMail running automatically whenever
> you start your computer, for Linux installations using Sys-V Init.

Create a file at `/etc/init.d/libremail` with executable permissions owned
by `root:root`. Make sure to update the paths to the files in EXEC and
possibly PIDFILE. Also change the user and group in RUNAS.

```
#!/bin/sh

### BEGIN INIT INFO
# Provides:           libremail
# Required-Start:
# Required-Stop:
# Default-Start:      2 3 4 5
# Default-Stop:       0 1 6
# Short-Description:  libremail
# Description: LibreMail IMAP Syncing Engine
### END INIT INFO

. /lib/lsb/init-functions

OPTS="-b"
RUNAS="user:group"
PIDFILE=/var/run/libremail.pid
EXEC=/path/to/LibreMail/sync/libremail

case "$1" in
    start)
        if [ -f $PIDFILE ]
        then
            log_begin_msg "$PIDFILE exists, process is already running or crashed"
        else
            log_begin_msg "Starting LibreMail..."
            start-stop-daemon --start --chuid $RUNAS --pidfile $PIDFILE \
                --make-pidfile --background --exec $EXEC -- $OPTS
        fi
        log_end_msg 0
        ;;
    stop)
        log_begin_msg "Stopping LibreMail"
        if [ ! -f $PIDFILE ]
        then
            echo "$PIDFILE does not exist, process is not running"
        else
            PID=$(cat $PIDFILE)
            start-stop-daemon --stop --quiet --pidfile $PIDFILE
            rm -f $PIDFILE
        fi
        log_end_msg 0
        ;;
    *)
        echo "Please use start or stop as first argument"
        ;;
esac
```
