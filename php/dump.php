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
$debug_json_find=array();//"Gear_Myomer_TSM","Gear_MASC","chassisdef_leviathan_LVT-C","Gear_Engine_400");
$debug_json_find_ignore=array();//"ComponentDefID");//ignores keys containing this

abstract class JSONType
{
    const UNKNOWN = 0;
	const MECH = 1;
	const CHASSIS=2;
	const ENGINE=3;
	const COMPONENT=4;
	const MAX_TYPE = 4;
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
	".Custom.EngineCore.Rating",".Description.Id"
	),
	JSONType::COMPONENT => array (
	".ComponentType",".Description.Id"
	),
);
//This is the Primary Key for lookup of each JSONType
$json_type_pk = array( 
	JSONType::MECH => ".Description.Id",
	JSONType::CHASSIS => ".Description.Id",
	JSONType::ENGINE => ".Description.Id",
	JSONType::COMPONENT => ".Description.Id"
);

//some things are other things as well :P
$json_additional_types = array( 
	JSONType::MECH =>	array (),
	JSONType::CHASSIS => array (),
	JSONType::ENGINE => array (JSONType::COMPONENT),
	JSONType::COMPONENT => array ()
);


function add_json_pk($jd,$f,$json_type)
{
	GLOBAL $json_index_2_filename,$json_type_pk;
	//echo json_encode($json_type_pk).":=?".$json_type;
	$k="[$json_type]".json_val($jd,$f,$json_type_pk[$json_type]);
	$json_index_2_filename[$k]=$f;
	//if($json_type==JSONType::COMPONENT)
		//echo "$k => $f".PHP_EOL;
}

function json_for_pk($json_type,$pk){
   GLOBAL $json_index_2_filename,$json_filename_2_decoded;
   $k="[$json_type]".$pk;
   return $json_filename_2_decoded[$json_index_2_filename[$k]];
}

$einfo_dump=array();

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
	   GLOBAL $json_type_2_filenames,$json_filename_2_decoded,$json_index_2_filename,$json_additional_types;
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
				foreach($json_additional_types[$json_type] as $json_type_a){
					array_push($json_type_2_filenames[$json_type_a],$f);
					add_json_pk($jd,$f,$json_type_a);
				}
			}
		}
		echo "Loaded..".PHP_EOL;
		echo "MECHS:".count($json_type_2_filenames[JSONType::MECH]).PHP_EOL;
		echo "CHASSIS:".count($json_type_2_filenames[JSONType::CHASSIS]).PHP_EOL;
		echo "ENGINE:".count($json_type_2_filenames[JSONType::ENGINE]).PHP_EOL;
		echo "COMPONENT:".count($json_type_2_filenames[JSONType::COMPONENT]).PHP_EOL;
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
		if($type_match[$x]==count($json_type_hint["".$x]) ){
			$type=$x;
			break;//first match
		}
	}
	
	return $type;
}

public static function dumpMechs(){
	GLOBAL $json_type_2_filenames,$json_filename_2_decoded,$einfo_dump;
	$csvheader=array("#MECH Id","Tons","Engine Rating",
	"Walk_base","Walk_activated","Run_base","Run_activated",
	"Equipment",
	"path");
	$fp = fopen('./Output/mechs.csv', 'wb');
	fputcsv($fp, $csvheader);
	foreach($json_type_2_filenames[JSONType::MECH] as $f){
	    //$f="C:\games\steam\steamapps\common\BATTLETECH\Mods\Superheavys\mech\mechdef_leviathan_LVT-C.json";$engine_rating
		//$f="C:\games\steam\steamapps\common\BATTLETECH\Mods\Jihad HeavyMetal Unique\mech\mechdef_stealth_STH-5X.json";
		//$f="C:\games\steam\steamapps\common\BATTLETECH\Mods\RogueOmnis\mech\mechdef_centurion_CN11-OX.json";

		//echo "!!!!!".$f.PHP_EOL;
		$mechjd=$json_filename_2_decoded[$f];
		if(DUMP::$debug_single_mech){
			if($mechjd["Description"]["Id"]==DUMP::$debug_single_mech)
				DUMP::$info=TRUE;
			else
			    DUMP::$info=FALSE;
		}
		$chasisjd=json_for_pk(JSONType::CHASSIS,$mechjd["ChassisID"]);
		$equipment=array();
		$einfo=array( // flattened list of all equipment effects and characteristics we extract
		".Custom.EngineCore.Rating"=>"",
		".Custom.EngineHeatBlock.HeatSinkCount"=>0,
		".Custom.Cooling.HeatSinkDefId" => "Gear_HeatSink_Generic_Standard",
		".DissipationCapacity"=>0,
		"CBTBE_RunMultiMod_base"=>0,
		"CBTBE_RunMultiMod_activated"=>0,
		"WalkSpeed_base"=>0,
		"WalkSpeed_activated"=>0
		);
		if($chasisjd["FixedEquipment"])
			Dump::gatherEquipment($chasisjd,"FixedEquipment",$equipment,$einfo);
		Dump::gatherEquipment($mechjd,"inventory",$equipment,$einfo);
		if(DUMP::$info)
			echo json_encode($einfo,JSON_PRETTY_PRINT).PHP_EOL;
		if(DUMP::$debug)
		 	 $einfo_dump=array_merge($einfo_dump, $einfo);
		 
		
		$tonnage=$chasisjd["Tonnage"];
		$engine_rating=$einfo[".Custom.EngineCore.Rating"];
		if(!($engine_rating && $tonnage) )
		{
			//mechdef_deploy_director.json
			if(DUMP::$debug)
				echo "[DEBUG] Ignoring $f".PHP_EOL;
			continue;
		}
		
		$walk_base=0;
		$walk_activated=0;
		$run_base=0;
		$run_activated=0;
		Dump::getWalkRunInfo($einfo,$engine_rating,$tonnage,$walk_base,$walk_activated,$run_base,$run_activated);
		$dissipation_capacity=0;
		$heat_generated=0;

		Dump::getHeatInfo($einfo,$engine_rating,$tonnage,$dissipation_capacity,$heat_generated);
	
		$dump=array($mechjd["Description"]["Id"],$tonnage,$engine_rating,
			$walk_base,$walk_activated,$run_base,$run_activated,
			implode(" ",$equipment),
			str_replace(Dump::$RT_Mods_dir,"",$f));

		if(DUMP::$info)
			echo implode(",", $dump) . PHP_EOL;
		fputcsv($fp, $dump);
		//break;
}
	fclose($fp);
	if(DUMP::$debug){
		$fp = fopen('./Output/debug_einfo.json', 'wb');
		fputs($fp,json_encode($einfo_dump,JSON_PRETTY_PRINT));
		fclose($fp);
	}
	echo "Exported Mechs to ".'./Output/mechs.csv'.PHP_EOL;
}

public static function getWalkRunInfo($einfo,$engine_rating,$tonnage,&$walk_base,&$walk_activated,&$run_base,&$run_activated){
		//walk/run distance
		//https://github.com/BattletechModders/CBTBehaviorsEnhanced
		$MovementPointDistanceMultiplier = 24;
		$walk_base=round($engine_rating/$tonnage+($einfo["WalkSpeed_base"]/$MovementPointDistanceMultiplier));
		$walk_activated=round($engine_rating/$tonnage+(($einfo["WalkSpeed_base"]+$einfo["WalkSpeed_activated"])/$MovementPointDistanceMultiplier));
		$run_base=round (($engine_rating/$tonnage+($einfo["WalkSpeed_base"]/$MovementPointDistanceMultiplier))*(1.5+$einfo["CBTBE_RunMultiMod_base"]));
		$run_activated=round(($engine_rating/$tonnage+(($einfo["WalkSpeed_base"]+$einfo["WalkSpeed_activated"])/$MovementPointDistanceMultiplier))*(1.5+$einfo["CBTBE_RunMultiMod_base"]+$einfo["CBTBE_RunMultiMod_activated"]));
}

public static function getHeatInfo($einfo,$engine_rating,$tonnage,&$dissipation_capacity,&$heat_generated){
		$internal_hs=(int)($engine_rating/25);
		if($internal_hs>10)
		 $internal_hs=10;
		$heatsinkjd=json_for_pk(JSONType::COMPONENT, $einfo[".Custom.Cooling.HeatSinkDefId"]);
		$per_heatsink_dissipation=$heatsinkjd["DissipationCapacity"];
		$dissipation_capacity=$internal_hs * $per_heatsink_dissipation+$einfo["DissipationCapacity"];
		if(DUMP::$info){
			 echo "DissipationCapacity Internal[ $internal_hs x $per_heatsink_dissipation ] + External[".$einfo["DissipationCapacity"]."] = $dissipation_capacity".PHP_EOL;
		}
		$heat_generated=0;
		foreach($einfo as $key => $value) {
			if (startswith($key,".WeaponHeatGenerated|")){
				$h=$value;
				
				if(DUMP::$info)
					echo "HeatGenerated from $key = $h ".PHP_EOL;
				
				foreach($einfo as $ekey => $evalue) {
					if (startswith($ekey,"Weapon.|") && endswith($ekey,"|.HeatGenerated_activated") ){
						 if(Dump::weaponMatch($key,$ekey)){
							if(DUMP::$info)
								echo "Y $ekey = $h x $evalue".PHP_EOL;
							$h=$h*$evalue;
						 }else{
							if(DUMP::$info)
								echo "X $ekey".PHP_EOL;
						 }
					}
				}
				if(DUMP::$info)
					echo "= $h ".PHP_EOL;
				$heat_generated+=$h;
			}
		}
}

public static function weaponMatch($key,$ekey){
    //".WeaponHeatGenerated|Energy|Laser|LargeLaser|*|." key <-weapon
    //"Weapon.|*|Laser|*|*|.HeatGenerated_activated" ekey <-equipment with targetCollection Weapon
	$k=explode ("|", $key); 
	$e=explode ("|", $ekey); 
	if($e[1]!='*' && $e[1]!=$k[1])
		return FALSE;
	if($e[2]!='*' && $e[2]!=$k[2])
		return FALSE;
	if($e[3]!='*' && $e[3]!=$k[3])
		return FALSE;
	if($e[4]!='*' && $e[4]!=$k[4])
		return FALSE;
	return TRUE;
}

public static function gatherEquipment($jd,$json_loc,&$e,&$einfo){
	if(!$jd[$json_loc])
	{
		if(DUMP::$debug)
			echo "[DEBUG] Missing $json_loc in ".json_encode($jd,JSON_PRETTY_PRINT).PHP_EOL;
		return;
	}
	foreach($jd[$json_loc] as $item){
		array_push($e,$item["ComponentDefID"]);
		$location="ALL";
		if($item["MountedLocation"])
		  $location=$item["MountedLocation"];
		$enginejd=json_for_pk(JSONType::ENGINE, $item["ComponentDefID"]);
		if($enginejd){
			$einfo[".Custom.EngineCore.Rating"]=$enginejd["Custom"]["EngineCore"]["Rating"];
			if(DUMP::$info)
				echo "EINFO[.Custom.EngineCore.Rating ] : ".$einfo[".Custom.EngineCore.Rating"].PHP_EOL;
		}

		$componentjd=json_for_pk(JSONType::COMPONENT, $item["ComponentDefID"]);

		if(DUMP::$info)
			echo $item["ComponentDefID"]." ===========================> ".PHP_EOL.json_encode($componentjd,JSON_PRETTY_PRINT).PHP_EOL.PHP_EOL;

		//Heat
		if($componentjd["Custom"] && $componentjd["Custom"]["EngineHeatBlock"] && $componentjd["Custom"]["EngineHeatBlock"]["HeatSinkCount"]){
			$einfo[".Custom.EngineHeatBlock.HeatSinkCount"]=$einfo[".Custom.EngineHeatBlock.HeatSinkCount"]+(int)$componentjd["Custom"]["EngineHeatBlock"]["HeatSinkCount"];
			if(DUMP::$info)
				echo "EINFO[.Custom.EngineHeatBlock.HeatSinkCount ] : ".$einfo[".Custom.EngineHeatBlock.HeatSinkCount"].PHP_EOL;
		}
		if($componentjd["Custom"] && $componentjd["Custom"]["Cooling"] && $componentjd["Custom"]["Cooling"]["HeatSinkDefId"]){
			$einfo[".Custom.Cooling.HeatSinkDefId"]=$componentjd["Custom"]["Cooling"]["HeatSinkDefId"];
			if(DUMP::$info)
				echo "EINFO[.Custom.Cooling.HeatSinkDefId ] : ".$einfo[".Custom.Cooling.HeatSinkDefId"].PHP_EOL;
		}
		if($componentjd["DissipationCapacity"])
		{
			$einfo[".DissipationCapacity"]=$einfo[".DissipationCapacity"]+(float)$componentjd["DissipationCapacity"];
			if(DUMP::$info)
				echo "EINFO[.DissipationCapacity ] : ".$einfo[".DissipationCapacity"].PHP_EOL;
		}
		if($componentjd["HeatGenerated"])
		{
			$class=
				  "|".( (!$componentjd["Category"] || $componentjd["Category"]=="NotSet") ? "*" :$componentjd["Category"]).
				  "|".( (!$componentjd["Type"] || $componentjd["Type"]=="NotSet") ? "*" :$componentjd["Type"]).
				  "|".( (!$componentjd["WeaponSubType"] || $componentjd["WeaponSubType"]=="NotSet") ? "*" :$componentjd["WeaponSubType"]).
				  "|".( (!$componentjd["AmmoCategory"] || $componentjd["AmmoCategory"]=="NotSet") ? "*" :$componentjd["AmmoCategory"]).
				  "|.";
		    $k=".".$componentjd["ComponentType"]."HeatGenerated".$class;
			$einfo[$k]=($einfo[$k] ? $einfo[$k] :0) +(float)$componentjd["HeatGenerated"];
			if(DUMP::$info)
				echo "EINFO[$k ] : ".$einfo[$k].PHP_EOL;
		}
		//Heat

		

		if($componentjd["Custom"] && $componentjd["Custom"]["ActivatableComponent"] && $componentjd["Custom"]["ActivatableComponent"]["statusEffects"]){
			foreach($componentjd["Custom"]["ActivatableComponent"]["statusEffects"]  as $effectjd){
				Dump::gatherEquipmentEffectInfo($item["ComponentDefID"],$location,$effectjd,$einfo,true);
			}
		}
		if($componentjd["Auras"] ){
			foreach($componentjd["Auras"] as $aura){
			    if($aura["statusEffects"]){
					foreach($aura["statusEffects"]  as $effectjd){
						Dump::gatherEquipmentEffectInfo($item["ComponentDefID"],$location,$effectjd,$einfo);
					}
				}
			}
		}
		if($componentjd["statusEffects"] ){
			foreach($componentjd["statusEffects"] as $effectjd){
				Dump::gatherEquipmentEffectInfo($item["ComponentDefID"],$location,$effectjd,$einfo);
			}
		}	
		if(DUMP::$info)
			echo" <===========================".PHP_EOL;
	}
}

public static function gatherEquipmentEffectInfo($componentid,$location,$effectjd,&$einfo,$force_activated=false){
	
	if($effectjd["targetingData"] && $effectjd["targetingData"]["effectTargetType"]=="Creator"){
		
		$effect=null;
		$effectval=null;

		$duration="_activated";

		//$force_activated is cos CAE status effect have duration -1 but they are activateable
		if(!$force_activated && $effectjd[ "durationData"] && $effectjd[ "durationData"]["duration"]<0)
			$duration="_base";

		if($effectjd[ "statisticData"] && $effectjd[ "statisticData"]["operation"]){
			$effect=$effectjd[ "statisticData"]["statName"];
			switch ($effectjd[ "statisticData"]["operation"]) {
				case "Int_Add":
				case "Float_Add":
					$effectval = ($einfo[$effect.$duration] ? $einfo[$effect.$duration]:0)+(float)$effectjd[ "statisticData"]["modValue"];
					break;
				case "Int_Subtract":
				case "Float_Subtract":
					$effectval = ($einfo[$effect.$duration] ? $einfo[$effect.$duration]:0)-(float)$effectjd[ "statisticData"]["modValue"];
					break;
				case "Float_Multiply":
				case "Int_Multiply":
					$effectval = ($einfo[$effect.$duration] ? $einfo[$effect.$duration]:1)*(float)$effectjd[ "statisticData"]["modValue"];
					break;
				case "Set":
				    break;
				default:
					if(DUMP::$debug)
						echo "[DEBUG] UNKNOWN OPERATION ".$effectjd[ "statisticData"]["operation"].PHP_EOL;
					break;
			}
			switch ($effectjd[ "statisticData"]["targetCollection"]) {
				case "Pilot":
				  $effect="Pilot.".$effect;
				  break;
				case "Weapon":
				  $class=
				  "|".( (!$effectjd[ "statisticData"]["targetWeaponCategory"] || $effectjd[ "statisticData"]["targetWeaponCategory"]=="NotSet") ? "*" :$effectjd[ "statisticData"]["targetWeaponCategory"]).
				  "|".( (!$effectjd[ "statisticData"]["targetWeaponType"] || $effectjd[ "statisticData"]["targetWeaponType"]=="NotSet") ? "*" :$effectjd[ "statisticData"]["targetWeaponType"]).
				  "|".( (!$effectjd[ "statisticData"]["targetWeaponSubType"] || $effectjd[ "statisticData"]["targetWeaponSubType"]=="NotSet") ? "*" :$effectjd[ "statisticData"]["targetWeaponSubType"]).
				  "|".( (!$effectjd[ "statisticData"]["targetAmmoCategory"] || $effectjd[ "statisticData"]["targetAmmoCategory"]=="NotSet") ? "*" :$effectjd[ "statisticData"]["targetAmmoCategory"]).
				  "|.";
				  $effect="Weapon.".$class.$effect;
				  $duration="_activated";//weapons have to be fired so always treat effect as activated.
				  break;
				default:
					/*if(DUMP::$debug)
						echo "[DEBUG]  targetCollection ".$effectjd[ "statisticData"]["targetCollection"]." >>".$effect.$duration."( $componentid )".PHP_EOL;*/
					break;
			}
		}
		
		if($effect && $effectval){
			$effect=str_replace("{location}",$location,$effect);
			$einfo[$effect.$duration]=$effectval;
			if(DUMP::$info)
				echo "EINFO[ $effect"."$duration ] : $effectval".PHP_EOL;
		}

	}
}
}

Dump::main();
 
?>