/**
 * NanoPub Main JavaScript
 * 
 * Handles form submissions, infinite scroll, status interactions,
 * and notification updates using vanilla JavaScript.
 */

(function () {
    'use strict';

    // ============================================
    // Utility Functions
    // ============================================

    /**
     * Get CSRF token from meta tag or data attribute
     */
    function getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) return meta.getAttribute('content');

        const form = document.querySelector('[data-csrf]');
        if (form) return form.getAttribute('data-csrf');

        return '';
    }

    /**
     * Make an API request
     */
    async function apiRequest(url, method = 'GET', data = null) {
        const options = {
            method: method,
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-Token': getCsrfToken()
            },
            credentials: 'same-origin'
        };

        if (data && method !== 'GET') {
            options.body = JSON.stringify(data);
        }

        const response = await fetch(url, options);

        if (!response.ok) {
            const error = await response.json().catch(() => ({}));
            throw new Error(error.error || 'Request failed');
        }

        if (response.status === 204) {
            return null;
        }

        return response.json();
    }

    /**
     * Debounce function
     */
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    /**
     * Show toast notification
     */
    function showToast(message, type = 'info') {
        const container = document.getElementById('toast-container') || createToastContainer();

        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'polite');
        toast.innerHTML = `
            <span class="toast-message">${escapeHtml(message)}</span>
            <button type="button" class="toast-close" aria-label="Close">
                <svg width="16" height="16" viewBox="0 0 16 16">
                    <path d="M4 4l8 8M12 4l-8 8" fill="none" stroke="currentColor" stroke-width="2"/>
                </svg>
            </button>
        `;

        container.appendChild(toast);

        // Auto remove after 5 seconds
        setTimeout(() => {
            toast.classList.add('fade-out');
            setTimeout(() => toast.remove(), 300);
        }, 5000);

        // Close button
        toast.querySelector('.toast-close').addEventListener('click', () => {
            toast.classList.add('fade-out');
            setTimeout(() => toast.remove(), 300);
        });
    }

    function createToastContainer() {
        const container = document.createElement('div');
        container.id = 'toast-container';
        container.setAttribute('aria-live', 'polite');
        container.setAttribute('aria-atomic', 'true');
        document.body.appendChild(container);
        return container;
    }

    /**
     * Escape HTML entities
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // ============================================
    // Form Submission Handling
    // ============================================

    const FormHandler = {
        init() {
            document.addEventListener('submit', this.handleSubmit.bind(this));
        },

        async handleSubmit(event) {
            const form = event.target;

            // Only handle forms with data-ajax attribute or API forms
            if (!form.hasAttribute('data-ajax') && !form.action.includes('/api/')) {
                return;
            }

            event.preventDefault();

            const submitBtn = form.querySelector('[type="submit"]');
            const originalText = submitBtn ? submitBtn.textContent : '';

            try {
                // Disable submit button
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.textContent = 'Submitting...';
                }

                const formData = new FormData(form);
                const data = Object.fromEntries(formData.entries());

                const result = await apiRequest(form.action, form.method, data);

                // Handle success
                this.handleSuccess(form, result);

            } catch (error) {
                // Handle error
                this.handleError(form, error.message);
            } finally {
                // Re-enable submit button
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                }
            }
        },

        handleSuccess(form, result) {
            // Show success message
            showToast(result?.message || 'Saved successfully', 'success');

            // Dispatch custom event
            form.dispatchEvent(new CustomEvent('form:success', {
                detail: result,
                bubbles: true
            }));

            // Reset form if specified
            if (form.hasAttribute('data-reset-on-success')) {
                form.reset();
            }

            // Redirect if specified
            const redirect = form.getAttribute('data-redirect');
            if (redirect) {
                window.location.href = redirect;
            }
        },

        handleError(form, message) {
            showToast(message, 'error');

            form.dispatchEvent(new CustomEvent('form:error', {
                detail: { message },
                bubbles: true
            }));
        }
    };

    // ============================================
    // Status Interactions
    // ============================================

    const StatusInteractions = {
        init() {
            document.addEventListener('click', this.handleClick.bind(this));
        },

        handleClick(event) {
            const btn = event.target.closest('[data-action]');
            if (!btn) return;

            const action = btn.getAttribute('data-action');

            switch (action) {
                case 'favourite':
                case 'unfavourite':
                    this.toggleFavourite(btn);
                    break;
                case 'boost':
                case 'unboost':
                    this.toggleBoost(btn);
                    break;
                case 'bookmark':
                case 'unbookmark':
                    this.toggleBookmark(btn);
                    break;
                case 'delete-status':
                    this.deleteStatus(btn);
                    break;
                case 'toggle-cw':
                    this.toggleContentWarning(btn);
                    break;
            }
        },

        async toggleFavourite(btn) {
            const statusId = btn.getAttribute('data-status-id');
            const isFavourited = btn.classList.contains('active');
            const action = isFavourited ? 'unfavourite' : 'favourite';

            try {
                const result = await apiRequest(
                    `/api/v1/statuses/${statusId}/${action}`,
                    'POST'
                );

                btn.classList.toggle('active');
                btn.setAttribute('aria-pressed', !isFavourited);

                // Update count
                this.updateCount(btn, result.favourites_count);

            } catch (error) {
                showToast(error.message, 'error');
            }
        },

        async toggleBoost(btn) {
            const statusId = btn.getAttribute('data-status-id');
            const isBoosted = btn.classList.contains('active');
            const action = isBoosted ? 'unboost' : 'boost';

            try {
                const result = await apiRequest(
                    `/api/v1/statuses/${statusId}/${action}`,
                    'POST'
                );

                btn.classList.toggle('active');
                btn.setAttribute('aria-pressed', !isBoosted);

                this.updateCount(btn, result.reblogs_count);

            } catch (error) {
                showToast(error.message, 'error');
            }
        },

        async toggleBookmark(btn) {
            const statusId = btn.getAttribute('data-status-id');
            const isBookmarked = btn.classList.contains('active');
            const action = isBookmarked ? 'unbookmark' : 'bookmark';

            try {
                await apiRequest(
                    `/api/v1/statuses/${statusId}/${action}`,
                    'POST'
                );

                btn.classList.toggle('active');
                btn.setAttribute('aria-pressed', !isBookmarked);

                showToast(isBookmarked ? 'Removed from bookmarks' : 'Added to bookmarks', 'success');

            } catch (error) {
                showToast(error.message, 'error');
            }
        },

        async deleteStatus(btn) {
            const statusId = btn.getAttribute('data-status-id');

            if (!confirm('Are you sure you want to delete this post?')) {
                return;
            }

            try {
                await apiRequest(`/api/v1/statuses/${statusId}`, 'DELETE');

                // Remove status from DOM
                const statusEl = btn.closest('.status');
                if (statusEl) {
                    statusEl.classList.add('fade-out');
                    setTimeout(() => statusEl.remove(), 300);
                }

                showToast('Post deleted', 'success');

            } catch (error) {
                showToast(error.message, 'error');
            }
        },

        toggleContentWarning(btn) {
            const statusEl = btn.closest('.status');
            const textEl = statusEl.querySelector('.status-text');
            const isExpanded = btn.getAttribute('aria-expanded') === 'true';

            textEl.classList.toggle('hidden');
            btn.setAttribute('aria-expanded', !isExpanded);
            btn.textContent = isExpanded ? 'Show more' : 'Show less';
        },

        updateCount(btn, count) {
            const countEl = btn.closest('.status-footer')
                ?.querySelector('.stat strong');
            if (countEl) {
                countEl.textContent = count.toLocaleString();
            }
        }
    };

    // ============================================
    // Follow/Unfollow
    // ============================================

    const FollowHandler = {
        init() {
            document.addEventListener('click', this.handleClick.bind(this));
        },

        async handleClick(event) {
            const btn = event.target.closest('[data-action="follow"], [data-action="unfollow"]');
            if (!btn) return;

            event.preventDefault();

            const accountId = btn.getAttribute('data-account-id');
            const action = btn.getAttribute('data-action');

            try {
                const result = await apiRequest(
                    `/api/v1/accounts/${accountId}/${action}`,
                    'POST'
                );

                // Update button state
                if (action === 'follow') {
                    btn.setAttribute('data-action', 'unfollow');
                    btn.textContent = 'Following';
                    btn.classList.remove('btn-primary');
                    btn.classList.add('btn-secondary');
                } else {
                    btn.setAttribute('data-action', 'follow');
                    btn.textContent = 'Follow';
                    btn.classList.remove('btn-secondary');
                    btn.classList.add('btn-primary');
                }

                // Dispatch event for other components
                document.dispatchEvent(new CustomEvent('follow:changed', {
                    detail: { accountId, following: action === 'follow' }
                }));

            } catch (error) {
                showToast(error.message, 'error');
            }
        }
    };

    // ============================================
    // Infinite Scroll
    // ============================================

    const InfiniteScroll = {
        init() {
            const container = document.querySelector('[data-infinite-scroll]');
            if (!container) return;

            this.container = container;
            this.loading = false;
            this.hasMore = true;
            this.nextUrl = container.getAttribute('data-next-url');

            // Use IntersectionObserver for better performance
            const sentinel = document.createElement('div');
            sentinel.className = 'infinite-scroll-sentinel';
            container.appendChild(sentinel);

            const observer = new IntersectionObserver(
                debounce(this.handleIntersection.bind(this), 200),
                { rootMargin: '200px' }
            );

            observer.observe(sentinel);
        },

        async handleIntersection(entries) {
            if (this.loading || !this.hasMore) return;

            const entry = entries[0];
            if (!entry.isIntersecting) return;

            this.loading = true;

            try {
                const response = await fetch(this.nextUrl, {
                    headers: { 'Accept': 'application/json' }
                });

                if (!response.ok) throw new Error('Failed to load');

                const data = await response.json();

                // Append new content
                this.appendContent(data);

                // Update next URL
                this.nextUrl = data.next_url || null;
                this.hasMore = !!this.nextUrl;

            } catch (error) {
                console.error('Infinite scroll error:', error);
            } finally {
                this.loading = false;
            }
        },

        appendContent(data) {
            const listEl = this.container.querySelector('.status-list, .follow-list');
            if (!listEl || !data.html) return;

            // Create temporary container
            const temp = document.createElement('div');
            temp.innerHTML = data.html;

            // Append each item
            temp.querySelectorAll(':scope > *').forEach(item => {
                listEl.appendChild(item);
            });

            // Dispatch event
            this.container.dispatchEvent(new CustomEvent('content:appended', {
                detail: { count: temp.children.length }
            }));
        }
    };

    // ============================================
    // Compose Form
    // ============================================

    const ComposeForm = {
        init() {
            const textarea = document.querySelector('.compose-textarea');
            if (!textarea) return;

            this.textarea = textarea;
            this.counter = document.querySelector('.char-counter');
            this.maxChars = parseInt(textarea.getAttribute('maxlength')) || 500;

            textarea.addEventListener('input', this.updateCounter.bind(this));
            textarea.addEventListener('keydown', this.handleKeydown.bind(this));

            // Initial count
            this.updateCounter();
        },

        updateCounter() {
            if (!this.counter) return;

            const remaining = this.maxChars - this.textarea.value.length;
            this.counter.textContent = remaining;

            this.counter.classList.remove('warning', 'error');
            if (remaining < 0) {
                this.counter.classList.add('error');
            } else if (remaining < 50) {
                this.counter.classList.add('warning');
            }
        },

        handleKeydown(event) {
            // Submit on Ctrl+Enter or Cmd+Enter
            if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
                event.preventDefault();
                this.textarea.form?.dispatchEvent(new Event('submit', { bubbles: true }));
            }
        }
    };

    // ============================================
    // Notification Updates
    // ============================================

    const NotificationUpdater = {
        init() {
            // Only run if user is logged in and on a page that needs updates
            const badge = document.querySelector('[data-notification-count]');
            if (!badge) return;

            this.badge = badge;
            this.lastId = badge.getAttribute('data-last-id') || '0';

            // Poll every 30 seconds
            this.pollInterval = setInterval(this.poll.bind(this), 30000);

            // Also use EventSource if available
            this.initEventSource();
        },

        async poll() {
            try {
                const data = await apiRequest('/api/v1/notifications?since_id=' + this.lastId);

                if (data && data.length > 0) {
                    this.updateBadge(data.length);
                    this.lastId = data[0].id;
                }

            } catch (error) {
                console.error('Notification poll error:', error);
            }
        },

        initEventSource() {
            if (typeof EventSource === 'undefined') return;

            const streamUrl = document.querySelector('[data-stream-url]')?.getAttribute('data-stream-url');
            if (!streamUrl) return;

            const source = new EventSource(streamUrl);

            source.addEventListener('notification', (event) => {
                const data = JSON.parse(event.data);
                this.updateBadge(1);
                this.lastId = data.id;
                showToast('New notification', 'info');
            });

            source.onerror = () => {
                console.log('EventSource connection lost, falling back to polling');
            };
        },

        updateBadge(count) {
            const current = parseInt(this.badge.textContent) || 0;
            const newCount = current + count;

            this.badge.textContent = newCount;
            this.badge.classList.toggle('hidden', newCount === 0);

            // Update document title
            const title = document.title.replace(/^\(\d+\)\s*/, '');
            document.title = newCount > 0 ? `(${newCount}) ${title}` : title;
        }
    };

    // ============================================
    // Dropdown Menus
    // ============================================

    const DropdownHandler = {
        init() {
            document.addEventListener('click', this.handleClick.bind(this));
            document.addEventListener('keydown', this.handleKeydown.bind(this));
        },

        handleClick(event) {
            const toggle = event.target.closest('.dropdown-toggle');

            // Close all dropdowns if clicking outside
            if (!toggle) {
                this.closeAll();
                return;
            }

            event.preventDefault();

            const dropdown = toggle.nextElementSibling;
            const isOpen = !dropdown.classList.contains('hidden');

            this.closeAll();

            if (!isOpen) {
                dropdown.classList.remove('hidden');
                dropdown.setAttribute('aria-hidden', 'false');
                toggle.setAttribute('aria-expanded', 'true');

                // Focus first item
                const firstItem = dropdown.querySelector('a, button');
                if (firstItem) firstItem.focus();
            }
        },

        handleKeydown(event) {
            if (event.key === 'Escape') {
                this.closeAll();
            }

            // Arrow key navigation
            const dropdown = event.target.closest('.dropdown-menu');
            if (!dropdown) return;

            const items = Array.from(dropdown.querySelectorAll('a, button'));
            const currentIndex = items.indexOf(event.target);

            if (event.key === 'ArrowDown' && currentIndex < items.length - 1) {
                event.preventDefault();
                items[currentIndex + 1].focus();
            } else if (event.key === 'ArrowUp' && currentIndex > 0) {
                event.preventDefault();
                items[currentIndex - 1].focus();
            }
        },

        closeAll() {
            document.querySelectorAll('.dropdown-menu').forEach(dropdown => {
                dropdown.classList.add('hidden');
                dropdown.setAttribute('aria-hidden', 'true');
            });

            document.querySelectorAll('.dropdown-toggle').forEach(toggle => {
                toggle.setAttribute('aria-expanded', 'false');
            });
        }
    };

    // ============================================
    // Tab Panels
    // ============================================

    const TabHandler = {
        init() {
            document.addEventListener('click', this.handleClick.bind(this));
            document.addEventListener('keydown', this.handleKeydown.bind(this));
        },

        handleClick(event) {
            const tab = event.target.closest('[role="tab"]');
            if (!tab) return;

            event.preventDefault();
            this.activateTab(tab);
        },

        handleKeydown(event) {
            const tab = event.target.closest('[role="tab"]');
            if (!tab) return;

            const tabs = Array.from(tab.parentElement.querySelectorAll('[role="tab"]'));
            const currentIndex = tabs.indexOf(tab);

            let newTab = null;

            if (event.key === 'ArrowRight' && currentIndex < tabs.length - 1) {
                newTab = tabs[currentIndex + 1];
            } else if (event.key === 'ArrowLeft' && currentIndex > 0) {
                newTab = tabs[currentIndex - 1];
            } else if (event.key === 'Home') {
                newTab = tabs[0];
            } else if (event.key === 'End') {
                newTab = tabs[tabs.length - 1];
            }

            if (newTab) {
                event.preventDefault();
                this.activateTab(newTab);
                newTab.focus();
            }
        },

        activateTab(tab) {
            const tabList = tab.parentElement;
            const panelId = tab.getAttribute('aria-controls');

            // Update tabs
            tabList.querySelectorAll('[role="tab"]').forEach(t => {
                t.classList.remove('active');
                t.setAttribute('aria-selected', 'false');
            });

            tab.classList.add('active');
            tab.setAttribute('aria-selected', 'true');

            // Update panels
            document.querySelectorAll('[role="tabpanel"]').forEach(panel => {
                panel.classList.remove('active');
                panel.hidden = true;
            });

            const panel = document.getElementById(panelId);
            if (panel) {
                panel.classList.add('active');
                panel.hidden = false;
            }
        }
    };

    // ============================================
    // Image Preview
    // ============================================

    const ImagePreview = {
        init() {
            document.addEventListener('change', this.handleChange.bind(this));
        },

        handleChange(event) {
            const input = event.target;
            if (input.type !== 'file' || !input.accept.includes('image')) return;

            const previewId = input.getAttribute('data-preview');
            if (!previewId) return;

            const preview = document.getElementById(previewId);
            if (!preview) return;

            const file = input.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = (e) => {
                preview.src = e.target.result;
            };
            reader.readAsDataURL(file);
        }
    };

    // ============================================
    // Settings Rules Management
    // ============================================

    const RulesManager = {
        init() {
            const addBtn = document.querySelector('[data-action="add-rule"]');
            if (!addBtn) return;

            this.template = document.getElementById('rule-template');
            this.container = document.getElementById('rules-list');

            addBtn.addEventListener('click', this.addRule.bind(this));
            document.addEventListener('click', this.handleClick.bind(this));
        },

        addRule() {
            if (!this.template || !this.container) return;

            const index = this.container.children.length;
            const html = this.template.innerHTML
                .replace(/__INDEX__/g, index)
                .replace(/__NUMBER__/g, index + 1);

            const temp = document.createElement('div');
            temp.innerHTML = html.trim();

            this.container.appendChild(temp.firstChild);
        },

        handleClick(event) {
            const btn = event.target.closest('[data-action="remove-rule"]');
            if (!btn) return;

            const ruleItem = btn.closest('.rule-item');
            if (ruleItem) {
                ruleItem.remove();
                this.renumberRules();
            }
        },

        renumberRules() {
            const rules = this.container.querySelectorAll('.rule-item');
            rules.forEach((rule, index) => {
                rule.querySelector('.rule-number').textContent = index + 1;
                rule.setAttribute('data-rule-index', index);

                const input = rule.querySelector('input');
                input.name = `rules[${index}]`;
            });
        }
    };

    // ============================================
    // Initialize
    // ============================================

    function init() {
        FormHandler.init();
        StatusInteractions.init();
        FollowHandler.init();
        InfiniteScroll.init();
        ComposeForm.init();
        NotificationUpdater.init();
        DropdownHandler.init();
        TabHandler.init();
        ImagePreview.init();
        RulesManager.init();

        // Add toast container styles
        const style = document.createElement('style');
        style.textContent = `
            #toast-container {
                position: fixed;
                bottom: 1rem;
                right: 1rem;
                z-index: 1000;
                display: flex;
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .toast {
                display: flex;
                align-items: center;
                gap: 0.75rem;
                padding: 0.75rem 1rem;
                background-color: #1a1a1a;
                color: #fff;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                animation: slideIn 0.3s ease;
            }
            
            .toast-success { background-color: #22c55e; }
            .toast-error { background-color: #dc2626; }
            .toast-info { background-color: #2d5be3; }
            
            .toast-close {
                padding: 0.25rem;
                color: inherit;
                opacity: 0.7;
            }
            
            .toast-close:hover { opacity: 1; }
            
            .toast.fade-out {
                animation: fadeOut 0.3s ease forwards;
            }
            
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            
            @keyframes fadeOut {
                from { opacity: 1; }
                to { opacity: 0; }
            }
            
            .fade-out {
                animation: fadeOut 0.3s ease forwards;
            }
            
            .infinite-scroll-sentinel {
                height: 1px;
            }
        `;
        document.head.appendChild(style);
    }

    // Mobile menu toggle
    document.addEventListener('click', function(e) {
        const toggle = e.target.closest('.mobile-menu-toggle');
        if (toggle) {
            const nav = document.querySelector('.header-nav');
            if (nav) {
                nav.classList.toggle('mobile-open');
                toggle.setAttribute('aria-expanded', 
                    toggle.getAttribute('aria-expanded') === 'true' ? 'false' : 'true'
                );
            }
        }
    });

    // Run on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();