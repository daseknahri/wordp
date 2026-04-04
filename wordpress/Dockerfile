FROM wordpress:6.9.4-php8.3-apache

COPY wp-content /opt/kuchnia-twist/wp-content
COPY run-wordpress.sh /usr/local/bin/run-kuchnia-wordpress

RUN chmod +x /usr/local/bin/run-kuchnia-wordpress

CMD ["/usr/local/bin/run-kuchnia-wordpress"]
