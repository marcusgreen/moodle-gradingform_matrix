# Matrix grading method: user guide

This guide is for teachers. It explains how the **Matrix** grading method differs from
Moodle's standard **Rubric**. In the list of grading methods it's called **Rubric (Matrix)**.

> **Status:** Matrix is in development (alpha). Its main purpose is to serve as the base for
> other grading methods, so don't use it for real assessment on a production site yet.
>
> It needs Moodle 5.1 or later.

## Same as Rubric

If you know how to use Rubric, you already know how to use Matrix. These all work the same
way:

- **The grid:** criteria are rows, and each criterion has a set of levels. Each level has a
  definition and a number of points.
- **Editing:** you can add, delete, move and duplicate criteria, and add and delete levels.
  Duplicating a criterion copies its description and all its levels and points.
- **Grading:** for each criterion, you click the level the student reached. You can add a
  remark (feedback) for each criterion.
- **Calculating the grade:** the points for the selected levels are added up and scaled to
  the activity's maximum grade, the same way Rubric does it.
- **Options:** sort levels by points, lock zero points, show or hide the definitions, show or
  hide the points to teachers and students, enable remarks, show remarks to students.
- **Drafts and saving:** you can save a form as a draft and save it as ready when it's
  finished.
- **Changing a form after grading:** if you change a form that has already been used, you're
  asked whether existing grades should be marked for regrading.
- **Templates:** you can share a form as a template and reuse your own forms or shared ones.
- **Backup and restore, the grader view and the grading panel:** all work as they do with
  Rubric.

Some wording is different. Matrix says "grading form" where Rubric says "rubric", for
example "Save grading form and make it ready" and "Define grading form".

## What's different

### 1. Formatted criteria and level definitions (Markdown)

In Rubric, criterion descriptions and level definitions are shown as plain text. In Matrix
they're written in **Markdown**, so you can format them:

| You type | Students and graders see |
|---|---|
| `**must** include a bibliography` | **must** include a bibliography |
| `*italic*` | *italic* |
| `* clear aim`<br>`* method described` | a bulleted list |
| `1. first step` | a numbered list |
| `[marking notes](https://example.org)` | a link |

A blank line starts a new paragraph. The help icon next to **Grading form** on the editing
page lists all the formatting you can use.

The formatting is shown wherever the form is displayed: the preview, while grading, and when
students view their feedback. On the editing page, too, criteria and levels you aren't
editing show the formatting. Click one to edit it, and you see the text you typed.

### 2. Wide forms scroll instead of breaking the page

A rubric with many levels can be wider than the screen, and then it pushes the rest of the
page out of shape. In Matrix the grid scrolls left and right inside its own box, and the rest
of the page stays in place.

### 3. A resizable pop-out grading panel

When you grade an assignment, you can pop the grading form out of the panel into its own
box. In Matrix, that box can be changed to suit you:

- **Resize it** by dragging the **Resize grading form** handle in its bottom-right corner.
  The grid rearranges itself to fit.
- **Resize it with the keyboard:** Tab to the same handle, then use the arrow keys. Hold
  Shift for bigger steps. Home makes it as small as possible and End as large as possible.
  Screen readers announce the new size.
- **Maximise it** with the **Maximise grading form** button, to use the whole window.
- The size you choose is kept while you move between students.

On a small screen, the popped-out form fills the screen.

### 4. Choosing levels with the keyboard and screen readers

Each criterion's levels work as a group of radio buttons:

- Tab moves to the selected level of a criterion, or to its first level if none is selected.
- The arrow keys move the selection between that criterion's levels. Home and End go to the
  first and last level.
- Space or Enter selects the level you're on.

Screen readers announce each criterion's name with its levels, and say which level is
selected.

### 5. Import and export grading forms

The editing page has **Export** and **Import** buttons. Rubric has neither.

- **Export** downloads the whole grading form as a file: its name, description (including
  any images), options, criteria and levels. You can keep it as a backup or move it to
  another course or another Moodle site.
- **Import** replaces the current form with one from an exported file.
  - The imported form is saved as a **draft**. Check it, then save it to make it ready.
  - You can't import into a form that has already been used for grading, because the
    existing grades depend on its levels. Create a new form instead.
  - If the file isn't a valid export (for example, a score that isn't a number), the import
    is refused with a message that tells you where the problem is.

Rubric's only way to reuse a form is the template system, and that works only within one
site. Export and import also work between sites.

**Import from a spreadsheet.** Import also accepts a CSV file, an Excel workbook (.xlsx) or an
OpenDocument spreadsheet (.ods), so you can write the criteria in a spreadsheet and bring them
in. Lay it out like this:

| Criterion | Level 1 | Points | Level 2 | Points | Level 3 | Points |
|---|---|---|---|---|---|---|
| Clear position | Absent | 0 | Partial | 2 | Explicit | 4 |
| Use of evidence | None | 0 | Limited | 2 | Strong | 4 |

- Each row is a criterion. The first column is its description, then each level takes two
  columns: its definition, then its points.
- A criterion can have fewer levels than the others; leave the extra cells empty.
- A first row of headings is optional and is skipped.
- Only the first sheet is read. Formulas are fine: the result your spreadsheet program saved
  is used.
- CSV files can use commas, semicolons or tabs.
- The form's name, description and options stay as they are. A new form is named after the
  file.
- Old Excel files (.xls) can't be read. Save them as .xlsx first.
- A level with text but no points, or points that aren't a number, stops the import with a
  message naming the row and level.

### 6. An AI assistant for writing grading forms

The editing page can have an **AI assistant** button. The assistant reads course material
you choose and drafts a grading form with you in a conversation.

1. Select **AI assistant**.
2. Under **Course material to read**, tick the material the assistant should base the form
   on. It can read pages, labels, books, lessons, and folders and files that contain plain
   text, HTML or CSV. It can't read PDFs or images.
3. In **Instruction**, say what you want, for example "Draft a form with 4 criteria and 3
   levels", then select **Send** (or press Ctrl+Enter).
4. The proposed form appears under **Proposed grading form**. Ask for changes, for example
   "Make the presentation criterion stricter", as many times as you like. **Start again**
   clears the conversation.
5. When you're happy, select **Apply as draft**. This replaces the current form with the
   proposal and saves it as a draft. Check it, then save it to make it ready.

Things to know:

- The material you select is sent to the site's AI provider. AI can make mistakes, so check
  the form before you use it.
- Like import, applying a proposal isn't possible once the form has been used for grading.
- The first time you use AI on the site, you're asked to accept its AI policy.
- If the button is missing, the assistant is turned off for the site, or you don't have
  permission to use it.
- If the assistant says it isn't available, AI tools may be turned off for the course or
  activity, or no AI provider may be set up for generating text. Ask your site
  administrator.

### 7. A live summary of how points become a grade

Above the criteria, the editing page shows what grade the lowest and highest possible totals
give, for example:

> Lowest possible total: 3 points, which gives 30% of the maximum grade. Highest possible
> total: 10 points, which gives the maximum grade.

The summary updates as you change points, add or delete levels and criteria, or tick and
untick **Calculate grade having a minimum score of the minimum achievable grade for the
grading form**. The lowest total is the sum of each criterion's lowest level.

This is there because Rubric's most common complaint is grades that look wrong when no level
is worth 0. With that option unticked, a student who gets the lowest level everywhere gets
**0**, even if those levels are worth points. The summary shows **0%** in that case, so you
see it before grading starts. The option is ticked by default for new forms.

The summary gives a percentage rather than a grade, because the activity's maximum grade can
change after the form is made. With a scale, the result is rounded to a scale item. The
summary is hidden while it can't be worked out, for example while every level has the same
points.

### 8. Download each criterion's results

The editing page has a **Download results** button. Choose a file format (for example CSV
or Excel) and select **Download**. You get one row for each person graded with the form:

- their name and the identity fields your site shows, such as email address
- who graded them, when, and whether the grade needs regrading after a change to the form
- for each criterion: the level chosen, its points and the remark
- the total points, and the grade as a percentage of the activity's maximum grade

Only each person's current grade is included, not drafts or earlier grades. Rubric has no
way to do this: Moodle's grade export gives only the final grade.

You need permission to view all grades (`moodle/grade:viewall`). This works for assignments
and forums. In other activities, people are listed by item ID instead of by name.

## Choosing Matrix for an activity

1. Open the activity (for example an assignment), then go to **Advanced grading**.
2. Under **Change active grading method to**, choose **Rubric (Matrix)**.
3. Choose **Define new grading form from scratch**, or start from a template.
4. Add criteria and levels, set the options, then select **Save grading form and make it
   ready**.

## For site administrators

Grading methods have no page in **Site administration**, so Matrix's settings are linked from
the grading form editing page instead: site administrators see a **Matrix grading method
settings** link there.

- **Enable the AI assistant** is off by default, because the assistant sends course material
  to the site's AI provider. AI providers themselves are set up in **Site administration >
  AI**, and generating text must be enabled there too.
- Who may use the assistant is controlled by the capability **Use the AI assistant to write
  grading forms** (`gradingform/matrix:useaiassistant`). By default, editing teachers and
  managers have it.
