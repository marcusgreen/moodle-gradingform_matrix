# Multi-language filter support

Status: changes 1–4 built on branch `feature/multilang-filter` (tests pass, not yet committed).
Change 5 and the API checks come from the MDL-77404 research below and are not built yet.
Change 6 (third-party `filter_multilang2`) is planned and not built.
Change 7 (multilang text from the AI assistant) is an assessment with a proposed design, not built.

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

### 6. Support the third-party `filter_multilang2`

Read from the plugin source (iarenaza/moodle-filter_multilang2, v2.0.5.5, `classes/text_filter.php`):

- Syntax: `{mlang en}Good{mlang}{mlang de}Gut{mlang}`. One block can name several languages
  (`{mlang en,fr}…{mlang}`), and `{mlang other}…{mlang}` is shown when no other block matches.
  Parent languages count, depending on the `parentlangbehaviour` setting.
- It filters at the **`pre_format`** stage, before Markdown or HTML conversion, and at the
  **`string`** stage (for `format_string()`). It does nothing at `pre_clean` / `post_clean`.
- **When no block matches and there is no `other` block, it outputs an empty string.** Core
  `filter_multilang` falls back to the first language instead.

What that means for each path:

| Path | Format | Works with multilang2? |
|---|---|---|
| Criteria and levels on the page (`renderer.php`) | FORMAT_MARKDOWN | Yes. `pre_format` runs before `markdown_to_html()`, so a block can hold several paragraphs or a list, which the `<span>` syntax can't. |
| `plain_text()`: accessible names, `data-criterion`, download headings and cells | FORMAT_MARKDOWN | Yes. |
| Remarks, `remark_html()` | FORMAT_HTML after `nl2br(s())` | Yes. `s()` leaves the braces alone, and the kept text is already escaped. Tags typed on their own lines leave a stray `<br>` (see below). |
| Live editor preview (`render_text.php`) and the grading panel (`fetch.php`) | Markdown / stored format | Yes. Every format except FORMAT_PLAIN runs `pre_format`. |
| Form name in download filenames, `get_download_name()` | `format_string()` | Only when the filter applies to **content and headings**. Otherwise the tags stay in and `clean_filename()` keeps the braces. Document this. |
| JSON export, spreadsheet import, backup | raw | Yes, the text is stored unchanged. |

Changes:

1. **Empty text when the reader's language is missing.** If a teacher writes only
   `{mlang en}` and `{mlang de}` blocks, a French reader gets an empty criterion, the accessible name
   "Criterion " and a download column ": level".
   - `renderer.php`: when the filtered plain text is empty but the raw text is not, use a numbered
     name for the accessible name and `data-criterion`: new strings `criterionnumbered`
     ("Criterion {$a}") and `levelnumbered` ("Level {$a}"). The visible cell stays empty, because
     that is what the filter decided.
   - `results_exporter.php`: the same fallback for column headings (`totext` result empty →
     numbered name), so headings are never blank or duplicated.
   - Editor: a non-blocking warning when a criterion or level uses `{mlang` without
     `{mlang other}`. Add a `mlangnoother` string and check in `edit.php` /
     `definition_validator` as a warning, not an error, because the site may have every language
     covered.
   - User docs: recommend ending with an `{mlang other}…{mlang}` block, or using core multilang,
     which falls back to the first language.
2. **Stray line breaks in remarks.** A remark typed as
   `{mlang en}Well done{mlang}⏎{mlang de}Gut gemacht{mlang}` leaves `Well done<br />`. In
   `remark_html()`, trim leading and trailing `<br />` and whitespace from the filtered result.
   Markdown fields need nothing, because blank lines left between blocks do nothing in Markdown.
3. **Security:** none needed. The filter runs before `clean_text()` for Markdown and HTML, and
   remarks are escaped before filtering, so a language block can't bring in markup.
4. **Tests.** The plugin is not in core, so tests must cope with it being missing:
   - PHPUnit in `tests/renderer_test.php`: `markTestSkipped()` unless
     `core_component::get_component_directory('filter_multilang2')`. Cover a criterion with
     `{mlang en}`/`{mlang de}`, a multi-paragraph block, `{mlang en,fr}`, a remark with `{mlang}`
     on separate lines (no stray `<br>`), and the numbered fallback when no block matches.
   - Behat: `tests/behat/multilang2.feature` tagged `@filter_multilang2`, the same scenario as
     `multilang.feature` with the `{mlang}` syntax plus a remark.
   - CI (`.github/workflows/moodle-ci.yml`): before "Install moodle-plugin-ci", add
     `moodle-plugin-ci add-plugin iarenaza/moodle-filter_multilang2` so CI runs the tests above.
     Locally, run Behat with `--tags="@gradingform_matrix&&~@filter_multilang2"` unless the plugin
     is installed.
5. **Docs.** In `docs/userdocs.md` "Text in several languages": both syntaxes, the `other` advice,
   "content and headings" for the file name, and that `{mlang}` blocks can span paragraphs.
   `docs/devdocs.md`: the stage table above.

### 7. Multi-language text from the AI assistant (assessment, not built)

The question: should the AI assistant know about multilang, so a teacher can ask "add mlang tags
for French, German and Spanish" and get a form in four languages?

**What happens today, with no change.** The instruction is free text (`ai_turn.php`, PARAM_TEXT,
2000 characters), so a teacher can already ask for it. The rules in `prompt_builder::rules()` and
`controller::ai_assistant()` say nothing about languages, so the result depends on the model:

- It has to guess the syntax. It may write `<span lang="fr" class="multilang">`, `{mlang fr}`, a
  `lang` attribute without the class, or `[FR]` labels. Only the syntax of the filter that is on in
  this context works. The other kinds show as literal text, or the purifier removes them.
- It has to guess the language codes. A code with no installed language pack is never shown. Core
  `filter_multilang` then falls back to the first language. `filter_multilang2` shows nothing (see
  change 6).
- The method rules clash with the core syntax. Each level definition must be "**Name**, a blank
  line, then one or two sentences". Markdown turns a blank line inside a `<span>` into two
  paragraphs, so the span is split and the core filter, which runs on the HTML after Markdown, can't
  pair the languages. A span group in each paragraph works. `{mlang}` blocks across paragraphs also
  work, because multilang2 runs at `pre_format` (see change 6).
- The preview (`ai_turn::preview()`) runs filters, so the teacher sees only their own language and
  can't check the other three before applying. The editor afterwards shows them all.

The output path is safe as it is. The proposal goes through `definition_importer` and is formatted
with `format_text()` when shown, so tags from the model are cleaned like typed ones. Stored text
stays raw, which keeps the rule "format on display, never on store".

**Verdict: worth doing as a small, conditional prompt rule. A dedicated UI is not worth it.**

Reasons for:

- The plugin's design case is the German/French university from MDL-77404. Writing every
  criterion and level in three or four languages by hand is the tedious work the assistant is for.
- Most of the cost is one more method rule. The importer, renderer and filters already handle the
  result.
- Without a rule, the teacher gets unreliable syntax and codes and may only find out after applying.

Reasons for caution:

- **Translation quality.** Criteria are assessment text. Students are graded against them, and a
  wrong word can change the meaning (the Dutch line-break complaint above is the same problem). The
  teacher often can't read every target language. The assistant's message must say the
  translations are machine-made and should be checked by someone who reads the language. The user
  docs must say so too.
- **Size.** Four languages make every field about four times longer. The whole proposal is resent
  each turn (`prompt_builder::build()`), and the model must write the whole form each time. A long
  form can reach the provider's output limit. The reply is then cut short, the JSON block doesn't
  parse, and `reply_parser` reports an error. Suggest translating as the last step, once the form
  is settled, rather than editing it in four languages.
- **Keeping languages in step.** After translation, each change in a later turn has to be made in
  every language. Models drift: one language gets the change and the others don't. The rule must
  say so, and a check (below) catches what it can.

**Changes, if built:**

1. `controller::ai_assistant()`: add the multilang rules only when a multilang filter is active in
   the form's context (`filter_get_active_in_context($context)`, `multilang2` first, then
   `multilang`). If neither is on, add nothing, and the model writes in one language as now. When
   one is on, the rule:
   - names the syntax of that filter, with one example of a level definition (for core: one span
     group per paragraph; for multilang2: `{mlang xx}` blocks, ending with `{mlang other}`);
   - lists the installed language codes (`get_string_manager()->get_list_of_translations()`) and
     says to use only those;
   - says: only add languages when the teacher asks; keep every existing language when changing the
     form; apply each change to every language; never translate the JSON keys.
   The rule is a few lines, so it costs little in the prompt. Pass it as part of `$methodrules`, so
   `prompt_builder` stays the same for other methods (see `docs/library-split.md`).
2. `ai_turn`: after parsing, warn (in `error` or the message, not a rejection) when a language code
   in the proposal is not installed, or when one field has languages that another field lacks.
   Reuse the `{mlang` without `other` check from change 6. Put the code-scanning in a small helper
   in `classes/local/framework/`, so change 6's editor warning and this share it.
3. Preview: add a "Show all languages" switch in `aiassistant.js` that asks `ai_turn` for a preview
   rendered with `'filter' => false`, so the teacher can see every language before applying. If
   that's too much, at least say in the message that the preview shows only the teacher's language.
4. Course material: `content_source::extract()` calls `html_to_text()` on the raw HTML, so a page
   written with spans reaches the model as "SpellingRechtschreibung", every language run together.
   Run `format_text()` with the module's context first, so filters pick the teacher's language. That
   also saves budget in `SOURCE_BUDGET`. Separate from translation, but found while checking this.
5. No new UI for picking languages. A chip or checkbox list would hold the same information as the
   instruction "add French, German and Spanish", and the teacher would still need to know which
   languages to ask for.
6. Tests: `prompt_builder`/`controller` test that the rule is there only when a filter is on, and
   names the right syntax; a stub backend returning a three-language proposal, checking the preview
   in the user's language and the warning for an unknown code. Behat: the existing AI scenarios with
   a stub reply that holds spans.
7. Docs: `docs/userdocs.md` "Text in several languages" gets a paragraph on asking the assistant to
   translate, with the quality warning and "translate last". `docs/ai-assistant-plan.md` links here.

Order: 4 is small and worth doing on its own. 1 and the message wording give most of the value. 2
and 3 can wait until someone uses it.

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
- The AI assistant sends the raw multilang text of the current form to the LLM, so the model sees
  every language and can keep them. That is intended. Course material and asking the assistant to
  translate are covered in change 7.
- Core grading management pages that show the form name belong to core (no core patches).

## Verification

With `filter_multilang2` installed on a dev site (it is not on mdl52 yet): switch between a language that has a block, one that only `other` covers, and one
with no block, and check the page, the grading panel, the student view, the screen reader names and
the download.


1. PHPUnit for `gradingform_matrix`: the new renderer tests pass and existing tests stay green.
2. Behat `--tags=@gradingform_matrix`, including `multilang.feature`.
3. Manual check: enable Multi-Language Content, install a second language pack, make a matrix with
   spans, then switch the user language and look at the preview, grading, the student's feedback view,
   the results download and the JSON export filename.
4. phpcs, phpdoc and mustache lint on the changed files.
