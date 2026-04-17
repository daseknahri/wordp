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

php_upload_max_filesize="${PHP_UPLOAD_MAX_FILESIZE:-20M}"
php_post_max_size="${PHP_POST_MAX_SIZE:-40M}"
php_memory_limit="${PHP_MEMORY_LIMIT:-256M}"
php_max_file_uploads="${PHP_MAX_FILE_UPLOADS:-20}"
php_max_execution_time="${PHP_MAX_EXECUTION_TIME:-180}"
php_max_input_time="${PHP_MAX_INPUT_TIME:-180}"

cat > /usr/local/etc/php/conf.d/kuchnia-twist-upload-limits.ini <<EOF
upload_max_filesize = ${php_upload_max_filesize}
post_max_size = ${php_post_max_size}
memory_limit = ${php_memory_limit}
max_file_uploads = ${php_max_file_uploads}
max_execution_time = ${php_max_execution_time}
max_input_time = ${php_max_input_time}
EOF

sync_item /opt/kuchnia-twist/wp-content/plugins/kuchnia-twist-publisher /var/www/html/wp-content/plugins/kuchnia-twist-publisher
sync_item /opt/kuchnia-twist/wp-content/themes/kuchnia-twist /var/www/html/wp-content/themes/kuchnia-twist
sync_item /opt/kuchnia-twist/wp-content/mu-plugins/kuchnia-twist-bootstrap.php /var/www/html/wp-content/mu-plugins/kuchnia-twist-bootstrap.php
sync_item /opt/kuchnia-twist/ads.txt /var/www/html/ads.txt
sync_item /opt/kuchnia-twist/.htaccess /var/www/html/.htaccess

if [ -n "${ADSENSE_PUB_ID:-}" ]; then
  printf "google.com, %s, DIRECT, f08c47fec0942fa0\n" "${ADSENSE_PUB_ID}" > /var/www/html/ads.txt
elif [ -n "${ADSENSE_CLIENT_ID:-}" ]; then
  if [[ "${ADSENSE_CLIENT_ID}" == ca-pub-* ]]; then
    pub_id="pub-${ADSENSE_CLIENT_ID#ca-pub-}"
    printf "google.com, %s, DIRECT, f08c47fec0942fa0\n" "${pub_id}" > /var/www/html/ads.txt
  fi
fi

exec apache2-foreground
