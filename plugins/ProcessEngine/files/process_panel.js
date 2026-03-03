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

        // Setup action buttons
        initActionButtons();

        // Setup auto-refresh
        startAutoRefresh();
    }

    function initActionButtons() {
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

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDashboard);
    } else {
        initDashboard();
    }
})();
