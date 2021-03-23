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


$data_collect=array();

$stat_min=array();
$stat_max=array();
$stat_avg=array();
$stat_stddev_lt=array();
$stat_stddev_gt=array();

// Function to calculate square of value - mean
function sd_square($x, $mean) { return pow($x - $mean,2); }

// Function to calculate standard deviation (uses sd_square)   
function sd($array,$mean) {
   
	// square root of sum of squares devided by N-1
	return sqrt(array_sum(array_map("sd_square", $array, array_fill(0,count($array),$mean) ) ) / (count($array)-1) );
}

//don't reorder - used by aitag and dump
$csv_header=array("#MECH Id","Tons","Engine Rating",//x,1,2
	"Max Walk base (hex)","Max Walk activated (hex)","Max Run base (hex)","Max Run activated (hex)",//3,4,5,6
	"Max Jump base (hex)","Max Jump activated (hex)",//7,8
	"Heat Sinking base","Heat Sinking activated","Auto Activation Heat","Alpha Strike Heat","Jump Heat base","Jump Heat activated",//9,10,11,12,13,14
    "Max Ammo Explosion damage","Max Volatile Ammo Explosion damage",//15,16
    "AMS Single Heat","AMS Multi Heat",//17,18
    "Heat Damage Injury","Heat Efficency",//19,20
    "Charge Attacker Damage","Charge Target Damage","Charge Attacker Instability","Charge Target Instability",//21,22,23,24
	"DFA Attacker Damage","DFA Target Damage","DFA Attacker Instability","DFA Target Instability",//25,26,27,28
	"Kick Damage","Kick Instability",//29,30
	"Physical Weapon Damage","Physical Weapon Instability",//31,32
	"Punch Damage","Punch Instability",//33,34
    "Armor","Leg Armor","Structure","Leg Structure",//35,36,37,38
    "Repair Armor","Repair Leg Armor","Repair Structure","Repair Leg Structure",//39,40,41,42
    "DFA Self Damage Efficency","DFA Damage Efficency","DFA Self Instability Efficency",//43,44,45
    "UnsteadyThreshold",//46
    "Melee Damage Efficency",//47
    "DamageReductionMultiplierAll","DamageReductionMultiplierBallistic","DamageReductionMultiplierMissile","DamageReductionMultiplierEnergy","DamageReductionMultiplierMelee",//48,49,50,51,52
    "Max Evasive Pips",//53
     "LV_ADVANCED_SENSORS","LV_PROBE_CARRIER","LV_ECM_SHIELD","LV_ECM_JAMMED","LV_SHARES_VISION","LV_NIGHT_VISION","LV_PROBE_PING",//54,55,56,57,58,59,60
     "EnemiesWithinRange_LV_ECM_JAMMED","EnemiesWithinRange_LV_PROBE_PING","EnemiesWithinRange_LV_ECM_SHIELD",//61,62,63
     "AlliesWithinRange_LV_ECM_JAMMED","AlliesWithinRange_LV_ECM_SHIELD",//64,65
     "EnemiesWithinRange_SensorSignatureModifier","EnemiesWithinRange_SpottingVisibilityMultiplier","EnemiesWithinRange_MoraleBonusGain","EnemiesWithinRange_BaseInitiative","EnemiesWithinRange_PanicStatModifier",//66,67,68,69,70
     "AlliesWithinRange_SensorDistanceAbsolute","AlliesWithinRange_SpotterDistanceAbsolute",//71,72
     "SensorDistanceAbsolute","SensorSignatureModifier","SensorDistanceMultiplier",//73,74,75
     "SpottingVisibilityMultiplier",//76
     "LV_STEALTH_signature_modifier","LV_STEALTH_details_modifier","LV_STEALTH_mediumAttackMod","LV_STEALTH_longAttackmod","LV_STEALTH_extremeAttackMod",//77,78,79,80,81
     "LV_MIMETIC_maxCharges","LV_MIMETIC_visibilityModPerCharge","LV_MIMETIC_attackModPerCharge","LV_MIMETIC_hexesUntilDecay",//82,83,84,85
     //"LV_NARC_rating","LV_TAG_rating",//todo these must be on ammo CAC?
     "Equipment",
	"path");

//these are processed to find mean/std dev
$csv_min_stat=1;
$csv_max_stat=85;

//Heat Efficency is just spare heat dissipation after alpha strike expressed as % of dissipation capacity
//DFA Self Damage Efficency is how many a DFAs a mech can perform before both its legs break
//DFA Damage Efficency is DFA damage per mech tonnage
//DFA Self Instability Efficency is Self UnsteadyThreshold remaining after DFA expressed as % of UnsteadyThreshold
//Max Evasive Pips actually checks for appropriate max move/jump distances and derives evasion . before checking the stat of same name. (check if Max evasion is possible)


$stats_ignore_zeros=array(
    25,26,27,28,//"DFA Attacker Damage","DFA Target Damage","DFA Attacker Instability","DFA Target Instability", most mechs don't have Jump Jets, so data is highly skewed if including zeros
    43,44,45,// "DFA Self Damage Efficency","DFA Damage Efficency","DFA Self Instability Efficency" most mechs don't have Jump Jets, so data is highly skewed so data is highly skewed if including zeros
    31,32//"Physical Weapon Damage","Physical Weapon Instability" no physical weapon no physical damage
);

$ai_tags=array("ai_heat","ai_dfa","ai_melee");

$ai_tags_calc=array(
//ai_heat={R Max Ammo Explosion damage}  {R Max Volatile Ammo Explosion damage}  {R "AMS Single Heat"}  {R "AMS Multi Heat" }  {R Heat Damage Injury}  {R Heat Efficency } {R Auto Activation Heat}
    array(15,16,17,18,19,20,11),
//ai_dfa={R DFA Self Damage Efficency}  {R DFA Damage Efficency} {R DFA Self Instability Efficency} {R DFA Target Damage} {R DFA Target Instability}
    array(43,44,45,26,28),
//ai_melee {R KickDamage}{R PhysicalWeaponDamage}{R PunchDamage} {R Melee Damage Efficency}
    array(29,31,33,47),
);

$ai_tags_weights=array(
//ai_heat={R Max Ammo Explosion damage}  {R Max Volatile Ammo Explosion damage}  {R "AMS Single Heat"}  {R "AMS Multi Heat" }  {R Heat Damage Injury}  {R Heat Efficency } {R Auto Activation Heat}
    array(2.5,2.5,1,1,0.5,4,2.5),
//ai_dfa={R DFA Self Damage Efficency}  {R DFA Damage Efficency} {R DFA Self Instability Efficency} {R DFA Target Damage} {R DFA Target Instability}
    array(15,7,20,5,3),
//ai_melee {R KickDamage}{R PhysicalWeaponDamage}{R PunchDamage} {R Melee Damage Efficency}
    array(1,1,1,7),
);

//false means larger values better -> i.e.  on higher values i want ai_tag high
//true means smaller values better ->on lower values i want ai_tag high 
$ai_tags_reverserating=array(
//ai_heat={R Max Ammo Explosion damage}  {R Max Volatile Ammo Explosion damage}  {R "AMS Single Heat"}  {R "AMS Multi Heat" }  {R Heat Damage Injury}  {R Heat Efficency } {R Auto Activation Heat}
    array(true,true,true,true,true,true,false),//ai_heat
//ai_dfa={R DFA Self Damage Efficency}  {R DFA Damage Efficency} {R DFA Self Instability Efficency} {R DFA Target Damage} {R DFA Target Instability}
    array(false,false,false,false,false),//ai_dfa
//ai_melee {R KickDamage}{R PhysicalWeaponDamage}{R PunchDamage} {R Melee Damage Efficency}
     array(false,false,false),//ai_melee
);

//ignore ratings of 0 , for cases where there are a large number of them throwing the stat off.
//this doesn't set the specific ai_tag for these mechs. for this to work all statistics {R} used by tag should calc to zero and be set in $stats_ignore_zeros
$ai_tags_ignore_zeros=array(
    "ai_dfa"//most mechs don't have Jump Jets, so data is highly skewed, don't tag mechs without JJ
);

//The tags are generated from a rating number [0-1] where <=0.2 is low  >=0.8 is high, else normal
//skew allows the ratings to be adjusted up/down. [0-1]+skew , before tagging
//skew values of >0.2 and <-0.2 don't make sense - would cause high / low tags not to appear
$ai_tags_skew=array( 
  0,//ai_heat
  0,//ai_dfa
  0,//ai_melee
);


 
?>