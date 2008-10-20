<?php
/*
    This file is part of STFC.
    Copyright 2006-2007 by Michael Krauss (info@stfc2.de) and Tobias Gafner

    STFC is based on STGC,
    Copyright 2003-2007 by Florian Brede (florian_brede@hotmail.com) and Philipp Schmidt

    STFC is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 3 of the License, or
    (at your option) any later version.

    STFC is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/


// ########################################################################################
// ########################################################################################
// Startup Config

// include game definitions, path url and so on
include('config.script.php');

error_reporting(E_ERROR);
ini_set('memory_limit', '200M');
set_time_limit(300); // 5 minutes

if(!empty($_SERVER['SERVER_SOFTWARE'])) {
    echo 'The scheduler can only be called by CLI!'; exit;
}

define('TICK_LOG_FILE', $game_path . 'logs/sixhours/tick_'.date('d-m-Y', time()).'.log');
define('TICK_LOG_FILE_NPC', TICK_LOG_FILE);
define('IN_SCHEDULER', true); // we are in the scheduler...

// include commons classes and functions
include('commons.php');

// include BOT class
include('NPC_BOT.php');


// ########################################################################################
// ########################################################################################
// Init

$starttime = ( microtime() + time() );

include($game_path . 'include/global.php');
include($game_path . 'include/functions.php');
include($game_path . 'include/text_races.php');
include($game_path . 'include/race_data.php');
include($game_path . 'include/ship_data.php');
include($game_path . 'include/libs/moves.php');

$sdl = new scheduler();
$db = new sql($config['server'].":".$config['port'], $config['game_database'], $config['user'], $config['password']); // create sql-object for db-connection

$game = new game();

$sdl->log("\n\n\n".'<b>-------------------------------------------------------------</b>'."\n".
          '<b>Starting SixHours-Script at '.date('d.m.y H:i:s', time()).'</b>');

if(($cfg_data = $db->queryrow('SELECT * FROM config')) === false) {
    $sdl->log('- Fatal: Could not query tick data! ABORTED');
  exit;
}

$ACTUAL_TICK = $cfg_data['tick_id'];
$NEXT_TICK = ($cfg_data['tick_time'] - time());
$LAST_TICK_TIME = ($cfg_data['tick_time']-5*60);
$STARDATE = $cfg_data['stardate'];



/*
Example Job:

$sdl->start_job('Mine Job');

do something ... during error / message:
  $sdl->log('...');
best also - before, so it's apart from the other messages, also: $sdl->log('- this was not true');

$sdl->finish_job('Mine Job'); // terminates the timer

*/



// Ok, now we try to update the user_max_colo
$sdl->start_job('Recalculate colony ship limits');

$sql = 'SELECT user_points FROM user ORDER BY user_points DESC LIMIT 30,1';
if(!$limit = $db->queryrow($sql)) {
	$sdl->log('<b>Error:</b> Could not query user points data! CONTINUED');
	$limit['user_points'] = 2000;
}

// Chi sta SOPRA la soglia puà fare solo una colonizzatrice per volta!!!
$sql = 'UPDATE user SET user_max_colo = 1 WHERE user_points > '.$limit['user_points'];
if(!$db->query($sql))
	$sdl->log('<b>Error:</b> Cannot set user_max_colo to 1! CONTINUED');

//Chi è uguale o minore della soglia, può fare quante colonizzatrici desidera!!!
$sql = 'UPDATE user SET user_max_colo = 0 WHERE user_points <= '.$limit['user_points'];
if(!$db->query($sql))
	$sdl->log('<b>Error:</b> Cannot set user_max_colo to 0! CONTINUED');

$sdl->finish_job('Recalculate colony ship limits');



// Check of Settler Planets OMG!!! LOT OF TIME USED!!!
$sdl->start_job('Colony DB checkup');
$sql = 'SELECT planet_id FROM planets WHERE planet_owner = '.INDEPENDENT_USERID;
$planets_restored = 0;
$settlers_planets = $db->query($sql);
while($fetch_planet=$db->fetchrow($settlers_planets)) {
	$sql='SELECT * FROM planet_details WHERE planet_id = '.$fetch_planet['planet_id'].' AND log_code = 300';
	if(!$db->queryrow($sql)) {
		$sdl->log('Colony Exception: planet '.$fetch_planet['planet_id'].' with missing moods information! Restoring with default data...');
		$sql='INSERT INTO planet_details SET planet_id  = '.$fetch_planet['planet_id'].', 
		                  user_id = '.INDEPENDENT_USERID.',
		                  log_code   = 300, 
		                  timestamp  = '.time();
		//$sdl->log('Colony SQL: '.$sql);
		if(!$db->query($sql))
		{
			$sdl->log('<b>Error:</b> Bot: Could not insert default colony moods data!');
		}
		$planets_restored++;
	}
}
if($planets_restored != 0) $sdl->log('Colony Report: Restored '.$planets_restored.' default planets mood data');
$sdl->finish_job('Colony DB checkup');



// Check of miners available on Borg planets
$sdl->start_job('Check miners on Borg planets');
$mines_upgraded = 0;
$miners = array();
$mines_level = array();
$borg = new NPC($db,$sdl);
$borg -> LoadNPCUserData(BORG_USERID);

// We need many infos here, for StartBuild() function
$sql = 'SELECT * FROM planets WHERE planet_owner = '.BORG_USERID;
$borg_planets = $db->query($sql);
while($planet=$db->fetchrow($borg_planets)) {
	// If we have at least a workers slot
	if($planet['resource_4'] > 100) {
		$mine = 0;
		$max_reached = 0;
		$miners_updated = false;
		$miners[0] = $planet['workermine_1'];
		$miners[1] = $planet['workermine_2'];
		$miners[2] = $planet['workermine_3'];
		$workers = $planet['resource_4'];
		$mines_level[0] = $planet['building_2'];
		$mines_level[1] = $planet['building_3'];
		$mines_level[2] = $planet['building_4'];

		while($workers > 100 && $max_reached < 3) {
			// If there is space for new workers
			if($miners[$mine] < (($mines_level[$mine]*100)+100)) {
				$miners[$mine]+=100;
				$workers-=100;
				$miners_updated = true;
			}
			// There is no space available, perhaps we can increase the mine level?
			else {
				$max_reached++;
			}
			$mine++;
			if($mine > 2)
				$mine = 0;
		}

		// If we exit from the while because there isn't space available on the mines
		if($max_reached) {
			// Search the mine with lowest level
			$min = $mines_level[0];
			$mine = 1;
			for($i = 1;$i < 3;$i++)
				if($mines_level[$i] < $min)
				{
					$min = $mines_level[$i];
					$mine = $i + 1;
				}

			// Start to build a new mine level
			$res = $borg->StartBuild($ACTUAL_TICK,$mine,$planet);
			if($res == BUILD_ERR_ENERGY)
				$res = $borg->StartBuild($ACTUAL_TICK,4,$planet);
		}

		if($miners_updated) {
			$sql='UPDATE planets SET
			             workermine_1 = '.$miners[0].',
			             workermine_2 = '.$miners[1].',
			             workermine_3 = '.$miners[2].',
			             resource_4 = '.$workers.'
			      WHERE planet_id = '.$planet['planet_id'];

			$sdl->log('Mines workers increased to: '.$miners[0].'/'.$miners[1].'/'.$miners[2].' on Borg planet: <b>#'.$planet['planet_id'].'</b>');

			$mines_upgraded++;

			if(!$db->query($sql)) {
				$sdl->log('<b>Error:</b> could not update mines workers!');
			}
		}
	}
}
if($mines_upgraded != 0) $sdl->log('Upgraded <b>'.$mines_upgraded.'</b> mines on Borg planets');
$sdl->finish_job('Check miners on Borg planets');



// ########################################################################################
// ########################################################################################
// Quit and close log

$db->close();
$sdl->log('<b>Finished SixHours-Script in <font color=#009900>'.round((microtime()+time())-$starttime, 4).' secs</font>'."\n".'Executed Queries: <font color=#ff0000>'.$db->i_query.'</font></b>');

?>
