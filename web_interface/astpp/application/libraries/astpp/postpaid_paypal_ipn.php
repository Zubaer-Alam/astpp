<?php

if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

/**
 * PayPal IPN handler for postpaid dashboard settle/top-up.
 * Use login/postpaid_settle_paypal_ipn so no customer-session redirect runs.
 */
class postpaid_paypal_ipn
{

    protected $CI;

    public function __construct()
    {
        $this->CI = & get_instance();
        $this->CI->load->library('astpp/payment');
    }

    public function process()
    {
        if (empty($_POST)) {
            echo '';
            return;
        }
        $this->_log('postpaid_ipn: received txn_id=' . (isset($_POST['txn_id']) ? $_POST['txn_id'] : '') . ' item_number=' . (isset($_POST['item_number']) ? $_POST['item_number'] : ''));

        $system_config = common_model::$global_config['system_config'];
        $sandbox = !($system_config['paypal_mode'] == 0);
        if (!$this->_verify_with_paypal($sandbox)) {
            $this->_log('postpaid_ipn: PayPal verification FAILED or empty response');
            echo '';
            return;
        }
        $txn_id = (string) $this->CI->input->post('txn_id', true);
        if ($txn_id !== '') {
            $already = $this->CI->db->get_where('payment_transaction', array('transaction_id' => $txn_id))->row_array();
            if (!empty($already)) {
                echo 'OK';
                return;
            }
        }
        $item_number = (string) $this->CI->input->post('item_number', true);
        $pending = $this->CI->db->get_where('payment_transaction', array(
            'transaction_details' => $item_number,
            'amount' => '0',
            'actual_amount' => '0',
            'user_currency' => ''
        ))->row_array();
        if (empty($pending) || empty($pending['accountid'])) {
            $this->_log('postpaid_ipn: no pending row item_number=' . $item_number);
            echo 'OK';
            return;
        }
        $accountid = (int) $pending['accountid'];
        $custom = (string) $this->CI->input->post('custom', true);
        if ((int) $accountid !== (int) $custom) {
            $this->_log('postpaid_ipn: custom mismatch');
            echo 'OK';
            return;
        }
        if ($system_config['paypal_mode'] == 0) {
            $expect_email = strtolower(trim((string) $system_config['paypal_id']));
        } else {
            $expect_email = strtolower(trim((string) $system_config['paypal_sandbox_id']));
        }
        $receiver = strtolower(trim((string) $this->CI->input->post('receiver_email', true)));
        if ($expect_email !== '' && $receiver !== '' && $receiver !== $expect_email) {
            $this->_log('postpaid_ipn: receiver_email mismatch');
            echo 'OK';
            return;
        }
        $payment_status = (string) $this->CI->input->post('payment_status', true);
        if (!in_array($payment_status, array('Completed', 'Complete', 'Pending'), true)) {
            echo 'OK';
            return;
        }
        $account_info = (array) $this->CI->db->get_where('accounts', array(
            'id' => $accountid,
            'deleted' => '0',
            'status' => '0'
        ))->first_row();
        if (empty($account_info) || (int) $account_info['posttoexternal'] !== 1) {
            echo 'OK';
            return;
        }
        $mc_currency = (string) $this->CI->input->post('mc_currency', true);
        if ($mc_currency === '') {
            $mc_currency = (string) $this->CI->input->post('currency_code', true);
        }
        $mc_gross = (float) $this->CI->input->post('mc_gross', true);
        if ($mc_gross <= 0 && $this->CI->input->post('payment_gross', true) !== null && $this->CI->input->post('payment_gross', true) !== '') {
            $mc_gross = (float) $this->CI->input->post('payment_gross', true);
        }
        if ($mc_gross <= 0) {
            echo 'OK';
            return;
        }
        $amount_base = $this->_convert_paypal_to_base($mc_gross, $mc_currency);
        if ($amount_base <= 0) {
            echo 'OK';
            return;
        }
        $currency_info = (array) $this->CI->db->get_where('currency', array(
            'id' => $account_info['currency_id']
        ))->first_row();
        $mc_fee = $this->CI->input->post('mc_fee', true);
        $payment_fee = ($mc_fee !== null && $mc_fee !== '') ? (float) $mc_fee : 0;
        $item_name_note = trim((string) $this->CI->input->post('item_name', true));
        $desc = $item_name_note !== '' ? $item_name_note : 'Postpaid PayPal payment';
        $payment_info = array(
            'order_item_id' => 0,
            'name' => $desc,
            'description' => $desc,
            'price' => $amount_base,
            'invoice_type' => 'credit',
            'charge_type' => 'REFILL',
            'product_category' => 3,
            'payment_by' => 'Paypal',
            'is_apply_tax' => 'false',
            'transaction_id' => $txn_id !== '' ? $txn_id : crc32(uniqid((string) $item_number, true)),
            'payment_fee' => $payment_fee
        );
        $this->CI->payment->add_payments_transcation($payment_info, $account_info, $currency_info);
        $this->CI->db->where('id', (int) $pending['id']);
        $this->CI->db->delete('payment_transaction');
        $this->_log('postpaid_ipn: applied payment account=' . $accountid . ' base=' . $amount_base . ' txn_id=' . $txn_id);
        echo 'OK';
    }

    protected function _log($line)
    {
        $logger = (array) $this->CI->db->get_where('system', array(
            'name' => 'log_path',
            'group_title' => 'global'
        ))->first_row();
        if (empty($logger['value'])) {
            return;
        }
        $path = rtrim($logger['value'], '/') . '/astpp_postpaid_paypal_ipn.log';
        $fp = @fopen($path, 'a+');
        if (!$fp) {
            return;
        }
        fwrite($fp, date('Y-m-d H:i:s') . ' ' . $line . "\n");
        fclose($fp);
    }

    /**
     * PayPal classic IPN: echo POST back with cmd=_notify-validate using their encoding rules.
     */
    protected function _verify_with_paypal($sandbox)
    {
        $req = 'cmd=_notify-validate';
        if (function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc()) {
            $post = array();
            foreach ($_POST as $k => $v) {
                $post[$k] = stripslashes($v);
            }
        } else {
            $post = $_POST;
        }
        foreach ($post as $key => $value) {
            $req .= '&' . $key . '=' . urlencode((string) $value);
        }
        $url = $sandbox ? 'https://ipnpb.sandbox.paypal.com/cgi-bin/webscr' : 'https://ipnpb.paypal.com/cgi-bin/webscr';
        $res = false;
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $req);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
            curl_setopt($ch, CURLOPT_TIMEOUT, 90);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Connection: Close'));
            $res = curl_exec($ch);
            $err = curl_error($ch);
            curl_close($ch);
            if ($res === false && $err !== '') {
                $this->_log('postpaid_ipn curl error: ' . $err);
            }
        }
        if ($res === false || $res === null || $res === '') {
            $opts = array(
                'http' => array(
                    'method' => 'POST',
                    'header' => "Content-Type: application/x-www-form-urlencoded\r\nConnection: Close\r\n",
                    'content' => $req,
                    'timeout' => 90
                )
            );
            $res = @file_get_contents($url, false, stream_context_create($opts));
        }
        if ($res === false || $res === null) {
            return false;
        }
        $res = trim($res);
        return (strncmp($res, 'VERIFIED', 8) === 0);
    }

    protected function _convert_paypal_to_base($amount, $from_currency)
    {
        $from_currency = trim((string) $from_currency);
        $base = common_model::$global_config['system_config']['base_currency'];
        $list = common_model::$global_config['currency_list'];
        $from_rate = (isset($list[$from_currency]) && (float) $list[$from_currency] > 0) ? (float) $list[$from_currency] : 0.0;
        $to_rate = (isset($list[$base]) && (float) $list[$base] > 0) ? (float) $list[$base] : 1.0;
        if ($from_rate <= 0) {
            return (float) $amount;
        }
        return round((float) (($amount * $to_rate) / $from_rate), 8);
    }
}
