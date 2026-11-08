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
import {SELECTORS, IDS} from 'gradingform_matrix/local/selectors';
import {renderTexts} from 'gradingform_matrix/repository';

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
 * Show or hide an element with the hiddenelement class.
 *
 * @param {HTMLElement} el
 * @param {Boolean} hidden
 */
const setHidden = (el, hidden) => el.classList.toggle('hiddenelement', hidden);

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
        /** @type {Map<HTMLElement, String>} cells waiting to be shown as formatted text, with their text */
        this.toRender = new Map();
        this.renderTimer = null;
        this.root = document.getElementById(IDS.root(name));
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
        this.root.querySelectorAll(SELECTORS.EDITABLE_CELL).forEach((node) => {
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
        // The textarea is hidden in plain mode, so there is nothing to do when it is already in the requested mode.
        if (!ta || editmode !== ta.classList.contains('hiddenelement')) {
            return;
        }
        const tb = el.querySelector(SELECTORS.SCORE_INPUT);
        const taplain = this.plainHolder(ta, 'div');
        const tbplain = tb ? this.plainHolder(tb, 'span') : null;

        if (editmode) {
            this.sizeTextarea(el, ta);
        } else {
            this.showPlainValues(el, ta, taplain, tb, tbplain);
        }
        setHidden(taplain, editmode);
        setHidden(ta, !editmode);
        if (tb) {
            setHidden(tbplain, editmode);
            setHidden(tb, !editmode);
        }
        if (editmode) {
            (tb && focusScore ? tb : ta).focus();
        }
    }

    /**
     * Returns the element showing an input's value in plain mode, creating it the first time.
     *
     * @param {HTMLElement} input the textarea or score input
     * @param {String} tag element to create: div for the text, span for the score
     * @returns {HTMLElement}
     */
    plainHolder(input, tag) {
        let plain = input.parentNode.querySelector('.plainvalue');
        if (!plain) {
            input.parentNode.insertAdjacentHTML('beforeend', `<${tag} class="plainvalue">` +
                `<span class="pseudotablink" tabindex="0"></span><span class="textvalue">&nbsp;</span></${tag}>`);
            plain = input.parentNode.querySelector('.plainvalue');
            plain.querySelector('.pseudotablink').addEventListener('focus', (e) => this.clickAnywhere(e));
        }
        return plain;
    }

    /**
     * Copies the inputs' contents into their plain holders, with placeholder text when the text is empty.
     *
     * @param {HTMLElement} el The .level or .description cell.
     * @param {HTMLElement} ta the cell's textarea
     * @param {HTMLElement} taplain its plain holder
     * @param {HTMLElement|null} tb the cell's score input, if it has one
     * @param {HTMLElement|null} tbplain its plain holder
     */
    showPlainValues(el, ta, taplain, tb, tbplain) {
        const empty = !ta.value.length;
        let value = ta.value;
        if (empty) {
            value = el.classList.contains('level') ? this.strings.levelempty : this.strings.criterionempty;
        }
        taplain.classList.toggle('empty', empty);
        taplain.querySelector('.textvalue').innerHTML = escapeHTML(value);
        if (!empty) {
            this.queueRender(taplain.querySelector('.textvalue'), value);
        }
        if (tb) {
            tbplain.querySelector('.textvalue').innerHTML = escapeHTML(tb.value);
        }
    }

    /**
     * Sizes a textarea so it fills its cell.
     *
     * @param {HTMLElement} el The .level or .description cell.
     * @param {HTMLElement} ta the cell's textarea
     */
    sizeTextarea(el, ta) {
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
    }

    /**
     * Handle a click on one of the editor submit buttons: add/delete/move/duplicate
     * criteria and levels on the client side.
     *
     * @param {Event} e
     * @param {Boolean} [confirmed] Set after the user confirms a delete.
     */
    buttonClick(e, confirmed) {
        const target = e.target;
        if (target.type !== 'submit') {
            return;
        }
        this.disableAllEditors();

        // Button ids are {name}-criteria-{criterionid}[-levels-{levelid}]-{action}.
        const chunks = target.id.split('-');
        if (chunks[0] !== this.name || chunks[1] !== 'criteria') {
            return;
        }
        const button = this.buttonActions(chunks)[`${chunks.length}-${chunks[chunks.length - 1]}`];
        if (!button) {
            return;
        }
        // Stop the form submitting, including while a delete waits for confirmation.
        e.preventDefault();
        if (button.confirm && !confirmed) {
            this.confirmDelete(e, ...button.confirm);
            return;
        }
        button.run();
    }

    /**
     * The actions of the editor buttons, keyed by the number of parts in the button id and the action.
     *
     * @param {String[]} chunks the parts of the button id
     * @returns {Object} for each action, run() and, for deletes, confirm: [message string key, pending key]
     */
    buttonActions(chunks) {
        const name = this.name;
        const criterionid = chunks[2];
        const criteria = `#${IDS.root(name)} ${SELECTORS.CRITERION}`;
        const levels = `#${IDS.levels(name, criterionid)} ${SELECTORS.LEVEL}`;
        const criterion = () => document.getElementById(IDS.criterion(name, criterionid));
        return {
            '3-addcriterion': {run: () => this.addCriterion(...this.newIds(), criteria)},
            '5-addlevel': {run: () => this.addLevel(criterionid, this.newIds()[1], levels)},
            '4-duplicate': {run: () => this.duplicateCriterion(criterionid, ...this.newIds(), criteria)},
            '4-moveup': {
                run: () => {
                    const el = criterion();
                    if (el && el.previousElementSibling) {
                        el.parentNode.insertBefore(el, el.previousElementSibling);
                    }
                    this.assignClasses(criteria);
                },
            },
            '4-movedown': {
                run: () => {
                    const el = criterion();
                    if (el && el.nextElementSibling) {
                        el.parentNode.insertBefore(el.nextElementSibling, el);
                    }
                    this.assignClasses(criteria);
                },
            },
            '4-delete': {
                confirm: ['confirmdeletecriterion', 'gradingform_matrixeditor:deleteConfirmation'],
                run: () => {
                    criterion()?.remove();
                    this.assignClasses(criteria);
                },
            },
            '6-delete': {
                confirm: ['confirmdeletelevel', 'gradingform_matrixeditor:deleteLevelConfirmation'],
                run: () => {
                    document.getElementById(IDS.level(name, criterionid, chunks[4]))?.remove();
                    this.assignClasses(levels);
                },
            },
        };
    }

    /**
     * The ids for the next criterion and level to be added.
     *
     * @returns {Number[]} [criterion id, level id]
     */
    newIds() {
        return [
            this.calculateNewId(`#${IDS.root(this.name)} ${SELECTORS.CRITERION}`),
            this.calculateNewId(`#${IDS.root(this.name)} ${SELECTORS.LEVEL}`),
        ];
    }

    /**
     * Display a save/cancel confirmation, then re-run the button click as confirmed.
     *
     * @param {Event} e
     * @param {String} messagekey Language string key for the confirmation body.
     * @param {String} pendingkey Pending-promise key.
     */
    async confirmDelete(e, messagekey, pendingkey) {
        const pendingPromise = new Pending(pendingkey);
        try {
            const [title, question, yes] = await getStrings([
                {key: 'confirmation', component: 'admin'},
                {key: messagekey, component: 'gradingform_matrix'},
                {key: 'yes', component: 'moodle'},
            ]);
            let confirmed = true;
            await Notification.saveCancelPromise(title, question, yes).catch(() => {
                confirmed = false;
            });
            if (confirmed) {
                this.buttonClick(e, true);
            }
        } catch (error) {
            Notification.exception(error);
        } finally {
            pendingPromise.resolve();
        }
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
        let parentel = document.getElementById(IDS.criteria(name));
        const tbody = parentel.querySelector(':scope > tbody');
        if (tbody) {
            parentel = tbody;
        }
        const criteria = parentel.querySelectorAll(SELECTORS.CRITERION);
        if (criteria.length) {
            const lastLevels = criteria[criteria.length - 1].querySelectorAll(SELECTORS.LEVEL);
            for (levidx = 0; levidx < lastLevels.length; levidx++) {
                levelsscores[levidx] = lastLevels[levidx].querySelector(SELECTORS.SCORE_INPUT).value;
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

        this.assignClasses(`#${IDS.levels(name, `NEWID${newid}`)} ${SELECTORS.LEVEL}`);
        this.disableAllEditors();
        this.assignClasses(elementsStr);
        this.editMode(document.getElementById(IDS.descriptionCell(name, `NEWID${newid}`)), true);
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
        const parent = document.getElementById(IDS.levels(name, criterionid));
        let newscore = 0;
        let levelIndex = 1;
        parent.querySelectorAll(SELECTORS.LEVEL).forEach((node) => {
            newscore = Math.max(newscore, parseFloat(node.querySelector(SELECTORS.SCORE_INPUT).value) + 1);
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
        const levels = parent.querySelectorAll(SELECTORS.LEVEL);
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
        let parentel = document.getElementById(IDS.criteria(name));
        const tbody = parentel.querySelector(':scope > tbody');
        if (tbody) {
            parentel = tbody;
        }

        const source = document.getElementById(IDS.criterion(name, criterionid));
        const sourceLevels = source.querySelectorAll(SELECTORS.LEVEL);
        for (levidx = 0; levidx < sourceLevels.length; levidx++) {
            levelsdef[levidx] = escapeHTML(sourceLevels[levidx].querySelector('.definition textarea').value);
        }
        for (levidx = 0; levidx < sourceLevels.length; levidx++) {
            levelsscores[levidx] = sourceLevels[levidx].querySelector(SELECTORS.SCORE_INPUT).value;
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
        const description = escapeHTML(source.querySelector('.description textarea').value);
        const newcriterion = this.templates.criterion
            .replace(/\{LEVELS\}/, levelsstr)
            .replace(/\{CRITERION-description\}/, description);
        parentel.insertAdjacentHTML('beforeend',
            newcriterion.replace(/\{CRITERION-id\}/g, `NEWID${newid}`).replace(/\{.+?\}/g, ''));

        this.assignClasses(`#${IDS.levels(name, `NEWID${newid}`)} ${SELECTORS.LEVEL}`);
        this.disableAllEditors();
        this.assignClasses(elementsStr);
        this.editMode(document.getElementById(IDS.descriptionCell(name, `NEWID${newid}`)), true);
    }

    /**
     * Shows a cell's text formatted as Markdown, as graders and students will see it.
     *
     * Cells are collected and sent in one request, so opening the editor makes a single call.
     *
     * @param {HTMLElement} target the element showing the text
     * @param {String} text the cell's Markdown
     */
    queueRender(target, text) {
        this.toRender.set(target, text);
        if (this.renderTimer === null) {
            this.renderTimer = setTimeout(() => this.renderQueued(), 0);
        }
    }

    /**
     * Renders the collected cells.
     */
    async renderQueued() {
        const queued = [...this.toRender];
        this.toRender.clear();
        this.renderTimer = null;
        const pending = new Pending('gradingform_matrix/matrixeditor:render');
        try {
            const html = await renderTexts(M.cfg.contextid, queued.map(([, text]) => text));
            queued.forEach(([target, text], i) => {
                // Skip cells that were edited again while the request ran.
                const textarea = target.closest(SELECTORS.EDITABLE_CELL)?.querySelector('textarea');
                if (textarea && textarea.value === text) {
                    target.innerHTML = html[i];
                }
            });
        } catch (error) {
            // The plain text stays on show, which is still correct.
            window.console.warn(error);
        } finally {
            pending.resolve();
        }
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
            el.querySelectorAll(SELECTORS.SORTORDER_INPUT).forEach((node) => {
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
    const criterionHolder = document.getElementById(IDS.criterionTemplate(name));
    const levelHolder = document.getElementById(IDS.levelTemplate(name));
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
