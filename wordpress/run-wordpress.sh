#!/usr/bin/env bash
set -euo pipefail

sync_item() {
  local source_path="$1"
  local target_path="$2"

  if [ ! -e "$source_path" ]; then
    return
  fi

  rm -rf "$target_path"
  mkdir -p "$(dirname "$target_path")"
  cp -a "$source_path" "$target_path"
  chown -R www-data:www-data "$target_path" || true
}

mkdir -p \
  /var/www/html/wp-content/plugins \
  /var/www/html/wp-content/themes \
  /var/www/html/wp-content/mu-plugins

sync_item /opt/kuchnia-twist/wp-content/plugins/kuchnia-twist-publisher /var/www/html/wp-content/plugins/kuchnia-twist-publisher
sync_item /opt/kuchnia-twist/wp-content/themes/kuchnia-twist /var/www/html/wp-content/themes/kuchnia-twist
sync_item /opt/kuchnia-twist/wp-content/mu-plugins/kuchnia-twist-bootstrap.php /var/www/html/wp-content/mu-plugins/kuchnia-twist-bootstrap.php
sync_item /opt/kuchnia-twist/ads.txt /var/www/html/ads.txt
sync_item /opt/kuchnia-twist/.htaccess /var/www/html/.htaccess

exec apache2-foreground
