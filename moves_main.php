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


$sdl->log('entering file');

// backward
if(!isset($CURRENT_TICK)) $CURRENT_TICK = $ACTUAL_TICK;

include('moves_common.php');
include('moves_combat.php');


include('moves_action_11.php');
include('moves_action_12.php');
include('moves_action_13.php');
include('moves_action_14.php');

include('moves_action_21.php');
include('moves_action_22.php');
include('moves_action_23.php');
include('moves_action_24.php');
include('moves_action_25.php');
include('moves_action_26.php');
include('moves_action_27.php');
include('moves_action_28.php');


include('moves_action_31.php');
include('moves_action_32.php');
include('moves_action_33.php');
include('moves_action_34.php');


include('moves_action_40.php');
include('moves_action_41.php');
include('moves_action_42.php');
include('moves_action_46.php');
;

include('moves_action_51.php');
include('moves_action_52.php');
include('moves_action_53.php');
include('moves_action_54.php');
include('moves_action_55.php');


// #############################################################################


$sql = 'UPDATE scheduler_shipmovement
        SET move_status = 2
        WHERE action_code = 32 AND
              move_status = 0 AND
              move_finish <= '.$CURRENT_TICK;

if(!$db->query($sql)) {
    $sdl->log('- Moves Database Error: Could not update fake fleets data! CONTINUE');
}


// #############################################################################


$sql = 'SELECT ss.*,
               u.user_name, u.user_active, u.user_race, u.user_alliance, u.user_alliance_status, u.user_planets
        FROM (scheduler_shipmovement ss)
        INNER JOIN user u ON u.user_id = ss.user_id
        WHERE ss.move_finish <= '.$CURRENT_TICK.' AND
              ss.move_status = 0
        ORDER BY ss.move_id ASC';

if(!$q_moves = $db->query($sql)) {
    $sdl->log('- Moves Database Error: Could not select main moves data! MOVES ABORTED');

    return MV_EXEC_ERROR;
}

$sdl->log('- Moves: Starting process');


while($cur_move = $db->fetchrow($q_moves)) {
    $action_class = 'moves_action_'.$cur_move['action_code'];

    //if ($cur_move['action_code']<50 && $cur_move['action_code']!=24  && $cur_move['action_code']!=25)
//{
    $mv = new $action_class($db, $cur_move, $CURRENT_TICK);

    $mv->_main();

    $mv = null;
//}
}


$sdl->log('- Moves: Ending process');


?>
