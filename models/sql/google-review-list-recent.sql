SELECT R.id, R.author_name, R.rating, R.review_text, R.created_at, R.updated_at, R.deleted_at, CL.user_name AS changed_by, CU.avatar AS changed_by_avatar
FROM {TABLE} AS R
LEFT JOIN {PREFIX}changelog AS CL ON CL.id = (SELECT MAX(id) FROM {PREFIX}changelog WHERE record_type = 'google_review' AND record_id = R.id)
LEFT JOIN {PREFIX}users AS CU ON CU.id = CL.user_id
WHERE (R.created_at >= NOW() - INTERVAL 24 HOUR OR R.updated_at >= NOW() - INTERVAL 24 HOUR OR R.deleted_at >= NOW() - INTERVAL 24 HOUR)
ORDER BY GREATEST(COALESCE(R.updated_at, R.created_at), R.created_at, COALESCE(R.deleted_at, R.created_at)) DESC, R.id DESC
LIMIT 10
