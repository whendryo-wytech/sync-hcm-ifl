# Uses the latest Alpine Linux base image
FROM alpine:3.21

# Maintainer
MAINTAINER "Whendryo Nascimento <whendryo@wytech.com.br>"

# Set working directory
WORKDIR /app
RUN chmod -R 775 /app

# Update packages and install required dependencies
RUN apk update && \
    apk add --no-cache \
    build-base \
    gcompat \
    libaio-dev \
    g++ \
    gcc \
    make \
    zlib-dev \
    gcompat \
    libnsl \
    bash \
    curl \
    wget \
    supervisor \
    nginx \
    nano \
    php83 \
    php83-fpm \
    php83-mbstring \
    php83-xml \
    php83-tokenizer \
    php83-json \
    php83-gd \
    php83-curl \
    php83-openssl \
    php83-session \
    php83-zlib \
    php83-fileinfo \
    php83-dom \
    php83-xmlwriter \
    php83-xmlreader \
    php83-pdo \
    php83-pgsql \
    php83-pdo_pgsql \
    php83-redis \
    php83-pcntl \
    php83-posix \
    php83-pear \
    php83-dev \
    composer

# [Oracle] - Install Oracle Instant Client
RUN wget https://download.oracle.com/otn_software/linux/instantclient/2117000/instantclient-basic-linux.x64-21.17.0.0.0dbru.zip -O /tmp/instantclient-basic-linux.zip
RUN wget https://download.oracle.com/otn_software/linux/instantclient/2117000/instantclient-sdk-linux.x64-21.17.0.0.0dbru.zip  -O /tmp/instantclient-sdk-linux.zip
RUN mkdir -p /opt/oracle
RUN unzip -o /tmp/instantclient-basic-linux.zip -d /opt/oracle && mv /opt/oracle/instantclient_* /opt/oracle/instantclient
RUN rm -rf /opt/oracle/instantclient_*
RUN unzip -o /tmp/instantclient-sdk-linux.zip -d /opt/oracle && mv /opt/oracle/instantclient_*/sdk /opt/oracle/instantclient
RUN rm -rf /opt/oracle/instantclient_*
ENV PATH="/opt/oracle/instantclient:${PATH}"
ENV LD_LIBRARY_PATH="/opt/oracle/instantclient"
ENV ORACLE_HOME="/opt/oracle/instantclient"exit
RUN chmod -R 755 /opt/oracle/instantclient
RUN echo "instantclient,/opt/oracle/instantclient,shared" | pecl install oci8
#RUN echo "instantclient,/opt/oracle/instantclient,shared" | pecl install pdo_oci

# [Supervisor] - Configuration
RUN mkdir -p /etc/supervisor/conf.d /var/log/supervisor /var/run/supervisor
COPY supervisord.conf /etc/supervisor/supervisord.conf

# [Nginx] - Configuration
RUN mkdir -p /run/nginx
COPY nginx.conf /etc/nginx/nginx.conf

# [PHP] - Configuration
RUN sed -i 's/^post_max_size\s*=.*/post_max_size = 100M/' /etc/php83/php.ini
RUN sed -i 's/^upload_max_filesize\s*=.*/upload_max_filesize = 100M/' /etc/php83/php.ini
RUN sed -i 's/^variables_order\s*=.*/variables_order = EGPCS/' /etc/php83/php.ini
RUN sed -i 's/^hard_timeout\s*=.*/hard_timeout = 360/' /etc/php83/php.ini
RUN sed -i 's/^max_execution_time\s*=.*/max_execution_time = 360/' /etc/php83/php.ini
RUN sed -i 's/^default_socket_timeout\s*=.*/default_socket_timeout = 360/' /etc/php83/php.ini
RUN sed -i 's/^memory_limit\s*=.*/memory_limit = 512M/' /etc/php83/php.ini
RUN echo 'pcov.directory = .' >> /etc/php83/php.ini
RUN echo 'extension=oci8.so' >> /etc/php83/php.ini

# [Cron] - Configuration
RUN mkdir -p /var/log/cron
RUN echo '* * * * * /usr/bin/php /app/artisan schedule:run >> /dev/null 2>&1' | crontab -

# Optimizing Image
RUN apk del gcc musl-dev build-base make g++
RUN rm -rf /var/cache/apk/*
RUN rm -rf /tmp/*

# Command to be executed when the container starts
RUN mkdir -p /run/sail
COPY start-container.sh /run/sail/start-container.sh
RUN chmod +x /run/sail/start-container.sh
CMD ["sh","/run/sail/start-container.sh"]
#CMD ["tail", "-f", "/dev/null"]
