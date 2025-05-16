<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @package auth_saml2sso
 * @author Daniel Miranda <daniellopes at gmail.com>
 * @author Marco Ferrante, AulaWeb/University of Genoa <staff@aulaweb.unige.it>
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * Parts of the code was original made for another moodle plugin available at
 * https://moodle.org/plugins/auth_saml2
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/authlib.php');
require_once($CFG->dirroot . '/auth/saml2sso/locallib.php');

/**
 * Plugin for authentication using SimpleSAMLphp Service Provider
 * For SimpleSAMLphp instructions, go to https://simplesamlphp.org/
 */
class auth_plugin_saml2sso extends auth_plugin_base {

    /**
     * The name of the component. Used by the configuration.
     */
    const COMPONENT_NAME = \auth_saml2sso\COMPONENT_NAME;

    /**
     * Config vars
     * @var string
     */
    public $defaults = array(
        'sp_path' => '',
        'dual_login' => 1,
        'single_signoff' => 1,
        'idpattr' => '',
        'moodle_mapping' => 'username',
        'autocreate' => 0,
        'authsource' => '',
        'logout_url_redir' => '',
        'edit_profile' => 0,
        'allow_empty_email' => 0,
        'delete_if_empty' => false,  // Delete the profile field value if the correspondig attribute is missing/empty.
    );

    /**
     * Mapping vars
     * @var string
     */
    public static $stringmapping = array(
        'email' => 'email',
        'idnumber' => 'idnumber',
    );

    public object $mapping;

    /**
     * Constructor
     */
    public function __construct() {
        $this->authtype = \auth_saml2sso\AUTH_NAME;
        $componentName = (array) get_config(self::COMPONENT_NAME);
        $this->config = (object) array_merge($this->defaults, $componentName);
        $this->mapping = (object) self::$stringmapping;
    }

    /**
     * Load SimpleSAMLphp library autoloader
     *
     * @since 3.6.0 Dropped support for non namespaced functions
     */
    private function getsspauth() {
        $found = auth_saml2sso\load_ssp_lib($this->config->sp_path);
        if (!$found) {
            throw new \Exception('SimpleSAMLphp libraries not found, check configuration');
        }
        
        return new \SimpleSAML\Auth\Simple($this->config->authsource);
    }

    /**
     * Makes the saml2 plugin appear as a idsp on login screen
     * @param string $wantsurl
     * @return array
     * Added by Praxis
     */
    public function loginpage_idp_list($wantsurl) {
        $url = '/login/?saml=on';

        if (!empty($this->config->button_url)) {
            $button_path = new moodle_url($this->config->button_url);
        } else {
            $button_path = new moodle_url('/auth/saml2sso/pix/login-btn.png');
        }

        $button_name = 'SAML Login';
        if (!empty(trim($this->config->button_name))) {
            $button_name = (new moodle_url($this->config->button_name))->out();
        }

        if ($this->config->show_button_name === '0') {
            $button_name = '';
        }

        return [[
            'url' => new moodle_url($url),
            'name' => format_string($button_name),
            'iconurl' => $button_path
        ]];
    }

    protected function get_attribute_mapping($username_attribute = null) {
        $configarray = (array) $this->config;

        $moodleattributes = array();
        $userfields = array_merge($this->userfields, $this->get_custom_user_profile_fields());
        foreach ($userfields as $field) {
            if (isset($configarray["field_map_$field"])) {
                $moodleattributes[$field] = $configarray["field_map_$field"];
            }
        }

        if ($username_attribute) {
            $moodleattributes['username'] = $username_attribute;
        }

        return $moodleattributes;
    }

    /**
     * Normalize the username from IdP because:
     * - SAML username are case insensitive and Moodle isn't;
     * - SAML username has no length limit and Moodle have it.
     * 
     * @global type $DB
     * @param type $username
     * @return type
     */
    private function normalize_username($username) {
        global $DB;

        $uid = trim(core_text::strtolower($username));
        $dbfieldinfo = $DB->get_columns('user');
        if (strlen($uid) >= $dbfieldinfo['username']->max_length) {
            // If longer than the Moodle field, it uses an hash.
            $uid = sha1($uid);
        }

        return $uid;
    }

    /**
     * Read user information from the current simpleSAMLphp session.
     *
     * @param string $username username, if not the current SSO user, returns false
     *
     * @return mixed array with no magic quotes or false on error
     */
    public function get_userinfo($username) {
        $auth = $this->getsspauth();
        if (!$auth->isAuthenticated()) {
            return false;
        }

        $attributes = $auth->getAttributes();
        $uid = $this->normalize_username($attributes[$this->config->idpattr][0]);
        if ($this->normalize_username($username) != $uid) {
            // Not the current user.
            return false;
        }

        $attrmap = $this->get_attribute_mapping($this->config->idpattr);

        $result = array();
        foreach ($attrmap as $key => $value) {
            // Check if attribute is present.
            if (empty($attributes[$value])) {
                // If the IdP aggregate different information sources, an attribute
                // can be missing due to a temporary problem. It is unreasobable
                // deleting the old value.
                if ($this->config->delete_if_empty) {
                    $result[$key] = '';
                }
                continue;
            }

            // Make usename lowercase.
            if ($key == 'username') {
                $result[$key] = $this->normalize_username($attributes[$value][0]);
            } else {
                $result[$key] = $attributes[$value][0];
            }
        }

        return $result;
    }

    /**
     * @global string $SESSION
     * @return type
     */
    public function loginpage_hook() {
        global $SESSION;

        if(!isset($SESSION->saml)) {
            $SESSION->saml = '';
        }

        $saml = optional_param('saml', $SESSION->saml, PARAM_TEXT);

        // Check if dual login is enabled.
        // Can bypass IdP auth.
        // To bypass IdP auth, go to <moodle-url>/login/index.php?saml=off
        // Thanks to Henrik Sune Pedersen.
        if ((int) $this->config->dual_login && $saml !== 'on') {
            $saml = 'off';
        }

        // If saml=off, go to default login page regardless any other
        // settings. Useful to administrators to recover from misconfiguration.
        if ($saml == 'off') {
            $SESSION->saml = 'off';
            return;
        }

        // If dual login is disabled or saml=on, the user is redirect to the IdP.
        if ($saml == 'on') {
            $SESSION->saml = 'on';
            $this->saml2_login();
        }

        // Otherwise, is checked the last option in session.
        if (!empty($SESSION->saml) && $SESSION->saml == 'off') {
            return;
        }
        if ($this->config->dual_login) {
            return;
        }
        $this->saml2_login();
    }

    /**
     * Post logout hook, triggers the SLO process.
     *
     * @param stdClass $user clone of USER object object before the user session was terminated
     */
    public function postlogout_hook($user) {
         global $CFG;

        if ($user->auth != $this->authtype) {
            // SingleLogOut must not be called for user handled by other plugins.
            return;
        }

        // Check if we need to sign off user from IdP too.
        if ((int) $this->config->single_signoff) {
            $auth = $this->getsspauth();

            if (filter_var($this->config->logout_url_redir, FILTER_VALIDATE_URL)) {
                $returnTo = $this->config->logout_url_redir;
                try {
                    // Moodle session is already destroyed, but if the config redirects
                    // to a untrusted URL the user will receive a useless exception.
                    (new \SimpleSAML\Utils\HTTP())->checkURLAllowed($returnTo);
                } catch (\Exception $e) {
                    debugging($returnTo . ' is not allowed as redirect URL, check your SSP config.', DEBUG_NORMAL);
                    $returnTo = $CFG->wwwroot; // The root should always be trusted.
                }
            }
            else {
                $returnTo = $CFG->wwwroot;
            }
          
            $urllogout = $auth->getLogoutURL($returnTo);
            redirect($urllogout);
        }
    }

    /**
     * Do all the magic during SSO login procedure
     * @global type $DB
     * @global type $USER
     * @global type $CFG
     */
    public function saml2_login() {
        global $DB, $USER, $CFG;

        $auth = $this->getsspauth();
        $param = ['KeepPost' => true];
        if ($this->config->force_authn) {
            $param['ForceAuthn'] = true;
        }

        // Admins can have multiple sessions.
        $apply_session_control = !is_siteadmin($USER->id)
                && $this->config->session_control
                && $CFG->limitconcurrentlogins >= 1;
        if ($apply_session_control) {
            // Force a reauthentication even if a SSO session is active in the SP.
            // Throw away the POST values because after reauthentication user must
            // fill the form again: session control is used in exams or similar
            // situation, if we keep the POST data, cheating is still possible.
            $param = ['ForceAuthn' => true, 'KeepPost' => false];
        }
        // Retrieve the Moodle session ID from the local SSO session data
        $sspsession = \SimpleSAML\Session::getSessionFromRequest();
        $prevmoodlesession = $sspsession->getData('\Moodle\\' . \auth_saml2sso\COMPONENT_NAME, 
            'moodle:' . get_site_identifier() . 'session'
        );

        // Moodle session changed within the same local SSO session.
        if (!empty($prevmoodlesession) && $prevmoodlesession != session_id()) {
            if ($apply_session_control) {
                $event = \auth_saml2sso\event\user_kicked_off::create();
                $event->trigger();
            }
            $sspsession->deleteData('\Moodle\\' . \auth_saml2sso\COMPONENT_NAME, 
                'moodle:' . get_site_identifier() . 'session'
            );
            $auth->login($param);
        }
        else {
            $auth->requireAuth($param);
        }

        // Save the Moodle session ID in the local SSO session data.
        $timeout = !empty($CFG->sessiontimeout) && intval($CFG->sessiontimeout) > 0 ? intval($CFG->sessiontimeout) : null;
        $sspsession->setData('\Moodle\\' . \auth_saml2sso\COMPONENT_NAME, 
            'moodle:' . get_site_identifier() . 'session',
            session_id(),
            $timeout
        );
            
        $saml_attributes = $auth->getAttributes();  // Attributes from SAML assertion.
        // User Id returned from IdP
        // Will be used to get user from our Moodle database if exists
        // create_user_record lowercases the username, so we need to lower it here.
        if (empty($saml_attributes[$this->config->idpattr])) {
            $event = \auth_saml2sso\event\not_searchable::create();
            $event->trigger();
            $this->error_page(get_string('error_nokey', \auth_saml2sso\COMPONENT_NAME));
        }
        $uid = $this->normalize_username($saml_attributes[$this->config->idpattr][0]);

        // Get attributes from the assertion already mapped to Moodle fields.
        $userinfo = $this->get_userinfo($uid);
        if (empty($userinfo[$this->config->moodle_mapping])) {
            $event = \auth_saml2sso\event\not_searchable::create();
            $event->trigger();
            $this->error_page(get_string('error_nokey', \auth_saml2sso\COMPONENT_NAME));
        }
        // Now we check if the key returned from IdP exists in the Moodle user database
        // for this authentication type.
        $criteria = [
            'auth' => $this->authtype,
            $this->config->moodle_mapping => $userinfo[$this->config->moodle_mapping]
        ];
        $isuser = $DB->get_record('user', $criteria);
        
        if (!empty($isuser)) {
            core_user::require_active_user($isuser, true, true);
        }
        else {
            // Verify if user can be created.
            if ((int) $this->config->autocreate) {
                // Check the user is not existing belonging another plugin.
                $criteria = [
                    $this->config->moodle_mapping => $userinfo[$this->config->moodle_mapping]
                ];
                $exists = $DB->get_record('user', $criteria);
                if ($exists) {
                    // Log the event only if dual login is disable; if enable could be a wrong action by the user.
                    if (!$this->config->dual_login) {
                        $event = \auth_saml2sso\event\duplicate_user::event_duplicate_user($this->config->moodle_mapping,
                                s($userinfo[$this->config->moodle_mapping]), $exists->auth);
                        $event->trigger();
                    }
                    $this->error_page(get_string('duplicateuser', self::COMPONENT_NAME));
                }
                // Insert new user.
                $isuser = create_user_record($uid, '', $this->authtype);
            } else {
                // If autocreate is not allowed, show error.
                $this->error_page(get_string('nouser', self::COMPONENT_NAME));
            }
        }

        // We expected that here we have a existing user or a new one.
        if ($isuser) {
            $USER = get_complete_user_data('username', $isuser->username);
        } else {
            $this->error_page(get_string('error_create_user', self::COMPONENT_NAME));
        }

        $isuser = update_user_record_by_id($isuser->id);

        // now we get the URL to where user wanna go previouly.
        $urltogo = core_login_get_return_url();

        // and pass to login method
        $this->do_login($isuser, $urltogo);
    }

    /**
     * Do Moodle login will set session and cookie to authenticated user
     * @global type $USER
     * @global type $CFG
     * @param type $urltogo
     */
    protected function do_login($user, $urltogo) {
        global $USER, $CFG;
        
        $USER = complete_user_login($user);
        $USER->loggedin = true;
        $USER->site = $CFG->wwwroot;
        set_moodle_cookie($USER->username);

        $apply_session_control = !is_siteadmin($USER->id)
                && $this->config->session_control
                && $CFG->limitconcurrentlogins == 1;
        if ($apply_session_control) {
            // https://tracker.moodle.org/browse/MDL-62753?jql=text%20~%20%22session%20kill%22
            // moodle\auth\shibboleth\classes\helper.php

            // Honour limit Concurrent Logins.
            // https://moodle.org/mod/forum/discuss.php?d=387784
            \core\session\manager::apply_concurrent_login_limit($user->id, session_id());
        }

        // If we are not on the page we want, then redirect to it.
        if (qualified_me() !== $urltogo) {
            redirect($urltogo);
            exit;
        }
    }

    /**
     * Returns true if the username and password work or don't exist and false
     * if the user exists and the password is wrong.
     *
     * @param string $username The username
     * @param string $password The password
     * @return bool Authentication success or failure.
     */
    public function user_login($username, $password) {
        return false;
    }

    /**
     * Updates the user's password.
     *
     * called when the user password is updated.
     *
     * @param  object  $user        User table object
     * @param  string  $newpassword Plaintext password
     * @return boolean result
     *
     */
    public function user_update_password($user, $newpassword) {
        return false;
    }

    /**
     *
     * @return boolean
     */
    public function prevent_local_passwords() {
        return true;
    }

    /**
     * Returns true if this authentication plugin is 'internal'.
     *
     * @return bool
     */
    public function is_internal() {
        return false;
    }

    /**
     * Returns true if this authentication plugin can change the user's
     * password.
     *
     * @return bool
     */
    public function can_change_password() {
        return false;
    }

    /**
     * Returns the URL for changing the user's pw, or empty if the default can
     * be used.
     *
     * @return moodle_url
     */
    public function change_password_url() {
        return null;
    }

    /**
     * Returns true if plugin allows resetting of internal password.
     *
     * @return bool
     */
    public function can_reset_password() {
        return false;
    }

    /**
     * The plugin can be manually set in csv import.
     *
     * @return bool
     */
    public function can_be_manually_set() {
        return true;
    }

    /**
     * @return type
     */
    public function can_edit_profile() {
        return (int) $this->config->edit_profile;
    }

    /**
     * In loginpage_hook() the page rendering is not active yet, so is not
     * enoght to throw an Exception.
     * Probably should be better to invoke Moodle fatal_error() function
     * instead of rewriting it, but Moodle docs discourage this.
     *
     * @global type $PAGE
     * @global type $OUTPUT
     * @global type $SITE
     * @param type $msg
     */
    protected function error_page($msg) {
        global $PAGE, $OUTPUT, $SITE;

        $auth = $this->getsspauth();

        $urltogo = $this->config->logout_url_redir;
        if (empty($urltogo)) {
            $urltogo = (new moodle_url('/'))->out();
        }
        $logouturl = $auth->getLogoutURL($urltogo);

        $PAGE->set_course($SITE);
        $PAGE->set_url('/');
        $PAGE->set_title(get_string('error') . ' - ' . $msg);
        $PAGE->set_heading($PAGE->course->fullname);
        echo $OUTPUT->header();
        echo $OUTPUT->box($msg, 'errorbox alert alert-danger', null, array('data-rel' => 'fatalerror'));
        echo $OUTPUT->box(get_string('error_you_are_still_connected', self::COMPONENT_NAME)
                . ' <a href="' . $logouturl . '">'
                . get_string('label_logout', self::COMPONENT_NAME) . '</a>', 'errorbox alert');
        echo $OUTPUT->footer();
        exit;
    }

    /**
     * Test if settings are correct, print info to output.
     * @author Marco Ferrante <marco at csita.unige.it>
     */
    public function test_settings() {
        global $OUTPUT;
        $warning = false;

        // NOTE: this is not localised intentionally, admins are supposed to understand English at least a bit...

        if (empty($this->config->sp_path)) {
            echo $OUTPUT->notification('SimpleSAMLphp lib path not set', \core\output\notification::NOTIFY_ERROR);
            return;
        }
        if (!empty(getenv('SIMPLESAMLPHP_CONFIG_DIR')) && $this->config->sp_path != dirname(getenv('SIMPLESAMLPHP_CONFIG_DIR'))) {
            echo $OUTPUT->notification('SimpleSAMLphp lib path differs from the environment default ('
                    . dirname(getenv('SIMPLESAMLPHP_CONFIG_DIR'))
                    . '): it could be fine, but check if the library has been updated', \core\output\notification::NOTIFY_WARNING);
            $warning = true;
        }
        if (!file_exists($this->config->sp_path . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . '_autoload.php')) {
            echo $OUTPUT->notification('SimpleSAMLphp version seems < 2.x or lib path is invalid', \core\output\notification::NOTIFY_ERROR);
            return;
        }
        if (!file_exists($this->config->sp_path . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.php')) {
            echo $OUTPUT->notification('SimpleSAMLphp seems not configured yet or config path is invalid', \core\output\notification::NOTIFY_ERROR);
            return;
        }

        require($this->config->sp_path . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . '_autoload.php');
        $sspconfig = \SimpleSAML\Configuration::getInstance();
        if (version_compare($sspconfig->getVersion(), '2.0.0') < 0) {
            echo $OUTPUT->notification('SimpleSAMLphp lib version ('
                    . $sspconfig->getVersion() . ') is not supported, this plugin requires a 2.x release', \core\output\notification::NOTIFY_ERROR);
            return;
        }
        elseif (version_compare($sspconfig->getVersion(), '2.3.4') < 0) {
            echo $OUTPUT->notification('SimpleSAMLphp lib seems too old ('
                    . $sspconfig->getVersion() . ') and insicure: you should upgrade it', \core\output\notification::NOTIFY_WARNING);
            $warning = true;
        }
        else {
            echo $OUTPUT->notification('SimpleSAMLphp version is ' . $sspconfig->getVersion(), \core\output\notification::NOTIFY_INFO);
        }

        @include($this->config->sp_path . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.php');
        if ($config['store.type'] == 'phpsession') {
            echo $OUTPUT->notification('It seems SimpleSAMLphp uses default PHP session storage, it could be troublesome: switch to another store.type in config.php',
                    \core\output\notification::NOTIFY_WARNING);
            $warning = true;
        }

        $sourcesnames = array_map(function($source){
            return $source->getAuthId();
        }, \SimpleSAML\Auth\Source::getSourcesOfType('saml:SP'));
        if (empty($this->config->authsource) || !in_array($this->config->authsource, $sourcesnames)) {
            echo $OUTPUT->notification('Invalid authentication source. Available sources: '
                    . implode(', ', $sourcesnames), \core\output\notification::NOTIFY_WARNING);
            $warning = true;
        }
        else {
            $auth = $this->getsspauth();
            if (empty($auth)) {
                echo $OUTPUT->notification('SimpleSAMLphp library loading failed!', \core\output\notification::NOTIFY_ERROR);
                return;
            }
        }
        
        if (!empty($this->config->logout_url_redir)) {
            try {
                (new \SimpleSAML\Utils\HTTP())->checkURLAllowed($this->config->logout_url_redir);
            }
            catch (\Exception $e) {
                echo $OUTPUT->notification($this->config->logout_url_redir . ' is not allowed as redirect URL, check SSP config.', \core\output\notification::NOTIFY_WARNING);
                $warning = true;
            }
        }

        if (empty($this->config->idpattr)) {
            echo $OUTPUT->notification('The attribute from the IdP to use as Moodle Username is not set',
                    \core\output\notification::NOTIFY_WARNING);
            $warning = true;
        }
        else {
            $attrmap = $this->get_attribute_mapping($this->config->idpattr);
            if (empty($attrmap[$this->config->moodle_mapping])) {
                echo $OUTPUT->notification('The user will be search by ' . $this->config->moodle_mapping
                        . ' but no attribute from the IdP is map to this field',
                        \core\output\notification::NOTIFY_WARNING);
                $warning = true;
            }
        }

        if (!empty($this->config->user_directory)) {
            $plugin = get_auth_plugin($this->config->user_directory);
            if (!$plugin) {
                echo $OUTPUT->notification('Invalid directory plugin \''
                        . $this->config->user_directory . '\'', \core\output\notification::NOTIFY_WARNING);
                $warning = true;
            }
            if (method_exists($plugin, 'test_settings')) {
                $options[$this->config->user_directory] = get_string('pluginname', 'auth_'.$this->config->user_directory);
                $url = new moodle_url('/auth/test_settings.php', array('sesskey'=>sesskey(), 'auth' => $this->config->user_directory));
                echo $OUTPUT->notification('A sync process with \'' . get_string('pluginname', 'auth_'.$this->config->user_directory)
                        . '\' auth plugin is enabled. <a href="' . $url
                        . '">Check its configuration</a>.', \core\output\notification::NOTIFY_INFO);
            }
            else {
                echo $OUTPUT->notification('A sync process with \'' . get_string('pluginname', 'auth_'.$this->config->user_directory)
                        . '\' auth plugin is enabled. Please check its configuration too.', \core\output\notification::NOTIFY_INFO);
            }
        }

        if (!$this->config->edit_profile && $this->config->allow_empty_email) {
            echo $OUTPUT->notification('The plugin accepts SAML assertion with empty '
                    . 'e-mail address, but the user is not enabled to edit '
                    . 'his profile to add it by himself. Users without e-mail will be locked out by Moodle.',
                    \core\output\notification::NOTIFY_WARNING);
            $warning = true;
        }

        if ($warning) {
            echo $OUTPUT->notification('You have some minor issue, authentication will still work', \core\output\notification::NOTIFY_WARNING);
        }
        else {
            echo $OUTPUT->notification('Everything seems ok', \core\output\notification::NOTIFY_SUCCESS);
        }
    }

}
