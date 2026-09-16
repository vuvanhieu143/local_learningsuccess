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
 * Admin settings for local_learningsuccess.
 *
 * @package    local_learningsuccess
 * @copyright  2026 vuvanhieu143
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage(
        'local_learningsuccess',
        get_string('pluginname', 'local_learningsuccess')
    );

    $ADMIN->add('localplugins', $settings);

    if ($ADMIN->fulltree) {
        // Enable plugin.
        $settings->add(new admin_setting_configcheckbox(
            'local_learningsuccess/enabled',
            get_string('setting_enabled', 'local_learningsuccess'),
            get_string('setting_enabled_desc', 'local_learningsuccess'),
            1
        ));

        // Enable Class Pulse.
        $settings->add(new admin_setting_configcheckbox(
            'local_learningsuccess/enable_classpulse',
            get_string('setting_enable_classpulse', 'local_learningsuccess'),
            get_string('setting_enable_classpulse_desc', 'local_learningsuccess'),
            1
        ));

        // Enable Intervention Tracking.
        $settings->add(new admin_setting_configcheckbox(
            'local_learningsuccess/enable_interventions',
            get_string('setting_enable_interventions', 'local_learningsuccess'),
            get_string('setting_enable_interventions_desc', 'local_learningsuccess'),
            1
        ));

        // Inactivity threshold (days).
        $settings->add(new admin_setting_configtext(
            'local_learningsuccess/inactivity_threshold',
            get_string('setting_inactivity_threshold', 'local_learningsuccess'),
            get_string('setting_inactivity_threshold_desc', 'local_learningsuccess'),
            7,
            PARAM_INT
        ));

        // Grade decline threshold percentage.
        $settings->add(new admin_setting_configtext(
            'local_learningsuccess/grade_decline_threshold',
            get_string('setting_grade_decline_threshold', 'local_learningsuccess'),
            get_string('setting_grade_decline_threshold_desc', 'local_learningsuccess'),
            20,
            PARAM_INT
        ));

        // Preferred Moodle Analytics model target.
        $settings->add(new admin_setting_configtext(
            'local_learningsuccess/analytics_model',
            get_string('setting_analytics_model', 'local_learningsuccess'),
            get_string('setting_analytics_model_desc', 'local_learningsuccess'),
            '\core\analytics\target\course_dropout',
            PARAM_RAW_TRIMMED
        ));
    }
}

