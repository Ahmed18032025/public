## CSV Import Testing Checklist

### 1. Admin page access
- [✅] Go to **WP Admin → GMCQ → CSV Import**.
- [✅] Confirm the page loads without fatal errors.
- [✅] Confirm you see:
  - Upload CSV form
  - Target Category dropdown
  - Assign to Quiz dropdown
  - CSV format help
  - Import History table

### 2. Prepare test data
Create a small CSV file like this:

```csv
question_text,option_a,option_b,option_c,option_d,correct_answer,explanation,difficulty,marks,negative_marks,question_type,category_slug
What is 2+2?,3,4,5,6,B,Basic arithmetic,easy,1,0.25,mcq_single,
Which are prime numbers?,2,3,4,6,A,B,"2 and 3 are prime",medium,2,0.5,mcq_multiple,
```

> If you leave `category_slug` empty, choose a valid **leaf category** in the Target Category dropdown.

### 3. Upload + preview validation
- [✅] Upload the CSV.
- [ ] Select a leaf Target Category.
- [ ] Optionally select a quiz.
- [ ] Click **Validate & Preview**.
- [ ] Confirm preview summary shows:
  - Total rows
  - Valid rows
  - Duplicate rows
  - Error rows
- [ ] Confirm first rows appear in preview table.

### 4. Confirm import
- [ ] Click **Import Valid Rows**.
- [ ] Confirm success notice appears with imported/skipped counts.
- [ ] Confirm a new row appears in **Import History**.
- [ ] Go to **GMCQ → Questions** and confirm imported questions exist.

### 5. Duplicate detection
- [ ] Upload the same CSV again.
- [ ] Confirm preview marks those questions as duplicates.
- [ ] Confirm duplicate rows are skipped and not inserted again.

### 6. Category override test
- [ ] Add a row with `category_slug` set to an existing category slug.
- [ ] Add another row with `category_slug` as `parent/child`.
- [ ] Confirm those rows import into the resolved category, overriding the selected Target Category.

### 7. Validation error tests
Try CSVs with:
- [ ] Missing `question_text`.
- [ ] Missing `option_b`.
- [ ] Invalid `correct_answer`, e.g. `Z`.
- [ ] No selected Target Category and no `category_slug`.
- [ ] Parent category selected instead of leaf category.

Expected result: rows should show errors in preview and be skipped.

### 8. Quiz assignment test
- [ ] Select a quiz during import.
- [ ] After import, open **GMCQ → Quizzes → Manage Questions** for that quiz.
- [ ] Confirm imported questions are assigned.
- [ ] Confirm quiz question count updates.

### 9. Backup verification
- [ ] Check `wp-content/uploads/gmcq-backups/`.
- [ ] Confirm a pre-import JSON backup was created.
- [ ] Confirm backup is indexed in plugin settings/backup history if the settings UI exposes it.

### 10. Regression checks
- [ ] Confirm **GMCQ → Questions** still loads.
- [ ] Confirm **GMCQ → Categories** still loads.
- [ ] Confirm **GMCQ → Quizzes** still loads.
- [ ] Confirm dashboard stats still load.

Note: VS Code may still show `generate_import.py` as an open stale tab, but the file has been deleted from the plugin directory.