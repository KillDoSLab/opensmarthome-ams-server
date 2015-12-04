<?php
require("./widget/widget.php");
class EmonWidget extends Widget {
	
	function draw($device_id,$device_name)
	{
		global $MongoDB,$deviceRealDataList,$TodayDBDataList,$MongoDataCol;
		
		$realTimeData = null;
		foreach($deviceRealDataList as $item)
		{
			if($item["device_id"] == $device_id)
			{
				$realTimeData = $item;
			}
		}
		
		$emonTodayDatas = null;
		if($TodayDBDataList != null){
			foreach($TodayDBDataList as $item)
			{
				$emonTodayDatas[] = array("date"=>$item["TIME"],"value"=>$item[(string)$device_id]);
				
			}	
		}
		
		
		//한달분의 데이터
		$endDate  = strtotime(date("Y-m-d H:i:s",mktime (23,59,59, date("m"), date("d"), date("Y")))) * 1000;
		$startDate  = strtotime(date("Y-m-d H:i:s",mktime (0,0,0, date("m")-1, 0, date("Y")))) * 1000;
		$MongoDailyCol = $MongoDB->dailyData;
		$DBDataListCursor =  $MongoDailyCol->find(array("TIME"=>array('$gt'=>$startDate,'$lt'=>$endDate)));
		$monthlyDBDatas = null;
		$monthlyTotal = 0;
		foreach($DBDataListCursor as $data)
		{
			$monthlyTotal = $monthlyTotal + $data[(string)$device_id]["v"]["avg"] + $data[(string)$device_id]["v2"]["avg"] + $data[(string)$device_id]["v3"]["avg"];
		}
		
		//시간별 통계
		$endDate  = strtotime(date("Y-m-d H:i:s",mktime (23,59,59, date("m"), date("d"), date("Y")))) * 1000;
		$startDate  = strtotime(date("Y-m-d H:i:s",mktime (0,0,0, date("m"), date("d"), date("Y")))) * 1000;
		$MongoHoulyCol = $MongoDB->hourlyData;
		$DBDataListCursor =  $MongoHoulyCol->find(array("TIME"=>array('$gt'=>$startDate,'$lt'=>$endDate)));
		
		for($i = 0 ; $i <= 24 ;$i++)
		{
			$findFlag = false;
			foreach($DBDataListCursor as $data)
			{
				if($startDate + ($i*60*60*1000) == $data["TIME"])
				{
					$houlyDatas[] = array("date"=>$data["TIME"],"v"=>$data[(string)$device_id]["v"]["avg"],"v2"=>$data[(string)$device_id]["v2"]["avg"],"v3"=>$data[(string)$device_id]["v3"]["avg"]);
					$findFlag = true;
					break;
				}
			}
			if($findFlag == false)
			{
				$houlyDatas[] = array("date"=>$startDate + ($i*60*60*1000),"v"=>0,"v2"=>0,"v3"=>0);

			}
		}
		
		
		
	?>
		<div class="panel-heading">
			<h3 class="panel-title"> 전력량 측정 - <?php echo($device_name); ?></h3>
		</div>
		<div class="panel-body">
		
		
			<ul>
				<li><div id="test">현재     사용량 : <span id="emon_nowValue_<?php echo($device_id); ?>" ><?php 
					$total = $realTimeData["value"]["v"] + $realTimeData["value"]["v2"] + $realTimeData["value"]["v3"];
					echo(($total*220)/1000);
				 ?> </span> KW/h</div></li>
				<li><div>금일 누적 사용량 : <?php
					$todayTotal = 0;
					foreach($houlyDatas as $item)
					{
						$todayTotal = $todayTotal + (($item["v"] + $item["v2"] + $item["v3"]) * 220);
					}
					echo(number_format($todayTotal/1000, 2));
				 ?> KW/h</div></li>
				<li><div>11월 누적 사용량 : <?php
				
				echo(number_format(($monthlyTotal*220)/1000,2));
				 ?> KW/h</div></li>
				<li><div>11월 예상 전기료 : 15,000 원</div></li>
			</ul>
			<script type="text/javascript">
		    google.load('visualization', '1.1', {packages: ['line']});
		    google.setOnLoadCallback(drawChart);
		
		    function drawChart() {
		
		      var data = new google.visualization.DataTable();
		      data.addColumn('date', 'Time');
		      data.addColumn('number', 'KW/h');
		      
		      data.addRows([
		      <?php
		      for($i = 0 ; $i <count($houlyDatas); $i ++)
		      {
		
				$t = $houlyDatas[$i]["date"]/1000;
				$sum = $houlyDatas[$i]["v"] + $houlyDatas[$i]["v2"] + $houlyDatas[$i]["v3"];
				$date = "".date("m",$t)." ".date("d",$t).", ".date("Y",$t)." ".date("H",$t).":".date("i",$t).":".date("s",$t);
				//$sum=1;
				echo("["."new Date('".$date."'),Number(".$sum.")],");
			  }
		        
		      ?>
		      ]);
		
			  var formatter = new google.visualization.NumberFormat({pattern:'#,###C'});
			  formatter.format(data, 1);
			  
		      var options = {
		        chart: {},
		        vAxis: {minValue: 0},
		        legend: 'none',
		      };
			  
			           
		      var chart = new google.charts.Line(document.getElementById('linechart_material'));
		
		      chart.draw(data,options);
		    }
		  </script>
		  <div id="linechart_material"></div>
		</div>		
	<?php
	}
}

?>



