<?php
	require("config.php");
	require("widgets.php");
	//사용할 DB 컬렉션 선언
	$MongoDeviceCol = $MongoDB->device;
	$MongoRealDataCol = $MongoDB->realTimeData;
	$MongoDataCol = $MongoDB->data;
	//등록된 디바이스 목록을 가져오고
	$deviceListCursor = $MongoDeviceCol->find();
	$deviceList = array();
	foreach($deviceListCursor as $device)
	{
		$deviceList[] = $device;
	}
	
	//해당 등록된 디바이스의 최신 정보를 가져오고
	$deviceRealDataList = array();
	if(isset($deviceList))
	{
		foreach($deviceList as $device)
		{
			$realTimeData = $MongoRealDataCol->findOne(array("device_id"=>$device["_id"]));
			$deviceRealDataList[] = $realTimeData;
		}
	}

	//24시간 분의 데이터를 추출
	$endDate  = strtotime(date("Y-m-d H:i:s",mktime (23,59,59, date("m"), date("d"), date("Y")))) * 1000;
	$startDate  = strtotime(date("Y-m-d H:i:s",mktime (0,0,0, date("m"), date("d"), date("Y")))) * 1000;
	$DBDataListCursor =  $MongoDataCol->find(array("TIME"=>array('$gt'=>$startDate,'$lt'=>$endDate)));
	foreach($DBDataListCursor as $data)
	{
		$TodayDBDataList[] = $data;
	}

	
	
?>
<!DOCTYPE html>
<html>
	<head>
		<title>
			IoT Smart Home Dash Board
		</title>
		<meta charset="utf-8">
		<meta http-equiv="X-UA-Compatible" content="IE=edge">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		
		<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.2/css/bootstrap.min.css">
		<script src="http://cdn.socket.io/socket.io-1.3.0.js"></script>
		<link rel="stylesheet" href="./killdos-common.css">
		<script src="https://cdnjs.cloudflare.com/ajax/libs/json3/3.3.2/json3.js"></script>
		<!-- jQuery (부트스트랩의 자바스크립트 플러그인을 위해 필요합니다) -->
	    <script src="http://code.jquery.com/jquery-latest.js"></script>
	    <script src="/js/jquery.color-2.1.2.js"></script>
	    <script>
			var socket = io(window.location.host+':1011');
			socket.on("onInsert",function(data)
			{
				console.log(data);
				if('daily' in data)
				{
					if(data["deviceType"] == "EMON")
					{
							
					}
				}
				
				if('houly' in data)
				{
					
				}
				
				if('daily' in data)
				{
					
				}
				if('realData' in data)
				{
					if(data['deviceType'] == "EMON")
					{
						var sum = Number(data.realData.v)+Number(data.realData.v2)+Number(data.realData.v3);
						sum = (sum*220)/1000;
						document.getElementById("emon_nowValue_"+data.device_id[0]).innerHTML=sum.toFixed(4);
						jQuery("#test").animate({color:"red"}, 500).animate({color:"black"},1000);
					}
				}
				
			/*
				var sum = Number(data.v)+Number(data.v2)+Number(data.v3);
				console.log(sum);
				document.getElementById("emon_nowValue_<?php echo($device_id); ?>").innerHTML=sum;
				//jQuery("#emon_nowValue_<?php echo($device_id); ?>").animate({backgroundColor: "#aa0000" },1000,function(){console.log("finish ani");});

//				jQuery("#emon_nowValue_<?php echo($device_id); ?>").css({color:"red"});
				
				//jQuery("#test").animate({backgroundColor: "#aa0000"},1000,"linear",function(){});
				jQuery("#test").animate({color:"red"}, 500).animate({color:"black"},500);
				*/
			});
		</script>

	    
		<script type="text/javascript" src="https://www.google.com/jsapi"></script>
	</head>
	<body>
		<nav class="navbar navbar-default">
			<div class="container-fluid">
				<div class="navbar-header">
					<button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#bs-example-navbar-collapse-1">
					<span class="sr-only">Toggle navigation</span>
					<span class="icon-bar"></span>
					<span class="icon-bar"></span>
					<span class="icon-bar"></span>
					</button>
					<a class="navbar-brand" href="#">KillDoS Lab</a>
				</div> <!-- end navbar-header -->
				
				<div class="collapse navbar-collapse" id="bs-example-navbar-collapse-1">
					<ul class="nav navbar-nav">
						<li class="active"><a href="#">홈<span class="sr-only">(current)</span></a></li>
						<li class="dropdown"><a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false">Dropdown <span class="caret"></span></a>
						<ul class="dropdown-menu" role="menu">
							<li><a href="#">Setup</a></li>
							<li class="divider"></li>
							<li><a href="#">설정</a></li>
						</ul> <!-- end dropdown-menu -->
						</li>
					</ul> <!-- end nav navbar-nav -->
				</div> <!-- end collapse navbar-collapse -->
			</div> <!-- end container-fluid -->
		</nav> <!--end navbar -->
		<div class="container">
		
			<h1>대시 보드</h1>
			<div class="row placeholders">
			<?php
			foreach($deviceList as $device)
			{
			?>
				<div class="col-lg-4">
					<div class="panel panel-default killdos-widget">
			
						<?php
						if($device["DeviceType"] == "EMON")
						{
							$emon = new EmonWidget();
							$emon->draw($deviceList[0]["_id"],$deviceList[0]["DeviceType"]);
						}
						?>
					</div>
				</div>
			<?php
			}
			?>	
				
            </div>
            
			</div> <!-- end row placeholders -->
		</div><!-- End container --!>
	    <!-- 모든 컴파일된 플러그인을 포함합니다 (아래), 원하지 않는다면 필요한 각각의 파일을 포함하세요 -->
	    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.2/js/bootstrap.min.js"></script>
	</body>
</html>