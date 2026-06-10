/**
 * Government MCQ - Blog Page JavaScript
 *
 * Category filter pills, scroll animations, reading time enhancements.
 * Vanilla JS. No jQuery dependency.
 *
 * @package Astra
 * @subpackage Government_MCQ
 */

(function () {
    'use strict';

    // ============================================================
    // CATEGORY FILTER PILLS
    // ============================================================
    function initCategoryFilters() {
        var filterContainer = document.querySelector('.gmcq-blog-filters');
        if (!filterContainer) {
            return;
        }

        var pills = filterContainer.querySelectorAll('.gmcq-blog-filter-pill');

        pills.forEach(function (pill) {
            pill.addEventListener('click', function () {
                var categoryId = this.getAttribute('data-category');

                // Update active state
                pills.forEach(function (p) {
                    p.classList.remove('active');
                });
                this.classList.add('active');

                // Navigate to category archive or show all
                if (categoryId && categoryId !== '0') {
                    // Redirect to category archive page
                    var categoryUrl = this.getAttribute('data-url');
                    if (categoryUrl) {
                        window.location.href = categoryUrl;
                    }
                } else {
                    // Show all - go to blog home
                    var blogUrl = filterContainer.getAttribute('data-blog-url');
                    if (blogUrl) {
                        window.location.href = blogUrl;
                    }
                }
            });

            // Store category URL in data attribute from WordPress category link
            var catId = pill.getAttribute('data-category');
            if (catId && catId !== '0') {
                var catLink = document.querySelector('.gmcq-blog-widget-categories a[href*="category"]');
                // We'll use the category archive links from sidebar as reference
                var sidebarLinks = document.querySelectorAll('.gmcq-blog-widget-categories a');
                sidebarLinks.forEach(function (link) {
                    var href = link.getAttribute('href');
                    if (href && href.indexOf('cat_' + catId) > -1) {
                        pill.setAttribute('data-url', href);
                    }
                });
            }
        });
    }

    // ============================================================
    // CARD ANIMATIONS (Intersection Observer)
    // ============================================================
    function initCardAnimations() {
        var prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (prefersReducedMotion || !('IntersectionObserver' in window)) {
            var allCards = document.querySelectorAll('.gmcq-blog-card');
            allCards.forEach(function (card) {
                card.classList.add('gmcq-visible');
            });
            return;
        }

        var observer = new IntersectionObserver(
            function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        var el = entry.target;
                        var delay = parseInt(el.getAttribute('data-delay')) || 0;
                        setTimeout(function () {
                            el.classList.add('gmcq-visible');
                        }, delay);
                        observer.unobserve(el);
                    }
                });
            },
            { threshold: 0.1, rootMargin: '0px 0px -30px 0px' }
        );

        var cards = document.querySelectorAll('.gmcq-blog-card');
        cards.forEach(function (card) {
            observer.observe(card);
        });
    }

    // ============================================================
    // SMOOTH SCROLL FOR ANCHOR LINKS
    // ============================================================
    function initSmoothScroll() {
        var links = document.querySelectorAll('a[href^="#"]');
        links.forEach(function (link) {
            link.addEventListener('click', function (e) {
                var href = this.getAttribute('href');
                if (href === '#') {
                    return;
                }
                var target = document.querySelector(href);
                if (target) {
                    e.preventDefault();
                    var adminBar = document.getElementById('wpadminbar');
                    var offset = adminBar ? adminBar.offsetHeight + 20 : 20;
                    var top = target.getBoundingClientRect().top + window.pageYOffset - offset;
                    window.scrollTo({ top: top, behavior: 'smooth' });
                }
            });
        });
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
            // Only run on blog page
            if (!document.querySelector('.gmcq-blog-page')) {
                return;
            }

            initCategoryFilters();
            initCardAnimations();
            initSmoothScroll();
        }
    }

    init();
})();