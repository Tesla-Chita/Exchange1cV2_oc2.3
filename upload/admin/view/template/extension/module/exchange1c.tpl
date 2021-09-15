<?php echo $header; ?><?php echo $column_left; ?>
<div id="content">
	<div class="page-header">
		<div class="container-fluid">
			<div class="pull-right">
				<button type="submit" form="form-exchange1c" class="btn btn-primary" id="form-save" data-toggle="tooltip" title="<?php echo $lang['button_save']; ?>"><i class="fa fa-save"></i></button>
				<button type="submit" form="form-exchange1c" class="btn btn-primary" id="form-save-refresh" data-toggle="tooltip" title="<?php echo $lang['button_apply']; ?>" onclick="$('#form-exchange1c').attr('action','<?php echo $refresh; ?>&refresh=1').submit()"><i class="fa fa-save"></i> + <i class="fa fa-refresh"></i></button>
				<a href="<?php echo $cancel; ?>" class="btn btn-default" data-toggle="tooltip" title="<?php echo $lang['button_cancel']; ?>" ><i class="fa fa-reply"></i></a>
			</div>
			<h1><?php echo $lang['heading_title']; ?></h1>
			<ul class="breadcrumb">
				<?php foreach ($breadcrumbs as $breadcrumb) { ?>
					<li><a href="<?php echo $breadcrumb['href']; ?>"><?php echo $breadcrumb['text']; ?></a></li>
				<?php } ?>
			</ul>
		</div>
	</div>
	<!-- <pre><?php echo print_r($settings, true) ?></pre>-->
	<div class="container-fluid">
		<?php if ($error_warning) { ?>
			<div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> <?php echo $error_warning; ?>
				<button type="button" class="close" data-dismiss="alert">&times;</button>
			</div>
		<?php } ?>
		<?php if ($text_info) { ?>
			<div class="alert alert-info">
				<i class="fa fa-info-circle"></i>
				<?php echo $text_info; ?>
			</div>
		<?php } ?>
		<div class="panel panel-default">
			<div class="panel-heading">
				<h3 class="panel-title"><i class="fa fa-pencil"></i> <?php echo $lang['text_edit']; ?></h3>
			</div>
			<div class="panel-body">
				<form action="index.php?route=extension/module/exchange1c/downloadOrders&token=<?php echo $token; ?>" method="post" enctype="multipart/form-data" id="form-download-orders" class="form-horizontal"></form>
				<form action="index.php?route=extension/module/exchange1c/export&token=<?php echo $token; ?>" method="post" enctype="multipart/form-data" id="form-export-module" class="form-horizontal"></form>
				<form action="index.php?route=extension/module/exchange1c/modeRemoveModule&token=<?php echo $token; ?>" method="post" enctype="multipart/form-data" id="form-remove-module" class="form-horizontal"></form>
				<form action="<?php echo $action; ?>" method="post" enctype="multipart/form-data" id="form-exchange1c" class="form-horizontal">
				<ul class="nav nav-tabs">
					<li class="active"><a href="#tab-general" data-toggle="tab"><?php echo $lang['text_tab_general']; ?></a></li>
					<li><a href="#tab-classifier" data-toggle="tab"><?php echo $lang['text_tab_classifier']; ?><?php echo " (".$classifier_count.")"; ?></a></li>
					<li><a href="#tab-product" data-toggle="tab"><?php echo $lang['text_tab_product']; ?><?php echo " (".$products_count.")"; ?></a></li>
					<li><a href="#tab-offers" data-toggle="tab"><?php echo $lang['text_tab_offers']; ?><?php echo " (".$offers_count.")"; ?></a></li>
					<li><a href="#tab-order" data-toggle="tab"><?php echo $lang['text_tab_order']; ?><?php echo " (".$orders_count.")"; ?></a></li>
					<li><a href="#tab-manual" data-toggle="tab"><?php echo $lang['text_tab_manual']; ?></a></li>
					<li><a href="#tab-info" data-toggle="tab"><?php echo $lang['text_tab_info']; ?></a></li>
					<li><a href="#tab-service" data-toggle="tab"><?php echo $lang['text_tab_service']; ?></a></li>
				</ul>
				<div class="tab-content">

					<!-- ОСНОВНЫЕ -->
					<div class="tab-pane active" id="tab-general">
						<?php echo $html_module_status; ?>
						<?php echo $html_export_system; ?>
						<?php echo $html_username; ?>
						<?php echo $html_password; ?>
						<?php echo $html_allow_ip; ?>
						<?php echo $html_upload_dirname; ?>

						<?php if ($zip_support) { ?>
						<div class="form-group">
							<?php echo $html_use_zip; ?>
						</div>
						<?php } ?>
						<?php echo $html_file_size_limit; ?>
						<legend class="title"><?php echo $lang['legend_log']; ?></legend>
						<?php echo $html_log_debug; ?>
						<?php echo $html_clear_log; ?>
						
					</div><!-- tab general -->

					<!-- КЛАССИФИКАТОРЫ -->
					<div class="tab-pane" id="tab-classifier">
						<legend class="title"><?php echo $lang['legend_categories']; ?><?php echo " (".$category_count.")"; ?></legend>
						<?php echo $html_categories_import ?>
						<?php echo $html_category_new_create ?>
						<?php echo $html_category_new_status_disable ?>
						<?php echo $html_category_sort_order_from_1c ?>
						<legend class="title"><?php echo $lang['legend_pcategories']; ?><?php echo " (".$pcategory_count.")"; ?></legend>
						<legend class="title"><?php echo $lang['legend_properties']; ?><?php echo " (".$property_count.")"; ?></legend>
					</div>
					<!-- КЛАССИФИКАТОРЫ -->

					<!-- ТОВАРЫ -->
					<div class="tab-pane" id="tab-product">

						<!-- ОСНОВНЫЕ -->
						<legend class="title"><?php echo $lang['legend_general']; ?></legend>
						<?php echo $html_product_sync_mode ?>
						<?php echo $html_product_description_import ?>
						<?php echo $html_product_category_import ?>
						<?php echo $html_product_category_fill_parent ?>
						<?php echo $html_product_manufacturer_import ?>
						<?php echo $html_product_images_import ?>
						<?php echo $html_product_taxes_import ?>
						<?php echo $html_product_new_status_disable ?>
						<?php echo $html_check_product_double_link; ?>

						<!-- РЕКВИЗИТЫ -->
						<legend class="title"><?php echo $lang['legend_requisites']; ?></legend>
						<div class="form-group">
							<label class="col-sm-11">
								<div class="label-description">В реквизитах также выгружаются "ВидНоменклатуры", "ТипНоменклатуры" и другие, которые вы включите в настройках обмена 1С. <br />
								Обработка их возможна с помощью установки расширений. Расширения можно заказать у разработчика либо написать самому, инструкция и примеры можно найти на <a href="http://exchange1c.tesla-chita.ru">сайте модуля</a> </div>
							</label>
						</div>
						<?php echo $html_product_name_from_requisite ?>

						<!-- СВОЙСТВА (АТРИБУТЫ) -->
						<legend class="title"><?php echo $lang['legend_attributes']; ?></legend>
						<?php echo $html_attribute_import ?>
						<?php echo $html_attribute_group_name ?>
						<?php echo $html_property_groups ?>

					</div>
					<!-- ТОВАРЫ -->

					<!-- ПРЕДЛОЖЕНИЯ -->
					<div class="tab-pane" id="tab-offers">
						<?php echo $html_offer_non_exist_error ?>
						<legend class="title"><?php echo $lang['legend_prices']; ?><?php echo " (".$prices_count.")"; ?></legend>
						<div class="table-responsive">
							<table id="price_type_config" class="table table-bordered table-hover">
								<thead>
									<tr>
										<td class="hidden">price_config_id</td>
										<td class="hidden">guid</td>
										<td class="col-sm-1 text-left"><?php echo $lang['text_config_price_count']; ?></td>
										<td class="col-sm-4 text-left"><?php echo $lang['text_name']; ?></td>
										<td class="col-sm-2 text-left"><?php echo $lang['text_purpose']; ?></td>
										<td class="col-sm-3 text-left"><?php echo $lang['text_for_customer_group']; ?></td>
										<td class="col-1 text-right"><?php echo $lang['text_quantity']; ?></td>
										<td class="col-1 text-right"><?php echo $lang['text_priority']; ?></td>
										<td class="col-1"><?php echo $lang['text_action']; ?></td>
									</tr>
								</thead>
								<tbody>
									<?php $price_row = 0; ?>
									<?php foreach ($exchange1c_price_type_config as $price_config_id => $obj) { ?>
										<tr id="price_type_config_row<?php echo $price_config_id; ?>">
											<td class="hidden"><input type="number" name="exchange1c_price_type_config[<?php echo $price_config_id; ?>][price_config_id]"  value="<?php echo $obj['price_config_id']; ?>"></td>
											<td class="text-left"><?php echo $obj['prices_count']; ?></td>
											<td class="text-left">
												<select class="form-control" name="exchange1c_price_type_config[<?php echo $price_config_id; ?>][guid]">
												<?php foreach ($price_type_list as $guid => $row) { ?>
												<?php if ($guid == $obj['guid']) { ?>
													<option value="<?php echo $guid; ?>" selected="selected"><?php echo $row['name']; ?></option>
												<?php } else { ?>
													<option value="<?php echo $guid; ?>"><?php echo $row['name']; ?></option>
												<?php } ?>
												<?php } ?>
												</select>
											</td>
											<td class="text-left">
												<select class="form-control" name="exchange1c_price_type_config[<?php echo $price_config_id; ?>][purpose]">
												<?php foreach ($price_purpose as $value => $name) { ?>
												<?php if ($value == $obj['purpose']) { ?>
													<option value="<?php echo $value; ?>" selected="selected"><?php echo $name; ?></option>
												<?php } else { ?>
													<option value="<?php echo $value; ?>"><?php echo $name; ?></option>
												<?php } ?>
												<?php } ?>
												</select>
											</td>
											<td class="text-left">
												<select class="form-control" name="exchange1c_price_type_config[<?php echo $price_config_id; ?>][customer_group_id]">
												<?php foreach ($customer_groups_list as $customer_group) { ?>
												<?php if ($customer_group['customer_group_id'] == $obj['customer_group_id']) { ?>
													<option value="<?php echo $customer_group['customer_group_id']; ?>" selected="selected"><?php echo $customer_group['name']; ?></option>
												<?php } else { ?>
													<option value="<?php echo $customer_group['customer_group_id']; ?>"><?php echo $customer_group['name']; ?></option>
												<?php } ?>
												<?php } ?>
												</select>
											</td>
											<td class="text-left"><input class="form-control" type="number" name="exchange1c_price_type_config[<?php echo $price_config_id; ?>][quantity]" value="<?php echo $obj['quantity']; ?>" size="2" /></td>
											<td class="text-left"><input class="form-control" type="number" name="exchange1c_price_type_config[<?php echo $price_config_id; ?>][priority]" value="<?php echo $obj['priority']; ?>" size="2" /></td>
											<td class="text-left">
												<button type="button" data-toggle="tooltip" title="<?php echo $lang['button_remove']; ?>" class="btn btn-danger" onclick="confirm('<?php echo $lang['text_confirm']; ?>') ? $('#price_type_config_row<?php echo $price_config_id; ?>').remove() : false;"><i class="fa fa-minus-circle"></i></button>
											</td>
										</tr>
										<?php //$price_row++; ?>
									<?php } ?>
								</tbody>
								<tfoot>
									<tr>
										<td colspan="6" class="text-right"></td>
										<td class="text-left">
											<button type="button" id="price_type_config_button_add" data-toggle="tooltip" title="Add price" class="btn btn-primary" onclick="addPrice()"><i class="fa fa-plus-circle"></i></button>
										</td>
									</tr>
								</tfoot>
							</table>
						</div> <!-- table price type discount-->
						<div class="alert alert-info">
							<i class="fa fa-info-circle"></i>
							<?php echo $lang['info_table_price_price_type'] ?>
						</div>

						<!-- ВАЛЮТЫ -->
						<legend class="title"><?php echo $lang['legend_currency']; ?></legend>
						<div class="table-responsive">
							<table id="exchange1c_currency" class="table table-bordered table-hover">
								<thead>
									<tr>
										<td class="col-sm-5 text-left"><?php echo $lang['text_currency_code']; ?></td>
										<td class="col-sm-5 text-left"><?php echo $lang['text_currency']; ?></td>
										<td class="col-sm-1"><?php echo $lang['text_action']; ?></td>
									</tr>
								</thead>
								<tbody>
									<?php $currency_row = 0; ?>
									<?php foreach ($exchange1c_currency as $obj) { ?>
										<tr id="currency_row<?php echo $currency_row; ?>">
											<td class="text-left"><input class="form-control" type="text" name="exchange1c_currency[<?php echo $currency_row; ?>][code]" value="<?php echo $obj['code']; ?>" /></td>
											<td class="text-left"><select class="form-control" name="exchange1c_currency[<?php echo $currency_row; ?>][currency_id]">
											<?php foreach ($currency_list as $currency_id => $currency_title) { ?>
												<?php if ($currency_id == $obj['currency_id']) { ?>
													<option value="<?php echo $currency_id; ?>" selected="selected"><?php echo $currency_title; ?></option>
												<?php } else { ?>
													<option value="<?php echo $currency_id; ?>"><?php echo $currency_title; ?></option>
												<?php } ?>
											<?php } ?>
											</select></td>
											<td class="text-left">
											<button type="button" data-toggle="tooltip" title="<?php echo $lang['button_remove']; ?>" class="btn btn-danger" onclick="confirm('<?php echo $lang['text_confirm']; ?>') ? $('#currency_row<?php echo $currency_row; ?>').remove() : false;"><i class="fa fa-minus-circle"></i></button>
											</td>
										</tr>
										<?php $currency_row++; ?>
									<?php } ?>
								</tbody>
							</table>
						</div> <!-- table currency-->
						
						
						<!-- ХАРАКТЕРИСТИКИ (ОПЦИИ) -->
						<legend class="title"><?php echo $lang['legend_features']; ?><?php echo " (".$features_count.")"; ?></legend>
						<?php echo $html_feature_import_mode ?>

						<!-- ОСТАТКИ -->
						<legend class="title"><?php echo $lang['legend_rests']; ?><?php echo " (".$rests_count.")"; ?></legend>
						<?php echo $html_product_stock_status_off; ?>
						<?php echo $html_product_stock_status_on; ?>

					</div>

					<!-- ЗАКАЗЫ -->
					<div class="tab-pane" id="tab-order">
						<legend><?php echo $lang['legend_order_export']; ?><?php echo " (".$orders_count.")"; ?></legend>
						<div class="form-group" id="exchange1c_begin_orders_export">
							<label class="col-sm-4 control-label">
								<?php echo $lang['entry_begin_orders_export'] ?>
								<div class="label-description"><?php echo $lang['desc_begin_orders_export'] ?></div>
							</label>
							<div class="col-sm-2">
								<input type="datetime-local" name="exchange1c_begin_orders_export" class="form-control" value="<?php echo $exchange1c_begin_orders_export ?>" />
							</div>
						</div>
						<div class="form-group">
                  			<label class="col-sm-4 control-label" for="input-export-order-status"><span data-toggle="tooltip" title="" data-original-title="<?php echo $desc_export_order_status ?>"><?php echo $entry_export_order_status ?></span></label>         
							<div class="col-sm-8">
								<div class="well well-sm" style="height: 150px; overflow: auto;">
									<?php foreach ($export_order_statuses as $value => $order_status) { ?>
									<div class="checkbox">
										<label><input type="checkbox" name="exchange1c_export_order_statuses[]" value="<?php echo $value ?>" <?php echo $order_status['checked'] ? 'checked="checked"' : ''?>>
											<?php echo $order_status['name'] ?> (id=<?php echo $value ?>)
										</label>
									</div>
									<?php } ?>
								</div>
							</div>
						</div>
						<?php echo $html_orders_export_only_pay ?>
						<?php echo $html_orders_export_only_shipping ?>
						<?php echo $html_orders_change_status_from_1c ?>
						<?php echo $html_order_reserve_product; ?>
					</div><!-- tab-order -->

					<!-- РУЧНАЯ ОБРАБОТКА -->
					<div class="tab-pane" id="tab-manual">
						<div class="form-group">
							<label class="col-sm-3 control-label" for="button-upload">
								<span title="" data-original-title="<?php echo $lang['help_upload']; ?>" data-toggle="tooltip"><?php echo $lang['entry_upload']; ?></span>
								<div class="label-description"><?php echo $lang['desc_upload_file']; ?></div>
							</label>
							<button id="button_upload" class="col-sm-2 btn btn-primary" type="button" data-loading-text="<?php echo $lang['button_upload']; ?>">
								<i class="fa fa-upload"></i>
								<?php echo $lang['button_upload']; ?>
							</button>
							<div class="col-sm-7">
								<label class="alert alert-info"><i class="fa fa-info-circle"></i> Upload max file size : <?php echo $upload_max_filesize; ?></label>
								<label class="alert alert-info"><i class="fa fa-info-circle"></i> Maximum size of POST data : <?php echo $post_max_size; ?></label>
							</div>
						</div>
						<div class="form-group">
							<label class="col-sm-3 control-label" for="button-download-orders">
								<span title="" data-original-title="<?php echo $lang['help_download_orders']; ?>" data-toggle="tooltip"><?php echo $lang['entry_download_orders']; ?></span>
								<div class="label-description"><?php echo $lang['desc_download_orders']; ?></div>
							</label>
							<button class="col-sm-2 btn btn-primary" form="form-download-orders" type="submit" data-loading-text="<?php echo $lang['button_download_orders']; ?>">
							<i class="fa fa-download"></i>
							<?php echo $lang['button_download']; ?>
							</button>
						</div>
					</div><!-- tab-manual -->

					<!-- СЕРВИСЫЕ ФУНКЦИИ -->
					<div class="tab-pane" id="tab-service">
						<div class="form-group">
							<?php echo $html_setting_default ?>
						</div>
						<div class="form-group">
							<?php echo $html_cleaning_db ?>
						</div>
						<div class="form-group">
							<?php echo $html_delete_import_data; ?>
						</div>
						<div class="form-group">
							<?php echo $html_remove_unised_manufacturers; ?>
						</div>
						<div class="form-group">
							<?php echo $html_remove_unised_images; ?>
						</div>
						<div class="form-group">
							<?php echo $html_update_catalog; ?>
						</div>
						<div class="form-group">
							<?php echo $html_remove_module; ?>
						</div>
						<div class="form-group">
							<?php echo $html_export_module; ?>
						</div>
					</div><!-- tab-service -->
			</form>

					<!-- ИНФОРМАЦИЯ -->
					<div class="tab-pane" id="tab-info">
						<div class="table-responsive">
							<table id="sessions" class="table table-bordered table-hover">
								<thead>
									<tr>
										<td class="col-sm-1 text-left">Status</td>
										<td class="col-sm-5 text-left">Session ID</td>
										<td class="col-sm-2">Expire</td>
										<td class="col-sm-1">Action</td>
									</tr>
								</thead>
								<tbody>
									<?php foreach ($sessions as $session_row => $obj) { ?>
										<tr id="session_row<?php echo $session_row; ?>">
											<td class="text-left"><?php echo $obj['status'] ? 'Active' : 'Close'; ?></td>
											<td class="text-left"><?php echo $obj['session_id']; ?></td>
											<td class="text-left"><?php echo $obj['expire']; ?></td>
											<td><button type="button" data-toggle="tooltip" title="<?php echo $lang['button_remove']; ?>" class="btn btn-danger" onclick="SessionDelete('<?php echo $obj['session_id']; ?>')"><i class="fa fa-minus-circle"></i></button></td>
										</tr>
									<?php } ?>
								</tbody>
							</table>
						</div> <!-- table -->
						<div class="form-group">
							<label class="col-sm-4 control-label"><?php echo $lang['entry_first_load_config'] ?>
								<div class="label-description"><?php echo $lang['desc_first_load_config'] ?></div>
							</label>
							<div class="col-sm-2">
								<div class="form-control"><?php echo $exchange1c_first_load_config ?></div>
							</div>
						</div>
						<div class="form-group">
							<label class="col-sm-4 control-label"><?php echo $lang['entry_first_import_data'] ?>
								<div class="label-description"><?php echo $lang['desc_first_import_data'] ?></div>
							</label>
							<div class="col-sm-2">
								<div class="form-control"><?php echo $exchange1c_first_import_data ?></div>
							</div>
						</div>
						<div class="form-group">
							<label class="col-sm-4 control-label"><?php echo $lang['entry_max_execution_time'] ?>
								<div class="label-description"><?php echo $lang['desc_max_execution_time'] ?></div>
							</label>
							<div class="col-sm-2">
								<div class="form-control"><?php echo $max_execution_time ?></div>
							</div>
						</div>
						<div class="form-group">
							<label class="col-sm-4 control-label"><?php echo $lang['entry_memory_limit'] ?>
							<div class="label-description"><?php echo $lang['desc_memory_limit'] ?></div>
							</label>
							<div class="col-sm-2">
								<div class="form-control"><?php echo $memory_limit ?></div>
							</div>
						</div>
					</div><!-- tab-info -->

	 			</div><!-- tab-content -->

 			</div><!-- panel-body-->
		</div><!-- panel panel-default -->
	</div><!-- container-fluid  -->
	<div style="text-align:center; opacity: .5">
		<p><?php echo sprintf($lang['text_module_name'], $version); ?>| <a href=http://exchange1c.tesla-chita.ru><?php echo $lang['text_homepage']; ?></a><br />
		<?php echo $lang['text_support']; ?></p>
	</div>
</div><!-- content -->


<script type="text/javascript"><!--

function SessionDelete(session_id) {
	$('#form-clean').remove();
	if (confirm('<?php echo $lang['text_confirm'] ?>')) {
		$('#session_row'+session_id).remove();
		$.ajax({
			url: 'index.php?route=extension/module/exchange1c/manualSessionDelete&sessid='+session_id+'&token=<?php echo $token; ?>',
			type: 'post',
			dataType: 'json',
			data: new FormData($('#form-clean')[0]),
			cache: false,
			contentType: false,
			processData: false,
			beforeSend: function() {
				$('#button-clean i').replaceWith('<i class="fa fa-circle-o-notch fa-spin"></i>');
				$('#button-clean').prop('disabled', true);
			},
			complete: function() {
				$('#button-clean i').replaceWith('<i class="fa fa-trash-o"></i>');
				$('#button-clean').prop('disabled', false);
			},
			success: function(json) {
				if (json['error']) {
					alert(json['error']);
				}

				if (json['success']) {
					alert(json['success']);
				}
			},
			error: function(xhr, ajaxOptions, thrownError) {
				alert(thrownError + "\r\n" + xhr.statusText + "\r\n" + xhr.responseText);
			}
		});
	};
};


$('#button_upload').on('click', function() {
	$('#form-upload').remove();

	$('body').prepend('<form enctype="multipart/form-data" id="form-upload" style="display: none;"><input type="file" name="file" value="" /></form>');

	$('#form-upload input[name=\'file\']').trigger('click');

	if (typeof timer != 'undefined') {
	clearInterval(timer);
	}

	timer = setInterval(function() {
		if ($('#form-upload input[name=\'file\']').val() != '') {
			clearInterval(timer);

			$.ajax({
				url: 'index.php?route=extension/module/exchange1c/manualImport&token=<?php echo $token; ?>',
				type: 'post',
				dataType: 'json',
				data: new FormData($('#form-upload')[0]),
				cache: false,
				contentType: false,
				processData: false,
				beforeSend: function() {
					$('#button-upload i').replaceWith('<i class="fa fa-circle-o-notch fa-spin"></i>');
					$('#button-upload').prop('disabled', true);
				},
				complete: function() {
					$('#button-upload i').replaceWith('<i class="fa fa-upload"></i>');
					$('#button-upload').prop('disabled', false);
				},
				success: function(json) {
					if (json['error']) {
						alert(json['error']);
					}

					if (json['success']) {
						alert(json['success']);
					}
				},
				error: function(xhr, ajaxOptions, thrownError) {
					alert(thrownError + "\r\n" + xhr.statusText + "\r\n" + xhr.responseText);
				}
			});
		}
	}, 500);
});


$('#button_cleaning_db').on('click', function() {
	$('#form-clean').remove();
	if (confirm('<?php echo $lang['text_confirm'] ?>')) {
		$.ajax({
			url: 'index.php?route=extension/module/exchange1c/manualCleaning&token=<?php echo $token; ?>',
			type: 'post',
			dataType: 'json',
			data: new FormData($('#form-clean')[0]),
			cache: false,
			contentType: false,
			processData: false,
			beforeSend: function() {
				$('#button-clean i').replaceWith('<i class="fa fa-circle-o-notch fa-spin"></i>');
				$('#button-clean').prop('disabled', true);
			},
			complete: function() {
				$('#button-clean i').replaceWith('<i class="fa fa-trash-o"></i>');
				$('#button-clean').prop('disabled', false);
			},
			success: function(json) {
				if (json['error']) {
					alert(json['error']);
				}

				if (json['success']) {
					alert(json['success']);
				}
			},
			error: function(xhr, ajaxOptions, thrownError) {
				alert(thrownError + "\r\n" + xhr.statusText + "\r\n" + xhr.responseText);
			}
		});
	}
});


$('#button_delete_import_data').on('click', function() {
	$('#form-clean').remove();
	if (confirm('<?php echo $lang['text_confirm'] ?>')) {
		$.ajax({
			url: 'index.php?route=extension/module/exchange1c/manualDeleteImportData&token=<?php echo $token; ?>',
			type: 'post',
			dataType: 'json',
			data: new FormData($('#form-clean')[0]),
			cache: false,
			contentType: false,
			processData: false,
			beforeSend: function() {
				$('#button-clean i').replaceWith('<i class="fa fa-circle-o-notch fa-spin"></i>');
				$('#button-clean').prop('disabled', true);
			},
			complete: function() {
				$('#button-clean i').replaceWith('<i class="fa fa-trash-o"></i>');
				$('#button-clean').prop('disabled', false);
			},
			success: function(json) {
				if (json['error']) {
					alert(json['error']);
				}

				if (json['success']) {
					alert(json['success']);
				}
			},
			error: function(xhr, ajaxOptions, thrownError) {
				alert(thrownError + "\r\n" + xhr.statusText + "\r\n" + xhr.responseText);
			}
		});
	}
});

$('#button_remove_unised_manufacturers').on('click', function() {
	$('#form-clean').remove();
	if (confirm('<?php echo $lang['text_confirm'] ?>')) {
		$.ajax({
			url: 'index.php?route=extension/module/exchange1c/manualRemoveUnisedManufacturers&token=<?php echo $token; ?>',
			type: 'post',
			dataType: 'json',
			data: new FormData($('#form-clean')[0]),
			cache: false,
			contentType: false,
			processData: false,
			beforeSend: function() {
				$('#button-clean i').replaceWith('<i class="fa fa-circle-o-notch fa-spin"></i>');
				$('#button-clean').prop('disabled', true);
			},
			complete: function() {
				$('#button-clean i').replaceWith('<i class="fa fa-trash-o"></i>');
				$('#button-clean').prop('disabled', false);
			},
			success: function(json) {
				if (json['error']) {
					alert(json['error']);
				}

				if (json['success']) {
					alert(json['success']);
				}
			},
			error: function(xhr, ajaxOptions, thrownError) {
				alert(thrownError + "\r\n" + xhr.statusText + "\r\n" + xhr.responseText);
			}
		});
	}
});

$('#button_remove_unised_images').on('click', function() {
	$('#form-clean').remove();
	if (confirm('<?php echo $lang['text_confirm'] ?>')) {
		$.ajax({
			url: 'index.php?route=extension/module/exchange1c/manualRemoveUnisedImages&token=<?php echo $token; ?>',
			type: 'post',
			dataType: 'json',
			data: new FormData($('#form-clean')[0]),
			cache: false,
			contentType: false,
			processData: false,
			beforeSend: function() {
				$('#button-clean i').replaceWith('<i class="fa fa-circle-o-notch fa-spin"></i>');
				$('#button-clean').prop('disabled', true);
			},
			complete: function() {
				$('#button-clean i').replaceWith('<i class="fa fa-trash-o"></i>');
				$('#button-clean').prop('disabled', false);
			},
			success: function(json) {
				if (json['error']) {
					alert(json['error']);
				}

				if (json['success']) {
					alert(json['success']);
				}
			},
			error: function(xhr, ajaxOptions, thrownError) {
				alert(thrownError + "\r\n" + xhr.statusText + "\r\n" + xhr.responseText);
			}
		});
	}
});

$('#button_setting_default').on('click', function() {
	$('#form-clean').remove();
	if (confirm('<?php echo $lang['text_confirm'] ?>')) {
		$.ajax({
			url: 'index.php?route=extension/module/exchange1c/defaultSettings&token=<?php echo $token; ?>',
			type: 'post',
			dataType: 'json',
			data: new FormData($('#form-clean')[0]),
			cache: false,
			contentType: false,
			processData: false,
			beforeSend: function() {
				$('#button-clean').prop('disabled', true);
			},
			complete: function() {
				$('#button-clean').prop('disabled', false);
			},
			success: function(json) {
				if (json['error']) {
					alert(json['error']);
				}

				if (json['success']) {
					alert(json['success']);
				}
			},
			error: function(xhr, ajaxOptions, thrownError) {
				alert(thrownError + "\r\n" + xhr.statusText + "\r\n" + xhr.responseText);
			}
		});
	}
});

$('#button_update_catalog').on('click', function() {
	$('#form-clean').remove();
	if (confirm('<?php echo $lang['text_confirm'] ?>')) {
		$.ajax({
			url: 'index.php?route=extension/module/exchange1c/updateCatalog&token=<?php echo $token; ?>',
			type: 'post',
			dataType: 'json',
			data: new FormData($('#form-clean')[0]),
			cache: false,
			contentType: false,
			processData: false,
			beforeSend: function() {
				$('#button-clean i').replaceWith('<i class="fa fa-circle-o-notch fa-spin"></i>');
				$('#button-clean').prop('disabled', true);
			},
			complete: function() {
				$('#button-clean i').replaceWith('<i class="fa fa-trash-o"></i>');
				$('#button-clean').prop('disabled', false);
			},
			success: function(json) {
				if (json['error']) {
					alert(json['error']);
				}

				if (json['success']) {
					alert(json['success']);
				}
			},
			error: function(xhr, ajaxOptions, thrownError) {
				alert(thrownError + "\r\n" + xhr.statusText + "\r\n" + xhr.responseText);
			}
		});
	}
});

$('#button_uninstall').on('click', function() {
	$('#form-clean').remove();
	if (confirm('<?php echo $lang['text_confirm'] ?>')) {
		$.ajax({
			url: 'index.php?route=extension/module/exchange1c/uninstall&token=<?php echo $token; ?>',
			type: 'post',
			dataType: 'json',
			data: new FormData($('#form-clean')[0]),
			cache: false,
			contentType: false,
			processData: false,
			beforeSend: function() {
				$('#button-clean i').replaceWith('<i class="fa fa-circle-o-notch fa-spin"></i>');
				$('#button-clean').prop('disabled', true);
			},
			complete: function() {
				$('#button-clean i').replaceWith('<i class="fa fa-trash-o"></i>');
				$('#button-clean').prop('disabled', false);
			},
			success: function(json) {
				if (json['error']) {
					alert(json['error']);
				}

				if (json['success']) {
					alert(json['success']);
				}
			},
			error: function(xhr, ajaxOptions, thrownError) {
				alert(thrownError + "\r\n" + xhr.statusText + "\r\n" + xhr.responseText);
			}
		});
	}
});

$('#button_export_module').on('click', function() {
	$('#form-clean').remove();
	if (confirm('<?php echo $lang['text_confirm'] ?>')) {
		$.ajax({
			url: 'index.php?route=extension/module/exchange1c/export&token=<?php echo $token; ?>',
			type: 'post',
			dataType: 'json',
			data: new FormData($('#form-clean')[0]),
			cache: false,
			contentType: false,
			processData: false,
			beforeSend: function() {
				$('#button-clean i').replaceWith('<i class="fa fa-circle-o-notch fa-spin"></i>');
				$('#button-clean').prop('disabled', true);
			},
			complete: function() {
				$('#button-clean i').replaceWith('<i class="fa fa-trash-o"></i>');
				$('#button-clean').prop('disabled', false);
			},
			success: function(json) {
				if (json['error']) {
					alert(json['error']);
				}

				if (json['success']) {
					alert(json['success']);
				}
			},
			error: function(xhr, ajaxOptions, thrownError) {
				alert(thrownError + "\r\n" + xhr.statusText + "\r\n" + xhr.responseText);
			}
		});
	}
});


var price_row = <?php echo $price_row; ?>;
 $('#price_type_config_button_add').on('click', function() {
	var priority = price_row + 1;
	html = '<tr id="price_type_config_row' + price_row + '">';
	html += '<td class="hidden"><input type="number" name="exchange1c_price_type_config[' + price_row + '][price_config_id]"  value="0"></td>';
	html += '<td class="text-left">0</td>';
	html += '<td class="text-left">';
	html += '<select class="form-control" name="exchange1c_price_type_config[' + price_row + '][guid]">';
	html += '<?php foreach ($price_type_list as $guid => $row) { ?>';
	html += '<option value="<?php echo $guid; ?>"><?php echo $row['name']; ?></option>';
	html +=	'<?php } ?></select>';
	html += '</td>';
	html += '<td class="text-left"><select class="form-control" name="exchange1c_price_type_config[' + price_row + '][purpose]">';
	<?php foreach ($price_purpose as $value => $name) { ?>
	html += '<option value="<?php echo $value; ?>"><?php echo $name; ?></option>';
	<?php } ?>
	html += '</select></td>';
	html += '<td class="text-left"><select class="form-control" name="exchange1c_price_type_config[' + price_row + '][customer_group_id]">';
	<?php foreach ($customer_groups_list as $customer_group) { ?>
	html += '<option value="<?php echo $customer_group['customer_group_id']; ?>"><?php echo $customer_group['name']; ?></option>';
	<?php } ?>
	html += '</select></td>';
	html += '<td class="text-left"><input class="form-control" type="number" name="exchange1c_price_type_config[' + price_row + '][quantity]" value="1" size="2" /></td>';
	html += '<td class="text-left"><input class="form-control" type="number" name="exchange1c_price_type_config[' + price_row + '][priority]" value="' + priority + '" size="2" /></td>';
	html += '<td class="text-left"><button type="button" data-toggle="tooltip" title="<?php echo $lang['button_remove']; ?>" class="btn btn-danger" onclick="confirm(\'<?php echo $lang['text_confirm']; ?>\') ? $(\'#price_type_config_row' + price_row + '\').remove() : false;"><i class="fa fa-minus-circle"></i></button></td>';
	html += '</tr>';

	$('#price_type_config tbody').append(html);
	$('select#customer_group').change();
	price_row++;
});

function update(ver) {
	if (confirm('<?php echo $lang['text_confirm'] ?>')) {
		$.ajax({
			url: 'index.php?route=extension/module/exchange1c/manualUpdate&version=' + ver + '&token=<?php echo $token; ?>',
			type: 'post',
			dataType: 'json',
			data: new FormData($('#form-exchange1c')[0]),
			cache: false,
			contentType: false,
			processData: false,
			success: function(json) {
				if (json['error']) {
					alert(json['error']);
				}

				if (json['success']) {
					alert(json['success']);
				}
			},
			error: function(xhr, ajaxOptions, thrownError) {
				alert(thrownError + "\r\n" + xhr.statusText + "\r\n" + xhr.responseText);
			}
		});
	}
}

function image_upload(field, thumb) {
	$('#dialog').remove();

	$('#content').prepend('<div id="dialog" style="padding: 3px 0px 0px 0px;"><iframe src="index.php?route=common/filemanager&token=<?php echo $token; ?>&field=' + encodeURIComponent(field) + '" style="padding:0; margin: 0; display: block; width: 100%; height: 100%;" frameborder="no" scrolling="auto"></iframe></div>');

	$('#dialog').dialog({
		title: '<?php echo $lang['text_image_manager']; ?>',
		close: function (event, ui) {
			if ($('#' + field).attr('value')) {
				$.ajax({
					url: 'index.php?route=common/filemanager/image&token=<?php echo $token; ?>&image=' + encodeURIComponent($('#' + field).val()),
					dataType: 'text',
					success: function(data) {
						$('#' + thumb).replaceWith('<img src="' + data + '" alt="" id="' + thumb + '" />');
					}
				});
			}
		},
		bgiframe: false,
		width: 800,
		height: 400,
		resizable: false,
		modal: false
	});
};

$('.numbersOnly').keyup(function () {
    this.value = this.value.replace(/[^0-9\.]/g,'');
});

$(document).ready(function() {

});

//--></script>

<?php echo $footer; ?>
