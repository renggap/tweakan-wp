server {
  listen 80;
  listen [::]:80;
  listen 443 ssl http2;
  listen [::]:443 ssl http2;
  {{ssl_certificate_key}}
  {{ssl_certificate}}
  server_name www.DOMAIN.id;
  return 301 https://www.DOMAIN.id$request_uri;
}

server {
  listen 80;
  listen [::]:80;
  listen 443 ssl http2;
  listen [::]:443 ssl http2;
  {{ssl_certificate_key}}
  {{ssl_certificate}}
  server_name www.DOMAIN.id www1.DOMAIN.id;
  {{root}}

  {{nginx_access_log}}
  {{nginx_error_log}}

  if ($scheme != "https") {
    rewrite ^ https://$host$uri permanent;
  }

  location ~ /.well-known {
    auth_basic off;
    allow all;
  }

  {{settings}}

  location ~/\.git {
    deny all;
  }

  location = /xmlrpc.php {
    deny all;
  }

  # Uncomment the following to exclude admin-ajax.php from basic auth if it breaks frontend functionality.
  #location ~* ^/wp-admin/admin-ajax\.php$ {
  #  auth_basic off;
  #}

  location ~/(wp-admin/|wp-login.php) {
    #auth_basic "Restricted Area";
    #auth_basic_user_file /home/site-user/.htpasswd;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $remote_addr;
    proxy_set_header X-Forwarded-Host $http_host;
    proxy_set_header Host $host;
    proxy_pass http://127.0.0.1:8080;
    proxy_max_temp_file_size 0;
    proxy_connect_timeout      7200;
    proxy_send_timeout         7200;
    proxy_read_timeout         7200;
    proxy_buffer_size          128k;
    proxy_buffers              4 256k;
    proxy_busy_buffers_size    256k;
    proxy_temp_file_write_size 256k;
# Tambahan untuk limit req
    limit_req zone=limit burst=1 nodelay;
  }

  location / {
    {{varnish_proxy_pass}}
    proxy_set_header Host $http_host;
    proxy_set_header X-Forwarded-Host $http_host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_hide_header X-Varnish;
    proxy_redirect off;
    proxy_max_temp_file_size 0;
    proxy_connect_timeout      720;
    proxy_send_timeout         720;
    proxy_read_timeout         720;
    proxy_buffer_size          128k;
    proxy_buffers              4 256k;
    proxy_busy_buffers_size    256k;
    proxy_temp_file_write_size 256k;
  }

  location ~* ^.+\.(css|js|jpg|jpeg|gif|png|ico|gz|svg|svgz|ttf|otf|woff|woff2|eot|mp4|ogg|ogv|webm|webp|zip|swf|map)$ {
    # WordPress Multisite Subdirectory
    rewrite ^/[_0-9a-zA-Z-]+(/wp-.*) $1 break;
    rewrite ^/[_0-9a-zA-Z-]+(/.*\.php)$ $1 break;
    add_header Access-Control-Allow-Origin "*";
    expires max;
    access_log off;
  }

  if (-f $request_filename) {
    break;
  }
}

server {
  listen 8080;
  listen [::]:8080;
  server_name DOMAIN.id www1.DOMAIN.id;
  {{root}}

  try_files $uri $uri/ /index.php?$args;
  index index.php index.html;

  location ~ \.php$ {
    include fastcgi_params;
    fastcgi_intercept_errors on;
    fastcgi_index index.php;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    try_files $uri =404;
    fastcgi_read_timeout 3600;
    fastcgi_send_timeout 3600;
    fastcgi_param HTTPS "on";
    fastcgi_param SERVER_PORT 443;
    fastcgi_pass 127.0.0.1:{{php_fpm_port}};
    fastcgi_param PHP_VALUE "{{php_settings}}";
  }

  # WordPress Multisite Subdirectory
  if (!-e $request_filename) {
    rewrite /wp-admin$ https://$host$uri permanent;
    rewrite ^/[_0-9a-zA-Z-]+(/wp-.*) $1 last;
    rewrite ^/[_0-9a-zA-Z-]+(/.*\.php)$ $1 last;
  }

  if (-f $request_filename) {
    break;
  }
}
