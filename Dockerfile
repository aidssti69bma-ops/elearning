FROM php:8.2-cli

# ติดตั้ง mysqli extension
RUN docker-php-ext-install mysqli pdo pdo_mysql

# copy ไฟล์ทั้งหมด
COPY . /app

WORKDIR /app

EXPOSE 8080

CMD ["php", "-S", "0.0.0.0:8080", "-t", "/app"]
