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
 * Library of functions, classes and constants for module subcourse
 *
 * @package     mod_subcourse
 * @copyright   2008 David Mudrak <david@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Returns the information if the module supports a feature
 *
 * @see plugin_supports() in lib/moodlelib.php
 *
 * @param string $feature FEATURE_xx constant for requested feature
 *
 * @return mixed true if the feature is supported, null if unknown
 */
function subcourse_supports($feature) {
    switch ($feature) {
        case FEATURE_GROUPS:
            return true;
        case FEATURE_GROUPINGS:
            return true;
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_GRADE_OUTCOMES:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_COMMENT:
            return true;
        case FEATURE_MOD_ARCHETYPE:
            return MOD_ARCHETYPE_RESOURCE;

        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_COLLABORATION;
        default:
            return null;
    }
}

/**
 * Given an object containing all the necessary data, (defined by the form)
 * this function will create a new instance and return the id number of the new
 * instance.
 *
 * @param stdClass $subcourse
 *
 * @return int The id of the newly inserted subcourse record
 * @throws dml_exception
 */
function subcourse_add_instance(stdClass $subcourse) {
    global $CFG, $DB;
    require_once($CFG->dirroot . '/mod/subcourse/locallib.php');

    $subcourse->timecreated = time();

    if (empty($subcourse->instantredirect)) {
        $subcourse->instantredirect = 0;
    }

    if (empty($subcourse->blankwindow)) {
        $subcourse->blankwindow = 0;
    }

    if (empty($subcourse->coursepageprintprogress)) {
        $subcourse->coursepageprintprogress = 0;
    }

    if (empty($subcourse->coursepageprintgrade)) {
        $subcourse->coursepageprintgrade = 0;
    }

    $newid = $DB->insert_record("subcourse", $subcourse);

    if (!empty($subcourse->refcourse)) {
        // Create grade_item but do not fetch grades.
        // The context does not exist yet and we can't get users by capability.
        subcourse_grades_update($subcourse->course, $newid, $subcourse->refcourse, $subcourse->name, true);
    }

    if (!empty($subcourse->completionexpected)) {
        \core_completion\api::update_completion_date_event($subcourse->coursemodule, 'subcourse', $newid,
            $subcourse->completionexpected);
    }

    return $newid;
}

/**
 * Given an object containing all the necessary data, (defined by the form)
 * this function will update an existing instance with new data.
 *
 * @param stdClass $subcourse
 *
 * @return boolean success/failure
 * @throws coding_exception
 * @throws dml_exception
 */
function subcourse_update_instance(stdClass $subcourse) {
    global $CFG, $DB;
    require_once($CFG->dirroot . '/mod/subcourse/locallib.php');

    $cmid = $subcourse->coursemodule;

    $subcourse->timemodified = time();
    $subcourse->id = $subcourse->instance;

    if (!empty($subcourse->refcoursecurrent)) {
        unset($subcourse->refcourse);
    }

    if (empty($subcourse->instantredirect)) {
        $subcourse->instantredirect = 0;
    }

    if (empty($subcourse->blankwindow)) {
        $subcourse->blankwindow = 0;
    }

    if (empty($subcourse->coursepageprintprogress)) {
        $subcourse->coursepageprintprogress = 0;
    }

    if (empty($subcourse->coursepageprintgrade)) {
        $subcourse->coursepageprintgrade = 0;
    }

    $DB->update_record('subcourse', $subcourse);

    if (!empty($subcourse->refcourse)) {
        if (has_capability('mod/subcourse:fetchgrades', context_module::instance($cmid))) {
            subcourse_grades_update($subcourse->course, $subcourse->id, $subcourse->refcourse, $subcourse->name,
                false, false, [], $subcourse->fetchpercentage);
            subcourse_update_timefetched($subcourse->id);
        }
    }

    \core_completion\api::update_completion_date_event($cmid, 'subcourse', $subcourse->id, $subcourse->completionexpected);

    return true;
}

/**
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param int $id Id of the module instance
 *
 * @return boolean success/failure
 * @throws dml_exception
 */
function subcourse_delete_instance($id) {
    global $CFG, $DB;
    require_once($CFG->libdir . '/gradelib.php');

    // Check the instance exists.
    if (!$subcourse = $DB->get_record("subcourse", ["id" => $id])) {
        return false;
    }

    // Remove the instance record.
    $DB->delete_records("subcourse", ["id" => $subcourse->id]);

    // Clean up the gradebook items.
    grade_update('mod/subcourse', $subcourse->course, 'mod', 'subcourse', $subcourse->id, 0, null, ['deleted' => true]);

    return true;
}

/**
 * Mark the activity completed (if required) and trigger the course_module_viewed event.
 *
 * @param  stdClass $url     url object
 * @param  stdClass $course  course object
 * @param  stdClass $cm      course module object
 * @param  stdClass $context context object
 *
 * @throws coding_exception
 */
function subcourse_view($subcourse, $course, $cm, $context) {

    // Trigger course_module_viewed event.
    $params = [
        'context' => $context,
        'objectid' => $subcourse->id,
    ];

    $event = \mod_subcourse\event\course_module_viewed::create($params);
    $event->add_record_snapshot('course_modules', $cm);
    $event->add_record_snapshot('course', $course);
    $event->add_record_snapshot('subcourse', $subcourse);
    $event->trigger();

    // Completion.
    $completion = new completion_info($course);
    $completion->set_module_viewed($cm);
}

/**
 * Return the action associated with the given calendar event, or null if there is none.
 * This is used by block_myoverview in order to display the event appropriately. If null is returned then the event
 * is not displayed on the block.
 *
 * @param calendar_event $event
 * @param \core_calendar\action_factory $factory
 *
 * @return \core_calendar\local\event\entities\action_interface|null
 */
function mod_subcourse_core_calendar_provide_event_action(calendar_event $event, \core_calendar\action_factory $factory) {

    $cm = get_fast_modinfo($event->courseid)->instances['subcourse'][$event->instance];

    return $factory->create_instance(
        get_string('view'),
        new \moodle_url('/mod/subcourse/view.php', ['id' => $cm->id]),
        1,
        true
    );
}

/**
 * Given a course_module object, this function returns any
 * "extra" information that may be needed when printing
 * this activity in a course listing.
 * See {@see get_array_of_activities()} in course/lib.php
 *
 * @param object $coursemodule
 *
 * @return cached_cm_info info
 * @throws dml_exception
 * @throws moodle_exception
 */
function subcourse_get_coursemodule_info($coursemodule) {
    global $DB;

    $subcourse = $DB->get_record('subcourse', ['id' => $coursemodule->instance]);

    if (!$subcourse) {
        return null;
    }

    $info = new cached_cm_info();
    $info->name = $subcourse->name;
    $info->customdata = (object)[
        'coursepageprintgrade' => $subcourse->coursepageprintgrade,
        'coursepageprintprogress' => $subcourse->coursepageprintprogress,
    ];

    if ($subcourse->instantredirect && $subcourse->blankwindow) {
        $url = new moodle_url('/mod/subcourse/view.php', ['id' => $coursemodule->id, 'isblankwindow' => 1]);
        $info->onclick = "window.open('" . $url->out(false) . "'); return false;";
    }

    if ($coursemodule->showdescription) {
        // Set content from intro and introformat. Filters are disabled because we filter with format_text at display time.
        $info->content = format_module_intro('subcourse', $subcourse, $coursemodule->id, false);
    }

    if ($coursemodule->completion == COMPLETION_TRACKING_AUTOMATIC) {
        $info->customdata->customcompletionrules = [
            'completionrefcourse' => $subcourse->completioncourse,
        ];
    }

    //$info->completionpassgrade = true;
    //$info->downloadcontent = false;
    //$info->lang = false;

    return $info;
}


/**
 * Create or update the grade item for given subcourse
 *
 * @category grade
 *
 * @param object $subcourse object
 * @param mixed $grades     optional array/object of grade(s); 'reset' means reset grades in gradebook
 *
 * @return int 0 if ok, error code otherwise
 */
function subcourse_grade_item_update($subcourse, $grades = null) {
    global $CFG;
    require_once($CFG->dirroot . '/mod/subcourse/locallib.php');

    $reset = false;
    if ($grades === 'reset') {
        $reset = true;
    }
    $gradeitemonly = true;
    if (!empty($grades)) {
        $gradeitemonly = false;
    }
    return subcourse_grades_update($subcourse->course, $subcourse->id, $subcourse->refcourse,
        $subcourse->name, $gradeitemonly, $reset);
}

/**
 * Update activity grades.
 *
 * @param stdClass $subcourse subcourse record
 * @param int $userid         specific user only, 0 means all
 * @param bool $nullifnone    - not used
 */
function subcourse_update_grades($subcourse, $userid = 0, $nullifnone = true) {
    global $CFG;
    require_once($CFG->dirroot . '/mod/subcourse/locallib.php');
    require_once($CFG->libdir . '/gradelib.php');

    $refgrades = subcourse_fetch_refgrades($subcourse->id, $subcourse->refcourse, false, $userid, false);

    if ($refgrades && $refgrades->grades) {
        if (!empty($refgrades->localremotescale)) {
            // Unable to fetch remote grades - local scale is used in the remote course.
            return GRADE_UPDATE_FAILED;
        }
        return subcourse_grade_item_update($subcourse, $refgrades->grades);
    } else {
        return subcourse_grade_item_update($subcourse);
    }
}

/**
 * Callback which returns human-readable strings describing the active completion custom rules for the module instance.
 *
 * @param cm_info|stdClass $cm object with fields ->completion and ->customdata['customcompletionrules']
 *
 * @return array $descriptions the array of descriptions for the custom rules.
 */
function mod_subcourse_get_completion_active_rule_descriptions($cm) {
    if (empty($cm->customdata['customcompletionrules']) || $cm->completion != COMPLETION_TRACKING_AUTOMATIC) {
        return [];
    }

    $descriptions = [];
    $completionrefcourse = $cm->customdata['customcompletionrules']['completionrefcourse'] ?? 0;
    $descriptions[] = "Requer {$completionrefcourse} %";
    return $descriptions;
}

/**
 * Sets the automatic completion state for this database item based on the count of on its entries.
 *
 * @param object $data   The data object for this activity
 * @param object $course Course
 * @param object $cm     course-module
 *
 * @throws moodle_exception
 */
function subcourse_update_completion_state($data, $course, $cm) {

    // If completion option is enabled, evaluate it and return true/false.
    $completion = new completion_info($course);
    if ($data->completionrefcourse && $completion->is_enabled($cm)) {
        $numentries = data_numentries($data);
        // Check the number of entries required against the number of entries already made.
        if ($numentries >= $data->completionrefcourse) {
            $completion->update_state($cm, COMPLETION_COMPLETE);
        } else {
            $completion->update_state($cm, COMPLETION_INCOMPLETE);
        }
    }
}

/**
 * Obtains the automatic completion state for this database item based on any conditions
 * on its settings. The call for this is in completion lib where the modulename is appended
 * to the function name. This is why there are unused parameters.
 *
 * @param stdClass $course     Course
 * @param cm_info|stdClass $cm course-module
 * @param int $userid          User ID
 * @param bool $type           Type of comparison (or/and; can be used as return value if no conditions)
 *
 * @return bool True if completed, false if not, $type if conditions not set.
 * @throws dml_exception
 */
function subcourse_get_completion_state($course, $cm, $userid, $type) {
    global $DB, $PAGE;

    // No need to call debugging here. Deprecation debugging notice already being called in \completion_info::internal_get_state().

    $result = $type; // Default return value
    // Get data details.
    if (isset($PAGE->cm->id) && $PAGE->cm->id == $cm->id) {
        $data = $PAGE->activityrecord;
    } else {
        $data = $DB->get_record('data', ['id' => $cm->instance], '*', MUST_EXIST);
    }
    // If completion option is enabled, evaluate it and return true/false.
    if ($data->completionrefcourse) {

        $numentries = 10;

        // Check the number of entries required against the number of entries already made.
        if ($numentries >= $data->completionrefcourse) {
            $result = true;
        } else {
            $result = false;
        }
    }
    return $result;
}
