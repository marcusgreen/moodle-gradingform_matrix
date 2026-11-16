# Multi-language filter support

Status: planned, branch `feature/multilang-filter`.

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

- `docs/userdocs.md`: a "Multiple languages" section: both filter syntaxes, where they apply
  (criteria, levels, form description, remarks with `{mlang}` only, downloads), and that the editor
  shows every language while the preview shows yours.
- `docs/devdocs.md`: the formatting rule (display = `format_text()` with the page context, edit = raw)
  with pointers to `renderer::plain_text()` / `remark_html()`, `fetch.php` `get_formatted_text()`
  and `render_text.php`.
- `README.md`: one line in the feature list.
- `version.php`: bump so sites pick up the changed `criterion.mustache` template. No DB change.

## Out of scope

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
