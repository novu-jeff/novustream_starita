# Merge readings_offline → readings (terminal & API)

Merge pending rows from `readings_offline` into `readings` and create/update bills (same logic as web).

**Novupay QR flow (sync then merge):** See **NOVUPAY_AND_MERGE_FLOW.md**. One-liner:  
`php artisan novupay:sync-readings && php artisan readings:merge`

---

## Artisan (recommended for cron / no token)

Run from the **sta-rita** project root.

```bash
# Merge up to 100 pending offline readings (default)
php artisan readings:merge

# Merge up to 500 per run
php artisan readings:merge --limit=500

# Dry run: only show how many would be merged
php artisan readings:merge --dry-run
php artisan readings:merge --limit=50 --dry-run
```

### Parameters

| Option   | Default | Description |
|----------|---------|-------------|
| `--limit=N` | 100 | Max number of pending offline readings to merge in this run. |
| `--dry-run` | — | Do not merge; only count how many would be merged. |

### Cron example

```bash
# Every 15 minutes, merge up to 100 pending readings
*/15 * * * * cd /var/www/html/sta-rita && php artisan readings:merge >> /var/log/sta-rita-merge.log 2>&1
```

---

## HTTP API (POST; requires Bearer token)

If you call the API instead of Artisan, you need an admin/technician token (e.g. from `POST /api/auth/login`).

### Endpoints

- `POST /api/readings/merge`
- `POST /api/offline/merge`

### Parameters (query or body)

| Param   | Type | Default | Description |
|---------|------|---------|-------------|
| `limit` | int | 100 | Max number of pending offline readings to merge per request. |

### cURL examples

```bash
# Merge (default limit 100); replace TOKEN with your Bearer token
curl -X POST "https://admin.staritawaterdistrictpamp.gov.ph/api/readings/merge" \
  -H "Authorization: Bearer TOKEN" \
  -H "Accept: application/json"

# Merge with limit 200 (query string)
curl -X POST "https://admin.staritawaterdistrictpamp.gov.ph/api/readings/merge?limit=200" \
  -H "Authorization: Bearer TOKEN" \
  -H "Accept: application/json"

# Same with limit in JSON body
curl -X POST "https://admin.staritawaterdistrictpamp.gov.ph/api/readings/merge" \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"limit": 200}'
```

### Response (JSON)

```json
{
  "status": "merged",
  "count": 42
}
```

If there are errors (e.g. account not found):

```json
{
  "status": "merged",
  "count": 38,
  "errors": [
    { "reference_no": "REF-001", "error": "Account not found" },
    { "reference_no": "REF-002", "error": "Property type not found" }
  ]
}
```

---

## Summary

| Method        | Command / URL | Params |
|---------------|----------------|--------|
| **Artisan**   | `php artisan readings:merge` | `--limit=N`, `--dry-run` |
| **API**       | `POST /api/readings/merge` or `POST /api/offline/merge` | `limit` (int, default 100) |

Save the Artisan line for cron; use the API when calling from another app (with a Bearer token).
