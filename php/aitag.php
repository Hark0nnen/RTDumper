<?php
include ".\php\common.php";
/* Notes
Dump dumps the mech characteristics
DumpStats compares mechs against each other.
AItags translates the stats into actionable ai info i.e. tags

stats are rated.
Rating {R} [0-1]. Based on the max/min/average/standard deviation of a stat.
Boolean false / true based on if a mech has a characteristic , are also converted to rating {R} [0/1]. 

For each AI tag determine a value between [0-1] . Each AI tag, is grouped into low(<.2), med (.2-.8) , high > .8 

*1. Heat: how it handles heat
RTDumper understands CASE , AmmoExplosions , Volatile AmmoExplosions , AMS Heat , heat damage injury, heat activated components, heat efficency.

tag based on:
{R Max Ammo Explosion damage}  {R Max Volatile Ammo Explosion damage}  {R "AMS Single Heat"}  {R "AMS Multi Heat" }  {R Heat Damage Injury} {R Heat Efficency } {R Auto Activation Heat}

low - avoids overheating, has volatile ammo 
normal - will run hot but be carefull, basically most mechs
high - will ride the redline hard, for units like the nova 

Desired AI Behaviour:
* low: Turn OFF AMS,heat generating components when redlined. Avoid overheating.
* normal/high: switch ON AMS Overload if available. Switch ON heat generating components (Hotseat cockpit/Vibro blade etc).
* high: run near the readline, use alpha strike even if it can't dissipate heat. Overheat. Turn of injury causing components before alpha strike.


*2 dfa: likelyhood to dfa
low - avoids DFA at all cost
normal - may dfa when reasonable
high - has dfa buffing gear and wants to jump in their face

{R DFA Damage}  {R DFA Self Damage} 

*/

class AITag extends Config{
   public static function main(){
    AITag::init();
	AITag::processStats();
	//and dump what we need to csv
	AITag::dump();
   }

   public static function init(){
	   GLOBAL $data_collect,$csv_min_stat,$csv_max_stat,$ai_tags;
	   for ($x = 0; $x < count($ai_tags); $x++) {
			$data_collect[$x]=array();
	   }
   }

     public static function processStats(){
	   GLOBAL $csv_header,$stat_min,$stat_max,$stat_avg,$stat_stddev,$data_collect,$csv_min_stat,$csv_max_stat,$csv_header,$ai_tags,$ai_tags_calc,$ai_tags_weights;
	   $file = fopen('./Output/mechratings.csv', 'r');
		while (($line = fgetcsv($file)) !== FALSE) {
		   if(!startswith($line[0],"#"))
		   {
			   for ($x = 0; $x < count($ai_tags); $x++) {
					$data_collect[$x][]=(float)$line[count($csv_header)+$x];
			   }
			}
		}
		fclose($file);
		for ($x = 0; $x < count($ai_tags); $x++) {
				$stat_min[$x]=min($data_collect[$x]);
				$stat_max[$x]=max($data_collect[$x]);
				$stat_avg[$x]=(array_sum($data_collect[$x]) / count($data_collect[$x]));
				$stat_stddev[$x]=sd($data_collect[$x],$stat_avg[$x]);
				echo str_pad ( $ai_tags[$x],25)." MIN: ".str_pad ( $stat_min[$x],8)." AVG: ".str_pad ( number_format($stat_avg[$x],2),8)." MAX: ".str_pad ( $stat_max[$x],8)."  STD DEV: ".str_pad ( number_format($stat_stddev[$x],2),8).PHP_EOL;
		}	
		$file = fopen('./Output/mechratings.csv', 'r');
		$fp = fopen('./Output/mechaitags.csv', 'wb');
		$csv_header_r=array();
		$csv_header_r[]=$csv_header[0];
		$csv_header_r[]=$csv_header[count($csv_header)-1];
		for($x=0; $x<count($ai_tags); $x++){
			$csv_header_r[]=$ai_tags[$x].' Rating';
		}
		for($x=0; $x<count($ai_tags); $x++){
			$csv_header_r[]=$ai_tags[$x];
		}
		fputcsv($fp, $csv_header_r);
		while (($line = fgetcsv($file)) !== FALSE) {
		   if(startswith($line[0],"#"))
				continue;
			
			if(AITag::$debug_single_mech){
				if($line[0]==AITag::$debug_single_mech)
					AITag::$info=TRUE;
				else
					AITag::$info=FALSE;
			}

			$dump=array();
			$dump[]=$line[0];
			$dump[]=$line[count($csv_header)-1];
			for($x=0; $x<count($ai_tags); $x++){
				$dump[]=0;
				$dump[]=0;
			}
			for ($x = 0; $x < count($ai_tags); $x++) {
					$data=(float)$line[count($csv_header)+$x];
					$avg=$stat_avg[$x];
					$max=$stat_max[$x];
					$min=$stat_min[$x];
					$maxsd=$avg+$stat_stddev[$x];
					if($maxsd>$max)
					  $maxsd=$max;
					$minsd=$avg-$stat_stddev[$x];
					if($minsd<$min)
					  $minsd=$min;
					if($data<=$minsd){
					  $dump[2+count($ai_tags)+$x]='low';
					  if($minsd==$min)
						$dump[2+$x]=0;
					  else 
	                    $dump[2+$x]=($data-$min)/($minsd-$min)*0.2;
					}else if($data>$minsd && $data<$maxsd){
						$dump[2+count($ai_tags)+$x]='normal';
	                    $dump[2+$x]=0.2+(($data-$minsd)/($maxsd-$minsd)*0.6);
					}else if($data>=$maxsd){
					  $dump[2+count($ai_tags)+$x]='high';
					  if($maxsd==$max)
						$dump[2+$x]=1;
					  else 
	                    $dump[2+$x]=0.8+(($data-$maxsd)/($max-$maxsd)*0.2);
					}

			}	

			if(AITag::$info)
				echo implode(",", $dump) . PHP_EOL;
			fputcsv($fp, $dump);
		}

		fclose($file);
		fclose($fp);
   }

   public static function dump(){
   }

}

AITag::main();
 
?>