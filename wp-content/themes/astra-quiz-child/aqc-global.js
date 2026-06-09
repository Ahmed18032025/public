(function () {
	'use strict';

	// ── Helpers ───────────────────────────────────────────────────────
	function qs(selector, root) {
		root = root || document;
		return root.querySelector(selector);
	}

	function qsa(selector, root) {
		root = root || document;
		return Array.prototype.slice.call(root.querySelectorAll(selector));
	}

	function ready(fn) {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', fn);
		} else {
			fn();
		}
	}

	// ── Theme Toggle ──────────────────────────────────────────────────
	function initThemeToggle() {
		var toggle = qs('.aqc-theme-toggle');
		if (!toggle) return;

		var stored = localStorage.getItem('aqc-theme');
		var defaultMode = (window.aqcData && window.aqcData.defaultTheme) || 'light';

		if (!stored && defaultMode) {
			stored = defaultMode;
		}

		if (stored) {
			document.documentElement.setAttribute('data-theme', stored);
		}

		toggle.addEventListener('click', function () {
			var current = document.documentElement.getAttribute('data-theme');
			var next = current === 'dark' ? 'light' : 'dark';
			document.documentElement.setAttribute('data-theme', next);
			localStorage.setItem('aqc-theme', next);
		});
	}

	// ── Hamburger Menu ────────────────────────────────────────────────
	function initHamburger() {
		var hamburger = qs('.aqc-hamburger');
		var menu = qs('#aqc-primary-menu');
		var drawer = qs('.aqc-mobile-drawer');
		var overlay = qs('.aqc-mobile-overlay');
		var closeBtn = qs('.aqc-mobile-close');

		if (!hamburger || !menu || !drawer) return;

		var isOpen = false;

		function openMenu() {
			isOpen = true;
			drawer.classList.add('is-open');
			if (overlay) overlay.classList.add('is-open');
			hamburger.classList.add('is-open');
			hamburger.setAttribute('aria-expanded', 'true');
			document.body.style.overflow = 'hidden';

			var firstLink = qs('.aqc-mobile-drawer .aqc-menu a');
			if (firstLink) firstLink.focus();
		}

		function closeMenu() {
			isOpen = false;
			drawer.classList.remove('is-open');
			if (overlay) overlay.classList.remove('is-open');
			hamburger.classList.remove('is-open');
			hamburger.setAttribute('aria-expanded', 'false');
			document.body.style.overflow = '';
			hamburger.focus();
		}

		hamburger.addEventListener('click', function () {
			if (isOpen) {
				closeMenu();
			} else {
				openMenu();
			}
		});

		if (closeBtn) {
			closeBtn.addEventListener('click', closeMenu);
		}

		if (overlay) {
			overlay.addEventListener('click', closeMenu);
		}

		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && isOpen) {
				closeMenu();
			}
		});

		// Close on window resize above mobile breakpoint
		var mql = window.matchMedia('(min-width: 922px)');
		mql.addEventListener('change', function (e) {
			if (e.matches && isOpen) {
				closeMenu();
			}
		});
	}

	// ── Snowfall (front page only, gated) ─────────────────────────────
	function initSnowfall() {
		var container = qs('.aqc-snowfall');
		if (!container) return;

		if (window.aqcData && !window.aqcData.enableSnow) {
			container.style.display = 'none';
			return;
		}

		var maxFlakes = (window.aqcData && window.aqcData.snowDensity) ? window.aqcData.snowDensity : 30;
		if (maxFlakes > 150) maxFlakes = 150;

		var canvas = document.createElement('canvas');
		canvas.setAttribute('aria-hidden', 'true');
		container.appendChild(canvas);

		var ctx = canvas.getContext('2d');
		var width = 0;
		var height = 0;
		var flakes = [];

		function resize() {
			width = window.innerWidth;
			height = window.innerHeight;
			canvas.width = width;
			canvas.height = height;
		}

		function randomBetween(min, max) {
			return Math.random() * (max - min) + min;
		}

		function createFlake() {
			return {
				x: randomBetween(0, width),
				y: randomBetween(-100, -10),
				r: randomBetween(1, 3),
				speed: randomBetween(0.4, 1.4),
				wind: randomBetween(-0.2, 0.2),
				opacity: randomBetween(0.15, 0.55)
			};
		}

		function initFlakes() {
			flakes = [];
			for (var i = 0; i < maxFlakes; i++) {
				flakes.push(createFlake());
			}
		}

		function draw() {
			ctx.clearRect(0, 0, width, height);
			for (var i = 0; i < flakes.length; i++) {
				var f = flakes[i];
				ctx.beginPath();
				ctx.arc(f.x, f.y, f.r, 0, Math.PI * 2);
				ctx.fillStyle = 'rgba(255,255,255,' + f.opacity + ')';
				ctx.fill();

				f.y += f.speed;
				f.x += f.wind;

				if (f.y > height) {
					f.y = -10;
					f.x = randomBetween(0, width);
				}
				if (f.x > width) f.x = 0;
				if (f.x < 0) f.x = width;
			}
			requestAnimationFrame(draw);
		}

		resize();
		initFlakes();
		draw();

		var resizeTimer;
		window.addEventListener('resize', function () {
			clearTimeout(resizeTimer);
			resizeTimer = setTimeout(function () {
				resize();
				initFlakes();
			}, 200);
		});
	}

	// ── Reveal Animations (lighter) ───────────────────────────────────
	function initRevealAnimations() {
		var root = qs('.aqc-page-wrapper') || qs('.aqc-homepage');
		if (!root) return;

		if (window.aqcData && !window.aqcData.enableReveal) {
			return;
		}

		var selectors = [
			'.aqc-section-heading',
			'.aqc-feature-card',
			'.aqc-quiz-card',
			'.aqc-step',
			'.aqc-cta-box',
			'.aqc-stat-card',
			'.aqc-blog-card'
		];

		var items = qsa(selectors.join(','), root);
		if (!items.length) return;

		items.forEach(function (item, index) {
			item.classList.add('aqc-reveal');
			item.style.setProperty('--aqc-reveal-delay', (index % 6) * 80 + 'ms');
		});

		if (!('IntersectionObserver' in window)) {
			items.forEach(function (item) {
				item.classList.add('is-visible');
			});
			return;
		}

		var observer = new IntersectionObserver(function (entries) {
			entries.forEach(function (entry) {
				if (entry.isIntersecting) {
					entry.target.classList.add('is-visible');
				}
			});
		}, {
			threshold: 0.12,
			rootMargin: '0px 0px -6% 0px'
		});

		items.forEach(function (item) {
			observer.observe(item);
		});
	}

	// ── Smooth Scroll ─────────────────────────────────────────────────
	function initSmoothScroll() {
		qsa('a[href^="#"]').forEach(function (link) {
			link.addEventListener('click', function (e) {
				var targetId = this.getAttribute('href');
				if (!targetId || targetId === '#') return;
				var target = qs(targetId);
				if (!target) return;
				e.preventDefault();
				target.scrollIntoView({ behavior: 'smooth', block: 'start' });
			});
		});
	}

	// ── Toast Helper ───────────────────────────────────────────────────
	function showToast(message, type) {
		var existing = qs('.aqc-toast');
		if (existing) existing.remove();

		var toast = document.createElement('div');
		toast.className = 'aqc-toast is-' + (type || 'success');
		toast.textContent = message;
		document.body.appendChild(toast);

		requestAnimationFrame(function () {
			toast.classList.add('is-visible');
		});

		setTimeout(function () {
			toast.classList.remove('is-visible');
			setTimeout(function () {
				toast.remove();
			}, 300);
		}, 4000);
	}

	// ── Skeleton Trigger Helper ────────────────────────────────────────
	function showSkeleton(container, count) {
		var html = '';
		for (var i = 0; i < count; i++) {
			html += '<div class="aqc-quiz-card aqc-skeleton aqc-skeleton-card"></div>';
		}
		container.innerHTML = html;
	}

	// ── Offline Indicator ──────────────────────────────────────────────
	function initOfflineIndicator() {
		function update() {
			var banner = qs('.aqc-offline-banner');
			if (!navigator.onLine) {
				if (!banner) {
					banner = document.createElement('div');
					banner.className = 'aqc-offline-banner';
					banner.textContent = 'You are currently offline.';
					banner.setAttribute('role', 'status');
					document.body.prepend(banner);
				}
				banner.style.display = 'block';
			} else if (banner) {
				banner.style.display = 'none';
			}
		}

		window.addEventListener('online', update);
		window.addEventListener('offline', update);
		update();
	}

	// ── Quiz Filter AJAX ────────────────────────────────────────────────
	function initQuizFilters() {
		var grid = qs('#aqc-quiz-grid');
		var form = qs('#aqc-quiz-filters');
		var searchInput = qs('#aqc-search');
		var categorySelect = qs('#aqc-category');
		var sortSelect = qs('#aqc-sort');
		var clearBtn = qs('#aqc-clear-filters');

		if (!grid || !form || !searchInput || !categorySelect || !sortSelect) return;

		var debounceTimer;

		function buildParams() {
			return {
				action: 'gmcq_filter_quizzes',
				nonce: (window.aqcData && window.aqcData.nonce) || '',
				search: searchInput.value,
				category_id: categorySelect.value,
				sort_by: sortSelect.value
			};
		}

		function doFetch(params) {
			if (window.aqcShowSkeleton) {
				window.aqcShowSkeleton(grid, 6);
			}

			var url = (window.aqcData && window.aqcData.ajaxUrl) || '/wp-admin/admin-ajax.php';
			var qs = Object.keys(params).map(function (k) {
				return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]);
			}).join('&');

			fetch(url + '?' + qs, { credentials: 'same-origin' })
				.then(function (res) { return res.json(); })
				.then(function (data) {
					if (data.success && data.data && data.data.html) {
						grid.innerHTML = data.data.html;
					} else if (data.data && data.data.message) {
						grid.innerHTML = '<p class="aqc-no-results">' + data.data.message + '</p>';
					} else {
						grid.innerHTML = '<p class="aqc-no-results">No quizzes found.</p>';
					}
				})
				.catch(function () {
					grid.innerHTML = '<p class="aqc-no-results">Something went wrong. Please try again.</p>';
				});
		}

		function triggerFetch() {
			doFetch(buildParams());
		}

		searchInput.addEventListener('input', function () {
			clearTimeout(debounceTimer);
			debounceTimer = setTimeout(triggerFetch, 350);
		});

		categorySelect.addEventListener('change', triggerFetch);
		sortSelect.addEventListener('change', triggerFetch);

		if (clearBtn) {
			clearBtn.addEventListener('click', function () {
				searchInput.value = '';
				categorySelect.value = '';
				sortSelect.value = 'popular';
				triggerFetch();
			});
		}
	}

	// ── Contact Form AJAX ───────────────────────────────────────────────
	function initContactForm() {
		var form = qs('#aqc-contact-form');
		if (!form) return;

		form.addEventListener('submit', function (e) {
			e.preventDefault();

			var data = {
				action: 'gmcq_contact_form',
				nonce: (window.aqcData && window.aqcData.nonce) || '',
				name: qs('#aqc-name', form).value,
				email: qs('#aqc-email', form).value,
				subject: qs('#aqc-subject', form).value,
				message: qs('#aqc-message', form).value
			};

			var btn = qs('button[type="submit"]', form);
			var originalText = btn.textContent;
			btn.disabled = true;
			btn.textContent = 'Sending...';

			var url = (window.aqcData && window.aqcData.ajaxUrl) || '/wp-admin/admin-ajax.php';
			var qs = Object.keys(data).map(function (k) {
				return encodeURIComponent(k) + '=' + encodeURIComponent(data[k]);
			}).join('&');

			fetch(url + '?' + qs, { method: 'POST', credentials: 'same-origin' })
				.then(function (res) { return res.json(); })
				.then(function (res) {
					if (res.success) {
						window.aqcToast && window.aqcToast(res.data.message || 'Message sent!', 'success');
						form.reset();
					} else {
						window.aqcToast && window.aqcToast(res.data.message || 'Something went wrong.', 'error');
					}
				})
				.catch(function () {
					window.aqcToast && window.aqcToast('Network error. Please try again.', 'error');
				})
				.finally(function () {
					btn.disabled = false;
					btn.textContent = originalText;
				});
		});
	}

	// ── Stats Counter Animation ─────────────────────────────────────────
	function initStatsCounter() {
		var root = qs('.aqc-page-wrapper') || qs('.aqc-homepage');
		if (!root) return;

		var counters = qsa('[data-count]', root);
		if (!counters.length) return;

		if (!('IntersectionObserver' in window)) {
			counters.forEach(function (el) { el.textContent = el.getAttribute('data-count'); });
			return;
		}

		var observer = new IntersectionObserver(function (entries) {
			entries.forEach(function (entry) {
				if (!entry.isIntersecting) return;
				var el = entry.target;
				var card = el.closest('.aqc-stat-card');
				if (card) card.classList.add('is-revealed');
			var target = parseInt(el.getAttribute('data-count'), 10);
			if (isNaN(target)) return;
			var start = 0;
			var duration = 1600;
			var startTime = null;

			function step(timestamp) {
				if (!startTime) startTime = timestamp;
				var progress = Math.min((timestamp - startTime) / duration, 1);
				var eased = 1 - Math.pow(1 - progress, 3);
				el.textContent = Math.floor(eased * target);
				if (progress < 1) {
					requestAnimationFrame(step);
				} else {
					el.textContent = target;
				}
			}

			requestAnimationFrame(step);
				observer.unobserve(el);
			});
		}, { threshold: 0.5 });

		counters.forEach(function (el) { observer.observe(el); });
	}

	function initAnalytics() {
		document.addEventListener('click', function (e) {
			var card = e.target.closest('.aqc-quiz-card a, .aqc-quiz-card');
			if (card && card.matches('.aqc-quiz-card')) {
				var dataAttr = card.getAttribute('data-analytics');
				if (dataAttr) {
					try {
						var data = JSON.parse(dataAttr);
						if (window.console && console.log) {
							console.log('analytics:quiz_click', data);
						}
					} catch (err) {}
				}
			}
		});

		var hoverTimers = new Map();
		document.addEventListener('mouseover', function (e) {
			var card = e.target.closest('.aqc-blog-card');
			if (!card) return;
			var id = card.getAttribute('id') || card.innerText.slice(0, 20);
			if (!hoverTimers.has(id)) {
				var timer = setTimeout(function () {
					if (window.console && console.log) {
						console.log('analytics:post_view', { id: id });
					}
				}, 1000);
				hoverTimers.set(id, timer);
			}
		});
		document.addEventListener('mouseout', function (e) {
			var card = e.target.closest('.aqc-blog-card');
			if (!card) return;
			var id = card.getAttribute('id') || card.innerText.slice(0, 20);
			var timer = hoverTimers.get(id);
			if (timer) {
				clearTimeout(timer);
				hoverTimers.delete(id);
			}
		});

		document.addEventListener('click', function (e) {
			var btn = e.target.closest('.aqc-cta-banner .aqc-btn, .aqc-cta-box .aqc-btn');
			if (btn && window.console && console.log) {
				console.log('analytics:cta_click', { href: btn.getAttribute('href') });
			}
		});
	}

	// ── Init ───────────────────────────────────────────────────────────
	// ── Quiz Progress (single quiz page) ──────────────────────────────
	function initQuizProgress() {
		var items = qsa('#aqc-quiz-questions [data-question-index]');
		if (!items.length) return;

		var fill = qs('#aqc-progress-fill');
		var label = qs('#aqc-progress-current');
		var total = items.length;

		function setActive(index) {
			var pct = total > 0 ? Math.round((index / (total - 1)) * 100) : 0;
			if (fill) fill.style.width = pct + '%';
			if (label) label.textContent = 'Question ' + (index + 1);

			items.forEach(function (el, i) {
				if (i <= index) {
					el.style.borderLeft = '4px solid var(--aqc-accent)';
				} else {
					el.style.borderLeft = '';
				}
			});
		}

		if (!('IntersectionObserver' in window)) {
			setActive(0);
			return;
		}

		var observer = new IntersectionObserver(function (entries) {
			var visible = 0;
			entries.forEach(function (entry) {
				if (entry.isIntersecting) {
					var idx = parseInt(entry.target.getAttribute('data-question-index'), 10);
					if (!isNaN(idx) && idx > visible) {
						visible = idx;
					}
				}
			});
			if (visible > 0 || entries.length === 0) {
				setActive(visible);
			}
		}, {
			threshold: 0.5,
			rootMargin: '-20% 0px -40% 0px'
		});

		items.forEach(function (el) { observer.observe(el); });
		setActive(0);
	}

	ready(function () {
		initThemeToggle();
		initHamburger();
		initSnowfall();
		initRevealAnimations();
		initSmoothScroll();
		initOfflineIndicator();
		initQuizFilters();
		initContactForm();
		initStatsCounter();
		initAnalytics();
		initQuizProgress();

		window.aqcToast = showToast;
		window.aqcShowSkeleton = showSkeleton;
	});
}());
