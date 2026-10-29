# Readings, Bills, and Offline Merge Flow (sta-rita)

## 1. When readings occur

Readings are created in three ways:

| Source | How | Where data goes |
|--------|-----|------------------|
| **Web (ReadingController)** | Staff submits a reading form (account, present/previous reading, date). | Directly to `readings` table, then bill + breakdown + discount. |
| **API (MeterController)** | External system POSTs reading data. | Directly to `readings` → `bill` → `bill_breakdown` (no discount in this path). |
| **Offline app** | Technician records readings offline; when back online, app syncs. | First to `readings_offline`; **merge** later moves them to `readings` and creates bill. |

So: **online** flows write straight to `readings` (and then bill). **Offline** flow writes to `readings_offline`; a separate **merge** step creates the real reading and bill.

---

## 2. Core flow: one reading → one bill → breakdown + discount

Conceptually:

```
Reading (one row per meter read)
    ↓ 1:1
Bill (amount, due_date, previous_unpaid, penalty, discount, isPaid, etc.)
    ↓ 1:many
bill_breakdown  (line items: Previous Balance, Basic Charge, other deductions)
bill_discount   (line items: Senior, Franchise Tax, etc.)
```

- **readings**: One row per actual meter reading (account_no, previous_reading, present_reading, consumption, reference_no, isReRead, etc.).
- **bill**: One row per bill, linked by `reading_id` to one reading. Holds totals: amount, previous_unpaid, penalty, discount, amount_after_due, due_date, isPaid, etc.
- **bill_breakdown**: Line items that **add up** to the bill (Previous Balance, Basic Charge, other charges from payment breakdown rules). Used for OR/invoice display.
- **bill_discount**: Line items that **reduce** the bill (Senior discount, Franchise Tax, etc.). Stored so you can show “Franchise Tax -₱X” on the bill.

The **same** creation logic is used for both “web reading” and “merge offline reading”: `MeterService::create_breakdown()`.

---

## 3. How bills, bill_breakdown, and bill_discount are created

### 3.1 Single entry point: `MeterService::create_breakdown($payload)`

This method:

1. **Validates** ruling, payment breakdowns, penalties, discounts, account, property type.
2. **Resolves previous reading** (latest non–re-read reading for the account) for previous_reading and any advance payment.
3. **Computes unpaid** (latest unpaid bill for that account) and builds “Previous Balance” in the breakdown.
4. **Rates**: Uses consumption and property type to get basic charge (rate × consumption or tiered).
5. **Deductions**: Gets payment breakdown rules (e.g. other charges) and builds the **deductions** array (Previous Balance, Basic Charge, others).
6. **Discounts**: Applies senior/franchise from `PaymentDiscount` and account’s discount type → builds **appliedDiscounts** (Senior, Franchise, etc.).
7. **Penalty**: If there is unpaid amount, applies penalty rules (due_from/due_to, amount_type percentage/fixed).
8. **Dates**: bill_period_from/to, due_date from ruling/last bill.
9. **Persists**:
   - **Reading**: `Reading::updateOrCreate` by `reference_no` (or insert) with zone, account_no, previous_reading, present_reading, consumption, reader_name, etc.
   - **Bill**: Insert new or update existing by `reference_no`, set `reading_id`, then:
   - **bill_breakdown**: Delete old rows for that bill, then insert one row per deduction (name, description, amount).
   - **bill_discount**: Delete old rows for that bill, then insert one row per applied discount (name, description, amount).
10. If re-read: links original reading to this one via `reread_reference_no`.

So **one** call to `create_breakdown()` creates/updates:

- 1 row in `readings`
- 1 row in `bill`
- N rows in `bill_breakdown`
- M rows in `bill_discount`

---

## 4. Where create_breakdown is used

| Caller | When | Extra step after create_breakdown |
|--------|------|----------------------------------|
| **ReadingController::store** | Web form submit | Updates bill (dates, penalty), then adds **extra** BillDiscount rows (Franchise Tax, Senior, Franchise) and penalty. |
| **OfflineSyncController::merge** | Merge pending offline readings | Calls `applyStorePostProcessingToBill()` (same discounts + penalty; can skip HitPay QR). Then marks `readings_offline` row as merged. |
| **Api\MeterController** | External API | Only creates Reading + Bill + BillBreakdown (no BillDiscount in this path). |
| **PreviousBillingImport** | Import | Uses its own create_breakdown; creates Bill + BillBreakdown + BillDiscount. |

So for **offline**, the “same logic as ReadingController::store” is: **create_breakdown** + **applyStorePostProcessingToBill** (with `skipHitPayQr = true` for merge).

---

## 5. How merge triggers the full flow (readings_offline → readings + bill)

Merge is the step that turns **readings_offline** into real readings and bills.

### 5.1 When to run merge

- **Manually**: `POST /api/readings/merge` (optionally `?limit=100`).
- **Cron**: Schedule the same endpoint (e.g. every 5–15 minutes) so new offline readings are merged automatically.

### 5.2 What merge does (per pending row in readings_offline)

For each row in `readings_offline` where `synced_at` IS NULL and `merged_into_reading_id` IS NULL:

1. **Load account**  
   - Get concessionaire by `account_no`.  
   - Resolve `property_types_id` from account’s property type.  
   - If account or property type missing → skip and record error.

2. **Build payload**  
   - account_no, previous_reading, present_reading, consumption, reference_no, date (e.g. created_at), is_high_consumption, isReRead, property_types_id from the offline row (and payload JSON).

3. **Call `MeterService::create_breakdown($payload)`**  
   - This **creates/updates**:
     - one row in **readings** (with this reference_no),
     - one row in **bill** (with reading_id),
     - rows in **bill_breakdown**,
     - rows in **bill_discount** (from MeterService’s deduction/discount logic).

4. **Call `MeterService::applyStorePostProcessingToBill(...)`**  
   - Finds the bill by `reference_no`.  
   - Applies **extra** BillDiscount rows (Franchise Tax, Senior, Franchise) and updates bill penalty, amount, discount, amount_after_due (same idea as web).  
   - For merge, HitPay QR is skipped (`skipHitPayQr = true`).

5. **Optional: Novupay**  
   - If offline row came from Novupay and there is a paid starita_bill for this reference_no, the merged bill is marked paid (isPaid, date_paid, payment_method).

6. **Mark offline row as merged**  
   - Set `synced_at = now()` and `merged_into_reading_id = reading->id` (so this offline row is tied to the real reading).

So: **merge does not “move” rows from readings_offline into readings**. It **uses** each pending readings_offline row as input and then runs the **same** create_breakdown + post-processing that the web uses, which **creates new** rows in `readings`, `bill`, `bill_breakdown`, and `bill_discount`. After that, the offline row is just marked as merged and is no longer processed again.

---

## 6. Summary

- **When readings occur**: Web form, API, or offline app (offline app writes to `readings_offline` only).
- **Bills / breakdown / discount**: One reading → one bill → many breakdown rows + many discount rows; all created inside `MeterService::create_breakdown()` (and optionally refined by `applyStorePostProcessingToBill()`).
- **Triggering the flow after offline sync**: Call **merge** (`POST /api/readings/merge`). Merge loops over pending `readings_offline` and for each one runs create_breakdown + applyStorePostProcessingToBill, which creates the real reading and bill (and breakdown + discount). No separate “move” step: merge **is** the trigger that turns offline rows into real readings and bills.
