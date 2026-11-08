<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Grading method controller for the Matrix plugin
 *
 * @package    gradingform_matrix
 * @copyright  2011 David Mudrak <david@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core_external\external_format_value;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use gradingform_matrix\local\framework\ai\assistant;
use gradingform_matrix\local\framework\ai\backend;
use gradingform_matrix\local\framework\ai\prompt_builder;
use gradingform_matrix\local\framework\ai\reply_parser;
use gradingform_matrix\local\framework\change_level;
use gradingform_matrix\local\framework\criteria_repository;
use gradingform_matrix\local\framework\definition_diff;
use gradingform_matrix\local\framework\definition_exporter;
use gradingform_matrix\local\framework\definition_importer;
use gradingform_matrix\local\framework\filling_repository;
use gradingform_matrix\local\framework\filling_validator;
use gradingform_matrix\local\framework\grade_calculator;
use gradingform_matrix\local\framework\level_sum_calculator;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/grade/grading/form/lib.php');
require_once($CFG->dirroot . '/lib/filelib.php');

/**
 * This controller encapsulates the matrix grading logic
 *
 * @package    gradingform_matrix
 * @copyright  2011 David Mudrak <david@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gradingform_matrix_controller extends gradingform_controller {
    /** Grading method name, used to compare against a grade item's advanced grading method. */
    const METHOD = 'matrix';

    // Modes of displaying the matrix (used in gradingform_matrix_renderer)
    /** Matrix display mode: For editing (moderator or teacher creates a matrix) */
    const DISPLAY_EDIT_FULL     = 1;
    /** Matrix display mode: Preview the matrix design with hidden fields */
    const DISPLAY_EDIT_FROZEN   = 2;
    /** Matrix display mode: Preview the matrix design (for person with manage permission) */
    const DISPLAY_PREVIEW       = 3;
    /** Matrix display mode: Preview the matrix (for people being graded) */
    const DISPLAY_PREVIEW_GRADED = 8;
    /** Matrix display mode: For evaluation, enabled (teacher grades a student) */
    const DISPLAY_EVAL          = 4;
    /** Matrix display mode: For evaluation, with hidden fields */
    const DISPLAY_EVAL_FROZEN   = 5;
    /** Matrix display mode: Teacher reviews filled matrix */
    const DISPLAY_REVIEW        = 6;
    /** Matrix display mode: Dispaly filled matrix (i.e. students see their grades) */
    const DISPLAY_VIEW          = 7;

    /**
     * Extends the module settings navigation with the matrix grading settings
     *
     * This function is called when the context for the page is an activity module with the
     * FEATURE_ADVANCED_GRADING, the user has the permission moodle/grade:managegradingforms
     * and there is an area with the active grading method set to 'matrix'.
     *
     * @param settings_navigation $settingsnav {@link settings_navigation}
     * @param navigation_node $node {@link navigation_node}
     */
    public function extend_settings_navigation(settings_navigation $settingsnav, ?navigation_node $node = null) {
        $node->add(
            get_string('defineform', 'gradingform_matrix'),
            $this->get_editor_url(),
            settings_navigation::TYPE_CUSTOM,
            null,
            null,
            new pix_icon('icon', '', 'gradingform_matrix')
        );
    }

    /**
     * Extends the module navigation
     *
     * This function is called when the context for the page is an activity module with the
     * FEATURE_ADVANCED_GRADING and there is an area with the active grading method set to the given plugin.
     *
     * @param global_navigation $navigation {@link global_navigation}
     * @param navigation_node $node {@link navigation_node}
     */
    public function extend_navigation(global_navigation $navigation, ?navigation_node $node = null) {
        if (has_capability('moodle/grade:managegradingforms', $this->get_context())) {
            // no need for preview if user can manage forms, he will have link to manage.php in settings instead
            return;
        }
        if ($this->is_form_defined() && ($options = $this->get_options()) && !empty($options['alwaysshowdefinition'])) {
            $node->add(
                get_string('gradingof', 'gradingform_matrix', get_grading_manager($this->get_areaid())->get_area_title()),
                new moodle_url('/grade/grading/form/' . $this->get_method_name() . '/preview.php', ['areaid' => $this->get_areaid()]),
                settings_navigation::TYPE_CUSTOM
            );
        }
    }

    /**
     * Saves the matrix definition into the database
     *
     * @see parent::update_definition()
     * @param stdClass $newdefinition matrix definition data as coming from gradingform_matrix_editmatrix::get_data()
     * @param int|null $usermodified optional userid of the author of the definition, defaults to the current user
     */
    public function update_definition(stdClass $newdefinition, $usermodified = null) {
        $this->update_or_check_matrix($newdefinition, $usermodified, true);
        if (isset($newdefinition->matrix['regrade']) && $newdefinition->matrix['regrade']) {
            $this->mark_for_regrade();
        }
    }

    /**
     * Either saves the matrix definition into the database or check if it has been changed.
     * Returns the level of changes:
     * 0 - no changes
     * 1 - only texts or criteria sortorders are changed, students probably do not require re-grading
     * 2 - added levels but maximum score on matrix is the same, students still may not require re-grading
     * 3 - removed criteria or added levels or changed number of points, students require re-grading but may be re-graded automatically
     * 4 - removed levels - students require re-grading and not all students may be re-graded automatically
     * 5 - added criteria - all students require manual re-grading
     *
     * @param stdClass $newdefinition matrix definition data as coming from gradingform_matrix_editmatrix::get_data()
     * @param int|null $usermodified optional userid of the author of the definition, defaults to the current user
     * @param boolean $doupdate if true actually updates DB, otherwise performs a check
     *
     */
    public function update_or_check_matrix(stdClass $newdefinition, $usermodified = null, $doupdate = false) {
        global $DB;

        if ($this->definition === false) {
            if (!$doupdate) {
                // If we create the new definition there is no such thing as re-grading anyway.
                return change_level::CRITERIA_ADDED->value;
            }
            // If definition does not exist yet, create a blank one (we need id to save files embedded in description).
            parent::update_definition(new stdClass(), $usermodified);
            parent::load_definition();
        }
        if (!isset($newdefinition->matrix['options'])) {
            $newdefinition->matrix['options'] = self::get_default_options();
        }
        $newdefinition->options = json_encode($newdefinition->matrix['options']);
        $editoroptions = self::description_form_field_options($this->get_context());
        $newdefinition = file_postupdate_standard_editor(
            $newdefinition,
            'description',
            $editoroptions,
            $this->get_context(),
            'grading',
            'description',
            $this->definition->id
        );

        // Reload the definition from the database.
        $currentdefinition = $this->get_definition(true);
        $currentcriteria = $currentdefinition->matrix_criteria;
        $newcriteria = empty($newdefinition->matrix['criteria']) ? [] : self::normalise_scores($newdefinition->matrix['criteria']);

        $summary = (new definition_diff())->compare(
            $currentdefinition,
            $currentcriteria,
            !empty($this->get_options()['lockzeropoints']),
            $newdefinition,
            $newcriteria,
            !empty($newdefinition->matrix['options']['lockzeropoints']),
            $usermodified
        );
        if (!$summary->has_changes()) {
            return change_level::NONE->value;
        }
        if ($doupdate) {
            $transaction = $DB->start_delegated_transaction();
            $this->criteria_repository()->save($this->definition->id, $newcriteria, $currentcriteria);
            parent::update_definition($newdefinition, $usermodified);
            $transaction->allow_commit();
            $this->load_definition();
        }
        return $summary->level()->value;
    }

    /**
     * Converts submitted level scores from the user's number format to floats.
     *
     * @param array $criteria submitted criteria with levels
     * @return array
     */
    protected static function normalise_scores(array $criteria): array {
        foreach ($criteria as $cid => $criterion) {
            foreach ($criterion['levels'] ?? [] as $lid => $level) {
                if (isset($level['score'])) {
                    $criteria[$cid]['levels'][$lid]['score'] = unformat_float($level['score']);
                }
            }
        }
        return $criteria;
    }

    /**
     * Returns the repository for this method's criteria and levels.
     *
     * @return criteria_repository
     */
    protected function criteria_repository(): criteria_repository {
        return new criteria_repository('gradingform_matrix_criteria', 'gradingform_matrix_levels');
    }

    /**
     * Returns the repository for this method's fillings.
     *
     * @return filling_repository
     */
    public function filling_repository(): filling_repository {
        return new filling_repository('gradingform_matrix_fillings');
    }

    /**
     * Returns the calculator that turns a filling into a grade.
     *
     * @return grade_calculator
     */
    public function grade_calculator(): grade_calculator {
        return new level_sum_calculator();
    }

    /**
     * Returns the validator for submitted fillings.
     *
     * @return filling_validator
     */
    public function filling_validator(): filling_validator {
        return new filling_validator();
    }

    /**
     * Returns the writer for exported definitions.
     *
     * @return definition_exporter
     */
    public function definition_exporter(): definition_exporter {
        return new definition_exporter($this->get_method_name());
    }

    /**
     * Returns the reader for imported definitions.
     *
     * @return definition_importer
     */
    public function definition_importer(): definition_importer {
        return new definition_importer(self::get_default_options(), 'gradingform_matrix');
    }

    /**
     * Returns the AI assistant for this method, which proposes forms with scored levels and Markdown definitions.
     *
     * @param backend $backend
     * @return assistant
     */
    public function ai_assistant(backend $backend): assistant {
        $rules = implode("\n", [
            '- Give each criterion between 2 and 6 levels. The lowest level usually scores 0.',
            '- Scores are numbers, and higher levels score more.',
            '- Level definitions may use Markdown. Start each one with the name of the level in bold, for example',
            '  "**Competent**", then a blank line, then one or two sentences.',
            '- Criterion descriptions are short headings, such as "Use of evidence".',
        ]);
        return new assistant($backend, new prompt_builder($rules), $this->ai_reply_parser());
    }

    /**
     * Returns the reader for the AI assistant's replies and proposals.
     *
     * @return reply_parser
     */
    public function ai_reply_parser(): reply_parser {
        return new reply_parser($this->definition_importer());
    }

    /**
     * Exports the current definition.
     *
     * @return string JSON
     */
    public function export_definition(): string {
        $definition = $this->get_definition();
        if (!$definition) {
            throw new coding_exception('There is no definition to export.');
        }
        $exporter = $this->definition_exporter();
        $files = [];
        $areafiles = get_file_storage()->get_area_files(
            $this->get_context()->id,
            'grading',
            'description',
            $definition->id,
            'filepath, filename',
            false
        );
        foreach ($areafiles as $file) {
            $files[] = [
                'filepath' => $file->get_filepath(),
                'filename' => $file->get_filename(),
                'mimetype' => $file->get_mimetype(),
                'content' => $file->get_content(),
            ];
        }
        return $exporter->to_json(
            $definition->name,
            (string)$definition->description,
            (int)$definition->descriptionformat,
            $this->get_options(),
            $definition->matrix_criteria,
            $files
        );
    }

    /**
     * Whether an import may replace the definition.
     *
     * False once anyone has been graded with it, including grades that are waiting for regrading,
     * because their fillings refer to the levels that an import replaces.
     *
     * @return bool
     */
    public function can_import(): bool {
        global $DB;
        if (empty($this->definition->id)) {
            return true;
        }
        [$insql, $params] = $DB->get_in_or_equal(
            [gradingform_instance::INSTANCE_STATUS_ACTIVE, gradingform_instance::INSTANCE_STATUS_NEEDUPDATE],
            SQL_PARAMS_NAMED
        );
        $params['definitionid'] = $this->definition->id;
        return !$DB->record_exists_select('grading_instances', "definitionid = :definitionid AND status $insql", $params);
    }

    /**
     * Replaces the definition with imported data and saves it as a draft.
     *
     * Values missing from the import (name, description, options) are kept from the current definition.
     * Not allowed once the form has been used for grading, see {@see can_import()}.
     *
     * @param array $data result of {@see definition_importer::from_json()}
     * @param string $fallbackname name to use when neither the import nor the current definition has one
     * @param int|null $usermodified optional userid of the author of the definition, defaults to the current user
     * @throws moodle_exception if the form has been used for grading
     */
    public function import_definition(array $data, string $fallbackname, $usermodified = null): void {
        if (!$this->can_import()) {
            throw new moodle_exception('importerrorused', 'gradingform_matrix');
        }
        $current = $this->get_definition();

        $new = new stdClass();
        $new->name = $data['name'] ?? (($current && $current->name !== '') ? $current->name : $fallbackname);
        if (isset($data['description'])) {
            // The text editor can only edit HTML; with any other format the editor falls back to a plain text
            // area. AI proposals use Markdown, so convert it.
            $text = $data['description'];
            $format = $data['descriptionformat'];
            if ((int)$format === (int)FORMAT_MARKDOWN) {
                $text = markdown_to_html($text);
                $format = FORMAT_HTML;
            }
            // Saving through a draft area replaces the description's files and applies the editor's size limits.
            $new->description_editor = [
                'text' => $text,
                'format' => $format,
                'itemid' => self::create_draft_area($data['files'] ?? []),
            ];
        } else {
            $new->description_editor = [
                'text' => $current ? (string)$current->description : '',
                'format' => $current ? (int)$current->descriptionformat : FORMAT_HTML,
            ];
        }
        $new->status = self::DEFINITION_STATUS_DRAFT;
        $new->matrix = ['options' => $data['options'] ?? $this->get_options(), 'criteria' => []];

        $newlevid = 1;
        foreach (array_values($data['criteria']) as $index => $criterion) {
            $levels = [];
            foreach ($criterion['levels'] as $level) {
                $levels['NEWID' . $newlevid++] = ['score' => $level['score'], 'definition' => $level['definition']];
            }
            $new->matrix['criteria']['NEWID' . ($index + 1)] = [
                'sortorder' => $index + 1,
                'description' => $criterion['description'],
                'levels' => $levels,
            ];
        }

        $this->update_definition($new, $usermodified);
    }

    /**
     * Puts files into a new draft area of the current user.
     *
     * @param array $files list of ['filepath', 'filename', 'content']
     * @return int draft item id
     */
    protected static function create_draft_area(array $files): int {
        global $USER;
        $draftitemid = file_get_unused_draft_itemid();
        $fs = get_file_storage();
        foreach ($files as $file) {
            $fs->create_file_from_string([
                'contextid' => context_user::instance($USER->id)->id,
                'component' => 'user',
                'filearea' => 'draft',
                'itemid' => $draftitemid,
                'filepath' => $file['filepath'],
                'filename' => $file['filename'],
            ], $file['content']);
        }
        return $draftitemid;
    }

    /**
     * Marks all instances filled with this matrix with the status INSTANCE_STATUS_NEEDUPDATE
     */
    public function mark_for_regrade() {
        global $DB;
        if ($this->has_active_instances()) {
            $conditions = ['definitionid'  => $this->definition->id,
                        'status'  => gradingform_instance::INSTANCE_STATUS_ACTIVE];
            $DB->set_field('grading_instances', 'status', gradingform_instance::INSTANCE_STATUS_NEEDUPDATE, $conditions);
        }
    }

    /**
     * Loads the matrix form definition if it exists
     *
     * There is a new array called 'matrix_criteria' appended to the list of parent's definition properties.
     */
    protected function load_definition() {
        $loaded = $this->criteria_repository()->load($this->areaid, $this->get_method_name());
        if ($loaded === null) {
            $this->definition = false;
            return;
        }
        [$this->definition, $criteria] = $loaded;
        $this->definition->matrix_criteria = $criteria;
        if (!$this->get_options()['sortlevelsasc']) {
            foreach (array_keys($this->definition->matrix_criteria) as $rcid) {
                $this->definition->matrix_criteria[$rcid]['levels'] =
                    array_reverse($this->definition->matrix_criteria[$rcid]['levels'], true);
            }
        }
    }

    /**
     * Returns the default options for the matrix display
     *
     * @return array
     */
    public static function get_default_options() {
        $options = [
            'sortlevelsasc' => 1,
            'lockzeropoints' => 1,
            'alwaysshowdefinition' => 1,
            'showdescriptionteacher' => 1,
            'showdescriptionstudent' => 1,
            'showscoreteacher' => 1,
            'showscorestudent' => 1,
            'enableremarks' => 1,
            'showremarksstudent' => 1,
        ];
        return $options;
    }

    /**
     * Gets the options of this matrix definition, fills the missing options with default values
     *
     * The only exception is 'lockzeropoints' - if other options are present in the json string but this
     * one is absent, this means that the matrix was created before Moodle 3.2 and the 0 value should be used.
     *
     * @return array
     */
    public function get_options() {
        $options = self::get_default_options();
        if (!empty($this->definition->options)) {
            $thisoptions = json_decode($this->definition->options, true); // Assoc. array is expected.
            foreach ($thisoptions as $option => $value) {
                $options[$option] = $value;
            }
            if (!array_key_exists('lockzeropoints', $thisoptions)) {
                // Matrices created before Moodle 3.2 don't have 'lockzeropoints' option. In this case they should not
                // assume default value 1 but use "legacy" value 0.
                $options['lockzeropoints'] = 0;
            }
        }
        return $options;
    }

    /**
     * Converts the current definition into an object suitable for the editor form's set_data()
     *
     * @param boolean $addemptycriterion whether to add an empty criterion if the matrix is completely empty (just being created)
     * @return stdClass
     */
    public function get_definition_for_editing($addemptycriterion = false) {

        $definition = $this->get_definition();
        $properties = new stdClass();
        $properties->areaid = $this->areaid;
        if ($definition) {
            foreach (['id', 'name', 'description', 'descriptionformat', 'status'] as $key) {
                $properties->$key = $definition->$key;
            }
            $options = self::description_form_field_options($this->get_context());
            $properties = file_prepare_standard_editor(
                $properties,
                'description',
                $options,
                $this->get_context(),
                'grading',
                'description',
                $definition->id
            );
        }
        $properties->matrix = ['criteria' => [], 'options' => $this->get_options()];
        if (!empty($definition->matrix_criteria)) {
            $properties->matrix['criteria'] = $definition->matrix_criteria;
        } else if (!$definition && $addemptycriterion) {
            $properties->matrix['criteria'] = ['addcriterion' => 1];
        }

        return $properties;
    }

    /**
     * Returns the form definition suitable for cloning into another area
     *
     * @see parent::get_definition_copy()
     * @param gradingform_controller $target the controller of the new copy
     * @return stdClass definition structure to pass to the target's {@link update_definition()}
     */
    public function get_definition_copy(gradingform_controller $target) {

        $new = parent::get_definition_copy($target);
        $old = $this->get_definition_for_editing();
        $new->description_editor = $old->description_editor;
        $new->matrix = ['criteria' => [], 'options' => $old->matrix['options']];
        $newcritid = 1;
        $newlevid = 1;
        foreach ($old->matrix['criteria'] as $oldcritid => $oldcrit) {
            unset($oldcrit['id']);
            if (isset($oldcrit['levels'])) {
                foreach ($oldcrit['levels'] as $oldlevid => $oldlev) {
                    unset($oldlev['id']);
                    $oldcrit['levels']['NEWID' . $newlevid] = $oldlev;
                    unset($oldcrit['levels'][$oldlevid]);
                    $newlevid++;
                }
            } else {
                $oldcrit['levels'] = [];
            }
            $new->matrix['criteria']['NEWID' . $newcritid] = $oldcrit;
            $newcritid++;
        }

        return $new;
    }

    /**
     * Options for displaying the matrix description field in the form
     *
     * @param object $context
     * @return array options for the form description field
     */
    public static function description_form_field_options($context) {
        global $CFG;
        return [
            'maxfiles' => -1,
            'maxbytes' => get_user_max_upload_file_size($context, $CFG->maxbytes),
            'context'  => $context,
        ];
    }

    /**
     * Formats the definition description for display on page
     *
     * @return string
     */
    public function get_formatted_description() {
        if (!isset($this->definition->description)) {
            return '';
        }
        $context = $this->get_context();

        $options = self::description_form_field_options($this->get_context());
        $description = file_rewrite_pluginfile_urls(
            $this->definition->description,
            'pluginfile.php',
            $context->id,
            'grading',
            'description',
            $this->definition->id,
            $options
        );

        $formatoptions = [
            'noclean' => false,
            'trusted' => false,
            'filter' => true,
            'context' => $context,
        ];
        return format_text($description, $this->definition->descriptionformat, $formatoptions);
    }

    /**
     * Returns the matrix plugin renderer
     *
     * @param moodle_page $page the target page
     * @return gradingform_matrix_renderer
     */
    public function get_renderer(moodle_page $page) {
        return $page->get_renderer('gradingform_' . $this->get_method_name());
    }

    /**
     * Returns the HTML code displaying the preview of the grading form
     *
     * @param moodle_page $page the target page
     * @return string
     */
    public function render_preview(moodle_page $page) {

        if (!$this->is_form_defined()) {
            throw new coding_exception('It is the caller\'s responsibility to make sure that the form is actually defined');
        }

        $criteria = $this->definition->matrix_criteria;
        $options = $this->get_options();
        $matrix = '';
        if (has_capability('moodle/grade:managegradingforms', $page->context)) {
            $showdescription = true;
        } else {
            if (empty($options['alwaysshowdefinition'])) {
                // ensure we don't display unless show matrix option enabled
                return '';
            }
            $showdescription = $options['showdescriptionstudent'];
        }
        $output = $this->get_renderer($page);
        if ($showdescription) {
            $matrix .= $output->box($this->get_formatted_description(), 'gradingform_matrix-description');
        }
        if (has_capability('moodle/grade:managegradingforms', $page->context)) {
            if (!$options['lockzeropoints']) {
                // Warn about using grade calculation method where minimum number of points is flexible.
                $matrix .= $output->display_matrix_mapping_explained($this->get_min_max_score());
            }
            $matrix .= $output->display_matrix($criteria, $options, self::DISPLAY_PREVIEW, 'matrix');
        } else {
            $matrix .= $output->display_matrix($criteria, $options, self::DISPLAY_PREVIEW_GRADED, 'matrix');
        }

        return $matrix;
    }

    /**
     * Deletes the matrix definition and all the associated information
     */
    protected function delete_plugin_definition() {
        global $DB;
        $transaction = $DB->start_delegated_transaction();
        $this->filling_repository()->delete_for_definition($this->definition->id);
        $DB->delete_records('grading_instances', ['definitionid' => $this->definition->id]);
        $this->criteria_repository()->delete_for_definition($this->definition->id);
        $transaction->allow_commit();
    }

    /**
     * If instanceid is specified and grading instance exists and it is created by this rater for
     * this item, this instance is returned.
     * If there exists a draft for this raterid+itemid, take this draft (this is the change from parent)
     * Otherwise new instance is created for the specified rater and itemid
     *
     * @param int $instanceid
     * @param int $raterid
     * @param int $itemid
     * @return gradingform_instance
     */
    public function get_or_create_instance($instanceid, $raterid, $itemid) {
        global $DB;
        if (
            $instanceid &&
                $instance = $DB->get_record('grading_instances', ['id'  => $instanceid, 'raterid' => $raterid, 'itemid' => $itemid], '*', IGNORE_MISSING)
        ) {
            return $this->get_instance($instance);
        }
        if ($itemid && $raterid) {
            $params = ['definitionid' => $this->definition->id, 'raterid' => $raterid, 'itemid' => $itemid];
            if ($rs = $DB->get_records('grading_instances', $params, 'timemodified DESC', '*', 0, 1)) {
                $record = reset($rs);
                $currentinstance = $this->get_current_instance($raterid, $itemid);
                if (
                    $record->status == gradingform_matrix_instance::INSTANCE_STATUS_INCOMPLETE &&
                        (!$currentinstance || $record->timemodified > $currentinstance->get_data('timemodified'))
                ) {
                    $record->isrestored = true;
                    return $this->get_instance($record);
                }
            }
        }
        return $this->create_instance($raterid, $itemid);
    }

    /**
     * Returns html code to be included in student's feedback.
     *
     * @param moodle_page $page
     * @param int $itemid
     * @param array $gradinginfo result of function grade_get_grades
     * @param string $defaultcontent default string to be returned if no active grading is found
     * @param boolean $cangrade whether current user has capability to grade in this context
     * @return string
     */
    public function render_grade($page, $itemid, $gradinginfo, $defaultcontent, $cangrade) {
        return $this->get_renderer($page)->display_instances($this->get_active_instances($itemid), $defaultcontent, $cangrade);
    }

    // ///// full-text search support /////////////////////////////////////////////

    /**
     * Prepare the part of the search query to append to the FROM statement
     *
     * @param string $gdid the alias of grading_definitions.id column used by the caller
     * @return string
     */
    public static function sql_search_from_tables($gdid) {
        return " LEFT JOIN {gradingform_matrix_criteria} rc ON (rc.definitionid = $gdid)
                 LEFT JOIN {gradingform_matrix_levels} rl ON (rl.criterionid = rc.id)";
    }

    /**
     * Prepare the parts of the SQL WHERE statement to search for the given token
     *
     * The returned array cosists of the list of SQL comparions and the list of
     * respective parameters for the comparisons. The returned chunks will be joined
     * with other conditions using the OR operator.
     *
     * @param string $token token to search for
     * @return array
     */
    public static function sql_search_where($token) {
        global $DB;

        $subsql = [];
        $params = [];

        // search in matrix criteria description
        $subsql[] = $DB->sql_like('rc.description', '?', false, false);
        $params[] = '%' . $DB->sql_like_escape($token) . '%';

        // search in matrix levels definition
        $subsql[] = $DB->sql_like('rl.definition', '?', false, false);
        $params[] = '%' . $DB->sql_like_escape($token) . '%';

        return [$subsql, $params];
    }

    /**
     * Calculates and returns the possible minimum and maximum score (in points) for this matrix
     *
     * @return array
     */
    public function get_min_max_score() {
        if (!$this->is_form_available()) {
            return null;
        }
        return level_sum_calculator::score_bounds($this->get_definition()->matrix_criteria);
    }

    /**
     * @return array An array containing a single key/value pair with the 'matrix_criteria' external_multiple_structure.
     * @see gradingform_controller::get_external_definition_details()
     * @since Moodle 2.5
     */
    public static function get_external_definition_details() {
        $matrix_criteria = new external_multiple_structure(
            new external_single_structure(
                [
                   'id'   => new external_value(PARAM_INT, 'criterion id', VALUE_OPTIONAL),
                   'sortorder' => new external_value(PARAM_INT, 'sortorder', VALUE_OPTIONAL),
                   'description' => new external_value(PARAM_RAW, 'description', VALUE_OPTIONAL),
                   'descriptionformat' => new external_format_value('description', VALUE_OPTIONAL),
                   'levels' => new external_multiple_structure(
                       new external_single_structure(
                           [
                                        'id' => new external_value(PARAM_INT, 'level id', VALUE_OPTIONAL),
                                        'score' => new external_value(PARAM_FLOAT, 'score', VALUE_OPTIONAL),
                                        'definition' => new external_value(PARAM_RAW, 'definition', VALUE_OPTIONAL),
                                        'definitionformat' => new external_format_value('definition', VALUE_OPTIONAL),
                                       ]
                       ),
                       'levels',
                       VALUE_OPTIONAL
                   ),
                   ]
            ),
            'definition details',
            VALUE_OPTIONAL
        );
        return ['matrix_criteria' => $matrix_criteria];
    }

    /**
     * Returns an array that defines the structure of the matrix's filling. This function is used by
     * the web service function core_grading_external::get_gradingform_instances().
     *
     * @return An array containing a single key/value pair with the 'criteria' external_multiple_structure
     * @see gradingform_controller::get_external_instance_filling_details()
     * @since Moodle 2.6
     */
    public static function get_external_instance_filling_details() {
        $criteria = new external_multiple_structure(
            new external_single_structure(
                [
                    'id' => new external_value(PARAM_INT, 'filling id'),
                    'criterionid' => new external_value(PARAM_INT, 'criterion id'),
                    'levelid' => new external_value(PARAM_INT, 'level id', VALUE_OPTIONAL),
                    'remark' => new external_value(PARAM_RAW, 'remark', VALUE_OPTIONAL),
                    'remarkformat' => new external_format_value('remark', VALUE_OPTIONAL),
                ]
            ),
            'filling',
            VALUE_OPTIONAL
        );
        return  ['criteria' => $criteria];
    }
}

/**
 * Class to manage one matrix grading instance.
 *
 * Stores information and performs actions like update, copy, validate, submit, etc.
 *
 * @package    gradingform_matrix
 * @copyright  2011 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gradingform_matrix_instance extends gradingform_instance {
    /** @var array stores the matrix, has two keys: 'criteria' and 'options' */
    protected $matrix;

    /**
     * Deletes this (INCOMPLETE) instance from database.
     */
    public function cancel() {
        parent::cancel();
        $this->get_controller()->filling_repository()->delete($this->get_id());
    }

    /**
     * Duplicates the instance before editing (optionally substitutes raterid and/or itemid with
     * the specified values)
     *
     * @param int $raterid value for raterid in the duplicate
     * @param int $itemid value for itemid in the duplicate
     * @return int id of the new instance
     */
    public function copy($raterid, $itemid) {
        $instanceid = parent::copy($raterid, $itemid);
        $this->get_controller()->filling_repository()->copy($this->get_matrix_filling()['criteria'], $instanceid);
        return $instanceid;
    }

    /**
     * Determines whether the submitted form was empty.
     *
     * @param array $elementvalue value of element submitted from the form
     * @return boolean true if the form is empty
     */
    public function is_empty_form($elementvalue) {
        $controller = $this->get_controller();
        return $controller->filling_validator()->is_empty($controller->get_definition()->matrix_criteria, $elementvalue);
    }

    /**
     * Removes the attempt from the fillings table
     * @param array $data the attempt data
     */
    public function clear_attempt($data) {
        $this->get_controller()->filling_repository()->delete($this->get_id(), array_keys($data['criteria'] ?? []));
    }

    /**
     * Validates that matrix is fully completed and contains valid grade on each criterion
     *
     * @param array $elementvalue value of element as came in form submit
     * @return boolean true if the form data is validated and contains no errors
     */
    public function validate_grading_element($elementvalue) {
        $controller = $this->get_controller();
        return $controller->filling_validator()->is_complete($controller->get_definition()->matrix_criteria, $elementvalue);
    }

    /**
     * Retrieves from DB and returns the data how this matrix was filled
     *
     * @param boolean $force whether to force DB query even if the data is cached
     * @return array
     */
    public function get_matrix_filling($force = false) {
        if ($this->matrix === null || $force) {
            $this->matrix = ['criteria' => $this->get_controller()->filling_repository()->load($this->get_id())];
        }
        return $this->matrix;
    }

    /**
     * Updates the instance with the data received from grading form. This function may be
     * called via AJAX when grading is not yet completed, so it does not change the
     * status of the instance.
     *
     * @param array $data
     */
    public function update($data) {
        global $DB;
        $currentgrade = $this->get_matrix_filling();
        $controller = $this->get_controller();
        $submitted = [];
        $criteria = $controller->filling_validator()->filter($controller->get_definition()->matrix_criteria, $data);
        foreach ($criteria as $criterionid => $record) {
            // Hardcoding/defaulting to html format for new/existing record.
            $record['remarkformat'] = FORMAT_HTML;
            $submitted[$criterionid] = $record;
        }
        $transaction = $DB->start_delegated_transaction();
        parent::update($data);
        $controller->filling_repository()->save($this->get_id(), $currentgrade['criteria'], $submitted);
        $transaction->allow_commit();
        $this->get_matrix_filling(true);
    }

    /**
     * Calculates the grade to be pushed to the gradebook
     *
     * @return float|int the valid grade from $this->get_controller()->get_grade_range()
     */
    public function get_grade() {
        $controller = $this->get_controller();
        if (!$controller->is_form_available()) {
            return -1;
        }
        return $controller->grade_calculator()->calculate(
            $controller->get_definition()->matrix_criteria,
            $this->get_matrix_filling()['criteria'],
            array_keys($controller->get_grade_range()),
            $controller->get_options(),
            (bool)$controller->get_allow_grade_decimals()
        );
    }

    /**
     * Returns html for form element of type 'grading'.
     *
     * @param moodle_page $page
     * @param MoodleQuickForm_grading $gradingformelement
     * @return string
     */
    public function render_grading_element($page, $gradingformelement) {
        global $USER;
        if (!$gradingformelement->_flagFrozen) {
            $page->requires->js_call_amd('gradingform_matrix/matrix', 'init', [$gradingformelement->getName()]);
            $mode = gradingform_matrix_controller::DISPLAY_EVAL;
        } else {
            if ($gradingformelement->_persistantFreeze) {
                $mode = gradingform_matrix_controller::DISPLAY_EVAL_FROZEN;
            } else {
                $mode = gradingform_matrix_controller::DISPLAY_REVIEW;
            }
        }
        $criteria = $this->get_controller()->get_definition()->matrix_criteria;
        $options = $this->get_controller()->get_options();
        $value = $gradingformelement->getValue();
        $html = '';
        if ($value === null) {
            $value = $this->get_matrix_filling();
        } else if (!$this->validate_grading_element($value)) {
            $html .= html_writer::tag('div', get_string('formnotcompleted', 'gradingform_matrix'), ['class' => 'gradingform_matrix-error']);
        }
        $currentinstance = $this->get_current_instance();
        if ($currentinstance && $currentinstance->get_status() == gradingform_instance::INSTANCE_STATUS_NEEDUPDATE) {
            $html .= html_writer::div(
                get_string('needregrademessage', 'gradingform_matrix'),
                'gradingform_matrix-regrade',
                ['role' => 'alert']
            );
        }
        $haschanges = false;
        if ($currentinstance) {
            $curfilling = $currentinstance->get_matrix_filling();
            foreach ($curfilling['criteria'] as $criterionid => $curvalues) {
                $value['criteria'][$criterionid]['savedlevelid'] = $curvalues['levelid'];
                $newremark = null;
                $newlevelid = null;
                if (isset($value['criteria'][$criterionid]['remark'])) {
                    $newremark = $value['criteria'][$criterionid]['remark'];
                }
                if (isset($value['criteria'][$criterionid]['levelid'])) {
                    $newlevelid = $value['criteria'][$criterionid]['levelid'];
                }
                if ($newlevelid != $curvalues['levelid'] || $newremark != $curvalues['remark']) {
                    $haschanges = true;
                }
            }
        }
        if ($this->get_data('isrestored') && $haschanges) {
            $html .= html_writer::tag('div', get_string('restoredfromdraft', 'gradingform_matrix'), ['class' => 'gradingform_matrix-restored']);
        }
        if (!empty($options['showdescriptionteacher'])) {
            $html .= html_writer::tag('div', $this->get_controller()->get_formatted_description(), ['class' => 'gradingform_matrix-description']);
        }
        $html .= $this->get_controller()->get_renderer($page)->display_matrix($criteria, $options, $mode, $gradingformelement->getName(), $value);
        return $html;
    }
}
