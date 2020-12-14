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
      // Fonction exécutée toutes les minutes
      //
      public static function cron()
      {

          // Pour chacun des équipements
          //
          foreach (terrarium::byType('terrarium', true) as $terrarium) {

              // Mémo de la température initiale du terrarium
              //
              $temperature = $terrarium->getCmd(null, 'temperature')->execCmd();
              if (!is_numeric($temperature)) {
                  $terrarium->getCmd(null, 'temperature')->event(jeedom::evaluateExpression($terrarium->getConfiguration('temperature_terrarium')));
              }

              // Mémo de la consigne initiale du terrarium
              //
              $consigne = $terrarium->getCmd(null, 'consigne')->execCmd();
              if (!is_numeric($consigne)) {
                switch ($terrarium->getCmd(null, 'status')->execCmd()) {
                    case __('Jour', __FILE__):
                    $terrarium->actionsConsignesJour();
                    break;
                    case __('Nuit', __FILE__):
                    $terrarium->actionsConsignesNuit();
                    break;
                }
              }

              // Première utilisation, jour par défaut
              //
              $statut = $terrarium->getCmd(null, 'status')->execCmd();
              if (($statut != __('Jour', __FILE__)) && ($statut !=  __('Nuit', __FILE__))) {                
                  $terrarium->getCmd(null, 'etat_verrou_eclairage')->event(0);
                  $terrarium->getCmd(null, 'etat_verrou_consignes')->event(0);
                  $terrarium->jour();
                  $terrarium->temperature();
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
                      }
                  } catch (Exception $e) {
                      log::add('terrarium', 'error', $terrarium->getHumanName() . ' : ' . $e->getMessage());
                  }
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

          if ($temperature <= $consigne_min) {
              $this->chauffe();
          } elseif ($temperature >= $consigne_max) {
              $this->pasDeChauffe();
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

      // Actions chauffage nuit
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

      // Gestion de la température
      //
      public function consommation()
      {
          // On récupère la valeur précédente
          //
          $oldConsommation = $this->getConfiguration('oldConsommation', -1);
          $consommation = jeedom::evaluateExpression($this->getConfiguration('cmdConsommation'));
          if (!is_numeric($consommation)) {
              return;
          }
          $this->setConfiguration('oldConsommation', $consommation)->save();
 
          // Si première fois, pas de cumul
          //
          if ($oldConsommation == -1) {
              return;
          }
         
          // Mémo de la consommation
          //
          $this->getCmd(null, 'consommation')->event(jeedom::evaluateExpression($this->getConfiguration('cmdConsommation')));

          // Différence entre les deux valeurs
          //
          $diff = $consommation - $oldConsommation;

          // Une demi heure avant minuit pour avoir la bonne date en historisation
          //
          $plusTard = time()+30*60;

          // Historisation du jour si nécessaire
          //
          $jour = date("d", $plusTard);
          $oldJour = $this->getConfiguration('oldJour', 0);
          log::add('consos', 'debug', 'Jour : ' . $oldJour . ' ' .$jour);
          if ($jour != $oldJour) {
              $cmd = $this->getCmd(null, 'consoJour');
              $this->checkAndUpdateCmd('histoJour', $cmd->execCmd());
              $this->checkAndUpdateCmd('consoJour', 0);
              $this->setConfiguration('oldJour', $jour)->save();
          }
       
          // Historisation de la semaine si nécessaire
          //
          $jourSemaine = date("N", $plusTard);
          $oldJourSemaine = $this->getConfiguration('oldJourSemaine', 0);
          log::add('consos', 'debug', 'Semaine : ' . $oldJourSemaine . ' ' .$jourSemaine);
          if ($jourSemaine != $oldJourSemaine) {
              if ($jourSemaine == 1) {
                  $cmd = $this->getCmd(null, 'consoSemaine');
                  $this->checkAndUpdateCmd('histoSemaine', $cmd->execCmd());
                  $this->checkAndUpdateCmd('consoSemaine', 0);
              }
              $this->setConfiguration('oldJourSemaine', $jourSemaine)->save();
          }
       
          // Historisation du mois si nécessaire
          //
          $mois = date("m", $plusTard);
          $oldMois = $this->getConfiguration('oldMois', 0);
          log::add('consos', 'debug', 'Mois : ' . $oldMois . ' ' .$mois);
          if ($mois != $oldMois) {
              $cmd = $this->getCmd(null, 'consoMois');
              $this->checkAndUpdateCmd('histoMois', $cmd->execCmd());
              $this->checkAndUpdateCmd('consoMois', 0);
              $this->setConfiguration('oldMois', $mois)->save();
          }
       
          // Historisation de l'année si nécessaire
          //
          $annee = date("Y", $plusTard);
          $oldAnnee = $this->getConfiguration('oldAnnee', 0);
          log::add('consos', 'debug', 'Jour : ' . $oldAnnee . ' ' .$annee);
          if ($annee != $oldAnnee) {
              $cmd = $this->getCmd(null, 'consoAnnee');
              $this->checkAndUpdateCmd('histoAnnee', $cmd->execCmd());
              $this->checkAndUpdateCmd('consoAnnee', 0);
              $this->setConfiguration('oldAnnee', $annee)->save();
          }
       
          // Et j'ajoute la consommation instantanée
          //
          $cmd = $this->getCmd(null, 'consoJour');
          $this->checkAndUpdateCmd('consoJour', $cmd->execCmd()+$diff);

          $cmd = $this->getCmd(null, 'consoSemaine');
          $this->checkAndUpdateCmd('consoSemaine', $cmd->execCmd()+$diff);

          $cmd = $this->getCmd(null, 'consoMois');
          $this->checkAndUpdateCmd('consoMois', $cmd->execCmd()+$diff);

          $cmd = $this->getCmd(null, 'consoAnnee');
          $this->checkAndUpdateCmd('consoAnnee', $cmd->execCmd()+$diff);
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
          if ($this->getConfiguration('hysteresis_min') === '') {
              $this->setConfiguration('hysteresis_min', 0.5);
          }
          if ($this->getConfiguration('hysteresis_max') === '') {
              $this->setConfiguration('hysteresis_max', 1);
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
          $etatVerrouEclairage->setOrder(2);
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
          $lock->setOrder(3);
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
          $unlock->setOrder(4);
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
          $etatVerrouConsignes->setOrder(5);
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
          $lock->setOrder(6);
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
          $unlock->setOrder(7);
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
          $mode->setOrder(8);
          $mode->save();

          $consigne = $this->getCmd(null, 'consigne');
          if (!is_object($consigne)) {
              $consigne = new terrariumCmd();
              $consigne->setIsVisible(0);
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
          $consigne->setOrder(9);
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
          $thermostat->setOrder(10);
          $thermostat->save();

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
          $temperature->setOrder(11);
          $temperature->save();

          $obj = $this->getCmd(null, 'consoJour');
          if (!is_object($obj)) {
              $obj = new consosCmd();
              $obj->setName(__('ConsoJour', __FILE__));
          }
          $obj->setEqLogic_id($this->getId());
          $obj->setLogicalId('consoJour');
          $obj->setType('info');
          $obj->setSubType('numeric');
          $obj->setOrder(12);
          $obj->save();

          $obj = $this->getCmd(null, 'histoJour');
          if (!is_object($obj)) {
              $obj = new consosCmd();
              $obj->setName(__('HistoJour', __FILE__));
              $obj->setIsVisible(0);
              $obj->setIsHistorized(1);
          }
          $obj->setEqLogic_id($this->getId());
          $obj->setLogicalId('histoJour');
          $obj->setType('info');
          $obj->setSubType('numeric');
          $obj->setOrder(13);
          $obj->save();

          $obj = $this->getCmd(null, 'consoSemaine');
          if (!is_object($obj)) {
              $obj = new consosCmd();
              $obj->setName(__('ConsoSemaine', __FILE__));
          }
          $obj->setEqLogic_id($this->getId());
          $obj->setLogicalId('consoSemaine');
          $obj->setType('info');
          $obj->setSubType('numeric');
          $obj->setOrder(14);
          $obj->save();

          $obj = $this->getCmd(null, 'histoSemaine');
          if (!is_object($obj)) {
              $obj = new consosCmd();
              $obj->setName(__('HistoSemaine', __FILE__));
              $obj->setIsVisible(0);
              $obj->setIsHistorized(1);
          }
          $obj->setEqLogic_id($this->getId());
          $obj->setLogicalId('histoSemaine');
          $obj->setType('info');
          $obj->setSubType('numeric');
          $obj->setOrder(15);
          $obj->save();

          $obj = $this->getCmd(null, 'consoMois');
          if (!is_object($obj)) {
              $obj = new consosCmd();
              $obj->setName(__('ConsoMois', __FILE__));
          }
          $obj->setEqLogic_id($this->getId());
          $obj->setLogicalId('consoMois');
          $obj->setType('info');
          $obj->setSubType('numeric');
          $obj->setOrder(16);
          $obj->save();

          $obj = $this->getCmd(null, 'histoMois');
          if (!is_object($obj)) {
              $obj = new consosCmd();
              $obj->setName(__('HistoMois', __FILE__));
              $obj->setIsVisible(0);
              $obj->setIsHistorized(1);
          }
          $obj->setEqLogic_id($this->getId());
          $obj->setLogicalId('histoMois');
          $obj->setType('info');
          $obj->setSubType('numeric');
          $obj->setOrder(17);
          $obj->save();

          $obj = $this->getCmd(null, 'consoAnnee');
          if (!is_object($obj)) {
              $obj = new consosCmd();
              $obj->setName(__('ConsoAnnee', __FILE__));
          }
          $obj->setEqLogic_id($this->getId());
          $obj->setLogicalId('consoAnnee');
          $obj->setType('info');
          $obj->setSubType('numeric');
          $obj->setOrder(18);
          $obj->save();

          $obj = $this->getCmd(null, 'histoAnnee');
          if (!is_object($obj)) {
              $obj = new consosCmd();
              $obj->setName(__('HistoAnnee', __FILE__));
              $obj->setIsVisible(0);
              $obj->setIsHistorized(1);
          }
          $obj->setEqLogic_id($this->getId());
          $obj->setLogicalId('histoAnnee');
          $obj->setType('info');
          $obj->setSubType('numeric');
          $obj->setOrder(19);
          $obj->save();

          // On écoute les événements qui interviennent dans la gestion du chauffage
          //
          //   La température du terrarium
          //   La consigne de température
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
      }

      // Fonction exécutée automatiquement avant la suppression de l'équipement
      //
      public function preRemove()
      {
          // On supprime l'écoute
          //
          $listener = listener::byClassAndFunction('terrarium', 'onTemperature', array('terrarium_id' => intval($this->getId())));
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
          $replace = $this->preToHtml($_version);
          if (!is_array($replace)) {
              return $replace;
          }
          $version = jeedom::versionAlias($_version);
 
          $obj = $this->getCmd(null, 'status');
          $replace["#statut#"] = $obj->execCmd();
          $replace["#idStatut#"] = $obj->getId();

          $obj = $this->getCmd(null, 'mode');
          $replace["#mode#"] = $obj->execCmd();
          $replace["#idMode#"] = $obj->getId();

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

          $obj = $this->getCmd(null, 'thermostat');
          $replace["#idThermostat#"] = $obj->getId();

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
    
          return template_replace($replace, getTemplate('core', $version, 'terrarium_view', 'terrarium'));
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

          if ($this->getLogicalId() == 'thermostat') {
              if (!isset($_options['slider']) || $_options['slider'] == '' || !is_numeric(intval($_options['slider']))) {
                  return;
              }
              $eqLogic->getCmd(null, 'consigne')->event($_options['slider']);
          }
      }
  }
