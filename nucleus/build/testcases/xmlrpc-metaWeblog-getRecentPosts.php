<?php
/*
	Test call to the nucleus XML-RPC server sending a metaWeblog.getRecentPosts request
	
	Wouter Demuynck / 2003-08-31
*/

// URL of XML-RPC server
$serverHost = 'localhost';
$serverPost = 80;
$serverPath = '/release/nucleus/xmlrpc/server.php';
	
include('../../config.php');
include($DIR_LIBS . 'xmlrpc.inc.php');

$f=new xmlrpcmsg(
	'metaWeblog.getRecentPosts',
	 array(
	 	new xmlrpcval('1', 'string'),			// blogid
	 	new xmlrpcval('god', 'string'),			// username
	 	new xmlrpcval('heaven', 'string'),		// password
	 	new xmlrpcval('5', 'string')			// amount to get
	 )
 );
	 

  $c=new xmlrpc_client($serverPath, $serverHost, $serverPort);
  $c->setDebug(1);
  $r=$c->send($f);
  $v=$r->value();


  if (!$r->faultCode()) {
  	echo 'success!';
  } else {
      print "Fault: ";
      print "Code: " . $r->faultCode() . 
            " Reason '" .$r->faultString()."'<BR>";
  }
	

	
?>