# Local / Codespaces runtime: PHP 8.2 + Apache, same setup as XAMPP (mysqli, .htaccess enabled)
FROM php:8.2-apache
RUN docker-php-ext-install mysqli \
 && a2enmod headers expires \
 && sed -ri 's/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf
COPY start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh
CMD ["start.sh"]
