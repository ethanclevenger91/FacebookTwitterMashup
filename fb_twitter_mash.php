<?php
/*
Plugin Name: Facebook/Twitter Feed
Description: A JSON-based feed compiler for Facebook and Twitter. Twitter API v1.1 friendly
Author: Ethan Clevenger
Version: 2.2.2
*/

class Webspec_FBTwit_Mash {
	public $_enabledPlatforms = array();

	function __construct() {
		add_action('admin_menu', array($this, 'fb_twitter_mash_menus'));
		add_action('admin_init', array($this, 'register_fbtwitsettings'));
		add_shortcode('fb_twitter_feed', array($this, 'display_social'));

		//Enable the platforms
		$this->_enablePlatforms();
	}

	public function _enablePlatforms() {
		//Facebook
		if(
			   get_option('fb_page')
			&& get_option('fb_app_id')
			&& get_option('fb_app_secret')
		) {
			$this->_enabledPlatforms['facebook'] = true;
		}

		//Twitter
		if(
			   get_option('twit_cons_key')
			&& get_option('twit_cons_sec')
			&& get_option('twit_access_token')
			&& get_option('twit_access_token_secret')
		) {
			$this->_enabledPlatforms['twitter'] = true;
		}
	}
	public function enabledPlatforms() {
		return array_keys($this->_enabledPlatforms);
	}
	public function isEnabled($platform) {
		return isset($this->_enabledPlatforms[$platform]) && $this->_enabledPlatforms[$platform];
	}

	function register_fbtwitsettings() {
		register_setting('fb_twit_feed_options', 'twit_cons_key');
		register_setting('fb_twit_feed_options', 'twit_cons_sec');
		register_setting('fb_twit_feed_options', 'twit_access_token');
		register_setting('fb_twit_feed_options', 'twit_access_token_secret');
		register_setting('fb_twit_feed_options', 'fb_page');
		register_setting('fb_twit_feed_options', 'fb_app_id');
		register_setting('fb_twit_feed_options', 'fb_app_secret');
		register_setting('fb_twit_feed_options', 'ws_date_style');
	}

	function fb_twitter_mash_menus() {
		add_menu_page('FB/Twitter Feed', 'FB/Twitter Feed', 'administrator', 'fb_twitter_feed', array($this, '_html_fb_twitter_feed')); 
	}

	function _html_fb_twitter_feed() { ?>
		<div class="wrap">
		<?php screen_icon(); ?>
			<h2>FB/Twitter Combined Feed</h2>
			<p>Use the shortcode '[fb_twitter_feed number = x]' where 'x' is the number of posts you'd like displayed</p>
			<p>Leaving any options below blank will disable the service that option belongs to from appearing in your feed.</p>
			<form action="options.php" method="post">
				<?php settings_fields('fb_twit_feed_options'); ?>
				<?php do_settings_fields('fb_twit_feed_options', ''); ?>
			<h3>General</h3>
			<?php $date_style_options = array(
				'time-since'=>'Time Since Post',
				'date-posted'=>'Post Date'
			); ?>
			<p>Date Style: <select name="ws_date_style">
					<?php foreach($date_style_options as $option=>$label) { ?>
						<option value="<?php echo $option; ?>"<?php if($option == get_option('ws_date_style')) echo ' selected'; ?>><?php echo $label; ?></option>
					<?php } ?>
					</select>
				</p>
			<h3>Twitter</h3>
				
				<p>API Key: <input type="text" name="twit_cons_key" value="<?php echo get_option('twit_cons_key'); ?>"></p>
				<p>API Secret: <input type="text" name="twit_cons_sec" value="<?php echo get_option('twit_cons_sec'); ?>"></p>
				<p>Access Token: <input type="text" name="twit_access_token" value="<?php echo get_option('twit_access_token'); ?>"></p>
				<p>Access Token Secret: <input type="text" name="twit_access_token_secret" value="<?php echo get_option('twit_access_token_secret'); ?>"><br><br>
				For these, go to <a href="http://apps.twitter.com" target="_blank">Twitter's Developer Site</a>, agree to the developer terms, and create an app. The information isn't important. Once created, generate the access token and secret, then grab all four of these and fill them in here.</p>
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

		$feedData = array();
		foreach($this->enabledPlatforms() as $platform) {
			$feedData[$platform]['feed'] = $this->getResults($platform);
			$feedData[$platform]['count'] = 0;
		}

		//GO!
		echo '<ul class="fb_twit_list">';
		for($i=0; $i<$atts['number']; $i++) {
			$createdDates = array();
			foreach($feedData as $platform=>$data) {
				$createdDates[$platform] = $this->getCreated($platform, $feedData[$platform]);
			}
			$min = array_keys($createdDates, max($createdDates));
			switch($min[0]) {
				case 'twitter':
					echo $this->_getOutputTwitter($feedData['twitter']['feed'], $feedData['twitter']['count']);
					$feedData['twitter']['count']++;
					break;
				case 'facebook':
					echo $this->_getOutputFacebook($feedData['facebook']['feed'], $feedData['facebook']['count']);
					$feedData['facebook']['count']++;
					break;
			}
		}
		echo '</ul>';
	}

	public function return_social($num) {
		$feedData = array();
		$returnArray = array();
		foreach($this->enabledPlatforms() as $platform) {
			$feedData[$platform]['feed'] = $this->getResults($platform);
			$feedData[$platform]['count'] = 0;
		}
		for($i=0; $i<$num; $i++) {
			$createdDates = array();
			foreach($feedData as $platform=>$data) {
				$createdDates[$platform] = $this->getCreated($platform, $feedData[$platform]);
			}
			$max = array_keys($createdDates, max($createdDates));
			switch($max[0]) {
				case 'twitter':
					$returnArray[] = $this->_getTwitterObject($feedData['twitter']['feed'], $feedData['twitter']['count']);
					$feedData['twitter']['count']++;
					break;
				case 'facebook':
					$returnArray[] = $this->_getFacebookObject($feedData['facebook']['feed'], $feedData['facebook']['count']);
					$feedData['facebook']['count']++;
					break;
			}
		}
		return $returnArray;
	}

	//You can get individual platform results by calling $this->getResults('twitter');
	public function getResults($platform) {
		//Get the cached results
		$results = get_transient("fb_twitter_mash_results_{$platform}");

		//If the results are present, then return them
		if(false !== $results) {
			return maybe_unserialize(base64_decode($results));
		}
		//Else they are expired or missing
		else {
			$results = array();

			switch($platform) {
				case 'twitter' :
					$results = $this->_getResultsTwitter();
					break;
				case 'facebook' :
					$results = $this->_getResultsFacebook();
					break;
			}

			//Cache the results for 1 hour
			set_transient("fb_twitter_mash_results_{$platform}", base64_encode(maybe_serialize($results)), 1 * HOUR_IN_SECONDS);

			//Return the results
			return $results;
		}
	}
	public function getCreated($platform, $data) {
		switch($platform) {
			case 'facebook':
				return $this->_getCreatedFacebook($data['feed'], $data['count']);
				break;
			case 'twitter':
				return $this->_getCreatedTwitter($data['feed'], $data['count']);
				break;
		}
	}
	public function _getCreatedFacebook($data, $count) {
		return strtotime($data->data[$count]->created_time);
	}
	public function _getCreatedTwitter($data, $count) {
		return strtotime($data[$count]->created_at);
	}
	public function _getResultsTwitter() {
		//Twitter
		require_once('twitteroauth/twitteroauth/twitteroauth.php');
		$twitterConnection = new TwitterOAuth(
			get_option('twit_cons_key'),
			get_option('twit_cons_sec'),
			get_option('twit_access_token'),
			get_option('twit_access_token_secret')
		);

		return $twitterConnection->get('statuses/user_timeline');
	}
	public function _getResultsFacebook() {
		//Facebook
		$profile_id = get_option('fb_page');

		//App Info, needed for Auth
		$app_id = get_option('fb_app_id');
		$app_secret = get_option('fb_app_secret');
		$fb_token = $this->fetchUrl("https://graph.facebook.com/oauth/access_token?client_id={$app_id}&client_secret={$app_secret}&grant_type=client_credentials");
		$fb_token = str_replace('access_token=', '', $fb_token);
		$json_object = $this->fetchUrl("https://graph.facebook.com/{$profile_id}/posts?access_token={$fb_token}");

		return json_decode($json_object);
	}

	public function _getOutputTwitter($data, $count) {
		$output = '<li class="social_item twitter">
			<h5 class="social_date">
				<a target="_blank" href="https://twitter.com/'.$data[$count]->user->screen_name.'/status/'.$data[$count]->id_str.'">
					<i class="fa fa-twitter-square"></i>
					'.$this->_getFormattedDate($data, $count, 'twitter').'
				</a>
			</h5>';
			if(strpos($data[$count]->text, 'RT @') == 0) {
				preg_match('/^RT @([0-9A-Za-z_]{1,15})/', $data[$count]->text, $rt);
				$message = $this->convert_twit_links($rt[0].' '.$data[$count]->retweeted_status->text);
			} else {
				$message = $this->convert_twit_links($data[$count]->text);
			}
			echo '<p class="social_message">'.$message.'</p>';
		if($data[$count]->entities->media[0]->media_url != '') { 
			$output .= '<img class="twit_pic" src="'.$data[$count]->entities->media[0]->media_url.'">'; 
		}
		$output .= '</li>';
		echo $output;
	}

	public function _getTwitterObject($data, $count) {
		$return = array();
		$return['service'] = 'Twitter';
		$return['dateString'] = $this->_getFormattedDate($data, $count, 'twitter');
		if(strpos($data[$count]->text, 'RT @') === 0) {
			preg_match('/^RT @([0-9A-Za-z_]{1,15})/', $data[$count]->text, $rt);
			$return['message'] = $this->convert_twit_links($rt[0].' '.$data[$count]->retweeted_status->text);
		} else {
			$return['message'] = $this->convert_twit_links($data[$count]->text);
		}
		$return['image'] = ($data[$count]->entities->media[0]->media_url != '' ? $data[$count]->entities->media[0]->media_url : '');
		return $return;
	}

	public function _getOutputFacebook($data, $count) {
		$output = '';
		if($data->data[$count]->message != NULL || $data->data[$count]->picture != NULL) {
			$output = '<li class="social_item facebook">
				<a target="_blank" href="'.$data->data[$count]->link.'"><h5 class="social_date"><i class="fa fa-facebook-square"></i>'.$this->_getFormattedDate($data, $count, 'facebook').'</h5></a>
				<p class="social_message">'.$this->filter_fb_links($data->data[$count]->message).'</p>';
			if($data->data[$count]->picture != '') {
				$output .= '<img class="fb_pic" src="'.$data->data[$count]->picture.'">';
			}
			$output .= '</li>';
		}
		return $output;
	}

	public function _getFacebookObject($data, $count) {
		$return = array();
		$return['service'] = 'Facebook';
		$return['dateString'] = $this->_getFormattedDate($data, $count, 'facebook');
		$return['message'] = $this->filter_fb_links($data->data[$count]->message);
		$return['image'] = ($data->data[$count]->picture != '' ? $data->data[$count]->picture : '');
		return $return;
	}

	public function _getFormattedDate($data, $count, $platform) {
		$style = get_option('ws_date_style');
		$output = '';
		switch($platform) {
			case 'twitter':
				switch($style) {
					case 'time-since':
						$output = $this->_getTimeAgoString($this->_getCreatedTwitter($data, $count));
						break;
					case 'post-date':
					default:
						$output = date('M d, Y', $this->_getCreatedTwitter($data, $count));
						break;
				}
				break;
			case 'facebook':
				switch($style) {
					case 'time-since':
						$output = $this->_getTimeAgoString($this->_getCreatedFacebook($data, $count));
						break;
					case 'post-date':
					default:
						$output = date('M d, Y', $this->_getCreatedFacebook($data, $count));
						break;
				}
				break;
		}
		return $output;
	}

	/*From SO: http://stackoverflow.com/questions/2915864/php-how-to-find-the-time-elapsed-since-a-date-time
	Credit @arnorhs: http://stackoverflow.com/users/2038/arnorhs */ 
	public function _getTimeAgoString($time) {
		$time = time() - $time; // to get the time since that moment
	    $tokens = array (
	        31536000 => 'year',
	        2592000 => 'month',
	        604800 => 'week',
	        86400 => 'day',
	        3600 => 'hour',
	        60 => 'minute',
	        1 => 'second'
	    );
	    foreach ($tokens as $unit => $text) {
	        if ($time < $unit) continue;
	        $numberOfUnits = floor($time / $unit);
	        return $numberOfUnits.' '.$text.(($numberOfUnits>1)?'s':'').' ago';
	    }
	}

	public function _getUserTwitter() {
		//Get the cached results
		$user = get_transient("fb_twitter_mash_twitter_user");
		//If doesn't exist or expired
		if($user === false) {
			require_once('twitteroauth/twitteroauth/twitteroauth.php');
			$twitterConnection = new TwitterOAuth(
				get_option('twit_cons_key'),
				get_option('twit_cons_sec'),
				get_option('twit_access_token'),
				get_option('twit_access_token_secret')
			);
			$user = $twitterConnection->get('account/settings');
			set_transient("fb_twitter_mash_twitter_user", base64_encode(maybe_serialize($user)), 1 * HOUR_IN_SECONDS);
		} else {
			$user = maybe_unserialize(base64_decode($user));
		}
		return '<a class="user" href="http://www.twitter.com/'.$user->screen_name.'">@'.$user->screen_name.'</a>';

	}

	/* Conversion Methods */
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
		if(preg_match($reg_exUrl, $content, $url)) {
			return preg_replace($reg_exUrl, '<a target="_blank" href="'.$url[0].'">'.$url[0].'</a> ', $content);
		} else {
			return $content;
		}
	}
}
$FBTwitMash = new Webspec_FBTwit_Mash();