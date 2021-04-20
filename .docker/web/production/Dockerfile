FROM nginx:latest
COPY ./public /var/www/html/public
COPY .docker/web/production/default.conf /etc/nginx/conf.d/default.conf
COPY .docker/web/production/nginx.conf /etc/nginx/nginx.conf
