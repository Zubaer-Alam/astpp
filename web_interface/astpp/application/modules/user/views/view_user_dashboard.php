<? extend('master.php') ?>
<? startblock('page-title') ?>
  <?php echo $page_title; ?>
<? endblock() ?>
<?php
	$accountinfo=$this->session->userdata('accountinfo');
	$currency=$this->common->get_field_name('currency','currency',array("id"=>$accountinfo['currency_id']));
	$is_postpaid_customer = ((int) $accountinfo['posttoexternal'] === 1);
	$system_currency = $is_postpaid_customer ? Common_model::$global_config['system_config']['base_currency'] : '';
	$settle_paypal_allowed = $is_postpaid_customer && isset($accountinfo['paypal_permission']) && (int) $accountinfo['paypal_permission'] === 0;

?>
<? startblock('extra_head') ?>
    <script type="text/javascript" src="<?php echo base_url();?>assets/js/chart/highcharts.js"></script>
    <script type="text/javascript" src="<?php echo base_url();?>assets/js/chart/exporting.js"></script>
    <script type="text/javascript" src="<?php echo base_url();?>assets/js/chart/highcharts-3d.js"></script>
<?php if ($is_postpaid_customer) { ?>
<link href="<?php echo base_url(); ?>assets/css/invoice.css" rel="stylesheet">
<?php } ?>
<style>
.second{
 color: black;
 opacity:0.4;
 border: 0px;
 width: 100%; 
 padding:100px 0px 100px 0px;
 margin:5px;  
 display:relative;
 text-align:center;
 font-size:250%;
}
.active_product table td{
	word-break:break-all;
	}
.new_products table td{
	word-break:break-all;
	}	
</style>
<script type="text/javascript">
	function get_current_data(){
		 $.ajax({
				type:'POST',
				url: "<?php echo base_url(); ?>"+'user/user_get_current_info/',
				dataType: 'JSON',
				cache    : false,
				async    : false,
        success: function(response_data) {
        		
        		$("#product_count").html(response_data.product_count);
        		$("#call_count").html(response_data.call_count);
        		$("#balance").html(response_data.balance);
        		$("#invoice_amount").html(response_data.invoice_amount);
        		if (typeof response_data.outstanding_net !== "undefined") {
        			window.__dashboardOutstanding = {
        				net: parseFloat(response_data.outstanding_net),
        				formatted: response_data.invoice_amount
        			};
        		}
        }
    	});
	}
<?php if ($is_postpaid_customer) { ?>
	var settle_from_cur = "<?php echo addslashes($currency); ?>";
	var settle_to_cur = "<?php echo addslashes($system_currency); ?>";
	function settle_modal_refresh_conversion() {
		var $inp = $("#settle_gross_amount");
		var raw = $inp.val().replace(/[^0-9\.]/g, '');
		$inp.val(raw);
		var amt = (raw === '') ? 0 : parseFloat(raw);
		if (isNaN(amt)) {
			amt = 0;
		}
		if (amt <= 0) {
			$("#settle_amt_in_currency").html("0 " + settle_from_cur + " <?php echo gettext('is equals to'); ?> 0 " + settle_to_cur);
			$("#settle_amount_usd").val("0");
			return;
		}
		$.ajax({
			type: 'POST',
			url: "<?php echo base_url(); ?>user/user_payment/GET_AMT/",
			data: "value=" + amt,
			success: function(response) {
				$("#settle_amt_in_currency").html(amt + " " + settle_from_cur + " <?php echo gettext('To'); ?> " + response.trim() + " " + settle_to_cur);
				$("#settle_amount_usd").val(response.trim());
			}
		});
	}
<?php } ?>
  $(document).ready(function() {
			get_current_data();
<?php if ($is_postpaid_customer) { ?>
			$("#btn_topup_postpaid").on("click", function() {
				var o = window.__dashboardOutstanding || {};
				$("#settle_modal_outstanding").html(o.formatted || $("#invoice_amount").html());
				$("#settle_gross_amount").val("");
				$("#settle_amt_in_currency").html("0 " + settle_from_cur + " <?php echo gettext('is equals to'); ?> 0 " + settle_to_cur);
				$("#settle_amount_usd").val("0");
				$("#settle_pay_error").html("");
				$("#settleSpecialNote").val("");
				$("#settleOutstandingModal").modal("show");
				if (!isNaN(o.net) && o.net > 0) {
					$("#settle_gross_amount").val(o.net);
					settle_modal_refresh_conversion();
				}
			});
			$("#settle_gross_amount").on("input", function() {
				var self = $(this);
				self.val(self.val().replace(/[^0-9\.]/g, ''));
				settle_modal_refresh_conversion();
			});
			$("#settle_pay_paypal_btn").on("click", function() {
				var baseAmt = parseFloat(String($("#settle_amount_usd").val()).replace(/,/g, ''));
				var acctAmt = parseFloat(String($("#settle_gross_amount").val()).replace(/,/g, ''));
				if (!baseAmt || baseAmt <= 0 || isNaN(baseAmt)) {
					$("#settle_pay_error2").text("<?php echo addslashes(gettext('Enter a valid amount and wait for currency conversion.')); ?>");
					return;
				}
				$("#settle_pay_error2").text("");
				var form = document.createElement("form");
				form.method = "post";
				form.action = "<?php echo base_url(); ?>user/user_postpaid_settle_paypal_prepare/";
				var ab = document.createElement("input");
				ab.name = "amount_base";
				ab.type = "hidden";
				ab.value = baseAmt;
				form.appendChild(ab);
				var aa = document.createElement("input");
				aa.name = "amount_account";
				aa.type = "hidden";
				aa.value = (acctAmt && !isNaN(acctAmt)) ? acctAmt : "";
				form.appendChild(aa);
				var nm = document.createElement("input");
				nm.name = "item_name";
				nm.type = "hidden";
				nm.value = $("#settleSpecialNote").val() || "";
				form.appendChild(nm);
				document.body.appendChild(form);
				form.submit();
			});
<?php } ?>
	});
</script> 
<? endblock() ?>
<? startblock('content') ?>

<section class="slice">
    <div class="w-section inverse no-padding">
            <div class="row">
                <div class="col-md-12">        
                    
                     <div class="dashboard_values row mb-3">
                     
                     		<a href="<?php echo base_url(); ?>user/user_products_list/" class="col-lg-3 col-md-6 col-sm-12 pt-2 pr-lg-2">
                            <div class="bg-primary card col-12 text-light">
                                <div class="col-lg-8 col-7 float-left py-5 px-4 ">
                                    <div class="h1" id="product_count">0</div>
                                    <h3><?php echo gettext('My Products') ?></h3>
                                </div>
                                <div class="col-lg-4 col-5 float-left py-5 px-4 ">
                                    <i class="fa fa-archive fa-4x float-left"></i>
                                </div>
                            </div>
                        </a>
                     
                     		<a href="<?php echo base_url(); ?>user/user_cdrs_report/" class="col-lg-3 col-md-6 col-sm-12 pt-2 pr-lg-2 pl-lg-2">
                            <div class="bg-danger card col-12 text-light">
                                <div class="col-lg-8 col-7 float-left py-5 px-4 ">
                                    <div class="h1" id="call_count">0</div>
                                    <h3><?php echo gettext("Todays Calls"); ?></h3>
                                </div>
                                <div class="col-lg-4 col-5 float-left py-5 px-4 ">
                                    <i class="fa fa-phone fa-4x float-left"></i>
                                </div>
                            </div>
                        </a>
                        
                        <div class="col-lg-3 col-md-6 col-sm-12 pt-2 pr-lg-2 pl-lg-2">
                            <div class="bg-success card col-12 text-light">
                                <a href="<?php echo base_url(); ?>user/user_invoices_list/" class="col-lg-8 col-7 float-left py-5 px-4 text-light" style="text-decoration: none;">
                                   <div class="h1" id="invoice_amount">0</div>
                                    <h3><?php echo gettext('Outstanding'); ?> (<?php echo $currency;?>)</h3>
                                </a>
                                <div class="col-lg-4 col-5 py-4 float-left text-center">
                                    <i class="fa fa-wpforms fa-4x"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-3 col-md-6 col-sm-12 pt-2"><div class="bg-dark card col-12 text-light pl-lg-2">
                                <div class="col-lg-8 col-7 float-left py-5">
                                    <div class="h1" id="balance">0</div>
                                    <?php
										$variable =$accountinfo['posttoexternal']==1 ? 'Credit' : gettext('Balance');  
									?>
                                    <h3><?php echo $variable." (".$currency.")";?></h3>
                                </div>
                                <div class="col-lg-4 col-5 py-4 float-left text-center">
                                    <i class="fa fa-money fa-4x"></i>
                                    <?php if ($is_postpaid_customer) { ?>
                                    <button type="button" id="btn_topup_postpaid" class="btn btn-success btn-sm p-1 mt-2 w-100"><?php echo gettext('Top Up'); ?></button>
                                    <?php } else { ?>
                                    <a href="<?php echo base_url();?>products/products_topuplist/" class="btn btn-success btn-sm p-1 mt-2 w-100"><?php echo gettext('Top Up'); ?></a>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>      
                    </div>

                <div class="row mb-3">
                 <div class="col-lg-6 d-flex pr-lg-2">
                    <div class="card w-100 active_product">
                        <h3 class="text-dark p-3"><i class="fa fa-archive text-primary fa-fw"></i> <?php echo gettext('Active Products'); ?>
                                <a href="<?php echo base_url();?>user/user_products_list/" class="float-right btn btn-secondary"><?php echo gettext('View All'); ?></a>
                        </h3>
                        <div class="card-body table-responsive">

                            <table class="table table-hover">
                              <thead class="thead-light">
                                <tr>
                                  <th scope="col"><?php echo gettext('Name') ?></th>
                                  <th scope="col"><?php echo gettext('Date'); ?></th>
                                  <th scope="col"><?php echo gettext('Order');?></th>
                                  <th scope="col"><?php echo gettext('Payment Method'); ?></th>
				  <th scope="col"><?php echo gettext('Quantity'); ?></th>
                                  <th scope="col"><?php echo gettext('Setup Fee'); ?> [<?php echo $currency;?>]</th>
                                  <th scope="col"><?php echo gettext('Price');?> [<?php echo $currency;?>]</th>
                                  <th scope="col"><?php echo gettext('Status'); ?></th>
                                </tr>
                              </thead>
                              <tbody>
                              	<?php
                              		
																	$account_data = $this->session->userdata ( "accountinfo" );
																  $where_arr = array ("orders.accountid"=>$account_data['id'],"order_items.is_terminated"=>'0');
																	$this->db->order_by("orders.order_date", "desc");
																	
																	

																	
																	$query = $this->db_model->getJionQuery('orders', 'orders.id,orders.order_date,orders.order_id as id1,orders.payment_gateway,orders.payment_status,order_items.order_id as orderid,order_items.price,order_items.quantity,order_items.setup_fee,order_items.product_id',$where_arr, 'order_items','orders.id=order_items.order_id', 'inner',10, 0,'','');
																	

																	//
																	if($query->num_rows > 0){
																		$result_array =  $query->result_array();
																		foreach($result_array as $key=>$val){
																			echo "<tr>";
																			echo "<td>".$this->common->get_field_name('name',"products",array('id'=>$val['product_id']))."</td>";
											                echo "<td>".$val['order_date']."</td>";
											                echo "<td>".$val['id1']."</td>";
											                echo "<td>".$val['payment_gateway']."</td>";

     echo "<td>".$val['quantity']."</td>";
											                echo "<td>".$this->common_model->calculate_currency_customer($val ['setup_fee'])."</td>";
											                echo "<td>".$this->common_model->calculate_currency_customer($val ['price'])."</td>";
											                echo "<td>".$val['payment_status']."</td>";
											                echo "</tr>";
																		}
																	}
                                ?> 
                              </tbody>
                            </table>
                        
                        </div>
                    </div>
                 </div>
             
                 <div class="col-lg-6 d-flex pl-lg-2">
                    <div class="card w-100 new_products">
                        <h3 class="text-dark p-3"><i class="fa fa-archive text-primary fa-fw"></i> <?php echo gettext('New Products');?>
                                
                                <a href="<?php echo base_url();?>user/user_available_products/" class="float-right btn btn-secondary"><?php echo gettext('View All')?></a>
                        </h3>
                        <div class="card-body table-responsive">

                            <table class="table table-hover">
                              <thead class="thead-light">
                                <tr>
                                  <th scope="col"><?php echo gettext('Name');?></th>
                                  <th scope="col"><?php echo gettext('Category');?></th>
                                  <th scope="col"><?php echo gettext('Price'); ?> [<?php echo $currency;?>]</th>
                                  <th scope="col"><?php echo gettext('Setup Fee');?> [<?php echo $currency;?>]</th>
                                  <th scope="col"><?php echo gettext('Bill Type');?></th>
                                  <th scope="col"><?php echo gettext('Bill Days');?></th>
                                </tr>
                              </thead>
                              <tbody>
                              	<?php
                              	
                              		if(isset($productdata) && !empty($productdata)){
                              			foreach($productdata as $key=>$val){
                              				echo "<tr>";
										                	echo "<td>".$val['name']."</td>";
										                	echo "<td>".$this->common->get_field_name("name","category",array("id"=>$val['product_category']))."</td>";
										                	echo "<td>".$this->common_model->calculate_currency_customer($val ['price'])."</td>";
										                	echo "<td>".$this->common_model->calculate_currency_customer($val ['setup_fee'])."</td>";
										                	if($val['billing_type'] == "0"){
						                          	echo "<td><span class='badge badge-success'>One time</span></td>";
						                          }else{
						                          	echo "<td><span class='badge badge-danger'>Recurring</span></td>";
						                          }
						                          echo "<td>".$val['billing_days']."</td>";
						                          echo "</tr>";
                              			}
                              		}
                              	?>
                              </tbody>
                            </table>
                        
                        </div>
                    </div>
                 </div>
             </div>

              <div class="row">
                 <div class="col-lg-12">
                    <div class="card">
                        <h3 class="text-dark p-3"><i class="fa fa-phone text-primary fa-fw"></i> <?php echo gettext('Recent Calls'); ?>
                                
                                <a href="<?php echo base_url();?>user/user_cdrs_report/" class="float-right btn btn-secondary"><?php echo gettext('View All'); ?></a>
                        </h3>
                        <div class="card-body table-responsive">

                            <table class="table table-hover">
                              <thead class="thead-light">
                                <tr>
                                  <th scope="col"><?php echo gettext('Date');?></th>
                                  <th scope="col"><?php echo gettext('Caller ID');?></th>
                                  <th scope="col"><?php echo gettext('Called Number');?></th>
                                  <th scope="col"><?php echo gettext('Destination');?></th>
                                  <th scope="col"><?php echo gettext('Duration');?></th>
                                  <th scope="col"><?php echo gettext('Debit');?> [<?php echo $currency;?>]</th>
                                  <th scope="col"><?php echo gettext('Disposition');?></th>
                                  <th scope="col"><?php echo gettext('Call Type');?></th>
                                  <th scope="col"><?php echo gettext("Direction");?></th>
                                </tr>
                              </thead>
                              <tbody>
                              	<?php
                              		
                              		$accountinfo = $this->session->userdata ( 'accountinfo' );
																	$where = array (
																			"accountid" => $accountinfo['id']
																	);
            											$query_cdrs = $this->db_model->select ( "*", "cdrs", $where,"callstart","desc","10","0","");
																	if($query_cdrs->num_rows > 0){
																		$result_array =  $query_cdrs->result_array();
																		foreach($result_array as $key=>$val){
											                echo "<tr>";
															echo "<td>".$this->common->convert_GMT_to ( '', '', $val ['callstart'] )."</td>";
										                	echo "<td>".$val['callerid']."</td>";
										                	echo "<td>".$val['callednum']."</td>";
										                	echo "<td>".$val['notes']."</td>";
										                	echo "<td>".$val['billseconds']."</td>";
						                          if($accountinfo['type'] =='3'){
						                          	echo "<td>".$this->common_model->calculate_currency_customer($val ['cost'])."</td>";
						                          }else{
						                          	echo "<td>".$this->common_model->calculate_currency_customer($val ['debit'])."</td>";
						                          }
						                          echo "<td>".$val['disposition']."</td>";
						                          echo "<td>".$val['calltype']."</td>";
						                          if($val['call_direction'] == "outbound"){
						                          	echo "<td><span class='badge badge-success'>".$val['call_direction']."</span></td>";
						                          }else{
						                          	echo "<td><span class='badge badge-danger'>".$val['call_direction']."</span></td>";
						                          }
						                          echo "</tr>";
																		}
																	}
                                ?>
                               
                              </tbody>
                            </table>
                        
                        </div>
                    </div>
                 </div>
             </div>    
	    </div>
	    </div>
    </div>
</section>

<?php if ($is_postpaid_customer) { ?>
<div class="modal fade" id="settleOutstandingModal" tabindex="-1" role="dialog" aria-labelledby="settleOutstandingModalTitle" aria-hidden="true">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content border-0 rounded-0">
			<section class="slice m-0 border-bottom-0">
				<div class="w-section inverse p-0">
					<div class="col-md-12 p-0 card-header d-flex align-items-center justify-content-between">
						<h3 class="fw4 p-4 m-0" id="settleOutstandingModalTitle"><?php echo gettext('Settle Outstanding'); ?></h3>
						<button type="button" class="close p-4 m-0" data-dismiss="modal" aria-label="<?php echo gettext('Close'); ?>">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
				</div>
			</section>
			<section class="slice m-0">
				<div class="w-section inverse p-4">
					<table class="table invoice_table4 border-0 mb-4" border="0">
						<tbody>
							<tr>
								<td class="border-0 pl-0"><b><?php echo gettext('Outstanding Amount'); ?> :</b></td>
								<td class="border-0 pr-0"><span class="float-right" style="color: #3278b6"><b><span id="settle_modal_outstanding">—</span></b></span></td>
							</tr>
						</tbody>
					</table>
					<table id="settle_floating_label" class="invoice_table w-100" border="0">
						<tr class="form_invoice_head">
							<td class="border-0 p-0" valign="top">
								<div class="table-responsive">
									<table class="col-md-12 p-0">
										<tr>
											<td class="border-0 p-0">
												<div class="col-md-12 form-group p-0">
													<label class="control-label"><?php echo gettext('Please Insert Amount'); ?> :</label>
													<input type="text" name="settle_gross_amount" id="settle_gross_amount" value="" class="col-md-12 form-control form-control-lg" autocomplete="off">
												</div>
												<div id="settle_pay_error" style="padding-bottom: 10px; color: red;"></div>
											</td>
										</tr>
										<tr>
											<td class="col-md-12 text-center p-0">
												<div class="card py-2 border">
													<label class="float-left mb-0"><?php echo gettext('Your Amount In'); ?> <?php echo $system_currency; ?>:</label>
													<div id="settle_amt_in_currency" class="text-center font-weight-bold" style="color: green; font-size: 18px;">0 <?php echo $currency; ?> <?php echo gettext('is equals to'); ?> 0 <?php echo $system_currency; ?></div>
												</div>
											</td>
										</tr>
										<tr>
											<td class="form-group col-md-12 p-0">
												<label class="control-label"><?php echo gettext('Net Payable Amount in'); ?> <?php echo $system_currency; ?> :</label>
												<input type="text" name="settle_amount_usd" id="settle_amount_usd" value="0" readonly class="form-control form-control-lg col-md-12">
											</td>
										</tr>
										<tr>
											<td class="col-md-12 px-0 form-group h-auto m-0 border-0">
												<label class="control-label"><?php echo gettext('Special Note'); ?> :</label>
												<textarea class="form-control form-control-lg mit-20" rows="1" id="settleSpecialNote" name="settle_special_note"></textarea>
											</td>
										</tr>
									</table>
								</div>
							</td>
						</tr>
					</table>
					<div class="col-md-12 py-4 text-center">
						<div id="settle_pay_error2" style="padding-bottom: 10px; color: red;"></div>
						<?php if ($settle_paypal_allowed) { ?>
						<div class="mt-2">
							<input class="btn btn-success search_generate_bar" type="button" id="settle_pay_paypal_btn" value="<?php echo gettext('Pay With Paypal'); ?>">
						</div>
						<div class="mt-3">
							<img src="<?php echo base_url(); ?>assets/images/paypal_logo11.png" alt="PayPal">
						</div>
						<?php } ?>
					</div>
				</div>
			</section>
		</div>
	</div>
</div>
<?php } ?>

<? endblock() ?>
<? startblock('sidebar') ?>
<? endblock() ?>
<? end_extend() ?>  

