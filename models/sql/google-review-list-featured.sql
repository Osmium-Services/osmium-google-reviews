SELECT
    r.id,
    r.author_name,
    r.rating,
    r.review_text,
    r.review_date,
    r.sort_order
FROM {TABLE} r
WHERE r.deleted_at IS NULL
  AND r.is_featured = 1
ORDER BY r.sort_order ASC, r.review_date DESC
LIMIT :limit
