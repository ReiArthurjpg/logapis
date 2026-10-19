FROM php:8.2-apache

# Instala extensões necessárias para MySQL PDO
RUN docker-php-ext-install pdo pdo_mysql

# Habilita o mod_rewrite do Apache
RUN a2enmod rewrite

# Ajusta o DocumentRoot do Apache para a pasta public
ENV APACHE_DOCUMENT_ROOT /var/www/html/public

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Permite override no diretório
RUN echo '<Directory /var/www/html/public>\n\
    Options Indexes FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' >> /etc/apache2/apache2.conf

WORKDIR /var/www/html

COPY . /var/www/html

EXPOSE 80
