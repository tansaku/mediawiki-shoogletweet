<?php

if (!defined('MEDIAWIKI')) die();

$wgExtensionFunctions[] = 'wfShoogleTweet';
$wgExtensionCredits['parserhook'][] = array(
        'path'            => __FILE__,
        'name'            => 'ShoogleTweet',
        'version'         => '0.00001',
        'author'          => 'Schinken',
        'url'             => 'http://www.hackerspace-bamberg.de',
        'description'     => 'High Availabilty Twitter Feed for MediaWiki'  );


// Register parser-hook
function wfShoogleTweet() {
    new ShoogleTweet();
}


class ShoogleTweet {

    // Default configuration
    private $settings = array(
        'timeout' => 5,                 // Timeout in seconds
        'con_timeout' => 3,             // Timeout in seconds
        'cachetime_tier_1' =>   300,    // Cachetime in seconds
        'cachetime_tier_2' =>   9000    // Cachetime in seconds
    );

    public function __construct() {
        global $wgParser;
        $wgParser->setHook('shoogleTweet', array(&$this, 'hookShoogleTweet'));
    }


    public function hookShoogleTweet($screen_name, $argv, $parser) {

        $output = '';
        $parser->disableCache();

        // Merge user specific settings with own defaults
        $this->settings = array_merge($this->settings, $argv);

        $localParser = new Parser();
        $category = $localParser->preprocess($category, $parser->mTitle, $parser->mOptions, false);

        return $this->generate_output($screen_name);
    }

    public function tst(){
        return $this->generate_output('b4ckspace');
    }

    /**
     * Returns output for the page
     * @param string twitter screen name
     * @return string
     */

    private function generate_output($screen_name){ 

        $twitter_feed = $this->high_availability_wrapper($screen_name);
        if($twitter_feed === false) {
            return "An error occured. That's all we know.";
        }

        $json_data = @json_decode($twitter_feed, true);

        // This should never ever happen, because its checked before
        // but you'll never know
        if($json_data === null){
            return "An error occured. That's all we know.";
        }

        $twitter_items = array();
        foreach($json_data as $index => $item){
            try {
                $twitter_item = new ShoogleTweet_Tweet($item);
                $twitter_items[] = $twitter_item;
            } catch(Exception $e) {
                // Log error with tweet, but also skip!
                continue;
            }
        }

        return $this->render_tweets($twitter_items, $screen_name);

    }

    /**
     * Finally renders the tweets as html
     * @param ShoogleTweet[] List of ShoogleTweet's
     * @return string
     */

    private function render_tweets($items, $screen_name) {

        $odd_even = array('odd', 'even');

        $output = '<ul id="tw-list">';

        foreach($items as $i => $item) {

            $time = $item->get_pubdate_object()->format('d.m.Y H:i:s');
            $link = sprintf('https://twitter.com/%s/status/%d', $screen_name, $item->get_id());
            $user = $item->get_user();
            
            $desc = $item->get_text();
            $desc = $this->process_description($desc);

            $output .= sprintf('<li class="%s">', $odd_even[$i%count($odd_even)]);
            $output .= sprintf('<span class="tw-content">%s</span>', $desc);
            $output .= sprintf('<span class="tw-date">[<a href="%s" target="_blank" title="%s">%s</a>]</span>', $link, $user, $time);
            $output .= '</li>';
        }

        $output .= '</ul>';

        return $output;
    }

    /**
     * Formats links to real links
     */
    private function process_description($description) {
        $description = preg_replace('/(https?:\/\/[^\s$]+)/', '<a href="$1" target="_blank">$1</a>', $description);
        return $description;
    }

    /**
     * Takes care of all fails that can happen while retrieving data and
     * provides a failover-failover
     * @param string twitter screen name
     * @return string|false json string
     */

    private function high_availability_wrapper($screen_name) {

        try {
            $twitter_feed = $this->retrieve_twitterfeed($screen_name);
            $this->write_cache('shoogle_tweet_tier_2', $twitter_feed, $this->settings['cachetime_tier_2']);
        } catch(Exception $e) {

            // Something went wrong retrieving the twitterfeed, so we Check
            // our long-living tier-2 cache for data
            if(($cache = $this->get_cache('shoogle_tweet_tier_2')) !== false) {
                $twitter_feed = $cache;

                // Write back to tier 1 cache to prevent timeout for the next
                // cachetime_tier_1 seconds
                $this->write_cache('shoogle_tweet_tier_1', $cache, $this->settings['cachetime_tier_1']);
            }else {
                $twitter_feed = false;
            }
            
        }

        return $twitter_feed;
    }

    /**
     * returns eventually cached data from tier1 cache, or fresh data
     * from the json twitterfeed
     * @param string twitter screen name
     * @return string
     * @throws Exception
     */

    private function retrieve_twitterfeed($screen_name) {

        // Check if there is a cached in tier 1 cache, if yes, return
        if( ($cache = $this->get_cache('shoogle_tweet_tier_1')) !== false ) {
            return $cache;
        }

        $url = 'http://api.twitter.com/1/statuses/user_timeline.json?screen_name='.$screen_name;

        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_TIMEOUT, $this->settings['timeout']);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->settings['con_timeout']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_ENCODING, 'gzip,deflate');
        curl_setopt($ch, CURLOPT_USERAGENT, 'ShoogleTweet Feedparser');

        $returned = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        // Checking for json_decode === null is legit here, because
        // the url should never return null (even if its a valid json value
        // itself)
        if($http_code != 200 || $returned == false || @json_decode($returned) === null) {
            throw new Exception('Failed to retrieve twitterfeed');
        }

        // Write tier 1 cache
        $this->write_cache('shoogle_tweet_tier_1', $returned, $this->settings['cachetime_tier_1']);

        return $returned;
    }

    /**
    * Get cached data by $key, if apc is available
    *
    * @param string $key apc cache key
    * @return bool|mixed
    */

    private function get_cache($key) {
        if(!function_exists('apc_fetch')) {
            return false;    
        }    

        return apc_fetch($key);
    } 

    /**
    * Writes data to apc cache by $key
    *
    * @param string $key apc cache key
    * @param mixed $data
    * @param int $cache_time time in seconds
    */

    private function write_cache($key, $data, $cache_time) {
        if( function_exists('apc_store') ) {
            apc_store( $key, $data, $cache_time );
        }
    }

}

class ShoogleTweet_Tweet {

    private $attributes = array();
    //private $required = array('id', 'text', 'retweeted', 'created_at');

    public function __construct($data) {
        $this->attributes = $data;
    }


    /**
     * Returns value by gived key from attributes, if missing, Returns
     * default
     * @param string key
     * @param mixed default
     * @return mixed
     */

    private function get_attribute($key, $default=null) {

        $layer = $this->attributes;
        $inception = explode('/', $key);
        $value = null;

        foreach($inception as $incept){
            if( isset($layer[$incept]) ){
                // we need to go deeper
                $layer = $layer[$incept];
                $value = $layer;
            } else {
                return null;
            }
        }

        return $value;
    }

    /**
     * Returns the id of the tweet
     * @param int
     */

    public function get_id() {
        return $this->get_attribute('id');
    }

    /**
     * Returns user of the tweet
     * @return string
     */

    public function get_user() {
        return $this->get_attribute('user/name', '');
    }

    /**
     * Returns text/content of the tweet
     * @return string
     */

    public function get_text() {
        return $this->get_attribute('text', '');    
    }

    /**
     * Returns date object for twitter date
     * @return DateTime|null
     * @throws Exception
     */
    
    public function get_pubdate_object() {
        $pubdate = $this->get_pubdate();
        if($pubdate === null) {
            throw new Exception('Invalid date input');
        }

        return new DateTime($pubdate);
    }

    /**
     * Returns twitter time format
     * @return string|null
     */

    public function get_pubdate() {
        return $this->get_attribute('created_at', null);    
    }

    /**
     * Returns if tweet is a retweet
     * @return bool
     */

    public function is_retweeted() {
        return (bool) $this->get_attribute('retweeted', false);
    }

}

