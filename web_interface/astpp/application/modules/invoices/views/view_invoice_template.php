<style type="text/css">
	@media print {

		::before,
		::after {
			-webkit-box-sizing: border-box;
			-moz-box-sizing: border-box;
			box-sizing: border-box;
		}

		hr {
			margin-top: 20px;
			margin-bottom: 20px;
			border: 0;
			border-top: solid 0.3mm #eee;
		}

		address {
			margin-bottom: 20px;
			font-style: normal;
			line-height: 1.428571429;
		}

		table {
			max-width: 100%;
			background-color: transparent;
			border-collapse: collapse;
			border-spacing: 0;
		}

		table tr td {
			padding: 5px;
		}

		.table {
			width: 100%;
			margin-bottom: 20px;
		}

		.table>tr>th,
		.table>tr>th,
		.table>tr>th,
		.table>tr>td,
		.table>tr>td,
		.table>tr>td {
			padding: 8px;
			line-height: 1.428571429;
			vertical-align: top;
			border-top: solid 0.3mm #ddd;
		}

		.col-xs-6 {
			width: 49%;
			float: left;
			position: relative;
			min-height: 1px;
		}

		.col-md-6 {
			width: 50%;
			float: left;
			position: relative;
			min-height: 1px;
		}

		.panel {
			margin-bottom: 20px;
			background-color: #fff;
			border: solid 0.5mm #ddd;
			border-radius: 4px;
			-webkit-box-shadow: 0 1px 1px rgba(0, 0, 0, .05);
			box-shadow: 0 1px 1px rgba(0, 0, 0, .05);
		}

		.bg_gray {
			background: #f5f5f5;
		}

		.box_border {
			border: solid 0.3mm !important;
			border-color: #000000;
			padding: 10px;
		}

		.float-right {
			float: right !important;
		}

		.text-right {
			text-align: right;
		}

		.py-2 {
			padding: 20px 0;
		}

		.mb-20 {
			margin-bottom: 20px;
		}
	}
</style>

<page backtop="10mm" backbottom="10mm" backleft="10mm" backright="10mm" style="font-size: 8pt">
	<table class="page_header">
		<tr>
			<?php if ($logo == '' || getimagesize('upload/' . $logo) === False) { ?>
				<td style="width: 50%; text-align: left">
					<img height="50" width="181" style="background-color: #ffffff;" src="assets/images/logo.png" alt="LOGO">
				</td>
			<?php } else { ?>
				<td style="width: 50%; text-align: left">
					<img height="50" width="181" style="background-color: #ffffff;" src="upload/<?php echo $logo; ?>"
						alt="LOGO">
				</td>
			<?php } ?>
			<td style="width: 50%; text-align: right; font-size: 12pt">
				<b><?php echo gettext('INVOICE'); ?></b>
			</td>
		</tr>
	</table>

	<table class="table py-2">
		<tbody>
			<tr>
				<td style="width: 50%; text-align: left">
					<address>
						<br>
						<?php echo $cmp_name; ?>,<br>
						<?php echo $cmp_address; ?>,<br>
						<?php echo $cmp_city_zipcode; ?><br>
						<?php echo $cmp_province_country; ?><br>
						<?php echo gettext('Phone') . ":"; ?> <?php echo $cmp_telephone; ?><br>
						<?php echo $cmp_tax; ?><br>
					</address>
				</td>

				<td style="width: 50%; text-align: right">
					<address>
						<b><?php echo gettext('Bill To') . ":"; ?></b><br>
						<?php echo $fullname; ?><br>
						<?php if (!empty($account_number)): ?>
							<?php echo gettext('Account No') . ": "; ?> 	<?php echo $account_number; ?><br>
						<?php endif; ?>
						<?php
						if (isset($address_1) && trim($address_1) != '') {
							echo ucfirst($address_1);
						}
						?>
						<?php
						if (isset($address_2) && trim($address_2) != '') {
							if (isset($address_1) && trim($address_1) != '') {
								echo ",<br>";
							}
							echo ucfirst($address_2);
						}
						?>
						<?php
						if (isset($city_postalcode) && $city_postalcode != '') {
							if (isset($address_2) && trim($address_2) != '') {
								echo ",<br>";
							}
							echo ucfirst($city_postalcode);
						}
						?>
						<?php
						if (isset($province_country) && $province_country != '') {
							if (isset($city_postalcode) && $city_postalcode != '') {
								echo ",<br>";
							}
							echo ucfirst($province_country);
						}
						?>
						<?php
						if (isset($tax_number) && $tax_number != '') {
							if (isset($tax_number) && $tax_number != '') {
								echo ",<br>";
							}
							echo $tax_number;
						}
						?>
					</address>
				</td>
			</tr>
		</tbody>
	</table>

	<table class="table">
		<tr style="width: 100%;" class="mb-20">
			<!-- LEFT BOX: invoice meta -->
			<td class="box_border col-xs-6" style="padding: 0;">
				<table class="table">
					<tbody>
						<tr>
							<td class="col-md-6"><b><?php echo gettext('Invoice Number'); ?> </b></td>
							<td class="col-md-6 text-right"><?php echo $invoicenumber; ?></td>
						</tr>
						<tr>
							<td class="col-md-6"><b><?php echo gettext('Invoice Date'); ?> </b></td>
							<td class="col-md-6 text-right"><?php echo htmlspecialchars($invoice_date); ?></td>
						</tr>
						<tr>
							<td class="col-md-6"><b><?php echo gettext('Invoice Due Date'); ?> </b></td>
							<td class="col-md-6 text-right"><?php echo htmlspecialchars($invoice_due_date); ?></td>

						</tr>
						<tr>
							<td class="col-md-6"><b><?php echo gettext('Billing Period'); ?> </b></td>
							<td class="col-md-6 text-right">
								<?php
								if (isset($from_date) && !empty($from_date)) {
									echo htmlspecialchars($from_date);
									if (!empty($to_date)) {
										echo ' - ' . htmlspecialchars($to_date);
									}
								} else {
									echo '&nbsp;';
								}
								?>
							</td>
						</tr>
						<tr>
							<td class="col-md-6"><b><?php echo gettext('Currency'); ?> </b></td>
							<td class="col-md-6 text-right"><?php echo $currency; ?></td>
						</tr>
					</tbody>
				</table>
			</td>

			<td class="box_border"
				style="width: 2%; padding: 0; border-top: none !important; border-bottom: none !important;">
			</td>

			<!-- RIGHT BOX: NEW ACCOUNT SUMMARY -->
			<td class="box_border col-xs-6" style="padding: 0;">
				<table class="table">
					<tr class="bg_gray">
						<td colspan="2"><b><?php echo gettext('Account Summary'); ?></b></td>
					</tr>
					<tr>
						<td class="col-md-6"><?php echo gettext('Previous Balance'); ?></td>
						<td class="col-md-6 text-right">
							<?php echo $this->common_model->calculate_currency($this->common->currency_decimal($previous_balance), '', '', true, false); ?>
						</td>
					</tr>
					<tr>
						<td class="col-md-6"><?php echo gettext('Payments Received'); ?></td>
						<td class="col-md-6 text-right">
							<?php echo $this->common_model->calculate_currency($this->common->currency_decimal($payments_received), '', '', true, false); ?>
						</td>
					</tr>
					<tr>
						<td class="col-md-6"><?php echo gettext('Postcharges / Refunds'); ?></td>
						<td class="col-md-6 text-right">
							<?php echo $this->common_model->calculate_currency($this->common->currency_decimal($credits_refunds), '', '', true, false); ?>
						</td>
					</tr>
					<tr>
						<td class="col-md-6"><?php echo gettext('Subscription Charges'); ?></td>
						<td class="col-md-6 text-right">
							<?php echo $this->common_model->calculate_currency($this->common->currency_decimal($subscription_charges), '', '', true, false); ?>
						</td>
					</tr>
					<tr>
						<td class="col-md-6"><?php echo gettext('Session Charges (Calls)'); ?></td>
						<td class="col-md-6 text-right">
							<?php echo $this->common_model->calculate_currency($this->common->currency_decimal($session_charges), '', '', true, false); ?>
						</td>
					</tr>
					<tr>
						<td class="col-md-6"><?php echo gettext('Funds Transferred'); ?></td>
						<td class="col-md-6 text-right">
							<?php echo $this->common_model->calculate_currency($this->common->currency_decimal($transfer_debits), '', '', true, false); ?>
						</td>
					</tr>
					<tr class="bg_gray">
						<td class="col-md-6"><b><?php echo gettext('Invoice Total (Current Period)'); ?></b></td>
						<td class="col-md-6 text-right">
							<b><?php echo $this->common_model->calculate_currency($this->common->currency_decimal($invoice_total), '', '', true, false); ?></b>
						</td>
					</tr>
					<tr>
						<td class="col-md-6"><b><?php echo gettext('New Balance / Amount Due'); ?></b></td>
						<td class="col-md-6 text-right">
							<b><?php echo $this->common_model->calculate_currency($this->common->currency_decimal($amount_due), '', '', true, false); ?></b>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>

	<?php if (trim($invoice_notes) != '') { ?>
		<table class="table py-2">
			<tr>
				<td style="width: 10%;"><b><?php echo gettext('Note:'); ?> </b></td>
				<td style="width: 90%;"><?php echo $invoice_notes; ?></td>
			</tr>
		</table>
	<?php } else { ?>
		<br>
	<?php } ?>

	<div class="panel">
		<table class="table table-condensed" style="padding: 0;">
			<tr class="bg_gray" style="width: 100%;">
				<td style="width: 18%; border-bottom: 1px solid #f5f5f5;"><b><?php echo gettext('Date / Time'); ?></b>
				</td>
				<td style="width: 42%; border-bottom: 1px solid #f5f5f5;"><b><?php echo gettext('Description'); ?></b>
				</td>
				<td style="width: 15%; border-bottom: 1px solid #f5f5f5;"><b><?php echo gettext('Type'); ?></b></td>
				<td style="width: 12%; border-bottom: 1px solid #f5f5f5;" class="text-right">
					<b><?php echo gettext('Debit'); ?> (<?php echo $currency; ?>)</b>
				</td>
				<td style="width: 13%; border-bottom: 1px solid #f5f5f5;" class="text-right">
					<b><?php echo gettext('Credit'); ?> (<?php echo $currency; ?>)</b>
				</td>
			</tr>
			<?php
			$total_debit = 0;
			$total_credit = 0;
			foreach ($invoice_details_data as $invdatakey => $inv_data_value) {
				?>
				<tr style="width: 100%;">
					<td style="width: 18%; border-bottom: 1px solid #f5f5f5;">
						<?php
						if (!empty($inv_data_value['created_date'])) {
							echo date("d-m-Y H:i:s", strtotime($inv_data_value['created_date']));
						} else {
							echo '&nbsp;';
						}
						?>
					</td>
					<td style="width: 42%; border-bottom: 1px solid #f5f5f5;"><?php echo $inv_data_value['description']; ?>
					</td>
					<td style="width: 15%; border-bottom: 1px solid #f5f5f5;">
						<?php echo isset($inv_data_value['charge_type']) ? htmlspecialchars($inv_data_value['charge_type']) : ''; ?>
					</td>
					<td style="width: 12%; border-bottom: 1px solid #f5f5f5;" class="text-right">
						<?php
						if ($posttoexternal == 0 && $inv_data_value['credit'] > 0 && $inv_data_value['credit'] != '') {
							echo '&nbsp;';
						} else {
							// ASTPPCOM-1369_kinjal_start
							if ($inv_data_value['order_item_id'] == 0 && ($inv_data_value['charge_type'] == "INVPAY" || $inv_data_value['charge_type'] == "") && $inv_data_value['generate_type'] == 0) {
								echo '&nbsp;';
							} else {
								echo $this->common_model->calculate_currency($this->common->currency_decimal($inv_data_value['debit']), '', '', true, false);
							}
							// ASTPPCOM-1369_kinjal_end
						}
						?>
					</td>
					<td style="width: 13%; border-bottom: 1px solid #f5f5f5;" class="text-right">
						<?php
						if ($posttoexternal == 0 && $inv_data_value['credit'] > 0 && $inv_data_value['credit'] != '') {
							echo $this->common_model->calculate_currency($this->common->currency_decimal($inv_data_value['credit']), '', '', true, false);
						} else {
							// ASTPPCOM-1369_kinjal_start
							if ($inv_data_value['order_item_id'] == 0 && ($inv_data_value['charge_type'] == "INVPAY" || $inv_data_value['charge_type'] == "") && $inv_data_value['generate_type'] == 0) {
								echo $this->common_model->calculate_currency($this->common->currency_decimal($inv_data_value['credit']), '', '', true, false);
							} else {
								echo $this->common_model->calculate_currency($this->common->currency_decimal($inv_data_value['credit']), '', '', true, false);
							}
							// ASTPPCOM-1369_kinjal_end
						}
						?>
					</td>
				</tr>
				<?php
				// Debit total: same logic as old Subtotal (skip prepaid credit-only rows)
				if (!($posttoexternal == 0 && $inv_data_value['credit'] > 0 && $inv_data_value['credit'] != '')) {
					$total_debit += $inv_data_value['debit'];
				}

				// Credit total: sum all positive credits shown in the table
				if ($inv_data_value['credit'] > 0) {
					$total_credit += $inv_data_value['credit'];
				}

			}
			?>
		</table>

		<hr>

		<table class="table table-condensed" style="padding: 0;">
			<tr style="width: 100%;">
				<td style="width: 60%; border-bottom: 1px solid #f5f5f5;">&nbsp;</td>
				<td class="thick-line text-right" style="width: 20%; border-bottom: 1px solid #f5f5f5;">
					<b><?php echo gettext('Total Debit'); ?></b>
				</td>
				<td class="thick-line text-right" style="width: 20%; border-bottom: 1px solid #f5f5f5;">
					<b><?php echo $this->common_model->calculate_currency(
						$this->common->currency_decimal($total_debit),
						'',
						'',
						true,
						false
					); ?></b>
				</td>
			</tr>
			<tr style="width: 100%;">
				<td style="width: 60%; border-bottom: 1px solid #f5f5f5;">&nbsp;</td>
				<td class="thick-line text-right" style="width: 20%; border-bottom: 1px solid #f5f5f5;">
					<b><?php echo gettext('Total Credit'); ?></b>
				</td>
				<td class="thick-line text-right" style="width: 20%; border-bottom: 1px solid #f5f5f5;">
					<b><?php echo $this->common_model->calculate_currency(
						$this->common->currency_decimal($total_credit),
						'',
						'',
						true,
						false
					); ?></b>
				</td>
			</tr>

		</table>
	</div>

	<div>
		<p>
			<?php
			echo "<b>" . gettext("NOTE") . ": </b>";
			echo gettext($cmp_invoice_note);
			?>
		</p>
	</div>


</page>

<?php if (isset($cdr_data) && !empty($cdr_data)) { ?>

	<page backtop="10mm" backbottom="10mm" backleft="10mm" backright="10mm" style="font-size: 8pt">

		<!-- Same header as first page -->
		<table class="page_header">
			<tr>
				<?php if ($logo == '' || getimagesize('upload/' . $logo) === False) { ?>
					<td style="width: 50%; text-align: left">
						<img height="50" width="181" style="background-color: #ffffff;" src="assets/images/logo.png" alt="LOGO">
					</td>
				<?php } else { ?>
					<td style="width: 50%; text-align: left">
						<img height="50" width="181" style="background-color: #ffffff;" src="upload/<?php echo $logo; ?>"
							alt="LOGO">
					</td>
				<?php } ?>
				<td style="width: 50%; text-align: right; font-size: 12pt">
					<b><?php echo gettext('Call Detail Records'); ?></b>
				</td>
			</tr>
		</table>

		<div class="panel" style="margin-top: 20px;">
			<table class="table table-condensed" style="padding:0;">
				<tr class="bg_gray" style="width:100%;">
					<td style="width:18%; border-bottom:1px solid #f5f5f5;">
						<b><?php echo gettext('Caller ID'); ?></b>
					</td>
					<td style="width:22%; border-bottom:1px solid #f5f5f5;">
						<b><?php echo gettext('Called Number'); ?></b>
					</td>
					<td style="width:10%; border-bottom:1px solid #f5f5f5;">
						<b><?php echo gettext('Call Type'); ?></b>
					</td>
					<td style="width:25%; border-bottom:1px solid #f5f5f5;">
						<b><?php echo gettext('Date / Time'); ?></b>
					</td>
					<td style="width:10%; border-bottom:1px solid #f5f5f5;" class="text-right">
						<b><?php echo gettext('Duration'); ?></b>
					</td>
					<td style="width:15%; border-bottom:1px solid #f5f5f5;" class="text-right">
						<b><?php echo gettext('Charge'); ?></b>
					</td>
				</tr>

				<?php foreach ($cdr_data as $cdr) { ?>
					<?php
					$sec = (int) $cdr['billseconds'];
					$h = floor($sec / 3600);
					$m = floor(($sec % 3600) / 60);
					$s = $sec % 60;
					$duration_str = sprintf('%02d:%02d:%02d', $h, $m, $s);
					?>
					<tr style="width:100%;">
						<td style="width:18%; border-bottom:1px solid #f5f5f5;">
							<?php echo htmlspecialchars($cdr['sip_user']); ?>
						</td>
						<td style="width:22%; border-bottom:1px solid #f5f5f5;">
							<?php echo htmlspecialchars($cdr['callednum']); ?>
						</td>
						<td style="width:10%; border-bottom:1px solid #f5f5f5;">
							<?php echo htmlspecialchars($cdr['calltype']); ?>
						</td>
						<td style="width:25%; border-bottom:1px solid #f5f5f5;">
							<?php echo date('d-m-Y H:i:s', strtotime($cdr['callstart'])); ?>
						</td>
						<td style="width:10%; border-bottom:1px solid #f5f5f5;" class="text-right">
							<?php echo $duration_str; ?>
						</td>
						<td style="width:15%; border-bottom:1px solid #f5f5f5;" class="text-right">
							<?php
							echo $this->common_model->calculate_currency(
								$this->common->currency_decimal($cdr['debit']),
								'',
								'',
								true,
								false
							);
							?>
						</td>
					</tr>
				<?php } ?>
			</table>
		</div>

	</page>

<?php } ?>