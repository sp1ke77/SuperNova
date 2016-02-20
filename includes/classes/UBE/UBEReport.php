<?php

class UBEReport {

  // ------------------------------------------------------------------------------------------------
  // Записывает боевой отчет в БД
  /**
   * @param UBE $ube
   *
   * @return bool|string
   */
  function sn_ube_report_save($ube) {
    global $config;

    // Если уже есть ИД репорта - значит репорт был взят из таблицы. С таким мы не работаем
    if($ube->get_cypher()) {
      return false;
    }

    // Генерируем уникальный секретный ключ и проверяем наличие в базе
    do {
      $ube->report_cypher = sys_random_string(32);
    } while(doquery("SELECT ube_report_cypher FROM {{ube_report}} WHERE ube_report_cypher = '{$ube->report_cypher}' LIMIT 1 FOR UPDATE", true));

    // Инициализация таблицы для пакетной вставки информации
    $sql_perform = array(
      'ube_report_player' => array(
        array(
          '`ube_report_id`',
          '`ube_report_player_player_id`',
          '`ube_report_player_name`',
          '`ube_report_player_attacker`',
          '`ube_report_player_bonus_attack`',
          '`ube_report_player_bonus_shield`',
          '`ube_report_player_bonus_armor`',
        ),
      ),

      'ube_report_fleet' => array(
        array(
          '`ube_report_id`',
          '`ube_report_fleet_player_id`',
          '`ube_report_fleet_fleet_id`',
          '`ube_report_fleet_planet_id`',
          '`ube_report_fleet_planet_name`',
          '`ube_report_fleet_planet_galaxy`',
          '`ube_report_fleet_planet_system`',
          '`ube_report_fleet_planet_planet`',
          '`ube_report_fleet_planet_planet_type`',
          '`ube_report_fleet_resource_metal`',
          '`ube_report_fleet_resource_crystal`',
          '`ube_report_fleet_resource_deuterium`',
          '`ube_report_fleet_bonus_attack`',
          '`ube_report_fleet_bonus_shield`',
          '`ube_report_fleet_bonus_armor`',
        ),
      ),

      'ube_report_outcome_fleet' => array(
        array(
          '`ube_report_id`',
          '`ube_report_outcome_fleet_fleet_id`',
          '`ube_report_outcome_fleet_resource_lost_metal`',
          '`ube_report_outcome_fleet_resource_lost_crystal`',
          '`ube_report_outcome_fleet_resource_lost_deuterium`',
          '`ube_report_outcome_fleet_resource_dropped_metal`',
          '`ube_report_outcome_fleet_resource_dropped_crystal`',
          '`ube_report_outcome_fleet_resource_dropped_deuterium`',
          '`ube_report_outcome_fleet_resource_loot_metal`',
          '`ube_report_outcome_fleet_resource_loot_crystal`',
          '`ube_report_outcome_fleet_resource_loot_deuterium`',
          '`ube_report_outcome_fleet_resource_lost_in_metal`',
        ),
      ),

      'ube_report_outcome_unit' => array(
        array(
          '`ube_report_id`',
          '`ube_report_outcome_unit_fleet_id`',
          '`ube_report_outcome_unit_unit_id`',
          '`ube_report_outcome_unit_restored`',
          '`ube_report_outcome_unit_lost`',
          '`ube_report_outcome_unit_sort_order`',
        ),
      ),

      'ube_report_unit' => array(
        array(
          '`ube_report_id`',
          '`ube_report_unit_player_id`',
          '`ube_report_unit_fleet_id`',
          '`ube_report_unit_round`',
          '`ube_report_unit_unit_id`',
          '`ube_report_unit_count`',
          '`ube_report_unit_boom`',
          '`ube_report_unit_attack`',
          '`ube_report_unit_shield`',
          '`ube_report_unit_armor`',
          '`ube_report_unit_attack_base`',
          '`ube_report_unit_shield_base`',
          '`ube_report_unit_armor_base`',
          '`ube_report_unit_sort_order`',
        ),
      ),
    );

    // Сохраняем общую информацию о бое
    $outcome = &$ube->outcome;
    $ube_report_debris_total_in_metal = (
        floatval($outcome[UBE_DEBRIS][RES_METAL])
        + floatval($outcome[UBE_DEBRIS][RES_CRYSTAL]) * floatval($config->rpg_exchange_crystal)
      ) / (floatval($config->rpg_exchange_metal) ? floatval($config->rpg_exchange_metal) : 1);
    doquery("INSERT INTO `{{ube_report}}`
    SET
      `ube_report_cypher` = '{$ube->report_cypher}',
      `ube_report_time_combat` = '" . date(FMT_DATE_TIME_SQL, $ube->combat_timestamp) . "',
      `ube_report_time_spent` = {$ube->time_spent},

      `ube_report_combat_admin` = " . (int)$ube->is_admin_in_combat . ",
      `ube_report_mission_type` = {$ube->mission_type_id},

      `ube_report_combat_result` = {$outcome[UBE_COMBAT_RESULT]},
      `ube_report_combat_sfr` = " . (int)$outcome[UBE_SFR] . ",

      `ube_report_debris_metal` = " . (float)$outcome[UBE_DEBRIS][RES_METAL] . ",
      `ube_report_debris_crystal` = " . (float)$outcome[UBE_DEBRIS][RES_CRYSTAL] . ",
      `ube_report_debris_total_in_metal` = " . $ube_report_debris_total_in_metal . ",

      `ube_report_planet_id`          = " . (int)$outcome[UBE_PLANET][PLANET_ID] . ",
      `ube_report_planet_name`        = '" . db_escape($outcome[UBE_PLANET][PLANET_NAME]) . "',
      `ube_report_planet_size`        = " . (int)$outcome[UBE_PLANET][PLANET_SIZE] . ",
      `ube_report_planet_galaxy`      = " . (int)$outcome[UBE_PLANET][PLANET_GALAXY] . ",
      `ube_report_planet_system`      = " . (int)$outcome[UBE_PLANET][PLANET_SYSTEM] . ",
      `ube_report_planet_planet`      = " . (int)$outcome[UBE_PLANET][PLANET_PLANET] . ",
      `ube_report_planet_planet_type` = " . (int)$outcome[UBE_PLANET][PLANET_TYPE] . ",

      `ube_report_moon` = " . (int)$outcome[UBE_MOON] . ",
      `ube_report_moon_chance` = " . (int)$outcome[UBE_MOON_CHANCE] . ",
      `ube_report_moon_size` = " . (float)$outcome[UBE_MOON_SIZE] . ",

      `ube_report_moon_reapers` = " . (int)$outcome[UBE_MOON_REAPERS] . ",
      `ube_report_moon_destroy_chance` = " . (int)$outcome[UBE_MOON_DESTROY_CHANCE] . ",
      `ube_report_moon_reapers_die_chance` = " . (int)$outcome[UBE_MOON_REAPERS_DIE_CHANCE] . ",

      `ube_report_capture_result` = " . (int)$outcome[UBE_CAPTURE_RESULT] . "
  ");
//    $ube_report_id = $combat_data[UBE_REPORT_ID] = db_insert_id();
    $ube_report_id = db_insert_id();

    // Сохраняем общую информацию по игрокам
    $player_sides = $ube->players_obj->get_player_sides();
    foreach($player_sides as $player_id => $player_side) {
      $sql_perform['ube_report_player'][] = array(
        $ube_report_id,
        $player_id,

        "'" . db_escape($ube->players_obj->get_player_name($player_id)) . "'",
        (int)$ube->players_obj->get_player_side($player_id),

        (float)$ube->players_obj->get_player_bonus_single($player_id, UBE_ATTACK),
        (float)$ube->players_obj->get_player_bonus_single($player_id, UBE_SHIELD),
        (float)$ube->players_obj->get_player_bonus_single($player_id, UBE_ARMOR),
      );
    }

    // Всякая информация по флотам
    $unit_sort_order = 0;
    foreach($ube->fleets_obj->fleets as $fleet_id => &$fleet_info) {
      // Сохраняем общую информацию по флотам
      $sql_perform['ube_report_fleet'][] = array(
        $ube_report_id,
        $fleet_info[UBE_OWNER],
        $fleet_id,

        (float)$fleet_info[UBE_PLANET][PLANET_ID],
        "'" . db_escape($fleet_info[UBE_PLANET][PLANET_NAME]) . "'",
        (int)$fleet_info[UBE_PLANET][PLANET_GALAXY],
        (int)$fleet_info[UBE_PLANET][PLANET_SYSTEM],
        (int)$fleet_info[UBE_PLANET][PLANET_PLANET],
        (int)$fleet_info[UBE_PLANET][PLANET_TYPE],

        (float)$fleet_info[UBE_RESOURCES][RES_METAL],
        (float)$fleet_info[UBE_RESOURCES][RES_CRYSTAL],
        (float)$fleet_info[UBE_RESOURCES][RES_DEUTERIUM],

        (float)$fleet_info[UBE_BONUSES][UBE_ATTACK],
        (float)$fleet_info[UBE_BONUSES][UBE_SHIELD],
        (float)$fleet_info[UBE_BONUSES][UBE_ARMOR],
      );

      // Сохраняем итоговую информацию по ресурсам флота - потеряно, выброшено, увезено
      $fleet_outcome_data = &$outcome[UBE_FLEETS][$fleet_id];
      $sql_perform['ube_report_outcome_fleet'][] = array(
        $ube_report_id,
        $fleet_id,

        (float)$fleet_outcome_data[UBE_RESOURCES_LOST][RES_METAL],
        (float)$fleet_outcome_data[UBE_RESOURCES_LOST][RES_CRYSTAL],
        (float)$fleet_outcome_data[UBE_RESOURCES_LOST][RES_DEUTERIUM],

        (float)$fleet_outcome_data[UBE_CARGO_DROPPED][RES_METAL],
        (float)$fleet_outcome_data[UBE_CARGO_DROPPED][RES_CRYSTAL],
        (float)$fleet_outcome_data[UBE_CARGO_DROPPED][RES_DEUTERIUM],

        (float)$fleet_outcome_data[UBE_RESOURCES_LOOTED][RES_METAL],
        (float)$fleet_outcome_data[UBE_RESOURCES_LOOTED][RES_CRYSTAL],
        (float)$fleet_outcome_data[UBE_RESOURCES_LOOTED][RES_DEUTERIUM],

        (float)$fleet_outcome_data[UBE_RESOURCES_LOST_IN_METAL][RES_METAL],
      );

      // Сохраняем результаты по юнитам - потеряно и восстановлено
      foreach($fleet_info[UBE_COUNT] as $unit_id => $unit_count) {
        if($fleet_outcome_data[UBE_UNITS_LOST][$unit_id] || $fleet_outcome_data[UBE_DEFENCE_RESTORE][$unit_id]) {
          $unit_sort_order++;
          $sql_perform['ube_report_outcome_unit'][] = array(
            $ube_report_id,
            $fleet_id,

            $unit_id,
            (float)$fleet_outcome_data[UBE_DEFENCE_RESTORE][$unit_id],
            (float)$fleet_outcome_data[UBE_UNITS_LOST][$unit_id],

            $unit_sort_order,
          );
        }
      }
    }

    // Сохраняем информацию о раундах
    $unit_sort_order = 0;
    foreach($ube->rounds as $round => &$round_data) {
      foreach($round_data[UBE_FLEETS] as $fleet_id => &$fleet_data) {
        foreach($fleet_data[UBE_COUNT] as $unit_id => $unit_count) {
          $unit_sort_order++;

          $sql_perform['ube_report_unit'][] = array(
            $ube_report_id,
            $fleet_data[UBE_FLEET_INFO][UBE_OWNER],
            $fleet_id,
            $round,

            $unit_id,
            $unit_count,
            (int)$fleet_data[UBE_UNITS_BOOM][$unit_id],

            $fleet_data[UBE_ATTACK][$unit_id],
            $fleet_data[UBE_SHIELD][$unit_id],
            $fleet_data[UBE_ARMOR][$unit_id],

            $fleet_data[UBE_ATTACK_BASE][$unit_id],
            $fleet_data[UBE_SHIELD_BASE][$unit_id],
            $fleet_data[UBE_ARMOR_BASE][$unit_id],

            $unit_sort_order,
          );
        }
      }
    }

    // Пакетная вставка данных
    foreach($sql_perform as $table_name => $table_data) {
      if(count($table_data) < 2) {
        continue;
      }
      foreach($table_data as &$record_data) {
        $record_data = '(' . implode(',', $record_data) . ')';
      }
      $fields = $table_data[0];
      unset($table_data[0]);
      doquery("INSERT INTO {{{$table_name}}} {$fields} VALUES " . implode(',', $table_data));
    }

    return $ube->report_cypher;
  }


  // ------------------------------------------------------------------------------------------------
  // Читает боевой отчет из БД
  /**
   * @param $report_cypher
   *
   * @return string|UBE
   */
  function sn_ube_report_load($report_cypher) {
    $report_cypher = db_escape($report_cypher);

    $report_row = doquery("SELECT * FROM {{ube_report}} WHERE ube_report_cypher = '{$report_cypher}' LIMIT 1", true);
    if(!$report_row) {
      return UBE_REPORT_NOT_FOUND;
    }

    $ube = new UBE();
    $ube->combat_timestamp = strtotime($report_row['ube_report_time_combat']);
    $ube->time_spent = $report_row['ube_report_time_spent'];
    $ube->report_cypher = $report_cypher;

    $ube->is_ube_loaded = true;
    $ube->is_admin_in_combat = $report_row['ube_report_combat_admin'];
    $ube->mission_type_id = $report_row['ube_report_mission_type'];

    $ube->outcome = array(
      UBE_COMBAT_RESULT => $report_row['ube_report_combat_result'],
      UBE_SFR           => $report_row['ube_report_combat_sfr'],

      UBE_PLANET => array(
        PLANET_ID     => $report_row['ube_report_planet_id'],
        PLANET_NAME   => $report_row['ube_report_planet_name'],
        PLANET_SIZE   => $report_row['ube_report_planet_size'],
        PLANET_GALAXY => $report_row['ube_report_planet_galaxy'],
        PLANET_SYSTEM => $report_row['ube_report_planet_system'],
        PLANET_PLANET => $report_row['ube_report_planet_planet'],
        PLANET_TYPE   => $report_row['ube_report_planet_planet_type'],
      ),

      UBE_DEBRIS => array(
        RES_METAL   => $report_row['ube_report_debris_metal'],
        RES_CRYSTAL => $report_row['ube_report_debris_crystal'],
      ),

      UBE_MOON        => $report_row['ube_report_moon'],
      UBE_MOON_CHANCE => $report_row['ube_report_moon_chance'],
      UBE_MOON_SIZE   => $report_row['ube_report_moon_size'],

      UBE_MOON_REAPERS            => $report_row['ube_report_moon_reapers'],
      UBE_MOON_DESTROY_CHANCE     => $report_row['ube_report_moon_destroy_chance'],
      UBE_MOON_REAPERS_DIE_CHANCE => $report_row['ube_report_moon_reapers_die_chance'],

      UBE_CAPTURE_RESULT => $report_row['ube_report_capture_result'],

      UBE_ATTACKERS => array(),
      UBE_DEFENDERS => array(),
    );

    $outcome = &$ube->outcome;

    $query = doquery("SELECT * FROM {{ube_report_player}} WHERE `ube_report_id` = {$report_row['ube_report_id']}");
    while($player_row = db_fetch($query)) {
      $ube->players_obj->init_player_from_report_info($player_row);
    }

    $query = doquery("SELECT * FROM {{ube_report_fleet}} WHERE `ube_report_id` = {$report_row['ube_report_id']}");
    while($fleet_row = db_fetch($query)) {
      $ube->fleets_obj->fleets[$fleet_row['ube_report_fleet_fleet_id']] = array(
        UBE_OWNER => $fleet_row['ube_report_fleet_player_id'],

        UBE_FLEET_TYPE => $ube->players_obj->get_player_side($fleet_row['ube_report_fleet_player_id']) ? UBE_ATTACKERS : UBE_DEFENDERS,

        UBE_PLANET => array(
          PLANET_ID     => $fleet_row['ube_report_fleet_planet_id'],
          PLANET_NAME   => $fleet_row['ube_report_fleet_planet_name'],
          PLANET_GALAXY => $fleet_row['ube_report_fleet_planet_galaxy'],
          PLANET_SYSTEM => $fleet_row['ube_report_fleet_planet_system'],
          PLANET_PLANET => $fleet_row['ube_report_fleet_planet_planet'],
          PLANET_TYPE   => $fleet_row['ube_report_fleet_planet_planet_type'],
        ),

        UBE_BONUSES => array(
          UBE_ATTACK => $player_row['ube_report_fleet_bonus_attack'],
          UBE_SHIELD => $player_row['ube_report_fleet_bonus_shield'],
          UBE_ARMOR  => $player_row['ube_report_fleet_bonus_armor'],
        ),

        UBE_RESOURCES => array(
          RES_METAL     => $player_row['ube_report_fleet_resource_metal'],
          RES_CRYSTAL   => $player_row['ube_report_fleet_resource_crystal'],
          RES_DEUTERIUM => $player_row['ube_report_fleet_resource_deuterium'],
        ),
      );
    }

    $ube->rounds = array();
    $rounds_data = &$ube->rounds;

    $query = doquery("SELECT * FROM {{ube_report_unit}} WHERE `ube_report_id` = {$report_row['ube_report_id']} ORDER BY `ube_report_unit_sort_order`");
    while($round_row = db_fetch($query)) {
      $round = $round_row['ube_report_unit_round'];
      $fleet_id = $round_row['ube_report_unit_fleet_id'];

      $side = $ube->fleets_obj->fleets[$fleet_id][UBE_FLEET_TYPE];
      $rounds_data[$round][$side][UBE_ATTACK][$fleet_id] = 0;

      if(!isset($rounds_data[$round][UBE_FLEETS][$fleet_id])) {
        $rounds_data[$round][UBE_FLEETS][$fleet_id] = array();
      }

      $round_fleet_data = &$rounds_data[$round][UBE_FLEETS][$fleet_id];
      $unit_id = $round_row['ube_report_unit_unit_id'];
      $round_fleet_data[UBE_COUNT][$unit_id] = $round_row['ube_report_unit_count'];
      $round_fleet_data[UBE_UNITS_BOOM][$unit_id] = $round_row['ube_report_unit_boom'];

      $round_fleet_data[UBE_ATTACK][$unit_id] = $round_row['ube_report_unit_attack'];
      $round_fleet_data[UBE_SHIELD][$unit_id] = $round_row['ube_report_unit_shield'];
      $round_fleet_data[UBE_ARMOR][$unit_id] = $round_row['ube_report_unit_armor'];

      $round_fleet_data[UBE_ATTACK_BASE][$unit_id] = $round_row['ube_report_unit_attack_base'];
      $round_fleet_data[UBE_SHIELD_BASE][$unit_id] = $round_row['ube_report_unit_shield_base'];
      $round_fleet_data[UBE_ARMOR_BASE][$unit_id] = $round_row['ube_report_unit_armor_base'];
    }


    $query = doquery("SELECT * FROM {{ube_report_outcome_fleet}} WHERE `ube_report_id` = {$report_row['ube_report_id']}");
    while($row = db_fetch($query)) {
      $fleet_id = $row['ube_report_outcome_fleet_fleet_id'];

      $outcome[UBE_FLEETS][$fleet_id] = array(
        UBE_RESOURCES_LOST => array(
          RES_METAL     => $row['ube_report_outcome_fleet_resource_lost_metal'],
          RES_CRYSTAL   => $row['ube_report_outcome_fleet_resource_lost_crystal'],
          RES_DEUTERIUM => $row['ube_report_outcome_fleet_resource_lost_deuterium'],
        ),

        UBE_CARGO_DROPPED => array(
          RES_METAL     => $row['ube_report_outcome_fleet_resource_dropped_metal'],
          RES_CRYSTAL   => $row['ube_report_outcome_fleet_resource_dropped_crystal'],
          RES_DEUTERIUM => $row['ube_report_outcome_fleet_resource_dropped_deuterium'],
        ),

        UBE_RESOURCES_LOOTED => array(
          RES_METAL     => $row['ube_report_outcome_fleet_resource_loot_metal'],
          RES_CRYSTAL   => $row['ube_report_outcome_fleet_resource_loot_crystal'],
          RES_DEUTERIUM => $row['ube_report_outcome_fleet_resource_loot_deuterium'],
        ),

        UBE_RESOURCES_LOST_IN_METAL => array(
          RES_METAL => $row['ube_report_outcome_fleet_resource_lost_in_metal'],
        ),
      );

      $side = $ube->fleets_obj->fleets[$fleet_id][UBE_FLEET_TYPE];

      $outcome[$side][UBE_FLEETS][$fleet_id] = &$outcome[UBE_FLEETS][$fleet_id];
    }

    $query = doquery("SELECT * FROM {{ube_report_outcome_unit}} WHERE `ube_report_id` = {$report_row['ube_report_id']} ORDER BY `ube_report_outcome_unit_sort_order`");
    while($row = db_fetch($query)) {
      $fleet_id = $row['ube_report_outcome_unit_fleet_id'];
      $side = $ube->fleets_obj->fleets[$fleet_id][UBE_FLEET_TYPE];
      $outcome[$side][UBE_FLEETS][$fleet_id][UBE_UNITS_LOST][$row['ube_report_outcome_unit_unit_id']] = $row['ube_report_outcome_unit_lost'];
      $outcome[$side][UBE_FLEETS][$fleet_id][UBE_DEFENCE_RESTORE][$row['ube_report_outcome_unit_unit_id']] = $row['ube_report_outcome_unit_restored'];
    }

    return $ube;
  }


// ------------------------------------------------------------------------------------------------
// Генерирует данные для отчета из разобранных данных боя
  /**
   * @param UBE $ube
   * @param     $template_result
   */
  function sn_ube_report_generate(UBE $ube, &$template_result)
  {
    if(!is_object($ube))
    {
      return;
    }

    global $lang;

    // Обсчитываем результаты боя из начальных данных
    $outcome = &$ube->outcome;
    // Генерируем отчет по флотам
    for($round = 1; $round <= count($ube->rounds) - 1; $round++)
    {
      $round_template = array(
        'NUMBER' => $round,
        '.' => array(
          'fleet' => $this->sn_ube_report_round_fleet($ube, $round),
        ),
      );
      $template_result['.']['round'][] = $round_template;
    }

    // Боевые потери флотов
    foreach(array(UBE_ATTACKERS, UBE_DEFENDERS) as $side)
    {
      if(!is_array($outcome[$side][UBE_FLEETS]))
      {
        continue;
      }
      foreach($outcome[$side][UBE_FLEETS] as $fleet_id => $temp)
      {
        $fleet_owner_id = $ube->fleets_obj->fleets[$fleet_id][UBE_OWNER];
        $fleet_outcome = &$outcome[UBE_FLEETS][$fleet_id];

        $template_result['.']['loss'][] = array(
          'ID' => $fleet_id,
          'NAME' => $ube->players_obj->get_player_name($fleet_owner_id),
          'IS_ATTACKER' => $ube->players_obj->get_player_side($fleet_owner_id),
          '.' => array(
            'param' => array_merge(
              $this->sn_ube_report_table_render($fleet_outcome[UBE_DEFENCE_RESTORE], $lang['ube_report_info_restored']),
              $this->sn_ube_report_table_render($fleet_outcome[UBE_UNITS_LOST], $lang['ube_report_info_loss_final']),
              $this->sn_ube_report_table_render($fleet_outcome[UBE_RESOURCES_LOST], $lang['ube_report_info_loss_resources']),
              $this->sn_ube_report_table_render($fleet_outcome[UBE_CARGO_DROPPED], $lang['ube_report_info_loss_dropped']),
              $this->sn_ube_report_table_render($fleet_outcome[UBE_RESOURCES_LOOTED], $lang[$ube->players_obj->get_player_side($fleet_owner_id) ? 'ube_report_info_loot_lost' : 'ube_report_info_loss_gained']),
              $this->sn_ube_report_table_render($fleet_outcome[UBE_RESOURCES_LOST_IN_METAL], $lang['ube_report_info_loss_in_metal'])
            ),
          ),
        );
      }
    }

    // Обломки
    $debris = array();
    foreach(array(RES_METAL, RES_CRYSTAL) as $resource_id)
    {
      if($resource_amount = $outcome[UBE_DEBRIS][$resource_id])
      {
        $debris[] = array(
          'NAME' => $lang['tech'][$resource_id],
          'AMOUNT' => pretty_number($resource_amount),
        );
      }
    }

// TODO: $combat_data[UBE_OPTIONS][UBE_COMBAT_ADMIN] - если админский бой не генерировать осколки и не делать луну. Сделать серверную опцию

    // Координаты, тип и название планеты - если есть
//R  $planet_owner_id = $combat_data[UBE_FLEETS][0][UBE_OWNER];
    if(isset($ube->outcome[UBE_PLANET]))
    {
      $template_result += $ube->outcome[UBE_PLANET];
      $template_result[PLANET_NAME] = str_replace(' ', '&nbsp;', htmlentities($template_result[PLANET_NAME], ENT_COMPAT, 'UTF-8'));
    }

    /** @noinspection SpellCheckingInspection */
    $template_result += array(
      'MICROTIME' => $ube->get_time_spent(),
      'COMBAT_TIME' => $ube->combat_timestamp ? $ube->combat_timestamp + SN_CLIENT_TIME_DIFF : 0,
      'COMBAT_TIME_TEXT' => date(FMT_DATE_TIME, $ube->combat_timestamp + SN_CLIENT_TIME_DIFF),
      'COMBAT_ROUNDS' => count($ube->rounds) - 1,
      'UBE_MISSION_TYPE' => $ube->mission_type_id,
      'MT_DESTROY' => MT_DESTROY,
      'UBE_REPORT_CYPHER' => $ube->get_cypher(),

      'PLANET_TYPE_TEXT' => $lang['sys_planet_type_sh'][$template_result['PLANET_TYPE']],

      'UBE_MOON' => $outcome[UBE_MOON],
      'UBE_MOON_CHANCE' => round($outcome[UBE_MOON_CHANCE], 2),
      'UBE_MOON_SIZE' => $outcome[UBE_MOON_SIZE],
      'UBE_MOON_REAPERS' => $outcome[UBE_MOON_REAPERS],
      'UBE_MOON_DESTROY_CHANCE' => $outcome[UBE_MOON_DESTROY_CHANCE],
      'UBE_MOON_REAPERS_DIE_CHANCE' => $outcome[UBE_MOON_REAPERS_DIE_CHANCE],

      'UBE_MOON_WAS' => UBE_MOON_WAS,
      'UBE_MOON_NONE' => UBE_MOON_NONE,
      'UBE_MOON_CREATE_SUCCESS' => UBE_MOON_CREATE_SUCCESS,
      'UBE_MOON_CREATE_FAILED' => UBE_MOON_CREATE_FAILED,
      'UBE_MOON_REAPERS_NONE' => UBE_MOON_REAPERS_NONE,
      'UBE_MOON_DESTROY_SUCCESS' => UBE_MOON_DESTROY_SUCCESS,
      'UBE_MOON_REAPERS_RETURNED' => UBE_MOON_REAPERS_RETURNED,

      'UBE_CAPTURE_RESULT' => $ube->outcome[UBE_CAPTURE_RESULT],
      'UBE_CAPTURE_RESULT_TEXT' => $lang['ube_report_capture_result'][$ube->outcome[UBE_CAPTURE_RESULT]],

      'UBE_SFR' => $outcome[UBE_SFR],
      'UBE_COMBAT_RESULT' => $outcome[UBE_COMBAT_RESULT],
      'UBE_COMBAT_RESULT_WIN' => UBE_COMBAT_RESULT_WIN,
      'UBE_COMBAT_RESULT_LOSS' => UBE_COMBAT_RESULT_LOSS,
    );
    $template_result['.']['debris'] = $debris;
  }

  // ------------------------------------------------------------------------------------------------
// Парсит инфу о раунде для темплейта
  function sn_ube_report_round_fleet(UBE $ube, $round)
  {
    global $lang;

    $round_template = array();
    $round_data = &$ube->rounds[$round];
    foreach(array(UBE_ATTACKERS, UBE_DEFENDERS) as $side)
    {
      $round_data[$side][UBE_ATTACK] = $round_data[$side][UBE_ATTACK] ? $round_data[$side][UBE_ATTACK] : array();
      foreach($round_data[$side][UBE_ATTACK] as $fleet_id => $temp)
      {
        $fleet_data = &$round_data[UBE_FLEETS][$fleet_id];
        $fleet_data_prev = &$ube->rounds[$round - 1][UBE_FLEETS][$fleet_id];
        $fleet_template = array(
          'ID' => $fleet_id,
          'IS_ATTACKER' => $side == UBE_ATTACKERS,
          'PLAYER_NAME' => $ube->players_obj->get_player_name($ube->fleets_obj->fleets[$fleet_id][UBE_OWNER], true),
        );

        if(is_array($ube->fleets_obj->fleets[$fleet_id][UBE_PLANET]))
        {
          $fleet_template += $ube->fleets_obj->fleets[$fleet_id][UBE_PLANET];
          $fleet_template[PLANET_NAME] = $fleet_template[PLANET_NAME] ? htmlentities($fleet_template[PLANET_NAME], ENT_COMPAT, 'UTF-8') : '';
          $fleet_template['PLANET_TYPE_TEXT'] = $lang['sys_planet_type_sh'][$fleet_template['PLANET_TYPE']];
        }

        foreach($fleet_data[UBE_COUNT] as $unit_id => $unit_count)
        {
          $shields_original = $fleet_data[UBE_SHIELD_BASE][$unit_id] * $fleet_data_prev[UBE_COUNT][$unit_id];
          $ship_template = array(
            'ID' => $unit_id,
            'NAME' => $lang['tech'][$unit_id],
            'ATTACK' => pretty_number($fleet_data[UBE_ATTACK][$unit_id]),
            'SHIELD' => pretty_number($shields_original),
            'SHIELD_LOST' => pretty_number($shields_original - $fleet_data[UBE_SHIELD][$unit_id]),
            'ARMOR' => pretty_number($fleet_data_prev[UBE_ARMOR][$unit_id]),
            'ARMOR_LOST' => pretty_number($fleet_data_prev[UBE_ARMOR][$unit_id] - $fleet_data[UBE_ARMOR][$unit_id]),
            'UNITS' => pretty_number($fleet_data_prev[UBE_COUNT][$unit_id]),
            'UNITS_LOST' => pretty_number($fleet_data_prev[UBE_COUNT][$unit_id] - $fleet_data[UBE_COUNT][$unit_id]),
            'UNITS_BOOM' => pretty_number($fleet_data[UBE_UNITS_BOOM][$unit_id]),
          );

          $fleet_template['.']['ship'][] = $ship_template;
        }

        $round_template[] = $fleet_template;
      }
    }

    return $round_template;
  }

// ------------------------------------------------------------------------------------------------
// Рендерит таблицу общего результата боя
  function sn_ube_report_table_render(&$array, $header)
  {
    global $lang;

    $result = array();
    if(!empty($array))
    {
      foreach($array as $unit_id => $unit_count)
      {
        if($unit_count)
        {
          $result[] = array(
            'NAME' => $lang['tech'][$unit_id],
            'LOSS' => pretty_number($unit_count),
          );
        }
      }
      if($header && count($result))
      {
        array_unshift($result, array('NAME' => $header));
      }
    }

    return $result;
  }

}
