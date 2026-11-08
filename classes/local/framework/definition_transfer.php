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

namespace gradingform_matrix\local\framework;

use coding_exception;
use context_user;
use gradingform_controller;
use gradingform_instance;
use moodle_exception;
use stdClass;

/**
 * Exports a controller's definition, and replaces it with an imported one.
 *
 * Works for any level-based method whose definition holds its criteria in {method}_criteria and whose
 * update_definition() takes them in ->{method}['criteria'], as the matrix does.
 *
 * @package    gradingform_matrix
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class definition_transfer {
    /**
     * Constructor.
     *
     * @param gradingform_controller $controller the controller whose definition is exported or replaced
     * @param definition_exporter $exporter writes the JSON file
     * @param string $method name of the grading method, which names the criteria in the definition
     * @param string $component frankenstyle name of the method, for error strings
     */
    public function __construct(
        /** @var gradingform_controller the controller whose definition is exported or replaced */
        protected readonly gradingform_controller $controller,
        /** @var definition_exporter writes the JSON file */
        protected readonly definition_exporter $exporter,
        /** @var string name of the grading method, which names the criteria in the definition */
        protected readonly string $method,
        /** @var string frankenstyle name of the method, for error strings */
        protected readonly string $component,
    ) {
    }

    /**
     * Exports the current definition.
     *
     * @return string JSON
     */
    public function export(): string {
        $definition = $this->controller->get_definition();
        if (!$definition) {
            throw new coding_exception('There is no definition to export.');
        }
        $files = [];
        $areafiles = get_file_storage()->get_area_files(
            $this->controller->get_context()->id,
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
        return $this->exporter->to_json(
            $definition->name,
            (string)$definition->description,
            (int)$definition->descriptionformat,
            $this->controller->get_options(),
            $definition->{$this->method . '_criteria'},
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
        $definition = $this->controller->get_definition();
        if (empty($definition->id)) {
            return true;
        }
        [$insql, $params] = $DB->get_in_or_equal(
            [gradingform_instance::INSTANCE_STATUS_ACTIVE, gradingform_instance::INSTANCE_STATUS_NEEDUPDATE],
            SQL_PARAMS_NAMED
        );
        $params['definitionid'] = $definition->id;
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
    public function import(array $data, string $fallbackname, $usermodified = null): void {
        if (!$this->can_import()) {
            throw new moodle_exception('importerrorused', $this->component);
        }
        $current = $this->controller->get_definition();

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
        $new->status = gradingform_controller::DEFINITION_STATUS_DRAFT;
        $criteria = [];
        $newlevid = 1;
        foreach (array_values($data['criteria']) as $index => $criterion) {
            $levels = [];
            foreach ($criterion['levels'] as $level) {
                $levels['NEWID' . $newlevid++] = ['score' => $level['score'], 'definition' => $level['definition']];
            }
            $criteria['NEWID' . ($index + 1)] = [
                'sortorder' => $index + 1,
                'description' => $criterion['description'],
                'levels' => $levels,
            ];
        }
        $new->{$this->method} = [
            'options' => $data['options'] ?? $this->controller->get_options(),
            'criteria' => $criteria,
        ];

        $this->controller->update_definition($new, $usermodified);
    }

    /**
     * Puts files into a new draft area of the current user.
     *
     * @param array $files list of ['filepath', 'filename', 'content']
     * @return int draft item id
     */
    protected static function create_draft_area(array $files): int {
        global $CFG, $USER;
        require_once($CFG->libdir . '/filelib.php');
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
}
