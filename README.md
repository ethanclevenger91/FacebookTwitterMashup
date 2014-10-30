FB/Twitter Mash Plugin
==================

You'll Need
------------------

Twitter:

- Consumer Key
- Consumer Secret
- Access Token
- Access Token Secret

These can be acquired by making a [Twitter App](http://developer.twitter.com). Note that it must be generated from the account that will be generating the tweets.

Facebook:

- App ID
- App Secret

These can be acquired by making a [Facebook App](http://developers.facebook.com). Note that the account used to generate the app must be able to view the page being queried (not a problem for public pages, which are most of our clients)

Leaving any options blank will disable the service to which that option belongs.

You'll Get
------------------

The shortcode for displaying a generic list of tweets, posts and associated media is as follows:

     [fb_twitter_feed number=x]

That simple. Put it anywhere. It gets the combined x most recent tweets/posts.

For more control, you can get a feed object and iterate over the posts:

	$feedGetter = new Webspec_FBTwit_Mash();
	$feed = $feedGetter->return_social(3);
	foreach($feed as $post) {
		//output the good stuff here
	}

Each post looks like this:
	
	$post = array(
		'service'=>'Facebook', //or Twitter
		'dateString'=>'1 day ago', //or however you've set your options
		'message'=>'The content of the post',
		'image'=> 'http://foo.com/bar.jpg' //a url to the post's attached image, if one exists
	)

You can also get the Twitter User like so:
	
	echo $feedGetter->_getUserTwitter();
	echo Webspec_FBTwit_Mash::_getUserTwitter(); //also works statically

Features:
------------------

- Supports Twitter API v1.1
- Twitter handles and hashtags are filtered out and linked, as are links in Facebook posts
- New in 2.1: Use a single service or both!
- New in 2.2: Get an object rather than HTML
