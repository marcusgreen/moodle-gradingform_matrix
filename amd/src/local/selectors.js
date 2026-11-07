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
 * Selectors and element ids used by the matrix grading form and editor.
 *
 * The ids match the ones built in renderer.php, where {NAME} is the form element name.
 *
 * @module     gradingform_matrix/local/selectors
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

export const SELECTORS = {
    CRITERION: '.criterion',
    LEVEL: '.level',
    DESCRIPTION: '.description',
    EDITABLE_CELL: '.level, .description',
    SCORE_INPUT: '.score input[type=text]',
    SORTORDER_INPUT: 'input[type=hidden]',
    RADIO_WRAPPER: '.radio',
    RADIO_INPUT: 'input[type=radio]',
    REMARK_TEXTAREA: '.remark textarea',
    FITEM: '.fitem',
    POPOUT_CONTENT: '.felement',
};

export const IDS = {
    root: (name) => `matrix-${name}`,
    criterionTemplate: (name) => `matrix-${name}-criterion-template`,
    levelTemplate: (name) => `matrix-${name}-level-template`,
    criteria: (name) => `${name}-criteria`,
    criterion: (name, criterionid) => `${name}-criteria-${criterionid}`,
    descriptionCell: (name, criterionid) => `${name}-criteria-${criterionid}-description-cell`,
    levels: (name, criterionid) => `${name}-criteria-${criterionid}-levels`,
    level: (name, criterionid, levelid) => `${name}-criteria-${criterionid}-levels-${levelid}`,
};
