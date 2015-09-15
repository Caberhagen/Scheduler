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



class moves_action_24 extends moves_common {
    function _action_main() {
        
        global $RACE_DATA;
        
        // #############################################################################
        // Prepare Logbook to be able to cancel at any time

        $log_data = array(24, $this->move['user_id'], $this->move['start'], $this->start['planet_name'], $this->start['user_id'], $this->move['dest'], $this->dest['planet_name'], $this->dest['user_id'], 0);


        // #############################################################################
        // 10/03/08 - AC: Retrieve player language
        switch($this->move['language'])
        {
            case 'GER':
                $col_title = 'Kolonisation von ';
                $col_fail = ' fehlgeschlagen';
                $col_success = ' erfolgreich';
                $ter_title =   'Terraforming von ';
                $ter_fail =    ' fehlgeschlagen';
                $ter_success = ' erfolgreich';
            break;
            case 'ITA':
                $col_title = 'Colonizzazione di ';
                $col_fail = ' fallita';
                $col_success = ' riuscita';
                $ter_title =   'Terraforming di ';
                $ter_fail =    ' fallito';
                $ter_success = ' riuscito';
            break;
            default:
                $col_title = 'Colonization of ';
                $col_fail = ' failed';
                $col_success = ' successful';
                $ter_title =   'Terraforming of ';
                $ter_fail =    ' failed';
                $ter_success = ' succeesful';
            break;
        }


        // #############################################################################
        // Args check

        if(!isset($this->action_data[1])) $this->action_data[1] = false;

        if(!isset($this->action_data[2])) $this->action_data[2] = false;

        // #############################################################################
        // Planets check

        if(!empty($this->dest['user_id'])) {
            $sql = 'UPDATE ship_fleets
                    SET planet_id = '.$this->move['dest'].',
                        move_id = 0
                    WHERE fleet_id IN ('.$this->fleet_ids_str.')';
                    
            // Here there is a point at which I should be notified
            if(!$this->db->query($sql)) {
                return $this->log(MV_M_DATABASE, 'Could not update fleets location data! CONTINUE');
            }

            $log_data[8] = -1;
            add_logbook_entry($this->move['user_id'], LOGBOOK_TACTICAL, $col_title.$this->dest['planet_name'].$col_fail, $log_data);

            return MV_EXEC_OK;
        }


        // #############################################################################
        // Colonization ship check

        if(empty($this->action_data[0])) {
            return $this->log(MV_M_ERROR, 'action_24: Could not find required action_data entry [0]! SKIP');
        }

        $ship_id = (int)$this->action_data[0];

        $sql = 'SELECT s.ship_id, s.user_id, s.unit_1, s.unit_2, s.unit_3, s.unit_4,
                       st.name, st.race, st.ship_torso, st.min_unit_1, st.min_unit_2, st.min_unit_3, st.min_unit_4,
                       f.fleet_id, f.move_id, f.n_ships
                FROM (ships s)
                INNER JOIN (ship_templates st) ON st.id = s.template_id
                INNER JOIN (ship_fleets f) ON f.fleet_id = s.fleet_id
                WHERE s.ship_id = '.$ship_id;
                
        if(($cship = $this->db->queryrow($sql)) === false) {
            return $this->log(MV_M_DATABASE, 'Could not query cship data! SKIP');
        }

        $cship_exists = true;

        if(empty($cship['ship_id'])) {
            $cship_exists = false;
        }
        elseif($cship['user_id'] != $this->move['user_id']) {
            $cship_exists = false;
        }
        elseif($cship['ship_torso'] != SHIP_TYPE_COLO) {
            $cship_exists = false;
        }
        elseif($cship['move_id'] != $this->mid) {
            $cship_exists = false;
        }

        if(!$cship_exists) {
            $sql = 'UPDATE ship_fleets
                    SET planet_id = '.$this->move['dest'].',
                        move_id = 0
                    WHERE fleet_id IN ('.$this->fleet_ids_str.')';

            // Here there is a point at which I should be notified
            if(!$this->db->query($sql)) {
                $this->log(MV_M_DATABASE, 'Could not update fleets location data! CONTINUE');
            }

            $log_data[8] = -2;
            add_logbook_entry($this->move['user_id'], LOGBOOK_TACTICAL, $col_title.$this->dest['planet_name'].$col_fail, $log_data);

            return MV_EXEC_OK;
        }


        // #############################################################################
        // Terraforming planet!!!

        if((bool)$this->action_data[1]) {

            // Pre-Terraforming Check
            $sql='SELECT resource_3 FROM ship_fleets WHERE fleet_id IN ('.$this->fleet_ids_str.')';
            if(($dilicheck = $this->db->queryrow($sql)) === false) {
                $this->log(MV_M_DATABASE, 'Could not query fleets cargo data! Assumed zero.');
                $dilithium = 0;
            }
            else {
                $dilithium = $dilicheck['resource_3'];
            }

            if($dilithium < 150000) {
                $log_data[8] = -3;
                add_logbook_entry($this->move['user_id'], LOGBOOK_TACTICAL, $ter_title.$this->dest['planet_name'].$ter_fail, $log_data);

                return MV_EXEC_OK;
            }

            $check_clear = true;

            $sql='SELECT planet_type, planet_owner, planet_distance_id FROM planets WHERE planet_id = '.$this->move['dest'];
            if(($_check = $this->db->queryrow($sql)) === false) {
                $this->log(MV_M_DATABASE, 'Could not query terraforming planet data! Assumed invalid.');
                $check_clear = false;
            }
            elseif($_check['planet_owner'] != 0) $check_clear = false;
            elseif($_check['planet_type'] != 'a' && $_check['planet_type'] != 'b' && $_check['planet_type'] != 'c' && $_check['planet_type'] != 'd')$check_clear = false;

            if(!$check_clear) {
                $log_data[8] = -4;
                add_logbook_entry($this->move['user_id'], LOGBOOK_TACTICAL, $ter_title.$this->dest['planet_name'].$ter_fail, $log_data);

                return MV_EXEC_OK;
            }

            // Ok, it seems like all is GREEN, let's do it.

            switch($_check['planet_distance_id']) {
                case 0:
                case 1:
                    $type_probabilities = array(
                        'k' => 35,
                        'e' => 13,
                        'f' => 13,
                        'g' => 13,
                        'n' => 10,
                        'l' => 8,
                        'x' => 5,
                        'y' => 3
                    );
                break;
                case 2:
                case 3:
                    $type_probabilities = array(
                        'l' => 8,
                        'e' => 20,
                        'f' => 20,
                        'g' => 20,
                        'n' >= 15,
                        'm' => 6,
                        'o' => 5,
                        'x' => 4,
                        'y' => 2
                    );
                break;
                case 4:
                case 5:
                case 6:
                case 7:
                    $type_probabilities = array(
                        'h' => 25,
                        'j' => 31,
                        'k' => 15,
                        'n' => 15,
                        'p' => 10,
                        'x' => 3,
                        'y' => 1
                    );
                break;
            }

            $type_array = array();

            foreach($type_probabilities as $type => $probability) {
                for($i = 0; $i < $probability; ++$i) {
                    $type_array[] = $type;
                }
            }

            $planet_type = $type_array[array_rand($type_array)];

            global $PLANETS_DATA;

            // Random variance of the constants basis of the planet
            $rateo_1 = round(($PLANETS_DATA[$planet_type][0] + ((200 - mt_rand(0, 350))*0.001)), 2);
            if($rateo_1 < 0.1) $rateo_1 = 0.1;
            $rateo_2 = round(($PLANETS_DATA[$planet_type][1] + ((200 - mt_rand(0, 350))*0.001)), 2);
            if($rateo_2 < 0.1) $rateo_2 = 0.1;
            $rateo_3 = round(($PLANETS_DATA[$planet_type][2] + ((200 - mt_rand(0, 350))*0.001)), 2);
            if($rateo_3 < 0.1) $rateo_3 = 0.1;
            $rateo_4 = $PLANETS_DATA[$planet_type][3] + 0.05;

            $sql = 'UPDATE planets SET
                           planet_type = "'.$planet_type.'",
                           rateo_1 = '.$rateo_1.',
                           rateo_2 = '.$rateo_2.',
                           rateo_3 = '.$rateo_3.',
                           rateo_4 = '.$rateo_4.',
                           recompute_static = 1
                    WHERE planet_id = '.$this->move['dest'];

            if(!$this->db->query($sql)) {
                return $this->log(MV_M_DATABASE, 'Could not update planets data! SKIP');
            }

            $sql = 'DELETE FROM planet_details WHERE planet_id = '.$this->move['dest'].' AND log_code = 100';

            if(!$this->db->query($sql)) {
                return $this->log(MV_M_DATABASE, 'Could not update planet details data! SKIP');
            }

            $sql = 'UPDATE ship_fleets
                    SET planet_id = '.$this->move['dest'].',
                        move_id = 0,
                        resource_3 = resource_3 - 150000
                    WHERE fleet_id IN ('.$this->fleet_ids_str.')';

            if(!$this->db->query($sql)) {
                return $this->log(MV_M_DATABASE, 'Could not update fleets data! SKIP');
            }

            $log_data[8] = 2;

            add_logbook_entry($this->move['user_id'], LOGBOOK_TACTICAL, $ter_title.$this->dest['planet_name'].$ter_success, $log_data);

            // #############################################################################
            // Add History Record in planet details; log_code = 31 

            $sql = 'INSERT INTO planet_details (planet_id, planet_type, user_id, alliance_id, source_uid, source_aid, timestamp, log_code)
                    VALUES ('.$this->move['dest'].', "'.$planet_type.'",'.$this->move['user_id'].', '.$this->move['user_alliance'].', '.$this->move['user_id'].', '.$this->move['user_alliance'].', '.time().', 31)';

            if(!$this->db->query($sql)) {
                $this->log(MV_M_DATABASE, 'Could not update planet details data!');
            }


            return MV_EXEC_OK;

        }

        // #############################################################################
        // Planet goes to Settlers Community!

        if((bool)$this->action_data[2]) {
            // Target planet MUST BE unsettled!
            if(!empty($this->dest['planet_owner'])) {
                $log_data[8] = -4;
                add_logbook_entry($this->move['user_id'], LOGBOOK_TACTICAL, $ter_title.$this->dest['planet_name'].$ter_fail, $log_data);

                return MV_EXEC_OK;
            }

            // How many planets Settlers already controls?
            $sql = 'SELECT COUNT(planet_id) AS n_planets FROM planets WHERE planet_owner = '.INDEPENDENT_USERID;

            if(($pcount = $this->db->queryrow($sql)) === false) {
                $this->log(MV_M_DATABASE, 'Could not query planets count data! CONTINUE USING INSTABLE VALUE');

                $n_planets = $cur_move['user_planets'];
            }
            else {
                $n_planets = $pcount['n_planets'];
            }

            // All seems ok, let's clear all the queue on the planet
            $sql = 'DELETE FROM scheduler_instbuild WHERE planet_id = '.$this->move['dest'];

            if(!$this->db->query($sql)) {
                $this->log(MV_M_DATABASE, 'Could not delete scheduler instbuild data! CONTINUE');
            }

            $sql = 'DELETE FROM scheduler_shipbuild WHERE planet_id = '.$this->move['dest'];

            if(!$this->db->query($sql)) {
                $this->log('MySQL', 'Could not delete shipbuild data! CONTINUE');
            }

            $sql = 'DELETE FROM scheduler_research WHERE planet_id = '.$this->move['dest'];

            if(!$this->db->query($sql)) {
                $this->log(MV_M_DATABASE, 'Could not delete scheduler research data! CONTINUE');
            }

            $sql = 'UPDATE planets
                    SET planet_owner = '.INDEPENDENT_USERID.',
                    planet_owned_date = '.time().',
                    planet_owner_enum = '.(($n_planets - 1) > 0 ? ($n_planets - 1) : 0).',
                    planet_name = "Colony'.$this->move['dest'].'",
                    npc_last_action = 0,
                    research_1 = 0,
                    research_2 = 0,
                    research_3 = 0,
                    research_4 = 0,
                    research_5 = 0,
                    resource_1 = 100,
                    resource_2 = 100,
                    resource_3 = 100,
                    resource_4 = 100,
                    recompute_static = 1,
                    building_1 = 1,
                    building_2 = 0,
                    building_3 = 0,
                    building_4 = 0,
                    building_5 = 0,
                    building_6 = 0,
                    building_7 = 0,
                    building_8 = 0,
                    building_9 = 0,
                    building_10 = 0,
                    building_11 = 0,
                    building_12 = 0,
                    building_13 = 0,
                    unit_1 = '.(($cship['unit_1'] - $cship['min_unit_1']) + 50).',
                    unit_2 = '.(($cship['unit_2'] - $cship['min_unit_2']) + 25).',
                    unit_3 = '.($cship['unit_3'] - $cship['min_unit_3']).',
                    unit_4 = '.(($cship['unit_4'] - $cship['min_unit_4']) + 1).',
                    unit_5 = 0,
                    unit_6 = 0,
                    workermine_1 = 100,
                    workermine_2 = 100,
                    workermine_3 = 100,
                    catresearch_1 = 0,
                    catresearch_2 = 0,
                    catresearch_3 = 0,
                    catresearch_4 = 0,
                    catresearch_5 = 0,
                    catresearch_6 = 0,
                    catresearch_7 = 0,
                    catresearch_8 = 0,
                    catresearch_9 = 0,
                    catresearch_10 = 0,
                    unittrainid_1 = 0,
                    unittrainid_2 = 0,
                    unittrainid_3 = 0,
                    unittrainid_4 = 0,
                    unittrainid_5 = 0,
                    unittrainid_6 = 0,
                    unittrainid_7 = 0,
                    unittrainid_8 = 0,
                    unittrainid_9 = 0,
                    unittrainid_10 = 0,
                    unittrainnumber_1 = 0,
                    unittrainnumber_2 = 0,
                    unittrainnumber_3 = 0,
                    unittrainnumber_4 = 0,
                    unittrainnumber_5 = 0,
                    unittrainnumber_6 = 0,
                    unittrainnumber_7 = 0,
                    unittrainnumber_8 = 0,
                    unittrainnumber_9 = 0,
                    unittrainnumber_10 = 0,
                    unittrainnumberleft_1 = 0,
                    unittrainnumberleft_2 = 0,
                    unittrainnumberleft_3 = 0,
                    unittrainnumberleft_4 = 0,
                    unittrainnumberleft_5 = 0,
                    unittrainnumberleft_6 = 0,
                    unittrainnumberleft_7 = 0,
                    unittrainnumberleft_8 = 0,
                    unittrainnumberleft_9 = 0,
                    unittrainnumberleft_10 = 0,
                    unittrain_actual = 0,
                    unittrainid_nexttime=0,
                    planet_surrender=0
                WHERE planet_id = '.$this->move['dest'];

            if(!$this->db->query($sql)) {
                return $this->log(MV_M_DATABASE, 'Could not update planets data! SKIP');
            }

            $sql = 'DELETE FROM ships WHERE fleet_id = -'.$this->move['dest'].' OR ship_id = '.$ship_id;

            if(!$this->db->query($sql)) {
                return $this->log(MV_M_DATABASE, 'Could not delete ships data! SKIP');
            }

            if($cship['n_ships'] == 1) {
                $sql = 'DELETE FROM ship_fleets WHERE fleet_id = '.$cship['fleet_id'];
            }
            else {
                $sql = 'UPDATE ship_fleets SET n_ships = n_ships - 1 WHERE fleet_id = '.$cship['fleet_id'];
            }

            if(!$this->db->query($sql)) {
                $this->log(MV_M_DATABASE, 'Could not update/delete cships fleet data! CONTINUE');
            }

            $sql = 'UPDATE ship_fleets SET planet_id = '.$this->move['dest'].', move_id = 0 WHERE fleet_id IN ('.$this->fleet_ids_str.')';

            if(!$this->db->query($sql)) {
                return $this->log(MV_M_DATABASE, 'Could not update fleets data! SKIP');
            }

            $log_data[8] = 1;
            $log_data[9] = $cship['name'];
            $log_data[10] = $cship['race'];

            add_logbook_entry($this->move['user_id'], LOGBOOK_TACTICAL, $col_title.$this->dest['planet_name'].$col_success, $log_data);

            // #############################################################################
            // Add History Record in planet details; log_code = 32

            $sql = 'INSERT INTO planet_details (planet_id, user_id, alliance_id, source_uid, source_aid, timestamp, log_code)
                    VALUES ('.$this->move['dest'].', '.$this->move['user_id'].', '.$this->move['user_alliance'].', '.$this->move['user_id'].', '.$this->move['user_alliance'].', '.time().', 32)';

            if(!$this->db->query($sql)) {
                $this->log(MV_M_DATABASE, 'Could not update planet details data!');
            }

            // #############################################################################
            // Mood Records. We have to add log_code 1,30

            if(isset($this->move['user_alliance']) && !empty($this->move['user_alliance']) && $this->move['user_alliance_status'] > 2)
            {
                // First Contact
                $sql = 'INSERT INTO settlers_relations SET planet_id = '.$this->move['dest'].', race_id = '.$this->move['user_race'].', user_id = '.$this->move['user_id'].', alliance_id = '.$this->move['user_alliance'].', timestamp = '.time().', log_code = 1, mood_modifier = 20';

                if(!$this->db->query($sql)) {
                    return $this->log(MV_M_DATABASE, 'Could not create new settlers relations! SKIP!!!');
                }

                // Colo Founder, +1 to timestap to avoid duplicate key
                $sql = 'INSERT INTO settlers_relations SET planet_id = '.$this->move['dest'].', user_id = '.$this->move['user_id'].', alliance_id = '.$this->move['user_alliance'].', race_id = '.$this->move['user_race'].', timestamp = '.(time() + 1).', log_code = 30, mood_modifier = 80';

                if(!$this->db->query($sql)) {
                    $this->log(MV_M_DATABASE, 'Could not update settlers moods! CONTINUE!');
                }
            }
            else
            {
                // First Contact
                $sql = 'INSERT INTO settlers_relations SET planet_id = '.$this->move['dest'].', race_id = '.$this->move['user_race'].', user_id = '.$this->move['user_id'].', timestamp = '.time().', log_code = 1, mood_modifier = 10';

                if(!$this->db->query($sql)) {
                    return $this->log(MV_M_DATABASE, 'Could not create new settlers relations! SKIP!!!');
                }

                // Colo Founder +1 to timestap to avoid duplicate key
                $sql = 'INSERT INTO settlers_relations SET planet_id = '.$this->move['dest'].', user_id = '.$this->move['user_id'].', race_id = '.$this->move['user_race'].', timestamp = '.(time() + 1).', log_code = 30, mood_modifier = 80';

                if(!$this->db->query($sql)) {
                    $this->log(MV_M_DATABASE, 'Could not update settlers moods! CONTINUE!');
                }
            }

            return MV_EXEC_OK;
        }


        // #############################################################################
        // We need the number of planets, which owns the colonizer
        // (we want to determine planet_owner_enum surely)

        $sql = 'SELECT COUNT(planet_id) AS n_planets
                FROM planets
                WHERE planet_owner = '.$this->move['user_id'];

        if(($pcount = $this->db->queryrow($sql)) === false) {
            $this->log(MV_M_DATABASE, 'Could not query planets count data! CONTINUE USING INSTABLE VALUE');

            $n_planets = $cur_move['user_planets'];
        }
        else {
            $n_planets = $pcount['n_planets'];
        }

        if($n_planets >= $RACE_DATA[$this->move['user_race']][31]) {
            $sql = 'UPDATE ship_fleets
                    SET planet_id = '.$this->move['dest'].',
                        move_id = 0
                    WHERE fleet_id IN ('.$this->fleet_ids_str.')';

            // Here there is a point at which I should be notified
            if(!$this->db->query($sql)) {
                return $this->log(MV_M_DATABASE, 'Could not update fleets location data! CONTINUE');
            }

            $log_data[8] = -3;
            add_logbook_entry($this->move['user_id'], LOGBOOK_TACTICAL, $col_title.$this->dest['planet_name'].$col_fail, $log_data);

            return MV_EXEC_OK;
        }


        // #############################################################################
        // Colonization attempt

        $sql = 'DELETE FROM scheduler_instbuild
                WHERE planet_id = '.$this->move['dest'];
                
        if(!$this->db->query($sql)) {
            $this->log(MV_M_DATABASE, 'Could not delete scheduler instbuild data! CONTINUE');
        }

        $sql = 'DELETE FROM scheduler_shipbuild
                WHERE planet_id = '.$this->move['dest'];

        if(!$this->db->query($sql)) {
            $this->log('MySQL', 'Could not delete shipbuild data! CONTINUE');
        }

        $sql = 'DELETE FROM scheduler_research
                WHERE planet_id = '.$this->move['dest'];
                
        if(!$this->db->query($sql)) {
            $this->log(MV_M_DATABASE, 'Could not delete scheduler research data! CONTINUE');
        }

        $structure_pts = $this->get_structure_points($this->move['user_id'], $this->move['dest']);

        $sql = 'UPDATE planets
                SET planet_owner = '.$this->move['user_id'].',
                    planet_owned_date = '.time().',
                    planet_owner_enum = '.($n_planets - 1).',
                    planet_available_points = '.$structure_pts.',
                    research_1 = 0,
                    research_2 = 0,
                    research_3 = 0,
                    research_4 = 0,
                    research_5 = 0,
                    resource_1 = 50,
                    resource_2 = 50,
                    resource_3 = 0,
                    resource_4 = 10,
                    recompute_static = 1,
                    building_1 = 1,
                    building_2 = 0,
                    building_3 = 0,
                    building_4 = 0,
                    building_5 = 0,
                    building_6 = 0,
                    building_7 = 0,
                    building_8 = 0,
                    building_9 = 0,
                    building_10 = 0,
                    building_11 = 0,
                    building_12 = 0,
                    building_13 = 0,
                    unit_1 = '.($cship['unit_1'] - $cship['min_unit_1']).',
                    unit_2 = '.($cship['unit_2'] - $cship['min_unit_2']).',
                    unit_3 = '.($cship['unit_3'] - $cship['min_unit_3']).',
                    unit_4 = '.($cship['unit_4'] - $cship['min_unit_4']).',
                    unit_5 = 0,
                    unit_6 = 0,
                    workermine_1 = 100,
                    workermine_2 = 100,
                    workermine_3 = 100,
                    catresearch_1 = 0,
                    catresearch_2 = 0,
                    catresearch_3 = 0,
                    catresearch_4 = 0,
                    catresearch_5 = 0,
                    catresearch_6 = 0,
                    catresearch_7 = 0,
                    catresearch_8 = 0,
                    catresearch_9 = 0,
                    catresearch_10 = 0,
                    unittrainid_1 = 0,
                    unittrainid_2 = 0,
                    unittrainid_3 = 0,
                    unittrainid_4 = 0,
                    unittrainid_5 = 0,
                    unittrainid_6 = 0,
                    unittrainid_7 = 0,
                    unittrainid_8 = 0,
                    unittrainid_9 = 0,
                    unittrainid_10 = 0,
                    unittrainnumber_1 = 0,
                    unittrainnumber_2 = 0,
                    unittrainnumber_3 = 0,
                    unittrainnumber_4 = 0,
                    unittrainnumber_5 = 0,
                    unittrainnumber_6 = 0,
                    unittrainnumber_7 = 0,
                    unittrainnumber_8 = 0,
                    unittrainnumber_9 = 0,
                    unittrainnumber_10 = 0,
                    unittrainnumberleft_1 = 0,
                    unittrainnumberleft_2 = 0,
                    unittrainnumberleft_3 = 0,
                    unittrainnumberleft_4 = 0,
                    unittrainnumberleft_5 = 0,
                    unittrainnumberleft_6 = 0,
                    unittrainnumberleft_7 = 0,
                    unittrainnumberleft_8 = 0,
                    unittrainnumberleft_9 = 0,
                    unittrainnumberleft_10 = 0,
                    unittrain_actual = 0,
                    unittrainid_nexttime=0,
                    planet_surrender=0
                WHERE planet_id = '.$this->move['dest'];

        //$this->log('SQL Debug', ''.$sql.'');

        if(!$this->db->query($sql)) {
            return $this->log(MV_M_DATABASE, 'Could not update planets data! SKIP');
        }

        //Concept of vessels destroyed by hostile takeover.
        //Version 0.2b by Mojo1987 - Computation adapted

        $this->log(MV_M_NOTICE, 'Start ships deletion');

        $sql = 'SELECT s.ship_id FROM (ships s) WHERE s.fleet_id = -'.$this->move['dest'].'';

        if(!$del_ship = $this->db->query($sql)) {
           $this->log(MV_M_DATABASE, 'Could not query planets ships! CONTINUE - '.$sql.'');
        }

        while($ship_wahl = $this->db->fetchrow($del_ship)) {

          $zufall = mt_rand(6,18);

          if($zufall>=8) {

            $sql = 'DELETE FROM ships WHERE ship_id = '.$ship_wahl['ship_id'].'';

            if(!$this->db->query($sql)) {
                $this->log(MV_M_DATABASE, 'Could not query deleted ship! CONTINUE');
            }
            else { $this->log(MV_M_NOTICE, 'Ship_ID: '.$ship_wahl['ship_id'].' Random number: '.$zufall.' <b> SUCCESS!</b>'); }
          }

        }
        $this->log(MV_M_NOTICE, 'Delete terminated');


        $sql = 'DELETE FROM ships
                WHERE fleet_id = -'.$this->move['dest'].' OR
                      ship_id = '.$ship_id;

        if(!$this->db->query($sql)) {
            return $this->log(MV_M_DATABASE, 'Could not delete ships data! SKIP');
        }

        if($cship['n_ships'] == 1) {
            $sql = 'DELETE FROM ship_fleets
                    WHERE fleet_id = '.$cship['fleet_id'];
        }
        else {
            $sql = 'UPDATE ship_fleets
                    SET n_ships = n_ships - 1
                    WHERE fleet_id = '.$cship['fleet_id'];
        }

        if(!$this->db->query($sql)) {
            $this->log(MV_M_DATABASE, 'Could not update/delete cships fleet data! CONTINUE');
        }


        // #############################################################################
        // Fleets positioning

        $sql = 'UPDATE ship_fleets
                SET planet_id = '.$this->move['dest'].',
                    move_id = 0
                WHERE fleet_id IN ('.$this->fleet_ids_str.')';
                
        if(!$this->db->query($sql)) {
            return $this->log(MV_M_DATABASE, 'Could not update fleets data! SKIP');
        }


        // #############################################################################
        // Write Logbook

        $log_data[8] = 1;
        $log_data[9] = $cship['name'];
        $log_data[10] = $cship['race'];

        add_logbook_entry($this->move['user_id'], LOGBOOK_TACTICAL, $col_title.$this->dest['planet_name'].$col_success, $log_data);

        // #############################################################################
        // Add History Record in planet details; log_code = 25

        $sql = 'INSERT INTO planet_details (planet_id, user_id, alliance_id, source_uid, source_aid, timestamp, log_code)
                VALUES ('.$this->move['dest'].', '.$this->move['user_id'].', '.$this->move['user_alliance'].', '.$this->move['user_id'].', '.$this->move['user_alliance'].', '.time().', 25)';

        if(!$this->db->query($sql)) {
            $this->log(MV_M_DATABASE, 'Could not update planet details data!');
        }


        return MV_EXEC_OK;
    }
}

?>
