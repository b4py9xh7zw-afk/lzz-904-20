FROM php:8.2-fpm-alpine

# 安装 FFmpeg 和必要的依赖
RUN apk add --no-cache \
    ffmpeg \
    bash \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev

# 配置 PHP 允许上传大文件
RUN { \
        echo 'upload_max_filesize = 50M'; \
        echo 'post_max_size = 55M'; \
        echo 'memory_limit = 256M'; \
        echo 'max_execution_time = 300'; \
    } > /usr/local/etc/php/conf.d/docker-php-upload.ini

# 创建临时工作目录并设置权限
RUN mkdir -p /tmp/ffmpeg_preview && chmod 777 /tmp/ffmpeg_preview

WORKDIR /var/www/html

# 在容器启动后给予权限 (或者在 Dockerfile 里处理)
# 注意：在本地开发挂载时，目录权限通常继承自宿主机

EXPOSE 9000
