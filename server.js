var net = require('net');
var PORT = 1010

var http = require('http').Server();
var io = require('socket.io')(http);

io.on('connection',function(socket){
	console.log('a user connected');
});
http.listen(1011,function()
{
	console.log('listening on *:1011');
});
var mongojs = require('mongojs');
var db =mongojs('oshDB');


var realTimeDataCol = db.collection('realTimeData');

//
Date.prototype.format = function(f) {
    if (!this.valueOf()) return " ";
 
    var weekName = ["일요일", "월요일", "화요일", "수요일", "목요일", "금요일", "토요일"];
    var d = this;
     
    return f.replace(/(yyyy|yy|MM|dd|E|hh|mm|ss|a\/p)/gi, function($1) {
        switch ($1) {
            case "yyyy": return d.getFullYear();
            case "yy": return (d.getFullYear() % 1000).zf(2);
            case "MM": return (d.getMonth() + 1).zf(2);
            case "dd": return d.getDate().zf(2);
            case "E": return weekName[d.getDay()];
            case "HH": return d.getHours().zf(2);
            case "hh": return ((h = d.getHours() % 12) ? h : 12).zf(2);
            case "mm": return d.getMinutes().zf(2);
            case "ss": return d.getSeconds().zf(2);
            case "a/p": return d.getHours() < 12 ? "오전" : "오후";
            default: return $1;
        }
    });
};
 
String.prototype.string = function(len){var s = '', i = 0; while (i++ < len) { s += this; } return s;};
String.prototype.zf = function(len){return "0".string(len - this.length) + this;};
Number.prototype.zf = function(len){return this.toString().zf(len);};
//


net.createServer( function(socket){
	
	console.log('Connected: ' + socket.remoteAddress + ':'+ socket.remotePort);
	socket.on('data',function(data){
		
		if(data.toString('ascii') == "hello")
		{
			socket.write("echo:"+data);
		}
		else
		{
			console.log("Receive Raw data:");
			console.log(data.toString('ascii'));
			try{
				var jsonData = JSON.parse(data.toString('ascii'));
				console.log("mac: "+jsonData.mac);
				var xbeeRawData =  new Buffer(jsonData.data,'base64');
				console.log("xbeeData: ");
				var xbeeAddress = xbeeRawData.slice(4,12);
				var xbeeData = xbeeRawData.slice(15,xbeeRawData.length-2);
				console.log(xbeeData.toString('ascii'));	
				var xbeeJson = JSON.parse(xbeeData.toString('ascii'));
				console.log("Device Type: "+xbeeJson.dt);
				console.log("Device Address: ");
				console.log(xbeeAddress);

				//기존에 등록된 디바이스인지 확인한다.
				var deviceCol = db.collection('device');
				deviceCol.find({DeviceType:xbeeJson.dt,Address:xbeeAddress},function(err,docs)
				{
					if(docs)
					{
						//등록된 디바이스라면
						if(docs.length > 0)
						{
							insertData(xbeeJson,docs[0]._id);
						}	
						else
						{
							deviceCol.insert({DeviceType:xbeeJson.dt,Address:xbeeAddress},function(err,docs)
							{
								console.log("device registered");
								//console.log(docs._id);
								insertData(xbeeJson,docs._id);
								
							});	
						}
					}
					deviceCol.close;

				});
			}
			catch(e)
			{
				console.log("json parse error: "+e);
			}
			
			
		}
	});
	socket.on('close',function(data)
	{
		console.log("Close: "+  socket.remoteAddress + ':'+ socket.remotePort);	
	});
	socket.on('error',function(e)
	{
		console.log("error:"+e);	
	});
	
}).listen(PORT);

function insertData(xbeeJson,device_id) 
{
	var houlyData = null;
	var dalyData = null;

	//RealTime
	var realTimeCol = db.collection('realTime');	
	var nowTime = new Date().getTime();
	realTimeDataCol.findOne({device_id:device_id},function(res,doc){
		if(doc)
		{
			//내용 업데이트
			realTimeDataCol.update({device_id:device_id},{$set:{value:xbeeJson,TIME:nowTime}},function(res,doc){
				realTimeDataCol.close;
			});
		}
		else
		{
			//새로 추가
			realTimeDataCol.insert({device_id:device_id,value:xbeeJson,TIME:nowTime},function(res,doc){
				realTimeDataCol.close;
			});
		}
	});
	// 분간데이터 처리
	var time = new Date().format("yyyy-MM-dd HH:mm:00");
	time = new Date(time);
	var nowMin = time.getTime();
	//현재 시간의 데이터가 있는지 확인
	var dataCol = db.collection('data');
	dataCol.findOne({TIME:nowMin},function(err,doc)
	{
		//분데이터는 있다
		if(doc)
		{
			//해당 Row에 디바이스가 있는가?
			if(device_id in doc) //있다면
			{
				console.log("update!");
				var items = doc[device_id];
				for(var attr in items)
				{
					items[attr].sum = Number(items[attr].sum) + Number(xbeeJson[attr]);
					items[attr].count = Number(items[attr].count) + 1;
					items[attr].avg = items[attr].sum / items[attr].count;
				}
				dataCol.update({_id:doc._id},{$set:{[device_id]:items}},function(err,doc){
					dataCol.close;
				});
				
			}
			else //없다면
			{
				console.log("add device(ROW)");
				var newData = {};
				for(var attr in xbeeJson)
				{
					if(attr != "dt")
					{
						newData[attr] = {avg:Number(xbeeJson[attr]),sum:Number(xbeeJson[attr]),count:1};
					}				
				}
				dataCol.update({_id:doc._id},{$set:{[device_id]:newData}},function(err,doc){
					dataCol.close;
				});
			}
			
			
		}
		else
		{
			var newData = {};
			
			for(var attr in xbeeJson)
			{
				if(attr != "dt")
				{
					newData[attr] = {avg:Number(xbeeJson[attr]),sum:Number(xbeeJson[attr]),count:1};
				}				
			}
			//새로 추가
			console.log("minly new insert!");
			var insertJson = {"TIME":nowMin, [device_id]:newData};
			console.log(insertJson);
			
			dataCol.insert(insertJson,function(err,doc)
			{
				if(doc)
				{
					console.log("newData insert success");
				}
				if(err)
				{
					console.log("newData insert error:"+err);
				}
				dataCol.close;
			});
			
		}
	});
	
	//시간당 평균 데이터 처리
	var time = new Date().format("yyyy-MM-dd HH:00:00");
	time = new Date(time);
	var nowHour = time.getTime();
	//현재 시간의 데이터가 있는지 확인
	var houlyDataCol = db.collection('hourlyData');
	houlyDataCol.findOne({TIME:nowHour},function(err,doc)
	{
		//시데이터는 있다
		if(doc)
		{
			//해당 Row에 디바이스가 있는가?
			if(device_id in doc) //있다면
			{
				console.log("update!");
				var items = doc[device_id];
				for(var attr in items)
				{
					items[attr].sum = Number(items[attr].sum) + Number(xbeeJson[attr]);
					items[attr].count = Number(items[attr].count) + 1;
					items[attr].avg = items[attr].sum / items[attr].count;
				}
				houlyDataCol.update({_id:doc._id},{$set:{[device_id]:items}},function(err,doc){
					houlyDataCol.close;
				});
				
				houlyData = items;
				io.emit("onInsert",{device_id:[device_id],houly:houlyData,deviceType:xbeeJson["dt"]});
				
			}
			else //없다면
			{
				console.log("add device(ROW)");
				var newData = {};
				for(var attr in xbeeJson)
				{
					if(attr != "dt")
					{
						newData[attr] = {avg:Number(xbeeJson[attr]),sum:Number(xbeeJson[attr]),count:1};
					}				
				}
				houlyDataCol.update({_id:doc._id},{$set:{[device_id]:newData}},function(err,doc){
					houlyDataCol.close;
				});
				houlyData = newData;
				io.emit("onInsert",{device_id:[device_id],houly:houlyData,deviceType:xbeeJson["dt"]});
			}
			
			
		}
		else
		{
			var newData = {};
			
			for(var attr in xbeeJson)
			{
				if(attr != "dt")
				{
					newData[attr] = {avg:Number(xbeeJson[attr]),sum:Number(xbeeJson[attr]),count:1};
				}				
			}
			//새로 추가
			console.log("hourly new insert!");
			var insertJson = {"TIME":nowHour, [device_id]:newData};
			console.log(insertJson);
			
			houlyDataCol.insert(insertJson,function(err,doc)
			{
				if(doc)
				{
					console.log("newData insert success");
				}
				if(err)
				{
					console.log("newData insert error:"+err);
				}
				houlyDataCol.close;
			});
			houlyData = newData;
			io.emit("onInsert",{device_id:[device_id],houly:houlyData,deviceType:xbeeJson["dt"]});
		}
	});

	//일간
	var time = new Date().format("yyyy-MM-dd HH:00:00");
	time = new Date(time);
	var nowDay = time.getTime();
	//현재 시간의 데이터가 있는지 확인
	var dalyDataCol = db.collection('dailyData');
	dalyDataCol.findOne({TIME:nowDay},function(err,doc)
	{
		//시데이터는 있다
		if(doc)
		{
			//해당 Row에 디바이스가 있는가?
			if(device_id in doc) //있다면
			{
				console.log("update!");
				var items = doc[device_id];
				for(var attr in items)
				{
					items[attr].sum = Number(items[attr].sum) + Number(xbeeJson[attr]);
					items[attr].count = Number(items[attr].count) + 1;
					items[attr].avg = items[attr].sum / items[attr].count;
				}
				dalyDataCol.update({_id:doc._id},{$set:{[device_id]:items}},function(err,doc){
					dataCol.close;
				});
				dalyData = items;
				io.emit("onInsert",{device_id:[device_id],daily:dalyData,deviceType:xbeeJson["dt"]});
			}
			else //없다면
			{
				console.log("add device(ROW)");
				var newData = {};
				for(var attr in xbeeJson)
				{
					if(attr != "dt")
					{
						newData[attr] = {avg:Number(xbeeJson[attr]),sum:Number(xbeeJson[attr]),count:1};
					}				
				}
				dalyDataCol.update({_id:doc._id},{$set:{[device_id]:newData}},function(err,doc){
					dataCol.close;
				});
				dalyData = newData;
				io.emit("onInsert",{device_id:[device_id],daily:dalyData,deviceType:xbeeJson["dt"]});
			}
			
			
		}
		else
		{
			var newData = {};
			
			for(var attr in xbeeJson)
			{
				if(attr != "dt")
				{
					newData[attr] = {avg:Number(xbeeJson[attr]),sum:Number(xbeeJson[attr]),count:1};
				}				
			}
			//새로 추가
			console.log("dayly new insert!");
			var insertJson = {"TIME":nowDay, [device_id]:newData};
			console.log(insertJson);
			
			dalyDataCol.insert(insertJson,function(err,doc)
			{
				if(doc)
				{
					console.log("newData insert success");
				}
				if(err)
				{
					console.log("newData insert error:"+err);
				}
				dalyDataCol.close;
			});
			dalyData = newData;
			io.emit("onInsert",{device_id:[device_id],daily:dalyData,deviceType:xbeeJson["dt"]});
		}
	});

	//월간
	io.emit("onInsert",{device_id:[device_id],realData:xbeeJson,deviceType:xbeeJson["dt"]});//,hourly:houlyData,daily:dalyData});
	
}

console.log('Server listening on ' + PORT);
