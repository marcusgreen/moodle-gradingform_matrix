# Refactor Plan: Separate Editing and Rendering Code in `gradingform_matrix`

The core problem is that `renderer.php`, `matrixeditor.php`, `styles.css`, and `js/matrixeditor.js` all have edit-mode and view/eval-mode code tightly intertwined. The separation uses a **delegation pattern** inside the existing registered renderer (Moodle's renderer registry only allows one renderer per plugin), extracting logic into plain PHP helper classes.

---

## Phase 1 — Extract Edit Renderer Class (Low Risk)

**New file: `classes/output/edit_renderer.php`**

Create class `gradingform_matrix\output\edit_renderer` (plain PHP, not a Moodle renderer). Move all `DISPLAY_EDIT_FULL` / `DISPLAY_EDIT_FROZEN` HTML generation out of `renderer.php` into this class:

- `criterion_controls(int $mode, array $options, string $elementname, array $criterion): string` — the `.controls` `<td>` with moveup/delete/movedown/duplicate buttons, hidden `sortorder` input, and the `contenteditable` description div
- `level_content_edit(array $options, string $elementname, string $criterionid, array $level): string` — the definition `<textarea>`, score `<input>`, delete button, and frozen hidden inputs
- `matrix_options_edit(string $mode, array $options): string` — the `<div class="options">` checkboxes/selects block
- `matrix_wrapper_edit(string $mode, string $elementname, string $criteriastr, array $options): string` — the editor/frozen/preview outer class suffix and "Add criterion" button

**Modified: `renderer.php`**

Add a private `$editrenderer` property with a lazy `get_edit_renderer()` initialiser. Replace inline edit-mode `if/else` blocks in `criterion_template()`, `level_template()`, `matrix_template()`, and `matrix_edit_options()` with delegation calls. All public method signatures remain unchanged — no callers change.

---

## Phase 2 — Extract View/Eval Renderer Class (Low Risk)

**New file: `classes/output/view_renderer.php`**

Create class `gradingform_matrix\output\view_renderer`. Move all `DISPLAY_EVAL`, `DISPLAY_REVIEW`, `DISPLAY_VIEW`, `DISPLAY_PREVIEW` HTML into:

- `criterion_content_view(int $mode, ..., ?array $value): string` — `format_text()` description, hidden criterion key input for eval, remark cell variants per mode
- `level_content_view(int $mode, ..., array $level): string` — radio buttons for eval, `aria-checked`/`role=radio` attributes for review/view, `role=listitem` for preview
- `matrix_wrapper_view(int $mode, string $elementname, string $criteriastr): string` — class suffixes `evaluate editable`, `evaluate frozen`, `review`, `view`

**Key:** Each delegate returns only the _inner content_ of the `<td>`. The outer `<td>` wrapper and the `{NAME}` macro `str_replace` calls remain in `renderer.php` to avoid duplicating wrapper logic.

**Modified: `renderer.php`**

Add `$viewrenderer` lazy loader. Delegate view/eval blocks. Result: `criterion_template()` and `level_template()` become thin dispatchers — they assemble the `<td>` wrapper and call either `$this->get_edit_renderer()` or `$this->get_view_renderer()` for the inner HTML.

---

## Phase 3 — Split `matrixeditor.php`: Data Processing vs. Display (Medium Risk)

**New file: `classes/editor/data_processor.php`**

Move `prepare_data()` and `get_next_id()` from `MoodleQuickForm_matrixeditor` into `gradingform_matrix\editor\data_processor`. The processor exposes public properties `$nonjsbuttonpressed` and `$validationerrors` that the form element reads after calling `prepare()`.

**Modified: `matrixeditor.php`**

`MoodleQuickForm_matrixeditor` becomes a thin shell:
- `toHtml()`: calls `$this->get_processor()->prepare(...)` then proceeds to rendering
- `non_js_button_pressed()`, `validate()`, `exportValue()`: delegate to the processor
- Remove `prepare_data()` and `get_next_id()` entirely

`toHtml()`'s JS loading block stays here (it is coupled to the page lifecycle and must remain in the form element).

---

## Phase 4 — Reorganise CSS with Section Comments (No Risk)

**Modified: `styles.css`**

Moodle only auto-loads `styles.css` — no multi-file split is possible without AMD. Reorganise into clearly demarcated sections with comments:

```css
/* SECTION: Shared structural styles (all modes) */
/* ... */

/* SECTION: Edit mode only (.gradingform_matrix.editor) */
/* ... buttons, htmleditor, hiddenelement, pseudotablink, options panel, error states */

/* SECTION: Eval/view/review mode only */
/* ... aria-checked border, grading panel styles */
```

This is purely editorial — no selectors change. It sets the stage for a true file split when AMD migration is complete (Phase 5).

---

## Phase 5 — Migrate YUI JS to AMD + Mustache Templates (High Risk, do last)

This phase breaks the deepest coupling: `matrixeditor.js` currently receives full PHP-rendered HTML strings as `criteriontemplate`/`leveltemplate` and instantiates new elements via regex string replacement.

**New files: `amd/src/matrixeditor.js` and `amd/src/matrix.js`**

AMD replacements for both YUI modules. The key architectural change: instead of HTML strings, the edit AMD module uses `core/templates` to render new criterion/level elements client-side.

**New files: `templates/criterion_edit.mustache` and `templates/level_edit.mustache`**

Mustache templates define the single HTML contract for edit-mode elements, used by both:
- PHP renderer (server-side render on page load)
- AMD module (client-side render when JS adds a new row)

**Modified: `renderer.php` / `classes/output/edit_renderer.php`**

`criterion_template()` and `level_template()` for `DISPLAY_EDIT_FULL` render from the Mustache templates via `$OUTPUT->render_from_template(...)` instead of inline HTML strings.

**Modified: `matrixeditor.php::toHtml()`**

Replace `$PAGE->requires->js_init_call('M.gradingform_matrixeditor.init', [...criteriontemplate, leveltemplate...])` with `$PAGE->requires->js_call_amd('gradingform_matrix/matrixeditor', 'init', [['name' => ..., 'options' => ...]])`.

**Modified: `lib.php::render_grading_element()`**

Replace `$page->requires->js_init_call('M.gradingform_matrix.init', ...)` with `$page->requires->js_call_amd('gradingform_matrix/matrix', 'init', [...])`.

Run `grunt amd` from Moodle root after this phase.

**Keep `js/matrixeditor.js` and `js/matrix.js` until the AMD equivalents are confirmed working.**

---

## Phase 6 — lib.php Targeted Updates (Low Risk)

Only two changes needed:
1. `render_grading_element()`: the JS init call swap (done as part of Phase 5)
2. `get_renderer()`: no change — the single registered renderer remains the public API

`DISPLAY_*` constants stay on `gradingform_matrix_controller`. The new helper classes receive mode integers and compare against those constants (safe since `lib.php` is always loaded first).

---

## Sequencing and Risk Summary

| Phase | What Changes | Risk | Plugin Working After? |
|---|---|---|---|
| 1 | Extract edit renderer class | Low | Yes |
| 2 | Extract view renderer class | Low | Yes |
| 3 | Extract data processor from `matrixeditor.php` | Medium | Yes |
| 4 | Reorganise CSS with comments | None | Yes |
| 5 | Migrate YUI to AMD + Mustache | High | Yes (after `grunt amd`) |
| 6 | `lib.php` JS init swap | Low | Yes |

Phases 1–4 can be done in any order. Phase 5 depends on Phases 1–2 (Mustache templates must match what the PHP edit renderer produces). Phase 6's JS change is part of Phase 5.

---

## Critical Coupling Points That Must Not Be Broken Until Phase 5

1. `criterion_template()` and `level_template()` signatures on `gradingform_matrix_renderer` — `matrixeditor.php::toHtml()` calls these to get HTML strings for JS
2. CSS class names (`.level`, `.score`, `.description`, `.htmleditor`, `.hiddenelement`) — `matrixeditor.js` selects by these; rename only in Phase 5 with the AMD migration
3. `{NAME}`, `{CRITERION-id}`, `{LEVEL-id}` macro syntax — shared between PHP `str_replace` and JS `regex replace`; eliminate in Phase 5 via Mustache
4. `MoodleQuickForm_matrixeditor` class name in `matrixeditor.php` — registered in `edit_form.php`, must not change
