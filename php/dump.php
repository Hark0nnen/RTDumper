<?php
include ".\php\common.php";

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
	const MODJSON=5;
	const MAX_TYPE = 5;
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
	JSONType::MODJSON => array (
	".Name",".DependsOn",".ConflictsWith",".Settings"
	),
);
//This is the Primary Key for lookup of each JSONType
$json_type_pk = array( 
	JSONType::MECH => ".Description.Id",
	JSONType::CHASSIS => ".Description.Id",
	JSONType::ENGINE => ".Description.Id",
	JSONType::COMPONENT => ".Description.Id",
	JSONType::MODJSON => ".Name"
);

//some things are other things as well :P
$json_additional_types = array( 
	JSONType::MECH =>	array (),
	JSONType::CHASSIS => array (),
	JSONType::ENGINE => array (JSONType::COMPONENT),
	JSONType::COMPONENT => array (),
	JSONType::MODJSON => array (),
);


function add_json_pk($jd,$f,$json_type)
{
	GLOBAL $json_index_2_filename,$json_type_pk;
	//echo json_encode($json_type_pk).":=?".$json_type;
	$k="[$json_type]".json_val($jd,$f,$json_type_pk[$json_type]);
	$json_index_2_filename[$k]=$f;
	/*if($json_type==JSONType::MODJSON)
		echo "$k => $f".PHP_EOL;*/
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
		echo "MODJSON:".count($json_type_2_filenames[JSONType::MODJSON]).PHP_EOL;
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
	GLOBAL $json_type_2_filenames,$json_filename_2_decoded,$einfo_dump,$csv_header;
	
	$fp = fopen('./Output/mechs.csv', 'wb');
	fputcsv($fp, $csv_header);
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
		$einfo=array( // flattened list of all equipment effects and characteristics we extract those starting with . are manually extracted, without are effects and auto extracted
		".Custom.EngineCore.Rating"=>"",
		".Custom.CASE.MaximumDamage"=>-1,
		".Custom.EngineHeatBlock.HeatSinkCount"=>0,
		".Custom.Cooling.HeatSinkDefId" => "Gear_HeatSink_Generic_Standard",
		".Custom.ActivatableComponent.AutoActivateOnHeat"=>0,
		".DissipationCapacity"=>0,
		"CBTBE_RunMultiMod_base"=>0,
		"CBTBE_RunMultiMod_activated"=>0,
		"CBTBE_AmmoBoxExplosionDamage"=>0,
		"CBTBE_VolatileAmmoBoxExplosionDamage"=>0,
		"CBTBE_Charge_Attacker_Damage_Multi_base"=>1,
		"CBTBE_Charge_Attacker_Damage_Multi_activated"=>1,
		"CBTBE_DFA_Attacker_Damage_Multi_base"=>1,
		"CBTBE_DFA_Attacker_Damage_Multi_activated"=>1,
		"CBTBE_Charge_Target_Damage_Multi_base"=>1,
		"CBTBE_Charge_Target_Damage_Multi_activated"=>1,
		"CBTBE_Charge_Attacker_Instability_Multi_base"=>1,
		"CBTBE_Charge_Attacker_Instability_Multi_activated"=>1,
		"CBTBE_Charge_Target_Instability_Multi_base"=>1,
		"CBTBE_Charge_Target_Instability_Multi_activated"=>1,
		"CBTBE_DFA_Target_Damage_Multi_base"=>1,
		"CBTBE_DFA_Target_Damage_Multi_activated"=>1,
		"CBTBE_DFA_Attacker_Instability_Multi_base"=>1,
		"CBTBE_DFA_Attacker_Instability_Multi_activated"=>1,
		"CBTBE_DFA_Target_Instability_Multi_base"=>1,
		"CBTBE_DFA_Target_Instability_Multi_activated"=>1,
		"CBTBE_Kick_Target_Damage_Multi_base"=>1,
		"CBTBE_Kick_Target_Damage_Multi_activated"=>1,
		"CBTBE_Kick_Target_Instability_Multi_base"=>1,
		"CBTBE_Kick_Target_Instability_Multi_activated"=>1,
		"CBTBE_Physical_Weapon_Target_Damage_Multi_base"=>1,
		"CBTBE_Physical_Weapon_Target_Damage_Multi_activated"=>1,
		"CBTBE_Physical_Weapon_Target_Instability_Multi_base"=>1,
		"CBTBE_Physical_Weapon_Target_Instability_Multi_activated"=>1,
		"CBTBE_Punch_Target_Damage_Multi_base"=>1,
		"CBTBE_Punch_Target_Damage_Multi_activated"=>1,
		"CBTBE_Physical_Weapon_Target_Instability_Multi_base"=>1,
		"CBTBE_Physical_Weapon_Target_Instability_Multi_activated"=>1,
		"CBTBE_Punch_Target_Instability_Multi_base"=>1,
		"CBTBE_Punch_Target_Instability_Multi_activated"=>1,
		"AMSSINGLE_HeatGenerated"=>0,
		"AMSMULTI_HeatGenerated"=>0,
		"WalkSpeed_base"=>0,
		"WalkSpeed_activated"=>0,
		".JumpCapacity"=>0,
		"JumpDistanceMultiplier_base"=>1,
		"JumpDistanceMultiplier_activated"=>1
		);
		try{
			if($chasisjd["FixedEquipment"])
				Dump::gatherEquipment($chasisjd,"FixedEquipment",$equipment,$einfo);
			Dump::gatherEquipment($mechjd,"inventory",$equipment,$einfo);
			if(DUMP::$info)
				echo json_encode($einfo,JSON_PRETTY_PRINT).PHP_EOL;
			if(DUMP::$debug)
		 		 $einfo_dump=array_merge($einfo_dump, $einfo);
		}catch (Exception $e) {
			if(DUMP::$debug)
				echo "[DEBUG] ".$e->getMessage().PHP_EOL;
		}	
		 
		
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
		$dissipation_capacity_base=0;
		$dissipation_capacity_activated=0;
		$heat_generated=0;
		$jump_heat_base=0;
		$jump_heat_activated=0;
		$heat_efficency=100;

		Dump::getHeatInfo($einfo,$engine_rating,$tonnage,$dissipation_capacity_base,$dissipation_capacity_activated,$heat_generated,$jump_heat_base,$jump_heat_activated,$heat_efficency);

		$jump_distance_base=(int) ($einfo[".JumpCapacity"]*$einfo["JumpDistanceMultiplier_base"]);
		$jump_distance_activated=(int) ($einfo[".JumpCapacity"]*$einfo["JumpDistanceMultiplier_base"]*$einfo["JumpDistanceMultiplier_activated"]);

		//CASE Explosion reduction
		if($einfo["CBTBE_AmmoBoxExplosionDamage"]>0 && $einfo[".Custom.CASE.MaximumDamage"]>=0)
			$einfo["CBTBE_AmmoBoxExplosionDamage"]=$einfo[".Custom.CASE.MaximumDamage"];
		if($einfo["CBTBE_VolatileAmmoBoxExplosionDamage"]>0 && $einfo[".Custom.CASE.MaximumDamage"]>=0)
			$einfo["CBTBE_VolatileAmmoBoxExplosionDamage"]=$einfo[".Custom.CASE.MaximumDamage"];

		Dump::getPhysicalInfo($einfo,$tonnage,$ChargeAttackerDamage,$ChargeTargetDamage,$ChargeAttackerInstability,$ChargeTargetInstability,$DFAAttackerDamage,$DFATargetDamage,$DFAAttackerInstability,$DFATargetInstability,$KickDamage,$KickInstability,$PhysicalWeaponDamage,$PhysicalWeaponInstability,$PunchDamage,$PunchInstability);

		Dump::getDefensiveInfo($einfo,$chasisjd,$armor,$structure,$leg_armor,$leg_structure,$armor_repair,$structure_repair,$leg_armor_repair,$leg_structure_repair);

		$dump=array($mechjd["Description"]["Id"],$tonnage,$engine_rating,
			$walk_base,$walk_activated,$run_base,$run_activated,
			$jump_distance_base,$jump_distance_activated,
			$dissipation_capacity_base,$dissipation_capacity_activated,$einfo[".Custom.ActivatableComponent.AutoActivateOnHeat"],$heat_generated,$jump_heat_base,$jump_heat_activated,
			$einfo["CBTBE_AmmoBoxExplosionDamage"],$einfo["CBTBE_VolatileAmmoBoxExplosionDamage"],
			$einfo["AMSSINGLE_HeatGenerated"],$einfo["AMSMULTI_HeatGenerated"],
			0+$einfo["ReceiveHeatDamageInjury_activated"]+$einfo["ReceiveHeatDamageInjury_base"],$heat_efficency,
			$ChargeAttackerDamage,$ChargeTargetDamage,$ChargeAttackerInstability,$ChargeTargetInstability,
			$DFAAttackerDamage,$DFATargetDamage,$DFAAttackerInstability,$DFATargetInstability,
			$KickDamage,$KickInstability,
			$PhysicalWeaponDamage,$PhysicalWeaponInstability,
			$PunchDamage,$PunchInstability,
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
public static function getDefensiveInfo($einfo,$chasisjd,&$armor,&$structure,&$leg_armor,&$leg_structure,&$armor_repair,&$structure_repair,&$leg_armor_repair,&$leg_structure_repair){
	$armor=0;
	$structure=0;
	$leg_armor=0;
	$leg_structure=0;
	$armor_repair=0;
	$structure_repair=0;
	$leg_armor_repair=0;
	$leg_structure_repair=0;
	$locations=$chasisjd['Locations'];
	foreach($locations as $l) {
		if(DUMP::$info){
			echo "Location:".$l['Location'].PHP_EOL;
			if($l['MaxArmor']>0){
				echo "\tMaxArmor ".$l['MaxArmor'].PHP_EOL;
			}
			if($l['MaxRearArmor']>0){
				echo "\tMaxRearArmor ".$l['MaxRearArmor'].PHP_EOL;
			}
			if($l['InternalStructure']>0){
				echo "\tInternalStructure ".$l['InternalStructure'].PHP_EOL;
		    }
			if($einfo[$l['Location'].".Armor_base"]>0){
				echo "\t.Armor_base ".$einfo[$l['Location'].".Armor_base"].PHP_EOL;
			}
			if($einfo[$l['Location'].".RearArmor_base"]>0){
				echo "\t.RearArmor_base ".$einfo[$l['Location'].".RearArmor_base"].PHP_EOL;
			}
			if($einfo[$l['Location'].".Structure_base"]>0){
				echo "\t.Structure_base ".$einfo[$l['Location'].".Structure_base"].PHP_EOL;
			}
			if($einfo[$l['Location'].".Custom.ActivatableComponent.Repair.Armor"]>0){
				echo "\t.Custom.ActivatableComponent.Repair.Armor ".$einfo[$l['Location'].".Custom.ActivatableComponent.Repair.Armor"].PHP_EOL;
			}
			if($einfo[$l['Location'].".Custom.ActivatableComponent.Repair.InnerStructure"]>0){
				echo "\t.Custom.ActivatableComponent.Repair.InnerStructure ".$einfo[$l['Location'].".Custom.ActivatableComponent.Repair.InnerStructure"].PHP_EOL;
			}
		}
		if($l['MaxArmor']>0){
			$armor+=$l['MaxArmor'];
			if(endswith($l['Location'],"Leg"))
				$leg_armor+=$l['MaxArmor'];
		}
		if($l['MaxRearArmor']>0){
			$armor+=$l['MaxRearArmor'];
			if(endswith($l['Location'],"Leg"))
				$leg_armor+=$l['MaxRearArmor'];
		}
		if($l['InternalStructure']>0){
			$structure+=$l['InternalStructure'];
			if(endswith($l['Location'],"Leg"))
				$leg_structure+=$l['InternalStructure'];
		}
		if($einfo[$l['Location'].".Armor_base"]>0){
			$armor+=$einfo[$l['Location'].".Armor_base"];
			if(endswith($l['Location'],"Leg"))
				$leg_armor+=$einfo[$l['Location'].".Armor_base"];
		}
		if($einfo[$l['Location'].".RearArmor_base"]>0){
			$armor+=$einfo[$l['Location'].".RearArmor_base"];
			if(endswith($l['Location'],"Leg"))
				$leg_armor+=$einfo[$l['Location'].".RearArmor_base"];
		}
		if($einfo[$l['Location'].".Structure_base"]>0){
			$structure+=$einfo[$l['Location'].".Structure_base"];
			if(endswith($l['Location'],"Leg"))
				$leg_structure+=$einfo[$l['Location'].".Structure_base"];
		}
		
		if($einfo[$l['Location'].".Custom.ActivatableComponent.Repair.Armor"]>0){
			$armor_repair+=$einfo[$l['Location'].".Custom.ActivatableComponent.Repair.Armor"];
			if(endswith($l['Location'],"Leg"))
				$leg_armor_repair+=$einfo[$l['Location'].".Custom.ActivatableComponent.Repair.Armor"];
		}
		if($einfo[$l['Location'].".Custom.ActivatableComponent.Repair.InnerStructure"]>0){
			$structure_repair+=$einfo[$l['Location'].".Custom.ActivatableComponent.Repair.InnerStructure"];
			if(endswith($l['Location'],"Leg"))
				$leg_structure_repair+=$einfo[$l['Location'].".Custom.ActivatableComponent.Repair.InnerStructure"];
		}
	}
	if(DUMP::$info){
			echo "armor: $armor leg_armor: $leg_armor structure: $structure leg_structure=$leg_structure".PHP_EOL;
			echo "armor_repair: $armor_repair leg_armor_repair: $leg_armor_repair structure_repair: $structure_repair leg_structure_repair=$leg_structure_repair".PHP_EOL;
	}
	
}
public static function getPhysicalInfo($einfo,$tonnage,&$ChargeAttackerDamage,&$ChargeTargetDamage,&$ChargeAttackerInstability,&$ChargeTargetInstability,&$DFAAttackerDamage,&$DFATargetDamage,&$DFAAttackerInstability,&$DFATargetInstability,&$KickDamage,&$KickInstability,&$PhysicalWeaponDamage,&$PhysicalWeaponInstability,&$PunchDamage,&$PunchInstability){

	//Watch https://github.com/BattletechModders/CBTBehaviorsEnhanced/commits/master/CBTBehaviorsEnhanced/CBTBehaviorsEnhanced/Extensions/MechExtensions.cs

	//derived from RTdumper avg stats for tonnage 58.x avg move ~5.1
	$avg_tonnage=60;
	$avg_hexesMoved=5;

	//Calculations from MechExtensions.cs in CBTBehaviorsEnhanced
	$modjson=json_for_pk(JSONType::MODJSON,"CBTBehaviorsEnhanced");

	//$ChargeAttackerDamage
	$AttackerDamagePerTargetTon=$modjson['Settings']['Melee']['Charge']['AttackerDamagePerTargetTon'];
	$CBTBE_Charge_Attacker_Damage_Mod=$einfo['CBTBE_Charge_Attacker_Damage_Mod_base'] + $einfo['CBTBE_Charge_Attacker_Damage_Mod_activated'];
	$CBTBE_Charge_Attacker_Damage_Multi=$einfo['CBTBE_Charge_Attacker_Damage_Multi_base'] * $einfo['CBTBE_Charge_Attacker_Damage_Multi_activated'];
	$ChargeAttackerDamage=ceil ( (ceil($AttackerDamagePerTargetTon*$avg_tonnage)+$CBTBE_Charge_Attacker_Damage_Mod)*$CBTBE_Charge_Attacker_Damage_Multi );
	if(DUMP::$info){
			echo " (AttackerDamagePerTargetTon x targetTonnage ( $AttackerDamagePerTargetTon x $avg_tonnage ) + CBTBE_Charge_Attacker_Damage_Mod ($CBTBE_Charge_Attacker_Damage_Mod) )* CBTBE_Charge_Attacker_Damage_Multi ($CBTBE_Charge_Attacker_Damage_Multi)".PHP_EOL;
			echo " ChargeAttackerDamage=$ChargeAttackerDamage".PHP_EOL;
	}

	//$DFAAttackerDamage

	$AttackerDamagePerTargetTon=$modjson['Settings']['Melee']['DFA']['AttackerDamagePerTargetTon'];
	$CBTBE_DFA_Attacker_Damage_Mod=$einfo['CBTBE_DFA_Attacker_Damage_Mod_base'] + $einfo['CBTBE_DFA_Attacker_Damage_Mod_activated'];
	$CBTBE_DFA_Attacker_Damage_Multi=$einfo['CBTBE_DFA_Attacker_Damage_Multi_base'] * $einfo['CBTBE_DFA_Attacker_Damage_Multi_activated'];
	$DFAAttackerDamage=ceil ( (ceil($AttackerDamagePerTargetTon*$avg_tonnage)+$CBTBE_DFA_Attacker_Damage_Mod)*$CBTBE_DFA_Attacker_Damage_Multi );
	if(DUMP::$info){
			echo " (AttackerDamagePerTargetTon x targetTonnage ( $AttackerDamagePerTargetTon x $avg_tonnage ) + CBTBE_DFA_Attacker_Damage_Mod ($CBTBE_DFA_Attacker_Damage_Mod) )* CBTBE_DFA_Attacker_Damage_Multi ($CBTBE_DFA_Attacker_Damage_Multi)".PHP_EOL;
			echo " DFAAttackerDamage=$DFAAttackerDamage".PHP_EOL;
	}

	//$DFATargetDamage
	$TargetDamagePerAttackerTon=$modjson['Settings']['Melee']['DFA']['TargetDamagePerAttackerTon'];
	$CBTBE_DFA_Target_Damage_Mod=$einfo['CBTBE_DFA_Target_Damage_Mod_base'] + $einfo['CBTBE_DFA_Target_Damage_Mod_activated'];
	$CBTBE_DFA_Target_Damage_Multi=$einfo['CBTBE_DFA_Target_Damage_Multi_base'] * $einfo['CBTBE_DFA_Target_Damage_Multi_activated'];
	$DFATargetDamage=ceil ( (ceil($TargetDamagePerAttackerTon*$tonnage)+$CBTBE_DFA_Target_Damage_Mod)*$CBTBE_DFA_Target_Damage_Multi );
	if(DUMP::$info){
			echo " (TargetDamagePerAttackerTon x tonnage ( $TargetDamagePerAttackerTon x $tonnage ) + CBTBE_DFA_Target_Damage_Mod ($CBTBE_DFA_Target_Damage_Mod) )* CBTBE_DFA_Target_Damage_Multi ($CBTBE_DFA_Target_Damage_Multi)".PHP_EOL;
			echo " DFATargetDamage=$DFATargetDamage".PHP_EOL;
	}

	//$ChargeTargetDamage
	
	$TargetDamagePerAttackerTon=$modjson['Settings']['Melee']['Charge']['TargetDamagePerAttackerTon'];
	$CBTBE_Charge_Target_Damage_Mod=$einfo['CBTBE_Charge_Target_Damage_Mod_base'] + $einfo['CBTBE_Charge_Target_Damage_Mod_activated'];
	$CBTBE_Charge_Target_Damage_Multi=$einfo['CBTBE_Charge_Target_Damage_Multi_base'] * $einfo['CBTBE_Charge_Target_Damage_Multi_activated'];
	$ChargeTargetDamage=ceil ( (ceil($TargetDamagePerAttackerTon*$tonnage*$avg_hexesMoved)+$CBTBE_Charge_Target_Damage_Mod)*$CBTBE_Charge_Target_Damage_Multi );
	if(DUMP::$info){
			echo " (TargetDamagePerAttackerTon x tonnage x hexesMoved ( $TargetDamagePerAttackerTon x $tonnage x $avg_hexesMoved ) + CBTBE_Charge_Target_Damage_Mod ($CBTBE_Charge_Target_Damage_Mod) )* CBTBE_Charge_Target_Damage_Multi ($CBTBE_Charge_Target_Damage_Multi)".PHP_EOL;
			echo " ChargeTargetDamage=$ChargeTargetDamage".PHP_EOL;
	}

	//$ChargeAttackerInstability

	$AttackerInstabilityPerTargetTon=$modjson['Settings']['Melee']['Charge']['AttackerInstabilityPerTargetTon'];
	$CBTBE_Charge_Attacker_Instability_Mod=$einfo['CBTBE_Charge_Attacker_Instability_Mod_base'] + $einfo['CBTBE_Charge_Attacker_Instability_Mod_activated'];
	$CBTBE_Charge_Attacker_Instability_Multi=$einfo['CBTBE_Charge_Attacker_Instability_Multi_base'] * $einfo['CBTBE_Charge_Attacker_Instability_Multi_activated'];
	$ChargeAttackerInstability=ceil ( (ceil($AttackerInstabilityPerTargetTon*$avg_tonnage*$avg_hexesMoved)+$CBTBE_Charge_Attacker_Instability_Mod)*$CBTBE_Charge_Attacker_Instability_Multi );
	if(DUMP::$info){
			echo " (AttackerInstabilityPerTargetTon x targetTonnage x hexesMoved ( $AttackerInstabilityPerTargetTon x $avg_tonnage x $avg_hexesMoved ) + CBTBE_Charge_Attacker_Instability_Mod ($CBTBE_Charge_Attacker_Instability_Mod) )* CBTBE_Charge_Attacker_Instability_Multi ($CBTBE_Charge_Attacker_Instability_Multi)".PHP_EOL;
			echo " ChargeAttackerInstability=$ChargeAttackerInstability".PHP_EOL;
	}

	//$ChargeTargetInstability

	$TargetInstabilityPerAttackerTon=$modjson['Settings']['Melee']['Charge']['TargetInstabilityPerAttackerTon'];
	$CBTBE_Charge_Target_Instability_Mod=$einfo['CBTBE_Charge_Target_Instability_Mod_base'] + $einfo['CBTBE_Charge_Target_Instability_Mod_activated'];
	$CBTBE_Charge_Target_Instability_Multi=$einfo['CBTBE_Charge_Target_Instability_Multi_base'] * $einfo['CBTBE_Charge_Target_Instability_Multi_activated'];
	$ChargeTargetInstability=ceil ( (ceil($TargetInstabilityPerAttackerTon*$tonnage*$avg_hexesMoved)+$CBTBE_Charge_Target_Instability_Mod)*$CBTBE_Charge_Target_Instability_Multi );
	if(DUMP::$info){
			echo " (TargetInstabilityPerAttackerTon x tonnage x hexesMoved ( $TargetInstabilityPerAttackerTon x $tonnage x $avg_hexesMoved ) + CBTBE_Charge_Target_Instability_Mod ($CBTBE_Charge_Target_Instability_Mod) )* CBTBE_Charge_Target_Instability_Multi ($CBTBE_Charge_Target_Instability_Multi)".PHP_EOL;
			echo " ChargeTargetInstability=$ChargeTargetInstability".PHP_EOL;
	}

	//$DFAAttackerInstability
	
	$AttackerInstabilityPerTargetTon=$modjson['Settings']['Melee']['DFA']['AttackerInstabilityPerTargetTon'];
	$CBTBE_DFA_Attacker_Instability_Mod=$einfo['CBTBE_DFA_Attacker_Instability_Mod_base'] + $einfo['CBTBE_DFA_Attacker_Instability_Mod_activated'];
	$CBTBE_DFA_Attacker_Instability_Multi=$einfo['CBTBE_DFA_Attacker_Instability_Multi_base'] * $einfo['CBTBE_DFA_Attacker_Instability_Multi_activated'];
	$DFAAttackerInstability=ceil ( (ceil($AttackerInstabilityPerTargetTon*$avg_tonnage)+$CBTBE_DFA_Attacker_Instability_Mod)*$CBTBE_DFA_Attacker_Instability_Multi );
	if(DUMP::$info){
			echo " (AttackerInstabilityPerTargetTon x targetTonnage ( $AttackerInstabilityPerTargetTon x $avg_tonnage ) + CBTBE_DFA_Attacker_Instability_Mod ($CBTBE_DFA_Attacker_Instability_Mod) )* CBTBE_DFA_Attacker_Instability_Multi ($CBTBE_DFA_Attacker_Instability_Multi)".PHP_EOL;
			echo " DFAAttackerInstability=$DFAAttackerInstability".PHP_EOL;
	}

	//$DFATargetInstability

	$TargetInstabilityPerAttackerTon=$modjson['Settings']['Melee']['DFA']['TargetInstabilityPerAttackerTon'];
	$CBTBE_DFA_Target_Instability_Mod=$einfo['CBTBE_DFA_Target_Instability_Mod_base'] + $einfo['CBTBE_DFA_Target_Instability_Mod_activated'];
	$CBTBE_DFA_Target_Instability_Multi=$einfo['CBTBE_DFA_Target_Instability_Multi_base'] * $einfo['CBTBE_DFA_Target_Instability_Multi_activated'];
	$DFATargetInstability=ceil ( (ceil($TargetInstabilityPerAttackerTon*$tonnage)+$CBTBE_DFA_Attacker_Instability_Mod)*$CBTBE_DFA_Target_Instability_Multi );
	if(DUMP::$info){
			echo " (TargetInstabilityPerAttackerTon x tonnage ( $TargetInstabilityPerAttackerTon x $tonnage ) + CBTBE_DFA_Target_Instability_Mod ($CBTBE_DFA_Target_Instability_Mod) )* CBTBE_DFA_Target_Instability_Multi ($CBTBE_DFA_Target_Instability_Multi)".PHP_EOL;
			echo " DFATargetInstability=$DFATargetInstability".PHP_EOL;
	}
	
	//$KickDamage
	$TargetDamagePerAttackerTon=$modjson['Settings']['Melee']['Kick']['TargetDamagePerAttackerTon'];
	$CBTBE_Kick_Target_Damage_Mod=$einfo['CBTBE_Kick_Target_Damage_Mod_base'] + $einfo['CBTBE_Kick_Target_Damage_Mod_activated'];
	$CBTBE_Kick_Target_Damage_Multi=$einfo['CBTBE_Kick_Target_Damage_Multi_base'] * $einfo['CBTBE_Kick_Target_Damage_Multi_activated'];
	//LegActuatorDamageReduction does not apply as we are calculating based on undamaged mech
	$KickDamage=ceil ( (ceil($TargetDamagePerAttackerTon*$tonnage)+$CBTBE_Kick_Target_Damage_Mod)*$CBTBE_Kick_Target_Damage_Multi );
	if($einfo['CBTBE_Kick_Extra_Hits_Count'] && !$modjson['Settings']['Melee']['ExtraHitsAverageAllDamage'])
		$KickDamage=$KickDamage*(1+$einfo['CBTBE_Kick_Extra_Hits_Count']);
	if(DUMP::$info){
			echo " (TargetDamagePerAttackerTon x tonnage ( $TargetDamagePerAttackerTon x $tonnage ) + CBTBE_Kick_Target_Damage_Mod ($CBTBE_Kick_Target_Damage_Mod) )* CBTBE_Kick_Target_Damage_Multi ($CBTBE_Kick_Target_Damage_Multi)".PHP_EOL;
			if($einfo['CBTBE_Kick_Extra_Hits_Count'] && !$modjson['Settings']['Melee']['ExtraHitsAverageAllDamage'])
				echo " x 1+CBTBE_Kick_Extra_Hits_Count x".(1+$einfo['CBTBE_Kick_Extra_Hits_Count']);
			echo " KickDamage=$KickDamage".PHP_EOL;
	}

	//$KickInstability
	$TargetInstabilityPerAttackerTon=$modjson['Settings']['Melee']['Kick']['TargetInstabilityPerAttackerTon'];
	$CBTBE_Kick_Target_Instability_Mod=$einfo['CBTBE_Kick_Target_Instability_Mod_base'] + $einfo['CBTBE_Kick_Target_Instability_Mod_activated'];
	$CBTBE_Kick_Target_Instability_Multi=$einfo['CBTBE_Kick_Target_Instability_Multi_base'] * $einfo['CBTBE_Kick_Target_Instability_Multi_activated'];
	$KickInstability=ceil ( (ceil($AttackerInstabilityPerTargetTon*$tonnage)+$CBTBE_Kick_Target_Instability_Mod)*$CBTBE_Kick_Target_Instability_Multi );
	//LegActuatorDamageReduction does not apply as we are calculating based on undamaged mech
	if(DUMP::$info){
			echo " (TargetInstabilityPerAttackerTon x tonnage ( $TargetInstabilityPerAttackerTon x $tonnage ) + CBTBE_Kick_Target_Instability_Mod ($CBTBE_Kick_Target_Instability_Mod) )* CBTBE_Kick_Target_Instability_Multi ($CBTBE_Kick_Target_Instability_Multi)".PHP_EOL;
			echo " KickInstability=$KickInstability".PHP_EOL;
	}


	//$PhysicalWeaponDamage
	$DamagePerAttackerTon=$einfo["CBTBE_Physical_Weapon_Target_Damage_Per_Attacker_Ton_base"]+$einfo["CBTBE_Physical_Weapon_Target_Damage_Per_Attacker_Ton_activated"];
	if($DamagePerAttackerTon<=0)
	 $DamagePerAttackerTon=$modjson['Settings']['Melee']['PhysicalWeapon']['DefaultDamagePerAttackerTon'];
	$CBTBE_Physical_Weapon_Target_Damage_Mod=$einfo['CBTBE_Physical_Weapon_Target_Damage_Mod_base'] + $einfo['CBTBE_Physical_Weapon_Target_Damage_Mod_activated'];
	$CBTBE_Physical_Weapon_Target_Damage_Multi=$einfo['CBTBE_Physical_Weapon_Target_Damage_Multi_base'] * $einfo['CBTBE_Physical_Weapon_Target_Damage_Multi_activated'];
	$PhysicalWeaponDamage=ceil ( (ceil($DamagePerAttackerTon*$tonnage)+$CBTBE_Physical_Weapon_Target_Damage_Mod)*$CBTBE_Physical_Weapon_Target_Damage_Multi );
	if($einfo['CBTBE_Physical_Weapon_Extra_Hits_Count'] && !$modjson['Settings']['Melee']['ExtraHitsAverageAllDamage'])
		$PhysicalWeaponDamage=$PhysicalWeaponDamage*(1+$einfo['CBTBE_Physical_Weapon_Extra_Hits_Count']);
	if(DUMP::$info){
			echo " (CBTBE_Physical_Weapon_Target_Damage_Per_Attacker_Ton/DefaultDamagePerAttackerTon x tonnage ( $DamagePerAttackerTon x $tonnage ) + CBTBE_Physical_Weapon_Target_Damage_Mod ($CBTBE_Physical_Weapon_Target_Damage_Mod) )* CBTBE_Physical_Weapon_Target_Damage_Multi ($CBTBE_Physical_Weapon_Target_Damage_Multi)".PHP_EOL;
			if($einfo['CBTBE_Physical_Weapon_Extra_Hits_Count'] && !$modjson['Settings']['Melee']['ExtraHitsAverageAllDamage'])
				echo " x 1+CBTBE_Physical_Weapon_Extra_Hits_Count x ".(1+$einfo['CBTBE_Physical_Weapon_Extra_Hits_Count']);
			echo " PhysicalWeaponDamage=$PhysicalWeaponDamage".PHP_EOL;
	}

	//$PhysicalWeaponInstability
	$InstabilityPerAttackerTon=$einfo["CBTBE_Physical_Weapon_Target_Instability_Per_Attacker_Ton_base"]+$einfo["CBTBE_Physical_Weapon_Target_Instability_Per_Attacker_Ton_activated"];
	if($InstabilityPerAttackerTon<=0)
	 $InstabilityPerAttackerTon=$modjson['Settings']['Melee']['PhysicalWeapon']['DefaultInstabilityPerAttackerTon'];
	$CBTBE_Physical_Weapon_Target_Instability_Mod=$einfo['CBTBE_Physical_Weapon_Target_Instability_Mod_base'] + $einfo['CBTBE_Physical_Weapon_Target_Instability_Mod_activated'];
	$CBTBE_Physical_Weapon_Target_Instability_Multi=$einfo['CBTBE_Physical_Weapon_Target_Instability_Multi_base'] * $einfo['CBTBE_Physical_Weapon_Target_Instability_Multi_activated'];
	$PhysicalWeaponInstability=ceil ( (ceil($InstabilityPerAttackerTon*$tonnage)+$CBTBE_Physical_Weapon_Target_Instability_Mod)*$CBTBE_Physical_Weapon_Target_Instability_Multi );
	//LegActuatorDamageReduction does not apply as we are calculating based on undamaged mech
	if(DUMP::$info){
			echo " (CBTBE_Physical_Weapon_Target_Instability_Per_Attacker_Ton/DefaultInstabilityPerAttackerTon x tonnage ( $InstabilityPerAttackerTon x $tonnage ) + CBTBE_Physical_Weapon_Target_Instability_Mod ($CBTBE_Physical_Weapon_Target_Instability_Mod) )* CBTBE_Physical_Weapon_Target_Instability_Multi ($CBTBE_Physical_Weapon_Target_Instability_Multi)".PHP_EOL;
			echo " PhysicalWeaponInstability=$PhysicalWeaponInstability".PHP_EOL;
	}

		
	//$PunchDamage
	$DamagePerAttackerTon=$einfo["CBTBE_Punch_Target_Damage_Per_Attacker_Ton_base"]+$einfo["CBTBE_Punch_Target_Damage_Per_Attacker_Ton_activated"];
	if($DamagePerAttackerTon<=0)
	 $DamagePerAttackerTon=$modjson['Settings']['Melee']['Punch']['TargetDamagePerAttackerTon'];
	$TargetDamagePerAttackerTon=$modjson['Settings']['Melee']['Kick']['CBTBE_Punch_Target_Damage_Per_Attacker_Ton'];
	$CBTBE_Punch_Target_Damage_Mod=$einfo['CBTBE_Punch_Target_Damage_Mod_base'] + $einfo['CBTBE_Punch_Target_Damage_Mod_activated'];
	$CBTBE_Punch_Target_Damage_Multi=$einfo['CBTBE_Punch_Target_Damage_Multi_base'] * $einfo['CBTBE_Punch_Target_Damage_Multi_activated'];
	//ArmActuatorDamageReduction does not apply as we are calculating based on undamaged mech
	$PunchDamage=ceil ( (ceil($DamagePerAttackerTon*$tonnage)+$CBTBE_Punch_Target_Damage_Mod)*$CBTBE_Punch_Target_Damage_Multi );
	if($einfo['CBTBE_Punch_Extra_Hits_Count'] && !$modjson['Settings']['Melee']['ExtraHitsAverageAllDamage'])
		$PunchDamage=$PunchDamage*(1+$einfo['CBTBE_Kick_Extra_Hits_Count']);
	if(DUMP::$info){
			echo " (DamagePerAttackerTon x tonnage ( $DamagePerAttackerTon x $tonnage ) + CBTBE_Punch_Target_Damage_Mod ($CBTBE_Punch_Target_Damage_Mod) )* CBTBE_Punch_Target_Damage_Multi ($CBTBE_Punch_Target_Damage_Multi)".PHP_EOL;
			if($einfo['CBTBE_Punch_Extra_Hits_Count'] && !$modjson['Settings']['Melee']['ExtraHitsAverageAllDamage'])
				echo " x 1+CBTBE_Punch_Extra_Hits_Count x".(1+$einfo['CBTBE_Punch_Extra_Hits_Count']);
			echo " PunchDamage=$PunchDamage".PHP_EOL;
	}

	//$PunchInstability
	$TargetInstabilityPerAttackerTon=$einfo["CBTBE_Punch_Target_Instability_Per_Attacker_Ton_base"]+$einfo["CBTBE_Punch_Target_Instability_Per_Attacker_Ton_activated"];
	if($TargetInstabilityPerAttackerTon<=0)
		$TargetInstabilityPerAttackerTon=$modjson['Settings']['Melee']['Punch']['TargetInstabilityPerAttackerTon'];
	$CBTBE_Punch_Target_Instability_Mod=$einfo['CBTBE_Punch_Target_Instability_Mod_base'] + $einfo['CBTBE_Punch_Target_Instability_Mod_activated'];
	$CBTBE_Punch_Target_Instability_Multi=$einfo['CBTBE_Punch_Target_Instability_Multi_base'] * $einfo['CBTBE_Punch_Target_Instability_Multi_activated'];
	$PunchInstability=ceil ( (ceil($TargetInstabilityPerAttackerTon*$tonnage)+$CBTBE_Punch_Target_Instability_Mod)*$CBTBE_Punch_Target_Instability_Multi );
	//ArmActuatorDamageReduction does not apply as we are calculating based on undamaged mech
	if(DUMP::$info){
			echo " (CBTBE_Punch_Target_Instability_Per_Attacker_Ton/TargetInstabilityPerAttackerTon x tonnage ( $TargetInstabilityPerAttackerTon x $tonnage ) + CBTBE_Punch_Target_Instability_Mod ($CBTBE_Punch_Target_Instability_Mod) )* CBTBE_Punch_Target_Instability_Multi ($CBTBE_Punch_Target_Instability_Multi)".PHP_EOL;
			echo " PunchInstability=$PunchInstability".PHP_EOL;
	}
	

}


public static function getHeatInfo($einfo,$engine_rating,$tonnage,&$dissipation_capacity_base,&$dissipation_capacity_activated,&$heat_generated,&$jump_heat_base,&$jump_heat_activated,&$heat_efficency){
		$internal_hs=(int)($engine_rating/25);
		if($internal_hs>10)
		 $internal_hs=10;
		$internal_hs+= $einfo[".Custom.EngineHeatBlock.HeatSinkCount"];
		$heatsinkjd=json_for_pk(JSONType::COMPONENT, $einfo[".Custom.Cooling.HeatSinkDefId"]);
		$per_heatsink_dissipation=$heatsinkjd["DissipationCapacity"];
		$dissipation_capacity_base=($internal_hs * $per_heatsink_dissipation)*(1+$einfo["heatSinkMultiplier_base"])+$einfo[".DissipationCapacity"]+$einfo["HeatSinkCapacity_base"]-$einfo["EndMoveHeat_base"];
		$dissipation_capacity_activated=($internal_hs * $per_heatsink_dissipation)*(1+$einfo["heatSinkMultiplier_base"]+$einfo["heatSinkMultiplier_activated"])+$einfo[".DissipationCapacity"]-$einfo["HeatSinkCapacity_base"]-$einfo["EndMoveHeat_base"]-$einfo["EndMoveHeat_activated"]+$einfo["HeatSinkCapacity_activated"];
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
					}else if (startswith($ekey,"Weapon.|") && endswith($ekey,"|.WeaponHeatMultiplier_activated") ){
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
		$jump_heat_base=0+$einfo["JumpHeat_base"];
		$jump_heat_activated=$einfo["JumpHeat_base"]+$einfo["JumpHeat_activated"];
		if(DUMP::$info){
			 echo "DissipationCapacity Internal[ $internal_hs x $per_heatsink_dissipation x ".(1+$einfo["heatSinkMultiplier_base"])." ] + External[".$einfo[".DissipationCapacity"]."] + HeatSinkCapacity[".+$einfo["HeatSinkCapacity_base"]."] -EndMoveHeat =[".$einfo["EndMoveHeat_base"]."] = ".$dissipation_capacity_base.PHP_EOL;
			 echo "Activated EndMoveHeat = ".$einfo["EndMoveHeat_activated"]." |Activatable Dissipation =".$einfo["HeatSinkCapacity_activated"]." |Activatable heatSinkMultiplier =".$einfo["heatSinkMultiplier_activated"].PHP_EOL;
			 echo "Total Heat Generated (weapons) = $heat_generated".PHP_EOL;
		}

		$heat_efficency=($dissipation_capacity_activated-$heat_generated)/$dissipation_capacity_activated*100;
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
		if(endswith_i($item["ComponentDefID"],"ReportMe"))
		{
			$einfo[".Custom.EngineCore.Rating"]=null;
			throw new Exception("Component caused mech to be ignored ".$item["ComponentDefID"]);
		}
		$location="ALL";
		if($item["MountedLocation"])
		  $location=$item["MountedLocation"];


		$componentjd=json_for_pk(JSONType::COMPONENT, $item["ComponentDefID"]);
		if(DUMP::$info)
			echo PHP_EOL."||".$item["ComponentDefID"]." ===========================> ".PHP_EOL.json_encode($componentjd,JSON_PRETTY_PRINT).PHP_EOL.PHP_EOL;
		
		//engine rating
		$enginejd=json_for_pk(JSONType::ENGINE, $item["ComponentDefID"]);
		if($enginejd){
			$einfo[".Custom.EngineCore.Rating"]=$enginejd["Custom"]["EngineCore"]["Rating"];
			if(DUMP::$info)
				echo "EINFO[.Custom.EngineCore.Rating ] : ".$einfo[".Custom.EngineCore.Rating"].PHP_EOL;
		}

		//CASE 
		if($componentjd["Custom"] && $componentjd["Custom"]["CASE"] && $componentjd["Custom"]["CASE"]["MaximumDamage"]){
			$einfo[".Custom.CASE.MaximumDamage"]= (int)$componentjd["Custom"]["CASE"]["MaximumDamage"];
			if(DUMP::$info)
				echo "EINFO[.Custom.CASE.MaximumDamage ] : ".$einfo[".Custom.CASE.MaximumDamage"].PHP_EOL;
		}
		
		//AMSSINGLE_HeatGenerated && AMSMULTI_HeatGenerated
		if($componentjd["PrefabIdentifier"]=="AMS"){
		 //echo "{}".$item["ComponentDefID"].PHP_EOL;
		 if($componentjd["IsAAMS"]==true && $componentjd["HeatGenerated"] && $componentjd["HeatGenerated"]>$einfo["AMSMULTI_HeatGenerated"])
		 {
			$einfo["AMSMULTI_HeatGenerated"]= $componentjd["HeatGenerated"];
		 }elseif($componentjd["IsAAMS"]==true && $componentjd["HeatGenerated"] && $componentjd["HeatGenerated"]>$einfo["AMSSINGLE_HeatGenerated"]){
			$einfo["AMSSINGLE_HeatGenerated"]=$mode["HeatGenerated"];
		 }
		 if(is_array($componentjd["Modes"])){
			 foreach($componentjd["Modes"] as $mode)
			 {
		 		 if(($mode["IsAAMS"]==true || $componentjd["IsAAMS"]==true) && $mode["HeatGenerated"]>$einfo["AMSMULTI_HeatGenerated"])
				 {
					$einfo["AMSMULTI_HeatGenerated"]= $mode["HeatGenerated"];
				 }elseif(($mode["IsAAMS"]==true || $componentjd["IsAAMS"]==true) && $mode["HeatGenerated"]>$einfo["AMSSINGLE_HeatGenerated"]){
					$einfo["AMSSINGLE_HeatGenerated"]=$mode["HeatGenerated"];
				 }
			 }
		 }

		if(DUMP::$info)
			echo "EINFO[AMSMULTI_HeatGenerated ] : ".$einfo["AMSMULTI_HeatGenerated"].PHP_EOL;
		if(DUMP::$info)
			echo "EINFO[AMSSINGLE_HeatGenerated ] : ".$einfo["AMSSINGLE_HeatGenerated"].PHP_EOL;
		}

		//CBTBE_AmmoBoxExplosionDamage && CBTBE_VolatileAmmoBoxExplosionDamage
		//this differs from CBTBE as it measures total explosion+heat+structure. We only take explosion damage
		if($componentjd["Custom"] && $componentjd["Custom"]["ComponentExplosion"] && $componentjd["Capacity"]){
			//ignore StabilityDamagePerAmmo as its non fatal
			//ignore HeatDamagePerAmmo as it adds heat and not damage. "CriticalHeatPerLocationLight" etc are set to 0 in RT
			$d=$componentjd["Custom"]["ComponentExplosion"]["ExplosionDamagePerAmmo"]*$componentjd["Capacity"];
			if($componentjd["Custom"] && $componentjd["Custom"]["VolatileAmmo"]){
				 if($componentjd["Custom"]["VolatileAmmo"]["damageWeighting"])
					$d*=$componentjd["Custom"]["VolatileAmmo"]["damageWeighting"];
				 if($d>$einfo["CBTBE_VolatileAmmoBoxExplosionDamage"]){
					$einfo["CBTBE_VolatileAmmoBoxExplosionDamage"]= $d;
					 if(DUMP::$info)
						echo "EINFO[CBTBE_VolatileAmmoBoxExplosionDamage ] : ".$einfo["CBTBE_VolatileAmmoBoxExplosionDamage"].PHP_EOL;
				 }
			}
			if($d>$einfo["CBTBE_AmmoBoxExplosionDamage"]){
				$einfo["CBTBE_AmmoBoxExplosionDamage"]= $d;
				if(DUMP::$info)
					echo "EINFO[CBTBE_AmmoBoxExplosionDamage ] : ".$einfo["CBTBE_AmmoBoxExplosionDamage"].PHP_EOL;
			}
		}

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
		if($componentjd["JumpCapacity"])
		{
			$einfo[".JumpCapacity"]=$einfo[".JumpCapacity"]+(float)$componentjd["JumpCapacity"];
			if(DUMP::$info)
				echo "EINFO[.JumpCapacity ] : ".$einfo[".JumpCapacity"].PHP_EOL;
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

		if($componentjd["Custom"] && $componentjd["Custom"]["ActivatableComponent"] && $componentjd["Custom"]["ActivatableComponent"]["AutoActivateOnHeat"]){
			if($componentjd["Custom"]["ActivatableComponent"]["AutoActivateOnHeat"]>$einfo[".Custom.ActivatableComponent.AutoActivateOnHeat"])
				$einfo[".Custom.ActivatableComponent.AutoActivateOnHeat"]=(int) $componentjd["Custom"]["ActivatableComponent"]["AutoActivateOnHeat"];
			if(DUMP::$info)
				echo "EINFO[.Custom.ActivatableComponent.AutoActivateOnHeat ] : ".$einfo[".Custom.ActivatableComponent.AutoActivateOnHeat"].PHP_EOL;
		}

		//Repair
		if($componentjd["Custom"] && $componentjd["Custom"]["ActivatableComponent"] && $componentjd["Custom"]["ActivatableComponent"]["Repair"] && !$componentjd["Custom"]["ActivatableComponent"]["Repair"]["AffectInstalledLocation"]){
			//TODO
			echo "|WARNING| Repair non installation location not handled.".PHP_EOL;
			echo "||". $item["ComponentDefID"].PHP_EOL.json_encode($componentjd["Custom"]["ActivatableComponent"]["Repair"],JSON_PRETTY_PRINT).PHP_EOL;
		}

		if($componentjd["Custom"] && $componentjd["Custom"]["ActivatableComponent"] && $componentjd["Custom"]["ActivatableComponent"]["Repair"] && $componentjd["Custom"]["ActivatableComponent"]["Repair"]["AffectInstalledLocation"]){
			if($componentjd["Custom"]["ActivatableComponent"]["Repair"]["InnerStructure"]>0){
				$einfo[$location.".Custom.ActivatableComponent.Repair.InnerStructure"]+=$componentjd["Custom"]["ActivatableComponent"]["Repair"]["InnerStructure"];
				if($componentjd["Custom"]["ActivatableComponent"]["Repair"]["TurnsSinceDamage"]>0)
					$einfo[$location.".Custom.ActivatableComponent.Repair.InnerStructure"]*=$componentjd["Custom"]["ActivatableComponent"]["Repair"]["TurnsSinceDamage"];
				if(DUMP::$info)
					echo "EINFO[".$location.".Custom.ActivatableComponent.Repair.InnerStructure ] : ".$einfo[$location.".Custom.ActivatableComponent.Repair.InnerStructure"].PHP_EOL;
			}
			if($componentjd["Custom"]["ActivatableComponent"]["Repair"]["Armor"]>0){
				$einfo[$location.".Custom.ActivatableComponent.Repair.Armor"]+=$componentjd["Custom"]["ActivatableComponent"]["Repair"]["Armor"];
				if($componentjd["Custom"]["ActivatableComponent"]["Repair"]["TurnsSinceDamage"]>0)
					$einfo[$location.".Custom.ActivatableComponent.Repair.Armor"]*=$componentjd["Custom"]["ActivatableComponent"]["Repair"]["TurnsSinceDamage"];
				if(DUMP::$info)
					echo "EINFO[".$location.".Custom.ActivatableComponent.Repair.Armor ] : ".$einfo[$location.".Custom.ActivatableComponent.Repair.Armor"].PHP_EOL;
			}
		}

		if($componentjd["Custom"] && $componentjd["Custom"]["ActivatableComponent"] && $componentjd["Custom"]["ActivatableComponent"]["statusEffects"]){
			foreach($componentjd["Custom"]["ActivatableComponent"]["statusEffects"]  as $effectjd){
				$force_activated=true;
				if($componentjd["Custom"]["ActivatableComponent"]["ActiveByDefault"]===TRUE)
				  $force_activated=false;
				Dump::gatherEquipmentEffectInfo($item["ComponentDefID"],$location,$effectjd,$einfo,$force_activated);
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
			echo" <===========================||".PHP_EOL;
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
					if($effect=="{location}.Armor"||$effect=="{location}.Structure")
						$effect = $effect."_Multi";//both multiplier and add / sub are used.
					$effectval = ($einfo[$effect.$duration] ? $einfo[$effect.$duration]:1)*(float)$effectjd[ "statisticData"]["modValue"];
					break;
				case "Set":
				    if ($effectjd[ "statisticData"]["modType"]=="System.Boolean") {
	                          if ($effectjd[ "statisticData"]["modValue"]=="true"){
								$effectval =1;
							  }else {
								$effectval =0;
                              }
                    } 
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
		if($effect!==null && $effectval!==null){
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