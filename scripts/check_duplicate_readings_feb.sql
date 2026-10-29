-- =============================================================================
-- Check duplicate readings and bills for February (Sta. Rita)
-- Run this in dry-run mode first to see what would be deleted.
-- =============================================================================

SET @year = 2026;
SET @month = 2;

-- 1) List duplicate readings to DELETE (recent ones; first per account kept; PAID bills excluded)
SELECT
    r.id AS reading_id,
    r.account_no,
    r.previous_reading,
    r.present_reading,
    r.created_at,
    b.id AS bill_id,
    b.reference_no,
    b.isPaid
FROM readings r
LEFT JOIN bill b ON b.reading_id = r.id
WHERE YEAR(r.created_at) = @year
  AND MONTH(r.created_at) = @month
  AND (b.id IS NULL OR b.isPaid = 0)
  AND r.id NOT IN (
    SELECT MIN(r2.id)
    FROM readings r2
    WHERE YEAR(r2.created_at) = @year
      AND MONTH(r2.created_at) = @month
    GROUP BY r2.account_no
  )
ORDER BY r.account_no, r.id;

-- 2) Count summary
SELECT
    (SELECT COUNT(*) FROM readings r
     WHERE YEAR(r.created_at) = @year AND MONTH(r.created_at) = @month
     AND r.id NOT IN (
       SELECT MIN(r2.id) FROM readings r2
       WHERE YEAR(r2.created_at) = @year AND MONTH(r2.created_at) = @month
       GROUP BY r2.account_no
     )) AS readings_to_delete;

-- 3) EXECUTE DELETION (uncomment to run - excludes paid bills)
/*
DELETE FROM readings
WHERE YEAR(created_at) = @year AND MONTH(created_at) = @month
  AND id IN (
    SELECT id FROM (
      SELECT r.id
      FROM readings r
      LEFT JOIN bill b ON b.reading_id = r.id
      WHERE YEAR(r.created_at) = @year AND MONTH(r.created_at) = @month
        AND (b.id IS NULL OR b.isPaid = 0)
        AND r.id NOT IN (
          SELECT MIN(r2.id) FROM readings r2
          WHERE YEAR(r2.created_at) = @year AND MONTH(r2.created_at) = @month
          GROUP BY r2.account_no
        )
    ) AS to_delete
  );
*/
