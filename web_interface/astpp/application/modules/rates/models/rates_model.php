<?php

// ##############################################################################
// ASTPP - Open Source VoIP Billing Solution
//
// Copyright (C) 2016 iNextrix Technologies Pvt. Ltd.
// Samir Doshi <samir.doshi@inextrix.com>
// ASTPP Version 3.0 and above
// License https://www.gnu.org/licenses/agpl-3.0.html
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU Affero General Public License as
// published by the Free Software Foundation, either version 3 of the
// License, or (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU Affero General Public License for more details.
//
// You should have received a copy of the GNU Affero General Public License
// along with this program. If not, see <http://www.gnu.org/licenses/>.
// ##############################################################################
class rates_model extends CI_Model
{

    function __construct()
    {
        parent::__construct();
    }

    function get_termination_rates_list($flag, $start = 0, $limit = 0)
    {
        $this->db_model->build_search('termination_rates_list_search');
        /**
         * ***********
         */
        if ($flag) {
            $query = $this->db_model->select("*", "outbound_routes", "", "id", "ASC", $limit, $start);
        } else {
            $query = $this->db_model->countQuery("*", "outbound_routes", "");
        }
        return $query;
    }

    // Search-delete uses its own validated criteria, never shared grid-session state.
    function termination_delete_criteria($input)
    {
        if (!is_array($input)) throw new Exception('Invalid search form.');
        $string_fields = array('pattern', 'comment', 'precedence');
        $number_fields = array('connectcost', 'includedseconds', 'cost', 'init_inc', 'inc');
        $allowed = array_merge($string_fields, $number_fields, array('trunk_id', 'status', 'ajax_search', 'advance_search', 'action'));
        $csrf_name = $this->config->item('csrf_token_name');
        if ($csrf_name) $allowed[] = $csrf_name;
        $clauses = array(); $values = array();
        foreach ($input as $field => $entry) {
            if (!in_array($field, $allowed, true)) throw new Exception('Unrecognized search field. Reload this page.');
            if (in_array($field, array('ajax_search', 'advance_search', 'action', $csrf_name), true)) continue;
            if ($field === 'trunk_id' || $field === 'status') {
                if (!is_scalar($entry)) throw new Exception('Invalid selection.');
                $value = trim((string)$entry);
                if ($value === '') continue;
                if ($field === 'trunk_id' && (!ctype_digit($value) || (int)$value < 1)) throw new Exception('Select a valid trunk.');
                if ($field === 'status' && !in_array($value, array('0', '1'), true)) throw new Exception('Invalid status.');
                $clauses[] = '`'.$field.'` = ?'; $values[] = $value;
                continue;
            }
            if (!is_array($entry) || !array_key_exists($field, $entry) || !is_scalar($entry[$field])) throw new Exception('Invalid search field.');
            $value = trim((string)$entry[$field]);
            if ($value === '') continue;
            $is_number = in_array($field, $number_fields, true);
            $operator_key = $field.($is_number ? '-integer' : '-string');
            if (!isset($entry[$operator_key]) || !is_scalar($entry[$operator_key])) throw new Exception('Missing search operator.');
            $operator = (string)$entry[$operator_key];
            if ($is_number) {
                $operators = array('1'=>'=', '2'=>'<>', '3'=>'>', '4'=>'<', '5'=>'>=', '6'=>'<=');
                if (!isset($operators[$operator]) || !is_numeric($value) || !is_finite((float)$value)) throw new Exception('Invalid numeric search.');
                $clauses[] = '`'.$field.'` '.$operators[$operator].' ?'; $values[] = $value;
            } else {
                if (!in_array($operator, array('1','2','3','4','5','6'), true)) throw new Exception('Invalid text search.');
                $comparison = in_array($operator, array('1','5','6'), true) ? 'LIKE' : ($operator === '2' ? 'NOT LIKE' : ($operator === '3' ? '=' : '<>'));
                if ($operator === '1' || $operator === '2') $value = '%'.$value.'%';
                elseif ($operator === '3' || $operator === '4') { if ($field === 'pattern') $value = '^'.$value.'.*'; }
                elseif ($operator === '5') $value = ($field === 'pattern' ? '^' : '').$value.'%';
                elseif ($operator === '6') $value = '%'.$value.($field === 'pattern' ? '.*' : '');
                $clauses[] = '`'.$field.'` '.$comparison.' ?'; $values[] = $value;
            }
        }
        if (!$clauses) throw new Exception('Choose a search filter first. Unfiltered deletion is blocked.');
        return array('sql'=>implode(' AND ', $clauses), 'values'=>$values);
    }

    // Bounded batches keep 100,000+ rate previews out of session storage/memory.
    function termination_delete_snapshot($criteria, $lock = false)
    {
        $digest = hash_init('sha256'); $count = 0; $last = 0;
        do {
            $query = $this->db->query('SELECT * FROM outbound_routes WHERE ('.$criteria['sql'].') AND id > ? ORDER BY id ASC LIMIT 1000'.($lock ? ' FOR UPDATE' : ''), array_merge($criteria['values'], array($last)));
            if (!$query) throw new Exception('Unable to read the selected rates. No deletion completed.');
            $rows = $query->result_array(); $query->free_result();
            foreach ($rows as $row) {
                if (!isset($row['id']) || !ctype_digit((string)$row['id']) || (int)$row['id'] <= $last) throw new Exception('Invalid rate selection.');
                $encoded = json_encode($row);
                if ($encoded === false) throw new Exception('Unable to prepare this rate selection.');
                hash_update($digest, $encoded."\n"); $last = (int)$row['id']; $count++;
            }
        } while (count($rows) === 1000);
        return array('count'=>$count, 'digest'=>hash_final($digest));
    }

    function termination_delete_confirmed($preview)
    {
        $engine = $this->db->query("SHOW TABLE STATUS WHERE Name = 'outbound_routes'");
        if (!$engine) throw new Exception('Unable to verify transactional deletion support.');
        $metadata = $engine->row_array(); $engine->free_result();
        if (!isset($metadata['Engine']) || strcasecmp($metadata['Engine'], 'InnoDB') !== 0) throw new Exception('Safe deletion requires the outbound_routes table to use InnoDB. No rates deleted.');
        $old_debug = $this->db->db_debug;
        $this->db->db_debug = false; // Query failures must reach our rollback handler.
        $started = false;
        try {
            if (!$this->db->query('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE')) throw new Exception('Unable to prepare safe deletion.');
            if (!$this->db->query('START TRANSACTION')) throw new Exception('Unable to start safe deletion.');
            $started = true;
            $current = $this->termination_delete_snapshot($preview['criteria'], true);
            if ($current['count'] !== $preview['count'] || !hash_equals($preview['digest'], $current['digest'])) throw new Exception('The selected rates changed. Nothing was deleted. Search and confirm again.');
            if ($current['count'] < 1) throw new Exception('No matching rates. Nothing was deleted.');
            if (!$this->db->query('DELETE FROM outbound_routes WHERE '.$preview['criteria']['sql'], $preview['criteria']['values'])) throw new Exception('Deletion failed.');
            if ((int)$this->db->affected_rows() !== $preview['count']) throw new Exception('Selection changed during deletion. Nothing was deleted.');
            if (!$this->db->query('COMMIT')) throw new Exception('Unable to confirm deletion. Check database state before retrying.');
            $started = false;
            return $current['count'];
        } catch (Exception $error) {
            if ($started) $this->db->query('ROLLBACK');
            throw $error;
        } finally {
            $this->db->db_debug = $old_debug;
        }
    }

    function get_termination_rate($flag, $start = 0, $limit = 0, $export = true)
    {
        $this->db_model->build_search('termination_rates_list_search');
        $this->db->from('outbound_routes');
        if ($flag) {
            if ($export)
                $this->db->limit($limit, $start);
            $result = $this->db->get();
        } else {
            $result = $this->db->count_all_results();
        }
        return $result;
    }

    // Keep search-delete within the same base scope as the origination grid.
    private function origination_delete_scope()
    {
        $scope = array('accountid'=>'0');
        $type = (string)$this->session->userdata('logintype');
        if ($type === '1' || $type === '5') {
            $account = $this->session->userdata('accountinfo');
            if (!isset($account['id']) || !ctype_digit((string)$account['id']) || (int)$account['id'] < 1) throw new Exception('Unable to verify reseller scope.');
            $scope['reseller_id'] = (string)(int)$account['id'];
        }
        return $scope;
    }

    // Search-delete uses its own validated criteria, never shared grid-session state.
    function origination_delete_criteria($input)
    {
        if (!is_array($input)) throw new Exception('Invalid search form.');
        $string_fields = array('pattern', 'comment');
        $number_fields = array('connectcost', 'includedseconds', 'cost', 'init_inc', 'inc');
        $allowed = array_merge($string_fields, $number_fields, array('country_id', 'pricelist_id', 'reseller_id', 'status', 'ajax_search', 'advance_search', 'action'));
        $csrf_name = $this->config->item('csrf_token_name');
        if ($csrf_name) $allowed[] = $csrf_name;
        $clauses = array(); $values = array();
        foreach ($input as $field => $entry) {
            if (!in_array($field, $allowed, true)) throw new Exception('Unrecognized search field. Reload this page.');
            if (in_array($field, array('ajax_search', 'advance_search', 'action', $csrf_name), true)) continue;
            if (in_array($field, array('country_id', 'pricelist_id', 'reseller_id', 'status'), true)) {
                if (!is_scalar($entry)) throw new Exception('Invalid selection.');
                $value = trim((string)$entry);
                if ($value === '') continue;
                if ($field !== 'status' && (!ctype_digit($value) || ($field !== 'reseller_id' && (int)$value < 1))) throw new Exception('Select a valid country, rate group or reseller.');
                if ($field === 'status' && !in_array($value, array('0', '1'), true)) throw new Exception('Invalid status.');
                $clauses[] = '`'.$field.'` = ?'; $values[] = $value;
                continue;
            }
            if (!is_array($entry) || !array_key_exists($field, $entry) || !is_scalar($entry[$field])) throw new Exception('Invalid search field.');
            $value = trim((string)$entry[$field]);
            if ($value === '') continue;
            $is_number = in_array($field, $number_fields, true);
            $operator_key = $field.($is_number ? '-integer' : '-string');
            if (!isset($entry[$operator_key]) || !is_scalar($entry[$operator_key])) throw new Exception('Missing search operator.');
            $operator = (string)$entry[$operator_key];
            if ($is_number) {
                $operators = array('1'=>'=', '2'=>'<>', '3'=>'>', '4'=>'<', '5'=>'>=', '6'=>'<=');
                if (!isset($operators[$operator]) || !is_numeric($value) || !is_finite((float)$value)) throw new Exception('Invalid numeric search.');
                $clauses[] = '`'.$field.'` '.$operators[$operator].' ?'; $values[] = $value;
            } else {
                if (!in_array($operator, array('1','2','3','4','5','6'), true)) throw new Exception('Invalid text search.');
                $comparison = in_array($operator, array('1','5','6'), true) ? 'LIKE' : ($operator === '2' ? 'NOT LIKE' : ($operator === '3' ? '=' : '<>'));
                if ($operator === '1' || $operator === '2') $value = '%'.$value.'%';
                elseif ($operator === '3' || $operator === '4') { if ($field === 'pattern') $value = '^'.$value.'.*'; }
                elseif ($operator === '5') $value = ($field === 'pattern' ? '^' : '').$value.'%';
                elseif ($operator === '6') $value = '%'.$value.($field === 'pattern' ? '.*' : '');
                $clauses[] = '`'.$field.'` '.$comparison.' ?'; $values[] = $value;
            }
        }
        if (!$clauses) throw new Exception('Choose a search filter first. Unfiltered deletion is blocked.');
        $scope = $this->origination_delete_scope();
        foreach ($scope as $field => $value) { $clauses[] = '`'.$field.'` = ?'; $values[] = $value; }
        return array('sql'=>implode(' AND ', $clauses), 'values'=>$values, 'scope'=>$scope);
    }

    // Bounded batches keep 100,000+ rate previews out of session storage/memory.
    function origination_delete_snapshot($criteria, $lock = false)
    {
        $digest = hash_init('sha256'); $count = 0; $last = 0;
        do {
            $query = $this->db->query('SELECT * FROM routes WHERE ('.$criteria['sql'].') AND id > ? ORDER BY id ASC LIMIT 1000'.($lock ? ' FOR UPDATE' : ''), array_merge($criteria['values'], array($last)));
            if (!$query) throw new Exception('Unable to read the selected rates. No deletion completed.');
            $rows = $query->result_array(); $query->free_result();
            foreach ($rows as $row) {
                if (!isset($row['id']) || !ctype_digit((string)$row['id']) || (int)$row['id'] <= $last) throw new Exception('Invalid rate selection.');
                $encoded = json_encode($row);
                if ($encoded === false) throw new Exception('Unable to prepare this rate selection.');
                hash_update($digest, $encoded."\n"); $last = (int)$row['id']; $count++;
            }
        } while (count($rows) === 1000);
        return array('count'=>$count, 'digest'=>hash_final($digest));
    }

    function origination_delete_confirmed($preview)
    {
        if (!isset($preview['criteria']['scope']) || $preview['criteria']['scope'] !== $this->origination_delete_scope()) throw new Exception('Account scope changed. Search and confirm again.');
        $engine = $this->db->query("SHOW TABLE STATUS WHERE Name = 'routes'");
        if (!$engine) throw new Exception('Unable to verify transactional deletion support.');
        $metadata = $engine->row_array(); $engine->free_result();
        if (!isset($metadata['Engine']) || strcasecmp($metadata['Engine'], 'InnoDB') !== 0) throw new Exception('Safe deletion requires the routes table to use InnoDB. No rates deleted.');
        $old_debug = $this->db->db_debug;
        $this->db->db_debug = false; // Query failures must reach our rollback handler.
        $started = false;
        try {
            if (!$this->db->query('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE')) throw new Exception('Unable to prepare safe deletion.');
            if (!$this->db->query('START TRANSACTION')) throw new Exception('Unable to start safe deletion.');
            $started = true;
            $current = $this->origination_delete_snapshot($preview['criteria'], true);
            if ($current['count'] !== $preview['count'] || !hash_equals($preview['digest'], $current['digest'])) throw new Exception('The selected rates changed. Nothing was deleted. Search and confirm again.');
            if ($current['count'] < 1) throw new Exception('No matching rates. Nothing was deleted.');
            if (!$this->db->query('DELETE FROM routes WHERE '.$preview['criteria']['sql'], $preview['criteria']['values'])) throw new Exception('Deletion failed.');
            if ((int)$this->db->affected_rows() !== $preview['count']) throw new Exception('Selection changed during deletion. Nothing was deleted.');
            if (!$this->db->query('COMMIT')) throw new Exception('Unable to confirm deletion. Check database state before retrying.');
            $started = false;
            return $current['count'];
        } catch (Exception $error) {
            if ($started) $this->db->query('ROLLBACK');
            throw $error;
        } finally {
            $this->db->db_debug = $old_debug;
        }
    }

    function get_origination_rate($flag, $start = 0, $limit = 0, $export = true)
    {
        $this->db_model->build_search('origination_rate_list_search');
        if ($this->session->userdata('logintype') == 1 || $this->session->userdata('logintype') == 5) {
            $account_data = $this->session->userdata("accountinfo");
            $reseller = $account_data['id'];
            $where = array(
                "reseller_id" => $reseller
            );
        } else {
            $where = array();
        }
        $where['accountid'] = '0';
        $this->db_model->build_search('origination_rate_list_search');
        if ($flag) {
            if ($export)
                $this->db->limit($limit, $start);
            $result = $this->db_model->select("*", "routes", $where, "id", "asc", $limit, $start);
        } else {
            $result = $this->db_model->countQuery("*", "routes", $where);
        }
        // echo "<pre>";print_r($result->result());exit;
        return $result;
    }

    function get_origination_rate_for_user($flag, $start = 0, $limit = 0, $export = true)
    {
        $this->db_model->build_search('origination_rate_list_search');

        $account_data = $this->session->userdata("accountinfo");

        $where = array(
            "pricelist_id" => $account_data["pricelist_id"]
        );

        $this->db_model->build_search('origination_rate_list_search');
        if ($flag) {
            if ($export)
                $this->db->limit($limit, $start);
            $result = $this->db_model->select("*", "routes", $where, "id", "ASC", $limit, $start);
        } else {
            $result = $this->db_model->countQuery("*", "routes", $where);
        }
        return $result;
    }

    // ==============================================
    function get_origination_rate_list($flag, $start = 0, $limit = 0)
    {
        $where = array();
        if ($this->session->userdata('logintype') == 1 || $this->session->userdata('logintype') == 5) {
            $account_data = $this->session->userdata("accountinfo");
            $where = array(
                "reseller_id" => $account_data['id']
            );
        }
        $where['accountid'] = '0';
        $this->db_model->build_search('origination_rate_list_search');
        if ($flag) {
            $query = $this->db_model->select("*", "routes", $where, "id,reseller_id", "DESC", $limit, $start);
        } else {
            $query = $this->db_model->countQuery("*", "routes", $where);
        }
        return $query;
    }

    function getunblocked_pattern_list($accountid, $flag, $start = 0, $limit = 0)
    {
        $this->db_model->build_search('origination_rate_list_search');
        if ($this->session->userdata('logintype') == 1 || $this->session->userdata('logintype') == 5) {
            $account_data = $this->session->userdata("accountinfo");
            $reseller = $account_data['id'];
            $where = array(
                "reseller_id" => $reseller,
                "status" => "0"
            );
        } else {
            $where = array(
                "status" => "0",
                'reseller_id' => '0'
            );
        }
        $where1 = '(pattern NOT IN (select blocked_patterns from block_patterns where accountid = "' . $accountid . '"))';
        $this->db->where($where1);
        if ($flag) {
            $query = $this->db_model->select("*", "routes", $where, "id", "ASC", $limit, $start);
            // echo "<pre>"; print_r($query); exit;
        } else {
            $query = $this->db_model->countQuery("*", "routes", $where);
        }
        return $query;
    }

    function getunblocked_package_pattern($accountid, $flag, $start = 0, $limit = 0)
    {
        $this->db_model->build_search('origination_rate_list_search');
        if ($this->session->userdata('logintype') == 1 || $this->session->userdata('logintype') == 5) {
            $account_data = $this->session->userdata("accountinfo");
            $reseller = $account_data['id'];
            $where = array(
                "reseller_id" => $reseller,
                "status" => "0"
            );
        } else {
            $where = array(
                "status" => "0",
                'reseller_id' => '0'
            );
        }
        $where1 = '(pattern NOT IN (select DISTINCT patterns from package_patterns where package_id = "' . $accountid . '"))';
        $this->db->where($where1);
        if ($flag) {
            $query = $this->db_model->select("*", "routes", $where, "id", "ASC", $limit, $start);
        } else {
            $query = $this->db_model->countQuery("*", "routes", $where);
        }
        return $query;
    }

    function get_origination_rate_list_for_user($flag, $start = 0, $limit = 0)
    {
        $this->db_model->build_search('user_rates_list_search');

        $account_data = $this->session->userdata("accountinfo");
        $where = array(
            "pricelist_id" => $account_data["pricelist_id"],
            "status" => '0'
        );

        $this->db_model->build_search('origination_rate_list_search');
        if ($flag) {
            $query = $this->db_model->select("*", "routes", $where, "id", "ASC", $limit, $start);
        } else {
            $query = $this->db_model->countQuery("*", "routes", $where);
        }
        return $query;
    }

    function add_termination_rate($add_array)
    {
        unset($add_array["action"]);
        if ($this->session->userdata('logintype') == 1 || $this->session->userdata('logintype') == 5) {
            $account_data = $this->session->userdata("accountinfo");
            $reseller = $account_data['id'];
            $add_array['reseller_id'] = $reseller;
        }
        $add_array['pattern'] = "^" . $add_array['pattern'] . ".*";
        $add_array['prepend'] = $add_array['prepend'];
        /*
         * ASTPP 3.0
         * add creation date.
         */
        $add_array['creation_date'] = gmdate('Y-m-d H:i:s');
        $add_array['last_modified_date'] = gmdate('Y-m-d H:i:s');
        /**
         * ****************************************************
         */
        $this->insert_if_not_exitst($add_array, "outbound_routes");
        // $this->db->insert("outbound_routes", $add_array);
        return true;
    }

    function edit_termination_rate($data, $id)
    {
        unset($data["action"]);
        unset($data["id"]);
        /*
         * ASTPP 3.0
         * Edit time last modified date
         */
        $data['last_modified_date'] = gmdate('Y-m-d H:i:s');
        /**
         * ************************************************
         */
        $data['pattern'] = "^" . $data['pattern'] . ".*";
        $this->db->where("id", $id);
        $this->db->update("outbound_routes", $data);
    }

    function remove_termination_rate($id)
    {
        $this->db->where("id", $id);
        $this->db->delete("outbound_routes");
        return true;
    }

    function add_origination_rate($add_array)
    {
        unset($add_array["action"]);

        $add_array['creation_date'] = gmdate('Y-m-d H:i:s');
        $add_array['last_modified_date'] = gmdate('Y-m-d H:i:s');

        if ($this->session->userdata('logintype') == 1 || $this->session->userdata('logintype') == 5) {
            $account_data = $this->session->userdata("accountinfo");
            $reseller = $account_data['id'];
            $add_array['reseller_id'] = $reseller;
        }
        $add_array['pattern'] = "^" . $add_array['pattern'] . ".*";

        $this->insert_if_not_exitst($add_array, "routes");

        return true;
    }

    function edit_origination_rate($data, $id)
    {
        unset($data["action"]);
        unset($data["id"]);
        /*
         * ASTPP 3.0
         * Edit tile last modified date update
         */
        $data['last_modified_date'] = gmdate('Y-m-d H:i:s');
        /**
         * *****************************************************
         */
        $data['pattern'] = "^" . $data['pattern'] . ".*";
        $this->db->where("id", $id);
        $this->db->update("routes", $data);
    }

    function remove_origination_rate($id)
    {
        $this->db->where("id", $id);
        $this->db->delete("routes");
        return true;
    }

    function get_trunk_name($field_value)
    {
        $this->db->where("name", $field_value);
        $query = $this->db->get('trunks');
        $data = $query->result();
        if ($query->num_rows() > 0) {
            return $data[0]->id;
        } else {
            return '';
        }
    }

    function bulk_insert_termination_rate($field_values, $count)
    {
        $keys = array_keys($field_values);
        $arr_key = array_keys($field_values[$keys[0]]);
        $str = null;
        $i = 0;
        $update_fields[] = "comment=VALUES(comment),
		connectcost=VALUES(connectcost),
		includedseconds=VALUES(includedseconds),
		cost=VALUES(cost),
		init_inc=VALUES(init_inc),
		inc=VALUES(inc),
		precedence=VALUES(precedence),
		strip=VALUES(strip),
		prepend=VALUES(prepend),
		last_modified_date=VALUES(last_modified_date),
		status=VALUES(status)";
        $insert_string = "INSERT INTO outbound_routes (" . implode(', ', $arr_key) . ") VALUES ";
        $update_string = " ON DUPLICATE KEY UPDATE " . implode(', ', $update_fields);
        $k = 0;
        $j = ceil($count / 500);
        $current_value = 0;
        for ($i = 0; $i < $j; $i ++) {
            $str = null;
            for ($k = 0; $k <= 500; $k ++) {
                $current_value = ($i * 500) + $k;
                if (isset($field_values[$current_value])) {
                    $str .= "('" . implode("','", $field_values[$current_value]) . "'),";
                }
            }
            $str = rtrim($str, ",");
            $this->db->query($insert_string . $str . $update_string, false);
        }
        return true;
    }

    function bulk_insert_origination_rate($field_values, $count)
    {
        $temp_arr = array();
        $keys = array_keys($field_values);
        $arr_key = array_keys($field_values[$keys[0]]);

        $str = null;
        $i = 0;
        $update_fields[] = "comment=VALUES(comment),
		connectcost=VALUES(connectcost),
		includedseconds=VALUES(includedseconds),
		cost=VALUES(cost),
		init_inc=VALUES(init_inc),
		inc=VALUES(inc),
		last_modified_date=VALUES(last_modified_date),
		status=VALUES(status),
		country_id=VALUES(country_id),
		call_type=VALUES(call_type)";
        $insert_string = "INSERT INTO routes (" . implode(', ', $arr_key) . ") VALUES ";
        $update_string = " ON DUPLICATE KEY UPDATE " . implode(', ', $update_fields);
        $k = 0;
        $j = ceil($count / 500);
        $current_value = 0;
        for ($i = 0; $i < $j; $i ++) {
            $str = null;
            for ($k = 0; $k <= 500; $k ++) {
                $current_value = ($i * 500) + $k;
                if (isset($field_values[$current_value])) {
                    $str .= "('" . implode("','", $field_values[$current_value]) . "'),";
                }
            }
            $str = rtrim($str, ",");
            $this->db->query($insert_string . $str . $update_string, false);
        }
        return true;
    }

    function termination_rate_batch_update($update_array)
    {
        $this->db_model->build_search('termination_rates_list_search');
        if ($this->session->userdata('logintype') == 1 || $this->session->userdata('logintype') == 5) {
            $account_data = $this->session->userdata("accountinfo");
            $this->db->where("reseller_id", $account_data['id']);
        }

        $updateflg = $this->db_model->build_batch_update_array($update_array);
        if ($updateflg)
            return $this->db->update("outbound_routes");
        else
            return false;
    }

    function origination_rate_batch_update($update_array)
    {
        $this->db_model->build_search('origination_rate_list_search');
        if ($this->session->userdata('logintype') == 1 || $this->session->userdata('logintype') == 5) {
            $account_data = $this->session->userdata("accountinfo");
            $this->db->where("reseller_id", $account_data['id']);
        }
        $updateflg = $this->db_model->build_batch_update_array($update_array);
        if ($updateflg)
            return $this->db->update("routes");
        else
            return false;
    }

    function getreseller_rates_list($flag, $start = 0, $limit = 0, $export = false)
    {
        $account_data = $this->session->userdata("accountinfo");
        $price_list_id = $this->common->get_field_name('pricelist_id', 'accounts', $account_data['id']);
        $this->db_model->build_search('resellerrates_list_search');
        /*
         * $where = array (
         * "status" => "0",
         * "pricelist_id" => $price_list_id,
         * "accountid" => "0"
         * );
         */
        $where = '(pricelist_id="' . $price_list_id . '" OR accountid="' . $account_data["id"] . '") and status=0';
        if ($flag) {
            $query = $this->db_model->select("*", "routes", $where, "id", "ASC", $limit, $start);
        } else {
            $query = $this->db_model->countQuery("*", "routes", $where);
        }
        return $query;
    }

    /**
     * ***********
     * ASTPP 3.0
     * Rate insert
     * ************
     */
    /**
     *
     * @param string $table_name
     */
    function insert_if_not_exitst($add_array, $table_name)
    {
        $db = get_instance()->db->conn_id;
        $insert_str = "Insert into $table_name (";
        $insert_key = "";
        $insert_value = "";
        $update_str = "";
        foreach ($add_array as $key => $value) {
            if ($key != 'id') {
                $insert_key .= $key . ",";
                // $insert_value .= "'" . mysqli_real_escape_string ( $db, $value ) . "',";
                // $update_str .= $key . " = '" . mysqli_real_escape_string ( $db, $value ) . "',";
                $insert_value .= "'" . $value . "',";
                $update_str .= $key . " = '" . $value . "',";
            }
        }
        $insert_key = rtrim($insert_key, ",");
        $insert_value = rtrim($insert_value, ",");
        $update_str = rtrim($update_str, ",");
        $insert_str .= $insert_key . ") values" . "(" . $insert_value . ")  ON DUPLICATE KEY UPDATE $update_str";

        $this->db->query($insert_str);
    }

    function get_ratedeck_details($pattern)
    {
        $this->db->where($pattern);
        $query = $this->db_model->select("*", "ratedeck", "", "", "", "", "");
        return $query;
    }
}
