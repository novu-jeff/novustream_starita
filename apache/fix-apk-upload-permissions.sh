#!/bin/bash
# Run on the server: sudo bash apache/fix-apk-upload-permissions.sh
# Makes public/apk and public/app-version writable so upload-apk.php can save uploads.

set -e
APP_PUBLIC="/var/www/html/sta-rita/public"

mkdir -p "$APP_PUBLIC/apk"
touch "$APP_PUBLIC/app-version"
chown -R www-data:www-data "$APP_PUBLIC/apk" "$APP_PUBLIC/app-version"
chmod -R 775 "$APP_PUBLIC/apk"
chmod 664 "$APP_PUBLIC/app-version"

echo "Done. www-data can write to sta-rita/public/apk/ and public/app-version."
