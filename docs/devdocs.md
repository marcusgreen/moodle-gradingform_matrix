# Matrix grading method: developer documentation

## Purpose

`gradingform_matrix` is an advanced grading method forked from core Rubric
(`gradingform_rubric`). Teachers can use it, but that is not why it exists. **It exists to
be a base for building new advanced grading methods**, so that each new method doesn't have
to start as another copy of rubric or marking guide.

The plugin has two roles, and they are kept apart:

1. **Shared code** in `classes/local/framework/` with method-neutral names (`criterion`,
   `level`, `filling`, `definition`). New methods are built on this code.
2. **The matrix method**, the smallest complete grading method that uses the shared code.
   It is the reference implementation and the test bed for that code.

The plugin is in development (`MATURITY_ALPHA`) and has no users yet. The full plan,
including the reasons behind each decision, is in [`REFACTORING.md`](../REFACTORING.md).

## The problem it solves

Moodle core ships two advanced grading methods, **Rubric** (`gradingform_rubric`) and
**Marking guide** (`gradingform_guide`). Almost every third-party grading method starts as a
fork of one of them and carries a `lib.php` of 700–1600 lines, most of it the same as the
original:

| Plugin | Forked from | `lib.php` lines | What it adds |
|---|---|---|---|
| [rubric_ranges](https://github.com/catalyst/moodle-gradingform_rubric_ranges) (Catalyst) | rubric | 1151 | per-criterion `isranged` flag: grader picks a number from 0 to the criterion maximum |
| [rubrix](https://github.com/catalyst/moodle-gradingform_rubrix) (Catalyst) | rubric | 1120 | penalty criteria (% or points) with a cap |
| [rubref](https://github.com/marcusgreen/moodle-gradingform_rubref) | rubric | 1029 | a mark chosen inside a band for each level |
| [fivedays](https://github.com/ntholi/moodle-gradingform_fivedays) | rubric | 709 | any mark between the midpoints of adjacent levels |
| [dukrub](https://github.com/Wunderbyte-GmbH/moodle-gradingform_dukrub) | rubric | 1055 | overrides the selected level's score |
| [enhancedrubric](https://github.com/ULCC/moodle-gradingform_enhancedrubric) (ULCC) | rubric | 1596 | level score bands, marking-workflow sections, grade history, marker-only comments |
| [erubric](https://github.com/johndimopoulos/moodle-gradingform_erubric) (LAe-R) | rubric | 1171 | levels chosen automatically from learning-analytics data |
| [simplefeedbackrubric](https://github.com/RVC-Learn-Digital-Team/moodle-gradingform_simplefeedbackrubric) | rubric | 881 | levels without scores; overall grade entered separately |
| [btec](https://github.com/marcusgreen/moodle-gradingform_btec) | guide | 730 | Pass/Merit/Distinction worked out by rule |
| [multigraders](https://github.com/newroco/moodle-gradingform_multigraders) | guide | 1382 | several graders per item, blind marking |

Once the component name is normalised, only about 450 of the 1151 lines in
`rubric_ranges/lib.php` differ from core rubric. Methods written from scratch, such as
[checklist](https://github.com/open-lms-open-source/moodle-gradingform_checklist),
[letter](https://github.com/CLAMP-IT/moodle-gradingform_letter) and
[weighted-total](https://github.com/ARyaskov/moodle-gradingform-weighted-total), rebuild the
same definition, filling, backup, privacy and web service code in their own way.
[random](https://github.com/mudrd8mz/moodle-gradingform_random) by David Mudrák is the
smallest possible method (112 lines) and a good reference for the bare core API.

Each fork has to repeat the same work:

- **Copying a large class and renaming it.** Persistence, regrade detection, validation,
  backup, privacy, search and the grading panel web services all live in one controller
  class and one instance class.
- **Keeping up with core by hand.** A fix to core rubric (for example MDL-87575 or
  MDL-68037) has to be found and ported to every fork, one at a time.
- **Copying bugs along with the code.** rubric_ranges checks its ranged grade only in the
  browser. `validate_grading_element()` never checks it, so a crafted form post or web service
  call can store an out-of-range grade. A shared validator that runs on the server prevents
  this kind of bug in every method that uses it.
- **Testing almost nothing.** Grade calculation sits in a method that needs the database,
  which makes it hard to unit test, so most forks don't test it at all.

## How it helps developers

### Small classes you can reuse, test and replace

The logic that used to live in `lib.php` has been moved into classes with one job each, under
`\gradingform_matrix\local\framework\`:

| Class | Job | Reuse notes |
|---|---|---|
| `criteria_repository` | Loads and saves a definition's criteria and levels | Table names are passed to the constructor, so it works with any method's tables, including short names such as `gradingform_rubric_ranges_c` |
| `filling_repository` | Loads, saves, copies and deletes fillings (one per criterion). `load_for_instances()` loads many instances in one query | Same: you pass the table name |
| `grade_calculator` (interface) | Turns a set of fillings into a grade | Must be pure: no database access, formatting or plugin lookups |
| `level_sum_calculator` | The rubric calculation: sums level scores and scales the total, with `lockzeropoints`. `lowest_grade_fraction()` gives the share of the maximum grade the lowest possible total earns, for the editor's summary | Default `grade_calculator` for level-based methods |
| `definition_diff` | Compares a stored definition with editor data and reports how much it changed | Pure: no database access |
| `change_summary`, `change_level` | The result of `definition_diff`: which kinds of change were found (none, minor, major…) | `change_level` uses the integers the editor and regrade confirmation already use |
| `filling_validator` | Checks a submission on the server for both the form and the web services (`is_complete()`, `is_empty()`) | Use one validator for both paths so the UI can't be bypassed |
| `definition_exporter` | Writes a level-based definition as JSON, with description images embedded as base64 | Works for any method; nothing matrix-specific |
| `results_exporter` | Builds the per-criterion results download: one row per graded item with the level, points and remark for each criterion, the total and the grade as a % of the maximum | Level-based; text goes through a closure, so a method picks its own text format. Score-based methods override `columns()` and `criteria_cells()` |
| `item_resolver` | Matches graded items to users. Core stores an item id whose meaning depends on the activity (`assign_grades.id`, `forum_grades.id`) | Add an activity by extending `TABLES` in a subclass. Unknown areas are listed by item id |
| `definition_importer` | Reads that JSON back, or criteria from spreadsheet rows (`from_rows()`), and checks their structure only | Content rules are left to the method's own editor, so each method keeps its own rules |
| `spreadsheet_reader` | Reads the cells of a CSV file (comma, semicolon or tab; UTF-8 or Windows-1252) or the first sheet of an .xlsx or .ods file, with core's PhpSpreadsheet | Values only: formulas aren't calculated, their saved results are used. Size limits are constructor arguments. No `.xls`: Moodle's PhpSpreadsheet has no reader for it |

Your plugin can usually reuse a class as it is. Where your method works differently, replace
only that one class. For example, a BTEC-style method would provide its own
`grade_calculator` for its Pass/Merit/Distinction rules and keep the repositories and the
diff.

### Calculation and change detection are unit tested

The calculator, the diff and the validator don't touch the database, so they can be tested
with plain arrays (`tests/local/framework/`). `tests/behaviour_test.php` pins the end-to-end
behaviour that was in place before the code was moved: negative points, `lockzeropoints`,
scale rounding, missing selections and regrade detection. Use it as a pattern for your own
method's contract tests.

### No rubric leftovers, neutral names

Identifiers inherited from rubric have been renamed, and the global `RUBRIC` constant has
been replaced by `gradingform_matrix_controller::METHOD`. Shared lang strings say "grading
form", "criterion" and "level" and have neutral ids (`saveform`, `formstatus`, `defineform`,
…). Only `pluginname` says "Matrix". So a method based on this code doesn't have to search
the UI for the word "rubric".

### Modern front end

- The grading UI (`amd/src/matrix.js`) and the editor (`amd/src/matrixeditor.js`) are
  ES6 AMD modules. The legacy YUI modules are gone.
- The grading panel resizes correctly when the panel is popped out (it watches the
  `.popout` / `.felement` container).
- Wide definitions scroll horizontally inside `.criteria-wrapper` instead of overflowing the
  page.
- **Reflow.** Every mode except the editor gets a `reflow` class (`display_mode::css_class()`),
  which switches each criterion from table cells to a flex row. Two CSS size containers decide
  how it folds, in the grader's side panel, the popout or a narrow screen alike:
  - `matrix` (`.criteria-wrapper`): at 43.75rem (700px) or less the description, levels and
    remark each take a full row.
  - `matrixlevels` (each criterion's `.levels` cell): the levels stack once each would be
    narrower than 7.5rem (120px). `tr:has(> .level:nth-child(n))` sets the width for each number
    of levels: below n × 7.5rem, so 15rem for two levels, 22.5rem for three, up to 75rem for ten.
    A flat 420px floor used to stack three-level criteria in the full-width grader, where core
    caps the grade panel at 800px and leaves each levels cell about 380px.
  - Breakpoints and the description, levels and remark widths are in `rem`, not `px`, so a user
    who sets a larger default font size gets a form that folds sooner, in step with the text
    (WCAG 1.4.4). Browser zoom already works with either unit. Percentages can't be used: a
    container query compares the container with itself.

  A size container has no width of its own, and the form's parent in the grader is a flex row
  that sizes it by its content, so the reflowing form sets `width: 100%`. Without it the form
  collapses to nothing.

  The site setting `gradingform_matrix/stacknarrow` (`layout_settings::stack_narrow()`, on by
  default; unset counts as on) chooses the class: the renderer passes it to
  `display_mode::css_class()`, which writes `reflow` when on and `reflow-popout` when off. Every
  reflow rule's selector starts `.gradingform_matrix:is(.reflow, .popout .reflow-popout)`, so
  with the setting off the form keeps its table and only folds inside the popout, as before
  the reflow existed.
- **Colours** come from the theme's Bootstrap variables (`--bs-body-bg`, `--bs-border-color`,
  `--bs-danger-bg-subtle`, …), so the form works in dark mode. There are no fixed colours in
  `styles.css`.
- **Accessibility checks:** `tests/behat/accessibility.feature` runs axe (core's default WCAG
  2.x A/AA tags) on the editor, the preview, the grading form and the student's graded view,
  scoped to the form so problems in core's page don't fail it.
- The editor shows a live score-to-grade summary (`#matrix-<name>-scoremapping`). The
  renderer's `score_mapping_summary()` prints it for the first page load and for browsers
  without JavaScript, using `level_sum_calculator::lowest_grade_fraction()`.
  `amd/src/local/scoremapping.js` keeps it up to date and repeats the same calculation, so
  change both together. It reports a percentage of the maximum grade, because the grade
  range is only set at grading time and isn't known on the editing page. A method with a
  different calculator should replace or hide this summary.

### Kept up to date with core rubric

Fixes made to core rubric in later Moodle versions are ported here, for example the editor
footer from MDL-87575 (guarded with `method_exists()` so it still runs on 5.2) and the
dark-mode row colour from MDL-68037. A method built on this code gets those fixes without
having to find them itself.

### Per-criterion results download

`results.php` (needs `moodle/grade:viewall`) downloads the results through core
`\core\dataformat`, so every installed format (CSV, Excel, ODS, …) works. The editing page links
to it. Core grade exports hold only the final grade, which is complaint #11 in
`docs/complaints.md`.

- Only the instance that counts is listed: active, or waiting for a regrade. Drafts and
  history are left out.
- The grade is a percentage of the maximum grade, because core doesn't store the grade a
  form gave (`grading_instances.rawgrade` is never written, MDL-31087) and the grade range is
  only set while grading.
- Identity columns follow the site's `showuseridentity` setting (standard user fields only).
- The controller wires it up in `results_exporter()` and `item_resolver()`. Criterion, level
  and remark text go through the renderer's `plain_text()`.

### Import and export

Teachers can export a definition as JSON and import it on the same site or another one
(`export.php`, `import.php`). The reusable parts are in the framework classes:

- An import saves the definition as a **draft** for the teacher to check.
- An import is refused once the form has been used for grading, including grades waiting
  for a regrade, because those fillings point at the levels an import replaces.
- Images go through a draft file area, so the editor's file size limits apply.
- The same page imports criteria from a spreadsheet: `import.php` sends `.csv`, `.xlsx` and
  `.ods` files through `spreadsheet_reader::read()` and `definition_importer::from_rows()`, and
  JSON through `from_json()`. Both give the same structure to `definition_transfer::import()`,
  which keeps the current name, description and options when the import has none.

### Text filters and multiple languages

The rule: text that is read goes through `format_text()` (filters on, the page's context),
and text that is edited or stored stays raw, with every language in it. Display modes format
criteria and levels as Markdown in `renderer.php` (`criterion_context()`, `level_context()`);
the purifier keeps multilang spans (`purify_html()` in `lib/weblib.php`).

- `renderer::plain_text()` formats and then strips the HTML, for accessible names,
  `data-criterion` and the results download, so these follow the reader's language too.
- `renderer::remark_html()` escapes a remark and keeps its line breaks, then runs filters
  (FORMAT_HTML). Markers type remarks in a plain textarea, so this keeps the rubric's
  "shows as typed" behaviour while letting `{mlang}` and similar filters work.
- `controller::get_download_name()` runs the form name through `format_string()` for
  `export.php` and `results.php` filenames. The exported JSON stays raw.
- The grading panel (`fetch.php` `get_formatted_text()`) and the editor's live preview
  (`classes/external/render_text.php`) already format with filters.
- Tests: `tests/renderer_test.php`, `results_exporter_test::test_columns_show_the_current_language()`,
  `tests/behat/multilang.feature`. Plan: `docs/multilang-filter-plan.md`.

## Building a new grading method on this code

> The shared code still lives inside `gradingform_matrix`. Until it moves into its own
> library plugin, **do not declare a dependency on `gradingform_matrix`** in a production
> plugin. Core lists every installed `gradingform` plugin in the grading method menu
> (`grading_manager::available_methods()`), and a grading method can't be disabled, so
> depending on matrix would put "Matrix" in front of every teacher on the site. For now,
> copy the classes you need and rename their namespace. Once the library plugin exists, set
> `$plugin->dependencies` to point at it.

What each method keeps for itself, because Moodle requires it per plugin:

- `version.php`, `db/install.xml`, `db/upgrade.php` and its own tables (the framework works
  *on* your tables but never owns them)
- lang strings (override the shared ones where your wording differs)
- the backup and restore classes and the privacy provider
- a thin `lib.php` that connects core's `gradingform_controller` / `gradingform_instance`
  to the framework classes

What differs between methods, and so is where you plug in your own code:

1. Schema: extra criterion, level or filling fields, and any extra tables
2. Grade calculation (`grade_calculator`)
3. The widget each criterion uses for grading: pick a level, enter a score, pick a level and
   then a score, and so on
4. Validation of the definition and of a filling (always on the server)
5. Which field changes need a regrade (`definition_diff`)
6. Optional features such as a comment bank, parts or rules

How a criterion is scored should be a property of the **criterion**, not of the whole method.
`rubric_ranges` already mixes level-scored criteria with ranged criteria in one definition.

## Roadmap

From `REFACTORING.md` → *Suggested order*:

1. Templates and view models in place of the HTML built in `renderer.php`
2. Generic Behat steps, a reusable test generator and contract tests
3. First port: [rubric_ranges](https://github.com/catalyst/moodle-gradingform_rubric_ranges) (per-criterion scoring, server-side range validation)
4. Move the shared code into its own library plugin, mark the stable API with `@api` and
   start `UPGRADING.md`
5. An author guide and a starter skeleton
6. Second port: [BTEC](https://github.com/marcusgreen/moodle-gradingform_btec) (score-based, rule-based result)

## Layout

```
classes/local/framework/   Shared, method-neutral code (see table above)
classes/grades/.../external/  fetch + store web services for the grading panel
classes/privacy/           GDPR provider
classes/form/import_form.php  Import upload form
lib.php                    Controller and instance (adapters over core)
renderer.php               HTML for all display modes
edit.php, edit_form.php, matrixeditor.php  Editor
import.php, export.php     JSON import and export
results.php                Per-criterion results download
amd/src/                   matrix.js (grading), matrixeditor.js (editor),
                           local/scoremapping.js (editor's score-to-grade summary)
backup/moodle2/            Backup and restore
tests/                     PHPUnit (incl. tests/local/framework/), Behat, generators
```
