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

namespace local_learningsuccess\local\signal;

defined('MOODLE_INTERNAL') || die();

use local_learningsuccess\local\explanation\explanation;
use local_learningsuccess\local\risk\moodle_analytics_provider;
use local_learningsuccess\local\risk\risk_result;

/**
 * Signal evaluator surfacing Moodle Analytics machine learning predictions.
 *
 * @package    local_learningsuccess
 * @copyright  2026 Learning Success Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class analytics_risk_signal implements signal {

    public const TYPE = 'analytics_risk';

    /** @var moodle_analytics_provider */
    private moodle_analytics_provider $provider;

    /**
     * Constructor.
     *
     * @param moodle_analytics_provider|null $provider
     */
    public function __construct(?moodle_analytics_provider $provider = null) {
        $this->provider = $provider ?? new moodle_analytics_provider();
    }

    /**
     * Evaluate Moodle Analytics prediction signal.
     *
     * @param int $userid
     * @param int $courseid
     * @return explanation|null
     */
    public function evaluate(int $userid, int $courseid): ?explanation {
        $risk = $this->provider->get_risk($userid, $courseid);

        if ($risk->get_source() !== moodle_analytics_provider::SOURCE_NAME) {
            return null;
        }

        if (!$risk->requires_attention()) {
            return null;
        }

        $severity = ($risk->get_level() === risk_result::LEVEL_CRITICAL)
            ? explanation::SEVERITY_CRITICAL
            : explanation::SEVERITY_WARNING;

        return new explanation(
            type: self::TYPE,
            severity: $severity,
            title: get_string('signal_analytics_title', 'local_learningsuccess'),
            description: get_string('signal_analytics_desc', 'local_learningsuccess', $risk->get_model() ?? ''),
            value: $risk->get_score(),
            evidence: [
                'model' => $risk->get_model(),
                'score' => $risk->get_score(),
                'level' => $risk->get_level(),
            ]
        );
    }
}
