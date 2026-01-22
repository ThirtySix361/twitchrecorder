#!/bin/bash

if [ -n "$USER" ] && [ -n "$PASS" ]; then
htpasswd -cb /home/twitchrecorder/.htpasswd "$USER" "$PASS"
echo '<Directory /var/www/html>
    AuthType Basic
    AuthName "Protected"
    AuthUserFile /home/twitchrecorder/.htpasswd
    Require valid-user
</Directory>' >> /etc/apache2/apache2.conf
fi

su -s /bin/bash twitchrecorder -c '
    cd /home/twitchrecorder/;
    for file in archive/*.pid; do
        [ -f "$file" ] || continue;
        channel=$(basename "$file" .pid);
        pid=$(bash record.sh "$channel" >> /tmp/"$channel".log 2>&1 & echo $!);
        echo "$pid" > "archive/$channel.pid";
    done
'

tail -F /var/log/apache2/error.log &
tail -F /var/log/apache2/access.log &

/usr/sbin/apachectl -D FOREGROUND
