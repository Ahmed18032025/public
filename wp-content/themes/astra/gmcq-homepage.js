/**
 * Government MCQ - Homepage JavaScript
 *
 * Premium interactivity with vanilla JS.
 * - Intersection Observer scroll animations
 * - Counter animation for statistics
 * - Testimonial slider
 * - Category search
 * - 60 FPS performance
 * - Respects prefers-reduced-motion
 *
 * @package Astra
 * @subpackage Government_MCQ
 */

(function () {
    'use strict';

    // ============================================================
    // CONFIGURATION
    // ============================================================
    var CONFIG = {
        animationThreshold: 0.15,
        counterDuration: 2000,
        testimonialInterval: 5000,
        scrollDebounce: 10,
    };

    // ============================================================
    // UTILITY FUNCTIONS
    // ============================================================

    /**
     * Check if user prefers reduced motion.
     *
     * @return {boolean}
     */
    function prefersReducedMotion() {
        return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    }

    /**
     * Debounce a function.
     *
     * @param {Function} fn
     * @param {number}   delay
     * @return {Function}
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
     * Ease out quad for counter animation.
     *
     * @param {number} t
     * @return {number}
     */
    function easeOutQuad(t) {
        return t * (2 - t);
    }

    // ============================================================
    // SCROLL ANIMATIONS (Intersection Observer)
    // ============================================================

    /**
     * Initialize Intersection Observer for scroll animations.
     * Supports staggered delays via data-delay attribute.
     */
    function initScrollAnimations() {
        if (prefersReducedMotion()) {
            // Make all animated elements visible immediately
            var allAnimated = document.querySelectorAll(
                '.gmcq-animate-fade-in-up, .gmcq-animate-fade-in-left, .gmcq-animate-fade-in-right'
            );
            for (var i = 0; i < allAnimated.length; i++) {
                allAnimated[i].classList.add('gmcq-visible');
            }
            return;
        }

        if (!('IntersectionObserver' in window)) {
            // Fallback: make all visible immediately
            var fallbackEls = document.querySelectorAll(
                '.gmcq-animate-fade-in-up, .gmcq-animate-fade-in-left, .gmcq-animate-fade-in-right'
            );
            for (var j = 0; j < fallbackEls.length; j++) {
                fallbackEls[j].classList.add('gmcq-visible');
            }
            return;
        }

        var observer = new IntersectionObserver(
            function (entries) {
                for (var k = 0; k < entries.length; k++) {
                    var entry = entries[k];
                    if (entry.isIntersecting) {
                        var el = entry.target;
                        var delay = parseInt(el.getAttribute('data-delay')) || 0;

                        setTimeout(function () {
                            el.classList.add('gmcq-visible');
                        }, delay);

                        // Unobserve after animation to save performance
                        observer.unobserve(el);
                    }
                }
            },
            {
                threshold: CONFIG.animationThreshold,
                rootMargin: '0px 0px -50px 0px',
            }
        );

        // Observe all animated elements
        var animatedElements = document.querySelectorAll(
            '.gmcq-animate-fade-in-up, .gmcq-animate-fade-in-left, .gmcq-animate-fade-in-right'
        );
        for (var l = 0; l < animatedElements.length; l++) {
            observer.observe(animatedElements[l]);
        }
    }

    // ============================================================
    // COUNTER ANIMATION
    // ============================================================

    /**
     * Animate counter numbers with easing.
     */
    function initCounters() {
        if (prefersReducedMotion()) {
            // Show final values immediately
            var immediateCounters = document.querySelectorAll('.gmcq-stat-number[data-target]');
            for (var i = 0; i < immediateCounters.length; i++) {
                var target = parseInt(immediateCounters[i].getAttribute('data-target'));
                immediateCounters[i].textContent = target.toLocaleString();
            }
            return;
        }

        if (!('IntersectionObserver' in window)) {
            return;
        }

        var counterObserver = new IntersectionObserver(
            function (entries) {
                for (var i = 0; i < entries.length; i++) {
                    if (entries[i].isIntersecting) {
                        var el = entries[i].target;
                        animateCounter(el);
                        counterObserver.unobserve(el);
                    }
                }
            },
            { threshold: 0.5 }
        );

        var counters = document.querySelectorAll('.gmcq-stat-number[data-target]');
        for (var j = 0; j < counters.length; j++) {
            counterObserver.observe(counters[j]);
        }
    }

    /**
     * Animate a single counter element.
     *
     * @param {HTMLElement} el
     */
    function animateCounter(el) {
        var target = parseInt(el.getAttribute('data-target'));
        if (target === 0) {
            el.textContent = '0';
            return;
        }

        var startTime = null;
        var duration = CONFIG.counterDuration;

        function step(timestamp) {
            if (!startTime) {
                startTime = timestamp;
            }
            var progress = Math.min((timestamp - startTime) / duration, 1);
            var easedProgress = easeOutQuad(progress);
            var current = Math.round(easedProgress * target);

            el.textContent = current.toLocaleString();

            if (progress < 1) {
                window.requestAnimationFrame(step);
            } else {
                el.textContent = target.toLocaleString();
            }
        }

        window.requestAnimationFrame(step);
    }

    // ============================================================
    // LEADERBOARD PROGRESS BARS
    // ============================================================

    /**
     * Animate leaderboard progress bars when visible.
     */
    function initLeaderboardBars() {
        if (prefersReducedMotion()) {
            // Ensure bars are already at their set width
            return;
        }

        if (!('IntersectionObserver' in window)) {
            return;
        }

        var barObserver = new IntersectionObserver(
            function (entries) {
                for (var i = 0; i < entries.length; i++) {
                    if (entries[i].isIntersecting) {
                        var wrapper = entries[i].target;
                        var bars = wrapper.querySelectorAll('.gmcq-lb-progress-bar');
                        for (var j = 0; j < bars.length; j++) {
                            var width = bars[j].style.width;
                            bars[j].style.width = '0%';
                            setTimeout(function (bar, w) {
                                bar.style.width = w;
                            }, 100 * j, bars[j], width);
                        }
                        barObserver.unobserve(wrapper);
                    }
                }
            },
            { threshold: 0.3 }
        );

        var wrapper = document.querySelector('.gmcq-leaderboard-table-wrapper');
        if (wrapper) {
            barObserver.observe(wrapper);
        }
    }

    // ============================================================
    // TESTIMONIAL SLIDER
    // ============================================================

    /**
     * Initialize the testimonial carousel/slider.
     */
    function initTestimonialSlider() {
        var track = document.getElementById('gmcq-testimonials-track');
        var dotsContainer = document.getElementById('gmcq-testimonial-dots');
        var prevBtn = document.querySelector('.gmcq-testimonial-prev');
        var nextBtn = document.querySelector('.gmcq-testimonial-next');

        if (!track) {
            return;
        }

        var slides = track.children;
        var slideCount = slides.length;
        if (slideCount <= 1) {
            // No need for slider if only 1 slide
            if (dotsContainer) {
                dotsContainer.innerHTML = '';
            }
            return;
        }

        var currentIndex = 0;
        var autoPlayTimer = null;

        // Create dots
        if (dotsContainer) {
            dotsContainer.innerHTML = '';
            for (var i = 0; i < slideCount; i++) {
                var dot = document.createElement('button');
                dot.className = 'gmcq-testimonial-dot' + (i === 0 ? ' active' : '');
                dot.setAttribute('aria-label', 'Go to testimonial ' + (i + 1));
                dot.setAttribute('data-index', i);
                dot.addEventListener('click', function () {
                    var idx = parseInt(this.getAttribute('data-index'));
                    goToSlide(idx);
                    resetAutoPlay();
                });
                dotsContainer.appendChild(dot);
            }
        }

        /**
         * Go to a specific slide index.
         *
         * @param {number} index
         */
        function goToSlide(index) {
            if (index < 0 || index >= slideCount) {
                return;
            }
            currentIndex = index;
            track.style.transform = 'translateX(-' + (index * 100) + '%)';

            // Update dots
            var dots = dotsContainer ? dotsContainer.querySelectorAll('.gmcq-testimonial-dot') : [];
            for (var d = 0; d < dots.length; d++) {
                dots[d].classList.remove('active');
            }
            if (dots[index]) {
                dots[index].classList.add('active');
            }
        }

        /**
         * Go to next slide.
         */
        function nextSlide() {
            var next = (currentIndex + 1) % slideCount;
            goToSlide(next);
        }

        /**
         * Go to previous slide.
         */
        function prevSlide() {
            var prev = (currentIndex - 1 + slideCount) % slideCount;
            goToSlide(prev);
        }

        /**
         * Reset auto-play timer.
         */
        function resetAutoPlay() {
            if (autoPlayTimer) {
                clearInterval(autoPlayTimer);
            }
            if (!prefersReducedMotion()) {
                autoPlayTimer = setInterval(nextSlide, CONFIG.testimonialInterval);
            }
        }

        // Event listeners
        if (nextBtn) {
            nextBtn.addEventListener('click', function () {
                nextSlide();
                resetAutoPlay();
            });
        }
        if (prevBtn) {
            prevBtn.addEventListener('click', function () {
                prevSlide();
                resetAutoPlay();
            });
        }

        // Touch support
        var touchStartX = 0;
        var touchEndX = 0;
        track.addEventListener('touchstart', function (e) {
            touchStartX = e.changedTouches[0].screenX;
        }, { passive: true });
        track.addEventListener('touchend', function (e) {
            touchEndX = e.changedTouches[0].screenX;
            var diff = touchStartX - touchEndX;
            if (Math.abs(diff) > 50) {
                if (diff > 0) {
                    nextSlide();
                } else {
                    prevSlide();
                }
                resetAutoPlay();
            }
        }, { passive: true });

        // Start auto-play
        if (!prefersReducedMotion()) {
            autoPlayTimer = setInterval(nextSlide, CONFIG.testimonialInterval);
        }

        // Expose for debugging
        window.gmcqTestimonial = {
            goToSlide: goToSlide,
            next: nextSlide,
            prev: prevSlide,
        };
    }

    // ============================================================
    // CATEGORY SEARCH
    // ============================================================

    /**
     * Initialize the category search with live filtering.
     */
    function initCategorySearch() {
        var searchInput = document.getElementById('gmcq-category-search');
        var grid = document.getElementById('gmcq-categories-grid');
        var noResults = document.getElementById('gmcq-no-results');

        if (!searchInput || !grid) {
            return;
        }

        var cards = grid.querySelectorAll('.gmcq-category-card');

        /**
         * Filter categories based on search query.
         */
        function filterCategories() {
            var query = searchInput.value.toLowerCase().trim();
            var visibleCount = 0;

            for (var i = 0; i < cards.length; i++) {
                var card = cards[i];
                var categoryName = (card.getAttribute('data-category') || '').toLowerCase();
                var titleElement = card.querySelector('.gmcq-category-name');
                var title = titleElement ? titleElement.textContent.toLowerCase() : '';

                var matches = query === '' ||
                    title.indexOf(query) !== -1 ||
                    categoryName.indexOf(query) !== -1;

                if (matches) {
                    card.style.display = '';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            }

            // Show/hide no results message
            if (noResults) {
                noResults.style.display = visibleCount === 0 ? 'block' : 'none';
            }
        }

        // Debounced search handler
        var debouncedFilter = debounce(filterCategories, 200);
        searchInput.addEventListener('input', debouncedFilter);

        // Also handle search on Enter key
        searchInput.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                searchInput.value = '';
                filterCategories();
                searchInput.blur();
            }
        });
    }

    // ============================================================
    // HERO ANIMATION ON LOAD
    // ============================================================

    /**
     * Animate hero elements on page load.
     */
    function initHeroAnimation() {
        if (prefersReducedMotion()) {
            return;
        }

        var heroContent = document.querySelector('.gmcq-hero-content');
        if (heroContent) {
            heroContent.style.opacity = '0';
            setTimeout(function () {
                heroContent.style.opacity = '1';
                heroContent.style.transition = 'opacity 0.8s ease-out';
            }, 100);
        }
    }

    // ============================================================
    // ADMIN BAR ADJUSTMENT
    // ============================================================

    /**
     * Ensure admin bar doesn't cause overlap issues.
     */
    function initAdminBarFix() {
        var adminBar = document.getElementById('wpadminbar');
        var hero = document.querySelector('.gmcq-hero');
        if (!adminBar || !hero) {
            return;
        }

        function adjustForAdminBar() {
            var adminBarHeight = adminBar.offsetHeight;
            if (adminBarHeight > 0) {
                hero.style.setProperty('--gmcq-adminbar-offset', adminBarHeight + 'px');
                // Apply as margin-top to the hero to ensure content starts below admin bar
                // Note: This is handled via CSS variables now
            }
        }

        adjustForAdminBar();
        window.addEventListener('resize', debounce(adjustForAdminBar, 100));
    }

    // ============================================================
    // SMOOTH SCROLL FOR ANCHOR LINKS
    // ============================================================

    /**
     * Enable smooth scrolling for internal anchor links.
     */
    function initSmoothScroll() {
        var anchorLinks = document.querySelectorAll('a[href^="#"]');
        for (var i = 0; i < anchorLinks.length; i++) {
            anchorLinks[i].addEventListener('click', function (e) {
                var href = this.getAttribute('href');
                if (href === '#') {
                    return;
                }
                var target = document.querySelector(href);
                if (target) {
                    e.preventDefault();
                    var adminBarHeight = 0;
                    var adminBar = document.getElementById('wpadminbar');
                    if (adminBar) {
                        adminBarHeight = adminBar.offsetHeight;
                    }
                    var targetPosition = target.getBoundingClientRect().top + window.pageYOffset - adminBarHeight - 20;
                    window.scrollTo({
                        top: targetPosition,
                        behavior: 'smooth',
                    });
                }
            });
        }
    }

    // ============================================================
    // PERFORMANCE OPTIMIZATIONS
    // ============================================================

    /**
     * Lazy load any images or heavy elements.
     * (Future-proof: currently no images, but ready for when needed)
     */
    function initLazyLoading() {
        if ('loading' in HTMLImageElement.prototype) {
            var images = document.querySelectorAll('img[loading="lazy"]');
            for (var i = 0; i < images.length; i++) {
                images[i].setAttribute('loading', 'lazy');
            }
        }
    }

    // ============================================================
    // INITIALIZATION
    // ============================================================

    /**
     * Initialize all homepage functionality.
     * Runs after DOM is fully loaded.
     */
    function init() {
        // Ensure DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', run);
        } else {
            run();
        }

        function run() {
            // Initialize hero animation
            initHeroAnimation();

            // Initialize scroll animations
            initScrollAnimations();

            // Initialize counters
            initCounters();

            // Initialize leaderboard progress bars
            initLeaderboardBars();

            // Initialize testimonial slider
            initTestimonialSlider();

            // Initialize category search
            initCategorySearch();

            // Initialize admin bar fix
            initAdminBarFix();

            // Initialize smooth scroll
            initSmoothScroll();

            // Initialize lazy loading
            initLazyLoading();
        }
    }

    // Start
    init();
})();