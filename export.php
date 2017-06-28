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
 * The site outcomes report export
 *
 * @package    report_siteoutcomes
 * @since      Moodle 3.3
 * @copyright  2017 Matthew Davidson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir . '/csvlib.class.php');

$url = new moodle_url('/report/siteoutcomes/export.php');
$PAGE->set_url($url);

$csv = $SESSION->siteoutcomes_export_values;

$downloadfilename = clean_filename("outcome_export");
$csvexport = new csv_export_writer();
$csvexport->set_filename($downloadfilename);
$i = 0;
foreach ($csv as $row) {
    if ($row["header"] && $i > 0) {
        $csvexport->add_data(array());
    }

    unset($row['header']);

    $csvexport->add_data($row);
    $i++;
}

$csvexport->download_file();
