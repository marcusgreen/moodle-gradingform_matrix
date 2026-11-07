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
 * Grading panel for gradingform_matrix.
 *
 * @module     gradingform_matrix/grades/grader/gradingpanel
 * @copyright  2019 Mathew May <mathew.solutions>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {fetchGrade, storeGrade} from 'gradingform_matrix/repository';
import {normaliseResult} from 'core_grades/grades/grader/gradingpanel/normalise';
import {compareData} from 'core_grades/grades/grader/gradingpanel/comparison';

/**
 * For a given component, contextid, itemname & gradeduserid we can fetch the currently assigned grade.
 *
 * @param {String} component
 * @param {Number} contextid
 * @param {String} itemname
 * @param {Number} gradeduserid
 *
 * @returns {Promise}
 */
export const fetchCurrentGrade = (component, contextid, itemname, gradeduserid) => {
    return fetchGrade(component, contextid, itemname, gradeduserid);
};

/**
 * For a given component, contextid, itemname & gradeduserid we can store the currently assigned grade in a given form.
 *
 * @param {String} component
 * @param {Number} contextid
 * @param {String} itemname
 * @param {Number} gradeduserid
 * @param {Boolean} notifyUser
 * @param {HTMLElement} rootNode
 *
 * @returns {Promise}
 */
export const storeCurrentGrade = async(component, contextid, itemname, gradeduserid, notifyUser, rootNode) => {
    const form = rootNode.querySelector('form');

    if (compareData(form) === true) {
        const formdata = new URLSearchParams(new FormData(form)).toString();
        return normaliseResult(await storeGrade(component, contextid, itemname, gradeduserid, notifyUser, formdata));
    } else {
        return '';
    }
};
