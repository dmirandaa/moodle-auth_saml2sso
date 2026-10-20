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
 * Strings for component 'auth_saml2sso', language 'en'.
 *
 * @package auth_saml2sso
 * @author Daniel Miranda <daniellopes at gmail.com>
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
$string['auth_saml2ssodescription']                 = 'Users can login using SAML2 Identity Provider';
$string['pluginname']                               = 'SAML2 SSO Auth';
$string['settings_saml2sso']                        = '';

// Label config strings.
$string['label_button_url']                         = 'Url to button (image)';
$string['label_button_name']                        = 'Button caption';
$string['label_show_button_name']                   = 'Show button caption';
$string['label_sp_path']                            = 'SimpleSAMLphp library path';
$string['label_dual_login']                         = 'Dual login';
$string['label_single_signoff']                     = 'Single Sign Off';
$string['label_idpattr']                            = 'Username attribute';
$string['label_moodle_mapping']                     = 'Username checking';
$string['label_autocreate']                         = 'Auto create users';
$string['label_authsource']                         = 'SP auth source name';
$string['label_logout_url_redir']                   = 'Logout URL';
$string['label_logout']                             = 'Click here to logout';
$string['label_edit_profile']                       = 'Can user edit profile?';
$string['label_instructions_title']                 = 'Instructions';

// Help config strings.
$string['help_button_url']                          = 'Url to an image that will be used as login button. Max 50px high';
$string['help_show_button_name']                    = 'Decide if the button caption should be displayed. A simple way to remove the text from the button';
$string['help_button_name']                         = 'A caption for the login button';
$string['help_sp_path']                             = 'Absolute path to Service Provider (SP) installation. Ex.: /var/www/simplesamlphp/';
$string['help_dual_login']                          = 'Define if prompt the user with standard Moodle login page';
$string['help_single_signoff']                      = 'Single Sign Off users from Moodle and IdP?';
$string['help_idpattr']                             = 'Attribute from the Identity Provider used as the Moodle username.';
$string['help_moodle_mapping']                      = 'Where to check if the username exists? If using \'Email address\' or \'' .
        get_string('idnumber') . '\', remember to mapping in Data mapping below';
$string['help_autocreate']                          = 'Allow create new users?';
$string['help_authsource']                          = 'Service Provider authentication source name available in /config/authsources.php SimpleSAMLphp installation';
$string['help_logout_url_redir']                    = 'URL to redirect users on logout. If the URL is invalid or empty, it will redirect to Moodle main page. (ex.: https://goto/another/url). Remember to include this url in the <tt>trusted.url.domains</tt> SSP config.';
$string['nouser']                                   = 'User not found in this site and auto-registration is not allowed. Login failed.';
$string['duplicateuser']                            = 'Authentication method is not valid or is disable, try another one if available or contact the site admin.';
$string['help_edit_profile']                        = 'If users cannot edit profile, they won\'t see the link to profile. ' .
        'If the IdP/ADFS doesn\'t provide mandatory attribute the user will be locked out!';
$string['help_session_control']                     = 'Apply the global setting \'{$a}\' if it is >= 1, except for admin users.';
$string['label_force_authn']                        = 'Always request auth';
$string['help_force_authn']                         = 'At the Moodle login, user must authenticate even if he/she has a valid SSO session.';

// Error config strings.
$string['error_create_user']                        = 'A error occured when create a user account. Please, contact the administrator.';
$string['error_sp_path']                            = 'The path to SimpleSAMLphp libraries must be given in config';
$string['error_idpattr']                            = 'A Username mapping attribute must be given';
$string['error_authsource']                         = 'A Service Provider source name must be given';
$string['error_you_are_still_connected']            = 'You are still connected in a SSO session';
$string['error_nokey']                              = 'The Identity Provider has not provide the attribute need to identify you';

$string['success_config']                           = 'All the config fields were saved successfully';

$string['label_profile_settings']                   = 'SAML attributes and user profile';

$string['label_dual_login_settings']  = 'Dual login';
$string['label_dual_login_help']   = '
By default Dual login is setted to No and users are redirect to the IdP or discovery service
configured in the SimpleSAMLphp authentication source.<br />
To perform Moodle standard login, add saml=off parameter. Ex.: /login/index.php?saml=off<br />
In Moodle 4.5 and later, saml=off cannot display the manual login form if it has been disabled in Manage authentication.<br />
Enabling Dual login users have to choose the authentication method.';
$string['label_sync_settings']        = 'Users sync';
$string['label_sync_settings_help']   = '
SAML IdPs cannot provide a user list suitable to users synchronization,
however they are often backended on a LDAP o DB source able to.
Configure the plugin for the backend authentication source.';
$string['label_user_directory']       = 'User source';
$string['help_user_directory']        = 'An auth plugin with listing capability';
$string['label_do_update']            = 'Update profiles';
$string['help_do_update']             = 'Profile fields of existing users will be update
with values from user source. If "no", only new users in user source will be created locally.
If "yes", user synchronization could overwrite values set at login by the IdP.';
$string['label_verbose_sync']        = 'Show details';
$string['help_verbose_sync']         = 'Enable verbose report';

$string['synctask']        = 'Users sync';

$string['label_hide_takeover_page']       = 'Hide import page';
$string['help_hide_takeover_page']        = '
The import page item appears in the admin menu only if there are
users belonging to other auth plugins that can be takeover.
It can be annoying if you want leave these users as-is.';

$string['takeover']             = 'Migrate users to ' . $string['pluginname'];
$string['label_takeover_link'] = '
There are still users handled by plugins compatible with this
one. Do you want <a href="{$a}">import them<a>?';
$string['label_takeover']       = 'Takeover existing users';
$string['help_takeover']        = '
Existing users belonging to the auth plugins listed below could be
converted to use ' . $string['pluginname'] . ' authentication.
<br />Deleted users will not migrate.';
$string['label_takeover_plugin']            = '{$a->auth} ({$a->count} active users)';
$string['label_takeover_unknown_plugin']    = 'Deleteted or corrupted "{$a->auth}" plugin ({$a->count} active users)';

$string['takeover_nouser']      = 'No plugin selected or plugins with no users';
$string['takeover_completed']   = 'Users converted';
$string['takeover_submit']      = 'Convert to ' . $string['pluginname'];
$string['takeover_count_migrated']      = '{$a->count} users imported from {$a->auth}<br />';
$string['event_user_migrate']       = 'User imported';
$string['event_user_migrate_desc']  = 'The user has been migrated to ' . $string['pluginname'];
$string['event_not_searchable']         = 'Not identifiable SSO user';
$string['event_not_searchable_desc']    = 'The IdP doesn\'t provided any attributes need to search for the user';
$string['event_user_kicked_off']        = 'Old sessions killed';
$string['event_user_kicked_off_desc']   = 'The user has activated a new Moodle session while the concurrent login limit was active: old sessions have been destroyed and unsaved data ignored';
$string['event_duplicate_user']       = 'Duplicate user';
$string['event_duplicate_user_desc']  = 'A user with {$a->key} \'{$a->id}\' is already registered by the plugin {$a->auth}. Cannot create a new account ' . $string['pluginname'];

$string['privacy:metadata'] = 'The SAML2 SSO authentication plugin does not store any personal data.';
