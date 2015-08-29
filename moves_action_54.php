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



class moves_action_54 extends moves_common {
    function _action_main() {

global $NUM_BUILDING, $RACE_DATA;

// #############################################################################
// Data from the attacker

$sql = 'SELECT '.$this->get_combat_query_fleet_columns().'
		FROM (ship_fleets f)
		INNER JOIN user u ON u.user_id = f.user_id
		WHERE f.fleet_id IN ('.$this->fleet_ids_str.')';

if(($atk_fleets = $this->db->queryrowset($sql)) === false) {
	return $this->log(MV_M_DATABASE, 'Could not query attackers fleets data! SKIP');
}


// #############################################################################
// Data from the defender

$user_id = $this->dest['user_id'];
$planetary_attack = true;

// The course could be optimized, but it should be possible 
// compatibility to provide much action_51.php maintained
$cur_user = array(
    'user_id' => $user_id,
    'user_name' => $this->dest['user_name'],
    'user_race' => $this->dest['user_race'],
    'user_planets' => $this->dest['user_planets'],
    'user_alliance' => $this->dest['user_alliance']
);

// 21/06/08 - AC: Check if attacker and defender are NOT the same player!
if($this->move['user_id'] == $this->dest['user_id']) {
    $this->log(MV_M_NOTICE, 'Player #'.$this->move['user_id'].' has tried to bomb himself! SKIP');

    // Put ships in safe place
    $sql = 'UPDATE ship_fleets
            SET planet_id = '.$this->move['dest'].',
                move_id = 0
            WHERE fleet_id IN ('.$this->fleet_ids_str.')';

    if(!$this->db->query($sql)) {
        return $this->log(MV_M_DATABASE, 'Could not update fleets location data! SKIP');
    }

    return MV_EXEC_OK;
}

$sql = 'SELECT DISTINCT f.user_id,
               u.user_alliance,
               ud.ud_id, ud.accepted,
               ad.ad_id, ad.type, ad.status
        FROM (ship_fleets f)
        INNER JOIN (user u) ON u.user_id = f.user_id
        LEFT JOIN (user_diplomacy ud) ON ( ( ud.user1_id = '.$user_id.' AND ud.user2_id = f.user_id ) OR ( ud.user1_id = f.user_id AND ud.user2_id = '.$user_id.' ) )
        LEFT JOIN (alliance_diplomacy ad) ON ( ( ad.alliance1_id = '.$cur_user['user_alliance'].' AND ad.alliance2_id = u.user_alliance) OR ( ad.alliance1_id = u.user_alliance AND ad.alliance2_id = '.$cur_user['user_alliance'].' ) )
        WHERE f.planet_id = '.$this->move['dest'].' AND
              f.user_id <> '.$user_id.' AND
              f.user_id <> '.$this->move['user_id'].' AND
              f.alert_phase >= '.ALERT_PHASE_YELLOW;

if(!$q_st_uid = $this->db->query($sql)) {
    return $this->log(MV_M_DATABASE, 'Could not query stationated fleets user data! SKIP');
}

$st_user = array();

while($st_uid = $this->db->fetchrow($q_st_uid)) {
    $allied = false;

    if($st_uid['user_alliance'] == $this->dest['user_alliance']) $allied = true;

    if(!empty($st_uid['ud_id'])) {
        if($st_uid['accepted'] == 1) $allied = true;
    }

    if(!empty($st_uid['ad_id'])) {
        if( ($st_uid['type'] == ALLIANCE_DIPLOMACY_PACT) && ($st_uid['status'] == 0) ) $allied = true;
    }

    if($allied) $st_user[] = $st_uid['user_id'];
}

$n_st_user = count($st_user);

if($n_st_user > 0) {
    $sql = 'SELECT '.$this->get_combat_query_fleet_columns().'
             FROM (ship_fleets f)
             INNER JOIN user u ON u.user_id = f.user_id
             WHERE f.planet_id = '.$this->move['dest'].' AND
                   (
                     (
                       f.user_id = '.$user_id.'
                     )
                     OR
                     (
                       f.user_id IN ('.implode(',', $st_user).') AND
                       f.alert_phase >= '.ALERT_PHASE_YELLOW.'
                     )
                   )';
}
else {
    $sql = 'SELECT '.$this->get_combat_query_fleet_columns().'
             FROM (ship_fleets f)
             INNER JOIN user u ON u.user_id = f.user_id
             WHERE f.planet_id = '.$this->move['dest'].' AND
                   f.user_id = '.$user_id;
}

if(($dfd_fleets = $this->db->queryrowset($sql)) === false) {
    return $this->log(MV_M_DATABASE, 'Could not query defenders fleets data! SKIP');
}

$dfd_fleet_ids = array();

foreach($dfd_fleets as $i => $cur_fleet) {
    $dfd_fleet_ids[] = $cur_fleet['fleet_id'];
}

$dfd_fleet_ids_str = implode(',', $dfd_fleet_ids);
if($this->do_ship_combat($this->fleet_ids_str, $dfd_fleet_ids_str, MV_COMBAT_LEVEL_PLANETARY) == MV_EXEC_ERROR) {
    $this->log(MV_M_DATABASE, 'Move Action 54: Something went wrong with this fight!');
    return MV_EXEC_ERROR;
}


// #############################################################################
// If the attacker has won more bomb or is final

// #############################################################################
// 03/04/08 - AC: Retrieve player language
switch($this->dest['language'])
{
    case 'GER':
        $dfd_title = 'Angriff auf '.$this->dest['planet_name'];
    break;
    case 'ITA':
        $dfd_title = 'Attacco su '.$this->dest['planet_name'];
    break;
    default:
        $dfd_title = 'Attack on '.$this->dest['planet_name'];
    break;
}

$status_code = 0;

if($this->cmb[MV_CMB_WINNER] == MV_CMB_ATTACKER) {
    $n_buildings = $NUM_BUILDING;
    $n_building_lvls = 0;

    $i = 0;

    while($i <= $n_buildings) {
        $n_building_lvls += (int)$this->dest['building_'.(++$i)];
    }

    $bomb_i = (!empty($this->action_data[1])) ? (int)$this->action_data[1] : 0;

    // 080408 DC: the oracle says....
    $this->log(MV_M_NOTICE, 'I read building_lvls = '.$n_building_lvls.' and bomb_i = '.$bomb_i);


    //Special "Antiborg" ship
    $queryadd='';
    if ($this->dest['user_id']==BORG_USERID)
    {
        $sql = 'SELECT COUNT(ship_id) AS num FROM (ships s) WHERE s.template_id = 8 AND s.fleet_id IN ('.$this->fleet_ids_str.')';

        if(($borgw = $this->db->queryrow($sql)) === false) {
            return $this->log(MV_M_DATABASE, 'Could not query fleets planetary weapons data! SKIP');
        }

        if ($borgw['num']>0)
        {
            $unitkill=array(min($this->dest['unit_1'],($borgw['num']*rand(200,400) ) ),
                            min($this->dest['unit_2'],($borgw['num']*rand(100,300) ) ),
                            min($this->dest['unit_3'],($borgw['num']*rand(75,200) ) ),
                            min($this->dest['unit_4'],($borgw['num']*rand(50,100) ) )
                           );

            $queryadd=' unit_1=unit_1-'.$unitkill[0].', 
                        unit_2=unit_2-'.$unitkill[1].', 
                        unit_3=unit_3-'.$unitkill[2].', 
                        unit_4=unit_4-'.$unitkill[3].', ';
        }
    }


    if($n_building_lvls == 1 && $queryadd=='') {
        // Enough Bombed...

        $sql = 'UPDATE ship_fleets
                SET planet_id = '.$this->move['dest'].',
                    move_id = 0
                WHERE fleet_id IN ('.$this->fleet_ids_str.')';

        // 080408 DC: Atrocity & Genocide are not YET permitted
        $this->log(MV_M_NOTICE, 'Atrocity & Genocide not YET permitted, bombing will not be done.');

        if(!$this->db->query($sql)) {
            return $this->log(MV_M_DATABASE, 'Could not update fleets location data! SKIP');
        }

        if($bomb_i > 0) {
            $status_code = -2;

            // #############################################################################
            // 03/04/08 - AC: Retrieve player language
            switch($this->move['language'])
            {
                case 'GER':
                   $atk_title = 'Angriff auf '.$this->dest['planet_name'].' beendet';
                break;
                case 'ITA':
                    $atk_title = 'Attacco su '.$this->dest['planet_name'].' terminato';
                break;
                default:
                    $atk_title = 'Attack on '.$this->dest['planet_name'].' ended';
                break;
            }
        }
        else {
            $status_code - 1;

            // #############################################################################
            // 03/04/08 - AC: Retrieve player language
            switch($this->move['language'])
            {
                case 'GER':
                   $atk_title = 'Angriff auf '.$this->dest['planet_name'].' teilweise erfolgreich';
                break;
                case 'ITA':
                    $atk_title = 'Attacco su '.$this->dest['planet_name'].' parzialmente riuscito';
                break;
                default:
                    $atk_title = 'Attack on '.$this->dest['planet_name'].' partially successful';
                break;
            }
        }
    }
    else {
        $sql = 'SELECT SUM(st.value_3) AS sum_planetary_weapons
                FROM (ships s)
                INNER JOIN ship_templates st ON st.id = s.template_id
                WHERE s.fleet_id IN ('.$this->fleet_ids_str.') AND s.torp >= 5';

        if(($plweapons = $this->db->queryrow($sql)) === false) {
            return $this->log(MV_M_DATABASE, 'Could not query fleets planetary weapons data! SKIP');
        }

        // 190815 DC Yeah! We need moooar querieeees!!!
        $sql = 'SELECT COUNT(*) as c3 FROM (ships s) INNER JOIN (ship_templates st) ON st.id =s.template_id
                 WHERE s.fleet_id IN ('.$this->fleet_ids_str.') AND s.torp >= 5 AND s.experience >= 70 AND st.ship_class = 3';

        if(($c3bonus = $this->db->queryrow($sql)) === false) {
            return $this->log(MV_M_DATABASE, 'Could not query fleets planetary weapons data! SKIP');
        }
        
        $sql = 'SELECT COUNT(*) as c2 FROM (ships s) INNER JOIN (ship_templates st) ON st.id =s.template_id
                 WHERE s.fleet_id IN ('.$this->fleet_ids_str.') AND s.torp >= 5 AND s.experience >= 100 AND st.ship_class = 2';
        
        if(($c2bonus = $this->db->queryrow($sql)) === false) {
            return $this->log(MV_M_DATABASE, 'Could not query fleets planetary weapons data! SKIP');
        }
        
        $planetary_weapons = (int)$plweapons['sum_planetary_weapons'] + (int)($c2bonus['c2']*10) + (int)($c3bonus['c3']*50);

        if($planetary_weapons == 0) {
            $sql = 'UPDATE ship_fleets
                    SET planet_id = '.$this->move['dest'].',
                        move_id = 0
                    WHERE fleet_id IN ('.$this->fleet_ids_str.')';

            if(!$this->db->query($sql)) {
                return $this->log(MV_M_DATABASE, 'Could not update fleets location data! SKIP');
            }

            $status_code = -3;

            // #############################################################################
            // 03/04/08 - AC: Retrieve player language
            switch($this->move['language'])
            {
                case 'GER':
                   $atk_title = 'Angriff auf '.$this->dest['planet_name'].' teilweise erfolgreich';
                break;
                case 'ITA':
                    $atk_title = 'Attacco su '.$this->dest['planet_name'].' parzialmente riuscito';
                break;
                default:
                    $atk_title = 'Attack on '.$this->dest['planet_name'].' partially successful';
                break;
            }
        }
        else {
            if(!isset($this->action_data[0])) {
                return $this->log(MV_M_ERROR, 'action_54: Could not find required action_data parameter 0! SKIP');
            }

            // Updating torpedo stocks on ships

            $sql = 'UPDATE ships SET torp = torp - 5 WHERE fleet_id IN ('.$this->fleet_ids_str.') AND torp >= 5';

            if(!$this->db->query($sql)) {
                return $this->log(MV_M_DATABASE, 'Could not update torpedo data after attack! SKIP');
            }

            $focus = (int)$this->move['action_data'][0];

            if($focus < 0) $focus = 0;
            if($focus > 3) $focus = 3;



            $new_dest = PlanetaryAttack($this->mid,$planetary_weapons, $this->dest, $focus, $RACE_DATA[$this->dest['user_race']][17]);

            $sql = 'UPDATE planets
                    SET resource_4 = '.$new_dest['resource_4'].',
                        building_1 = '.$new_dest['building_1'].',
                        building_2 = '.$new_dest['building_2'].',
                        building_3 = '.$new_dest['building_3'].',
                        building_4 = '.$new_dest['building_4'].',
                        building_5 = '.$new_dest['building_5'].',
                        building_6 = '.$new_dest['building_6'].',
                        building_7 = '.$new_dest['building_7'].',
                        building_8 = '.$new_dest['building_8'].',
                        building_9 = '.$new_dest['building_9'].',
                        building_10 = 0,
                        building_11 = '.$new_dest['building_11'].',
                        building_12 = '.$new_dest['building_12'].',
                        building_13 = 0,
                        unit_1 = '.$new_dest['unit_1'].',
                        unit_2 = '.$new_dest['unit_2'].',
                        unit_3 = '.$new_dest['unit_3'].',
                        unit_4 = '.$new_dest['unit_4'].',
                        unit_5 = '.$new_dest['unit_5'].',
                        unit_6 = '.$new_dest['unit_6'].',
                        workermine_1 = '.(($new_dest['building_2'] + 1) * 100).',
                        workermine_2 = '.(($new_dest['building_3'] + 1) * 100).',
                        workermine_3 = '.(($new_dest['building_4'] + 1) * 100).',
                        '.$queryadd.'
                        recompute_static=1
                    WHERE planet_id = '.$this->move['dest'];

            if(!$this->db->query($sql)) {
                return $this->log(MV_M_DATABASE, 'Could not update planets data after attack! SKIP');
            }

            $sql = 'UPDATE scheduler_shipmovement
                    SET move_begin = '.$this->CURRENT_TICK.',
                        move_finish = '.($this->CURRENT_TICK + 8).',
                        action_data = "'.serialize(array($focus, ($bomb_i + 1))).'"
                    WHERE move_id = '.$this->mid;

            if(!$this->db->query($sql)) {
                return $this->log(MV_M_DATABASE, 'Could not update move data! SKIP');
            }

            $this->check_academy_status(); // Gonna check the academy queue after bombing

            $this->check_shipyard_status(); // Gonna check the shipyard status after bombing

            $this->check_spacedock_status(); // Gonna check the ships in spacedock after bombing

            $status_code = ($bomb_i > 0) ? 2 : 1;

            // #############################################################################
            // 03/04/08 - AC: Retrieve player language
            switch($this->move['language'])
            {
                case 'GER':
                    $atk_title = 'Angriff auf '.$this->dest['planet_name'].' erfolgreich';
                break;
                case 'ITA':
                    $atk_title = 'Attacco su '.$this->dest['planet_name'].' riuscito';
                break;
                default:
                    $atk_title = 'Attack on '.$this->dest['planet_name'].' successful';
                break;
            }

            // If the attack was on a settlers planet, they will get a little mad!

            if($this->dest['user_id'] == INDEPENDENT_USERID) {
                $this->log(MV_M_NOTICE, 'Colony: Settlers being attacked!!! They gonna be mad!');

                $sql = 'DELETE FROM settlers_relations WHERE planet_id = '.$this->move['dest'].' AND user_id = '.$this->move['user_id'];

                if(!$this->db->query($sql)) {
                    $this->log(MV_M_DATABASE, 'Could not update settlers moods! CONTINUE!');
                }

                if(!empty($this->move['user_alliance']) && $this->move['user_alliance_status'] > 2)
                    $sql = 'INSERT INTO settlers_relations
                            SET planet_id = '.$this->move['dest'].',
                                user_id = '.$this->move['user_id'].',
                                alliance_id = '.$this->move['user_alliance'].',
                                race_id = '.$this->move['user_race'].',
                                timestamp = '.time().',
                                log_code = 11,
                                mood_modifier = - 50';
                else
                    $sql = 'INSERT INTO settlers_relations
                            SET planet_id = '.$this->move['dest'].',
                                user_id = '.$this->move['user_id'].',
                                race_id = '.$this->move['user_race'].',
                                timestamp = '.time().',
                                log_code = 11,
                                mood_modifier = - 50';

                if(!$this->db->query($sql)) {
                    $this->log(MV_M_DATABASE, 'Could not update settlers moods! CONTINUE!');
                }

                $this->check_best_mood($this->move['dest'], false);

            }

            $this->flags['keep_move_alive'] = true;
        }
    }
}
else {
    // #############################################################################
    // 17/08/15 - Retreat Management
    
    $sql = 'SELECT COUNT(*) as ship_escaped FROM ships WHERE fleet_id IN ('.$this->fleet_ids_str.')';
    $e_s_c = $this->db->queryrow($sql);
    if(isset($e_s_c['ship_escaped']) && $e_s_c['ship_escaped'] > 0) {
        $sql = 'SELECT system_id, planet_distance_id FROM planets WHERE planet_id = '.$this->move['dest'];
        $e_s_s = $this->db->queryrow($sql);
        $sql = 'SELECT planet_id FROM planets WHERE system_id = '.$e_s_s['system_id'].' AND planet_id <> '.$this->move['dest'].'
                       AND planet_distance_id > '.$e_s_s['planet_distance_id'].' ORDER BY planet_distance_id ASC LIMIT 0,1';
        if(!$res = $this->db->query($sql)) {
            return $this->log(MV_M_DATABASE, 'Could not read system data!!! SQL is '.$sql);
        }
        $rows = $this->db->num_rows();
        if($rows > 0) {
            $escaperes = $this->db->fetchrow($res);
            $newdest = $escaperes['planet_id'];
        }
        else {
            $newdest = $this->move['dest'];
        }
        $sql='UPDATE scheduler_shipmovement SET start = '.$this->move['dest'].', dest = '.$newdest.', move_begin = '.$this->CURRENT_TICK.', move_finish = '.($this->CURRENT_TICK + 4).','
            .' action_code = 28, action_data = 0, n_ships = '.$e_s_c['ship_escaped'].' WHERE move_id = '.$this->move['move_id'];
        if(!$this->db->query($sql)) {
            return $this->log(MV_M_DATABASE, 'Could not update movement data!!! SQL is '.$sql);
        }
        $this->flags['keep_move_alive'] = true;
    }    
    // #############################################################################
    // 03/04/08 - AC: Retrieve player language
    switch($this->move['language'])
    {
        case 'GER':
            $atk_title = 'Angriff auf '.$this->dest['planet_name'].' fehlgeschlagen';
        break;
        case 'ITA':
            $atk_title = 'Attacco su '.$this->dest['planet_name'].' fallito';
        break;
        default:
            $atk_title = 'Attack on '.$this->dest['planet_name'].' failed';
        break;
    }
}


// #############################################################################
// Write logbook

$log1_data = array(54, $this->move['user_id'], $this->move['dest'], $this->dest['planet_name'], $this->dest['user_id'], 0, 0, 0, MV_CMB_ATTACKER, ( ($this->cmb[MV_CMB_WINNER] == MV_CMB_ATTACKER) ? 1 : 0 ), 0,0, $atk_fleets, $dfd_fleets);
$log2_data = array(54, $this->move['user_id'], $this->move['dest'], $this->dest['planet_name'], $this->dest['user_id'], 0, 0, 0, MV_CMB_DEFENDER, ( ($this->cmb[MV_CMB_WINNER] == MV_CMB_DEFENDER) ? 1 : 0 ), 0,0, $atk_fleets, $dfd_fleets);


$log1_data[10] = $this->cmb[MV_CMB_KILLS_EXT];
$log2_data[10] = $this->cmb[MV_CMB_KILLS_EXT];


$log2_data[14] = $this->cmb[MV_CMB_KILLS_PLANETARY];

$log1_data[17] = $log2_data[17] = $status_code;

if(!empty($new_dest)) {
    $log1_data[18] = $log2_data[18] = array(
        $this->dest['resource_4'] - $new_dest['resource_4'],
        $this->dest['building_1'] - $new_dest['building_1'],
        $this->dest['building_2'] - $new_dest['building_2'],
        $this->dest['building_3'] - $new_dest['building_3'],
        $this->dest['building_4'] - $new_dest['building_4'],
        $this->dest['building_5'] - $new_dest['building_5'],
        $this->dest['building_6'] - $new_dest['building_6'],
        $this->dest['building_7'] - $new_dest['building_7'],
        $this->dest['building_8'] - $new_dest['building_8'],
        $this->dest['building_9'] - $new_dest['building_9'],
        0,
        $this->dest['building_11'] - $new_dest['building_11'],
        $this->dest['building_12'] - $new_dest['building_12'],
        $this->dest['unit_1'] - $new_dest['unit_1'],
        $this->dest['unit_2'] - $new_dest['unit_2'],
        $this->dest['unit_3'] - $new_dest['unit_3'],
        $this->dest['unit_4'] - $new_dest['unit_4'],
        $this->dest['unit_5'] - $new_dest['unit_5'],
        $this->dest['unit_6'] - $new_dest['unit_6'],
        0
    );
}
    $log1_data[19] = $log2_data[19] = $this->dest['user_race'];


if(isset($unitkill)) {
    $log1_data[20] = $log2_data[20] = $unitkill;

}

add_logbook_entry($this->move['user_id'], LOGBOOK_TACTICAL, $atk_title, $log1_data);
add_logbook_entry($this->dest['user_id'], LOGBOOK_TACTICAL, $dfd_title, $log2_data);

if($n_st_user > 0) {
    $log2_data[2] = $this->move['start'];
    $log2_data[3] = $this->dest['planet_name'];
    $log2_data[4] = $user_id;
    $log2_data[5] = 0;
    $log2_data[6] = 0;
    $log2_data[7] = 0;
    $log2_data[16] = 1;

    for($i = 0; $i < $n_st_user; ++$i) {
        // #############################################################################
        // 03/04/08 - AC: Retrieve player language
        $log_title = 'One of your allies defended '.$this->dest['planet_name'];
        $sql = 'SELECT language FROM user WHERE user_id = '.$st_user[$i];
        if(!($lang = $this->db->queryrow($sql))) {
            $this->log(MV_M_DATABASE, 'Could not retrieve player language');
        }
        else
        {
            switch($lang['language'])
            {
                case 'GER':
                    $log_title = 'Verb&uuml;ndeten bei '.$this->dest['planet_name'].' verteidigt';
                break;
                case 'ITA':
                    $log_title = 'Difesa alleata presso '.$this->dest['planet_name'];
                break;
            }
        }

        add_logbook_entry($st_user[$i], LOGBOOK_TACTICAL, $log_title, $log2_data);
    }
}

return MV_EXEC_OK;

    }
}

?>
