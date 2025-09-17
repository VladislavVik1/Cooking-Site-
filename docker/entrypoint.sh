set -e

: "${PORT:=10000}"        
mkdir -p /run/php /run/nginx

mkdir -p /data/uploads
chown -R www-data:www-data /data/uploads

envsubst '$PORT' < /etc/nginx/conf.d/default.conf.template > /etc/nginx/conf.d/default.conf

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
