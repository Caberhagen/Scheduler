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



class moves_action_51 extends moves_common {
    function _action_main() {

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

if(empty($this->action_data[0])) {
    return $this->log(MV_M_ERROR, 'action_51: Could not find required action_data entry [0]! SKIP');
}

$user_id = (int)$this->action_data[0];

$planetary_attack = ($user_id == $this->dest['user_id']) ? true : false;

if($planetary_attack) {
    $cur_user = array(
        'user_id' => $user_id,
        'user_name' => $this->dest['user_name'],
        'user_race' => $this->dest['user_race'],
        'user_planets' => $this->dest['user_planets'],
        'user_alliance' => $this->dest['user_alliance']
    );
}
else {
    $sql = 'SELECT user_id, user_name, user_race, user_planets, user_alliance
            FROM user
            WHERE user_id = '.$user_id;

    if(($cur_user = $this->db->queryrow($sql)) === false) {
        return $this->log(MV_M_DATABASE, 'Could not query attacked user data! SKIP');
    }

    if(empty($cur_user['user_id'])) {
        $this->log(MV_M_NOTICE, 'action_51: Could not find attacked user! FINISHED');
        
        $sql = 'UPDATE ship_fleets
                SET planet_id = '.$this->move['dest'].',
                    move_id = 0
                WHERE fleet_id IN ('.$this->fleet_ids_str.')';
                
        if(!$this->db->query($sql)) {
            return $this->log(MV_M_DATABASE, 'Could not update fleets location data! SKIP');
        }
        
        return MV_EXEC_OK;
    }
}

$sql = 'SELECT DISTINCT f.user_id,
               u.user_alliance,
               ud.ud_id, ud.accepted,
               ad.ad_id, ad.type, ad.status
        FROM (ship_fleets f)
        INNER JOIN user u ON u.user_id = f.user_id
        LEFT JOIN user_diplomacy ud ON ( ( ud.user1_id = '.$user_id.' AND ud.user2_id = f.user_id ) OR ( ud.user1_id = f.user_id AND ud.user2_id = '.$user_id.' ) )
        LEFT JOIN alliance_diplomacy ad ON ( ( ad.alliance1_id = '.$cur_user['user_alliance'].' AND ad.alliance2_id = u.user_alliance) OR ( ad.alliance1_id = u.user_alliance AND ad.alliance2_id = '.$cur_user['user_alliance'].' ) )
        WHERE f.planet_id = '.$this->move['start'].' AND
              f.user_id <> '.$user_id.' AND
              f.user_id <> '.$this->move['user_id'].' AND
              f.alert_phase >= '.ALERT_PHASE_YELLOW;

//$this->log(MV_M_NOTICE,'Defenders Select:<br>"'.$sql.'"<br>');

if(!$q_st_uid = $this->db->query($sql)) {
    return $this->log(MV_M_DATABASE, 'Could not query stationated fleets user data! SKIP');
}

$st_user = array();

while($st_uid = $this->db->fetchrow($q_st_uid)) {
    $allied = false;

    if($st_uid['user_alliance'] == $cur_user['user_alliance']) $allied = true;

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
             WHERE f.planet_id = '.$this->move['start'].' AND
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
             WHERE f.planet_id = '.$this->move['start'].' AND
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
if($this->do_ship_combat($this->fleet_ids_str, $dfd_fleet_ids_str, ( ($planetary_attack) ? MV_COMBAT_LEVEL_ORBITAL : MV_COMBAT_LEVEL_OUTER ) ) == MV_EXEC_ERROR) {
	$this->log(MV_M_DATABASE, 'Move Action 51: Something went wrong with this fight!');
    return MV_EXEC_ERROR;
}


// #############################################################################
// The fleet possibly send into orbit

// #############################################################################
// 03/04/08 - AC: Retrieve player language
$prep = ($planetary_attack) ? 'on' : 'at';
$dfd_title = 'Attack '.$prep.' '.$this->dest['planet_name'];
$sql = 'SELECT language FROM user WHERE user_id = '.$user_id;
if(!($lang = $this->db->queryrow($sql))) {
    $this->log(MV_M_DATABASE, 'Could not retrieve player language');
}
else
{
    switch($lang['language'])
    {
        case 'GER':
            $prep = ($planetary_attack) ? 'auf' : 'bei';
            $dfd_title = 'Angriff '.$prep.' '.$this->dest['planet_name'];
        break;
        case 'ITA':
            $prep = ($planetary_attack) ? 'su' : 'presso';
            $dfd_title = 'Attacco '.$prep.' '.$this->dest['planet_name'];
        break;
    }
}


if($this->cmb[MV_CMB_WINNER] == MV_CMB_ATTACKER) {
    // #############################################################################
    // 03/04/08 - AC: Retrieve player language
    switch($this->move['language'])
    {
        case 'GER':
            $prep = ($planetary_attack) ? 'auf' : 'bei';
            $atk_title = 'Angriff '.$prep.' '.$this->dest['planet_name'].' erfolgreich';
        break;
        case 'ITA':
            $prep = ($planetary_attack) ? 'su' : 'presso';
            $atk_title = 'Attacco '.$prep.' '.$this->dest['planet_name'].' riuscito';
        break;
        default:
            $prep = ($planetary_attack) ? 'on' : 'at';
            $atk_title = 'Attack '.$prep.' '.$this->dest['planet_name'].' successful';
        break;
    }

    $sql = 'UPDATE ship_fleets
            SET planet_id = '.$this->move['dest'].',
                move_id = 0
            WHERE fleet_id IN ('.$this->fleet_ids_str.')';

    if(!$this->db->query($sql)) {
        // Here one could also report and then go on
        return $this->log(MV_M_DATABASE, 'Could not update fleets location data! SKIP');
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
                        log_code = 10,
                        mood_modifier = - 20';
        else
            $sql = 'INSERT INTO settlers_relations
                    SET planet_id = '.$this->move['dest'].',
                        user_id = '.$this->move['user_id'].',
                        race_id = '.$this->move['user_race'].',
                        timestamp = '.time().',
                        log_code = 10,
                        mood_modifier = - 20';

        if(!$this->db->query($sql)) {
            $this->log(MV_M_DATABASE, 'Could not update settlers moods! CONTINUE!');
        }

        $this->check_best_mood($this->move['dest'], false);
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
            $prep = ($planetary_attack) ? 'auf' : 'bei';
            $atk_title = 'Angriff '.$prep.' '.$this->dest['planet_name'].' fehlgeschlagen';
        break;
        case 'ITA':
            $prep = ($planetary_attack) ? 'su' : 'presso';
            $atk_title = 'Attacco su '.$this->dest['planet_name'].' fallito';
        break;
        default:
            $prep = ($planetary_attack) ? 'on' : 'at';
            $atk_title = 'Attack on '.$this->dest['planet_name'].' failed';
        break;
    }
}


// #############################################################################
// Logbook write

$log1_data = array(51, $this->move['user_id'], $this->move['dest'], $this->dest['planet_name'], $this->dest['user_id'], 0, 0, 0, MV_CMB_ATTACKER, ( ($this->cmb[MV_CMB_WINNER] == MV_CMB_ATTACKER) ? 1 : 0 ), 0,0, $atk_fleets, $dfd_fleets);
$log2_data = array(51, $this->move['user_id'], $this->move['dest'], $this->dest['planet_name'], $this->dest['user_id'], 0, 0, 0, MV_CMB_DEFENDER, ( ($this->cmb[MV_CMB_WINNER] == MV_CMB_DEFENDER) ? 1 : 0 ), 0,0, $atk_fleets, $dfd_fleets);

if($planetary_attack) $log2_data[14] = $this->cmb[MV_CMB_KILLS_PLANETARY];

$log1_data[10] = $this->cmb[MV_CMB_KILLS_EXT];
$log2_data[10] = $this->cmb[MV_CMB_KILLS_EXT];


add_logbook_entry($this->move['user_id'], LOGBOOK_TACTICAL_2, $atk_title, $log1_data);
add_logbook_entry($user_id, LOGBOOK_TACTICAL_2, $dfd_title, $log2_data);

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
