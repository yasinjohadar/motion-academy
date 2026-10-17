(function () {
    'use strict';

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.content : '';
    }

    function findAlert(form) {
        var card = form.closest('.newsletter-form-card, .footer-newsletter, .blog-detail-newsletter');
        if (card) {
            return card.querySelector('.newsletter-alert');
        }
        return form.querySelector('.newsletter-alert');
    }

    function showAlert(alertEl, type, message) {
        if (!alertEl) return;
        alertEl.hidden = false;
        alertEl.className = 'newsletter-alert newsletter-alert--' + type;
        alertEl.innerHTML =
            '<span class="newsletter-alert__icon" aria-hidden="true">' +
            (type === 'success' ? '<i class="fas fa-check-circle"></i>' : '<i class="fas fa-exclamation-circle"></i>') +
            '</span>' +
            '<span class="newsletter-alert__text">' + message + '</span>';
        alertEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function hideAlert(alertEl) {
        if (!alertEl) return;
        alertEl.hidden = true;
        alertEl.className = 'newsletter-alert';
        alertEl.innerHTML = '';
    }

    function setLoading(form, button, loading) {
        if (!button) return;
        if (loading) {
            button.disabled = true;
            button.dataset.originalHtml = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري الإرسال...';
        } else {
            button.disabled = false;
            if (button.dataset.originalHtml) {
                button.innerHTML = button.dataset.originalHtml;
            }
        }
    }

    function extractErrorMessage(data) {
        if (data && data.errors) {
            var firstKey = Object.keys(data.errors)[0];
            if (firstKey && data.errors[firstKey] && data.errors[firstKey][0]) {
                return data.errors[firstKey][0];
            }
        }
        return (data && data.message) ? data.message : 'تعذر إتمام الاشتراك. حاول مرة أخرى.';
    }

    function handleSubmit(event) {
        event.preventDefault();
        var form = event.currentTarget;
        var alertEl = findAlert(form);
        var button = form.querySelector('[type="submit"]');
        var emailInput = form.querySelector('input[name="email"]');
        var action = form.getAttribute('action');
        var source = form.dataset.source || 'general';

        hideAlert(alertEl);
        setLoading(form, button, true);

        var body = new FormData(form);
        body.set('source', source);

        fetch(action, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken(),
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: body
        })
            .then(function (response) {
                return response.json().then(function (data) {
                    return { ok: response.ok, status: response.status, data: data };
                });
            })
            .then(function (result) {
                if (result.ok && result.data.success) {
                    showAlert(alertEl, 'success', result.data.message);
                    if (emailInput) {
                        emailInput.value = '';
                    }
                    return;
                }
                showAlert(alertEl, 'error', extractErrorMessage(result.data));
            })
            .catch(function () {
                showAlert(alertEl, 'error', 'حدث خطأ في الاتصال. تحقق من الإنترنت وحاول مرة أخرى.');
            })
            .finally(function () {
                setLoading(form, button, false);
            });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.js-newsletter-form').forEach(function (form) {
            form.addEventListener('submit', handleSubmit);
        });
    });
})();
