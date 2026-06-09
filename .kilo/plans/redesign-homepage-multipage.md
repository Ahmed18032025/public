# Astra Quiz Child — Complete Redesign & Multi-Page Plan

## 0. Design Foundation

### Color System (Light-First, Dark-Mode Toggle)
| Token | Light Mode | Dark Mode |
|-------|-----------|-----------|
| Page bg | `#f8fafc` | `#0f172a` |
| Card bg | `#ffffff` | `rgba(255,255,255,0.04)` |
| Card border | `rgba(0,0,0,0.06)` | `rgba(255,255,255,0.08)` |
| Primary text | `#1e293b` | `#f1f5f9` |
| Muted text | `#64748b` | `#94a3b8` |
| Primary accent | `#6366f1` (indigo) | `#818cf8` |
| Secondary accent | `#0ea5e9` (sky) | `#38bdf8` |
| Success | `#10b981` (emerald) | `#34d399` |
| CTA / Warning | `#f59e0b` (amber) | `#fbbf24` |

Gradient (CTA, badges): `linear-gradient(135deg, #6366f1, #0ea5e9)`
Maps to Astra Global Colors: `--ast-global-color-0` = indigo, `--ast-global-color-1` = sky.

### Typography
- Body: Inter (existing Astra webfont, `font-display: swap`)
- Headings (optional): Saira or Poppins via Google Fonts
- Scale:
  - H1: `clamp(2.4rem, 6vw, 4rem)`
  - H2: `clamp(1.6rem, 4vw, 2.6rem)`
  - H3: `clamp(1.2rem, 2.5vw, 1.6rem)`
  - Body: `clamp(1rem, 1.5vw, 1.125rem)`, line-height 1.75

### Spacing Scale
```css
--aqc-space-1: 0.25rem; --aqc-space-2: 0.5rem; --aqc-space-3: 1rem;
--aqc-space-4: 1.5rem; --aqc-space-5: 2rem; --aqc-space-6: 3rem;
--aqc-space-7: 4rem; --aqc-space-8: 6rem;
```

---

## 1. Core Infrastructure (Implement First)

These are the shared building blocks every page depends on.

### 1.1 `functions.php` — Theme Setup & Enqueue
- Add `add_theme_support('custom-logo')`.
- Enqueue global JS on **all** pages (not just front page).
- Register Customizer settings (see Section 5).
- Add AJAX handlers: `gmcq_filter_quizzes`, `gmcq_contact_form`.
- Add theme toggle logic + font enqueue (Saira/Poppins if chosen).

### 1.2 `header.php` — Universal Custom Header
- Remove `is_front_page()` conditional.
- Output:
  - Skip-link (`#primary`) for accessibility.
  - Custom logo via `the_custom_logo()` inside `.aqc-brand-mark` (fallback "✓").
  - Brand text from Customizer `aqc_brand_text`.
  - Nav: Home, All Quizzes, Blog, Contact Us, About Us.
  - Theme toggle button (sun/moon icon, `aria-label`).
  - Hamburger trigger at ≤921px.
- Active nav state set server-side via `is_page()` / `is_home()` / `is_singular('gmcq_quiz')`.
- Sticky header with subtle shadow on scroll.

### 1.3 `footer.php` — Universal Custom Footer
- Remove `is_front_page()` conditionals.
- Output:
  - Brand + tagline (editable).
  - Quick links column.
  - Exam categories from `gmcq_get_category_tree()` (graceful hide if plugin inactive).
  - Newsletter signup input.
  - Social placeholder links.
  - Bottom bar: copyright + "Made for aspirants".

### 1.4 `aqc-global.js` (renamed from `homepage.js`)
- Enqueue on all pages.
- Lighter scroll reveal: `translateY(30px)`, `opacity 0→1` only (remove blur/scale for performance).
- Theme toggle: read `localStorage` → set `[data-theme]` on `<html>`.
- Hamburger menu: open/close class toggle + focus trap + Escape key.
- Smooth scroll for anchor links.
- Snowfall: front page only, max 30 particles, gate via `aqc_enable_snowfall` toggle.
- Skeleton loader trigger helper.

---

## 2. Global Styling (`style.css` Extensions)

Add in this order:

1. **CSS Variables (light + dark)** at `:root` and `[data-theme="dark"]`.
2. **Layout utilities**: `.aqc-page-wrapper`, `.aqc-container` (dynamic width via Customizer).
3. **Header / Footer**: sticky nav, mobile drawer, brand mark.
4. **Page header banner**: `.aqc-page-header` (white bg, large heading, reveal animation).
5. **Section wrapper**: `.aqc-page-section` (generous padding, alternating bg).
6. **Cards**: `.aqc-feature-card`, `.aqc-quiz-card`, `.aqc-step`, `.aqc-blog-card` (white bg, subtle border, light hover).
7. **Forms**: `.aqc-contact-form`, `.aqc-filter-bar` (clean inputs, rounded, light borders).
8. **Buttons**: `.aqc-btn`, `.aqc-btn-primary` (gradient), `.aqc-btn-ghost` (outline).
9. **Animations**: `.aqc-reveal` (lighter), `.aqc-toast`, shimmer skeleton.
10. **Responsive breakpoints**:
    - Desktop >1024px: 3-column grids.
    - Tablet 600–1024px: 2-column grids.
    - Mobile <600px: 1 column, full-width cards.

---

## 3. Page Templates (Dependency Order)

### 3.1 `page-all-quizzes.php` (All Quizzes)
- Page header + filter bar (search, category dropdown, sort, clear).
- Quiz grid using `.aqc-quiz-card`.
- AJAX filter endpoint + debounced search.
- Pagination.
- Skeleton loader during load.

### 3.2 `home.php` (Blog — Dynamic WP Posts)
- Page header: "Blog & Updates".
- Native WP loop (`have_posts()` / `the_post()`).
- Blog cards: featured image, category badge, title, excerpt, meta (date, author).
- Pagination.
- Fully dynamic — posts added via WP admin appear automatically.

### 3.3 `page-contact-us.php` (Contact Us)
- Hero banner: "Get in Touch".
- Two-column: contact info cards + contact form.
- Form: Name, Email, Subject, Message.
- AJAX submit → `wp_mail()` to admin.
- Toast notification on success/error.

### 3.4 `page-about-us.php` (About Us)
- Mission section.
- Stats counters from `gmcq_get_dashboard_stats()`.
- Feature cards + step cards.
- CTA banner.

---

## 4. Additional Templates (Quality of Life)

### 4.1 `search.php`
- Custom styled search results.
- Show quiz cards + blog cards in one grid.

### 4.2 `404.php`
- "Page not found" with search input and home link.

### 4.3 `single-gmcq_quiz.php`
- Full theme styling for quiz-taking pages.
- Progress sidebar.
- Question navigator.

---

## 5. Astra Customizer Integration (`functions.php`)

Make everything editable via **Appearance → Customize**.

### 5.1 Logo / Brand
- `add_theme_support('custom-logo')`.
- `aqc_brand_text` (text input).
- `aqc_logo_width` (range 30–80px).

### 5.2 Theme Mode
- `aqc_default_theme_mode` (`light` / `dark` / `system`).
- `aqc_show_theme_toggle` (checkbox).

### 5.3 Header
- `aqc_header_bg_opacity` (0–1).
- `aqc_header_height` (range 10–30px padding).

### 5.4 Homepage Sections
- Toggles: Stats, Quiz Preview, Categories, Popular Quizzes, Recently Added, Features, Steps, CTA.

### 5.5 Footer
- `aqc_footer_tagline`, `aqc_footer_copyright`.
- Toggle: categories column, quick links.

### 5.6 Animations
- `aqc_enable_snowfall` (checkbox).
- `aqc_enable_reveal_animations` (checkbox).
- `aqc_snowfall_density` (range 30–150).
- `aqc_reveal_intensity` (`light` / `full`).

### 5.7 Layout
- `aqc_container_width` (range 900–1400px, default 1180px).
- `aqc_heading_font` (`inter` / `saira` / `poppins`).
- `aqc_body_font_size` (range 14–18px, default 16px).

Use `postMessage` transport for live preview where possible.

---

## 6. Functionality Additions

### 6.1 Quiz Filters (All Quizzes)
- AJAX: `gmcq_filter_quizzes`.
- Params: `search`, `category_id`, `sort_by` (`popular` / `newest` / `name`).
- Return: HTML fragment for `.aqc-quiz-grid`.
- Cache HTML fragment 2–5 min via `wp_cache_set`.

### 6.2 Contact Form
- AJAX: `gmcq_contact_form`.
- Validate: required, valid email.
- Rate limit: max 3 submissions / 10 min per IP via transient.
- Send via `wp_mail()` to `get_option('admin_email')`.
- Toast notification result.

### 6.3 Blog
- No custom logic needed.
- Set static front page + separate "Posts page" in Settings → Reading.
- `home.php` auto-loads for posts page.

---

## 7. Improvements (Organized by Category)

### 7.1 Performance
- Snowfall front-page-only; lightweight canvas (≤30 particles).
- `will-change: transform` only on animated cards.
- `font-display: swap` for web fonts.
- Lazy-load images (`loading="lazy"`).
- Minify/concatenate JS in production.
- `content-visibility: auto` on below-fold sections.

### 7.2 Accessibility
- Skip-link on every page.
- `<label>` + `aria-describedby` on all form inputs.
- Hamburger: `aria-expanded`, `aria-controls`, focus trap, Escape close.
- Muted text contrast bump to `#c8d3f0` on dark bg.
- Landmark roles: `navigation`, `banner`, `contentinfo`.

### 7.3 SEO
- JSON-LD `schema.org/WebPage` / `FAQPage` on quiz pages.
- Open Graph tags on quiz cards + blog posts.
- Breadcrumb schema on inner pages.
- Canonical URL on paginated quiz archive.

### 7.4 Error Handling & Fallbacks
- If GMCQ inactive: placeholder nav + hide footer categories.
- If no categories: "Coming soon" message.
- Offline indicator: `navigator.onLine` banner.
- Loading placeholder for empty quiz data.

### 7.5 Caching
- Quiz filter AJAX cache 2–5 min.
- Reuse `gmcq_get_category_tree()` transient.
- Blog query: default WP object cache.

### 7.6 Security
- Contact form: nonce, sanitize inputs, rate limit by IP.
- Quiz filters: sanitize `$_GET`, verify nonce.
- Escape all output (`esc_html`, `esc_url`, `esc_attr`).

### 7.7 Typography & Design System
- Spacing scale CSS variables (see Foundation).
- Optional Google Fonts: Saira / Poppins.
- Looser heading letter-spacing: `-0.02em`.

### 7.8 Interactive Polish
- Skeleton loaders (shimmer) during AJAX quiz load.
- Toast notifications (auto-dismiss 4s).
- Quiz card left-border accent color by category.
- Button `active:scale(0.97)`.
- Link underline hover animation (gradient underline).
- Stats counter pulse on reveal.

### 7.9 Mobile Responsiveness
- Hamburger: slide-in drawer, 320px, backdrop overlay.
- Quiz grid: 3-col desktop, 2-col tablet, 1-col mobile.
- Quiz meta tags stack vertically on mobile.
- Contact form: stacked on mobile, Name/Email side-by-side on desktop.

### 7.10 Analytics Hooks
- `data-analytics` attributes on quiz cards (`quiz_id`, `quiz_title`, `category`).
- Track `quiz_click`, `cta_click`, `post_view` (hover debounced 1s).

### 7.11 Maintenance / DX
- Document templates, Customizer keys, AJAX endpoints.
- Version comments in CSS + JS.
- `wp_json_encode()` for all localized script data.

---

## 8. Implementation Checklist (Run Order)

1. `functions.php` — supports, enqueues, Customizer, AJAX.
2. `style.css` — variables + global styles.
3. `header.php` — universal header + theme toggle.
4. `footer.php` — universal footer.
5. `aqc-global.js` — animations, toggle, hamburger, snowfall.
6. `page-all-quizzes.php` + AJAX filter handler.
7. `home.php` — dynamic blog.
8. `page-contact-us.php` + contact AJAX.
9. `page-about-us.php` — about content.
10. `search.php`, `404.php`, `single-gmcq_quiz.php`.
11. Accessibility & SEO passes.
12. Final responsive testing (desktop / tablet / mobile).
