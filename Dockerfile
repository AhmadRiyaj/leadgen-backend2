FROM php:8.2-apache

# Install Python
RUN apt-get update && apt-get install -y python3 python3-pip

# Copy project files
COPY . /var/www/html/

# Enable Apache rewrite
RUN a2enmod rewrite

# Copy apache config
COPY apache.conf /etc/apache2/sites-available/000-default.conf

# Permissions
RUN chmod -R 755 /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]