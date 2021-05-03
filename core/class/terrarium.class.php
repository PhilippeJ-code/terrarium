<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

  require_once __DIR__  . '/../../../../core/php/core.inc.php';

  // Classe eqLogic
  //
  class terrarium extends eqLogic
  {
      public static function deamon_info()
      {
          $return = array();
          $return['log'] = '';
          $return['state'] = 'nok';
          $cron = cron::byClassAndFunction(__CLASS__, 'daemon');
          if (is_object($cron) && $cron->running()) {
              $return['state'] = 'ok';
          }
          $return['launchable'] = 'ok';
          return $return;
      }

      public static function deamon_start()
      {
          self::deamon_stop();
          $deamon_info = self::deamon_info();
          if ($deamon_info['launchable'] != 'ok') {
              throw new Exception(__('Veuillez vérifier la configuration', __FILE__));
          }
          $cron = cron::byClassAndFunction(__CLASS__, 'daemon');
          if (!is_object($cron)) {
              $cron = new cron();
              $cron->setClass(__CLASS__);
              $cron->setFunction('daemon');
              $cron->setEnable(1);
              $cron->setDeamon(1);
              $cron->setTimeout(1440);
              $cron->setSchedule('* * * * *');
              $cron->save();
          }
          $cron->run();
      }

      public static function deamon_stop()
      {
          $cron = cron::byClassAndFunction(__CLASS__, 'daemon');
          if (is_object($cron)) {
              $cron->halt();
          }
      }

      public static function daemon()
      {
          foreach (terrarium::byType('terrarium', true) as $terrarium) {
              if ($terrarium->getIsEnable() == 1) {
                  $tempsRestant = $terrarium->getCache('tempsRestant', 10);
                  if ($tempsRestant > 0) {
                      $tempsRestant--;
                      if ($tempsRestant == 0) {
                          $terrarium->actionsBrumisationOff();
                      }
                      $terrarium->setCache('tempsRestant', $tempsRestant);
                  }
              }
          }
          sleep(1);
      }

      // Fonction exécutée toutes les minutes
      //
      public static function cron()
      {
 
          // Pour chacun des équipements
          //
          foreach (terrarium::byType('terrarium', true) as $terrarium) {
              if ($terrarium->getIsEnable() == 1) {

              // Calcul heure lever et coucher du soleil pour changement crons si nécessaire
                  //
                  $longitude = $terrarium->getConfiguration('longitude', 0);
                  $latitude = $terrarium->getConfiguration('latitude', 0);
                  $leverSoleil = $terrarium->getConfiguration('lever_soleil');
                  $coucherSoleil = $terrarium->getConfiguration('coucher_soleil');

                  $now = time();
                  $jour = date("d", $now);
                  $oldJour = $terrarium->getCache('oldJour', -1);
                  if ($oldJour != $jour) {
                      if ((is_numeric($longitude)) && (is_numeric($latitude))) {
                          $longitude = floatval($longitude);
                          $latitude = floatval($latitude);
                          if (($longitude != 0) && ($latitude != 0)) {
                              if ($leverSoleil) {
                                  $dateLever =  date_sunrise($now, 1, $latitude, $longitude, 90+35/60, date("Z", $now)/3600);
                                  $elms = explode(':', $dateLever);
                                  if (count($elms) == 2) {
                                      $heure = intval($elms[0], '0');
                                      $minute = intval($elms[1], '0');
                                      $cronLever = $minute . ' ' . $heure . ' * * *';
                                      $terrarium->setConfiguration('cron_jour', $cronLever);
                                  }
                              }
                              if ($coucherSoleil) {
                                  $dateCoucher =  date_sunset($now, 1, $latitude, $longitude, 90+35/60, date("Z", $now)/3600);
                                  $elms = explode(':', $dateCoucher);
                                  if (count($elms) == 2) {
                                      $heure = intval($elms[0], '0');
                                      $minute = intval($elms[1], '0');
                                      $cronCoucher = $minute . ' ' . $heure . ' * * *';
                                      $terrarium->setConfiguration('cron_nuit', $cronCoucher);
                                  }
                              }
                              if ($leverSoleil || $coucherSoleil) {
                                  $terrarium->save();
                              }
                          }
                      }
                      $terrarium->setCache('oldJour', $jour);
                  }

                  // Brumisation
                  //
                  $tempsBrume = $terrarium->getConfiguration('temps_brume', 15);
                  if (!is_numeric($tempsBrume)) {
                      $tempsBrume = 15;
                  }
                  $tempsBrume = intval($tempsBrume);
                  foreach ($terrarium->getConfiguration('schedule_brume_conf') as $schedule) {
                      $cron = $schedule['cmd'];
                
                      try {
                          $c = new Cron\CronExpression(checkAndFixCron($cron), new Cron\FieldFactory);
                          if ($c->isDue()) {
                              $terrarium->actionsBrumisationOn();
                              $terrarium->setCache('tempsRestant', $tempsBrume);
                          }
                      } catch (Exception $e) {
                          log::add('terrarium', 'error', $terrarium->getHumanName() . ' : ' . $e->getMessage());
                      }
                  }

                  // Première utilisation ou redémarrage on initialise
                  //
                  $statut = $terrarium->getCmd(null, 'status')->execCmd();
                  if (($statut != __('Jour', __FILE__)) && ($statut !=  __('Nuit', __FILE__))) {
                      log::add('terrarium', 'debug', 'Initialisation du terrarium');
                      $terrarium->getCmd(null, 'etat_verrou_eclairage')->event(0);
                      $terrarium->getCmd(null, 'etat_verrou_consignes')->event(0);

                      $now = time();
                      $heure = date("H", $now);

                      if (($heure > 21) || ($heure < 6)) {
                          $terrarium->nuit();
                      } else {
                          $terrarium->jour();
                      }

                      $terrarium->temperature();
                      $terrarium->humidite();
                      $terrarium->save();
                  }

                  // Est-il temps de passer en mode jour ?
                  //
                  if ($terrarium->getConfiguration('cron_jour') != '') {
                      try {
                          $c = new Cron\CronExpression(checkAndFixCron($terrarium->getConfiguration('cron_jour')), new Cron\FieldFactory);
                          if ($c->isDue()) {
                              $terrarium->jour();
                          }
                      } catch (Exception $e) {
                          log::add('terrarium', 'error', $terrarium->getHumanName() . ' : ' . $e->getMessage());
                      }
                  }

                  // Est-il temps de passer en mode nuit ?
                  //
                  if ($terrarium->getConfiguration('cron_nuit') != '') {
                      try {
                          $c = new Cron\CronExpression(checkAndFixCron($terrarium->getConfiguration('cron_nuit')), new Cron\FieldFactory);
                          if ($c->isDue()) {
                              $terrarium->nuit();
                          }
                      } catch (Exception $e) {
                          log::add('terrarium', 'error', $terrarium->getHumanName() . ' : ' . $e->getMessage());
                      }
                  }

                  // Est-il temps de répéter les actions d'éclairage ?
                  //
                  if ($terrarium->getConfiguration('cron_repetition_eclairage') != '') {
                      try {
                          $c = new Cron\CronExpression(checkAndFixCron($terrarium->getConfiguration('cron_repetition_eclairage')), new Cron\FieldFactory);
                          if ($c->isDue()) {
                              switch ($terrarium->getCmd(null, 'status')->execCmd()) {
                            case __('Jour', __FILE__):
                            $terrarium->actionsEclairageJour();
                            break;
                            case __('Nuit', __FILE__):
                            $terrarium->actionsEclairageNuit();
                            break;
                        }
                          }
                      } catch (Exception $e) {
                          log::add('terrarium', 'error', $terrarium->getHumanName() . ' : ' . $e->getMessage());
                      }
                  }

                  // Est-il temps de répéter les actions de chauffage ?
                  //
                  if ($terrarium->getConfiguration('cron_repetition_chauffage') != '') {
                      try {
                          $c = new Cron\CronExpression(checkAndFixCron($terrarium->getConfiguration('cron_repetition_chauffage')), new Cron\FieldFactory);
                          if ($c->isDue()) {
                              switch ($terrarium->getCmd(null, 'mode')->execCmd()) {
                                    case __('Chauffe', __FILE__):
                                        $terrarium->actionsChauffage();
                                        break;
                                    case __('Stoppé', __FILE__):
                                        $terrarium->actionsPasDeChauffage();
                                        break;
                                }
                              switch ($terrarium->getCmd(null, 'mode_ven')->execCmd()) {
                                    case __('Ventile', __FILE__):
                                        $terrarium->actionsVentile();
                                        break;
                                    case __('Stoppé', __FILE__):
                                        $terrarium->actionsPasVentile();
                                        break;
                                }
                          }
                      } catch (Exception $e) {
                          log::add('terrarium', 'error', $terrarium->getHumanName() . ' : ' . $e->getMessage());
                      }
                  }

                  // Est-il temps de répéter les actions d'humidité ?
                  //
                  if ($terrarium->getConfiguration('cron_repetition_humidite') != '') {
                      try {
                          $c = new Cron\CronExpression(checkAndFixCron($terrarium->getConfiguration('cron_repetition_humidite')), new Cron\FieldFactory);
                          if ($c->isDue()) {
                              switch ($terrarium->getCmd(null, 'mode_hum')->execCmd()) {
                                case __('Humidifie', __FILE__):
                                    $terrarium->actionsHumidite();
                                    break;
                                case __('Stoppé', __FILE__):
                                    $terrarium->actionsPasHumidite();
                                    break;
                            }
                          }
                      } catch (Exception $e) {
                          log::add('terrarium', 'error', $terrarium->getHumanName() . ' : ' . $e->getMessage());
                      }
                  }
              }
          }
      }
   
      // On exécute les actions brumisation On
      //
      public function actionsBrumisationOn()
      {
          foreach ($this->getConfiguration('brume_on_conf') as $action) {
              try {
                  $cmd = cmd::byId(str_replace('#', '', $action['cmd']));
                  if (!is_object($cmd)) {
                      continue;
                  }
                  $options = array();
                  if (isset($action['options'])) {
                      $options = $action['options'];
                  }
                  scenarioExpression::createAndExec('action', $action['cmd'], $options);
              } catch (Exception $e) {
                  log::add('terrarium', 'error', $this->getHumanName() . __(' : Erreur lors de l\'éxecution de ', __FILE__) . $action['cmd'] . __('. Détails : ', __FILE__) . $e->getMessage());
              }
          }
      }

      // On exécute les actions brumisation Off
      //
      public function actionsBrumisationOff()
      {
          foreach ($this->getConfiguration('brume_off_conf') as $action) {
              try {
                  $cmd = cmd::byId(str_replace('#', '', $action['cmd']));
                  if (!is_object($cmd)) {
                      continue;
                  }
                  $options = array();
                  if (isset($action['options'])) {
                      $options = $action['options'];
                  }
                  scenarioExpression::createAndExec('action', $action['cmd'], $options);
              } catch (Exception $e) {
                  log::add('terrarium', 'error', $this->getHumanName() . __(' : Erreur lors de l\'éxecution de ', __FILE__) . $action['cmd'] . __('. Détails : ', __FILE__) . $e->getMessage());
              }
          }
      }

      // On passe en jour
      //
      public function jour()
      {
          $this->getCmd(null, 'status')->event(__('Jour', __FILE__));
          $this->actionsEclairageJour();
          $this->actionsConsignesJour();
          if ($this->getCmd(null, 'mode_ven')->execCmd() === 'Ventile') {
              $this->pasVentile();
          }
      }

      // On exécute les actions d'éclairage jour
      //
      public function actionsEclairageJour()
      {
          if ($this->getCmd(null, 'etat_verrou_eclairage')->execCmd() == 1) {
              return;
          }
          foreach ($this->getConfiguration('ecl_jour_conf') as $action) {
              try {
                  $cmd = cmd::byId(str_replace('#', '', $action['cmd']));
                  if (!is_object($cmd)) {
                      continue;
                  }
                  $options = array();
                  if (isset($action['options'])) {
                      $options = $action['options'];
                  }
                  scenarioExpression::createAndExec('action', $action['cmd'], $options);
              } catch (Exception $e) {
                  log::add('terrarium', 'error', $this->getHumanName() . __(' : Erreur lors de l\'éxecution de ', __FILE__) . $action['cmd'] . __('. Détails : ', __FILE__) . $e->getMessage());
              }
          }
      }

      // On exécute les actions de consignes jour
      //
      public function actionsConsignesJour()
      {
          if ($this->getCmd(null, 'etat_verrou_consignes')->execCmd() == 1) {
              return;
          }
          foreach ($this->getConfiguration('csg_jour_conf') as $action) {
              try {
                  $cmd = cmd::byId(str_replace('#', '', $action['cmd']));
                  if (!is_object($cmd)) {
                      continue;
                  }
                  $options = array();
                  if (isset($action['options'])) {
                      $options = $action['options'];
                  }
                  scenarioExpression::createAndExec('action', $action['cmd'], $options);
              } catch (Exception $e) {
                  log::add('terrarium', 'error', $this->getHumanName() . __(' : Erreur lors de l\'éxecution de ', __FILE__) . $action['cmd'] . __('. Détails : ', __FILE__) . $e->getMessage());
              }
          }
      }

      // On passe en nuit
      //
      public function nuit()
      {
          $this->getCmd(null, 'status')->event(__('Nuit', __FILE__));
          $this->actionsEclairageNuit();
          $this->actionsConsignesNuit();
          $this->ventile();
      }

      // On éxécute les actions d'éclairage nuit
      //
      public function actionsEclairageNuit()
      {
          if ($this->getCmd(null, 'etat_verrou_eclairage')->execCmd() == 1) {
              return;
          }
      
          foreach ($this->getConfiguration('ecl_nuit_conf') as $action) {
              try {
                  $cmd = cmd::byId(str_replace('#', '', $action['cmd']));
                  if (!is_object($cmd)) {
                      continue;
                  }
                  $options = array();
                  if (isset($action['options'])) {
                      $options = $action['options'];
                  }
                  scenarioExpression::createAndExec('action', $action['cmd'], $options);
              } catch (Exception $e) {
                  log::add('terrarium', 'error', $this->getHumanName() . __(' : Erreur lors de l\'éxecution de ', __FILE__) . $action['cmd'] . __('. Détails : ', __FILE__) . $e->getMessage());
              }
          }
      }

      // On éxécute les actions consignes nuit
      //
      public function actionsConsignesNuit()
      {
          if ($this->getCmd(null, 'etat_verrou_consignes')->execCmd() == 1) {
              return;
          }
          foreach ($this->getConfiguration('csg_nuit_conf') as $action) {
              try {
                  $cmd = cmd::byId(str_replace('#', '', $action['cmd']));
                  if (!is_object($cmd)) {
                      continue;
                  }
                  $options = array();
                  if (isset($action['options'])) {
                      $options = $action['options'];
                  }
                  scenarioExpression::createAndExec('action', $action['cmd'], $options);
              } catch (Exception $e) {
                  log::add('terrarium', 'error', $this->getHumanName() . __(' : Erreur lors de l\'éxecution de ', __FILE__) . $action['cmd'] . __('. Détails : ', __FILE__) . $e->getMessage());
              }
          }
      }

      // Sur événement changement de la température du terrarium
      //
      public static function onTemperature($_options)
      {
          $terrarium = terrarium::byId($_options['terrarium_id']);
          if (!is_object($terrarium)) {
              return;
          }
          // Gestion de la température
          //
          $terrarium->temperature();
      }
        
      // Gestion de la température
      //
      public function temperature()
      {
          // Mémo de la température du terrarium
          //
          $this->getCmd(null, 'temperature')->event(jeedom::evaluateExpression($this->getConfiguration('temperature_terrarium')));

          $temperature = $this->getCmd(null, 'temperature')->execCmd();
          if (!is_numeric($temperature)) {
              return;
          }

          $consigne = $this->getCmd(null, 'consigne')->execCmd();
          if (!is_numeric($consigne)) {
              return;
          }
        
          $consigne_min = $consigne - $this->getConfiguration('hysteresis_min', 1);
          $consigne_max = $consigne + $this->getConfiguration('hysteresis_max', 1);

          $oldTemperature = $this->getCache('oldTemperature', -99);
          $this->setCache('oldTemperature', $temperature);
          $oldNow = $this->getCache('oldNow', 0);
          $now = time();
          $this->setCache('oldNow', $now);
          if (($oldTemperature != -99) && ($now != $oldNow)) {
              if ($oldTemperature > $temperature) {
                  $deltaBaisseMinute = (($oldTemperature - $temperature) * 60) / ($now - $oldNow);
                  $nombreBaisses = $this->getCache('nombreBaisses', 0) + 1;
                  $totalBaisses = $this->getCache('totalBaisses', 0) + $deltaBaisseMinute;
                  if ($nombreBaisses > 100) {
                      $this->setCache('moyenneBaisse', $totalBaisses/$nombreBaisses);
                      $nombreBaisses = 0;
                      $totalBaisses = 0;
                  }
                  $this->setCache('nombreBaisses', $nombreBaisses);
                  $this->setCache('totalBaisses', $totalBaisses);
              } elseif ($oldTemperature < $temperature) {
                  $deltaHausseMinute = (($temperature - $oldTemperature) * 60) / ($now - $oldNow);
                  $nombreHausses = $this->getCache('nombreHausses', 0) + 1;
                  $totalHausses = $this->getCache('totalHausses', 0) + $deltaHausseMinute;
                  if ($nombreHausses > 100) {
                      $this->setCache('moyenneHausse', $totalHausses/$nombreHausses);
                      $nombreHausses = 0;
                      $totalHausses = 0;
                  }
                  $this->setCache('nombreHausses', $nombreHausses);
                  $this->setCache('totalHausses', $totalHausses);
              }
          }

          $moyenneBaisse = $this->getCache('moyenneBaisse', 0);
          $moyenneHausse = $this->getCache('moyenneHausse', 0);
          if ($temperature <= $consigne_min + $moyenneBaisse / 2) {
              $this->chauffe();
          } elseif ($temperature >= $consigne_max - $moyenneHausse / 2) {
              $this->pasDeChauffe();
          }

          if ($this->getCmd(null, 'mode_ven')->execCmd() === 'Ventile') {
              if ($temperature <= $consigne_max + $moyenneBaisse / 2) {
                  $this->pasVentile();
              }
          }
      }

      // On chauffe
      //
      public function chauffe()
      {
          $this->getCmd(null, 'mode')->event(__('Chauffe', __FILE__));
          $this->actionsChauffage();
      }

      // On exécute les actions chauffage
      //
      public function actionsChauffage()
      {
          foreach ($this->getConfiguration('chf_oui_conf') as $action) {
              try {
                  $cmd = cmd::byId(str_replace('#', '', $action['cmd']));
                  if (!is_object($cmd)) {
                      continue;
                  }
                  $options = array();
                  if (isset($action['options'])) {
                      $options = $action['options'];
                  }
                  scenarioExpression::createAndExec('action', $action['cmd'], $options);
              } catch (Exception $e) {
                  log::add('terrarium', 'error', $this->getHumanName() . __(' : Erreur lors de l\'éxecution de ', __FILE__) . $action['cmd'] . __('. Détails : ', __FILE__) . $e->getMessage());
              }
          }
      }

      // On ne chauffe plus
      //
      public function pasDeChauffe()
      {
          $this->getCmd(null, 'mode')->event(__('Stoppé', __FILE__));
          $this->actionsPasDeChauffage();
      }

      // On n'exécute les actions on ne chauffe plus
      //
      public function actionsPasDeChauffage()
      {
          foreach ($this->getConfiguration('chf_non_conf') as $action) {
              try {
                  $cmd = cmd::byId(str_replace('#', '', $action['cmd']));
                  if (!is_object($cmd)) {
                      continue;
                  }
                  $options = array();
                  if (isset($action['options'])) {
                      $options = $action['options'];
                  }
                  scenarioExpression::createAndExec('action', $action['cmd'], $options);
              } catch (Exception $e) {
                  log::add('terrarium', 'error', $this->getHumanName() . __(' : Erreur lors de l\'éxecution de ', __FILE__) . $action['cmd'] . __('. Détails : ', __FILE__) . $e->getMessage());
              }
          }
      }

      // Sur événement changement de l'humidité du terrarium
      //
      public static function onHumidite($_options)
      {
          $terrarium = terrarium::byId($_options['terrarium_id']);
          if (!is_object($terrarium)) {
              return;
          }
          // Gestion de la température
          //
          $terrarium->humidite();
      }
        
      // Gestion de l'humidité
      //
      public function humidite()
      {
          // Mémo de l'humidité du terrarium
          //
          $this->getCmd(null, 'humidite')->event(jeedom::evaluateExpression($this->getConfiguration('humidite_terrarium')));

          $humidite = $this->getCmd(null, 'humidite')->execCmd();
          if (!is_numeric($humidite)) {
              return;
          }

          $consigne_hum = $this->getCmd(null, 'consigne_hum')->execCmd();
          if (!is_numeric($consigne_hum)) {
              return;
          }
        
          $consigne_hum_min = $consigne_hum - $this->getConfiguration('hysteresis_min_hum', 1);
          $consigne_hum_max = $consigne_hum + $this->getConfiguration('hysteresis_max_hum', 1);
 
          if ($humidite <= $consigne_hum_min) {
              $this->humidifie();
          } elseif ($humidite >= $consigne_hum_max) {
              $this->pasHumidifie();
          }
      }

      // On humidifie
      //
      public function humidifie()
      {
          $this->getCmd(null, 'mode_hum')->event(__('Humidifie', __FILE__));
          $this->actionsHumidite();
      }

      // On exécute les actions humidité
      //
      public function actionsHumidite()
      {
          foreach ($this->getConfiguration('hum_oui_conf') as $action) {
              try {
                  $cmd = cmd::byId(str_replace('#', '', $action['cmd']));
                  if (!is_object($cmd)) {
                      continue;
                  }
                  $options = array();
                  if (isset($action['options'])) {
                      $options = $action['options'];
                  }
                  scenarioExpression::createAndExec('action', $action['cmd'], $options);
              } catch (Exception $e) {
                  log::add('terrarium', 'error', $this->getHumanName() . __(' : Erreur lors de l\'éxecution de ', __FILE__) . $action['cmd'] . __('. Détails : ', __FILE__) . $e->getMessage());
              }
          }
      }

      // On n'humidife plus
      //
      public function pasHumidifie()
      {
          $this->getCmd(null, 'mode_hum')->event(__('Stoppé', __FILE__));
          $this->actionsPasHumidite();
      }

      // On exécute les actions pas humidité
      //
      public function actionsPasHumidite()
      {
          foreach ($this->getConfiguration('hum_non_conf') as $action) {
              try {
                  $cmd = cmd::byId(str_replace('#', '', $action['cmd']));
                  if (!is_object($cmd)) {
                      continue;
                  }
                  $options = array();
                  if (isset($action['options'])) {
                      $options = $action['options'];
                  }
                  scenarioExpression::createAndExec('action', $action['cmd'], $options);
              } catch (Exception $e) {
                  log::add('terrarium', 'error', $this->getHumanName() . __(' : Erreur lors de l\'éxecution de ', __FILE__) . $action['cmd'] . __('. Détails : ', __FILE__) . $e->getMessage());
              }
          }
      }

      // On ventile
      //
      public function ventile()
      {
          $this->getCmd(null, 'mode_ven')->event(__('Ventile', __FILE__));
          $this->actionsVentile();
      }

      // On exécute les actions ventilation
      //
      public function actionsVentile()
      {
          foreach ($this->getConfiguration('ven_oui_conf') as $action) {
              try {
                  $cmd = cmd::byId(str_replace('#', '', $action['cmd']));
                  if (!is_object($cmd)) {
                      continue;
                  }
                  $options = array();
                  if (isset($action['options'])) {
                      $options = $action['options'];
                  }
                  scenarioExpression::createAndExec('action', $action['cmd'], $options);
              } catch (Exception $e) {
                  log::add('terrarium', 'error', $this->getHumanName() . __(' : Erreur lors de l\'éxecution de ', __FILE__) . $action['cmd'] . __('. Détails : ', __FILE__) . $e->getMessage());
              }
          }
      }

      // On ne ventile plus
      //
      public function pasVentile()
      {
          $this->getCmd(null, 'mode_ven')->event(__('Stoppé', __FILE__));
          $this->actionsPasVentile();
      }

      // On exécute les actions pas ventilation
      //
      public function actionsPasVentile()
      {
          foreach ($this->getConfiguration('ven_non_conf') as $action) {
              try {
                  $cmd = cmd::byId(str_replace('#', '', $action['cmd']));
                  if (!is_object($cmd)) {
                      continue;
                  }
                  $options = array();
                  if (isset($action['options'])) {
                      $options = $action['options'];
                  }
                  scenarioExpression::createAndExec('action', $action['cmd'], $options);
              } catch (Exception $e) {
                  log::add('terrarium', 'error', $this->getHumanName() . __(' : Erreur lors de l\'éxecution de ', __FILE__) . $action['cmd'] . __('. Détails : ', __FILE__) . $e->getMessage());
              }
          }
      }

      // Sur événement changement de la consommation
      //
      public static function onConsommation($_options)
      {
          $terrarium = terrarium::byId($_options['terrarium_id']);
          if (!is_object($terrarium)) {
              return;
          }
          // Gestion de la consommation
          //
          $terrarium->consommation();
      }

      // Gestion de la consommation
      //
      public function consommation()
      {

          // On récupère la valeur de consommation précédente
          //
          $oldConsommation = $this->getConfiguration('oldConsommation', -1);
          $consommation = jeedom::evaluateExpression($this->getConfiguration('cmdConsommation'));
          if (!is_numeric($consommation)) {
              return;
          }
          $this->setConfiguration('oldConsommation', $consommation);
 
          // Si première fois, pas de cumul
          //
          if ($oldConsommation == -1) {
              $this->save();
              return;
          }
         
          // Différence entre les deux valeurs
          //
          $diff = $consommation - $oldConsommation;
          if ($diff < 0) {
              return;
          }

          // Une demi heure avant minuit pour avoir la bonne date en historisation
          //
          $plusTard = time()+30*60;

          // Historisation du jour si nécessaire
          //
          $jour = date("d", $plusTard);
          $oldJour = $this->getConfiguration('oldJour', 0);
          if ($jour != $oldJour) {
              $this->checkAndUpdateCmd('histoJour', $this->getConfiguration('consoJour', 0));
              $this->setConfiguration('consoJour', 0);
              $this->setConfiguration('oldJour', $jour);
          }
       
          // Historisation de la semaine si nécessaire
          //
          $jourSemaine = date("N", $plusTard);
          $oldJourSemaine = $this->getConfiguration('oldJourSemaine', 0);
          if ($jourSemaine != $oldJourSemaine) {
              if ($jourSemaine == 1) {
                  $this->checkAndUpdateCmd('histoSemaine', $this->getConfiguration('consoSemaine', 0));
                  $this->setConfiguration('consoSemaine', 0);
              }
              $this->setConfiguration('oldJourSemaine', $jourSemaine);
          }
       
          // Historisation du mois si nécessaire
          //
          $mois = date("m", $plusTard);
          $oldMois = $this->getConfiguration('oldMois', 0);
          if ($mois != $oldMois) {
              $this->checkAndUpdateCmd('histoMois', $this->getConfiguration('consoMois', 0));
              $this->setConfiguration('consoMois', 0);
              $this->setConfiguration('oldMois', $mois);
          }
       
          // Historisation de l'année si nécessaire
          //
          $annee = date("Y", $plusTard);
          $oldAnnee = $this->getConfiguration('oldAnnee', 0);
          if ($annee != $oldAnnee) {
              $this->checkAndUpdateCmd('histoAnnee', $this->getConfiguration('consoAnnee', 0));
              $this->setConfiguration('consoAnnee', 0);
              $this->setConfiguration('oldAnnee', $annee);
          }
       
          // Et j'ajoute la consommation instantanée
          //
          $conso = $this->getConfiguration('consoJour', 0) + $diff;
          $this->setConfiguration('consoJour', $conso);
          $this->checkAndUpdateCmd('consoJour', $conso);

          $conso = $this->getConfiguration('consoSemaine', 0) + $diff;
          $this->setConfiguration('consoSemaine', $conso);
          $this->checkAndUpdateCmd('consoSemaine', $conso);

          $conso = $this->getConfiguration('consoMois', 0) + $diff;
          $this->setConfiguration('consoMois', $conso);
          $this->checkAndUpdateCmd('consoMois', $conso);

          $conso = $this->getConfiguration('consoAnnee', 0) + $diff;
          $this->setConfiguration('consoAnnee', $conso);
          $this->checkAndUpdateCmd('consoAnnee', $conso);

          $this->save();
      }
      
      // Fonction exécutée automatiquement avant la création de l'équipement
      //
      public function preInsert()
      {
          $this->setCategory('energy', 1);
          $this->setCategory('heating', 1);
          $this->setCategory('light', 1);
      }

      // Fonction exécutée automatiquement après la création de l'équipement
      //
      public function postInsert()
      {
      }

      // Fonction exécutée automatiquement avant la mise à jour de l'équipement
      //
      public function preUpdate()
      {
      }

      // Fonction exécutée automatiquement après la mise à jour de l'équipement
      //
      public function postUpdate()
      {
      }

      // Fonction exécutée automatiquement avant la sauvegarde (création ou mise à jour) de l'équipement
      //
      public function preSave()
      {
          if ($this->getConfiguration('consigne_min') === '') {
              $this->setConfiguration('consigne_min', 20);
          }
          if ($this->getConfiguration('consigne_max') === '') {
              $this->setConfiguration('consigne_max', 30);
          }
          if ($this->getConfiguration('consigne_min') > $this->getConfiguration('consigne_max')) {
              throw new Exception(__('La température de consigne minimale ne peut être supérieure à la consigne maximale', __FILE__));
          }
          if ($this->getConfiguration('consigne_hum_min') === '') {
              $this->setConfiguration('consigne_hum_min', 0);
          }
          if ($this->getConfiguration('consigne_hum_max') === '') {
              $this->setConfiguration('consigne_hum_max', 100);
          }
          if ($this->getConfiguration('consigne_hum_min') > $this->getConfiguration('consigne_hum_max')) {
              throw new Exception(__('Humidité de consigne minimale ne peut être supérieure à la consigne maximale', __FILE__));
          }
          if ($this->getConfiguration('hysteresis_min') === '') {
              $this->setConfiguration('hysteresis_min', 0.5);
          }
          if ($this->getConfiguration('hysteresis_max') === '') {
              $this->setConfiguration('hysteresis_max', 1);
          }
          if ($this->getConfiguration('hysteresis_hum_min') === '') {
              $this->setConfiguration('hysteresis_hum_min', 1);
          }
          if ($this->getConfiguration('hysteresis_hum_max') === '') {
              $this->setConfiguration('hysteresis_hum_max', 1);
          }
      }

      // Fonction exécutée automatiquement après la sauvegarde (création ou mise à jour) de l'équipement
      //
      public function postSave()
      {
          // Statut Jour / Nuit
          //
          $status = $this->getCmd(null, 'status');
          if (!is_object($status)) {
              $status = new terrariumCmd();
              $status->setName(__('Statut', __FILE__));
              $status->setIsVisible(1);
              $status->setIsHistorized(0);
          }
          $status->setEqLogic_id($this->getId());
          $status->setLogicalId('status');
          $status->setType('info');
          $status->setSubType('string');
          $status->setOrder(1);
          $status->save();

          $lock = $this->getCmd(null, 'statut_jour');
          if (!is_object($lock)) {
              $lock = new terrariumCmd();
              $lock->setName('Statut Jour');
              $lock->setIsVisible(1);
              $lock->setIsHistorized(0);
          }
          $lock->setEqLogic_id($this->getId());
          $lock->setType('action');
          $lock->setSubType('other');
          $lock->setLogicalId('statut_jour');
          $lock->setValue($status->getId());
          $lock->setOrder(2);
          $lock->save();
  
          $unlock = $this->getCmd(null, 'statut_nuit');
          if (!is_object($unlock)) {
              $unlock = new terrariumCmd();
              $unlock->setName('Statut Nuit');
              $unlock->setIsVisible(1);
              $unlock->setIsHistorized(0);
          }
          $unlock->setEqLogic_id($this->getId());
          $unlock->setType('action');
          $unlock->setSubType('other');
          $unlock->setLogicalId('statut_nuit');
          $unlock->setValue($status->getId());
          $unlock->setOrder(3);
          $unlock->save();
  
          // Gestion éclairage verrouillé ou pas
          //
          //   L'état, le on et le off
          //
          $etatVerrouEclairage = $this->getCmd(null, 'etat_verrou_eclairage');
          if (!is_object($etatVerrouEclairage)) {
              $etatVerrouEclairage = new terrariumCmd();
              $etatVerrouEclairage->setName(__('Verrou Eclairage', __FILE__));
              $etatVerrouEclairage->setIsVisible(1);
              $etatVerrouEclairage->setIsHistorized(0);
          }
          $etatVerrouEclairage->setEqLogic_id($this->getId());
          $etatVerrouEclairage->setType('info');
          $etatVerrouEclairage->setSubType('binary');
          $etatVerrouEclairage->setLogicalId('etat_verrou_eclairage');
          $etatVerrouEclairage->setOrder(4);
          $etatVerrouEclairage->save();
  
          $lock = $this->getCmd(null, 'on_verrou_eclairage');
          if (!is_object($lock)) {
              $lock = new terrariumCmd();
              $lock->setName('Verrou Eclairage On');
              $lock->setIsVisible(1);
              $lock->setIsHistorized(0);
          }
          $lock->setEqLogic_id($this->getId());
          $lock->setType('action');
          $lock->setSubType('other');
          $lock->setLogicalId('on_verrou_eclairage');
          $lock->setValue($etatVerrouEclairage->getId());
          $lock->setOrder(5);
          $lock->save();
  
          $unlock = $this->getCmd(null, 'off_verrou_eclairage');
          if (!is_object($unlock)) {
              $unlock = new terrariumCmd();
              $unlock->setName('Verrou Eclairage Off');
              $unlock->setIsVisible(1);
              $unlock->setIsHistorized(0);
          }
          $unlock->setEqLogic_id($this->getId());
          $unlock->setType('action');
          $unlock->setSubType('other');
          $unlock->setLogicalId('off_verrou_eclairage');
          $unlock->setValue($etatVerrouEclairage->getId());
          $unlock->setOrder(6);
          $unlock->save();
  
          // Gestion consignes verrouillé ou pas
          //
          //   L'état, le on et le off
          //
          $etatVerrouConsignes = $this->getCmd(null, 'etat_verrou_consignes');
          if (!is_object($etatVerrouConsignes)) {
              $etatVerrouConsignes = new terrariumCmd();
              $etatVerrouConsignes->setName(__('Verrou Consignes', __FILE__));
              $etatVerrouConsignes->setIsVisible(1);
              $etatVerrouConsignes->setIsHistorized(0);
          }
          $etatVerrouConsignes->setEqLogic_id($this->getId());
          $etatVerrouConsignes->setType('info');
          $etatVerrouConsignes->setSubType('binary');
          $etatVerrouConsignes->setLogicalId('etat_verrou_consignes');
          $etatVerrouConsignes->setOrder(7);
          $etatVerrouConsignes->save();
  
          $lock = $this->getCmd(null, 'on_verrou_consignes');
          if (!is_object($lock)) {
              $lock = new terrariumCmd();
              $lock->setName('Verrou Consignes On');
              $lock->setIsVisible(1);
              $lock->setIsHistorized(0);
          }
          $lock->setEqLogic_id($this->getId());
          $lock->setType('action');
          $lock->setSubType('other');
          $lock->setLogicalId('on_verrou_consignes');
          $lock->setValue($etatVerrouConsignes->getId());
          $lock->setOrder(8);
          $lock->save();
  
          $unlock = $this->getCmd(null, 'off_verrou_consignes');
          if (!is_object($unlock)) {
              $unlock = new terrariumCmd();
              $unlock->setName('Verrou Consignes Off');
              $unlock->setIsVisible(1);
              $unlock->setIsHistorized(0);
          }
          $unlock->setEqLogic_id($this->getId());
          $unlock->setType('action');
          $unlock->setSubType('other');
          $unlock->setLogicalId('off_verrou_consignes');
          $unlock->setValue($etatVerrouConsignes->getId());
          $unlock->setOrder(9);
          $unlock->save();
  
          // Mode chauffage
          //
          $mode = $this->getCmd(null, 'mode');
          if (!is_object($mode)) {
              $mode = new terrariumCmd();
              $mode->setIsVisible(1);
              $mode->setName(__('Mode', __FILE__));
              $mode->setIsVisible(1);
              $mode->setIsHistorized(0);
          }
          $mode->setEqLogic_id($this->getId());
          $mode->setLogicalId('mode');
          $mode->setType('info');
          $mode->setSubType('string');
          $mode->setOrder(10);
          $mode->save();

          // Mode humidité
          //
          $mode_hum = $this->getCmd(null, 'mode_hum');
          if (!is_object($mode_hum)) {
              $mode_hum = new terrariumCmd();
              $mode_hum->setIsVisible(1);
              $mode_hum->setName(__('Mode Humidité', __FILE__));
              $mode_hum->setIsVisible(1);
              $mode_hum->setIsHistorized(0);
          }
          $mode_hum->setEqLogic_id($this->getId());
          $mode_hum->setLogicalId('mode_hum');
          $mode_hum->setType('info');
          $mode_hum->setSubType('string');
          $mode_hum->setOrder(11);
          $mode_hum->save();

          // Mode ventilation
          //
          $mode_ven = $this->getCmd(null, 'mode_ven');
          if (!is_object($mode_ven)) {
              $mode_ven = new terrariumCmd();
              $mode_ven->setIsVisible(1);
              $mode_ven->setName(__('Mode Ventilation', __FILE__));
              $mode_ven->setIsVisible(1);
              $mode_ven->setIsHistorized(0);
          }
          $mode_ven->setEqLogic_id($this->getId());
          $mode_ven->setLogicalId('mode_ven');
          $mode_ven->setType('info');
          $mode_ven->setSubType('string');
          $mode_ven->setOrder(11);
          $mode_ven->save();

          $consigne = $this->getCmd(null, 'consigne');
          if (!is_object($consigne)) {
              $consigne = new terrariumCmd();
              $consigne->setUnite('°C');
              $consigne->setName(__('Consigne', __FILE__));
              $consigne->setIsVisible(1);
              $consigne->setIsHistorized(0);
          }
          $consigne->setEqLogic_id($this->getId());
          $consigne->setType('info');
          $consigne->setSubType('numeric');
          $consigne->setLogicalId('consigne');
          $consigne->setConfiguration('minValue', $this->getConfiguration('consigne_min'));
          $consigne->setConfiguration('maxValue', $this->getConfiguration('consigne_max'));
          $consigne->setOrder(12);
          $consigne->save();
  
          $thermostat = $this->getCmd(null, 'thermostat');
          if (!is_object($thermostat)) {
              $thermostat = new terrariumCmd();
              $thermostat->setUnite('°C');
              $thermostat->setName(__('Thermostat', __FILE__));
              $thermostat->setIsVisible(1);
              $thermostat->setIsHistorized(0);
          }
          $thermostat->setEqLogic_id($this->getId());
          $thermostat->setType('action');
          $thermostat->setSubType('slider');
          $thermostat->setLogicalId('thermostat');
          $thermostat->setValue($consigne->getId());
          $thermostat->setConfiguration('minValue', $this->getConfiguration('consigne_min'));
          $thermostat->setConfiguration('maxValue', $this->getConfiguration('consigne_max'));
          $thermostat->setOrder(13);
          $thermostat->save();

          $consigne_hum = $this->getCmd(null, 'consigne_hum');
          if (!is_object($consigne_hum)) {
              $consigne_hum = new terrariumCmd();
              $consigne_hum->setUnite('%');
              $consigne_hum->setName(__('Consigne humidité', __FILE__));
              $consigne_hum->setIsVisible(1);
              $consigne_hum->setIsHistorized(0);
          }
          $consigne_hum->setEqLogic_id($this->getId());
          $consigne_hum->setType('info');
          $consigne_hum->setSubType('numeric');
          $consigne_hum->setLogicalId('consigne_hum');
          $consigne_hum->setConfiguration('minValue', $this->getConfiguration('consigne_hum_min'));
          $consigne_hum->setConfiguration('maxValue', $this->getConfiguration('consigne_hum_max'));
          $consigne_hum->setOrder(14);
          $consigne_hum->save();
  
          $thermostat_hum = $this->getCmd(null, 'thermostat_hum');
          if (!is_object($thermostat_hum)) {
              $thermostat_hum = new terrariumCmd();
              $thermostat_hum->setUnite('%');
              $thermostat_hum->setName(__('Thermostat Humidité', __FILE__));
              $thermostat_hum->setIsVisible(1);
              $thermostat_hum->setIsHistorized(0);
          }
          $thermostat_hum->setEqLogic_id($this->getId());
          $thermostat_hum->setType('action');
          $thermostat_hum->setSubType('slider');
          $thermostat_hum->setLogicalId('thermostat_hum');
          $thermostat_hum->setValue($consigne_hum->getId());
          $thermostat_hum->setConfiguration('minValue', $this->getConfiguration('consigne_hum_min'));
          $thermostat_hum->setConfiguration('maxValue', $this->getConfiguration('consigne_hum_max'));
          $thermostat_hum->setOrder(15);
          $thermostat_hum->save();

          $temperature = $this->getCmd(null, 'temperature');
          if (!is_object($temperature)) {
              $temperature = new terrariumCmd();
              $temperature->setName(__('Température', __FILE__));
              $temperature->setIsVisible(1);
              $temperature->setIsHistorized(0);
          }
          $temperature->setEqLogic_id($this->getId());
          $temperature->setType('info');
          $temperature->setSubType('numeric');
          $temperature->setLogicalId('temperature');
          $temperature->setUnite('°C');
          $temperature->setOrder(16);
          $temperature->save();

          $humidite = $this->getCmd(null, 'humidite');
          if (!is_object($humidite)) {
              $humidite = new terrariumCmd();
              $humidite->setName(__('Humidité', __FILE__));
              $humidite->setIsVisible(1);
              $humidite->setIsHistorized(0);
          }
          $humidite->setEqLogic_id($this->getId());
          $humidite->setType('info');
          $humidite->setSubType('numeric');
          $humidite->setLogicalId('humidité');
          $humidite->setUnite('%');
          $humidite->setOrder(17);
          $humidite->save();

          $obj = $this->getCmd(null, 'consoJour');
          if (!is_object($obj)) {
              $obj = new terrariumCmd();
              $obj->setName(__('ConsoJour', __FILE__));
          }
          $obj->setEqLogic_id($this->getId());
          $obj->setLogicalId('consoJour');
          $obj->setType('info');
          $obj->setSubType('numeric');
          $obj->setOrder(18);
          $obj->save();

          $obj = $this->getCmd(null, 'histoJour');
          if (!is_object($obj)) {
              $obj = new terrariumCmd();
              $obj->setName(__('HistoJour', __FILE__));
              $obj->setIsVisible(0);
              $obj->setIsHistorized(1);
          }
          $obj->setEqLogic_id($this->getId());
          $obj->setLogicalId('histoJour');
          $obj->setType('info');
          $obj->setSubType('numeric');
          $obj->setOrder(19);
          $obj->save();

          $obj = $this->getCmd(null, 'consoSemaine');
          if (!is_object($obj)) {
              $obj = new terrariumCmd();
              $obj->setName(__('ConsoSemaine', __FILE__));
          }
          $obj->setEqLogic_id($this->getId());
          $obj->setLogicalId('consoSemaine');
          $obj->setType('info');
          $obj->setSubType('numeric');
          $obj->setOrder(20);
          $obj->save();

          $obj = $this->getCmd(null, 'histoSemaine');
          if (!is_object($obj)) {
              $obj = new terrariumCmd();
              $obj->setName(__('HistoSemaine', __FILE__));
              $obj->setIsVisible(0);
              $obj->setIsHistorized(1);
          }
          $obj->setEqLogic_id($this->getId());
          $obj->setLogicalId('histoSemaine');
          $obj->setType('info');
          $obj->setSubType('numeric');
          $obj->setOrder(21);
          $obj->save();

          $obj = $this->getCmd(null, 'consoMois');
          if (!is_object($obj)) {
              $obj = new terrariumCmd();
              $obj->setName(__('ConsoMois', __FILE__));
          }
          $obj->setEqLogic_id($this->getId());
          $obj->setLogicalId('consoMois');
          $obj->setType('info');
          $obj->setSubType('numeric');
          $obj->setOrder(22);
          $obj->save();

          $obj = $this->getCmd(null, 'histoMois');
          if (!is_object($obj)) {
              $obj = new terrariumCmd();
              $obj->setName(__('HistoMois', __FILE__));
              $obj->setIsVisible(0);
              $obj->setIsHistorized(1);
          }
          $obj->setEqLogic_id($this->getId());
          $obj->setLogicalId('histoMois');
          $obj->setType('info');
          $obj->setSubType('numeric');
          $obj->setOrder(23);
          $obj->save();

          $obj = $this->getCmd(null, 'consoAnnee');
          if (!is_object($obj)) {
              $obj = new terrariumCmd();
              $obj->setName(__('ConsoAnnee', __FILE__));
          }
          $obj->setEqLogic_id($this->getId());
          $obj->setLogicalId('consoAnnee');
          $obj->setType('info');
          $obj->setSubType('numeric');
          $obj->setOrder(24);
          $obj->save();

          $obj = $this->getCmd(null, 'histoAnnee');
          if (!is_object($obj)) {
              $obj = new terrariumCmd();
              $obj->setName(__('HistoAnnee', __FILE__));
              $obj->setIsVisible(0);
              $obj->setIsHistorized(1);
          }
          $obj->setEqLogic_id($this->getId());
          $obj->setLogicalId('histoAnnee');
          $obj->setType('info');
          $obj->setSubType('numeric');
          $obj->setOrder(25);
          $obj->save();

          if ($this->getIsEnable() == 1) {

              // On écoute les événements qui interviennent dans la gestion du chauffage
              //
              //   La température du terrarium
              //   La consigne de température
              //   L'humidité du terrarium
              //   La consigne d'humidité
              //
              $listener = listener::byClassAndFunction('terrarium', 'onTemperature', array('terrarium_id' => intval($this->getId())));
              if (!is_object($listener)) {
                  $listener = new listener();
              }
              $listener->setClass('terrarium');
              $listener->setFunction('onTemperature');
              $listener->setOption(array('terrarium_id' => intval($this->getId())));
              $listener->emptyEvent();
              $cmd_id = $this->getConfiguration('temperature_terrarium');
              $listener->addEvent($cmd_id);
              $listener->addEvent($consigne->getId());
              $listener->save();

              $listener = listener::byClassAndFunction('terrarium', 'onHumidite', array('terrarium_id' => intval($this->getId())));
              if (!is_object($listener)) {
                  $listener = new listener();
              }
              $listener->setClass('terrarium');
              $listener->setFunction('onHumidite');
              $listener->setOption(array('terrarium_id' => intval($this->getId())));
              $listener->emptyEvent();
              $cmd_id = $this->getConfiguration('humidite_terrarium');
              $listener->addEvent($cmd_id);
              $listener->addEvent($consigne_hum->getId());
              $listener->save();

              // On écoute les événements qui interviennent dans la consommation
              //
              //
              $listener = listener::byClassAndFunction('terrarium', 'onConsommation', array('terrarium_id' => intval($this->getId())));
              if (!is_object($listener)) {
                  $listener = new listener();
              }
              $listener->setClass('terrarium');
              $listener->setFunction('onConsommation');
              $listener->setOption(array('terrarium_id' => intval($this->getId())));
              $listener->emptyEvent();
              $cmd_id = $this->getConfiguration('cmdConsommation');
              $listener->addEvent($cmd_id);
              $listener->save();
          } else {
              // On supprime les écoutes
              //
              $listener = listener::byClassAndFunction('terrarium', 'onTemperature', array('terrarium_id' => intval($this->getId())));
              if (is_object($listener)) {
                  $listener->remove();
              }

              $listener = listener::byClassAndFunction('terrarium', 'onHumidite', array('terrarium_id' => intval($this->getId())));
              if (is_object($listener)) {
                  $listener->remove();
              }

              $listener = listener::byClassAndFunction('terrarium', 'onConsommation', array('terrarium_id' => intval($this->getId())));
              if (is_object($listener)) {
                  $listener->remove();
              }
          }
      }

      // Fonction exécutée automatiquement avant la suppression de l'équipement
      //
      public function preRemove()
      {
          // On supprime les écoutes
          //
          $listener = listener::byClassAndFunction('terrarium', 'onTemperature', array('terrarium_id' => intval($this->getId())));
          if (is_object($listener)) {
              $listener->remove();
          }

          $listener = listener::byClassAndFunction('terrarium', 'onHumidite', array('terrarium_id' => intval($this->getId())));
          if (is_object($listener)) {
              $listener->remove();
          }

          $listener = listener::byClassAndFunction('terrarium', 'onConsommation', array('terrarium_id' => intval($this->getId())));
          if (is_object($listener)) {
              $listener->remove();
          }
      }

      // Fonction exécutée automatiquement après la suppression de l'équipement
      //
      public function postRemove()
      {
      }

      // Permet de modifier l'affichage du widget (également utilisable par les commandes)
      //
      public function toHtml($_version = 'dashboard')
      {
          $isWidgetPlugin = $this->getConfiguration('isWidgetPlugin');

          if (!$isWidgetPlugin) {
              return eqLogic::toHtml($_version);
          }

          $replace = $this->preToHtml($_version);
          if (!is_array($replace)) {
              return $replace;
          }
          $version = jeedom::versionAlias($_version);
 
          $obj = $this->getCmd(null, 'status');
          $replace["#statut#"] = $obj->execCmd();
          $replace["#idStatut#"] = $obj->getId();

          $obj = $this->getCmd(null, 'statut_jour');
          $replace["#idStatutJour#"] = $obj->getId();

          $obj = $this->getCmd(null, 'statut_nuit');
          $replace["#idStatutNuit#"] = $obj->getId();

          $obj = $this->getCmd(null, 'mode');
          $replace["#mode#"] = $obj->execCmd();
          $replace["#idMode#"] = $obj->getId();

          $obj = $this->getCmd(null, 'mode_hum');
          $replace["#mode_hum#"] = $obj->execCmd();
          $replace["#idMode_hum#"] = $obj->getId();

          $obj = $this->getCmd(null, 'mode_ven');
          $replace["#mode_ven#"] = $obj->execCmd();
          $replace["#idMode_ven#"] = $obj->getId();

          $obj = $this->getCmd(null, 'etat_verrou_eclairage');
          $replace["#etatVerrouEclairage#"] = $obj->execCmd();
          $replace["#idEtatVerrouEclairage#"] = $obj->getId();
           
          $obj = $this->getCmd(null, 'on_verrou_eclairage');
          $replace["#idOnVerrouEclairage#"] = $obj->getId();

          $obj = $this->getCmd(null, 'off_verrou_eclairage');
          $replace["#idOffVerrouEclairage#"] = $obj->getId();

          $obj = $this->getCmd(null, 'etat_verrou_consignes');
          $replace["#etatVerrouConsignes#"] = $obj->execCmd();
          $replace["#idEtatVerrouConsignes#"] = $obj->getId();
           
          $obj = $this->getCmd(null, 'on_verrou_consignes');
          $replace["#idOnVerrouConsignes#"] = $obj->getId();

          $obj = $this->getCmd(null, 'off_verrou_consignes');
          $replace["#idOffVerrouConsignes#"] = $obj->getId();

          $obj = $this->getCmd(null, 'temperature');
          $replace["#temperature#"] = $obj->execCmd();
          $replace["#idTemperature#"] = $obj->getId();

          $obj = $this->getCmd(null, 'consigne');
          $replace["#consigne#"] = $obj->execCmd();
          $replace["#idConsigne#"] = $obj->getId();
          $replace["#minConsigne#"] = $obj->getConfiguration('minValue');
          $replace["#maxConsigne#"] = $obj->getConfiguration('maxValue');
          $replace["#stepConsigne#"] = 0.5;

          $obj = $this->getCmd(null, 'humidite');
          $replace["#humidite#"] = $obj->execCmd();
          $replace["#idHumidite#"] = $obj->getId();

          $obj = $this->getCmd(null, 'consigne_hum');
          $replace["#consigne_hum#"] = $obj->execCmd();
          $replace["#idConsigne_hum#"] = $obj->getId();
          $replace["#minConsigne_hum#"] = $obj->getConfiguration('minValue');
          $replace["#maxConsigne_hum#"] = $obj->getConfiguration('maxValue');
          $replace["#stepConsigne_hum#"] = 1;

          $obj = $this->getCmd(null, 'thermostat');
          $replace["#idThermostat#"] = $obj->getId();

          $obj = $this->getCmd(null, 'thermostat_hum');
          $replace["#idThermostat_hum#"] = $obj->getId();

          $obj = $this->getCmd(null, 'consoJour');
          $replace["#consoJour#"] = round($obj->execCmd(), 2);
          $replace["#idConsoJour#"] = $obj->getId();
          $obj = $this->getCmd(null, 'consoSemaine');
          $replace["#consoSemaine#"] = round($obj->execCmd(), 2);
          $replace["#idConsoSemaine#"] = $obj->getId();
          $obj = $this->getCmd(null, 'consoMois');
          $replace["#consoMois#"] = round($obj->execCmd(), 2);
          $replace["#idConsoMois#"] = $obj->getId();
          $obj = $this->getCmd(null, 'consoAnnee');
          $replace["#consoAnnee#"] = round($obj->execCmd(), 2);
          $replace["#idConsoAnnee#"] = $obj->getId();
    
          $obj = $this->getCmd(null, 'histoJour');
          $replace["#idHistoJour#"] = $obj->getId();
          $obj = $this->getCmd(null, 'histoSemaine');
          $replace["#idHistoSemaine#"] = $obj->getId();
          $obj = $this->getCmd(null, 'histoMois');
          $replace["#idHistoMois#"] = $obj->getId();
          $obj = $this->getCmd(null, 'histoAnnee');
          $replace["#idHistoAnnee#"] = $obj->getId();
    
          return $this->postToHtml($_version, template_replace($replace, getTemplate('core', $version, 'terrarium_view', 'terrarium')));
      }
  }

  // Class Cmd
  //
  class terrariumCmd extends cmd
  {
      // Exécution d'une commande
      //
      public function execute($_options = array())
      {
          $eqLogic = $this->getEqLogic();

          $etatVerrouEclairage = $eqLogic->getCmd(null, 'etat_verrou_eclairage');
          if ($this->getLogicalId() == 'on_verrou_eclairage') {
              $etatVerrouEclairage->event(1);
          } elseif ($this->getLogicalId() == 'off_verrou_eclairage') {
              $etatVerrouEclairage->event(0);
          }

          $etatVerrouConsignes = $eqLogic->getCmd(null, 'etat_verrou_consignes');
          if ($this->getLogicalId() == 'on_verrou_consignes') {
              $etatVerrouConsignes->event(1);
          } elseif ($this->getLogicalId() == 'off_verrou_consignes') {
              $etatVerrouConsignes->event(0);
          }

          if ($this->getLogicalId() == 'statut_jour') {
              $eqLogic->jour();
          } elseif ($this->getLogicalId() == 'statut_nuit') {
              $eqLogic->nuit();
          }

          if ($this->getLogicalId() == 'thermostat') {
              if (!isset($_options['slider']) || $_options['slider'] == '' || !is_numeric(intval($_options['slider']))) {
                  return;
              }
              $eqLogic->getCmd(null, 'consigne')->event($_options['slider']);
          }
          if ($this->getLogicalId() == 'thermostat_hum') {
              if (!isset($_options['slider']) || $_options['slider'] == '' || !is_numeric(intval($_options['slider']))) {
                  return;
              }
              $eqLogic->getCmd(null, 'consigne_hum')->event($_options['slider']);
          }
      }
  }
