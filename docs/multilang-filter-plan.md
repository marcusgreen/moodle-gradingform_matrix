# Multi-language filter support

Status: changes 1–4 built on branch `feature/multilang-filter` (tests pass, not yet committed).
Change 5 and the API checks come from the MDL-77404 research below and are not built yet.

## Context

Sites running in several languages write criteria and levels with the multilang filter:
`<span lang="en" class="multilang">Good</span><span lang="de" class="multilang">Gut</span>`
(core `filter_multilang`) or `{mlang en}Good{mlang}{mlang de}Gut{mlang}` (third-party `filter_multilang2`).
Each viewer should see only their own language, and screen reader names and downloads should match.

Most of this already works, because the text goes through `format_text()` with filters on:

- Criterion descriptions and level definitions: `renderer.php` `criterion_context()` / `level_context()`
  (FORMAT_MARKDOWN). Markdown passes the span through and the purifier keeps it (`lib/weblib.php`
  `purify_html()`, the `xxxlang` swap).
- Accessible names, `data-criterion`, results download columns and cells: `renderer::plain_text()`,
  used by `controller::results_exporter()`.
- Form description: `controller::get_formatted_description()`.
- Grading panel: `classes/grades/grader/gradingpanel/external/fetch.php` `get_formatted_text()`.
- Live editor preview: `classes/external/render_text.php`.
- Editing textareas, hidden inputs, JSON export/import and spreadsheet import keep the raw text with
  every language, which is right.

Gaps:

1. Remarks in REVIEW/VIEW modes are printed escaped and never filtered (`renderer.php` remark cell,
   `templates/criterion.mustache`). The remark's accessible name also uses the raw text.
2. The form name is used raw in download filenames (`export.php`, `results.php`), so multilang
   markup ends up in the filename.
3. No tests or docs cover filters.

## Research: MDL-77404 and related core issues

[MDL-77404](https://moodle.atlassian.net/browse/MDL-77404) "Support text format for rubrics criteria
and levels" (open, unassigned since 2023, many votes) asks for rubric criteria, levels and remarks to
support text formats: filters (multilang), bold, italics, line breaks, possibly an HTML editor.
Related issues:

- [MDL-31235](https://moodle.atlassian.net/browse/MDL-31235) did this for the **marking guide**
  (fixed in 4.2). The guide now formats each field with its **stored** format:
  `format_text($criterion['description'], $criterion['descriptionformat'])`. Rubric was left with
  `TODO MDL-31235` comments and `FORMAT_MOODLE` hard-coded on save.
- [MDL-50414](https://moodle.atlassian.net/browse/MDL-50414) "Filters not applied to rubric text",
  closed as a duplicate. Sites patch core with Luca Bösch's change, which only runs filters when the
  rubric is rendered (used with `filter_multilang2`).
- [MDL-85382](https://moodle.atlassian.net/browse/MDL-85382) "Rubric formatting with editor",
  a duplicate.

History from the comments on MDL-31235 and MDL-50414:

- **Why rubric is plain text.** MDL-46223 (a security issue, restricted) removed HTML from the
  grading methods because of XSS. David Mudrak's reasoning: if HTML is allowed it should be
  supported fully, with embedded images; HTML without file support "could easily lead to false
  expectations". He later agreed that filters had not been considered, and that `format_string()`
  is enough for the XSS risk. He asked for a check of the other places MDL-46223 touched,
  "especially escaping the texts in Javascript".
- **Luca Bösch's MDL-50414 patch** (still used on sites, e.g. with `filter_multilang2`) runs
  rubric text through `format_string()` with no context, so it falls back to `$PAGE->context`. Core
  turned it down at the time because it partly undid the MDL-46223 fix, and pointed to MDL-31235.
- **MDL-31235 (Stephan Robotta, 4.2)** put the marking guide's labels, descriptions and frequently
  used comments through `format_text()`. That allows multilang and simple HTML such as `<b>` and
  `<i>`. The editor has no format picker, and the stored format was always 0. The reviewer's
  checklist left "mobile app / web services" unticked. Robotta saw a rare "undefined index
  `descriptionformat`" warning in the guide renderer when a field was missing.
- **The guide's frequent comments** are formatted when they are inserted, not when they are shown.
  A later comment (Jeffrey Black, 2025) shows the cost: `p<em>K</em><sub>a</sub>` becomes
  `p_K_a`, LaTeX turns back into its source, and the remark shown to the student loses its line
  breaks. Lesson: store what the person typed, and format only when showing it.
- **Line breaks in remarks.** The strongest user complaint (Richard van Iwaarden, 2025): rubric
  feedback is "one large string of words", and in Dutch running sentences together can change
  their meaning. Core rubric and guide print remarks with `s()`, so typed line breaks are lost.
- Duplicates [MDL-85382](https://moodle.atlassian.net/browse/MDL-85382) (2025, an editor for colour,
  bold and italic in criteria, levels and feedback) and MDL-85053 (in-place editing in the
  gradebook's single view) ask for the same thing elsewhere.

What the discussion settled, and what it means for Matrix:

| Point from the issue | Matrix |
|---|---|
| Filters (multilang) on criteria and levels matter most. A German/French university needs them. | Done: display goes through `format_text()`. |
| Teachers want bold, italics and **line breaks**; today a rubric is "one big line". | Done differently: Markdown with a live preview, so no HTML editor is needed. |
| A full HTML editor is hard because the rubric edits in place, and it brings files and images. Nobody wants images. | Out of scope. Markdown avoids the editor and file areas. |
| Remarks are one-to-one between marker and student, so they need no multilang. | Agreed. Change 1 still runs filters on remarks, for glossary, emoticon or `{mlang}` filters where a site wants them, but multilang is not the reason. The docs say that. |
| "The mobile app and any associated APIs also need to be considered." | Gap: see change 5 and the API checks. |
| Remarks lose their line breaks. | Done: `remark_html()` keeps them with `nl2br()`. Core rubric and guide still don't. |
| HTML without images raises false expectations (Mudrak). | Markdown can show an image by URL (`![](https://…)`), but there is no upload and no file area. The user docs say so. |
| Format on display, never on store or insert (guide frequent comments). | Already the rule: the stored text stays raw, and `format_text()` runs at display time. Kept for the AI assistant and imports too. |
| Check escaping in JavaScript (MDL-46223). | New security check below. |

The research found one gap in the current code. Matrix renders criteria and levels as Markdown on
the page, but `criteria_repository` saves `descriptionformat` / `definitionformat` as
`FORMAT_MOODLE`. Code that honours the stored format therefore treats the text as Moodle format:
the grading panel (`fetch.php` `get_formatted_text()`), the web service structures in
`external_structures.php`, backup, and the mobile app. Filters still run there, so multilang works,
but Markdown such as `**bold**` shows literally and the page and the panel disagree.

## Changes

### 1. Filter remarks in display modes (`renderer.php`, `templates/criterion.mustache`)

- Add `renderer::remark_html(string $remark): string` =
  `format_text(nl2br(s($remark)), FORMAT_HTML, ['context' => $this->page->context, 'para' => false])`.
  Escaping first keeps today's behaviour (what the teacher types shows literally, as in core rubric)
  and still runs text filters, so `{mlang}`, glossary and URL-to-link filters work. A literal
  `<span class="multilang">` typed in a remark stays visible text. That is the cost of not letting
  markers enter HTML, and the user docs say so.
- REVIEW/VIEW: the remark cell context gets `html` from `remark_html()` in place of `text`. The
  template prints `{{{html}}}`. Update the template doc block and example context.
- Remark accessible name: build it from the filtered text converted to plain text. Split
  `plain_text()` into `format_text()` + a private `html_to_plain(string $html)` helper, and reuse the
  helper.
- The EVAL textarea, the EVAL_FROZEN hidden input and the grading panel textarea stay raw. They are
  edit fields.

### 2. Form name in filenames (`export.php`, `results.php`)

- Before `clean_filename()`, run the name through `format_string($name, true, ['context' => $context])`,
  then `html_entity_decode(strip_tags(...))`. The downloader then gets their own language and no
  markup. The JSON content stays raw, so import still round-trips every language.

### 3. Tests

- New `tests/renderer_test.php` (advanced_testcase, `CoversClass(gradingform_matrix_renderer)`).
  Turn the filter on with `filter_set_global_state('multilang', TEXTFILTER_ON)`, set the page context,
  then check:
  - `plain_text()` of a two-language span returns only the current language (`en`);
  - criterion and level HTML in VIEW mode keep the `en` text and drop the `de` text;
  - `remark_html()` escapes `<b>`, turns newlines into `<br>` and runs the filters (check with
    `urltolink`, because multilang spans get escaped);
  - with the filter off, both languages show (control case).
- Results exporter: one case where `totext` filters, so the column headings show only one language,
  unless the existing exporter test injects a stub closure and the renderer test covers it.
- New `tests/behat/multilang.feature` (`@gradingform @gradingform_matrix @javascript`):
  `the "multilang" filter is "on"` and `the "multilang" filter applies to "content and headings"`.
  Define a matrix whose criterion and level hold `en`/`de` spans. Check that the teacher's grading view
  and the student's feedback view show "Good" and not "Gut", and that the downloaded results heading
  has only `en` (reuse the steps in `download_results.feature`).

### 4. Docs and version

- `docs/complaints.md`: a new item, "No formatting, line breaks or filters in rubric text", citing
  MDL-77404, MDL-31235, MDL-50414 and MDL-85382, with the Matrix status and code pointers.
- `docs/userdocs.md`: a "Multiple languages" section: both filter syntaxes, where they apply
  (criteria, levels, form description, remarks with `{mlang}` only, downloads), and that the editor
  shows every language while the preview shows yours.
- `docs/devdocs.md`: the formatting rule (display = `format_text()` with the page context, edit = raw)
  with pointers to `renderer::plain_text()` / `remark_html()`, `fetch.php` `get_formatted_text()`
  and `render_text.php`.
- `README.md`: one line in the feature list.
- `version.php`: bump so sites pick up the changed `criterion.mustache` template. No DB change.

### 5. Store the format the text is written in (from MDL-77404 / MDL-31235)

- `classes/local/framework/criteria_repository.php`: save `descriptionformat` and
  `definitionformat` as `FORMAT_MARKDOWN` in place of `FORMAT_MOODLE`.
- `db/upgrade.php`: one step setting both columns to `FORMAT_MARKDOWN` on existing rows. The plugin
  is unreleased, but dev sites have data. Bump `version.php`.
- `renderer.php`: format with the stored format, as the marking guide does, defaulting to
  `FORMAT_MARKDOWN` when the field is missing (templates, AI proposals, imports). `plain_text()`
  takes the format already. Remove the `TODO MDL-31235` comments this settles.
- The grading panel and web services then render Markdown the same way as the page, with no change
  to `fetch.php`.
- Import/export: the JSON format leaves formats out, so imported rows get `FORMAT_MARKDOWN` from
  the repository. Old exports need no migration.
- Backup/restore: the format columns are copied as stored. Restoring a rubric-era backup
  (`FORMAT_MOODLE`) still displays, as Moodle format. Check this in the restore test.
- Tests: the repository saves `FORMAT_MARKDOWN`; `fetch` returns `<strong>` for `**bold**`; the
  upgrade step converts rows.

### API and mobile checks

- `core_grading_get_definitions` and the `external_structures` returns: check that criteria and
  levels go out with `FORMAT_MARKDOWN`, so clients format them correctly.
- The Moodle app has no Matrix handler, so there is nothing to change there. If one is added, it
  must format text server-side with filters, as `fetch.php` does.

### Security check (from MDL-46223)

Go through every path that puts criterion, level or remark text into the page and confirm it
escapes or uses server-formatted HTML:

- `amd/src/matrixeditor.js`: the plain views use `escapeHTML()`, and the preview `innerHTML`
  comes from `gradingform_matrix_render_text` (`format_text()`, cleaned).
- `amd/src/local/aiassistant.js`: the preview `innerHTML` comes from the server (`ai_turn`,
  `format_text()`). Chat text uses `textContent`.
- Templates: only `description.html`, `definition.html` and the remark `html` use `{{{ }}}`, and
  each holds `format_text()` output. Everything else uses `{{ }}`.
- The grading panel template prints `description` / `definition` from `fetch.php`
  (`format_text()`) and the remark in a textarea.
- Add a test that saves `<script>`/`onerror` in a criterion, a level and a remark, and checks that
  the rendered HTML has none of it.

## Out of scope

- An HTML editor (TinyMCE) for criteria, levels or remarks, which MDL-77404 also asks for. In-place
  editing makes it costly, and Markdown covers bold, italics and line breaks.
- The AI assistant sends the raw multilang text and course content to the LLM. The model sees every
  language, which is acceptable.
- Core grading management pages that show the form name belong to core (no core patches).

## Verification

1. PHPUnit for `gradingform_matrix`: the new renderer tests pass and existing tests stay green.
2. Behat `--tags=@gradingform_matrix`, including `multilang.feature`.
3. Manual check: enable Multi-Language Content, install a second language pack, make a matrix with
   spans, then switch the user language and look at the preview, grading, the student's feedback view,
   the results download and the JSON export filename.
4. phpcs, phpdoc and mustache lint on the changed files.
