<?php defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Class Migration_Create_appointment_visitors_table
 *
 * @property CI_DB_query_builder $db
 * @property CI_DB_forge $dbforge
 */
class Migration_Create_appointment_visitors_table extends CI_Migration {
    /**
     * Upgrade method.
     */
    public function up()
    {
        $this->dbforge->add_field([
            'id' => [
                'type' => 'INT',
                'constraint' => '11',
                'unsigned' => TRUE,
                'auto_increment' => TRUE
            ],
            'id_appointment' => [
                'type' => 'INT',
                'constraint' => '11',
                'unsigned' => TRUE,
                'null' => FALSE
            ],
            'name' => [
                'type' => 'VARCHAR',
                'constraint' => '256',
                'null' => FALSE
            ],
            'is_relative' => [
                'type' => 'TINYINT',
                'constraint' => '4',
                'null' => FALSE
            ],
        ]);

        $this->dbforge->add_key('id', TRUE);
        $this->dbforge->add_key('id_appointment');
        $this->dbforge->create_table('appointment_visitors', TRUE, ['engine' => 'InnoDB']);

        $this->db->query('
            ALTER TABLE `' . $this->db->dbprefix('appointment_visitors') . '`
              ADD CONSTRAINT `fk_' . $this->db->dbprefix('appointment_visitors') . '_ibfk_2` FOREIGN KEY (`id_appointment`) REFERENCES `' . $this->db->dbprefix('appointments') . '` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
        ');
    }

    /**
     * Downgrade method.
     */
    public function down()
    {
        $this->db->query('ALTER TABLE `' . $this->db->dbprefix('appointment_visitors') . '` DROP FOREIGN KEY `' . $this->db->dbprefix('appointment_visitors') . '_ibfk_2`');
        
        $this->dbforge->drop_table('appointment_visitors');
    }
}
