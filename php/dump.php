#!/usr/bin/php
<?php
date_default_timezone_set("UTC");
include "config.php";

function startswith( $haystack, $needle ) {
     $length = strlen( $needle );
     return substr( $haystack, 0, $length ) === $needle;
}

function endswith($string, $test) {
    $strlen = strlen($string);
    $testlen = strlen($test);
    if ($testlen > $strlen) return false;
    return substr_compare($string, $test, $strlen - $testlen, $testlen) === 0;
}

function endswith_i($string, $test) {
	$string=strtolower($string);
	$test=strtolower($test);
    $strlen = strlen($string);
    $testlen = strlen($test);
    if ($testlen > $strlen) return false;
    return substr_compare($string, $test, $strlen - $testlen, $testlen) === 0;
}

function json_iterate($jd,$callback,$f){
		$jsonIterator = new RecursiveIteratorIterator(
		new RecursiveArrayIterator($jd),
			RecursiveIteratorIterator::SELF_FIRST);
        $scope=array();
		foreach ($jsonIterator as $key => $val) {
				
				while(count($scope)>$jsonIterator->getDepth()){
					 array_pop($scope);
				};
				$skey=join(".",$scope).".".$key;
				if(count($scope))
				 $skey=".".$skey;
				if(is_array($val)) {
					array_push($scope,$key);
				}
				$callback($skey,$val,$f);

			/*if(Dump::$debug ){
				//echo "<".$jsonIterator->getDepth().">";
				if(is_array($val)) {
					echo "$skey:{}".PHP_EOL;
				} else {
					echo "$skey => $val".PHP_EOL;
				}
			}*/
		}
}

function json_vals($jd,$f,&$scopes)
{
	$c=function($scope,$val,$f) use (&$scopes){ 
	   foreach ($scopes as $k=>$y) {
				if($k==$scope){
					$scopes[$k]=$val;
					break;
				}
		}
	};
	json_iterate($jd,$c,$f);
}

function json_val($jd,$f,$scope)
{
	$scopes=array($scope=>null);
	json_vals($jd,$f,$scopes);
	return $scopes[$scope];
}

//this just locates where these values are used / defined in the json. Runs in background while doing other useful stuff.
$debug_json_find=array("emod_armorslots_clanferrolamellor");//"chassisdef_leviathan_LVT-C","Gear_Engine_400");
$debug_json_find_ignore=array("ComponentDefID");//ignores keys containing this

abstract class JSONType
{
    const UNKNOWN = 0;
	const MECH = 1;
	const CHASSIS=2;
	const ENGINE=3;
	const MAX_TYPE = 3;
}
$json_type_2_filenames=array();
$json_filename_2_decoded=array();
$json_index_2_filename=array();

//presence of these scoped json vars is used to determine .json file type
$json_type_hint = array( 
	JSONType::MECH => array (
		".MechTags",".ChassisID",".Description.Id"
	),
	JSONType::CHASSIS => array (
		".ChassisTags",".Description.Id"
	),
	JSONType::ENGINE => array (
	".Custom.EngineCore.Rating"
	)
);
//This is the Primary Key for lookup of each JSONType
$json_type_pk = array( 
	JSONType::MECH => ".Description.Id",
	JSONType::CHASSIS => ".Description.Id",
	JSONType::ENGINE => ".Description.Id",
);

function add_json_pk($jd,$f,$json_type)
{
	GLOBAL $json_index_2_filename,$json_type_pk;
	//echo json_encode($json_type_pk).":=?".$json_type;
	$k="[$json_type]".json_val($jd,$f,$json_type_pk[$json_type]);
	$json_index_2_filename[$k]=$f;
	//if($json_type==JSONType::ENGINE)
		//echo "$k => $f".PHP_EOL;
}

function json_for_pk($json_type,$pk){
   GLOBAL $json_index_2_filename,$json_filename_2_decoded;
   $k="[$json_type]".$pk;
   return $json_filename_2_decoded[$json_index_2_filename[$k]];
}

class Dump extends Config{
   public static function main(){
   //we construct an in memory json db
    Dump::init();
	Dump::loadFromFiles();
	//and dump what we need to csv
	Dump::dumpMechs();
   }

   public static function init(){
	   GLOBAL $json_type_2_filenames;
	   for ($x = 1; $x <= JSONType::MAX_TYPE; $x++) {
			$json_type_2_filenames[$x]=array();
	   }
   }

   public static function loadFromFiles(){
	   GLOBAL $json_type_2_filenames,$json_filename_2_decoded,$json_index_2_filename;
   	   echo "Parsing *.json from ".Dump::$RT_Mods_dir.PHP_EOL;
		$files=array();
		Dump::getJSONFiles(Dump::$RT_Mods_dir,$files);
		foreach($files as $f){
			$jd=Dump::parseJSONFile($f);
			if(!$jd)
			  continue;
			//echo ">>>".$f.PHP_EOL;
			$json_type=Dump::guessJSONFileType($f,$jd);
			if($json_type){
				array_push($json_type_2_filenames[$json_type],$f);
				$json_filename_2_decoded[$f]=$jd;
				add_json_pk($jd,$f,$json_type);
			}
		}
		echo "Loaded..".PHP_EOL;
		echo "MECHS:".count($json_type_2_filenames[JSONType::MECH]).PHP_EOL;
		echo "CHASSIS:".count($json_type_2_filenames[JSONType::CHASSIS]).PHP_EOL;
		echo "ENGINE:".count($json_type_2_filenames[JSONType::ENGINE]).PHP_EOL;
   }

public static function getJSONFiles($dirname,&$array){
	if(!is_dir($dirname) || endswith($dirname,".modtek"))
		return;
	$dir = new DirectoryIterator($dirname);
	foreach ($dir as $fileinfo) {
	    if (!$fileinfo->isDot() && endswith($fileinfo->getFilename(),".json") ) {
			$array[]=$dirname.DIRECTORY_SEPARATOR.$fileinfo->getFilename();
	    }
	    if(!$fileinfo->isDot() && is_dir($dirname.DIRECTORY_SEPARATOR.$fileinfo->getFilename())){
			Dump::getJSONFiles($dirname.DIRECTORY_SEPARATOR.$fileinfo->getFilename(),$array);
	    }
	}

	return;
}

public static function parseJSONFile($f){
	$json = file_get_contents($f);

		$json=preg_replace('/\,\s+\}/', '}',$json);
		$json=preg_replace('/\,\s+\]/', ']',$json);
		// search and remove comments like /* */ and //
	    $json = preg_replace("#(/\*([^*]|[\r\n]|(\*+([^*/]|[\r\n])))*\*+/)|([\s\t]//.*)|(^//.*)#", '', $json);
		$json = preg_replace('/[[:^print:]]/', '', $json);

		$jd=json_decode($json, TRUE);

		if($jd==NULL){
			if(json_last_error_msg()!="No error"){
			echo "WARNING: BAD JSON in file:";
			echo $f.PHP_EOL;
			echo PHP_EOL.$json.PHP_EOL;
			echo "ERROR ".json_last_error_msg( ).PHP_EOL;
			}
		}

		return $jd;
}

public static function guessJSONFileType($f,$jd){
	
	$type=JSONType::UNKNOWN;
	$type_match=array();
	for ($x = 1; $x <= JSONType::MAX_TYPE; $x++) {
		$type_match[$x]=0;
	}

	$c=function($scope,$val,$f) use (&$type_match){ 
	   GLOBAL $json_type_hint,$debug_json_find,$debug_json_find_ignore;
	   for ($x = 1; $x <= JSONType::MAX_TYPE; $x++) {
			foreach ($json_type_hint[$x] as $y) {
				if($y==$scope)
				  $type_match[$x]++;
			}
	   }
	   if(DUMP::$debug && $f)
	   {
	    $ignored=false;
		foreach ($debug_json_find_ignore as $y) {
				if(strpos($scope,$y)!== false){
					$ignored=true;
					break;
				}
		}
		if(!$ignored){
	   		foreach ($debug_json_find as $y) {
					if($y===$val){
						echo "[DEBUG] $scope=> $val >>$f".PHP_EOL;
					}
			}
		}
	   }
	};

	json_iterate($jd,$c,$f);
	GLOBAL $json_type_hint;
    for ($x = 1; $x <= JSONType::MAX_TYPE; $x++) {
		//echo "[ $x ] => ".$type_match[$x]."/".count($json_type_hint["".$x]);
		if($type_match[$x]==count($json_type_hint["".$x]) )
			$type=$x;
	}
	
	return $type;
}

public static function dumpMechs(){
	GLOBAL $json_type_2_filenames,$json_filename_2_decoded;
	$csvheader=array("#MECH Id","Tons","Engine Rating","Equipment","path");
	foreach($json_type_2_filenames[JSONType::MECH] as $f){
	    $f="C:\games\steam\steamapps\common\BATTLETECH\Mods\Superheavys\mech\mechdef_leviathan_LVT-C.json";
		$mechjd=$json_filename_2_decoded[$f];
		$chasisjd=json_for_pk(JSONType::CHASSIS,$mechjd["ChassisID"]);
		$equipment=array();
		$engine_rating="";
		Dump::gatherEquipment($chasisjd,"FixedEquipment",$equipment,$engine_rating);
		Dump::gatherEquipment($mechjd,"inventory",$equipment,$engine_rating);
		$dump=array($mechjd["Description"]["Id"],$chasisjd["Tonnage"],$engine_rating, implode(";",$equipment) ,str_replace(Dump::$RT_Mods_dir,"",$f));
		
		echo implode(",", $dump) . PHP_EOL;
		break;
	}
}

public static function gatherEquipment($jd,$json_loc,&$e,&$engine_rating){
	foreach($jd[$json_loc] as $item){
		array_push($e,$item["ComponentDefID"]);
		$enginejd=json_for_pk(JSONType::ENGINE, $item["ComponentDefID"]);
		if($enginejd){
			 $engine_rating=$enginejd["Custom"]["EngineCore"]["Rating"];
		}
	}
}
}

Dump::main();
 
?>