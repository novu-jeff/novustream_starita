# Novupay QR → HitPay flow and updating sta-rita (readings + bills + breakdowns)

## 1. What happens when the customer scans the QR

1. **Customer scans QR** (from sta-rita bill / OR) → opens Novupay URL with params: `rn` (reference_no), `an` (account_no), `am` (amount), `pr` (present_reading), etc.
2. **Novupay API** (`/api/novustream/starita/payment-request` or similar):
   - Saves the bill to **starita_bills** (reference_no, account_no, amount, present_reading, status = initiated, etc.).
   - Creates a HitPay payment request and gets the checkout URL.
   - **Redirects the customer to HitPay** to pay.
3. **Customer pays on HitPay** → HitPay calls Novupay webhook → Novupay updates **starita_bills** (status = paid, paid_at, etc.).

So after payment, the “source of truth” for “this reference_no is paid” is in **Novupay’s starita_bills** table. Sta-rita needs to pull that into its own data and create/update the **same** reading with bill, bill_breakdown, and bill_discount.

---

## 2. How sta-rita gets the same readings with breakdowns (and paid status)

Sta-rita does **not** create the reading at QR-scan time. It creates it when you run the sync + merge steps. Those steps:

1. **Sync from Novupay into readings_offline**  
   - Reads from **starita_bills** (in Novupay DB or in sta-rita if replicated) and writes/updates rows in sta-rita’s **readings_offline** (one row per reference_no, with account_no, previous_reading, present_reading, consumption, source = novupay).

2. **Merge readings_offline into real readings**  
   - For each pending row in **readings_offline**, creates:
     - one row in **readings**,
     - one row in **bill** (linked to that reading),
     - rows in **bill_breakdown** (Previous Balance, Basic Charge, other charges),
     - rows in **bill_discount** (Senior, Franchise Tax, etc.),
   - and if that row came from Novupay and the **starita_bills** record is **paid**, marks the bill as paid (isPaid, date_paid, payment_method = online).

So: **same reference_no** → same reading and bill in sta-rita, with full breakdown and discount, and paid status in sync with Novupay.

---

## 3. Terminal commands (sta-rita)

Run from the **sta-rita** project root (e.g. `/var/www/html/sta-rita`).

### One-time: sync from Novupay then merge (recommended)

```bash
# Step 1: Copy starita_bills from Novupay into sta-rita readings_offline
php artisan novupay:sync-readings

# Step 2: Merge readings_offline into readings + bill + breakdown + discount (and set paid if Novupay says paid)
php artisan readings:merge
```

### Single line (sync then merge)

```bash
php artisan novupay:sync-readings && php artisan readings:merge
```

### With options

```bash
# Sync up to 200 from Novupay, then merge up to 200
php artisan novupay:sync-readings --limit=200 && php artisan readings:merge --limit=200

# Dry run merge only (see how many would be merged)
php artisan readings:merge --dry-run
```

### Cron (run every 15 minutes)

```bash
*/15 * * * * cd /var/www/html/sta-rita && php artisan novupay:sync-readings >> /var/log/sta-rita-sync.log 2>&1 && php artisan readings:merge >> /var/log/sta-rita-merge.log 2>&1
```

### Parameter summary (sta-rita)

| Command | Option | Default | Description |
|--------|--------|---------|-------------|
| `novupay:sync-readings` | `--limit=N` | 100 | Max starita_bills to sync into readings_offline per run. |
| `readings:merge` | `--limit=N` | 100 | Max readings_offline to merge into readings per run. |
| `readings:merge` | `--dry-run` | — | Only show how many would be merged; do not merge. |

---

## 4. Summary

- **Novupay flow:** QR scan → Novupay API (saves to starita_bills) → redirect to HitPay → HitPay callback updates starita_bills to paid.
- **Sta-rita update:** Run **novupay:sync-readings** (starita_bills → readings_offline), then **readings:merge** (readings_offline → readings + bill + bill_breakdown + bill_discount; mark paid from Novupay).
- **Terminal one-liner:**  
  `php artisan novupay:sync-readings && php artisan readings:merge`

For merge-only (no Novupay) and API/cURL, see **MERGE_COMMANDS.md**.
