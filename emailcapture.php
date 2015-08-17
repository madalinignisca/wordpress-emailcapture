<?php
/*
Plugin Name: Email Capture
Plugin URI: http://wordpress.org/plugins/emailcapture/
Description: Simple email capture plugin offering a download in return. Shortcode based with exportable emails list.
Author: Madalin Ignisca
Version: 0.1.0
Author URI: http://www.gabe.me.uk/
License: GPLv2 or later
*/

/*
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

// Make sure we don't expose any info if called directly (from Akistmet)
if ( !function_exists( 'add_action' ) ) {
    echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
    exit;
}

define( 'EMAILCAPTURE__VERSION', '1.0.0' );
define( 'EMAILCAPTURE__MINIMUM_WP_VERSION', '4.2' );
define( 'EMAILCAPTURE__PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'EMAILCAPTURE__PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

// register_activation_hook( __FILE__, array( 'EmailCapture', 'plugin_activation' ) );
// register_deactivation_hook( __FILE__, array( 'EmailCapture', 'plugin_deactivation' ) );

global $wpdb;

$emailcapture_table = $wpdb->prefix . "emailcaptures";

function emailcapture_options_install() {
    global $wpdb;
    $emailcapture_table = $wpdb->prefix . "emailcaptures";

    if($wpdb->get_var("show tables like '{$emailcapture_table}'") != $emailcapture_table)
    {
        $sql = "CREATE TABLE " . $emailcapture_table . " (
            `id` int NOT NULL AUTO_INCREMENT,
            `email` text NOT NULL,
            `other_fields` text NULL,
            `created_at` datetime NOT NULL,
            UNIQUE KEY id (id)
		);";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

}
// run the install scripts upon plugin activation
register_activation_hook(__FILE__,'emailcapture_options_install');

// Form fields template
function emailcapture_field( $field_label, $field_name) {
    if ($field_name == 'name') {
        return "<p class=\"warning\">Sorry, you can't use <em>name</em> as a field. Try <em>visitor_name</em> or similar please.</p>\n
This is a WordPress limitation!";
    }
    $field = '<div class="form-field">';
    $field .= '<label for="' . $field_name . '">' . $field_label . '</label>';
    $field .= '<input type="text" name ="' . $field_name . '" />';
    $field .= '</div>';

    return $field;
}

// Form template
function emailcapture_form( $fields = null ) {
    $form = '<div class="ec-form">';
    $form .= '<form method="post">';
    if (isset($fields) && !empty($fields)) {
        foreach ($fields as $field_name => $field_label) {
            $form .= emailcapture_field($field_label, $field_name);
        }
    } else {
        $form .= '<div class="form-field">';
        $form .= '<label for="email">Email</label>';
        $form .= '<input type="email" name="email" />';
        $form .= '</div>';
    }
    $form .= '<div class="form-field">';
    $form .= '<input type="text" name="website-x" class="hidden" />';
    $form .= '<input type="text" name="email-x" class="hidden" />';
    $form .= '<input type="hidden" name="submited" value="_submited" />';
    $form .= '<input type="submit" name="submit" value="DOWNLOAD" />';
    $form .= '</div>';
    $form .= '</form>';
    $form .= '</div>';

    return $form;
}

// add shortcode
function emailcapture_shortcode( $atts, $content = null )
{
    if (isset($_POST) && ($_POST['submited'] === '_submited')) {
        if (isset($content) && $content != '') {
            $original_atts = $atts;
            $response = '<div class="ec-response"><p>'.$content.'</p></div>';

            // sanitise & add to the database
            $fields = array();
            $fields['email'] = sanitize_email($_POST['email']);
            unset($_POST['email']);
            unset($atts['email']);
            if(!empty($atts)) {
                $other_fields = '';
                foreach($atts as $key => $value) {
                    $other_fields .= "{$value}: " . sanitize_text_field($_POST[$key]) . "; ";
                }
                $fields['other_fields'] = $other_fields;
            }

            if($fields['email'] == '') {
                $response = '<p class="ec-error">Sorry, that does\'nt appear to be a correct email address</p>';
                $response .= emailcapture_form($original_atts);
            } else {

                global $wpdb;
                $table = $wpdb->prefix . 'emailcaptures';
                date_default_timezone_set('UTC');
                $wpdb->insert($table, array(
                    'email' => $fields['email'],
                    'other_fields' => $fields['other_fields'],
                    'created_at' => date("Y-m-d H:i:s"),
                ));

            }

        } else {
            $response = '<p class="warning">You forgot to set the response content!<br />Go back and edit the post with proper content.</p>';
        }
    } else {
        $response = emailcapture_form($atts);
    }
    return $response;
}
add_shortcode('ec', 'emailcapture_shortcode');

// add admin page to the Dashboard
function emailcapture_admin_page() {
    include(EMAILCAPTURE__PLUGIN_DIR . "inc/admin.php");
}

function emailcaputre_admin_export_csv() {
    include(EMAILCAPTURE__PLUGIN_DIR . "inc/export.php");
}

function emailcapture_menu() {
    add_dashboard_page( 'Email captures', 'Email captures', 'activate_plugins', 'emailcaptures', 'emailcapture_admin_page');
    // add_menu_page('Email captures', 'Email captures', 'activate_plugins', 'emailcaptures', 'emailcapture_admin_page', 'dashicons-book');
    // add_submenu_page('emailcaptures', 'Export CSV', 'Export CSV', 'activate_plugins', 'emailcaptures_export_csv', 'emailcaputre_admin_export_csv');
}
add_action('admin_menu', 'emailcapture_menu');

// export action
function emailcapture_export() {
    if( ! current_user_can('manage_options') ) {
        return;
    }

    include (EMAILCAPTURE__PLUGIN_DIR . "inc/export.php");
}
add_action( 'admin_post_emailcaptures_export', 'emailcapture_export');