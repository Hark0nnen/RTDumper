<?php
class Config{
   public static $RT_Mods_dir="C:\games\steam\steamapps\common\BATTLETECH\Mods";

   #These are meant for developers
   public static $debug=TRUE;
   public static $info=FALSE;
   public static $warn=FALSE;
  
   public static $debug_single_mech='mechdef_longbow_LGB-RX';

   //useful to see the calculation for multiple mechs at a time when debugging the tag
   public static $debug_mechs_ai_tag=null;
   //public static $debug_mechs_ai_tag=array('ai_dfa','mechdef_ajax_AJX-A2','mechdef_highlander_HGN-732b','mechdef_elemental_toad','mechdef_black_queen_LVT-BKQN','mechdef_falcon_FLC-4N','mechdef_anvil_ANV-3M');
}