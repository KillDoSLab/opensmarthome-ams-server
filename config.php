<?php
//mongoDB
	date_default_timezone_set('Asia/Seoul');
	$MongoLink = null;
	try{ 
		$MongoLink = new Mongo();
	}catch (MongoConnectionException $e)
	{
		die("DB Connection error");
	}
	$MongoDB = $MongoLink->oshDB;
?>
