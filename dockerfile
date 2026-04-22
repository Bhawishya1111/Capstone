FROM node:20-alpine AS frontend-builder

WORKDIR /app

COPY package*.json ./
RUN npm install

COPY resources ./resources
COPY public ./public
COPY vite.config.* ./
COPY postcss.config.* ./
COPY tailwind.config.* ./

RUN npm run build

FROM php:8.3-cli-bookworm

WORKDIR /var/www/html
ARG TARGETARCH

RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    git \
    perl \
    libjson-perl \
    libnet-ssleay-perl \
    libio-socket-ssl-perl \
    libxml-writer-perl \
    unzip \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    default-mysql-client \
    && docker-php-ext-install pdo_mysql mbstring bcmath pcntl zip \
    && rm -rf /var/lib/apt/lists/*

RUN git clone --depth=1 https://github.com/sullo/nikto.git /opt/nikto \
    && ln -sf /opt/nikto/program/nikto.pl /usr/local/bin/nikto \
    && chmod +x /opt/nikto/program/nikto.pl /usr/local/bin/nikto

RUN if [ "${TARGETARCH}" = "arm64" ]; then export NUCLEI_ARCH="arm64"; else export NUCLEI_ARCH="amd64"; fi \
    && curl -fsSL "https://github.com/projectdiscovery/nuclei/releases/download/v3.8.0/nuclei_3.8.0_linux_${NUCLEI_ARCH}.zip" -o /tmp/nuclei.zip \
    && unzip /tmp/nuclei.zip -d /tmp \
    && mv /tmp/nuclei /usr/local/bin/nuclei \
    && chmod +x /usr/local/bin/nuclei \
    && rm -f /tmp/nuclei.zip /tmp/LICENSE.md /tmp/README.md

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

COPY . .
COPY --from=frontend-builder /app/public/build ./public/build

RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

COPY docker/start.sh /usr/local/bin/start-container
RUN chmod +x /usr/local/bin/start-container

EXPOSE 8000

ENTRYPOINT ["start-container"]
