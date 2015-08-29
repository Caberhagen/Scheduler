<?php
/*
    This file is part of STFC.it
    Copyright 2008-2013 by Andrea Carolfi (carolfi@stfc.it) and
    Cristiano Delogu (delogu@stfc.it).

    STFC.it is based on STFC,
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


//########################################################################################
//########################################################################################

/* 25. June 2008
  @Author: Delogu - Carolfi
  @Action: Thanks to Delogu they're definitely more active now! ^^
*/

// ########################################################################################
// ########################################################################################
// Startconfig of Settlers
class Settlers extends NPC
{
    public function Install($log = INSTALL_LOG_FILE_NPC)
    {
        $this->sdl->start_job('Mayflower basic system', $log);

        // We give the bot some data so that it is also Registered
        $this->bot = $this->db->queryrow('SELECT * FROM user WHERE user_id = '.INDEPENDENT_USERID);

        //Check whether the bot already lives
        if(empty($this->bot['user_id'])) {
            $this->sdl->log('We need to create TheSettlers!', $log);

            $sql = 'INSERT INTO user (user_id, user_auth_level, user_name, user_loginname, user_password,
                                      user_email, user_active, user_race, user_gfxpath, user_skinpath,
                                    user_registration_time, user_registration_ip,
                                          user_birthday, user_gender, plz, country, user_enable_sig,
                                          user_message_sig, user_signature, user_notepad, user_options, message_basement)
                         VALUES ('.INDEPENDENT_USERID.', '.STGC_BOT.', "Coloni(NPC)", "SettlersBot", "'.md5("settlers").'",
                                 "settlers@stfc.it", 1, 13, "'.DEFAULT_GFX_PATH.'", "skin1/", '.time().', "127.0.0.1",
                                 "25.06.2008", "", 16162 , "IT", 1,
                                 "",  "", "", "", "")';

            if(!$this->db->query($sql)) {
                $this->sdl->log('<b>Error:</b> could not create TheSettlers - ABORTED', $log);
            }
        }

        //Check for Settlers cargo ship definition
        $settler_ship_id = $this->db->queryrow('SELECT settler_tmp_1 FROM config WHERE config_set_id = 0');
        if(empty($settler_ship_id['settler_tmp_1'])) {
               $this->sdl->log('Writing Settler Cargo Ship!', $log);

               $sql = 'INSERT INTO ship_templates (owner, timestamp, name, description, race, ship_torso, ship_class,
                                                component_1, component_2, component_3, component_4, component_5,
                                                component_6, component_7, component_8, component_9, component_10,
                                                value_1, value_2, value_3, value_4, value_5,
                                                value_6, value_7, value_8, value_9, value_10,
                                                value_11, value_12, value_13, value_14, value_15,
                                                resource_1, resource_2, resource_3, resource_4, unit_5, unit_6,
                                                min_unit_1, min_unit_2, min_unit_3, min_unit_4,
                                                max_unit_1, max_unit_2, max_unit_3, max_unit_4,
                                                buildtime, rof, max_torp)
                     VALUES ("'.INDEPENDENT_USERID.'","'.time().'","Transporter","Settlers Cargo",13,1,0,
                             -1,-1,-1,-1,-1,
                             -1,-1,-1,-1,-1,
                             "15","0","0","5","20",
                             "2","1","5","5","3.4",
                             "5","0","10","4","0",
                             "4250","5800","4640","105","2","1",
                             "56","0","0","1",
                             "56","0","0","1",
                             720, 1, 0)';

               if(!$this->db->query($sql)) {
                    $this->sdl->log('<b>Error:</b> could not create TheSettlers - ABORTED', $log);
               }

               $template_id = $this->db->insert_id();
               $sql = 'UPDATE config SET settler_tmp_1 = '.$template_id.' WHERE config_set_id = 0';
               $this->db->query($sql);
        }

        //Check for Settlers cruiser ship definition
        $settler_ship_id = (int)$this->db->queryrow('SELECT settler_tmp_2 FROM config WHERE config_set_id = 0');
        if(empty($settler_ship_id['settler_tmp_2'])) {
               $this->sdl->log('Writing Settler Cruiser Ship!', $log);

               $sql = 'INSERT INTO ship_templates (owner, timestamp, name, description, race, ship_torso, ship_class,
                                                component_1, component_2, component_3, component_4, component_5,
                                                component_6, component_7, component_8, component_9, component_10,
                                                value_1, value_2, value_3, value_4, value_5,
                                                value_6, value_7, value_8, value_9, value_10,
                                                value_11, value_12, value_13, value_14, value_15,
                                                resource_1, resource_2, resource_3, resource_4, unit_5, unit_6,
                                                min_unit_1, min_unit_2, min_unit_3, min_unit_4,
                                                max_unit_1, max_unit_2, max_unit_3, max_unit_4,
                                                buildtime, rof, max_torp)
                     VALUES ("'.INDEPENDENT_USERID.'","'.time().'","Centaur","Settlers Cruiser",13,7,2,
                             -1,-1,-1,-1,-1,
                             -1,-1,-1,-1,-1,
                             "340","300","10","760","450",
                             "19","15","24","1","8.4",
                             "25","0","65","65","0",
                             "41000","38000","21500","550","15","5",
                             "90","30","10","1",
                             "110","45","25","3",
                             960, 1, 75)';

               if(!$this->db->query($sql)) {
                    $this->sdl->log('<b>Error:</b> could not create TheSettlers - ABORTED', $log);
               }

               $template_id = $this->db->insert_id();
               $sql = 'UPDATE config SET settler_tmp_2 = '.$template_id.' WHERE config_set_id = 0';
               $this->db->query($sql);
        }

        //Check for Settlers battleship ship definition
        $settler_ship_id = (int)$this->db->queryrow('SELECT settler_tmp_3 FROM config WHERE config_set_id = 0');
        if(empty($settler_ship_id['settler_tmp_3'])) {
               $this->sdl->log('Writing Settler Battleship!', $log);

               $sql = 'INSERT INTO ship_templates (owner, timestamp, name, description, race, ship_torso, ship_class,
                                                component_1, component_2, component_3, component_4, component_5,
                                                component_6, component_7, component_8, component_9, component_10,
                                                value_1, value_2, value_3, value_4, value_5,
                                                value_6, value_7, value_8, value_9, value_10,
                                                value_11, value_12, value_13, value_14, value_15,
                                                resource_1, resource_2, resource_3, resource_4, unit_5, unit_6,
                                                min_unit_1, min_unit_2, min_unit_3, min_unit_4,
                                                max_unit_1, max_unit_2, max_unit_3, max_unit_4,
                                                buildtime, rof, max_torp)
                     VALUES ("'.INDEPENDENT_USERID.'","'.time().'","Achilles","Settlers Battleship",13,11,3,
                             -1,-1,-1,-1,-1,
                             -1,-1,-1,-1,-1,
                             "500","675","50","1600","650",
                             "20","22","18","1","8.4",
                             "25","0","150","150","0",
                             "65700","60200","38000","1450","46","16",
                             "200","95","65","6",
                             "300","150","100","12",
                             2400, 1, 275)';

               if(!$this->db->query($sql)) {
                    $this->sdl->log('<b>Error:</b> could not create TheSettlers - ABORTED', $log);
               }

               $template_id = $this->db->insert_id();
               $sql = 'UPDATE config SET settler_tmp_3 = '.$template_id.' WHERE config_set_id = 0';
               $this->db->query($sql);
        }

        //Check for Settlers Orbital Cannon definition
        $settler_ship_id = (int)$this->db->queryrow('SELECT settler_tmp_4 FROM config WHERE config_set_id = 0');
        if(empty($settler_ship_id['settler_tmp_4'])) {
               $this->sdl->log('Writing Settler Orbital!', $log);

               $sql = 'INSERT INTO ship_templates (owner, timestamp, name, description, race, ship_torso, ship_class,
                                                component_1, component_2, component_3, component_4, component_5,
                                                component_6, component_7, component_8, component_9, component_10,
                                                value_1, value_2, value_3, value_4, value_5,
                                                value_6, value_7, value_8, value_9, value_10,
                                                value_11, value_12, value_13, value_14, value_15,
                                                resource_1, resource_2, resource_3, resource_4, unit_5, unit_6,
                                                min_unit_1, min_unit_2, min_unit_3, min_unit_4,
                                                max_unit_1, max_unit_2, max_unit_3, max_unit_4,
                                                buildtime, rof, max_torp)
                     VALUES ("'.INDEPENDENT_USERID.'","'.time().'","Orbitaler","Settlers PlanetaryShield",13,12,3,
                             -1,-1,-1,-1,-1,
                             -1,-1,-1,-1,-1,
                             "750","750","0","1000","100",
                             "35","25","0","0","0",
                             "35","0","33","33","0",
                             "50400","50400","40320","500","0","0",
                             "0","0","0","0",
                             "0","0","0","0",
                             480, 3, 400)';

               if(!$this->db->query($sql)) {
                    $this->sdl->log('<b>Error:</b> could not create TheSettlers - ABORTED', $log);
               }

               $template_id = $this->db->insert_id();
               $sql = 'UPDATE config SET settler_tmp_4 = '.$template_id.' WHERE config_set_id = 0';
               $this->db->query($sql);
        }

        $this->sdl->finish_job('Mayflower basic system', $log);
    }

    public function Execute($debug=0)
    {
        global $ACTUAL_TICK,$cfg_data;
        $starttime = ( microtime() + time() );

        $this->sdl->log('<br><b>-------------------------------------------------------------</b><br>'.
            '<b>Starting Settlers Bot Scheduler at '.date('d.m.y H:i:s', time()).'</b>', TICK_LOG_FILE_NPC);

        // Update BOT user ID
        $this->bot['user_id'] = INDEPENDENT_USERID;

        // ########################################################################################
        // ########################################################################################
        // Messages answer
        $messages=array('Bot system.','Bot system.','Se desiderate avere contatti con noi, dovrete scendere direttamente su uno dei nostri pianeti.<br><br>A presto.');
        $titles=array('--','--','Grazie per averci contattato.');

        $this->ReplyToUser($titles,$messages);
        // ########################################################################################
        // ########################################################################################
        // Read Logbook

        $this->ReadLogbook();
        // ########################################################################################
        // ########################################################################################
        // Ok, only three (3) Settlers' planets per run are going to wake up. More planets means more overhead.

        $this->sdl->start_job('Mayflower Planets Building Control', TICK_LOG_FILE_NPC);

        $sql='SELECT * FROM planets WHERE planet_owner = '.INDEPENDENT_USERID.' AND npc_last_action < '.$ACTUAL_TICK.' ORDER BY npc_last_action ASC LIMIT 0, 3';

        if(($setpoint = $this->db->query($sql)) === false)
        {
            $this->sdl->log('<b>Error:</b> Bot: Could not read planets DB', TICK_LOG_FILE_NPC);
        }
        else
        {
            $_already_done = false;
            while($planet_to_serve = $this->db->fetchrow($setpoint))
            {
                if($planet_to_serve['building_1'] < 9)
                {
                    $sql = 'UPDATE planets SET building_1 = 9, npc_last_action = '.$ACTUAL_TICK.' WHERE planet_id = '.$planet_to_serve['planet_id'];
                    $this->sdl->log('SQL 1.1 '.$sql, TICK_LOG_FILE_NPC);
                    $this->db->query($sql);
                    continue;
                }
                if($planet_to_serve['building_5'] < 9)
                {
                    $sql = 'UPDATE planets SET building_5 = 9, npc_last_action = '.$ACTUAL_TICK.' WHERE planet_id = '.$planet_to_serve['planet_id'];
                    $this->sdl->log('SQL 1'.$sql, TICK_LOG_FILE_NPC);
                    $this->db->query($sql);
                    continue;
                }
                if($planet_to_serve['building_2'] < 9)
                {
                    $sql = 'UPDATE planets SET building_2 = 9, npc_last_action = '.$ACTUAL_TICK.', recompute_static = 1 WHERE planet_id = '.$planet_to_serve['planet_id'];
                    $this->sdl->log('SQL 1.2'.$sql, TICK_LOG_FILE_NPC);
                    $this->db->query($sql);
                    continue;
                }if($planet_to_serve['building_3'] < 9)
                {
                    $sql = 'UPDATE planets SET building_3 = 9, npc_last_action = '.$ACTUAL_TICK.', recompute_static = 1 WHERE planet_id = '.$planet_to_serve['planet_id'];
                    $this->sdl->log('SQL 1.3'.$sql, TICK_LOG_FILE_NPC);
                    $this->db->query($sql);
                    continue;
                }if($planet_to_serve['building_4'] < 9)
                {
                    $sql = 'UPDATE planets SET building_4 = 9, npc_last_action = '.$ACTUAL_TICK.', recompute_static = 1 WHERE planet_id = '.$planet_to_serve['planet_id'];
                    $this->sdl->log('SQL 1.4'.$sql, TICK_LOG_FILE_NPC);
                    $this->db->query($sql);
                    continue;
                }
                if($planet_to_serve['building_12'] < 9)
                {
                    $sql = 'UPDATE planets SET building_12 = 9, npc_last_action = '.$ACTUAL_TICK.', recompute_static = 1 WHERE planet_id = '.$planet_to_serve['planet_id'];
                    $this->sdl->log('SQL 1.12'.$sql, TICK_LOG_FILE_NPC);
                    $this->db->query($sql);
                    continue;
                }
                // At this point, having rebuilt HQ and mines, Settlers automatically gains level 9 Academy.
                if($planet_to_serve['building_6'] < 9)
                {
                    $sql = 'UPDATE planets SET building_6 = 9, npc_last_action = '.$ACTUAL_TICK.', recompute_static = 1 WHERE planet_id = '.$planet_to_serve['planet_id'];
                    $this->sdl->log('SQL 2'.$sql, TICK_LOG_FILE_NPC);
                    $this->db->query($sql);
                    continue;
                }
                // Now the Spacedock
                if($planet_to_serve['building_7']< 3)
                {
                    $sql = 'UPDATE planets SET building_7 = 3, npc_last_action = '.$ACTUAL_TICK.', recompute_static = 1 WHERE planet_id = '.$planet_to_serve['planet_id'];
                    $this->sdl->log('SQL 3'.$sql, TICK_LOG_FILE_NPC);
                    $this->db->query($sql);
                    continue;
                }
                // Now the Spaceyard
                if($planet_to_serve['building_8'] < 1)
                {
                    $sql = 'UPDATE planets SET building_8 = 1, npc_last_action = '.$ACTUAL_TICK.', recompute_static = 1 WHERE planet_id = '.$planet_to_serve['planet_id'];
                    $this->sdl->log('SQL 4'.$sql, TICK_LOG_FILE_NPC);
                    $this->db->query($sql);
                    continue;
                }
                // Light orbital defense
                if(($planet_to_serve['building_10'] + $planet_to_serve['research_3']) < (14 + $planet_to_serve['research_3']))
                {
                    $sql = 'UPDATE planets SET building_10 = '.(14 + $planet_to_serve['research_3']).', npc_last_action = '.($ACTUAL_TICK + 10).', recompute_static = 1 WHERE planet_id = '.$planet_to_serve['planet_id'];
                    $this->sdl->log('SQL 5'.$sql, TICK_LOG_FILE_NPC);
                    $this->db->query($sql);
                    continue;
                }
                // Heavy orbital defense
                if(($planet_to_serve['building_13'] + $planet_to_serve['research_3']) < (14 + $planet_to_serve['research_3']))
                {
                    $sql = 'UPDATE planets SET building_13 = '.(14 + $planet_to_serve['research_3']).', npc_last_action = '.($ACTUAL_TICK + 30).', recompute_static = 1 WHERE planet_id = '.$planet_to_serve['planet_id'];
                    $this->sdl->log('SQL 6'.$sql, TICK_LOG_FILE_NPC);
                    $this->db->query($sql);
                    continue;
                }
                // Are there enough workers in the mines?
                if($planet_to_serve['workermine_1'] < 1000 || $planet_to_serve['workermine_2'] < 1000 || $planet_to_serve['workermine_3'] < 1000)
                {
                    $sql = 'UPDATE planets SET workermine_1 = 1000, workermine_2 = 1000, workermine_3 = 1000, npc_last_action = '.$ACTUAL_TICK.', recompute_static = 1 WHERE planet_id = '.$planet_to_serve['planet_id'];
                    $this->sdl->log('SQL 7'.$sql, TICK_LOG_FILE_NPC);
                    $this->db->query($sql);
                    continue;
                }

                // Are there enough security troops?
                $troops_check = ($planet_to_serve['unit_1']*2) + ($planet_to_serve['unit_2']*3) + ($planet_to_serve['unit_3']*4) + ($planet_to_serve['unit_4']*4);
                $troops_to_train = round(($planet_to_serve['min_security_troops'] - $troops_check) / 4);
                if($troops_to_train > 0)
                {
                    $sql = 'UPDATE planets SET unit_3 = unit_3 + '.$troops_to_train.', npc_last_action = '.$ACTUAL_TICK.', recompute_static = 1 WHERE planet_id = '.$planet_to_serve['planet_id'];
                    $this->sdl->log('SQL 7.1'.$sql, TICK_LOG_FILE_NPC);
                    $this->db->query($sql);
                    continue;
                }

                // Check for best mood!!!
                
                $newbest = false;
                
                $sql = 'SELECT user_id, SUM(mood_modifier) AS mood FROM settlers_relations
                        WHERE planet_id = '.$planet_to_serve['planet_id'].' GROUP BY user_id ORDER BY timestamp ASC';
                $q_p_m = $this->db->queryrowset($sql);
                
                // $this->sdl->log('DEBUG CHECK MOOD A'.$planet_to_serve['best_mood'].' '.$planet_to_serve['best_mood_user'], TICK_LOG_FILE_NPC);

                $newbest = $best = $best_id = 0;
                
                foreach($q_p_m as $q_m) {
                    if($q_m['mood'] > $best) {
                        $newbest = true;
                        $best = $q_m['mood'];
                        $best_id = $q_m['user_id'];
                    }
                }
                if($newbest) {
                    $sql = 'UPDATE planets
                            SET best_mood = '.$best.',
                            best_mood_user = '.$best_id.'
                            WHERE planet_id = '.$planet_to_serve['planet_id'];
                    $this->db->query($sql);
                }

                // $this->sdl->log('DEBUG CHECK MOOD B '.$best.' '.$best_id, TICK_LOG_FILE_NPC);
                
                if($best_id != $planet_to_serve['best_mood_user'])
                {
                    $log_data = array($planet_to_serve['planet_id'],$planet_to_serve['planet_name'], 0, '', 100);
                    add_logbook_entry($planet_to_serve['best_mood_user'], LOGBOOK_SETTLERS, 'Comunicazione prioritaria dalla colonia '.$planet_to_serve['planet_name'], $log_data);
                    $planet_to_serve['best_mood'] = $best;
                    $planet_to_serve['best_mood_user'] = $best_id;
                    $this->db->query('UPDATE planets SET best_mood_planet = NULL WHERE planet_id = '.$planet_to_serve['planet_id']);
                }
                
                // Let's activate the Academy! Sadly, we have to set ALL the fields for clearing them
                if($planet_to_serve['unittrain_actual'] == 0 &&
                  ($planet_to_serve['planet_type'] == 'm' || $planet_to_serve['planet_type'] == 'o' || $planet_to_serve['planet_type'] == 'p'))
                {
                    if($troops_to_train < 1) $troops_to_train = 0;
                    $sql = 'UPDATE planets SET unittrainid_1 = 5, unittrainid_2 = 6,
                                   unittrainid_3 = 0, unittrainid_4 = 0,
                                   unittrainid_5 = 0, unittrainid_6 = 0,
                                   unittrainid_7 = 0, unittrainid_8 = 0,
                                   unittrainid_9 = 0, unittrainid_10 = 0,
                                   unittrainnumber_1 = 3, unittrainnumber_2 = 1,
                                   unittrainnumber_3 = 0, unittrainnumber_4 = 0,
                                   unittrainnumber_5 = 0, unittrainnumber_6 = 0,
                                   unittrainnumber_7 = 0, unittrainnumber_8 = 0,
                                   unittrainnumber_9 = 0, unittrainnumber_10 = 0,
                                   unittrainnumberleft_1 = 3, unittrainnumberleft_2 = 1,
                                   unittrainnumberleft_3 = 0, unittrainnumberleft_4 = 0,
                                   unittrainnumberleft_5 = 0, unittrainnumberleft_6 = 0,
                                   unittrainnumberleft_7 = 0, unittrainnumberleft_8 = 0,
                                   unittrainnumberleft_9 = 0, unittrainnumberleft_10 = 0,
                                   unittrainendless_1 = 1, unittrainendless_2 = 1,
                                   unittrainendless_3 = 0, unittrainendless_4 = 0,
                                   unittrainendless_5 = 0, unittrainendless_6 = 0,
                                   unittrainendless_7 = 0, unittrainendless_8 = 0,
                                   unittrainendless_9 = 0, unittrainendless_10 = 0,
                                   unittrain_actual = 1, unittrainid_nexttime = '.($ACTUAL_TICK + 2).',
                                   npc_last_action = '.$ACTUAL_TICK.'
                            WHERE planet_id = '.$planet_to_serve['planet_id'];
                    $this->sdl->log('SQL A - 1A '.$sql, TICK_LOG_FILE_NPC);
                    $this->db->query($sql);
                    continue;
                }

                if($planet_to_serve['unittrain_actual'] == 0 &&
                  ($planet_to_serve['planet_type'] == 'a' ||
                   $planet_to_serve['planet_type'] == 'b' ||
                   $planet_to_serve['planet_type'] == 'c' ||
                   $planet_to_serve['planet_type'] == 'd' ||
                   $planet_to_serve['planet_type'] == 'n'))
                {
                    if($troops_to_train < 1) $troops_to_train = 0;
                    $sql = 'UPDATE planets SET unittrainid_1 = 1, unittrainid_2 = 2,
                                   unittrainid_3 = 0, unittrainid_4 = 0,
                                   unittrainid_5 = 0, unittrainid_6 = 0,
                                   unittrainid_7 = 0, unittrainid_8 = 0,
                                   unittrainid_9 = 0, unittrainid_10 = 0,
                                   unittrainnumber_1 = 2, unittrainnumber_2 = 1,
                                   unittrainnumber_3 = 0, unittrainnumber_4 = 0,
                                   unittrainnumber_5 = 0, unittrainnumber_6 = 0,
                                   unittrainnumber_7 = 0, unittrainnumber_8 = 0,
                                   unittrainnumber_9 = 0, unittrainnumber_10 = 0,
                                   unittrainnumberleft_1 = 2, unittrainnumberleft_2 = 1,
                                   unittrainnumberleft_3 = 0, unittrainnumberleft_4 = 0,
                                   unittrainnumberleft_5 = 0, unittrainnumberleft_6 = 0,
                                   unittrainnumberleft_7 = 0, unittrainnumberleft_8 = 0,
                                   unittrainnumberleft_9 = 0, unittrainnumberleft_10 = 0,
                                   unittrainendless_1 = 1, unittrainendless_2 = 1,
                                   unittrainendless_3 = 0, unittrainendless_4 = 0,
                                   unittrainendless_5 = 0, unittrainendless_6 = 0,
                                   unittrainendless_7 = 0, unittrainendless_8 = 0,
                                   unittrainendless_9 = 0, unittrainendless_10 = 0,
                                   unittrain_actual = 1, unittrainid_nexttime = '.($ACTUAL_TICK + 2).',
                                   npc_last_action = '.$ACTUAL_TICK.'
                            WHERE planet_id = '.$planet_to_serve['planet_id'];
                    $this->sdl->log('SQL A - 1B '.$sql, TICK_LOG_FILE_NPC);
                    $this->db->query($sql);
                    continue;
                }

                // A - 6
                // Settlers get back a ship from unsuccesful auction. 
                $sql='SELECT ship_id FROM FHB_warteschlange WHERE user_id = '.INDEPENDENT_USERID.' LIMIT 0,1';
                $notpayed=$this->db->queryrow($sql);
                if(isset($notpayed['ship_id']) && !empty($notpayed['ship_id']))
                {
                    $sql='DELETE FROM FHB_warteschlange WHERE ship_id = '.$notpayed['ship_id'];
                    $this->sdl->log('SQL A - 6A '.$sql, TICK_LOG_FILE_NPC);
                    $this->db->query($sql);
                    $sql='UPDATE ships SET user_id = '.INDEPENDENT_USERID.', fleet_id = -'.$planet_to_serve['planet_id'].' WHERE ship_id = '.$notpayed['ship_id'];
                    $this->sdl->log('SQL A - 6B '.$sql, TICK_LOG_FILE_NPC);
                    $this->db->query($sql);
                    $sql = 'UPDATE planets SET npc_last_action = '.($ACTUAL_TICK + 20).' WHERE planet_id = '.$planet_to_serve['planet_id'];
                    $this->sdl->log('SQL A - 6C '.$sql, TICK_LOG_FILE_NPC);
                    $this->db->query($sql);
                    continue;
                }

                // A - 2
                // Huge rewrite!
                // If we have a ship in the spacedock, we can send it to the best friend of the planet or
                // make and auction.
                // The query is made on all the planets, in order to free the deleted players spacedocks
                // Search for the oldest ship present in the spacedock.
                $sql = 'SELECT * FROM ships LEFT JOIN ship_templates ON ships.template_id = ship_templates.id
                        WHERE fleet_id = -'.$planet_to_serve['planet_id'].' AND ship_untouchable = 0
                        ORDER BY construction_time ASC
                        LIMIT 0,1';
                /**************************************************************/
                /* AC: I think would be lighter for the DB to:                */
                /* - first retrieve the oldest ship                           */
                /* - then retrieve the resources needed by its template       */
                /* instead of joining the two tables ships and ship_templates */
                /**************************************************************/
                $t_q=$this->db->queryrow($sql);
                if(isset($t_q['ship_id']) && !empty($t_q['ship_id']))
                {
                    $player_found = FALSE;
                    $setl_q = $this->db->queryrow($sql);
                    if($planet_to_serve['best_mood'] >= 120)
                    {
                        $player_chance = min((round(($planet_to_serve['best_mood'] - 120)/4 ,0) + 51), 96);
                        if($player_chance > rand(0,100)) $player_found = TRUE;
                    }
                    if($player_found)
                    {
                        // besty found. Make a fleet, put the ship into int, warn it about that.
                        // $sql = 'SELECT user_capital from user WHERE user_id = '.$planet_to_serve['best_mood_user'];
                        // $setl_bf = $this->db->queryrow($sql);
                        $sql='INSERT INTO ship_fleets (fleet_name, user_id, planet_id, move_id, n_ships)
                                     VALUES ("Reinforcement", '.$planet_to_serve['best_mood_user'].', '.$planet_to_serve['planet_id'].', 0, 1)';
                        $this->sdl->log('SQL A - 2.A1 '.$sql, TICK_LOG_FILE_NPC);
                        $this->db->query($sql);
                        $fleet_id = $this->db->insert_id();
                        $sql = 'UPDATE ships SET user_id = '.$planet_to_serve['best_mood_user'].', fleet_id = '.$fleet_id.' WHERE ship_id='.$t_q['ship_id'];
                        $this->sdl->log('SQL A - 2.A2 '.$sql, TICK_LOG_FILE_NPC);
                        $this->db->query($sql);
                        //Here we need multilanguage customization
                        $log_title = 'Nuova unit&agrave; disponibile dalla colonia indipendente '.$planet_to_serve['planet_name'];
                        $log_data = array($planet_to_serve['planet_id'], $planet_to_serve['planet_name'], $t_q['ship_id'], $t_q['name'], 0, 0);
                        add_logbook_entry($planet_to_serve['best_mood_user'], LOGBOOK_SETTLERS, $log_title, $log_data);
                        $sql = 'UPDATE planets SET npc_last_action = '.($ACTUAL_TICK + 20).' WHERE planet_id = '.$planet_to_serve['planet_id'];
                        $this->sdl->log('SQL A - 2.A5 '.$sql, TICK_LOG_FILE_NPC);
                        $this->db->query($sql);
                        continue;
                    }
                    else
                    {
                        // No besty found; let's make an auction.
                        $_ress_1 = round($t_q['resource_1']*0.16);
                        $_ress_2 = round($t_q['resource_2']*0.16);
                        $_ress_3 = round($t_q['resource_3']*0.16);
                        $_ress_1_step = round($t_q['resource_1']*0.03);
                        $_ress_2_step = round($t_q['resource_2']*0.03);
                        $_ress_3_step = round($t_q['resource_3']*0.03);
                        $sql = 'INSERT INTO ship_trade (user,planet,start_time,end_time,ship_id,resource_1,resource_2,resource_3,unit_1,unit_2,unit_3,unit_4,unit_5,unit_6,add_resource_1,add_resource_2,add_resource_3,add_unit_1,add_unit_2,add_unit_3,add_unit_4,add_unit_5,add_unit_6,header,description,show_data,font_bold,font_colored,unowed_only)
                                VALUES   ('.INDEPENDENT_USERID.','.$planet_to_serve['planet_id'].','.$ACTUAL_TICK.','.($ACTUAL_TICK + 480).','.$t_q['ship_id'].','.$_ress_1.','.$_ress_2.','.$_ress_3.', 0, 0, 0, 0,'.$t_q['unit_5'].','.$t_q['unit_6'].','.$_ress_1_step.','.$_ress_2_step.','.$_ress_3_step.',0,0,0,0,0,0,"'.$t_q['name'].'","This is an automatic generated auction for a ship held by the Settlers Community!!!",2,1,1,0)';
                        $this->sdl->log('SQL A - 2.B1 '.$sql, TICK_LOG_FILE_NPC);
                        $this->db->query($sql);
                        $sql = 'UPDATE ships SET ship_untouchable=1 WHERE ship_id='.$t_q['ship_id'];
                        $this->db->query($sql);
                        // This update is marginal and it could be removed to decrease the server load
                        $sql = 'UPDATE user SET num_auctions=num_auctions+1 WHERE user_id='.INDEPENDENT_USERID;
                        $this->db->query($sql);
                        $sql = 'UPDATE planets SET npc_last_action = '.($ACTUAL_TICK + 20).' WHERE planet_id = '.$planet_to_serve['planet_id'];
                        $this->sdl->log('SQL A - 2.B2 '.$sql, TICK_LOG_FILE_NPC);
                        $this->db->query($sql);
                        continue;
                    }

                }

                // A - 4
                // Troops sent to players / CC
                if($planet_to_serve['planet_type'] == 'a' || $planet_to_serve['planet_type'] == 'b' || $planet_to_serve['planet_type'] == 'c' || $planet_to_serve['planet_type'] == 'd' ||
                   $planet_to_serve['planet_type'] == 'n')
                {
                    // Supplies first and second level troops
                    $_ress_4 = $_unit_1 = $_unit_2 = 0;
                    $_unit_1_cut = $_unit_2_cut = 0;
                    $_unit_trade = 0.20;
                    $troops_check = ($planet_to_serve['unit_1']*2) + ($planet_to_serve['unit_2']*3) + ($planet_to_serve['unit_3']*4) + ($planet_to_serve['unit_4']*4);
                    $security_check = $planet_to_serve['min_security_troops'] * 0.75;
                    if($troops_check > $security_check) {
                        $_ress_4 = round(0.10*$planet_to_serve['resource_4']);
                        $planet_to_serve['resource_4'] = $planet_to_serve['resource_4'] - $_ress_4;
                        $_unit_1 = round(0.30*$planet_to_serve['unit_1']);
                        $planet_to_serve['unit_1'] = $planet_to_serve['unit_1'] - $_unit_1;
                        $_unit_2 = round(0.30*$planet_to_serve['unit_2']);
                        $planet_to_serve['unit_2'] = $planet_to_serve['unit_2'] - $_unit_2;
                    }
                    if(($_ress_4 + $_unit_1 + $_unit_2) > 0)
                    {
                        if($planet_to_serve['best_mood'] >= 120)
                        {
                            $_unit_1_cut = round($_unit_trade*$_unit_1);
                            $_unit_2_cut = round($_unit_trade*$_unit_2);
                            // $sql = 'UPDATE FHB_Handels_Lager SET unit_1=unit_1+'.$_unit_1_cut.', unit_2=unit_2+'.$_unit_2_cut.' WHERE id=1';
                            $avail_tick = mt_rand(23,420);
                            $avail_tick = $avail_tick + $ACTUAL_TICK;
                            $sql = 'INSERT INTO `FHB_cache_trupp_trade` (`unit_1` , `unit_2` , tick ) VALUES ('.$_unit_1_cut.', '.$_unit_2_cut.', '.$avail_tick.')';                                                        
                            $this->sdl->log('SQL A - 4.0.3.0'.$sql, TICK_LOG_FILE_NPC);
                            $this->db->query($sql);
                            // This is tricky!!!
                            // Il pianeta indicato in best_mood_planet potrebbe non appartenere più al giocatore, tuttavia
                            // in questo modo ci risparmiamo una lettura al DB
                            $log_title = 'Truppe in arrivo dalla colonia '.$planet_to_serve['planet_name'];
                            $log_data = array($planet_to_serve['planet_id'], $planet_to_serve['planet_name'], 0, 0, 101, 0);                            
                            if(is_null($planet_to_serve['best_mood_planet']))
                            {
                                $sql = 'SELECT user_capital from user WHERE user_id = '.$planet_to_serve['best_mood_user'];
                                $setl_bf = $this->db->queryrow($sql);
                                $target_planet = $setl_bf['user_capital'];
                            }
                            else {                                
                                $target_planet = $planet_to_serve['best_mood_planet'];
                                $log_data[5] = 1;
                                $log_data[6] = $target_planet;
                                $sql = 'SELECT planet_name from planets WHERE planet_id = '.$planet_to_serve['best_mood_planet'];
                                $setl_bfp = $this->db->queryrow($sql);                                
                                $log_data[7] = $setl_bfp['planet_name'];
                            }
                            $sql = 'INSERT INTO scheduler_resourcetrade (planet,resource_1,resource_2,resource_3,resource_4,unit_1,unit_2,unit_3,unit_4,unit_5,unit_6,arrival_time)
                                VALUES ('.$target_planet.', 0, 0, 0, '.$_ress_4.', '.($_unit_1 - $_unit_1_cut).', '.($_unit_2 - $_unit_2_cut).', 0, 0, 0, 0, '.($ACTUAL_TICK + 120).')';
                            $this->sdl->log('SQL A - 4.0.1'.$sql, TICK_LOG_FILE_NPC);
                            $this->db->query($sql);
                            $sql = 'UPDATE planets SET npc_last_action = '.($ACTUAL_TICK + 480).',
                                       resource_4 = '.$planet_to_serve['resource_4'].',
                                       unit_1 = '.$planet_to_serve['unit_1'].',
                                       unit_2 = '.$planet_to_serve['unit_2'].'
                                    WHERE planet_id = '.$planet_to_serve['planet_id'];
                            $this->sdl->log('SQL A - 4.0.2'.$sql, TICK_LOG_FILE_NPC);
                            $this->db->query($sql);
                            $log_data[8]['unit_1'] = $_unit_1 - $_unit_1_cut;
                            $log_data[8]['unit_2'] = $_unit_2 - $_unit_2_cut;
                            $log_data[8]['ress_4'] = $_ress_4;
                            add_logbook_entry($planet_to_serve['best_mood_user'], LOGBOOK_SETTLERS, $log_title, $log_data);
                            continue;
                        }
                        else
                        {
                            // $sql = 'UPDATE FHB_Handels_Lager SET unit_1=unit_1+'.$_unit_1.', unit_2=unit_2+'.$_unit_2.' WHERE id=1';
                            $avail_tick = mt_rand(23,420);
                            $avail_tick = $avail_tick + $ACTUAL_TICK;
                            $sql = 'INSERT INTO `FHB_cache_trupp_trade` (`unit_1` , `unit_2` , tick ) VALUES ('.$_unit_1.', '.$_unit_2.', '.$avail_tick.')';                            
                            $this->sdl->log('SQL A - 4.1.1'.$sql, TICK_LOG_FILE_NPC);
                            $this->db->query($sql);
                            $sql = 'UPDATE planets SET npc_last_action = '.($ACTUAL_TICK + 480).',
                                       unit_1 = '.$planet_to_serve['unit_1'].',
                                       unit_2 = '.$planet_to_serve['unit_2'].'
                                    WHERE planet_id = '.$planet_to_serve['planet_id'];
                            $this->sdl->log('SQL A - 4.1.2'.$sql, TICK_LOG_FILE_NPC);
                            $this->db->query($sql);
                            continue;
                        }
                    }
                }

                if($planet_to_serve['planet_type'] == 'm' || $planet_to_serve['planet_type'] == 'o' || $planet_to_serve['planet_type'] == 'p' || $planet_to_serve['planet_type'] == 'g' ||
                   $planet_to_serve['planet_type'] == 'h')
                {
                    // Supply workers and specs
                    $_ress_4 = $_unit_5 = $_unit_6 = 0;
                    $_unit_5_cut = $_unit_6_cut = 0;
                    $_unit_trade = 0.20;                    
                    $troops_check = ($planet_to_serve['unit_1']*2) + ($planet_to_serve['unit_2']*3) + ($planet_to_serve['unit_3']*4) + ($planet_to_serve['unit_4']*4);
                    $security_check = $planet_to_serve['min_security_troops'] * 0.75;
                    if($troops_check > $security_check) {
                        $_ress_4 = round(0.10*$planet_to_serve['resource_4']);
                        $planet_to_serve['resource_4'] = $planet_to_serve['resource_4'] - $_ress_4;
                        $_unit_5 = round(0.30*$planet_to_serve['unit_5']);
                        $planet_to_serve['unit_5'] = $planet_to_serve['unit_5'] - $_unit_5;
                        $_unit_6 = round(0.30*$planet_to_serve['unit_6']);
                        $planet_to_serve['unit_6'] = $planet_to_serve['unit_6'] - $_unit_6;
                    }
                    if(($_ress_4 + $_unit_5 + $_unit_6) > 0)
                    {
                        if($planet_to_serve['best_mood'] >= 120)
                        {
                            $_unit_5_cut = round($_unit_trade*$_unit_5);
                            $_unit_6_cut = round($_unit_trade*$_unit_6);
                            // $sql = 'UPDATE FHB_Handels_Lager SET unit_5=unit_5+'.$_unit_5_cut.', unit_6=unit_6+'.$_unit_6_cut.' WHERE id=1';
                            $avail_tick = mt_rand(23,420);
                            $avail_tick = $avail_tick + $ACTUAL_TICK;
                            $sql = 'INSERT INTO `FHB_cache_trupp_trade` (`unit_5` , `unit_6` , tick ) VALUES ('.$_unit_5_cut.', '.$_unit_6_cut.', '.$avail_tick.')';                            
                            $this->sdl->log('SQL A - 4.0.3'.$sql, TICK_LOG_FILE_NPC);
                            $this->db->query($sql);
                            $log_title = 'Truppe in arrivo dalla colonia '.$planet_to_serve['planet_name'];
                            $log_data = array($planet_to_serve['planet_id'], $planet_to_serve['planet_name'], 0, 0, 101, 0);                            
                            if(is_null($planet_to_serve['best_mood_planet']))
                            {
                                $sql = 'SELECT user_capital from user WHERE user_id = '.$planet_to_serve['best_mood_user'];
                                $setl_bf = $this->db->queryrow($sql);
                                $target_planet = $setl_bf['user_capital'];
                            }
                            else {
                                $target_planet = $planet_to_serve['best_mood_planet'];
                                $log_data[5] = 1;
                                $log_data[6] = $target_planet;
                                $sql = 'SELECT planet_name from planets WHERE planet_id = '.$planet_to_serve['best_mood_planet'];
                                $setl_bfp = $this->db->queryrow($sql);                                
                                $log_data[7] = $setl_bfp['planet_name'];                                
                            }
                            $sql = 'INSERT INTO scheduler_resourcetrade (planet,resource_1,resource_2,resource_3,resource_4,unit_1,unit_2,unit_3,unit_4,unit_5,unit_6,arrival_time)
                                VALUES ('.$target_planet.', 0, 0, 0, '.$_ress_4.', 0, 0, 0, 0, '.($_unit_5 - $_unit_5_cut).', '.($_unit_6 - $_unit_6_cut).', '.($ACTUAL_TICK + 120).')';
                            $this->sdl->log('SQL A - 4.3.1'.$sql, TICK_LOG_FILE_NPC);
                            $this->db->query($sql);
                            $sql = 'UPDATE planets SET npc_last_action = '.($ACTUAL_TICK + 480).',
                                       resource_4 = '.$planet_to_serve['resource_4'].',
                                       unit_5 = '.$planet_to_serve['unit_5'].',
                                       unit_6 = '.$planet_to_serve['unit_6'].'
                                    WHERE planet_id = '.$planet_to_serve['planet_id'];
                            $this->sdl->log('SQL A - 4.3.2'.$sql, TICK_LOG_FILE_NPC);
                            $this->db->query($sql);
                            $log_data[8]['unit_5'] = $_unit_5 - $_unit_5_cut;
                            $log_data[8]['unit_6'] = $_unit_6 - $_unit_6_cut;
                            $log_data[8]['ress_4'] = $_ress_4;
                            add_logbook_entry($planet_to_serve['best_mood_user'], LOGBOOK_SETTLERS, $log_title, $log_data);
                            continue;
                        }
                        else
                        {
                            // $sql = 'UPDATE FHB_Handels_Lager SET unit_5=unit_5+'.$_unit_5.', unit_6=unit_6+'.$_unit_6.' WHERE id=1';
                            $avail_tick = mt_rand(23,420);
                            $avail_tick = $avail_tick + $ACTUAL_TICK;
                            $sql = 'INSERT INTO `FHB_cache_trupp_trade` (`unit_5` , `unit_6` , tick ) VALUES ('.$_unit_5.', '.$_unit_6.', '.$avail_tick.')';
                            $this->sdl->log('SQL A - 4.4.1'.$sql, TICK_LOG_FILE_NPC);
                            $this->db->query($sql);
                            $sql = 'UPDATE planets SET npc_last_action = '.($ACTUAL_TICK + 480).',
                                       unit_5 = '.$planet_to_serve['unit_5'].',
                                       unit_6 = '.$planet_to_serve['unit_6'].'
                                    WHERE planet_id = '.$planet_to_serve['planet_id'];
                            $this->sdl->log('SQL A - 4.4.2'.$sql, TICK_LOG_FILE_NPC);
                            $this->db->query($sql);
                            continue;
                        }
                    }
                }

                // A - 4
                // Mining planets
                // We check the planet real production and get 55% of that
                // So the math is:  $_ress_X = 0.55*(add_X*20*24)
                if($planet_to_serve['planet_type'] == 'j' || $planet_to_serve['planet_type'] == 's' || $planet_to_serve['planet_type'] == 'i' ||
                   $planet_to_serve['planet_type'] == 't' || $planet_to_serve['planet_type'] == 'x' || $planet_to_serve['planet_type'] == 'y')
                {
                    $_ress_1 = $_ress_2 = $_ress_3 = 0;
                    $_ress_1_cut = $_ress_2_cut = $_ress_3_cut = 0;
                    // Should we put this value in a define?
                    $_ress_rateo = 0.55;
                    // Trading Center cut is 27%
                    $_ress_trade = 0.27;
                    if($planet_to_serve['planet_type'] == 'j')
                    {
                        $_ress_1 = round($_ress_rateo*($planet_to_serve['add_1']*20*24));
                        if($_ress_1 < $planet_to_serve['resource_1'])
                        {
                            $planet_to_serve['resource_1'] = $planet_to_serve['resource_1'] - $_ress_1;
                            $_ress_1_cut = round($_ress_trade*$_ress_1);
                        }
                        else
                            $_ress_1 = 0;
                    }
                    elseif($planet_to_serve['planet_type'] == 's')
                    {
                        $_ress_2 = round($_ress_rateo*($planet_to_serve['add_2']*20*24));
                        if($_ress_2 < $planet_to_serve['resource_2'])
                        {
                            $planet_to_serve['resource_2'] = $planet_to_serve['resource_2'] - $_ress_2;
                            $_ress_2_cut = round($_ress_trade*$_ress_2);
                        }
                        else
                            $_ress_2 = 0;
                    }
                    elseif($planet_to_serve['planet_type'] == 'i' || $planet_to_serve['planet_type'] == 't')
                    {
                        $_ress_3 = round($_ress_rateo*($planet_to_serve['add_3']*20*24));
                        if($_ress_3 < $planet_to_serve['resource_3'])
                        {
                            $planet_to_serve['resource_3'] = $planet_to_serve['resource_3'] - $_ress_3;
                            $_ress_3_cut = round($_ress_trade*$_ress_3);
                        }
                        else
                            $_ress_3 = 0;
                    }
                    elseif($planet_to_serve['planet_type'] == 'x' || $planet_to_serve['planet_type'] == 'y')
                    {
                        $_ress_1 = round($_ress_rateo*($planet_to_serve['add_1']*20*24));
                        if($_ress_1 < $planet_to_serve['resource_1'])
                        {
                            $planet_to_serve['resource_1'] = $planet_to_serve['resource_1'] - $_ress_1;
                            $_ress_1_cut = round($_ress_trade*$_ress_1);
                        }
                        else
                            $_ress_1 = 0;
                        $_ress_2 = round($_ress_rateo*($planet_to_serve['add_2']*20*24));
                        if($_ress_2 < $planet_to_serve['resource_2'])
                        {
                            $planet_to_serve['resource_2'] = $planet_to_serve['resource_2'] - $_ress_2;
                            $_ress_2_cut = round($_ress_trade*$_ress_2);
                        }
                        else
                            $_ress_2 = 0;
                        $_ress_3 = round($_ress_rateo*($planet_to_serve['add_3']*20*24));
                        if($_ress_3 < $planet_to_serve['resource_3'])
                        {
                            $planet_to_serve['resource_3'] = $planet_to_serve['resource_3'] - $_ress_3;
                            $_ress_3_cut = round($_ress_trade*$_ress_3);
                        }
                        else
                            $_ress_3 = 0;
                    }
                    if(($_ress_1 + $_ress_2 + $_ress_3) > 0)
                    {
                    	// Ships the cut to the tradecenter
                        $sql = 'UPDATE FHB_Handels_Lager SET ress_1=ress_1+'.$_ress_1_cut.',ress_2=ress_2+'.$_ress_2_cut.',ress_3=ress_3+'.$_ress_3_cut.' WHERE id=1';
                        $this->sdl->log('SQL A - 4.7'.$sql, TICK_LOG_FILE_NPC);
                    	$this->db->query($sql);

                        if($planet_to_serve['best_mood'] >= 120)
                        {
                            $log_title = 'Risorse in arrivo dalla colonia '.$planet_to_serve['planet_name'];
                            $log_data = array($planet_to_serve['planet_id'], $planet_to_serve['planet_name'], 0, 0, 102, 0);                            
                            if(is_null($planet_to_serve['best_mood_planet']))
                            {
                                $sql = 'SELECT user_capital from user WHERE user_id = '.$planet_to_serve['best_mood_user'];
                                $setl_bf = $this->db->queryrow($sql);
                                $target_planet = $setl_bf['user_capital'];
                            }
                            else {
                                $target_planet = $planet_to_serve['best_mood_planet'];
                                $log_data[5] = 1;
                                $log_data[6] = $target_planet;
                                $sql = 'SELECT planet_name from planets WHERE planet_id = '.$planet_to_serve['best_mood_planet'];
                                $setl_bfp = $this->db->queryrow($sql);                                
                                $log_data[7] = $setl_bfp['planet_name'];                                
                            }
                            $sql = 'INSERT INTO scheduler_resourcetrade (planet,resource_1,resource_2,resource_3,resource_4,unit_1,unit_2,unit_3,unit_4,unit_5,unit_6,arrival_time)
                                           VALUES ('.$target_planet.', '.($_ress_1 - $_ress_1_cut).', '.($_ress_2 - $_ress_2_cut).', '.($_ress_3 - $_ress_3_cut).', 0, 0, 0, 0, 0, 0, 0, '.($ACTUAL_TICK + 120).')';
                            $this->sdl->log('SQL A - 4.5.1'.$sql, TICK_LOG_FILE_NPC);
                            $this->db->query($sql);
                            $sql = 'UPDATE planets SET npc_last_action = '.($ACTUAL_TICK + 480).',
                                        resource_1 = '.$planet_to_serve['resource_1'].',
                                        resource_2 = '.$planet_to_serve['resource_2'].',
                                        resource_3 = '.$planet_to_serve['resource_3'].'
                                    WHERE planet_id = '.$planet_to_serve['planet_id'];
                            $this->sdl->log('SQL A - 4.5.2'.$sql, TICK_LOG_FILE_NPC);
                            $this->db->query($sql);
                            $log_data[8]['ress_1'] = $_ress_1 - $_ress_1_cut;
                            $log_data[8]['ress_2'] = $_ress_2 - $_ress_2_cut;
                            $log_data[8]['ress_3'] = $_ress_3 - $_ress_3_cut;
                            add_logbook_entry($planet_to_serve['best_mood_user'], LOGBOOK_SETTLERS, $log_title, $log_data);
                            continue;
                        }
                        else
                        {
                            $sql = 'UPDATE FHB_Handels_Lager SET ress_1=ress_1+'.$_ress_1.',ress_2=ress_2+'.$_ress_2.',ress_3=ress_3+'.$_ress_3.' WHERE id=1';
                            $this->sdl->log('SQL A - 4.6.1'.$sql, TICK_LOG_FILE_NPC);
                            $this->db->query($sql);
                            $sql = 'UPDATE planets SET npc_last_action = '.($ACTUAL_TICK + 480).',
                                        resource_1 = '.$planet_to_serve['resource_1'].',
                                        resource_2 = '.$planet_to_serve['resource_2'].',
                                        resource_3 = '.$planet_to_serve['resource_3'].'
                                    WHERE planet_id = '.$planet_to_serve['planet_id'];
                            $this->sdl->log('SQL A - 4.6.2'.$sql, TICK_LOG_FILE_NPC);
                            $this->db->query($sql);
                            continue;
                        }
                    }
                }

                // A - 3
                // Let's build a ship!!! We could avoid the use of the scheduler, just to decrease the overhead...
                // In the definitive version, only certain planets should build ships.
                /*
                if($planet_to_serve['planet_type'] == 'h' || $planet_to_serve['planet_type'] == 'n')
                {
                    $sql = 'SELECT COUNT(*) as ship_queue FROM scheduler_shipbuild WHERE planet_id = '.$planet_to_serve['planet_id'];
                    $s_q = $this->db->queryrow($sql);
                    if($s_q['ship_queue'] == 0)
                    {
                        $_buildtime = 720; // Yep, no access to the DB, let's avoid it...
                        $sql = 'INSERT INTO scheduler_shipbuild SET ship_type = '.$cfg_data['settler_tmp_1'].', planet_id = '.$planet_to_serve['planet_id'].', start_build = '.$ACTUAL_TICK.', finish_build = '.($ACTUAL_TICK + $_buildtime).', unit_1 = 56, unit_2 = 0, unit_3 = 0, unit_4 = 1';
                        $this->sdl->log('SQL A - 3.1 '.$sql, TICK_LOG_FILE_NPC);
                        $this->db->query($sql);
                        $sql = 'UPDATE planets SET npc_last_action = '.($ACTUAL_TICK + 20).' WHERE planet_id = '.$planet_to_serve['planet_id'];
                        $this->sdl->log('SQL A - 3.2 '.$sql, TICK_LOG_FILE_NPC);
                        $this->db->query($sql);
                        continue;
                    }
                }
                */
                
                if($planet_to_serve['planet_type'] == 'k' || $planet_to_serve['planet_type'] == 'l')
                {
                    // We build medium combat ships (hull 7)
                    $sql = 'SELECT COUNT(*) as ship_queue FROM scheduler_shipbuild WHERE planet_id = '.$planet_to_serve['planet_id'];
                    $s_q = $this->db->queryrow($sql);
                    if($s_q['ship_queue'] == 0)
                    {
                        $_buildtime = 1440; // Yep, no access to the DB, let's avoid it...
                        $sql = 'INSERT INTO scheduler_shipbuild SET ship_type = '.$cfg_data['settler_tmp_2'].', planet_id = '.$planet_to_serve['planet_id'].', start_build = '.$ACTUAL_TICK.', finish_build = '.($ACTUAL_TICK + $_buildtime).', unit_1 = 90, unit_2 = 30, unit_3 = 10, unit_4 = 1';
                        $this->sdl->log('SQL A - 3.1 '.$sql, TICK_LOG_FILE_NPC);
                        $this->db->query($sql);
                        $sql = 'UPDATE planets SET npc_last_action = '.($ACTUAL_TICK + 20).' WHERE planet_id = '.$planet_to_serve['planet_id'];
                        $this->sdl->log('SQL A - 3.2 '.$sql, TICK_LOG_FILE_NPC);
                        $this->db->query($sql);
                        continue;
                    }
                }
                
                if($planet_to_serve['planet_type'] == 'f' || $planet_to_serve['planet_type'] == 'e')
                {
                    $sql = 'SELECT COUNT(*) as ship_queue FROM scheduler_shipbuild WHERE planet_id = '.$planet_to_serve['planet_id'];
                    $s_q = $this->db->queryrow($sql);
                    // We build combat ships (hull 10)!!!
                    if($s_q['ship_queue'] == 0)
                    {
                        $_buildtime = 2880; // Yep, no access to the DB, let's avoid it...
                        $sql = 'INSERT INTO scheduler_shipbuild SET ship_type = '.$cfg_data['settler_tmp_3'].', planet_id = '.$planet_to_serve['planet_id'].', start_build = '.$ACTUAL_TICK.', finish_build = '.($ACTUAL_TICK + $_buildtime).', unit_1 = 200, unit_2 = 95, unit_3 = 65, unit_4 = 6';
                        $this->sdl->log('SQL A - 3.3 '.$sql, TICK_LOG_FILE_NPC);
                        $this->db->query($sql);
                        $sql = 'UPDATE planets SET npc_last_action = '.($ACTUAL_TICK + 20).' WHERE planet_id = '.$planet_to_serve['planet_id'];
                        $this->sdl->log('SQL A - 3.4 '.$sql, TICK_LOG_FILE_NPC);
                        $this->db->query($sql);
                        continue;
                    }
                }

                // A - 5
                // Planetary Orbital Shield!!!!
                // Let's give Settlers something for self-defense against Borg and nasty players
                $sql='SELECT fleet_id FROM ship_fleets WHERE fleet_name = "Orbital'.$planet_to_serve['planet_id'].'"';
                $od_q = $this->db->queryrow($sql);
                if(isset($od_q['fleet_id']) && !empty($od_q['fleet_id']))
                {
                    $sql='SELECT COUNT(*) AS counter FROM ships WHERE fleet_id = '.$od_q['fleet_id'];
                    $orbital_check=$this->db->queryrow($sql);
                    if($orbital_check['counter'] < 50)
                    {
                        //No access to DB, let's put the values right in, REMEMBER TO CHECK THE TEMPLATE IN THE DB WHEN EDITING THIS!!!
                        $sql = 'INSERT INTO ships (fleet_id, user_id, template_id, experience, hitpoints, construction_time, torp, rof, last_refit_time)
                                VALUES ('.$od_q['fleet_id'].', '.INDEPENDENT_USERID.', '.$cfg_data['settler_tmp_4'].', 0, 100, '.time().', 300, 5, '.time().')';
                        $this->db->query($sql);
                        $sql = 'UPDATE planets SET npc_last_action = '.($ACTUAL_TICK + 160).' WHERE planet_id = '.$planet_to_serve['planet_id'];
                        $this->sdl->log('SQL A - 5.1 '.$sql, TICK_LOG_FILE_NPC);
                        $this->db->query($sql);
                        continue;
                    }
                }
                else
                {
                // Orbital defence fleet not exists. Let's create this.
                    $sql = 'INSERT INTO ship_fleets (fleet_name, user_id, planet_id, alert_phase, move_id, n_ships)
                            VALUES ("Orbital'.$planet_to_serve['planet_id'].'", '.INDEPENDENT_USERID.', '.$planet_to_serve['planet_id'].', '.ALERT_PHASE_GREEN.', 0, 1)';
                    $this->db->query($sql);
                    $fleet_id = $this->db->insert_id();
                    $sql = 'INSERT INTO ships (fleet_id, user_id, template_id, experience, hitpoints, construction_time, torp, rof, last_refit_time)
                            VALUES ('.$fleet_id.', '.INDEPENDENT_USERID.', '.$cfg_data['settler_tmp_4'].', 0, 100, '.time().', 300, 5, '.time().')';
                    $this->db->query($sql);
                    $sql = 'UPDATE planets SET npc_last_action = '.($ACTUAL_TICK + 160).' WHERE planet_id = '.$planet_to_serve['planet_id'];
                    $this->sdl->log('SQL A - 5.0 '.$sql, TICK_LOG_FILE_NPC);
                    $this->db->query($sql);
                    continue;
                }
                
                $sql = 'UPDATE planets SET npc_last_action = '.($ACTUAL_TICK + 20).' WHERE planet_id = '.$planet_to_serve['planet_id'];
                $this->sdl->log('SQL E '.$sql, TICK_LOG_FILE_NPC);
                $this->db->query($sql);
            }
        }

        $this->sdl->finish_job('Mayflower Planets Building Control', TICK_LOG_FILE_NPC);
        // ########################################################################################
        // ########################################################################################
        // Auctions clean-up

        $this->sdl->start_job('Mayflower auctions clean-up', TICK_LOG_FILE_NPC);

        // Set to "every resources withdrawn" on Settler's paid auctions
        $sql = 'UPDATE schulden_table SET status=2
                WHERE user_ver = '.INDEPENDENT_USERID.' AND status = 1';
        if(!$this->db->query($sql)) {
            $this->sdl->log('<b>Error:</b> cannot update status to "resources withdrawn" for Settlers auctions',
                TICK_LOG_FILE_NPC);
        }

        $this->sdl->finish_job('Mayflower auctions clean-up', TICK_LOG_FILE_NPC);

        // ########################################################################################
        // ########################################################################################

        $this->sdl->log('<b>Finished Scheduler in <font color=#009900>'.round((microtime()+time())-$starttime, 4).' secs</font><br>Executed Queries: <font color=#ff0000>'.$this->db->i_query.'</font></b>', TICK_LOG_FILE_NPC);
    }
}


?>
