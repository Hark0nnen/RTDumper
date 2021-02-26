<?php
include ".\php\common.php";
/* Notes
Dump dumps the mech characteristics
DumpStats compares mechs against each other.
AItags translates the stats into actionable ai info

Overall two kinds of stats:
1. Ranking {R} [0-1]. Based on the max/min/average/standard deviation of a stat.
2. Boolean {B} [0/1]. false / true based on if a mech has a characteristic.

For each tag determine a value between [0-1] . Each AI tag, is grouped into low(<.2), med (.2-.8) , high > .8 .


Heat: how it handles heat
={B volatile_ammo}*? + {B explosive_ammo}*?+{R AMS_heat}*? + {B Has Pilot injury on overheat}*?
low - avoids overheating, has volatile ammo 
normal - will run hot but be carefull, basically most mechs
high - will ride the redline hard, for units like the nova 




*/

class AITag extends Config{
   public static function main(){
    
   }

}

AITag::main();
 
?>