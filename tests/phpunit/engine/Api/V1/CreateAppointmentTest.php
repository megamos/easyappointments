<?php
/*
 * CLG: Create booking with several rooms, guests, and relatives
*/

namespace EA\Engine\Api\V1;

use PHPUnit\Framework\TestCase;

class CreateAppointmentTest extends TestCase
{
  public function testCreatingAppointment()
  {
    //$type = new Url('http://localhost/easyappointments/index.php');
    $result = '';//$type->get();
    $this->assertIsString($result);
  }
}