# syntax=docker/dockerfile:1.7
# Web image: the built SPA served by nginx, which also fronts the API.

FROM node:20-bookworm-slim AS spa-builder
WORKDIR /app
COPY apps/web/package*.json ./
RUN npm ci --no-audit --no-fund
COPY apps/web ./
ARG APP_VERSION=dev
ENV VITE_APP_VERSION=${APP_VERSION}
RUN npm run build

FROM nginx:1.27-alpine AS web
COPY --from=spa-builder /app/dist /usr/share/nginx/html
COPY docker/nginx/default.conf.template docker/nginx/default-ssl.conf.template /etc/nginx/templates/
COPY docker/nginx/api-locations.conf docker/nginx/spa-locations.conf docker/nginx/security-headers.conf /etc/nginx/
COPY docker/web/start.sh /usr/local/bin/start-web
RUN chmod +x /usr/local/bin/start-web && mkdir -p /var/www/certbot

EXPOSE 80 443
HEALTHCHECK --interval=30s --timeout=5s --retries=5 CMD wget -qO- http://127.0.0.1/index.html >/dev/null 2>&1 || exit 1

CMD ["/usr/local/bin/start-web"]
