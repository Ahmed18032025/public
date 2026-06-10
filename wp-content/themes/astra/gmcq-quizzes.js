/**
 * Government MCQ - All Quizzes Page JavaScript
 *
 * AJAX filtering, search, sort, pagination, load more, view toggle.
 * Vanilla JS. No jQuery dependency.
 * Reuses Intersection Observer from gmcq-homepage.js.
 *
 * @package Astra
 * @subpackage Government_MCQ
 */

(function () {
    'use strict';

    // ============================================================
    // STATE
    // ============================================================
    var state = {
        search: '',
        categoryId: 0,
        sort: 'newest',
        currentPage: 1,
        totalPages: 1,
        isLoading: false,
        isListView: false,
    };

    // ============================================================
    // DOM REFERENCES
    // ============================================================
    var grid, searchInput, categorySelect, sortSelect, paginationEl, loadMoreBtn, viewToggle, loadingEl, totalEl;

    function cacheDom() {
        grid = document.getElementById('gmcq-q-grid');
        searchInput = document.getElementById('gmcq-q-search');
        categorySelect = document.getElementById('gmcq-q-category');
        sortSelect = document.getElementById('gmcq-q-sort');
        paginationEl = document.getElementById('gmcq-q-pagination');
        loadMoreBtn = document.getElementById('gmcq-q-loadmore');
        viewToggle = document.getElementById('gmcq-q-view-toggle');
        loadingEl = document.getElementById('gmcq-q-loading');
        totalEl = document.getElementById('gmcq-q-total');
    }

    // ============================================================
    // UTILITY
    // ============================================================

    /**
     * Debounce function.
     */
    function debounce(fn, delay) {
        var timeout;
        return function () {
            var context = this;
            var args = arguments;
            clearTimeout(timeout);
            timeout = setTimeout(function () {
                fn.apply(context, args);
            }, delay);
        };
    }

    /**
     * Check if user prefers reduced motion.
     */
    function prefersReducedMotion() {
        return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    }

    // ============================================================
    // AJAX FETCH
    // ============================================================

    /**
     * Fetch filtered quizzes from server.
     *
     * @param {boolean} append - If true, append results to existing grid instead of replacing.
     */
    function fetchQuizzes(append) {
        if (state.isLoading) {
            return;
        }
        state.isLoading = true;

        // Show loading indicator
        if (loadingEl) {
            loadingEl.style.display = 'flex';
        }
        if (loadMoreBtn) {
            loadMoreBtn.disabled = true;
            loadMoreBtn.innerHTML = '<i class="fas fa-spinner fa-spin" aria-hidden="true"></i> ' + (gmcqQuizzes.labels.loading || 'Loading...');
        }

        var formData = new FormData();
        formData.append('action', 'gmcq_filter_quizzes');
        formData.append('nonce', gmcqQuizzes.nonce);
        formData.append('search', state.search);
        formData.append('category_id', state.categoryId);
        formData.append('sort', state.sort);
        formData.append('page', state.currentPage);
        formData.append('per_page', 12);

        fetch(gmcqQuizzes.ajaxUrl, {
            method: 'POST',
            body: formData,
        })
        .then(function (response) {
            return response.json();
        })
        .then(function (result) {
            state.isLoading = false;

            // Hide loading
            if (loadingEl) {
                loadingEl.style.display = 'none';
            }
            if (loadMoreBtn) {
                loadMoreBtn.disabled = false;
                loadMoreBtn.innerHTML = '<i class="fas fa-chevron-down" aria-hidden="true"></i> ' + gmcqQuizzes.labels.loadMore;
            }

            if (result.success) {
                var data = result.data;

                // Update total count
                if (totalEl) {
                    totalEl.textContent = Number(data.total).toLocaleString();
                }

                // Update state
                state.totalPages = data.totalPages;
                state.currentPage = data.current;

                // Update grid
                if (append) {
                    grid.insertAdjacentHTML('beforeend', data.cards);
                } else {
                    grid.innerHTML = data.cards;
                    // Reset scroll position to top of grid
                    var gridSection = document.getElementById('gmcq-q-grid-section');
                    if (gridSection) {
                        gridSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                }

                // Update pagination
                if (paginationEl) {
                    paginationEl.outerHTML = data.pagination;
                    paginationEl = document.querySelector('.gmcq-q-pagination');
                    bindPagination();
                }

                // Update load more button visibility
                if (loadMoreBtn) {
                    var loadMoreWrap = document.getElementById('gmcq-q-loadmore-wrap');
                    if (loadMoreWrap) {
                        loadMoreWrap.style.display = data.hasMore ? 'block' : 'none';
                    }
                }

                // Maintain list view class if active
                if (state.isListView) {
                    grid.classList.add('gmcq-q-list-view');
                } else {
                    grid.classList.remove('gmcq-q-list-view');
                }

                // Apply scroll animations to new cards
                initCardAnimations();
            } else {
                // Error
                if (!append) {
                    grid.innerHTML = '<div class="gmcq-q-empty"><i class="fas fa-exclamation-triangle" aria-hidden="true"></i><h3>' + gmcqQuizzes.labels.error + '</h3></div>';
                }
            }
        })
        .catch(function () {
            state.isLoading = false;
            if (loadingEl) {
                loadingEl.style.display = 'none';
            }
            if (loadMoreBtn) {
                loadMoreBtn.disabled = false;
                loadMoreBtn.innerHTML = '<i class="fas fa-chevron-down" aria-hidden="true"></i> ' + (gmcqQuizzes.labels.loadMore || 'Load More');
            }
            if (!append) {
                grid.innerHTML = '<div class="gmcq-q-empty"><i class="fas fa-exclamation-triangle" aria-hidden="true"></i><h3>' + gmcqQuizzes.labels.error + '</h3></div>';
            }
        });
    }

    // ============================================================
    // FILTER HANDLERS
    // ============================================================

    /**
     * Apply filters and reload.
     */
    function applyFilters() {
        state.currentPage = 1;
        fetchQuizzes(false);
    }

    /**
     * Debounced search handler.
     */
    var handleSearch = debounce(function () {
        state.search = searchInput.value.trim();
        applyFilters();
    }, 300);

    /**
     * Category change handler.
     */
    function handleCategoryChange() {
        state.categoryId = parseInt(categorySelect.value) || 0;
        applyFilters();
    }

    /**
     * Sort change handler.
     */
    function handleSortChange() {
        state.sort = sortSelect.value;
        applyFilters();
    }

    /**
     * Pagination click handler.
     */
    function handlePaginationClick(e) {
        var target = e.target;
        if (target.tagName === 'A' && target.hasAttribute('data-page')) {
            e.preventDefault();
            var page = parseInt(target.getAttribute('data-page'));
            if (page && page !== state.currentPage) {
                state.currentPage = page;
                fetchQuizzes(false);
            }
        }
    }

    /**
     * Load More button handler.
     */
    function handleLoadMore() {
        if (state.isLoading) {
            return;
        }
        state.currentPage++;
        fetchQuizzes(true);
    }

    /**
     * View toggle handler.
     */
    function handleViewToggle() {
        state.isListView = !state.isListView;
        if (grid) {
            if (state.isListView) {
                grid.classList.add('gmcq-q-list-view');
                if (viewToggle) {
                    viewToggle.classList.add('gmcq-q-view-list');
                    viewToggle.innerHTML = '<i class="fas fa-th-large" aria-hidden="true"></i>';
                    viewToggle.setAttribute('aria-label', 'Switch to grid view');
                }
            } else {
                grid.classList.remove('gmcq-q-list-view');
                if (viewToggle) {
                    viewToggle.classList.remove('gmcq-q-view-list');
                    viewToggle.innerHTML = '<i class="fas fa-list" aria-hidden="true"></i>';
                    viewToggle.setAttribute('aria-label', 'Switch to list view');
                }
            }
            // Persist preference
            try {
                localStorage.setItem('gmcq_quiz_view', state.isListView ? 'list' : 'grid');
            } catch (e) {
                // localStorage not available
            }
        }
    }

    // ============================================================
    // BIND EVENTS
    // ============================================================

    function bindSearch() {
        if (searchInput) {
            searchInput.addEventListener('input', handleSearch);
            searchInput.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') {
                    searchInput.value = '';
                    state.search = '';
                    applyFilters();
                    searchInput.blur();
                }
            });
        }
    }

    function bindCategory() {
        if (categorySelect) {
            categorySelect.addEventListener('change', handleCategoryChange);
        }
    }

    function bindSort() {
        if (sortSelect) {
            sortSelect.addEventListener('change', handleSortChange);
        }
    }

    function bindPagination() {
        if (paginationEl) {
            paginationEl.addEventListener('click', handlePaginationClick);
        }
    }

    function bindLoadMore() {
        if (loadMoreBtn) {
            loadMoreBtn.addEventListener('click', handleLoadMore);
        }
    }

    function bindViewToggle() {
        if (viewToggle) {
            viewToggle.addEventListener('click', handleViewToggle);
            // Restore preference
            try {
                var saved = localStorage.getItem('gmcq_quiz_view');
                if (saved === 'list') {
                    state.isListView = true;
                    grid.classList.add('gmcq-q-list-view');
                    viewToggle.classList.add('gmcq-q-view-list');
                    viewToggle.innerHTML = '<i class="fas fa-th-large" aria-hidden="true"></i>';
                }
            } catch (e) {
                // localStorage not available
            }
        }
    }

    // ============================================================
    // CARD ANIMATIONS
    // ============================================================

    function initCardAnimations() {
        if (prefersReducedMotion() || !('IntersectionObserver' in window)) {
            // Make all cards visible immediately
            var cards = grid ? grid.querySelectorAll('.gmcq-q-card') : [];
            for (var i = 0; i < cards.length; i++) {
                cards[i].classList.add('gmcq-visible');
            }
            return;
        }

        var observer = new IntersectionObserver(
            function (entries) {
                for (var i = 0; i < entries.length; i++) {
                    if (entries[i].isIntersecting) {
                        var el = entries[i].target;
                        var delay = parseInt(el.getAttribute('data-delay')) || 0;
                        setTimeout(function () {
                            el.classList.add('gmcq-visible');
                        }, delay);
                        observer.unobserve(el);
                    }
                }
            },
            { threshold: 0.1, rootMargin: '0px 0px -30px 0px' }
        );

        var cards = grid ? grid.querySelectorAll('.gmcq-q-card') : [];
        for (var j = 0; j < cards.length; j++) {
            // Add animation class and set delay
            cards[j].classList.add('gmcq-animate-fade-in-up');
            if (!cards[j].getAttribute('data-delay')) {
                cards[j].setAttribute('data-delay', (j % 12) * 80);
            }
            observer.observe(cards[j]);
        }
    }

    // ============================================================
    // INITIALIZATION
    // ============================================================

    function init() {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', run);
        } else {
            run();
        }

        function run() {
            cacheDom();
            if (!grid) {
                return; // Not on quizzes page
            }

            bindSearch();
            bindCategory();
            bindSort();
            bindPagination();
            bindLoadMore();
            bindViewToggle();

            // Animate initial cards
            initCardAnimations();
        }
    }

    init();
})();