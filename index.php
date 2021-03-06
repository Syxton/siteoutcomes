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
 * The site outcomes report
 *
 * @package    report_siteoutcomes
 * @since      Moodle 3.3
 * @copyright  2017 Matthew Davidson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->dirroot.'/grade/lib.php');

require_login();

$outcomeid  = optional_param('outcomeid', 0, PARAM_INT);
$datefrom   = optional_param_array('datefrom', array(), PARAM_INT);
$datetill   = optional_param_array('datetill', array(), PARAM_INT);

$context = context_system::instance();

$PAGE->set_context($context);
$PAGE->set_url('/report/siteoutcomes/index.php', array());
$PAGE->set_pagelayout('report');
$PAGE->navbar->add(get_string('pluginname', 'report_siteoutcomes'));

require_capability('report/siteoutcomes:view', $context);

$mform = new \report_siteoutcomes\filter_form(null);

if ($mform->is_submitted() && !empty($outcomeid)) {
    // Select standard outcome.
    $outcome = $DB->get_record('grade_outcomes', array('id' => $outcomeid));
    $scale = new grade_scale(array('id' => $outcome->scaleid), false);

    // Grab all courses that use selected standard outcome.
    $courses = $DB->get_records('grade_outcomes_courses', array('outcomeid' => $outcomeid));

    $reportinfo = array();
    $csv = array();
    $reportinfo['outcome'] = $outcome;
    $reportinfo["items"] = array();
    $reportinfo["categoryavgs"] = array();

    // Grab all outcome items and merge them.
    foreach ($courses as $course) {
        // Get course context.
        $coursecontext = context_course::instance($course->courseid);

        // Will exclude grades of suspended users if required.
        $defaultgradeshowactiveenrol = !empty($CFG->grade_report_showonlyactiveenrol);
        $showonlyactiveenrol = get_user_preferences('grade_report_showonlyactiveenrol', $defaultgradeshowactiveenrol);
        $showonlyactiveenrol = $showonlyactiveenrol || !has_capability('moodle/course:viewsuspendedusers', $coursecontext);
        if ($showonlyactiveenrol) {
            $suspendedusers = get_suspended_userids($coursecontext);
        }

        $datesql = "";
        if (!empty($datefrom)) {
            $date = new DateTime("now", core_date::get_user_timezone_object());
            $date->setDate($datefrom["year"], $datefrom["month"], $datefrom["day"]);
            $datesql .= " AND timecreated >= " . $date->getTimestamp();
        }

        if (!empty($datetill)) {
            $date = new DateTime("now", core_date::get_user_timezone_object());
            $date->setDate($datetill["year"], $datetill["month"], $datetill["day"]);
            $datesql .= " AND timecreated <= " . $date->getTimestamp();
        }

        // Get grade_items that use each outcome.
        $checkthis = $DB->get_records_select('grade_items',
                                             "outcomeid = ? AND courseid = ? $datesql",
                                             array($outcomeid, $course->courseid));
        if (!empty($checkthis)) {
            $reportitems[$course->courseid] = $checkthis;
        }

        // Get average grades for each item.
        if (!empty($reportitems[$course->courseid]) && is_array($reportitems[$course->courseid])) {
            foreach ($reportitems[$course->courseid] as $itemid => $item) {
                $params = array();
                $hidesuspendedsql = '';
                if ($showonlyactiveenrol && !empty($suspendedusers)) {
                    list($notinusers, $params) = $DB->get_in_or_equal($suspendedusers, SQL_PARAMS_QM, null, false);
                    $hidesuspendedsql = ' AND userid ' . $notinusers;
                }
                $params = array_merge(array($itemid), $params);

                $sql = "SELECT itemid, AVG(finalgrade) AS avg, COUNT(finalgrade) AS count
                          FROM {grade_grades}
                         WHERE itemid = ?".
                         $hidesuspendedsql.
                      " GROUP BY itemid";

                $info = $DB->get_records_sql($sql, $params);

                if (!$info) {
                    unset($reportitems[$course->courseid][$itemid]);
                    continue;
                } else {
                    $info = reset($info);
                    $avg = round($info->avg, 2);
                    $count = $info->count;
                }

                $reportitems[$course->courseid][$itemid]->avg = $avg;
                $reportitems[$course->courseid][$itemid]->count = $count;
            }
        }

        if (empty($reportitems[$course->courseid])) {
            unset($reportitems[$course->courseid]);
        } else {
             // Merge outcomes data.
            $reportinfo["items"] += $reportitems;
        }
    }

    if (empty($reportinfo["items"])) {
        print_grade_page_head($COURSE->id, 'report', 'siteoutcomes', get_string('pluginname', 'report_siteoutcomes'), false, '');
        echo "No Data";
    } else {
        $html = '<h3>' . get_string('coursetitle', 'report_siteoutcomes') . '</h3>';
        $html .= '<table class="generaltable boxaligncenter" ' .
                 'width="90%" cellspacing="1" cellpadding="5" ' .
                 'summary=" ' . get_string('coursetitle', 'report_siteoutcomes') . '">' . "\n";
        $html .= '<th class="header c1" scope="col">' . get_string('courseavg', 'grades') . '</th>';
        $html .= '<th class="header c2" scope="col">' . get_string('activities', 'grades') . '</th>';
        $html .= '<th class="header c3" scope="col">' . get_string('average', 'grades') . '</th>';
        $html .= '<th class="header c4" scope="col">' . get_string('numberofgrades', 'grades') . '</th></tr>' . "\n";

        $row = 0;
        $csv[$row] = array(get_string('courseavg', 'grades'),
                           get_string('activities', 'grades'),
                           get_string('average', 'grades'),
                           get_string('average', 'grades') . " " . get_string('numeric', 'report_siteoutcomes'),
                           get_string('numberofgrades', 'grades'),
                           'header' => true);

        foreach ($reportinfo["items"] as $courseid => $outcomedata) {
            $course = $DB->get_record('course', array("id" => $courseid));
            $coursegradecount = 0;
            $rowspan = count($outcomedata, COUNT_RECURSIVE);
            // If there are no items for this outcome, rowspan will equal 0, which is not good.
            if ($rowspan == 0) {
                $rowspan = 1;
            }

            $reportinfo['outcome']->sum = 0;

            $printtr = false;
            $itemshtml = '';

            if (!empty($outcomedata)) {
                foreach ($outcomedata as $itemid => $item) {
                    if ($printtr) {
                        $row++;
                        $itemshtml .= "<tr class=\"r$row\">\n";
                    }

                    if ($item->itemtype == 'mod') {
                        $cm = get_coursemodule_from_instance($item->itemmodule, $item->iteminstance, $item->courseid);
                        $itemname = '<a href="' . $CFG->wwwroot . '/mod/'.$item->itemmodule.'/view.php?id='.$cm->id.'">' .
                                        format_string($cm->name, true, $cm->course) .
                                    '</a>';
                    } else {
                        $gradeitem = new grade_item($item, false);
                        $itemname = $gradeitem->get_name();
                    }

                    $reportinfo['outcome']->sum += $item->avg;
                    $gradehtml = $scale->get_nearest_item($item->avg);

                    $itemshtml .= "<td class=\"cell c2\">$itemname</td>"
                                 . "<td class=\"cell c3\">$gradehtml ($item->avg)</td>"
                                 . "<td class=\"cell c4\">$item->count</td></tr>\n";
                    $csv[$row + 1] = array($course->fullname,
                                         mb_convert_encoding(str_replace('"', '""', format_string($cm->name, true, $cm->course)),
                                                             'UTF-16LE',
                                                             'UTF-8'),
                                         $gradehtml,
                                         $item->avg,
                                         $item->count,
                                         'header' => false);
                    $coursegradecount += $item->count;
                    $printtr = true;
                }
            } else {
                $itemshtml .= "<td class=\"cell c2\"> - </td><td class=\"cell c3\"> - </td><td class=\"cell c4\"> 0 </td></tr>\n";
                $csv[$row + 1] = array($course->fullname,
                     "-",
                     "-",
                     "-",
                     "0");
            }

            // Calculate outcome average.
            if (is_array($outcomedata)) {
                $count = count($outcomedata);
                if ($count > 0) {
                    $avg = $reportinfo['outcome']->sum / $count;
                } else {
                    $avg = $reportinfo['outcome']->sum;
                }
                $avghtml = $scale->get_nearest_item($avg) . " (" . round($avg, 2) . ")\n";
            } else {
                $avghtml = ' - ';
            }

            $reportinfo["categoryavgs"][$course->category][] = array('avg' => $avg, 'count' => $coursegradecount);
            $outcomeavghtml = '<td class="cell c1" rowspan="' . $rowspan . '">' .
                               '<strong>' . $course->fullname . "</strong><br /><br />" . $avghtml . "</td>\n";

            $html .= $outcomeavghtml . $itemshtml;
            $row++;
        }

        $html .= '</table><br /><br />';

        $html .= '<h3>' . get_string('categorytitle', 'report_siteoutcomes') . '</h3>';
        $html .= '<table class="generaltable boxaligncenter" width="90%" cellspacing="1" cellpadding="5" ' .
                 'summary="' . get_string('categorytitle', 'report_siteoutcomes') . '">' . "\n";
        $html .= '<th class="header c1" scope="col">' . get_string('category') . '</th>';
        $html .= '<th class="header c2" scope="col">' . get_string('courses') . '</th>';
        $html .= '<th class="header c3" scope="col">' . get_string('average', 'grades') . '</th>';
        $html .= '<th class="header c4" scope="col">' . get_string('numberofgrades', 'grades') . '</th></tr>' . "\n";
        $row++;
        $csv[$row] = array(get_string('category'),
                           get_string('courses'),
                           get_string('average', 'grades'),
                           get_string('average', 'grades') . " " . get_string('numeric', 'report_siteoutcomes'),
                           get_string('numberofgrades', 'grades'),
                           'header' => true);

        $totalcourses = 0;
        $totalaverage = 0;
        $totalgrades = 0;
        foreach ($reportinfo["categoryavgs"] as $categoryid => $categorydata) {
            $categoryavgsum = 0; $categorycountsum = 0;
            $coursecount = count($categorydata);
            foreach ($categorydata as $coursedata) {
                $categoryavgsum += $coursedata["avg"];
                $categorycountsum += $coursedata["count"];
            }
            $categoryavg = $categoryavgsum / $coursecount;
            $gradehtml = $scale->get_nearest_item($categoryavg);
            $cat = $DB->get_record('course_categories', array("id" => $categoryid));
            $html .= "<td class=\"cell c1\">$cat->name</td>"
                  . "<td class=\"cell c2\">$coursecount</td>"
                  . "<td class=\"cell c3\">$gradehtml ($categoryavg)</td>"
                  . "<td class=\"cell c4\">$categorycountsum</td></tr>\n";
            $csv[$row + 1] = array($cat->name,
                             $coursecount,
                             $gradehtml,
                             $categoryavg,
                             $categorycountsum,
                             'header' => false);
            $totalcourses += $coursecount;
            $totalaverage += $categoryavgsum;
            $totalgrades += $categorycountsum;
            $row++;
        }

        $html .= '</table><br /><br />';
        $html .= '<h3>' . get_string('totaltitle', 'report_siteoutcomes') . '</h3>';
        $html .= '<table class="generaltable boxaligncenter" width="90%" cellspacing="1" ' .
                 'cellpadding="5" summary="' . get_string('totaltitle', 'report_siteoutcomes') . '">' . "\n";
        $html .= '<th class="header c1" scope="col">' . get_string('courses') . '</th>';
        $html .= '<th class="header c2" scope="col">' . get_string('average', 'grades') . '</th>';
        $html .= '<th class="header c3" scope="col">' . get_string('numberofgrades', 'grades') . '</th></tr>' . "\n";

        $row++;
        $csv[$row] = array(get_string('courses'),
                           get_string('average', 'grades'),
                           get_string('average', 'grades') . " " . get_string('numeric', 'report_siteoutcomes'),
                           get_string('numberofgrades', 'grades'),
                           'header' => true);
        $totalaverage = $totalaverage / $totalcourses;
        $gradehtml = $scale->get_nearest_item($totalaverage);
        $html .= "<td class=\"cell c1\">$totalcourses</td>"
                  . "<td class=\"cell c2\">$gradehtml ($totalaverage)</td>"
                  . "<td class=\"cell c3\">$totalgrades</td></tr>\n";
        $csv[$row + 1] = array($totalcourses,
                             $gradehtml,
                             $totalaverage,
                             $totalgrades,
                             'header' => false);
        $html .= '</table>';
        echo $OUTPUT->header();
        echo $OUTPUT->heading("\"" . $reportinfo['outcome']->shortname . "\" " .
                              get_string('outcomereport', 'report_siteoutcomes'));
        $SESSION->siteoutcomes_export_values = $csv;
        echo html_writer::link(new moodle_url('export.php',
                                              array()), get_string('downloadexcel'));
        echo $html;
    }

    $event = \report_siteoutcomes\event\report_viewed::create(
        array(
            'other' => array('requestedoutcome' => $outcomeid)
        )
    );
    $event->trigger();

} else { // FORM.
    $outcomeids = $DB->get_records_select_menu('grade_outcomes', "courseid IS NULL", null, 'shortname ASC', 'id, shortname');
    $outcomeids = array(0 => get_string('selectoutcome', 'report_siteoutcomes')) + $outcomeids;

    $params = array('outcomeids' => $outcomeids);
    $mform = new \report_siteoutcomes\filter_form(null, $params);
    $filters = array();
    if ($data = $mform->get_data()) {
        $filters = (array)$data;

        if (!empty($filters['datetill'])) {
            $filters['datetill'] += DAYSECS - 1; // Set to end of the chosen day.
        }
    } else {
        $filters = array(
            'outcomeid' => optional_param('outcomeid', 0, PARAM_INT),
            'datefrom' => optional_param_array('datefrom', array(), PARAM_INT),
            'datetill' => optional_param_array('datetill', array(), PARAM_INT)
        );
    }

    $params = array('outcomeids' => $outcomeids);
    $mform = new \report_siteoutcomes\filter_form(null, $params);
    $mform->set_data($filters);

    // Print header.
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('pluginname', 'report_siteoutcomes'));
    $mform->display();
}

echo $OUTPUT->footer();
