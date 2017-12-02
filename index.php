<?php

ignore_user_abort(true);
set_time_limit(0);
ini_set('memory_limit','4096M');

class database {

	var $host = "localhost";
	var $user = "root";
	var $pass = "";
	var $db, $con;
	
	function database() {}

	// Establish connection to the database
	function connect($db_name) {
		$this->db = $db_name;
		$this->con = mysqli_connect($this->host, $this->user, $this->pass, $this->db);
		if (mysqli_connect_errno()) $this->sql_error("db-connect", mysqli_connect_error());
	}

	// query with handling
	function query($query="",$type="query",$unbuffered=true) {
		if ($unbuffered) $result = mysqli_query($this->con,$query,MYSQLI_USE_RESULT) or $this->sql_error("$type", mysqli_error($this->con));
		else $result = mysqli_query($this->con,$query) or $this->sql_error("$type", mysqli_error($this->con));
		return $result;
	}

	// query with handling
	function table_list($filter="") {
		$query = "SHOW TABLES FROM ".$this->db;
		if ($filter && $filter != '') $query .= " LIKE '".$filter."'";
		$result = $this->query($query);		
		return $result;
	}

	// handle MySQL error cleanly and exit page with message
	function sql_error($title="",$detail="") {
		//error_log("$title - $detail",0);
		return false;
	}

	// safe query with transaction and error handling
	function safe_query($query="",$type="query",$unbuffered=true) {
		$this->begin();
		if ($unbuffered) $result = mysqli_query($this->con,$query,MYSQLI_USE_RESULT) or $this->sql_error("$type", mysqli_error($this->con));
		else $result = mysqli_query($this->con,$query) or $this->sql_error("$type", mysqli_error($this->con));
		if (!$result) {
			$this->rollback();
			return false;
		} else {
			$this->commit();
		}
		return $result;
	}
	
	// Close the connection to the database
	function close() {
		mysqli_close($this->con) or $this->sql_error("db-close", mysqli_error($this->con));
	}

	// mysql transaction begin
	function begin() {
		mysqli_query($this->con,"BEGIN");
	}

	// mysql transaction commit
	function commit() {
		mysqli_query($this->con,"COMMIT");
	}

	// mysql transaction rollback
	function rollback() {
		mysqli_query($this->con,"ROLLBACK");
	}
}

class rndSequence {

	 var $length = 0;								// length of sequence
	 var $caps = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';		// uppercase set
	 var $small = 'abcdefghjkmnpqrstuvwxyz';		// lowercase set
	 var $nums = '0123456789';						// integer set
	 var $specs = '+-*/,;.:%[]()&^$#@!';			// special char set
	 var $pattern = 'XXNNN-NNNXXX-X';				// pattern for char generation
	 var $badwords = array();						// global array of bad words 
	 var $shuffle = true;							// should we shuffle the characterset?
	
	function rndSequence($pattern) {
		$this->pattern = $pattern;
		$this->length = strlen($this->pattern);
		srand((double)microtime()*1000000);
	}
	
	function Generate(){
		$i = 0;
		$string = '';
		while ($i < $this->length) {
			$char = substr($this->pattern,$i,1);
			switch ($char) {
				 case "x":
					 $string .= $this->_getChar((($this->shuffle) ? str_shuffle($this->small) : $this->small));					 
					 break;
				 case "X":
					 $string .= $this->_getChar((($this->shuffle) ? str_shuffle($this->caps) : $this->caps));
					 break;
				 case "n":
				 case "N":
					 $string .= $this->_getChar((($this->shuffle) ? str_shuffle($this->nums) : $this->nums));
					 break;
				 case "s":
				 case "S":
					 $string .= $this->_getChar((($this->shuffle) ? str_shuffle($this->specs) : $this->specs));
					 break;
				 case "z":
				 case "Z":
                     $string .= $this->_getChar((($this->shuffle) ? str_shuffle($this->caps.$this->nums) : $this->caps.$this->nums));
					 break;
				 default:
					 $string .= $char; 
					 break;
			}
			$i++;
		}
		return ($this->_check($string)) ? $string : $this->Generate();
	}
	
	function _check($string) {
		$badword = false;
		foreach ($this->badwords as $key=>$value) {
			if (preg_match("/$value/",$string)) {
				$badword = true;
				break;
			}
		}
		return ($badword) ? false : true;
	}
	
	function _getChar($set) {
		mt_getrandmax();
		$num = rand() % strlen($set);
		$char = substr($set,$num,1);
		return $char;
	}
	
}

function generate_sequence(&$object,$max,&$database,$table,$prefix,$append) {
	$i = 0;
	while ($i < $max) {
		if ($database->query('INSERT INTO `'.$table.'` (`sequence`) VALUES (\''.$prefix.$object->Generate().$append.'\')')) {
			$i++;
		}
	}
	return $i;
}

function generate($pattern,&$badwords,$caps,$nums,$specs,$max,$table,$drop,$prefix,$append) {
	$seq = new rndSequence($pattern);
	$seq->badwords = $badwords;
	$seq->caps = $caps;
	$seq->small = strtolower($caps);
	$seq->nums = $nums;
	$seq->specs = $specs;
	$db = new database();
	$db->connect('sequence');
	$length = strlen($pattern) + strlen($prefix) + strlen($append);
	if ($drop == 'yes') $db->query('DROP TABLE IF EXISTS `sequence`.`'.$table.'`');
	$db->query('CREATE TABLE IF NOT EXISTS `sequence`.`'.$table.'` (`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT, `sequence` VARCHAR('.$length.') NOT NULL, PRIMARY KEY (`id`), UNIQUE KEY `sequence` (`sequence`), UNIQUE KEY `id` (`id`)) ENGINE = MYISAM');
	return generate_sequence($seq,$max,$db,$table,$prefix,$append);
}

// setup our defaults from request variables or global defaults
$today = date("YmdHms");
$prefix = (isset($_REQUEST['prefix']) && $_REQUEST['prefix'] != "") ? $_REQUEST['prefix'] : '';
$append = (isset($_REQUEST['append']) && $_REQUEST['append'] != "") ? $_REQUEST['append'] : '';
$pattern = (isset($_REQUEST['pattern']) && $_REQUEST['pattern'] != "") ? $_REQUEST['pattern'] : 'ZZZZZZZ';
$badwords = (isset($_REQUEST['badwords']) && $_REQUEST['badwords'] != "") ? explode(",",$_REQUEST['badwords']) : array('KKK','666','XXX');
$caps = (isset($_REQUEST['caps']) && $_REQUEST['caps'] != "") ? $_REQUEST['caps'] : "BCDFGHJKLMNPQRSTVWXYZ";
$nums = (isset($_REQUEST['nums']) && $_REQUEST['nums'] != "") ? $_REQUEST['nums'] : "2345679";
$specs = (isset($_REQUEST['specs']) && $_REQUEST['specs'] != "") ? $_REQUEST['specs'] : "+-*/,;.:%[]()&^$#@!";
$max = (isset($_REQUEST['max']) && $_REQUEST['max'] != "") ? $_REQUEST['max'] : 5000;
$table = (isset($_REQUEST['table']) && $_REQUEST['table'] != "") ? $_REQUEST['table'] : 'sequence'.$today;
$drop = (isset($_REQUEST['drop']) && $_REQUEST['drop'] != "") ? $_REQUEST['drop'] : 'no';
$start = (isset($_REQUEST['start']) && $_REQUEST['start'] != "") ? $_REQUEST['start'] : 0;
$columns = (isset($_REQUEST['columns']) && $_REQUEST['columns'] != "") ? $_REQUEST['columns'] : 1;
$orderby = (isset($_REQUEST['orderby']) && $_REQUEST['orderby'] != "") ? $_REQUEST['orderby'] : 'id';
$seperator = (isset($_REQUEST['seperator']) && $_REQUEST['seperator'] != "") ? $_REQUEST['seperator'] : ',';
$newline = "\n";
$current = 1;
$buffer = '';

if (isset($_REQUEST['action']) && $_REQUEST['action'] == "export") {
	header("Pragma: public");
	header("Expires: 0");
	header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
	header("Cache-Control: private", false);
	header("Content-Type: application/octet-stream");
	header("Content-Disposition: attachment; filename=\"sequence.$today.csv\";" );
	header("Content-Transfer-Encoding: binary"); 
	$db = new database();
	$db->connect('sequence');
	$result = $db->query('SELECT * FROM `sequence`.`'.$table.'` ORDER BY `'.$orderby.'` LIMIT '.$start.','.$max,true);
	if ($result) {
		while($row = @mysqli_fetch_object($result)) {
			if ($columns > 1) {
				$buffer .= ($buffer == '') ? $row->sequence : $seperator.$row->sequence;
				if ($current == $columns) {
					echo $buffer.$newline;
					$buffer = '';
					$current = 1;
				} else {
					$current++;
				}
			} else {
				echo $row->sequence.$newline;
			}
		}
	}
	$db->close();
} else if (isset($_REQUEST['action']) && $_REQUEST['action'] == "generate") {
	// get the start time
	$mtime = microtime();
	$mtime = explode(" ",$mtime);
	$mtime = $mtime[1] + $mtime[0];
	$starttime = $mtime; 
	$count = generate($pattern,$badwords,$caps,$nums,$specs,$max,$table,$drop,$prefix,$append);
	// get the end time
	$mtime = microtime();
	$mtime = explode(" ",$mtime);
	$mtime = $mtime[1] + $mtime[0];
	$endtime = $mtime;
	$totaltime = ($endtime - $starttime);
	$data = array('count'=>$count,'table'=>$table,'time'=>round($totaltime,3));
	$json = json_encode($data);
	$callback = isset($_REQUEST['callback']) ? $_REQUEST['callback'] : null;
	header('Access-Control-Allow-Origin: *');
	header('Content-Type: '.(($callback) ? 'application/javascript' : 'application/json').'; charset=UTF-8');
	echo $callback ? "$callback($json)" : $json;	
} else if (isset($_REQUEST['action']) && $_REQUEST['action'] == "status") {
	$db = new database();
	$db->connect('sequence');
	$result = $db->query("SELECT COUNT(*) AS id FROM ".$table);
     	$num = ($result) ? @mysqli_fetch_array($result) : 0;
	$count = $num["id"];
	$db->close();
	$data = array('count'=>$count,'table'=>$table,'time'=>-1);
	$json = json_encode($data);
	$callback = isset($_REQUEST['callback']) ? $_REQUEST['callback'] : null;
	header('Access-Control-Allow-Origin: *');
	header('Content-Type: '.(($callback) ? 'application/javascript' : 'application/json').'; charset=UTF-8');
	echo $callback ? "$callback($json)" : $json;
} else if (isset($_REQUEST['action']) && $_REQUEST['action'] == "tables") {
	$data = array();
	$db = new database();
	$db->connect('sequence');
	$query = (isset($_REQUEST['query']) && $_REQUEST['query'] != "") ? $_REQUEST['query']."%" : "";
	$result = $db->table_list($query);
	while($row = @mysqli_fetch_array($result)) {
		$data[] = $row[0];
	}	
	$db->close();
	$json = json_encode($data);
	$callback = isset($_REQUEST['callback']) ? $_REQUEST['callback'] : null;
	header('Access-Control-Allow-Origin: *');
	header('Content-Type: '.(($callback) ? 'application/javascript' : 'application/json').'; charset=UTF-8');
	echo $callback ? "$callback($json)" : $json;
} else if (isset($_REQUEST['action']) && $_REQUEST['action'] == "generateinit") {
	$data = array('pattern'=>$pattern, 'badwords'=>implode(',',$badwords), 'caps'=>$caps, 'nums'=>$nums, 'specs'=>$specs, 'max'=>$max, 'table'=>$table, 'drop'=>$drop, 'prefix'=>$prefix, 'append'=>$append);
	$json = json_encode($data);
	$callback = isset($_REQUEST['callback']) ? $_REQUEST['callback'] : null;
	header('Access-Control-Allow-Origin: *');
	header('Content-Type: '.(($callback) ? 'application/javascript' : 'application/json').'; charset=UTF-8');
	echo $callback ? "$callback($json)" : $json;
} else if (isset($_REQUEST['action']) && $_REQUEST['action'] == "exportinit") {
	$data = array('start'=>$start, 'max'=>$max, 'table'=>$table, 'columns'=>$columns, 'orderby'=>$orderby, 'seperator'=>$seperator);
	$json = json_encode($data);
	$callback = isset($_REQUEST['callback']) ? $_REQUEST['callback'] : null;
	header('Access-Control-Allow-Origin: *');
	header('Content-Type: '.(($callback) ? 'application/javascript' : 'application/json').'; charset=UTF-8');
	echo $callback ? "$callback($json)" : $json;
} else {
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Random Sequence Generator</title>
<link rel="stylesheet" href="//maxcdn.bootstrapcdn.com/bootstrap/3.3.5/css/bootstrap.min.css" />
<link rel="stylesheet" href="//maxcdn.bootstrapcdn.com/bootstrap/3.3.5/css/bootstrap-theme.min.css" />
<link rel="stylesheet" href="//gitcdn.github.io/bootstrap-toggle/2.2.0/css/bootstrap-toggle.min.css" />
<link rel="stylesheet" href="//maxcdn.bootstrapcdn.com/font-awesome/4.4.0/css/font-awesome.min.css" />
<link rel="stylesheet" href="//ajax.googleapis.com/ajax/libs/jqueryui/1.11.4/themes/smoothness/jquery-ui.css">
<script src="//ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js"></script>
<script src="//ajax.googleapis.com/ajax/libs/jqueryui/1.11.4/jquery-ui.min.js"></script>
<script src="//maxcdn.bootstrapcdn.com/bootstrap/3.3.5/js/bootstrap.min.js"></script> 
<script src="//gitcdn.github.io/bootstrap-toggle/2.2.0/js/bootstrap-toggle.min.js"></script>
<script src="//cdnjs.cloudflare.com/ajax/libs/bootbox.js/4.4.0/bootbox.min.js"></script>
<style type="text/css">
	body {
		padding-top: 50px;
		padding-bottom: 20px;
		font-size: 10pt;
	}
	.nav.nav-tabs {
		margin: 1em;
	}
	.tab-pane {
		margin: 1em 4em 2em 4em;
		padding-bottom: 2em;
	}
	#generate-result {
		overflow: auto;
		padding: 2em 2em 1em 2em;
		margin-bottom: 2em;
	}
</style>
</head>
<body>

<nav class="navbar navbar-default navbar-inverse navbar-fixed-top">
	<div class="container-fluid">
		<div class="navbar-header">
			<button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar" aria-expanded="false" aria-controls="navbar">
				<span class="sr-only">Toggle navigation</span>
				<span class="icon-bar"></span>
				<span class="icon-bar"></span>
				<span class="icon-bar"></span>
			</button>
			<a class="navbar-brand" href="#">Random Sequence Generator</a>
		</div>
		<div id="navbar" class="navbar-collapse collapse">
			<ul class="nav navbar-nav">
				<li class="active"><a href="#generate" aria-controls="generate" data-toggle="tab">Generate</a></li>
				<li ><a href="#export" aria-controls="export" data-toggle="tab">Export</a></li>
        	</ul>
		</div>
	</div>
</nav>

<div class="tab-content">
	<div role="tabpanel" class="tab-pane active" id="generate">
		<div id="generate-result" class="bg-info img-rounded hidden"></div>
		<form name="generate-form" id="generate-form" action="index.php" method="post">
			<div class="row">
				<div class="col-md-4 col-xs-12">
					<div class="form-group">
						<label for="caps">Character set</label>
						<input type="text" name="caps" id="caps" value="" pattern="[A-Z]*" class="form-control" />
					</div>
				</div>
				<div class="col-md-4 col-xs-12">
					<div class="form-group">
						<label for="nums">Number set</label>
						<input type="text" name="nums" id="nums" value="" pattern="[0-9]*" class="form-control" />
					</div>
				</div>
				<div class="col-md-4 col-xs-12">
					<div class="form-group">
						<label for="specs">Special set</label>
						<input type="text" name="specs" id="specs" value="" class="form-control" />
					</div>
				</div>
			</div>
			<div class="form-group">
				<label for="pattern">Pattern</label>
				<input type="text" name="pattern" id="pattern" value="" class="form-control" required />
				<p class="help-block">X = uppercase, x = lowercase, N = integer, Z = character or integer, S = special char, all other chars will be part of the pattern</p>
			</div>
			<div class="row">
				<div class="col-md-6 col-xs-12">
					<div class="form-group">
						<label for="prefix">Optional prefix string</label>
						<input type="text" name="prefix" id="prefix" value="" class="form-control" />
					</div>
				</div>
				<div class="col-md-6 col-xs-12">
					<div class="form-group">
						<label for="append">Optional append string</label>
						<input type="text" name="append" id="append" value="" class="form-control" />
					</div>
				</div>
			</div>
			<div class="form-group">
				<label for="badwords">Comma delimited list of bad words</label>
				<textarea name="badwords" id="badwords" class="form-control"></textarea>
			</div>
			<div class="form-group">
				<label for="table">Table for results?</label>
				<input type="text" name="table" id="table" value="" class="form-control" />
			</div>
			<div class="form-group">
				<label for="max">How many should we generate?</label>
				<input type="number" name="max" id="max" value="" min="0" max="1000000000" step="1000" class="form-control" />
			</div>
			<div class="form-group">
				<label class="checkbox-inline"><input name="drop" id="drop" type="checkbox" value="yes" data-on="Yes" data-off="No" data-toggle="toggle" checked /> <strong>Drop the table if it exists?</strong></label>
			</div>
			<div class="pull-right">
				<input type="hidden" name="action" value="generate" />
				<button type="submit" class="btn btn-primary btn-lg">
					<i class="fa fa-cogs"></i> Generate
				</button>
			</div>
		</form>
	</div>
	<div role="tabpanel" class="tab-pane" id="export">
		<form name="export-form" id="export-form" action="index.php" method="post">
			<div class="row">
				<div class="col-md-4 col-xs-6">
					<div class="form-group">
						<label for="start">Start at?</label>
						<input type="number" name="start" id="start" value="" min="0" max="1000000000" step="1000" class="form-control" />
					</div>
				</div>
				<div class="col-md-4 col-xs-6">
					<div class="form-group">
						<label for="max">Max rows?</label>
						<input type="number" name="max" id="max" value="" min="0" max="1000000000" step="1000" class="form-control" />
					</div>
				</div>
				<div class="col-md-4 col-xs-6">
					<div class="form-group">
						<label for="columns">Number of columns?</label>
						<input type="number" name="columns" id="columns" value="1" min="1" max="9999" step="1" class="form-control" />
					</div>
				</div>
			</div>
			<div class="row">
				<div class="col-md-4 col-xs-6">
					<div class="form-group">
						<label for="table">Table for results?</label>
						<input type="text" name="table" id="table" value="" class="form-control" />
					</div>
				</div>
				<div class="col-md-4 col-xs-6">
					<div class="form-group">
						<label for="orderby">Order By</label>
						<input type="text" name="orderby" id="orderby" value="id" class="form-control" />
					</div>
				</div>
				<div class="col-md-4 col-xs-6">
					<div class="form-group">
						<label for="seperator">Export Seperator</label>
						<input type="text" name="seperator" id="seperator" value="," class="form-control" />
					</div>
				</div>
			</div>
			<div class="pull-right">
				<input type="hidden" name="action" value="export" />
				<button type="submit" class="btn btn-primary btn-lg">
					<i class="fa fa-arrow-circle-o-down "></i> Export
				</button>
			</div>
		</form>		
	</div>
</div>
<script>

$.fn.serializeObject = function(){
    var o = {};
    var a = this.serializeArray();
    $.each(a, function() {
        if (o[this.name]) {
            if (!o[this.name].push) {
                o[this.name] = [o[this.name]];
            }
            o[this.name].push(this.value || '');
        } else {
            o[this.name] = this.value || '';
        }
    });
    return o;
};

$(document).ready(function() {
	var updateFormValue = function(t,v) {
		if ($(t).is(':checkbox')) {
			$(t).prop('checked', (($(t).val() == v) ? true : false));
		} else if ($(t).is(':radio')) {
			$(t).find('[value="' + v + '"]').prop('checked',true);
		} else {	
			$(t).val(v);
		}
	};
	$.getJSON('index.php', { action:'generateinit' }, function(data) {
		var form = $('#generate-form')[0];
		$.each(data,function(key,value) {
			updateFormValue($('#' + key,form),value);
		});
	});
	$.getJSON('index.php', { action:'exportinit' }, function(data) {
		var form = $('#export-form')[0];
		$.each(data,function(key,value) {
			updateFormValue($('#' + key,form),value);
		});
	});
});

$('#generate-form').on('submit',function(e) {
	e.preventDefault();
	var statusUpdater, statusInterval, statusTimer, statusMessage;
	var form = $(this);
	var result = $('#generate-result');
	var formData = $(this).serializeObject();
	statusMessage = function(data) {
		return data.count + ' rows of ' + formData.max + ' inserted into table ' + data.table + ' in ' + ((data.time > 0) ? data.time : (statusTimer/1000)) + ' seconds.';
	};
	statusUpdater = function() {
		$.getJSON('index.php', { action:'status', table:formData.table }, function(data) {
			result.html('<p><i class="fa fa-cog fa-spin"></i> ' + statusMessage(data) + '</p>').removeClass('hidden').show();
		});
	};
	$('#table','#export-form').val(formData.table);
	$("html, body").animate({ scrollTop: 0 }, "slow");
	$.ajax({
		type: 'POST',
		url: form.attr('action'),
		data: formData,
		timeout: 0,
		beforeSend: function() {
			//console.log(formData);
			result.html('<p><i class="fa fa-cog fa-spin"></i> Generating...</p>').removeClass('hidden').show();
			setTimeout(function() {
				statusTimer = 0;
				statusInterval = setInterval(function() {
					statusTimer += 5000;
					statusUpdater();
				}, 5000);
			},250);
		},
		success: function(data,status,jqXHR) {
			clearInterval(statusInterval);
			result.html('<p><i class="fa fa-check-circle"></i> ' + statusMessage(data) + '  Done.</p>').removeClass('hidden').show();
		},
		error: function(jqXHR,status,error) {
			bootbox.alert('An error occured (' + status + ').  Error: ' + error);
		}
	});	
});

$('#generate-form input[name="table"]').on('change keyup', document, function() {
	$('#table','#export-form').val($(this).val());
});

$('#generate-form input[name="max"]').on('change keyup', document, function() {
	$('#max','#export-form').val($(this).val());
});

$('input[name="table"]').autocomplete({
	source: function(request, response) {
		$.getJSON('index.php', {
			action: 'tables',
			query: request.term
		}, response);
	}
});

</script>
</body>
</html>
<?php
}

?>
