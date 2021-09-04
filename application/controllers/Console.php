<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Open Source Web Scheduler
 *
 * @package     EasyAppointments
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) 2013 - 2020, Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        https://easyappointments.org
 * @since       v1.3.2
 * ---------------------------------------------------------------------------- */

require_once __DIR__ . '/Google.php';

/**
 * Class Console
 *
 * CLI commands of Easy!Appointments, can only be executed from a terminal and not with a direct request.
 */
class Console extends EA_Controller {
    /**
     * Console constructor.
     */
    public function __construct()
    {
        if ( ! is_cli())
        {
            exit('No direct script access allowed');
        }

        parent::__construct();

        $this->load->dbutil();
        $this->load->helper('file');
        $this->load->library('migration');
        $this->load->model('admins_model');
        $this->load->model('customers_model');
        $this->load->model('providers_model');
        $this->load->model('secretaries_model');
        $this->load->model('services_model');
        $this->load->model('settings_model');
    }

    /**
     * Perform a console installation.
     *
     * Use this method to install Easy!Appointments directly from the terminal.
     *
     * Usage:
     *
     * php index.php console install
     */
    public function install()
    {
        $this->migrate('fresh');
        $this->seed();
        $this->output->set_output(PHP_EOL . '⇾ Installation completed, login with "administrator" / "administrator".' . PHP_EOL . PHP_EOL);
    }

    /**
     * Migrate the database to the latest state.
     *
     * Use this method to upgrade an existing installation to the latest database state.
     *
     * Notice:
     *
     * Do not use this method to install the app as it will not seed the database with the initial entries (admin,
     * provider, service, settings etc). Use the UI installation page for this.
     *
     * Usage:
     *
     * php index.php console migrate
     *
     * php index.php console migrate fresh
     *
     * @param string $type
     */
    public function migrate($type = '')
    {
        if ($type === 'fresh' && $this->migration->version(0) === FALSE)
        {
            show_error($this->migration->error_string());
        }

        if ($this->migration->current() === FALSE)
        {
            show_error($this->migration->error_string());
        }
    }

    /**
     * Seed the database with test data.
     *
     * Use this method to add test data to your database
     *
     * Usage:
     *
     * php index.php console seed
     */
    public function seed()
    {
        // Settings
        $this->settings_model->set_setting('company_name', 'Carl Larsson-gården');
        $this->settings_model->set_setting('company_email', 'info@carllarsson.se');
        $this->settings_model->set_setting('company_link', 'https://www.carllarsson.se');

        // Admins
         $this->admins_model->add([
            'first_name' => 'Marcus',
            'last_name' => 'Mueller',
            'email' => 'mm.swedev@gmail.com',
            'phone_number' => '+46 700 96 98 92',
            'settings' => [
                'username' => 'admin',
                'password' => 'fasf2345gdfhsh4u4784rjrjr252teh',
                'notifications' => TRUE,
                'calendar_view' => CALENDAR_VIEW_DEFAULT
            ],
        ]);

        $this->admins_model->add([
            'first_name' => 'Martin',
            'last_name' => 'Mueller',
            'email' => 'mm.select@telia.com',
            'phone_number' => '+46 70 539 48 04',
            'settings' => [
                'username' => 'martin',
                'password' => 'bytsenare123',
                'notifications' => TRUE,
                'calendar_view' => CALENDAR_VIEW_DEFAULT
            ],
        ]);

        // Room
        $room_1 = $this->services_model->add([
            'name' => 'Solhöjden (2,2,Allergi)',
            'duration' => '1440',
            'price' => '0',
            'currency' => '',
            'description' => '2st. + 2 barnsängar',
            'availabilities_type' => 'flexible',
            'attendants_number' => '2'
        ]);

        $room_2 = $this->services_model->add([
            'name' => 'Mörtstugan (5)',
            'duration' => '1440',
            'price' => '0',
            'currency' => '',
            'description' => '',
            'availabilities_type' => 'flexible',
            'attendants_number' => '5'
        ]);

        $room_3 = $this->services_model->add([
            'name' => 'Soluppgången (1)',
            'duration' => '1440',
            'price' => '0',
            'currency' => '',
            'description' => '',
            'availabilities_type' => 'flexible',
            'attendants_number' => '1'
        ]);

        $room_4 = $this->services_model->add([
            'name' => 'Ungkarlshotellet (2)',
            'duration' => '1440',
            'price' => '0',
            'currency' => '',
            'description' => '',
            'availabilities_type' => 'flexible',
            'attendants_number' => '2'
        ]);

        $room_5 = $this->services_model->add([
            'name' => 'Fiskarboden (2)',
            'duration' => '1440',
            'price' => '0',
            'currency' => '',
            'description' => '',
            'availabilities_type' => 'flexible',
            'attendants_number' => '2'
        ]);

        $room_6 = $this->services_model->add([
            'name' => 'Bagarstugan (2)',
            'duration' => '1440',
            'price' => '0',
            'currency' => '',
            'description' => '',
            'availabilities_type' => 'flexible',
            'attendants_number' => '2'
        ]);

        $room_7 = $this->services_model->add([
            'name' => 'Härbret (2+2)',
            'duration' => '1440',
            'price' => '0',
            'currency' => '',
            'description' => '+2 extra sängar',
            'availabilities_type' => 'flexible',
            'attendants_number' => '2'
        ]);

        $room_8 = $this->services_model->add([
            'name' => 'Svalboet (4)',
            'duration' => '1440',
            'price' => '0',
            'currency' => '',
            'description' => '',
            'availabilities_type' => 'flexible',
            'attendants_number' => '4'
        ]);

        $room_9 = $this->services_model->add([
            'name' => 'Suzannes rum (2)',
            'duration' => '1440',
            'price' => '0',
            'currency' => '',
            'description' => '',
            'availabilities_type' => 'flexible',
            'attendants_number' => '2'
        ]);

        $room_10 = $this->services_model->add([
            'name' => 'Jungfrukammaren (2,2)',
            'duration' => '1440',
            'price' => '0',
            'currency' => '',
            'description' => '+2 barnsängar',
            'availabilities_type' => 'flexible',
            'attendants_number' => '2'
        ]);

        $room_11 = $this->services_model->add([
            'name' => 'Karins (1,2)',
            'duration' => '1440',
            'price' => '0',
            'currency' => '',
            'description' => '+2 barnsängar',
            'availabilities_type' => 'flexible',
            'attendants_number' => '1'
        ]);

        $room_12 = $this->services_model->add([
            'name' => 'CLs rum (1)',
            'duration' => '1440',
            'price' => '0',
            'currency' => '',
            'description' => '',
            'availabilities_type' => 'flexible',
            'attendants_number' => '1'
        ]);

        $room_13 = $this->services_model->add([
            'name' => 'Gammelrummet (2)',
            'duration' => '1440',
            'price' => '0',
            'currency' => '',
            'description' => '',
            'availabilities_type' => 'flexible',
            'attendants_number' => '2'
        ]);

        $room_14 = $this->services_model->add([
            'name' => 'Duvslaget (4+2)',
            'duration' => '1440',
            'price' => '0',
            'currency' => '+2 st på övre',
            'description' => '',
            'availabilities_type' => 'flexible',
            'attendants_number' => '4'
        ]);

        $room_15 = $this->services_model->add([
            'name' => 'Bergsmansstugan (1)',
            'duration' => '1440',
            'price' => '0',
            'currency' => '',
            'description' => '',
            'availabilities_type' => 'flexible',
            'attendants_number' => '1'
        ]);

        $room_16 = $this->services_model->add([
            'name' => 'Guidestugan (1)',
            'duration' => '1440',
            'price' => '0',
            'currency' => '',
            'description' => 'Bäddsoffa ( dubbel ) + uppblåsbar madrass',
            'availabilities_type' => 'flexible',
            'attendants_number' => '1'
        ]);

        $room_17 = $this->services_model->add([
            'name' => 'Sågbacken Studierummet (1)',
            'duration' => '1440',
            'price' => '0',
            'currency' => '',
            'description' => '',
            'availabilities_type' => 'flexible',
            'attendants_number' => '1'
        ]);

        $room_18 = $this->services_model->add([
            'name' => 'Sågbacken Inre rum (1)',
            'duration' => '1440',
            'price' => '0',
            'currency' => '',
            'description' => '',
            'availabilities_type' => 'flexible',
            'attendants_number' => '1'
        ]);

        // Bokare
        $this->providers_model->add([
            'first_name' => 'Husmor',
            'last_name' => 'CLG',
            'email' => 'husmor@example.org',
            'phone_number' => '+1 (000) 000-0000',
            'services' => [
                $room_1,$room_2,$room_3,$room_4,$room_5,$room_6,
                $room_7,$room_8,$room_9,$room_10,$room_11,$room_12,
                $room_13,$room_14,$room_15,$room_16,$room_17,$room_18
            ],
            'settings' => [
                'username' => 'husmor',
                'password' => '57563abc618858a12a303154ae838aa3',
                'working_plan' => $this->settings_model->get_setting('company_working_plan'),
                'notifications' => TRUE,
                'google_sync' => FALSE,
                'sync_past_days' => 30,
                'sync_future_days' => 90,
                'calendar_view' => CALENDAR_VIEW_DEFAULT
            ],
        ]);

        // Add booking user for board
        $this->customers_model->add([
                        'first_name' => 'Styrelse',
                        'last_name' => 'Möte',
                        'email' => 'styrelse@carllarsson.se',
                        'phone_number' => '00000000',
                        'timezone' => 'Europe/Stockholm',
                        'language' => 'swedish'
                    ]);

        // "Customers" samt "Secretaries" (Släktingar som kan boka)
        // TODO: read file from website (https://familjen.carllarsson.se/s/Slaktens-kontaktlista-20200903.xlsx) and parse
        $filename = MEMBER_REGISTER_PATH;

        if(file_exists($filename)) {
            $csv = array_map('str_getcsv', file($filename));
            unset($csv[0]);

            foreach ($csv as $member) {
                if(filter_var(trim($member[8]), FILTER_VALIDATE_EMAIL)) {
                    $data = [
                        'first_name' => $member[1],
                        'last_name' => $member[0],
                        'email' => trim($member[8]),
                        'birthday' => $member[6],
                        'phone_number' => $member[7],
                        'address' => $member[2],
                        'city' => $member[4],
                        'state' => $member[5],
                        'zip_code' => $member[3],
                        'notes' => $member[9],
                        'timezone' => 'Europe/Stockholm',
                        'language' => 'swedish'
                    ];

                    $id = $this->customers_model->add($data);

                    // Create clone Secretaries (acting as login accounts, to provide
                    // "customers with proper functionality")

                    $password = $member[1];
                    foreach(str_split($password) as $int) {
	                    $password = $password . strval(ord($int));
                    }
                    $password = substr($password, 0, 11);

                    $data['settings'] = [
                            'username' => $member[1].$id,
                            'password' => $password,
                            'working_plan' => $this->settings_model->get_setting('company_working_plan'),
                            'notifications' => TRUE,
                            'google_sync' => FALSE,
                            'sync_past_days' => 30,
                            'sync_future_days' => 90,
                            'calendar_view' => CALENDAR_VIEW_DEFAULT
                    ];
                    // use "Husmor" as provider
                    $data['providers'] = [3];  

                    $this->secretaries_model->add($data);
                }
            }
        }
    }

    /**
     * Create a backup file.
     *
     * Use this method to backup your Easy!Appointments data.
     *
     * Usage:
     *
     * php index.php console backup
     *
     * php index.php console backup /path/to/backup/folder
     *
     * @throws Exception
     */
    public function backup()
    {
        $path = isset($GLOBALS['argv'][3]) ? $GLOBALS['argv'][3] : APPPATH . '/../storage/backups';

        if ( ! file_exists($path))
        {
            throw new Exception('The backup path does not exist™: ' . $path);
        }

        if ( ! is_writable($path))
        {
            throw new Exception('The backup path is not writable: ' . $path);
        }

        $contents = $this->dbutil->backup();

        $filename = 'easyappointments-backup-' . date('Y-m-d-His') . '.gz';

        write_file(rtrim($path, '/') . '/' . $filename, $contents);
    }

    /**
     * Trigger the synchronization of all provider calendars with Google Calendar.
     *
     * Use this method in a cronjob to automatically sync events between Easy!Appointments and Google Calendar.
     *
     * Notice:
     *
     * Google syncing must first be enabled for each individual provider from inside the backend calendar page.
     *
     * Usage:
     *
     * php index.php console sync
     */
    public function sync()
    {
        $providers = $this->providers_model->get_batch();

        foreach ($providers as $provider)
        {
            if ( ! filter_var($provider['settings']['google_sync'], FILTER_VALIDATE_BOOLEAN))
            {
                continue;
            }

            Google::sync($provider['id']);
        }
    }


    /**
     * Show help information about the console capabilities.
     *
     * Use this method to see the available commands.
     *
     * Usage:
     *
     * php index.php console help
     */
    public function help()
    {
        $help = [
            '',
            'Easy!Appointments ' . config('version'),
            '',
            'Usage:',
            '',
            '⇾ php index.php console [command] [arguments]',
            '',
            'Commands:',
            '',
            '⇾ php index.php console migrate',
            '⇾ php index.php console migrate fresh',
            '⇾ php index.php console seed',
            '⇾ php index.php console install',
            '⇾ php index.php console backup',
            '⇾ php index.php console sync',
            '',
            '',
        ];

        $this->output->set_output(implode(PHP_EOL, $help));
    }
}
