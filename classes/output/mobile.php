<?php
// This file is part of Moodle - https://moodle.org/
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
 * Provides {@see \mod_subcourse\output\mobile} class.
 *
 * @package     mod_subcourse
 * @copyright   2020 David Mudrák <david@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_subcourse\output;

use context_course;
use context_module;
use core_completion\progress;
use core_external\util;
use local_kopere_dashboard\util\enroll_util;
use moodle_url;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/subcourse/locallib.php');

/**
 * Controls the display of the plugin in the Mobile App.
 *
 * @package   mod_subcourse
 * @category  output
 * @copyright 2020 David Mudrák <david@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mobile {

    /**
     * Return the data for the CoreCourseModuleDelegate delegate.
     *
     * @param object $args
     *
     * @return array
     *
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     * @throws \require_login_exception
     * @throws \required_capability_exception
     */
    public static function main_view($args) {
        global $OUTPUT, $USER, $DB;

        $args = (object)$args;
        $cm = get_coursemodule_from_id('subcourse', $args->cmid);
        $context = context_module::instance($cm->id);

        require_login($args->courseid, false, $cm, true, true);
        require_capability('mod/subcourse:view', $context);

        $subcourse = $DB->get_record('subcourse', ['id' => $cm->instance], '*', MUST_EXIST);

        $warning = null;
        $progress = null;

        if (empty($subcourse->refcourse)) {
            $refcourse = false;

            if (has_capability('mod/subcourse:fetchgrades', $context)) {
                $warning = get_string('refcoursenull', 'subcourse');
            }

        } else {
            $refcourse = $DB->get_record('course', ['id' => $subcourse->refcourse], 'id, fullname', IGNORE_MISSING);
        }

        if ($refcourse) {

            // Auto enrol course reference.
            $contextcourseref = context_course::instance($refcourse->id);
            if (!has_capability('moodle/course:view', $contextcourseref)) {
                $config = get_config('mod_subcourse');
                if ($config->coursepageenrol) {
                    $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
                    $contextcourse = context_course::instance($course->id);

                    $enrol = $DB->get_record('enrol',
                        [
                            'courseid' => $course->id,
                            'enrol' => 'manual',
                        ]);
                    $testroleassignments = $DB->get_record('role_assignments',
                        [
                            'contextid' => $contextcourse->id,
                            'userid' => $USER->id,
                        ]);
                    $userenrolments = $DB->get_record('user_enrolments',
                        [
                            'enrolid' => $enrol->id,
                            'userid' => $USER->id,
                        ]);
                    $roleid = isset($testroleassignments->roleid) ? $testroleassignments->roleid : 5;

                    $timestart = isset($userenrolments->timestart) ? $userenrolments->timestart : 0;
                    $timeend = isset($userenrolments->timeend) ? $userenrolments->timeend : 0;

                    enroll_util::enrol($refcourse, $USER, $timestart, $timeend, $roleid);

                    if ($config->courseenrolhide) {
                        set_user_preference("block_myoverview_hidden_course_{$refcourse->id}", 1, $USER);
                    }
                }
            }

            $refcourse->fullname = util::format_string($refcourse->fullname, $context);
            $refcourse->url = new moodle_url('/course/view.php', ['id' => $refcourse->id]);
            $progress = progress::get_course_progress_percentage($refcourse);
        }

        $currentgrade = subcourse_get_current_grade($subcourse, $USER->id);

        // Pre-format some of the texts for the mobile app.
        $subcourse->name = util::format_string($subcourse->name, $context);
        [$subcourse->intro, $subcourse->introformat] = util::format_text($subcourse->intro,
            $subcourse->introformat, $context, 'mod_subcourse', 'intro');

        $data = [
            'cmid' => $cm->id,
            'subcourse' => $subcourse,
            'refcourse' => $refcourse,
            'progress' => $progress,
            'hasprogress' => isset($progress),
            'currentgrade' => $currentgrade,
            'hasgrade' => isset($currentgrade),
            'warning' => $warning,
        ];

        return [
            'templates' => [
                [
                    'id' => 'main',
                    'html' => $OUTPUT->render_from_template("mod_subcourse/mobile_view", $data),
                ],
            ],
            'javascript' => '',
            'otherdata' => '',
            'files' => [],
        ];
    }
}
