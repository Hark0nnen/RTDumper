<?php
include ".\php\common.php";
/* Notes
Dump dumps the mech characteristics
DumpStats compares mechs against each other.
AItags translates the stats into actionable ai info

stats are rated.
Rating {R} [0-1]. Based on the max/min/average/standard deviation of a stat.
Boolean false / true based on if a mech has a characteristic , are also converted to rating {R} [0/1]. 

For each AI tag determine a value between [0-1] . Each AI tag, is grouped into low(<.2), med (.2-.8) , high > .8 

*1. Heat: how it handles heat
RTDumper understands CASE , AmmoExplosions , Volatile AmmoExplosions , AMS Heat , heat damage injury, heat activated components
={R Max Ammo Explosion damage} * ? + {R Max Volatile Ammo Explosion damage} * ? + {R AMS_heat } * ? + {B Has Pilot injury on overheat}*?

low - avoids overheating, has volatile ammo 
normal - will run hot but be carefull, basically most mechs
high - will ride the redline hard, for units like the nova 

Desired AI Behaviour:
* low: Turn OFF AMS,heat generating components when redlined. 
* normal/high: switch ON AMS Overload (Multi) if available. Switch ON heat generating components (TSM/Hotseat cockpit/Vibro blade).




*/

class AITag extends Config{
   public static function main(){
    
   }

}

AITag::main();
 
?>