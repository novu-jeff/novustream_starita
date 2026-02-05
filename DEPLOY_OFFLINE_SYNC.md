# Offline sync 404 fix – run on the server

If the mobile app gets **404** when uploading (GET `.../api/offline/sync?readings=...`), the server is using **cached routes** that don’t include the sync route.

**On the server** that serves the API (e.g. where sta-rita runs):

```bash
cd /var/www/html/sta-rita
php artisan route:clear
```

If you use `route:cache` in production, run it again **after** deploying this code:

```bash
php artisan route:clear
php artisan route:cache
```

Then retry upload from the app. The route `GET|POST /api/offline/sync` must be registered for uploads to work.
