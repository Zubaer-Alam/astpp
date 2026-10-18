<? extend('master.php') ?>
<? startblock('extra_head') ?>
<script type="text/javascript" language="javascript">
function search_btn(){
    document.getElementById('origination_rate_batch_dlt').style.display = 'block';
}
var originationDeleteBusy = false;
function check_btn(){
    if (originationDeleteBusy) return;
    originationDeleteBusy = true;
    var button = $('#origination_rate_batch_dlt');
    var submitted = $('#origination_rate_list_search').serialize();
    button.prop('disabled', true);
    function finished(){ originationDeleteBusy = false; button.prop('disabled', false); }
    $.ajax({
        type: 'POST',
        url: '<?= base_url()?>rates/origination_rates_list_delete/',
        data: submitted,
        dataType: 'json',
        success: function(preview){
            if (!preview.ok) { alert(preview.message || 'Unable to prepare deletion.'); finished(); return; }
            if (!confirm('Delete exactly ' + preview.count + ' matching origination rates?')) { finished(); return; }
            if ($('#origination_rate_list_search').serialize() !== submitted) { alert('Search fields changed. Search and confirm again.'); finished(); return; }
            $.ajax({
                type: 'POST',
                url: '<?= base_url()?>rates/origination_rates_list_batch_delete/',
                data: submitted + '&delete_token=' + encodeURIComponent(preview.token),
                dataType: 'json',
                success: function(result){
                    if (!result.ok) { alert(result.message || 'Deletion was not completed.'); return; }
                    alert('Deleted ' + result.deleted + ' origination rates.');
                    // Restore this tab's search only after the explicit delete completes.
                    post_request_for_search('origination_rate_grid', '', 'origination_rate_list_search');
                },
                error: function(){ alert('Could not confirm the result. Do not retry blindly; check database counts.'); },
                complete: finished
            });
        },
        error: function(){ alert('Unable to prepare deletion. No delete request was sent.'); finished(); }
    });
}
    $(document).ready(function() {
        build_grid("origination_rate_grid","",<? echo $grid_fields; ?>,<? echo $grid_buttons; ?>);
        $('.checkall').click(function () {
            $('.chkRefNos').prop('checked', $(this).prop('checked')); //if you want to select/deselect checkboxes use this
        });
        $("#origination_rate_list_search_btn").click(function(){
            post_request_for_search("origination_rate_grid","","origination_rate_list_search");
        });        
        $("#id_reset").click(function(){
    document.getElementById('origination_rate_batch_dlt').style.display = 'none';
            clear_search_request("origination_rate_grid","");
            $("#pricelist_id_search_drp").html("<option value='' selected='selected'><?php echo gettext("--Select--")?></option>");
        });

         $("#batch_update").click(function(){
            submit_form("origination_rate_batch_update");
        })
        $("#id_batch_reset").click(function(){
            $(".update_drp").each(function() {
				var name=this.name;
				var split_name;
				if(name!=undefined){
					split_name=name.split("[");
					$('#'+split_name[0]).hide();
					$('#'+split_name[0]).val("");
					$('.update_drp').selectpicker('refresh');
				}else{
					$('.update_drp').val("1");
					$('.update_drp').selectpicker('refresh');
				}
            });
            $(".trunk_id").val("");
            $('.trunk_id').selectpicker('refresh');
            
            
        });
        
       $(".update_drp").change(function(){
           var inputid = this.name.split("[");
           if(this.value != "1"){
               $('#'+inputid[0]).show();
           }else{
               $('#'+inputid[0]).hide();
           }
       }).each(function(){
            var inputid = this.name.split("[");
            if(this.value != "1"){
                $('#'+inputid[0]).show();
            }else{
                $('#'+inputid[0]).hide();
            }
        });
        
        $(".reseller_id_search_drp").change(function(){
                if(this.value!=""){
					$.ajax({
						type:'POST',
						url: "<?= base_url()?>/accounts/customer_pricelist/",
						data:"reseller_id="+this.value, 
						success: function(response) {
							 $("#pricelist_id_search_drp").html(response);
							 $("#pricelist_id_search_drp").prepend("<option value='' selected='selected'><?php echo gettext("--Select--")?></option>");
							 $('.pricelist_id_search_drp').selectpicker('refresh');
						}
					});
				}else{
							$("#pricelist_id_search_drp").html("");
					}	
        });
        
        $(".reseller_id_search_drp").change();

    });
</script>

<? endblock() ?>

<? startblock('page-title') ?>
<?= $page_title ?>
<? endblock() ?>

<? startblock('content') ?>




<section class="slice color-three">
	<div class="w-section inverse p-0">
		<div class="col-12">
			<div class="portlet-content mb-4" id="search_bar"
				style="display: none;">
                        <?php echo $form_search; ?>
                </div>
		</div>
	</div>
</section>
<section class="slice color-three">
	<div class="w-section inverse p-0">
		<div class="col-12">
			<div class="portlet-content mb-4" id="update_bar"
				style="display: none;">
                        <?php echo $form_batch_update; ?>
                </div>
		</div>
	</div>
</section>
<section class="slice color-three pb-4">
	<div class="w-section inverse p-0">
		<div class="card col-md-12 pb-4">
			<form method="POST" enctype="multipart/form-data" id="ListForm">
				<table id="origination_rate_grid" align="left"
					style="display: none;"></table>
			</form>
		</div>
	</div>
</section>

<? endblock() ?>	
<? end_extend() ?>  
