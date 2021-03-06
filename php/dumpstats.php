<?php
include ".\php\common.php";
 
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
	   GLOBAL $csv_header,$stat_min,$stat_max,$stat_avg,$stat_stddev,$data_collect,$csv_min_stat,$csv_max_stat,$csv_header,$ai_tags,$ai_tags_calc,$ai_tags_weights,$ai_tags_reverserating;
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
		$file = fopen('./Output/mechs.csv', 'r');
		$fp = fopen('./Output/mechratings.csv', 'wb');
		$csv_header_r=array();
		$csv_header_r=$csv_header_r+ $csv_header;
		for($x=0; $x<count($ai_tags); $x++){
			$csv_header_r[]=$ai_tags[$x]." Value";
		}
		fputcsv($fp, $csv_header_r);
		while (($line = fgetcsv($file)) !== FALSE) {
		   if(startswith($line[0],"#"))
				continue;
			
			if(DumpStats::$debug_single_mech){
				if($line[0]==DumpStats::$debug_single_mech)
					DumpStats::$info=TRUE;
				else
					DumpStats::$info=FALSE;
			}

			$dump=$line;
			for ($x = $csv_min_stat; $x <= $csv_max_stat; $x++) {
					$data=(float)$line[$x];
					$avg=$stat_avg[$x];
					$max=$stat_max[$x];
					$min=$stat_min[$x];
					$maxsd=$avg+$stat_stddev[$x];
					if($maxsd>$max)
					  $maxsd=$max;
					$minsd=$avg-$stat_stddev[$x];
					if($minsd<$min)
					  $minsd=$min;

					//normalize all stats to 0-1 scale <0.2 & >0.8 are for statistical outliers <= & => avg+-standard deviation
					if($data<=$minsd){
					  if($minsd==$min)
						$dump[$x]=0;
					  else 
	                    $dump[$x]=($data-$min)/($minsd-$min)*0.2;
					}else if($data>$minsd && $data<$maxsd){
	                    $dump[$x]=0.2+(($data-$minsd)/($maxsd-$minsd)*0.6);
					}else if($data>=$maxsd){
					  if($maxsd==$max)
						$dump[$x]=1;
					  else 
	                    $dump[$x]=0.8+(($data-$maxsd)/($max-$maxsd)*0.2);
					}

			}	
			for ($x = 0; $x < count($ai_tags); $x++) {
				$t=0;
				for ($y = 0; $y < count($ai_tags_calc[$x]); $y++) {
					$rating=$dump[$ai_tags_calc[$x][$y]]*$ai_tags_weights[$x][$y];
					if($ai_tags_reverserating[$x][$y]){
						$rating=1-$rating;
					}
					$t+=$rating;
				}
				$dump[]=$t;
			}
			if(DumpStats::$info)
				echo implode(",", $dump) . PHP_EOL;
			fputcsv($fp, $dump);
		}

		fclose($file);
		fclose($fp);
   }

   public static function dump(){
   }

}

DumpStats::main();
 
?>