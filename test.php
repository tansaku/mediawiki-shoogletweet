<?php

require_once 'twitteroauth/twitteroauth/twitteroauth.php';

define('CONSUMER_KEY', '2bIzxZZ3S5xxwDe0TCOw');
define('CONSUMER_SECRET', 'g6zhdcbDRTnyCwwoxZPbo96g2E2BypRDLlPHLIY');

$oauth_token = '385564862-vxXROzMvPnQHMG6Qa6RZ2iw5I3Wyofsjxd05AxZq';
$oauth_token_secret = 'hmg8z1po5xyvxvKGweqsNuNXqDzxxGrTSDEF2Xnn1U';

$connection = new TwitterOAuth(CONSUMER_KEY, CONSUMER_SECRET, $oauth_token, $oauth_token_secret);
var_dump( $connection->get('statuses/home_timeline') );
