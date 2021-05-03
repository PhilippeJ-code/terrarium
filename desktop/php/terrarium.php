<?php
  if (!isConnect('admin')) {
      throw new Exception('{{401 - Accès non autorisé}}');
  }

  $plugin = plugin::byId('terrarium');
  sendVarToJS('eqType', $plugin->getId());
  $eqLogics = eqLogic::byType($plugin->getId());

?>

<div class="row row-overflow">
  <div class="col-xs-12 eqLogicThumbnailDisplay">
    <legend><i class="fas fa-cog"></i> {{Gestion}}</legend>
    <div class="eqLogicThumbnailContainer">
      <div class="cursor eqLogicAction logoPrimary" data-action="add">
        <i class="fas fa-plus-circle"></i>
        <br>
        <span>{{Ajouter}}</span>
      </div>
      <div class="cursor eqLogicAction logoSecondary" data-action="gotoPluginConf">
        <i class="fas fa-wrench"></i>
        <br>
        <span>{{Configuration}}</span>
      </div>
    </div>
    <legend><i class="fas fa-table"></i> {{Mes équipements}}</legend>
    <input class="form-control" placeholder="{{Rechercher}}" id="in_searchEqlogic" />
    <div class="eqLogicThumbnailContainer">
      <?php

        // Affiche la liste des équipements
        //
        foreach ($eqLogics as $eqLogic) {
            $opacity = ($eqLogic->getIsEnable()) ? '' : 'disableCard';
            echo '<div class="eqLogicDisplayCard cursor '.$opacity.'" data-eqLogic_id="' . $eqLogic->getId() . '">';
            echo '<img src="' . $plugin->getPathImgIcon() . '"/>';
            echo '<br>';
            echo '<span class="name">' . $eqLogic->getHumanName(true, true) . '</span>';
            echo '</div>';
        }
      ?>
    </div>
  </div>

  <div class="col-xs-12 eqLogic" style="display: none;">
    <div class="input-group pull-right" style="display:inline-flex">
      <span class="input-group-btn">
        <a class="btn btn-default btn-sm eqLogicAction roundedLeft" data-action="configure"><i class="fa fa-cogs"></i>
          {{Configuration avancée}}</a><a class="btn btn-default btn-sm eqLogicAction" data-action="copy"><i
            class="fas fa-copy"></i> {{Dupliquer}}</a><a class="btn btn-sm btn-success eqLogicAction"
          data-action="save"><i class="fas fa-check-circle"></i> {{Sauvegarder}}</a><a
          class="btn btn-danger btn-sm eqLogicAction roundedRight" data-action="remove"><i
            class="fas fa-minus-circle"></i> {{Supprimer}}</a>
      </span>
    </div>
    <ul class="nav nav-tabs" role="tablist">
      <li role="presentation"><a href="#" class="eqLogicAction" aria-controls="home" role="tab" data-toggle="tab"
          data-action="returnToThumbnailDisplay"><i class="fa fa-arrow-circle-left"></i></a></li>
      <li role="presentation" class="active"><a href="#eqlogictab" aria-controls="home" role="tab" data-toggle="tab"><i
            class="fas fa-tachometer-alt"></i> {{Equipement}}</a></li>
      <li role="presentation"><a href="#horairetab" role="tab" data-toggle="tab"><i class="fa fa-list-alt"></i>
          {{Horaire}}</a></li>
      <li role="presentation"><a href="#consignestab" role="tab" data-toggle="tab"><i class="fa fa-list-alt"></i>
          {{Consignes}}</a></li>
      <li role="presentation"><a href="#eclairagetab" role="tab" data-toggle="tab"><i class="fa fa-list-alt"></i>
          {{Eclairage}}</a></li>
      <li role="presentation"><a href="#chauffagetab" role="tab" data-toggle="tab"><i class="fa fa-list-alt"></i>
          {{Chauffage}}</a></li>
      <li role="presentation"><a href="#humiditetab" role="tab" data-toggle="tab"><i class="fa fa-list-alt"></i>
          {{Humidité}}</a></li>
      <li role="presentation"><a href="#brumisationtab" role="tab" data-toggle="tab"><i class="fa fa-list-alt"></i>
          {{Brumisation}}</a></li>
      <li role="presentation"><a href="#consommationtab" role="tab" data-toggle="tab"><i class="fa fa-list-alt"></i>
          {{Consommation}}</a></li>
      <li role="presentation"><a href="#commandtab" aria-controls="profile" role="tab" data-toggle="tab"><i
            class="fa fa-list-alt"></i> {{Commandes}}</a></li>
    </ul>
    <div class="tab-content" style="height:calc(100% - 50px);overflow:auto;overflow-x: hidden;">
      <div role="tabpanel" class="tab-pane active" id="eqlogictab">
        <br />
        <form class="form-horizontal">
          <fieldset>
            <legend><i class="fas fa-wrench"></i> {{Général}}</legend>
            <div class="form-group">
              <label class="col-sm-3 control-label">{{Nom de l'équipement}}</label>
              <div class="col-sm-3">
                <input type="text" class="eqLogicAttr form-control" data-l1key="id" style="display : none;" />
                <input type="text" class="eqLogicAttr form-control" data-l1key="name"
                  placeholder="{{Nom de l'équipement}}" />
              </div>
            </div>
            <div class="form-group">
              <label class="col-sm-3 control-label">{{Objet parent}}</label>
              <div class="col-sm-3">
                <select id="sel_object" class="eqLogicAttr form-control" data-l1key="object_id">
                  <option value="">{{Aucun}}</option>
                  <?php
                    foreach (jeeObject::all() as $object) {
                        echo '<option value="' . $object->getId() . '">' . $object->getName() . '</option>';
                    }
                  ?>
                </select>
              </div>
            </div>
            <div class="form-group">
              <label class="col-sm-3 control-label">{{Catégorie}}</label>
              <div class="col-sm-9">
                <?php
                  foreach (jeedom::getConfiguration('eqLogic:category') as $key => $value) {
                      echo '<label class="checkbox-inline">';
                      echo '<input type="checkbox" class="eqLogicAttr" data-l1key="category" data-l2key="' . $key . '" />' . $value['name'];
                      echo '</label>';
                  }
                ?>
              </div>
            </div>
            <div class="form-group">
              <label class="col-sm-3 control-label"></label>
              <div class="col-sm-9">
                <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isEnable"
                    checked />{{Activer}}</label>
                <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isVisible"
                    checked />{{Visible}}</label>
              </div>

            </div>

            <legend><i class="fas fa-cogs"></i> {{Paramètres}}</legend>
            <div class="form-group">
              <label class="col-sm-3 control-label">{{Utiliser le widget du plugin}}</label>
              <div class="col-sm-3 form-check-input">
                <input type="checkbox" required class="eqLogicAttr" data-l1key="configuration"
                  data-l2key="isWidgetPlugin" checked /></label>
              </div>
            </div>

          </fieldset>
        </form>
      </div>

      <!--

        Onglet Horaire 

      -->
      <div role="tabpanel" class="tab-pane" id="horairetab">
        <form class="form-horizontal">
          <fieldset>
            <br /><br />

            <div class="form-group">
              <label class="col-sm-2 control-label">{{Latitude}}</label>
              <div class="col-sm-2">
                <input type="text" class="eqLogicAttr form-control tooltips" data-l1key="configuration"
                  data-l2key="latitude" placeholder="0" />
              </div>
            </div>

            <div class="form-group">
              <label class="col-sm-2 control-label">{{Longitude}}</label>
              <div class="col-sm-2">
                <input type="text" class="eqLogicAttr form-control tooltips" data-l1key="configuration"
                  data-l2key="longitude" placeholder="0" />
              </div>
            </div>

            <div class="form-group">
              <label class="col-sm-2 control-label">{{Au lever du soleil}}</label>
              <div class="col-sm-2 form-check-input">
                <input type="checkbox" required class="eqLogicAttr" data-l1key="configuration" data-l2key="lever_soleil"
                  unchecked /></label>
              </div>
            </div>

            <div class="form-group">
              <label class="col-sm-2 control-label">{{Cron début du jour}}
                <sup><i class="fas fa-question-circle tooltips" title="{{Lever du soleil dans le terrarium}}"></i></sup>
              </label>
              <div class="col-sm-2">
                <div class="input-group">
                  <input type="text" class="eqLogicAttr form-control" data-l1key="configuration"
                    data-l2key="cron_jour" />
                  <span class="input-group-btn">
                    <a class="btn btn-default cursor jeeHelper" data-helper="cron"><i
                        class="fas fa-question-circle"></i></a>
                  </span>
                </div>
              </div>
            </div>

            <div class="form-group">
              <label class="col-sm-2 control-label">{{Au coucher du soleil}}</label>
              <div class="col-sm-2 form-check-input">
                <input type="checkbox" required class="eqLogicAttr" data-l1key="configuration"
                  data-l2key="coucher_soleil" unchecked /></label>
              </div>
            </div>

            <div class="form-group">
              <label class="col-sm-2 control-label">{{Cron début de nuit}}
                <sup><i class="fas fa-question-circle tooltips"
                    title="{{Coucher du soleil dans le terrarium}}"></i></sup>
              </label>
              <div class="col-sm-2">
                <div class="input-group">
                  <input type="text" class="eqLogicAttr form-control" data-l1key="configuration"
                    data-l2key="cron_nuit" />
                  <span class="input-group-btn">
                    <a class="btn btn-default cursor jeeHelper" data-helper="cron"><i
                        class="fas fa-question-circle"></i></a>
                  </span>
                </div>
              </div>
            </div>

          </fieldset>
        </form>
      </div>

      <!--

        Onglet Consignes 

      -->
      <div role="tabpanel" class="tab-pane" id="consignestab">

        <form class="form-horizontal">
          <fieldset>

            <br /><br />

            <div class="form-group">
              <label class="col-sm-2 control-label">{{Consigne min (°C)}}</label>
              <div class="col-sm-2">
                <input type="text" class="eqLogicAttr form-control tooltips" data-l1key="configuration"
                  data-l2key="consigne_min" placeholder="20" />
              </div>
            </div>

            <div class="form-group">
              <label class="col-sm-2 control-label">{{Consigne max (°C)}}</label>
              <div class="col-sm-2">
                <input type="text" class="eqLogicAttr form-control tooltips" data-l1key="configuration"
                  data-l2key="consigne_max" placeholder="30" />
              </div>
            </div>

            <div class="form-group">
              <label class="col-sm-2 control-label">{{Consigne humidité min (%)}}</label>
              <div class="col-sm-2">
                <input type="text" class="eqLogicAttr form-control tooltips" data-l1key="configuration"
                  data-l2key="consigne_hum_min" placeholder="0" />
              </div>
            </div>

            <div class="form-group">
              <label class="col-sm-2 control-label">{{Consigne humidité max (%)}}</label>
              <div class="col-sm-2">
                <input type="text" class="eqLogicAttr form-control tooltips" data-l1key="configuration"
                  data-l2key="consigne_hum_max" placeholder="100" />
              </div>
            </div>

            <div>
              <legend>
                {{Consignes du jour ?}}
                <a class="btn btn-primary btn-xs pull-right addAction" data-type="csg_jour"
                  style="position: relative; top : 5px;"><i class="fas fa-plus-circle"></i> {{Ajouter une
                  action}}</a>
              </legend>
              <div id="div_csg_jour">

              </div>
            </div>
          </fieldset>
        </form>

        <form class="form-horizontal">
          <fieldset>
            <div>
              <legend>
                {{Consignes de la nuit ?}}
                <a class="btn btn-primary btn-xs pull-right addAction" data-type="csg_nuit"
                  style="position: relative; top : 5px;"><i class="fas fa-plus-circle"></i> {{Ajouter une
                  action}}</a>
              </legend>
              <div id="div_csg_nuit">

              </div>
            </div>
          </fieldset>
        </form>
      </div>

      <!--

        Onglet Eclairage 

      -->
      <div role="tabpanel" class="tab-pane" id="eclairagetab">

        <form class="form-horizontal">
          <fieldset>

            <br /><br />

            <div class="form-group">
              <label class="col-sm-2 control-label">{{Cron répétition}}
                <sup><i class="fas fa-question-circle tooltips"
                    title="{{Pour pallier à une erreur de transmission}}"></i></sup>
              </label>
              <div class="col-sm-2">
                <div class="input-group">
                  <input type="text" class="eqLogicAttr form-control" data-l1key="configuration"
                    data-l2key="cron_repetition_eclairage" />
                  <span class="input-group-btn">
                    <a class="btn btn-default cursor jeeHelper" data-helper="cron"><i
                        class="fas fa-question-circle"></i></a>
                  </span>
                </div>
              </div>
            </div>

            <br /><br />

            <div>
              <legend>
                {{Actions du jour ?}}
                <a class="btn btn-primary btn-xs pull-right addAction" data-type="ecl_jour"
                  style="position: relative; top : 5px;"><i class="fas fa-plus-circle"></i> {{Ajouter une
                  action}}</a>
              </legend>
              <div id="div_ecl_jour">

              </div>
            </div>
          </fieldset>
        </form>

        <form class="form-horizontal">
          <fieldset>
            <div>
              <legend>
                {{Actions de la nuit ?}}
                <a class="btn btn-primary btn-xs pull-right addAction" data-type="ecl_nuit"
                  style="position: relative; top : 5px;"><i class="fas fa-plus-circle"></i> {{Ajouter une
                  action}}</a>
              </legend>
              <div id="div_ecl_nuit">

              </div>
            </div>
          </fieldset>
        </form>
      </div>

      <!--

        Onglet Chauffage

      -->
      <div role="tabpanel" class="tab-pane" id="chauffagetab">
        <form class="form-horizontal">
          <fieldset>
            <br /><br />

            <div class="form-group">
              <label class="col-sm-2 control-label">{{Température terrarium}}</label>
              <div class="col-sm-4">
                <div class="input-group">
                  <input type="text" class="eqLogicAttr form-control tooltips roundedLeft" data-l1key="configuration"
                    data-l2key="temperature_terrarium" data-concat="1" />
                  <span class="input-group-btn">
                    <a class="btn btn-default listCmdInfo roundedRight"><i class="fas fa-list-alt"></i></a>
                  </span>
                </div>
              </div>
            </div>

            <div class="form-group">
              <label class="col-sm-2 control-label">{{Hystéresis min (°C)}}</label>
              <div class="col-sm-2">
                <input type="text" class="eqLogicAttr form-control tooltips" data-l1key="configuration"
                  data-l2key="hysteresis_min" placeholder="1" />
              </div>
            </div>

            <div class="form-group">
              <label class="col-sm-2 control-label">{{Hystéresis max (°C)}}</label>
              <div class="col-sm-2">
                <input type="text" class="eqLogicAttr form-control tooltips" data-l1key="configuration"
                  data-l2key="hysteresis_max" placeholder="1" />
              </div>
            </div>

            <div class="form-group">
              <label class="col-sm-2 control-label">{{Cron répétition}}
                <sup><i class="fas fa-question-circle tooltips"
                    title="{{Pour pallier à une erreur de transmission}}"></i></sup>
              </label>
              <div class="col-sm-2">
                <div class="input-group">
                  <input type="text" class="eqLogicAttr form-control" data-l1key="configuration"
                    data-l2key="cron_repetition_chauffage" />
                  <span class="input-group-btn">
                    <a class="btn btn-default cursor jeeHelper" data-helper="cron"><i
                        class="fas fa-question-circle"></i></a>
                  </span>
                </div>
              </div>
            </div>

            <form class="form-horizontal">
              <fieldset>
                <div>
                  <legend>
                    {{Actions démarrage chauffage ?}}
                    <a class="btn btn-primary btn-xs pull-right addAction" data-type="chf_oui"
                      style="position: relative; top : 5px;"><i class="fas fa-plus-circle"></i> {{Ajouter une
                      action}}</a>
                  </legend>
                  <div id="div_chf_oui">

                  </div>
                </div>
              </fieldset>
            </form>

            <form class="form-horizontal">
              <fieldset>
                <div>
                  <legend>
                    {{Actions arrêt chauffage ?}}
                    <a class="btn btn-primary btn-xs pull-right addAction" data-type="chf_non"
                      style="position: relative; top : 5px;"><i class="fas fa-plus-circle"></i> {{Ajouter une
                      action}}</a>
                  </legend>
                  <div id="div_chf_non">

                  </div>
                </div>
              </fieldset>
            </form>

          </fieldset>
        </form>
      </div>

      <!--

        Onglet Humidité

      -->
      <div role="tabpanel" class="tab-pane" id="humiditetab">
        <form class="form-horizontal">
          <fieldset>
            <br /><br />

            <div class="form-group">
              <label class="col-sm-2 control-label">{{Humidité terrarium}}</label>
              <div class="col-sm-4">
                <div class="input-group">
                  <input type="text" class="eqLogicAttr form-control tooltips roundedLeft" data-l1key="configuration"
                    data-l2key="humidite_terrarium" data-concat="1" />
                  <span class="input-group-btn">
                    <a class="btn btn-default listCmdInfo roundedRight"><i class="fas fa-list-alt"></i></a>
                  </span>
                </div>
              </div>
            </div>

            <div class="form-group">
              <label class="col-sm-2 control-label">{{Hystéresis min (%)}}</label>
              <div class="col-sm-2">
                <input type="text" class="eqLogicAttr form-control tooltips" data-l1key="configuration"
                  data-l2key="hysteresis_min_hum" placeholder="1" />
              </div>
            </div>

            <div class="form-group">
              <label class="col-sm-2 control-label">{{Hystéresis max (%)}}</label>
              <div class="col-sm-2">
                <input type="text" class="eqLogicAttr form-control tooltips" data-l1key="configuration"
                  data-l2key="hysteresis_max_hum" placeholder="1" />
              </div>
            </div>

            <div class="form-group">
              <label class="col-sm-2 control-label">{{Cron répétition}}
                <sup><i class="fas fa-question-circle tooltips"
                    title="{{Pour pallier à une erreur de transmission}}"></i></sup>
              </label>
              <div class="col-sm-2">
                <div class="input-group">
                  <input type="text" class="eqLogicAttr form-control" data-l1key="configuration"
                    data-l2key="cron_repetition_humidite" />
                  <span class="input-group-btn">
                    <a class="btn btn-default cursor jeeHelper" data-helper="cron"><i
                        class="fas fa-question-circle"></i></a>
                  </span>
                </div>
              </div>
            </div>

            <form class="form-horizontal">
              <fieldset>
                <div>
                  <legend>
                    {{Actions démarrage humidité ?}}
                    <a class="btn btn-primary btn-xs pull-right addAction" data-type="hum_oui"
                      style="position: relative; top : 5px;"><i class="fas fa-plus-circle"></i> {{Ajouter une
                      action}}</a>
                  </legend>
                  <div id="div_hum_oui">

                  </div>
                </div>
              </fieldset>
            </form>

            <form class="form-horizontal">
              <fieldset>
                <div>
                  <legend>
                    {{Actions arrêt humidité ?}}
                    <a class="btn btn-primary btn-xs pull-right addAction" data-type="hum_non"
                      style="position: relative; top : 5px;"><i class="fas fa-plus-circle"></i> {{Ajouter une
                      action}}</a>
                  </legend>
                  <div id="div_hum_non">

                  </div>
                </div>
              </fieldset>
            </form>

          </fieldset>
        </form>
      </div>

      <!--

        Onglet Brumisation

      -->
      <div role="tabpanel" class="tab-pane" id="brumisationtab">
        <form class="form-horizontal">
          <fieldset>
            <br /><br />

            <div class="form-group">
              <label class="col-sm-2 control-label">{{Temps de brumisation (sec)}}</label>
              <div class="col-sm-2">
                <input type="text" class="eqLogicAttr form-control tooltips" data-l1key="configuration"
                  data-l2key="temps_brume" placeholder="15" />
              </div>
            </div>

            <form class="form-horizontal">
              <fieldset>
                <legend>
                  {{Déclenchement ?}}
                  <a class="btn btn-primary btn-xs pull-right addSchedule" data-type="schedule_brume"
                    style="position: relative; top : 5px;"><i class="fas fa-plus-circle"></i> {{Ajouter une
                    programmation}}</a>
                </legend>
                <div id="div_schedule_brume">

                </div>

              </fieldset>
            </form>

            <form class="form-horizontal">
              <fieldset>
                <div>
                  <legend>
                    {{Actions brumisation ?}}
                    <a class="btn btn-primary btn-xs pull-right addAction" data-type="brume_on"
                      style="position: relative; top : 5px;"><i class="fas fa-plus-circle"></i> {{Ajouter une
                      action}}</a>
                  </legend>
                  <div id="div_brume_on">

                  </div>
                </div>
              </fieldset>
            </form>

            <form class="form-horizontal">
              <fieldset>
                <div>
                  <legend>
                    {{Actions fin de brumisation ?}}
                    <a class="btn btn-primary btn-xs pull-right addAction" data-type="brume_off"
                      style="position: relative; top : 5px;"><i class="fas fa-plus-circle"></i> {{Ajouter une
                      action}}</a>
                  </legend>
                  <div id="div_brume_off">

                  </div>
                </div>
              </fieldset>
            </form>

          </fieldset>
        </form>
      </div>

      <!--

        Onglet Consommation

      -->
      <div role="tabpanel" class="tab-pane" id="consommationtab">
        <form class="form-horizontal">
          <fieldset>
            <br /><br />

            <div class="form-group">
              <label class="col-sm-2 control-label">{{Commande de consommation}}</label>
              <div class="col-sm-4 input-group">
                <input class="eqLogicAttr form-control input-sm" data-l1key="configuration"
                  data-l2key="cmdConsommation"></input>
                <a class="btn btn-default listCmdInfo cursor btn-sm input-group-addon" data-input="cmdConsommation"><i
                    class="fas fa-list-alt"></i></a>
              </div>
            </div>

          </fieldset>
        </form>
      </div>

      <div role="tabpanel" class="tab-pane" id="commandtab">
        <a class="btn btn-success btn-sm cmdAction pull-right" data-action="add" style="margin-top:5px;"><i
            class="fa fa-plus-circle"></i> {{Commandes}}</a><br /><br />
        <table id="table_cmd" class="table table-bordered table-condensed">
          <thead>
            <tr>
              <th>{{Nom}}</th>
              <th>{{Type}}</th>
              <th>{{Paramètres}}</th>
              <th>{{Action}}</th>
            </tr>
          </thead>
          <tbody>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Inclusion du fichier javascript du plugin (dossier, nom_du_fichier, extension_du_fichier, nom_du_plugin) -->
<?php include_file('desktop', 'terrarium', 'js', 'terrarium');?>
<!-- Inclusion du fichier javascript du core - NE PAS MODIFIER NI SUPPRIMER -->
<?php include_file('core', 'plugin.template', 'js');
