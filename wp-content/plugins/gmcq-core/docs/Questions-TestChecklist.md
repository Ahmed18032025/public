# Questions Class — Test Checklist

Use this checklist to manually verify the `class-gmcq-questions.php` module flow end-to-end.
Each item maps to a specific function in the file, the spec section it implements, and how to verify it.

---

## 1. Loading & Initialization

- [ ] **File is autoloaded** — `gmcq-core.php` line 30 contains `'includes/class-gmcq-questions.php'`
  - *Verify:* Search the file for `class-gmcq-questions.php`
- [ ] **`ABSPATH` guard** — first line after `<?php` is `defined( 'ABSPATH' ) || exit;`
  - *Verify:* `grep "defined.*ABSPATH" includes/class-gmcq-questions.php`
- [ ] **Auto-init runs on load** — last 2 lines call `gmcq_register_question_ajax_handlers()` and `gmcq_register_question_hooks()`
  - *Verify:* `tail -2 includes/class-gmcq-questions.php`
- [ ] **No PHP syntax errors** — `php -l includes/class-gmcq-questions.php` returns "No syntax errors"
- [ ] **All 29 expected functions defined** (see file line ~30 onwards)
  - *Verify in browser:* load any admin page — should NOT produce a fatal error

---

## 2. CRUD Flow

### 2.1 `gmcq_create_question()` — Section 1, lines ~57-180
- [ ] **Validates input first** → `gmcq_validate_question_data()` returns true before insert
- [ ] **Generates hash** via `gmcq_generate_question_hash()` BEFORE insert
- [ ] **Fires `gmcq_before_save_question`** with `$data` array (categories module listens to this)
- [ ] **Opens DB transaction** with `START TRANSACTION`
- [ ] **Inserts question row** with `is_active=1`, `usage_count=0`, `created_by=current user`
- [ ] **Conditionally inserts `import_id`** if provided (CSV import)
- [ ] **Auto-generates True/False** answers when type is `true_false`
- [ ] **Inserts all answers** with sequential `sort_order` (0, 1, 2…)
- [ ] **COMMITs on success**; **ROLLBACKs on any failure**
- [ ] **Detects duplicate hash** in catch block and returns `WP_Error('duplicate_question', …)`
- [ ] **Clears dashboard cache** with `gmcq_clear_dashboard_cache('question')`
- [ ] **Returns question ID** (int) on success, `WP_Error` on failure
- [ ] *Manual test:* WP Admin → Questions → Add New → fill form → Save → row appears in list

### 2.2 `gmcq_update_question()` — Section 1, lines ~185-310
- [ ] **Loads existing question** via `gmcq_get_question()`; returns 404 if not found
- [ ] **Re-validates** with `gmcq_validate_question_data()`
- [ ] **Regenerates hash ONLY if text changed** (preserves hash if same text)
- [ ] **Re-fires `gmcq_before_save_question`** hook
- [ ] **Opens transaction**
- [ ] **Updates question row** with new fields
- [ ] **Deletes all old answers** (`DELETE FROM gmcq_answers WHERE question_id = ?`)
- [ ] **Re-inserts new answers** (preserves answer IDs churn, but new sort_order is reset)
- [ ] **COMMITs / ROLLBACKs** correctly
- [ ] **Detects duplicate hash** on update
- [ ] **Clears cache** on success
- [ ] *Manual test:* Edit an existing question → change text → save → hash should update; same text → no change

### 2.3 `gmcq_delete_question()` (soft) — Section 1, lines ~315-355
- [ ] **Returns `not_found` error** if question doesn't exist
- [ ] **Returns `true` immediately** if already inactive (idempotent)
- [ ] **Sets `is_active=0`**, `deleted_at=NOW()`, `deleted_by=current user`
- [ ] **Fires `gmcq_question_deleted`** action (categories module listens!)
- [ ] **Clears cache**
- [ ] *Manual test:* Click Delete on a question → it moves to "Inactive" filter tab

### 2.4 `gmcq_restore_question()` — Section 1, lines ~360-395
- [ ] **Returns `not_found`** if missing
- [ ] **Returns `true` immediately** if already active (idempotent)
- [ ] **Clears `deleted_at` and `deleted_by`**, sets `is_active=1`
- [ ] **Fires `gmcq_question_restored`** action (categories module listens!)
- [ ] **Clears cache**
- [ ] *Manual test:* From "Inactive" tab → click Restore → question moves to "Active" tab

### 2.5 `gmcq_delete_question_permanently()` (hard) — Section 1, lines ~400-470
- [ ] **Returns `not_found`** if missing
- [ ] **Returns `not_inactive` error** if question is still active (safety guard)
- [ ] **Creates backup FIRST** via `gmcq_create_backup('pre_bulk_question', …)`
- [ ] **Opens transaction**
- [ ] **Deletes from `gmcq_question_map`** (removes from all quizzes)
- [ ] **Deletes from `gmcq_answers`**
- [ ] **Deletes question row**
- [ ] **COMMITs / ROLLBACKs**
- [ ] **Recalculates usage_counts** globally (via `gmcq_recalculate_usage_counts()`)
- [ ] **Fires `gmcq_quiz_questions_changed`** for every active quiz
- [ ] **Clears cache**
- [ ] *Manual test:* Soft-delete a question → click "Delete Permanently" → confirm → row gone, backup file appears in `wp-content/uploads/gmcq-backups/`

### 2.6 `gmcq_get_question()` / `gmcq_get_question_answers()` — Section 1, lines ~475-505
- [ ] **Returns null** if question not found
- [ ] **Attaches `answers` property** to the row object
- [ ] Answers are sorted by `sort_order ASC, id ASC`
- [ ] *Manual test:* Edit form pre-fills with all answers in the correct order

---

## 3. Search & Filter Flow

### 3.1 `gmcq_search_questions()` — Section 2, lines ~545-665
- [ ] **Default `filter='active'`** when no filter passed
- [ ] **`per_page` clamped** to 1-100 range
- [ ] **`page` clamped** to ≥ 1
- [ ] **All 7 filter modes work correctly:**

| Filter | SQL Condition | Test |
|---|---|---|
| `all` | none (shows active+inactive) | ?filter=all shows everything |
| `active` | `q.is_active = 1` | only active rows |
| `no_category` | `q.category_id IS NULL` | only unassigned |
| `unassigned` | `q.usage_count = 0` | only unused |
| `duplicates` | `q.question_hash IN (SELECT … GROUP BY … HAVING COUNT > 1)` | only hash collisions |
| `inactive` | `q.is_active = 0` | only soft-deleted |
| `inactive_category` | JOIN categories, `c.is_active = 0` | category is inactive |
| `archived_quiz` | EXISTS JOIN question_map + quizzes_meta `is_active = 0` | in archived quiz |

- [ ] **Search text uses LIKE** on `question_text OR explanation` (min 3 chars)
- [ ] **Dropdown filters** apply: category, difficulty, question_type
- [ ] **Pagination works** (LIMIT/OFFSET)
- [ ] **Returns proper shape**: `{ results, total, page, per_page }`

### 3.2 `gmcq_get_question_filter_counts()` — Section 2, lines ~670-715
- [ ] **Cached for 5 min** via transient `gmcq_question_filter_counts`
- [ ] **All 7 counts** returned in array
- [ ] *Manual test:* Tab labels show counts that match the actual filter results

---

## 4. Validation Flow

### `gmcq_validate_question_data()` — Section 3, lines ~720-820
- [ ] **Rejects empty question text** → `question_text_empty` error
- [ ] **Rejects invalid question_type** (not in mcq_single/mcq_multiple/true_false) → `invalid_type`
- [ ] **Rejects missing category_id** → `category_required`
- [ ] **Calls `gmcq_validate_question_category()`** → catches `category_not_found`, `category_inactive`, `category_not_leaf`
- [ ] **Sanitizes difficulty** to easy/medium/hard (defaults to medium)
- [ ] **Rejects negative marks** → `marks_negative`
- [ ] **Rejects negative negative_marks** → `negative_marks_negative`
- [ ] **For mcq_single/mcq_multiple:**
  - [ ] Requires at least 2 non-empty answers
  - [ ] Caps at 6 answers
  - [ ] Requires all answer texts filled
  - [ ] Requires at least 1 correct
  - [ ] For mcq_single: requires EXACTLY 1 correct
- [ ] **For true_false:** skips answer validation (auto-generated)
- [ ] **Normalizes data** (writes back to `$data` so caller gets sanitized values)
- [ ] **Returns true** on success, `WP_Error` on any failure
- [ ] *Manual test:* Try saving with empty text → error; with 1 answer → error; with 0 correct → error

---

## 5. Bulk Operations Flow

### `gmcq_bulk_questions()` — Section 1, lines ~535-605
- [ ] **Three actions supported:** `delete`, `restore`, `change_category`
- [ ] **Invalid action** → `WP_Error('invalid_action')`
- [ ] **Skips IDs ≤ 0**
- [ ] **`change_category` requires `extra['category_id']`** or returns `missing_category` error
- [ ] **Per-ID errors collected** in `errors` array (does NOT stop on first failure)
- [ ] **Returns `{ success: int, errors: array }`**
- [ ] *Manual test:* Select 3 questions, choose Bulk → Delete → check that 3 moved to Inactive, 0 errors

---

## 6. Batch Save Flow (CSV Import)

### `gmcq_batch_save_questions()` — Section 1, lines ~610-645
- [ ] **Continues past individual row errors** (does NOT abort on first failure)
- [ ] **Each row goes through `gmcq_create_question()`** (so all validation + hooks fire per row)
- [ ] **Counts `imported`** for successes
- [ ] **Counts `skipped`** for `duplicate_question` errors
- [ ] **Collects `errors`** with `{ row, message }` for failures
- [ ] **Returns `{ imported, skipped, errors }`**
- [ ] *Manual test:* (when CSV Import module is built) — import a CSV with 1 duplicate row → `imported=4, skipped=1, errors=[…]`

---

## 7. Hooks Flow

### `gmcq_register_question_hooks()` — Section 4, lines ~825-840
- [ ] **Listens on `gmcq_question_added_to_quiz`** → calls `gmcq_handle_question_added_to_quiz`
- [ ] **Listens on `gmcq_question_removed_from_quiz`** → calls `gmcq_handle_question_removed_from_quiz`
- [ ] **Listens on `gmcq_daily_cron`** → calls `gmcq_recalculate_usage_counts`

### `gmcq_handle_question_added_to_quiz()` — Section 4, lines ~845-865
- [ ] **Increments `usage_count`** by 1
- [ ] **Clears dashboard cache**
- [ ] *Manual test:* (when Quiz module fires the action) — `usage_count` on the question should go up

### `gmcq_handle_question_removed_from_quiz()` — Section 4, lines ~870-890
- [ ] **Decrements `usage_count`** by 1
- [ ] **FLOOR at 0** via `GREATEST(0, usage_count - 1)` (no negative counts)
- [ ] **Clears dashboard cache**

### `gmcq_recalculate_usage_counts()` — Section 4, lines ~895-915
- [ ] **Single SQL UPDATE** with subquery to count quiz memberships
- [ ] **Only for active questions** (`is_active = 1`)
- [ ] *Manual test:* Manually set a `usage_count` to 999 in DB → wait for cron / call `do_action('gmcq_daily_cron')` → count reverts to actual

### **CRITICAL: Hooks fired BY this module** (consumed by categories)
- [ ] **`gmcq_before_save_question`** fired in `gmcq_create_question()` (BEFORE transaction)
- [ ] **`gmcq_before_save_question`** fired in `gmcq_update_question()` (BEFORE transaction)
- [ ] **`gmcq_question_deleted`** fired in `gmcq_delete_question()` (AFTER soft-delete SQL)
- [ ] **`gmcq_question_restored`** fired in `gmcq_restore_question()` (AFTER restore SQL)
- [ ] *Manual test:* Save a new question → check the category's `question_count` incremented (categories hook handles this)

---

## 8. AJAX Endpoint Flow

### `gmcq_register_question_ajax_handlers()` — Section 5, lines ~920-935
- [ ] **All 8 endpoints registered** with `wp_ajax_gmcq_*`:

| Action | Registered Function |
|---|---|
| `gmcq_save_question` | `gmcq_ajax_save_question` |
| `gmcq_delete_question` | `gmcq_ajax_delete_question` |
| `gmcq_restore_question` | `gmcq_ajax_restore_question` |
| `gmcq_delete_question_permanently` | `gmcq_ajax_delete_question_permanently` |
| `gmcq_bulk_questions` | `gmcq_ajax_bulk_questions` |
| `gmcq_search_questions` | `gmcq_ajax_search_questions` |
| `gmcq_get_question` | `gmcq_ajax_get_question` |
| `gmcq_batch_save_questions` | `gmcq_ajax_batch_save_questions` |

### Common to all 8 handlers:
- [ ] **`check_ajax_referer('gmcq_question_nonce')`** — rejects without valid nonce
- [ ] **`current_user_can('manage_gmcq')`** — rejects without capability
- [ ] **Returns `wp_send_json_error({ message })`** on failure
- [ ] **Returns `wp_send_json_success({ message, ... })`** on success

### `gmcq_ajax_save_question()` — Section 5
- [ ] Reads `id` → routes to update or create
- [ ] Reads answers via `gmcq_read_post_answers()` helper
- [ ] Returns `{ message, question_id }` on success

### `gmcq_ajax_bulk_questions()` — Section 5
- [ ] **Creates backup BEFORE bulk delete** (if action='delete')
- [ ] Routes `change_category` with `category_id` extra
- [ ] Returns `{ success, errors }`

---

## 9. Admin Page Rendering Flow

### `gmcq_render_questions_page()` — Section 6
- [ ] **Capability check** (`manage_gmcq`) — `wp_die` if not allowed
- [ ] **Routes `?action=add`** → `gmcq_render_question_add_form()`
- [ ] **Routes `?action=edit&id=N`** → `gmcq_render_question_edit_form(N)`
- [ ] **Defaults to list view** otherwise
- [ ] **Reads filter params**: `filter`, `s`, `category_id`, `difficulty`, `question_type`, `paged`
- [ ] **Calls `gmcq_search_questions()`** with those args
- [ ] **Calls `gmcq_get_question_filter_counts()`** for tab counts
- [ ] **Calls `gmcq_get_categories()`** for the dropdown
- [ ] **Renders 8 filter tabs** with current-tab highlight
- [ ] **Renders search/dropdown form**
- [ ] **Renders question table** with checkbox column, question, category, type, difficulty, usage_count
- [ ] **Row actions** per status: Edit always; Delete (active) OR Restore + Delete Permanently (inactive)
- [ ] **Bulk action dropdown + Apply button**
- [ ] **JS at bottom** handles AJAX for delete, restore, delete-perm, bulk
- [ ] **Notice area** for success/error toasts

### `gmcq_render_question_add_form()` — Section 6
- [ ] Capability check
- [ ] Delegates to `gmcq_render_question_form(0)` (no `$q` object)

### `gmcq_render_question_edit_form($id)` — Section 6
- [ ] Capability check
- [ ] Loads question via `gmcq_get_question($id)`
- [ ] Shows error notice if not found
- [ ] Delegates to `gmcq_render_question_form($id, $q)`

### `gmcq_render_question_form($id, $q=null)` — Section 6
- [ ] **Enqueues rich editor** via `wp_enqueue_editor()` + `wp_enqueue_script('wp-tinymce')`
- [ ] **Renders all form fields** in `form-table` layout:
  - [ ] Category dropdown (required, shows parent→child prefix)
  - [ ] Type dropdown (mcq_single, mcq_multiple, true_false)
  - [ ] Difficulty dropdown
  - [ ] Question Text `wp_editor` (full, with media buttons)
  - [ ] Answers table (4 default rows) with correct-column (radio/checkbox)
  - [ ] Add Option / Delete buttons (min 2, max 6 enforced via JS)
  - [ ] True/False radio (shown only when type=true_false, JS toggle)
  - [ ] Explanation `wp_editor` (teeny, no media buttons)
  - [ ] Marks / Negative Marks number inputs (min=0)
- [ ] **Submit handler (inline JS):**
  - [ ] `e.preventDefault()` to stop native form submit
  - [ ] Pulls tinyMCE content for question_text and explanation
  - [ ] `$.post` to `gmcqAdmin.ajaxUrl` with action=`gmcq_save_question`
  - [ ] Sends `_ajax_nonce` for verification
  - [ ] On success: green notice + redirect to list
  - [ ] On error: red notice + re-enable submit button
- [ ] **JS for dynamic rows:**
  - [ ] Add answer row: validates count ≤ 6
  - [ ] Remove answer row: validates count ≥ 2
  - [ ] Toggle correct-column input type (radio/checkbox) when type changes
  - [ ] Show/hide True-False row when type=mcq_single/mcq_multiple/true_false

---

## 10. Security & Cache Flow

### Security (every AJAX + page)
- [ ] **`gmcq_question_nonce` is used** for ALL 8 AJAX endpoints (not the categories nonce)
- [ ] **`manage_gmcq` capability** checked on every AJAX handler and every page renderer
- [ ] **All SQL uses `$wpdb->prepare()`** or `$wpdb->insert/update/delete` (no string concatenation)
- [ ] **`wp_kses_post()`** on question_text and explanation (strips scripts/iframes)
- [ ] **`sanitize_text_field()`** on answer_text
- [ ] **`(int)` casts** on all IDs before SQL
- [ ] **`ob_get_length() / ob_clean()`** in AJAX handlers before `wp_send_json_*` (prevents PHP notice pollution of JSON response)

### Cache management
- [ ] **`gmcq_clear_dashboard_cache('question')`** is called on:
  - [ ] Successful create
  - [ ] Successful update
  - [ ] Successful delete (soft)
  - [ ] Successful restore
  - [ ] Successful delete_permanently
  - [ ] Successful bulk_questions (per ID)
  - [ ] Successful change_category (per ID)
  - [ ] Usage count change (added/removed)
- [ ] **NOT called on validation failure** (no point, no change)

---

## 11. Auto-Init Flow

- [ ] **Last 2 lines of file** call `gmcq_register_question_ajax_handlers()` then `gmcq_register_question_hooks()`
- [ ] These run automatically when the file is `require_once`'d by `gmcq-core.php`
- [ ] No `init` hook needed — registration happens at file-load time, which is correct
- [ ] *Verify:* `tail -2 includes/class-gmcq-questions.php` should show the two calls

---

## 12. Cross-Module Integration Flow

### Hooks fired BY this module → consumed by `class-gmcq-categories.php`
- [ ] **Save a question in a category** → `gmcq_before_save_question` fires → `gmcq_handle_category_question_change()` runs → category's `question_count` increments
- [ ] **Soft-delete a question** → `gmcq_question_deleted` fires → category's `question_count` decrements
- [ ] **Restore a question** → `gmcq_question_restored` fires → category's `question_count` re-increments
- [ ] *Verify:* Open Categories page → check `Questions` column on a category → after saving/deleting questions in that category, count updates

### Hooks this module listens to (will be fired by future Quiz module)
- [ ] **`gmcq_question_added_to_quiz`** → `usage_count += 1`
- [ ] **`gmcq_question_removed_from_quiz`** → `usage_count -= 1` (floored at 0)
- [ ] **`gmcq_daily_cron`** → `gmcq_recalculate_usage_counts()` runs

---

## 13. End-to-End Smoke Test (Browser)

Run through this exact sequence:

1. [✅] WP Admin → GMCQ → Questions
2. [✅] Page loads with Active tab selected, count = 0
3. [✅] Click **+ Add New Question**
4. [X] Try to save with empty text → red error "Question text cannot be empty"
5. [✅] Fill in all required fields with 4 answers, 1 marked correct
6. [X] Click **Save Question** → green notice → redirected to list → new row appears
7. [ ] Click on the question text → edit page loads with all fields pre-filled
8. [ ] Change the question text → Save → row updates in list
9. [ ] Click **Delete** on a row → confirm → row moves to "Inactive" tab
10. [ ] Switch to Inactive tab → row is there with "Restore" + "Delete Permanently" actions
11. [ ] Click **Restore** → row moves back to "Active" tab
12. [ ] Create 3 more questions
13. [ ] Check a few + Bulk Action = Delete → Apply → 3 rows move to Inactive
14. [ ] Switch to **Duplicates** tab (should be 0)
15. [ ] Try to create a question with the SAME text as an existing one → "This question already exists (duplicate detected)"
16. [ ] Switch to **No Category** tab → should be empty (all your test questions had categories)
17. [ ] Switch to **Unassigned** tab → should be empty (none of your questions are in quizzes yet)
18. [ ] Edit a question → change Category to a new one → Save → check the old category's count decreased and the new one's increased
19. [ ] Soft-delete a question, then click **Delete Permanently** → confirm → row gone forever
20. [ ] Check `wp-content/uploads/gmcq-backups/` for a JSON file from step 19
21. [ ] WP Admin → Categories → verify `question_count` matches reality
22. [ ] WP Admin → Dashboard → verify the "Active Questions" stat card reflects current count

If all 22 steps pass, the Questions module is fully functional.

---

## 14. Function Reference (Quick Lookup)

| Function | Section | Line ~ | Purpose |
|---|---|---|---|
| `gmcq_create_question()` | 1 | 57 | Insert with answers, transaction, hash |
| `gmcq_update_question()` | 1 | 185 | Update + replace answers, transaction |
| `gmcq_delete_question()` | 1 | 315 | Soft delete (is_active=0) |
| `gmcq_restore_question()` | 1 | 360 | Restore soft-deleted |
| `gmcq_delete_question_permanently()` | 1 | 400 | Hard delete with backup |
| `gmcq_get_question()` | 1 | 475 | Load one question + answers |
| `gmcq_get_question_answers()` | 1 | 495 | Load just the answers |
| `gmcq_bulk_questions()` | 1 | 535 | Bulk operations |
| `gmcq_batch_save_questions()` | 1 | 610 | CSV import batch |
| `gmcq_search_questions()` | 2 | 545 | List with filters |
| `gmcq_get_question_filter_counts()` | 2 | 670 | Tab counts (cached) |
| `gmcq_validate_question_data()` | 3 | 720 | 12 validation rules |
| `gmcq_register_question_hooks()` | 4 | 825 | Register action listeners |
| `gmcq_handle_question_added_to_quiz()` | 4 | 845 | usage_count += 1 |
| `gmcq_handle_question_removed_from_quiz()` | 4 | 870 | usage_count -= 1 (floor 0) |
| `gmcq_recalculate_usage_counts()` | 4 | 895 | Cron recalculation |
| `gmcq_register_question_ajax_handlers()` | 5 | 920 | Register 8 AJAX endpoints |
| `gmcq_read_post_answers()` | 5 | helper | Normalize $_POST answers |
| `gmcq_ajax_save_question()` | 5 | — | Create/Update via AJAX |
| `gmcq_ajax_delete_question()` | 5 | — | Soft delete via AJAX |
| `gmcq_ajax_restore_question()` | 5 | — | Restore via AJAX |
| `gmcq_ajax_delete_question_permanently()` | 5 | — | Hard delete via AJAX |
| `gmcq_ajax_bulk_questions()` | 5 | — | Bulk via AJAX |
| `gmcq_ajax_search_questions()` | 5 | — | Search via AJAX |
| `gmcq_ajax_get_question()` | 5 | — | Get one via AJAX |
| `gmcq_ajax_batch_save_questions()` | 5 | — | Batch via AJAX (CSV) |
| `gmcq_render_questions_page()` | 6 | — | List page renderer |
| `gmcq_render_question_add_form()` | 6 | — | Add form entry point |
| `gmcq_render_question_edit_form()` | 6 | — | Edit form entry point |
| `gmcq_render_question_form()` | 6 | — | Shared form HTML+JS |

---

*Generated for `gmcq-core` v1.0.0 — Questions Management v2.0*


