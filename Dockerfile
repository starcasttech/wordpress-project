FROM wordpress:php8.2-apache

# Install additional PHP extensions if needed
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Copy WordPress files
COPY . /var/www/html/

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Write entrypoint wrapper inline — runs MPM fix at container start, before Apache launches
RUN printf '#!/bin/bash\nset -e\nrm -f /etc/apache2/mods-enabled/mpm_*.conf /etc/apache2/mods-enabled/mpm_*.load\nln -sf /etc/apache2/mods-available/mpm_prefork.conf /etc/apache2/mods-enabled/mpm_prefork.conf\nln -sf /etc/apache2/mods-available/mpm_prefork.load /etc/apache2/mods-enabled/mpm_prefork.load\nexec /usr/local/bin/docker-entrypoint.sh "$@"\n' \
    > /usr/local/bin/entrypoint-mpm-fix.sh \
    && chmod +x /usr/local/bin/entrypoint-mpm-fix.sh

# Expose port
EXPOSE 80

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/ || exit 1

# Override entrypoint: fix MPM at every container start, then run WordPress entrypoint
ENTRYPOINT ["/usr/local/bin/entrypoint-mpm-fix.sh"]
CMD ["apache2-foreground"]
