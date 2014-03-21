<?php

/*
Plugin Name: FFG - Gravity Forms Trello Addon
Plugin URI: http://www.splicertechnology.com
Description: Integrates Gravity Forms with Trello to allow creation of cards and checklists based on Filthy Farm Girl's requirements
Version: 0.1
Author: Splicer Technology
Author URI: http://www.splicertechnology.com

------------------------------------------------------------------------
Copyright 2014 splicer technology

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
*/

add_action('init',  array('GFTrello', 'init'));
add_action('admin_init',  array('GFTrello', 'admin_init'));

define("GF_TRELLO_SETTINGS_URL", site_url() .'/wp-admin/admin.php?page=gf_settings&addon=Trello');
define("GF_TRELLO_CARD_NAME_FIELD", "Name of Shop");

class GFTrello {

    /**
     * This function inits the front end code and adds our after_submission hook
     */
	public static function init() {
		/* check Gravity Forms is installed */
		if(!self::check_gf_install())
			return;

		add_action("gform_after_submission", array('GFTrello', 'after_submission'), 10, 2);
	}

    /**
     * This function inits the admin
     */
    public static function admin_init(){

        /* check Gravity Forms is installed */
		if(!self::check_gf_install())
		{
			return;
		}

		self::admin_css();

        /* configure the settings page*/
    	self::settings_page();
    }

    private function check_gf_install() {
        if(!class_exists("GFCommon")){
          return false;
        }
		return true;
	}

    /**
     * Add CSS in the admin area
     *
     */
	public static function admin_css()
	{
		wp_enqueue_style('gf-trello-css', plugins_url( 'gf-trello.css' , __FILE__ ));
	}

    /* check if we're on the settings page */
    //@todo I think we would have a second settings page function if we did freshbooks integration
	private function settings_page() {

        if(RGForms::get("page") == "gf_settings" || rgget("oauth_token")){
			/* do validation before page is loaded */
			if(rgpost("gf_trello_update")){
				/* parse and store variables in options table */
				self::update_trello_settings();
			}
			elseif(rgpost("gf_trello_authenticate") || rgget("oauth_token"))
			{
				/* authenticate account */
				self::authenticate_trello_account();

			}
			elseif(rgpost("gf_trello_deauthenticate"))
			{
				delete_option('gf_trello_access_token');
			}

			if(rgget("auth")) {
				add_action('admin_notices', array('GFTrello', 'trello_connection_success'));
			}
			else if (rgget('auth_error'))
			{
				add_action('admin_notices', array('GFTrello', 'trello_connection_problem'));
			}

			/* Call settings page and */
			RGForms::add_settings_page("FFG - Trello", array("GFTrello", "trello_settings_page"),'');
        }
	}

        /**
     * This method is used to display an authentication error
     *
     */
	public static function trello_connection_problem() {
			echo '<div id="message" class="error"><p>';
			echo 'Trello Authentication process failed. Please try and authenticate again.';
			echo '</p></div>';
	}

    /**
     * This method is used to display a successful authentication
     *
     */
	public static function trello_connection_success() {
			echo '<div id="message" class="updated"><p>';
			echo 'You have successfully connected your Trello account to Gravity Forms. You can now configure GF Trello to save to your Trello Account (via the edit forms page).';
			echo '</p></div>';
	}

    /**
     * This method is used to update the admin area settings
     *
     */
	private static function update_trello_settings() {
		/* validate form */
		$app_key = sanitize_text_field($_POST["trello_key"]);
		$app_secret = sanitize_text_field($_POST["trello_secret"]);
        $app_board = sanitize_text_field($_POST["trello_board"]);
        $app_list = sanitize_text_field($_POST["trello_list"]);

		/* update options in wordpress options table */
		update_option('gf_trello_key', $app_key);
		update_option('gf_trello_secret', $app_secret);
        update_option('gf_trello_board', $app_board);
        update_option('gf_trello_list', $app_list);
	}

    /**
     * Generate the Trello Settings Page for setting our four settings
     */
    public static function trello_settings_page(){

        /* Get options from database */
		//$is_configured = get_option("gf_paypal_configured");
		$app_key = get_option("gf_trello_key");
		$app_secret = get_option("gf_trello_secret");
        $app_board = get_option("gf_trello_board");
        $app_list = get_option("gf_trello_list");

        //@todo potentially warn if app_list isn't set in the settings page

		$authenticated = get_option("gf_trello_access_token");

        /* include the trello api */
		include 'trello-api/Trello.php';

        $boards = false;
        $list = false;

        if ($authenticated) {
            $trello = new Trello($app_key, null, $authenticated);
            $boards = $trello->members->get('my/boards');

            if ($app_board) {
                $lists = $trello->get('boards/'. $app_board . '/lists');
            }
        }

        ?>
        <div id="gf-trello">
        <form action="" method="post">
            <?php wp_nonce_field("update", "gf_trello_update") ?>
            <h3><?php _e("Filthy Farm Girl - Trello Plugin Settings", "gravityformstrello") ?></h3>
            <p>Before you can use the Filthy Farm Girl - Trello Plugin in your Gravity Form's you'll need to authorize your account. Follow the steps below to setup the plugin:
            <ol>
            	<li>Login to Trello and go to <a target="_blank" href="https://trello.com/1/appKey/generate">https://trello.com/1/appKey/generate</a></li>
                <li>Copy the App Key and App Secret into the fields below and hit submit.</li>
                <li>Below the main form you'll be asked to authorize your account. Click Authorize and follow the prompts.</li>
            </ol>
            </p>

             <p>
             	<label for="gf_trello_key">Trello App Key:</label>
                <input class="input" id="gf_trello_key" type="text" name="trello_key" value="<?php echo $app_key; ?>" /> <?php gform_tooltip("gf_trello_key") ?>
             </p>

             <p>
             	<label for="gf_trello_secret">Trello App Secret:</label>
                <input class="input" id="gf_trello_secret" name="trello_secret" type="text" value="<?php echo $app_secret; ?>" /> <?php gform_tooltip("gf_trello_secret") ?>
             </p>

             <p>
                 <label for="gf_trello_board">Trello Board:</label>
                 <?php if($boards): ?>
                 <select class="input" id="gf_trello_board" name="trello_board">
                     <?php foreach($boards as $board) { ?>
                        <option value="<?php echo $board->id; ?>" <?php echo ($board->id == $app_board) ? "selected=selected" : "" ?>><?php echo $board->name; ?></option>
                     <?php } ?>
                 </select>
                 <?php else: ?>
                    Please authenticate to choose a board.
                 <?php endif; ?>
             </p>

             <p>
                 <label for="gf_trello_list">Trello List:</label>
                 <?php if($lists): ?>
                 <select class="input" id="gf_trello_list" name="trello_list">
                     <?php foreach($lists as $list) { ?>
                        <option value="<?php echo $list->id; ?>" <?php echo ($list->id == $app_list) ? "selected=selected" : "" ?>><?php echo $list->name; ?></option>
                     <?php } ?>
                 </select>
                 <?php else: ?>
                    Please choose a board to choose a list.
                 <?php endif; ?>
             </p>

            <input type="submit" name="submit" class="button" value="Update Settings" />

        </form>

        <?php
		if(strlen($authenticated) == 0 && strlen($app_key) > 0 && strlen($app_secret) > 0 )
		{
		?>
        <form action="" method="post">
        	<hr />
            <h3>Authenticate your Trello Account</h3>
            <p>You'll need to do this before you can create cards in your account.</p>
        	<?php wp_nonce_field("update", "gf_trello_authenticate"); ?>
            <input type="submit" name="submit" class="button" value="Authenticate Account" />
        </form>

        <?php
		}
		else if(strlen($app_key) > 0 && strlen($app_secret) > 0)
		{
		?>
        	<form action="" method="post">
        	<hr />
                <h3>Deauthorize Trello Account</h3>
                <p>
                	<em>Deauthorizing your application will stop the plugin working and your App will no longer have access to your account.</em><br />
                    You'll need to deauthorize and then reauthorize your account if you need to use a new App Key or App Secret</p>
                <?php wp_nonce_field("update", "gf_trello_deauthenticate"); ?>
                <input type="submit" name="submit" class="button" value="Deauthorize Account" />
            </form>
        <?php
		}

		?>

        </div>

		<?php
    }

    /**
     * We use the Trello API to do OAuth
     *
     */
	private static function authenticate_trello_account()
	{
		$app_key = get_option("gf_trello_key");
		$app_secret = get_option("gf_trello_secret");

		/* include the trello api */
		include 'trello-api/Trello.php';

		/* authenticate */
        $trello = new Trello($app_key, $app_secret);
        $trello->authorize(array(
            'expiration' => 'never',
            'scope' => array(
                'read' => true,
                'write' => true
            ),
            'name' => 'Filthy Farm Girl - Trello Plugin'
        ));

        if ($trello->token()) {
            update_option('gf_trello_access_token', $trello->token());
        }

        if ($trello->members->get('my/boards')) {
            wp_redirect(GF_TRELLO_SETTINGS_URL.'&auth=1');
        }
        else
        {
            /* wordpress error */
            wp_redirect(GF_TRELLO_SETTINGS_URL.'&auth_error=1');
        }
	}

    /**
     * This is the after submission function. It is called by the hook in our init function
     */
    public static function after_submission ($entry = null, $form)
    {
        $entryId = $entry['id'];
        $formId = $form['id'];
        exec("/usr/bin/php-cli ". __DIR__ ."/sendToTrello.php $entryId $formId > /dev/null 2>&1 &");
    }
}