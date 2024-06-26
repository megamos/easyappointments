<?php defined('BASEPATH') or exit('No direct script access allowed');

// use EA\Engine\Notifications\Email as EmailClient;
// use EA\Engine\Types\Email;
// use EA\Engine\Types\Text;
// use EA\Engine\Types\Url;

/**
 * Class Notifications
 *
 * Handles 
 */
class Clg {
    /**
     * @var EA_Controller
     */
    protected $CI;

    /**
     * @var Array
     */
    private $validation_faults;

    /**
     * @var DateTime
     */
    protected $start_date;
    
    /**
     * @var DateTime
     */
    protected $end_date;
    
    /**
     * @var DateTime
     */
    protected $last_day_in_may;
    
    /**
     * @var DateTime
     */
    protected $first_day_in_september;
    
    /**
     * @var DateTime
     */
    protected $appointment_year;
    
    /**
     * CLG constructor.
     */
    public function __construct()
    {
        $this->CI =& get_instance();
        $this->CI->load->model('appointments_model');
        $this->CI->load->model('services_model');
        
        $this->validation_faults = [];

        // $this->CI->load->model('providers_model');
        // $this->CI->load->model('secretaries_model');
        // $this->CI->load->model('secretaries_model');
        // $this->CI->load->model('admins_model');
        // $this->CI->load->model('settings_model');

        // $this->CI->load->library('ics_file');
        // $this->CI->load->library('timezones');

        // $this->CI->config->load('email');
    }

    private function is_summer_appointment($start, $end) {

        $appointment_months = [$start->format("n"), $end->format("n")];
        
        if (count(array_intersect($appointment_months, [6,7,8])) > 0) {
            return true;
        }

        return false;
    }

    /**
     * Send the required notifications, related to an appointment creation/modification.
     *
     * @param array $appointment Appointment record.
     * @param bool|false $manage_mode
     */
    public function validate_appointment($appointment, $manage_mode = FALSE)
    {
        try
        {
            // Needed for several rules
            $this->start_date = date_create($appointment['start_datetime']);
            $this->end_date = date_create($appointment['end_datetime']);
            $this->appointment_year = number_format($this->start_date->format('y'));
            $this->is_during_summer = $this->is_summer_appointment($this->start_date, $this->end_date);
            $this->last_day_in_may = date_create(date("Y-m-d H:i:s", mktime(0,0,0,6,0,$this->appointment_year)));
            $this->first_day_in_september = date_create(date("Y-m-d H:i:s", mktime(0,0,0,9,1,$this->appointment_year)));

            // Run CLG validations
            $this->R0_max_one_per_room_and_day($appointment);
            $this->R1_max_one_year_prior($appointment);
            $this->R2_max_seven_days($appointment);
            $this->R3_summer_two_years_in_a_row($appointment);
            //$this->R4_exchange_day($appointment);
            $this->R5_all_rooms($appointment);
            

            return $this->validation_faults;
        }
        catch (Exception $exception)
        {
            log_message('error', $exception->getMessage());
            log_message('error', $exception->getTraceAsString());
        }
    }

    /**
     * Endast en bokning per rum och dag
     */
    private function R0_max_one_per_room_and_day($appointment) {
        try {
            $appointment['id'] = isset($appointment['id']) ? $appointment['id'] : 0;
            $service_ids = [];
            array_push($service_ids, $appointment['id_services']);
            if ($appointment['additional_rooms']) {
                foreach($appointment['additional_rooms'] as $service_id) {
                    array_push($service_ids, $service_id);
                }
            }

            $appointments = $this->CI->appointments_model->get_already_booked_services(
                $this->start_date,
                $this->end_date,
                $appointment['id'],
                $service_ids
            );

            $booked_services = [];
            foreach($appointments as $appointment) {
                array_push($booked_services, $appointment['service']['name']);
            }

            if (count($booked_services) > 0 )
            {
                array_push($this->validation_faults, lang('appointment_exists') . join(', ', $booked_services));
            }
        }
        catch(Exception $exception) {
            log_message('error', $exception->getMessage());
            log_message('error', $exception->getTraceAsString());
        }
    }

    /**
     * Bokning får ske max ett år i förväg
     * - Undantag: Bokningar av styrelsen och årsmötet samt följande högtider: jul, nyår, bröllop och dop samt 50- och 75-års födelsedagar.
     */
    private function R1_max_one_year_prior($appointment) {
        try {
            if (strtotime($appointment['start_datetime']) > (time() + (60 * 60 * 24 * 356)))  {
                
                $service_ids = [];
                array_push($service_ids, $appointment['id_services']);
                
                $includes_all_rooms = $this->CI->services_model->includes_all_rooms_service($service_ids);
                
                if (!$includes_all_rooms) {
                    array_push($this->validation_faults, "Bokning får ske max ett år i förväg");
                }
            }
        }
        catch(Exception $exception) {
            log_message('error', $exception->getMessage());
            log_message('error', $exception->getTraceAsString());
        }
    }

    /**
     * Man har rätt att boka max sju nätter under perioden juni-augusti. 
     * - Vill man boka fler nätter under denna period kan man göra så, om lediga rum finns tillgängliga, tidigast fjorton dagar innan ankomst. 
     */
    private function R2_max_seven_days($appointment) {
        try {
            // Om det är 14 dagar innan så får man boka fler nätter            
            if (strtotime($appointment['start_datetime']) < (time() + (60 * 60 * 24 * 14))) {
                return;
            }

            // Return if appointment is not during summer months
            if ($this->is_during_summer == false) {
                return;
            }

            //Check how many days the appointment is for, then add that to summer_days booked
            $summer_days_booked = 0;
            $a_interval = date_diff($this->start_date, $this->end_date);
            $appointment_days = $a_interval->Format("%a") + 1;

            //array_push($this->validation_faults, $appointment_days);
            $summer_appointments = $this->CI->appointments_model->get_batch([
                'is_main' => TRUE,
                'id_users_customer' => $appointment['id_users_customer'],
                'start_datetime >=' => $this->last_day_in_may->format('Y-m-d'),
                'end_datetime <' => $this->first_day_in_september->format('Y-m-d')
            ]);

            // Exclude the current appointment being edited
            foreach ($summer_appointments as $key => $a) {
                if ($a['id'] == $appointment['id']) {
                    unset($summer_appointments[$key]);
                }
            }

            foreach ($summer_appointments as $a) {
                $a_end_date = date_create($a['end_datetime']);

                if($a_end_date >= $this->first_day_in_september) {
                    $a_end_date = date_create(date("Y-m-d H:i:s", mktime(0,0,0,8,31)));;
                }
                
                $interval = date_diff(date_create($a['start_datetime']), $a_end_date);
                
                $summer_days_booked += $interval->Format("%a");
                //array_push($this->validation_faults,"ID: ".$a['id'].", Days:".$interval->Format("%a"));
            }

            if (($summer_days_booked + $appointment_days) > 7) {
                array_push($this->validation_faults, "Man har rätt att boka max sju nätter under perioden juni-augusti. 
                - Vill man boka fler nätter under denna period kan man göra så, om lediga rum finns tillgängliga, tidigast fjorton dagar innan ankomst. ");
            }

            //array_push($this->validation_faults,"\nTotal days: ".$summer_days_booked.", A days: ".$a_interval->Format("%a"));
        }
        catch(Exception $exception) {
            log_message('error', $exception->getMessage());
            log_message('error', $exception->getTraceAsString());
        }
    }

    /**
     * Har man bokat under perioden juni-augusti året innan kan man endast boka, under denna period, sex månader i förväg. 
     */
    private function R3_summer_two_years_in_a_row($appointment) {
        try {
            //Return if appointment is not during summer months
            if ($this->is_during_summer == false) {
                return;
            }

            // Om det är inom sex månader i förväg så får man boka          
            if ((new DateTime()) >= date_modify($this->start_date, "-6 months")) {
                return;
            }

            $start_last_year = date_modify($this->last_day_in_may, "-1 year");
            $end_last_year = date_modify($this->first_day_in_september, "-1 year");

            $last_year_summer_appointments = $this->CI->appointments_model->get_batch([
                'is_main' => TRUE,
                'id_users_customer' => $appointment['id_users_customer'],
                'start_datetime >=' => $end_last_year->format('Y-m-d'),
                'end_datetime <' => $end_last_year->format('Y-m-d')
            ]);

            if (count($last_year_summer_appointments) > 0) {
                array_push($this->validation_faults,"Eftersom du bokade under sommaren i fjol så får du endast göra sommarbokningar (Juni-Aug) tidigast 6 månader i förväg.");
            }
        }
        catch(Exception $exception) {
            log_message('error', $exception->getMessage());
            log_message('error', $exception->getTraceAsString());
        }
    }

    /**
     * Bytesdag vid trängsel: söndagar. 
     */
    private function R4_exchange_day($appointment) {
        try {
            array_push($this->validation_faults,"R4 Running...");
        }
        catch(Exception $exception) {
            log_message('error', $exception->getMessage());
            log_message('error', $exception->getTraceAsString());
        }
    }

    /**
     * Bokning av hela gården bör undvikas, speciellt under perioden juni-augusti, men kan ske efter tillstånd från styrelsen. 
     * Styrelsens beslut behövs ej vid jul, nyår, bröllop, dop och begravningar.
     */
    private function R5_all_rooms($appointment) {
        try {
            $appointment['id'] = isset($appointment['id']) ? $appointment['id'] : 0;

            /** Check if "all_room" booking and other rooms are selected as well */
            if ($appointment['additional_rooms']) {
                $service_ids = [];
                array_push($service_ids, $appointment['id_services']);
                foreach($appointment['additional_rooms'] as $service_id) {
                    array_push($service_ids, $service_id);
                }
                
                $includes_all_rooms = $this->CI->services_model->includes_all_rooms_service($service_ids);
                if ($includes_all_rooms) {
                    array_push($this->validation_faults, "Vid bokning av hela gården så kan man inte välja andra rum samtidigt. Var vänlig ta bort de andra rummen från bokningen.");
                }
            }
            $start_date = date_create($appointment['start_datetime']);
            /** Check if "all_room" booking exists during selected dates */
            $appointments = $this->CI->appointments_model->get_all_rooms_appointments(
                $start_date,
                $this->end_date,
                $appointment['id']
            );

            $booked_services = [];
            foreach($appointments as $appointment) {
                array_push($booked_services, $appointment['service']['name']);
            }

            if (count($booked_services) > 0 )
            {
                array_push($this->validation_faults, lang('appointment_exists_all_rooms') . join(', ', $booked_services));
            }
        }
        catch(Exception $exception) {
            log_message('error', $exception->getMessage());
            log_message('error', $exception->getTraceAsString());
        }
    }

    /**
     * Högtiderna påsk, midsommar, jul och nyår bokas separat efter turordning (jul och nyår är olika högtider).
     * Har man bokat någon av högtiderna året innan, kan man endast boka samma högtid sex månader i förväg. 
     */
    private function R6_holidays($appointment) {
        try {
            throw new Exception("Not implemented!");
        }
        catch(Exception $exception) {
            log_message('error', $exception->getMessage());
            log_message('error', $exception->getTraceAsString());
        }
    }

    /**
     * Jul/nyår kan bokas med max en jul/ett nyår i taget.
     */
    private function R7_xmas_or_newyears($appointment) {
        try {
            throw new Exception("Not implemented!");
        }
        catch(Exception $exception) {
            log_message('error', $exception->getMessage());
            log_message('error', $exception->getTraceAsString());
        }
    }

    /**
     * Preliminärbokningar (över flera helger, veckor etc.) för ej göras
     */
    private function R8_preliminary_booking_restrictions($appointment) {
        try {
            throw new Exception("Not implemented!");
        }
        catch(Exception $exception) {
            log_message('error', $exception->getMessage());
            log_message('error', $exception->getTraceAsString());
        }
    }

    /**
     * Från det året man fyller 18 år kan man få bo på Lilla Hyttnäs.
     */
    private function R9_age_limit($appointment) {
        try {
            throw new Exception("Not implemented!");
        }
        catch(Exception $exception) {
            log_message('error', $exception->getMessage());
            log_message('error', $exception->getTraceAsString());
        }
    }

    /**
     * Som Släktguide får man boka längre än en vecka, i uthusen.
     * Bokningsgruppen ansvarar för förläggning. Måste bokas i god tid. 
     */
    private function R10_relative_guide($appointment) {
        try {
            throw new Exception("Not implemented!");
        }
        catch(Exception $exception) {
            log_message('error', $exception->getMessage());
            log_message('error', $exception->getTraceAsString());
        }
    }
}
