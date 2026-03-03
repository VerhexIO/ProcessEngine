/**
 * ProcessEngine - SLA Dashboard JS
 *
 * Handles auto-refresh and visual updates for the SLA monitoring page.
 */
(function() {
    'use strict';

    var SLA_REFRESH_INTERVAL = 30000; // 30 seconds

    function init() {
        highlightOverdue();
        startAutoRefresh();
    }

    function highlightOverdue() {
        // Add pulsing effect to exceeded badges
        var badges = document.querySelectorAll('.pe-sla-exceeded');
        badges.forEach(function(badge) {
            badge.style.animation = 'pe-pulse 2s infinite';
        });

        // Add CSS animation if not already present
        if (!document.getElementById('pe-sla-animations')) {
            var style = document.createElement('style');
            style.id = 'pe-sla-animations';
            style.textContent =
                '@keyframes pe-pulse { ' +
                '0%, 100% { opacity: 1; } ' +
                '50% { opacity: 0.6; } ' +
                '}';
            document.head.appendChild(style);
        }
    }

    function startAutoRefresh() {
        setInterval(function() {
            if (!document.hidden) {
                window.location.reload();
            }
        }, SLA_REFRESH_INTERVAL);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
