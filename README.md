# Matrix grading method (gradingform_matrix)

An advanced grading method for Moodle, forked from core **Rubric**
(`gradingform_rubric`). It behaves like the standard rubric — a grid of criteria and
scored levels used to grade activities such as assignments — with a set of presentation
and front-end changes layered on top.

- **Component:** `gradingform_matrix`
- **Type:** Grading method (`grade/grading/form/`)
- **Based on:** core `gradingform_rubric`
- **Requires:** Moodle 5.x (`$plugin->requires = 2025092600`)
- **Maturity:** Stable

## What it does

Lets a teacher define a matrix of **criteria** (rows), each with a set of scored
**levels** (cells). Students are graded by selecting one level per criterion; the points
map onto the activity's grade range. Supports drafts, re-grading prompts, per-criterion
remarks, and the same display options as core rubric (show/hide scores and descriptions
to teacher/student, sort order, lock zero points).

## How it differs from core rubric

This is a fork, not a configuration of rubric. The functional changes:

### 1. Markdown rendering of criteria and levels
Criterion descriptions and level definitions are rendered as **Markdown** in all display
modes (preview, evaluate, review, view) instead of being plain-text escaped.
- `renderer.php` — `format_text($criterion['description'], FORMAT_MARKDOWN)` and
  `format_text($level['definition'], FORMAT_MARKDOWN)` (core rubric uses `s()`).
- Edit-mode textareas and frozen hidden inputs still round-trip the **raw** text;
  aria-labels stay plain text.
- Note: per-field input formats are not stored yet (MDL-31235), so the format is
  hardcoded to Markdown rather than read from `descriptionformat` / `definitionformat`.

### 2. Front-end grading JavaScript migrated to AMD, with popout support
The student-facing grading interaction was migrated from the legacy YUI module
(`js/rubric.js`, `M.gradingform_rubric.init`) to an AMD module.
- `amd/src/matrix.js` (`gradingform_matrix/matrix`), loaded via
  `js_call_amd('gradingform_matrix/matrix', 'init', ...)` in `lib.php`.
- Handles level cell click / keyboard toggling and **popout-aware sizing** — it observes
  the `.popout` / `.felement` container so the matrix resizes correctly when the grading
  panel is popped out.
- The **editor** UI (add/delete/reorder criteria and levels) still uses the legacy YUI
  module `js/matrixeditor.js`.

### 3. Horizontal scrolling for wide matrices
The criteria table is wrapped in a `.criteria-wrapper` `<div>` (`renderer.php`,
`matrix_template()`) with accompanying CSS so a matrix with many levels scrolls
horizontally instead of overflowing the page. `styles.css` carries the extra rules
(~380 lines vs ~270 in core).

### 4. Naming / packaging
All identifiers, language strings, tests and backup classes are renamed
`rubric` → `matrix` (e.g. `gradingform_matrix_controller`,
`backup_gradingform_matrix_plugin`, `edit_matrix.feature`).

## Layout

```
edit.php / preview.php            Editor and preview entry pages
edit_form.php / matrixeditor.php  Editor form + custom QuickForm element
lib.php                           Controller + instance classes (grade calc, DB)
renderer.php                      HTML rendering for all display modes
amd/src/matrix.js                 Front-end grading (AMD, popout-aware)
js/matrixeditor.js                Editor UI (legacy YUI)
templates/grades/grader/...       Grading panel Mustache templates
classes/grades/.../external/      fetch + store web services (grading panel)
classes/privacy/provider.php      GDPR provider (fillings table)
backup/moodle2/                   Backup / restore
db/services.php, db/upgrade.php   WS definitions, upgrade steps
tests/                            PHPUnit + Behat + data generators
```

## Database tables

- `gradingform_matrix_criteria` — criteria (description, sortorder)
- `gradingform_matrix_levels` — levels per criterion (score, definition)
- `gradingform_matrix_fillings` — per-grade selections + remarks

## Installation

Copy this directory to `grade/grading/form/matrix` in the Moodle tree and visit
**Site administration → Notifications** to install. Then select **Matrix** as the
grading method on a gradable activity (e.g. Assignment → *Advanced grading*).


## Licence

GNU GPL v3 or later — same as Moodle core.
