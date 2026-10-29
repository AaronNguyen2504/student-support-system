#!/bin/sh
# Apache runs as www-data and must be able to write uploaded files and logs
chmod -R a+rwX /var/www/html/uploads /var/www/html/logs
exec apache2-foreground
