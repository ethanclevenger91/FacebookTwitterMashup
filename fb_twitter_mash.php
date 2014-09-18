<?php
/*
Plugin Name: Facebook/Twitter Feed
Description: A JSON-based feed compiler for Facebook and Twitter. Twitter API v1.1 friendly
Author: Ethan Clevenger
*/

class Webspec_FBTwit_Mash {

	function __construct() {
		add_action('admin_menu', array($this, 'fb_twitter_mash_menus'));
		add_action('admin_init', array($this, 'register_fbtwitsettings'));
		add_shortcode('fb_twitter_feed', array($this, 'display_social'));
	}

	function register_fbtwitsettings() {
		register_setting('fb_twit_feed_options', 'twit_cons_key');
		register_setting('fb_twit_feed_options', 'twit_cons_sec');
		register_setting('fb_twit_feed_options', 'twit_access_token');
		register_setting('fb_twit_feed_options', 'twit_access_token_secret');
		register_setting('fb_twit_feed_options', 'fb_page');
		register_setting('fb_twit_feed_options', 'fb_app_id');
		register_setting('fb_twit_feed_options', 'fb_app_secret');
	}

	function fb_twitter_mash_menus() {
		add_menu_page('FB/Twitter Feed', 'FB/Twitter Feed', 'administrator', 'fb_twitter_feed', array($this, '_html_fb_twitter_feed')); 
	}

	function _html_fb_twitter_feed() { ?>
		<div class="wrap">
		<?php screen_icon(); ?>
			<h2>FB/Twitter Combined Feed</h2>
			<p>Use the shortcode '[ fb_twitter_feed number = "x" ]' where 'x' is the number of posts you'd like displayed</p>
			<h3>Twitter</h3>
			<form action="options.php" method="post">
				<?php settings_fields('fb_twit_feed_options'); ?>
				<?php do_settings_fields('fb_twit_feed_options', ''); ?>
				<p>API Key: <input type="text" name="twit_cons_key" value="<?php echo get_option('twit_cons_key'); ?>"></p>
				<p>AIP Secret: <input type="text" name="twit_cons_sec" value="<?php echo get_option('twit_cons_sec'); ?>"></p>
				<p>Access Token: <input type="text" name="twit_access_token" value="<?php echo get_option('twit_access_token'); ?>"></p>
				<p>Access Token Secret: <input type="text" name="twit_access_token_secret" value="<?php echo get_option('twit_access_token_secret'); ?>"><br><br>
				For these, go to <a href="http://developer.twitter.com" target="_blank">Twitter's Developer Site</a>, agree to the developer terms, and create an app. The information isn't important. Once created, generate the access token and secret, then grab all four of these and fill them in here.</p>
			<h3>Facebook</h3>
				<p>Page ID: <input type="text" name="fb_page" value="<?php echo get_option('fb_page'); ?>"></p>
				<p>App ID: <input type="text" name="fb_app_id" value="<?php echo get_option('fb_app_id'); ?>"></p>
				<p>App Secret: <input type="text" name="fb_app_secret" value="<?php echo get_option('fb_app_secret'); ?>"><br><br>
				Your Facebook page's ID can be found <a href="http://findmyfacebookid.com/" target="_blank">here</a>. To get the App ID and Secret, go to <a href="http://developers.facebook.com" target="_blank">Facebook's Developer Site</a>, agree to the developer terms, click on 'Apps' in the top bar and create a new app. The name and category don't matter, and you'll see namespace is optional. On the next screen, select 'Disabled' for Sandbox mode, and then use the App ID and Secret from this screen.</p>
			<?php submit_button(); ?>
			</form>
		</div>
	<?php }

	function fetchUrl($url) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_TIMEOUT, 20);
		// You may need to add the line below
		curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,false);

		$feedData = curl_exec($ch);
		curl_close($ch); 

		return $feedData;
	}

	function display_social($atts) {
		$atts = shortcode_atts(array('number'=>1), $atts);
		//Twitter
		require_once('twitteroauth/twitteroauth/twitteroauth.php');
		$twitterConnection = new TwitterOAuth(
			get_option('twit_cons_key'),
			get_option('twit_cons_sec'),
			get_option('twit_access_token'),
			get_option('twit_access_token_secret')
		);
		$twitterData = $twitterConnection->get('statuses/user_timeline');
		
		//Facebook
		$profile_id = get_option('fb_page');

		//App Info, needed for Auth
		$app_id = get_option('fb_app_id');
		$app_secret = get_option('fb_app_secret');
		$fb_token = $this->fetchUrl("https://graph.facebook.com/oauth/access_token?client_id={$app_id}&client_secret={$app_secret}&grant_type=client_credentials");
		$fb_token = str_replace('access_token=', '', $fb_token);
		$json_object = $this->fetchUrl("https://graph.facebook.com/{$profile_id}/posts?access_token={$fb_token}");
		$facebookData = json_decode($json_object);

		//Counts
		$fb_count=0;
		$twit_count=0;

		//GO!
		echo '<ul class="fb_twit_list">';
		for($i=0; $i<$atts['number']; $i++) {
			$twit_date = strtotime($twitterData[$twit_count]->created_at);
			$fb_date = strtotime($facebookData->data[$fb_count]->created_time);
			if($fb_date < $twit_date) {
				$output = $this->convert_twit_links($twitterData[$twit_count]->text);
				echo '<li class="social_item twitter">';
				echo '<a target="_blank" href="https://twitter.com/'.$twitterData[$twit_count]->user->screen_name.'/status/'.$twitterData[$twit_count]->id_str.'"><h5 class="social_date"><i class="fa fa-twitter-square"></i>'.date('M d, Y', $twit_date).'</h5></a>';
				echo '<p class="social_message">'.$output.'</p>';
				if($twitterData[$twit_count]->entities->media[0]->media_url != '') { 
					echo '<img class="twit_pic" src="'.$twitterData[$twit_count]->entities->media[0]->media_url.'">'; 
				}
				echo '</li>';
				$twit_count++;
			}
			else {
				if($facebookData->data[$fb_count]->message != NULL || $facebookData->data[$fb_count]->picture != NULL) {
					echo '<li class="social_item facebook">';
					echo '<a target="_blank" href="'.$facebookData->data[$fb_count]->link.'"><h5 class="social_date"><i class="fa fa-facebook-square"></i>'.date('M d, Y', $fb_date).'</h5></a>';
					echo '<p class="social_message">'.$this->filter_fb_links($facebookData->data[$fb_count]->message).'</p>';
					if($facebookData->data[$fb_count]->picture != '') {
						echo '<img class="fb_pic" src="'.$facebookData->data[$fb_count]->picture.'">';
					}
					echo '</li>';
				}
				$fb_count++;
			}
		}
		echo '</ul>';
	}

	function convert_twit_links($feed) {
		//Find location of @ in feed
		$feed = str_pad($feed, 3, ' ', STR_PAD_LEFT);   //pad feed     
		$startat = stripos($feed, '@'); 
		$numat = substr_count($feed, '@');
		$numhash = substr_count($feed, '#'); 
		$numhttp = substr_count($feed, 'http'); 
		$feed = preg_replace("/(http:\/\/)(.*?)\/([\w\.\/\&\=\?\-\,\:\;\#\_\~\%\+]*)/", "<a target=\"_blank\" href=\"\\0\">\\0</a>", $feed);
		$feed = preg_replace("(@([a-zA-Z0-9\_]+))", "<a href=\"http://www.twitter.com/\\1\">\\0</a>", $feed);
		$feed = preg_replace('/(^|\s)#(\w+)/', '\1<a target="_blank" href="https://twitter.com/search?q=\2&src=typd">#\2</a>', $feed);
		return $feed;
	}
	function filter_fb_links($content) {
		$reg_exUrl = "/(http|https|ftp|ftps)\:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,3}(\/\S*)?/";
		$url='';
		if(preg_match($reg_exUrl, $content, $url)) {
			return preg_replace($reg_exUrl, '<a target="_blank" href="'.$url[0].'">'.$url[0].'</a> ', $content);
		} else {
			return $content;
		}
	}
}
$FBTwitMash = new Webspec_FBTwit_Mash();