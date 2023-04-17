# syntax = docker/dockerfile:1.4.0

FROM composer:latest as composer
FROM php:8.2.1-fpm-alpine3.17 as runtime

COPY --from=composer /usr/bin/composer /usr/bin/composer

ENV APP_ROOT /app
ENV WEB_ROOT /var/www/html
ENV ENTRYPOINT /entrypoint.sh

RUN apk add libpq-dev libsodium-dev
RUN docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql && \
        docker-php-ext-install pgsql \
                                pdo \
                                pdo_pgsql \
                                sodium \
                                pcntl

COPY --chown=www-data:www-data ./application ${APP_ROOT}
WORKDIR $APP_ROOT

RUN rm -rf ${WEB_ROOT} && \
        ln -s ${APP_ROOT}/public ${WEB_ROOT}

RUN composer install

RUN apk add nginx \
                supervisor

RUN sed -i 's/^\(\[supervisord\]\)$/\1\nnodaemon=true/' /etc/supervisord.conf && \
    sed -i 's/pm.max_children = 5/pm.max_children = 50/g' /usr/local/etc/php-fpm.d/www.conf && \
    sed -i 's/;pm.max_requests = 500/pm.max_requests = 200/g' /usr/local/etc/php-fpm.d/www.conf && \
    sed -i 's/;catch_workers_output = yes/catch_workers_output = yes/g' /usr/local/etc/php-fpm.d/www.conf && \
    sed -i 's/;decorate_workers_output = yes/decorate_workers_output = no/g' /usr/local/etc/php-fpm.d/www.conf && \
    sed -i 's/;emergency_restart_threshold = 0/emergency_restart_threshold = 10/g' /usr/local/etc/php-fpm.conf && \
    sed -i 's/;emergency_restart_interval = 0/emergency_restart_interval = 1m/g' /usr/local/etc/php-fpm.conf && \
    sed -i 's/;process_control_timeout = 0/process_control_timeout = 10s/g' /usr/local/etc/php-fpm.conf

# Set custom php.ini settings
RUN echo 'post_max_size = 512M' >> /usr/local/etc/php/conf.d/my-php.ini && \
    echo 'upload_max_filesize = 256M' >> /usr/local/etc/php/conf.d/my-php.ini && \
    echo 'memory_limit = 512M' >> /usr/local/etc/php/conf.d/my-php.ini

RUN <<EOF cat > /etc/nginx/http.d/default.conf
server {
    listen 80;
    index index.php index.html;
    error_log  /var/log/nginx/error.log;
    access_log /var/log/nginx/access.log;
    root /var/www/html;
    location ~ \.php\$ {
        try_files \$uri =404;
        fastcgi_split_path_info ^(.+\.php)(/.+)\$;
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_param PATH_INFO \$fastcgi_path_info;
    }
    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
        gzip_static on;
    }
}
EOF


RUN mkdir /etc/supervisor.d/
RUN <<EOF cat > /etc/supervisor.d/supervisor.ini
[program:php-fpm]
command=php-fpm
process_name=%(program_name)s
numprocs=1
autostart=true
autorestart=true
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes = 0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0

[program:nginx]
command=nginx -g 'daemon off;'
process_name=%(program_name)s
numprocs=1
autostart=true
autorestart=true
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes = 0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0

[program:laravel-worker]
process_name=%(program_name)s
command=php /app/artisan queue:work
autostart=true
autorestart=true
numprocs=1
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes = 0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0

[program:laravel-scheduler]
process_name=%(program_name)s
command=php /app/artisan schedule:work
autostart=true
autorestart=true
numprocs=1
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes = 0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0
EOF

RUN <<EOF cat > ${ENTRYPOINT}
#!/bin/sh
set -e

echo "Optimize for loading in runtime variables"
php artisan optimize

echo "Running migrations"
php artisan migrate --force

echo "Start application processes using supervisord..."
exec "\$@"
EOF

RUN chmod +x ${ENTRYPOINT}

CMD ["supervisord", "-c", "/etc/supervisord.conf"]
EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]