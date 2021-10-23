<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Open Source Web Scheduler
 *
 * @package     EasyAppointments
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) 2013 - 2020, Alex Tselegidis
 * @license     http://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        http://easyappointments.org
 * @since       v1.4.0
 * ---------------------------------------------------------------------------- */

/**
 * Class Migration_modify_secratary_permissions2
 *
 * @property CI_DB_query_builder $db
 * @property CI_DB_forge $dbforge
 */
class Migration_modify_secratary_permissions2 extends CI_Migration {
    /**
     * Upgrade method.
     */
    public function up()
    {
        $this->db->query('
            UPDATE `' . $this->db->dbprefix('roles') . '`
                SET `appointments`=15 WHERE `id`=4; 
        ');

    }

    /**
     * Downgrade method.
     */
    public function down()
    {
        $this->db->query('
            UPDATE `' . $this->db->dbprefix('roles') . '`
                SET `appointments`=3 WHERE `id`=4; 
        ');

    }
}
