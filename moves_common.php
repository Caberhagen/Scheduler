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

// include game definitions, path url and so on
include('config.script.php');

// status codes for _action_main() and _main()
define('MV_EXEC_OK', 1);
define('MV_EXEC_ERROR', -1);

// used for moves_common::log()
define('MV_M_NOTICE', 1);
define('MV_M_ERROR', 2);
define('MV_M_DATABASE', 3);
define('MV_M_CRITICAL', 4);

// macros for the elements of the numerical arrays returned by combat system
define('MV_CMB_WINNER', 0);
define('MV_CMB_KILLS_PLANETARY', 1);
define('MV_CMB_KILLS_SPLANETARY', 2);
define('MV_CMB_KILLS_EXT', 3);

define('MV_CMB_ATTACKER', 0);
define('MV_CMB_DEFENDER', 1);

define('MV_COMBAT_LEVEL_PLANETARY', 1); // Attack of the planet (= Colonization)
define('MV_COMBAT_LEVEL_ORBITAL', 2); // Attack on owned planet, but only ships
define('MV_COMBAT_LEVEL_OUTER', 3); // Fight beyond the planet between foreign players (or attack by the owner)

define('MV_COMBAT_BIN_PATH', $script_path . 'stfc-moves-combat/bin/moves_combat');

// defines form action27

define('LC_FIRST_CONTACT', 1);
define('LC_DIPLO_SPEECH', 2);

define('LC_SUP_TECH', 3);
define('LC_SUP_MEDI', 4);
define('LC_SUP_DFNS', 5);
define('LC_SUP_AUTO', 6);
define('LC_SUP_MINE',7);
define('LC_MIL_ORBITAL', 8); 

define('LC_REL_MULTIC', 10);
define('LC_REL_TSUPER', 11);
define('LC_REL_CHAMPN', 12);
define('LC_REL_INNVTR', 13);
define('LC_REL_OPPSTR', 14);
define('LC_REL_PLURLS', 15);
define('LC_REL_PRESTG', 16);
define('LC_REL_DEFNDR', 17);
define('LC_REL_CMPTNT', 18);
define('LC_REL_LIBERT', 19);
define('LC_REL_LEADER', 20);
define('LC_REL_MECENA', 21);
define('LC_REL_WORSHP', 22);
define('LC_REL_PREDAT', 23);
define('LC_REL_STRNGR', 24);
define('LC_REL_UNABLE', 25);
define('LC_REL_EXPLOI', 26);
define('LC_REL_PREY',   27);

define('LC_COLO_FOUNDER', 30);
define('LC_COLO_GIVER', 31);

define('STL_MAX_ORBITAL', 120);


function commonlog($message,$message2,$move_id=0)
{
    $fp = fopen(TICK_LOG_FILE, 'a');
        fwrite($fp, $message." (move_id: <b>".$move_id."</b>) ".$message2."<br>\n");
        echo str_replace('\n','<br>',$message." ".$message2."\n");
        fclose($fp);
}

class moves_common {
    var $db;

    var $move = array();
    var $mid = 0;

    var $CURRENT_TICK = 0;

    var $start = array();
    var $dest = array();

    var $n_fleets = 0;
    var $fleet_ids = array();
    var $fleet_names = array();
    var $n_ships = array();
    var $fleet_ids_str = '';

    var $flags = array(
        'is_orbital_move' => true,
        'is_friendly_move' => false,

        'free_dest_planet' => false,

        'skip_action' => false,
        'keep_move_alive' => false,

        'combat_happened' => false
    );

    var $action_data = array();

    var $cmb = array();


    function moves_common(&$db, &$cur_move, $CURRENT_TICK) {
        $this->db = $db;

        $this->move = $cur_move;
        $this->mid = $this->move['move_id'];

        $this->CURRENT_TICK = $CURRENT_TICK;

        /* 02/04/08 - AC: Load user language */
        $sql = 'SELECT language FROM user WHERE user_id = '.$this->move['user_id'];
        if(!($lang = $this->db->queryrow($sql))) {
            $this->log(MV_M_DATABASE, 'Could not retrieve player '.$this->move['user_id'].' (move) language! SET to ENG');
            $this->move['language'] = 'ENG';
        }
        else
            $this->move['language'] = $lang['language'];
        /* */
    }

    function log($level, $message) {
        global $sdl;

        switch($level) {
            case MV_M_NOTICE:
                $sdl->log('- Moves Notice: (move_id: '.$this->mid.') '.$message);
            break;

            case MV_M_ERROR:
                $sdl->log('- Moves Error: (move_id: '.$this->mid.') '.$message);
            break;

            case MV_M_DATABASE:
                $sdl->log('- Moves Database Error: (move_id: '.$this->mid.') '.$message.' - '.$this->db->error['message'].' - '.$this->db->error['sql']);
            break;
            case MV_M_CRITICAL:
                $sdl->log('- Moves CRITICAL: (move_id: '.$this->mid.') '.$message);
            break;

            default:
                $sdl->log($level.': (move_id: '.$this->mid.') '.$message);
            break;

        }

        return MV_EXEC_ERROR;
    }

    function report($reason) {

        $message = addslashes($reason.NL.NL.
                              'move_id: <b>'.$this->mid.'</b>'.NL.
                              'user_id: <b>'.$this->move['user_id'].'</b>'.NL.
                              'action_code: <b>'.$this->move['action_code'].'</b>'.NL.
                              'start: <b>'.$this->move['start'].'</b>'.NL.
                              'dest: <b>'.$this->move['dest'].'</b>'.NL.
                              'move_begin: <b>'.$this->move['move_begin'].'</b>'.NL.
                              'move_finish: <b>'.$this->move['move_finish'].'</b>'.NL.
                              'processing tick: <b>'.$this->CURRENT_TICK.'</b>');

        SystemMessage(DATA_UID, 'Move ('.$reason.')', $message);
    }

    function deactivate($to_status) {
        $sql = 'UPDATE ship_fleets
                SET planet_id = '.$this->move['start'].',
                    move_id = 0
                WHERE move_id = '.$this->mid;

        if(!$this->db->query($sql)) {
            $this->log(MV_M_DATABASE, 'Could not update fleets location data before move deativication! CONTINUE');
        }

        $sql = 'UPDATE scheduler_shipmovement
                SET move_status = '.$to_status.'
                WHERE move_id = '.$this->mid;

        if(!$this->db->query($sql)) {
            $this->log(MV_M_DATABASE, 'Could not update move status data to deactivation status '.$to_status.'! CONTINUE');
        }

        $this->log(MV_M_NOTICE, 'Deactivate move to status '.$to_status);
    }

    function get_combat_query_fleet_columns() {
        return 'f.fleet_id, f.fleet_name, f.n_ships,
                u.user_name AS owner_name';
    }

    function get_combat_query_ship_columns() {
        return 's.ship_id, s.hitpoints, s.unit_1, s.unit_2, s.unit_3, s.unit_4, s.experience, s.user_id, s.fleet_id,
                st.value_1, st.value_2, st.value_3, st.value_4, st.value_5, st.value_6, st.value_7, st.value_8, st.value_10, st.value_11, st.value_12, st.name, st.ship_torso, st.ship_class, st.race';
    }

    function check_academy_status() {
        // Checks if the training queues of a planet are consistent with the structures of the planet itself.
        // It's poorly written and with abuse of the continue, but that day I was so that ...

        $no_academy = false;
        $no_unit_3 = false;
        $no_unit_2 = false;
        $academy_is_working = false;
        $academy_next_tick = 0;

        $sql = 'SELECT unittrainid_1, unittrainid_2, unittrainid_3, unittrainid_4, unittrainid_5,
                       unittrainid_6, unittrainid_7, unittrainid_8, unittrainid_9, unittrainid_10,
                       unittrain_actual, unittrainid_nexttime, building_6, research_4
                FROM planets WHERE planet_id = '.$this->move['dest'];
        if(!($planet_info = $this->db->queryrow($sql))) {
            $this->log(MV_M_DATABASE, 'Error reading academy queues info of planet <b>'.$this->move['dest'].'</b>');
        }
        else
        {
            $academy_level = $planet_info['building_6'];
            if ($academy_level == 0) $no_academy = true;
            if ($academy_level < 5) $no_unit_2 = true;
            if ($academy_level < 9) $no_unit_3 = true;
            if ($planet_info['unittrain_actual'] != 0) $academy_is_working = true;
            if ($planet_info['unittrainid_nexttime'] != 0)
                $academy_next_tick = $planet_info['unittrainid_nexttime']; // Can't remember the meaning of this...

            if ($no_academy)
            {
                // No academy at all, so i reset all academy fields. Big overhead but little risks.
                $sql = 'UPDATE planets
                        SET unittrainid_1 = 0, unittrainnumber_1 = 0, unittrainnumberleft_1 = 0, unittrainendless_1 = 0,
                            unittrainid_2 = 0, unittrainnumber_2 = 0, unittrainnumberleft_2 = 0, unittrainendless_2 = 0,
                            unittrainid_3 = 0, unittrainnumber_3 = 0, unittrainnumberleft_3 = 0, unittrainendless_3 = 0,
                            unittrainid_4 = 0, unittrainnumber_4 = 0, unittrainnumberleft_4 = 0, unittrainendless_4 = 0,
                            unittrainid_5 = 0, unittrainnumber_5 = 0, unittrainnumberleft_5 = 0, unittrainendless_5 = 0,
                            unittrainid_6 = 0, unittrainnumber_6 = 0, unittrainnumberleft_6 = 0, unittrainendless_6 = 0,
                            unittrainid_7 = 0, unittrainnumber_7 = 0, unittrainnumberleft_7 = 0, unittrainendless_7 = 0,
                            unittrainid_8 = 0, unittrainnumber_8 = 0, unittrainnumberleft_8 = 0, unittrainendless_8 = 0,
                            unittrainid_9 = 0, unittrainnumber_9 = 0, unittrainnumberleft_9 = 0, unittrainendless_9 = 0,
                            unittrainid_10 = 0, unittrainnumber_10 = 0, unittrainnumberleft_10 = 0, unittrainendless_10 = 0,
                            unittrain_actual = 0, unittrainid_nexttime = 0, unittrain_error = 0
                        WHERE planet_id = '.$this->move['dest'];
                if (!$this->db->query($sql))
                {
                    $this->log(MV_M_DATABASE, 'Error emptying academy working queues of planet <b>'.$this->move['dest'].'</b>');
                }
            }
            else
            {
                $sqlpart = '';

                // Ok, we have to check all the academy slots
                for ($index = 1; $index < 11; $index++)
                {
                    if ($planet_info['unittrainid_'.$index] == 0) continue;

                    if ($planet_info['unittrainid_'.$index] == 2 && $no_unit_2)
                    {
                        // Gotcha! Get out from the queue!
                        $planet_info['unittrainid_'.$index] = 0;
                        $sqlpart .= 'unittrainid_'.$index.' = 0,
                            unittrainnumber_'.$index.' = 0,
                            unittrainnumberleft_'.$index.' = 0,
                            unittrainendless_'.$index.' = 0,';
                    }

                    if ($planet_info['unittrainid_'.$index] == 3 && $no_unit_3)
                    {
                        // Gotcha! Get out from the queue!
                        $planet_info['unittrainid_'.$index] = 0;
                        $sqlpart .= 'unittrainid_'.$index.' = 0,
                            unittrainnumber_'.$index.' = 0,
                            unittrainnumberleft_'.$index.' = 0,
                            unittrainendless_'.$index.' = 0,';
                    }
                }

                // There is something to remove from the queue?
                if($sqlpart != '')
                {
                    $sql = 'UPDATE planets
                            SET '.rtrim($sqlpart,',').'
                            WHERE planet_id = '.$this->move['dest'];
                    if (!$this->db->query($sql))
                    {
                        $this->log(MV_M_DATABASE, 'Error updating planet <b>'.$this->move['dest'].'</b> academy queues info');
                    }
                }

                // Check the active slot. If the active slot is now empty, go check for the next working slot and make it active.
                $active_slot = $planet_info['unittrain_actual'];
                $tries = 0;

                while ($academy_is_working && ($planet_info['unittrainid_'.$active_slot] == 0))
                {
                    $tries++; 

                    if ($tries > 9)
                    {
                        $academy_is_working = false;
                        $sql = 'UPDATE planets
                                SET unittrain_actual = 0, unittrainid_nexttime = 0,
                                    unittrain_error = 0
                                WHERE planet_id = '.$this->move['dest'];
                        if (!$this->db->query($sql))
                        {
                            $this->log(MV_M_DATABASE, 'Error clearing OLD academy active slot of planet <b>'.$this->move['dest'].'</b>');
                        }
                        continue;
                    }

                    $active_slot++;
                    if($active_slot > 10) $active_slot = 1;

                    if($planet_info['unittrainid_'.$active_slot] == 0) continue;

                    $academy_next_tick = $this->CURRENT_TICK+UnitTimeTicksScheduler($planet_info['unittrainid_'.$active_slot],
                        $planet_info['research_4'],$this->dest['user_race']);

                    $sql = 'UPDATE planets
                            SET unittrain_actual = '.$active_slot.', unittrainid_nexttime = '.$academy_next_tick.',
                                unittrain_error = 0
                            WHERE planet_id = '.$this->move['dest'];
                    if (!$this->db->query($sql))
                    {
                        $this->log(MV_M_DATABASE, 'Error updating NEW academy active slot of planet <b>'.$this->move['dest'].'</b>');
                    }
                }
            }
        }
    }

    function check_spacedock_status() {
        // Checks if the number of ships that may be present in a spacedock is consistent with the level of the structure

        global $MAX_SPACEDOCK_SHIPS;

        $sql = 'SELECT building_7 FROM planets WHERE planet_id = '.$this->move['dest'];
        if(!($planet_info = $this->db->queryrow($sql)))
        {
            $this->log(MV_M_DATABASE, 'moves_common.check_spacedock_status: error reading planet info');
        }
        else
        {
            $sql = 'SELECT COUNT(*) AS num_ships FROM ships WHERE fleet_id = -'.$this->move['dest'];
            if(!($ship_docked = $this->db->queryrow($sql))) 
            {
                $this->log(MV_M_DATABASE, 'moves_common.check_spacedock_status: error reading docked ships info');
            }
            else
            {
                if ($ship_docked['num_ships'] > $MAX_SPACEDOCK_SHIPS[$planet_info['building_7']])
                {
                    $ships_to_delete = $ship_docked['num_ships'] - $MAX_SPACEDOCK_SHIPS[$planet_info['building_7']];
                    $sql = 'DELETE FROM ships WHERE fleet_id = -'.$this->move['dest'].' LIMIT '.$ships_to_delete;
                    $this->log(MV_M_NOTICE, 'moves_common.check_spacedock_status: delete query = '.$sql);
                    $this->db->query($sql);
                    $ships_deleted = $this->db->affected_rows();
                    if($ships_to_delete != $ships_deleted)
                    {
                        $this->log(MV_M_DATABASE, 'moves_common.check_spacedock_status: error deleting ships. Expected '.$ships_to_delete.', MySQL reported '.$ships_deleted);
                    }
                }
            }
        }
    }

    function check_shipyard_status() {
        //If the level of the shipyard reaches zero, delete all the ships (if any) in the construction queue

        $sql = 'SELECT building_8 FROM planets WHERE planet_id = '.$this->move['dest'];
        if(!($planet_info = $this->db->queryrow($sql)))
        {
            $this->log(MV_M_DATABASE, 'moves_common.check_shipyard_status: error reading planet info');
        }
        else
        {
            if ($planet_info['building_8'] == 0)
            {
                $sql = 'DELETE FROM scheduler_shipbuild WHERE planet_id = '.$this->move['dest'];
                $this->db->query($sql);
            }
        }
    }

    function check_best_mood($planet_id, $notify) {
        /* This function will check all moods value on an indipedent planet and will set
        * the fields best_mood and best_mood_user
        */

        /* AC: Domanda: questa query non si poteva scrivere nel modo seguente?
         *
         *  $sql = 'SELECT best_mood, best_mood_user FROM planets
         *          WHERE planet_id = '.$planet_id.' AND planet_owner = '.INDIPENDENT_USERID;
         *
         *  if(!($q_p = $this->db->queryrow($sql));
         *      return -1;
         */
        $sql = 'SELECT planet_name, planet_owner, best_mood, best_mood_user FROM planets
                WHERE planet_id = '.$planet_id;

        $q_p = $this->db->queryrow($sql);

        if(empty($q_p['planet_owner']) || $q_p['planet_owner'] != INDEPENDENT_USERID) {
            return -1;
        }

        /* AC: Questa parte potrebbe essere riscritta nel modo seguente:
         *
         *  $sql = 'SELECT user_id, SUM(mood_modifier) AS mood FROM settlers_relations
         *           WHERE planet_id = '.$planet_id.' GROUP BY user_id ORDER BY timestamp ASC';
         *  $q_p_m = $this->db->queryrowset($sql);
         *
         *  foreach($q_p_m as $q_m) {
         *      if($q_m['mood'] > $best) {
         *           $newbest = true;
         *           $best = $q_m['mood'];
         *           $best_id = $q_m['user_id'];
         *       }
         *   }
         *   if($newbest) {
         *       $sql = 'UPDATE planets
         *               SET best_mood = '.$best.',
         *                   best_mood_user = '.$best_id.'
         *               WHERE planet_id = '.$planet_id;
         *       $this->db->query($sql);
         *   }
         */
        $sql = 'SELECT user_id, SUM(mood_modifier) AS mood FROM settlers_relations
                WHERE planet_id = '.$planet_id.' GROUP BY user_id ORDER BY timestamp ASC';

        $q_p_m = $this->db->query($sql);

        $rows = $this->db->num_rows($q_p_m);

        $best = $best_id = 0;

        if($rows > 0) {
            /* Db popolato.
            * Scansione dei risultati della query e ricerca del valore più alto.
            * Viene aggiornato il db
            */
            $q_m = $this->db->fetchrowset($q_p_m);
            // Non che mi piaccia molto ma vabbè...
            $best = $q_m[0]['mood'];
            $best_id = $q_m[0]['user_id'];
            for($i=0; $i < $rows; $i++) {
                if($q_m[$i]['mood'] > $best) {
                    $best = $q_m[$i]['mood'];
                    $best_id = $q_m[$i]['user_id'];
                }
            }
            $sql = 'UPDATE planets
                    SET best_mood = '.$best.',
                        best_mood_user = '.$best_id.'
                    WHERE planet_id = '.$planet_id;
            $this->db->query($sql);
        }

        if(!empty($best_id) && $q_p['best_mood_user'] != $best_id && $notify) {
            return $q_p['best_mood_user'];
        }

        return 0;
    }

    function get_distance($s_system, $d_system) {
        global $SYSTEM_WIDTH;

        /*
        $s_system[0] -> global X-Coordinate
        $s_system[1] -> global Y-Coordinate
        */

        if($s_system[0] == $d_system[0]) {
            $distance = abs( ( ($s_system[1] - $d_system[1]) * $SYSTEM_WIDTH) );
        }
        elseif($s_system[1] == $d_system[1]) {
            $distance = abs( ( ($s_system[0] - $d_system[0]) * $SYSTEM_WIDTH) );
        }
        else {
            $triangle_a = ($s_system[1] - $d_system[1]);
            $triangle_b = ($s_system[0] - $d_system[0]);

            $distance = ( sqrt( ( ($triangle_a * $triangle_a) + ($triangle_b * $triangle_b) ) ) * $SYSTEM_WIDTH );
        }

        return $distance;
    }

    function get_structure_points($player_id, $dest) {
        // Calculate the points structure for the planet

        global $MAX_POINTS;

        $points = $MAX_POINTS[0];

        // Mother system of the player
        $sql = 'SELECT system_id FROM planets WHERE planet_owner = '.$player_id.' AND planet_owner_enum = 0';
        if(!($mosystem = $this->db->queryrow($sql))) {
            return $this->log(MV_M_DATABASE, 'Could not read capital planet data! SKIP');
        }

        $sql = 'SELECT system_global_x, system_global_y FROM starsystems WHERE system_id = '.$mosystem['system_id'];
        if(!($mocoord = $this->db->queryrow($sql))) {
            return $this->log(MV_M_DATABASE, 'Could not read capital starsystem data! SKIP');
        }

        // Destination system
        $sql = 'SELECT system_id FROM planets WHERE planet_id = '.$dest;
        if(!($destsystem = $this->db->queryrow($sql))) {
            return $this->log(MV_M_DATABASE, 'Could not read destination planet data! SKIP');
        }

        $sql = 'SELECT system_global_x, system_global_y FROM starsystems WHERE system_id = '.$destsystem['system_id'];
        if(!($destcoord = $this->db->queryrow($sql))) {
            return $this->log(MV_M_DATABASE, 'Could not read destination starsystem data! SKIP');
        }

        $distance = $this->get_distance(array($mocoord['system_global_x'], $mocoord['system_global_y']),
                                        array($destcoord['system_global_x'], $destcoord['system_global_y']));
        $distance = round($distance, 2);

        if($distance > MAX_BOUND_RANGE) $points = $MAX_POINTS[2];

        return $points; 
    }

    function do_ship_combat(&$atk_fleet_ids_str, &$dfd_fleet_ids_str, $combat_level) {
        global $config;

        if(empty($atk_fleet_ids_str)) $atk_fleet_ids_str = '-1';
        if(empty($dfd_fleet_ids_str)) $dfd_fleet_ids_str = '-1';

        // When $combat_level == MV_COMBAT_LEVEL_PLANETARY the owner is always attacked
        //                        --> fight with all orbitals

        if($combat_level == MV_COMBAT_LEVEL_OUTER) {
            $n_large_orbital_defense = $n_small_orbital_defense = 0;
        }
        else {
            $n_large_orbital_defense = (int)$this->dest['building_10'];
            $n_small_orbital_defense = (int)$this->dest['building_13'];

            if($combat_level == MV_COMBAT_LEVEL_ORBITAL) {
                if ($n_large_orbital_defense < 20) $n_large_orbital_defense = (($n_large_orbital_defense < 10) ? $n_large_orbital_defense : 10);
                else $n_large_orbital_defense /= 2;

                if ($n_small_orbital_defense < 20) $n_small_orbital_defense = (($n_small_orbital_defense < 10) ? $n_small_orbital_defense : 10);
                else $n_small_orbital_defense /= 2;

                settype($n_large_orbital_defense, 'int');
                settype($n_small_orbital_defense, 'int');
            }
        }

        $bin_output = array();

        $cmd_line = MV_COMBAT_BIN_PATH.' '.$atk_fleet_ids_str.' '.$dfd_fleet_ids_str.' '.$this->move['dest'].' '.$n_large_orbital_defense.' '.$n_small_orbital_defense;
        $auth_line = ' '.$config['game_database'].' '.$config['user'].' '.$config['password'];
        exec($cmd_line.$auth_line, $bin_output);

        if($bin_output[0][0] == '0') {
            return $this->log(MV_M_ERROR, 'Combat Binary exited with an error ('.substr($bin_output[0], 1).' - '.$cmd_line.')');
        }

        $this->cmb[MV_CMB_WINNER] = $bin_output[1];
        $this->cmb[MV_CMB_KILLS_PLANETARY] = (int)$bin_output[2];
        $this->cmb[MV_CMB_KILLS_SPLANETARY] = (int)$bin_output[3];
        $this->cmb[MV_CMB_KILLS_EXT] = &$bin_output[4];

        if($this->cmb[MV_CMB_KILLS_PLANETARY] != 0) {
            $this->dest['building_10'] -= $this->cmb[MV_CMB_KILLS_PLANETARY];
        }

        if($this->cmb[MV_CMB_KILLS_SPLANETARY] != 0) {
            $this->dest['building_13'] -= $this->cmb[MV_CMB_KILLS_SPLANETARY];
        }

        return MV_EXEC_OK;
    }

    function _main() {
        $start_processing_time = time() + microtime();

        // #############################################################################
        // On...

        $this->flags['is_orbital_move'] = ($this->move['start'] == $this->move['dest']);
        $this->flags['is_friendly_action'] = in_array($this->move['action_code'], array(23, 31, 33));

        // #############################################################################
        // Security checks

        if(empty($this->mid)) {
            $this->deactivate(31);
            $this->report('Deactivated due to empty move data');

            return MV_EXEC_ERROR;
        }

        if($this->move['move_begin'] == $this->move['move_finish']) {
            $this->deactivate(32);
            $this->report('Deactivated due to illegal arrival times');

            return MV_EXEC_ERROR;
        }

        // #############################################################################
        // If the move was already tried 3 times, cancel

        if($this->move['move_exec_started'] >= 3) {
            $this->deactivate(33);
            $this->report('Deactivated due to execution limit');

            return MV_EXEC_ERROR;
        }

        // #############################################################################
        // Exec-Counter setup

        $sql = 'UPDATE scheduler_shipmovement
                SET move_exec_started = move_exec_started + 1
                WHERE move_id = '.$this->mid;

        if(!$this->db->query($sql)) {
            return $this->log(MV_M_DATABASE, 'Could not update move exec started data! SKIP');;
        }

        // #############################################################################
        // Data from the participating fleets

        $sql = 'SELECT fleet_id, fleet_name, n_ships
                FROM ship_fleets
                WHERE move_id = '.$this->mid;

        if(!$q_fleets = $this->db->query($sql)) {
            return $this->log(MV_M_DATABASE, 'Could not query moves fleets data! SKIP');
        }

        while($_fl = $this->db->fetchrow($q_fleets)) {
            $this->fleet_ids[] = $_fl['fleet_id'];
            $this->fleet_names[] = $_fl['fleet_name'];
            $this->n_ships[] = $_fl['n_ships'];
        }

        $this->n_fleets = count($this->fleet_ids);

        if($this->n_fleets == 0) {
            $this->deactivate(34);
            $this->report('Deactivated due to missing fleets');

            return MV_EXEC_ERROR;
        }

        $this->fleet_ids_str = implode(',', $this->fleet_ids);

        // #############################################################################
        // action_data decode, if available

        if(!empty($this->move['action_data'])) {
            $this->action_data = (array)unserialize($this->move['action_data']);
        }

        // #############################################################################
        // Data of start planet

        $sql = 'SELECT p.*,
                       u.user_id, u.user_active, u.user_name, u.user_race, u.user_planets, u.user_alliance, u.user_capital
                FROM (planets p)
                LEFT JOIN user u ON u.user_id = p.planet_owner
                WHERE p.planet_id = '.$this->move['start'];

        if(($this->start = $this->db->queryrow($sql)) === false) {
            return $this->log(MV_M_DATABASE, 'Could not query start planets data! SKIP');
        }
        settype($this->start['user_id'], 'int');

        // #############################################################################
        // Data of target planet

        if($this->flags['is_orbital_move']) {
            $this->dest = &$this->start;
            /* 03/04/08 - AC: Hmmm, we need language settings here?
             * However set it to english.
             */
            $this->dest['language'] = 'ENG';
        }
        else {
            $sql = 'SELECT p.*,
                           u.user_id, u.user_active, u.user_name, u.user_race, u.user_planets, u.user_alliance, u.user_capital
                    FROM (planets p)
                    LEFT JOIN user u ON u.user_id = p.planet_owner
                    WHERE p.planet_id = '.$this->move['dest'];

            if(($this->dest = $this->db->queryrow($sql)) === false) {
                return $this->log(MV_M_DATABASE, 'Could not query dest planets! SKIP');
            }

            settype($this->dest['user_id'], 'int');


            // #############################################################################
            /* 17/06/08 - AC: Load user language only if a player is present*/
            if($this->dest['user_id'] != 0)
            {
                $sql = 'SELECT language FROM user WHERE user_id = '.$this->dest['user_id'];
                if(!($lang = $this->db->queryrow($sql))) {
                    $this->log(MV_M_DATABASE, 'Could not retrieve player '.$this->dest['user_id'].' (dest) language! SET to ENG');
                    $this->dest['language'] = 'ENG';
                }
                else
                    $this->dest['language'] = $lang['language'];
            }
            else
                $this->dest['language'] = 'ENG';


            // #############################################################################
            // Look for stationed fleets in AR

            $sql = 'SELECT DISTINCT f.user_id,
                           u.user_alliance,
                           ud.ud_id, ud.accepted,
                           ad.ad_id, ad.type, ad.status
                    FROM (ship_fleets f)
                    INNER JOIN user u ON u.user_id = f.user_id
                    LEFT JOIN user_diplomacy ud ON ( ( ud.user1_id = '.$this->move['user_id'].' AND ud.user2_id = f.user_id ) OR ( ud.user1_id = f.user_id AND ud.user2_id = '.$this->move['user_id'].' ) )
                    LEFT JOIN alliance_diplomacy ad ON ( ( ad.alliance1_id = '.$this->move['user_alliance'].' AND ad.alliance2_id = u.user_alliance) OR ( ad.alliance1_id = u.user_alliance AND ad.alliance2_id = '.$this->move['user_alliance'].' ) )
                    WHERE f.planet_id = '.$this->move['dest'].' AND
                          f.user_id <> '.$this->move['user_id'].' AND
                          f.alert_phase = '.ALERT_PHASE_RED;

//$this->log(MV_M_NOTICE,'AR-Fleet:<br>"'.$sql.'"<br>');

            if(!$q_ar_uid = $this->db->query($sql)) {
                return $this->log(MV_M_DATABASE, 'Could not query alert phase red user! SKIP');
            }

            $ar_user = array();

            while($ar_uid = $this->db->fetchrow($q_ar_uid)) {
                //if( ($ar_uid['user_id'] == $this->dest['user_id']) && $is_friendly_action ) continue;

                // While checking for the same alliance, also check if THERE IS actually an alliance!
                if($ar_uid['user_alliance'] != 0 && ($ar_uid['user_alliance'] == $this->move['user_alliance'])) continue;

                if(!empty($ar_uid['ud_id'])) {
                    if($ar_uid['accepted'] == 1) continue;
                }

                if(!empty($ar_uid['ad_id'])) {
                    if( ($ar_uid['type'] == ALLIANCE_DIPLOMACY_PACT) && ($ar_uid['status'] == 0) ) continue;
                }

                $ar_user[] = $ar_uid['user_id'];

                $this->log(MV_M_NOTICE,'AR-User ID is '.$ar_uid['user_id']);
            }

            $this->db->free_result($q_ar_uid);
$this->log(MV_M_NOTICE,'AR-user(s): <b>'.count($ar_user).'</b>');

            for($i = 0; $i < count($ar_user); ++$i) {
                $this->log(MV_M_NOTICE, 'Entering AR-loop #'.$i);

                $sql = 'SELECT '.$this->get_combat_query_fleet_columns().'
                        FROM (ship_fleets f)
                        INNER JOIN user u ON u.user_id = f.user_id
                        WHERE f.planet_id = '.$this->move['dest'].' AND
                              f.user_id = '.$ar_user[$i].' AND
                              f.alert_phase = '.ALERT_PHASE_RED;

$this->log(MV_M_NOTICE,'AR-query:<br>"'.$sql.'"<br>');

                if(($atk_fleets = $this->db->queryrowset($sql)) === false) {
                    return $this->log(MV_M_DATABASE, 'Could not query attacker fleets in AR! SKIP');
                }

                $atk_fleet_ids = array();

                foreach($atk_fleets as $ihh => $cur_fleet) {
                    $atk_fleet_ids[] = $cur_fleet['fleet_id'];
                }

                $sql = 'SELECT '.$this->get_combat_query_fleet_columns().'
                        FROM (ship_fleets f)
                        INNER JOIN user u ON u.user_id = f.user_id
                        WHERE f.fleet_id IN ('.$this->fleet_ids_str.')';

                if(($dfd_fleets = $this->db->queryrowset($sql)) === false) {
                    return $this->log(MV_M_DATABASE, 'Could not query defender fleets in AR! SKIP');
                }

                $this->log(MV_M_NOTICE, 'Doing combat in AR-loop #'.$i);

                $atk_fleet_ids_str = implode(',', $atk_fleet_ids);
                if($this->do_ship_combat($atk_fleet_ids_str, $this->fleet_ids_str, MV_COMBAT_LEVEL_OUTER) == MV_EXEC_ERROR) {
                    $this->log(MV_M_CRITICAL, 'Move Direct: Something went wrong with this fight!');
                    return MV_EXEC_ERROR;
                }

                $this->log(MV_M_NOTICE, 'Combat done in AR-loop #'.$i);

                // If the attacker has won (the fleet AR)
                // the move can then be terminated immediately
                // Now ships can run from combat. We have to check if any ships fled the fight
                // before calling the move a "skipped action"

                if($this->cmb[MV_CMB_WINNER] == MV_CMB_ATTACKER) {

                    $this->flags['skip_action'] = true;
                    
                    $sql = 'SELECT COUNT(*) as ship_escaped FROM ships WHERE fleet_id IN ('.$this->fleet_ids_str.')';
                //  $this->log(MV_M_NOTICE, 'Active fleet lost the fight, we look for survivors with '.$sql);                    
                    $e_s_c = $this->db->queryrow($sql);

                    if(isset($e_s_c['ship_escaped']) && $e_s_c['ship_escaped'] > 0) {
                //      $this->log(MV_M_NOTICE, 'Active fleet survived the fight, will bounce back');
                        // Update move data, setting up the bouncing back
                        // start -> dest, dest -> start
                        $sql = 'UPDATE scheduler_shipmovement SET start = '.$this->move['dest'].', dest = '.$this->move['start'].', 
                                        move_begin = '.$this->CURRENT_TICK.', move_finish = '.($this->CURRENT_TICK + ($this->move['move_finish'] - $this->move['move_begin'])).',
                                        action_code = 28, action_data = 0, n_ships = '.$e_s_c['ship_escaped'].' WHERE move_id = '.$this->move['move_id'];
                        if(!$this->db->query($sql)) $this->log(MV_M_DATABASE, 'Could not update move data with retreat order! '.$sql);                    
                        $this->flags['keep_move_alive'] = true;
                    }
                }

                $log1_data = array(40, $this->move['user_id'], $this->move['start'], $this->start['planet_name'], $this->start['user_id'], $this->move['dest'], $this->dest['planet_name'], $this->dest['user_id'], MV_CMB_ATTACKER, ($this->cmb[MV_CMB_WINNER] == MV_CMB_ATTACKER), 0,0, $atk_fleets, $dfd_fleets, null, $ar_user[$i]);
                $log2_data = array(40, $this->move['user_id'], $this->move['start'], $this->start['planet_name'], $this->start['user_id'], $this->move['dest'], $this->dest['planet_name'], $this->dest['user_id'], MV_CMB_DEFENDER, ($this->cmb[MV_CMB_WINNER] == MV_CMB_DEFENDER), 0,0, $atk_fleets, $dfd_fleets, null, $ar_user[$i]);

                $log1_data[10] = $this->cmb[MV_CMB_KILLS_EXT];
                $log2_data[10] = $this->cmb[MV_CMB_KILLS_EXT];

                // #############################################################################
                // 03/04/08 - AC: Retrieve player language
                //  !! ACTUALLY WE ARE USING THE SAME LANGUAGE ALSO FOR ALLIES LOGBOOK ENTRY !!
                switch($this->move['language'])
                {
                    case 'GER':
                        $log_title1 = 'AR-Flottenverband hat Schiffe bei '.$this->dest['planet_name'].' angegriffen';
                        $log_title2 = 'Flottenverband wurde bei '.$this->dest['planet_name'].' angegriffen';
                    break;
                    case 'ITA':
                        $log_title1 = 'Flotta in AR ha attaccato navi presso '.$this->dest['planet_name'];
                        $log_title2 = 'Associazione flotta attaccata presso '.$this->dest['planet_name'];
                    break;
                    default:
                        $log_title1 = 'AR fleet has attacked ships at '.$this->dest['planet_name'];
                        $log_title2 = 'Fleet association was attacked at '.$this->dest['planet_name'];
                    break;
                }

                add_logbook_entry($ar_user[$i], LOGBOOK_TACTICAL_2, $log_title1, $log1_data);
                add_logbook_entry($this->move['user_id'], LOGBOOK_TACTICAL_2, $log_title2, $log2_data);

                $this->flags['combat_happened'] = true;

                $this->log(MV_M_NOTICE, 'Leaving AR-loop #'.$i);
            }
            
            // #############################################################################
            // Look for stationed fleets in Yellow Alert!!!
            // Non è la migliore delle scelte perché aggiungiamo un secondo loop
            
            $sql = 'SELECT DISTINCT f.user_id,
                           u.user_alliance, u.user_name,
                           ud.ud_id, ud.accepted, u.language,
                           ad.ad_id, ad.type, ad.status
                    FROM (ship_fleets f)
                    INNER JOIN user u ON u.user_id = f.user_id
                    LEFT JOIN user_diplomacy ud ON ( ( ud.user1_id = '.$this->move['user_id'].' AND ud.user2_id = f.user_id ) OR ( ud.user1_id = f.user_id AND ud.user2_id = '.$this->move['user_id'].' ) )
                    LEFT JOIN alliance_diplomacy ad ON ( ( ad.alliance1_id = '.$this->move['user_alliance'].' AND ad.alliance2_id = u.user_alliance) OR ( ad.alliance1_id = u.user_alliance AND ad.alliance2_id = '.$this->move['user_alliance'].' ) )
                    WHERE f.planet_id = '.$this->move['dest'].' AND
                          f.user_id <> '.$this->move['user_id'].' AND
                          f.alert_phase = '.ALERT_PHASE_YELLOW;
            
            $ay_query = $this->db->query($sql);
            
            $ay_n_user = $this->db->num_rows($ay_query);
            
            if($ay_n_user > 0) {
                
                $ay_user = array();
                
                $ay_rows = $this->db->fetchrowset($ay_query);
                
                // Raccogliamo info sulla flotta IN ARRIVO (ossia quella legata alla mossa in elaborazione)
                // per averle già pronte per la scrittura dei log da mandare ai giocatori.
                // Utilizzo queryrowset perché, salvo cataclismi, la flotta in movimento HA una composizione coerente e leggibile.
                
                $sql = 'SELECT st.name, st.race, st.ship_torso, st.ship_class, COUNT(st.name) as n_ships 
                        FROM ship_templates st
                        INNER JOIN ships s ON s.template_id = st.id
                        INNER JOIN ship_fleets f ON f.fleet_id = s.fleet_id
                        WHERE f.fleet_id IN ('.$this->fleet_ids_str.')
                        GROUP BY st.name
                        ORDER BY st.ship_class DESC, st.ship_torso DESC';
                
                $ay_ship_list = $this->db->queryrowset($sql);
                
                $ay_move_name = $this->db->queryrow('SELECT user_name FROM user WHERE user_id = '.$this->move['user_id']); 
                
                foreach ($ay_rows as $ay_uid) {
                    if($ay_uid['user_alliance'] != 0 && ($ay_uid['user_alliance'] == $this->move['user_alliance'])) continue;

                    if(isset($ay_uid['ud_id']) && !empty($ay_uid['ud_id'])) {
                        if($ay_uid['accepted'] == 1) continue;
                    }

                    if(isset($ay_uid['ad_id']) && !empty($ay_uid['ad_id'])) {
                        if( ($ay_uid['type'] == ALLIANCE_DIPLOMACY_PACT) && ($ay_uid['status'] == 0) ) continue;
                    }

                    $ay_user[] = array($ay_uid['user_id'], $ay_uid['language']);
                    

                    $this->log(MV_M_NOTICE,'AY-User ID is '.$ay_uid['user_id']);
                    
                }

$this->log(MV_M_NOTICE,'AY-user(s): <b>'.count($ay_user).'</b>');

                for($i = 0; $i < count($ay_user); ++$i) {
                    $this->log(MV_M_NOTICE, 'Entering AY-loop #'.$i);
                    
                    $sql = 'SELECT f.fleet_id, f.fleet_name
                            FROM (ship_fleets f)
                            INNER JOIN user u ON u.user_id = f.user_id
                             WHERE f.planet_id = '.$this->move['dest'].' AND
                              f.user_id = '.$ay_user[$i][0].' AND
                              f.alert_phase = '.ALERT_PHASE_YELLOW;

                    $this->log(MV_M_NOTICE,'AY-query:<br>"'.$sql.'"<br>');
                    
                    if(($sgnl_fleets = $this->db->queryrowset($sql)) === false) {
                        return $this->log(MV_M_DATABASE, 'Could not query signaling fleets in AY! SKIP');
                    }

                    foreach($sgnl_fleets as $ihh => $cur_fleet) {
                        $log_data[0] = 100; // Codice mossa fasullo per far funzionare il logbook
                        $log_data[1] = $this->move['user_id'];
                        $log_data[2] = $this->move['start'];
                        $log_data[3] = $this->start['planet_name'];
                        $log_data[4] = $this->start['user_id'];
                        $log_data[5] = $this->move['dest'];
                        $log_data[6] = $this->dest['planet_name'];
                        $log_data[7] = $this->dest['user_id'];
                        $log_data[8] = $cur_fleet['fleet_id'];
                        $log_data[9] = $cur_fleet['fleet_name'];
                        $log_data[10] = array_sum($this->n_ships);
                        $log_data[11] = $ay_ship_list;
                        $log_data[12] = $ay_move_name['user_name'];
                        
                        switch($ay_user[$i][1])
                        {
                            case 'GER':
                                $log_title = 'AY-fleet reporting the arrival of a fleet on planet '.$this->dest['planet_name'];
                                break;
                            case 'ITA':
                                $log_title = 'Flotta in AG comunica arrivo di navi sul pianeta '.$this->dest['planet_name'];
                                break;
                            default:
                                $log_title = 'AY-fleet reporting the arrival of ships on planet '.$this->dest['planet_name'];
                                break;
                        }

                        add_logbook_entry($ay_user[$i][0], LOGBOOK_TACTICAL_2, $log_title, $log_data);                        
                    }                    
                    
                    
                    
                }
            }
        }

        // #############################################################################
        // Come through without mistakes?

        $this->flags['free_dest_planet'] = (empty($this->dest['user_id'])) ? true : false;

        if($this->flags['skip_action']) {
            $this->log(MV_M_NOTICE, 'Skipped_action_main()');
        }
        else {
            if($this->_action_main() != MV_EXEC_OK)
            {
                return $this->log(MV_M_ERROR, 'Could not exec successfully _action_main()! SKIP');
            }
            else
            {
                // DC ---- Here we go, first steps into "Fog of War"
                // DC ---- FoW 2.0 Beta - Ally Section
                if(!empty($this->move['user_alliance']))
                {
                    $sql = 'SELECT alliance_id FROM starsystems_details WHERE system_id = '.$this->dest['system_id'].' AND alliance_id = '.$this->move['user_alliance'];
                    $_res = $this->db->queryrow($sql);
                    if(!isset($_res['alliance_id']))
                    {
                        $sql = 'INSERT INTO starsystems_details (system_id, alliance_id, timestamp)
                                VALUES ('.$this->dest['system_id'].', '.$this->move['user_alliance'].', '.time().')';
                        $this->db->query($sql); // No error check plz.
                    }
                }
                // DC ---- FoW 2.0 Beta -- User Section
                $sql = 'SELECT user_id FROM starsystems_details WHERE system_id = '.$this->dest['system_id'].' AND user_id = '.$this->move['user_id'];
                $_res = $this->db->queryrow($sql);
                if(!isset($_res['user_id']))
                {
                    $sql = 'INSERT INTO starsystems_details (system_id, user_id, timestamp)
                            VALUES ('.$this->dest['system_id'].', '.$this->move['user_id'].', '.time().')';
                    $this->db->query($sql); // No error check plz.
                }

                // DC Now, if the planet is unsettled...
                if(empty($this->dest['user_id']))
                {
                    // DC Maybe we did boldly go where nobody has boldly gone before?
                    $sql = 'SELECT COUNT(*) AS been_here FROM planet_details WHERE planet_id = '.$this->dest['planet_id'].' AND user_id = '.$this->move['user_id'].' AND log_code IN (1,2)';            
                    if(!$_flag = $this->db->queryrow($sql))
                        $this->log(MV_M_DATABASE, 'Could not query planet details data! CONTINUE!');
                    if($_flag['been_here'] == 0) {
                        // DC Yeah, we made it!
                        $sql = 'SELECT COUNT(*) AS first_here FROM planet_details WHERE planet_id = '.$this->dest['planet_id'].' AND log_code = 1';
                        if(!$_flag = $this->db->queryrow($sql))
                            $this->log(MV_M_DATABASE, 'Could not query planet details data! CONTINUE!');
                        if($_flag['first_here'] == 0)
                        {
                            $sql = 'INSERT INTO planet_details (planet_id, system_id, user_id, alliance_id, source_uid, source_aid, timestamp, log_code)
                                    VALUES ('.$this->dest['planet_id'].', '.$this->dest['system_id'].', '.$this->move['user_id'].', '.( (!empty($this->move['user_alliance'])) ? $this->move['user_alliance'] : 0 ).', '.$this->move['user_id'].', '.( (!empty($this->move['user_alliance'])) ? $this->move['user_alliance'] : 0 ).', '.time().', 1)';
                            if(!$this->db->query($sql))
                                $this->log(MV_M_DATABASE, 'Could not insert new planet details data! CONTINUE!');
                        }
                        else
                        {
                            // DC Bad luck, we are seconds...
                            // DC AND NOW we need to check if we already placed our flag here...
                            $sql = 'INSERT INTO planet_details (planet_id, system_id, user_id, alliance_id, source_uid, source_aid, timestamp, log_code)
                                    VALUES ('.$this->dest['planet_id'].', '.$this->dest['system_id'].', '.$this->move['user_id'].', '.( (!empty($this->move['user_alliance'])) ? $this->move['user_alliance'] : 0 ).', '.$this->move['user_id'].', '.( (!empty($this->move['user_alliance'])) ? $this->move['user_alliance'] : 0 ).', '.time().', 2)';
                            if(!$this->db->query($sql))
                                $this->log(MV_M_DATABASE, 'Could not insert new planet details data! CONTINUE!');
                        }
                    }
                }
            }
        }

        // #############################################################################
        // Clean-Up

        $total_processing_time = round( (time() + microtime()) - $start_processing_time, 4);

        if($this->flags['keep_move_alive']) {
            $sql = 'UPDATE scheduler_shipmovement
                    SET move_exec_started = 0
                    WHERE move_id = '.$this->mid;

            if(!$this->db->query($sql)) {
                $this->log(MV_M_DATABASE, 'Could not update move exec started data to keep move alive! CONTINUE');
            }

            $this->log(MV_M_NOTICE, 'Action '.$this->move['action_code'].' executed in '.$total_processing_time.'s, but kept alive');
        }
        else {
            $sql = 'UPDATE scheduler_shipmovement
                    SET move_status = '.( ($this->flags['skip_action']) ? '12' : '11' ).'
                    WHERE move_id = '.$this->mid;

            if(!$this->db->query($sql)) {
                $this->log(MV_M_DATABASE, 'Could not update move status data! CONTINUE');
            }

            $this->log(MV_M_NOTICE, 'Action '.$this->move['action_code'].' executed in '.$total_processing_time.'s');
        }

        return MV_EXEC_OK;
    }

    function _action_main() {
        $this->log(MV_M_ERROR, 'Action class defined no _action_main()-method! DEACTIVATE AND REPORT');

        $this->deactivate(36);
        $this->report('Action class defined no _action_main()-method');

        return MV_EXEC_ERROR;
    }
}

?>

