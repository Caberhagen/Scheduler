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

class moves_action_27 extends moves_common {

    function do_simple_relation($user_id, $planet_id, $log_code, $mood_modifier)
    {
    
        $sql='SELECT * FROM settlers_relations WHERE planet_id = '.$planet_id.' AND user_id = '.$user_id.' AND log_code = '.$log_code;

        $pre_data = $this->db->queryrow($sql);
            
        if(!isset($pre_data['mood_modifier']))
        {
            $sql='INSERT INTO settlers_relations SET planet_id = '.$planet_id.', user_id = '.$user_id.',timestamp = '.time().',log_code = '.$log_code.', mood_modifier = '.$mood_modifier;
        }
        else
        {
            $new_mood = min(80, ($pre_data['mood_modifier'] + $mood_modifier));
                
            $sql='UPDATE settlers_relations SET mood_modifier = '.$new_mood.', timestamp = '.time().'
                    WHERE planet_id = '.$planet_id.' AND user_id = '.$user_id.' AND log_code = '.$log_code;                
        }
            
        $this->db->query($sql);
    }

    function fetch_founder_mood($planet_id)
    {
        $sql = 'SELECT mood_modifier, user_id FROM settlers_relations WHERE log_code = '.LC_COLO_FOUNDER.' AND planet_id = '.$planet_id;
        $founder_query = $this->db->queryrow($sql);
        if(isset($founder_query['mood_modifier'])) {
            return(array($founder_query['mood_modifier'], $founder_query['user_id']));
        }
        else {
            return(array(-1, -1));
        }
    }

    function update_founder_mood($planet_id, $mood_modifier)
    {
        $sql = 'SELECT mood_modifier, user_id FROM settlers_relations WHERE log_code = '.LC_COLO_FOUNDER.' AND planet_id = '.$planet_id;
        $founder_query = $this->db->queryrow($sql);        
        $new_mood = max(0, ($founder_query['mood_modifier'] - $mood_modifier));
        if($new_mood > 0)
        {
            $sql='UPDATE settlers_relations SET mood_modifier = '.$new_mood.' WHERE log_code = '.LC_COLO_FOUNDER.' AND planet_id = '.$planet_id;
            $this->db->query($sql);
        }
    }

    function get_mood_text_string($log_code, $mood_modifier)
    {
    $text_lines = array(
            1  => 'Primo Contatto',
            2  => 'Trattato Diplomatico',
            3  => 'Supporto Tecnologico: Modifiche Ambientali',
            4  => 'Supporto Tecnologico: Ricerca Medica',
            5  => 'Supporto Tecnologico: Difesa',
            6  => 'Supporto Tecnologico: Automazione',
            7  => 'Supporto Tecnologico: Estrazione',
            8  => 'Supporto: Difese Planetarie',
            10 => 'Multiculturalismo',
            11 => 'Supremazia Tecnologica',
            12 => 'Campione',
            13 => 'Innovatore',
            14 => 'Oppositore',
            15 => 'Pluralista',
            16 => 'Prestigio',
            17 => 'Difensore',
            18 => 'Competente',
            19 => 'Liberatore',
            20 => 'Leader',
            21 => 'Mecenate',
            22 => 'Venerato',
            23 => 'Predatore',
            24 => 'Straniero',
            25 => 'Incapace',
            26 => 'Sfruttatore',
            27 => 'Preda',
            30 => 'Fondatore',
            31 => 'Ex-Governatore',
            32 => 'Attacco Orbitale',
            33 => 'Bombardamento Planetario',
            34 => 'Conquista di una Colonia'
    );

        $text_string = '<tr><td>'.$text_lines[$log_code].'</td><td>:</td><td>'.$mood_modifier.'</td></tr>';

        return($text_string);
    }
    
    function _action_main() {

        global $PLANETS_DATA, $RACE_DATA, $TECH_DATA, $TECH_NAME, $MAX_RESEARCH_LVL, $ACTUAL_TICK, $cfg_data;

        // #############################################################################
        // Away Team Missions!!!
        //
        // Beam us down! Energy!
        //
        //#############################################################################

        $tech_reward = array(5,7,7,7,10,10,10,12,12);

        // cache_mood è un array che raccoglie tutte le modifiche da apportare alla settlers_relations e viene svuotata alla fine
        // e inizializziamolo sto array, sennò php scassa le balle...

        $cache_mood = array(3 => 0, 4 => 0, 5 => 0, 6 => 0, 7 => 0, 8 => 0, 10 => 0, 11 => 0, 12 => 0, 13 => 0, 14 => 0, 15 => 0,
                            16 => 0, 17 => 0, 18 => 0, 19 => 0, 20 => 0, 21 => 0, 22 => 0, 23 => 0, 24 => 0, 25 => 0, 26 => 0, 27 => 0);

        $cache_event_sql = array();

        // $decrease_founder è un flag che ci dice, a fine missione, se dobbiamo abbassare il mood del Founder
        
        $decrease_founder = 0;

        $is_first_contact = false;
        $is_diplo_speech = false;

        $sql = 'SELECT s.ship_name, s.experience, s.awayteam, s.ship_id,
                        s.unit_1, s.unit_2, s.unit_3, s.unit_4,
                        t.name, t.min_unit_1, t.min_unit_2, t.min_unit_3, t.min_unit_4,
                        t.max_unit_1, t.max_unit_2, t.max_unit_3, t.max_unit_4
                        FROM ship_fleets f
                        LEFT JOIN ships s ON s.fleet_id = f.fleet_id
                        LEFT JOIN ship_templates t ON t.id = s.template_id
                WHERE f.fleet_id IN ('.$this->fleet_ids_str.')';

        if(($ship_details = $this->db->queryrow($sql)) == false) {
            $this->log(MV_M_DATABASE, 'Could not read ship name! CONTINUE');
            $name_of_ship = '<i>Sconosciuto</i>';
        }
        else {
            if(!empty($ship_details['ship_name'])) {
                $name_of_ship = '<b>'.$ship_details['ship_name'].'</b>';
            }
            else {
                $name_of_ship = '<b><i>&#171;'.$ship_details['name'].'&#187;</i></b>';
            }
        }

        // Check Action Code
        if(!isset($this->action_data[0])) {
            // first item tell us the kind of mission we are going to do
            $this->log(MV_M_DATABASE, 'action_27: Could not find required action_data entry [0]! FORCED TO ZERO');
            $this->action_data[0] = 0;
        }

        if($this->action_data[0] == 3 && !isset($this->action_data[1])) {
            // second item tell us more on the mission target
            // for tech mission:
            // - 0 = research_1 (higher rooms for ppl on planet, higher labourer rate)
            // - 1 = research_2 (higher labourer rate, will not be implemented)
            // - 2 = research_3 (better defense protection)
            // - 3 = research_4 (optimization of accademy, shipyards)
            // - 4 = research_5 (higher mines output)
            $this->log(MV_M_NOTICE, 'action_27: Could not find mission parameters [1]! FORCED TO -1');
            $this->action_data[1] = -1;
        }
        
        /**
         * Check planet's mood.
         */

        $sql = 'SELECT log_code FROM settlers_relations WHERE log_code IN (1, 2) AND planet_id = '.$this->move['dest'].' AND user_id = '.$this->move['user_id'];
        
        $log_query = $this->db->query($sql);
        
        $log_rows = $this->db->num_rows($log_query);
        
        if($log_rows > 0) {
            $log_list = $this->db->fetchrowset($log_query);
            foreach($log_list AS $log_item) {
                switch($log_item['log_code']) {
                    case 1:
                        $is_first_contact = true;
                        break;
                    case 2:
                        $is_diplo_speech = true;
                        break;
                }
            }
        }
        
        // Player mood
        $sql = 'SELECT SUM(mood_modifier) AS mood_user
                FROM settlers_relations
                WHERE planet_id = '.$this->move['dest'].' AND
                      user_id = '.$this->move['user_id'];
        if(($mood_user = $this->db->queryrow($sql)) === false) {
            return $this->log(MV_M_DATABASE, 'Could not read planet data! SKIP');
        }

        $mood['user'] = $mood_user['mood_user'];


        $mood['value'] = (!empty($mood['race']) ? $mood['race'] : 0) +
                         (!empty($mood['alliance']) ? $mood['alliance'] : 0) +
                         (!empty($mood['user']) ? $mood['user'] : 0);

        // Event check section
        // EVENT TABLE FORMAT
        // planet_id
        // user_id
        // event_code                                   < il codice ci dice anche se un evento è VISIBILE o NON VISIBILE
        // timestamp                                    < data e ora inizio evento
        // tick                                         < TICK inizio evento
        // awayteamship_id                              < id della nave che ha sbarcato la squadra
        // awayteam_startlevel                          < livello iniziale della squadra
        // unit_1                                       <
        // unit_2                                       <
        // unit_3                                       <
        // unit_4                                       <
        // awayteam_alive                               < 0 = la squadra è persa, 1 = la squadra è viva e recuperabile
        // event_status                                 < 0 = evento non più attivo, 1 = evento attivo
        // event_result                                 < 0 = evento concluso negativamente (bloccato), 1 = evento concluso positivamente
        // count_ok                                   <-----
        // count_crit_ok                              <   contatori usati per il calcolo dell'exp guadagnata al momento del recupero
        // count_ko                                   <
        // count_crit_ko                              <------
        

        $sql = 'SELECT * FROM settlers_events WHERE planet_id = '.$this->move['dest'].' AND event_status = 1 ORDER BY timestamp ASC, event_code ASC';

        $ev_query = $this->db->query($sql);
        $ev_num = $this->db->num_rows($ev_query);

        if($ev_num > 0)
        {
            $ev_list = $this->db->fetchrowset($ev_query);

            // Scansione della lista eventi, ordinati per cronologico e event_code. Eventi con codice basso sono prioritari e possono bloccare
            // la scansione della lista e l'esecuzione della mossa

            $halt_mission = FALSE;
            $res = $this->fetch_founder_mood($this->move['dest']);
            foreach($ev_list AS $event_row)
            {
                $new_event_code    = $event_row['event_code'];
                $new_event_status  = $event_row['event_status'];
                $new_event_result  = $event_row['event_result'];
                $new_count_ok      = $event_row['count_ok'];
                $new_count_ko      = $event_row['count_ko'];
                $new_count_crit_ok = $event_row['count_crit_ok'];
                $new_count_crit_ko = $event_row['count_crit_ko'];
                $event_delete = FALSE;
                $changed = FALSE;
                
                switch($event_row['event_code'])
                {
                    case '100': // Terreno di Caccia - NASCOSTO. Si aggiorna il codice evento sul db e si esegue il codice 101
                        if($event_row['user_id'] == $this->move['user_id']) break;
                        $new_event_code = '101';
                        $sql = 'UPDATE settlers_events SET event_code = '.$new_event_code.' WHERE planet_id = '.$this->move['dest'].' AND event_code = '.$event_row['event_code'].' AND user_id = '.$event_row['user_id'];
                        if(!$this->db->query($sql)) {
                            return $this->log(MV_M_DATABASE, 'Could not update ship AT level!!! '.$sql);
                        }
                    case '101': // Terreno di Caccia - Può bloccare la missione - NON usa l'array cache_mood e scrive direttamente
                        if($event_row['user_id'] == $this->move['user_id']) break;
                        if($this->action_data[0] == 5) break;
                        $critical_test = rand(0, 10000);
                        $test_value = $ship_details['awayteam'] - $event_row['awayteam_startlevel'];
                        $log_data1 = array($this->move['dest'],$this->dest['planet_name'], $event_row['awayteamship_id'], 'N/A', 107, 0);
                        $log_title1 = 'Rapporto dei cacciatori su '.$this->dest['planet_name'];
                        $log_data2 = array($this->move['dest'],$this->dest['planet_name'], $ship_details['ship_id'], $name_of_ship, 106, 0);                        
                        if($test_value > 0)
                        {

                            $critical_ko_chance = intval(7500 - (180 * ($ship_details['awayteam'] - $event_row['awayteam_startlevel'])));
                            if($critical_test > $critical_ko_chance)
                            {
                                // La missione NEUTRALIZZA il TdC
                                $log_title2 = 'La tua missione su '.$this->dest['planet_name'].' ha subito un agguato!';
                                $log_data1[5] = $log_data2[5] = -2;
                                $event_delete = TRUE;
                                $sql = 'UPDATE ships SET awayteam = 1, awayteamplanet_id = 0 WHERE ship_id = '.$event_row['awayteamship_id'];
                                if(!$this->db->query($sql)) {
                                    return $this->log(MV_M_DATABASE, 'Could not update ship AT level!!! '.$sql);
                                }                                
                            }
                            else
                            {
                                // La missione NON SUPERA il Terreno di Caccia
                                $changed = TRUE;
                                $log_title2 = 'La tua missione su '.$this->dest['planet_name'].' &egrave; fallita!';                                
                                $this->do_simple_relation($event_row['user_id'], $event_row['planet_id'], LC_REL_PREDAT, 5);
                                $this->do_simple_relation($this->move['user_id'], $event_row['planet_id'], LC_REL_PREY, -5);
                                $new_count_ok += 1;
                                $halt_mission = TRUE;
                            }
                        }
                        else
                        {
                            $changed = TRUE;
                            $log_title2 = 'La tua missione su '.$this->dest['planet_name'].' &egrave; fallita!';
                            // La missione NON SUPERA il Terreno di Caccia e viene uccisa!
                            $this->do_simple_relation($event_row['user_id'], $event_row['planet_id'], LC_REL_PREDAT, 10);
                            $this->do_simple_relation($this->move['user_id'], $event_row['planet_id'], LC_REL_PREY, -10);
                                $sql = 'UPDATE ships SET unit_1 = '.$ship_details['min_unit_1'].', unit_2 = '.$ship_details['min_unit_2'].', unit_3 = '.$ship_details['min_unit_3'].', unit_4 = '.$ship_details['min_unit_4'].', awayteam = 1 WHERE ship_id = '.$ship_details['ship_id'];
                            if(!$this->db->query($sql)) {
                                return $this->log(MV_M_DATABASE, 'Could not create new settlers relations! SKIP!!!');
                            }
                            $log_data1[5] = $log_data2[5] = 1;
                            $new_count_crit_ok += 1;                            
                            $halt_mission = TRUE;
                        }
                        add_logbook_entry($event_row['user_id'], LOGBOOK_SETTLERS, $log_title1, $log_data1);
                        add_logbook_entry($this->move['user_id'], LOGBOOK_SETTLERS, $log_title2, $log_data2);
                        break;
                    case '102': // Razziatori in attesa NASCOSTO
                        if($event_row['user_id'] == $this->move['user_id']) break;
                        if($this->action_data[0] != 3) break; // Rimane nascosto finché non arriva una missione di supporto
                        $new_event_code = '103';
                        $sql = 'UPDATE settlers_events SET event_code = '.$new_event_code.' WHERE planet_id = '.$this->move['dest'].' AND event_code = '.$event_row['event_code'].' AND user_id = '.$event_row['user_id'];
                        if(!$this->db->query($sql)) {
                            return $this->log(MV_M_DATABASE, 'Could not update ship AT level!!! '.$sql);
                        }                        
                    case '103': // Razziatori in attesa. Può bloccare la missione - NON usa l'array cache_mood e scrive direttamente
                                // Attende lo sbarco a terra di altre squadre nel tentativo di sottrarre la nave al proprietario
                        if($event_row['user_id'] == $this->move['user_id']) break;
                        if($this->action_data[0] == 5) break;
                        $check = rand(0, 1000);
                        $log_data1 = array($this->move['dest'],$this->dest['planet_name'], $event_row['awayteamship_id'], 'N/A', 109, 0);
                        $log_title1 = 'Rapporto della squadra su '.$this->dest['planet_name'];
                        $log_data2 = array($this->move['dest'],$this->dest['planet_name'], $ship_details['ship_id'], $name_of_ship, 108, 0);                        
                        $test_value =  $event_row['awayteam_startlevel'] - $ship_details['awayteam'];
                        if($test_value > 0) 
                        {
                            $success_test = $test_value * 1.334 * 10;
                            if($success_test > $check) {
                                // Successo CRITICO! La nave avversaria viene CATTURATA
                                $log_title2 = 'La tua missione su '.$this->dest['planet_name'].' &egrave; fallita!';
                                $this->do_simple_relation($event_row['user_id'], $event_row['planet_id'], LC_REL_PREDAT, 10);
                                $this->do_simple_relation($this->move['user_id'], $event_row['planet_id'], LC_REL_PREY, -10);
                                $newatlevel = $event_row['awayteam_startlevel'] + $event_row['count_ok'] * 2.0 + 15.0;
                                $sql = 'UPDATE ships SET unit_1 = '.$event_row['unit_1'].', unit_2 = '.$event_row['unit_2'].', unit_3 = '.$event_row['unit_3'].', unit_4 = '.$event_row['unit_4'].',
                                                         awayteam = '.$newatlevel.', user_id = '.$event_row['user_id'].', awayteamplanet_id = 0 WHERE ship_id = '.$ship_details['ship_id'];
                                if(!$this->db->query($sql)) {
                                    return $this->log(MV_M_DATABASE, 'Could not update captured ship data! SKIP!!! '.$sql);
                                }
                                $sql = 'UPDATE ship_fleets SET user_id = '.$event_row['user_id'].', fleet_name = "Catturata" WHERE fleet_id = '.$ship_details['fleet_id'];
                                if(!$this->db->query($sql)) {
                                    return $this->log(MV_M_DATABASE, 'Could not update captured fleet data! SKIP!!! '.$sql);
                                }
                                $sql = 'UPDATE ships SET awayteam = 1, awayteamplanet_id = 0 WHERE ship_id = '.$event_row['awayteamship_id'];
                                if(!$this->db->query($sql)) {
                                    return $this->log(MV_M_DATABASE, 'Could not update ship AT level!!! '.$sql);
                                }
                                $log_data1[5] = $log_data2[5] = 1;
                                $event_delete = TRUE;
                                $halt_mission = TRUE;                                
                            }
                            else {
                                // Successo! La squadra avversaria è battuta e viene respinta
                                $log_title2 = 'La tua missione su '.$this->dest['planet_name'].' &egrave; fallita!';                                
                                $this->do_simple_relation($event_row['user_id'], $event_row['planet_id'], LC_REL_PREDAT, 5);
                                $this->do_simple_relation($this->move['user_id'], $event_row['planet_id'], LC_REL_PREY, -5);                                
                                $changed = TRUE;
                                $new_count_ok += 1;
                                $halt_mission = TRUE;
                            }
                        }
                        else
                        {
                            $failure_test = -1 * ($test_value * 1.334 * 10);
                            if($failure_test > $check) {
                                // Fallimento CRITICO! I razziatori vengono UCCISI!
                                $log_title2 = 'La tua missione su '.$this->dest['planet_name'].' ha subito un agguato!';
                                $log_data1[5] = $log_data2[5] = -2;
                                $event_delete = TRUE;
                                $sql = 'UPDATE ships SET awayteam = 1, awayteamplanet_id = 0 WHERE ship_id = '.$event_row['awayteamship_id'];
                                if(!$this->db->query($sql)) {
                                    return $this->log(MV_M_DATABASE, 'Could not update ship AT level!!! '.$sql);
                                }                                
                            }
                            else {
                                // Fallimento!!! I razziatori non riescono a battere la squadra.
                                $log_title2 = 'La tua missione su '.$this->dest['planet_name'].' ha subito un agguato!';
                                $log_data1[5] = $log_data2[5] = -1;
                                $changed = TRUE;                                
                                $new_count_ko += 1;
                            }
                        }
                        break;
                    case '120':
                        // Presidio Federale.
                        if($this->action_data[0] != 3 && $this->action_data[0] != 4) break;
                        $new_count_ok += 1;
                        $new_event_result = 1;
                        $changed = TRUE;
                        $cache_mood[LC_REL_MULTIC] += ($res[1] == $this->move['user_id'] ? 2 : 4);
                    break;
                    case '121':
                        // Presidio Romulano.
                        if($this->action_data[0] != 3) break;
                        if($res[1] == $this->move['user_id'])
                        {
                            $cache_mood[LC_REL_TSUPER] += 3;
                            $new_count_ok += 1;
                            $new_event_result = 1;
                            $changed = TRUE;
                        }
                    break;
                    case '122':
                        // Presidio Klingon.
                        if($this->action_data[0] != 3 && $this->action_data[0] != 4) break;
                        if($res[1] == $this->move['user_id'])
                        {
                            switch($this->action_data[0])
                            {
                                case 3:
                                    $cache_mood[LC_REL_CHAMPN] += 4;
                                break;
                                case 4:
                                    $cache_mood[LC_REL_DEFNDR] += 8;
                                break;
                            }
                            $new_count_ok += 1;
                            $new_event_result = 1;
                            $changed = TRUE;
                            break;
                        }
                        if($ship_details['awayteam'] > $event_row['awayteam_startlevel'])
                        {
                            $cache_mood[LC_REL_CHAMPN] += 4;
                            $new_count_ko += 1;
                            $changed = TRUE;                            
                        }
                    break;
                    case '123':
                        // Presidio Cardassiano
                        if($this->action_data[0] != 3 && $this->action_data[0] != 4) break;
                        if($res[1] == $this->move['user_id'])
                        {
                            switch($this->action_data[0])
                            {
                                case 3:
                                    $cache_mood[LC_REL_INNVTR] += 2;
                                    break;
                                case 4:
                                    $cache_mood[LC_REL_INNVTR] += 3;
                                    break;
                            }
                            $new_count_ok += 1;
                            $new_event_result = 1;
                            $changed = TRUE;                            
                            break;
                        }
                        if($event_row['awayteam_startlevel'] > $ship_details['awayteam'])
                        {
                            $cache_mood[LC_REL_STRNGR] -= 5;
                            $new_count_crit_ok += 1;
                            $new_event_result = 1;
                            $changed = TRUE;                            
                        }
                        else
                        {
                            $cache_mood[LC_REL_INNVTR] += 5;
                            $new_count_ko += 1;                            
                            $changed = TRUE;                            
                        }
                    break;
                    case '124':
                        // Presidio Dominion
                        if($this->action_data[0] != 3 && $this->action_data[0] != 4) break;
                        if($res[1] == $this->move['user_id'])
                        {
                            $cache_mood[LC_REL_WORSHP] += 2;
                            $new_count_ok += 1;
                            $new_event_result = 1;
                            $changed = TRUE;                            
                            break;
                        }
                        
                        if($ship_details['awayteam'] > $event_row['awayteam_startlevel']) 
                        {
                            $cache_mood[LC_REL_OPPSTR] +=5;
                            $new_count_ko += 1;
                            $changed = TRUE;                                                           
                        }
                    break;
                    case '130':
                        // Pluralismo
                        if($this->action_data[0] != 3) break;
                        $sql = 'SELECT COUNT(log_code) AS speech_count FROM settlers_relations WHERE user_id <> '.$res[1].' AND planet_id = '.$this->move['dest'].' AND log_code = '.LC_DIPLO_SPEECH;
                        $r1 = $this->db->queryrow($sql);
                        if($res[1] == $this->move['user_id'] && $r1['speech_count'] > 0)
                        {
                            $tech_data = $this->action_data[1];
                            switch($tech_data)
                            {
                                case 0:
                                    $cache_mood[LC_SUP_TECH] -= (1 * min($r1['speech_count'], 3));
                                break;
                                case 1:
                                    $cache_mood[LC_SUP_MEDI] -= (1 * min($r1['speech_count'], 3));
                                break;
                                case 2:
                                    $cache_mood[LC_SUP_DFNS] -= (1 * min($r1['speech_count'], 3));
                                break;
                                case 3:
                                    $cache_mood[LC_SUP_AUTO] -= (1 * min($r1['speech_count'], 3));
                                break;
                                case 4:
                                    $cache_mood[LC_SUP_MINE] -= (1 * min($r1['speech_count'], 3));
                                break;
                            }
                            $new_count_crit_ok += 1;
                            $new_event_result = 1;
                            $changed = TRUE;                            
                            break;
                        }
                        if($event_row['user_id'] == $this->move['user_id'])
                        {
                            // $decrease_founder += 3;
                            $new_count_ok += 1;
                            $new_event_result = 1;
                            $changed = TRUE;                            
                        }
                        $cache_mood[LC_REL_PLURLS] += 3;
                    break;
                    case '131':
                        // Orgoglio del Pretore
                        if($this->action_data[0] != 3) break;
                        if($event_row['user_id'] == $this->move['user_id'])
                        {
                            // $decrease_founder += 3;
                            $cache_mood[LC_REL_PRESTG] += 4;
                            $new_count_ok += 1;
                            $new_event_result = 1;
                            $changed = TRUE;
                            break;
                        }
                        if($res[1] == $this->move['user_id'])
                        {
                            $sql = 'SELECT research_'.($this->action_data[1] + 1).' AS tech_level FROM planets p INNER JOIN user u ON u.user_capital = p.planet_id WHERE user_id = '.$res[1];
                            $r1 = $this->db->queryrow($sql);
                            $sql = 'SELECT research_'.($this->action_data[1] + 1).' AS tech_level FROM planets p INNER JOIN user u ON u.user_capital = p.planet_id WHERE user_id = '.$event_row['user_id'];
                            $r2 = $this->db->queryrow($sql);
                            if(empty($r1['tech_level']) || empty($r2['tech_level']) || ($r1['tech_level'] > $r2['tech_level']))
                            {
                                // $decrease_founder += 3;
                                $cache_mood[LC_REL_PRESTG] += 5;
                                $new_count_ko += 1;
                                $changed = TRUE;
                            }
                            else
                            {
                                // $decrease_founder += 5;
                                $this->do_simple_relation($event_row['user_id'], $event_row['planet_id'], LC_REL_PRESTG, 5);
                                $new_count_crit_ok += 1;
                                $changed = TRUE;                                
                            }
                        }
                    break;
                    case '132':
                        // Addestramento Autodifesa
                        if($this->action_data[0] != 4) break;
                        if($event_row['user_id'] == $this->move['user_id'])
                        {
                            // $decrease_founder += 8;
                            $cache_mood[LC_REL_DEFNDR] += 8;
                            $new_count_ok += 1;
                            $new_event_result = 1;
                            $changed = TRUE;
                        }
                    break;
                    case '133':
                        // Propaganda Sovversiva
                        if($this->action_data[0] != 3 && $this->action_data[0] != 4) break;
                        if(($res[1] == $this->move['user_id']) && ($event_row['awayteam_startlevel'] > $ship_details['awayteam']))
                        {
                            $this->do_simple_relation($event_row['user_id'], $event_row['planet_id'], LC_REL_LIBERT, 5);
                            $cache_mood[LC_REL_EXPLOI] -= 3;
                            $new_count_crit_ok += 1;
                            $new_event_result = 1;
                            $changed = TRUE;                            
                        }
                        if($event_row['user_id'] == $this->move['user_id'])
                        {
                            // $decrease_founder += 2;
                            $cache_mood[LC_REL_LEADER] += 4;
                            $new_count_ok += 1;
                            $new_event_result = 1;
                            $changed = TRUE;
                        }
                    break;
                    case '134':
                        // Denunciare Incompetenza
                        if($this->action_data[0] != 3 && $this->action_data[0] != 4) break;
                        if(($res[1] == $this->move['user_id']) && ($event_row['awayteam_startlevel'] > $ship_details['awayteam']))
                        {
                            $this->do_simple_relation($event_row['user_id'], $event_row['planet_id'], LC_REL_CMPTNT, 5);
                            $cache_mood[LC_REL_UNABLE] -= 3;
                            $new_count_crit_ok += 1;
                            $new_event_result = 1;
                            $changed = TRUE;
                        }
                        if($event_row['user_id'] == $this->move['user_id'])
                        {
                            // $decrease_founder += 2;
                            $cache_mood[LC_REL_MECENA] += 4;
                            $new_count_ok += 1;
                            $new_event_result = 1;
                            $changed = TRUE;
                        }
                    break;
                }

                if($event_delete)
                {
                    $sql = 'DELETE FROM settlers_events WHERE planet_id = '.$this->move['dest'].' AND event_code = '.$event_row['event_code'].' AND user_id = '.$event_row['user_id'];
                    $this->db->query($sql);
                    $changed = false;
                }
                if($changed)
                {
                    $cache_event_sql[] = 'UPDATE settlers_events SET event_code = '.$new_event_code.', event_status = '.$new_event_status.', event_result = '.$new_event_result.',
                                                       count_ok = '.$new_count_ok.', count_ko = '.$new_count_ko.', count_crit_ok = '.$new_count_crit_ok.', count_crit_ko = '.$new_count_crit_ko.'
                                   WHERE planet_id = '.$this->move['dest'].' AND event_code = '.$event_row['event_code'].' AND user_id = '.$event_row['user_id'];
                }
                if($halt_mission)
                {
                    if(count($cache_event_sql) > 0)
                        foreach($cache_event_sql AS $cached_query) {
                            if(!$this->db->query($cached_query)) {
                                return $this->log(MV_M_DATABASE, 'Could not update event data. SKIP!!! '.$cached_query);
                            }
                        }
                    $this->action_data[0] = 99;
                    break;
                }
            }
            // if($decrease_founder > 0) $this->update_founder_mood($this->move['dest'], $decrease_founder);
        }

        
        // Diplo Section
        // In this section, good things happens
        switch($this->action_data[0]){
            /**
             * First contact mission.
             */
            case 0:
                switch($this->move['language'])
                {
                    case 'GER':
                        $f_c_title = 'First contact on ';
                        $f_c_fail = ' failed';
                        $f_c_success = ' done';
                    break;
                    case 'ITA':
                        $f_c_title = 'Primo contatto su ';
                        $f_c_fail = ' fallito';
                        $f_c_success = ' avvenuto';
                    break;
                    default:
                        $f_c_title = 'First contact on ';
                        $f_c_fail = ' failed';
                        $f_c_success = ' done';
                    break;
                }

                $log_data = array(
                    27,
                    $this->move['user_id'],
                    $this->move['start'],
                    $this->start['planet_name'],
                    $this->start['user_id'],
                    $this->move['dest'],
                    $this->dest['planet_name'],
                    $this->dest['user_id'],
                    0
                );

                $log_data[8] = array(
                    'mission_type'   => 0,
                    'mission_result' => 0,
                );

                if($is_first_contact) {
                    // Invalid move. 
                    $log_data[8]['mission_result'] = -1;
                    add_logbook_entry($this->move['user_id'], LOGBOOK_TACTICAL_2, $f_c_title.$this->dest['planet_name'].$f_c_fail, $log_data);
                }
                else
                {
                    if($mood['value'] >= 0)  {
                        // Mission successfully
                        // Insert First Contact record, log_code = 1
                        // If the player is President or Diplomatic, add alliance mood value
                        if(isset($this->move['user_alliance']) && !empty($this->move['user_alliance']) && $this->move['user_alliance_status'] > 2) {
                            $sql = 'INSERT INTO settlers_relations
                                    SET planet_id = '.$this->move['dest'].',
                                        race_id = '.$this->move['user_race'].',
                                        user_id = '.$this->move['user_id'].',
                                        alliance_id = '.$this->move['user_alliance'].',
                                        timestamp = '.time().',
                                        log_code = '.LC_FIRST_CONTACT.',
                                        mood_modifier = 20';
                            
                            if(!$this->db->query($sql)) {
                                return $this->log(MV_M_DATABASE, 'Could not create new settlers relations! SKIP!!!');
                            }
                        }
                        else {
                            $sql = 'INSERT INTO settlers_relations
                                    SET planet_id = '.$this->move['dest'].',
                                        race_id = '.$this->move['user_race'].',
                                        user_id = '.$this->move['user_id'].',
                                        timestamp = '.time().',
                                        log_code = '.LC_FIRST_CONTACT.',
                                        mood_modifier = 10';

                            if(!$this->db->query($sql)) {
                                return $this->log(MV_M_DATABASE, 'Could not create new settlers relations! SKIP!!!');
                            }
                        }

                        // Calculate Exp of the mission
                        
                        if($ship_details['experience'] < 75) {
                            $actual_exp = $ship_details['experience'];
                            $exp = (2.7/((float)$actual_exp*0.0635))+1.5;
                            $sql = 'UPDATE ships SET experience = experience+'.$exp.' WHERE ship_id = '.$ship_details['ship_id'];
                            if(!$this->db->query($sql)) {
                                return $this->log(MV_M_DATABASE, 'Could not update ship exp! SKIP');
                            }
                        }
                        
                        $awayteamlvl = round($ship_details['awayteam'], 0);
                        $exp_award = round(2.56 / $awayteamlvl, 3);
                        $ship_details['awayteam'] += $exp_award;
                        $sql = 'UPDATE ships SET awayteam = '.$ship_details['awayteam'].' WHERE ship_id = '.$ship_details['ship_id'];
                        if(!$this->db->query($sql)) {
                            return $this->log(MV_M_DATABASE, 'Could not update ship exp! SKIP');
                        }
                        
                        $log_data[8]['mission_result'] = 1;
                        add_logbook_entry($this->move['user_id'], LOGBOOK_TACTICAL_2, $f_c_title.$this->dest['planet_name'].$f_c_success, $log_data);
                    }
                    else
                    {
                        // Mission failed!
                        $log_data[8]['mission_result'] = -2;
                        add_logbook_entry($this->move['user_id'], LOGBOOK_TACTICAL_2, $f_c_title.$this->dest['planet_name'].$f_c_fail, $log_data);
                    }
                }
            break;

            /**
             * Reconnaissance mission.
             */
            case 1:
                switch($this->move['language'])	{
                    case 'GER':
                        $f_c_title = 'Recon mission on ';
                        $f_c_fail = ' failed';
                        $f_c_success = ' done';
                    break;
                    case 'ITA':
                        $f_c_title = 'Ricognizione su ';
                        $f_c_fail = ' fallita';
                        $f_c_success = ' terminata';
                    break;
                    default:
                        $f_c_title = 'Recon mission on ';
                        $f_c_fail = ' failed';
                        $f_c_success = ' done';
                    break;
                }

                $log_data = array($this->move['dest'],$this->dest['planet_name'], $ship_details['ship_id'], $name_of_ship, 1, 0);                

                // Mood Section
                $index = 0;
                $sql = 'SELECT sr.user_id, u.user_name, u.user_alliance, u.user_race, SUM(mood_modifier) as mood_value
                        FROM (settlers_relations sr)
                        LEFT JOIN (user u) ON sr.user_id = u.user_id
                        WHERE sr.user_id != '.$this->move['user_id'].' AND
                              sr.planet_id = '.$this->move['dest'].'
                              GROUP BY sr.user_id ORDER BY mood_value
                        LIMIT 0,10';
                $user_mood_query = $this->db->query($sql);
                $user_mood_data = array();
                while($user_mood_item = $this->db->fetchrow($user_mood_query)) {
                    $user_mood_data[$index] = $user_mood_item;
                    $index++;
                }

                $log_data[5] = array();
                $log_data[6] = array();
                $log_data[5]['user_mood'] = $mood['user'];
                $log_data[5]['toptenlist'] = $user_mood_data;

                // Tech section
                // We will display info on the tech we can teach to the planet, accordingly with RACE_DATA table, element 29

                $sql='SELECT MAX(research_1) as rescap1, MAX(research_2) as rescap2, MAX(research_3) as rescap3,
                             MAX(research_4) as rescap4, MAX(research_5) as rescap5
                      FROM planets WHERE planet_owner = '.$this->move['user_id'];
                $rc_q = $this->db->queryrow($sql);

                
                
                if($RACE_DATA[$this->move['user_race']][29][0]) {
                    $sql = 'SELECT research_start, research_finish
                            FROM scheduler_research WHERE planet_id = '.$this->move['dest'].'
                            AND player_id = '.INDEPENDENT_USERID.' AND research_id = 0';
                    $q_time = $this->db->queryrow($sql);
                    if(isset($q_time['research_finish']) && !empty($q_time['research_finish'])) {
                        $actual_lvl = $this->dest['research_1'] + 1;
                        if($actual_lvl < 9 && $this->dest['research_1'] < $rc_q['rescap1']) {
                            $log_data[6]['research_1'] = true;
                            $log_data[6]['time_1'] = format_time(($q_time['research_finish'] - $ACTUAL_TICK)*TICK_DURATION);
                        }                        
                    }
                    else {
                        if($this->dest['research_1'] < 9 && $this->dest['research_1'] < $rc_q['rescap1']) {
                            $log_data[6]['research_1'] = true;
                        }
                    }
                }
                /*
                if($this->dest['research_1'] < 9 && $RACE_DATA[$this->move['user_race']][29][0] && $this->dest['research_1'] < $rc_q['rescap1'])
                {
                    $log_data[6]['research_1'] = true;
                    $sql = 'SELECT research_start, research_finish
                            FROM scheduler_research WHERE planet_id = '.$this->move['dest'].'
                            AND player_id = '.INDEPENDENT_USERID.' AND research_id = 0';
                    if($q_time = $this->db->queryrow($sql))
                    {
                        $log_data[6]['time_1'] = format_time(($q_time['research_finish'] - $ACTUAL_TICK)*TICK_DURATION);
                    }
                }
                 * 
                 */

                if($RACE_DATA[$this->move['user_race']][29][1]) {
                    $sql = 'SELECT research_start, research_finish
                            FROM scheduler_research WHERE planet_id = '.$this->move['dest'].'
                            AND player_id = '.INDEPENDENT_USERID.' AND research_id = 1';
                    $q_time = $this->db->queryrow($sql);
                    if(isset($q_time['research_finish']) && !empty($q_time['research_finish'])) {
                        $actual_lvl = $this->dest['research_2'] + 1;
                        if($actual_lvl < 9 && $this->dest['research_2'] < $rc_q['rescap2']) {
                            $log_data[6]['research_2'] = true;
                        $log_data[6]['time_2'] = format_time(($q_time['research_finish'] - $ACTUAL_TICK)*TICK_DURATION);
                    }
                }
                    else {
                        if($this->dest['research_2'] < 9 && $this->dest['research_2'] < $rc_q['rescap2']) {
                            $log_data[6]['research_2'] = true;
                        }
                    }
                }

                if($RACE_DATA[$this->move['user_race']][29][2]) {
                    $sql = 'SELECT research_start, research_finish
                            FROM scheduler_research WHERE planet_id = '.$this->move['dest'].'
                            AND player_id = '.INDEPENDENT_USERID.' AND research_id = 2';
                    $q_time = $this->db->queryrow($sql);
                    if(isset($q_time['research_finish']) && !empty($q_time['research_finish'])) {
                        $actual_lvl = $this->dest['research_3'] + 1;
                        if($actual_lvl < 9 && $this->dest['research_3'] < $rc_q['rescap3']) {
                            $log_data[6]['research_3'] = true;
                        $log_data[6]['time_3'] = format_time(($q_time['research_finish'] - $ACTUAL_TICK)*TICK_DURATION);
                    }
                }
                    else {
                        if($this->dest['research_3'] < 9 && $this->dest['research_3'] < $rc_q['rescap3']) {
                            $log_data[6]['research_3'] = true;
                        }
                    }
                }

                if($RACE_DATA[$this->move['user_race']][29][3]) {
                    $sql = 'SELECT research_start, research_finish
                            FROM scheduler_research WHERE planet_id = '.$this->move['dest'].'
                            AND player_id = '.INDEPENDENT_USERID.' AND research_id = 3';
                    $q_time = $this->db->queryrow($sql);
                    if(isset($q_time['research_finish']) && !empty($q_time['research_finish'])) {
                        $actual_lvl = $this->dest['research_4'] + 1;
                        if($actual_lvl < 9 && $this->dest['research_4'] < $rc_q['rescap4']) {
                            $log_data[6]['research_4'] = true;
                        $log_data[6]['time_4'] = format_time(($q_time['research_finish'] - $ACTUAL_TICK)*TICK_DURATION);
                    }
                }
                    else {
                        if($this->dest['research_4'] < 9 && $this->dest['research_4'] < $rc_q['rescap4']) {
                            $log_data[6]['research_4'] = true;
                        }
                    }
                }

                if($RACE_DATA[$this->move['user_race']][29][4]) {
                    $sql = 'SELECT research_start, research_finish
                            FROM scheduler_research WHERE planet_id = '.$this->move['dest'].'
                            AND player_id = '.INDEPENDENT_USERID.' AND research_id = 4';
                    $q_time = $this->db->queryrow($sql);
                    if(isset($q_time['research_finish']) && !empty($q_time['research_finish'])) {
                        $actual_lvl = $this->dest['research_5'] + 1;
                        if($actual_lvl < 9 && $this->dest['research_5'] < $rc_q['rescap5']) {
                            $log_data[6]['research_5'] = true;
                        $log_data[6]['time_5'] = format_time(($q_time['research_finish'] - $ACTUAL_TICK)*TICK_DURATION);
                    }
                }
                    else {
                        if($this->dest['research_5'] < 9 && $this->dest['research_5'] < $rc_q['rescap5']) {
                            $log_data[6]['research_5'] = true;
                        }
                    }
                }

                add_logbook_entry($this->move['user_id'], LOGBOOK_SETTLERS, $f_c_title.$this->dest['planet_name'].$f_c_success, $log_data);

            break;

            /**
             * Diplomatic Agreement.
             */
            case 2:
                switch($this->move['language'])
                {
                    case 'GER':
                        $f_c_title = 'Diplo Speech on ';
                        $f_c_fail = ' failed';
                        $f_c_success = ' done';
                    break;
                    case 'ITA':
                        $f_c_title = 'Trattative Diplomatiche su ';
                        $f_c_fail = ' fallite';
                        $f_c_success = ' concluse';
                    break;
                    default:
                        $f_c_title = 'Diplo Speech on ';
                        $f_c_fail = ' failed';
                        $f_c_success = ' done';
                    break;
                }

                $log_data = array($this->move['dest'],$this->dest['planet_name'], $ship_details['ship_id'], $name_of_ship, 2, 0);

                if($mood['value'] < 1) {
                    // Invalid move. 
                    $log_title = $f_c_title.$this->dest['planet_name'].$f_c_fail;
                    $log_data[5] = -1;
                    add_logbook_entry($this->move['user_id'], LOGBOOK_SETTLERS, $log_title, $log_data);
                }
                else
                {
                    // Check whether an agreement is still valid
                
                    $sql = 'SELECT log_code, mood_modifier
                            FROM settlers_relations
                            WHERE planet_id = '.$this->move['dest'].' AND
                                  user_id = '.$this->move['user_id'].' AND
                                  log_code = '.LC_DIPLO_SPEECH;
                    if(($_qd = $this->db->queryrow($sql)) === false) {
                        return $this->log(MV_M_DATABASE, 'Could not read planet data! SKIP');
                    }

                    if(!empty($_qd['log_code']) && $_qd['log_code'] == LC_DIPLO_SPEECH && $_qd['mood_modifier'] > 0) {
                        // There's already a valid agreement, invalid move
                        $log_title = $f_c_title.$this->dest['planet_name'].$f_c_fail;
                        $log_data[5] = -2;
                        add_logbook_entry($this->move['user_id'], LOGBOOK_SETTLERS, $log_title, $log_data);
                    }
                    else
                    {
                        $speech_value = 40;
                        
                        $sql = 'SELECT threat_level FROM borg_target WHERE user_id = '.$this->move['user_id'];
                        $bot_target_data = $this->db->query($sql);
                        $already_acquired = $this->db->num_rows($bot_target_data);
                        if($already_acquired > 0) {
                            $honor_bonus_data = $this->db->fetchrow($bot_target_data);
                            // REMEMBER to keep this aligned with those present in borg.php!!
                            if($honor_bonus_data['threat_level'] > 1400.0)
                                $speech_value += 50;
                            elseif($honor_bonus_data['threat_level'] > 950.0)
                                $speech_value += 35;
                            elseif($honor_bonus_data['threat_level'] > 450.0)
                                $speech_value += 25;
                            elseif($honor_bonus_data['threat_level'] > 200.0)
                                $speech_value += 15;
                            else
                                $speech_value += 10;
                        }

                        $sql = 'INSERT INTO settlers_relations
                                SET planet_id = '.$this->move['dest'].',
                                    race_id = '.$this->move['user_race'].',
                                    user_id = '.$this->move['user_id'].',
                                    timestamp = '.time().',
                                    log_code = '.LC_DIPLO_SPEECH.',
                                    mood_modifier = '.$speech_value;

                        if(!$this->db->query($sql)) {
                            return $this->log(MV_M_DATABASE, 'Could not create new settlers relations! SKIP!!!');
                        }

                        // Calculate Exp of the mission
                        
                        if($ship_details['experience'] < 99) {
                            $actual_exp = $ship_details['experience'];
                            $exp = (2.9/((float)$actual_exp*0.0635))+2.5;
                            $sql = 'UPDATE ships SET experience = experience+'.$exp.' WHERE ship_id = '.$ship_details['ship_id'];
                            if(!$this->db->query($sql)) {
                                return $this->log(MV_M_DATABASE, 'Could not update ship exp! SKIP');
                            }
                        }
                        
                        $awayteamlvl = round($ship_details['awayteam'], 0);
                        $exp_award = round(2.34 / $awayteamlvl, 3);
                        $ship_details['awayteam'] += $exp_award;
                        $sql = 'UPDATE ships SET awayteam = '.$ship_details['awayteam'].' WHERE ship_id = '.$ship_details['ship_id'];
                        if(!$this->db->query($sql)) {
                            return $this->log(MV_M_DATABASE, 'Could not update ship exp! SKIP');
                        }
                                                
                        $log_title = $f_c_title.$this->dest['planet_name'].$f_c_success;
                        $log_data[6] = $speech_value;
                        add_logbook_entry($this->move['user_id'], LOGBOOK_SETTLERS, $log_title, $log_data);
                    }
                }
            break;
            /**
             * Tech Help
             */
            case 3:
                switch($this->move['language'])
                {
                    case 'GER':
                        $f_c_title = 'Tech Support on ';
                        $f_c_fail = ' failed';
                        $f_c_success = ' succesful';
                    break;
                    case 'ITA':
                        $f_c_title = 'Supporto tecnologico su ';
                        $f_c_fail = ' non fornito';
                        $f_c_success = ' fornito con successo';
                    break;
                    default:
                        $f_c_title = 'Tech Support on ';
                        $f_c_fail = ' failed';
                        $f_c_success = ' succesful';
                    break;
                }

                $log_data = array($this->move['dest'],$this->dest['planet_name'], $ship_details['ship_id'], $name_of_ship, 3, 0);

                if($this->action_data[1] < 0) {
                    // Mission parameter invalid. Exit.
                    $log_title = $f_c_title.$this->dest['planet_name'].$f_c_fail;
                    $log_data[5] = -1;
                    add_logbook_entry($this->move['user_id'], LOGBOOK_SETTLERS, $log_title, $log_data);
                }

                // A Treaty must exists on planet for this move to work

                if(!$is_diplo_speech)
                {
                    $log_title = $f_c_title.$this->dest['planet_name'].$f_c_fail;
                    $log_data[5] = -5;
                    add_logbook_entry($this->move['user_id'], LOGBOOK_SETTLERS, $log_title, $log_data);
                }                

                $tech_data = $this->action_data[1];

                if(!$RACE_DATA[$this->move['user_race']][$tech_data])
                {
                    $log_title = $f_c_title.$this->dest['planet_name'].$f_c_fail;
                    $log_data[5] = -6;
                    add_logbook_entry($this->move['user_id'], LOGBOOK_SETTLERS, $log_title, $log_data);
                }                

                switch($tech_data)
                {
                    case 0:
                        $_log_code = LC_SUP_TECH;
                        switch($this->move['language'])
                        {
                            case 'GER':
                                $log_data[7] = '<b>Environmental modification</b>';
                            break;
                            case 'ITA':
                                $log_data[7] = '<b>Modifica ambientale</b>';
                            break;
                            default:
                                $log_data[7] = '<b>Environmental modification</b>';
                            break;
                        }
                    break;
                    case 1:
                        $_log_code = LC_SUP_MEDI;
                        switch($this->move['language'])
                        {
                            case 'GER':
                                $log_data[7] = '<b>Medical research</b>';
                            break;
                            case 'ITA':
                                $log_data[7] = '<b>Ricerca medica</b>';
                            break;
                            default:
                                $log_data[7] = '<b>Medical research</b>';
                            break;
                        }
                    break;                    
                    case 2:
                        $_log_code = LC_SUP_DFNS;
                        switch($this->move['language'])
                        {
                            case 'GER':
                                $log_data[7] = '<b>Defenses upgrade</b>';
                            break;
                            case 'ITA':
                                $log_data[7] = '<b>Aggiornamento difese</b>';
                            break;
                            default:
                                $log_data[7] = '<b>Defenses upgrade</b>';
                            break;
                        }
                    break;
                    case 3:
                        $_log_code = LC_SUP_AUTO;
                        switch($this->move['language'])
                        {
                            case 'GER':
                                $log_data[7] = '<b>Automation</b>';
                            break;
                            case 'ITA':
                                $log_data[7] = '<b>Automazione</b>';
                            break;
                            default:
                                $log_data[7] = '<b>Automation</b>';
                            break;
                        }
                    break;
                    case 4:
                        $_log_code = LC_SUP_MINE;
                        switch($this->move['language'])
                        {
                            case 'GER':
                                $log_data[7] = '<b>Mining</b>';
                            break;
                            case 'ITA':
                                $log_data[7] = '<b>Estrazione</b>';
                            break;
                            default:
                                $log_data[7] = '<b>Mining</b>';
                            break;
                        }
                    break;
                }
                
                // Let's check if planet reached maximum research

                if($this->dest['research_'.($tech_data+1)] >= 9) {
                    //Research cannot go tooo far!!! Exit.
                    $log_title = $f_c_title.$this->dest['planet_name'].$f_c_fail;
                    $log_data[5] = -2;
                    add_logbook_entry($this->move['user_id'], LOGBOOK_SETTLERS, $log_title, $log_data);
                }
                else
                {
                    // Let's check if we can afford to supply this tech

                    $sql='SELECT MAX(research_'.($tech_data+1).') as rescap FROM planets WHERE planet_owner = '.$this->move['user_id'];
                    $rc_q = $this->db->queryrow($sql);

                    if(!isset($rc_q['rescap']) || $rc_q['rescap'] <= ($this->dest['research_'.($tech_data+1)])) {
                        // We can't do this!!!
                        $log_title = $f_c_title.$this->dest['planet_name'].$f_c_fail;
                        $log_data[5] = -3;
                        add_logbook_entry($this->move['user_id'], LOGBOOK_SETTLERS, $log_title, $log_data);
                    }
                }

                // Let's check if someone else is already doing this mission.

                $sql = 'SELECT COUNT(*) as counter 
                        FROM scheduler_research WHERE planet_id = '.$this->move['dest'].'
                        AND research_id = '.$tech_data; 
                $c_q = $this->db->queryrow($sql);

                if(isset($c_q['counter']) && $c_q['counter'] > 0) {
                    // The research is already undergoing!!!
                    $log_title = $f_c_title.$this->dest['planet_name'].$f_c_fail;
                    $log_data[5] = -4;
                    add_logbook_entry($this->move['user_id'], LOGBOOK_SETTLERS, $log_title, $log_data);
                    // refound the resources!
                }

                if($log_data[5] == 0) {
                    // ALL GREEN: LET'S DO THIS
                    // Code taken from modules/researchlabs.php
                    $time=0;

                    $time=$TECH_DATA[$tech_data][3]+ pow($this->dest['research_'.($tech_data+1)],$TECH_DATA[$tech_data][4]);
                    $time*=$RACE_DATA[13][4]; // Settlers Race is 13

                    $time/=100;
                    $time*=(100-2*($this->dest['research_4']*$RACE_DATA[13][20])); // Settlers Race is 13
                    if ($time<1) $time=1;
                    $time=round($time,0);

                    $sql = 'INSERT INTO scheduler_research (research_id,planet_id,player_id,research_finish,research_start)
                            VALUES ("'.$tech_data.'","'.$this->move['dest'].'","'.INDEPENDENT_USERID.'","'.($ACTUAL_TICK+$time).'","'.$ACTUAL_TICK.'")';

                    if(!$this->db->query($sql)) {
                        return $this->log(MV_M_DATABASE, 'Could not write research record for settlers research! SKIP!!!');
                    }

                    $log_data[8] = format_time($time*TICK_DURATION);

                    // Tech Rewards. Create a LC_TECH_SUPPORT/LC_DEF_SUPPORT entry on settlers_relations or upgrade it

                    $tech_value = $tech_reward[$this->dest['research_'.($tech_data+1)]];
                    
                    // Calculate Exp of the mission
                    
                    if($ship_details['experience'] < 99) {
                        $actual_exp = $ship_details['experience'];
                        $exp = (2.9/((float)$actual_exp*0.0635))+2.5;
                        $sql = 'UPDATE ships SET experience = experience+'.$exp.' WHERE ship_id = '.$ship_details['ship_id'];
                        if(!$this->db->query($sql)) {
                            return $this->log(MV_M_DATABASE, 'Could not update ship exp! SKIP');
                        }
                    }
                    
                    $awayteamlvl = round($ship_details['awayteam'], 0);
                    $exp_award = round(6.75 / $awayteamlvl, 3);
                    $ship_details['awayteam'] += $exp_award;
                    $sql = 'UPDATE ships SET awayteam = '.$ship_details['awayteam'].' WHERE ship_id = '.$ship_details['ship_id'];
                    if(!$this->db->query($sql)) {
                        return $this->log(MV_M_DATABASE, 'Could not update ship exp! SKIP');
                    }                        
                    $log_title = $f_c_title.$this->dest['planet_name'].$f_c_success;
                    $log_data[6] = $tech_value;
                    $this->do_simple_relation($this->move['user_id'], $this->move['dest'], $_log_code, $tech_value);
                    // if($decrease_founder != 0) $this->update_founder_mood($this->move['dest'], $decrease_founder);
                    if(count($cache_event_sql)) foreach($cache_event_sql as $cached_query) $this->db->query($cached_query);
                    foreach($cache_mood as $key => $cache_mood_modifier)
                    {
                        if($cache_mood_modifier <> 0)
                        {
                            $this->do_simple_relation($this->move['user_id'], $this->move['dest'], $key, $cache_mood_modifier);
                            $log_data[9][] = $this->get_mood_text_string($key, $cache_mood_modifier);
                        }
                    }                    
                    add_logbook_entry($this->move['user_id'], LOGBOOK_SETTLERS, $log_title, $log_data);
                }

            break;
            /**
             * Orbital Defense Help
             */
            case 4:
                switch($this->move['language'])
                {
                    case 'GER':
                        $f_c_title = 'Orbital defense building on ';
                        $f_c_fail = ' not done';
                        $f_c_success = ' done';
                    break;
                    case 'ITA':
                        $f_c_title = 'Costruzione difese orbitali su ';
                        $f_c_fail = ' non ultimata';
                        $f_c_success = ' ultimata';
                    break;
                    default:
                        $f_c_title = 'Orbital defense building on ';
                        $f_c_fail = ' not done';
                        $f_c_success = ' done';
                    break;
                }

                $log_data = array($this->move['dest'],$this->dest['planet_name'], $ship_details['ship_id'], $name_of_ship, 4, 0);


                $sql = 'SELECT COUNT(*) as conto FROM settlers_relations WHERE planet_id = '.$this->move['dest'].' AND user_id = '.$this->move['user_id'].' AND log_code = '.LC_DIPLO_SPEECH;

                if(!$is_diplo_speech)
                {
                    // A Treaty must exists on planet for this move to work
                    $log_title = $f_c_title.$this->dest['planet_name'].$f_c_fail;
                    $log_data[5] = -2;
                    add_logbook_entry($this->move['user_id'], LOGBOOK_SETTLERS, $log_title, $log_data);                    
                }

                $sql='SELECT fleet_id FROM ship_fleets WHERE fleet_name = "Orbital'.$this->move['dest'].'"';
                $od_q = $this->db->queryrow($sql);
                if(isset($od_q['fleet_id']) && !empty($od_q['fleet_id']))
                {
                        $sql='SELECT COUNT(*) AS counter FROM ships WHERE fleet_id = '.$od_q['fleet_id'];
                        $orbital_check=$this->db->queryrow($sql);
                        $orbital_counter = $orbital_check['counter'];
                        if($orbital_counter >= STL_MAX_ORBITAL)
                        {
                            $log_title = $f_c_title.$this->dest['planet_name'].$f_c_fail;
                            $log_data[5] = -1;
                            add_logbook_entry($this->move['user_id'], LOGBOOK_SETTLERS, $log_title, $log_data);                    
                        }
                }
                else
                {
                    $orbital_counter = 0;
                }


                if($log_data[5] == 0)
                {
                    $sql = 'SELECT value_5, value_9, rof, max_torp FROM `ship_templates`
                            WHERE `id` = '.$cfg_data['settler_tmp_4'];
                    if(($stpl = $this->db->queryrow($sql)) === false)
                        return $this->log(MV_M_DATABASE, '<b>Error:</b> Could not query settlers ship template data');

                    if($orbital_counter > 1 && $orbital_counter < STL_MAX_ORBITAL)
                    {
                        $fleet_id = $od_q['fleet_id'];
                        $orbital_to_made = min((STL_MAX_ORBITAL - $orbital_counter), 10);
                    }
                    else
                    {
                        // Orbital defence fleet not exists. Let's create this.
                        $sql = 'INSERT INTO ship_fleets (fleet_name, user_id, planet_id,
                                                         alert_phase, move_id, n_ships)
                                VALUES ("Orbital'.$this->move['dest'].'", '.INDEPENDENT_USERID.', '.$this->move['dest'].',
                                        '.ALERT_PHASE_GREEN.', 0, 10)';
                        $this->db->query($sql);
                        $fleet_id = $this->db->insert_id();
                        $orbital_to_made = 10;
                    }

                    $sql = 'INSERT INTO ships (fleet_id, user_id, template_id,
                                               experience, hitpoints, construction_time,
                                               torp, rof, last_refit_time)
                            VALUES ('.$fleet_id.', '.INDEPENDENT_USERID.', '.$cfg_data['settler_tmp_4'].',
                                    '.$stpl['value_9'].', '.$stpl['value_5'].', '.time().',
                                    '.$stpl['max_torp'].', '.$stpl['rof'].', '.time().')';
                    $tally = 0;
                    while($tally < $orbital_to_made)
                    {
                        if(!$this->db->query($sql)) 
                        {
                            return $this->log(MV_M_DATABASE, 'Error while adding an orbital cannon for Settlers ---> '.$sql);
                        }
                        $tally++;
                    }

                    $log_title = $f_c_title.$this->dest['planet_name'].$f_c_success;
                    if(count($cache_event_sql)) foreach($cache_event_sql as $cached_query) $this->db->query($cached_query);
                    foreach($cache_mood as $key => $cache_mood_modifier)
                    {
                        if($cache_mood_modifier <> 0)
                        {
                            $this->do_simple_relation($this->move['user_id'], $this->move['dest'], $key, $cache_mood_modifier);
                            $log_data[7][] = $this->get_mood_text_string($key, $cache_mood_modifier);
                        }
                    }
                    $log_data[6] = $tally;
                    add_logbook_entry($this->move['user_id'], LOGBOOK_SETTLERS, $log_title, $log_data);

                    // Calculate Exp of the mission

                    if($ship_details['experience'] < 99) {
                        $actual_exp = $ship_details['experience'];
                        $exp = (2.9/((float)$actual_exp*0.0635))+2.5;
                        $sql = 'UPDATE ships SET experience = experience+'.$exp.' WHERE ship_id = '.$ship_details['ship_id'];
                        if(!$this->db->query($sql)) {
                            return $this->log(MV_M_DATABASE, 'Could not update ship exp! SKIP');
                        }
                    }
                    
                    $awayteamlvl = round($ship_details['awayteam'], 0);
                    $exp_award = round(15.6 / $awayteamlvl, 3);
                    $ship_details['awayteam'] += $exp_award;
                    $sql = 'UPDATE ships SET awayteam = '.$ship_details['awayteam'].' WHERE ship_id = '.$ship_details['ship_id'];
                    if(!$this->db->query($sql)) {
                        return $this->log(MV_M_DATABASE, 'Could not update ship exp! SKIP');
                    }                    
                }
            break;
            /**
             * Rescue Mission
             */            
            case 5:

                $mission_exp = array(100 => 0, 101 => 80.0, 120 => 50.0, 121 => 50.0, 122 => 50.0, 123 => 50.0, 124 => 50.0,
                                     130 => 70.0, 131 =>70.0, 132 => 70.0, 133 => 70.0, 134 => 70.0, 150 => 50.0);
                                  
                switch($this->move['language'])
                {
                    case 'GER':
                        $f_c_title = 'Rescue mission on ';
                        $f_c_fail = ' not done';
                        $f_c_success = ' done';
                    break;
                    case 'ITA':
                        $f_c_title = 'Missione di recupero su ';
                        $f_c_fail = ' non ultimata';
                        $f_c_success = ' ultimata';
                    break;
                    default:
                        $f_c_title = 'Rescue mission on ';
                        $f_c_fail = ' not done';
                        $f_c_success = ' done';
                    break;
                }

                $log_data = array($this->move['dest'],$this->dest['planet_name'], $ship_details['ship_id'], $name_of_ship, 5, 0);
                
                // Cerchiamo la squadra a terra                
                $sql = 'SELECT * from settlers_events WHERE planet_id = '.$this->move['dest'].' AND user_id = '.$this->move['user_id'];
                $rescue_data = $this->db->queryrow($sql);
                
                $crew_check = ($ship_details['unit_1'] - $ship_details['min_unit_1']) +
                              ($ship_details['unit_1'] - $ship_details['min_unit_1']) +
                              ($ship_details['unit_1'] - $ship_details['min_unit_1']) +
                              ($ship_details['unit_1'] - $ship_details['min_unit_1']);
                if($crew_check > 0)
                {
                    $log_title = $f_c_title.$this->dest['planet_name'].$f_c_fail;
                    $log_data[5] = -4;
                    add_logbook_entry($this->move['user_id'], LOGBOOK_SETTLERS, $log_title, $log_data);
                }
                elseif(!isset($rescue_data['event_code']))
                {
                    $log_title = $f_c_title.$this->dest['planet_name'].$f_c_fail;
                    $log_data[5] = -3;
                    add_logbook_entry($this->move['user_id'], LOGBOOK_SETTLERS, $log_title, $log_data);
                }
                elseif($rescue_data['awayteam_alive'] == 0)
                {
                    $log_title = $f_c_title.$this->dest['planet_name'].$f_c_fail;
                    $log_data[5] = -2;
                    add_logbook_entry($this->move['user_id'], LOGBOOK_SETTLERS, $log_title, $log_data);
                    $sql = 'UPDATE ships SET awayteam = 1, awayteamplanet_id = 0 WHERE ship_id = '.$ship_details['ship_id'];
                    $this->db->query($sql);
                }
                elseif($rescue_data['event_result'] == 0)
                {
                    $log_title = $f_c_title.$this->dest['planet_name'].$f_c_success;
                    $log_data[5] = -1;
                    add_logbook_entry($this->move['user_id'], LOGBOOK_SETTLERS, $log_title, $log_data);
                    $new_unit_1 = min(($ship_details['unit_1'] + $rescue_data['unit_1']), $ship_details['max_unit_1']);
                    $new_unit_2 = min(($ship_details['unit_2'] + $rescue_data['unit_2']), $ship_details['max_unit_2']);
                    $new_unit_3 = min(($ship_details['unit_3'] + $rescue_data['unit_3']), $ship_details['max_unit_3']);
                    $new_unit_4 = min(($ship_details['unit_4'] + $rescue_data['unit_4']), $ship_details['max_unit_4']);                    
                    $sql = 'UPDATE ships SET awayteam = '.$rescue_data['awayteam_startlevel'].', awayteamplanet_id = 0,
                                   unit_1 = '.$new_unit_1.', unit_2 = '.$new_unit_2.', unit_3 = '.$new_unit_3.' ,unit_4 = '.$new_unit_4.' 
                            WHERE ship_id = '.$ship_details['ship_id'];
                    if(!$this->db->query($sql)) {
                        return $this->log(MV_M_DATABASE, 'Could not update ship data! SQL DUMP 1: '.$sql);
                    }                    
                    $sql = 'DELETE FROM settlers_events WHERE planet_id = '.$this->move['dest'].' AND user_id = '.$this->move['user_id'].' AND event_code = '.$rescue_data['event_code'];
                    if(!$this->db->query($sql)) {
                        return $this->log(MV_M_DATABASE, 'Could not update settlers event data! SQL DUMP: '.$sql);
                    }                    
                }

                // Preparazione Report e calcolo EXP
                if($log_data[5] == 0)
                {
                    // Liberiamo la nave di partenza.
                    $sql = 'UPDATE ships SET awayteam = 1, awayteamplanet_id = 0 WHERE ship_id = '.$rescue_data['awayteamship_id'];
                    $this->db->query($sql);
                    $this->log(MV_M_NOTICE, 'Liberando la nave di partenza dalla sua squadra: '.$sql);
                    $new_unit_1 = min(($ship_details['unit_1'] + $rescue_data['unit_1']), $ship_details['max_unit_1']);
                    $new_unit_2 = min(($ship_details['unit_2'] + $rescue_data['unit_2']), $ship_details['max_unit_2']);
                    $new_unit_3 = min(($ship_details['unit_3'] + $rescue_data['unit_3']), $ship_details['max_unit_3']);
                    $new_unit_4 = min(($ship_details['unit_4'] + $rescue_data['unit_4']), $ship_details['max_unit_4']);
                    $base_exp = $mission_exp[$rescue_data['event_code']];
                    switch ($rescue_data['event_code'])
                    {
                        case 150:
                            $base_exp = $base_exp + ($rescue_data['count_ok'] * 30.0) + ($rescue_data['count_crit_ok'] * 150.0);
                        break;
                        default :
                            $base_exp = $base_exp + ($rescue_data['count_ok'] * 1.0) + ($rescue_data['count_crit_ok'] * 10.0);
                    }
                    $awayteamlvl = round($rescue_data['awayteam_startlevel'], 0);
                    $exp_award = round($base_exp / $awayteamlvl, 3);
                    $sql = 'UPDATE ships SET awayteam = '.($rescue_data['awayteam_startlevel'] + $exp_award).', awayteamplanet_id = 0,
                                   unit_1 = '.$new_unit_1.', unit_2 = '.$new_unit_2.', unit_3 = '.$new_unit_3.' ,unit_4 = '.$new_unit_4.' 
                            WHERE ship_id = '.$ship_details['ship_id'];
                    if(!$this->db->query($sql)) {
                        return $this->log(MV_M_DATABASE, 'Could not update ship data! SQL DUMP 3: '.$sql);
                    }                            
                    $sql = 'DELETE FROM settlers_events WHERE planet_id = '.$this->move['dest'].' AND user_id = '.$this->move['user_id'].' AND event_code = '.$rescue_data['event_code'];
                    if(!$this->db->query($sql)) {
                        return $this->log(MV_M_DATABASE, 'Could not update settlers event data! SQL DUMP: '.$sql);
                    }                    
                    $log_title = $f_c_title.$this->dest['planet_name'].$f_c_success;
                    add_logbook_entry($this->move['user_id'], LOGBOOK_SETTLERS, $log_title, $log_data);                    
                }
            break;
            /**
             * Deploy mission
             */            
            case 10:
                switch($this->move['language'])
                {
                    case 'GER':
                        $f_c_title = 'AT deploy on ';
                        $f_c_fail = ' not done';
                        $f_c_success = ' done';
                    break;
                    case 'ITA':
                        $f_c_title = 'Sbarco della squadra su ';
                        $f_c_fail = ' non ultimato';
                        $f_c_success = ' ultimato';
                    break;
                    default:
                        $f_c_title = 'AT deploy on ';
                        $f_c_fail = ' not done';
                        $f_c_success = ' done';
                    break;
                }
                // Event codes
                $event_codes_table = array(
                '0'  => 100,
                '1'  => 102,
                '10'  => 120,
                '20'  => 121,
                '30'  => 122,
                '40'  => 123,
                '50'  => 124,
                '100' => 130,
                '110' => 131,
                '120' => 132,
                '130' => 133,
                '140' => 134,
                '150' => 150
                );
                $log_data = array($this->move['dest'],$this->dest['planet_name'], $ship_details['ship_id'], $name_of_ship, 10, 0);
                $e_c_i = $this->action_data[1];
                

                if($ship_details['awayteam'] == 0)
                {
                    $log_title = $f_c_title.$this->dest['planet_name'].$f_c_fail;
                    $log_data[5] = -5;
                    add_logbook_entry($this->move['user_id'], LOGBOOK_SETTLERS, $log_title, $log_data);
                }
                elseif(!isset($event_codes_table[$e_c_i]))
                {
                    $log_title = $f_c_title.$this->dest['planet_name'].$f_c_fail;
                    $log_data[5] = -5;
                    add_logbook_entry($this->move['user_id'], LOGBOOK_SETTLERS, $log_title, $log_data);                    
                }
                elseif(($event_codes_table[$e_c_i] > 119) && !$is_diplo_speech) {
                    $log_title = $f_c_title.$this->dest['planet_name'].$f_c_fail;
                    $log_data[5] = -7;
                    add_logbook_entry($this->move['user_id'], LOGBOOK_SETTLERS, $log_title, $log_data);                    
                }
                elseif($event_codes_table[$e_c_i] == 150 && $RACE_DATA[$this->move['user_race']][30])
                {
                    $log_title = $f_c_title.$this->dest['planet_name'].$f_c_fail;
                    $log_data[5] = -5;
                    add_logbook_entry($this->move['user_id'], LOGBOOK_SETTLERS, $log_title, $log_data);                    
                }
                elseif($event_codes_table[$e_c_i] == 150 && ($this->dest['planet_type'] != 'a' && $this->dest['planet_type'] != 'b' && $this->dest['planet_type'] != 'c' && $this->dest['planet_type'] != 'd' && $this->dest['planet_type'] != 'm' && $this->dest['planet_type'] != 'o' && $this->dest['planet_type'] != 'p'))
                {
                    $log_title = $f_c_title.$this->dest['planet_name'].$f_c_fail;
                    $log_data[5] = -6;
                    add_logbook_entry($this->move['user_id'], LOGBOOK_SETTLERS, $log_title, $log_data);
                }
                
                $sql = 'SELECT user_id, mood_modifier FROM settlers_relations WHERE planet_id = '.$this->move['dest'].' AND log_code = 30';
                $founder_q = $this->db->queryrow($sql);
                if(!isset($founder_q['user_id']))
                {
                    $founder_q['user_id'] = 0;
                    $founder_q['mood_modifier'] = -1;
                }
                if(($event_codes_table[$e_c_i] >= 120 && $event_codes_table[$e_c_i] <= 124) && ($this->move['user_id'] <> $founder_q['user_id']))
                {
                    $log_title = $f_c_title.$this->dest['planet_name'].$f_c_fail;
                    $log_data[5] = -4;
                    add_logbook_entry($this->move['user_id'], LOGBOOK_SETTLERS, $log_title, $log_data);                    
                }
                elseif($event_codes_table[$e_c_i] >= 130 && $event_codes_table[$e_c_i] <= 134 && ($this->move['user_id'] == $founder_q['user_id']))
                {
                    $log_title = $f_c_title.$this->dest['planet_name'].$f_c_fail;
                    $log_data[5] = -3;
                    add_logbook_entry($this->move['user_id'], LOGBOOK_SETTLERS, $log_title, $log_data);                    
                }
                elseif($founder_q['mood_modifier'] == -1 && ($event_codes_table[$e_c_i] >= 120 && $event_codes_table[$e_c_i] < 150))
                {
                    $log_title = $f_c_title.$this->dest['planet_name'].$f_c_fail;
                    $log_data[5] = -2;
                    add_logbook_entry($this->move['user_id'], LOGBOOK_SETTLERS, $log_title, $log_data);                    
                }
                
                $sql = 'SELECT COUNT(event_code) AS check_code FROM settlers_events WHERE planet_id = '.$this->move['dest'].' AND user_id = '.$this->move['user_id'];
                $mission_q = $this->db->queryrow($sql);
                if($mission_q['check_code'] > 0)
                {
                    $log_title = $f_c_title.$this->dest['planet_name'].$f_c_fail;
                    $log_data[5] = -1;
                    add_logbook_entry($this->move['user_id'], LOGBOOK_SETTLERS, $log_title, $log_data);
                }
                if($log_data[5] == 0)
                {
                    $sql = 'INSERT INTO settlers_events
                                        (planet_id, user_id, event_code, timestamp, tick, awayteamship_id, awayteam_startlevel, unit_1, unit_2, unit_3, unit_4, awayteam_alive, event_status)
                                VALUES  ('.$this->move['dest'].', '.$this->move['user_id'].', '.$event_codes_table[$e_c_i].', '.time().', '.$ACTUAL_TICK.', '.$ship_details['ship_id'].', '.$ship_details['awayteam'].',
                                         '.($ship_details['unit_1'] - $ship_details['min_unit_1']).', '.($ship_details['unit_2'] - $ship_details['min_unit_2']).', '.($ship_details['unit_3'] - $ship_details['min_unit_3']).', '.($ship_details['unit_4'] - $ship_details['min_unit_4']).', 1, 1)';
                    if(!$this->db->query($sql)) {
                        return $this->log(MV_M_DATABASE, 'Could not insert settlers event data! SKIP '.$sql);
                    }
                    $sql = 'UPDATE ships SET awayteam = 0, awayteamplanet_id = '.$this->move['dest'].', unit_1 = '.$ship_details['min_unit_1'].', unit_2 = '.$ship_details['min_unit_2'].', unit_3 = '.$ship_details['min_unit_3'].', unit_4 = '.$ship_details['min_unit_4'].' WHERE ship_id = '.$ship_details['ship_id'];
                    $this->db->query($sql);
                    $log_title = $f_c_title.$this->dest['planet_name'].$f_c_success;
                    add_logbook_entry($this->move['user_id'], LOGBOOK_SETTLERS, $log_title, $log_data);
                }
            break;
        }
        
        $old_besty = $this->check_best_mood($this->move['dest'], true);

        if($old_besty > 0)
        {
            $log_data = array($this->move['dest'],$this->dest['planet_name'], 0, '', 100);

            switch($this->move['language'])
            {
                case 'GER':
                    $log_title = 'Priority message from ';
                break;
                case 'ITA':
                    $log_title = 'Comunicazione prioritaria dalla colonia ';
                break;
                default:
                    $log_title = 'Priority message from ';
                break;
            }

            add_logbook_entry($old_besty, LOGBOOK_SETTLERS, $log_title.$this->dest['planet_name'], $log_data);
            
            $this->db->query('UPDATE planets SET best_mood_planet = NULL WHERE planet_id = '.$this->move['dest']);
        }

        $sql = 'UPDATE ship_fleets
                SET planet_id = '.$this->move['dest'].',
                    move_id = 0
                WHERE fleet_id IN ('.$this->fleet_ids_str.')';

        if(!$this->db->query($sql)) {
            return $this->log(MV_M_DATABASE, 'Could not update fleets data! SKIP');
        }

        return MV_EXEC_OK;
    }
}



?>

