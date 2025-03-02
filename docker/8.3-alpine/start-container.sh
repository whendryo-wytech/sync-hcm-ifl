#!/usr/bin/env bash

composer install --working-dir=/app
/usr/bin/supervisord -c /etc/supervisor/supervisord.conf
