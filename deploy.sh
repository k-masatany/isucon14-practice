#!/bin/bash

set -eu -o pipefail
trap 'EXIT_STATUS="${?}"; echo "ERROR: file = "${0}", line no = "${LINENO}", exit status = "${EXIT_STATUS}"" >&2; exit "${EXIT_STATUS}"' ERR

DATE_STRING=$(date +"%Y%m%d%H%M%S")

# PHP-FPM
systemctl restart --now isuride-php.service

# Nginx
cp ./etc/nginx/nginx.conf /etc/nginx/nginx.conf
cp /var/log/nginx/access.log /var/log/nginx/access.log.${DATE_STRING}
cp /var/log/nginx/error.log /var/log/nginx/error.log.${DATE_STRING}
echo "" > /var/log/nginx/access.log
echo "" > /var/log/nginx/error.log
systemctl restart nginx