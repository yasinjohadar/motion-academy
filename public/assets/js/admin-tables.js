/**
 * Admin Tables — أدوات عامة للجداول (بحث Ajax، نسخ، تبديل الحالة)
 */
(function () {
    'use strict';

    function ensureToastContainer() {
        let container = document.getElementById('admin-toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'admin-toast-container';
            container.className = 'admin-toast-container';
            document.body.appendChild(container);
        }
        return container;
    }

    function showToast(message, type = 'success') {
        const container = ensureToastContainer();
        const toast = document.createElement('div');
        toast.className = `admin-toast admin-toast-${type}`;
        toast.textContent = message;
        container.appendChild(toast);
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(-12px)';
            toast.style.transition = 'all 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, 3200);
    }

    async function copyToClipboard(text, button, successMessage) {
        try {
            await navigator.clipboard.writeText(text);
            if (button) {
                button.classList.add('copied');
                const icon = button.querySelector('i');
                const originalClass = icon ? icon.className : '';
                if (icon) icon.className = 'ri-check-line';
                setTimeout(() => {
                    button.classList.remove('copied');
                    if (icon) icon.className = originalClass || 'ri-file-copy-line';
                }, 1800);
            }
            showToast(successMessage || 'تم النسخ', 'success');
        } catch (e) {
            showToast('تعذر النسخ', 'error');
        }
    }

    function getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function debounce(fn, delay) {
        let timer;
        return function (...args) {
            clearTimeout(timer);
            timer = setTimeout(() => fn.apply(this, args), delay);
        };
    }

    function initCopyButtons(root = document) {
        root.querySelectorAll('[data-copy-email]').forEach((btn) => {
            if (btn.dataset.copyBound) return;
            btn.dataset.copyBound = '1';
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                copyToClipboard(this.dataset.copyEmail, this, 'تم نسخ البريد الإلكتروني');
            });
        });
    }

    function initToggleStatus(root = document, options = {}) {
        const urlTemplate = options.urlTemplate || '/users/:id/toggle-status';

        root.querySelectorAll('.toggle-status').forEach((toggle) => {
            if (toggle.dataset.toggleBound) return;
            toggle.dataset.toggleBound = '1';

            toggle.addEventListener('change', async function () {
                const userId = this.dataset.userId;
                const isActive = this.checked;
                const label = this.closest('.admin-status-switch')?.querySelector('.status-label');
                const previous = !isActive;

                if (!userId) return;

                const confirmMessage = isActive
                    ? 'هل أنت متأكد من تفعيل هذا المستخدم؟'
                    : 'هل أنت متأكد من إلغاء تفعيل هذا المستخدم؟';

                if (!confirm(confirmMessage)) {
                    this.checked = previous;
                    return;
                }

                this.disabled = true;
                const url = urlTemplate.replace(':id', userId);

                try {
                    const response = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': getCsrfToken(),
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({ is_active: isActive }),
                    });

                    const data = await response.json();

                    if (!response.ok || !data.success) {
                        throw new Error(data.message || 'فشل تحديث الحالة');
                    }

                    this.checked = Boolean(data.is_active);
                    if (label) {
                        label.textContent = data.is_active ? 'نشط' : 'غير نشط';
                        label.classList.toggle('is-active', data.is_active);
                    }
                    showToast(data.message || 'تم تحديث الحالة بنجاح', 'success');
                } catch (error) {
                    this.checked = previous;
                    showToast(error.message || 'حدث خطأ أثناء تحديث الحالة', 'error');
                } finally {
                    this.disabled = false;
                }
            });
        });
    }

    function initFilterSelects(form) {
        if (!form || typeof Choices === 'undefined') return;

        form.querySelectorAll('select.form-select').forEach((select) => {
            if (select.dataset.adminChoicesInit) return;
            select.dataset.adminChoicesInit = '1';

            select._adminChoices = new Choices(select, {
                searchEnabled: false,
                itemSelectText: '',
                shouldSort: false,
                allowHTML: false,
                position: 'bottom',
            });
        });
    }

    function initAdminForm(form) {
        if (!form) return;

        form.querySelectorAll('[data-toggle-password]').forEach((btn) => {
            btn.addEventListener('click', function () {
                const input = document.getElementById(this.dataset.togglePassword);
                if (!input) return;
                const icon = this.querySelector('i');
                if (input.type === 'password') {
                    input.type = 'text';
                    if (icon) icon.className = 'ri-eye-off-line';
                } else {
                    input.type = 'password';
                    if (icon) icon.className = 'ri-eye-line';
                }
            });
        });

        form.querySelectorAll('[data-photo-preview]').forEach((input) => {
            input.addEventListener('change', function () {
                const preview = document.getElementById(this.dataset.photoPreview);
                if (!preview || !this.files || !this.files[0]) return;
                const reader = new FileReader();
                reader.onload = (e) => { preview.src = e.target.result; };
                reader.readAsDataURL(this.files[0]);
            });
        });

        if (typeof Choices !== 'undefined') {
            form.querySelectorAll('select[data-admin-choices]').forEach((select) => {
                if (select.dataset.adminChoicesInit) return;
                select.dataset.adminChoicesInit = '1';
                select._adminChoices = new Choices(select, {
                    searchEnabled: false,
                    itemSelectText: '',
                    shouldSort: false,
                    allowHTML: false,
                    position: 'bottom',
                });
            });
        }

        form.querySelectorAll('[data-permission-picker]').forEach(initPermissionPicker);
    }

    function initPermissionPicker(picker) {
        if (picker.dataset.permissionPickerInit) return;
        picker.dataset.permissionPickerInit = '1';

        const searchInput = picker.querySelector('[data-permission-search]');
        const emptyHint = picker.querySelector('[data-permission-empty]');
        const selectAllBtn = picker.querySelector('[data-permission-select-all]');
        const deselectAllBtn = picker.querySelector('[data-permission-deselect-all]');
        const expandAllBtn = picker.querySelector('[data-permission-expand-all]');
        const collapseAllBtn = picker.querySelector('[data-permission-collapse-all]');

        function getGroups() {
            return [...picker.querySelectorAll('[data-permission-group]')];
        }

        function getVisibleItems() {
            return [...picker.querySelectorAll('[data-permission-item]')].filter(
                (item) => !item.classList.contains('is-hidden')
            );
        }

        function updateGroupSelectedCounts() {
            getGroups().forEach((group) => {
                const badge = group.querySelector('[data-group-selected]');
                if (!badge) return;

                const items = [...group.querySelectorAll('[data-permission-item]')];
                const checked = items.filter((item) => item.querySelector('.role-perms__checkbox')?.checked).length;
                badge.textContent = `${checked}/${items.length}`;
            });
        }

        function setGroupExpanded(group, expanded) {
            if (group.tagName === 'DETAILS') {
                group.open = expanded;
            }
        }

        function filterPermissions() {
            const term = (searchInput?.value || '').trim().toLowerCase();
            let visibleCount = 0;

            getGroups().forEach((group) => {
                let groupVisible = 0;
                group.querySelectorAll('[data-permission-item]').forEach((item) => {
                    const name = (item.dataset.name || '').toLowerCase();
                    const label = (item.dataset.label || '').toLowerCase();
                    const match = !term || name.includes(term) || label.includes(term);
                    item.classList.toggle('is-hidden', !match);
                    if (match) {
                        groupVisible++;
                        visibleCount++;
                    }
                });
                group.classList.toggle('is-hidden', groupVisible === 0);
                if (term && groupVisible > 0) {
                    setGroupExpanded(group, true);
                }
            });

            if (emptyHint) {
                emptyHint.style.display = visibleCount === 0 ? 'block' : 'none';
            }
        }

        if (searchInput) {
            searchInput.addEventListener('input', filterPermissions);
        }

        getGroups().forEach((group) => {
            const selectGroupBtn = group.querySelector('[data-permission-group-select]');
            const deselectGroupBtn = group.querySelector('[data-permission-group-deselect]');

            if (selectGroupBtn) {
                selectGroupBtn.addEventListener('click', (event) => {
                    event.preventDefault();
                    group.querySelectorAll('[data-permission-item]').forEach((item) => {
                        if (item.classList.contains('is-hidden')) return;
                        const input = item.querySelector('.role-perms__checkbox');
                        if (input) input.checked = true;
                    });
                    updateGroupSelectedCounts();
                });
            }

            if (deselectGroupBtn) {
                deselectGroupBtn.addEventListener('click', (event) => {
                    event.preventDefault();
                    group.querySelectorAll('[data-permission-item]').forEach((item) => {
                        const input = item.querySelector('.role-perms__checkbox');
                        if (input) input.checked = false;
                    });
                    updateGroupSelectedCounts();
                });
            }
        });

        picker.addEventListener('change', (event) => {
            if (event.target.matches('.role-perms__checkbox')) {
                updateGroupSelectedCounts();
            }
        });

        if (selectAllBtn) {
            selectAllBtn.addEventListener('click', () => {
                getVisibleItems().forEach((item) => {
                    const input = item.querySelector('.role-perms__checkbox');
                    if (input) input.checked = true;
                });
                updateGroupSelectedCounts();
            });
        }

        if (deselectAllBtn) {
            deselectAllBtn.addEventListener('click', () => {
                getVisibleItems().forEach((item) => {
                    const input = item.querySelector('.role-perms__checkbox');
                    if (input) input.checked = false;
                });
                updateGroupSelectedCounts();
            });
        }

        if (expandAllBtn) {
            expandAllBtn.addEventListener('click', () => {
                getGroups().forEach((group) => {
                    if (!group.classList.contains('is-hidden')) {
                        setGroupExpanded(group, true);
                    }
                });
            });
        }

        if (collapseAllBtn) {
            collapseAllBtn.addEventListener('click', () => {
                getGroups().forEach((group) => {
                    setGroupExpanded(group, false);
                });
            });
        }

        updateGroupSelectedCounts();
    }

    function generateSecurePassword(length = 12) {
        const upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        const lower = 'abcdefghjkmnpqrstuvwxyz';
        const numbers = '23456789';
        const symbols = '!@#$%&*';
        const all = upper + lower + numbers + symbols;

        let password = '';
        password += upper[Math.floor(Math.random() * upper.length)];
        password += lower[Math.floor(Math.random() * lower.length)];
        password += numbers[Math.floor(Math.random() * numbers.length)];
        password += symbols[Math.floor(Math.random() * symbols.length)];

        while (password.length < length) {
            password += all[Math.floor(Math.random() * all.length)];
        }

        return password.split('').sort(() => Math.random() - 0.5).join('');
    }

    function fillPasswordSuggestions(modal) {
        const container = modal.querySelector('[data-pw-suggestions]');
        if (!container) return;

        container.innerHTML = '';
        for (let i = 0; i < 3; i++) {
            const pwd = generateSecurePassword(12);
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'admin-pw-suggestion';
            btn.dataset.password = pwd;
            btn.innerHTML = `<span class="admin-pw-suggestion-text">${pwd}</span><i class="ri-arrow-left-line"></i>`;
            container.appendChild(btn);
        }
    }

    function applyPasswordToModal(modal, password) {
        const main = modal.querySelector('[data-pw-main]');
        const confirm = modal.querySelector('[data-pw-confirm]');
        if (main) {
            main.value = password;
            main.type = 'text';
        }
        if (confirm) {
            confirm.value = password;
            confirm.type = 'text';
        }
        modal.querySelectorAll('[data-toggle-password] i').forEach((icon) => {
            icon.className = 'ri-eye-off-line';
        });
    }

    function initPasswordModals() {
        if (document.body.dataset.passwordModalBound) return;
        document.body.dataset.passwordModalBound = '1';

        document.addEventListener('shown.bs.modal', function (e) {
            const modal = e.target.closest('.admin-password-modal');
            if (!modal) return;
            fillPasswordSuggestions(modal);
            modal.querySelectorAll('.admin-pw-suggestion').forEach((s) => s.classList.remove('is-selected'));
            const form = modal.querySelector('form');
            if (form) form.reset();
        });

        document.addEventListener('click', function (e) {
            const refreshBtn = e.target.closest('[data-refresh-pw-suggestions]');
            if (refreshBtn) {
                e.preventDefault();
                const modal = refreshBtn.closest('.admin-password-modal');
                if (modal) fillPasswordSuggestions(modal);
                return;
            }

            const suggestion = e.target.closest('.admin-pw-suggestion');
            if (suggestion) {
                e.preventDefault();
                const modal = suggestion.closest('.admin-password-modal');
                if (!modal) return;
                applyPasswordToModal(modal, suggestion.dataset.password);
                modal.querySelectorAll('.admin-pw-suggestion').forEach((s) => s.classList.remove('is-selected'));
                suggestion.classList.add('is-selected');
                return;
            }

            const copyBtn = e.target.closest('[data-copy-password]');
            if (copyBtn) {
                e.preventDefault();
                const input = document.getElementById(copyBtn.dataset.copyTarget);
                if (input && input.value) {
                    copyToClipboard(input.value, copyBtn, 'تم نسخ كلمة المرور');
                } else {
                    showToast('لا توجد كلمة مرور للنسخ', 'error');
                }
                return;
            }

            const toggleBtn = e.target.closest('[data-toggle-password]');
            if (toggleBtn && toggleBtn.dataset.togglePassword) {
                const input = document.getElementById(toggleBtn.dataset.togglePassword);
                if (!input) return;
                const icon = toggleBtn.querySelector('i');
                if (input.type === 'password') {
                    input.type = 'text';
                    if (icon) icon.className = 'ri-eye-off-line';
                } else {
                    input.type = 'password';
                    if (icon) icon.className = 'ri-eye-line';
                }
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.admin-form').forEach((form) => {
            if (form.closest('.admin-password-modal')) return;
            initAdminForm(form);
        });
        initPasswordModals();
    });

    function initAjaxTable(options) {
        const form = document.querySelector(options.formSelector);
        const container = options.containerSelector ? document.querySelector(options.containerSelector) : null;
        const tableWrap = document.querySelector(options.tableWrapSelector || options.containerSelector);

        if (!form || (!container && !options.bodySelector)) return;

        initFilterSelects(form);

        const fetchUrl = options.url || form.getAttribute('action');
        let currentPage = 1;

        function setLoading(loading) {
            if (tableWrap) tableWrap.classList.toggle('is-loading', loading);
        }

        async function load(page = 1) {
            currentPage = page;
            setLoading(true);

            const params = new URLSearchParams(new FormData(form));
            params.set('page', page);

            try {
                const response = await fetch(`${fetchUrl}?${params.toString()}`, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                });

                if (!response.ok) throw new Error('فشل تحميل البيانات');

                const data = await response.json();

                const bodyEl = document.querySelector(options.bodySelector);
                const extraEl = options.extraSelector ? document.querySelector(options.extraSelector) : null;
                const targetEl = document.querySelector(options.containerSelector);

                if (bodyEl && data.body !== undefined) {
                    bodyEl.innerHTML = data.body;
                } else if (targetEl && data.html !== undefined) {
                    targetEl.innerHTML = data.html;
                }

                if (extraEl && data.extra !== undefined) {
                    extraEl.innerHTML = data.extra;
                }

                if (options.metaSelector && data.total !== undefined) {
                    const meta = document.querySelector(options.metaSelector);
                    if (meta && data.from !== undefined && data.to !== undefined) {
                        meta.textContent = `عرض ${data.from} إلى ${data.to} من ${data.total} نتيجة`;
                    } else if (meta && data.total === 0) {
                        meta.textContent = 'لا توجد نتائج';
                    }
                }

                const rebindRoot = bodyEl || targetEl || document;
                initCopyButtons(rebindRoot);
                initToggleStatus(rebindRoot, options.toggleOptions || {});
                if (extraEl) {
                    initCopyButtons(extraEl);
                    initToggleStatus(extraEl, options.toggleOptions || {});
                }

                document.querySelectorAll('.admin-password-modal [data-pw-suggestions]').forEach((el) => {
                    const modal = el.closest('.admin-password-modal');
                    if (modal && !modal.classList.contains('show')) {
                        el.innerHTML = '';
                    }
                });

                if (typeof options.onLoaded === 'function') {
                    options.onLoaded(data);
                }
            } catch (error) {
                showToast(error.message || 'حدث خطأ أثناء تحميل البيانات', 'error');
            } finally {
                setLoading(false);
            }
        }

        const debouncedSearch = debounce(() => load(1), 400);

        form.querySelectorAll('input[name="query"]').forEach((input) => {
            input.addEventListener('input', debouncedSearch);
        });

        form.querySelectorAll('select').forEach((select) => {
            select.addEventListener('change', () => load(1));
        });

        form.querySelectorAll('input[type="date"]').forEach((input) => {
            input.addEventListener('change', () => load(1));
        });

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            load(1);
        });

        const resetBtn = form.querySelector('[data-admin-filter-reset]');
        if (resetBtn) {
            resetBtn.addEventListener('click', function (e) {
                e.preventDefault();
                form.reset();
                form.querySelectorAll('select.form-select').forEach((select) => {
                    if (select._adminChoices) {
                        select._adminChoices.setChoiceByValue('');
                    }
                });
                load(1);
            });
        }

        document.addEventListener('click', function (e) {
            const paginationRoot = options.extraSelector || options.containerSelector;
            const link = e.target.closest(`${paginationRoot} .pagination a`);
            if (!link) return;
            e.preventDefault();
            const url = new URL(link.href);
            const page = url.searchParams.get('page') || 1;
            load(page);
            const scrollTarget = document.querySelector(options.tableWrapSelector || options.bodySelector);
            if (scrollTarget) {
                scrollTarget.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        });

        initCopyButtons(document.querySelector(options.bodySelector) || container);
        initToggleStatus(document.querySelector(options.bodySelector) || container, options.toggleOptions || {});

        return { reload: load };
    }

    window.togglePasswordVisibility = function (inputId, button) {
        const input = document.getElementById(inputId);
        if (!input) return;
        const eyeIcon = button.querySelector('svg');
        if (input.type === 'password') {
            input.type = 'text';
            if (eyeIcon) {
                eyeIcon.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line>';
            }
        } else {
            input.type = 'password';
            if (eyeIcon) {
                eyeIcon.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle>';
            }
        }
    };

    function initDeleteButtons() {
        if (document.body.dataset.deleteDelegationBound) return;
        document.body.dataset.deleteDelegationBound = '1';

        document.addEventListener('click', function (e) {
            const button = e.target.closest('[data-delete-url]');
            if (!button) return;
            e.preventDefault();

            const deleteUrl = button.getAttribute('data-delete-url');
            const deleteMessage = button.getAttribute('data-delete-message') || 'هل أنت متأكد من رغبتك في حذف هذا العنصر؟';
            const deleteForm = document.getElementById('deleteForm');
            const messageElement = document.getElementById('deleteModalMessage');
            const modalElement = document.getElementById('deleteModal');

            if (deleteForm) deleteForm.action = deleteUrl;
            if (messageElement) messageElement.innerHTML = deleteMessage;
            if (modalElement) bootstrap.Modal.getOrCreateInstance(modalElement).show();
        });
    }

    document.addEventListener('DOMContentLoaded', initDeleteButtons);

    window.AdminTables = {
        showToast,
        copyToClipboard,
        initCopyButtons,
        initToggleStatus,
        initAjaxTable,
        initDeleteButtons,
        initFilterSelects,
        initAdminForm,
        initPasswordModals,
        initPermissionPicker,
        generateSecurePassword,
    };
})();
