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
 * Web service calls for gradingform_matrix.
 *
 * @module     gradingform_matrix/repository
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {call as fetchMany} from 'core/ajax';

/**
 * Fetch the grade currently assigned to a user.
 *
 * @param {String} component
 * @param {Number} contextid
 * @param {String} itemname
 * @param {Number} gradeduserid
 * @returns {Promise}
 */
export const fetchGrade = (component, contextid, itemname, gradeduserid) => fetchMany([{
    methodname: 'gradingform_matrix_grader_gradingpanel_fetch',
    args: {
        component,
        contextid,
        itemname,
        gradeduserid,
    },
}])[0];

/**
 * Store a grade from serialised grading form data.
 *
 * @param {String} component
 * @param {Number} contextid
 * @param {String} itemname
 * @param {Number} gradeduserid
 * @param {Boolean} notifyuser
 * @param {String} formdata The grading form, URL-encoded
 * @returns {Promise}
 */
export const storeGrade = (component, contextid, itemname, gradeduserid, notifyuser, formdata) => fetchMany([{
    methodname: 'gradingform_matrix_grader_gradingpanel_store',
    args: {
        component,
        contextid,
        itemname,
        gradeduserid,
        notifyuser,
        formdata,
    },
}])[0];

/**
 * List the course material the AI assistant can read, and whether it can be used.
 *
 * @param {Number} areaid
 * @returns {Promise}
 */
export const aiSources = (areaid) => fetchMany([{
    methodname: 'gradingform_matrix_ai_sources',
    args: {areaid},
}])[0];

/**
 * Run one turn of the AI assistant's conversation.
 *
 * @param {Number} areaid
 * @param {String} instruction the teacher's new instruction
 * @param {Number[]} cmids course modules to read
 * @param {Object[]} history earlier turns, oldest first, each {instruction, reply}
 * @param {String} proposal the current proposal as JSON, or empty
 * @returns {Promise}
 */
export const aiTurn = (areaid, instruction, cmids, history, proposal) => fetchMany([{
    methodname: 'gradingform_matrix_ai_turn',
    args: {areaid, instruction, cmids, history, proposal},
}])[0];

/**
 * Replace the grading form with a proposal, saved as a draft.
 *
 * @param {Number} areaid
 * @param {String} proposal the proposal as JSON
 * @returns {Promise}
 */
export const aiApply = (areaid, proposal) => fetchMany([{
    methodname: 'gradingform_matrix_ai_apply',
    args: {areaid, proposal},
}])[0];

/**
 * Render criterion and level texts as Markdown.
 *
 * @param {Number} contextid context of the grading form
 * @param {String[]} texts
 * @returns {Promise} HTML for each text, in the same order
 */
export const renderTexts = (contextid, texts) => fetchMany([{
    methodname: 'gradingform_matrix_render_text',
    args: {contextid, texts},
}])[0];
