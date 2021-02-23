<?php
include ".\php\common.php";

$data_collect=array();

$stat_min=array();
$stat_max=array();
$stat_avg=array();
$stat_stddev=array();

// Function to calculate square of value - mean
function sd_square($x, $mean) { return pow($x - $mean,2); }

// Function to calculate standard deviation (uses sd_square)   
function sd($array,$mean) {
   
	// square root of sum of squares devided by N-1
	return sqrt(array_sum(array_map("sd_square", $array, array_fill(0,count($array),$mean) ) ) / (count($array)-1) );
}
 
class DumpStats extends Config{
   public static function main(){
    DumpStats::init();
	DumpStats::processStats();
	//and dump what we need to csv
	DumpStats::dump();
   }

   public static function init(){
	   GLOBAL $data_collect,$csv_min_stat,$csv_max_stat;
	   for ($x = $csv_min_stat; $x <= $csv_max_stat; $x++) {
			$data_collect[$x]=array();
	   }
   }

     public static function processStats(){
	   GLOBAL $csv_header,$stat_min,$stat_max,$stat_avg,$stat_stddev,$data_collect,$csv_min_stat,$csv_max_stat;
	   $file = fopen('./Output/mechs.csv', 'r');
		while (($line = fgetcsv($file)) !== FALSE) {
		   if(!startswith($line[0],"#"))
		   {
			   for ($x = $csv_min_stat; $x <= $csv_max_stat; $x++) {
					$data_collect[$x][]=(float)$line[$x];
			   }	
			}
		}
		fclose($file);
		for ($x = $csv_min_stat; $x <= $csv_max_stat; $x++) {
				$stat_min[$x]=min($data_collect[$x]);
				$stat_max[$x]=max($data_collect[$x]);
				$stat_avg[$x]=(array_sum($data_collect[$x]) / count($data_collect[$x]));
				$stat_stddev[$x]=sd($data_collect[$x],$stat_avg[$x]);
				echo str_pad ( $csv_header[$x],25)." MIN: ".str_pad ( $stat_min[$x],8)." AVG: ".str_pad ( number_format($stat_avg[$x],2),8)." MAX: ".str_pad ( $stat_max[$x],8)."  STD DEV: ".str_pad ( number_format($stat_stddev[$x],2),8).PHP_EOL;
		}	
   }

   public static function dump(){
   }

}

DumpStats::main();
 
?>