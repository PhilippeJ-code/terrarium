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

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';

// Fonction exécutée automatiquement après l'installation du plugin
//
function terrarium_install() 
{
	$cron = cron::byClassAndFunction('terrarium', 'daemon');
	if (!is_object($cron)) {
		$cron = new cron();
		$cron->setClass('terrarium');
		$cron->setFunction('daemon');
		$cron->setEnable(1);
		$cron->setDeamon(1);
		$cron->setTimeout(1440);
		$cron->setSchedule('* * * * *');
		$cron->save();
	}
	$cron->start();

    if (version_compare(jeedom::version(), '4.4', '<')) {
        event::add('jeedom::alert', array(
            'level' => 'danger',
            'title' => __('Plugin Terrarium Version Jeedom', __FILE__),
            'message' => __('Le plugin Terrarium ne supporte pas les versions de Jeedom < v4.4', __FILE__),
        ));
    }
}

// Fonction exécutée automatiquement après la mise à jour du plugin
//
function terrarium_update() 
{
	$cron = cron::byClassAndFunction('terrarium', 'daemon');
	if (!is_object($cron)) {
		$cron = new cron();
		$cron->setClass('terrarium');
		$cron->setFunction('daemon');
		$cron->setEnable(1);
		$cron->setDeamon(1);
		$cron->setDeamonSleepTime(1);
		$cron->setSchedule('* * * * *');
		$cron->setTimeout(1440);
		$cron->save();
	}
	$cron->start();

    if (version_compare(jeedom::version(), '4.4', '<')) {
        event::add('jeedom::alert', array(
            'level' => 'danger',
            'title' => __('Plugin Terrarium Version Jeedom', __FILE__),
            'message' => __('Le plugin Terrarium ne supporte plus les versions de Jeedom < v4.4', __FILE__),
        ));
    }
}

// Fonction exécutée automatiquement après la suppression du plugin
//
function terrarium_remove() 
{
	$cron = cron::byClassAndFunction('terrarium', 'daemon');
	if (is_object($cron)) {
		$cron->remove();
	}
}

?>
