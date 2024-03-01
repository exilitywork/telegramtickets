readme under development..

Non webhook instalation
For update use system cron:
* * * * * sleep  0 ; /usr/bin/php8.1 /var/www/glpi/plugins/telegramtickets/utils/listen.php >> /var/log/cronjob.log 2>&1
* * * * * sleep 10 ; /usr/bin/php8.1 /var/www/glpi/plugins/telegramtickets/utils/listen.php >> /var/log/cronjob.log 2>&1
* * * * * sleep 20 ; /usr/bin/php8.1 /var/www/glpi/plugins/telegramtickets/utils/listen.php >> /var/log/cronjob.log 2>&1
* * * * * sleep 30 ; /usr/bin/php8.1 /var/www/glpi/plugins/telegramtickets/utils/listen.php >> /var/log/cronjob.log 2>&1
* * * * * sleep 40 ; /usr/bin/php8.1 /var/www/glpi/plugins/telegramtickets/utils/listen.php >> /var/log/cronjob.log 2>&1
* * * * * sleep 50 ; /usr/bin/php8.1 /var/www/glpi/plugins/telegramtickets/utils/listen.php >> /var/log/cronjob.log 2>&1