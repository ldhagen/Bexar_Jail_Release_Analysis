#!/bin/bash
set -e

# Ensure proper permissions
chown -R www-data:www-data /var/www/html/data

# Execute the main command
exec "$@"
