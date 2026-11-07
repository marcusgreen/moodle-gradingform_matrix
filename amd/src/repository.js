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
