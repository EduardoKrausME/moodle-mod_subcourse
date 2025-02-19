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
 * View a particular instance of the subcourse
 *
 * @package     mod_subcourse
 * @copyright   2008 David Mudrak <david@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/subcourse/locallib.php');
require_once($CFG->libdir . '/gradelib.php');

$id = required_param('id', PARAM_INT);
$fetchnow = optional_param('fetchnow', 0, PARAM_INT);
$isblankwindow = optional_param('isblankwindow', false, PARAM_BOOL);

$cm = get_coursemodule_from_id('subcourse', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$subcourse = $DB->get_record('subcourse', ['id' => $cm->instance], '*', MUST_EXIST);

$context = context_module::instance($cm->id);
$coursecontext = context_course::instance($course->id);

require_login($course);
require_capability('mod/subcourse:view', $context);

$PAGE->set_url(new moodle_url('/mod/subcourse/view.php', ['id' => $cm->id]));
$PAGE->set_title($subcourse->name);
$PAGE->set_heading($course->fullname);

if (empty($subcourse->refcourse)) {
    $refcourse = false;
} else {
    $refcourse = $DB->get_record('course', ['id' => $subcourse->refcourse], '*', IGNORE_MISSING);
}

if (!$refcourse->visible) {
    $refcourse->visible = 1;
    $DB->update_record('course', $refcourse);
}


if ($fetchnow && $refcourse) {
    require_sesskey();
    require_capability('mod/subcourse:fetchgrades', $context);

    $event = \mod_subcourse\event\subcourse_grades_fetched::create([
        'objectid' => $subcourse->id,
        'context' => $context,
        'other' => ['refcourse' => $refcourse->id],
    ]);

    $event->add_record_snapshot('course_modules', $cm);
    $event->add_record_snapshot('course', $course);
    $event->add_record_snapshot('subcourse', $subcourse);
    $event->trigger();

    $result = subcourse_grades_update($subcourse->course, $subcourse->id, $subcourse->refcourse,
        null, false, false, [], $subcourse->fetchpercentage);

    if ($result == GRADE_UPDATE_OK) {
        subcourse_update_timefetched($subcourse->id);
        redirect(new moodle_url('/mod/subcourse/view.php', ['id' => $cm->id]));

    } else {
        throw new moodle_exception('errfetch', 'subcourse', $CFG->wwwroot . '/mod/subcourse/view.php?id=' . $cm->id, $result);
    }
}

subcourse_set_module_viewed($subcourse, $context, $course, $cm);

$contextcourse = context_course::instance($course->id);
$contextcourseref = context_course::instance($refcourse->id);
if (!has_capability('moodle/course:view', $contextcourseref)) {
    $config = get_config('mod_subcourse');
    if ($config->coursepageenrol) {
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

        \local_kopere_dashboard\util\enroll_util::enrol($refcourse, $USER, $timestart, $timeend, $roleid);

        if ($config->courseenrolhide) {
            set_user_preference("block_myoverview_hidden_course_{$refcourse->id}", 1, $USER);
        }
    }
}

$instantredirect = optional_param('instantredirect', false, PARAM_INT);
if ($refcourse && ($subcourse->instantredirect || $instantredirect)) {
    // These three variables are used in the theme_degrade and theme_boost_magnific...
    // to create the Back to course button.
    $_SESSION['return_course_id'] = $course->id;
    $_SESSION['return_course_name'] = $course->fullname;
    $_SESSION['refcourse_course_id'] = $refcourse->id;

    redirect(new moodle_url('/course/view.php', ['id' => $refcourse->id]));
} else if ($refcourse) {
    echo $OUTPUT->header();

    $percentage = \core_completion\progress::get_course_progress_percentage($refcourse);
    $strgrade = subcourse_get_current_grade($subcourse, $USER->id);

    echo $OUTPUT->render_from_template('mod_subcourse/subcourseinfo', [
        'haspercentage' => ($percentage !== null),
        'hasstrgrade' => ($strgrade !== null),
        'percentage' => floor((float)$percentage),
        'strgrade' => $strgrade,
    ]);

    echo html_writer::start_div('actionbuttons');

    if ($subcourse->blankwindow && !$isblankwindow) {
        $target = '_blank';
    } else {
        $target = '';
    }

    echo html_writer::link(
        new moodle_url('/mod/subcourse/view.php', ['id' => $cm->id, 'instantredirect' => 1]),
        get_string('gotorefcourse', 'subcourse', format_string($refcourse->fullname)),
        ['class' => 'btn btn-primary', 'target' => $target]
    );

    $refcoursecontext = context_course::instance($refcourse->id);

    if (has_all_capabilities(['gradereport/grader:view', 'moodle/grade:viewall'], $refcoursecontext)) {
        echo html_writer::link(
            new moodle_url('/grade/report/grader/index.php', ['id' => $refcourse->id]),
            get_string('gotorefcoursegrader', 'subcourse', format_string($refcourse->fullname)),
            ['class' => 'btn btn-secondary']
        );
    }

    if (has_all_capabilities(['gradereport/user:view', 'moodle/grade:view'], $refcoursecontext)
        && $refcourse->showgrades && ($strgrade !== null)) {
        echo html_writer::link(
            new moodle_url('/grade/report/user/index.php', ['id' => $refcourse->id]),
            get_string('gotorefcoursemygrades', 'subcourse', format_string($refcourse->fullname)),
            ['class' => 'btn btn-secondary']
        );
    }

    if (has_capability('mod/subcourse:fetchgrades', $context)) {
        echo html_writer::link(
            new moodle_url($PAGE->url, ['sesskey' => sesskey(), 'fetchnow' => 1]),
            get_string('fetchnow', 'subcourse'),
            ['class' => 'btn btn-link']
        );

        if (empty($subcourse->timefetched)) {
            $fetchinfo = get_string('lastfetchnever', 'subcourse');
        } else {
            $fetchinfo = get_string('lastfetchtime', 'subcourse', userdate($subcourse->timefetched));
        }

        echo html_writer::tag('small', $fetchinfo, ['class' => 'dimmed_text']);
    }

    // End of div.actionbuttons.
    echo html_writer::end_div();
    echo $OUTPUT->footer();

} else {
    echo $OUTPUT->header();

    if (has_capability('mod/subcourse:fetchgrades', $context)) {
        echo $OUTPUT->notification(get_string('refcoursenull', 'subcourse'));
    }

    echo $OUTPUT->footer();
}

