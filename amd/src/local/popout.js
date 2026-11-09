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
 * Size controls for the popped-out grading form.
 *
 * mod_assign pops a grading field out by adding the popout class to its .fitem.
 * This adds a maximise button next to the core zoom button and a resize grip
 * that works with the keyboard as well as a pointer, replacing the browser's
 * own resize handle, which only works by dragging.
 *
 * The size is kept for the rest of the page, so it survives moving between students.
 *
 * Not specific to the matrix: any grading method can call {@see watch()} with its grading form.
 * While popped out the .fitem has the class gradingform_matrix-popout, which the popout rules in
 * styles.css apply to.
 *
 * @module     gradingform_matrix/local/popout
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {getStrings} from 'core/str';
import Templates from 'core/templates';
import Notification from 'core/notification';
import Pending from 'core/pending';

const MIN_WIDTH = 320;
const MIN_HEIGHT = 240;
const STEP = 20;
const BIG_STEP = 100;
const MARGIN = 16;
const SMALL_SCREEN = '(max-width: 575.98px)';

const CLASSES = {
    POPOUT: 'popout',
    SIZED: 'gradingform_matrix-popout',
    MAXIMISED: 'gradingform_matrix-maximised',
    MAXIMISE: 'gradingform_matrix-maximise',
    GRIP: 'gradingform_matrix-resizegrip',
};

const SELECTORS = {
    CORE_POPOUT_BUTTON: '[data-region="popout-button"]',
    CONTROLS: '[data-region="gradingform_matrix-popoutcontrols"]',
    MAXIMISE: '.gradingform_matrix-maximise',
    GRIP: '.gradingform_matrix-resizegrip',
    LIVE: '[data-region="gradingform_matrix-popoutsize"]',
};

/** @var {Object|null} size chosen by the user, kept while the page is open */
let savedSize = null;

/** @var {Boolean} whether the user maximised the popout */
let maximised = false;

/**
 * Adds or removes a class only when that changes it.
 *
 * classList.remove() and toggle() rewrite the class attribute even when nothing changes,
 * which would retrigger the class observer that calls {@see sync()}.
 *
 * @param {HTMLElement} element
 * @param {String} name
 * @param {Boolean} on
 */
const setClass = (element, name, on) => {
    if (element.classList.contains(name) !== on) {
        element.classList.toggle(name, on);
    }
};

/**
 * The smallest and largest size the popout may have, keeping it inside the viewport.
 *
 * @param {HTMLElement} fitem
 * @return {Object}
 */
const limits = (fitem) => {
    const rect = fitem.getBoundingClientRect();
    const maxWidth = Math.max(MIN_WIDTH, window.innerWidth - rect.left - MARGIN);
    const maxHeight = Math.max(MIN_HEIGHT, window.innerHeight - rect.top - MARGIN);
    return {
        minWidth: Math.min(MIN_WIDTH, maxWidth),
        minHeight: Math.min(MIN_HEIGHT, maxHeight),
        maxWidth,
        maxHeight,
    };
};

/**
 * Sets the popout size within its limits and remembers it.
 *
 * @param {HTMLElement} fitem
 * @param {Number} width
 * @param {Number} height
 */
const setSize = (fitem, width, height) => {
    const {minWidth, minHeight, maxWidth, maxHeight} = limits(fitem);
    savedSize = {
        width: Math.round(Math.min(maxWidth, Math.max(minWidth, width))),
        height: Math.round(Math.min(maxHeight, Math.max(minHeight, height))),
    };
    fitem.style.width = `${savedSize.width}px`;
    fitem.style.height = `${savedSize.height}px`;
};

/**
 * Removes the sizes set here, so the stylesheet decides.
 *
 * @param {HTMLElement} fitem
 */
const clearSize = (fitem) => {
    fitem.style.width = '';
    fitem.style.height = '';
};

/**
 * Switches maximised on or off.
 *
 * @param {HTMLElement} fitem
 * @param {Boolean} on
 */
const setMaximised = (fitem, on) => {
    maximised = on;
    setClass(fitem, CLASSES.MAXIMISED, on);
    const button = fitem.querySelector(SELECTORS.MAXIMISE);
    if (button) {
        button.setAttribute('aria-pressed', on ? 'true' : 'false');
    }
    if (on) {
        clearSize(fitem);
    } else if (savedSize) {
        setSize(fitem, savedSize.width, savedSize.height);
    }
};

/**
 * Tells screen reader users the new size.
 *
 * @param {HTMLElement} fitem
 * @param {Object} strings
 */
const announceSize = (fitem, strings) => {
    const live = fitem.querySelector(SELECTORS.LIVE);
    if (live && savedSize) {
        live.textContent = strings.size.replace('{width}', savedSize.width).replace('{height}', savedSize.height);
    }
};

/**
 * Leaves maximised mode, starting from the size the popout has on screen.
 *
 * @param {HTMLElement} fitem
 */
const startResizing = (fitem) => {
    if (maximised) {
        const rect = fitem.getBoundingClientRect();
        setMaximised(fitem, false);
        savedSize = null;
        setSize(fitem, rect.width, rect.height);
    }
};

/**
 * Handles the arrow, Home and End keys on the resize grip.
 *
 * @param {HTMLElement} fitem
 * @param {Object} strings
 * @param {KeyboardEvent} event
 */
const onGripKey = (fitem, strings, event) => {
    const step = event.shiftKey ? BIG_STEP : STEP;
    const deltas = {
        ArrowRight: [step, 0],
        ArrowLeft: [-step, 0],
        ArrowDown: [0, step],
        ArrowUp: [0, -step],
    };
    startResizing(fitem);
    const rect = fitem.getBoundingClientRect();
    const {minWidth, minHeight, maxWidth, maxHeight} = limits(fitem);
    if (deltas[event.key]) {
        const [dx, dy] = deltas[event.key];
        setSize(fitem, rect.width + dx, rect.height + dy);
    } else if (event.key === 'Home') {
        setSize(fitem, minWidth, minHeight);
    } else if (event.key === 'End') {
        setSize(fitem, maxWidth, maxHeight);
    } else {
        return;
    }
    event.preventDefault();
    announceSize(fitem, strings);
};

/**
 * Lets the grip be dragged with a mouse, pen or finger.
 *
 * @param {HTMLElement} fitem
 * @param {HTMLElement} grip
 * @param {Object} strings
 */
const enablePointerResize = (fitem, grip, strings) => {
    grip.addEventListener('pointerdown', (event) => {
        if (event.button !== 0) {
            return;
        }
        event.preventDefault();
        grip.focus();
        startResizing(fitem);
        const rect = fitem.getBoundingClientRect();
        const start = {x: event.clientX, y: event.clientY, width: rect.width, height: rect.height};
        grip.setPointerCapture(event.pointerId);

        const move = (e) => setSize(fitem, start.width + e.clientX - start.x, start.height + e.clientY - start.y);
        const end = () => {
            grip.removeEventListener('pointermove', move);
            grip.removeEventListener('pointerup', end);
            grip.removeEventListener('pointercancel', end);
            announceSize(fitem, strings);
        };
        grip.addEventListener('pointermove', move);
        grip.addEventListener('pointerup', end);
        grip.addEventListener('pointercancel', end);
    });
};

/**
 * Adds the maximise button, resize grip and live region to a popout, once.
 *
 * @param {HTMLElement} fitem
 * @return {Promise}
 */
const addControls = (fitem) => {
    if (fitem.querySelector(SELECTORS.CONTROLS)) {
        return Promise.resolve();
    }
    const pending = new Pending('gradingform_matrix/local/popout:addControls');
    const controls = document.createElement('div');
    controls.dataset.region = 'gradingform_matrix-popoutcontrols';
    fitem.append(controls);

    return Promise.all([
        getStrings([
            {key: 'popoutmaximise', component: 'gradingform_matrix'},
            {key: 'popoutresize', component: 'gradingform_matrix'},
            {key: 'popoutresizehelp', component: 'gradingform_matrix'},
            {key: 'popoutsize', component: 'gradingform_matrix', param: {width: '{width}', height: '{height}'}},
        ]),
        Templates.renderPix('e/resize', 'core'),
    ]).then(([[maximise, resize, help, size], icon]) => {
        const strings = {size};
        const helpid = `${fitem.id || 'matrix'}-gradingform_matrix-resizehelp`;

        const button = document.createElement('button');
        button.type = 'button';
        button.className = `btn btn-icon ${CLASSES.MAXIMISE}`;
        button.setAttribute('aria-label', maximise);
        button.setAttribute('aria-pressed', maximised ? 'true' : 'false');
        button.title = maximise;
        button.innerHTML = icon;
        button.addEventListener('click', () => setMaximised(fitem, !maximised));
        const corebutton = fitem.querySelector(SELECTORS.CORE_POPOUT_BUTTON);
        if (corebutton) {
            corebutton.after(button);
        } else {
            controls.append(button);
        }

        const grip = document.createElement('button');
        grip.type = 'button';
        grip.className = CLASSES.GRIP;
        grip.setAttribute('aria-label', resize);
        grip.setAttribute('aria-describedby', helpid);
        grip.title = resize;
        grip.addEventListener('keydown', (event) => onGripKey(fitem, strings, event));
        enablePointerResize(fitem, grip, strings);

        const helptext = document.createElement('span');
        helptext.id = helpid;
        helptext.className = 'visually-hidden';
        helptext.textContent = help;

        const live = document.createElement('span');
        live.dataset.region = 'gradingform_matrix-popoutsize';
        live.className = 'visually-hidden';
        live.setAttribute('aria-live', 'polite');

        controls.append(grip, helptext, live);
        return;
    }).catch(Notification.exception).finally(() => pending.resolve());
};

/**
 * Brings a popout's size and controls in line with whether it is popped out.
 *
 * Call whenever the .fitem's classes change.
 *
 * @param {HTMLElement} fitem
 */
export const sync = (fitem) => {
    const popout = fitem.classList.contains(CLASSES.POPOUT);
    setClass(fitem, CLASSES.SIZED, popout);
    if (!popout) {
        clearSize(fitem);
        setClass(fitem, CLASSES.MAXIMISED, false);
        maximised = false;
        return;
    }
    addControls(fitem);
    if (window.matchMedia(SMALL_SCREEN).matches) {
        // The stylesheet fills the screen; sizes set here would override it.
        clearSize(fitem);
        return;
    }
    setClass(fitem, CLASSES.MAXIMISED, maximised);
    if (!maximised && savedSize && !fitem.style.width) {
        setSize(fitem, savedSize.width, savedSize.height);
    }
};

/**
 * Keeps the popout controls of the .fitem holding a grading form in step with mod_assign's popout.
 *
 * @param {HTMLElement} root the grading form
 */
export const watch = (root) => {
    const fitem = root.closest('.fitem');
    if (!fitem) {
        return;
    }
    new MutationObserver(() => sync(fitem)).observe(fitem, {
        attributes: true,
        attributeFilter: ['class'],
    });
    sync(fitem);
};
