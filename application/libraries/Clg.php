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
class Clg
{
    /**
     * @var EA_Controller
     */
    protected $CI;

    /**
     * @var Array
     */
    private $validation_faults;

    /**
     * @var array
     */
    protected $privileges;

    /**
     * @var CI_Session
     */
    protected $session;

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
     * @var bool
     */
    protected $is_during_summer;

    /**
     * @var bool
     */
    protected $is_provider;

    /**
     * CLG constructor.
     */
    public function __construct()
    {
        $this->CI = &get_instance();
        $this->CI->load->model('appointments_model');
        $this->CI->load->model('services_model');
        $this->CI->load->model('roles_model');
        $this->CI->load->model('user_model');

        $this->validation_faults = [];
    }

    private function is_summer_appointment($start, $end)
    {

        $appointment_months = [$start->format("n"), $end->format("n")];

        if (count(array_intersect($appointment_months, [6, 7, 8])) > 0) {
            return true;
        }

        return false;
    }

    /**
     * Send the required notifications, related to an appointment creation/modification.
     *
     * @param array $appointment Appointment record.
     * @param CI_Session $current_session Appointment record.
     * @param bool|false $manage_mode
     */
    public function validate_appointment($appointment, $current_session, $manage_mode = FALSE)
    {
        try {
            // Needed for several rules
            $this->session = $current_session;
            $this->privileges = $this->CI->roles_model->get_privileges($this->session->userdata('role_slug'));
            $this->is_provider = $this->session->userdata('role_slug') === DB_SLUG_PROVIDER;

            $this->start_date = date_create($appointment['start_datetime']);
            $this->end_date = date_create($appointment['end_datetime']);
            $this->appointment_year = (int)$this->start_date->format('Y');
            $this->is_during_summer = $this->is_summer_appointment($this->start_date, $this->end_date);
            $this->last_day_in_may = date_create(date("Y-m-d H:i:s", mktime(0, 0, 0, 5, 31, $this->appointment_year)));
            $this->first_day_in_september = date_create(date("Y-m-d H:i:s", mktime(0, 0, 0, 9, 1, $this->appointment_year)));

            // Run CLG validations
            $this->R0_max_one_per_room_and_day($appointment);
            $this->R1_max_one_year_prior($appointment);
            $this->R2_max_seven_days($appointment);
            $this->R3_summer_two_years_in_a_row($appointment);
            $this->R4_exchange_day($appointment);
            $this->R5_all_rooms($appointment);
            $this->R6_holidays($appointment);
            $this->R7_xmas_or_newyears($appointment);
            $this->R8_preliminary_booking_restrictions($appointment);
            $this->R9_age_limit($appointment);
            $this->R10_relative_guide($appointment);

            // Run system validations
            $this->V1_minimum_one_person_per_room($appointment);

            return $this->validation_faults;
        } catch (Exception $exception) {
            log_message('error', $exception->getMessage());
            log_message('error', $exception->getTraceAsString());
        }
    }

    /**
     * Endast en bokning per rum och dag
     */
    private function R0_max_one_per_room_and_day($appointment)
    {
        try {
            $appointment['id'] = isset($appointment['id']) ? $appointment['id'] : 0;
            $service_ids = [];

            array_push($service_ids, $appointment['id_services']);
            if ($appointment['additional_rooms']) {
                foreach ($appointment['additional_rooms'] as $service_id) {
                    array_push($service_ids, $service_id);
                }
            }

            $existing_appointments = $this->CI->appointments_model->get_already_booked_services(
                $this->start_date,
                $this->end_date,
                $appointment['id'],
                $service_ids
            );

            $booked_services = [];
            foreach ($existing_appointments as $existing_appointment) {
                array_push($booked_services, $existing_appointment['service']['name']);
            }

            if (count($booked_services) > 0) {
                array_push($this->validation_faults, lang('appointment_exists') . join(', ', $booked_services));
            }
        } catch (Exception $exception) {
            log_message('error', $exception->getMessage());
            log_message('error', $exception->getTraceAsString());
        }
    }

    /**
     * Bokning får ske max ett år i förväg
     * - Undantag: Bokningar av styrelsen och årsmötet samt följande högtider: jul, nyår, bröllop och dop samt 50- och 75-års födelsedagar.
     */
    private function R1_max_one_year_prior($appointment)
    {
        try {
            // Allow providers/husmor to break this rule
            if ($this->is_provider) {
                return;
            }

            if (strtotime($appointment['start_datetime']) > (time() + (60 * 60 * 24 * 356))) {

                $service_ids = [];
                array_push($service_ids, $appointment['id_services']);

                $includes_all_rooms = $this->CI->services_model->includes_all_rooms_service($service_ids);

                if (!$includes_all_rooms) {
                    array_push($this->validation_faults, "Bokning får ske max ett år i förväg");
                }
            }
        } catch (Exception $exception) {
            log_message('error', $exception->getMessage());
            log_message('error', $exception->getTraceAsString());
        }
    }

    /**
     * Man har rätt att boka max sju nätter under perioden juni-augusti. 
     * - Vill man boka fler nätter under denna period kan man göra så, om lediga rum finns tillgängliga, tidigast fjorton dagar innan ankomst. 
     */
    private function R2_max_seven_days($appointment)
    {
        try {
            // Allow providers/husmor to break this rule
            if ($this->is_provider) {
                return;
            }

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

                if ($a_end_date >= $this->first_day_in_september) {
                    $a_end_date = date_create(date("Y-m-d H:i:s", mktime(0, 0, 0, 8, 31)));;
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
        } catch (Exception $exception) {
            log_message('error', $exception->getMessage());
            log_message('error', $exception->getTraceAsString());
        }
    }

    /**
     * Har man bokat under perioden juni-augusti året innan kan man endast boka, under denna period, sex månader i förväg. 
     */
    private function R3_summer_two_years_in_a_row($appointment)
    {
        try {
            // Allow providers/husmor to break this rule
            if ($this->is_provider) {
                return;
            }

            //Return if appointment is not during summer months
            if ($this->is_during_summer == false) {
                return;
            }

            // Om det är inom sex månader i förväg så får man boka     
            $six_months_prior = clone $this->start_date;
            $six_months_prior->modify("-6 months");

            if ((new DateTime()) >= $six_months_prior) {
                return;
            }

            $start_last_year = clone $this->last_day_in_may;
            $start_last_year->modify("-1 year");
            $end_last_year = clone $this->first_day_in_september;
            $end_last_year->modify("-1 year");

            $last_year_summer_appointments = $this->CI->appointments_model->get_batch([
                'is_main' => TRUE,
                'id_users_customer' => $appointment['id_users_customer'],
                'start_datetime >=' => $start_last_year->format('Y-m-d'),
                'end_datetime <' => $end_last_year->format('Y-m-d')
            ]);

            if (count($last_year_summer_appointments) > 0) {
                array_push($this->validation_faults, "Eftersom du bokade under sommaren i fjol så får du endast göra sommarbokningar (Juni-Aug) tidigast 6 månader i förväg.");
            }
        } catch (Exception $exception) {
            log_message('error', $exception->getMessage());
            log_message('error', $exception->getTraceAsString());
        }
    }

    /**
     * Bytesdag vid trängsel: söndagar. 
     * This means bookings should end on Sundays to allow for changeover
     */
    private function R4_exchange_day($appointment)
    {
        try {
            // Allow providers/husmor to break this rule
            if ($this->is_provider) {
                return;
            }

            // Check if the booking is during a busy period (summer months)
            if (!$this->is_during_summer) {
                return;
            }

            // Check if end date is a Sunday (day of week 0 = Sunday)
            $end_day_of_week = (int)$this->end_date->format('w');

            // If it's not ending on a Sunday, it might cause issues during busy periods
            if ($end_day_of_week !== 0) {
                // Check if there are other bookings that might conflict
                $service_ids = [];
                array_push($service_ids, $appointment['id_services']);
                if (isset($appointment['additional_rooms']) && $appointment['additional_rooms']) {
                    foreach ($appointment['additional_rooms'] as $service_id) {
                        array_push($service_ids, $service_id);
                    }
                }

                // Check if there's high demand (multiple bookings around this time)
                $week_start = clone $this->start_date;
                $week_start->modify('monday this week');
                $week_end = clone $week_start;
                $week_end->modify('+7 days');

                $weekly_bookings = $this->CI->appointments_model->get_batch([
                    'is_main' => TRUE,
                    'start_datetime >=' => $week_start->format('Y-m-d H:i:s'),
                    'end_datetime <=' => $week_end->format('Y-m-d H:i:s')
                ]);

                // If there are many bookings this week, enforce Sunday changeover
                if (count($weekly_bookings) >= 3) {
                    array_push($this->validation_faults, "Under trängselperioder (sommaren) bör bokningar avslutas på söndagar för att underlätta byte mellan gäster.");
                }
            }
        } catch (Exception $exception) {
            log_message('error', $exception->getMessage());
            log_message('error', $exception->getTraceAsString());
        }
    }

    /**
     * Bokning av hela gården bör undvikas, speciellt under perioden juni-augusti, men kan ske efter tillstånd från styrelsen. 
     * Styrelsens beslut behövs ej vid jul, nyår, bröllop, dop och begravningar.
     */
    private function R5_all_rooms($appointment)
    {
        try {
            $appointment['id'] = isset($appointment['id']) ? $appointment['id'] : 0;

            /** Check if "all_room" booking and other rooms are selected as well */
            if ($appointment['additional_rooms']) {
                $service_ids = [];
                array_push($service_ids, $appointment['id_services']);
                foreach ($appointment['additional_rooms'] as $service_id) {
                    array_push($service_ids, $service_id);
                }

                $includes_all_rooms = $this->CI->services_model->includes_all_rooms_service($service_ids);
                if ($includes_all_rooms) {
                    array_push($this->validation_faults, "Vid bokning av hela gården så kan man inte välja andra rum samtidigt. Var vänlig ta bort de andra rummen från bokningen.");
                }
            }
            $start_date = date_create($appointment['start_datetime']);
            /** Check if "all_room" booking exists during selected dates */
            $all_room_appointments = $this->CI->appointments_model->get_all_rooms_appointments(
                $start_date,
                $this->end_date,
                $appointment['id']
            );

            $booked_services = [];
            foreach ($all_room_appointments as $existing_appointment) {
                array_push($booked_services, $existing_appointment['service']['name']);
            }

            if (count($booked_services) > 0) {
                array_push($this->validation_faults, lang('appointment_exists_all_rooms') . join(', ', $booked_services));
            }
        } catch (Exception $exception) {
            log_message('error', $exception->getMessage());
            log_message('error', $exception->getTraceAsString());
        }
    }

    /**
     * Högtiderna påsk, midsommar, jul och nyår bokas separat efter turordning (jul och nyår är olika högtider).
     * Har man bokat någon av högtiderna året innan, kan man endast boka samma högtid sex månader i förväg. 
     */
    private function R6_holidays($appointment)
    {
        try {
            // Allow providers/husmor to break this rule
            if ($this->is_provider) {
                return;
            }

            // Define holiday service names
            $holiday_services = ['Jul', 'Nyår', 'Påsk', 'Midsommar'];

            // Check if this booking includes any holiday services
            $service = $this->CI->services_model->get_row($appointment['id_services']);
            $is_holiday_booking = in_array($service['name'], $holiday_services);

            if (!$is_holiday_booking) {
                return;
            }

            // Check if user booked the same holiday last year
            $last_year = $this->appointment_year - 1;
            $user_id = $appointment['id_users_customer'];

            // Get the same holiday service ID
            $holiday_name = $service['name'];

            // Check for bookings of the same holiday last year
            $last_year_holiday_bookings = $this->CI->db
                ->select('ea_appointments.*')
                ->from('ea_appointments')
                ->join('ea_services', 'ea_appointments.id_services = ea_services.id')
                ->where('ea_appointments.id_users_customer', $user_id)
                ->where('ea_services.name', $holiday_name)
                ->where('YEAR(ea_appointments.start_datetime)', $last_year)
                ->get()
                ->result_array();

            if (count($last_year_holiday_bookings) > 0) {
                // They booked this holiday last year, check if booking at least 6 months in advance
                $six_months_prior = clone $this->start_date;
                $six_months_prior->modify("-6 months");

                if ((new DateTime()) > $six_months_prior) {
                    array_push($this->validation_faults, "Du bokade " . $holiday_name . " förra året. Du kan endast boka samma högtid tidigast sex månader i förväg.");
                }
            }
        } catch (Exception $exception) {
            log_message('error', $exception->getMessage());
            log_message('error', $exception->getTraceAsString());
        }
    }

    /**
     * Jul/nyår kan bokas med max en jul/ett nyår i taget.
     * TODO: Check if it's roughly the same ppl booking both holidays but booker is different
     */
    private function R7_xmas_or_newyears($appointment)
    {
        try {
            $user_id = $appointment['id_users_customer'];
            $is_booking_both = $this->CI->appointments_model->is_booking_xmas_and_new_years($user_id, $appointment);

            if ($is_booking_both) {
                $holidays = ['Jul', 'Nyår'];
                $appointment_year = date('Y', strtotime($appointment['start_datetime']));

                $this->CI->db->select('appointments.*');
                $this->CI->db->from('appointments');
                $this->CI->db->join('services', 'appointments.id_services = services.id');
                $this->CI->db->where('appointments.id_users_customer', $user_id);
                $this->CI->db->where_in('services.name', $holidays);
                $this->CI->db->where('YEAR(ea_appointments.start_datetime)', $appointment_year);
                $existing_appointments = $this->CI->db->get()->result_array();

                foreach ($existing_appointments as $existing_appointment) {
                    if (!$this->CI->appointments_model->is_less_than_40_percent_overlap($appointment, $existing_appointment['id'])) {
                        array_push($this->validation_faults, "Jul/nyår kan bokas med max en jul/ett nyår i taget och mindre än 40% av gästerna får vara samma.");
                        break;
                    }
                }
            }
        } catch (Exception $exception) {
            log_message('error', $exception->getMessage());
            log_message('error', $exception->getTraceAsString());
        }
    }

    /**
     * Preliminärbokningar (över flera helger, veckor etc.) för ej göras
     */
    private function R8_preliminary_booking_restrictions($appointment)
    {
        try {
            // Allow providers/husmor to break this rule
            if ($this->is_provider) {
                return;
            }

            $start_date = new DateTime($appointment['start_datetime']);
            $end_date = new DateTime($appointment['end_datetime']);
            $interval = $start_date->diff($end_date);
            $days = $interval->days + 1; // Include the start day

            if ($days > 7) {
                array_push($this->validation_faults, "Bokningar får inte överstiga 7 dagar.");
            }
        } catch (Exception $exception) {
            log_message('error', $exception->getMessage());
            log_message('error', $exception->getTraceAsString());
        }
    }

    /**
     * Från det året man fyller 18 år kan man få bo på Lilla Hyttnäs.
     */
    private function R9_age_limit($appointment)
    {
        try {
            $user_id = $appointment['id_users_customer'];
            $user = $this->CI->user_model->get_user($user_id);

            if (isset($user['birthday'])) {
                $birthday = new DateTime($user['birthday']);
                $today = new DateTime();
                $age = $today->diff($birthday)->y;

                if ($age < 18) {
                    array_push($this->validation_faults, "Från det året man fyller 18 år kan man få bo på Lilla Hyttnäs.");
                }
            } else {
                array_push($this->validation_faults, "Födelsedatum saknas för användaren.");
            }
        } catch (Exception $exception) {
            log_message('error', $exception->getMessage());
            log_message('error', $exception->getTraceAsString());
        }
    }

    /**
     * Som Släktguide får man boka längre än en vecka, i uthusen.
     * Bokningsgruppen ansvarar för förläggning. Måste bokas i god tid. 
     * NOTE: This is primarily handled by R2_max_seven_days as providers/husmor can override.
     * This adds additional checks for släktguide-specific bookings.
     */
    private function R10_relative_guide($appointment)
    {
        try {
            // Check if user has släktguide role/permission
            $user_id = $appointment['id_users_customer'];
            $user = $this->CI->user_model->get_user($user_id);

            // Check if this is a släktguide booking (longer than 7 days in outhouses)
            $start_date = new DateTime($appointment['start_datetime']);
            $end_date = new DateTime($appointment['end_datetime']);
            $interval = $start_date->diff($end_date);
            $days = $interval->days + 1;

            if ($days <= 7) {
                return; // Normal booking, no special validation needed
            }

            // Check if the service is an outhouse (uthus)
            $service = $this->CI->services_model->get_row($appointment['id_services']);
            $service_name = strtolower($service['name']);

            // Check if it's an outhouse service (you may need to adjust this logic)
            $is_outhouse = (strpos($service_name, 'uthus') !== false ||
                strpos($service_name, 'loge') !== false ||
                strpos($service_name, 'stuga') !== false);

            if (!$is_outhouse) {
                array_push($this->validation_faults, "Släktguide-bokningar över en vecka är endast tillåtna i uthusen.");
                return;
            }

            // Check if booking is made well in advance (at least 1 month)
            $one_month_in_advance = clone $start_date;
            $one_month_in_advance->modify("-1 month");

            if ((new DateTime()) > $one_month_in_advance && !$this->is_provider) {
                array_push($this->validation_faults, "Släktguide-bokningar måste göras i god tid (minst 1 månad i förväg).");
            }
        } catch (Exception $exception) {
            log_message('error', $exception->getMessage());
            log_message('error', $exception->getTraceAsString());
        }
    }

    /**
     ************ SYSTEMETS EGNA VALIDERINGAR (Ej genomklubbade regler) ************
     */

    /**
     * En bokning måste åtminstånde ha lika många personer som rum.
     */
    private function V1_minimum_one_person_per_room($appointment)
    {
        try {
            // Get visiting relatives
            $relatives = [];
            if (isset($appointment['relatives'])) {
                $relatives = $appointment['relatives'];
            }

            // Get visiting guests
            $guests = [];
            if (isset($appointment['guests'])) {
                $guests = $appointment['guests'];
            }

            // Count the number of rooms in the booking
            $room_count = 1; // Start with the main room
            if (isset($appointment['additional_rooms'])) {
                $room_count += count($appointment['additional_rooms']);
            }

            // Count the number of people (relatives + guests + 1 for the person making the booking)
            $people_count = count($relatives) + count($guests) + 1;

            // Check if the number of people is fewer than the number of rooms
            if ($people_count < $room_count) {
                array_push($this->validation_faults, "En bokning måste åtminstånde ha lika många personer som rum.");
            }

            // Fetch the service details to check if is_all_rooms is set
            $service = $this->CI->services_model->get_row($appointment['id_services']);
            $is_all_rooms = isset($service['is_all_rooms']) ? $service['is_all_rooms'] : false;

            // Check if 'is_all_rooms' is set and there are at least 2 relatives
            if ($is_all_rooms && count($relatives) < 2) {
                array_push($this->validation_faults, "När hela gården bokas måste minst 2 släktingar läggas till i bokningen.");
            }
        } catch (Exception $exception) {
            log_message('error', $exception->getMessage());
            log_message('error', $exception->getTraceAsString());
        }
    }
}
