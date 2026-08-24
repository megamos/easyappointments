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
     * @var bool True när det generiska tekniska felet redan lagts till för den här körningen.
     */
    private $technical_fault_pushed;

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
     * @deprecated Sommarfönstret börjar 1 juni, se $first_day_in_june. Behålls bara för bakåtkompatibilitet.
     */
    protected $last_day_in_may;

    /**
     * @var DateTime
     */
    protected $first_day_in_june;
    
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
     * @var bool
     */
    protected $is_admin;
    
    /**
     * CLG constructor.
     */
    public function __construct()
    {
        $this->CI =& get_instance();
        $this->CI->load->model('appointments_model');
        $this->CI->load->model('services_model');
        $this->CI->load->model('roles_model');
        
        $this->validation_faults = [];
        $this->technical_fault_pushed = FALSE;

        // $this->CI->load->model('providers_model');
        // $this->CI->load->model('secretaries_model');
        // $this->CI->load->model('secretaries_model');
        // $this->CI->load->model('admins_model');
        // $this->CI->load->model('settings_model');

        // $this->CI->load->library('ics_file');
        // $this->CI->load->library('timezones');

        // $this->CI->config->load('email');
    }

    /**
     * Lägger till det generiska tekniska felet (en gång) så att bokningen stoppas
     * när en regel inte kunde utvärderas.
     *
     * @param Throwable|NULL $exception
     */
    private function push_technical_fault($exception = NULL) {
        $reference = date('ymd-His') . '-' . substr(md5(uniqid('', TRUE)), 0, 4);

        log_message('error', 'CLG regelkontroll ref=' . $reference . ' '
            . ($exception !== NULL ? $exception->getMessage() : 'regelkontrollen kunde inte genomföras'));

        if ($exception !== NULL) {
            log_message('error', $exception->getTraceAsString());
        }

        // Visa bara ett tekniskt fel per bokningsförsök, men logga varje förekomst.
        if ($this->technical_fault_pushed) {
            return;
        }

        $this->technical_fault_pushed = TRUE;
        array_push($this->validation_faults, $this->technical_fault_message($reference));
    }

    /**
     * Generiskt fel som visas när en regelkontroll inte kunde genomföras.
     * Bokningen ska då stoppas (fail closed) - aldrig släppas igenom okontrollerad.
     *
     * Texten byggs i technical_fault_message() eftersom den innehåller en felkod som
     * loggas tillsammans med felet - en klasskonstant kan inte interpolera.
     * Meddelandet ska förbli generiskt: inga tabellnamn, ingen exception-text, inga id:n.
     *
     * @param string $reference Kort felkod som också hamnar i loggen.
     * @return string
     */
    private function technical_fault_message($reference) {
        return "Något gick fel när vi kontrollerade bokningsreglerna, så bokningen kunde inte sparas."
            . " Försök gärna igen om en stund. Om det inte hjälper, hör av dig till bokningsgruppen"
            . " och ange felkod " . $reference . ", så kan de leta upp vad som hände.";
    }

    /**
     * Böjer ett antal så att meddelandena inte skriver "1 nätter".
     *
     * @param int $count
     * @param string $singular
     * @param string $plural
     * @return string
     */
    private function plural_count($count, $singular, $plural) {
        return (int)$count . ' ' . ((int)$count === 1 ? $singular : $plural);
    }

    /**
     * Rum som ligger i uthusen. Släktguidens längre vistelser får bara bokas här.
     *
     * Kategorierna i ea_service_categories går INTE att lita på: de sattes en gång med
     * ett blint id-intervall (id<9 => Privat, 9..18 => Stora Huset) och inte efter hus.
     * Tre uthus hamnade därför i "Stora Huset", bland dem Guidestugan. Listan nedan är
     * avstämd mot CLGBooking.Core/Data/EstateCanon.cs.
     *
     * Tjänster 19-27 är evenemang (Bröllop, Jul, Påsk ...) och saknas medvetet här -
     * en guidevistelse bokas på ett rum, aldrig på hela gården.
     *
     * @var array
     */
    private static $outbuilding_service_ids = [
        1,  // Solhöjden
        2,  // Mörtstugan
        3,  // Soluppgången
        4,  // Ungkarlshotellet
        5,  // Fiskarboden
        6,  // Bagarstugan
        7,  // Härbret
        8,  // Svalboet
        16, // Guidestugan
        17, // Sågbacken Studierummet
        18, // Sågbacken Inre rum
    ];

    /**
     * Samtliga tjänste-id:n i bokningen: huvudtjänsten plus eventuella extrarum.
     *
     * @param array $appointment
     * @return array
     */
    private function booked_service_ids($appointment) {
        $service_ids = $this->get_main_service_ids($appointment);

        // is_array, inte bara !empty: ett skalärt värde här ger en Warning (inte Throwable)
        // och skulle tyst hoppa över extrarummen, dvs. under-validera.
        if (!empty($appointment['additional_rooms']) && is_array($appointment['additional_rooms'])) {
            foreach ($appointment['additional_rooms'] as $service_id) {
                array_push($service_ids, (int)$service_id);
            }
        }

        return $service_ids;
    }

    /**
     * Räknar sommarnätter för en bokning: dels vistelsens egna nätter inom
     * [1 juni, 1 september), dels de nätter samma person redan har bokade samma sommar.
     *
     * Delas av R2 och R10 så att de inte kan glida isär.
     *
     * @param array $appointment
     * @return array|NULL ['appointment' => int, 'already_booked' => int], NULL vid fel
     */
    private function summer_nights_total($appointment) {
        $appointment_nights = $this->summer_nights($this->start_date, $this->end_date);

        // Överlappsfråga mot sommarfönstret: en vistelse som sträcker sig förbi 1 september
        // ska klippas, inte tappas bort.
        $summer_appointments = $this->CI->appointments_model->get_batch([
            'is_main' => TRUE,
            'id_users_customer' => $appointment['id_users_customer'],
            'start_datetime <' => $this->first_day_in_september->format('Y-m-d H:i:s'),
            'end_datetime >=' => $this->first_day_in_june->format('Y-m-d H:i:s')
        ]);

        if (!is_array($summer_appointments)) {
            return NULL;
        }

        $current_id = isset($appointment['id']) ? (int)$appointment['id'] : 0;
        $summer_nights_booked = 0;

        foreach ($summer_appointments as $a) {
            // Exclude the current appointment being edited
            if ((int)$a['id'] === $current_id) {
                continue;
            }

            $a_start_date = date_create($a['start_datetime']);
            $a_end_date = date_create($a['end_datetime']);

            if ($a_start_date === FALSE || $a_end_date === FALSE) {
                return NULL;
            }

            // summer_nights() klipper vistelsen mot [1 juni, 1 september) och räknar nätter.
            $summer_nights_booked += $this->summer_nights($a_start_date, $a_end_date);
        }

        return [
            'appointment' => $appointment_nights,
            'already_booked' => $summer_nights_booked,
        ];
    }

    /**
     * Namnger en lista med tjänste-id:n så att felmeddelanden kan peka ut konkreta rum.
     * Faller tillbaka på $fallback när namnen inte går att slå upp.
     *
     * @param array $service_ids
     * @param string $fallback
     * @return string
     */
    private function describe_services($service_ids, $fallback) {
        if (!is_array($service_ids) || count($service_ids) === 0) {
            return $fallback;
        }

        $names_by_id = [];
        $services = $this->CI->services_model->get_batch();

        if (is_array($services)) {
            foreach ($services as $service) {
                if (isset($service['id']) && isset($service['name'])) {
                    $names_by_id[(int)$service['id']] = $service['name'];
                }
            }
        }

        $names = [];
        foreach ($service_ids as $service_id) {
            if (isset($names_by_id[(int)$service_id])) {
                array_push($names, $names_by_id[(int)$service_id]);
            }
        }

        if (count($names) === 0) {
            return $fallback;
        }

        return join(', ', $names);
    }

    /**
     * Sommarfönstrets början för ett givet år: 1 juni 00:00.
     *
     * @param int $year
     * @return DateTime
     */
    private function summer_window_start($year) {
        return date_create(date("Y-m-d H:i:s", mktime(0, 0, 0, 6, 1, (int)$year)));
    }

    /**
     * Sommarfönstrets slut (exklusivt) för ett givet år: 1 september 00:00.
     *
     * @param int $year
     * @return DateTime
     */
    private function summer_window_end($year) {
        return date_create(date("Y-m-d H:i:s", mktime(0, 0, 0, 9, 1, (int)$year)));
    }

    /**
     * True om vistelsen överlappar perioden [1 juni, 1 september) något av de år den berör.
     * Tidigare jämfördes bara start- och slutmånaden mot [6,7,8], vilket missade t.ex.
     * 25 maj - 5 september.
     */
    private function is_summer_appointment($start, $end) {

        if (!($start instanceof DateTime) || !($end instanceof DateTime)) {
            return false;
        }

        $first_year = (int)$start->format("Y");
        $last_year = (int)$end->format("Y");

        for ($year = $first_year; $year <= $last_year; $year++) {
            $window_start = $this->summer_window_start($year);
            $window_end = $this->summer_window_end($year);

            // Äkta intervallöverlapp: vistelsen börjar före fönstrets slut och slutar efter dess början.
            if ($start < $window_end && $end > $window_start) {
                return true;
            }
        }

        return false;
    }

    /**
     * Antal NÄTTER av vistelsen som ligger inom sommarfönstret [1 juni, 1 september).
     * En natt räknas till sitt ankomstdatum, så vistelsen täcker nätterna
     * [ankomstdatum .. avresedatum - 1]. Både vistelsen och fönstret klipps ihop,
     * vilket gör att en bokning som spänner över fönstrets kant räknas delvis
     * i stället för att tappas bort helt.
     *
     * @param DateTime $start
     * @param DateTime $end
     * @return int
     */
    private function summer_nights(DateTime $start, DateTime $end) {
        $first_night = date_create($start->format("Y-m-d") . " 00:00:00");
        $last_night_exclusive = date_create($end->format("Y-m-d") . " 00:00:00");

        if ($first_night === FALSE || $last_night_exclusive === FALSE) {
            return 0;
        }

        if ($last_night_exclusive <= $first_night) {
            return 0;
        }

        $nights = 0;
        $first_year = (int)$first_night->format("Y");
        $last_year = (int)$last_night_exclusive->format("Y");

        for ($year = $first_year; $year <= $last_year; $year++) {
            $window_start = $this->summer_window_start($year);
            $window_end = $this->summer_window_end($year);

            $from = ($first_night > $window_start) ? $first_night : $window_start;
            $to = ($last_night_exclusive < $window_end) ? $last_night_exclusive : $window_end;

            if ($to <= $from) {
                continue;
            }

            $interval = date_diff($from, $to);
            $nights += (int)$interval->format("%a");
        }

        return $nights;
    }

    /**
     * Plockar isär ett id_services-värde (kan vara "12" eller "12,14") till en lista med heltal.
     *
     * @param mixed $value
     * @return array
     */
    private function parse_service_ids($value) {
        $ids = [];

        if ($value === NULL || $value === '') {
            return $ids;
        }

        foreach (explode(',', (string)$value) as $id) {
            $id = trim($id);
            if ($id !== '') {
                array_push($ids, (int)$id);
            }
        }

        return $ids;
    }

    /**
     * Bokningens huvudtjänst(er), dvs id_services (utan additional_rooms).
     *
     * @param array $appointment
     * @return array
     */
    private function get_main_service_ids($appointment) {
        return isset($appointment['id_services']) ? $this->parse_service_ids($appointment['id_services']) : [];
    }

    /**
     * Slår upp tjänste-id:n på namn, t.ex. "Släktmöte", "Påsk", "Jul", "Nyår".
     * Namnuppslag används i stället för hårdkodade id:n.
     *
     * Ett tomt resultat betyder att tjänsten döpts om eller tagits bort. Då KAN regeln inte
     * utvärderas, och tystnad är fel svar: tidigare stängdes R6 av helt av ett namnbyte i
     * admin-gränssnittet, utan logg och utan fel. Kasta i stället - anropande regels
     * catch (Throwable) gör det till ett tekniskt fel och bokningen stoppas (fail closed).
     *
     * @param string $name
     * @return array
     * @throws RuntimeException när namnet inte matchar någon tjänst.
     */
    private function get_service_ids_by_name($name) {
        $ids = [];

        $services = $this->CI->services_model->get_batch(['name' => $name]);

        if (is_array($services)) {
            foreach ($services as $service) {
                if (isset($service['id'])) {
                    array_push($ids, (int)$service['id']);
                }
            }
        }

        if (count($ids) === 0) {
            throw new RuntimeException('CLG: ingen tjänst heter "' . $name . '" - regelkontrollen kan inte utvärderas.');
        }

        return $ids;
    }

    /**
     * True om bokningens huvudtjänst är tjänsten med det angivna namnet.
     *
     * @param array $appointment
     * @param string $name
     * @return bool
     */
    private function main_service_is_named($appointment, $name) {
        $named_ids = $this->get_service_ids_by_name($name);

        return count(array_intersect($named_ids, $this->get_main_service_ids($appointment))) > 0;
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
    try
    {
        // Needed for several rules
        $this->validation_faults = [];
        $this->technical_fault_pushed = FALSE;
        $this->session = $current_session;
        $this->privileges = $this->CI->roles_model->get_privileges($this->session->userdata('role_slug'));
        $this->is_provider = $this->session->userdata('role_slug') === DB_SLUG_PROVIDER; 
        $this->is_admin = $this->session->userdata('role_slug') === DB_SLUG_ADMIN;

        // Reglerna nedan läser dessa nycklar rakt av. Saknas någon av dem ger PHP 7.2 bara en
        // Notice - som INTE är Throwable, så catch-satserna nedan ser den aldrig. Värdet blir NULL,
        // frågan blir "... IS NULL", noll rader kommer tillbaka och kvotregeln SLÄPPER IGENOM
        // bokningen okontrollerad. Kräv nycklarna här uppe i stället, så stoppas bokningen.
        //
        // TODO: kontrollera dessutom att id_users_customer hör ihop med den inloggade användaren
        // för icke-admin/husmor - annars kan kvotreglerna (R2, R3, R6, R7) kringgås genom att posta
        // någon annans kund-id. Medvetet inte gjort här; separat beslut.
        foreach (['id_services', 'id_users_customer', 'start_datetime', 'end_datetime'] as $required_key) {
            if (!isset($appointment[$required_key])
                || $appointment[$required_key] === ''
                || $appointment[$required_key] === []) {

                // Saknat rum är det enda av de här som en användare kan orsaka och rätta själv.
                // Övriga nycklar sätts av systemet - då är det ett tekniskt fel.
                if ($required_key === 'id_services') {
                    array_push($this->validation_faults,
                        "Bokningen saknar rum. Välj vilket rum eller vilken bokningsform det gäller och spara igen.");
                } else {
                    $this->push_technical_fault();
                }

                return $this->validation_faults;
            }
        }

        $this->start_date = date_create($appointment['start_datetime']);
        $this->end_date = date_create($appointment['end_datetime']);

        if ($this->start_date === FALSE || $this->end_date === FALSE) {
            $this->push_technical_fault();
            return $this->validation_faults;
        }

        $this->appointment_year = (int)$this->start_date->format('Y'); 
        $this->is_during_summer = $this->is_summer_appointment($this->start_date, $this->end_date);
        $this->last_day_in_may = date_create(date("Y-m-d H:i:s", mktime(0, 0, 0, 5, 31, $this->appointment_year))); 
        $this->first_day_in_june = $this->summer_window_start($this->appointment_year);
        $this->first_day_in_september = $this->summer_window_end($this->appointment_year);

        // Run CLG validations
        $this->R0_max_one_per_room_and_day($appointment);
        $this->R1_max_one_year_prior($appointment);
        $this->R2_max_seven_days($appointment);
        $this->R3_summer_two_years_in_a_row($appointment);
        //$this->R4_exchange_day($appointment);
        $this->R5_all_rooms($appointment);
        $this->R6_holidays($appointment);
        $this->R7_xmas_or_newyears($appointment);
        //$this->R8_preliminary_booking_restrictions($appointment);
        //$this->R9_age_limit($appointment);
        $this->R10_relative_guide($appointment);
        
        // Run system validations
        $this->V1_minimum_one_person_per_room($appointment);

        return $this->validation_faults;
    }
    catch (Throwable $exception)
    {
        $this->push_technical_fault($exception);
        return $this->validation_faults;
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
            if (!empty($appointment['additional_rooms'])) {
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
                // Svensk text direkt här - lang() gav engelska för alla konton med language = english.
                // OBS: $appointment är överskriven av foreach-slingan ovan, använd $this->start_date/end_date.
                array_push($this->validation_faults,
                    "Följande är tyvärr redan bokat under de datum du valt ("
                    . $this->start_date->format('Y-m-d') . "–" . $this->end_date->format('Y-m-d') . "): "
                    . join(', ', $booked_services) . "."
                    . " Prova andra datum eller ett annat rum – i kalendern ser du vad som är ledigt.");
            }
        }
        catch(Throwable $exception) {
            $this->push_technical_fault($exception);
        }
    }

    /**
     * Bokning får ske max ett år i förväg
     * - Undantag: Bokningar av styrelsen och årsmötet samt följande högtider: jul, nyår, bröllop och dop samt 50- och 75-års födelsedagar.
     */
    private function R1_max_one_year_prior($appointment) {
        try {
            // Allow providers/husmor and admins (styrelsen) to break this rule
            if ($this->is_provider || $this->is_admin) {
                return;
            }

            if (strtotime($appointment['start_datetime']) > strtotime('+1 year'))  {
                
                $service_ids = [];
                array_push($service_ids, $appointment['id_services']);
                
                // Jul, nyår, bröllop, dop, påsk och styrelsemöte har is_all_rooms = 1 och undantas här.
                $includes_all_rooms = $this->CI->services_model->includes_all_rooms_service($service_ids);

                // Släktmötet (årsmötet) har is_all_rooms = 0 och fångas därför inte ovan.
                // Undantaget slås upp på namn i stället för på hårdkodat id.
                // OBS: "50- och 75-års födelsedagar" går inte att undanta - det finns varken
                // en tjänst eller ett fält i bokningen som talar om att det rör sig om en sådan.
                $is_family_meeting = $this->main_service_is_named($appointment, 'Släktmöte');

                if (!$includes_all_rooms && !$is_family_meeting) {
                    array_push($this->validation_faults,
                        "Du kan boka högst ett år i förväg, alltså till och med " . date('Y-m-d', strtotime('+1 year')) . "."
                        . " Din ankomst " . $this->start_date->format('Y-m-d') . " ligger längre fram än så."
                        . " Gäller det en högtid, ett bröllop, ett dop eller en jämn födelsedag? Då finns undantag –"
                        . " hör av dig till bokningsgruppen, så lägger de in bokningen åt dig.");
                }
            }
        }
        catch(Throwable $exception) {
            $this->push_technical_fault($exception);
        }
    }

    /**
     * Man har rätt att boka max sju nätter under perioden juni-augusti. 
     * - Vill man boka fler nätter under denna period kan man göra så, om lediga rum finns tillgängliga, tidigast fjorton dagar innan ankomst. 
     */
    private function R2_max_seven_days($appointment) {
        try {
            $appointment['id'] = isset($appointment['id']) ? (int)$appointment['id'] : 0;
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

            // Endast de nätter av den nya bokningen som faktiskt ligger inom juni-augusti räknas.
            // Räkningen delas med R10, se summer_nights_total().
            $nights = $this->summer_nights_total($appointment);

            if ($nights === NULL) {
                $this->push_technical_fault();
                return;
            }

            $appointment_nights = $nights['appointment'];
            $summer_nights_booked = $nights['already_booked'];

            if (($summer_nights_booked + $appointment_nights) > 7) {
                // Fjortondagarsgränsen speglar undantaget högst upp i regeln.
                $can_book_from = date_modify(clone $this->start_date, "-14 days");

                // Vanligaste fallet är en enda lång vistelse utan tidigare bokningar.
                // Då vore "du har redan 0 nätter bokade" bara förvirrande.
                if ($summer_nights_booked === 0) {
                    $so_far = "Den här vistelsen är på "
                        . $this->plural_count($appointment_nights, 'natt', 'nätter') . ".";
                } else {
                    $so_far = "Du har redan "
                        . $this->plural_count($summer_nights_booked, 'natt', 'nätter') . " bokade i sommar,"
                        . " och den här vistelsen lägger till "
                        . $this->plural_count($appointment_nights, 'natt', 'nätter') . " –"
                        . " tillsammans "
                        . $this->plural_count($summer_nights_booked + $appointment_nights, 'natt', 'nätter') . ".";
                }

                array_push($this->validation_faults,
                    "Sommaren (1 juni–31 augusti) räcker till sju nätter var. " . $so_far
                    . " Vill du ändå bo längre går det bra, men de extra nätterna kan bokas först fjorton dagar före ankomst,"
                    . " alltså från och med " . $can_book_from->format('Y-m-d') . ", om det då finns rum lediga.");
            }
        }
        catch(Throwable $exception) {
            $this->push_technical_fault($exception);
        }
    }

    /**
     * Har man bokat under perioden juni-augusti året innan kan man endast boka, under denna period, sex månader i förväg. 
     */
    private function R3_summer_two_years_in_a_row($appointment) {
        try {
            // Bokningsgruppen kan lägga in undantag som styrelsen har godkänt, och behöver
            // dessutom kunna boka släktguidernas platshållare sommar efter sommar.
            // Medvetet INTE is_admin: styrelsen ska inte kunna ge sig själv en sommar till
            // utan att någon annan lägger in den.
            if ($this->is_provider) {
                log_message('info', 'R3 kringgången av bokningsgruppen for kund '
                    . (isset($appointment['id_users_customer']) ? $appointment['id_users_customer'] : '?'));
                return;
            }

            //Return if appointment is not during summer months
            if ($this->is_during_summer == false) {
                return;
            }

            // Om det är inom sex månader i förväg så får man boka          
            if ((new DateTime()) >= date_modify(clone $this->start_date, "-6 months")) {
                return;
            }

            $start_last_year = date_modify(clone $this->first_day_in_june, "-1 year");
            $end_last_year = date_modify(clone $this->first_day_in_september, "-1 year");

            // Överlapp mot fjolårets sommarfönster [1 juni, 1 september):
            // bokningen börjar före fönstrets slut och slutar efter dess början.
            $last_year_summer_appointments = $this->CI->appointments_model->get_batch([
                'is_main' => TRUE,
                'id_users_customer' => $appointment['id_users_customer'],
                'start_datetime <' => $end_last_year->format('Y-m-d H:i:s'),
                'end_datetime >=' => $start_last_year->format('Y-m-d H:i:s')
            ]);

            if (!is_array($last_year_summer_appointments)) {
                $this->push_technical_fault();
                return;
            }

            // Frågan ovan hämtar allt som ÖVERLAPPAR fjolårets sommarfönster, och en vistelse som
            // bara nuddar 1 juni (t.ex. avresa 1 juni 00:00) räknas då med trots att den inte har
            // en enda natt i juni-augusti. Poängsätt därför varje träff med summer_nights() -
            // annars nekas den som bodde här i maj i fjol.
            $matched_stay_start = NULL;
            $matched_stay_end = NULL;

            foreach ($last_year_summer_appointments as $a) {
                if (!isset($a['start_datetime']) || !isset($a['end_datetime'])) {
                    continue;
                }

                $a_start_date = date_create($a['start_datetime']);
                $a_end_date = date_create($a['end_datetime']);

                if ($a_start_date === FALSE || $a_end_date === FALSE) {
                    $this->push_technical_fault();
                    return;
                }

                if ($this->summer_nights($a_start_date, $a_end_date) > 0) {
                    $matched_stay_start = $a_start_date;
                    $matched_stay_end = $a_end_date;
                    break;
                }
            }

            if ($matched_stay_start !== NULL) {
                $can_book_from = date_modify(clone $this->start_date, "-6 months");

                array_push($this->validation_faults,
                    "Du bodde på gården sommaren " . $start_last_year->format('Y')
                    . " (" . $matched_stay_start->format('Y-m-d') . "–" . $matched_stay_end->format('Y-m-d') . "),"
                    . " och då gäller att nästa sommarvistelse får bokas tidigast sex månader före ankomst."
                    . " Du är välkommen att lägga in den här bokningen från och med " . $can_book_from->format('Y-m-d') . "."
                    . " Stämmer det inte att du bodde här då? Hör av dig till bokningsgruppen.");
            }
        }
        catch(Throwable $exception) {
            $this->push_technical_fault($exception);
        }
    }

    /**
     * Bytesdag vid trängsel: söndagar. 
     */
    private function R4_exchange_day($appointment) {
        try {
            // TODO: not implemented. Rule is disabled in validate_appointment().
        }
        catch(Throwable $exception) {
            $this->push_technical_fault($exception);
        }
    }

    /**
     * Bokning av hela gården bör undvikas, speciellt under perioden juni-augusti, men kan ske efter tillstånd från styrelsen. 
     * Styrelsens beslut behövs ej vid jul, nyår, bröllop, dop och begravningar.
     */
    private function R5_all_rooms($appointment) {
        try {
            $appointment['id'] = isset($appointment['id']) ? $appointment['id'] : 0;
            $current_id = $appointment['id'];

            /** Samtliga valda tjänster: huvudtjänsten plus eventuella extrarum. */
            $service_ids = $this->get_main_service_ids($appointment);
            if (!empty($appointment['additional_rooms']) && is_array($appointment['additional_rooms'])) {
                foreach($appointment['additional_rooms'] as $service_id) {
                    array_push($service_ids, (int)$service_id);
                }
            }

            $includes_all_rooms = count($service_ids) > 0
                ? $this->CI->services_model->includes_all_rooms_service($service_ids)
                : FALSE;

            /**
             * "Bokning av hela gården bör undvikas ... men kan ske efter tillstånd från styrelsen."
             * Undantagna enligt bokningsreglerna: jul, nyår, bröllop och dop. Styrelsemöte är
             * styrelsen själv. Kvar att godkänna blir därmed påsk.
             * OBS: "jämna födelsedagar" och begravningar går inte att kontrollera automatiskt -
             * det finns varken tjänst eller flagga för dem i systemet.
             */
            if ($includes_all_rooms && !$this->is_provider && !$this->is_admin) {
                $exempt_ids = [];
                foreach (['Jul', 'Nyår', 'Bröllop', 'Dop', 'Styrelsemöte'] as $exempt_name) {
                    foreach ($this->get_service_ids_by_name($exempt_name) as $exempt_id) {
                        array_push($exempt_ids, (int)$exempt_id);
                    }
                }

                foreach ($service_ids as $service_id) {
                    if (in_array((int)$service_id, $exempt_ids, TRUE)) {
                        continue;
                    }

                    if ($this->CI->services_model->includes_all_rooms_service([$service_id])) {
                        $service_name = $this->describe_services([$service_id], '');
                        $service_label = ($service_name !== '') ? ' (' . $service_name . ')' : '';

                        array_push($this->validation_faults,
                            "Att boka hela gården" . $service_label . " "
                            . $this->start_date->format('Y-m-d') . "–" . $this->end_date->format('Y-m-d')
                            . " behöver först godkännas av styrelsen. Hör av dig till dem med dina datum,"
                            . " så lägger de in bokningen åt dig. Gäller det jul, nyår, bröllop eller dop"
                            . " behövs inget beslut – då kan du boka direkt.");
                        break;
                    }
                }
            }

            /** Check if "all_room" booking and other rooms are selected as well */
            if (!empty($appointment['additional_rooms'])) {
                if ($includes_all_rooms) {
                    array_push($this->validation_faults,
                        "När du bokar hela gården ingår alla rum redan, så du behöver inte välja några extra."
                        . " Ta bort " . $this->describe_services($appointment['additional_rooms'], 'de extra rummen du valt')
                        . " från bokningen, så går den igenom.");
                }
            }
            $start_date = date_create($appointment['start_datetime']);

            if ($start_date === FALSE) {
                $this->push_technical_fault();
                return;
            }

            /** Check if "all_room" booking exists during selected dates */
            $appointments = $this->CI->appointments_model->get_all_rooms_appointments(
                $start_date,
                $this->end_date,
                $current_id
            );

            $booked_services = [];
            foreach($appointments as $booked_appointment) {
                array_push($booked_services, $booked_appointment['service']['name']);
            }

            if (count($booked_services) > 0 )
            {
                // Svensk text direkt här - lang() gav engelska för alla konton med language = english.
                array_push($this->validation_faults,
                    "Hela gården är redan bokad " . $start_date->format('Y-m-d') . "–" . $this->end_date->format('Y-m-d')
                    . " för " . join(', ', $booked_services) . ", så det går tyvärr inte att boka rum då."
                    . " Prova andra datum – eller hör av dig till bokningsgruppen om du tror att det blivit fel.");
            }

            /**
             * Bokas hela gården måste även vanliga rumsbokningar kontrolleras - annars kan
             * hela gården bokas över datum där enskilda rum redan är upptagna.
             */
            if ($includes_all_rooms) {
                $room_services = $this->CI->services_model->get_batch(['is_all_rooms' => 0]);

                if (!is_array($room_services) || count($room_services) === 0) {
                    $this->push_technical_fault();
                    return;
                }

                $room_service_ids = [];
                foreach ($room_services as $room_service) {
                    if (isset($room_service['id'])) {
                        array_push($room_service_ids, (int)$room_service['id']);
                    }
                }

                if (count($room_service_ids) === 0) {
                    $this->push_technical_fault();
                    return;
                }

                $occupied_appointments = $this->CI->appointments_model->get_already_booked_services(
                    $start_date,
                    $this->end_date,
                    $current_id,
                    $room_service_ids
                );

                $occupied_rooms = [];
                foreach ($occupied_appointments as $occupied_appointment) {
                    if (isset($occupied_appointment['service']['name'])) {
                        array_push($occupied_rooms, $occupied_appointment['service']['name']);
                    }
                }

                $occupied_rooms = array_unique($occupied_rooms);

                if (count($occupied_rooms) > 0)
                {
                    array_push($this->validation_faults,
                        "Hela gården går inte att boka " . $start_date->format('Y-m-d') . "–" . $this->end_date->format('Y-m-d')
                        . ", eftersom det redan finns bokningar på: " . join(', ', $occupied_rooms) . "."
                        . " Prova andra datum, eller prata med dem som bokat om de kan tänka sig att flytta –"
                        . " hör av dig till bokningsgruppen om du vill ha hjälp med det.");
                }
            }
        }
        catch(Throwable $exception) {
            $this->push_technical_fault($exception);
        }
    }

    /**
     * Högtiderna påsk, midsommar, jul och nyår bokas separat efter turordning (jul och nyår är olika högtider).
     * Har man bokat någon av högtiderna året innan, kan man endast boka samma högtid sex månader i förväg. 
     */
    private function R6_holidays($appointment) {
        try {
            // Bokningsgruppen kan lägga in undantag som styrelsen har godkänt, och behöver
            // dessutom kunna boka släktguidernas platshållare sommar efter sommar.
            // Medvetet INTE is_admin: styrelsen ska inte kunna ge sig själv en sommar till
            // utan att någon annan lägger in den.
            if ($this->is_provider) {
                log_message('info', 'R6 kringgången av bokningsgruppen for kund '
                    . (isset($appointment['id_users_customer']) ? $appointment['id_users_customer'] : '?'));
                return;
            }

            // Om det är inom sex månader i förväg så får man boka
            if ((new DateTime()) >= date_modify(clone $this->start_date, "-6 months")) {
                return;
            }

            // Högtiderna slås upp på namn i stället för på hårdkodade id:n.
            // OBS: midsommar går inte att kontrollera - det finns ingen midsommartjänst i systemet.
            $holidays = ['Påsk', 'Jul', 'Nyår'];
            $main_service_ids = $this->get_main_service_ids($appointment);

            $booked_holiday = NULL;
            $holiday_service_ids = [];

            foreach ($holidays as $holiday) {
                $ids = $this->get_service_ids_by_name($holiday);

                if (count($ids) > 0 && count(array_intersect($ids, $main_service_ids)) > 0) {
                    $booked_holiday = $holiday;
                    $holiday_service_ids = $ids;
                    break;
                }
            }

            // Bokningen avser ingen av högtiderna - regeln gäller inte.
            if ($booked_holiday === NULL) {
                return;
            }

            // Fjolårets motsvarande högtid. Fönstret är +/- 45 dagar eftersom påsken flyttar sig.
            $same_holiday_last_year = date_modify(clone $this->start_date, "-1 year");
            $window_start = date_modify(clone $same_holiday_last_year, "-45 days");
            $window_end = date_modify(clone $same_holiday_last_year, "+45 days");

            $last_year_appointments = $this->CI->appointments_model->get_batch([
                'is_main' => TRUE,
                'id_users_customer' => $appointment['id_users_customer'],
                'start_datetime >=' => $window_start->format('Y-m-d H:i:s'),
                'start_datetime <' => $window_end->format('Y-m-d H:i:s')
            ]);

            if (!is_array($last_year_appointments)) {
                $this->push_technical_fault();
                return;
            }

            $current_id = isset($appointment['id']) ? (int)$appointment['id'] : 0;

            foreach ($last_year_appointments as $a) {
                if ($current_id > 0 && isset($a['id']) && (int)$a['id'] === $current_id) {
                    continue; // don't compare an edit against itself
                }

                if (!isset($a['id_services'])) {
                    continue;
                }

                if (count(array_intersect($holiday_service_ids, $this->parse_service_ids($a['id_services']))) > 0) {
                    $last_year_start = isset($a['start_datetime']) ? date_create($a['start_datetime']) : FALSE;

                    if ($last_year_start === FALSE) {
                        $this->push_technical_fault();
                        return;
                    }

                    $can_book_from = date_modify(clone $this->start_date, "-6 months");

                    array_push($this->validation_faults,
                        "Du firade " . $booked_holiday . " på gården förra året (" . $last_year_start->format('Y-m-d') . "),"
                        . " och samma högtid får bokas tidigast sex månader före ankomst."
                        . " Du kan lägga in den här bokningen från och med " . $can_book_from->format('Y-m-d') . "."
                        . " Andra högtider kan du boka som vanligt.");
                    break;
                }
            }
        }
        catch(Throwable $exception) {
            $this->push_technical_fault($exception);
        }
    }

    /**
     * Jul/nyår kan bokas med max en jul/ett nyår i taget.
     * TODO: Check if it's roughly the same ppl booking both holidays but booker is different
     */
    private function R7_xmas_or_newyears($appointment) {
        try {
            $user_id = $appointment['id_users_customer'];
            $current_id = isset($appointment['id']) ? (int)$appointment['id'] : 0;
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
                    if ($current_id > 0 && (int)$existing_appointment['id'] === $current_id) {
                        continue; // don't compare an edit against itself
                    }
                    if (!$this->CI->appointments_model->is_less_than_40_percent_overlap($appointment, $existing_appointment['id'])) {
                        $existing_start = isset($existing_appointment['start_datetime']) ? date_create($existing_appointment['start_datetime']) : FALSE;
                        $existing_end = isset($existing_appointment['end_datetime']) ? date_create($existing_appointment['end_datetime']) : FALSE;
                        $existing_period = ($existing_start !== FALSE && $existing_end !== FALSE)
                            ? ' ' . $existing_start->format('Y-m-d') . '–' . $existing_end->format('Y-m-d')
                            : '';

                        array_push($this->validation_faults,
                            "Jul och nyår är två olika högtider, och samma sällskap kan bara ha en av dem samma år."
                            . " Du har redan en bokning" . $existing_period . ", och gästlistorna överlappar för mycket –"
                            . " minst 40 procent är samma personer. Vill ni fira båda högtiderna på gården får det bli"
                            . " olika sällskap. Hör av dig till bokningsgruppen om ni behöver hjälp att lösa det.");
                        break;
                    }
                }
            }
        }
        catch(Throwable $exception) {
            $this->push_technical_fault($exception);
        }
    }

    /**
     * Preliminärbokningar (över flera helger, veckor etc.) för ej göras
     *
     * INAKTIVERAD - anropet är bortkommenterat i validate_appointment().
     *
     * TODO: Regeln kan inte implementeras med dagens datamodell. Spec-regeln handlar om
     * PRELIMINÄRbokningar ("över flera helger, veckor etc."), men det finns inget fält i
     * bokningen som säger att en bokning är preliminär. Den tidigare implementationen nedan
     * satte i stället ett generellt tak på 7 dagar ÅRET RUNT (dessutom med "+1", så taket
     * blev i praktiken 6 nätter). Det taket finns inte i reglerna, det dubblerade R2 på ett
     * felaktigt sätt och det gjorde R2:s dokumenterade fjortondagarsundantag omöjligt att nå.
     * För att implementera regeln på riktigt krävs en flagga för preliminärbokning på
     * appointments-tabellen (t.ex. is_preliminary) - först då kan antalet helger/veckor som
     * en preliminärbokning spänner över kontrolleras.
     */
    private function R8_preliminary_booking_restrictions($appointment) {
        try {
            return; // TODO: not implemented. Rule is disabled in validate_appointment().

            /*
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
            */
        }
        catch(Throwable $exception) {
            $this->push_technical_fault($exception);
        }
    }

    /**
     * Från det året man fyller 18 år kan man få bo på Lilla Hyttnäs.
     */
    private function R9_age_limit($appointment) {
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
        }
        catch(Throwable $exception) {
            $this->push_technical_fault($exception);
        }
    }

    /**
     * Som Släktguide får man boka längre än en vecka, i uthusen.
     * Bokningsgruppen ansvarar för förläggning. Måste bokas i god tid.
     *
     * OBS: "i god tid" kontrolleras inte - ingen tidsgräns är beslutad av styrelsen.
     * Observerad praxis är 4-5 månader. Frågan ligger hos styrelsen.
     *
     * Guidevistelser markeras inte i databasen. I praktiken är markören att
     * Bokningsgruppen (enda provider-kontot) lägger in bokningen - vilket stämmer
     * med samtliga sex långa vistelser som finns i systemet.
     */
    private function R10_relative_guide($appointment) {
        try {
            // Regeln lägger INTE på någon ny gräns. Den begränsar det undantag som R2 redan
            // ger Bokningsgruppen, och slår bara till i exakt det läge där en vistelse
            // släpps igenom som en vanlig släkting inte hade fått boka.

            // Alla utom Bokningsgruppen bromsas redan av R2.
            if (!$this->is_provider) {
                return;
            }

            // R2 kapar bara sommarnätter. Utanför sommaren finns ingen gräns för någon,
            // och då ska Bokningsgruppen inte begränsas hårdare än en vanlig släkting.
            if ($this->is_during_summer == false) {
                return;
            }

            // Fjortondagarsundantaget i R2 gäller alla - då behövs inget guideundantag.
            if (strtotime($appointment['start_datetime']) < (time() + (60 * 60 * 24 * 14))) {
                return;
            }

            $service_ids = $this->booked_service_ids($appointment);

            if (count($service_ids) === 0) {
                return;
            }

            // Regeln gäller rumsbokningar (tjänst 1-18). Evenemang - bröllop, dop, jul,
            // påsk, kräftfiske, släktmöte - är hela gården eller egna arrangemang, och
            // "välj ett uthus i stället" vore ett omöjligt besked. De lämnas till R5.
            //
            // OBS: intervallet 1-18 är hårdkodat mot dagens rum. Läggs ett NYTT rum upp
            // (id 28 eller högre) hoppar regeln tyst över det. Lägg då till rummet både
            // här och i $outbuilding_service_ids.
            foreach ($service_ids as $service_id) {
                if ((int)$service_id < 1 || (int)$service_id > 18) {
                    return;
                }
            }

            $nights = $this->summer_nights_total($appointment);

            if ($nights === NULL) {
                $this->push_technical_fault();
                return;
            }

            // Gränsen gäller DEN HÄR vistelsens längd, inte hela sommarens kvot.
            // Regeln lyder "boka längre än en vecka". Att väga in tidigare bokningar
            // skulle ge beskedet "korta ner till sju nätter" till någon vars vistelse
            // redan är kortare än så.
            $appointment_nights = $nights['appointment'];

            // Sju nätter eller kortare - ingen guideförmån utnyttjas.
            if ($appointment_nights <= 7) {
                return;
            }

            // Här utnyttjas förmånen. Då gäller släktguidens villkor: bara uthusen.
            $not_outbuildings = [];

            foreach ($service_ids as $service_id) {
                if (!in_array((int)$service_id, self::$outbuilding_service_ids, TRUE)) {
                    array_push($not_outbuildings, (int)$service_id);
                }
            }

            if (count($not_outbuildings) > 0) {
                $names = $this->describe_services($not_outbuildings, 'de valda rummen');

                array_push($this->validation_faults,
                    "Vistelser längre än sju nätter under sommaren är släktguidens förmån och gäller bara uthusen."
                    . " Den här vistelsen är på " . $this->plural_count($appointment_nights, 'natt', 'nätter')
                    . " och omfattar " . $names . ", som inte ligger i uthusen."
                    . " Välj rum i uthusen, eller korta ner vistelsen till sju nätter.");
            }
        }
        catch(Throwable $exception) {
            $this->push_technical_fault($exception);
        }
    }
    
    /**
     ************ SYSTEMETS EGNA VALIDERINGAR (Ej genomklubbade regler) ************
     */

    /**
     * En bokning måste åtminstone ha lika många personer som rum.
     */
    private function V1_minimum_one_person_per_room($appointment) {
        try {
            // Get visiting relatives (count() på en icke-array ger 1 i PHP 7.2 - kräv array).
            $relatives = [];
            if (isset($appointment['relatives']) && is_array($appointment['relatives'])) {
                $relatives = $appointment['relatives'];
            }

            // Get visiting guests
            $guests = [];
            if (isset($appointment['guests']) && is_array($appointment['guests'])) {
                $guests = $appointment['guests'];
            }

            // Count the number of rooms in the booking
            $room_count = 1; // Start with the main room
            if (isset($appointment['additional_rooms']) && is_array($appointment['additional_rooms'])) {
                $room_count += count($appointment['additional_rooms']);
            }

            // Count the number of people (relatives + guests + 1 for the person making the booking)
            $people_count = count($relatives) + count($guests) + 1;

            // Check if the number of people is fewer than the number of rooms
            if ($people_count < $room_count) {
                array_push($this->validation_faults,
                    "Du har valt " . $this->plural_count($room_count, 'rum', 'rum') . " men bara "
                    . $this->plural_count($people_count, 'person', 'personer') . " står på bokningen."
                    . " Lägg till fler släktingar eller gäster, eller ta bort ett rum –"
                    . " det ska bo minst en person i varje rum.");
            }

            // Fetch the service details to check if is_all_rooms is set
            $service = $this->CI->services_model->get_row($appointment['id_services']);
            $is_all_rooms = isset($service['is_all_rooms']) ? $service['is_all_rooms'] : false;

            // Check if 'is_all_rooms' is set and there are at least 2 relatives
            if ($is_all_rooms && count($relatives) < 2) {
                array_push($this->validation_faults,
                    "När hela gården bokas ska minst två släktingar stå med på bokningen."
                    . " Just nu har du lagt till " . count($relatives) . "."
                    . " Fyll på listan över släktingar, så går bokningen igenom.");
            }
        }
        catch(Throwable $exception) {
            $this->push_technical_fault($exception);
        }
    }
}
