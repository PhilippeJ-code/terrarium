
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

// Event Add Action
//
function eventAddAction()
{
    addAction({}, this.getAttribute('data-type'));
}

els = document.querySelectorAll(".addAction");
els.forEach(function(el) {
    el.removeEventListener('click', eventAddAction);
    el.addEventListener('click', eventAddAction);
});

// Event Select Action
//
function eventSelectAction()
{
    var type = this.getAttribute('data-type');
    var el = this.closest('.' + type).querySelector('.expressionAttr[data-l1key=cmd]');

    jeedom.cmd.getSelectModal({ cmd: { type: 'action' } }, function (result) {
        el.value = result.human;
        jeedom.cmd.displayActionOption(el.value, '', function (html) {
            el.closest('.' + type).querySelector('.actionOptions').innerHTML= html;
            let scripts = el.closest('.' + type).querySelector('.actionOptions').querySelectorAll('script');
            scripts.forEach(script => {
              let newScript = document.createElement('script');
              newScript.text = script.text;
              document.body.appendChild(newScript);
              script.remove();
            });
        });   
    });
}

// Event Remove Action
//
function eventRemoveAction()
{
    var type = this.getAttribute('data-type');
    this.removeEventListener('click', eventRemoveAction);
    this.removeEventListener('click', eventSelectAction);
    this.closest('.' + type).remove();
}

// Event Add Schedule
//
function eventAddSchedule()
{
    addSchedule({}, this.getAttribute('data-type'));
}

els = document.querySelectorAll(".addSchedule");
els.forEach(function(el) {
    el.removeEventListener('click', eventAddSchedule);
    el.addEventListener('click', eventAddSchedule);
});

// Event Remove Schedule
//
function eventRemoveSchedule()
{
    var type = this.getAttribute('data-type');
    this.removeEventListener('click', eventRemoveSchedule);
    this.closest('.' + type).remove();
}

// Event Select Info
//
function eventSelectInfo () {
    var el = this.closest('.form-group').querySelector('.eqLogicAttr');
    jeedom.cmd.getSelectModal({ cmd: { type: 'info' } }, function (result) {
        el.value = result.human;
    });
}

els = document.querySelectorAll(".listCmdInfo");
els.forEach(function(el) {
    el.removeEventListener('click', eventSelectInfo);
    el.addEventListener('click', eventSelectInfo);
});

// Add command
//
function addCmdToTable(_cmd) {

    if (document.getElementById('table_cmd') == null) return
    if (document.querySelector('#table_cmd thead') == null) {
      table = '<thead>'
      table += '<tr>'
      table += '<th>{{Id}}</th>'
      table += '<th>{{Nom}}</th>'
      table += '<th>{{Type}}</th>'
      table += '<th>{{Paramètres}}</th>'
      table += '<th>{{Etat}}</th>'
      table += '<th>{{Action}}</th>'
      table += '</tr>'
      table += '</thead>'
      table += '<tbody>'
      table += '</tbody>'
      document.getElementById('table_cmd').insertAdjacentHTML('beforeend', table)
    }
    if (!isset(_cmd)) {
      var _cmd = { configuration: {} }
    }
    if (!isset(_cmd.configuration)) {
      _cmd.configuration = {}
    }
    var tr = ''
    tr += '<td style="min-width:50px;width:70px;">'
    tr += '<span class="cmdAttr" data-l1key="id"></span>'
    tr += '</td>'
    tr += '<td>'
    tr += '<div class="row">'
    tr += '<div class="col-sm-6">'
    tr += '<a class="cmdAction btn btn-default btn-sm" data-l1key="chooseIcon"><i class="fa fa-flag"></i> Icône</a>'
    tr += '<span class="cmdAttr" data-l1key="display" data-l2key="icon" style="margin-left : 10px;"></span>'
    tr += '</div>'
    tr += '<div class="col-sm-6">'
    tr += '<input class="cmdAttr form-control input-sm" data-l1key="name">'
    tr += '</div>'
    tr += '</div>'
    tr += '<select class="cmdAttr form-control input-sm" data-l1key="value" style="display : none;margin-top : 5px;" title="{{La valeur de la commande vaut par défaut la commande}}">'
    tr += '<option value="">Aucune</option>'
    tr += '</select>'
    tr += '</td>'
    tr += '<td>'
    tr += '<span class="type" type="' + init(_cmd.type) + '">' + jeedom.cmd.availableType() + '</span>'
    tr += '<span class="subType" subType="' + init(_cmd.subType) + '"></span>'
    tr += '</td>'
    tr += '<td>'
    tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="minValue" placeholder="{{Min}}" title="{{Min}}" style="width:30%;display:inline-block;">'
    tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="maxValue" placeholder="{{Max}}" title="{{Max}}" style="width:30%;display:inline-block;">'
    tr += '<input class="cmdAttr form-control input-sm" data-l1key="unite" placeholder="{{Unité}}" title="{{Unité}}" style="width:30%;display:inline-block;margin-left:2px;">'
    tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="listValue" placeholder="{{Liste de valeur|texte séparé par ;}}" title="{{Liste}}">'
    tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="isVisible" checked/>{{Afficher}}</label></span> '
    tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="isHistorized" checked/>{{Historiser}}</label></span> '
    tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-l1key="display" data-l2key="invertBinary"/>{{Inverser}}</label></span> '
    tr += '</td>'
    tr += '<td>'
    tr += '<span class="cmdAttr" data-l1key="htmlstate"></span>'
    tr += '</td>'
    tr += '<td>'
    if (is_numeric(_cmd.id)) {
      tr += '<a class="btn btn-default btn-xs cmdAction" data-action="configure"><i class="fas fa-cogs"></i></a> '
      tr += '<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fa fa-rss"></i> {{Tester}}</a>'
    }
    tr += '<i class="fas fa-minus-circle pull-right cmdAction cursor" data-action="remove"></i>'
    tr += '</td>'

    let newRow = document.createElement('tr')
    newRow.innerHTML = tr
    newRow.addClass('cmd')
    newRow.setAttribute('data-cmd_id', init(_cmd.id))
    document.getElementById('table_cmd').querySelector('tbody').appendChild(newRow)

    jeedom.eqLogic.buildSelectCmd({
      id: document.querySelector('.eqLogicAttr[data-l1key="id"]').jeeValue(),
      filter: { type: 'info' },
      error: function(error) {
        jeedomUtils.showAlert({ message: error.message, level: 'danger' })
      },
        success: function(result) {
            newRow.querySelector('.cmdAttr[data-l1key="value"]').insertAdjacentHTML('beforeend', result)
            newRow.setJeeValues(_cmd, '.cmdAttr')
            jeedom.cmd.changeType(newRow, init(_cmd.subType))
        }
    })
}

// Add Action
//
function addAction(_action, _type) {

    if (!isset(_action)) {
      _action = {}
    }
    if (!isset(_action.options)) {
      _action.options = {}
    }
  
    var div = '<div class="' + _type + '">'
    div += '<div class="form-group">';
    div += '<label class="col-sm-1 control-label">Action</label>';  
    div += '<div class="col-sm-3">';
    div += '<div class="input-group">';
    div += '<span class="input-group-btn">';
    div += '<a class="btn btn-default bt_removeAction roundedLeft" data-type="' + _type + '"><i class="fas fa-minus-circle"></i></a>';
    div += '</span>';
    div += '<input class="expressionAttr form-control cmdAction" data-l1key="cmd" data-type="' + _type + '" />';
    div += '<span class="input-group-btn">';
    div += '<a class="btn btn-default listCmdAction roundedRight" data-type="' + _type + '"><i class="fas fa-list-alt"></i></a>';
    div += '</span>';
    div += '</div>';
    div += '</div>';
    div += '<div class="col-sm-4 actionOptions">';
    div += jeedom.cmd.displayActionOption(init(_action.cmd, ''), _action.options);
    div += '</div>';
    div += '</div>';
    div += '</div>';
    
    document.getElementById('div_' + _type).insertAdjacentHTML('beforeend', div);
    let scripts = document.getElementById('div_' + _type).querySelectorAll('script');
    scripts.forEach(script => {
      let newScript = document.createElement('script');
      newScript.text = script.text;
      document.body.appendChild(newScript);
      script.remove();
    });
  
    var newRow = document.querySelectorAll('#div_' + _type + ' .' + _type + '').last();
  
    newRow.setJeeValues(_action, '.expressionAttr');
  
    let el = newRow.querySelector(".bt_removeAction");
    el.addEventListener('click', eventRemoveAction);
  
    el = newRow.querySelector(".listCmdAction");
    el.addEventListener('click', eventSelectAction); 
    
  }
  
  // Add Schedule
//
function addSchedule(_schedule, _type) {

    var div = '<div class="form-group ">';
    div += '<label class="col-sm-1 control-label">Programmation</label>';
    div += '<div class="col-sm-3">';
    div += '<div class="input-group">';
    div += '<span class="input-group-btn">';
    div += '<a class="btn btn-default bt_removeSchedule roundedLeft" data-type="' + _type + '"><i class="fas fa-minus-circle"></i></a>';
    div += '</span>';
    div += '<input class="scheduleAttr form-control" data-l1key="cmd" data-type="' + _type + '" />';
    div += '<span class="input-group-btn">';
    div += '<a class="btn btn-default cursor jeeHelper roundedRight" data-helper="cron"><i class="fas fa-question-circle"></i></a>'
    div += '</span>';
    div += '</div>';
    div += '</div>';
    div += '<div class="col-sm-4 actionOptions">';
    div += jeedom.cmd.displayActionOption(init(_schedule.cmd, ''), _schedule.options);
    div += '</div>';
    div += '</div>';

    let newRow = document.createElement('div');
    newRow.innerHTML = div;
    newRow.addClass(_type);
    document.getElementById('div_' + _type).appendChild(newRow);
    newRow.setJeeValues(_schedule, '.scheduleAttr');

    let el = newRow.querySelector(".bt_removeSchedule");
    el.addEventListener('click', eventRemoveSchedule);

  }
  
  function saveEqLogic(_eqLogic) {
    if (!isset(_eqLogic.configuration)) {
        _eqLogic.configuration = {};
    }

    _eqLogic.configuration.ecl_jour_conf = document.querySelectorAll('#div_ecl_jour .ecl_jour').getJeeValues('.expressionAttr');
    _eqLogic.configuration.ecl_nuit_conf = document.querySelectorAll('#div_ecl_nuit .ecl_nuit').getJeeValues('.expressionAttr');
    _eqLogic.configuration.csg_jour_conf = document.querySelectorAll('#div_csg_jour .csg_jour').getJeeValues('.expressionAttr');
    _eqLogic.configuration.csg_nuit_conf = document.querySelectorAll('#div_csg_nuit .csg_nuit').getJeeValues('.expressionAttr');
    _eqLogic.configuration.chf_oui_conf = document.querySelectorAll('#div_chf_oui .chf_oui').getJeeValues('.expressionAttr');
    _eqLogic.configuration.chf_non_conf = document.querySelectorAll('#div_chf_non .chf_non').getJeeValues('.expressionAttr');
    _eqLogic.configuration.hum_oui_conf = document.querySelectorAll('#div_hum_oui .hum_oui').getJeeValues('.expressionAttr');
    _eqLogic.configuration.hum_non_conf = document.querySelectorAll('#div_hum_non .hum_non').getJeeValues('.expressionAttr');
    _eqLogic.configuration.brume_on_conf = document.querySelectorAll('#div_brume_on .brume_on').getJeeValues('.expressionAttr');
    _eqLogic.configuration.brume_off_conf = document.querySelectorAll('#div_brume_off .brume_off').getJeeValues('.expressionAttr');
    _eqLogic.configuration.ven_oui_conf = document.querySelectorAll('#div_ven_oui .ven_oui').getJeeValues('.expressionAttr');
    _eqLogic.configuration.ven_non_conf = document.querySelectorAll('#div_ven_non .ven_non').getJeeValues('.expressionAttr');

    _eqLogic.configuration.schedule_brume_conf = document.querySelectorAll('#div_schedule_brume .schedule_brume').getJeeValues('.scheduleAttr');

    return _eqLogic;
}

function printEqLogic(_eqLogic) {

    document.getElementById('div_ecl_jour').innerHTML = '';
    document.getElementById('div_ecl_nuit').innerHTML = '';
    document.getElementById('div_csg_jour').innerHTML = '';
    document.getElementById('div_csg_nuit').innerHTML = '';
    document.getElementById('div_chf_oui').innerHTML = '';
    document.getElementById('div_chf_non').innerHTML = '';
    document.getElementById('div_hum_oui').innerHTML = '';
    document.getElementById('div_hum_non').innerHTML = '';
    document.getElementById('div_brume_on').innerHTML = '';
    document.getElementById('div_brume_off').innerHTML = '';
    document.getElementById('div_schedule_brume').innerHTML = '';

    if (isset(_eqLogic.configuration)) {
        if (isset(_eqLogic.configuration.ecl_jour_conf)) {
            for (var i in _eqLogic.configuration.ecl_jour_conf) {
                addAction(_eqLogic.configuration.ecl_jour_conf[i], 'ecl_jour');
            }
        }
        if (isset(_eqLogic.configuration.ecl_nuit_conf)) {
            for (var i in _eqLogic.configuration.ecl_nuit_conf) {
                addAction(_eqLogic.configuration.ecl_nuit_conf[i], 'ecl_nuit');
            }
        }
        if (isset(_eqLogic.configuration.csg_jour_conf)) {
            for (var i in _eqLogic.configuration.csg_jour_conf) {
                addAction(_eqLogic.configuration.csg_jour_conf[i], 'csg_jour');
            }
        }
        if (isset(_eqLogic.configuration.csg_nuit_conf)) {
            for (var i in _eqLogic.configuration.csg_nuit_conf) {
                addAction(_eqLogic.configuration.csg_nuit_conf[i], 'csg_nuit');
            }
        }
        if (isset(_eqLogic.configuration.chf_oui_conf)) {
            for (var i in _eqLogic.configuration.chf_oui_conf) {
                addAction(_eqLogic.configuration.chf_oui_conf[i], 'chf_oui');
            }
        }
        if (isset(_eqLogic.configuration.chf_non_conf)) {
            for (var i in _eqLogic.configuration.chf_non_conf) {
                addAction(_eqLogic.configuration.chf_non_conf[i], 'chf_non');
            }
        }
        if (isset(_eqLogic.configuration.hum_oui_conf)) {
            for (var i in _eqLogic.configuration.hum_oui_conf) {
                addAction(_eqLogic.configuration.hum_oui_conf[i], 'hum_oui');
            }
        }
        if (isset(_eqLogic.configuration.hum_non_conf)) {
            for (var i in _eqLogic.configuration.hum_non_conf) {
                addAction(_eqLogic.configuration.hum_non_conf[i], 'hum_non');
            }
        }
        if (isset(_eqLogic.configuration.brume_on_conf)) {
            for (var i in _eqLogic.configuration.brume_on_conf) {
                addAction(_eqLogic.configuration.brume_on_conf[i], 'brume_on');
            }
        }
        if (isset(_eqLogic.configuration.brume_off_conf)) {
            for (var i in _eqLogic.configuration.brume_off_conf) {
                addAction(_eqLogic.configuration.brume_off_conf[i], 'brume_off');
            }
        }
        if (isset(_eqLogic.configuration.ven_oui_conf)) {
            for (var i in _eqLogic.configuration.ven_oui_conf) {
                addAction(_eqLogic.configuration.ven_oui_conf[i], 'ven_oui');
            }
        }
        if (isset(_eqLogic.configuration.ven_non_conf)) {
            for (var i in _eqLogic.configuration.ven_non_conf) {
                addAction(_eqLogic.configuration.ven_non_conf[i], 'ven_non');
            }
        }
        if (isset(_eqLogic.configuration.schedule_brume_conf)) {
            for (var i in _eqLogic.configuration.schedule_brume_conf) {
                addSchedule(_eqLogic.configuration.schedule_brume_conf[i], 'schedule_brume');
            }
        }
    }
}
