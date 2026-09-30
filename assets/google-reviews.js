(function () {
    'use strict';

    const config = window.GoogleReviewsConfig || {};
    const actionUrl = config.basePath + 'google-reviews/action/';

    // Link-only toolbar: this field only ever needs to add a product link to
    // a review, never rich formatting, so nothing else is exposed.
    const purifyConfig = {
        ALLOWED_TAGS: ['p', 'br', 'a'],
        ALLOWED_ATTR: ['href', 'target']
    };

    function sanitizeReviewText(content) {
        return DOMPurify.sanitize(content, purifyConfig);
    }

    function initSummernote() {
        $('#reviewText').summernote({
            height: 120,
            toolbar: [
                ['insert', ['link']]
            ]
        });
    }

    function setReviewTextContent(html) {
        $('#reviewText').summernote('code', html || '');
    }

    function getReviewTextContent() {
        return sanitizeReviewText($('#reviewText').summernote('code'));
    }

    function showError(message) {
        const resultDiv = document.getElementById('reviewResult');
        resultDiv.className = 'alert alert-danger';
        resultDiv.textContent = message;
        resultDiv.classList.remove('d-none');
    }

    function clearResult() {
        const resultDiv = document.getElementById('reviewResult');
        resultDiv.className = 'alert d-none';
        resultDiv.textContent = '';
    }

    async function postJson(payload) {
        const response = await fetch(actionUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(Object.assign({ csrf_token: window.csrfToken }, payload))
        });

        return response.json();
    }

    function openModal(review) {
        clearResult();

        const isEdit = Boolean(review);
        document.getElementById('reviewModalTitle').textContent = isEdit ? 'Edit Review' : 'Add Review';
        document.getElementById('reviewId').value = isEdit ? review.id : '';
        document.getElementById('reviewAuthorName').value = isEdit ? review.authorName : '';
        document.getElementById('reviewRating').value = isEdit ? review.rating : '5';
        document.getElementById('reviewDate').value = isEdit ? review.reviewDate : '';
        setReviewTextContent(isEdit ? review.reviewText : '');
        document.getElementById('reviewSortOrder').value = isEdit ? review.sortOrder : 0;
        document.getElementById('reviewIsFeatured').checked = isEdit ? review.isFeatured : false;

        new bootstrap.Modal(document.getElementById('reviewModal')).show();
    }

    async function handleSubmit(e) {
        e.preventDefault();
        clearResult();

        const btn = document.getElementById('reviewSaveButton');
        btn.disabled = true;

        const id = document.getElementById('reviewId').value;

        try {
            const result = await postJson({
                action: id ? 'update' : 'create',
                id: id,
                author_name: document.getElementById('reviewAuthorName').value,
                rating: document.getElementById('reviewRating').value,
                review_date: document.getElementById('reviewDate').value,
                review_text: getReviewTextContent(),
                sort_order: document.getElementById('reviewSortOrder').value,
                is_featured: document.getElementById('reviewIsFeatured').checked
            });

            if (result.success) {
                window.location.reload();
                return;
            }

            showError(result.error || 'Failed to save');
        } catch (error) {
            showError('An error occurred while saving');
        } finally {
            btn.disabled = false;
        }
    }

    async function handleToggleFeatured(button) {
        button.disabled = true;

        try {
            const result = await postJson({
                action: 'toggle_featured',
                id: button.dataset.id,
                is_featured: button.dataset.featured !== '1'
            });

            if (result.success) {
                window.location.reload();
                return;
            }

            window.alert(result.error || 'Failed to change visibility');
        } catch (error) {
            window.alert('An error occurred while changing visibility');
        } finally {
            button.disabled = false;
        }
    }

    async function handleDelete(id, name) {
        const confirmed = window.confirm('Delete the review from "' + name + '"?');
        if (!confirmed) return;

        try {
            const result = await postJson({ action: 'delete', id: id });

            if (result.success) {
                window.location.reload();
                return;
            }

            window.alert(result.error || 'Failed to delete');
        } catch (error) {
            window.alert('An error occurred while deleting');
        }
    }

    function init() {
        document.getElementById('addReviewButton').addEventListener('click', function () {
            openModal(null);
        });

        document.querySelectorAll('.edit-review').forEach(function (link) {
            link.addEventListener('click', function (e) {
                e.preventDefault();
                openModal(JSON.parse(link.dataset.review));
            });
        });

        document.querySelectorAll('.delete-review').forEach(function (link) {
            link.addEventListener('click', function (e) {
                e.preventDefault();
                handleDelete(link.dataset.id, link.dataset.name);
            });
        });

        document.querySelectorAll('.toggle-featured').forEach(function (button) {
            button.addEventListener('click', function () {
                handleToggleFeatured(button);
            });
        });

        document.getElementById('reviewForm').addEventListener('submit', handleSubmit);

        initSummernote();
    }

    document.addEventListener('DOMContentLoaded', init);
})();
