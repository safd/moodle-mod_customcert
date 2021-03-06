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

require_once($CFG->dirroot . '/course/moodleform_mod.php');
require_once($CFG->dirroot . '/mod/customcert/lib.php');
require_once($CFG->dirroot . '/mod/customcert/colourpicker.php');

MoodleQuickForm::registerElementType('customcert_colourpicker', $CFG->dirroot . '/mod/customcert/colourpicker.php', 'MoodleQuickForm_customcert_colourpicker');

/**
 * Instance add/edit form.
 *
 * @package    mod
 * @subpackage customcert
 * @copyright  Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_customcert_edit_form extends moodleform {

    /**
     * The instance id.
     */
    private $id = null;

    /**
     * The course.
     */
    private $course = null;

    /**
     * The total number of pages for this cert.
     */
    private $numpages = 1;

    /**
     * The filemanager options.
     */
    private $filemanageroptions = array();

    /**
     * The array of element objects loaded on this form.
     */
    private $elementobjects = array();

    /**
     * Form definition.
     */
    function definition() {
        global $CFG, $DB, $OUTPUT;

        $this->id = $this->_customdata['customcertid'];
        $this->filemanageroptions = array('maxbytes' => $this->_customdata['course']->maxbytes,
                                          'subdirs' => 1,
                                          'accepted_types' => 'image');

        $mform =& $this->_form;

        // Get the number of pages for this module.
        if ($pages = $DB->get_records('customcert_pages', array('customcertid' => $this->id), 'pagenumber')) {
            $this->numpages = count($pages);
            foreach ($pages as $p) {
                $this->add_customcert_page_elements($p);
            }
        } else {
            $this->add_customcert_page_elements();
        }

        $mform->closeHeaderBefore('addcertpage');

        $mform->addElement('submit', 'addcertpage', get_string('addcertpage', 'customcert'));

        $mform->addElement('header', 'uploadimage', get_string('uploadimage', 'customcert'));

        $mform->addElement('filemanager', 'customcertimage', get_string('uploadimage', 'customcert'), '', $this->filemanageroptions);

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->setDefault('id', $this->id);
        $mform->addElement('hidden', 'cmid');
        $mform->setType('cmid', PARAM_INT);
        $mform->setDefault('cmid', $this->_customdata['cmid']);

        $this->add_action_buttons();
    }

    /**
     * Fill in the current page data for this certificate.
     */
    function definition_after_data() {
        global $DB;

        $mform = $this->_form;

        // Editing existing instance - copy existing files into draft area.
        $draftitemid = file_get_submitted_draft_itemid('customcertimage');
        file_prepare_draft_area($draftitemid, context_system::instance()->id, 'mod_customcert', 'image', 0, $this->filemanageroptions);
        $element = $mform->getElement('customcertimage');
        $element->setValue($draftitemid);

        // Check that we are updating a current customcert.
        if ($this->id) {
            // Get the pages for this certificate.
            if ($pages = $DB->get_records('customcert_pages', array('customcertid' => $this->id))) {
                // Loop through the pages.
                foreach ($pages as $p) {
                    // Set the orientation.
                    $element = $mform->getElement('orientation_' . $p->id);
                    $element->setValue($p->orientation);
                    // Set the width.
                    $element = $mform->getElement('width_' . $p->id);
                    $element->setValue($p->width);
                    // Set the height.
                    $element = $mform->getElement('height_' . $p->id);
                    $element->setValue($p->height);
                }
            }
        }
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

        // Go through the data and check any width or height values.
        foreach ($data as $key => $value) {
            if (strpos($key, 'width_') !== false) {
                $page = str_replace('width_', '', $key);
                // Validate that the width is a valid value.
                if (!isset($data['width_' . $page]) || !is_number($data['width_' . $page])) {
                    $errors['width_' . $page] = get_string('widthnotvalid', 'customcert');
                }
            }
            if (strpos($key, 'height_') !== false) {
                $page = str_replace('height_', '', $key);
                // Validate that the height is a valid value.
                if (!isset($data['height_' . $page]) || !is_number($data['height_' . $page])) {
                    $errors['height_' . $page] = get_string('heightnotvalid', 'customcert');
                }
            }
        }

        // Go through each element and perform validation.
        if (!empty($this->elementobjects)) {
            foreach ($this->elementobjects as $e) {
                $errors += $e->validate_form_elements($data, $files);
            }
        }

        return $errors;
    }

    /**
     * Adds the page elements to the form.
     *
     * @param stdClass $page the customcert page
     **/
    private function add_customcert_page_elements($page = null) {
        global $CFG, $DB, $OUTPUT;

        // Create the form object.
        $mform =& $this->_form;

        // Get the elements that are available
        $elementsavailable = customcert_get_elements();

        // If page is null we are adding a customcert, not editing one, so set pageid to 1.
        if (is_null($page)) {
            $pageid = 1;
            $pagenum = 1;
        } else {
            $pageid = $page->id;
            $pagenum = $page->pagenumber;
        }

        $mform->addElement('header', 'page_' . $pageid, get_string('page', 'customcert', $pagenum));

        // Place the ordering arrows.
        // Only display the move up arrow if it is not the first.
        if ($pagenum > 1) {
            $url = new moodle_url('/mod/customcert/edit.php', array('cmid' => $this->_customdata['cmid'], 'moveup' => $pageid));
            $mform->addElement('html', $OUTPUT->action_icon($url, new pix_icon('t/up', get_string('moveup'))));
        }
        // Only display the move down arrow if it is not the last.
        if ($pagenum < $this->numpages) {
            $url = new moodle_url('/mod/customcert/edit.php', array('cmid' => $this->_customdata['cmid'], 'movedown' => $pageid));
            $mform->addElement('html', $OUTPUT->action_icon($url, new pix_icon('t/down', get_string('movedown'))));
        }

        $orientationoptions = array('L' => get_string('landscape', 'customcert'),
                                    'P' => get_string('portrait', 'customcert'));
        $mform->addElement('select', 'orientation_' . $pageid, get_string('orientation', 'customcert'), $orientationoptions);
        $mform->setDefault('orientation_' . $pageid, 'P');
        $mform->addHelpButton('orientation_' . $pageid, 'orientation', 'customcert');

        $mform->addElement('text', 'width_' . $pageid, get_string('width', 'customcert'));
        $mform->addRule('width_' . $pageid, null, 'required', null, 'client');
        $mform->addHelpButton('width_' . $pageid, 'width', 'customcert');

        $mform->addElement('text', 'height_' . $pageid, get_string('height', 'customcert'));
        $mform->addRule('height_' . $pageid, null, 'required', null, 'client');
        $mform->addHelpButton('height_' . $pageid, 'height', 'customcert');

        $group = array();
        $group[] = $mform->createElement('select', 'element_' . $pageid, '', $elementsavailable);
        $group[] = $mform->createElement('submit', 'addelement_' . $pageid, get_string('addelement', 'customcert'));
        $mform->addElement('group', 'elementgroup', '', $group, '', false);

        // Add option to delete this page if it is not the first page.
        if ($pagenum > 1) {
            $mform->addElement('html', html_writer::start_tag('div', array('class' => 'deletecertpage')));
            $mform->addElement('submit', 'deletecertpage_' . $pageid, get_string('deletecertpage', 'customcert'));
            $mform->addElement('html', html_writer::end_tag('div'));
        }

        // Check if there are elements to add.
        if ($elements = $DB->get_records('customcert_elements', array('pageid' => $pageid), 'id ASC')) {
            // Loop through and add the ones present.
            foreach ($elements as $element) {
                $classfile = "{$CFG->dirroot}/mod/customcert/elements/{$element->element}/lib.php";
                // It's possible this element was added to the database then the folder was deleted, if
                // this is the case we do not want to render these elements as an error will occur.
                if (file_exists($classfile)) {
                    // Add element header.
                    $mform->addElement('header', 'headerelement_' . $element->id, get_string('page', 'customcert', $pagenum) . " - " .
                        get_string('pluginname', 'customcertelement_' . $element->element));
                    // Add the page number to the element so we can use within the element.
                    $element->pagenum = $pagenum;
                    // Get the classname.
                    $classname = "customcert_element_{$element->element}";
                    $e = new $classname($element);
                    $e->render_form_elements($mform);
                    // Add this to the objects array.
                    $this->elementobjects[] = $e;
                    // Add submit button to delete this.
                    $mform->addElement('submit', 'deleteelement_' . $element->id, get_string('delete', 'customcert'));
                }
            }
        }
    }
}
