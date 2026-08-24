<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Open Source Web Scheduler
 *
 * @package     EasyAppointments
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) 2013 - 2020, Alex Tselegidis
 * @license     http://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        http://easyappointments.org
 * @since       v1.0.0
 * ---------------------------------------------------------------------------- */

/**
 * User Model
 *
 * Contains current user's methods.
 *
 * @package Models
 */
class User_model extends EA_Model {
    /**
     * User_Model constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->load->library('timezones');
        $this->load->helper('general');
        $this->load->helper('string');
    }

    /**
     * Returns the user from the database for the "settings" page.
     *
     * @param int $user_id User record id.
     *
     * @return array Returns an array with user data.
     */
    public function get_user($user_id)
    {
        $user = $this->db->get_where('users', ['id' => $user_id])->row_array();
        $user['settings'] = $this->db->get_where('user_settings', ['id_users' => $user_id])->row_array();
        unset($user['settings']['id_users']);
        return $user;
    }

    /**
     * This method saves the user record into the database (used in backend settings page).
     *
     * @param array $user Contains the current users data.
     *
     * @return bool Returns the operation result.
     *
     * @throws Exception If the user settings record is missing, or if a new password was not stored.
     */
    public function save_user($user)
    {
        // Hold on to the real user id. The customer mirror further down used to assign the
        // customer's id to $user['id'], which then became the WHERE of the user_settings update -
        // so username, preferences and password were written to a row that does not exist and the
        // save silently did nothing.
        $user_id = $user['id'];

        $user_settings = $user['settings'];
        $user_settings['id_users'] = $user_id;
        unset($user['settings']);

        $settings_record = $this->db->get_where('user_settings', ['id_users' => $user_id])->row();

        if ( ! $settings_record)
        {
            throw new Exception('Vi hittade inga inställningar för ditt konto, så ingenting sparades. Hör av dig till bokningsgruppen så hjälper vi dig.');
        }

        // Prepare user password (hash).
        $new_password_hash = NULL;

        if (isset($user_settings['password']))
        {
            $new_password_hash = hash_password($settings_record->salt, $user_settings['password']);
            $user_settings['password'] = $new_password_hash;
        }

        if ( ! $this->db->update('users', $user, ['id' => $user_id]))
        {
            return FALSE;
        }

        // Family members also have a paired customer record, created right before their login
        // record and carrying the same email. Keep its contact details in sync - but on a copy,
        // so the real user id survives for the user_settings update below.
        $customer = $this->db->get_where('users', ['id' => $user_id - 1])->row();

        if (isset($customer) && $customer->id_roles == '3' && $customer->email == $user['email'])
        {
            $customer_record = $user;
            $customer_record['id'] = $customer->id;
            $this->db->update('users', $customer_record, ['id' => $customer->id]);
        }

        if ( ! $this->db->update('user_settings', $user_settings, ['id_users' => $user_id]))
        {
            return FALSE;
        }

        // Never report success without having written. The database driver reports a successful
        // query even when it matched no row, so read the record back and confirm the new password
        // really landed before letting the caller answer OK.
        if ($new_password_hash !== NULL)
        {
            $stored = $this->db->get_where('user_settings', ['id_users' => $user_id])->row();

            if ( ! $stored || ! hash_equals($new_password_hash, (string)$stored->password))
            {
                throw new Exception('Det nya lösenordet kunde inte sparas. Ditt gamla lösenord fungerar fortfarande, så prova gärna igen eller hör av dig till bokningsgruppen.');
            }
        }

        return TRUE;
    }

    /**
     * Performs the check of the given user credentials.
     *
     * @param string $username Given user's name.
     * @param string $password Given user's password (not hashed yet).
     *
     * @return array|null Returns the session data of the logged in user or null on failure.
     */
    public function check_login($username, $password)
    {
        $salt = $this->get_salt($username);
        $password = hash_password($salt, $password);

        $user_settings = $this->db->get_where('user_settings', [
            'username' => $username,
            'password' => $password
        ])->row_array();

        if (empty($user_settings))
        {
            return NULL;
        }

        $user = $this->db->get_where('users', ['id' => $user_settings['id_users']])->row_array();

        if (empty($user))
        {
            return NULL;
        }

        $role = $this->db->get_where('roles', ['id' => $user['id_roles']])->row_array();

        if (empty($role))
        {
            return NULL;
        }

        $default_timezone = $this->timezones->get_default_timezone();

        return [
            'user_id' => $user['id'],
            'user_email' => $user['email'],
            'username' => $username,
            'timezone' => isset($user['timezone']) ? $user['timezone'] : $default_timezone,
            'role_slug' => $role['slug'],
        ];
    }

    /**
     * Retrieve user's salt from database.
     *
     * @param string $username This will be used to find the user record.
     *
     * @return string Returns the salt db value.
     */
    public function get_salt($username)
    {
        $user = $this->db->get_where('user_settings', ['username' => $username])->row_array();
        return ($user) ? $user['salt'] : '';
    }

    /**
     * Get the given user's display name (first + last name).
     *
     * @param int $user_id The given user record id.
     *
     * @return string Returns the user display name.
     *
     * @throws Exception If $user_id argument is invalid.
     */
    public function get_user_display_name($user_id)
    {
        if ( ! is_numeric($user_id))
        {
            throw new Exception ('Invalid argument given: ' . $user_id);
        }

        $user = $this->db->get_where('users', ['id' => $user_id])->row_array();

        return $user['first_name'] . ' ' . $user['last_name'];
    }

    /**
     * If the given arguments correspond to an existing user record, generate a new
     * password and send it with an email.
     *
     * @param string $username User's username.
     * @param string $email User's email.
     * @param callable|null $deliver Receives the new password and must deliver it. If it throws,
     *                               the new password is NOT stored and the old one keeps working.
     *
     * @return string|bool Returns the new password on success or FALSE on failure.
     */
    public function regenerate_password($username, $email, callable $deliver = NULL)
    {
        $result = $this->db
            ->select('users.id')
            ->from('users')
            ->join('user_settings', 'user_settings.id_users = users.id', 'inner')
            ->where('users.email', $email)
            ->where('user_settings.username', $username)
            ->get();

        if ($result->num_rows() == 0)
        {
            return FALSE;
        }

        $user_id = $result->row()->id;

        // Create a new password and send it with an email to the given email address.
        $new_password = random_string('alnum', 12);
        $salt = $this->db->get_where('user_settings', ['id_users' => $user_id])->row()->salt;
        $hash_password = hash_password($salt, $new_password);

        // Deliver first, persist second. Storing the new password before the email is known to have
        // left the building would lock the user out whenever delivery fails.
        if ($deliver !== NULL)
        {
            $deliver($new_password);
        }

        $this->db->update('user_settings', ['password' => $hash_password], ['id_users' => $user_id]);

        return $new_password;
    }

    /**
     * Get the timezone of a user.
     *
     * @param int $id Database ID of the user.
     *
     * @return string|null
     */
    public function get_user_timezone($id)
    {
        $row = $this->db->get_where('users', ['id' => $id])->row_array();

        return $row ? $row['timezone'] : NULL;
    }
}
