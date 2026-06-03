## ✅ Test Checklist — Categories Module

Use this checklist to verify all changes on your WordPress site.

### 1. Add New Category — Cascading Create Form
- [✅] Go to **GMCQ → Categories** → click **Add New Category**
- [✅] If no categories exist yet: verify only a **text input** for "New Main Category Name" appears
- [✅] If categories exist: verify a **dropdown** shows existing main categories + "Other (Type New)"
- [✅] Select "Other (Type New)" → verify a **text input appears** below
- [✅] Type a new main name (e.g. "Science") → verify **Sub Category section appears**
- [✅] Verify Sub Category shows "— None —" in dropdown
- [✅] Type a sub name (e.g. "Physics") and a description → click **Save Category** 
- [✅] Verify: page redirects to categories list, new category appears

### 2. Add Subcategory Under Existing Main
- [✅] Click **Add New Category** again
- [ ] In the Main dropdown, select an **existing main** (e.g. "Science")
- [ ] Verify Sub Category section appears with a **Loading...** state then populates
- [ ] If the main has existing subs → verify they appear in the dropdown + "Other (Type New)"
- [ ] Select "Other (Type New)" → type a new sub name → **Save**
- [ ] Verify page redirects, both Main + Sub appear in list

### 3. Status Filter Tabs
- [ ] On the Categories list page, verify **3 filter tabs** appear: **All | Active | Inactive**
- [ ] Click **Active** → verify only active categories show
- [ ] Click **Inactive** → verify only inactive categories show
- [ ] Click **All** → verify all categories show

### 4. Search Box
- [ ] On the Categories list page, verify the **search box** appears
- [ ] Type a category name → click **Search** → verify filtered results
- [ ] Click **Clear** → verify all results return

### 5. Row Actions — Active Category
- [ ] Find an **Active** category in the list
- [ ] Verify row actions show: **Edit | Deactivate**
- [ ] Click **Deactivate** → confirm dialog appears → click **OK**
- [ ] Verify: notice appears ("Category deactivated successfully"), page reloads
- [ ] Verify the category now shows **Inactive** status + **Reactivate | Remove** actions

### 6. Row Actions — Inactive Category
- [ ] Find an **Inactive** category
- [ ] Verify row actions show: **Edit | Reactivate | Remove**
- [ ] Click **Reactivate** → confirm → verify it becomes **Active** again
- [ ] Click **Deactivate** → then click **Remove**
- [ ] Verify: confirm dialog shows Phase 1 warning + question count
- [ ] Click **OK** → verify success notice

### 7. Edit Category Form
- [ ] Click **Edit** on any category → verify form loads with existing data
- [ ] Change name → click **Update Category** → verify success + redirect

### 8. Error Handling (defense in depth)
- [ ] With an **Active** category, manually try the Remove link → verify error: "Please deactivate first"
- [ ] With an **Inactive** category that has questions → try Remove → verify confirm shows question count + backend refuses with message
- [ ] With an **Inactive** category that has active children → try Remove → verify error message with children names

### 9. New Category Appears in Future Selections
- [ ] Create a new Main + Sub → page loads
- [ ] Click **Add New Category** again → verify the new Main appears in the dropdown
- [ ] Select the new Main → verify the new Sub appears in the Sub dropdown

### Phase 1 Compliance
- [ ] Verify no database errors or PHP warnings in debug log
- [ ] Verify all slugs are auto-generated and unique (e.g. "science", "science-1")
- [ ] Verify cache clearing works (category stats update after mutations)</｜｜DSML｜｜parameter>
</attempt_complete>