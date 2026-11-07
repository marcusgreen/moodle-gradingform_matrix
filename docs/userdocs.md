# Matrix grading method: user guide

This guide is for teachers. It explains how the **Matrix** grading method differs from
Moodle's standard **Rubric**.

> **Status:** Matrix is in development (alpha). Its main purpose is to serve as the base for
> other grading methods, so don't use it for real assessment on a production site yet.

## Same as Rubric

If you know how to use Rubric, you already know how to use Matrix. These all work the same
way:

- **The grid:** criteria are rows, and each criterion has a set of levels. Each level has a
  definition and a number of points.
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
| `- clear aim`<br>`- method described` | a bulleted list |
| `[marking notes](https://example.org)` | a link |

The formatting is shown wherever the form is displayed: the preview, while grading, and when
students view their feedback. While you edit, you see the raw text you typed.

### 2. Wide forms scroll instead of breaking the page

A rubric with many levels can be wider than the screen, and then it pushes the rest of the
page out of shape. In Matrix the grid scrolls left and right inside its own box, and the rest
of the page stays in place.

### 3. Works better in the pop-out grading panel

When you grade in the grading panel and pop it out into a larger view, the Matrix grid
resizes to fit the new space. In Rubric it can stay at its old size.

### 4. Import and export grading forms

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

## Choosing Matrix for an activity

1. Open the activity (for example an assignment), then go to **Advanced grading**.
2. Under **Change active grading method to**, choose **Matrix**.
3. Choose **Define new grading form from scratch**, or start from a template.
4. Add criteria and levels, set the options, then select **Save grading form and make it
   ready**.
