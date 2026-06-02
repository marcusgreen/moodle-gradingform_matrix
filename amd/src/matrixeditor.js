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
 * Matrix editor behaviour.
 *
 * Client-side handling of the matrix definition editor: switching criterion
 * descriptions and level definitions in and out of edit mode, and processing
 * the add/delete/move/duplicate buttons so that criteria and levels can be
 * manipulated without a server round trip.
 *
 * Ported from the legacy YUI module M.gradingform_matrixeditor.
 *
 * @module     gradingform_matrix/matrixeditor
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {get_strings as getStrings} from 'core/str';
import Notification from 'core/notification';
import Pending from 'core/pending';

/**
 * Escape a string for safe insertion as text/HTML content.
 *
 * @param {String} text
 * @return {String}
 */
const escapeHTML = (text) => {
    const div = document.createElement('div');
    div.textContent = text === null || text === undefined ? '' : text;
    return div.innerHTML;
};

/**
 * A single matrix editor instance, scoped to one form element.
 */
class MatrixEditor {
    /**
     * @param {String} name The form element name (and id prefix).
     * @param {String} criterionTemplate HTML template for a criterion row.
     * @param {String} levelTemplate HTML template for a level cell.
     * @param {Object} strings Resolved language strings (criterionempty, levelempty).
     */
    constructor(name, criterionTemplate, levelTemplate, strings) {
        this.name = name;
        this.templates = {criterion: criterionTemplate, level: levelTemplate};
        this.strings = strings;
        this.root = document.getElementById(`matrix-${name}`);
        if (!this.root) {
            return;
        }

        this.disableAllEditors();

        // A single delegated click handler covers both submit buttons (add/delete/...)
        // and clicks on criterion/level cells (switch to edit mode). Closing the open
        // editor when clicking elsewhere requires a document-level listener.
        document.addEventListener('click', (e) => this.onClick(e));
    }

    /**
     * Top-level click dispatcher.
     *
     * @param {Event} e
     */
    onClick(e) {
        const target = e.target;
        if (target.matches('input[type=submit]')) {
            if (this.root.contains(target)) {
                this.buttonClick(e);
            }
            return;
        }
        this.clickAnywhere(e);
    }

    /**
     * Switch all level/description cells in this editor back to plain (non-edit) mode.
     */
    disableAllEditors() {
        this.root.querySelectorAll('.level, .description').forEach((node) => {
            this.editMode(node, false);
        });
    }

    /**
     * Handle a click anywhere on the page: switch the clicked cell into edit mode, or
     * close any open editor when clicking outside an editable cell.
     *
     * @param {Event} e
     */
    clickAnywhere(e) {
        let el = e.target;
        let focusScore = false;
        while (el && el !== document.body && !(el.classList
                && (el.classList.contains('level') || el.classList.contains('description')))) {
            if (el.classList && el.classList.contains('score')) {
                focusScore = true;
            }
            el = el.parentNode;
        }
        if (el && el.classList && (el.classList.contains('level') || el.classList.contains('description'))
                && this.root.contains(el)) {
            const ta = el.querySelector('textarea');
            if (ta && ta.classList.contains('hiddenelement')) {
                this.disableAllEditors();
                this.editMode(el, true, focusScore);
            }
            return;
        }
        this.disableAllEditors();
    }

    /**
     * Switch a criterion description or level cell to edit mode or back to plain mode.
     *
     * @param {HTMLElement} el The .level or .description cell.
     * @param {Boolean} editmode True to show inputs, false to show plain values.
     * @param {Boolean} [focusScore] When entering edit mode, focus the score input.
     */
    editMode(el, editmode, focusScore) {
        const ta = el.querySelector('textarea');
        if (!ta) {
            return;
        }
        if (!editmode && ta.classList.contains('hiddenelement')) {
            return;
        }
        if (editmode && !ta.classList.contains('hiddenelement')) {
            return;
        }

        const pseudotablink = '<span class="pseudotablink" tabindex="0"></span>';
        let taplain = ta.parentNode.querySelector('.plainvalue');
        let tbplain = null;
        const tb = el.querySelector('.score input[type=text]');

        // Lazily create the .plainvalue holders next to the textarea (and score input).
        if (!taplain) {
            ta.parentNode.insertAdjacentHTML('beforeend',
                `<div class="plainvalue">${pseudotablink}<span class="textvalue">&nbsp;</span></div>`);
            taplain = ta.parentNode.querySelector('.plainvalue');
            taplain.querySelector('.pseudotablink').addEventListener('focus', (e) => this.clickAnywhere(e));
            if (tb) {
                tb.parentNode.insertAdjacentHTML('beforeend',
                    `<span class="plainvalue">${pseudotablink}<span class="textvalue">&nbsp;</span></span>`);
                tbplain = tb.parentNode.querySelector('.plainvalue');
                tbplain.querySelector('.pseudotablink').addEventListener('focus', (e) => this.clickAnywhere(e));
            }
        }
        if (tb && !tbplain) {
            tbplain = tb.parentNode.querySelector('.plainvalue');
        }

        if (!editmode) {
            // Copy the input contents into the plain holders. Show placeholder text when empty.
            let value = ta.value;
            if (value.length) {
                taplain.classList.remove('empty');
            } else {
                value = el.classList.contains('level') ? this.strings.levelempty : this.strings.criterionempty;
                taplain.classList.add('empty');
            }
            taplain.querySelector('.textvalue').innerHTML = escapeHTML(value);
            if (tb) {
                tbplain.querySelector('.textvalue').innerHTML = escapeHTML(tb.value);
            }
            taplain.classList.remove('hiddenelement');
            ta.classList.add('hiddenelement');
            if (tb) {
                tbplain.classList.remove('hiddenelement');
                tb.classList.add('hiddenelement');
            }
        } else {
            // Size the textarea so it fills the cell.
            try {
                const width = parseFloat(getComputedStyle(ta.parentNode).width);
                let height;
                if (el.classList.contains('level')) {
                    height = parseFloat(getComputedStyle(el).height)
                        - parseFloat(getComputedStyle(el.querySelector('.score')).height);
                } else {
                    height = parseFloat(getComputedStyle(ta.parentNode).height);
                }
                ta.style.width = `${Math.max(width - 16, 50)}px`;
                ta.style.height = `${Math.max(height, 20)}px`;
            } catch (err) {
                // Leave the default size of the textbox.
            }
            taplain.classList.add('hiddenelement');
            ta.classList.remove('hiddenelement');
            if (tb) {
                tbplain.classList.add('hiddenelement');
                tb.classList.remove('hiddenelement');
            }
        }

        if (editmode) {
            if (tb && focusScore) {
                tb.focus();
            } else {
                ta.focus();
            }
        }
    }

    /**
     * Handle a click on one of the editor submit buttons: add/delete/move/duplicate
     * criteria and levels on the client side.
     *
     * @param {Event} e
     * @param {Boolean} [confirmed] Set after the user confirms a delete.
     */
    buttonClick(e, confirmed) {
        const name = this.name;
        const target = e.target;
        if (target.type !== 'submit') {
            return;
        }
        this.disableAllEditors();

        const chunks = target.id.split('-');
        const action = chunks[chunks.length - 1];
        if (chunks[0] !== name || chunks[1] !== 'criteria') {
            return;
        }

        let elementsStr;
        if (chunks.length > 4 || action === 'addlevel') {
            elementsStr = `#${name}-criteria-${chunks[2]}-levels .level`;
        } else {
            elementsStr = `#matrix-${name} .criterion`;
        }

        // Prepare the ids of the next inserted level/criterion.
        let newlevid = 0;
        let newid = 0;
        if (action === 'addcriterion' || action === 'addlevel' || action === 'duplicate') {
            newid = this.calculateNewId(`#matrix-${name} .criterion`);
            newlevid = this.calculateNewId(`#matrix-${name} .level`);
        }

        if (chunks.length === 3 && action === 'addcriterion') {
            this.addCriterion(newid, newlevid, elementsStr);
        } else if (chunks.length === 5 && action === 'addlevel') {
            this.addLevel(chunks[2], newlevid, elementsStr);
        } else if (chunks.length === 4 && action === 'moveup') {
            const el = document.getElementById(`${name}-criteria-${chunks[2]}`);
            if (el && el.previousElementSibling) {
                el.parentNode.insertBefore(el, el.previousElementSibling);
            }
            this.assignClasses(elementsStr);
        } else if (chunks.length === 4 && action === 'movedown') {
            const el = document.getElementById(`${name}-criteria-${chunks[2]}`);
            if (el && el.nextElementSibling) {
                el.parentNode.insertBefore(el.nextElementSibling, el);
            }
            this.assignClasses(elementsStr);
        } else if (chunks.length === 4 && action === 'delete') {
            if (confirmed) {
                const el = document.getElementById(`${name}-criteria-${chunks[2]}`);
                if (el) {
                    el.remove();
                }
                this.assignClasses(elementsStr);
            } else {
                this.confirmDelete(e, 'confirmdeletecriterion', 'gradingform_matrixeditor:deleteConfirmation');
                return;
            }
        } else if (chunks.length === 4 && action === 'duplicate') {
            this.duplicateCriterion(chunks[2], newid, newlevid, elementsStr);
        } else if (chunks.length === 6 && action === 'delete') {
            if (confirmed) {
                const el = document.getElementById(`${name}-criteria-${chunks[2]}-${chunks[3]}-${chunks[4]}`);
                if (el) {
                    el.remove();
                }
                this.assignClasses(elementsStr);
            } else {
                this.confirmDelete(e, 'confirmdeletelevel', 'gradingform_matrixeditor:deleteLevelConfirmation');
                return;
            }
        } else {
            // Unknown action.
            return;
        }
        e.preventDefault();
    }

    /**
     * Display a save/cancel confirmation, then re-run the button click as confirmed.
     *
     * @param {Event} e
     * @param {String} messagekey Language string key for the confirmation body.
     * @param {String} pendingkey Pending-promise key.
     */
    confirmDelete(e, messagekey, pendingkey) {
        const pendingPromise = new Pending(pendingkey);
        getStrings([
            {key: 'confirmation', component: 'admin'},
            {key: messagekey, component: 'gradingform_matrix'},
            {key: 'yes', component: 'moodle'},
        ]).then(([title, question, yes]) => {
            return Notification.saveCancelPromise(title, question, yes).then(() => {
                this.buttonClick(e, true);
                return;
            }).catch(() => {
                // User cancelled.
            });
        }).then(() => pendingPromise.resolve()).catch(Notification.exception);
    }

    /**
     * Add a new criterion, copying the level count/scores from the last criterion.
     *
     * @param {Number} newid
     * @param {Number} newlevid
     * @param {String} elementsStr
     */
    addCriterion(newid, newlevid, elementsStr) {
        const name = this.name;
        const levelsscores = [0];
        let levidx = 1;
        let parentel = document.getElementById(`${name}-criteria`);
        const tbody = parentel.querySelector(':scope > tbody');
        if (tbody) {
            parentel = tbody;
        }
        const criteria = parentel.querySelectorAll('.criterion');
        if (criteria.length) {
            const lastLevels = criteria[criteria.length - 1].querySelectorAll('.level');
            for (levidx = 0; levidx < lastLevels.length; levidx++) {
                levelsscores[levidx] = lastLevels[levidx].querySelector('.score input[type=text]').value;
            }
        }
        for (levidx; levidx < 3; levidx++) {
            levelsscores[levidx] = parseFloat(levelsscores[levidx - 1]) + 1;
        }

        let levelsstr = '';
        for (levidx = 0; levidx < levelsscores.length; levidx++) {
            levelsstr += this.templates.level
                .replace(/\{LEVEL-id\}/g, `NEWID${newlevid + levidx}`)
                .replace(/\{LEVEL-score\}/g, levelsscores[levidx])
                .replace(/\{LEVEL-index\}/g, levidx + 1);
        }
        const newcriterion = this.templates.criterion.replace(/\{LEVELS\}/, levelsstr);
        parentel.insertAdjacentHTML('beforeend',
            newcriterion.replace(/\{CRITERION-id\}/g, `NEWID${newid}`).replace(/\{.+?\}/g, ''));

        this.assignClasses(`#matrix-${name} #${name}-criteria-NEWID${newid}-levels .level`);
        this.disableAllEditors();
        this.assignClasses(elementsStr);
        this.editMode(document.getElementById(`${name}-criteria-NEWID${newid}-description-cell`), true);
    }

    /**
     * Add a new level to an existing criterion.
     *
     * @param {String} criterionid
     * @param {Number} newlevid
     * @param {String} elementsStr
     */
    addLevel(criterionid, newlevid, elementsStr) {
        const name = this.name;
        const parent = document.getElementById(`${name}-criteria-${criterionid}-levels`);
        let newscore = 0;
        let levelIndex = 1;
        parent.querySelectorAll('.level').forEach((node) => {
            newscore = Math.max(newscore, parseFloat(node.querySelector('.score input[type=text]').value) + 1);
            levelIndex++;
        });
        const newlevel = this.templates.level
            .replace(/\{CRITERION-id\}/g, criterionid)
            .replace(/\{LEVEL-id\}/g, `NEWID${newlevid}`)
            .replace(/\{LEVEL-score\}/g, newscore)
            .replace(/\{LEVEL-index\}/g, levelIndex)
            .replace(/\{.+?\}/g, '');
        parent.insertAdjacentHTML('beforeend', newlevel);

        this.disableAllEditors();
        this.assignClasses(elementsStr);
        const levels = parent.querySelectorAll('.level');
        this.editMode(levels[levels.length - 1], true);
    }

    /**
     * Duplicate an existing criterion, copying descriptions, definitions and scores.
     *
     * @param {String} criterionid
     * @param {Number} newid
     * @param {Number} newlevid
     * @param {String} elementsStr
     */
    duplicateCriterion(criterionid, newid, newlevid, elementsStr) {
        const name = this.name;
        const levelsdef = [];
        const levelsscores = [0];
        let levidx = 0;
        let parentel = document.getElementById(`${name}-criteria`);
        const tbody = parentel.querySelector(':scope > tbody');
        if (tbody) {
            parentel = tbody;
        }

        const source = document.getElementById(`${name}-criteria-${criterionid}`);
        const sourceLevels = source.querySelectorAll('.level');
        for (levidx = 0; levidx < sourceLevels.length; levidx++) {
            levelsdef[levidx] = sourceLevels[levidx].querySelector('.definition .textvalue').innerHTML;
        }
        for (levidx = 0; levidx < sourceLevels.length; levidx++) {
            levelsscores[levidx] = sourceLevels[levidx].querySelector('.score input[type=text]').value;
        }
        for (levidx; levidx < 3; levidx++) {
            levelsscores[levidx] = parseFloat(levelsscores[levidx - 1]) + 1;
        }

        let levelsstr = '';
        for (levidx = 0; levidx < levelsscores.length; levidx++) {
            levelsstr += this.templates.level
                .replace(/\{LEVEL-id\}/g, `NEWID${newlevid + levidx}`)
                .replace(/\{LEVEL-score\}/g, levelsscores[levidx])
                .replace(/\{LEVEL-definition\}/g, levelsdef[levidx]);
        }
        const description = source.querySelector('.description .textvalue');
        const newcriterion = this.templates.criterion
            .replace(/\{LEVELS\}/, levelsstr)
            .replace(/\{CRITERION-description\}/, description.innerHTML);
        parentel.insertAdjacentHTML('beforeend',
            newcriterion.replace(/\{CRITERION-id\}/g, `NEWID${newid}`).replace(/\{.+?\}/g, ''));

        this.assignClasses(`#matrix-${name} #${name}-criteria-NEWID${newid}-levels .level`);
        this.disableAllEditors();
        this.assignClasses(elementsStr);
        this.editMode(document.getElementById(`${name}-criteria-NEWID${newid}-description-cell`), true);
    }

    /**
     * Reassign first/last/odd/even classes, level widths and criterion sort orders.
     *
     * @param {String} elementsStr CSS selector for the elements to renumber.
     */
    assignClasses(elementsStr) {
        const elements = document.querySelectorAll(elementsStr);
        elements.forEach((el, i) => {
            el.classList.remove('first', 'last', 'even', 'odd');
            el.classList.add(i % 2 ? 'odd' : 'even');
            if (i === 0) {
                el.classList.add('first');
            }
            if (i === elements.length - 1) {
                el.classList.add('last');
            }
            el.querySelectorAll('input[type=hidden]').forEach((node) => {
                if (/sortorder/.test(node.name)) {
                    node.value = i;
                }
            });
            if (el.classList.contains('level')) {
                el.style.width = `${Math.round(100 / elements.length)}%`;
            }
        });
    }

    /**
     * Return a unique NEWID number not used by any element matching the selector.
     *
     * @param {String} elementsStr
     * @return {Number}
     */
    calculateNewId(elementsStr) {
        let newid = 1;
        document.querySelectorAll(elementsStr).forEach((node) => {
            const id = node.id.split('-').pop();
            const matches = id.match(/^NEWID(\d+)$/);
            if (matches) {
                newid = Math.max(newid, parseInt(matches[1], 10) + 1);
            }
        });
        return newid;
    }
}

/**
 * Initialise a matrix editor.
 *
 * The criterion/level HTML templates are read from hidden <script> holders rendered
 * alongside the editor (they are too long to pass as JS call arguments).
 *
 * @param {String} name The form element name.
 * @return {Promise}
 */
export const init = (name) => {
    const criterionHolder = document.getElementById(`matrix-${name}-criterion-template`);
    const levelHolder = document.getElementById(`matrix-${name}-level-template`);
    const criterionTemplate = criterionHolder ? criterionHolder.textContent : '';
    const levelTemplate = levelHolder ? levelHolder.textContent : '';

    return getStrings([
        {key: 'criterionempty', component: 'gradingform_matrix'},
        {key: 'levelempty', component: 'gradingform_matrix'},
    ]).then(([criterionempty, levelempty]) => {
        new MatrixEditor(name, criterionTemplate, levelTemplate, {criterionempty, levelempty});
        return;
    }).catch(Notification.exception);
};
