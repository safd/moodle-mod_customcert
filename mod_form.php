<?php

// This file is part of the customcert module for Moodle - http://moodle.org/
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
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

require_once($CFG->dirroot.'/course/moodleform_mod.php');
require_once($CFG->dirroot.'/mod/customcert/lib.php');

/**
 * Instance add/edit form.
 *
 * @package    mod
 * @subpackage customcert
 * @copyright  Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_customcert_mod_form extends moodleform_mod {

    /**
     * Form definition.
     */
    function definition() {
        global $CFG, $DB, $OUTPUT;

        $mform =& $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('name', 'customcert'), array('size' => '64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEAN);
        }
        $mform->addRule('name', null, 'required', null, 'client');

        $this->add_intro_editor(false, get_string('intro', 'customcert'));

        $mform->addElement('header', 'options', get_string('options', 'customcert'));

        $mform->addElement('text', 'requiredtime', get_string('coursetimereq', 'customcert'), array('size' => '3'));
        $mform->addHelpButton('requiredtime', 'coursetimereq', 'customcert');

        $this->standard_coursemodule_elements();

        $this->add_action_buttons();
    }

    /**
     * Some basic validation.
     *
     * @param $data
     * @param $files
     * @return array the errors that were found
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // Check that the required time entered is valid if it was entered at all.
        if (!empty($data['requiredtime'])) {
            if ((!is_number($data['requiredtime']) || $data['requiredtime'] < 0)) {
                $errors['requiredtime'] = get_string('requiredtimenotvalid', 'customcert');
            }
        }

        return $errors;
    }
}
