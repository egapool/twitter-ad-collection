<?php

date_default_timezone_set('Asia/Tokyo');
require __DIR__ . '/vendor/autoload.php';
require __DIR__."/config.php";

spl_autoload_register(function ($classname) {
    require (__DIR__."/" . $classname . ".php");
});

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$log = new Logger('my_log');
$logfile = (new DateTime())->format('Ymd');
$log->pushHandler(new StreamHandler(__DIR__."/logs/{$logfile}.log", Logger::INFO));

$log->info('start');

$users = $config['users'];
foreach ( $users as $user ) {

	$url = "https://twitter.com/login";

	$fp = fopen(__DIR__."/tmp/tmp", "w");

	$ch = curl_init($url);
	curl_setopt_array($ch, [
	    CURLOPT_RETURNTRANSFER 	=> true,
	    CURLOPT_FOLLOWLOCATION 	=> true,
	    CURLOPT_SSL_VERIFYPEER 	=> false,
	    CURLOPT_SSL_VERIFYHOST 	=> false,
	    CURLOPT_COOKIEJAR      	=> __DIR__.'/tmp/cookie',
	    CURLOPT_HTTPHEADER		=> [
			"upgrade-insecure-requests: 1",
			// "user-agent: Mozilla/5.0 (iPhone; CPU iPhone OS 9_2 like Mac OS X) AppleWebKit/601.1.46 (KHTML, like Gecko) Mobile/13C75 Twitter for iPhone"
			"user-agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/50.0.2661.102 Safari/537.36"
		],
	]);
	curl_setopt($ch, CURLOPT_WRITEHEADER, $fp);
	$html = curl_exec($ch);
	fclose($fp);
	curl_close($ch);
	// var_dump($html);die;

	$dom = new DOMDocument;
	@$dom->loadHTML($html);
	$xpath = new DOMXPath($dom);

	// get authentication token (csrf?)
	$token = $xpath->query('//input[@name="authenticity_token"]')->item(0)->getAttribute('value');
	unset($dom,$xpath);

	$sPost = "session[username_or_email]={$user['username']}&session[password]={$user['password']}&return_to_ssl=true&scribe_log=&redirect_after_login=%2F&authenticity_token={$token}";

	$url = "https://twitter.com/sessions";
	$ch = curl_init($url);
	curl_setopt_array($ch, [
		CURLOPT_ENCODING		=> "gzip",
	    CURLOPT_RETURNTRANSFER 	=> true,
	    CURLOPT_FOLLOWLOCATION 	=> true,
	    CURLOPT_SSL_VERIFYPEER 	=> false,
	    CURLOPT_COOKIEJAR      	=> __DIR__.'/tmp/cookie',
	    CURLOPT_COOKIEFILE 		=> __DIR__."/tmp/tmp",
		CURLOPT_POST 			=> TRUE,
		CURLOPT_HTTPHEADER		=> [
			"Content-type: application/x-www-form-urlencoded",
			"origin: https://twitter.com",
			"referer: https://twitter.com/login",
			"upgrade-insecure-requests: 1",
			// "user-agent: Mozilla/5.0 (iPhone; CPU iPhone OS 9_2 like Mac OS X) AppleWebKit/601.1.46 (KHTML, like Gecko) Mobile/13C75 Twitter for iPhone"
			"user-agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/50.0.2661.102 Safari/537.36"
		],
	]);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $sPost);
	$html = curl_exec($ch);
	curl_close($ch);

	$dom = new DOMDocument;
	@$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
	$xpath = new DOMXPath($dom);

	$promotes = [];
	foreach ( $xpath->query('//div[@data-promoted="true"]') as $pr){
		$tw['user_id'] 	   = $xpath->evaluate('string(@data-user-id)', $pr);
		$tw['screen_name'] = $xpath->evaluate('string(@data-screen-name)', $pr);
		$tw['name'] 	   = $xpath->evaluate('string(@data-name)', $pr);
		$icon_url 		   = $xpath->evaluate('string(.//div[@class="content"]/div/a/img/@src)', $pr);
		$icon_url 		   = str_replace("bigger", "400x400", $icon_url);
		$tw['icon'] 	   = chunk_split(base64_encode(file_get_contents($icon_url)));
		$tw['status'] 	   = $xpath->evaluate('string(@data-tweet-id)', $pr);
		$tw['url'] 		   = "https://twitter.com/{$tw['screen_name']}/status/{$tw['status']}";
		$promotes[] 	   = $tw;
	}

	$pt = new PromoteTweet($config['db']['host'],$config['db']['dbname'],$config['db']['user'],$config['db']['pass']);
	foreach ( $promotes as $pr ) {
		$res = $pt->registerAccount($pr['user_id'], $pr['screen_name'],$pr['name'], $pr['status'], $pr['icon']);

		if ( $res ) {
			$log->info('new register!',['user'=>$user['username'],'screen_name'=>$pr['screen_name'],'status'=>$pr['status']]);
		} else {
			$log->info('still having..',['user'=>$user['username'],'screen_name'=>$pr['screen_name'],'status'=>$pr['status']]);
		}
	}

	sleep(5);
}