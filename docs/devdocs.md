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
| `filling_repository` | Loads, saves, copies and deletes fillings (one per criterion) | Same: you pass the table name |
| `grade_calculator` (interface) | Turns a set of fillings into a grade | Must be pure: no database access, formatting or plugin lookups |
| `level_sum_calculator` | The rubric calculation: sums level scores and scales the total, with `lockzeropoints`. `lowest_grade_fraction()` gives the share of the maximum grade the lowest possible total earns, for the editor's summary | Default `grade_calculator` for level-based methods |
| `definition_diff` | Compares a stored definition with editor data and reports how much it changed | Pure: no database access |
| `change_summary`, `change_level` | The result of `definition_diff`: which kinds of change were found (none, minor, major…) | `change_level` uses the integers the editor and regrade confirmation already use |
| `filling_validator` | Checks a submission on the server for both the form and the web services (`is_complete()`, `is_empty()`) | Use one validator for both paths so the UI can't be bypassed |
| `definition_exporter` | Writes a level-based definition as JSON, with description images embedded as base64 | Works for any method; nothing matrix-specific |
| `definition_importer` | Reads that JSON back and checks its structure only | Content rules are left to the method's own editor, so each method keeps its own rules |

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

### Import and export

Teachers can export a definition as JSON and import it on the same site or another one
(`export.php`, `import.php`). The reusable parts are in the framework classes:

- An import saves the definition as a **draft** for the teacher to check.
- An import is refused once the form has been used for grading, including grades waiting
  for a regrade, because those fillings point at the levels an import replaces.
- Images go through a draft file area, so the editor's file size limits apply.

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
amd/src/                   matrix.js (grading), matrixeditor.js (editor),
                           local/scoremapping.js (editor's score-to-grade summary)
backup/moodle2/            Backup and restore
tests/                     PHPUnit (incl. tests/local/framework/), Behat, generators
```
