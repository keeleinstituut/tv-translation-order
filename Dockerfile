# syntax = docker/dockerfile:1.4.0

FROM composer:latest as composer
FROM php:8.5-fpm-alpine3.23 as runtime

COPY --from=composer /usr/bin/composer /usr/bin/composer

ENV APP_ROOT /app
ENV WEB_ROOT /var/www/html
ENV ENTRYPOINT /entrypoint.sh

RUN apk add --no-cache libpq libpq-dev libsodium libsodium-dev linux-headers && \
    docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql && \
    docker-php-ext-install pgsql pdo pdo_pgsql sodium pcntl sockets exif fileinfo && \
    apk del libpq-dev libsodium-dev linux-headers

COPY ./application ${APP_ROOT}

WORKDIR $APP_ROOT

RUN composer install --no-dev --optimize-autoloader --no-interaction && \
    composer clear-cache && \
    rm -f /usr/bin/composer

RUN chown -R root:root ${APP_ROOT} && \
    chown -R www-data:www-data ${APP_ROOT}/storage ${APP_ROOT}/bootstrap/cache && \
    find ${APP_ROOT} -type f -exec chmod 644 {} \; && \
    find ${APP_ROOT} -type d -exec chmod 755 {} \; && \
    chmod -R 775 ${APP_ROOT}/storage ${APP_ROOT}/bootstrap/cache

RUN rm -rf ${WEB_ROOT} && \
        ln -s ${APP_ROOT}/public ${WEB_ROOT}

RUN apk add --no-cache nginx supervisor curl

RUN sed -i 's/^\(\[supervisord\]\)$/\1\nnodaemon=true\nuser=root/' /etc/supervisord.conf && \
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
    echo 'max_file_uploads = 50' >> /usr/local/etc/php/conf.d/my-php.ini && \
    echo 'memory_limit = 512M' >> /usr/local/etc/php/conf.d/my-php.ini

RUN ln -s /dev/stdout /var/log/nginx/access.log && \
    ln -s /dev/stderr /var/log/nginx/error.log

RUN <<EOF cat > /etc/nginx/http.d/default.conf
server {
    listen 80;
    index index.php index.html;
    root /var/www/html;

    error_log  /var/log/nginx/error.log;
    access_log /var/log/nginx/access.log;
    client_max_body_size 100M;

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
user=www-data
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
user=www-data
numprocs=1
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes = 0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0

[program:laravel-classifiers-sync]
process_name=%(program_name)s
command=php /app/artisan amqp:consume tv-translation-order.classifier-value # see application/config/amqp.php
autostart=true
autorestart=true
user=www-data
numprocs=1
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes = 0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0

[program:laravel-institutions-sync]
process_name=%(program_name)s
command=php /app/artisan amqp:consume tv-translation-order.institution  # see application/config/amqp.php
autostart=true
autorestart=true
user=www-data
numprocs=1
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes = 0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0

[program:laravel-institution-users-sync]
process_name=%(program_name)s
command=php /app/artisan amqp:consume tv-translation-order.institution-user  # see /application/config/amqp.php
autostart=true
autorestart=true
user=www-data
numprocs=1
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes = 0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0
EOF

RUN <<EOF cat > ${ENTRYPOINT}
#!/bin/sh
set -e
# Run artisan commands as www-data user
echo "Optimize for loading in runtime variables"
su www-data -s /bin/sh -c "php artisan optimize --except view:cache"

echo "Consolidating schemas to public (if needed)"
su www-data -s /bin/sh -c "php artisan db:consolidate-schemas"

echo "Running migrations"
su www-data -s /bin/sh -c "php artisan migrate --force"

echo "Setup AMQP queues"
su www-data -s /bin/sh -c "php artisan amqp:setup"

echo "Generating OpenAPI document"
su www-data -s /bin/sh -c "php artisan l5-swagger:generate"

echo "Synchronizing entities from external services"
su www-data -s /bin/sh -c "php artisan sync:all"

echo "Deploying BPMN templates to Camunda"
su www-data -s /bin/sh -c "php artisan workflow:deploy"

echo "Start application processes using supervisord..."
exec "\$@"
EOF

RUN chmod +x ${ENTRYPOINT}

# Health check scripts
RUN <<EOF cat > /healthz-startup.sh
#!/bin/sh
set -e

if ! pgrep -x supervisord > /dev/null; then
    exit 1
fi

REQUIRED_PROCESSES="php-fpm nginx"
for process in \$REQUIRED_PROCESSES; do
    if ! supervisorctl status "\$process" 2>/dev/null | grep -q RUNNING; then
        exit 1
    fi
done

# Check that entrypoint commands are NOT running
if pgrep -f "php artisan migrate" > /dev/null; then
    exit 1
fi

if pgrep -f "php artisan amqp:setup" > /dev/null; then
    exit 1
fi

if pgrep -f "php artisan sync:all" > /dev/null; then
    exit 1
fi

if pgrep -f "php artisan workflow:deploy" > /dev/null; then
    exit 1
fi

exit 0
EOF

RUN <<EOF cat > /healthz-ready.sh
#!/bin/sh
set -e

if ! pgrep -x supervisord > /dev/null; then
    exit 1
fi

REQUIRED_PROCESSES="php-fpm nginx laravel-worker laravel-scheduler laravel-classifiers-sync laravel-institutions-sync laravel-institution-users-sync"
for process in \$REQUIRED_PROCESSES; do
    if ! supervisorctl status "\$process" 2>/dev/null | grep -q RUNNING; then
        exit 1
    fi
done

if ! curl -f -s --max-time 2 http://localhost/healthz > /dev/null 2>&1; then
    exit 1
fi

exit 0
EOF

RUN <<EOF cat > /healthz-live.sh
#!/bin/sh
set -e

if ! pgrep -x supervisord > /dev/null; then
    exit 1
fi

REQUIRED_PROCESSES="php-fpm nginx laravel-worker laravel-scheduler laravel-classifiers-sync laravel-institutions-sync laravel-institution-users-sync"
for process in \$REQUIRED_PROCESSES; do
    status=\$(supervisorctl status "\$process" 2>/dev/null | awk '{print \$2}')
    if [ "\$status" = "FATAL" ]; then
        exit 1
    fi
done

exit 0
EOF

RUN chmod +x /healthz-startup.sh /healthz-ready.sh /healthz-live.sh

CMD ["supervisord", "-c", "/etc/supervisord.conf"]
EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]