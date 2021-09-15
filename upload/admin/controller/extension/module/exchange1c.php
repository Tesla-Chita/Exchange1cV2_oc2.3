<?php
class ControllerExtensionModuleExchange1c extends Controller {

	private $DB_INSTALLED = 0;
	public $error = array();
	public $VERSION_MODULE = "2.0.5b";
	public $CML_VERSION = "2.08";

	/**
	 * Пишет информацию в файл журнала
	 * @param	int				Уровень сообщения
	 * @param	string,object	Сообщение или объект
	 */
	private function log($message, $debug = "") {

		$msg = $message;
		$error = "";
		if (isset($this->session->data['error'])) {
			$error = $this->session->data['error'];
		}
		if (($this->config->get('exchange1c_log_debug') && $debug) || $error) {
			list ($di) = debug_backtrace();
			$line = sprintf("%04sC ", $di["line"]);

			if (is_array($message) || is_object($message)) {
				$msg = array();
				if ($error) {
					$msg['ERROR'] = $error;
				}
				$msg['TITLE'] = $debug;
				$msg['LINE'] = $line;
				$msg['LOG'] = print_r($message, true);
				$this->log->write(print_r($msg, true));
			} else {
				$this->log->write($line . $message);
			}

		} elseif (!$debug) {
			if (is_array($message) || is_object($message)) {
				$this->log->write(print_r($message, true));
			} else {
				$this->log->write($message);
			}
		}
		
	} // log()


	/**
	 * Выводит сообщение учетной системе
	 */
	private function echo_msg($data) {
		if (is_array($data)) {
			foreach ($data as $key => $message) {
				if (end($data) == $message) {
					echo $message;
				} else {
					echo $message . "\n";
				}
				$this->log("Ответ: " . $message, 2);
			}
		} else {
			echo $data;
			$this->log("Ответ: " . $data, 2);
		}
	} // echo_msg()


	/**
	 * Выводит форму текстового многострочного поля
	 */
	private function htmlTextarea($name, $value, $param) {

		if (!$value && isset($param['default'])) $value = $param['default'];

		$tmpl = '<textarea class="form-control" id="exchange1c_'.$name.'" name="exchange1c_'.$name.'" rows="6">'.$value.'</textarea>';

		return $this->htmlParam($name, $tmpl, $param);

	} // htmlTextarea()


	/**
	 * Выводит форму выбора значений
	 */
	private function htmlSelect($name, $value, $param) {

		//if (!$value && isset($param['default'])) $value = $param['default'];
		$disabled = isset($param['disabled']) ? ' disabled="true"' : '';

		$tmpl = '<select name="exchange1c_'.$name.'" id="exchange1c_'.$name.'" class="form-control"'.$disabled.'>';

		foreach ($param['options'] as $option => $text) {
			$selected = ($option == $value ? ' selected="selected"' : '');
			$tmpl .= '<option value="'.$option.'"'.$selected.'>'.$text.'</option>';
		}

		$tmpl .= '</select>';

		return $this->htmlParam($name, $tmpl, $param);

	} // htmlSelect()


	/**
	 * Выводит форму checkbox (вкл или выкл)
	 */
	private function htmlCheckbox($name, $value, $param) {

		$checked = $value == "1" ? ' checked = "checked"' : '';

		$tmpl = '<div class="checkbox">';
		$tmpl .= '<label><input type="checkbox" name="exchange1c_'.$name.'" value="'.$value.'"'.$checked.'></label>';
		$tmpl .= '</div>';

		return $this->htmlParam($name, $tmpl, $param);

	} // htmlCheckbox()


	/**
	 * Выводит форму radio (вкл или выкл)
	 */
	private function htmlRadio($name, $value, $param) {

		$checked_1 = $value == "1" ? ' checked = "checked"' : '';
		$checked_0 = $value == "0" ? ' checked = "checked"' : '';

		$tmpl = '<div>';
		$tmpl .= '<label class="radio-inline"><input type="radio" name="exchange1c_'.$name.'" value="1"'.$checked_1.'>&nbsp;'.$this->language->get('text_yes').'</label>';
		$tmpl .= '<label class="radio-inline"><input type="radio" name="exchange1c_'.$name.'" value="0"'.$checked_0.'>&nbsp;' . $this->language->get('text_no').'</label>';
		$tmpl .= '</div>';

		return $this->htmlParam($name, $tmpl, $param);

	} // htmlRadio()


	/**
	 * Формирует форму кнопки
	 */
	private function htmlButton($name, $value, $param) {

		$onclick = '';

		if (!empty($param['ver'])) {
			$onclick = ' onclick="update(' . $param['ver'] . ')"';
		}
		$form = '';
		if (isset($param['form'])) {
			$form = ' form="' . $param['form'] . '"';
		}
		$type = 'button';
		if (isset($param['type_button'])) {
			$type = $param['type_button'];
		}

		$icon = empty($param['icon']) ? '' : $param['icon'];
		$tmpl = '<button' . $onclick . $form . ' id="button_'.$name.'" class="col-sm-3 btn btn-primary" type="' . $type . '" data-loading-text="' . $this->language->get('entry_button_'.$name). '">';
		$tmpl .= $icon . ' ' . $this->language->get('text_button_'.$name) . '</button>';

		return $this->htmlParam($name, $tmpl, $param);

	} // htmlButton()


	/**
	 * Формирует форму поля ввода
	 */
	private function htmlInput($name, $value, $param, $type='text') {

		//if (empty($value) && !empty($param['default'])) $value = $param['default'];

		$disabled = isset($param['disabled']) ? ' disabled="true"' : '';

		if ($this->language->get('ph_'.$name) != 'ph_'.$name) {
			$placeholder = ' placeholder="' . $this->language->get('ph_'.$name) . '"';
		} else {
			$placeholder = '';
		}

		$tmpl = '<input class="form-control"' . $placeholder . ' type="'.$type.'" id="exchange1c_'.$name.'" name="exchange1c_'.$name.'" value="'.$value.'"'.$disabled.'>';

		return $this->htmlParam($name, $tmpl, $param);

	} // htmlInput()


	/**
	 * Формирует форму ...
	 */
	private function htmlParam($name, $html, $param) {

		$id = isset($param['id']) ? ' id="'.$param['id'].'"' : '';

		if ($this->language->get('desc_'.$name) != 'desc_'.$name) {
			$description =  '<div class="label-description">' . $this->language->get('desc_'.$name) . '</div>';
		} else {
			$description = '';
		}

		// Основной слой
		$tmpl = '<div class="form-group"'.$id.'>';

		// Левая часть
		$tmpl .= '<label class="col-sm-4 control-label">' . $this->language->get('entry_'.$name) . $description . '</label>';

		// Правая часть
		$tmpl .= '<div class="col-sm-8">';

		$tmpl .= $html;

		$tmpl .= '</div>'; // правая часть
		$tmpl .= '</div>'; // основной

		return $tmpl;

	} // HtmlParam()


	/**
	 * Проверка разрешения на изменение
	 */
	private function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/exchange1c')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}
		return !$this->error;
	} // validate()


	/**
	 * Функция при нажатии кнопки "записать и обновить"
	 */
	public function refresh() {
		$this->index(true);
	}


	/**
	 */
	public function status() {

    	echo '<div>Module Exchange1C 8.x</div>';
    	echo '<div>Status: installed / enable / in the development</div>';
    	echo '<div>Module Version 2.0 beta</div>';
    	echo '<div>Author Vitaly E. Kirillov</div>';
    	echo '<div>Module Site: <a target="_blank" href="http://exchange1c.tesla-chita.ru">exchange1c.tesla-chita.ru</a></div>';
		echo '<div>Support comand: type=catalog mode=checkauth,init,file,import</div>';
		echo '<div>Support comand: type=sale mode=init,query,file,import,info,success</div>';
		echo '<div>Support comand: type=get_catalog mode=init,query</div>';
		//echo '<div>Support comand: module=export,remove,status,cron_import</div>';
		
	} // status()


	private function arrayRecursiveDiff($aArray1, $aArray2) { 
	    $aReturn = array(); 

	    foreach ($aArray1 as $mKey => $mValue) { 
	        if (array_key_exists($mKey, $aArray2)) { 
	            if (is_array($mValue)) { 
	                $aRecursiveDiff = $this->arrayRecursiveDiff($mValue, $aArray2[$mKey]); 
	                if (count($aRecursiveDiff)) { $aReturn[$mKey] = $aRecursiveDiff; } 
	            } else { 
	                if ($mValue != $aArray2[$mKey]) { 
	                    $aReturn[$mKey] = $mValue; 
	                } 
	            } 
	        } else { 
	            $aReturn[$mKey] = $mValue;
	        } 
	    } 
	
	    return $aReturn; 
	}


	private function setOptions($settings = array())  {

		// Режимы работы модуля
		$module_status_list = array(
			'disable'			=> $this->language->get('text_disable')
		);
		if ($this->DB_INSTALLED) {
			$module_status_list['load_config'] = $this->language->get('text_load_config');
		}
		if ($this->config->get('exchange1c_first_load_config')) {
			$module_status_list['import'] = $this->language->get('text_import_data');
			$module_status_list['check'] = $this->language->get('text_check');
		}

		// Учетные системы
		$export_system_list = array(
			'1c_unf16' 	=> $this->language->get('text_1c_unf16'),
			'1c_ut11'  	=> $this->language->get('text_1c_ut11'),
			'1c_ut10.3' => $this->language->get('text_1c_ut10.3')
		);

		// Режим синхронизации товаров
		$product_sync_mode_list = array(
			'guid'  	=> $this->language->get('text_guid')
			,'sku'    	=> $this->language->get('text_sku')
			,'model'    => $this->language->get('text_model')
			,'name'		=> $this->language->get('text_name')
			,'ean'		=> $this->language->get('text_ean')
		);

		$feature_import_mode_list = array(
			'1'		=> $this->language->get('text_feature_import_standart'),
			'2'		=> $this->language->get('text_feature_import_property')
		);

		// Статусы товара
		$this->load->model('localisation/stock_status');
		$stock_statuses_info = $this->model_localisation_stock_status->getStockStatuses();
		$stock_status_list = array();
		$stock_status_list[] = "< " . $this->language->get('text_not_change') . " >";
		foreach ($stock_statuses_info as $status) {
			$stock_status_list[$status['stock_status_id']] = $status['name'];
		}

		// Список статусов заказов
		$this->load->model('localisation/order_status');
		$order_statuses_info = $this->model_localisation_order_status->getOrderStatuses();
		$order_status_list = array();
		$order_status_list[] = $this->language->get('text_not_change');
		foreach ($order_statuses_info as $order_status) {
			$order_status_list[$order_status['order_status_id']] = $order_status['name'];
		}

		$options = array(
		// Основные
			'module_status'								=> array('type' => 'select', 'options' => $module_status_list, 'default' => 'disable')
			,'export_system'							=> array('type' => 'select', 'options' => $export_system_list, 'default' => '1c_unf16')
			,'username'									=> array('type' => 'input')
			,'password'									=> array('type' => 'input',)
			,'allow_ip'									=> array('type' => 'textarea')
			,'use_zip'									=> array('type' => 'radio', 'default' => 1)
			,'file_size_limit'							=> array('type' => 'input', 'format' => 'int', 'default' => '')
			,'log_debug'								=> array('type' => 'radio', 'default' => 1)
			,'log_filename'								=> array('type' => 'input', 'default' => 'exchange1c.log')
			,'upload_dirname'							=> array('type' => 'input', 'default' => 'exchange1c')
			,'clear_log'								=> array('type' => 'radio', 'default' => 1)
		// Товары
			,'product_sync_mode'						=> array('type' => 'select', 'options' => $product_sync_mode_list, 'default' => 'name')
			,'product_description_import'				=> array('type' => 'radio', 'default' => 1)
			,'product_category_import'					=> array('type' => 'radio', 'default' => 1)
			,'product_category_fill_parent'				=> array('type' => 'radio', 'default' => 1)
			,'product_manufacturer_import'				=> array('type' => 'radio', 'default' => 1)
			,'product_images_import'					=> array('type' => 'radio', 'default' => 1)
			,'product_taxes_import'						=> array('type' => 'radio', 'default' => 0)
			,'product_new_status_disable'				=> array('type' => 'radio', 'default' => 0)
			,'product_name_from_requisite'				=> array('type' => 'radio', 'default' => 0)
			//,'product_no_images_not_import'				=> array('type' => 'radio', 'default' => 1)
			// Свойства
			,'attribute_import'							=> array('type' => 'radio', 'default' => 1)
			,'attribute_group_name'						=> array('type' => 'input', 'default' => 'Характеристики')
			,'property_groups'							=> array('type' => 'textarea')
			// Характеристики
			,'feature_import_mode'						=> array('type' => 'select', 'options' => $feature_import_mode_list, 'default' => '1')
		// Категории
			,'categories_import'						=> array('type' => 'radio', 'default' => 1)
			,'category_new_create'						=> array('type' => 'radio', 'default' => 1)
			,'category_new_status_disable'				=> array('type' => 'radio', 'default' => 0)
			,'category_sort_order_from_1c'				=> array('type' => 'radio', 'default' => 0)
		// Предложения
   			,'offer_non_exist_error'					=> array('type' => 'radio', 'default' => 1)
		// Цены
		// Остатки
			,'product_stock_status_off'					=> array('type'	=> 'select', 'options' => $stock_status_list)
			,'product_stock_status_on'					=> array('type'	=> 'select', 'options' => $stock_status_list)
		// Заказы
			,'begin_orders_export'						=> array('type' => 'datetime')
			,'order_status_exported'					=> array('type' => 'select', 'options' => $order_status_list, 'default' => '0')
			,'orders_export_only_pay'					=> array('type' => 'radio', 'default' => 0)
			,'orders_export_only_shipping'				=> array('type' => 'radio', 'default' => 0)
			,'order_reserve_product'					=> array('type' => 'radio', 'default' => 1)
			,'orders_change_status_from_1c'				=> array('type' => 'radio', 'default' => 0)
		// Дополнительные настройки
			,'check_product_double_link'				=> array('type' => 'radio', 'default' => 1)
		// Информационные
			,'first_load_config'						=> array('type' => 'input')
			,'first_import_data'						=> array('type' => 'input')
		);

		$options['setting_default']						= array('type' => 'button');
		$options['cleaning_db']							= array('type' => 'button', 'icon' => '<i class="fa fa-trash-o fa-lg"></i>');
		$options['delete_import_data']					= array('type' => 'button', 'icon' => '<i class="fa fa-trash-o fa-lg"></i>');
		$options['remove_unised_manufacturers']			= array('type' => 'button', 'icon' => '<i class="fa fa-trash-o fa-lg"></i>');
		$options['remove_unised_images']				= array('type' => 'button', 'icon' => '<i class="fa fa-trash-o fa-lg"></i>');
		$options['update_catalog']						= array('type' => 'button');
		$options['remove_module']						= array('type' => 'button', 'form' => 'form-remove-module', 'type_button' => 'submit');
		$options['export_module']						= array('type' => 'button', 'form' => 'form-export-module', 'type_button' => 'submit');

		if ($settings) {
			foreach ($options as $name => $param) {
				if (isset($param['default'])) {
					$value = $param['default'];
					if (isset($param['options'])){
						if (!isset($param['options'][$param['default']])) {
							$this->log($param['options']);
							$this->log($param['default']);
						}
						$value = $param['options'][$param['default']];
					}
					$settings['exchange1c_'.$name] = $value;
				}
			}
			$this->log($settings, "setOptions() = settings");
			return $settings;
		}

		$this->log($options, "setOptions() =options");
		return $options;

	} // setOptions()


	/**
	 * Основная функция
	 */
	public function index($refresh = false) {

		$data['lang'] = $this->load->language('extension/module/exchange1c');

		$this->load->model('tool/image');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');
		$this->load->model('extension/exchange1c');

		$data['text_info'] = "";
		$this->DB_INSTALLED = $this->model_extension_exchange1c->getModuleStatus();
		
		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			
			if (!$this->DB_INSTALLED) {
				$this->uninstall();
				$this->install();
				$this->request->post['exchange1c_module_status'] = 'load_config';
				$this->request->post['exchange1c_version'] = $this->VERSION_MODULE;
			} else {
				$this->request->post['exchange1c_version'] = $this->config->get('exchange1c_version');
			}
			
			if ($this->config->get('exchange1c_version') != $this->VERSION_MODULE) {
				$this->request->post['exchange1c_version'] = $this->VERSION_MODULE;
				$this->config->set('exchange1c_version', $this->VERSION_MODULE);
			}

			if (!isset($this->request->post['exchange1c_price_type_config'])) {
				$this->request->post['exchange1c_price_type_config'] = array();
			}
			$this->request->post['exchange1c_price_type_config'] = $this->model_extension_exchange1c->setPriceConfig($this->request->post['exchange1c_price_type_config']);

			$this->request->post['VERSION'] = VERSION;
			$this->request->post['exchange1c_table_fields'] = $this->model_extension_exchange1c->defineTableFields();
			
			$this->request->post['exchange1c_first_load_config'] = $this->config->get('exchange1c_first_load_config');
			$this->request->post['exchange1c_first_import_data'] = $this->config->get('exchange1c_first_import_data');

			$this->model_setting_setting->editSetting('exchange1c', $this->request->post);
			
			if (isset($this->request->post['exchange1c_export_order_statuses'])) {
				$this->config->set("exchange1c_export_order_statuses", $this->request->post['exchange1c_export_order_statuses']);
			}
			//$this->config->set("exchange1c_begin_orders_export", $this->request->post['exchange1c_begin_orders_export']);
			//$this->config->set("exchange1c_begin_orders_export", $this->request->post['exchange1c_begin_orders_export']);

			$this->session->data['success'] = $this->language->get('text_success');
			$data['text_info'] = "Настройки сохранены";
			$this->log($data['text_info']);
			
			if (!$refresh) {
				$this->response->redirect($this->url->link('extension/extension', 'token=' . $this->session->data['token'], 'SSL'));
			}
		}

		$data['version'] = $this->config->get('exchange1c_version');
		if (!$data['version']) {
			$data['version'] = "2.0.0";
		}

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = "";
		}

		$data['breadcrumbs'] = array();
		$data['breadcrumbs'][] = array(
			'text'		=> $this->language->get('text_home'),
			'href'		=> $this->url->link('common/home', 'token=' . $this->session->data['token'], 'SSL'),
			'separator'	=> false
		);
		$data['breadcrumbs'][] = array(
			'text'		=> $this->language->get('text_module'),
			'href'		=> $this->url->link('extension/extension', 'token=' . $this->session->data['token'], 'SSL'),
			'separator'	=> ' :: '
		);
		
		$data['breadcrumbs'][] = array(
			'text'      => $this->language->get('heading_title'),
			'href'      => $this->url->link('extension/module/exchange1c', 'token=' . $this->session->data['token'], 'SSL'),
			'separator' => ' :: '
		);

		$data['token'] = $this->session->data['token'];
		$data['refresh'] = $this->url->link('extension/module/exchange1c/refresh', 'token=' . $this->session->data['token'], 'SSL');
		//$data['uninstall'] = $this->url->link('extension/module/exchange1c/uninstall', 'token=' . $this->session->data['token'], 'SSL');
		$data['action'] = $this->url->link('extension/module/exchange1c', 'token=' . $this->session->data['token'], 'SSL');
		$data['cancel'] = $this->url->link('extension/extension', 'token=' . $this->session->data['token'] . '&type=module', 'SSL');

		// Валюты
		$this->load->model('localisation/currency');
		$currencies = $this->model_localisation_currency->getCurrencies();
		// Список валют для формы
		$data['currency_list'] = array();
		$data['currency_list'][0] = $this->language->get('text_not_select');;
		foreach ($currencies as $currency) {
			$data['currency_list'][$currency['currency_id']] = $currency['title'];
		}

		$yes_no_list = array(
			'on'	=> $this->language->get('text_on'),
			'off'	=> $this->language->get('text_off')
		);

		$options_type_list = array(
			'select'	=> $this->language->get('text_product_options_type_select'),
			'radio'		=> $this->language->get('text_product_options_type_radio')
		);

		$options = $this->setOptions();

		$data['exchange1c_detail_resize']			= false;
		$data['exchange1c_detail_height']			= 2000;
		$data['exchange1c_detail_width']			= 2000;
		$data['exchange1c_secure_1c_exchange']		= true;

		// Магазины
		if (isset($this->request->post['exchange1c_stores'])) {
			$data['exchange1c_stores'] = $this->request->post['exchange1c_stores'];
		} elseif ($this->config->get('exchange1c_stores')) {
			$data['exchange1c_stores'] = $this->config->get('exchange1c_stores');
		} else {
			$data['exchange1c_stores'] = array();
			$data['exchange1c_stores'][] = array(
				'store_id'	=> 0,
				'name'		=> ''
			);
		}
		
		foreach ($options as $name => $param) {

			$value = 0;
			if ($this->request->server['REQUEST_METHOD'] == 'POST') {
				if (isset($this->request->post['exchange1c_'.$name])) {
					$value = $this->request->post['exchange1c_'.$name];
				}
				$this->config->set('exchange1c_'.$name, $value);
			} else {
				$value = $this->config->get('exchange1c_'.$name);
			}

			$data['exchange1c_'.$name] = $value;
			$this->log($name." = ".$value." (".$this->config->get('exchange1c_'.$name).")", "index() name=value");

			if ($param['type'] == 'select') {
				$data['html_'.$name] = $this->htmlSelect($name, $value, $param);

			} elseif ($param['type'] == 'checkbox') {
				$data['html_'.$name] = $this->htmlCheckbox($name, $value, $param, $data[$name]);

			} elseif ($param['type'] == 'radio') {
				$data['html_'.$name] = $this->htmlRadio($name, $value, $param);

			} elseif ($param['type'] == 'input') {
				$data['html_'.$name] = $this->htmlInput($name, $value, $param);

			} elseif ($param['type'] == 'textarea') {
				$data['html_'.$name] = $this->htmlTextarea($name, $value, $param);

			} elseif ($param['type'] == 'button') {
				$data['html_'.$name] = $this->htmlButton($name, $value, $param);

			}

		} // foreach

		if (empty($data['exchange1c_price_type_config']) && $this->DB_INSTALLED) {
			$data['exchange1c_price_type_config'] = $this->model_extension_exchange1c->getPriceConfig();
		} else {
			$data['exchange1c_price_type_config'] = array();
		}
		
		if ($this->DB_INSTALLED) {
			$data['sessions'] = $this->sessionList();
		} else {
			$data['sessions'] = array();
			$data['error_warning'] = $this->language->get('text_module_not_install');
		}

		// Дата и время выгрузки заказов
//		if (isset($this->request->post['exchange1c_begin_order_export'])) {
//			$data['exchange1c_begin_order_export'] = $this->request->post['exchange1c_begin_order_export'];
//		} elseif ($this->config->get('exchange1c_begin_order_export')) {
//			$data['exchange1c_begin_order_export'] = strftime('%Y-%m-%dT%H:%M', strtotime($this->config->get('exchange1c_begin_order_export')));
//		} else {
//			$data['exchange1c_begin_order_export'] = strftime('%Y-%m-%dT%H:%M', strtotime('2000-01-01 00:00:00'));
//		}

		if ($this->DB_INSTALLED) {
			// Информация о количествах ценах
			$aDataCount = $this->model_extension_exchange1c->importDataCount();
			$this->log($aDataCount, "index=aDataCount");
			$data['classifier_count'] = $aDataCount['category'] + $aDataCount['pcategory'] + $aDataCount['property'];
			$data['category_count'] = $aDataCount['category'];
			$data['pcategory_count'] = $aDataCount['pcategory'];
			$data['property_count'] = $aDataCount['property'];
			$data['features_count'] = $aDataCount['features'];
			$data['rests_count'] = $aDataCount['rests'];
			$data['prices_count'] = array_sum($aDataCount['prices']);
			$data['products_count'] = $aDataCount['product'];
			$data['offers_count'] = $aDataCount['offers'];
	
			foreach ($data['exchange1c_price_type_config'] as $key => $price_type_config_row) {
				if (isset($aDataCount['prices'][$key]))
					$data['exchange1c_price_type_config'][$key]['prices_count'] = $aDataCount['prices'][$key];
				else
					$data['exchange1c_price_type_config'][$key]['prices_count'] = 0;
			}
			
			$data['orders_count'] = $aDataCount['orders'];

			// Сессии в сервисном меню
		} else {
			$data['classifier_count'] = 0;
			$data['category_count'] = 0;
			$data['pcategory_count'] = 0;
			$data['property_count'] = 0;
			$data['features_count'] = 0;
			$data['rests_count'] = 0;
			$data['prices_count'] = 0;
			$data['products_count'] = 0;
			$data['offers_count'] = 0;
			$data['orders_count'] = 0;
		}

		// Статусы заказов заказов
		$order_statuses = $this->model_extension_exchange1c->getOrderStatus();
		$data['export_order_statuses'] = array();
		foreach ($order_statuses as $order_status) {
			$data['export_order_statuses'][$order_status['order_status_id']] = array(
				'name'		=> $order_status['name'],
				'checked'	=> 0
			);
		}
		if (isset($this->request->post['exchange1c_export_order_statuses'])) {
			foreach($this->request->post['exchange1c_export_order_statuses'] as $order_status_id) {
				$data['export_order_statuses'][$order_status_id]['checked'] = 1;
			}
		} else {
			if (is_array($this->config->get('exchange1c_export_order_statuses'))) {
				foreach($this->config->get('exchange1c_export_order_statuses') as $order_status_id) {
					$data['export_order_statuses'][$order_status_id]['checked'] = 1;
				}
			}
		}
		$data['entry_export_order_status'] = $this->language->get('entry_export_order_status');
		$data['desc_export_order_status'] = $this->language->get('desc_export_order_status');

		// Сопоставление валюты (таблица формы)
		if (isset($this->request->post['exchange1c_currency'])) {
			$data['exchange1c_currency'] = $this->request->post['exchange1c_currency'];
		} elseif ($this->config->get('exchange1c_currency')) {
			$data['exchange1c_currency'] = $this->config->get('exchange1c_currency');
		} else {
			$data['exchange1c_currency'] = array();
		}

		// Таблица "Типы цен"
		if ($this->DB_INSTALLED) {
			$this->load->model('extension/exchange1c');
			$data['price_type_list'] = $this->model_extension_exchange1c->getPriceType();
		} else {
			$data['price_type_list'] = array();
		}
		
		// Список назначений цен
		$data['price_purpose'] = array(
			'B' => $this->language->get('text_price_base'),
			'D' => $this->language->get('text_price_discount'),
			'S'	=> $this->language->get('text_price_special')
		);

		$data['zip_support'] = class_exists('ZipArchive') ? true : false;

		// Группы покупателей
		$this->load->model('customer/customer_group');
		$data['customer_groups_list'] = $this->model_customer_customer_group->getCustomerGroups();

	 	// максимальный размер загружаемых файлов
		$data['lang']['text_max_filesize'] = sprintf($this->language->get('text_max_filesize'), @ini_get('max_file_uploads'));
		$data['upload_max_filesize'] = ini_get('upload_max_filesize');
		$data['post_max_size'] = ini_get('post_max_size');

	 	// информация о памяти
		$data['memory_limit'] = ini_get('memory_limit');
	 	// информация о времени выполнения PHP
		$data['max_execution_time'] = ini_get('max_execution_time');

		$data['settings'] = $this->model_setting_setting->getSetting('exchange1c', 0);

		// Вывод шаблона
		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/exchange1c', $data));

	} // index()


	/**
	 * Установка модуля
	 */
	public function install() {

		$message = "";
		$this->load->model('setting/setting');
		$settings = $this->model_setting_setting->getSetting('exchange1c', 0);
		
		$this->load->model('extension/exchange1c');
		$msg = $this->model_extension_exchange1c->createTables();
		$this->log($msg, 2);

		if (version_compare(VERSION, '2.3') >= 0) {
			$this->load->model('extension/extension');
			$this->model_extension_extension->install('module', 'exchange1c');
		}

		$settings['exchange1c_install'] 					= true;
		$settings['exchange1c_version'] 					= $this->VERSION_MODULE;
		$settings['exchange1c_name'] 						= 'Exchange 1C 8.x for OpenCart 2.x';
		$settings['exchange1c_CMS_version']					= VERSION;
		$settings['exchange1c_table_fields']				= $this->model_extension_exchange1c->defineTableFields();

		$this->model_setting_setting->editSetting('exchange1c', $settings);

		$this->load->model('extension/modification');
		$modification = $this->model_extension_modification->getModificationByCode('exchange1c');
		if ($modification) $this->model_extension_modification->enableModification($modification['modification_id']);

		$this->log->write("Включен модуль " . $this->module_name . " версии " . $settings['exchange1c_version']);
		$this->log->write($message);

	} // install()


	/**
	 * Деинсталляция
	 */
	public function uninstall() {

		$this->load->model('extension/exchange1c');
		$result = $this->model_extension_exchange1c->dropTables();
		$version = $this->config->get('exchange1c_version');

		//$this->load->model('extension/event');
		//$this->model_extension_event->deleteEvent('exchange1c');

		$this->load->model('setting/setting');
		$this->model_setting_setting->deleteSetting('exchange1c');

		$this->load->model('extension/module');
		$this->model_extension_module->deleteModule('exchange1c');

		$this->load->model('extension/modification');
		$modification = $this->model_extension_modification->getModificationByCode('exchange1c');
		if ($modification) $this->model_extension_modification->disableModification($modification['modification_id']);

		$this->log->write("Отключен модуль " . $this->module_name . " версии " . $version);
		$this->log->write($result);
		
		$this->log($this->request->get);

	} // uninstall()


	/**
	 **************************** ФУНКЦИИ РАБОТЫ С ФАЙЛАМИ И КАТАЛОГАМИ ******************************
	 */


	/**
	 * Очистка папку root рекурсивно
	 * Если self = true тогда будет удален каталог root
	 */
	private function cleanDir($root, $self = false) {

		if (is_file($root)) {
			unlink($root);

		} else {
			if (substr($root, -1) !=  DIRECTORY_SEPARATOR) {
				$root .= DIRECTORY_SEPARATOR;
			}

			$dir = dir($root);

			while ($file = $dir->read()) {
				if ($file == '.' || $file == '..')
					continue;

				if ($file == 'index.html')
					continue;

				$path = $root . $file;

				if (file_exists($path)) {
					if (is_file($path)) {
						unlink($path);
						continue;

					} elseif (is_dir($path)) {
						$this->cleanDir($path . DIRECTORY_SEPARATOR, true);
						continue;
					}
				}
			} // while
		}

		if ($self) {
			if(file_exists($root) && is_dir($root)) {
				rmdir($root); return 0;
			}
		}

		return 0;

	} // cleanDir()


	/**
	 */
	private function fileExtension($filename) {

    	return strtoupper(substr(strrchr($filename, '.'), 1));

	} // fileExtension()


	/**
	 * Создание каталогов по порядку в указанной папке
	 * $root - это директория, в конце обязательно должен быть разделитель "/" или "\"
	 * Решение взято из источника
	 * http://qaru.site/questions/280376/creating-a-file-inside-a-folder-that-doesnt-exist-yet-in-php
	 */
	private function createDirectories($root, $path) {

		$folders = explode(DIRECTORY_SEPARATOR, $path);

		// Если в конце строки указан файл, то убираем его
		if (substr($path, -1) != DIRECTORY_SEPARATOR) {
	 		array_pop($folders);
		}

		$this->log($folders);

		$current_path = $root;

		foreach ($folders as $folder) {

			if (empty($folder)) continue;

			$current_path .= $folder . DIRECTORY_SEPARATOR;

			if (!file_exists($current_path)) {
				if (!mkdir($current_path, 0775)) {
					$this->log("Ошибка создания каталога: " . $current_path);
					return false;
				}
				$this->log("Создан каталог: " . $current_path, 2);
			}
		}

		return true;

	}  // createDirectories()


	/**
	 * 
	 */
	private function getFolderLevel($path) {

		$arPath = explode(DIRECTORY_SEPARATOR, $path);
		$arPath = array_diff($arPath, array('', NULL, false));
		//$this->log($arPath);
		
		return count($arPath);

	} // getFolderLevel()


	/**
	 * Проверяет наличие директории и вслучае отсутствия, создает ее
	 */
	private function createDirectory($path) {

		if (file_exists($path)) {
			if (is_dir($path)) {
				return -1;

			} else {
				return -2;
			}
		}
		
		if (!@mkdir($path, 0775)) {
			return 0;
		}
		
		return 1;

	} // createDirectory()


	/**
	 * Запиисывает файл на диск, если файл существует, то перезаписывает его
	 */
	private function createFile($zip_entry, $file) {

		$dump = zip_entry_read($zip_entry, zip_entry_filesize($zip_entry));

		if (file_exists($file)) {
			unlink($file);
		}

		$fd = @fopen($file, "wb");
		if ($fd === false) {
			return 0;
		}
		
		@fwrite($fd, $dump);
		@fclose($fd);
		
		return 1;

	} // createFile()


	/**
	 * Полная распаковка ZIP архива с каталогами
	 */
	private function extractZipV2($filename, $extract_dir) {

		$result = array(
			'error'				=> "",
			'error_description'	=> "",
			'xml_files'			=> array()
		);
		
		// Массив XML файлов
		$xml_files = array();
		$xml_goods = array();
		$xml_properties = array();
		
		$zipArc = zip_open($filename);
		if (!is_resource($zipArc)) {
			return $xml_files;
		}

		while ($zip_entry = zip_read($zipArc)) {
			$path = zip_entry_name($zip_entry);
			$level = $this->getFolderLevel($path);
			$this->log($path, 2);
			
			// Каталог
			if (substr($path, -1) == DIRECTORY_SEPARATOR) {

				$this->log($path);
				$success = $this->createDirectory($extract_dir . $path);
				if (!$success) {
					$result['error'] = "1567";
					$result['error_desc'] = "Ошибка создания каталога: " . $path;
					return $result;
				}

			// Файл
			} elseif (zip_entry_open($zipArc, $zip_entry, "r")) {

				// Расширение файла
				$file_extension = $this->fileExtension($path);
				//$this->log($file_extension, 2);
				
				$this->log($level);
				$this->log($path);
				$success = $this->createFile($zip_entry, $extract_dir . $path);
				if (!$success) {
					$result['error'] = "1582";
					$result['error_desc'] = "Ошибка создания файла: " . $path;
					return $result; 
				}
				
				if ($file_extension == "xml") {

					$pos = stripos($path, 'goods/');
					if ($pos !== false) {
						$xml_goods[] = $path;
						continue;
					}

					$pos = stripos($path, 'properties/');
					if ($pos !== false) {
						$xml_properties[] = $path;
						continue;
					}

					$xml_files[] = $path;
				}
				

			}
		}
		
		sort($xml_files);
		sort($xml_goods);
		sort($xml_properties);
		
		$this->log($xml_files);
		$this->log($xml_properties);
		$this->log($xml_goods);

		foreach ($xml_properties as $xml) {
			$xml_files[] = $xml;
		}
		
		foreach ($xml_goods as $xml) {
			$xml_files[] = $xml;
		}
		
		$this->log($xml_files);
		
		$result['xml_files'] = $xml_files;

		return $result;

	} // extractZipV2()


	/**
	 * Полная распаковка ZIP архива с каталогами
	 */
	private function extractZipV3($filename, $extract_dir) {
		
		if ($this->fileExtension($filename) != "ZIP") {
			return true;
		}

		$filename = DIR_UPLOAD. $this->session->data['upload_dirname'] . $filename;

		if (!file_exists($filename)) {
			$this->session->data['error'] = "C040";
			$this->log("Файл отсутствует '" . $filename ."'");
			$this->log($this->session);
			return false;
		}
		
		$zip = new ZipArchive;
		$xml_files = array();
		if ($zip->open($filename) === TRUE) {
		    $zip->extractTo(DIR_UPLOAD . $extract_dir);
		    $zip->close();

			// Запишем все XML файлы
			$zipArc = zip_open($filename);
			while ($zip_entry = zip_read($zipArc)) {

				$path = zip_entry_name($zip_entry);
				$this->log($path, "extractZipV3() =path");
				if ($this->fileExtension($path) == "XML") {
					$xml_files[] = $path;
				}
			}
		    $this->session->data['extract_xml_files'] = $xml_files;

		} else {
			$this->session->data['error'] = "C041";
			$this->log("Ошибка чтения архива '" . $filename ."'");
			$this->log($this->session);
		    unlink($filename);
			return false;
		}
		
	    unlink($filename);
		return true;

	} // extractZipV3


    /**
     * Создает новую сессию в базе данных, и удаляет просроченные
     */
    private function sessionStart() {

		$expire = date('Y-m-d H:i:s', time() + ini_get('session.gc_maxlifetime'));
		$this->db->query("DELETE FROM 1c_session WHERE expire < '" . $expire . "'");

		$this->session->data['error'] = "";
		$this->db->query("INSERT INTO 1c_session SET 
			session_id = '" . $this->session->session_id . "',
			data = '" . $this->db->escape(json_encode($this->session->data)) . "',
			expire = '" . $expire . "',
			status = 1"
		);

    } // sessionStart()


    /**
     * Запись сессии в базу данных
    */
    private function sessionWrite($status = 1) {

		$expire = date('Y-m-d H:i:s', time() + ini_get('session.gc_maxlifetime'));

		if ($this->session->data['error']) {
			$status = 0;
		}

		$this->db->query("UPDATE 1c_session SET
			data = '" . $this->db->escape(json_encode($this->session->data)) . "',
			expire = '" . $expire . "',
			status = '" . $status . "'
			WHERE session_id = '" . $this->session->session_id . "'"
		);

		if ($status) {
			$this->log("сессия обновлена, активна:", "sessionWrite");
		} else {
			$this->log("сессия обновлена, закрыта:", "sessionWrite");
		}
		$this->log($this->session->data, "sessionWrite=session->data");

    } // sessionUpdate()


    /**
     * Список сессий
    */
    private function sessionList() {

		$data = array();
		$query = $this->db->query("SELECT * FROM 1c_session");
		if ($query->num_rows) {
			foreach($query->rows as $session) {
				$data[$session['session_id']] = $session;
			}
		}
		return $data;

    } // sessionList()


    /**
     * Удаление сессии
    */
    private function sessionDelete($sessiod_id) {

		$this->db->query("DELETE FROM 1c_session WHERE session_id = '" . $this->db->escape($sessiod_id) . "'");
		return true;

    } // sessionDelete()


    /**
     * Возвращает сессию в базе по токену
     */
    private function sessionGet() {
    	
		if (!isset($_COOKIE['sess_id'])) {
			$this->session->data['error'] = "C020";
			$this->log($this->session, "Сессия не получена либо нет поддержки куки. sessionGet()");
			return false;
		}
		
		// Если активных сессий более одной, то отказ
		$query = $this->db->query("SELECT status FROM 1c_session WHERE status = 1");
		if ($query->num_rows > 1) {
			$this->session->data['error'] = "C022";
			$this->log($this->session, "В настоящий момент уже есть активная сессия. sessionGet()");
			return false;
		}
		

		$this->session->session_id = $_COOKIE['sess_id'];
		$query = $this->db->query("SELECT status, data FROM 1c_session WHERE session_id = " . (int)$this->session->session_id);
		if (!$query->num_rows) {
			$this->session->data['error'] = "C021";
			$this->log($this->session, "Сессия не найдена в базе. sessionGet()");
			return false;
		}
		
		$this->session->data = json_decode($query->row['data'], true);
		return true;

    } // sessionGet()

	/**
	 ******************************** ФУНКЦИИ АВТОРИЗАЦИИ МОДУЛЯ С 1С:ПРЕДПРИЯТИЕ ********************************
 	*/

	/**
	 * Проверка доступа с IP адреса
	 */
	private function checkAccess() {

		// Разрешен ли IP
		$config_allow_ips = $this->config->get('exchange1c_allow_ip');

		if ($config_allow_ips != '') {
			$ip = $_SERVER['REMOTE_ADDR'];
			$allow_ips = explode("\r\n", $config_allow_ips);
			foreach ($allow_ips as $allow_ip) {
				$length = strlen($allow_ip);
				if (substr($ip,0,$length) == $allow_ip) {
					return true;
				}
			}

		} else {
			return true;
		}
		return false;

	} // checkAccess()


	/**
	 * Алгортим описан https://dev.1c-bitrix.ru/api_help/sale/algorithms/data_2_site.php
	 * Авторизация на сайте
	 */
	private function checkauth() {

		// Проверяем включен или нет модуль
		if ($this->config->get('exchange1c_module_status') == 'disable') {
			$this->session->data['error'] = "C010";
			$this->log("Модуль отключен, обмен невозможен с IP адреса " . $_SERVER['REMOTE_ADDR'] . ". checkauth()", "checkauth");
			return false;
		}

		if (!$this->checkAccess()) {
			$this->session->data['error'] = "C011";
			$this->log("Доступ запрещен с IP адреса " . $_SERVER['REMOTE_ADDR'] . ". checkauth()", "checkauth");
			return false;
		}

		// определение логина и пароля
		$auth_user = '';
		$auth_pw = '';

		// Определение авторизации на сервере
		if (!isset($_SERVER['PHP_AUTH_USER'])) {
			// Определяем пользователя
			if (isset($_SERVER["REMOTE_USER"])) {
				$remote_user = $_SERVER["REMOTE_USER"];
			} elseif (isset($_SERVER["REDIRECT_REMOTE_USER"])) {
				$remote_user = $_SERVER["REMOTE_USER"] ? $_SERVER["REMOTE_USER"]: $_SERVER["REDIRECT_REMOTE_USER"];
			} elseif (isset($_SERVER["REDIRECT_HTTP_AUTHORIZATION"])) {
				$remote_user = $_SERVER["REDIRECT_HTTP_AUTHORIZATION"];
			}

			// Если удалось установить пользователя, тогда раскодируем
			if (isset($remote_user)) {
				$strTmp = base64_decode(substr($remote_user,6));
				if($strTmp)
					list($auth_user, $auth_pw) = explode(':', $strTmp);
			}
		} else {
			$auth_user = $_SERVER['PHP_AUTH_USER'];
			$auth_pw = $_SERVER['PHP_AUTH_PW'];
		}

		// Создаем ключ сессии
		$this->session->start();
		if ($auth_user == $this->config->get('exchange1c_username') && ($auth_pw == $this->config->get('exchange1c_password'))) {
			return true;
		}

		$this->session->data['error'] = "C012";
		$this->log("Ошибка авторизации, user: " . $auth_user . ". checkauth()", "checkauth");
		return false;

	} // checkauth()


	/**
	 ******************************** ЗАПРОС ПАРАМЕТРОВ САЙТА ********************************
	 */


	/**
	 * Переводит значение из килобайт, мегабат и гигабайт в байты
	 */
	private function formatSize($size) {
		if (empty($size)) {
			return 0;
		}
		$type = substr($size, -1);
		if (!is_numeric($type)) {
			$size = (integer)$size;
			switch ($type) {
				case 'K': $size = $size*1024;
					break;
				case 'M': $size = $size*1024*1024;
					break;
				case 'G': $size = $size*1024*1024*1024;
					break;
			}
			return $size;
		}
		return (int)$size;

	} // formatSize()


	/**
	 * Возвращает максимальный объем файла в байта для загрузки
	 */
	private function getPostMaxFileSize() {

		$size = $this->formatSize(ini_get('post_max_size'));
		$this->log("POST_MAX_SIZE: " . $size, "getPostMaxFileSize");

		$size_max_manual = $this->formatSize($this->config->get('exchange1c_file_max_size'));
		if ($size_max_manual) {
			$this->log("POST_MAX_SIZE (переопределен в настройках): " . $size_max_manual, "getPostMaxFileSize");
			if ($size_max_manual < $size) {
				$size = $size_max_manual;
			}
		}

		return $size;

	} //getPostMaxFileSize()


	/**
	 */
	public function modeSaleQuery() {

		$this->log("Запрос Sale Query", "modeSaleQuery");

		if (!$this->sessionGet()) {
			$this->echo_msg(array(
				"failure",
				"ERROR: " . $this->session->data['error']
			));
			exit;
		}
		$this->sessionWrite(1);


		$this->session->data['type'] = $this->request->get['type'];
		$this->session->data['mode'] = $this->request->get['mode'];

    	$this->log($this->session->data, "modeSaleQuery");
    	
    	// Подготовить список заказов
		$this->load->model('extension/exchange1c');
    	$orders = $this->model_extension_exchange1c->ordersExport();
    	
		// Формируем заголовок
		$root = '<?xml version="1.0" encoding="utf-8"?><КоммерческаяИнформация ВерсияСхемы="2.10" ДатаФормирования="' . date('Y-m-d', time()) . '" />';

        $root_xml = new SimpleXMLElement($root);
        $xml = $this->array_to_xml($orders, $root_xml);
        $this->log($xml, "modeSaleQuery=xml");

        // Проверка на запись файлов в кэш
        if (@is_writable(DIR_DOWNLOAD)) {
            // запись заказа в файл
            $f_order = @fopen(DIR_DOWNLOAD . 'orders.xml', 'w');
            if (!$f_order)             {
                $this->log("Нет доступа для записи в папку: " . DIR_DOWNLOAD, "modeSaleQuery");
            } else {
                fwrite($f_order, $xml->asXML());
                fclose($f_order);
            }
        } else {
            $this->log("Папка " . DIR_DOWNLOAD . " не доступна для записи, файл заказов не может быть сохранен!", "modeSaleQuery");
        }

		echo $xml->asXML();
        //return $xml->asXML();

	} // modeSaleQuery


	/**
	 */
	public function modeSaleSuccess() {

		$this->log("Запрос Sale Success", "modeSaleSuccess");

		if (!$this->sessionGet()) {
			$this->echo_msg(array(
				"failure",
				"ERROR: " . $this->session->data['error']
			));
			exit;
		}
		$this->sessionWrite(1);

		$this->session->data['type'] = $this->request->get['type'];
		$this->session->data['mode'] = $this->request->get['mode'];

    	$this->log($this->session->data, "modeSaleSuccess");
    	
		$this->load->model('extension/exchange1c');
		$result = $this->model_extension_exchange1c->ordersExportSuccess();

		if($result){

			$this->load->model('setting/setting');
			$config = $this->model_setting_setting->getSetting('exchange1c');
			$config['exchange1c_order_date'] = date('Y-m-d H:i:s');
			$this->model_setting_setting->editSetting('exchange1c', $config);
			$config['exchange1c_order_date'] = $this->config->get('exchange1c_order_date');
		} else {
			$this->log("Нет заказов для выгрузки в учетную систему");
		}

		$this->sessionWrite(0);
		$this->echo_message(1,$result);
	} //modeSaleQuery()   	


    /**
     * Конвертирует массив в XML
     *
     * @param	array				data
     * @param	SimpleXMLElement	XML
     * @return	XML
     */
    function array_to_xml($data, &$xml) {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if (!is_numeric($key)) {
                    $subnode = $xml->addChild(preg_replace('/\d/', '', $key));
                    $this->array_to_xml($value, $subnode);
                }
            } else {
                $xml->addChild($key, $value);
            }
        }
        return $xml;
    } // array_to_xml()


	/**
	 * ============================ Запросы 1С =========================
	 */


	/**
	 */
	public function modeSaleInfo() {

		$this->log("Запрос Sale Info", "modeSaleInfo");

		if (!$this->sessionGet()) {
			$this->echo_msg(array("failure", "ERROR: " . $this->session->data['error']));
			exit;
		}

		$this->session->data['type'] = $this->request->get['type'];
		$this->session->data['mode'] = $this->request->get['mode'];
		
		$this->log($this->request->get, "modeSaleInfo");

		// Сохранение сессии перед выполнением шагов
		$this->sessionWrite(1);
		$this->log($this->session, "modeSaleInfo");

		$this->load->model('extension/exchange1c');

    	// Формируем XML файл
    	$info = array();
    	$info['Статусы'] = $this->model_extension_exchange1c->orderStatus();
    	$info['ПлатежныеСистемы'] = $this->model_extension_exchange1c->payMethod();
    	$info['СлужбыДоставки'] = $this->model_extension_exchange1c->shippingMethod();
    	
    	
		// Формируем заголовок
		$root = '<?xml version="1.0" encoding="utf-8"?><КоммерческаяИнформация ВерсияСхемы="2.10" ДатаФормирования="' . date('Y-m-d', time()) . '" />';
		$root_xml = new SimpleXMLElement($root);
		$xml = $this->array_to_xml($info, $root_xml);

        // Проверка на запись файлов в кэш
        if (@is_writable(DIR_DOWNLOAD)) {
            // запись заказа в файл
            $f_order = @fopen(DIR_DOWNLOAD . 'sale_info.xml', 'w');
            if (!$f_order)             {
                $this->log("Нет доступа для записи в папку: " . DIR_DOWNLOAD, "modeSaleInfo");
            } else {
                fwrite($f_order, $xml->asXML());
                fclose($f_order);
            }
        } else {
            $this->log("Папка " . DIR_DOWNLOAD . " не доступна для записи, файл заказов не может быть сохранен!", 1);
        }

		echo $xml->asXML();
		$this->log($this->session, "modeSaleInfo");
		$this->sessionWrite(0);

	} // modeSaleInfo


	/**
	 * Материал взят с сайта: https://dev.1c-bitrix.ru/api_help/sale/algorithms/data_2_site.php
	 * При успешной авторизации сайт возвращает временный файл с данными:
	 * во 2-ой строке содержится имя куки файла;
	 * в 3-ей строке содержится значение куки файла;
	 * в 4-ой строке содержится ключ сессии обмена (CSRF);
	 * в 5-ой строке содержится дата и время сервера сайта (CSRF).
	 */
	public function modeCheckauth() {

		$this->log("Запрос Checkauth", "modeCheckauth");

		// Чистка файла журнала (временно)
		$this->clearLog();

		// Проверка авторизации в модуле и генерация нового ключа
		$success = $this->checkauth();

		if (!$success) {
			$this->echo_msg(array(
				"failure",
				$this->session->data['error']
			));
			$this->log($this->session, "modeCheckauth");
		}

		$msg = array(
			"success",
			"sess_id",
			$this->session->session_id,
			"sessid=" . $this->session->session_id,
			"date=" . date('Y-m-d H:i:s')
		);
		$this->echo_msg($msg);
		$this->log("Авторизация прошла успешно с IP адреса " . $_SERVER['REMOTE_ADDR'], "modeCheckauth");

		// Массив данных
		$this->session->data['type'] = $this->request->get['type'];
		$this->session->data['mode'] = $this->request->get['mode'];
		$this->sessionStart();

	} // modeCheckauth()


	/**
	 * Этот запрос с 1С следует после ModeCheckAuth где была проверка авторизации и был выдан ключ
	 * Ключ после успешной авторизации сохраняется в базе
	 * Материал взят с сайта: https://dev.1c-bitrix.ru/api_help/sale/algorithms/data_2_site.php
	 * Инициализация сессии
	 * При успешной инициализации возвращает временный файл с данными:
	 * в 1-ой строке содержится признак, разрешен ли Zip (zip=yes);
	 * во 2-ой строке содержится информация об ограничении файлов по размеру (file_limit=);
	 * в 3-ейй строке содержится ключ сессии обмена (sessid=);
	 * в 4-ой строке содержится версия CommerceML (version=).
	 */
	public function modeInit() {
		
		$this->log("Запрос Init", "modeCatalogInit");
		$msg = array(
			"failure"
		);

		if (!$this->sessionGet()) {
			$msg[1]	= "ERROR: " . $this->session->data['error'];
			$this->echo_msg($msg);
			$this->sessionWrite(0);
			exit;
		}
		
		$this->session->data['type'] = $this->request->get['type'];
		$this->session->data['mode'] = $this->request->get['mode'];

		// Сессия найдена, авторизация пройдена, ответим 1С
		$this->session->data['version']	= isset($this->request->get['version']) ? $this->request->get['version'] : $this->CML_VERSION;

		$this->session->data['use_zip'] = "no";
		$this->session->data['zip_support'] = class_exists('ZipArchive') ? true : false;

		if ($this->session->data['zip_support'] && $this->config->get('exchange1c_use_zip')) {
			$this->session->data['use_zip'] = "yes";
		}

		$upload_dirname = $this->config->get('exchange1c_upload_dirname') ? $this->config->get('exchange1c_upload_dirname') : "exchange1c";
		// Подготовим каталог для загрузки данных
		if (!file_exists(DIR_UPLOAD . $upload_dirname)) {
			mkdir(DIR_UPLOAD . $upload_dirname, 0775);
			$this->log("Создана временная папка: " . DIR_UPLOAD . $upload_dirname, "modeCatalogInit", "modeInit");
		} else {
			// Очищаем папку
			//$this->cleanDir($upload_dirname);
		}
		$this->session->data['upload_dirname'] = $upload_dirname . DIRECTORY_SEPARATOR;
		$this->session->data['upload_filename'] = "";

		$file_size_limit = $this->formatSize($this->config->get('exchange1c_file_size_limit'));
		$post_max_size = $this->getPostMaxFileSize();

		if ($this->config->get('exchange1c_file_size_limit') && $file_size_limit <= $post_max_size) {
			$this->session->data['file_limit'] = $file_size_limit;
		} else {
			$this->session->data['file_limit'] = $post_max_size;
		}
		
		$this->session->data['progress'] = 0;

		$this->log($this->session, "modeCatalogInit=session");

		// сохраняем сессию
		$this->sessionWrite();

		$msg = array(
			"zip=" . $this->session->data['use_zip'],
			"file_limit=" . $this->session->data['file_limit'],
			"sessid=" . $this->session->session_id,
			"version=" . $this->session->data['version']
		);
		$this->echo_msg($msg);
		
		// почистил файл журнала
		$this->clearLog();
		
	} // modeCatalogInit()


	/**
	 * Загружает файл каталога на сервер
	 * Вызывается из /bitrix/admin/1c_exchange.php
	 * Загружает файл в каталог UPLOAD, если это архив, то сразу разархивирует
	 */
	public function modeFile() {

		$this->log("Запрос File", "modeFile");

		$msg = array(
			"failure"
		);

		if (!$this->sessionGet()) {
			$msg[1]	= "ERROR: " . $this->session->data['error'];
			$this->echo_msg($msg);
			exit;
		}
		$this->sessionWrite(1);

		$this->session->data['type'] = $this->request->get['type'];
		$this->session->data['mode'] = $this->request->get['mode'];
		$this->session->data['step'] = 0;
		$this->session->data['upload_filename'] = $this->request->get['filename'];

		$this->log($this->session, "modeFile");

		if (!$this->uploadFile()) {
			$msg[1] = "ERROR: " . $this->session->data['error'];
			$this->echo_msg($msg);
			$this->sessionWrite(0);
			exit;
		}

		$this->sessionWrite(0);
		$this->echo_msg("success");

	} // modeFile()


	/**
	 * Обработка загруженного, либо распакованного файла XML 
	 */
	public function modeCatalogImport() {
		$this->log("Запрос Catalog Import", "modeCatalogImport");

		$aResult = array("success","");
		$bSuccess = false;
		$sMessage = "";

		if (!$this->sessionGet()) {
			$this->echo_msg(array("failure", "ERROR: " . $this->session->data['error']));
			exit;
		}

		$this->session->data['type'] = $this->request->get['type'];
		$this->session->data['mode'] = $this->request->get['mode'];
		$this->session->data['import_filename'] = $this->request->get['filename'];

		// Сохранение сессии перед выполнением шагов
		$this->sessionWrite(1);
		$this->log($this->session, "modeCatalogImport");

		if ($this->session->data['step'] == 0) {
			$this->session->data['progress'] = 1;
			$this->session->data['step'] = 1;
			$sFileExt = $this->fileExtension($this->session->data['upload_filename']);
		
			if ($sFileExt == 'ZIP') {
				$this->log("STEP 1 (распаковка архива...)");
				$this->extractZipV3($this->session->data['upload_filename'], $this->session->data['upload_dirname']);
		    	if (!$this->session->data['error']) {
					$sMessage = "Archive Extract Succefully";
				}
			}
		}
		
		if ($this->session->data['step'] == 1 && !$this->session->data['error']) {
			$this->log("STEP 2 (импорт каталога)");
			$this->load->model('extension/exchange1c');

			if (!file_exists(DIR_UPLOAD . $this->session->data['upload_dirname'] . $this->session->data['import_filename'])) {
				$this->session->data['error'] = 'С050';
				$sMessage = "Ошибка, файл не существует: " . DIR_UPLOAD . $this->session->data['upload_dirname'] . $this->session->data['import_filename'];
				$this->log($sMessage);
			} else {
		    	$this->model_extension_exchange1c->importCatalog();
		    	if (!$this->session->data['error']) {
					$sMessage = "Import Catalog Succefully";
				}
			}
		}

		// ШАГ 3 - обновление цен, остатков в таблицы opencart на товары загруженные в шаге 2
		if ($this->session->data['step'] == 2 && !$this->session->data['error']) {
			$this->log("STEP 3 (обновление каталога opencart)");
			$this->load->model('extension/exchange1c');
	    	$result = $this->model_extension_exchange1c->updateCatalog();
	    	if (!$this->session->data['error']) {
				$sMessage = "Update Catalog Succefully, product processed " . $result['product_count'];
	    	}
			// Шагов дальше нет
			$this->session->data['progress'] = 0;
		}

		if ($this->session->data['progress'] && !$this->session->data['error']) {
			$this->session->data['step']++;
			$aResult = array("progress", $sMessage);
		}

    	if ($this->session->data['error']) {
           	$this->log($this->session->data, "modeCatalogImport=session->data");
			$this->echo_msg(array("failure", "ERROR: " . $this->session->data['error']));
			$this->session->data['progress'] = 0;
			// Закрываем сессию
			$this->sessionWrite(0);
			exit;
    	}

		$this->log($this->session, "modeCatalogImport");
		$this->sessionWrite($this->session->data['progress']);

		$aResult[1] = $sMessage;
		$this->echo_msg($aResult);

	} // modeCatalogImport()


	/**
	 * =================================================================================================
	 */


	/**
	 * Загружает файл в указанную директорию
	 * Директория уже должна быть создана!
	 */
	private function uploadFile() {

		$this->log("[F] Upload File");
		$upload_dirname = DIR_UPLOAD . $this->session->data['upload_dirname'];
		$upload_filename = $upload_dirname . $this->session->data['upload_filename'];

		// Получаем данные
		$dump = file_get_contents("php://input");

		if ($dump !== false) {
			// Если указан путь, закачка одтельными файлами, создаем каталоги
			// Картинки сразу сохраняем в папку DIR_IMAGE
			if (strpos($upload_filename, DIRECTORY_SEPARATOR)) {
				if (!$this->createDirectories($upload_dirname, $this->session->data['upload_dirname'])) {
					$this->session->data['error'] = "C030";
					$this->log("Невозможно создать директорию: " . $upload_dirname);
					return false;
				}
			}
			
			$file_size = @file_put_contents($upload_filename, $dump, LOCK_EX);

			if ($file_size === false) {
				$this->session->data['error'] = "С031";
				$this->log("Ошибка записи файла '" . $upload_filename . "'  в директорию " . $upload_dirname);
				return false;
			}
		}

		// Проверим записался ли файл
		if (file_exists($upload_filename)) {
			// Проверим размер файла
			$stat = stat($upload_filename);
			if ($stat['size'] != $file_size) {
				$this->session->data['error'] = "C032";
				$this->log("Размер файла записанного и прочитанного не совпадает");
				return false;
			}
		} else {
			$this->session->data['error'] = "C033";
			$this->log("Ошибка чтения атрибутов записанного файла, файл не существует");
			return false;
		}
		$file_size = round($stat['size'] / 1024, 2);
		$this->log("Загружен файл: " . $upload_filename . " (" . $file_size . " кбайт)");
		return true;

	} // uploadFile()


	/**
	 */
	private function manualImportProcess() {

		$result = array(
			'error' => '',
			'success' => ''
		);
		// Подготовим временную папку
		if (file_exists($this->session->data['upload_dirname'])) {

			$this->cleanDir($this->session->data['upload_dirname']);

		} else {

			if (empty($this->session->data['upload_dirname'])) {
				$this->session->data['error'] = '4004';
				$result['error'] = "Не установлена папка загрузки файлов";
				return $result;
			}

			if (!mkdir($this->session->data['upload_dirname'])) {
				$this->session->data['error'] = '4001';
				$result['error'] = "Ошибка создания временной папки: " . $this->session->data['upload_dirname'];
				return $result;
			}
		}

		if (!$this->session->data['upload_file_size']) {
			$this->session->data['error'] = "4006";
			$result['error'] = "Файл не был загружен на сервер, наиболее вероятная причина это ограничение в PHP или Веб-сервере";
		}

		if (!move_uploaded_file($this->session->data['temp_file'], $this->session->data['upload_dirname'] . $this->session->data['upload_filename'])) {
			$this->session->data['error'] = '4002';
			$result['error'] = "Загруженый файл " . $this->session->data['temp_file'] . " не удалось переместить в каталог: " . $this->session->data['upload_dirname'];
			return $result;
		}

		if (!file_exists($this->session->data['upload_dirname'] . $this->session->data['upload_filename'])) {
			$this->session->data['error'] = '4003';
			$result['error'] = "Ошибка, файл не существует: " . $this->session->data['upload_filename'];
			return $result;
		}

		$this->log("Загружен файл: " . $this->session->data['upload_filename']);

		$file_extension = $this->fileExtension($this->session->data['upload_filename']);

		$this->session->data['xml_files'] = array();

		// Архив распакуем
		if ($file_extension == 'ZIP') {

			// Если это архив, сначала распакуем
			$extract_info = $this->extractZipV3(
				$this->session->data['upload_dirname'] . $this->session->data['upload_filename'], 
				$this->session->data['upload_dirname']
			);

			if ($extract_info['error']) {
				$this->session->data['error'] = $extract_info['error'];
				$result['error'] = "Ошибка распаковки архива";
				$this->log($extract_info);
				return $result;
			}

			//$this->session->data['xml_files'] = sort($extract_info['xml_files']);
			$this->log($extract_info, 2);
			$this->session->data['xml_files'] = $extract_info['xml_files'];

			// Удалим архив после успешной распаковки
			//unlink($session['upload_dirname'] . $session['upload_filename']);

		} else { // if ($file_extension == 'zip')

			$this->session->data['xml_files'][] = $this->session->data['upload_filename'];
		}
		$result['success'] = 'Успешно загружено ' . count($this->session->data['xml_files']) . ' XML файлов';

		return $result;

	} // manualImportProcess()


	/**
	 * Импорт файла через админ-панель
	 */
	public function manualImport() {
		
		$this->load->language('extension/module/exchange1c');
		$this->log("Ручная загрузка данных");
		$json = array(
			'error'	=> '',
			'success' => ''
		);

		if ($this->config->get('exchange1c_module_status') != "import") {
			$json['error'] = "Модуль не установлен в статус загрузки данных!";
			$this->log($json['error']);
			$this->response->setOutput(json_encode($json));
			exit;
		}

		// Очистка лога
		if ($this->config->get('exchange1c_clear_log')) {
			$this->clearLog();
			$this->log("Очистка журнала");
		}

		$temp_folder = sys_get_temp_dir();
		$this->load->model('extension/exchange1c');
		$this->sessionStart();

		$this->log($this->request->files);
		$this->session->data['temp_file']			= $this->request->files['file']['tmp_name'];
		$this->session->data['upload_file_size']	= $this->request->files['file']['size'];
		$this->session->data['upload_file_type']	= $this->request->files['file']['type'];
		$this->session->data['upload_filename']		= html_entity_decode($this->request->files['file']['name'], ENT_QUOTES, 'UTF-8');
		$this->session->data['upload_dirname']		= $this->config->get('exchange1c_upload_dirname');

		$this->log($this->session);

		$result = $this->manualImportProcess();
		if ($this->session->data['error']) {
			$json['error'] = $this->session->data['error'] . '. ' . $result['error'];
			$this->response->setOutput(json_encode($json));
			$this->sessionWrite(0);
			exit;
		}

		foreach ($this->session->data['xml_files'] as $xml_file) {
			$this->sessionWrite();
			$this->log($xml_file);
			$this->session->data['import_filename'] = $xml_file;
			$this->model_extension_exchange1c->importCatalog();

			if ($this->session->data['error']) {
				break;
			}

		} // foreach

		if ($this->session->data['error']) {
			$json['error'] .= "\n" . $this->language->get('text_upload_error') . ". ERROR: " . $this->session->data['error'];
		} else {
			$json['success'] = "Обмен прошел успешно";
		}
		$this->sessionWrite(0);

		$this->response->setOutput(json_encode($json));

	} // manualImport()


	/**
	 */
	public function updateCatalog() {

		$this->load->language('extension/module/exchange1c');
		$json = array();
		if ($this->user->hasPermission('modify', 'extension/module/exchange1c'))  {
			$this->load->model('extension/exchange1c');
			$result = $this->model_extension_exchange1c->manualUpdateCatalog();		
			$json['success'] = "Обработано товаров: " . $result['product_count'];
		} else {
			$json['error'] = $this->language->get('error_permission');
		}
		$this->response->setOutput(json_encode($json));

	} // updateCatalog()


	/**
	 * Значения по умолчанию
	 */
	public function defaultSettings() {

		$this->load->language('extension/module/exchange1c');
		$json = array();

		if ($this->user->hasPermission('modify', 'extension/module/exchange1c'))  {

			$this->load->model('setting/setting');
			$settings = $this->model_setting_setting->getSetting('exchange1c');
			$settings = $this->setOptions($settings, true);
			$this->model_setting_setting->editSetting('exchange1c', $settings);

			$json['success'] = "Настройки успешно обновлены\nОбновите страницу!";
		} else {
			$json['error'] = $this->language->get('error_permission');
		}
		$this->response->setOutput(json_encode($json));

	} // manualUpdate()


	/**
	 * Очистка базы данных через админ-панель
	 */
	public function manualCleaning() {

		$this->load->language('extension/module/exchange1c');
		$json = array();
		// Проверим разрешение
		if ($this->user->hasPermission('modify', 'extension/module/exchange1c'))  {
			$this->load->model('extension/exchange1c');
			$result = $this->model_extension_exchange1c->cleanDB();
			if (!$result) {
				$json['error'] = "Таблицы не были очищены";
			} else {
				$json['success'] = "Успешно очищены таблицы: \n" . $result;
			}
		} else {
			$json['error'] = $this->language->get('error_permission');
		}
		$this->response->setOutput(json_encode($json));

	} // manualCleaning()


	/**
	 */
	public function manualSessionDelete() {

		//$this->log($this->request);
		$this->load->language('extension/module/exchange1c');
		$json = array();
		// Проверим разрешение
		if ($this->user->hasPermission('modify', 'extension/module/exchange1c'))  {
			$session_id = "";
			if (isset($this->request->get['sessid'])) {
				$session_id = $this->request->get['sessid'];
			}
			$result = $this->sessionDelete($session_id);
			if ($result['error']) {
				$json['error'] = $result['error'];
			} else {
				$json['success'] = "Успешно удалена сессия: " . $session_id;
			}
		} else {
			$json['error'] = $this->language->get('error_permission');
		}
		$this->response->setOutput(json_encode($json));

	} // manualCleaningImportImages()


	/**
	 * Удаляет неиспользуемые производители
	 */
	public function manualRemoveUnisedManufacturers() {

		$this->load->language('extension/module/exchange1c');
		$json = array();
		// Проверим разрешение
		if ($this->user->hasPermission('modify', 'extension/module/exchange1c'))  {
			$this->load->model('extension/exchange1c');
			$result = $this->model_extension_exchange1c->removeUnisedManufacturers();
 			if ($result['error']) {
				$json['error'] = "Ошибка удаления:\n" . $result['error'];
			} else {
				$json['success'] = "Обработано: " . $result['total'] . "\nУдалено: " . $result['delete'];
			}
		} else {
			$json['error'] = $this->language->get('error_permission');
		}
		$this->response->setOutput(json_encode($json));

	} // manualRemoveUnisedManufacturers()


	/**
	 * Удаляет неиспользуемые картинки, освобождает место
	 */
	public function manualRemoveUnisedImages() {

		$this->load->language('extension/module/exchange1c');
		$json = array();
		// Проверим разрешение
		if ($this->user->hasPermission('modify', 'extension/module/exchange1c'))  {
			$this->load->model('extension/exchange1c');
			$result = $this->model_extension_exchange1c->removeUnisedImages();
 			if ($result['error']) {
				$json['error'] = "Ошибка удаления:\n" . $result['error'];
			} else {
				$json['success'] = "Обработано: " . $result['total'] . " (" . $result['total_size'] . ")\nУдалено: " . $result['delete'] . " (" . $result['delete_size'] . ")";
			}
		} else {
			$json['error'] = $this->language->get('error_permission');
		}
		$this->response->setOutput(json_encode($json));

	} // manualRemoveUnisedImages()


	/**
	 * Удаляет товары у которых были загружены из УС
	 */
	public function manualDeleteImportData() {

		$this->load->language('extension/module/exchange1c');
		$json = array();
		// Проверим разрешение
		if ($this->user->hasPermission('modify', 'extension/module/exchange1c'))  {
			$this->load->model('extension/exchange1c');
			$result = $this->model_extension_exchange1c->deleteImportData();
 			if ($result['error']) {
				$json['error'] = "ERROR: 1003";
			} else {
				if (file_exists(DIR_IMAGE . 'import_files')) {
					$this->cleanDir(DIR_IMAGE . 'import_files', true);
				}
				if (file_exists(DIR_IMAGE . 'cache' . DIRECTORY_SEPARATOR . 'import_files')) {
					$this->cleanDir(DIR_IMAGE . 'cache' . DIRECTORY_SEPARATOR . 'import_files', true);
				}
				$json['success'] = "Успешно удалено:".
				"\nАтрибутов: " . $result['attribute'] .
				"\nКатегорий: " . $result['category'] .
				"\nПроизводителей: " . $result['manufacturer'] .
				"\nТоваров: " . $result['product'] . 
				"\nКаталог картинок import_files";
			}
		} else {
			$json['error'] = $this->language->get('error_permission');
		}
		$this->response->setOutput(json_encode($json));

	} // manualDeleteImportProducts()


	/**
	 * Создание и скачивание заказов
	 */
	public function downloadOrders() {

		$this->load->model('extension/exchange1c');
		$orders = $this->model_extension_exchange1c->queryOrders(
			array(
				 'from_date' 		=> $this->config->get('exchange1c_order_date')
				,'new_status'		=> $this->config->get('exchange1c_order_status')
				,'notify'			=> $this->config->get('exchange1c_order_notify')
				,'currency'			=> $this->config->get('exchange1c_order_currency') ? $this->config->get('exchange1c_order_currency') : 'руб.'
			)
		);
		$this->response->addheader('Pragma: public');
		$this->response->addheader('Connection: Keep-Alive');
		$this->response->addheader('Expires: 0');
		$this->response->addheader('Content-Description: File Transfer');
		$this->response->addheader('Content-Type: application/octet-stream');
		$this->response->addheader('Content-Disposition: attachment; filename="orders.xml"');
		$this->response->addheader('Content-Transfer-Encoding: binary');
		$this->response->addheader('Content-Length: ' . strlen($orders));

        $this->response->setOutput($orders);

	} // downloadOrders()


	/**
	 * Очистка лога
	 */
	private function clearLog() {

		if ($this->config->get('exchange1c_log_filename')) {
			$file = DIR_LOGS . $this->config->get('exchange1c_log_filename');
			$handle = fopen($file, 'w+');
			fclose($handle);
		}

		$file = DIR_LOGS . $this->config->get('config_error_filename');
		$handle = fopen($file, 'w+');
		fclose($handle);

	} // clearLog()


	/**
	 * В стадии разработке
	 * Отправляет информацию для УС, например список статусов заказов
	 */
	public function modeInfo() {

    	// В разработке
		$this->log('type=sale, mode=info');

	} // modeInfo()


	/**
	 * События
	 */
	public function eventDeleteProduct($route, $products) {
		$this->load->model('extension/exchange1c');
		foreach ($products as $product_id) {
			$this->log("Удаление связи с товаром product_id = " . $product_id);
			$this->model_extension_exchange1c->deleteLinkProduct($product_id);
		}
	} // eventProductDelete()


	/**
	 * События
	 */
	public function eventDeleteCategory($route, $categories) {
		$this->load->model('extension/exchange1c');
		foreach ($categories as $category_id) {
			$this->log("Удаление связи с категорией category_id = " . $category_id);
			$this->model_extension_exchange1c->deleteLinkCategory($category_id);
		}
	} // eventCategoryDelete()


	/**
	 * События
	 */
	public function eventDeleteManufacturer($route, $manufacturers) {
		$this->load->model('extension/exchange1c');
		foreach ($manufacturers as $manufacturer_id) {
			$this->log("Удаление связи с производителем manufacturer_id = " . $manufacturer_id);
			$this->log("route = " . $route);
			$this->model_extension_exchange1c->deleteLinkManufacturer($manufacturer_id);
		}
	} // eventManufacturerDelete()


	/**
	 * События
	 */
	public function eventDeleteAttribute($route, $attributes) {
		$this->load->model('extension/exchange1c');
		foreach ($attributes as $attribute_id) {
			$this->log("Удаление связи с атрибутом attribute_id = " . $attribute_id);
			$this->log("route = " . $route);
			$this->model_extension_exchange1c->deleteLinkAttribute($attribute_id);
		}
	} // eventDeleteAttribute()


	/**
	 * События
	 */
	public function eventGetProducts(&$route, &$data, &$output) {
		//$this->log("route = " . $route);
		//$this->log($data, 2);
		$this->log($output, 2);
		$this->load->model('extension/exchange1c');
		foreach ($output as &$product) {
			$prices = $this->model_extension_exchange1c->getProductPrices($product['product_id']);
			if ($prices['min'] != $prices['max']) {
				$product['price'] = $prices['min'] . " - " . $prices['max'];
			} elseif ($prices['min'] > 0) {
				$product['price'] = $prices['min'];
			}
		}
	} // eventDeleteAttribute()


	/**
	* Формирует архив модуля для инсталляции
	*/
	public function export() {

		if ($this->checkAccess(true) || $this->config->get('exchange1c_export_module_to_all')) {
			$this->log("Экспорт модуля " . $this->module_name . " для IP " . $_SERVER['REMOTE_ADDR']);
		} else {
			echo "<br />\n";
			echo "Module " . $this->module_name . " for IP " . $_SERVER['REMOTE_ADDR'] . " denied!";
			$this->log("Экспорт модуля " . $this->module_name . " для IP " . $_SERVER['REMOTE_ADDR'] . " запрещен!");
			return false;
		}

		$filename = DIR_DOWNLOAD . 'oc2.3-exchange1c_' . $this->config->get('exchange1c_version') . '.ocmod.zip';
		if (is_file($filename))
			unlink($filename);

		// Пакуем в архив
		$zip = new ZipArchive;
		$zip->open($filename, ZIPARCHIVE::CREATE);
		$zip->addFile(DIR_APPLICATION . 'controller/extension/module/exchange1c.php', 'upload/admin/controller/extension/module/exchange1c.php');
		$zip->addFile(DIR_APPLICATION . 'language/en-gb/extension/module/exchange1c.php', 'upload/admin/language/en-gb/extension/module/exchange1c.php');
		$zip->addFile(DIR_APPLICATION . 'language/ru-ru/extension/module/exchange1c.php', 'upload/admin/language/ru-ru/extension/module/exchange1c.php');
		$zip->addFile(DIR_APPLICATION . 'model/extension/exchange1c.php', 'upload/admin/model/extension/exchange1c.php');
		$zip->addFile(DIR_APPLICATION . 'view/template/extension/module/exchange1c.tpl', 'upload/admin/view/template/extension/module/exchange1c.tpl');
		$zip->addFile(DIR_APPLICATION . '../bitrix/admin/1c_exchange.php', 'upload/bitrix/admin/1c_exchange.php');
		if (is_file(DIR_APPLICATION . '../catalog/model/catalog/exchange1c.php')) {
			$zip->addFile(DIR_APPLICATION . '../catalog/model/catalog/exchange1c.php', 'upload/catalog/model/catalog/exchange1c.php');
		}

		$sql = "SELECT `xml`,`code` FROM " . DB_PREFIX . "modification WHERE code LIKE 'exchange1c%'";
		$query = $this->db->query($sql);
		$xml_files = array();
		if ($query->num_rows) {
			foreach ($query->rows as $row) {
				if ($row['code'] == 'exchange1c') {
					$xml_name = 'install.xml';
				} else{
					$xml_name = $row['code'] . '.ocmod.xml';
				}
				if ($fp = @fopen(DIR_DOWNLOAD . $xml_name, "wb")) {
					$result = @fwrite($fp, $row['xml']);
					$this->log("Add to arhive file: " . $xml_name);
					$zip->addFile(DIR_DOWNLOAD . $xml_name, $xml_name);
					@fclose($fp);
					$xml_files[] = $xml_name;
				}
			}
		}

		$zip->close();
		foreach ($xml_files as $file) {
			@unlink(DIR_DOWNLOAD . $file);
		}

		if ($fp = fopen($filename, "rb")) {
			echo '<a href="' . HTTP_CATALOG . 'system/storage/download/' . substr($filename, strlen(DIR_DOWNLOAD)) . '">' . substr($filename, strlen(DIR_DOWNLOAD)) . '</a>';
		}
		
	} // export()


	/**
	* Эта функция самоуничтожения модуля! Будьте осторожны!
	* Данные в базе не изменяются и не восстанавливаются в предыдущее состояние
	*/
	public function modeRemoveModule() {

		// Эта строчка защищает от несанкционированного удаления, для удаления модуля, закомментарьте строчку ниже
		//return false;

		$this->load->language('extension/module/exchange1c');
		$json = array();

		if ($this->checkAccess(true) && $this->user->hasPermission('modify', 'extension/module/exchange1c')) {
			$this->log("Удаление модуля " . $this->module_name . " для IP " . $_SERVER['REMOTE_ADDR']);
		} else {
			$json['error'] = $this->language->get('error_permission');
			$this->response->setOutput(json_encode($json));
			exit;
		}

		$this->log("Удаление модуля exchage1c версии " . $this->VERSION_MODULE);

		$this->uninstall();

		$files = array();
		$files[] = DIR_APPLICATION . 'controller/extension/module/exchange1c.php';
		$files[] = DIR_APPLICATION . 'language/en-gb/extension/module/exchange1c.php';
		$files[] = DIR_APPLICATION . 'language/ru-ru/extension/module/exchange1c.php';
		$files[] = DIR_APPLICATION . 'model/extension/exchange1c.php';
		$files[] = DIR_APPLICATION . 'view/template/extension/module/exchange1c.tpl';
		$files[] = DIR_APPLICATION . 'bitrix/admin/1c_exchange.php';
		$files[] = substr(DIR_APPLICATION, 0, strlen(DIR_APPLICATION) - 6) . 'export/exchange1c.php';
		foreach ($files as $file) {
			if (is_file($file)) {
				unlink($file);
				$this->log("Удален файл " . $file,1);
			}
		}

		// Удаление модификатора
		$this->load->model('extension/modification');
		$modification = $this->model_extension_modification->getModificationByCode('exchange1c');
		if ($modification) $this->model_extension_modification->deleteModification($modification['modification_id']);

		$json['success'] = 'OK';
		$this->response->setOutput(json_encode($json));

	} // modeRemoveModule()

}

?>
