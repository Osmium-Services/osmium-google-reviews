SELECT
    r.id,
    r.author_name,
    r.rating,
    r.review_text,
    r.review_date,
    r.is_featured,
    r.sort_order
FROM {TABLE} r
WHERE r.deleted_at IS NULL
ORDER BY r.is_featured DESC, r.sort_order ASC, r.review_date DESC
