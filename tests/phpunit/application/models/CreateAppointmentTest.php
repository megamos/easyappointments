<?php
/*
 * CLG: Create booking with several rooms, guests, and relatives
*/

//namespace application\models;

use PHPUnit\Framework\TestCase;

class CreateAppointmentTest extends TestCase
{
  /**
   * @var EA_Controller
   */
  protected $CI;

  public function __construct()
  {
    parent::__construct();

    $this->CI =& get_instance();
    $this->CI->load->model('appointments_model');
      
      // $this->CI->load->model('providers_model');
      // $this->CI->load->model('secretaries_model');
      // $this->CI->load->model('secretaries_model');
      // $this->CI->load->model('admins_model');
      // $this->CI->load->model('settings_model');

      // $this->CI->load->library('ics_file');
      // $this->CI->load->library('timezones');

      // $this->CI->config->load('email');
  }

  // public static function setUpBeforeClass(): void 
  // {
  //   self::$CI =& get_instance();
  //   self::$CI->load->model('appointments_model');
  // }

  public function test_creating_appointment()
  {
    //$type = new Url('http://localhost/easyappointments/index.php');
    $result = '';//$type->get();
    $this->assertIsString($result);
  }

  public function test_validation_rule_R1_max_one_year_prior()
  {
    // $record_id = $this->db->escape($this->input->post('record_id'));
    // $start_date = $this->db->escape($this->input->post('start_date'));
    // $end_date = $this->db->escape(date('Y-m-d', strtotime($this->input->post('end_date') . ' +1 day')));

    // $where_clause = $where_id . ' = ' . $record_id . '
    //     AND ((start_datetime > ' . $start_date . ' AND start_datetime < ' . $end_date . ') 
    //     or (end_datetime > ' . $start_date . ' AND end_datetime < ' . $end_date . ') 
    //     or (start_datetime <= ' . $start_date . ' AND end_datetime >= ' . $end_date . ')) 
    //     AND is_unavailable = 0
    // ';

    // $response['appointments'] = $this->appointments_model->get_batch($where_clause);

    // foreach ($response['appointments'] as &$appointment)
    // {
    //     $appointment['provider'] = $this->providers_model->get_row($appointment['id_users_provider']);
    //     $appointment['service'] = $this->services_model->get_row($appointment['id_services']);
    //     $appointment['customer'] = $this->customers_model->get_row($appointment['id_users_customer']);
    //     $appointment['visitors'] = $this->appointments_model->get_visitors($appointment['id']);
    // }


    // $exceptions = $this->clg->validate_appointment($appointment, false);

    //$this->assertInstanceOf(Appointments_model::class, Appointments_model::get_row(178));
    $this->assertIsArray($this->CI->appointments_model->get_row(178));

    //$this->assertIsString($result);
  }
}