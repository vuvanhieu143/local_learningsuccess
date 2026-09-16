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
 * Modern ES6 module controlling Dashboard interactions for local_learningsuccess.
 *
 * @module     local_learningsuccess/dashboard
 * @copyright  2026 vuvanhieu143
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';
import Selectors from 'local_learningsuccess/selectors';
import {init as initInterventions} from 'local_learningsuccess/intervention';

/**
 * Register event listeners for the dashboard.
 *
 * @param {number} courseId
 */
const registerEventListeners = (courseId) => {
    document.addEventListener('click', e => {
        const refreshBtn = e.target.closest(Selectors.actions.refreshDashboardButton);
        if (refreshBtn) {
            e.preventDefault();
            refreshBtn.disabled = true;

            Ajax.call([{
                methodname: 'local_learningsuccess_get_dashboard_data',
                args: {courseid: courseId}
            }])[0].then(() => {
                window.location.reload();
                return null;
            }).catch(err => {
                refreshBtn.disabled = false;
                Notification.exception(err);
            });
        }
    });
};

/**
 * Initialize dashboard module.
 *
 * @param {Object} config
 * @param {number} config.courseid
 */
export const init = (config) => {
    let courseId;
    if (typeof config === 'object' && config !== null) {
        courseId = parseInt(config.courseid, 10);
    } else {
        courseId = parseInt(config, 10);
    }

    if (isNaN(courseId) || courseId <= 0) {
        const rootEl = document.querySelector('[data-region="dashboard"], [data-courseid]');
        if (rootEl && rootEl.dataset.courseid) {
            courseId = parseInt(rootEl.dataset.courseid, 10);
        }
    }

    initInterventions(courseId);

    registerEventListeners(courseId);
};
