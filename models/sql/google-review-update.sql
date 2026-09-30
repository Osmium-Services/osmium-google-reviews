UPDATE {TABLE}
SET author_name = :author_name,
    rating = :rating,
    review_text = :review_text,
    review_date = :review_date,
    is_featured = :is_featured,
    sort_order = :sort_order
WHERE id = :id
