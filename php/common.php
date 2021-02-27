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

$csv_header=array("#MECH Id","Tons","Engine Rating",
	"Max Walk base (hex)","Max Walk activated (hex)","Max Run base (hex)","Max Run activated (hex)",
	"Max Jump base (hex)","Max Jump activated (hex)",
	"Heat Sinking base","Heat Sinking activated","Alpha Strike Heat","Jump Heat base","Jump Heat activated",
    "Max Ammo Explosion damage","Max Volatile Ammo Explosion damage",
    "AMS Single Heat","AMS Multi Heat",
	"Equipment",
	"path");

//these are processed to find mean/std dev
$csv_min_stat=1;
$csv_max_stat=17;
 
?>