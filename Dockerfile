# نستخدم صورة جاهزة فيها Apache + PHP
FROM php:8.2-apache

# نسخ كل ملفات الريبو إلى مجلد الويب في أباتشي
COPY . /var/www/html/

# (اختياري) تأكيد صلاحيات الملفات
RUN chown -R www-data:www-data /var/www/html