(function () {
	'use strict';

	function initSnowfall() {
		var container = document.querySelector('.aqc-snowfall');
		if (!container) {
			return;
		}

		var canvas = document.createElement('canvas');
		canvas.setAttribute('aria-hidden', 'true');
		container.appendChild(canvas);

		var ctx = canvas.getContext('2d');
		var width = 0;
		var height = 0;
		var flakes = [];
		var maxFlakes = 70;

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
				r: randomBetween(1, 4),
				speed: randomBetween(0.6, 2),
				wind: randomBetween(-0.25, 0.25),
				opacity: randomBetween(0.2, 0.7)
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
					f.x = createFlake().x;
				}
				if (f.x > width) {
					f.x = createFlake().x;
				}
				if (f.x < 0) {
					f.x = createFlake().x;
				}
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

	function initRevealAnimations() {
		var root = document.querySelector('.aqc-homepage');
		if (!root) {
			return;
		}

		var selectors = [
			'.aqc-section-heading',
			'.aqc-feature-card',
			'.aqc-quiz-card',
			'.aqc-step',
			'.aqc-cta-box',
			'.aqc-hero-card',
			'.aqc-stat'
		];

		var items = Array.prototype.slice.call(root.querySelectorAll(selectors.join(',')));
		if (!items.length) {
			return;
		}

		items.forEach(function (item, index) {
			item.classList.add('aqc-reveal');

			if (
				item.classList.contains('aqc-feature-card') ||
				item.classList.contains('aqc-quiz-card') ||
				item.classList.contains('aqc-step') ||
				item.classList.contains('aqc-hero-card')
			) {
				item.classList.add('aqc-float-loop');
			}

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
				} else {
					entry.target.classList.remove('is-visible');
				}
			});
		}, {
			threshold: 0.16,
			rootMargin: '0px 0px -8% 0px'
		});

		items.forEach(function (item) {
			observer.observe(item);
		});
	}

	initSnowfall();

	var rootNode = document.querySelector('.aqc-homepage');
	if (rootNode) {
		var scrolledTicks = 0;
		window.addEventListener('scroll', function () {
			scrolledTicks += 1;
			if (!window.requestAnimationFrame) {
				rootNode.classList.toggle('scrolled', window.scrollY > 24);
				return;
			}
			window.requestAnimationFrame(function () {
				rootNode.classList.toggle('scrolled', window.scrollY > 24);
				scrolledTicks -= 1;
			});
		}, { passive: true });
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initRevealAnimations);
	} else {
		initRevealAnimations();
	}
}());