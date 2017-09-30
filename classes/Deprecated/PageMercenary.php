<?php
/**
 * Created by Gorlum 30.09.2017 8:28
 */

namespace Deprecated;

use \Exception;
use \classSupernova;
use \DBStaticUnit;

class PageMercenary {

  /**
   * @var \classConfig $config
   */
  protected $config;
  /**
   * @var \classLocale $lang
   */
  protected $lang;

  /**
   * @var float[] $sn_powerup_buy_discounts
   */
  protected $sn_powerup_buy_discounts;

  /**
   * What we purchasing - Plans or Mercenaries?
   * @var int $mode
   */
  protected $mode = UNIT_MERCENARIES; // Or UNIT_PLANS

  /**
   * If purchased units are permanent?
   * @var bool $isUnitsPermanent
   */
  protected $isUnitsPermanent = false;

  /**
   * Multiplier for Alliance's purchases
   *
   * @var float $cost_alliance_multiplyer
   */
  protected $cost_alliance_multiplyer;

  public function __construct() {
    global $lang, $sn_powerup_buy_discounts;

    lng_include('mrc_mercenary');
    lng_include('infos');

    $this->config = classSupernova::$config;
    $this->lang = $lang;
    $this->sn_powerup_buy_discounts = $sn_powerup_buy_discounts;
  }

  protected function loadParams() {
    // Getting page mode
    $this->mode = sys_get_param_int('mode', UNIT_MERCENARIES);
    $this->mode = in_array($this->mode, array(UNIT_MERCENARIES, UNIT_PLANS)) ? $this->mode : UNIT_MERCENARIES;

    $this->cost_alliance_multiplyer = min(1, SN_IN_ALLY === true && $this->mode == UNIT_PLANS ? $this->config->ali_bonus_members : 1);
  }


  /**
   * @param array $user
   */
  public function mrc_mercenary_render($user) {
    $this->loadParams();

    $operation_result = [];

    $this->isUnitsPermanent = $this->mode == UNIT_PLANS || !$this->config->empire_mercenary_temporary;

    if ($mercenary_id = sys_get_param_int('mercenary_id')) {
      try {
        $this->mrc_mercenary_hire($user, $mercenary_id);
      } catch (Exception $e) {
        sn_db_transaction_rollback();
        $operation_result = array(
          'STATUS'  => in_array($e->getCode(), array(ERR_NONE, ERR_WARNING, ERR_ERROR)) ? $e->getCode() : ERR_ERROR,
          'MESSAGE' => $this->lang[$e->getMessage()],
        );
      }

      return $operation_result;
    }


    $template = gettemplate('mrc_mercenary_hire', true);

    if (!empty($operation_result)) {
      $template->assign_block_vars('result', $operation_result);
    }

    $this->fillDiscountTable($template);

    $user_dark_matter = mrc_get_level($user, '', RES_DARK_MATTER);
//    $cost_alliance_multiplyer = (SN_IN_ALLY === true && $this->mode == UNIT_PLANS ? $this->config->ali_bonus_members : 1);
//    $cost_alliance_multiplyer = $cost_alliance_multiplyer >= 1 ? $cost_alliance_multiplyer : 1;
    foreach (sn_get_groups($this->mode == UNIT_PLANS ? 'plans' : 'mercenaries') as $mercenary_id) {
      $mercenary = get_unit_param($mercenary_id);
      $mercenary_bonus = $mercenary['bonus'];
      $mercenary_bonus = $mercenary_bonus >= 0 ? "+{$mercenary_bonus}" : "{$mercenary_bonus}";
      switch ($mercenary['bonus_type']) {
        case BONUS_PERCENT:
          $mercenary_bonus = "{$mercenary_bonus}% ";
        break;

        case BONUS_ABILITY:
          $mercenary_bonus = '';
        break;

        case BONUS_ADD:
        default:
        break;
      }

      $mercenary_level = mrc_get_level($user, null, $mercenary_id, false, true);
      $mercenary_level_bonus = max(0, mrc_get_level($user, null, $mercenary_id) - $mercenary_level);
      $total_cost_old = 0;
      if ($this->isUnitsPermanent) {
        $total_cost_old = eco_get_total_cost($mercenary_id, $mercenary_level);
        $total_cost_old = $total_cost_old[BUILD_CREATE][RES_DARK_MATTER] * $this->cost_alliance_multiplyer;
      }
      $total_cost = eco_get_total_cost($mercenary_id, $mercenary_level + 1);
      $total_cost[BUILD_CREATE][RES_DARK_MATTER] *= $this->cost_alliance_multiplyer;
      $mercenary_unit = classSupernova::db_get_unit_by_location($user['id'], LOC_USER, $user['id'], $mercenary_id);
      $mercenary_time_start = strtotime($mercenary_unit['unit_time_start']);
      $mercenary_time_finish = strtotime($mercenary_unit['unit_time_finish']);
      $template->assign_block_vars('officer', array(
        'ID'                => $mercenary_id,
        'NAME'              => $this->lang['tech'][$mercenary_id],
        'DESCRIPTION'       => $this->lang['info'][$mercenary_id]['description'],
        'EFFECT'            => $this->lang['info'][$mercenary_id]['effect'],
        'COST'              => $total_cost[BUILD_CREATE][RES_DARK_MATTER] - $total_cost_old,
        'COST_TEXT'         => pretty_number($total_cost[BUILD_CREATE][RES_DARK_MATTER] - $total_cost_old, 0, $user_dark_matter),
        'LEVEL'             => $mercenary_level,
        'LEVEL_BONUS'       => $mercenary_level_bonus,
        'LEVEL_MAX'         => $mercenary['max'],
        'BONUS'             => $mercenary_bonus,
        'BONUS_TYPE'        => $mercenary['bonus_type'],
        'HIRE_END'          => $mercenary_time_finish && $mercenary_time_finish >= SN_TIME_NOW ? date(FMT_DATE_TIME, $mercenary_time_finish) : '',
        'HIRE_LEFT_PERCENT' => $mercenary_time_finish && $mercenary_time_finish >= SN_TIME_NOW
          ? round(($mercenary_time_finish - SN_TIME_NOW) / ($mercenary_time_finish - $mercenary_time_start) * 100, 1)
          : 0,
        'CAN_BUY'           => $this->mrc_officer_accessible($user, $mercenary_id),
      ));

      $upgrade_cost = 1;
      for ($i = $this->config->empire_mercenary_temporary ? 1 : $mercenary_level + 1; $mercenary['max'] ? ($i <= $mercenary['max']) : $upgrade_cost <= $user_dark_matter; $i++) {
        $total_cost = eco_get_total_cost($mercenary_id, $i);
        $total_cost[BUILD_CREATE][RES_DARK_MATTER] *= $this->cost_alliance_multiplyer;
        $upgrade_cost = $total_cost[BUILD_CREATE][RES_DARK_MATTER] - $total_cost_old;
        $template->assign_block_vars('officer.level', array(
          'VALUE' => $i,
          'PRICE' => $upgrade_cost,
        ));
      }
    }

    $template->assign_vars(array(
      'PAGE_HEADER'                => $this->lang['tech'][$this->mode],
      'MODE'                       => $this->mode,
      'IS_PERMANENT'               => intval($this->isUnitsPermanent),
      'EMPIRE_MERCENARY_TEMPORARY' => $this->config->empire_mercenary_temporary,
      'DARK_MATTER'                => $user_dark_matter,
    ));

    display($template, $this->lang['tech'][$this->mode]);
  }

  protected function mrc_mercenary_hire($user, $mercenary_id) {
      if (!in_array($mercenary_id, sn_get_groups($this->mode == UNIT_PLANS ? 'plans' : 'mercenaries'))) {
        throw new Exception('mrc_msg_error_wrong_mercenary', ERR_ERROR);
      }

      if (!$this->mrc_officer_accessible($user, $mercenary_id)) {
        throw new Exception('mrc_msg_error_requirements', ERR_ERROR);
      }

      $mercenary_level = sys_get_param_int('mercenary_level');
      if ($mercenary_level < 0 || $mercenary_level > get_unit_param($mercenary_id, P_MAX_STACK)) {
        throw new Exception('mrc_msg_error_wrong_level', ERR_ERROR);
      }

      $mercenary_period = sys_get_param_int('mercenary_period');
      if ($mercenary_level && !array_key_exists($mercenary_period, $this->sn_powerup_buy_discounts)) {
        throw new Exception('mrc_msg_error_wrong_period', ERR_ERROR);
      }

      sn_db_transaction_start();

      $mercenary_level_old = mrc_get_level($user, [], $mercenary_id, true, true);
      if ($this->config->empire_mercenary_temporary && $mercenary_level_old && $mercenary_level) {
        throw new Exception('mrc_msg_error_already_hired', ERR_ERROR); // Can't hire already hired temp mercenary - dismiss first
      } elseif ($this->config->empire_mercenary_temporary && !$mercenary_level_old && !$mercenary_level) {
        throw new Exception('', ERR_NONE); // Can't dismiss (!$mercenary_level) not hired (!$mercenary_level_old) temp mercenary. But no error
      }

      if ($mercenary_level) {
        $darkmater_cost = eco_get_total_cost($mercenary_id, $mercenary_level);
        if (!$this->config->empire_mercenary_temporary && $mercenary_level_old) {
          $darkmater_cost_old = eco_get_total_cost($mercenary_id, $mercenary_level_old);
          $darkmater_cost[BUILD_CREATE][RES_DARK_MATTER] -= $darkmater_cost_old[BUILD_CREATE][RES_DARK_MATTER];
        }
        $darkmater_cost = ceil($darkmater_cost[BUILD_CREATE][RES_DARK_MATTER] * $mercenary_period * $this->sn_powerup_buy_discounts[$mercenary_period] / $this->config->empire_mercenary_base_period);
      } else {
        $darkmater_cost = 0;
      }
      $darkmater_cost *= $this->cost_alliance_multiplyer;

      if (mrc_get_level($user, null, RES_DARK_MATTER) < $darkmater_cost) {
        throw new Exception('mrc_msg_error_no_resource', ERR_ERROR);
      }

      if (($darkmater_cost && $mercenary_level) || !$this->isUnitsPermanent) {
        $unit_row = DBStaticUnit::db_unit_by_location($user['id'], LOC_USER, $user['id'], $mercenary_id);
        if (is_array($unit_row) && ($dismiss_left_days = floor((strtotime($unit_row['unit_time_finish']) - SN_TIME_NOW) / PERIOD_DAY))) {
          $dismiss_full_cost = eco_get_total_cost($mercenary_id, $unit_row['unit_level']);
          $dismiss_full_cost = $dismiss_full_cost[BUILD_CREATE][RES_DARK_MATTER];

          $dismiss_full_days = round((strtotime($unit_row['unit_time_finish']) - strtotime($unit_row['unit_time_start'])) / PERIOD_DAY);
          /*
                  print(sprintf($this->lang['mrc_mercenary_dismissed_log'], $this->lang['tech'][$mercenary_id], $mercenary_id, $dismiss_full_cost, $dismiss_full_days,
                    $unit_row['unit_time_start'], $unit_row['unit_time_finish'], $dismiss_left_days, floor($dismiss_full_cost * $dismiss_left_days / $dismiss_full_days)
                    ));
          */
          rpg_points_change($user['id'], RPG_MERCENARY_DISMISSED, 0,
            sprintf($this->lang['mrc_mercenary_dismissed_log'], $this->lang['tech'][$mercenary_id], $mercenary_id, $dismiss_full_cost, $dismiss_full_days,
              $unit_row['unit_time_start'], $unit_row['unit_time_finish'], $dismiss_left_days, floor($dismiss_full_cost * $dismiss_left_days / $dismiss_full_days)
            ));
        }
        DBStaticUnit::db_unit_list_delete($user['id'], LOC_USER, $user['id'], $mercenary_id);
      }

      if ($darkmater_cost && $mercenary_level) {
        DBStaticUnit::db_unit_set_insert(
          "unit_player_id = {$user['id']},
        unit_location_type = " . LOC_USER . ",
        unit_location_id = {$user['id']},
        unit_type = {$this->mode},
        unit_snid = {$mercenary_id},
        unit_level = {$mercenary_level},
        unit_time_start = " . (!$this->isUnitsPermanent ? 'FROM_UNIXTIME(' . SN_TIME_NOW . ')' : 'null') . ",
        unit_time_finish = " . (!$this->isUnitsPermanent ? 'FROM_UNIXTIME(' . (SN_TIME_NOW + $mercenary_period) . ')' : 'null')
        );

        rpg_points_change($user['id'], $this->mode == UNIT_PLANS ? RPG_PLANS : RPG_MERCENARY, -($darkmater_cost),
          sprintf($this->lang[$this->mode == UNIT_PLANS ? 'mrc_plan_bought_log' : 'mrc_mercenary_hired_log'], $this->lang['tech'][$mercenary_id], $mercenary_id, $darkmater_cost, round($mercenary_period / PERIOD_DAY)));
      }
      sn_db_transaction_commit();
      sys_redirect($_SERVER['REQUEST_URI']);
  }

  protected function mrc_officer_accessible(&$user, $mercenary_id) {
    $mercenary_info = get_unit_param($mercenary_id);
    if ($this->config->empire_mercenary_temporary || $mercenary_info[P_UNIT_TYPE] == UNIT_PLANS) {
      return true;
    }

    if (isset($mercenary_info[P_REQUIRE])) {
      foreach ($mercenary_info[P_REQUIRE] as $unit_id => $unit_level) {
        if (mrc_get_level($user, null, $unit_id) < $unit_level) {
          return false;
        }
      }
    }

    return true;
  }

  /**
   * @param \template $template
   */
  protected function fillDiscountTable($template) {
    foreach ($this->sn_powerup_buy_discounts as $hire_period => $hire_discount) {
      $template->assign_block_vars('period', array(
        'LENGTH'   => $hire_period,
        'TEXT'     => $this->lang['mrc_period_list'][$hire_period],
        'DISCOUNT' => $hire_period / $this->config->empire_mercenary_base_period * $hire_discount,
        'SELECTED' => $hire_period == $this->config->empire_mercenary_base_period,
      ));
    }
  }

}