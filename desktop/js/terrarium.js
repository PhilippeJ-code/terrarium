
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

$("#div_ecl_jour").sortable({ axis: "y", cursor: "move", items: ".ecl_jour", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true });
$("#div_ecl_nuit").sortable({ axis: "y", cursor: "move", items: ".ecl_nuit", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true });
$("#div_csg_jour").sortable({ axis: "y", cursor: "move", items: ".csg_jour", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true });
$("#div_csg_nuit").sortable({ axis: "y", cursor: "move", items: ".csg_nuit", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true });
$("#div_chf_oui").sortable({ axis: "y", cursor: "move", items: ".chf_oui", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true });
$("#div_chf_non").sortable({ axis: "y", cursor: "move", items: ".chf_non", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true });

$("#table_cmd").sortable({ axis: "y", cursor: "move", items: ".cmd", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true });
/*
 * Fonction permettant l'affichage des commandes dans l'équipement
 */

$('.addAction').off('click').on('click', function () {
    addAction({}, $(this).attr('data-type'));
});

$("body").off('click', '.bt_removeAction').on('click', '.bt_removeAction', function () {
    var type = $(this).attr('data-type');
    $(this).closest('.' + type).remove();
});

$("body").off('click', '.listCmdAction').on('click', '.listCmdAction', function () {
    var type = $(this).attr('data-type');
    var el = $(this).closest('.' + type).find('.expressionAttr[data-l1key=cmd]');
    jeedom.cmd.getSelectModal({ cmd: { type: 'action' } }, function (result) {
        el.value(result.human);
        jeedom.cmd.displayActionOption(el.value(), '', function (html) {
            el.closest('.' + type).find('.actionOptions').html(html);
        });

    });
});

$(".eqLogic").off('click', '.listCmdInfo').on('click', '.listCmdInfo', function () {
    var el = $(this).closest('.form-group').find('.eqLogicAttr');
    jeedom.cmd.getSelectModal({ cmd: { type: 'info' } }, function (result) {
        if (el.attr('data-concat') == 1) {
            el.atCaret('insert', result.human);
        } else {
            el.value(result.human);
        }
    });
});

function addCmdToTable(_cmd) {
    if (!isset(_cmd)) {
        var _cmd = { configuration: {} };
    }
    if (!isset(_cmd.configuration)) {
        _cmd.configuration = {};
    }
    var tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">';
    tr += '<td>';
    tr += '<span class="cmdAttr" data-l1key="id" style="display:none;"></span>';
    tr += '<input class="cmdAttr form-control input-sm" data-l1key="name" style="width : 140px;" placeholder="{{Nom}}">';
    tr += '</td>';
    tr += '<td>';
    tr += '<span class="type" type="' + init(_cmd.type) + '">' + jeedom.cmd.availableType() + '</span>';
    tr += '<span class="subType" subType="' + init(_cmd.subType) + '"></span>';
    tr += '</td>';
    tr += '<td>';
    tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="isVisible" checked/>{{Afficher}}</label></span> ';
    tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="isHistorized" checked/>{{Historiser}}</label></span> ';
    tr += '</td>';
    tr += '<td>';
    if (is_numeric(_cmd.id)) {
        tr += '<a class="btn btn-default btn-xs cmdAction" data-action="configure"><i class="fas fa-cogs"></i></a> ';
        tr += '<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fas fa-rss"></i> {{Tester}}</a>';
    }
    tr += '<i class="fas fa-minus-circle pull-right cmdAction cursor" data-action="remove"></i>';
    tr += '</td>';
    tr += '</tr>';
    $('#table_cmd tbody').append(tr);
    $('#table_cmd tbody tr:last').setValues(_cmd, '.cmdAttr');
    if (isset(_cmd.type)) {
        $('#table_cmd tbody tr:last .cmdAttr[data-l1key=type]').value(init(_cmd.type));
    }
    jeedom.cmd.changeType($('#table_cmd tbody tr:last'), init(_cmd.subType));
}

function addAction(_action, _type) {
    var div = '<div class="' + _type + '">';
    div += '<div class="form-group ">';
    div += '<label class="col-sm-1 control-label">Action</label>';
    div += '<div class="col-sm-4">';
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
    $('#div_' + _type).append(div);
    $('#div_' + _type + ' .' + _type + '').last().setValues(_action, '.expressionAttr');
}

function saveEqLogic(_eqLogic) {
    if (!isset(_eqLogic.configuration)) {
        _eqLogic.configuration = {};
    }
    _eqLogic.configuration.ecl_jour_conf = $('#div_ecl_jour .ecl_jour').getValues('.expressionAttr');
    _eqLogic.configuration.ecl_nuit_conf = $('#div_ecl_nuit .ecl_nuit').getValues('.expressionAttr');
    _eqLogic.configuration.csg_jour_conf = $('#div_csg_jour .csg_jour').getValues('.expressionAttr');
    _eqLogic.configuration.csg_nuit_conf = $('#div_csg_nuit .csg_nuit').getValues('.expressionAttr');
    _eqLogic.configuration.chf_oui_conf = $('#div_chf_oui .chf_oui').getValues('.expressionAttr');
    _eqLogic.configuration.chf_non_conf = $('#div_chf_non .chf_non').getValues('.expressionAttr');
    return _eqLogic;
}

function printEqLogic(_eqLogic) {
    $('#div_ecl_jour').empty();
    $('#div_ecl_nuit').empty();
    $('#div_csg_jour').empty();
    $('#div_csg_nuit').empty();
    $('#div_chf_oui').empty();
    $('#div_chf_non').empty();
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
    }
}
