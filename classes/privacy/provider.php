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
 * Privacy Subsystem implementation.
 *
 * @package enrol_arlo
 * @author  Nathan Nguyen <nathannguyen@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_arlo\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\context;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;

defined('MOODLE_INTERNAL') || die();

class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param   int $userid The user to search.
     * @return  contextlist   $contextlist  The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $sql1 = "SELECT c.id
                  FROM {enrol_arlo_contact} eac
                  JOIN {context} c ON c.contextlevel = :contextlevel AND c.instanceid = eac.userid
                 WHERE eac.userid = :userid";

        $sql2 = "SELECT c.id
                  FROM {enrol_arlo_emailqueue} eae
                  JOIN {context} c ON c.contextlevel = :contextlevel AND c.instanceid = eae.userid
                 WHERE eae.userid = :userid";

        $sql3 = "SELECT c.id
                  FROM {enrol_arlo_registration} ear
                  JOIN {context} c ON c.contextlevel = :contextlevel AND c.instanceid = ear.userid
                 WHERE ear.userid = :userid";

        $params = ['contextlevel' => CONTEXT_USER, 'userid' => $userid];

        $contextlist = new contextlist();
        $contextlist->set_component('enrol_arlo');
        $contextlist->add_from_sql($sql1, $params);
        $contextlist->add_from_sql($sql2, $params);
        $contextlist->add_from_sql($sql3, $params);

        return $contextlist;
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param   approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        $contexts = $contextlist->get_contexts();
        if (count($contexts) == 0) {
            return;
        }
        $context = reset($contexts);
        if ($context->contextlevel !== CONTEXT_USER) {
            return;
        }
        $userid = $context->instanceid;

        $subcontext = [
            get_string('pluginname', 'enrol_arlo'),
            get_string('privacy:metadata:mydata', 'enrol_arlo')
        ];

        $sql1 = "SELECT *
                  FROM {enrol_arlo_contact}
                 WHERE userid = :userid
              ORDER BY timecreated";

        $sql2 = "SELECT *
                  FROM {enrol_arlo_emailqueue}
                 WHERE userid = :userid
              ORDER BY timecreated";

        $sql3 = "SELECT *
                  FROM {enrol_arlo_registration}
                 WHERE userid = :userid
              ORDER BY timecreated";

        $params = ['userid' => $userid];

        $contact = $DB->get_records_sql($sql1, $params);
        $queue = $DB->get_records_sql($sql2, $params);
        $registration = $DB->get_records_sql($sql3, $params);

        $data = (object) [
            'contact' => $contact,
            'queue' => $queue,
            'registration' => $registration,
        ];

        writer::with_context($context)->export_data($subcontext, $data);

    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param   context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if ($context->contextlevel !== CONTEXT_USER) {
            return;
        }
        $userid = $context->instanceid;

        $DB->delete_records('enrol_arlo_contact', ['userid' => $userid]);
        $DB->delete_records('enrol_arlo_emailqueue', ['userid' => $userid]);
        $DB->delete_records('enrol_arlo_registration', ['userid' => $userid]);
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param   approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        $contexts = $contextlist->get_contexts();
        if (count($contexts) == 0) {
            return;
        }
        $context = reset($contexts);

        if ($context->contextlevel !== CONTEXT_USER) {
            return;
        }
        $userid = $context->instanceid;

        $DB->delete_records('enrol_arlo_contact', ['userid' => $userid]);
        $DB->delete_records('enrol_arlo_emailqueue', ['userid' => $userid]);
        $DB->delete_records('enrol_arlo_registration', ['userid' => $userid]);
    }

    /**
     * Returns meta data about this system.
     *
     * @param   collection $collection The initialised collection to add items to.
     * @return  collection     A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('enrol_arlo_contact', [
            'userid' => 'privacy:metadata:userid',
            'firstname' => 'privacy:metadata:firstname',
            'lastname' => 'privacy:metadata:lastname',
            'email' => 'privacy:metadata:email',
            'phonework' => 'privacy:metadata:phonework',
            'phonemobile' => 'privacy:metadata:phonemobile',
        ], 'privacy:metadata:enrol_arlo_contact');

        $collection->add_database_table('enrol_arlo_emailqueue', [
            'userid' => 'privacy:metadata:userid',
            'area' => 'privacy:metadata:area',
            'type' => 'privacy:metadata:type',
            'status' => 'privacy:metadata:status',
            'extra' => 'privacy:metadata:extra',
        ], 'privacy:metadata:enrol_arlo_emailqueue');

        $collection->add_database_table('enrol_arlo_registration', [
            'userid' => 'privacy:metadata:userid',
            'attendance' => 'privacy:metadata:attendance',
            'grade' => 'privacy:metadata:grade',
            'outcome' => 'privacy:metadata:outcome',
            'lastactivity' => 'privacy:metadata:lastactivity',
            'progressstatus' => 'privacy:metadata:progressstatus',
            'progresspercent' => 'privacy:metadata:progresspercent',
            'sourcestatus' => 'privacy:metadata:sourcestatus',
        ], 'privacy:metadata:enrol_arlo_registration');

        return $collection;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param   userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if (!$context instanceof \context_user) {
            return;
        }

        $params = ['contextid' => $context->id, 'contextlevel' => CONTEXT_USER];

        $sql1 = "SELECT c.id
                  FROM {enrol_arlo_contact} eac
                  JOIN {context} c ON c.contextlevel = :contextlevel AND c.instanceid = eac.userid
                 WHERE c.id = :contextid";

        $sql2 = "SELECT c.id
                  FROM {enrol_arlo_emailqueue} eae
                  JOIN {context} c ON c.contextlevel = :contextlevel AND c.instanceid = eae.userid
                 WHERE c.id = :contextid";

        $sql3 = "SELECT c.id
                  FROM {enrol_arlo_registration} ear
                  JOIN {context} c ON c.contextlevel = :contextlevel AND c.instanceid = ear.userid
                 WHERE c.id = :contextid";

        $userlist->add_from_sql('userid', $sql1, $params);
        $userlist->add_from_sql('userid', $sql2, $params);
        $userlist->add_from_sql('userid', $sql3, $params);
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param   approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();

        if ($context instanceof \context_user) {
            $userid = $context->instanceid;
            $DB->delete_records('enrol_arlo_contact', ['userid' => $userid]);
            $DB->delete_records('enrol_arlo_emailqueue', ['userid' => $userid]);
            $DB->delete_records('enrol_arlo_registration', ['userid' => $userid]);
        }
    }
}
