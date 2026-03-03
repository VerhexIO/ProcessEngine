/**
 * ProcessEngine - Process Tree JavaScript
 *
 * Provides expand/collapse functionality for the process tree view.
 */
(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        // Expand/collapse toggle
        var toggles = document.querySelectorAll('.pe-tree-toggle');
        for (var i = 0; i < toggles.length; i++) {
            toggles[i].addEventListener('click', function(e) {
                e.preventDefault();
                var node = this.closest('.pe-tree-node');
                if (!node) return;
                var children = node.querySelector('.pe-tree-children');
                if (!children) return;

                if (children.classList.contains('collapsed')) {
                    children.classList.remove('collapsed');
                    this.classList.remove('collapsed');
                } else {
                    children.classList.add('collapsed');
                    this.classList.add('collapsed');
                }
            });
        }

        // Scroll target node into view
        var target = document.querySelector('.pe-tree-node-target');
        if (target) {
            target.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });
})();
