SELECT R.id, R.author_name, R.rating, R.deleted_at, CL.user_name AS changed_by, CU.avatar AS changed_by_avatar
FROM {TABLE} AS R
LEFT JOIN {PREFIX}changelog AS CL ON CL.id = (SELECT MAX(id) FROM {PREFIX}changelog WHERE record_type = 'google_review' AND record_id = R.id)
LEFT JOIN {PREFIX}users AS CU ON CU.id = CL.user_id
WHERE R.deleted_at IS NOT NULL
ORDER BY R.deleted_at DESC
