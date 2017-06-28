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
 * The site outcomes report filter form
 *
 * @package    report_siteoutcomes
 * @since      Moodle 3.3
 * @copyright  2017 Matthew Davidson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_siteoutcomes;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir.'/formslib.php');

/**
 * Form for site outcomes filters
 *
 * @since      Moodle 3.3
 * @package    report_siteoutcomes
 * @copyright  2017 Matthew Davidson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class filter_form extends \moodleform {

    /**
     * Definition of the Mform for filters displayed in the report.
     */
    public function definition() {

        $mform = $this->_form;
        $outcomeids = $this->_customdata['outcomeids'];

        $mform->addElement('select', 'outcomeid', get_string('courseoutcomes', 'report_siteoutcomes'), $outcomeids);
        $mform->addElement('date_selector', 'datefrom', get_string('datefrom', 'report_siteoutcomes'), array('optional' => true));
        $mform->addElement('date_selector', 'datetill', get_string('dateto', 'report_siteoutcomes'), array('optional' => true));

        // Add a submit button.
        $mform->addElement('submit', 'submitbutton', get_string('submit'));
    }
}
