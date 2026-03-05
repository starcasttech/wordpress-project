#!/bin/bash
set -e

# Fix Apache MPM at container start (before entrypoint runs a2enmod etc.)
# This runs AFTER any build-time changes and guarantees only prefork is loaded.
rm -f /etc/apache2/mods-enabled/mpm_*.conf /etc/apache2/mods-enabled/mpm_*.load
ln -sf /etc/apache2/mods-available/mpm_prefork.conf /etc/apache2/mods-enabled/mpm_prefork.conf
ln -sf /etc/apache2/mods-available/mpm_prefork.load /etc/apache2/mods-enabled/mpm_prefork.load

# Call the original WordPress Docker entrypoint
exec /usr/local/bin/docker-entrypoint.sh "$@"
