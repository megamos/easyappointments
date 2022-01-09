<?php defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Class Migration_Create_appointment_isallrooms_column
 *
 * @property CI_DB_query_builder $db
 * @property CI_DB_forge $dbforge
 */
class Migration_Create_appointment_isallrooms_column extends CI_Migration {
    /**
     * Upgrade method.
     */
    public function up()
    {
        $this->db->query('
            ALTER TABLE `' . $this->db->dbprefix('appointments') . '`
             ADD COLUMN `is_all_rooms` BOOLEAN NOT NULL DEFAULT 0 AFTER `hash`; 
        ');

        $this->db->query('
            ALTER TABLE `' . $this->db->dbprefix('appointments') . '`
             ADD COLUMN `id_main` int(11) DEFAULT 0 AFTER `id`; 
        ');
    }

    /**
     * Downgrade method.
     */
    public function down()
    {
        $this->db->query('
            ALTER TABLE `' . $this->db->dbprefix('appointments') . '`
                DROP COLUMN `is_all_rooms`; 
        ');
        
        $this->db->query('
            ALTER TABLE `' . $this->db->dbprefix('appointments') . '`
                DROP COLUMN `id_main`; 
        ');
    }
}


