/**
 * ProcessEngine - Dashboard JS
 *
 * Handles filter interactions and auto-refresh for the dashboard.
 */
(function() {
    'use strict';

    // Auto-refresh dashboard every 60 seconds
    var PE_REFRESH_INTERVAL = 60000;
    var refreshTimer = null;

    function initDashboard() {
        // Highlight active filter button
        var filterBtns = document.querySelectorAll('.widget-toolbox .btn-group .btn');
        filterBtns.forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                filterBtns.forEach(function(b) {
                    b.classList.remove('btn-primary');
                    b.classList.add('btn-white');
                });
                this.classList.remove('btn-white');
                this.classList.add('btn-primary');
            });
        });

        // Setup action buttons (dashboard)
        initActionButtons();

        // Setup auto-refresh
        startAutoRefresh();
    }

    function initBugViewActions() {
        // Geri alma (rollback) — bug view
        document.querySelectorAll('.pe-bugview-rollback').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                var bugId = this.getAttribute('data-bug-id');
                if (!confirm(this.getAttribute('title') || 'Geri almak istediğinize emin misiniz?')) {
                    return;
                }
                peDoBugViewAction('rollback_step', bugId, this, {});
            });
        });

        // İlerleme modalı ile adım ilerletme
        document.querySelectorAll('.pe-bugview-advance').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                var bugId = this.getAttribute('data-bug-id');
                var instructions = this.getAttribute('data-instructions') || '';
                var currentStep = this.getAttribute('data-current-step') || '';
                var nextStep = this.getAttribute('data-next-step') || '';

                peOpenAdvanceModal(bugId, instructions, currentStep, nextStep);
            });
        });

        // Subprocess oluşturma
        document.querySelectorAll('.pe-create-subprocess').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                var bugId = this.getAttribute('data-bug-id');
                var targetId = this.getAttribute('data-target-id') || '0';
                peDoBugViewAction('create_subprocess', bugId, this, { target_id: targetId });
            });
        });

        // Manuel çocuk bağlama
        document.querySelectorAll('.pe-link-child-btn').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                var bugId = this.getAttribute('data-bug-id');
                var input = document.querySelector('.pe-link-child-input[data-bug-id="' + bugId + '"]');
                var childBugId = input ? input.value.replace('#', '').trim() : '';
                if (!childBugId || isNaN(childBugId)) {
                    alert('Geçerli bir talep numarası girin.');
                    return;
                }
                peDoBugViewAction('link_manual_child', bugId, this, { child_bug_id: childBugId });
            });
        });
    }

    function peOpenAdvanceModal(bugId, instructions, currentStep, nextStep) {
        var overlay = document.getElementById('pe-advance-overlay');
        var modal = document.getElementById('pe-advance-modal');
        if (!overlay || !modal) return;

        // Modal alanlarını doldur
        var currentEl = document.getElementById('pe-modal-current-step');
        var nextEl = document.getElementById('pe-modal-next-step');
        var instructionsBox = document.getElementById('pe-modal-instructions');
        var instructionsText = document.getElementById('pe-modal-instructions-text');
        var errorEl = document.getElementById('pe-modal-error');

        if (currentEl) currentEl.textContent = currentStep;
        if (nextEl) nextEl.textContent = nextStep;

        // Talimatlar varsa göster
        if (instructionsBox && instructionsText) {
            if (instructions && instructions.trim() !== '') {
                instructionsText.textContent = instructions;
                instructionsBox.style.display = 'block';
            } else {
                instructionsBox.style.display = 'none';
            }
        }
        if (errorEl) { errorEl.textContent = ''; errorEl.style.display = 'none'; }

        overlay.style.display = 'block';
        modal.style.display = 'block';

        // Onay butonu
        var confirmBtn = document.getElementById('pe-modal-confirm');
        var cancelBtn = document.getElementById('pe-modal-cancel');
        var closeBtn = document.getElementById('pe-modal-close');

        function closeModal() {
            overlay.style.display = 'none';
            modal.style.display = 'none';
        }

        function onConfirm() {
            closeModal();
            var advanceBtn = document.querySelector('.pe-bugview-advance[data-bug-id="' + bugId + '"]');
            if (advanceBtn) {
                peDoBugViewAction('advance_step', bugId, advanceBtn, {});
            }
        }

        // Eski dinleyicileri temizle (clone ile)
        var newConfirm = confirmBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newConfirm, confirmBtn);
        newConfirm.addEventListener('click', onConfirm);

        var newCancel = cancelBtn.cloneNode(true);
        cancelBtn.parentNode.replaceChild(newCancel, cancelBtn);
        newCancel.addEventListener('click', closeModal);

        var newClose = closeBtn.cloneNode(true);
        closeBtn.parentNode.replaceChild(newClose, closeBtn);
        newClose.addEventListener('click', closeModal);

        overlay.addEventListener('click', closeModal);
    }

    function peDoBugViewAction(action, bugId, btnEl, extraData) {
        var urlEl = document.getElementById('pe-bugview-action-url');
        var tokenEl = document.getElementById('pe-bugview-token');
        if (!urlEl || !tokenEl) return;

        var url = urlEl.value;
        var token = tokenEl.value;

        btnEl.disabled = true;
        var origHtml = btnEl.innerHTML;
        btnEl.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';

        var formData = new FormData();
        formData.append('action', action);
        formData.append('bug_id', bugId);
        formData.append('ProcessEngine_dashboard_action_token', token);

        if (extraData) {
            for (var key in extraData) {
                if (extraData.hasOwnProperty(key)) {
                    formData.append(key, extraData[key]);
                }
            }
        }

        var xhr = new XMLHttpRequest();
        xhr.open('POST', url, true);
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                btnEl.disabled = false;
                btnEl.innerHTML = origHtml;

                if (xhr.status === 200) {
                    try {
                        var resp = JSON.parse(xhr.responseText);
                        // CSRF token yenile
                        if (resp.new_token && tokenEl) {
                            tokenEl.value = resp.new_token;
                        }
                        if (resp.success) {
                            window.location.reload();
                        } else {
                            alert(resp.message || 'Hata oluştu.');
                        }
                    } catch (e) {
                        window.location.reload();
                    }
                } else {
                    window.location.reload();
                }
            }
        };
        xhr.send(formData);
    }

    function initActionButtons() {
        // Adımı Geri Al (Rollback) — dashboard
        document.querySelectorAll('.pe-action-rollback').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                var bugId = this.getAttribute('data-bug-id');
                if (!confirm(this.getAttribute('title') || 'Geri almak istediğinize emin misiniz?')) {
                    return;
                }
                peDoAction('rollback_step', bugId, this);
            });
        });

        // Adımı İlerlet
        document.querySelectorAll('.pe-action-advance').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                var bugId = this.getAttribute('data-bug-id');
                if (!confirm('Bu sorunu sonraki adıma ilerletmek istediğinize emin misiniz?')) {
                    return;
                }
                peDoAction('advance_step', bugId, this);
            });
        });

        // SLA Güncelle
        document.querySelectorAll('.pe-action-sla').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                var bugId = this.getAttribute('data-bug-id');
                peDoAction('refresh_sla', bugId, this);
            });
        });

        // Global SLA Kontrol (MANAGER+)
        var globalSlaBtn = document.querySelector('.pe-sla-global-check');
        if (globalSlaBtn) {
            globalSlaBtn.addEventListener('click', function(e) {
                e.preventDefault();
                // bug_id=0 gönder, action handler bunu kullanmaz
                peDoAction('global_sla_check', 0, this);
            });
        }
    }

    function peDoAction(action, bugId, btnEl) {
        var urlEl = document.getElementById('pe-action-url');
        var tokenEl = document.getElementById('pe-security-token');
        if (!urlEl || !tokenEl) return;

        var url = urlEl.value;
        var token = tokenEl.value;

        // Butonu devre dışı bırak
        btnEl.disabled = true;
        var origHtml = btnEl.innerHTML;
        btnEl.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';

        var formData = new FormData();
        formData.append('action', action);
        formData.append('bug_id', bugId);
        formData.append('ProcessEngine_dashboard_action_token', token);

        var xhr = new XMLHttpRequest();
        xhr.open('POST', url, true);
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                btnEl.disabled = false;
                btnEl.innerHTML = origHtml;

                if (xhr.status === 200) {
                    try {
                        var resp = JSON.parse(xhr.responseText);
                        // CSRF token yenile
                        if (resp.new_token && tokenEl) {
                            tokenEl.value = resp.new_token;
                        }
                        if (resp.success) {
                            window.location.reload();
                        } else {
                            alert(resp.message || 'Hata oluştu.');
                        }
                    } catch (e) {
                        // JSON parse hatası — sayfa yönlendirmesi olmuş olabilir, yeniden yükle
                        window.location.reload();
                    }
                } else {
                    // HTTP hata — sayfa yönlendirmesi olmuş olabilir, yeniden yükle
                    window.location.reload();
                }
            }
        };
        xhr.send(formData);
    }

    function startAutoRefresh() {
        if (refreshTimer) {
            clearInterval(refreshTimer);
        }
        refreshTimer = setInterval(function() {
            // Only refresh if page is visible
            if (!document.hidden) {
                window.location.reload();
            }
        }, PE_REFRESH_INTERVAL);
    }

    function initAll() {
        // Dashboard sayfa elemanları varsa dashboard'u başlat
        if (document.getElementById('pe-action-url')) {
            initDashboard();
        }
        // Bug view sayfasındaki ilerleme/subprocess butonları varsa başlat
        if (document.querySelector('.pe-bugview-advance') ||
            document.querySelector('.pe-bugview-rollback') ||
            document.querySelector('.pe-create-subprocess') ||
            document.querySelector('.pe-link-child-btn')) {
            initBugViewActions();
        }
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }
})();
