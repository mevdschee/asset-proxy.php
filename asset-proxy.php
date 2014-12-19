<?php

// hostnames for which "GET" requests can be proxied over "HTTP" (no ssl) 
$hostnames = array(
	'fonts.gstatic.com',
	'maxcdn.bootstrapcdn.com',
	'netdna.bootstrapcdn.com',
	'fonts.googleapis.com',
	'ajax.googleapis.com',
);

// maximum age of a file before being refreshed 
$refresh_age = 24*3600;

// strip the leading "/proxy.php/" from the URL
$url = substr($_SERVER['REQUEST_URI'], strlen($_SERVER['SCRIPT_NAME'].'/'));

// if there is no URL specified show bad request error 
if(!$url || !strpos($url,'/')){
	header('Bad Request', true, 400);
	exit;
}

// get the hostname which should be the first segment (until the first slash)
$hostname = substr($url, 0, strpos($url, '/'));

// if the hostname is not in the list of allowed hostnames show forbidden error
if (!in_array($hostname, $hostnames)) {
	header('Forbidden', true, 403);
	exit;
}

// calculate the cached filename and check whether it already exists
$filename = "cache/".md5($url);
$file_exists = file_exists($filename);

// get the file age if the file exists
if ($file_exists) {
	$file_age = time()-filemtime($filename);
}

// if cache exists and is fresh, let's read the file, else retrieve it with cURL
if ($file_exists && $file_age<$refresh_age) {
	$result = file_get_contents($filename);
} else { 
	// set some headers on the cURL call to pretend we are a user 
	$sent_headers = array();
	foreach (array('User-Agent','Accept','Accept-Language','Referer') as $header) {
		$key = 'HTTP_'.strtoupper(str_replace('-','_',$header));
		if (isset($_SERVER[$key])) {
			$sent_headers[] = $header.': '.$_SERVER[$key];
		}
	}

	// make sure we do net get chunked, deflated or gzipped content  
	$sent_headers[] = 'Accept-Encoding: ';
	$sent_headers[] = 'Cache-Control: max-age=0';
	$sent_headers[] = 'Connection: keep-alive';
	
	// initialize cURL with the URL, our headers and set headers retrieval on
	$curl = curl_init('http://'.$url);
	curl_setopt_array($curl, array(
			CURLOPT_HEADER => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_BINARYTRANSFER => true,
			CURLOPT_HTTPHEADER => $sent_headers
	));

	// execute cURL call and get status code
	$result = curl_exec($curl);
	$status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
	curl_close($curl);

	if ($status == 200) {
		// file was successfully retrieved 
		if (file_put_contents($filename, $result)===false) {
			// show error on unsuccessful write
			header('Internal Server Error', true, 500);
			exit;
		}
	} else if ($file_exists) {
		// serve stale
		$result = file_get_contents($filename);
		// reset refresh timer
		touch($filename);
	}
	
}

// split the message in raw headers and body  
if (strpos($result,"\r\n\r\n")!==false) {
	list($raw_headers,$body) = explode("\r\n\r\n", $result, 2);
} else {
	list($raw_headers,$body) = array($result,'');
}

// convert raw headers into an array   
$raw_headers = explode("\n", $raw_headers);

// parse raw headers into received headers 
$received_headers = array();
foreach ($raw_headers as $h) {
	$h = explode(':', $h, 2);
	if (isset($h[1])) {
		$received_headers[$h[0]] = trim($h[1]);
	}
}

// set certain headers for the output 
foreach (array('Content-Type','Content-Encoding','Cache-Control','ETag','Last-Modified','Vary') as $header) {
	if (isset($received_headers[$header])) {
		header($header.': '.$received_headers[$header]);
	}
}

// replace the absolute URL's in the output
foreach ($hostnames as $hostname) {
	$body = preg_replace('/(https?:)?\/\/'.str_replace('.','\.',$hostname).'\//', $_SERVER['SCRIPT_NAME'].'/'.$hostname.'/', $body);
}

// set the new content length properly
header('Content-Length: '.strlen($body));

// echo the contents of the body
echo $body;
