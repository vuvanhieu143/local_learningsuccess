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

namespace local_learningsuccess\local\intervention;

/**
 * Service providing editable, empathetic message templates based on detected signals.
 *
 * All messages are rendered as teacher drafts and are never sent automatically.
 *
 * @package    local_learningsuccess
 * @copyright  2026 vuvanhieu143
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class message_template {
    /** @var string Inactivity message template identifier. */
    public const TEMPLATE_INACTIVITY = 'inactivity';

    /** @var string Overdue activity message template identifier. */
    public const TEMPLATE_OVERDUE = 'overdue';

    /** @var string Grade decline message template identifier. */
    public const TEMPLATE_GRADE_DECLINE = 'grade_decline';

    /** @var string General check-in message template identifier. */
    public const TEMPLATE_GENERAL = 'general';

    /**
     * Get available template definitions with localized labels and raw bodies.
     *
     * @return array<string, array{type: string, title: string, body: string}>
     */
    public static function get_templates(): array {
        return [
            self::TEMPLATE_INACTIVITY => [
                'type' => self::TEMPLATE_INACTIVITY,
                'title' => get_string('template_inactivity_title', 'local_learningsuccess'),
                'body' => get_string('template_inactivity_body', 'local_learningsuccess'),
            ],
            self::TEMPLATE_OVERDUE => [
                'type' => self::TEMPLATE_OVERDUE,
                'title' => get_string('template_overdue_title', 'local_learningsuccess'),
                'body' => get_string('template_overdue_body', 'local_learningsuccess'),
            ],
            self::TEMPLATE_GRADE_DECLINE => [
                'type' => self::TEMPLATE_GRADE_DECLINE,
                'title' => get_string('template_grade_decline_title', 'local_learningsuccess'),
                'body' => get_string('template_grade_decline_body', 'local_learningsuccess'),
            ],
            self::TEMPLATE_GENERAL => [
                'type' => self::TEMPLATE_GENERAL,
                'title' => get_string('template_general_title', 'local_learningsuccess'),
                'body' => get_string('template_general_body', 'local_learningsuccess'),
            ],
        ];
    }

    /**
     * Determine best template based on student signals.
     *
     * @param array $signals
     * @return string Template constant
     */
    public static function detect_best_template(array $signals): string {
        $rules = array_map(fn($s) => is_array($s) ? ($s['rule'] ?? '') : ($s->rule ?? ''), $signals);

        if (in_array('inactivity', $rules, true)) {
            return self::TEMPLATE_INACTIVITY;
        }
        if (
            in_array('overdue', $rules, true)
            || in_array('missed_assignments', $rules, true)
            || in_array('missed_activity', $rules, true)
        ) {
            return self::TEMPLATE_OVERDUE;
        }
        if (
            in_array('grade_decline', $rules, true)
            || in_array('grade_performance', $rules, true)
            || in_array('quiz_retries', $rules, true)
        ) {
            return self::TEMPLATE_GRADE_DECLINE;
        }

        return self::TEMPLATE_GENERAL;
    }

    /**
     * Render a template body with student and course placeholders.
     *
     * @param string $templatetype
     * @param object|array $user Target student (must have firstname, optionally lastname)
     * @param object|string $course Course record or course fullname string
     * @return string Rendered text ready for teacher review/edit
     */
    public static function render(string $templatetype, object|array $user, object|string $course): string {
        $templates = self::get_templates();
        $template = $templates[$templatetype] ?? $templates[self::TEMPLATE_GENERAL];
        $rawbody = $template['body'];

        $uobj = is_array($user) ? (object) $user : $user;
        $firstname = $uobj->firstname ?? '';
        $lastname = $uobj->lastname ?? '';
        foreach (\core_user\fields::get_name_fields() as $nf) {
            if (!isset($uobj->$nf)) {
                $uobj->$nf = '';
            }
        }
        $fullname = fullname($uobj);

        $coursename = is_object($course) ? ($course->fullname ?? '') : (string) $course;

        $replacements = [
            '{firstname}' => $firstname,
            '{lastname}' => $lastname,
            '{fullname}' => $fullname,
            '{coursename}' => $coursename,
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $rawbody);
    }
}
