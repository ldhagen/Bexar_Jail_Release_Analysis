FROM php:8.2-apache

# Install additional PHP extensions
RUN docker-php-ext-install opcache

# Configure PHP memory and execution limits
RUN echo "memory_limit = 512M" > /usr/local/etc/php/conf.d/memory-limit.ini && \
    echo "max_execution_time = 600" >> /usr/local/etc/php/conf.d/memory-limit.ini && \
    echo "max_input_time = 600" >> /usr/local/etc/php/conf.d/memory-limit.ini && \
    echo "upload_max_filesize = 100M" >> /usr/local/etc/php/conf.d/memory-limit.ini && \
    echo "post_max_size = 100M" >> /usr/local/etc/php/conf.d/memory-limit.ini

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY index.php /var/www/html/
COPY docker-entrypoint.sh /usr/local/bin/

# Create data directory and set permissions
RUN mkdir -p /var/www/html/data && \
    chown -R www-data:www-data /var/www/html && \
    chmod +x /usr/local/bin/docker-entrypoint.sh

# Expose HTTP port
EXPOSE 80

# Use custom entrypoint
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]