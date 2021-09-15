<?php

class ModelExtensionExchange1c extends Model
{

    // VARS
    private $CATEGORY 			= array();
    private $PRODUCT 			= array();
    private $PROPERTY 			= array();
    private $PCATEGORY 			= array();
    private $ATTRIBUTE		 	= array();
    private $UNIT				= array();
    private $CURRENCY			= array();
    private $PRICE_TYPE			= array();

    // Языки
    private $LANG 				= array();
    // Язык по-умолчанию
    private $LANG_ID 			= 0;

    // Магазины
    private $STORE 				= array();
    // Магазин по-умолчанию
    private $STORE_ID 			= 0;

	/**
     * ****************************** ОБЩИЕ ФУНКЦИИ ******************************
     */
    
	
	/**
	 * Пишет информацию в файл журнала
	 * @param	int				Уровень сообщения
	 * @param	string,object	Сообщение или объект
	 */
	private function log($message, $debug = "") {

		$msg = array();
		$line = "";
		$error = "";
		if (isset($this->session->data['error'])) {
			$error = $this->session->data['error'];
		}
		if ($this->config->get('exchange1c_log_debug')) {
			list ($di) = debug_backtrace();
			$line = sprintf("%04sM ", $di["line"]);
			$msg['LINE'] = $line;
		}
		if ($error) {
			$msg['ERROR'] = $error;
		}
		if ($debug) { 
			$msg['TITLE'] = $debug;
			$msg['LOG'] = print_r($message, true);
			$this->log->write(print_r($msg, true));

		} else {
			if (is_array($message) || is_object($message)) {
				$msg['LOG'] = print_r($message, true);
				$this->log->write($msg);
			} else {
				$this->log->write($line . $message);
			}
		}
		
	} // log()


    /**
     * Выполняет запрос, записывает в лог в режим отладки и возвращает результат
     */
    function query($sql) {

		if ($this->config->get('exchange1c_log_debug')) {
			list($di) = debug_backtrace();
			$line = sprintf("%04sM ", $di["line"]);
			$this->log->write($line . $sql);
   		}
		return $this->db->query($sql);

    } // query()


    /**
     * Возвращает строку даты
     *
     * @param	string	var
     * @return	string
     */
    function format($var) {
        return preg_replace_callback('/\\\u([0-9a-fA-F]{4})/', create_function('$match',
            'return mb_convert_encoding("&#" . intval($match[1], 16) . ";", "UTF-8", "HTML-ENTITIES");'),
            json_encode($var));
    } // format()


    /**
     * Преобразует строчу в число (float)
     */
    private function formatFloat($str) {
        $search = array(',', ' ');
        $replace = array('.', '');
        return floatval(str_replace($search, $replace, $str));
    } // formatFloat()


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


    /**
     * Информацию из XML группирует в массив
     */
    private function getInfoXML($xml) {

		$data = array();

		$data['version'] = ($xml['ВерсияСхемы']) ? (string)$xml['ВерсияСхемы'] : '';
		$data['datetime'] = ($xml['ДатаФормирования']) ? str_replace('T', ' ', (string)$xml['ДатаФормирования']) : '';

        if ($xml->Классификатор) {

            if ($this->config->get('exchange1c_module_status') !== 'load_config') {
				if ($xml->Классификатор->Группы) {
	                $data['category'] = $xml->Классификатор->Группы;
	            }
				if ($xml->Классификатор->Свойства) {
					$data['property'] = $xml->Классификатор->Свойства;
				}
	            if ($xml->Классификатор->Категории) {
	                $data['pcategory'] = $xml->Классификатор->Категории;
	            }
    		}
            if ($xml->Классификатор->ЕдиницыИзмерения) {
                $data['unit'] = $xml->Классификатор->ЕдиницыИзмерения;
            }
            if ($xml->Классификатор->ТипыЦен) {
                $data['price_type'] = $xml->Классификатор->ТипыЦен;
            }
            if ($xml->Классификатор->Склады) {
                $data['storage'] = $xml->Классификатор->Склады;
            }
        }

		if ($xml->Каталог) {
			$this->checkModeImport($xml->Каталог);
	        if ($xml->Каталог->Товары && $this->config->get('exchange1c_module_status') !== 'load_config') {
				$data['products'] = $xml->Каталог;
			}
		}
	
        if ($xml->ПакетПредложений) {
        	$this->checkModeImport($xml->ПакетПредложений);
            if ($xml->ПакетПредложений->ТипыЦен) {
                $data['price_type'] = $xml->ПакетПредложений->ТипыЦен;
            }
            if ($xml->ПакетПредложений->Склады) {
                $data['storage'] = $xml->ПакетПредложений->Склады;
            }
            if ($xml->ПакетПредложений->Предложения && $this->config->get('exchange1c_module_status') !== 'load_config') {
	            $data['offers'] = $xml->ПакетПредложений->Предложения;
            }
        }

        if ($xml->Документ) {
            $data['document'] = $xml->Документ;
        }

        return $data;

    } // getInfoXML()


    /**
     * Очищает базу
     * Вызывается из контроллера, manualCleaning()
     */
    public function cleanDB()  {

        $this->log("Очистка таблиц базы данных:", 1);
        // Удаляем товары
		$result = "";

		$this->dropTables();
		$msg_create_tables = $this->createTables();

		$result .= "- Пересозданы таблицы модуля(" . count($msg_create_tables) . ")\n";
        
        // Очистим настройки цен
		$this->load->model('setting/setting');
		$settings = $this->model_setting_setting->getSetting('exchange1c');
		$settings['exchange1c_price_type'] = array();
		$settings['exchange1c_module_status'] = "load_config";
		$this->model_setting_setting->editSetting('exchange1c', $settings);
		$result .= "- Очищены настройки цен\n";

        
        $this->query("TRUNCATE TABLE " . DB_PREFIX . "product");
        $this->query("TRUNCATE TABLE " . DB_PREFIX . "product_attribute");
        $this->query("TRUNCATE TABLE " . DB_PREFIX . "product_description");
        $this->query("TRUNCATE TABLE " . DB_PREFIX . "product_discount");
        $this->query("TRUNCATE TABLE " . DB_PREFIX . "product_image");
        $this->query("TRUNCATE TABLE " . DB_PREFIX . "product_option");
        $this->query("TRUNCATE TABLE " . DB_PREFIX . "product_option_value");
        $this->query("TRUNCATE TABLE " . DB_PREFIX . "product_related");
        $this->query("TRUNCATE TABLE " . DB_PREFIX . "product_reward");
        $this->query("TRUNCATE TABLE " . DB_PREFIX . "product_special");
        $this->query("TRUNCATE TABLE " . DB_PREFIX . "product_to_category");
        $this->query("TRUNCATE TABLE " . DB_PREFIX . "product_to_download");
        $this->query("TRUNCATE TABLE " . DB_PREFIX . "product_to_layout");
        $this->query("TRUNCATE TABLE " . DB_PREFIX . "product_to_store");

        $this->query("TRUNCATE TABLE " . DB_PREFIX . "option");
        $this->query("TRUNCATE TABLE " . DB_PREFIX . "option_description");
        $this->query("TRUNCATE TABLE " . DB_PREFIX . "option_value");
        $this->query("TRUNCATE TABLE " . DB_PREFIX . "option_value_description");

        $this->query("DELETE FROM " . DB_PREFIX . "url_alias WHERE query LIKE 'product_id=%'");
        $this->query("DELETE FROM " . DB_PREFIX . "url_alias WHERE query LIKE 'category_id=%'");

        // Очищает таблицы категорий
        $this->query("TRUNCATE TABLE " . DB_PREFIX . "category");
        $this->query("TRUNCATE TABLE " . DB_PREFIX . "category_description");
        $this->query("TRUNCATE TABLE " . DB_PREFIX . "category_to_store");
        $this->query("TRUNCATE TABLE " . DB_PREFIX . "category_to_layout");
        $this->query("TRUNCATE TABLE " . DB_PREFIX . "category_path");

        $this->query("TRUNCATE TABLE " . DB_PREFIX . "attribute");
        $this->query("TRUNCATE TABLE " . DB_PREFIX . "attribute_description");
        $this->query("TRUNCATE TABLE " . DB_PREFIX . "attribute_group");
        $this->query("TRUNCATE TABLE " . DB_PREFIX . "attribute_group_description");

        $result .= "- Товаров и их связи, опций, категорий, атрибутов\n";

        // Очищает таблицы от всех производителей
        $this->query("TRUNCATE TABLE " . DB_PREFIX . "manufacturer");
        $query = $this->query("SHOW TABLES FROM " . DB_DATABASE . " WHERE Tables_in_" . DB_DATABASE . " LIKE '" . DB_PREFIX . "manufacturer_description'");
        //$query = $this->db->query("SHOW TABLES FROM " . DB_DATABASE . " LIKE '" . DB_PREFIX . "manufacturer_description'");
        if ($query->num_rows) {
            $this->query("TRUNCATE TABLE " . DB_PREFIX . "manufacturer_description");
        }
        $this->query("TRUNCATE TABLE " . DB_PREFIX . "manufacturer_to_store");
        $this->query("DELETE FROM " . DB_PREFIX . "url_alias WHERE query LIKE 'manufacturer_id=%'");
        $result .= "- Производителей, url-alias производителей\n";

        // Доработка от SunLit (Skype: strong_forever2000)
        // Удаляем все отзывы
        $this->log("Очистка отзывов...");
        $this->query("TRUNCATE TABLE " . DB_PREFIX . "review");
        $result .= "- Отзывов\n\n";
        $result .= "ОБНОВИТЕ СТРАНИЦУ МОДУЛЯ!";

        return $result;

    } // cleanDB()


    /**
     * Читает значения на нескольких языках
     */
    private function getLanguageName($xml_name, $html_format = 0)  {

        $result = array();

        // Мультиязычность
        if (count($xml_name) > 1)  {
            foreach ($xml_name as $name) {
                $lang_code = (string)$name['lang'];
                //$this->log("Lang code '" . $lang_code . "'");
                if (isset($this->LANG[$lang_code]))
                    $lang_id = $this->LANG[$lang_code];
                else
                    $lang_id = $this->LANG_ID;

                $result[$lang_id] = htmlspecialchars(trim((string)$name));
				if ($html_format)
					$result[$lang_id] = str_replace(array("\r\n","\r","\n"), "<br />", $result[$lang_id]);
            }
        } else {
		         $result[$this->LANG_ID] = htmlspecialchars(trim((string )$xml_name));
			if ($html_format)
				$result[$this->LANG_ID] = str_replace(array("\r\n","\r","\n"), "<br />", $result[$this->LANG_ID]);

        }

        // Если в системе определено более одного языка, проверим чтобы значения были на всех языках
        // на которых не указаны в файле, будут скопированы с основного
        foreach ($this->LANG as $lang_id) {
            if (!isset($result[$lang_id]))
                $result[$lang_id] = $result[$this->LANG_ID];
        }
        return $result;

    } // getLanguageName()


    /**
     * Удаляет неиспользованных производителей
     * Вызывается из контроллера, manualRemoveUnisedManufacturers()
     */
    public function removeUnisedManufacturers() {

        $total = 0;
        $delete = 0;

        $query = $this->query("SELECT manufacturer_id,name FROM " . DB_PREFIX .
            "manufacturer");
        if ($query->num_rows) {

			foreach ($query->rows as $manufacturer_info) {
                $total++;
                // Проверяем использование только в товарах
                $query_count = $this->query("SELECT COUNT(*) as total FROM " . DB_PREFIX .
                    "product WHERE manufacturer_id = " . $manufacturer_info['manufacturer_id']);

				if ($query_count->num_rows) {

                    if ($query_count->row['total']) {
                        $this->log("Производитель '" . $manufacturer_info['name'] . "' используется в " .
                            $query_count->row['total'] . " товарах");
                        continue;
                    }

                    $this->query("DELETE FROM " . DB_PREFIX . "manufacturer WHERE manufacturer_id = " . $manufacturer_info['manufacturer_id']);
                    $this->query("DELETE FROM " . DB_PREFIX . "manufacturer_description WHERE manufacturer_id = " . $manufacturer_info['manufacturer_id']);
                    $this->query("DELETE FROM " . DB_PREFIX . "1c_manufacturer WHERE manufacturer_id = " . $manufacturer_info['manufacturer_id']);

                    if (isset($this->TAB_FIELDS['manufacturer_to_layout'])) {
                        $this->query("DELETE FROM " . DB_PREFIX . "manufacturer_to_layout WHERE manufacturer_id = " . $manufacturer_info['manufacturer_id']);
                    }
 
                    $this->query("DELETE FROM " . DB_PREFIX . "manufacturer_to_store WHERE manufacturer_id = " . $manufacturer_info['manufacturer_id']);
                    $delete++;
                }
            }
        }

        return array(
            'total' => $total,
            'delete' => $delete,
            'error' => '');

    } // removeUnisedManufacturers()


    private function dirToArray($dir) {

		$result = array();
		$cdir = scandir($dir);
		foreach ($cdir as $key => $value) {
			if (!in_array($value,array(".",".."))) {
				if (is_dir($dir . DIRECTORY_SEPARATOR . $value)) {
            		$result[$value] = $this->dirToArray($dir . DIRECTORY_SEPARATOR . $value);
				} else {
					$result[] = $value;
				}
			}
		}
  		return $result;
  
   	} // dirToArray()


     /**
     * Удаляет неиспользованные картинки
     * Вызывается из контроллера, manualRemoveUnisedImages()
     */
    public function removeUnisedImages() {

		$total = 0;
		$total_size = 0;
		$delete = 0;
		$delete_size = 0;
        
        $aScanDir = $this->dirToArray(DIR_IMAGE . "import_files");
        foreach ($aScanDir as $dir => $aImages) {
        	foreach ($aImages as $image) {
	        	$path_image = 'import_files' . DIRECTORY_SEPARATOR . $dir . DIRECTORY_SEPARATOR . $image;

	        	$total++;
				$filesize = filesize(DIR_IMAGE . $path_image);
				if ($filesize) {
					$total_size += $filesize;
				}

				$query = $this->query("SELECT count(*) as count FROM " . DB_PREFIX . "product WHERE image = '" . $path_image . "'");
				if ($query->row['count']) {
					$this->log("Основная картинка товара " . $image);
					continue;
				}
				$query = $this->query("SELECT count(*) as count FROM " . DB_PREFIX . "product_image WHERE image = '" . $path_image . "'");
				if ($query->row['count']) {
					$this->log("Дополнительная картинка товара " . $image);
					continue;
				}
				$query = $this->query("SELECT count(*) as count FROM " . DB_PREFIX . "option_value WHERE image = '" . $path_image . "'");
				if ($query->row['count']) {
					$this->log("Картинка опции " . $image);
					continue;
				}
 				$query = $this->query("SELECT count(*) as count FROM " . DB_PREFIX . "manufacturer WHERE image = '" . $path_image . "'");
				if ($query->row['count']) {
					$this->log("Картинка производителя " . $image);
					continue;
				}
 				$query = $this->query("SELECT count(*) as count FROM " . DB_PREFIX . "category WHERE image = '" . $path_image . "'");
				if ($query->row['count']) {
					$this->log("Картинка категории " . $image);
					continue;
				}

				$this->log("Удалена картинка " . $image);
				$filesize = filesize(DIR_IMAGE . $path_image);
				if ($filesize) {
					$delete_size += $filesize;
				}
				@unlink(DIR_IMAGE . $path_image);
				@unlink(DIR_IMAGE . 'cache' . DIRECTORY_SEPARATOR . $path_image);
				$delete++;
			}
        }
        
        $total_size = round($total_size / 1024, 0);
        $delete_size = round($delete_size / 1024, 0);

        return array(
			'total' => $total,
			'total_size' => (string)$total_size.' Мб',
			'delete' => $delete,
			'delete_size' => (string)$delete_size.' Мб',
			'error' => '');

    } // removeUnisedImages()


   /**
     * Удаляет все товары загруженные через модуль
     * Вызывается из контроллера, функция manualDeleteImportData()
     */
    public function deleteImportData() {

        $this->log("Удаление данных которые были загружены с УС, то есть которые имеют связи");
        $result = array(
            'error' 		=> "",
            'product' 		=> 0,
            'attribute' 	=> 0,
            'manufacturer' 	=> 0,
            'category' 		=> 0
		);

        $this->load->model('catalog/product');
        $query = $this->query("SELECT product_id FROM 1c_product");

        if ($query->num_rows) {
            $this->log("Удаление товаров...");
            $result['product'] = $query->num_rows;

            foreach ($query->rows as $row) {
                $this->model_catalog_product->deleteProduct($row['product_id']);
            }
        }
		$this->query("TRUNCATE TABLE 1c_product");
		$this->query("TRUNCATE TABLE 1c_prices");
		$this->query("TRUNCATE TABLE 1c_rests");
		$this->query("TRUNCATE TABLE 1c_offers");
		$this->query("TRUNCATE TABLE 1c_features");

        $this->load->model('catalog/category');
        $query = $this->query("SELECT category_id FROM 1c_category");

        if ($query->num_rows){

            $this->log("Удаление категорий...");
            $result['category'] = $query->num_rows;

            foreach ($query->rows as $row) {
                $this->model_catalog_category->deleteCategory($row['category_id']);
            }
        }
		$this->query("TRUNCATE TABLE 1c_category");
		$this->query("TRUNCATE TABLE 1c_pcategory");
		$this->query("TRUNCATE TABLE 1c_pcategory_property");

        $this->load->model('catalog/manufacturer');
        $query = $this->query("SELECT manufacturer_id FROM 1c_manufacturer");
        
		if ($query->num_rows) {

            $this->log("Удаление производителей...");
            $result['manufacturer'] = $query->num_rows;

            foreach ($query->rows as $row) {
                $this->model_catalog_manufacturer->deleteManufacturer($row['manufacturer_id']);
            }
 			$this->query("TRUNCATE TABLE 1c_manufacturer");
       }

        $this->load->model('catalog/attribute');
        $query = $this->query("SELECT attribute_id FROM 1c_property");
 
        if ($query->num_rows) {

            $this->log("Удаление атрибутов...");
            $result['attribute'] = $query->num_rows;

            foreach ($query->rows as $row) {
                $this->model_catalog_attribute->deleteAttribute($row['attribute_id']);
                $this->query("DELETE FROM " . DB_PREFIX . "attribute WHERE attribute_id = " . (int)$row['attribute_id']);
            }
        }
		$this->query("TRUNCATE TABLE 1c_property");
		$this->query("TRUNCATE TABLE 1c_property_value");
		$this->query("TRUNCATE TABLE 1c_property_value_description");
		$this->query("TRUNCATE TABLE 1c_storage");
		$this->query("TRUNCATE TABLE 1c_unit");
		
        return $result;

    } // deleteImportData()


    /**
     * Удаляет связь с товаром
     */
    public function deleteLinkProduct($product_id) {

        $this->log("Удаление связей у товара product_id: " . $product_id, "deleteLinkProduct");
        $this->query("DELETE FROM 1c_product WHERE product_id = " . (int)$product_id);

    } // deleteLinkProduct()


    /**
     * ****************************** ФУНКЦИИ ДЛЯ СЕССИИЙ ******************************
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

			if(!file_exists($root)) exit;

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
				rmdir($root); exit;
			}
		}

	} // cleanDir()


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

		$size_max_manual = $this->formatSize($this->config->get('exchange1c_file_max_size'));
		if ($size_max_manual) {
			$this->log("POST_MAX_SIZE: " . $size_max_manual);
			if ($size_max_manual < $size) {
				$size = $size_max_manual;
			}
		}

		$client_max_body_size = ini_get('client_max_body_size');
		if ($client_max_body_size) {
			$this->log("CLIENT MAX BODY SIZE: " . $client_max_body_size);
			if ($client_max_body_size < $size) {
				$size = $client_max_body_size;
			}
		}

		return $size;
	
	} // getPostMaxFileSize()


    /**
     * ********************************************************************************
     */


    /**
     * Возвращает id языка по-умолчанию в системе
     */
    private function getLanguageIdDefault($lang = '') {

        if (!$lang) {
            $lang = $this->config->get('config_language');
        }

        $query = $this->query("SELECT language_id FROM " . DB_PREFIX . "language WHERE code = '" . $lang . "'");

        if ($query->num_rows) {
            return $query->row['language_id'];
        }

        return 0;

    } // getLanguageIdDefault()


    /**
     * Поиск guid товара по ID
     */
    public function getGuidByProductId($product_id) {

        $query = $this->query("SELECT guid FROM 1c_product WHERE product_id = " . (int)$product_id);
        if ($query->num_rows) {
            return $query->row['guid'];
        }
        return '';

    } // getGuidByProductId()


    /**
     * ****************************** ФУНКЦИИ ДЛЯ SQL ЗАПРОСОВ ******************************
     */


    /**
     * Формирует массив полей для SQL запроса
     * table - таблица в которую производится запись
     * data - массив ключ-значение для запроса
     * если в массиве data будет ключ поля то попадет в запрос, даже если будет пустое значение
     * если нужно не обновлять значение, нужно удалить ключ из массива data
     */
    private function querySetFields($table, $data, $lang_id = 0, $skip_fields = array(), $old_data = array()) {

		$set_fields_array = array();
        $query = $this->query("SHOW COLUMNS FROM " . $table);

        if (!$query->num_rows) {
            $this->session->data['error'] = "M500";
            $this->log("В таблиице " . $table . " нет полей", "querySetFields:EM500");
            return "";
        }

        foreach ($skip_fields as $field) {
            if (array_key_exists($field, $data)) {
                unset($data[$field]);
            }
        }

        foreach ($query->rows as $col) {
			if (!isset($data[$col['Field']])) {
                continue;
			}

			$value = $data[$col['Field']];

			if (isset($old_data[$col['Field']])) {
				if ($value == $old_data[$col['Field']]) {
					continue;
				}
			}

            if ($lang_id && isset($value[$lang_id])) {
				$value = $value[$lang_id];
				if (isset($old_data[$col['Field']][$lang_id])) {
					if ($value == $old_data[$col['Field']][$lang_id]) {
						continue;
					}
				}
            }

			if (empty($value)) {
				$this->log("Поле " . $col['Field'] . " пропущено так как нет данных (пустое значение или массив)", "querySetFields");
				continue;
			}

			if (is_array($value)) {
				$this->session->data['error'] = "M501";
				$this->log("Значение поля является массив вместо строки!", "querySetFields:EM501");
				$this->log("Обработка таблицы " . $table . ", поля " . $col['Field'], "querySetFields:EM501");
				$this->log($value, "querySetFields:EM501=value");
				return "";
			}
				
			if (stristr($col['Type'], "varchar") || stristr($col['Type'], "text")) {
                $value = "'" . $this->db->escape($value) . "'";
            }

	        $set_fields_array[$col['Field']] = $col['Field'] . " = " . $value;

        }

        return implode(", ", $set_fields_array);

    } // querySetFields()


    /**
     * ****************************** ФУНКЦИИ ДЛЯ СКЛАДОВ ******************************
     */


    /**
     * Получает из базы все единицы измерения в массив
     */
    private function getUnits() {

        $data = array();
        $query = $this->query("SELECT code, unit_id FROM 1c_unit");

        foreach ($query->rows as $row) {
            $data[$row['code']] = $row['unit_id'];
        }
        return $data;

    } // getUnits()


    /**
     *  ********************************** ФУНКЦИИ ДЛЯ РАБОТЫ С ОТЗЫВАМИ с ЯНДЕКСА ************************************
     */


    /**
     * Отзывы парсятся с Яндекса в 1С, а затем на сайт
     * Доработка от SunLit (Skype: strong_forever2000)
     * Читает отзывы из классификатора и записывает их в массив
     */
    private function importProductReview($xml) {

        $product_review = array();
        foreach ($xml->Отзыв as $property) {
            $product_review[trim((string )$property->Ид)] = array(
                'review_id' => trim((string )$property->Ид),
                'author' => trim((string )$property->Имя),
                'yes' => trim((string )$property->Да),
                'no' => trim((string )$property->Нет),
                'text' => trim((string )$property->Текст),
                'rating' => (int)$property->Рейтинг,
                'date_added' => trim((string )$property->Дата),
                );
        }
        $this->log("Прочитано отзывов о товарах: " . count($product_review));
        return $product_review;

    } // importProductReview()


    /**
     * Записывает отзывы
     */
    private function setProductReview($product_id, $product_reviews) {

		// Проверяем
		$product_oreview = array();
		$query = $this->query("SELECT review_id,status FROM " . DB_PREFIX . "review WHERE product_id = " . (int)$product_id);

		foreach ($query->rows as $review) {
			$product_oreview[$review['review_id']] = $review['status'];
		}

        foreach ($product_reviews as $review) {
            $update = array();
            $text = '<i class="fa fa-plus-square"></i> ' . $this->db->escape($review['yes']) .
                '<br><i class="fa fa-minus-square"></i> ' . $this->db->escape($review['no']) .
                '<br>' . $this->db->escape($review['text']);

            if (isset($product_oreview[$review['review_id']])) {
                if ($product_oreview[$review['review_id']]['text'] != $text) {
                    $update[] = "text = '" . $this->db->escape($text) . "'";
                }
                
                if ($product_oreview[$review['review_id']]['author'] != $review['author']) {
                    $update[] = "author = '" . $this->db->escape($review['author']) . "'";
                }
                
                if ($product_oreview[$review['review_id']]['rating'] != $review['rating']) {
                    $update[] = "rating = " . (int)$review['rating'];
                }
                
                if ($product_oreview[$review['review_id']]['status'] != $review['status']) {
                    $update[] = "status = '" . $review['status'] . "'";
                }
                
                if ($update) {
                    $fields = implode(", ", $update);
                    $this->query("UPDATE " . DB_PREFIX . "review SET " . $fields . ", date_modified = NOW()");
                }

            } else {
                $this->query("INSERT INTO " . DB_PREFIX . "review SET 
					review_id = " . (int)$review['review_id'] . ",
					product_id = " . (int)$product_id . ", 
					status = 1,
					author = '" . $this->db->escape($review['name']) . "', 
					rating = " . (int)$review['rate'] . ", 
					text = '" . $text . "', 
					date_added = '" . $review['date'] . "'"
				);
            }
        }

    } // setProductReview()


    /**
     *  ********************************** ФУНКЦИИ ДЛЯ РАБОТЫ С ТОВАРАМИ ************************************
     */


    /**
     * Добавляет товар в базу
     */
    private function addProduct($aData, $aDescriptions) {

		if ($this->config->get('exchange1c_module_status') == "debug") {
			return 1;
		}
		
        if ($this->config->get('exchange1c_product_new_status_disable'))
			$aData['status'] = 0;

        $aData['date_added'] = "NOW()";
        $aData['date_modified'] = "NOW()";

        // ЕДИНИЦА ДЛИНЫ
        if ($this->config->get('config_length_class_id'))
            $aData['length_class_id'] = $this->config->get('config_length_class_id');

        // ЕДИНИЦА ВЕСА
        if ($this->config->get('config_weight_class_id'))
            $aData['weight_class_id'] = $this->config->get('config_weight_class_id');

        // Статус на складе
        if ((int)$this->config->get('exchange1c_product_stock_status_off'))
            $aData['stock_status_id'] = (int)$this->config->get('exchange1c_product_stock_status_off');

		$aSkipFields = array();
		if (!$aData['product_id']) {
			$aSkipFields[] = "product_id";
		}

		$set_fields_str = $this->querySetFields(DB_PREFIX."product", $aData, 0, $aSkipFields);
		if ($set_fields_str) {
            $this->query("INSERT INTO " . DB_PREFIX . "product SET " . $set_fields_str);
            $aData['product_id'] = $this->db->getLastId();
        }

		$this->log($aData, 1);
		if (!$aData['product_id']) {
            $this->session->data['error'] = "M260";
            $this->log("При добавлении товара в таблицу product произошла ошибка product_id не получен!");
            $this->log($aData, "addProduct:EM260");
            return 0;
        }

		foreach ($this->LANG as $lang_id) {
            $set_fields_str = $this->querySetFields(DB_PREFIX."product_description", $aDescriptions, $lang_id);

            if ($set_fields_str)
                $this->query("INSERT INTO " . DB_PREFIX . "product_description 
					SET language_id = " . $lang_id . ", 
					product_id = " . $aData['product_id'] . ",
					" . $set_fields_str
			);
        }

        // Картинка основная
		if (isset($aData['image'])) {
			$this->moveProductImage($aData['image']);
		}

		// Связь с 1С только по Ид объекта из торговой системы
		$aData['action'] = 1;
		$set_fields_str = $this->querySetFields("1c_product", $aData);

		if ($set_fields_str)
			$this->query("INSERT INTO 1c_product SET " . $set_fields_str);

		// Пропишем товар в магазин
		$this->query("INSERT INTO " . DB_PREFIX . "product_to_store
			SET	product_id = " . (int)$aData['product_id'] . ", store_id = " . $this->STORE_ID
		);

		return $aData['product_id'];

    } // addProduct()


    /**
     * Обновляет товар в базе поля в таблице product
     * Если есть характеристики, тогда получает общий остаток по уже загруженным характеристикам прибавляет текущий и обновляет в таблице product
     */
    private function updateProduct($aData, $aDescriptions) {

        $aProductOld = $this->getProduct($aData['product_id']);
        $this->log($aProductOld, "updateProduct=aProductOld");

		if ($this->config->get('exchange1c_module_status') == "debug") {
			return 1;
		}

        $update = 0;
        
		// Производитель
		if (!$this->config->get('exchange1c_product_manufacturer_import') && isset($aData['manufacturer_id']))
			unset($aData['manufacturer_id']);

        // Картинка основная
		if (isset($aData['image'])) {
			$this->moveProductImage($aData['image']);
		}

		$diff_fields = $this->arrayRecursiveDiff($aData, $aProductOld['data']);
		$this->log($diff_fields, "updateProduct=diff_fields");
		
        if ($diff_fields) {
			$set_fields_str = $this->querySetFields(DB_PREFIX."product", $diff_fields, 0, array('product_id'));

            if ($set_fields_str) {
	           $this->query("UPDATE " . DB_PREFIX . "product SET " . $set_fields_str . ", date_modified = NOW()
					WHERE product_id = " . (int)$aData['product_id']
				);
            	$update = 1;
            }
    	}

		$diff_fields = $this->arrayRecursiveDiff($aDescriptions, $aProductOld['description']);
		$this->log($diff_fields, "updateProduct=diff_fields");
		$diff_fields1 = $this->compareArraysData($aDescriptions, $aProductOld['description']);
		$this->log($diff_fields1, "updateProduct=diff_fields1");

        if ($diff_fields) {
			foreach ($this->LANG as $lang_id) {
				$set_fields_str = $this->querySetFields(DB_PREFIX."product_description", $diff_fields, $lang_id);

	            if ($set_fields_str) {
					$this->query("UPDATE " . DB_PREFIX . "product_description SET " . $set_fields_str . "
						WHERE product_id = " . (int)$aData['product_id'] . " AND language_id = " . $lang_id
					);
	            	$update = 1;
	            }
            }
        }

		if ($update)
			$this->query("UPDATE 1c_product SET action = 1 WHERE product_id = " . (int)$aData['product_id']);

        // Очистим кэш товаров
		$this->cache->delete('product');

        return $update;

    } // updateProduct()


    /**
     * С поддержкой нескольких языков
     * Получает данные товара и описание (из двух таблц только: product и product_description)
     */
    private function getProduct($product_id)  {

        $aProduct = array(
        	'data'			=> array(),
        	'description'	=> array()
		);

        $query = $this->query("SELECT * FROM " . DB_PREFIX . "product p
			LEFT JOIN 1c_product 1c
			ON (p.product_id = 1c.product_id)
			WHERE p.product_id = " . (int)$product_id
		);

        if ($query->num_rows) {
			$aProduct['data'] = $query->row;
        } else {
        	return $aProduct;
		}

		$aDescription = array();

        foreach ($this->LANG as $lang_id) {
			// Читаем наименование товара на всех языках
	        $query = $this->query("SELECT * FROM " . DB_PREFIX . "product_description
				WHERE product_id = " . (int)$product_id . " 
				AND language_id = " . $lang_id
			);
		
			if (!$aDescription) {
				$aDescription['name'] 				= array($lang_id => $query->row['name']);
				$aDescription['description'] 		= array($lang_id => $query->row['description']);
				$aDescription['tag'] 				= array($lang_id => $query->row['tag']);
				$aDescription['meta_title'] 		= array($lang_id => $query->row['meta_title']);
				$aDescription['meta_description'] 	= array($lang_id => $query->row['meta_description']);
				$aDescription['meta_keyword'] 		= array($lang_id => $query->row['meta_keyword']);
			} else {
				$aDescription['name'][$lang_id] 				= $query->row['name'];
				$aDescription['description'][$lang_id] 			= $query->row['description'];
				$aDescription['tag'][$lang_id] 					= $query->row['tag'];
				$aDescription['meta_title'][$lang_id] 			= $query->row['meta_title'];
				$aDescription['meta_description'][$lang_id] 	= $query->row['meta_description'];
				$aDescription['meta_keyword'][$lang_id] 		= $query->row['meta_keyword'];
				
			}
        }
        $aProduct['description'] = $aDescription;

        return $aProduct;

    } // getProduct()


    /**
     * Получает все категории продукта из базы в массив
     * первым в массиме будет главная категория
     */
    private function getProductCategories($product_id) {
        $query = $this->query("SELECT *	FROM " . DB_PREFIX . "product_to_category ptc
			LEFT JOIN 1c_category c ON (ptc.category_id = c.category_id)
			WHERE product_id = " . (int)$product_id
		);

        $aCategories = array();
        foreach ($query->rows as $aCategory) {
			$aCategories[$aCategory['guid']] = $aCategory['category_id'];
        }

        return $aCategories;

    } // getProductCategories()


    /**
      * Обновляет категории в товаре
     */
    private function setProductCategories($product_id, $aCategories) {
		$aCategories_old = $this->getProductCategories($product_id);
		$aDeleteCategories = array();
		$aAddCategory = array();
		
		foreach ($aCategories as $guid => $category_id) {
			if (!isset($aCategories_old[$guid])) {
				$this->query("INSERT INTO " . DB_PREFIX . "product_to_category SET
					product_id = " . (int)$product_id . ",
					category_id = " . (int)$category_id
				);
			}
		}
		
		foreach ($aCategories_old as $guid => $category_id) {
			if (!isset($aCategories[$guid])) {
				$this->query("DELETE FROM " . DB_PREFIX . "product_to_category WHERE 
					product_id = " . (int)$product_id . " AND
					category_id = " . (int)$category_id
				);
			}
		}

    } // setProductCategories()


    /**
     * Получает product_id по артикулу
     */
    private function getProductBySKU($sku) {
        $query = $this->query("SELECT product_id FROM " . DB_PREFIX . "product WHERE sku = '" . $this->db->escape($sku) . "'");
        if ($query->num_rows) {
            return $query->row['product_id'];
        }
        return 0;

    } // getProductBySKU()


    /**
     * Получает product_id по модели
     */
    private function getProductByModel($model) {
        $query = $this->query("SELECT product_id FROM " . DB_PREFIX . "product WHERE model = '" . $this->db->escape($model) . "'");
        if ($query->num_rows) {
            return $query->row['product_id'];
        }
        return 0;

    } // getProductByModel()


    /**
     * Получает product_id по наименованию товара
     */
    private function getProductByName($name) {
		
		$query = $this->query("SELECT pd.product_id FROM " . DB_PREFIX . "product p 
			LEFT JOIN " . DB_PREFIX . "product_description pd 
			ON (p.product_id = pd.product_id) 
			WHERE pd.name = LOWER('" . $this->db->escape(strtolower($name[$this->LANG_ID])) . "')"
		);
        
		if ($query->num_rows) {
            return $query->row['product_id'];
        }
        return 0;

    } // getProductByName()


    /**
     * Получает product_id по штрихкоду
     */
    private function getProductByEAN($ean) {

        $query = $this->query("SELECT product_id FROM " . DB_PREFIX . "product WHERE ean = '" . $ean . "'");

        if ($query->num_rows) {
            return $query->row['product_id'];
        }
        return 0;

    } // getProductByEAN()


    /**
     * Читает из базы дополнительные картинки товара
     */
    private function getProductImages($product_id) {

        $images = array();
        $query = $this->query("SELECT * FROM " . DB_PREFIX . "product_image 
			WHERE product_id = " . (int)$product_id . " 
			ORDER BY sort_order"
		);

        if ($query->num_rows) {
            foreach ($query->rows as $row) {
                $images[$row['product_image_id']] = $row['image'];
            }
        }

        return $images;

    } // getProductImages()


    /**
     * Поиск товара по доп полям
     */
    private function syncProduct($aSearch) {

        $product_id = 0;
		//  по артикулу
        if ($this->config->get('exchange1c_product_sync_mode') == "sku") {

            if (empty($aSearch['sku'])) {
            	$this->session->data['error'] = "M250";
            	$this->log("Пустое поле АРТИКУЛ (SKU)");
                return 0;
            }
            $product_id = $this->getProductBySKU($aSearch['sku']);

            // Поиск по модели

        } elseif ($this->config->get('exchange1c_product_sync_mode') == "model" && isset($aSearch['model'])) {

            if (empty($aSearch['model'])) {
				$this->session->data['error'] = "M251";
				$this->log("Пустое поле МОДЕЛЬ (MODEL)");
                return 0;
            }
            $product_id = $this->getProductByModel($aSearch['model']);

            // Поиск по наименованию
        } elseif ($this->config->get('exchange1c_product_sync_mode') == "name") {

            if (empty($aSearch['name'])) {
				$this->session->data['error'] = "M252";
				$this->log("Пустое поле НАИМЕНОВАНИЕ (NAME)");
                return 0;
            }
            $product_id = $this->getProductByName($aSearch['name']);

            // Поиск по штрихкоду
        } elseif ($this->config->get('exchange1c_product_sync_mode') == "ean") {

            if (empty($aSearch['ean'])) {
				$this->session->data['error'] = "M253";
				$this->log("Пустое поле ШТРИХКОД (EAN)");
                return 0;
            }
            $product_id = $this->getProductByEan($aSearch['ean']);

            // Поиск по коду
        } elseif ($this->config->get('exchange1c_product_sync_mode') == "code") {

            if (isset($aSearch['code'])) {
				$this->session->data['error'] = "M254";
				$this->log("Пустое поле КОД (CODE)");
				if (!$aSearch['code']) return 0;
            } else {
                return 0;
            }
            $product_id = $this->getProductById($aSearch['code']);
        }
        return $product_id;

    } // syncProduct()


    /**
     *  ********************************** ФУНКЦИИ ДЛЯ РАБОТЫ С КАРТИНКАМИ ************************************
     */


    /**
     * Читает картинки из XML в массив
     */
    private function parseImages($xml) {

        $images = array();

        foreach ($xml as $image) {
            $image = (string )$image;

            // Пропускаем файл с пустым именем
            if (empty($image)) {
                $this->log("Пустое наименование картинки, пропуск.", 2);
                continue;
            }

            if (!file_exists(DIR_IMAGE . $image) && $this->config->get('exchange1c_product_images_check')) {
                // Пропускаем несуществующие файлы если включено в настройках
                $this->log("файл не существует, согласно настройкам не будет записан в товар", 2);
                continue;
            }

            $this->log("Картинка: " . $image, 2);
            $images[] = $image;

        } // foreach()

        return $images;

    } // parseImages()


    /**
     * Перемещает картинки с временной папки в папку images CMS
     */
    private function moveProductImage($image) {
    	$upload_image_path = DIR_UPLOAD . $this->session->data['upload_dirname'] . $image;
    	$catalog_image_path = DIR_IMAGE . $image;

		if (file_exists($upload_image_path)) {

            // Если существует старая, сравниваем
            if (file_exists($catalog_image_path)) {

                if (filesize($catalog_image_path) == filesize($upload_image_path)) {
                	$this->log("Картинка не изменилась: " . $image, 2);
                    return true;
                }

                // Если разные, удаляем старое изображение
                unlink($catalog_image_path);
            }

            // Проверим и создадим еобходимые каталоги
            if (!$this->createDirectories(DIR_IMAGE, $image)) {
            	return false;
            }

            if (!rename($upload_image_path, $catalog_image_path)) {
                $this->session->data['error'] = 'M100';
                $this->log("Изображение не удалось переместить из " . $upload_image_path . " в " . $catalog_image_path, "moveProductImage:EM100");
                return false;
            }

        } elseif (file_exists($catalog_image_path)) {
			// Если картинка отсутствует в загрузке, но была загружена ранее
			//unlink($catalog_image_path);
            return true;

        } else {
            $this->log("Картинка отсутствует: " . $upload_image_path);
        }

        return true;

    } // moveProductImage()


    /**
     * Добавляет картинки в новый товар
     */
    private function addProductImages($product) {

        //$this->log($product['images'], 2);
        
        // Порядок сортировки пока не обновляет
        foreach ($product['images'] as $sort_order => $image) {
            $this->query("INSERT INTO " . DB_PREFIX . "product_image SET 
				product_id = " . $product['product_id'] . ", 
				image = '" . $this->db->escape($image) . "', 
				sort_order = '" . $sort_order . "'"
			);

            if (!$this->moveProductImage($this->session->data['upload_dirname'], $image)) {
				return false;
            }
        }

        return true;

    } // addProductImages()


    /**
     * Устанавливает дополнительные картинки в товаре
     */
    private function setProductImages($product_id, $aImages) {

		$aImagesOld = $this->getProductImages($product_id);

		$status = 0;
		foreach ($aImages as $key => $sImage) {

			$key_search = array_search($sImage, $aImagesOld);
			if ($key_search === false) {
                $this->query("INSERT INTO " . DB_PREFIX . "product_image SET 
					product_id = '" . $product_id . "', 
					image = '" . $this->db->escape($sImage) . "', 
					sort_order = '" . $key . "'"
				);
			}

			if (!$this->moveProductImage($sImage)) {
				return false;
			}
		}

		// Удалим неиспользуемые
		foreach ($aImagesOld as $product_image_id => $sImage) {
			$key_search = array_search($sImage, $aImages);
			if ($key_search === false) {
				$this->query("DELETE FROM " . DB_PREFIX . "product_image WHERE product_image_id = " . (int)$product_image_id);
				unlink(DIR_IMAGE . $sImage);
			}
		}

		return true;

	} // setProductImages()


    /**
     ***************************** ФУНКЦИИ ДЛЯ РАБОТЫ С ФАЙЛАМИ ********************************
     */


    /**
     * Удаляет в кэше эту картинку
     */
    private function deleteCacheImage($image_info) {

        if (!$image_info) {
            // Нечего удалять
            return false;
        }

        // Путь в папке кэш к картинке
        $path = str_replace(DIR_IMAGE, DIR_IMAGE . "cache/", $image_info['dirname']);

        // Откроем папку для чтения
        $delete_files = array();
        $dh = @opendir($path);

        // Если каталог не открывается
        if (!$dh) {
            $this->log("Каталог не существует: " . $path);
            return false;
        }

        while (($file = readdir($dh)) !== false) {

            $find = strstr($file, $image_info['filename']);

            if ($find != "") {
                $delete_files[] = $find;
            }
        }

        closedir($dh);

        if ($delete_files) {

            foreach ($delete_files as $filename) {

                if (file_exists($path . "/" . $filename)) {
                    unlink($path . "/" . $filename);
                }

                $this->log("Удалена картинка из кэша: " . $filename);
            }
        }

        return true;

    } // deleteCacheImage()


    /**
     * Создание каталогов по порядку в указанной папке
     * $root - это директория, в конце обязательно должен быть разделитель "/" или "\"
     * Решение взято из источника
     * http://qaru.site/questions/280376/creating-a-file-inside-a-folder-that-doesnt-exist-yet-in-php
     */
    private function createDirectories($root, $path) {

        if (!file_exists($root)) {
            $this->session->data['error'] = 'M280';
            $this->log("Каталог не существует: " . $root);
            return false;
        }

        $folders = explode(DIRECTORY_SEPARATOR, $path);
        array_pop($folders);
        $current_path = $root;

        foreach ($folders as $folder) {
			if (empty($folder)) continue;
			$current_path .= $folder . DIRECTORY_SEPARATOR;

			if (!file_exists($current_path)) {
                if (!mkdir($current_path, 0775)) {
                    $this->session->data['error'] = 'M281';
                    $this->log("Ошибка создания каталога: " . $current_path);
                    return false;
                }
            }
        }

        return true;

    } // createDirectories()


    /**
     ********************************** КАТЕГОРИИ САЙТА *********************************
     */


    /**
     * Читает все категории из базы данных в массив, где ключем является GUID
     */
    private function getCategories() {

        $data = array();
        
        if ($this->config->get('exchange1c_module_status') == "debug") {
        	return $data;
       	}

        $query = $this->query(
			"SELECT category_id, guid 
			FROM 1c_category"
		);

        foreach ($query->rows as $row) {
            $data[$row['guid']] = $row['category_id'];
        }

        return $data;

    } // getCategories()


    /**
     * Сравнивает массивы и формирует список измененных полей для запроса
     * data_new - новые данные
     * data_old - старые данные
     */
    private function compareArraysData($data_new, $data_old) {

		$this->log($data_new, "compareArraysData=data_new");
		$this->log($data_old, "compareArraysData=data_old");
        
		$result = array();

        foreach ($data_new as $field => $value) {

            if (is_array($value)) {
            	$this->log($value, "compareArraysData=value массив!");
            	foreach ($value as $key => $val) {
            		if (is_array($val)) {
            			continue;
            		}
            		if (!isset($data_old[$field])) {
            			$this->session->data['error'] = "M320";
	           			$this->log($data_old, "compareArraysData:EM320=data_old");
            			return false;
            		}
					$this->log($data_old[$field], "compareArraysData=data_old[field]");
					if (isset($data_old[$field][$key]) && $data_old[$field][$key] == $val) {
						continue;
					} else {
						$result[$field][$key] = $this->db->escape($data_new[$field][$key]);
            		}
				}
                continue;
			}

			if (!isset($data_old[$field])) {
				$this->log("Поле '" . $field . "' отсутствует в старых данных, пропущено", "compareArraysData=field");
				continue;
			}
			
			if ($value != $data_old[$field]) {
				$this->log("Отличие в " . $field . " = '" . $value . "'", "compareArraysData");
				$result[$field] = $this->db->escape($data_new[$field]);
			}
		}
        if ($result) $this->log($result, "compareArraysData=result");

        return $result;

    } // compareArraysData()


    /**
     * Заполняет родительские категории у продукта
     */
    public function fillParentsCategories($product_category) {

         $categories = array();

        foreach ($product_category as $category_id) {
            $parents = $this->findParentsCategories($category_id);

            foreach ($parents as $parent_id) {
                $key = array_search($parent_id, $categories);

                if ($key === false) {
                    $categories[] = $parent_id;
                }
            }
        }

        return $categories;

    } // fillParentsCategories()


    /**
     * Ищет все родительские категории
     */
    private function findParentsCategories($category_id) {

        $result = array();
        $query = $this->query("SELECT * FROM " . DB_PREFIX .
            "category WHERE category_id = " . (int)$category_id);

        if (isset($query->row['parent_id'])) {

            if ($query->row['parent_id'] <> 0) {
                $result[] = $query->row['parent_id'];
                $result = array_merge($result, $this->findParentsCategories($query->row['parent_id']));
            }
        }

        return $result;

    } // findParentsCategories()


    /**
     ************************************ СВОЙСТВА 1С *************************************
     */

    /**
     * Читает свойства из базы данных в массив
     */
    private function getProperties() {

		$data = array();

        if ($this->config->get('exchange1c_module_status') == "debug") {
        	return $data;
       	}

        $query = $this->query("SELECT * FROM 1c_property");

        foreach ($query->rows as $row) {
            $data[$row['guid']] = array (
                'attribute_id'	=> $row['attribute_id'],
                'manufacturer'	=> $row['manufacturer'],
                'type'			=> $row['type'],
                'version' 		=> $row['version']
			);
        }

        $this->log("Свойств в базе: " . count($data), "getProperties=count(data)");

		return $data;

    } // getProperties()


    /**
     * Получает все значения свойства
     * Мультиязычность
     */
    private function getPropertyValues($property_id) {

        $data = array();

        $query = $this->query("SELECT * FROM 1c_property_value pv
			LEFT JOIN 1c_property_value_description pvd
			ON (pv.property_value_id = pvd.property_value_id)
			WHERE pv.property_id = " . (int)$property_id
		);

        if (!$query->num_rows) {
        	return $data;
        }

		foreach ($query->rows as $property_value) {
        	$guid = $property_value['guid'];
	
			if (!isset($data[$guid])) {
					
				$data[$guid] = array(
					'property_value_id'	=> $property_value['property_value_id'],
					'guid'				=> $property_value['guid'],
					'property_id'		=> $property_value['property_id'],
					'date_modified'		=> $property_value['date_modified'],
					'name'				=> array(
						$property_value['language_id'] => $property_value['name']
					)
				);

			} else {
				$data[$guid]['name'][$property_value['language_id']] = $property_value['name'];
			}
		}
		
        return $data;

    } // getPropertyValues()


    /**
     * Создает или обновляет связи свойства 1С с атрибутом сайта
     */
    private function setProperty($property, $property_info) {

		if ($this->config->get('exchange1c_module_status') == "debug") {
			$property['property_id'] = 1;
			return $property;
		}

		if ($property_info) {
			$property['property_id'] = $property_info['property_id'];

			$fields_update = $this->compareArraysData($property, $property_info);
			$set_fields_str = $this->querySetFields("1c_property", $fields_update, 0, array('property_id'));

			if ($set_fields_str) {
	            $this->query("UPDATE 1c_property SET " . $set_fields_str . " 
					WHERE property_id = " . (int)$property['property_id']
				);
			}
			
			// Описание
			$property['attribute_id'] = $property_info['attribute_id'];
			foreach ($this->LANG as $lang_id) {
				if ($property['name'][$lang_id] != $property_info['name'][$lang_id]) {
		            $this->query("UPDATE " . DB_PREFIX . "attribute_description SET 
						name = '" . $this->db->escape($property['name'][$lang_id]). "', 
						WHERE 
						attribute_id = " . (int)$property_info['attribute_id'] . ", 
						language_id = " . (int)$lang_id
					);
				}
			}

        } else {

            // Новое свойство
            $set_fields_str = $this->querySetFields("1c_property", $property);
            
			if ($set_fields_str) {
				$this->query("INSERT INTO 1c_property SET " . $set_fields_str);
				$property['property_id'] = $this->db->getLastId();
			}

		}
		return $property;

    } // setProperty()


    /**
     ********************************** ФУНКЦИИ ДЛЯ РАБОТЫ С АТРИБУТАМИ САЙТА **************************************
     */

    /**
     * Получает атрибут из базы
     * Мультиязычность
     */
    private function getAttribute($attribute_id, $desc_only = 0) {
		$attribute = array();

        if (!$desc_only) {
			$query_a = $this->query("SELECT * FROM " . DB_PREFIX . "attribute WHERE attribute_id = " . (int)$attribute_id);

	        if ($query_a->num_rows) {
	            $attribute = $query_a->row;
	        }
        }

        $query_ad = $this->query("SELECT * FROM " . DB_PREFIX . "attribute_description 	WHERE attribute_id = " . (int)$attribute_id);

		if ($query_ad->num_rows) {
	        $attribute['name'] = array();

	        foreach ($query_ad->rows as $row) {
	            $attribute['name'][$row['language_id']] = $row['name'];
	        }
		}

        return $attribute;

    } // getAttribute()


    /**
     * Ищет группу атрибута по названию и возвращает id
     * Если группы такой нет, тогда создает содним названием на всех языках системы
     */
    private function setAttributeGroup($name = "", $sort_order = 0) {

        if (!$name) {
            $search_name = $this->config->get("exchange1c_attribute_group_name");
            $sort_order = 0;
        } else {
        	$search_name = $name;
        }
            
		$attribute_group_id = 0;
		$query = $this->query("SELECT * FROM " . DB_PREFIX . "attribute_group_description agd
			LEFT JOIN " . DB_PREFIX . "attribute_group ag ON (agd.attribute_group_id = ag.attribute_group_id)
			WHERE LOWER(agd.name) = '" . $this->db->escape(strtolower($search_name)) . "' LIMIT 1"
		);
		if ($query->num_rows) {
			$attribute_group_id = $query->row['attribute_group_id'];
			$sort_order_old = $query->row['sort_order'];
		}


        if ($this->config->get('exchange1c_module_status') == "debug") {
			$this->log("DEBUG: name = " . $search_name);
			$this->log("DEBUG: attribute_group_id = " . $attribute_group_id);
        	return 1;
       	}
        
		if (!$attribute_group_id) {
            // Добавляем группу
            $this->query("INSERT INTO " . DB_PREFIX . "attribute_group SET sort_order = " . (int)$sort_order);
            $attribute_group_id = $this->db->getLastId();
            
			// Запишем название группы на всех языках (без перевода на другие языки)
            foreach ($this->LANG as $lang_id)  {
				$this->query("INSERT INTO " . DB_PREFIX . "attribute_group_description SET
					attribute_group_id = " . (int)$attribute_group_id . ",
					language_id = " . (int)$lang_id . ",
					name = '" . $this->db->escape($search_name) . "'"
				);
            }

            $this->log("Создана группа атрибута: " . $search_name, "setAttributeGroup=search_name");

        } else {
        	if ($sort_order_old != $sort_order) {
				$this->query("UPDATE " . DB_PREFIX . "attribute_group SET sort_order = " . (int)$sort_order . "
					WHERE attribute_group_id = " . (int)$attribute_group_id
				);
        	}
        }

        return $attribute_group_id;

    } // setAttributeGroup()


    /**
     * Ищет в базе атрибут
     */
    private function searchAttribute($attribute, $lang_id = 0) {

		if (!$lang_id)
			$lang_id = $this->LANG_ID;

		$name = $attribute['name'][$lang_id];
		
   		$query = $this->query("SELECT attribute_id FROM " . DB_PREFIX . "attribute_description
			WHERE language_id = " . $lang_id . " AND name = '" . $this->db->escape($name) . "'"
		);

		if ($query->num_rows)
			return $query->row['attribute_id'];

		return 0;
   		
   	} // searchAttribute()


    /**
     * Добавляет или обновляет атрибут в базе
     * Мультиязычный
     */
    private function setAttribute($attribute) {

		if ($this->config->get('exchange1c_module_status') == "debug") {
			return 1;
		}
		
		// Поищем атрибут в базе по наименованию на языке по-умолчанию
		$attribute['attribute_id'] = $this->searchAttribute($attribute, $this->LANG_ID);

        // Определим это новый атрибут или существующий
        if (!$attribute['attribute_id']) {

			// Новое свойство, добавляем
			$set_fields_str = $this->querySetFields(DB_PREFIX."attribute", $attribute, 0, array('attribute_id'));
			//$this->log($set_fields_str, 2);
			
            if ($set_fields_str)
				$this->query("INSERT INTO " . DB_PREFIX . "attribute SET " . $set_fields_str);

            // Получим id добавленного атрибута
            $attribute['attribute_id'] = $this->db->getLastId();
            
            // Добавим описание
            foreach ($this->LANG as $lang_id) {
				$set_fields_str = $this->querySetFields(DB_PREFIX."attribute_description", $attribute, $lang_id);
				//$this->log($set_fields_str, 2);

		        if ($set_fields_str) {
		            $this->query("INSERT INTO " . DB_PREFIX . "attribute_description 
						SET language_id = " . (int)$lang_id . ", " . $set_fields_str . ""
					);
		        }
            }
            return $attribute['attribute_id'];

        } else {

			// Атрибут существует, проверим наименование на других языках
			$attribute_info = $this->getAttribute($attribute['attribute_id']);
			$this->log($attribute_info, 2);
			
			if ($attribute['attribute_group_id'] != $attribute_info['attribute_group_id']) {
				$this->query("UPDATE " . DB_PREFIX . "attribute 
					SET attribute_group_id = " . (int)$attribute['attribute_group_id'] . "
					WHERE attribute_id = " . (int)$attribute['attribute_id']
				);
			}

			foreach ($this->LANG as $lang_id) {

				if ($attribute['name'][$lang_id] != $attribute_info['name'][$lang_id]) {
					$set_fields_str = $this->querySetFields(
						DB_PREFIX."attribute_description", 
						$attribute, 
						$lang_id, 
						array ('attribute_id','language_id')
					);

			        if ($set_fields_str) {
			            $this->query("UPDATE " . DB_PREFIX . "attribute_description 
							SET " . $set_fields_str . " 
							WHERE language_id = " . (int)$lang_id . " 
							AND attribute_id = " . (int)$attribute['attribute_id']
						);
			        }
				}
			} // foreach
        }

        return $attribute['attribute_id'];

    } // setAttribute()


    /**
     * Добавляет или обновляет значения атрибута
     * Не удаляет старые значения атрибута!
     * Multilanguage
     */
    private function setPropertyValue($property, $value_info) {

		if (empty($property['values'])) {
			return 0;
    	}

        if ($this->config->get('exchange1c_module_status') == "debug") {
        	$this->log("setPropertyValue(): property");
        	$this->log($property);
        	$this->log("setPropertyValue(): value_info");
        	$this->log($value_info);
        	return 1;
        }
        
		foreach ($property['values'] as $value) {
            // Проверим на существование по Ид
            if (isset($value_info[$value['guid']])) {

                // Значение атрибута должно быть на всех языках
                $update = 0;
                foreach ($this->LANG as $lang_id) {

					if ($this->config->get('exchange1c_module_status') == "debug") {
						continue;
					}
					
					if ($value['name'][$lang_id] != $value_info[$value['guid']]['name'][$lang_id]){
	                    $this->query("UPDATE 1c_property_value_description 
							SET name = '" . $this->db->escape($value['name'][$lang_id]) . "'
							WHERE property_value_id = " . (int)$value_info[$value['guid']]['property_value_id'] . "
							AND language_id = " . (int)$lang_id
						);
						$update = 1;
					}
                }
                if ($update) {
					$this->query("UPDATE 1c_property_value 
						SET date_modified = NOW()
						WHERE property_value_id = " . (int)$value_info[$value['guid']]['property_value_id']
					);
                	
                }

            } else {
            	// Новое значение
            	$set_fields_str = $this->querySetFields("1c_property_value", $value, 0, array('property_value_id'));

				if ($this->config->get('exchange1c_module_status') == "debug") {
					$value['property_value_id'] = 0;

				} else {

					if ($set_fields_str) {

						$this->query("INSERT INTO 1c_property_value
							SET property_id = " . (int)$property['property_id'] . ",
							date_modified = NOW(), " . $set_fields_str
						);

						$value['property_value_id'] = $this->db->getLastId();
			            //$this->log($value, 2);
	
				        // Запишем наименование/описание на всех языках
						foreach ($this->LANG as $lang_id) {
				            $this->query("INSERT INTO 1c_property_value_description SET 
								property_value_id = " . (int)$value['property_value_id'] . ",
								property_id = " . (int)$property['property_id'] . ", 
								language_id = " . (int)$lang_id . ", 
								name = '" . $this->db->escape($value['name'][$lang_id]) . "'"
							);
				        }
					}
				}
            }
            
			if ($property['manufacturer']) {
				$value['manufacturer_id'] = $this->setManufacturer($value);
			}

        } // foreach values1c
        return 1;

    } // setPropertyValue()


    /**
     * Читает все группы свойств из базы данных в массив
     * Только на основном языке
     */
    private function getAttributeGroups() {
        $data = array();

		$query = $this->query("SELECT * FROM " . DB_PREFIX . "attribute_group_description WHERE language_id = " . $this->LANG_ID);

		foreach ($query->rows as $row) {
			if (!isset($data[$row['attribute_group_id']])){
				$data[$row['attribute_group_id']] = $row['name'];
			}
		}

        return $data;

    } // getAttributeGroups()


    /**
     * Возвращает массив свойства по Ид, а также все значения
     */
    private function getPropertyInfoByGuid($guid) {

		$data = array();
		
		if ($this->config->get('exchange1c_module_status') == "debug") {
			return $data;
		}

		$query = $this->query("SELECT * FROM 1c_property WHERE guid = '" . $this->db->escape($guid) . "'");
		$this->log($query->rows, "getPropertyInfoByGuid=query->rows");

		// Не найдено свойство
		if (!$query->num_rows) {
			return $data;
		}

		// Дубликаты
		if ($query->num_rows > 1) {
			$this->session->data['error'] = "M290";
			$this->log("По Ид получено более одного свойства!");
			$this->log($query, "getPropertyInfoByGuid=query:EM290");
			return $data;
		}

		$data = $query->row;
		
		if ($data['attribute_id']) {
			// Это атрибут
			$query = $this->query("SELECT name, language_id FROM " . DB_PREFIX . "attribute_description
				WHERE attribute_id = " . (int)$data['attribute_id']
			);
			if ($query->num_rows != count($this->LANG)) {
				$this->session->data['error'] = "M291";
				$this->log("Получено названия атрибутов не равное количеству языков в системе");
				$this->log($query, "getPropertyInfoByGuid=query:EM291");
				return $data;
			}

			$data['name'] = array();

			foreach ($query->rows as $row) {
				$data['name'][$row['language_id']] = $row['name'];
			}

		} else {
			// Это производитель
			$data['name'] = array();
			foreach ($this->LANG as $lang_id) {
				$data['name'][$lang_id] = "Производитель";
			} 
		}
		
		$data['values'] = $this->getPropertyValues($data['property_id']);

		// Значения читаем только для справочников
		if ($data['type'] != 'R') {
			return $data;
		}
		
		// Это значения производителя
		if ($data['manufacturer']) {

			foreach ($data['values'] as $key => $pvalue) {
	
				$search_data = array(
					'property_value_id'	=> $pvalue['property_value_id']
				);

				$manufacturer = $this->getManufacturer($search_data);
	
				if (!$manufacturer) {
					$this->session->data['error'] = "M293";
					$this->log("Не найден производитель " . $pvalue['name'] . " в базе данных, либо не был загружен справочник", "getPropertyInfoByGuid=pvalue[name]:EM293");
					return false;
				}

				$data['values'][$key]['manufacturer_id'] = $manufacturer['manufacturer_id'];
				$data['values'][$key]['manufacturer_guid'] = $manufacturer['guid'];

				if ($pvalue['name'][$this->LANG_ID] != $manufacturer['name'][$this->LANG_ID]) {
					$this->session->data['error'] = "M292";
					$this->log("Есть расхождения наименования значения свойства и производителя на основном языке");
					$this->log($pvalue, "getPropertyInfoByGuid=pvalue:EM292");
					$this->log($manufacturer, "getPropertyInfoByGuid=manufacturer:EM292");
					return false;
				}
			}
		}

		return $data;

    } // getPropertyInfoByGuid()


    /**
     * Ищет в массиве по Ид значение свойства
     */
    private function searchPropertyValue($values_info, $guid) {

    	$data = array();
	
		foreach ($values_info as $value) {
    		if ($value['guid'] == $guid) {
    			$data = $value;
    			break;
    		}
    	}
    	
    	return $data;

    } // searchPropertyValue()


    /**
     * Возвращает символ типа объекта
     */
    private function getCharPropertyType($str) {

		$property_type = array(
			'R'		=> "Справочник",
			'S'		=> "Строка",
			'N'		=> "Число"
		);

		$char = array_search($str, $property_type);
		if ($char) {
			return $char;
		}
		
		return "";
    	
    } // getCharPropertyType()


	/**
	*/
	private function getPropertyGroups() {

		$aResult = array();
		$str = $this->config->get('exchange1c_property_groups');
		$re = '/(.+?)\s\{(.*?)\}/m';
		preg_match_all($re, $str, $matches, PREG_SET_ORDER, 0);

		foreach ($matches as $match) {
			if (count($match) == 3) {
				$aResult[$match[1]] = $match[2];
			}
		}
		return $aResult;

	} // getPropertyGroups()


	/**
	*/


	private function searchPropertyGroup($needle, $haystack) {

		foreach($haystack as $key=>$value) {
			$current_key=$key;
			$found = stripos($value, $needle);
			if($found !== false) {
				return $current_key;
			}
		}
		return false;

	} //searchPropertyGroup()


   /**
     * Загружает атрибуты (Свойства из 1С) в классификаторе из XML
     * Данные загружаются напрямую в базу данных
     */
    private function parseProperty($xml) {

		if (!$this->config->get('exchange1c_attribute_import')) {
			$this->log("Загрузка свойств 1С отключена");
			return true;
		}
		
		// Наименования свойств которые нужно загрузить в производители
		$aManufacturerPropertyName = array(
			'Производитель',
			'Изготовитель',
			'Бренд'
		);
		
		// Группы свойств
		$aPropertyGroups = $this->getPropertyGroups();
		$this->log($aPropertyGroups, "parseProperty=aPropertyGroups");
		
		
        // Для разных версий XML
        if ($xml->Свойство) {
        	$properties_xml = $xml->Свойство;
        } else {
        	$properties_xml = $xml->СвойствоНоменклатуры;
        }

		$prop_num = 0;
		$prop_total = count($properties_xml);
		$this->log("Найдено свойств в XML: " . $prop_total);

        foreach ($properties_xml as $property_xml) {

			$property = array (
            	'guid'				=> (string)$property_xml->Ид,
            	'type'				=> $this->getCharPropertyType((string)$property_xml->ТипЗначений),
            	'name'				=> $this->getLanguageName($property_xml->Наименование),
            	'delete_mark'		=> (string)$property_xml->ПометкаУдаления == 'true' ? 1 : 0,
            	'version'			=> (string)$property_xml->НомерВерсии,
            	'property_id'		=> 0,
				'attribute_group_id'=> 0,
            	'attribute_id'		=> 0,
            	'manufacturer'		=> 0,
            	'values'			=> array(),
            	'update'			=> 0
			);
			
			// Название свойства на языке по-умолчанию
			$property_name = $property['name'][$this->LANG_ID];
			
			if (in_array($property_name, $aManufacturerPropertyName))
				$property['manufacturer'] = true;
			
			$prop_num ++;
			$this->log("~Свойство " . $prop_num . " из " . $prop_total . " [" . $property['type'] . "] " . $property_name, "parseProperty");

			$sPropertyGroupName = $this->searchPropertyGroup($property_name, $aPropertyGroups);
			$property['attribute_group_id'] = $this->setAttributeGroup($sPropertyGroupName);
			
            // Найти свойство в таблице property
            $aPropertyOld = $this->getPropertyInfoByGuid($property['guid']);
            $this->log($aPropertyOld, "parseProperty=aPropertyOld");
            
			// Найти атрибут если свойство есть в базе
			if ($aPropertyOld) {
				// Свойство есть в базе
				$property['property_id'] = $aPropertyOld['property_id'];
				$property['attribute_id'] = $aPropertyOld['attribute_id'];

				//$this->log($property, 2);

				// Изменилась версия, надо обновить
				if ($property['version'] && $property['version'] != $aPropertyOld['version']) {
					$property['update'] = 1;
				}

				foreach ($property['name'] as $lang_id => $name) {
					// Обновилось наименование
					if ($name != $aPropertyOld['name'][$lang_id] || $property['update']) {
						$this->query("UPDATE " . DB_PREFIX . "attribute_description 
							SET name = '" . $this->db->escape($name) . "' 
							WHERE attribute_id = " . (int)$property['attribute_id'] . " 
							AND language_id = " . (int)$lang_id
						);
					}
				}
			}

			// Если не производитель, добавим Свойство в атрибуты
			if(!$property['manufacturer']) {
				$property['attribute_id'] = $this->setAttribute($property);
			}

			$property = $this->setProperty($property, $aPropertyOld);
			
			// Если это справочник, то значения атрибутов читаем на нескольких языках
			if ($property['type'] == 'R') {
           		$values_info = array();

            	if ($aPropertyOld)
					$values_info = $aPropertyOld['values'];

            	if (!$property_xml->ВариантыЗначений)
            		continue;

				// Значения ищем по Ид в таблице property_value
                foreach ($property_xml->ВариантыЗначений->Справочник as $value_xml) {

		            $value = array (
		            	'guid'					=> trim((string)$value_xml->ИдЗначения),
		            	'property_value_id'		=> 0,
		            	'name'					=> $this->getLanguageName($value_xml->Значение)
					);

					// Найти значение свойства по Ид
					$value_info = $this->searchPropertyValue($values_info, $value['guid']);
					$this->log($value_info, "parseProperty=value_info");

					if ($value_info) {
						$value['property_value_id'] = $value_info['property_value_id'];
					}

					$property['values'][$value['guid']] = $value;
				}

				if (!$property['property_id']) {
					$this->session->data['error'] = "M030";
					$this->log("Значения нельзя записать так как property_id = 0, то есть не сохранено само свойство");
					$this->log($property, "parseProperty=property:EM030");
					$this->log($value, "parseProperty=value:EM030");
					return false;
				}
				$this->setPropertyValue($property, $values_info);

    		} //Справочник
			
			$this->PROPERTY[$property['guid']] = $property;

			if ($property['type'] == 'S')
				$this->log(" - Значений " . count($property['values']));

        } // foreach

        return true;

    } // parseProperty()


    /**
     * Ищет свойство по Ид
     */
    private function getProperty($guid) {
    	$data = array();

		if (isset($this->PROPERTY[$guid])) {
			$query = $this->query("SELECT * FROM 1c_property WHERE guid = '" . $this->db->escape($guid) . "'");
			$data = $query->row;
		} else {
			return $data;
		}

		if ($data['manufacturer']) {
			$data['name'] = array();

			foreach ($this->LANG as $lang_id) {
				$data['name'][$lang_id] = "Производитель";
			}

		} else {
			// наименования свойства берем из атрибута
			$attribute_info = $this->getAttribute($data['attribute_id']);
			$this->log($attribute_info, "getProperty=attribute_info");

			if (!$attribute_info) {
				$this->session->data['error'] = "M040";
				$this->log("Не найден атрибут который привязан к свойству в 1С");
				$this->log($data, "getProperty=data:EM040");
				return array();
			}
			
			$data['name'] = $attribute_info['name'];
		}

		$data['values'] = array();
		
		if ($data['type'] != "R")
			return $data;

    	// Все значения свойства 
		$query = $this->query("SELECT * FROM 1c_property_value pv
			LEFT JOIN 1c_property_value_description pvd
			ON (pv.property_value_id = pvd.property_value_id)
			WHERE pv.property_id = " . (int)$data['property_id']
		);
		//$this->log($query->num_rows, 2);

		foreach ($query->rows as $row) {
			//$this->log($row, 2);

			if (!isset($data['values'][$row['guid']])) {
				$data['values'][$row['guid']] = array(
					'property_value_id'		=> $row['property_value_id'],
					'date_modified'			=> $row['date_modified'],
					'name'					=> array(
						$row['language_id'] => $row['name']
					)
				);

			} else {
				$data['values'][$row['guid']]['name'][$row['language_id']] = $row['name'];
			};
		};
		return $data;
		
   	} //getProperty()


    /**
     * Читает свойства товара из XML (товар, категория) и записывает их в массив
     * Поддержка: Мультиязычность
     */
    private function parseProductProperty($xml) {

        $data = array();
		$manufacturer = array();
        $attributes = array();
        $properties = array();
        
        // Добавляем в массив свойства которые не нужно загружать
		$property_skip = array(
        	'Коды поставщиков'
		);

        // Читаем все свойства из 1С
        foreach ($xml->ЗначенияСвойства as $property_xml) {
			// Несколько языков
			$property = array(
	            'attribute_id' 	=> 0,
	            'manufacturer' 	=> 0,
	            'guid' 			=> (string)$property_xml->Ид,
	            'type'		 	=> "",
	            'version'		=> 0,
				'name'			=> array(),
				'value' 		=> array(),
			);

            // Пропускаем с пустыми значениями
            if (empty($property_xml->Значение)) {
                $this->log("Пустое значение у свойства Ид " . $property['guid'], "parseProductProperty");
                continue;
            }

			if ($this->config->get('exchange1c_module_status') == "debug") {
				return false;
			}
			
			// Читаем свойство из его значения из базы
			$property_info = $this->getProperty($property['guid']);
			//$this->log($property_info, 2);
			
			if (!$property_info) {
				// Свойство не найдено в базе
				$this->session->data['error'] = "M050";
				$this->log("Невозможно установить свойство в товаре так как по Ид его не удалось найти.\nИд = " . $property['guid']);
				$this->log($property, "parseProductProperty=property:EM050");
				return false;
			}

			$property['attribute_id'] 	= $property_info['attribute_id'];
			$property['manufacturer'] 	= $property_info['manufacturer'];
			$property['type'] 			= $property_info['type'];
			$property['name']			= $property_info['name'];
			
			// Пропускаем свойства которые не нужно загружать в товар
			if ($property_skip) {
				if (array_search($property['name'][$this->LANG_ID], $property_skip) !== FALSE) {
					$this->log(" - Свойство отключено" . $property['name'][$this->LANG_ID]);
					continue;
				}
			}

            // Значение
            $value = array(
                'guid' 					=> "",
                'name'					=> array(),
                'property_value_id'		=> 0
			);
			
			if ($property['type'] == "R") {
				// Тип справочник
				$value['guid']	= trim((string)$property_xml->Значение);

				// Если нет в свойствах такого значения пропускаем и запишем в лог
				// значит что-то в 1С не так со свойствами у этого товара
				if (!isset($property_info['values'][$value['guid']])) {
					$this->log("Не найдено значение свойства, проверьте в 1С ");
					$this->log($property_xml, "parseProductProperty=property_xml");
					$this->log($property_info['values'], "parseProductProperty=property_info[values]");
					$this->log($value, "parseProductProperty=value");
					$this->log($property, "parseProductProperty=property");
					continue;
				}

				$value['property_value_id'] = $property_info['values'][$value['guid']]['property_value_id'];
				foreach ($this->LANG as $lang_id) {
					$value['name'][$lang_id] = $property_info['values'][$value['guid']]['name'][$lang_id];
					//$this->log($value, 2);
				}

			} else {
				// Текстовое значение
				$value['name']	= $this->getLanguageName($property_xml->Значение);
				//$this->log($value, 2);
			}

			$property['value'] = $value;

            // Производитель
            if ($property['manufacturer']) {
				$manufacturer = $this->getManufacturer($value);
				if ($manufacturer) {
					$data['manufacturer_id'] = $manufacturer['manufacturer_id'];
					$this->log(" - Производитель (из свойств)" . $value['name'][$this->LANG_ID]);
	                continue;
				}
            }

			$value_name = $value['name'][$this->LANG_ID];

			switch ($property['name'][$this->LANG_ID]) {
                case 'Вес':
                    $data['weight'] = $this->formatFloat($value_name);
                    break;

                case 'Длина':
                    $data['length'] = $this->formatFloat($value_name);
                    break;

                case 'Ширина':
                    $data['width'] = $this->formatFloat($value_name);
                    break;

                case 'Высота':
                    $data['height'] = $this->formatFloat($value_name);
                    break;

                default:
                    $attributes[$property['attribute_id']] = $property;
            }

            $properties[$property['guid']] = $property;

        } // foreach

        $result = array(
			'data' 			=> $data,
			'manufacturer'	=> $manufacturer,
			'attributes'	=> $attributes,
			'properties' 	=> $properties
		);
		//$this->log($result);

        return $result;

    } // parseProductAttributes()


    /**
     * Записывает свойства товара (мультиязычный)
     */
    private function setProductAttribute($product_id, $aAttributes) {

		$this->log($aAttributes);

		// Получим старые атрибуты товара
		$aAttributesOld = array();
		$query = $this->query("SELECT * FROM " . DB_PREFIX . "product_attribute WHERE product_id = " . (int)$product_id);
 		foreach ($query->rows as $row) {
 			if (!isset($aAttributesOld[$row['attribute_id']])) {
 				$aAttributesOld[$row['attribute_id']] = array();
 			}
 			$aAttributesOld[$row['attribute_id']][$row['language_id']] = $row['text'];
 		}
 		
		// Добавим или обновим
		foreach ($aAttributes as $aAttribute) {
			if (!isset($aAttributesOld[$aAttribute['attribute_id']])) {
				foreach ($this->LANG as $lang_id) {
					$this->query("INSERT INTO " . DB_PREFIX . "product_attribute SET 
						product_id = " . (int)$product_id . ",
						attribute_id = " . (int)$aAttribute['attribute_id'] . ",
						language_id = " . (int)$lang_id . ",
						text = '" . $this->db->escape($aAttribute['value']['name'][$lang_id]) . "'"
					);
				}
			} else {
				foreach ($this->LANG as $lang_id) {
					if ($aAttributesOld[$aAttribute['attribute_id']][$lang_id] == $aAttribute['value']['name'][$lang_id]) {
						continue;
					}
					$this->query("UPDATE " . DB_PREFIX . "product_attribute SET
						text = '" . $this->db->escape($aAttribute['value']['name'][$lang_id]) . "'
						WHERE 
						product_id = " . (int)$product_id . " AND
						attribute_id = " . (int)$aAttribute['attribute_id'] . " AND
						language_id = " . (int)$lang_id
					);
				}
			}
		}
		
		// Удалим неиспользуемы
		foreach ($aAttributesOld as $attribute_id => $aAttribute) {
			if (!isset($aAttributes[$attribute_id])) {
				$this->query("DELETE FROM " . DB_PREFIX . "product_attribute WHERE 
					product_id = " . (int)$product_id . " AND
					attribute_id = " . (int)$aAttribute['attribute_id']
				);
				continue;
			}
		}

    } // setProductAttribute()


    /**
     * Поиск производителя в базе по наименованию на основном языке
     * С поддержкой нескольких языков
     */
    private function searchManufacturer($manufacturer_name) {
		$data = array();
		
    	if (isset($this->TAB_FIELDS['manufacturer_description'])) {
    		// С поддержкой мультиязычности
			$query = $this->query("SELECT manufacturer_id FROM " . DB_PREFIX . "manufacturer_description
				WHERE name = '" . $this->db->escape($manufacturer_name[$this->LANG_ID]) . "' 
				AND language_id = " . (int)$this->LANG_ID
			);

			if ($query->num_rows) {
				$data['manufacturer_id'] = $query->row['manufacturer_id'];
				$data['guid'] = "";
				$data['name'] = array();
			}

			// Получим наименования на всех языках системы
			$query = $this->query("SELECT name FROM " . DB_PREFIX . "manufacturer_description
				WHERE manufacturer_id = " . (int)$data['manufacturer_id']
			);

			foreach ($query->rows as $row) {
				$data['name'][$row['language_id']] = $row['name'];
			}
			
    	} else {
			$query = $this->query("SELECT manufacturer_id FROM " . DB_PREFIX . "manufacturer
				WHERE name = '" . $this->db->escape($manufacturer_name[$this->LANG_ID]) . "'"
			);

			if ($query->num_rows) {
				$data['manufacturer_id'] = $query->row['manufacturer_id'];
				$data['guid'] = "";
				$data['name'] = array();

				foreach ($this->LANG as $lang_id) {
					$data['name'][$lang_id] = $manufacturer_name[$this->LANG_ID];
				}
			}
    	}
//		if ($data) {
//			$this->log("searchManufacturer():", 2);
//			$this->log($data, 2);
//		}
    	return $data;

   	} // searchManufacturer()


    /**
     * Функция определяет нужно добавлять или обновлять производителя
     * С поддержкой нескольких языков
     */
    private function setManufacturer($manufacturer) {

		$this->log("Производитель: " . $manufacturer['name'][$this->LANG_ID]);
        // Массив с данными для функции addManufacturer()
        // Первоначальное заполнения для языка по-умолчанию
        $data = array(
            'manufacturer_id' 	=> 0,
            'property_value_id'	=> $manufacturer['property_value_id'],
            'guid' 				=> $manufacturer['guid'],
            'sort_order' 		=> 0,
            'ml_support' 		=> isset($this->TAB_FIELDS['manufacturer_description']) ? 1 : 0,
            'name'				=> $manufacturer['name']
		);

        // Если свойство не общее, а у товарной категории, то Ид у одного и того же производителя будет разным.
        // В таких случаях после поиска по Ид будет произведен поиск и по наименованию

		$manufacturer_info = $this->searchManufacturer($data['name']);

		if ($manufacturer_info) {
			$data['manufacturer_id'] = $manufacturer_info['manufacturer_id'];
		}

        // Проверим надо ли обновлять наименования на разных языках
        if ($data['manufacturer_id']) {

			// с поддержкой мультиязычности
			if ($data['ml_support']) {

				foreach ($data['name'] as $lang_id => $name) {

					if ($name != $manufacturer_info['name'][$lang_id]) {
						$this->query("UPDATE " . DB_PREFIX . "manufacturer_description 
							SET name = '" . $this->db->escape($name) . "'
							WHERE manufacturer_id = " . (int)$data['manufacturer_id'] . " 
							AND language_id = " . (int)$lang_id
						);
					}
				}

			} else {

				// Для одного языка
				if ($data['name'][$this->LANG_ID] != $manufacturer_info['name'][$this->LANG_ID]) {
					$this->query("UPDATE " . DB_PREFIX . "manufacturer 
						SET name = '" . $this->db->escape($data['name'][$this->LANG_ID]) . "'
						WHERE manufacturer_id = " . (int)$data['manufacturer_id']
					);
				}
			}

		} else {

			// Для одного языка
			$this->query("INSERT INTO " . DB_PREFIX . "manufacturer SET name = '" . $this->db->escape($data['name'][$this->LANG_ID]) . "'");

			$data['manufacturer_id'] = $this->db->getLastId(); 

			// С поддержкой мультиязычности
			if ($data['ml_support']) {

				foreach ($data['name'] as $lang_id => $name) {
					$this->query("INSERT INTO " . DB_PREFIX . "manufacturer_description 
						SET name = '" . $this->db->escape($data['name']) . "', 
						language_id = " . (int)$lang_id . ", 
						manufacturer_id = " . (int)$data['manufacturer_id']
					);
					$data['manufacturer_id'] = (int)$this->db->getLastId(); 
				}
			}
			
			// Добавим в магазин основной
			$this->query("INSERT INTO " . DB_PREFIX . "manufacturer_to_store 
				SET manufacturer_id = " . (int)$data['manufacturer_id'] . ", 
				store_id = " . (int)$this->STORE_ID
			);

		}

        if ($data['manufacturer_id']) {
 
        	// Если производителей будет очень много, чтобы не грузить базу запросами SELECT 
			// можно считать всех производителей в массив и там производить поиск

			// Проверим связи
        	$query = $this->query("SELECT * FROM 1c_manufacturer WHERE property_value_id = " . (int)$data['property_value_id']);

        	if (!$query->num_rows) {
        		$this->query("INSERT INTO 1c_manufacturer SET 
					manufacturer_id = " . (int)$data['manufacturer_id'] . ", 
					property_value_id = " . (int)$data['property_value_id'] . ", 
					guid = '" . $this->db->escape($data['guid']) . "'"
				);
        	}
        }
		
		//$this->log("setManufacturer():", 2);
		//$this->log($data, 2);
        return $data['manufacturer_id'];

    } // setManufacturer()


    /**
     * Удаляет загруженные картинки
     * Сканирует все файлы в папке import_files и ищет где они указаны в товаре, иначе удаляет файл
     * Вызывается из контроллера, manualCleaningImportImages()
     */
    public function cleanImportImages($folder) {

		$result = array('error' => "", 'num' => 0);
        if (!file_exists(DIR_IMAGE . $folder)) {
            return "Папка не существует: /image/" . $folder;
        }

        $dir = dir(DIR_IMAGE . $folder);
        while ($file = $dir->read()) {

            if ($file == '.' || $file == '..') continue;

            $path = $folder . $file;

            if (file_exists(DIR_IMAGE . $path)) {

                if (is_file(DIR_IMAGE . $path)) {

                    // это файл, проверим его причастность к товару
                    $query = $this->query(
						"SELECT product_id,image FROM " . DB_PREFIX . "product 
						WHERE image LIKE '" . $path . "'"
					);

                    if ($query->num_rows) {
                        $this->log("файл: '" . $path . "' принадлежит товару: " . $query->row['product_id'], "cleanImportImages");
                        continue;

                    } else {
                        $this->log("Не найден в базе, нужно удалить файл: " . $path, "cleanImportImages");
                        $success = @unlink(DIR_IMAGE . $path);

                        if ($success) {
                            $result['num']++;
  
                        } else {
                            $this->log("[!] Ошибка удаления файла: " . $path, "cleanImportImages:ERROR");
                            $result['error'] = "Ошибка удаления файла: " . $path;
                            return $result;
                        }
                    }

                } elseif (is_dir(DIR_IMAGE . $path)) {

                    $result_ = $this->cleanOldImages($path . '/');

                    // Обработка результатов
                    $result['num'] += $result_['num'];
                    if ($result_['error']) {
                        $result['error'] = $result_['error'];
                        return $result;
                    }

                    // Попытка удалить папку, если она не пустая, то произойдет удаление
                    $success = @rmdir(DIR_IMAGE . $path);
                    if ($success) {
						$this->log("Удалена пустая папка: " . $path, "cleanImportImages=path");
                    }
                    continue;
                }
            }
        }
        return $result;

    } // cleanImportImages()


    /**
     * Возвращает преобразованный числовой id из Код товара торговой системы
     */
    private function parseCode($code) {
        $out = "";
        // Пока руки не дошли до преобразования, надо откидывать префикс, а после лидирующие нули
        $length = mb_strlen($code);
        $begin = -1;
        for ($i = 0; $i <= $length; $i++) {
            $char = mb_substr($code, $i, 1);
            // ищем первую цифру не ноль
            if ($begin == -1 && is_numeric($char) && $char != '0') {
                $begin = $i;
                $out = $char;
            } else {
                // начало уже определено, читаем все цифры до конца
                if (is_numeric($char)) {
                    $out .= $char;
                }
            }
        }
        return (int)$out;

    } // parseCode()


    /**
     * Парсит категории в товаре, в товаре может быть несколько категорий
     * Возвращает id категорий по GUID
     */
    private function parseProductCategories($categories) {

		$result = array();
		$skip_error = 1;

		foreach ($categories->Ид as $category_guid) {
            $data = array(
				'guid'			=> trim((string)$category_guid),
	            'category_id'	=> 0
			);

            $this->log("Категория товара Ид: " . $data['guid'], "parseProductCategories");

            if (isset($this->CATEGORY[$data['guid']])) {
                $category_id = $this->CATEGORY[$data['guid']];
                if (!$category_id && !$skip_error) {
	                $this->session->data['error'] = "M060";
					$this->log($this->CATEGORY[$data['guid']], "Найдена категория по Ид с нулевым category_id!");
					return $result;
                }
                $data['category_id'] = $category_id;
            } else {
            	if (!$skip_error) {
	                $this->session->data['error'] = "M061";
	                $this->log("При разборе товара не удалось найти категорию по Ид: " . $data['guid'], "parseProductCategories=data[guid]");
            	} else {
            		$this->log("У товара не найдена категория по Ид: " . $data['guid'], "parseProductCategories=data[guid]");
            	}
            }
            $result[$data['guid']] = $data['category_id'];
        }

        return $result;

    } // parseProductCategories()


    /**
     * Поиск производителя
     * Multilanguage
     */
    private function getManufacturer($data) {

		$result = array();

        $query_str = "SELECT * FROM 1c_manufacturer";

		if (isset($data['property_value_id'])) {
			$query_str .= " WHERE property_value_id = " . (int)$data['property_value_id'];

		} elseif (isset($data['guid'])) {
			$query_str .= " WHERE guid = '" . $data['guid'] . "'";

		} elseif (isset($data['name'])) {

        	if (isset($this->TAB_FIELDS['manufacturer_description'])) {

        		$query_str .= " m1c LEFT JOIN " . DB_PREFIX . "manufacturer_description md
					ON (m1c.manufacturer_id = md.manufacturer_id) 
					LEFT JOIN " . DB_PREFIX . "manufacturer_to_store m2s
					ON (m1c.manufacturer_id = m2s.manufacturer_id)
					WHERE md.name = '" . $this->db->escape($data['name'][$this->LANG_ID]) . "' 
					AND md.language_id = " . (int)$this->LANG_ID;

        	} else {
        		$query_str = "m1c LEFT JOIN " . DB_PREFIX . "manufacturer m
				ON (m1c.manufacturer_id = m.manufacturer_id) 
				WHERE m.name = '" . $this->db->escape($data['name'][$this->LANG_ID]) . "'";
        	}
        }

        $query = $this->query($query_str);

        if (!$query->num_rows) {
        	return false;
       	}

        if ($query->num_rows > 1) {
			$this->session->data['error'] = "M070";
			$this->log("По Ид '" . $guid . "' найдено более одного производителя");
			$this->log($query, "getManufacturer=query");
			$this->log($data, "getManufacturer=data");
			return false;
        }

        $result['manufacturer_id'] 		= $query->row['manufacturer_id'];
        $result['guid'] 				= $query->row['guid'];
        $result['property_value_id'] 	= $query->row['property_value_id'];
		$result['name']					= array();
        
        // Получим наименования на всех языках
        if (isset($this->TAB_FIELDS['manufacturer_description'])) {
			$query = $this->query("SELECT name,language_id FROM " . DB_PREFIX . "manufacturer_description md
				LEFT JOIN " . DB_PREFIX . "manufacturer_to_store m2s
				ON (md.manufacturer_id = m2s.manufacturer_id)
				WHERE md.manufacturer_id = " . (int)$result['manufacturer_id'] . " 
				AND m2s.store_id = " . (int)$this->STORE_ID
			);

			foreach ($query->rows as $row) {
				$result['name'][$row['language_id']] = $row['name'];
			}

		} else {

			$query = $this->query("SELECT name FROM " . DB_PREFIX . "manufacturer WHERE manufacturer_id = " . (int)$result['manufacturer_id']);

			$name = $query->row['name'];
			foreach ($this->LANG as $lang_id) {
				$result['name'][$lang_id] = $name;
			}
		}

        return $result;

    } // getManufacturer()


    /**
     * <НЕ ИСПОЛЬЗУЕТСЯ, ДОРАБОТАТЬ>
     * Читает всех производителей из базы в массив
     * Multilanguage
     */
    private function getManufacturers() {

        $data = array();
        $description = array();

        if (isset($this->TAB_FIELDS['manufacturer_description'])) {

            foreach ($this->LANG as $lang_id) {

                $description[$lang_id] = array();

                // Запрос для каждого языка
                $query = $this->query(
					"SELECT m.manufacturer_id, md.name FROM " . DB_PREFIX . "manufacturer m
					LEFT JOIN " . DB_PREFIX . "manufacturer_description md
					ON (m.manufacturer_id = md.manufacturer_id)
					LEFT JOIN " . DB_PREFIX . "manufacturer_to_store m2s
					ON (m.manufacturer_id = m2s.manufacturer_id)
					WHERE m2s.store_id = " . (int)$this->STORE_ID . " 
					AND md.language_id = " . (int)$lang_id
				);

                foreach ($query->rows as $row) {
                    $description[$lang_id][$row['manufacturer_id']] = $row['name'];
                } // foreach

            } // foreach

        } else {

            $description[$this->LANG_ID] = array();

            $query = $this->query(
				"SELECT m.manufacturer_id, m.name FROM " . DB_PREFIX . "manufacturer m
				LEFT JOIN " . DB_PREFIX . "manufacturer_to_store m2s
				ON (m.manufacturer_id = m2s.manufacturer_id)
				WHERE m2s.store_id = " . (int)$this->STORE_ID
			);

            foreach ($query->rows as $row) {
                $description[$this->LANG_ID][$row['manufacturer_id']] = $row['name'];
            }
        }

        $links = array();
        $query = $this->query("SELECT * FROM 1c_manufacturer");

        foreach ($query->rows as $row) {
            $links[$row['guid']] = $row['manufacturer_id'];
        }

        $data['description'] = $description;
        $data['links'] = $links;

        $this->log("Количество производителей в базе: " . count($links));
        $this->log($data, "getManufacturers=data");

        return $data;

    } // getManufacturers()


    /**
     * Читает из XML данные о налогах
     */
    private function parseProductTaxes($xml_product) {

        $tax_class_id = 0;
        foreach ($xml_product->СтавкаНалога as $product_tax) {

            $name = trim((string )$product_tax->Наименование);
            $new_rate = 0;

            if ($product_tax->Ставка) {
                $new_rate = (float)$product_tax->Ставка;
                $sql_where = " AND rate.rate = " . $new_rate;
                $name = $name . " " . $new_rate . "%";
            }

            if ($new_rate == 0) {
                // значит налог не используем
                continue;
            }

            // Найдем налог по наименованию в базе
            $query = $this->query("SELECT rate.rate, rate.tax_rate_id, rule.tax_class_id 
				FROM " . DB_PREFIX . "tax_rate rate 
				LEFT JOIN " . DB_PREFIX . "tax_rule rule 
				ON (rate.tax_rate_id = rule.tax_rate_id) 
				WHERE rate.name = '" . $this->db->escape($name) . "'" . $sql_where
			);
            $this->log($query, "parseProductTaxes=query");

            if ($query->num_rows) {
                $tax_class_id = $query->row['tax_class_id'];
                $rate = $query->row['rate'];

            } else {
                $this->session->data['error'] = 'M080';
                return 0;
            }

            if (!$rate) {
				$this->session->data['error'] = 'M081';
				return 0;
            }

            if (!$tax_class_id) {
				$this->session->data['error'] = 'M082';
				return 0;
            }

            $this->log("Налог найден: '" . $name . "', ставка: '" . $rate . "'", "parseProductTaxes");
        }

        return $tax_class_id;

    } // parseProductTaxes()


    /**
     */
    private function parseProductRules($xml, &$data) {

        $rules = $this->config->get('exchange1c_product_rules_pre_parse');
        if (!$rules)
            return;

        $this->log($rules, "parseProductRules");
        $rules = explode("\r\n", $rules);
        $num = 0;

        foreach ($rules as $rule_str) {

            $rule_str = trim($rule_str);

            if (empty($rule_str))
                continue;
 
            $num++;
            $rule_data = explode('#', $rule_str);
            if (count($rule_data) != 3) {
                $this->log("Неверный формат правил в строке " . $num . " правило '" . $rule_str . "'");
                continue;
            }
            $result = '';
            $this->log($rule_data, "parseProductRules=rule_data");
            if (isset($rule_data[0])) {
                $tag = trim($rule_data[0]);
                if ($xml->$tag) {
                    $result = trim((string )$xml->$tag);
                    $this->log($result, "parseProductRules=result");
                };
            }
            $script = trim($rule_data[1]);
            if (!empty($script) && $result) {

                ob_start();
                $return = eval("\$result= $script;");
                if ($return === false && ($error = error_get_last())) {
                    $this->log($error, 2);
                }
                $result = ob_get_contents();
                ob_end_clean();
                $this->log($result, "parseProductRules=result");
            }
            if (isset($rule_data[2])) {
                $field = trim($rule_data[2]);
                $data[$field] = $result;
                $this->log($data[$field], 2);
            }
        }

    } // parseProductRules()


    /**
     * Читает реквизиты товара из XML в массив данных
     */
    private function parseProductRequisite($xml) {
        $data = array();
        $count = 0;

        foreach ($xml->ЗначениеРеквизита as $requisite) {
            $this->log($requisite, "parseProductRequisite=requisite");

            $name = htmlspecialchars(trim((string)$requisite->Наименование));
            $value = htmlspecialchars(trim((string)$requisite->Значение));

            switch ($name) {
                case 'Полное наименование':
                    if ($requisite->Значение)
                        $data['full_name'] = $this->getLanguageName($requisite->Значение);
                    break;
                case 'Вес':
                    $data['weight'] = $this->formatFloat($value);
                    break;
                case 'Длина':
                    $data['length'] = $this->formatFloat($value);
                    break;
                case 'Высота':
                    $data['height'] = $this->formatFloat($value);
                    break;
                case 'Ширина':
                    $data['width'] = $this->formatFloat($value);
                    break;
                case 'Модель':
                    $data['model'] = $value;
                    break;
                case 'ЭтоНовинка':
					$data['date_available'] =  date('Y-m-d', strtotime('now'));
                    $data['date_added'] =  $data['date_available'];
                    //break;
                case 'Ячейка':
                    $data['location'] = $value;
                    break;
                case 'ТипНоменклатуры':
                    if ($value == "Запас" || $value == "Товар")
                        $data['subtract'] = 1;
                    else
                        $data['subtract'] = 0;
                    break;
                case 'ОписаниеВФорматеHTML':
                    $data['description'] = $this->getLanguageName($requisite->Значение, true);
                    $data['description_html'] = 1;
                    break;
                case 'Производитель':
                    // Устанавливаем производителя из свойства только если он не был еще загружен в секции Товар
                    $manufacturer = $this->getLanguageName($requisite->Значение);
                    $manufacturer_info = $this->getManufacturer(array('name'=>$manufacturer[$this->LANG_ID]));
                    if ($manufacturer_info)
                    {
	                    $data['manufacturer_id'] = $manufacturer_info['manufacturer_id'];
                    }
                    break;
                case 'Код':
                    $data['code_1c'] = $this->parseCode($value);
                    break;
                case 'ISBN':
                    $data['isbn'] = $value;
                    break;

            } // switch

			$count++;
        } // foreach()

        $this->log($count, "Количество прочитано реквизитов");
		$this->log($data, "parseProductRequisite=data");

        return $data;

    } // parseProductRequisite()


    /**
     * Загрузка каталога
     */
    private function parseProduct($xml) {

		$aGuid = explode("#", (string)$xml->Ид);

		$aData = array(
			'guid'			=> $aGuid[0],
			'product_id'	=> 0,
			'version'		=> "",
			'action'		=> 0, // 1-new, 2-update
			'sku'			=> $xml->Артикул ? htmlspecialchars(trim((string )$xml->Артикул)) : "",
			'delete_mark'	=> (string)$xml->ПометкаУдаления == 'true' ? 1 : 0,
			'ean'			=> $xml->Штрихкод ? trim((string )$xml->Штрихкод) : "",
			'status'		=> 1
		);
		$aImages			= array();
		$aFeature 			= array();
		$aDescriptions 		= array(
			'name'				=> array(),
			'description'		=> array()
		);
		$aAttributes 		= array();
		$aProperties		= array();
        $aRequisites 		= array();
		$aCategories		= array();
		$aPCategories		= array();
		$aManufacturer		= array();
		$aReview			= array();

        if (isset($aGuid[1])) {
			$aFeature['guid'] =  $aGuid[1];
			$aFeature['xml_data'] = $xml->ХарактеристикиТовара;
        }

		// МОДЕЛЬ (Обязательное для заполнение поле)
        if ($xml->Модель)
            $aData['model'] = htmlspecialchars(trim((string)$xml->Модель));
        elseif ($xml->Код)
            $aData['model'] = htmlspecialchars(trim((string)$xml->Код));
        else
            $aData['model'] = $aData['sku'];

        // Штрихкод
        if (!$aData['ean'])
            $aData['ean'] = $xml->ШтрихКод ? trim((string)$xml->ШтрихКод) : "";

        // Налоги
        if ($xml->СтавкиНалогов && $this->config->get('exchange1c_product_taxes_import')) {
            $aData['tax_class_id'] = $this->parseProductTaxes($xml->СтавкиНалогов);
            if ($this->session->data['error']) {
				$this->log($aData);
            	return false;
            }
        }

        // Наименование товара
        if ($xml->Наименование) {
            // Мультиязычность
            $aDescriptions['name'] = $this->getLanguageName($xml->Наименование);

        } else {
            $this->session->data['error'] = "M090";
            $this->log("Наименование товара отсутствует в файле! Это обязательный тег!");
			$this->log($aDescriptions, "parseProduct=aDescription");
			$this->log($aData, "parseProduct=aData");
            return false;
        }

        // Описание товара
        if ($xml->Описание && $this->config->get('exchange1c_product_description_import')) {
            $aDescriptions['description'] = $this->getLanguageName($xml->Описание);
            $aDescriptions['description'] = str_replace(
				array("\r\n",  "\r", "\n"),
				"<br />", 
				$aDescriptions['description']
			);
        }

        // Производитель / Изготовитель из специального тега
        $xmlManufacturer = "";
		if ($xml->Изготовитель)
        	$xmlManufacturer = $xml->Изготовитель;

        elseif ($xml->Производитель)
            $xmlManufacturer = $xml->Производитель;

		if ($xmlManufacturer) {
            $aManufacturer = $this->getLanguageName($xmlManufacturer);
            $aManufacturer_info = $this->getManufacturer($aManufacturer);

            if ($aManufacturer_info)
            	$aData['manufacturer_id'] = $aManufacturer_info['manufacturer_id'];
		}

        // Индексация, при наличии поля в таблице товара
        if (isset($this->TAB_FIELDS['product']['noindex']))
            $aData['noindex'] = $this->config->get('exchange1c_product_index');

        // Реквизиты
        if ($xml->ЗначениеРеквизита)
            $aRequisites = $this->parseProductRequisite($xml->ЗначениеРеквизита);
        elseif ($xml->ЗначенияРеквизитов)
            $aRequisites = $this->parseProductRequisite($xml->ЗначенияРеквизитов);
        $this->log($aRequisites, 2);

		// Полное наименование вставим в начало описания
		// Если кому так не нужно удалите эти строки
		if (isset($aDescriptions['full_name']) && $this->config->get('exchange1c_product_description_import')) {
			foreach ($this->LANG as $lang_id)
				$aDescriptions['full_name'][$lang_id] = $aDescriptions['full_name'][$lang_id] . "<br />" . $aDescriptions['description'][$lang_id];
		}

		// Описание товара
//		if (!$this->config->get('exchange1c_product_description_import') && isset($aDescriptions['description']))
//			unset($aDescriptions['description']);

        // Группы товаров
		if ($xml->Группы && $this->config->get('exchange1c_product_category_import')) {
            $aCategories = $this->parseProductCategories($xml->Группы);
            if ($this->session->data['error']) {
				$this->log($aCategories, "parseProduct=aCategories");
				$this->log($aData, "parseProduct=aData");
	            return false;
            }
        }
        // Присутствие товара в родительских категориях (создает нагрузку на opencart)
        if ($this->config->get('exchange1c_fill_parent_cats') == 1) {
            $aCategories = $this->fillParentsCategories($aCategories);
        }

        // Товарные категории
        if ($xml->Категория) {
			$aPCategories = $this->getPCategory((string)$xml->Категория);
        }

        // Атрибуты
        if ($xml->ЗначенияСвойств && $this->config->get('exchange1c_attribute_import')) {
            $aProperties = $this->parseProductProperty($xml->ЗначенияСвойств);
            if ($this->session->data['error']) {
				$this->log($aProperties, "parseProduct=aProperties");
				$this->log($aData, "parseProduct=aData");
	            return false;
            }

			if ($aProperties['data']) {
	            $aData = array_merge($aData, $aProperties['data']);
            }
			if ($aProperties['attributes']) {
	            $aAttributes = $aProperties['attributes'];
            }
        }

        // Картинки
		if ($this->config->get('exchange1c_product_images_import')) {
			if ($xml->Картинка) {
	            $aImages = $this->parseImages($xml->Картинка);
	        }
	
	        // CML 2.04
	        if ($xml->ОсновнаяКартинка) {
	            $aImages = $this->parseImages($xml->ОсновнаяКартинка);
	
	            // дополнительные, когда элементы в файле называются <Картинка1>, <Картинка2>...
	            $cnt = 1;
	            $var = 'Картинка' . $cnt;
	
	            while (!empty($xml->$var)) {
	                $aImages[] = $this->parseImages($xml->$var);
	                $cnt++;
	                $var = 'Картинка' . $cnt;
	            }
	        }

			$aData['image'] = 'no_image.png';

			if ($aImages) {
				$aData['image'] = array_shift($aImages);
			}
	
		}


        // Отзывы парсятся с Яндекса в 1С, а затем на сайт
        // Доработка от SunLit (Skype: strong_forever2000)
        if ($xml->ЗначенияОтзывов) {
            $aReview = $this->importProductReview($aData, $xml->ЗначенияОтзывов);
        }

        // Обязательные поля, можно использовать обработчик
        //META_FIELDS
        if (empty($aDescriptions['meta_title'])) {
            $aDescriptions['meta_title'] = $aDescriptions['name'];
        }
        
		if ($this->config->get('exchange1c_module_status') == "debug") {
			$this->log($aData, "parseProduct=aData");
			return true;
		}

		// Проверим существование товара по Ид (guid)
		$aData['product_id'] = $this->getProductIdByGuid($aData['guid']);

		// Если товар ранее не загружался, пробуем найти по указанным полям в настройках
		if (!$aData['product_id']) {
			// guid, sku, model, name, ean, code
			$aSearchData = array(
				'sku'	=> $aData['sku'],
				'model'	=> $aData['model'],
				'name'	=> $aDescriptions['name'][$this->LANG_ID],
				'code'	=> ""
			);
	
			$aData['product_id'] = $this->syncProduct($aSearchData);
		}

		// Создаем товар
        if (!$aData['product_id']) {
       		$aData['product_id'] = $this->addProduct($aData, $aDescriptions);
            if ($this->session->data['error']) {
            	$this->log($aData, "parseProduct=aData");
            	$this->log($aDescriptions, "parseProduct=adescription");
				return false;
            }

		} else {
			$this->updateProduct($aData, $aDescriptions);
			if ($this->session->data['error']) {
				$this->log($this->session, 2);
            	$this->log($aData, "parseProduct=aData");
            	$this->log($aDescriptions, "parseProduct=aDescription");
				return false;
			}
		}
		
		// Проверим не сопоставлен ли товар еще какому-нибудь Ид
		if ($this->config->get('exchange1c_check_product_double_link')) {
			$query = $this->query("SELECT product_id FROM 1c_product WHERE guid = '" . $aData['guid'] . "'");
	    	
	    	if ($query->num_rows > 1) {
	    		$this->session->data['error'] = "M091";
	    		$this->log("Найдено более одного товара по Ид = " . $aData['guid']);
	    		$this->log($query);
	    		return array();
	    	}
		}

		if (!$aData['product_id']) {
			$this->log("Товар не был создан");
			return false;
		}

		if ($aCategories) {
			$this->setProductCategories($aData['product_id'], $aCategories);
		}

		if ($aImages) {
			$this->setProductImages($aData['product_id'], $aImages);
		}

		if ($aAttributes) {
			$this->setProductAttribute($aData['product_id'], $aAttributes);
		}

		if ($aReview) {
			$this->setProductReview($aData['product_id'], $aReview);
		}

		$this->log(" - Товар " . $aDescriptions['name'][$this->LANG_ID]);
        return true;

    } // parseProduct()


    /**
     * Загрузка документов
     */
    private function parseProducts($xml) {
 
		$only_change = $xml['СодержитТолькоИзменения'] == 'true' ? 1 : 0;
		$xml_count = count($xml->Товары->Товар);

        if (!$xml->Товары) {
            return 0;
        }

        if (empty($this->CATEGORY)) {
            $this->CATEGORY = $this->getCategories();
        }

        if (empty($this->PCATEGORY)) {
            $this->PCATEGORY = $this->getPCategories();
        }

		if (empty($this->PROPERTY)) {
			$this->PROPERTY = $this->getProperties();
		}

        foreach ($xml->Товары->Товар as $num => $xml_product) {
            $this->parseProduct($xml_product);
            if ($this->session->data['error']) {
	            return 0;
    		}

        } // foreach

        // Очистим кэш товаров
        $this->cache->delete('product');

        return true;

    } // parseProducts()


    /**
     * Установка значений в настройку модуля
     */
    public function setConfig($key, $value, $serialized = 0, $code = 'exchange1c', $clean = false) {

        if ($clean) {
            $this->query("DELETE FROM " . DB_PREFIX . "setting WHERE code = '" . $code . "'");
        }

        $query = $this->query("SELECT * FROM " . DB_PREFIX . "setting 
			WHERE code = '" . $code . "' AND `key` = '" . $key . "'"
		);

        if ($serialized) {
        	$value = json_encode($value);
        }
		
		if ($query->num_rows) {

            if ($query->row['value'] != $value) {
                $this->query("UPDATE " . DB_PREFIX . "setting 
					SET `value` = '" . $this->db->escape($value) . "' 
					WHERE setting_id = " . (int)$query->row['setting_id']
				);
            }

        } else {
            $this->query("INSERT INTO " . DB_PREFIX . "setting SET
				`store_id` = " . (int)$this->STORE_ID . ", 
				`code` = '" . $code . "', 
				`key` = '" . $key . "', 
				`value` = '" . $this->db->escape($value) . "', 
				serialized = " . (int)$serialized
			);
        }

    } // setConfig()


    /**
     * Получает список групп покупателей
     */
    private function getCustomerGroups() {

        $query = $this->query("SELECT customer_group_id FROM " . DB_PREFIX . "customer_group ORDER BY sort_order");
        $data = array();

        foreach ($query->rows as $row) {
            $data[] = $row['customer_group_id'];
        }
        return $data;

    } // getCustomerGroups()


    /**
     * Загружает склады из классификатора
     */
    private function parseStorage($xml) {

        $storage_data = array();
        
        // Прочитаем все склады из базы в массив
        $storage_info = array();
        $query = $this->query("SELECT * FROM 1c_storage WHERE store_id = " . (int)$this->STORE_ID);

        foreach ($query->rows as $row) {
        	$storage_info[$row['guid']] = array(
        		'name'			=> $row['name'],
        		'storage_id'	=> $row['storage_id']
			);
        }

        foreach ($xml->Склад as $storage) {

            $guid = trim((string)$storage->Ид);
            $name = htmlspecialchars(trim((string)$storage->Наименование));

            $storage_id = 0;
			if (!isset($storage_info[$guid])) {
            	$this->query("INSERT INTO 1c_storage SET 
					guid = '" . $this->db->escape($guid) . "',
					name = '" . $this->db->escape($name) . "'"
				);
				$storage_id = $this->db->getlastId();

            } else {
            	if ($storage_info[$guid]['name'] != $name) {
	            	$this->query("UPDATE 1c_storage SET 
						name = '" . $this->db->escape($name) . "'
						WHERE guid = '" . $this->db->escape($guid) . "'"
					);
            	}
            	$storage_id = $storage_info[$guid]['storage_id'];
            }
            $storage_data[$guid] = $storage_id;
        }

        return $storage_data;

    } // parseStorage()


    /**
     * ====================================== ХАРАКТЕРИСТИКИ ======================================
     */

    /**
     * Читает описание опции на всех языак системы
     */
    private function getProductOptionValues($product_option_id) {

         $values = array();

        $query = $this->query("SELECT * FROM " . DB_PREFIX . "product_option_value pov
			LEFT JOIN " . DB_PREFIX . "option_value_description ovd
			ON (pov.option_value_id = ovd.option_value_id)
			WHERE pov.product_option_id = " . (int)$product_option_id
		);

        if ($query->num_rows) {

            foreach ($query->rows as $row) {

                if (!isset($values[$row['product_option_value_id']])) {

                    $this->log("  Значение: " . $row['name'], "getProductOptionValues=row[name]");

                    $values[$row['product_option_value_id']] = array(
                        'product_option_value_id' 	=> $row['product_option_value_id'],
                        'option_value_id' 			=> $row['option_value_id'],
                        'quantity'					=> $row['quantity'],
                        'subtract' 					=> $row['subtract'],
                        'price' 					=> $row['price'],
                        'price_prefix' 				=> $row['price_prefix'],
                        'points' 					=> $row['points'],
                        'points_prefix' 			=> $row['points_prefix'],
                        'weight' 					=> $row['weight'],
                        'weight_prefix' 			=> $row['weight_prefix'],
                        'status' 					=> $row['status'],
                        'name_ml' 					=> array($row['language_id'] => $row['name'])
					);

                } else {
                    $values[$row['product_option_value_id']]['name_ml'][$row['language_id']] = $row['name'];
                }
            }
        }

        $this->log($values, "getProductOptionValues=values");
        return $values;

    } // getProductOptionValues()


    /**
     * Читает все существующие опции товара из базы
     */
    private function getProductOptions($product_id) {

        $options = array();

        $query = $this->query("SELECT * FROM " . DB_PREFIX . "product_option po
			LEFT JOIN  " . DB_PREFIX . "option_description od
			ON (po.option_id = od.option_id)
			LEFT JOIN  " . DB_PREFIX . "option o
			ON (po.option_id = o.option_id)
			WHERE po.product_id = " . (int)$product_id
		);

        if ($query->num_rows) {
 
            foreach ($query->rows as $row) {
 
                if (!isset($options[$row['product_option_id']])) {

                    $options[$row['product_option_id']] = array(
                        'option_id' 	=> $row['option_id'],
                        'type' 			=> $row['type'],
                        'sort_order' 	=> $row['sort_order'],
                        'required' 		=> $row['required'],
                        'name_ml' 		=> array($row['language_id'] => $row['name']));

                } else {
                    $options[$row['product_option_id']]['name_ml'][$row['language_id']] = $row['name'];
                }
            }
        }

        foreach ($options as &$product_option) {
             $product_option['values'] = $this->getProductOptionValues($product_option['product_option_id']);
        }

        $this->log($options, "getProductOptions=options");
        return $options;

    } // getProductOptions()


    /**
     * Получает product_id по Ид
     */
    private function getProductIdByGuid($guid) {
    	
    	if (!$guid) {
    		$this->session->data['error'] = "M110";
    		$this->log("Пустые данные переданные в функцию getProductIdByGuid()", "getProductIdByGuid");
    		return 0;
    	}
    	
		// Поиск по массиву
		if (isset($this->PRODUCT[$guid])) {
			return $this->PRODUCT[$guid];
		}

    	// Поиск в базе
		$query = $this->query("SELECT product_id FROM 1c_product WHERE guid = '" . $this->db->escape($guid) . "'");

		if (!$query->num_rows) return 0;
		
		if ($query->num_rows > 1) {
    		$this->session->data['error'] = "M111";
    		$this->log("Несколько товаров соответствуют одному ИД", "getProductIdByGuid");
    		$this->log($query, "getProductIdByGuid=query");
    		return 0;
		}
		
		return $query->row['product_id'];
    	
   	} // getProductIdByGuid()


    /**
     * Получает наименование характеристики заключенной в скобках в конце наименования
     */
    private function getFeatureName($str) {

        $str = trim(str_replace(array("\r", "\n"), '', $str));
        $length = mb_strlen($str);
        $feature_name = "";

        $pos_name_start = 0;
        $pos_opt_end = 0;
        $pos_opt_start = $length;

		// Ищем опцию
		$level = 0;
		for ($i = $length; $i > 0; $i--) {
			$char = mb_substr($str, $i, 1);
			if ($char == ")") {
				$level++;
				if (!$pos_opt_end)
					$pos_opt_end = $i;
			}
			if ($char == "(") {
				$level--;
				if ($level == 0) {
					$pos_opt_start = $i + 1;
					$feature_name = mb_substr($str, $pos_opt_start, $pos_opt_end - $pos_opt_start);
					$pos_opt_start -= 2;
					break;
				}
			}
		} // for

        return $feature_name;

    } // getFeatureName()


    /**
     *  Обновляет предложение товара
     */
    private function updateOffer($offer, $offer_info) {

		if ($this->config->get('exchange1c_module_status') == "debug") {
			return 1;
		}

		$update_fields = array();

		// обновим то что изменилось
		if ($offer['name'] != $offer_info['name']) {
			$update_fields['name'] = "name = '" . $this->db->escape($offer['name']) . "'";
		}

		if ($offer['ean'] != $offer_info['ean']) {
			$update_fields['ean'] = "ean = '" . $offer['ean'] . "'";
		}

		if ($offer['version']) {
			if ($offer['version'] != $offer_info['version']) {
				$update_fields['version'] = "version = '" . $offer['version'] . "'";
			}
		}
				
		if ($update_fields) {
			$update_fields['date_modified'] = "date_modified = NOW()";
			$set_fields = implode(", ", $update_fields);

			$this->query("UPDATE 1c_offers SET " . $set_fields . " WHERE offer_id = " . $offer_info['offer_id']);
		}
		
		return $offer_info['offer_id'];

   	} // updateOffer()
    	


    /**
     * Добавляет предложение товара
     */
    private function addOffer($offer) {

		if ($this->config->get('exchange1c_module_status') == "debug") {
			return 1;
		}

		if (!$offer['product_id']) {
			$this->session->data['error'] = "M120";
			$this->log("Для добавления предложения нужно указать product_id", "addOffer");
			return 0;
		}

		// Новое предложение
		$this->query("INSERT INTO 1c_offers SET
			product_id = " . (int)$offer['product_id'] . ", 
			feature_id = " . (int)$offer['feature_id'] . ", 
			name = '" . $this->db->escape($offer['name']) . "',
			ean = '" . $this->db->escape($offer['ean']) . "',
			version = '" . $this->db->escape($offer['version']) . "',
			date_modified = NOW()"
		);
		
		return $this->db->getLastId();

   	} // addOffer()
    	

    /**
     * Обновляет характеристику предложения
     */
    private function updateFeature($offer, $feature_info) {

		if ($this->config->get('exchange1c_module_status') == "debug") {
			return 0;
		}

		$update_fields = array();
		
		// обновим то что изменилось
		if ($offer['feature_name'] != $feature_info['name']) {
			$update_fields['name'] = "name = '" . $this->db->escape($offer['feature_name']) . "'";
		}

		if ($offer['ean'] != $feature_info['ean']) {
			$update_fields['ean'] = "ean = '" . $offer['ean'] . "'";
		}

		if ($offer['version']) {
			if ($offer['version'] != $feature_info['version']) {
				$update_fields['version'] = "version = '" . $offer['version'] . "'";
			}
		}
				
		if ($update_fields) {
			$update_fields['date_modified'] = "date_modified = NOW()";
			$set_fields = implode(", ", $update_fields);

			$this->query("UPDATE 1c_features SET " . $set_fields . " WHERE feature_id = " . $feature_info['feature_id']);
		}
		
		return $feature_info['feature_id'];

   	} // updateFeature()
    	

    /**
     * Добавляет характеристику предложения
     */
    private function addFeature($offer) {

		if ($this->config->get('exchange1c_module_status') == "debug") {
			return 1;
		}

		if (!$offer['feature_name']) {
			$this->session->data['error'] = "M130";
			$this->log("Не указано наименование характеристики", "addFeature");
			return 0;
		}
		
		if (!$offer['product_id']) {
			$this->session->data['error'] = "M131";
			$this->log("Не указан product_id", "addFeature");
			return 0;
		}

		// Новое предложение
		$this->query(
			"INSERT INTO 1c_features SET
			guid = '" . $offer['feature_guid'] . "',
			name = '" . $offer['feature_name'] . "',
			ean = '" . $offer['ean'] . "',
			version = '" . $offer['version'] . "',
			date_modified = NOW()"
		);
		
		return $this->db->getLastId();

   	} // addFeature()
    	

    /**
     * Получает предложение из базы
     */
    private function getOffer($offer) {
    	
		if ($this->config->get('exchange1c_module_status') == "debug") {
			return array();
		}

		$where = "";
		if (empty($offer['product_id'])) {
    		$this->session->data['error'] = "M140";
    		$this->log("Пустое значение product_id", "getOffer");
    		$this->log($offer, "getOffer=offer");
    		return array();
    	}

   		if ($offer['offer_id']) {
   			$where = "offer_id = '" . $offer['offer_id'] . "'";
   		} else {
   			$where = "product_id = '" . $offer['product_id'] . "' AND feature_id = '" . $offer['feature_id'] . "'";
   		}

		$query = $this->query("SELECT * FROM 1c_offers WHERE " . $where);

    	if (!$query->num_rows) {
    		return array();
    	}
    	
    	if ($query->num_rows > 1) {
    		$this->session->data['error'] = "M141";
    		$this->log("Найдено более одного предложения по параметрам условию запроса: " . $where, "getOffer=where");
    		$this->log($query, "getOffer=query");
    		return array();
   		}
   		
   		$data = $query->row;
   		
   		$this->log($data, "getOffer=data");
		return $data;
    	
   	} // getOffer()


    /**
     */
    public function importDataCount() {

    	$aData = array(
    		'category' 	=> 0,
    		'pcategory'	=> 0,
    		'property'	=> 0,
    		'rests'		=> 0,
    		'product'	=> 0,
    		'features'	=> 0,
    		'offers'	=> 0,
    		'prices'	=> array(),
    		'orders'	=> 0
		);

    	$query = $this->query("SELECT count(*) as count FROM 1c_category");
    	if ($query->num_rows) {
    		$aData['category'] = $query->row['count'];
    	}

    	$query = $this->query("SELECT count(*) as count FROM 1c_pcategory");
    	if ($query->num_rows) {
    		$aData['pcategory'] = $query->row['count'];
    	}

    	$query = $this->query("SELECT count(*) as count FROM 1c_property");
    	if ($query->num_rows) {
    		$aData['property'] = $query->row['count'];
    	}

    	$query = $this->query("SELECT count(*) as count FROM 1c_rests");
    	if ($query->num_rows) {
    		$aData['rests'] = $query->row['count'];
    	}

    	$query = $this->query("SELECT count(*) as count FROM 1c_product");
    	if ($query->num_rows) {
    		$aData['product'] = $query->row['count'];
    	}

    	$query = $this->query("SELECT count(*) as count FROM 1c_features");
    	if ($query->num_rows) {
    		$aData['features'] = $query->row['count'];
    	}

    	$query = $this->query("SELECT count(*) as count FROM 1c_offers");
    	if ($query->num_rows) {
    		$aData['offers'] = $query->row['count'];
    	}

		$aPrices = array();
		$query = $this->query("SELECT count(*) as count,price_config_id FROM 1c_prices GROUP BY price_config_id");
		foreach ($query->rows as $row) {
			$aPrices[$row['price_config_id']] = $row['count'];
		}
		$aData['prices'] = $aPrices;

    	$aOrders = $this->setOrdersExport();
		$aData['orders'] = count($aOrders);
		return $aData;
    	
	} // importDataCount()


    /**
     */
    public function classifierCount() {
    	$aDtata = array(
    		'category' 	=> 0,
    		'pcategory'	=> 0,
    		'property'	=> 0
		);
    	$query = $this->query("SELECT count(*) as count FROM 1c_category");
    	if ($query->num_rows) {
    		$aDtata['category'] = $query->row['count'];
    	}
    	$query = $this->query("SELECT count(*) as count FROM 1c_pcategory");
    	if ($query->num_rows) {
    		$aDtata['pcategory'] = $query->row['count'];
    	}
    	$query = $this->query("SELECT count(*) as count FROM 1c_property");
    	if ($query->num_rows) {
    		$aDtata['property'] = $query->row['count'];
    	}
    	return $aDtata;
    	
	} // featuresCount()


    /**
     * Получает характеристику из базы
     */
    private function getFeature($offer) {
    	
    	if (empty($offer['feature_guid']) && empty($offer['feature_id'])) {
    		$this->session->data['error'] = "M150";
    		$this->log($offer, "Пустые feature_id либо feature_guid были переданы в функцию getFeature()");
    		return array();
    	}

    	if ($offer['feature_id']) {
    		$sql = "SELECT * FROM 1c_features WHERE feature_id = '" . $offer['feature_id'] . "'";
    		$search_field = "feature_id";
    	} else {
    		$sql = "SELECT * FROM 1c_features WHERE guid = '" . $offer['feature_guid'] . "'";
    		$search_field = "feature_guid";
    	}

		$query = $this->query($sql);
    	if (!$query->num_rows) {
    		return array();
    	}
    	
    	if ($query->num_rows > 1) {
    		$this->session->data['error'] = "M151";
    		$this->log("Найдено более одной характеристики по параметрам: " . $search_field . " = " . $offer[$search_field], "getFeature");
    		$this->log($query, "getFeature=query");
    		return array();
   		}
   		
   		$this->log($query->row, "getFeature=query->row");
   		return $query->row;
    	
   	} // getFeature()


    /**
     * Возвращает id валюты по коду
     */
    private function getCurrencyId($code = "") {

        $currency = $this->getCurrency($code);
        
		if ($currency) {
			return $currency['currency_id'];
		}

        return 0;

    } // getCurrencyId()


    /**
     * Получим валюту по коду из базы
     */
    private function getCurrency($code) {
    	
        if (!$code) {
            $code = $this->config->get('config_currency');
        }

		$currency = array();

        $query = $this->query("SELECT currency_id FROM " . DB_PREFIX . "currency WHERE code = '" . $this->db->escape($code) . "'");

        if ($query->num_rows) {
			$currency = $query->row;
        }

        if (!$currency) {
			// Попробуем поискать по символу справа
	        $query = $this->query("SELECT currency_id FROM " . DB_PREFIX . "currency WHERE symbol_right = '" . $this->db->escape($code) . "'");
	
	        if ($query->num_rows) {
				$currency = $query->row;
	        }
        }

        if (!$currency) {
	        
	        // Поищем валюту в таблице соответствий, если ее там нет, то добавим и попросим пользователя сопоставить
	        $currency_config = $this->config->get("exchange1c_currency");
	        if (!$currency_config) {
	        	$currency_config = array();
	        }
	        
   			$found = false;
        	foreach ($currency_config as $key => $currency) {
        		if ($currency['code'] == $code) {
        			$found = $key;
        			break;
        		}
       		}

       		if ($found === false) {
	        	$currency_config[] = array(
	        		'code'			=> $code,
	        		'currency_id'	=> 0
				);
				$found = 0;
       		}
       		
       		// Если валюта на сайте единственная. то сразу сопоставим с ней.
			$this->load->model('localisation/currency');
			$currencies = $this->model_localisation_currency->getCurrencies();

			if (count($currencies) == 1) {
				$currency_default = array_shift($currencies);
				$currency_config[$found]['currency_id'] = $currency_default['currency_id'];

			} else {
				$this->session->data['error'] = "M170";
		        $this->log("Не сопоставлена валюта по коду " . $code . ", \n
					обновите страничку модуля и настройте валюты в разделе Предложения");
			}

			$this->setConfig("exchange1c_currency", $currency_config, 1);

        }
		
		return $currency;
   		
   	} // getCurrency()


    /**
     * Возвращает данные валюты (курс, кол. символов после запятой...)
     */
    private function getCurrencyData($currency_id) {

        $query = $this->query ("SELECT * FROM " . DB_PREFIX . "currency WHERE currency_id = '" . (int)$currency_id . "' AND status = 1");

        if ($query->num_rows) {
            return $query->row;
        }

        return array();

    } // getCurrencyData()


    /**
     * Получает единицу измерения по коду
     */
    private function getUnit($unit_code) {
		
    	if (empty($unit_code)) {
    		$this->session->data['error'] = "M180";
    		$this->log("Пустое значение КодЕдиницы передано в функцию getUnit()");
    		return array();
    	}
    	
    	$unit = array();

        $query = $this->query("SELECT * FROM 1c_unit WHERE code = '" . $this->db->escape($unit_code) . "'");

        if (!$query->num_rows) {
        	$this->session->data['error'] = "M181";
        	$this->log("Не найдено ни одной единицы по коду = " . $unit_code);
        	$this->log($query);
        	return array();
        }

        if ($query->num_rows > 1) {
        	$this->session->data['error'] = "M182";
        	$this->log("Получено более одной единицы по коду = " . $unit_code);
        	$this->log($query);
        	return array();
        }

		if ($query->num_rows) {
        	$unit = $query->row;
        }

		//$this->log($unit, true);
        return $unit;

    } // getUnit()


    /**
     * Записывет остатки из предложений
     */
    private function setQuantity($offer) {

		if ($this->config->get('exchange1c_module_status') == "debug") {
			return true;
		}
		
		$aStorages = array();
		$query = $this->query("SELECT storage_id,guid FROM 1c_storage");
		foreach ($query->rows as $row) {
			$aStorages[$row['guid']] = $row['storage_id'];
		}

		$bResult = false;
		
		$aQuantityOld = array();
		$query = $this->query("SELECT r.*,s.guid FROM 1c_rests r
			LEFT JOIN 1c_storage s ON (r.storage_id = s.storage_id)
			WHERE r.product_id = " . (int)$offer['product_id'] . " 
			AND r.offer_id = " . (int)$offer['offer_id']
		);
		foreach ($query->rows as $row) {
			$aQuantityOld[$row['guid']] = array(
				'product_rest_id'	=> $row['product_rest_id'],
				'quantity'			=> $row['quantity'],
				'storage_id'		=> $row['storage_id']
			);
		}
		$this->log($aQuantityOld, 2);

		if (!count($aStorages)) {
			$this->session->data['error'] = "M200";
			return false;
		}

		foreach ($offer['quantities'] as $sStorageGuid => $fQuantity) {
			if (!isset($aQuantityOld[$sStorageGuid])) {

				$this->query("INSERT INTO 1c_rests SET
					storage_id = " . (int)$aStorages[$sStorageGuid]. ",
					offer_id = " . (int)$offer['offer_id'] . ",
					product_id = " . (int)$offer['product_id'] . ",
					quantity = " . $fQuantity
				);
				$bResult = true;
				continue;
			}
			if ($aQuantityOld[$sStorageGuid]['quantity'] != $fQuantity) {
				$this->query("UPDATE 1c_rests 
					SET quantity = '" . $quantity . "'
					WHERE product_rest_id = " . (int)$aQuantityOld[$sStorageGuid]['product_rest_id']
				);
				$bResult = true;
			}					
		} // foreach() по складам

		return $bResult;

	} // setQuantity()


	/**
	*	---------------------------------	Функции с ценами (начало)  ----------------------------------------
	*/

    /**
     * Получает список загруженных цен
     */
    public function getPriceType() {

		$aResult = array();
		$query = $this->query("SELECT * FROM 1c_price_type");
		foreach ($query->rows as $row) {
			$aResult[$row['guid']] = array(
				'name'			=> $row['name'],
				'price_type_id'	=> $row['price_type_id'],
				'currency_id'	=> $row['currency_id']
			);
		}
		
		return $aResult;

   	} // getPriceType()


	/**
     */
    public function getPriceConfig() {
    	
		$aDataConfig = array();

		// Получим данные из таблицы настроек
		$aPriceConfig = array();
		$query = $this->query("SELECT * FROM 1c_price_type_config c
			LEFT JOIN 1c_price_type t ON (c.price_type_id = t.price_type_id)"
		);
		foreach ($query->rows as $row) {
			$aDataConfig[$row['price_config_id']] = $row;
		}
		
		//$this->log($aDataConfig);
		return $aDataConfig;

    } // getPriceConfig()


	/**
     */
    public function setPriceConfig($aSetting = array()) {
    	
		$aDataConfig = array();
		$aPriceType = $this->getPriceType();

		$this->log($aPriceType);
		$this->log($aSetting);
		
		// Получим данные из таблицы настроек
		//$this->query("UPDATE 1c_price_type_config SET action = 0");
		$aPriceConfig = $this->getPriceConfig();
		$this->log($aPriceConfig);
		
		foreach ($aSetting as $aSettingRow) {
			if (empty($aSettingRow['guid'])) {
				$this->log("Тип цены пропущен!");
				$this->log($aSettingRow);
				continue;
			}
			$this->log($aSettingRow);
			if(empty($aSettingRow['price_config_id'])) {
				$this->query("INSERT INTO 1c_price_type_config SET 
					price_type_id = " . $aPriceType[$aSettingRow['guid']]['price_type_id'] . ",
					customer_group_id = " . $aSettingRow['customer_group_id'] . ",
					purpose = '" . $aSettingRow['purpose'] . "',
					quantity = " . $aSettingRow['quantity'] . ",
					priority = " . $aSettingRow['priority'] . ",
					action = 1"
				);
				$aSettingRow['price_config_id'] = $this->db->getLastId();
				$aSettingRow['action'] = 1;
				$aPriceConfig[$aSettingRow['price_config_id']] = $aSettingRow;
			} else {
				$aSettingRow['action'] = 2;
				if (array_diff_assoc($aSettingRow, $aPriceConfig[$aSettingRow['price_config_id']])) {
					$aSettingRow['action'] = 1;
					$this->query("UPDATE 1c_price_type_config SET
						price_type_id = " . $aPriceType[$aSettingRow['guid']]['price_type_id'] . ",
						customer_group_id = " . $aSettingRow['customer_group_id'] . ",
						purpose = '" . $aSettingRow['purpose'] . "',
						quantity = " . $aSettingRow['quantity'] . ",
						priority = " . $aSettingRow['priority'] . ",
						action = 1
						WHERE price_config_id = " . $aSettingRow['price_config_id']
					);
				}
				$aPriceConfig[$aSettingRow['price_config_id']] = $aSettingRow;
			}
		}
		
		// проверим неиспользуемые настройки
		$this->log($aPriceConfig);
		foreach ($aPriceConfig as $price_config_id => $aPriceConfigRow) {
			if (!$aPriceConfigRow['action']) {
				$this->query("DELETE FROM 1c_price_type_config WHERE price_config_id = " . (int)$price_config_id);
				// Удалим все цены связанные с этим типом
				$this->query("DELETE FROM 1c_prices WHERE price_config_id = " .  (int)$price_config_id);
				continue;
			} elseif ($aPriceConfigRow['action'] == 1) {
				$query = $this->query("SELECT product_id FROM 1c_prices WHERE price_config_id = " . (int)$price_config_id);
				foreach ($query->rows as $row) {
					$this->query("UPDATE 1c_product SET action = 1 WHERE product_id = " . (int)$row['product_id']);
				}
				$this->updateCatalog($aSetting);
				$this->query("UPDATE 1c_price_type_config SET action = 0 WHERE price_config_id = " . $price_config_id);
			}
			$aDataConfig[$price_config_id] = $aPriceConfigRow;
		}

		$this->log($aDataConfig);
		return $aDataConfig;

    } // priceConfig()


    /**
     * Загружает типы цен из классификатора
     */
    private function parsePriceType($xml){

		// Читаем типы цен из файла
		foreach ($xml->ТипЦены as $xPrice) {
			$aPriceType = array(
				'guid'				=> trim((string)$xPrice->Ид),
				'name'				=> trim((string)$xPrice->Наименование),
				'currency_id'		=> $this->getCurrencyId((string)$xPrice->Валюта)
			);

			$query = $this->query("SELECT * FROM 1c_price_type WHERE guid = '" . $this->db->escape($aPriceType['guid']) . "'");

			if (!$query->num_rows) {
		    	$this->query("INSERT INTO 1c_price_type SET
		    		guid = '" . $this->db->escape($aPriceType['guid']) . "',
		    		name = '" . $this->db->escape($aPriceType['name']) . "',
					currency_id = " . (int)$aPriceType['currency_id']
				);
			}

        } // foreach ($xml->ТипЦены)

		return true;

    } // parsePriceType()


	/**
	* Записывет цены из предложений
	*/
	private function setPrice($aOffer) {

		if ($this->config->get('exchange1c_module_status') == "debug") {
			return true;
		}
		
		$bResult = false;

		// Единица измерения по-умолчанию
		if ($aOffer['unit']) {
			$aOffer['unit_id'] = $aOffer['unit']['unit_id']; 
		}

		$this->log($aOffer['prices']);
		foreach ($aOffer['prices'] as $aPriceRow) {
			$iProductPriceId = 0;
			$query = $this->query("SELECT * FROM 1c_prices WHERE
				price_config_id = " . (int)$aPriceRow['price_config_id'] . " AND
				offer_id = " . (int)$aOffer['offer_id']
			);
			$this->log($query);

			if ($query->num_rows) {
				$iProductPriceId = $query->row['product_price_id'];
				if ($query->row['price'] != $aPriceRow['price']) {
					$this->query("UPDATE 1c_prices SET price = '" . $aPriceRow['price'] . "' WHERE product_price_id = " . (int)$iProductPriceId);
					$bResult = true;
				}
			}
			
			if (!$iProductPriceId) {
				$this->query("INSERT INTO 1c_prices SET 
					price_type_id = " . (int)$aPriceRow['price_type_id'] . ",
					price_config_id = " . (int)$aPriceRow['price_config_id'] . ",
					offer_id = " . (int)$aOffer['offer_id'] . ",
					feature_id = " . (int)$aOffer['feature_id'] . ",
					product_id = " . (int)$aOffer['product_id'] . ",
					unit_id = " . (int)$aOffer['unit_id'] . ",
					price = '" . $aPriceRow['price'] . "'"
				);
				$bResult = true;
			}
		}

		return $bResult;

	} // setPrice()


    /**
     */
    private function setPriceDiscount($product_id, $aPriceConfig) {
    	$query = $this->query("SELECT * FROM " . DB_PREFIX . "product_discount WHERE product_id = " . (int)$product_id . " 
			AND customer_group_id = " . (int)$aPriceConfig['customergroup_id']);
    	if (!$query->num_rows) {
    		return;
    	}
    	foreach ($query->rows as $row) {
    		
    	}
	}

	/**
	*	---------------------------------	Функции с ценами (конец)  ----------------------------------------
	* 
	*/

    /**
     * Загружает предложения (остатки и цены)
     * Предложения пока не поддерживают мультиязычность
     */
    private function parseOffers_v3($xml) {

        $this->log("~importOffers v3 (Begin)", 2);

        if (!$xml->Предложение) {
        	$this->log("Нет предложений!");
        	return true;
        }
        
		$aPriceConfig = $this->getPriceConfig();
		
        $this->log("Всего предложений: " . count($xml->Предложение));
        
        // Перебираем все предложения
        foreach ($xml->Предложение as $offer_xml) {
        	
			$guid = explode("#", (string)$offer_xml->Ид);
	
			// Данные о предложении
			$offer = array(
				'product_id'		=> $this->getProductIdByGuid($guid[0]),
				'offer_id'			=> 0,
				'product_guid'		=> $guid[0],
				'feature_guid'		=> isset($guid[1]) ? $guid[1] : "",
				'feature_id'		=> 0,
				'version'			=> $offer_xml->НомерВерсии ? (string)$offer_xml->НомерВерсии : "",
				'delete_mark'		=> $offer_xml->ПометкаУдаления ? 1 : 0,
				'ean'				=> $offer_xml->Штрихкод ? (string)$offer_xml->Штрихкод : "",
				'name'				=> (string)$offer_xml->Наименование,
				'feature_name'		=> "",
				'sku'				=> $offer_xml->Артикул ? (string)$offer_xml->Артикул : "",
				'unit'				=> array(),
				'unit_id'			=> 0,
				'storage_version'	=> ""
			);
			
			if ($this->session->data['error']) return false;
			
			if (!$offer['product_id'] && $this->config->get('exchange1c_module_status') != "debug") {
				if ($this->config->get('exchange1c_offer_non_exist_error')) {
					$this->session->data['error'] = "M220";
					$this->log("Не найден товар по Ид при загрузке предложения");
					$this->log($offer);
					return false;
				}
			}

			// Базовая единица измерения
			if ($offer_xml->БазоваяЕдиница) {
				$offer['unit'] = $this->getUnit((string)$offer_xml->БазоваяЕдиница['Код']);

				if ($this->session->data['error']) return false;

				//$offer['unit'] = $this->setUnit($offer_xml->БазоваяЕдиница);
				$offer['unit_id'] = $offer['unit']['unit_id'];
			}

			// Цены загружаем если указаны типы цен
			$this->log($aPriceConfig, 2);
			if ($offer_xml->Цены && $aPriceConfig) {

				$offer['prices'] = array();

				foreach ($offer_xml->Цены->Цена as $xPrice) { 

					$sPriceGuid = (string)$xPrice->ИдТипаЦены;

					foreach ($aPriceConfig as $row) {
						if ($row['guid'] == $sPriceGuid) {
							$offer['prices'][$row['guid']] = array(
								'price_config_id'	=> $row['price_config_id'],
								'price_type_id'		=> $row['price_type_id'],
								'price'				=> (float)$xPrice->ЦенаЗаЕдиницу
							);
						}
					}

//					$price_config_current['unit']		= $this->getUnit((string)$xPrice->Единица);
//					if ($this->session->data['error']) {
//						$this->log($this->session);
//						$this->log($xPrice);
//						return 0;
//					}

				}
			}
			// Цены
			
			// Остатки v3
			if ($offer_xml->Остатки) {
				$quantites = array();
				$quantity = 0;
				foreach ($offer_xml->Остатки->Остаток as $quantity_xml) {
					if ($quantity_xml->Склад) {
						$storage_guid = (string)$quantity_xml->Склад->Ид;
						$quantites[$storage_guid] = (float)$quantity_xml->Склад->Количество;
						$quantity += $quantites[$storage_guid];
					}
				}
				$offer['quantities'] = $quantites;
				$offer['quantity'] = $quantity;
			}
			// Остатки v3

			// Остатки (без складов)
			if ($offer_xml->Количество) {
				$offer['quantity'] = (float)$offer_xml->Количество;
			}
			// Остатки

			// Остатки v2
			if ($offer_xml->Склад) {
				$quantites = array();
				$quantity = 0;
				foreach ($offer_xml->Склад as $quantity_xml) {
					$storage_guid = (string)$quantity_xml['ИдСклада'];
					$quantites[$storage_guid] = (float)$quantity_xml['КоличествоНаСкладе'];
					$quantity += $quantites[$storage_guid];
				}
				$this->log($quantites, 2);
				$offer['quantities'] = $quantites;
				$offer['quantity'] = $quantity;
			}
			// Остатки v2
			
			// Характеристика
			if ($offer['feature_guid']) {
				// Прочитаем существующую характеристикку
				$feature_info = $this->getFeature($offer);
				if ($this->session->data['error']) {
					$this->log($this->session);
					$this->log($feature_info);
					return false;
				}
	
				// Отделим название характеристики от наименования предложения
				if ($offer['name']) {
					$offer['feature_name']	= $this->getFeatureName($offer['name']);
				}

				if ($feature_info) {
					$offer['feature_id'] = $feature_info['feature_id'];
	
					// Обновляем или добавляем если есть наименование
					if ($offer['feature_name']) {
						if (!$this->updateFeature($offer, $feature_info)) {
							return false;
						}
					}
	
				} else {
					$offer['feature_id'] = $this->addFeature($offer);
					if ($this->session->data['error']) return false;

				}
			}
			// Характеристика

			$this->log($offer, 2);
			
			$offer_info = $this->getOffer($offer);
			if ($this->session->data['error']) return false;

			$this->log($offer_info, 2);

			if ($offer_info) {
				$offer['offer_id'] = $offer_info['offer_id'];

				// Обновляем или создаем предложение если есть наименование
				if ($offer['name']) {
					$this->log("Предложение " . $offer['name'], 1);
					$this->updateOffer($offer, $offer_info);
				}

			} else {

				$offer['offer_id'] = $this->addOffer($offer);
				if ($this->session->data['error']) return false;

			}
			
			if (isset($offer['prices'])) {
				$this->session->data['progress'] = 1;
				if ($this->setPrice($offer)) {
					$this->session->data['progress'] = 1;
				}
				if ($this->session->data['error']) return false;
			}

			if (isset($offer['quantities'])) {
				$this->session->data['progress'] = 1;
				if ($this->setQuantity($offer)) {
					$this->session->data['progress'] = 1;
				}
				if ($this->session->data['error']) return false;
			}

			if ($this->session->data['progress'])
				$this->query("UPDATE 1c_product SET action = 1 WHERE product_id = " . (int)$offer['product_id']);

			$this->log($offer, 2);
			$this->log("_", 2);
			
			$this->cache->delete('product');

		} // foreach

		$this->log("_parseOffers_v3 (End)", 2);
		
		return true;

	} // parseOffers_v3


    /**
     * Проверяет на наличие полной выгрузки в каталоге или в предложениях
     */
    private function checkModeImport($xml) {

		if ($xml['СодержитТолькоИзменения'])
			$this->session->data['full_import'] = (string)$xml['СодержитТолькоИзменения'] == "false" ? true : false;
		elseif ($xml->СодержитТолькоИзменения)
			$this->session->data['full_import'] = (string)$xml->СодержитТолькоИзменения == "false" ? true : false;

    } // checkModeImport()


	/**
	 */
    public function getModuleStatus() {

		$query = $this->query("SHOW TABLES LIKE '1c_session'");
		if ($query->num_rows) {
			return 1;
		}
		return 0;

	} //getModuleStatus()


	/**
     * ****************************** ФУНКЦИИ ДЛЯ ЗАГРУЗКИ ЗАКАЗОВ ******************************
     */


	/**
     */
    public function ordersExportSuccess() {
    	//Получим список выгруженных заказов
    	$query = $this->query("SELECT * FROM 1c_order WHERE status_export = 1");
   		foreach ($query->rows as $row) {
   			continue;
			$this->query("UPDATE 1c_order SET 
				status_export = 2,
				date_export = NOW()
				WHERE order_id = " . (int)$row['order_id']
			);
			// если нужно изменить статус заказа
   		}
   	} //ordersExportSuccess()


	/**
     * Меняет статусы у новых заказов заказов
     *
     * @param	int		exchange_status
     * @return	bool
     */
    public function queryOrdersChangeStatus($orders) {

        // Если статус новый пустой, тогда не меняем, чтобы не породить ошибку
        $new_status = $this->config->get('exchange1c_order_status_exported');
        if (!$new_status) {
            $this->log("ERROR 2101");
            return false;
        }

        // Уведомление при смене статуса
        $notify = 0;

        if ($orders) {

            $this->XML_DATE = date('Y-m-d H:i:s');

            foreach ($orders as $order_id => $order_status_id) {

                // Пропускаем те у кого статус не равен "Статус для выгрузки"
                if ($order_status_id != $this->config->get('exchange1c_order_status_export')) {
                    $this->log("> Cтатус заказа #" . $order_id . " не менялся.", 2);
                    continue;
                }

                // Меняем статус
                $query = $this->query("UPDATE " . DB_PREFIX . "order SET order_status_id = " .
                    (int)$new_status . " WHERE order_id = " . (int)$order_id
				);
                $this->log("> Изменен статус заказа #" . $order_id);

                // Добавляем историю в заказ
                $query = $this->query("INSERT INTO " . DB_PREFIX . "order_history 
					SET order_id = " . (int)$order_id . ", 
					comment = 'Заказ выгружен в учетную систему', 
					order_status_id = " . (int)
                    $new_status . ", 
					notify = " . $notify . ", 
					date_added = '" . $this->XML_DATE . "'"
				);
                $this->log("> Добавлена история в заказ (изменен статус) #" . $order_id, 2);
            }
        }

        return true;

    } // queryOrdersStatus()


    /**
     * Получает название статуса документа на текущем языке
     *
     */
    private function getOrderStatusName($order_staus_id) {

        $query = $this->query("SELECT name FROM " . DB_PREFIX . "order_status 
			WHERE order_status_id = " . (int)$order_staus_id . " 
			AND language_id = " . $this->LANG_ID
		);
        if ($query->num_rows) {
            return $query->row['name'];
        }
        return "";
    } // getOrderStatusName()


    /**
     * Возвращает список статусов заказов
     *
     */
    public function getOrderStatus() {

        $this->LANG_ID = $this->getLanguageIdDefault();

		$result = array();

		$query = $this->query("SELECT * FROM " . DB_PREFIX . "order_status WHERE language_id = " . (int)$this->LANG_ID);
        if ($query->num_rows) {
        	foreach ($query->rows as $row) {
        		$result[$row['order_status_id']] = $row;
        	}
            return $result;
        }
        return $result;
    } // getOrderStatus()


    /**
     * Получает GUID характеристики по выбранным опциям
     */
    private function getFeatureGUID($product_id, $order_id) {

        $order_options = $this->model_sale_order->getOrderOptions($order_id, $product_id);
        $options = array();
        foreach ($order_options as $order_option) {
            $options[$order_option['product_option_id']] = $order_option['product_option_value_id'];
        }

        $product_feature_id = 0;
        foreach ($order_options as $order_option) {
            $query = $this->query("SELECT product_feature_id FROM " . DB_PREFIX . "product_feature_value 
				WHERE product_option_value_id = " . (int)$order_option['product_option_value_id']
			);

            if ($query->num_rows) {
                if ($product_feature_id) {
                    if ($product_feature_id != $query->row['product_feature_id']) {
                        $this->log("ERROR 2006");
                        return false;
                    }

                } else {
                    $product_feature_id = $query->row['product_feature_id'];
                }
            }
        }

        $feature_guid = "";
        if ($product_feature_id) {
            // Получаем Ид
            $query = $this->query("SELECT guid FROM " . DB_PREFIX . "product_feature 
				WHERE product_feature_id = " . (int)$product_feature_id
			);

            if ($query->num_rows) {
                $feature_guid = $query->row['guid'];
            }
        }

        return $feature_guid;

    } // getFeatureGUID


    /** ****************************** ФУНКЦИИ ДЛЯ ВЫГРУЗКИ ЗАКАЗОВ *******************************/


    /**
     * ver 2
     * update 2018-04-09
     * Формирует адрес с полями и представлением в виде массива
     */
    private function setCustomerAddress($aOrder, $sMode = 'shipping') {

        // Соответствие полей в XML и в базе данных
        $aFields = array(
			'postcode'	=> "",
			'country'	=> "",
			'zone' 		=> "",
			'city' 		=> "г. ",
            'address_1'	=> "",
            'address_2' => ""
   		);

        $aAddress = array();
        $nCounter = 0;

        // Представление
        $aName = array();

        // Формирование полей
        foreach ($aFields as $sType => $sSocr) {
            if (isset($aOrder[$sMode . '_' . $sType])) {
                // формируем наименование
                $aName[] = $sSocr . $aOrder[$sMode . '_' . $sType];
                $nCounter++;
            }
        }

        $aAddress['Представление'] = implode(', ', $aName);

        return $aAddress;

    } // setCustomerAddress()


    /**
     * Формирует контактные данные контрагента
     */
    private function setCustomerContacts($aOrder) {
        $this->log($aOrder, 2);
        // Соответствие полей в XML и в базе данных
        $aFields = array(
            'Телефон Рабочий' => 'telephone',
            'Телефон' => 'telephone',
            'Почта' => 'email');

        $aContact = array();
        $nCounter = 0;

        // Формирование полей
        foreach ($aFields as $sType => $sField) {

            if (isset($aOrder[$sField])) {
                // Формируем типы полей
                $aContact['Контакт' . $nCounter] = array('Тип' => $sType, 'Значение' => $aOrder[$sField]);
            }
            $nCounter++;
        }
        return $contact;

    } // setCustomerContacts()


    /**
     */
    private function getPriceTypeName($customer_group_id) {
		$query = $this->query("SELECT * FROM 1c_price_type_config ptc LEFT JOIN 1c_price_type pt 
			ON (ptc.price_type_id = pt.price_type_id) WHERE ptc.customer_group_id = " . $customer_group_id);
		if (!$query->num_rows) {
			return "";
		}
		return $query->row['name'];
   	} // getPriceTypeName()


    /**
     * Формирует реквизиты документа
     */
    private function setDocumentRequisites($order, $document) {

        $requisites = array();
        // Счетчик
        $counter = 0;

        $requisites['Дата отгрузки'] = $order['date_added'];
        $requisites['Статус заказа'] = $this->getOrderStatusName($order['order_status_id']);
        $requisites['Вид цен'] = $this->getPriceTypeName($order['customer_group_id']);
        $requisites['Контрагент'] = $order['lastname'] . " " . $order['firstname'];
        //		$requisites['Склад'] 						= $this->getWarehouseName($order['warehouse_id']);
        //		$requisites['Организация'] 					= 'Наша фирма';
        //		$requisites['Подразделение'] 				= 'Интернет-магазин';
        //		$requisites['Сумма включает НДС'] 			= 'true';
        //		$requisites['Договор контрагента'] 			= 'Основной договор';
        //		$requisites['Метод оплаты'] 				= 'Заказ по телефону';

        // Для 1С:Розница
        //		$requisites['ТочкаСамовывоза'] 				= 'Название магазина';
        //		$requisites['ВидЦенНаименование'] 			= 'Розничная';
        //		$requisites['СуммаВключаетНДС'] 			= 'true';
        //		$requisites['НаименованиеСкидки'] 			= 'Скидка 5%';
        //		$requisites['ПроцентСкидки']				= 5;
        //		$requisites['СуммаСкидки']					= 1000;
        //		$requisites['СкладНаименование']			= 'Основной склад';
        //		$requisites['ПодразделениеНаименование']	= 'Основное подразделение';
        //		$requisites['Склад']						= 'Основной склад'

        // Для УНФ XML 2.08
        //		$requisites['ВидЦен'] 						= 'Розничная';
        //		$requisites['СкладДляПодстановкиВЗаказы'] 	= 'Склад основной';


        $data = array();
        foreach ($requisites as $name => $value) {

            // Пропускаем пустые значения
            if (!$value)
                continue;

            $data['ЗначениеРеквизита' . $counter] = array('Наименование' => $name,
                    'Значение' => $value);

            $counter++;

        } // foreach

        return $data;

    } // setDocumentRequisites()


    /**
     * Получает информацию о покупателе (организации и физ.лице)
     */
    public function getCustomerInfo(&$order) {

        $query = $this->query("SELECT firstname, lastname 
			FROM " . DB_PREFIX . "customer WHERE customer_id = '" . (int)$order['customer_id'] . "'"
		);
        if ($query->num_rows) {
			$order['firstname'] = $query->row['firstname'];
			$order['lastname'] = $query->row['lastname'];
         }

    } // getCustomerInfo()


    /**
     * Формирует Контрагента
     */
    private function setCustomer($aOrder) {

		// Первая буква должна быть заглавной и убираем лишние пробелы сдева и справа
		// ТОЛЬКО ДЛЯ САЙТА РАБОТАЮЩЕГО НА КОДИРОВКЕ UTF-8
		$aOrder['firstname'] = mb_convert_case(trim($aOrder['firstname']), MB_CASE_TITLE, "UTF-8");
		$aOrder['lastname'] = mb_convert_case(trim($aOrder['lastname']), MB_CASE_TITLE, "UTF-8");

		// Собираем полное наименование покупателя, ФИО
		$aOrder['username'] = $aOrder['lastname'] . ' ' . $aOrder['firstname'];

        if (empty($aOrder['firstname'])) {
			$this->session->data['error'] = "2110";
			$this->log($aOrder, "setCustomer=order:EM2110");
			return false;
        }

        if (empty($aOrder['lastname'])) {
			$this->session->data['error'] = "2111";
			$this->log($aOrder, "setCustomer=order:EM2111");
			return false;
        }

		$aCustomer = array();
        // Обязательные поля покупателя для торговой системы
        $aCustomer = array(
            'Ид' => $aOrder['customer_id'] . '#' . $aOrder['email'],
            'Роль' => 'Покупатель',
            'Наименование' => trim($aOrder['username']),
            'ПолноеНаименование' => trim($aOrder['username']),
            'Фамилия' => trim($aOrder['lastname']),
            'Имя' => trim($aOrder['firstname']),
			'Телефон' => $aOrder['telephone'],
			'Email' => $aOrder['email'],
            'Адрес' => $this->setCustomerAddress($aOrder),
		);

        $this->log($aCustomer, "setCustomer=aCustomer");
        return $aCustomer;

    } // setCustomer()


    /**
     * Подготавливает заказы для экспорта
     */
    private function setOrdersExport() {
        $aOrdersExport = array();
        $sql = "SELECT * FROM " . DB_PREFIX . "order";
        $aSqlWhere = array();

		$msg = "Заказы для экспорта";

        // Выгрузка заказов с указанными статусами
        $status_list = array();
        
        if (!$this->config->get('exchange1c_export_order_statuses')) {
        	return $aOrdersExport;
        }

        foreach ($this->config->get('exchange1c_export_order_statuses') as $order_status_id) {
        	$aStatusList[] = $order_status_id;
        }

		if ($aStatusList) {
			$aSqlWhere[] = "order_status_id IN (" . implode(',', $aStatusList) . ")";
			$msg .= " со статусами status_id=(" . implode(',', $aStatusList) . ")";
		}

		if ($this->config->get('exchange1c_begin_orders_export')) {
			$tFromDate = str_replace("T", " ", $this->config->get('exchange1c_begin_orders_export'));
			$tToDate = date("Y-m-d H:i:s");
			$aSqlWhere[] = "date_added BETWEEN STR_TO_DATE('" . $tFromDate . ":00', '%Y-%m-%d %H:%i:%s') AND STR_TO_DATE('" . $tToDate . "', '%Y-%m-%d %H:%i:%s')";
			$msg .= " начиная с даты " . $tFromDate;
		}
		$query = $this->query($sql . " WHERE " . implode(' AND ', $aSqlWhere));
		$this->log($msg);

		foreach ($query->rows as $row) {
			$aOrdersExport[$row['order_id']] = $row;
		}
		
		// Если включена галочка только оплаченные, удалим из списка неоплаченные
		if ($this->config->get('exchange1c_orders_export_only_pay')) {
			foreach ($aOrdersExport as $order_id => $order_status_id) {
				$query = $this->query("SELECT order_id FROM 1c_document WHERE order_id = " . (int)$order_id . " AND payment_date <> ''");
				if (!$query->num_rows && isset($aOrdersExport[$order_id])) {
					$this->log("Удален заказ #" . $order_id . " из экспорта так как не оплаченный", "setOrdersExport=order_id");
					unset($aOrdersExport[$row['order_id']]);
				}
			}
		}
		
		// Если включена галочка необходима доставка
		if ($this->config->get('exchange1c_orders_export_only_shipping')) {
			foreach ($aOrdersExport as $order_id => $order_status_id) {
				$query = $this->query("SELECT op.order_id, p.product_id, p.shipping FROM " . DB_PREFIX . "order_product op 
					LEFT JOIN " . DB_PREFIX . "product p ON (op.product_id = p.product_id)
					WHERE op.order_id = " . (int)$order_id . " AND p.shipping = 1"
				);
				if (!$query->num_rows && isset($aOrdersExport[$order_id])) {
					$this->log("Удален заказ #" . $order_id . " из экспорта так как не разрешена доставка товаров", "setOrdersExport=order_id");
					unset($aOrdersExport[$order_id]);
				}
			}
		}
		
		// Отметим заказы которые выгрузили
		foreach ($aOrdersExport as $aOrder) {
			$query = $this->query("SELECT * FROM 1c_order WHERE order_id = " . $aOrder['order_id']);
			if ($query->num_rows) {
				if ($query->row['status_export'] != 1) {
					$this->query("UPDATE 1c_order SET status_export = 1 WHERE order_id = " . $aOrder['order_id']);
				}
			} else {
				$this->query("INSERT INTO 1c_order SET order_id = " . $aOrder['order_id'] . ", status_export = 1");
			}
		}

		$this->log($aOrdersExport);
		
		// Получим список заказов которые еще не выгружались в 1С

        return $aOrdersExport;

    } // setOrdersExport()


    /**
     * Выгружает заказы в торговую систему
     */
    public function ordersExport() {

        $this->log("Экспорт заказов");

        $this->LANG_ID = $this->getLanguageIdDefault();

        // Получим список order_id которые подлежат выгрузке
		$aOrdersExport = $this->setOrdersExport();
		if ($aOrdersExport) {
	        $this->log($aOrdersExport, "ordersExport=aOrdersExport");
		}

		
        // Валюта документа равняется валюте сайта по-умолчанию
		$config_currency = $this->config->get('exchange1c_currency');

        $currency = $this->config->get('exchange1c_order_currency') ? $this->config->get('exchange1c_order_currency') :  "руб.";

        $document = array();

        if (count($aOrdersExport)) {

            $document_counter = 0;

            $this->load->model('customer/customer_group');
            $this->load->model('sale/order');

            foreach ($aOrdersExport as $aOrder) {

                //$aOrder = $this->model_sale_order->getOrder($aOrder['order_id']);
                $this->log("Заказ #" . $aOrder['order_id']);
                $this->log($aOrder, "ordersExport=order");


                // Если при оформлении заказа покупатель зарегистрировался
                if ($aOrder['customer_id']) {
                    $this->getCustomerInfo($aOrder);
                }

                $customer_group = $this->model_customer_customer_group->getCustomerGroup($aOrder['customer_group_id']);

                // Шапка документа
                $document['Документ' . $document_counter] = array(
					'Ид' => $aOrder['order_id'],
					'Номер' => $aOrder['order_id'],
					'Дата' => date('Y-m-d', strtotime($aOrder['date_added'])),
					'Время' => date('H:i:s', strtotime($aOrder['date_added'])),
                    'ХозОперация' => 'Заказ товара',
                    'Роль' => 'Продавец',
                    'Валюта' => $currency,
                    'Курс' => 1,
                    'Сумма' => $aOrder['total'],
                    'Комментарий' => $aOrder['comment']
                        //,'Соглашение'  => $customer_group['name'] // the agreement
                        );

                // ПОКУПАТЕЛЬ (КОНТРАГЕНТ)
                $document['Документ' . $document_counter]['Контрагенты']['Контрагент'] = $this->setCustomer($aOrder);
                if ($this->session->data['error']) return false;

                // РЕКВИЗИТЫ ДОКУМЕНТА
                $document['Документ' . $document_counter]['ЗначенияРеквизитов'] = $this->setDocumentRequisites($aOrder,
                    $document);
                if ($this->session->data['error']) return false;

                // ТОВАРЫ ДОКУМЕНТА
                $products = $this->model_sale_order->getOrderProducts($aOrder['order_id']);

                $product_counter = 0;
                foreach ($products as $product) {
                    $product_guid = $this->getGuidByProductId($product['product_id']);
                    $document['Документ' . $document_counter]['Товары']['Товар' . $product_counter] = array(
                        'Ид' => $product_guid,
                        'Наименование' => $product['name'],
                        'ЦенаЗаЕдиницу' => $product['price'],
                        'Количество' => $product['quantity'],
                        'Сумма' => $product['total'],
                        'Скидки' => array('Скидка' => array('УчтеноВСумме' => 'false', 'Сумма' => 0)),
                        'ЗначенияРеквизитов' => array(
							'ЗначениеРеквизита' => array(
								'Наименование' => 'ТипНоменклатуры', 
								'Значение' => 'Товар'
							)
						)
					);
                    $current_product = &$document['Документ' . $document_counter]['Товары']['Товар' .
                        $product_counter];
                    // Резервирование товаров
                    if ($this->config->get('exchange1c_order_reserve_product') == 1) {
                        $current_product['Резерв'] = $product['quantity'];
                    }

                    // Если не заданы единицы измерений товара, выгружаем базовую
                    if ($this->config->get('exchange1c_export_system') == '1c_ut11') {
                        $current_product['БазоваяЕдиница'] = array(
							'Код' => '796', 
							'НаименованиеПолное' => 'Штука'
						);
                    }

                    // Характеристики
                    $feature_guid = $this->getFeatureGuid($product['order_product_id'], $aOrder['order_id']);
                    if ($feature_guid) {
                        $current_product['Ид'] .= "#" . $feature_guid;
                    }

                    // Доставка в комментарий
                    $query = $this->query ("SELECT title FROM " . DB_PREFIX . "order_total 
						WHERE order_id = " . $aOrder['order_id'] . " AND code = 'shipping'"
					);

                    if ($query->num_rows)  {
                        $document['Документ' . $document_counter]['Комментарий'] .= "\nДоставка: " . $query->row['title'];
                    }
                    // Доставка в комментарий

                    $product_counter++;
                }

                $document_counter++;

            } // foreach ($query->rows as $orders_data)

        } // if (count($aOrdersExport))
        $this->log($document, "ordersExport=document");

        return $document;
        
    } // ordersExport()


	/**
	 * Выгружает статусы заказов в торговую систему
	 */
    public function orderStatus() {

        $this->log("Выгрузка статусов заказов", 2);
        $result = array();
        
        $this->LANG_ID = $this->getLanguageIdDefault();
		$query = $this->query("SELECT * FROM " . DB_PREFIX . "order_status WHERE language_id = " . (int)$this->LANG_ID);
        $index = 0;
		foreach ($query->rows as $row) {
			$result['Элемент'.$index] = array(
	        	'Ид' 		=> $row['order_status_id'],
	        	'Название' 	=> $row['name']
			);
			$index++;
		}
		
		return $result;

	} //orderStatus()


	/**
	 * Выгружает платежные системы в торговую систему
	 */
    public function payMethod() {

        $this->log("Выгрузка платежных систем", "payMethod");
        $results = array();
        
		$this->LANG_ID = $this->getLanguageIdDefault();
		$query = $this->query("SELECT * FROM " . DB_PREFIX . "extension WHERE type = 'payment'");
		
        $index = 0;
		foreach ($query->rows as $row) {
			$payment = $this->load->language('extension/payment/'.$row['code']);
			$this->log($payment, "payMethod=payment");
			$results['Элемент'.$index] = array(
	        	'Ид' 		=> $row['code'],
	        	'Название' 	=> $payment['heading_title']
			);
			$index++;
		}
		
		return $results;

	} //payMethod()


	/**
	 * Выгружает платежные системы в торговую систему
	 */
    public function shippingMethod() {

        $this->log("Выгрузка платежных систем", "shippingMethod");
        $results = array();
        
		$this->LANG_ID = $this->getLanguageIdDefault();
		$query = $this->query("SELECT * FROM " . DB_PREFIX . "extension WHERE type = 'shipping'");
		
        $index = 0;
		foreach ($query->rows as $row) {
			$this->load->language('extension/payment/'.$row['code']);
			$results['Элемент'.$index] = array(
	        	'Ид' 		=> $row['code'],
	        	'Название' 	=> $this->language->get('heading_title')
			);
			$index++;
		}
		
		return $results;

	} //shippingMethod()


    /**
     * Устанавливает опции заказа в товаре
     */
    private function setOrderProductOptions($order_id, $product_id, $order_product_id, $product_feature_id = 0) {

        // удалим на всякий случай если были
        $this->query("DELETE FROM " . DB_PREFIX . "order_option WHERE order_product_id = " . (int)$order_product_id);

        // если есть, добавим
        if ($product_feature_id) {
            $query_feature = $this->query("SELECT pfv.product_option_value_id, pf.name 
				FROM " . DB_PREFIX . "product_feature_value pfv 
				LEFT JOIN " . DB_PREFIX . "product_feature pf 
				ON (pfv.product_feature_id = pf.product_feature_id) 
				WHERE pfv.product_feature_id = " . (int)$product_feature_id . " 
				AND pfv.product_id = " . (int)$product_id
			);
            $this->log($query_feature, "setOrderProductOptions=query_feature");
            foreach ($query_feature->rows as $row_feature) {

                $query_options = $this->query("SELECT pov.product_option_id, pov.product_option_value_id, po.value, o.type 
					FROM " . DB_PREFIX . "product_option_value pov 
					LEFT JOIN " . DB_PREFIX . "product_option po 
					ON (pov.product_option_id = po.product_option_id) 
					LEFT JOIN " . DB_PREFIX . "option o 
					ON (o.option_id = pov.option_id) 
					WHERE pov.product_option_value_id = " . (int)$row_feature['product_option_value_id']
				);
                $this->log($query_options, "setOrderProductOptions=query_options");
                foreach ($query_options->rows as $row_option) {

                    $this->query("INSERT INTO " . DB_PREFIX . "order_option SET 
						order_id = " . (int)$order_id . ", 
						order_product_id = " . (int)$order_product_id . ", 
						product_option_id = " . (int)$row_option['product_option_id'] . ", 
						product_option_value_id = " . (int)$row_option['product_option_value_id'] . ", 
						name = '" . $this->db->escape($row_option['value']) .	"', 
						value = '" . $this->db->escape($row_feature['name']) . "', 
						type = '" . $this->db->escape($row_option['type']) . "'"
					);
                    $order_option_id = $this->db->getLastId();
                    $this->log("order_option_id: " . $order_option_id, "setOrderProductOptions");
                }
            }
        }
        $this->log("Записаны опции в заказ", 2);

    } // setOrderProductOptions()


    /**
     * Обновляет товар в заказе
     */
    private function updateOrderProduct($order_id, $order_product_data, $order_product_id) {

        $this->log($order_product_data, 2);

        $this->query("UPDATE " . DB_PREFIX . "order_product
			SET product_id = " . (int)$order_product_data['product_id'] . ",
			order_id = " . (int)$order_id . ",
			name = '" . $this->db->escape($order_product_data['name']) . "',
			model = '" . $this->db->escape($order_product_data['model']) . "',
			price = " . (float)$order_product_data['price'] . ",
			quantity = " . (float)$order_product_data['quantity'] . ",
			total = " . (float)$order_product_data['total'] . ",
			tax = " . (float)$order_product_data['tax'] . ",
			reward = " . (int)$order_product_data['reward'] . "
			WHERE order_product_id = " . (int)$order_product_id
		);
        $this->log("Товар '" . $order_product_data['name'] . "' обновлен в заказе #" . $order_id .
			", order_product_id = " . $order_product_id, "updateOrderProduct");

        // ОПЦИИ ТОВАРА
        if ($order_product_data['product_feature_id']) {

            // Получим все опции товара
            $this->load->model('catalog/product');
            $product_options_data = $this->model_catalog_product->getProductOptions($order_product_data['product_id']);

            //$product_options_data = $this->getProductOptions($order_product_data['product_id']);
            $this->log($product_options_data, 2);
            if (count($product_options_data) == 0) {
                // Опции в товаре нет
                $this->log("ERROR 2400");
                return false;
            }

            // Получим опции в заказе
            $order_product_options_data = $this->model_sale_order->getOrderOptions($order_id,
                $order_product_id);
            $this->log($order_product_options_data, "updateOrderProduct");

            // Получим опции по характеристике, то есть по product_feature_id
            $query_feature_value = $this->query (
				"SELECT pfv.product_option_id, pfv.product_option_value_id, od.name, ovd.name as value, o.type 
				FROM " . DB_PREFIX . "product_feature_value pfv
				LEFT JOIN " . DB_PREFIX . "product_option_value pov 
				ON (pfv.product_option_value_id = pov.product_option_value_id)
				LEFT JOIN " . DB_PREFIX . "option o 
				ON (pov.option_id = o.option_id)
				LEFT JOIN " . DB_PREFIX . "option_description od 
				ON (pov.option_id = od.option_id)
				LEFT JOIN " . DB_PREFIX . "option_value_description ovd 
				ON (pov.option_value_id = ovd.option_value_id)
				WHERE pfv.product_feature_id = " . (int)$order_product_data['product_feature_id']
			);

            $this->log($query_feature_value, "updateOrderProduct");

            // Сохраним order_option_id во временный массив
            $old_order_option_values = array();
            foreach ($order_product_options_data as $order_product_option) {
                $old_order_option_values[$order_product_option['order_option_id']] = $order_product_option['order_option_id'];
                $this->log($order_product_option, "updateOrderProduct");
            }

            // ПОИЩЕМ ОПЦИИ В ЗАКАЗЕ
            foreach ($query_feature_value->rows as $option) {
                $order_option_id = 0;
                foreach ($order_product_options_data as $order_option) {
                    if ($option['product_option_id'] == $order_option['product_option_id'] && $option['product_option_value_id'] ==
                        $order_option['product_option_value_id'])
                    {
                        $order_option_id = $order_option['order_option_id'];
                        $found = true;
                        unset($old_order_option_values[$order_option_id]);
                    }
                }

                if (!$order_option_id) {
                    // Добавим
                    $this->query("INSERT INTO " . DB_PREFIX . "order_option SET 
						order_id = " . (int)$order_id . ", 
						order_product_id = " . (int)$order_product_id . ", 
						product_option_id = " . (int)$option['product_option_id'] . ", 
						product_option_value_id " . (int)$option['product_option_value_id'] . ", 
						name = '" . $this->db->escape($option['name']) . "', 
						value = '" . $option['value'] . "', 
						type = '" . $option['type'] . "'"
					);
                    $order_option_id = $this->db->getLastId();
                    $this->log("Добавлена опция в заказ, order_option_id = " . $order_option_id, "updateOrderProduct");
                }
            }

            // УДАЛЕНИЕ СТАРЫХ НЕИСПОЛЬЗУЕМЫХ ОПЦИЙ ИЗ ЗАКАЗА
            if (count($old_order_option_values)) {
                foreach ($old_order_option_values as $order_option_id) {
                    $this->query("DELETE FROM " . DB_PREFIX . "order_option WHERE order_option_id = " . (int)$order_option_id);
                }
            }
        } // if ($order_product_data['product_feature_id'])
        //ОПЦИИ ТОВАРА

    } // updateOrderProduct()


    /**
     * Меняет статус заказа
     */
    private function getOrderStatusLast($order_id){

        $order_status_id = 0;
        $query = $this->query("SELECT order_status_id FROM " . DB_PREFIX . "order_history 
			WHERE order_id = " . (int)$order_id . " 
			ORDER BY date_added DESC LIMIT 1"
		);
        if ($query->num_rows) {
            $this->log("<== getOrderStatusLast() return: " . $query->row['order_status_id'], "getOrderStatusLast");
            $order_status_id = $query->row['order_status_id'];
        }
        $this->log("Получен статус заказа = " . $order_status_id, "getOrderStatusLast");
        return $order_status_id;
    }


    /**
     * Если изменился статус заказа, добавляем в историю
     */
    private function changeOrderStatus($order_id, $status_name, $canceled = false) {

        if ($canceled) {
            // Устанавливаем статус отмененного заказа
            $new_order_status_id = $this->config->get('exchange1c_order_status_canceled');

        } else {

            $query = $this->query("SELECT order_status_id FROM " . DB_PREFIX . "order_status 
				WHERE language_id = " . $this->SET['lang'][0] . " 
				AND name = '" . $this->db->escape($status_name) . "'"
			);
            if ($query->num_rows) {
                $new_order_status_id = (int)$query->row['order_status_id'];
            } else {
                $this->log("Статус заказа '" . $status_name . "' не найден!");
                $this->log("ERROR 2207");
                return false;
            }
            $this->log("[i] Найден status_id=" . $new_order_status_id . " по названию '" . $status_name . "'", 2);

        }

        // получим старый статус
        $order_status_id = $this->getOrderStatusLast($order_id);
        if (!$order_status_id) {
            $this->log("ВНИМАНИЕ! У заказа еще нет ни одной записи в истории статуса заказа!");
        }

        if ($order_status_id == $new_order_status_id) {
            $this->log("Статус документа не изменился");
            return 0;
        }

        // Меняем статус если он равен начальному
        //if ((int)$this->config->get('exchange1c_order_status_export') != (int)$order_status_id) {
        //	$this->log("Статус документа не меняем так как он уже не имеет статуса указанного для выгрузки");
        //	return 0;
        //}

        // если он изменился, изменим в заказе
        $this->query("INSERT INTO " . DB_PREFIX . "order_history SET 
			order_id = " . (int)$order_id . ", 
			order_status_id = " . (int)$new_order_status_id . ", 
			date_added = '" . $this->XML_DATE . "', 
			comment = 'Change auto from trade system'"
		);

        // Обновим статус в заказе
        //$this->query("UPDATE `" . DB_PREFIX . "order` SET `order_status_id` = " . (int)$new_order_status_id . ", `date_modified` = '" . $this->XML_DATE . "' WHERE `order_id` = " . (int)$order_id);
        $this->query("UPDATE " . DB_PREFIX . "order SET 
			order_status_id = " . (int)$new_order_status_id . " 
			WHERE order_id = " . (int)$order_id
		);

        $this->log("Изменен статус документа", 2);
        return $order_status_id;

    } // changeOrderStatus()


    /**
     * Обновляет документ
     */
    private function updateDocument($doc, $order, $products) {

        $order_fields = array();

        // обновим входящий номер
        if (!empty($doc['invoice_no'])) {
            $order_fields['invoice_no'] = $doc['invoice_no'];
        }

        // проверим валюту
        if (!empty($doc['currency'])) {

            $order_fields['currency_id'] = $doc['currency']['currency_id'];
            $order_fields['currency_code'] = $doc['currency']['code'];
            $order_fields['currency_value'] = $doc['currency']['value'];
        }

        // проверим сумму
        if (!empty($doc['total'])) {
            if ($doc['total'] != $order['total']) {
                $order_fields['total'] = $doc['total'];
            }
        }

        // Временная заплатка!!!
        // Проверим ФИО
        if (isset($doc['firstname']) && isset($order['firstname'])) {
            if ($doc['firstname'] != $order['firstname']) {
                $order_fields['firstname'] = $doc['firstname'];
            }
        }

        if (isset($doc['lastname']) && isset($order['lastname'])) {
            if ($doc['lastname'] != $order['lastname']) {
                $order_fields['lastname'] = $doc['lastname'];
            }
        }

        if (isset($doc['middlename']) && isset($order['middlename'])) {
            if ($doc['middlename'] != $order['middlename']) {
                $order_fields['middlename'] = $doc['middlename'];
            }
        }

        // статус заказа
        if (!empty($doc['status'])) {

            // Заказ был завершен со статусом отмены в учетной системе
            $canceled = false;
            if (isset($doc['canceled'])) {
                if ($doc['canceled'] == 'true') {
                    $this->log("Заказ был отменен в учетной системе", 2);
                    $canceled = true;
                }
            }

            $this->changeOrderStatus($doc['order_id'], $doc['status'], $canceled);
            if ($this->session->data['error']) return false;
        }

        $update = false;

        $old_products = $products;

        // Сумма товаров, нужна для расчета стоимости доставки
        $product_total = 0;

        // проверим товары, порядок должен быть такой же как и в торговой системе
        // Если порядок будет отличаться, то товары будут заменены
        if (!empty($doc['products'])) {
            $this->log("Обработка товаров документа...");

            foreach ($doc['products'] as $key => $doc_product) {

                $this->log("Товар: " . $doc_product['name'], 2);

                $order_product_fields = array();
                $order_option_fields = array();
                $product_total += $doc_product['total'];

                if (isset($products[$key])) {
                	// проверим товар
                    $product = $products[$key];
                    $this->log($product, 2);

                    // Сравним товар
                    if ($product['product_id'] != $doc_product['product_id']) {
                        // заменим товар
                    } else {
                        $num_str = $key + 1;
                        $this->log("В строке " . $num_str . " товар не изменился");

                        // Проверим цену, количество, налоги, сумму
                        if ($product['price'] != $doc_product['price']) {
                            $this->log("Изменена цена");
                            $update = true;
                        }
                        if ($product['quantity'] != $doc_product['quantity']) {
                            $this->log("Изменено количество");
                            $update = true;
                        }
                        if ($product['tax'] != $doc_product['tax']) {
                            $this->log("Изменена ставка налога");
                            $update = true;
                        }
                        $this->log($doc_product, 2);
                        if ($update) {
                            $this->updateOrderProduct($doc['order_id'], $doc_product, $product['order_product_id']);
                            if ($this->session->data['error']) return false;
                        }
                    }

                } else {
                    // Добавить строчку
                    $this->log("Добавление товара '" . $doc_product['name'] . "' в документ");

                    $this->query("INSERT INTO " . DB_PREFIX . "order_product
						SET product_id = " . (int)$doc_product['product_id'] . ",
						order_id = " . (int)$doc['order_id'] . ",
						name = '" . $this->db->escape($doc_product['name']) . "',
						model = '" . $this->db->escape($doc_product['model']) . "',
						price = " . (float)$doc_product['price'] . ",
						quantity = " . (float)$doc_product['quantity'] . ",
						total = " . (float)$doc_product['total']
					);
                    $this->log("Товар '" . $doc_product['name'] . "' добавлен в заказ #" . $doc['order_id'], 2);
                    $order_product_id = $this->db->getLastId();

                    $update = true;
                }


            } // foreach

         } // if

        if ($doc['total'] != $order['total']) {
            $order_fields['total'] = $doc['total'];
        }

        $sql = "UPDATE " . DB_PREFIX . "order SET ";
        if ($order_fields) {
            $sql_set = "";
            foreach ($order_fields as $field => $value) {
                $sql_set .= ($sql_set ? ", " : "`") . $field . " = '" . $value . "'";
            }
            $this->log($sql_set, 2);
            $this->query($sql . $sql_set . " WHERE order_id = " . $order['order_id']);

            // Обновим сумму заказа и дату модификации
            //			$this->query("UPDATE `" . DB_PREFIX . "order` SET
            //			`total` = " . (float)$doc['total'] . ",
            //			`date_modified` = NOW()
            //			WHERE `order_id` = " . (int)$order['order_id']);
            //			$this->log("Обновлено в документе: Итого",2);

            // ИТОГИ
            // Вычислим сумму доставки
            $shipping_total = $doc['total'] - $product_total;
            $this->query("UPDATE " . DB_PREFIX . "order_total SET
				value = " . (float)$shipping_total . "
				WHERE order_id = " . (int)$order['order_id'] . " AND
				code = 'shipping'");
            $this->log("Сумма доставки = " . $shipping_total, 2);

            // Итоги по таблице товаров
            $this->query("UPDATE " . DB_PREFIX . "order_total SET
				value = " . (float)$product_total . "
				WHERE order_id = " . (int)$order['order_id'] . " AND
				code = 'sub_total'");
            $this->log("Сумма товаров = " . $product_total, 2);

            // Обновим тоталы, разницу между суммой товаров закинем в доставку
            $this->query("UPDATE " . DB_PREFIX . "order_total SET
				value = " . (float)$doc['total'] . "
				WHERE order_id = " . (int)$order['order_id'] . " AND
				code = 'total'");
            $this->log("Всего = " . $doc['total'], 2);
        }

        $this->log("Документ обновлен", 2);

        return true;

    } // updateDocument()


    /**
     * Читает их XML реквизиты документа
     */
    private function parseDocumentRequisite($xml, &$doc) {

        foreach ($xml->ЗначениеРеквизита as $requisite) {
            // обрабатываем только товары
            $name = (string )$requisite->Наименование;
            $value = (string )$requisite->Значение;
            $this->log("> Реквизит документа: " . $name . " = " . $value, "parseDocumentRequisite");
            switch ($name) {
                case 'Номер по 1С':
                    $doc['invoice_no'] = $value;
                    break;
                case 'Дата по 1С':
                    $doc['datetime'] = $value;
                    break;
                case 'Статус заказа':
                    $doc['status'] = $value;
                    break;
                case 'ПометкаУдаления':
                    $doc['DeletionMark'] = $value;
                    break;
                case 'Проведен':
                    $doc['Posted'] = $value;
                    break;
                case 'Отменен':
                    $doc['canceled'] = $value;
                    break;

                    // Оплата (в процессе реализации)
                case 'Оплачен':
                    $doc['Paid'] = $value;
                    break;
                case 'Номер оплаты по 1С':
                    $doc['NumPay'] = $value;
                    break;
                case 'Дата оплаты по 1С':
                    $doc['DataPay'] = $value;
                    break;

                    // Отгрузка (в процессе реализации)
                case 'Отгружен':
                    $doc['Shipped'] = $value;
                    break;
                case 'Номер отгрузки по 1С':
                    $doc['NumSale'] = $value;
                    break;
                case 'Дата отгрузки по 1С':
                    $doc['DateSale'] = $value;
                    break;

                    // Доставка (в процессе реализации)
                case 'Идентификатор отправления':
                    $doc['DeliveryID'] = $value;
                    break;
                case 'Комментарий доставки':
                    $doc['DeliveryComment'] = $value;
                    break;
                case 'Адрес доставки':
                    $doc['DeliveryAddress'] = $value;
                    break;
                case 'Способ доставки':
                    $doc['DeliveryMethod'] = $value;
                    break;
                case 'Стоимость доставки':
                    $doc['DeliveryAmount'] = $value;
                    break;
                case 'Ставка НДС доставки':
                    $doc['DeliveryTax'] = $value;
                    break;
                case 'Получатель':
                    $doc['DeliveryRecipient'] = $value;
                    break;
                case 'Контактный телефон':
                    $doc['DeliveryContactPhone'] = $value;
                    break;
                case 'Почта получателя':
                    $doc['DeliveryContactEmail'] = $value;
                    break;

                default:
            }
        }
        $this->log("Реквизиты документа прочитаны", 2);

    } // parseDocumentRequisite()


    /**
     * Контрагент из строки: Организация [Контакт]
     * Пример1: Фамилия Имя Отчество [Фамилия Имя Оотчество]
     * Пример2: Наименование организации [Фамилия Имя Оотчество]
     * Получает ID покупателя и адреса
     */
    private function parseCustomerStr($customer_name) {

        $this->log($customer_name, "parseCustomerStr");
        $customer_name_split = explode(" ", $customer_name);
        $this->log($customer_name_split, "parseCustomerStr");

        $customer_info = array();
        $customer_info['company'] = '';
        $customer_info['customer'] = array();

        // Определим есть ли в названии квадратные скобки, то есть есть ли организация.
        $pos = mb_stripos($customer_name, '[');
        if ($pos === false) {
            // Это физическое лицо
            foreach ($customer_name_split as $str) {
                $str = trim($str);

                // Пропускаем пустые, если между словами было больше одного пробела
                if (empty($str))
                    continue;

                // Если сайт работает на кодировке UTF-8
                $str = mb_convert_case($str, MB_CASE_TITLE, "UTF-8");

                $customer_info['customer'][] = $str;
            }
        } else {
            // Это организация
            $type = 'company';
            foreach ($customer_name_split as $str) {
                $str = trim($str);

                if (mb_substr($str, 0, 1) == '[') {
                    $type = 'customer';
                    $str = str_replace('[', '', $str);
                }

                if (mb_substr($str, -1, 1) == ']') {
                    $str = str_replace(']', '', $str);
                }

                // Пропускаем пустые, если между словами было больше одного пробела
                if (empty($str))
                    continue;

                if ($type == 'customer') {
                    // Если сайт работает на кодировке UTF-8
                    // Только для ФИО
                    $str = mb_convert_case($str, MB_CASE_TITLE, "UTF-8");
                    $customer_info[$type][] = $str;
                } else {
                    $customer_info[$type] .= ' ' . $str;
                }
            }
        }

        $this->log($customer_info, "parseCustomerStr");
        return $customer_info;

    } // parseCustomerStr()


    /**
     * Контрагент
     * Получает ID покупателя и адреса
     */
    private function parseDocumentCustomer($xml, &$doc) {

        // Читаем контрагента, определим где организация а где контактное лицо
        $this->log($xml, "parseDocumentCustomer");

        $doc['customer_id'] = 0;
        $doc['address_id'] = 0;

        $customer_guid = (string )$xml->Контрагент->Ид;

        // Определение типа покупателя: Организация или физ.лицо
        // Поиск организации будет осуществлен, если заполнено поле "ОфициальноеНаименование" и указан ИНН, иначе будет прочитано как физ.лицо
        if ($xml->Контрагент->ОфициальноеНаименование && $xml->Контрагент->ИНН) {
            $company_name = trim((string )$xml->Контрагент->ОфициальноеНаименование);
            $company_inn = trim((string )$xml->Контрагент->ИНН);
            $company_kpp = trim((string )$xml->Контрагент->КПП);

            $customer_type = (strlen($company_inn) == 12) ? 3 : 2;

            // Поиск по организации по ИНН
            $this->log("Поиск организации по ИНН: " . $company_inn, "parseDocumentCustomer");
            $query = $this->query("SELECT customer_id FROM " . DB_PREFIX . "customer 
				WHERE company_inn = '" . $this->db->escape($company_inn) . "'"
			);
            if ($query->num_rows) {
                $doc['payment_company'] = $company_name;
                $doc['shipping_company'] = $company_name;
                $doc['customer_id'] = $query->row['customer_id'];

                $query_address = $this->query("SELECT address_id FROM " . DB_PREFIX . "address 
					WHERE customer_id = '" . (int)$doc['customer_id'] . "'"
				);
                if ($query_address->num_rows) {
                    $doc['address_id'] = $query_address->row['address_id'];
                }
            }

            // Если не найдено по реквизитам, значит изменилось наименование или переименован в название организации.
            // В этом случае пропишем название организации, ИНН и КПП
            if (!$doc['customer_id']) {
                $doc['company'] = $company_name;
                $doc['company_inn'] = $company_inn;
                $doc['company_kpp'] = $company_kpp;
            }

            $this->log("В ПРОЦЕССЕ РЕАЛИЗАЦИИ", "parseDocumentCustomer");

        } else {
            if ($xml->Контрагент->ПолноеНаименование) {
                // Тогда ФИО покупателя будет сначала а в квадратных скобках ФИО получателя в таблице address
                // В квадратных скобках указывается если пользователь регистрировался на сайте.
                $customer_info = $this->parseCustomerStr(trim((string )$xml->Контрагент->ПолноеНаименование));
            } else {
                $customer_info = $this->parseCustomerStr(trim((string )$xml->Контрагент->Наименование));
            }

            // Поиск по ФИО
            $customer = $customer_info['customer'];

            $customer_fullname = implode(" ", $customer);
            $this->log($customer_fullname, "parseDocumentCustomer");
            $lastname = isset($customer[0]) ? trim($customer[0]) : '';
            $firstname = isset($customer[1]) ? trim($customer[1]) : '';
            $middlename = isset($customer[2]) ? trim($customer[2]) : '';

            // Покупатель
            if (!$doc['customer_id']) {

                $doc['firstname'] = $firstname;
                $doc['lastname'] = $lastname;
                $doc['middlename'] = $middlename;
                $this->log("Покупатель не найден в базе, возможно были изменены ФИО", "parseDocumentCustomer");

            }

            if (!$doc['customer_id']) {
                // поиск в адресах
                if (!$doc['customer_id']) {
                    $query = $this->query("SELECT address_id, customer_id FROM " . DB_PREFIX . "address 
						WHERE firstname = '" . $this->db->escape($firstname) . "' 
						AND lastname = '" . $this->db->escape($lastname) . "'"
					);
                    if ($query->num_rows) {
                        $doc['customer_id'] = $query->row['customer_id'];
                        $doc['address_id'] = $query->row['address_id'];
                    }
                }
            }

            if (!$doc['customer_id']) {

                // Поиск в покупателях
                $sql = "SELECT customer_id FROM " . DB_PREFIX . "customer 
					WHERE firstname = '" . $this->db->escape($firstname) . "' 
					AND lastname = '" . $this->db->escape($lastname) . "'";
                if ($middlename) {
                    $sql .= " AND middlename = '" . $this->db->escape($middlename) . "'";
                }
                $query = $this->query($sql);
                if ($query->num_rows) {
                    $doc['customer_id'] = $query->row['customer_id'];
                }
            } // if (!$doc['customer_id'])

        } // if ($xml->Контрагент->ОфициальноеНаименование)

        if (!$doc['customer_id'] && empty($doc['firstname']) && empty($doc['lastname'])) {
            $this->log($doc, "parseDocumentCustomer");
        }
        $this->log("Покупатель в документе прочитан", "parseDocumentCustomer");
        return true;

    } // parseDocumentCustomer()


    /**
     * Товары документа
     */
    private function parseDocumentProducts($xml, &$doc) {

        foreach ($xml->Товар as $product) {
            $guid = explode("#", (string )$xml_product->Ид);
            $this->log($guid, "parseDocumentProducts");

            if (!$guid) {
                $this->session->data['error'] = "M510";
                return false;
            }

            $data = array();

            // Сначала наименование подставляем из файла
            if ($xml_product->Наименование) {
                $data['name'] = trim((string )$xml_product->Наименование);
            } else {
                $this->session->data['error'] = "M511";
                return false;
            }

            if (isset($guid[0])) {

                $data['product_guid'] = $guid[0];

                // Доставка Ид = ORDER_DELIVERY
                if ($data['product_guid'] == 'ORDER_DELIVERY') {
                    // Доставка в процессе реализации
                    continue;
                }

                $product_info = $this->getProductByGUID($data['product_guid']);

				if ($product_info == false) {
                    $this->Log("Не найден товар на сайте '" . $data['name'] . "' по Ид " . $data['product_guid'], "parseDocumentProducts");
                    continue;
                }
            } else {
                $this->session->data['error'] = "M512";
                return false;
            }

            $data['product_id'] = $product_info['product_id'];

            // Меняем наименование на то которое в базе, потому-что в базу могли записать полное наименование, а в заказе только короткое
            if ($xml_product->Наименование) {
                $data['name'] = $product_info['name'];
            }

            if (isset($guid[1])) {
                $data['product_feature_guid'] = $guid[1];
                $data['product_feature_id'] = $this->getProductFeatureIdByGuid($data['product_feature_guid']);

                if (!$data['product_feature_id']) {
                    $this->session->data['error'] = "M513";
                    return false;
                }
            } else {
                $data['product_feature_id'] = 0;
            }

            if ($xml_product->Артикул) {
                $data['sku'] = (string )$xml_product->Артикул;
                $data['model'] = (string )$xml_product->Артикул;
            }

            $data['ratio'] = (float)$xml_product->Коэффициент;

            if ($xml_product->ЦенаЗаЕдиницу) {
                $data['price'] = (float)$xml_product->ЦенаЗаЕдиницу;
            }
            if ($xml_product->Количество) {
                $data['quantity'] = (float)$xml_product->Количество;
            }

            // Вычисление суммы налогов пока в разработке
            $data['tax'] = 0;
            $data['reward'] = 0;

            if ($xml_product->Сумма) {
                $data['total'] = (float)$xml_product->Сумма;
            }

            if (!isset($doc['products'])) {
                $doc['products'] = array();
            }
            $doc['products'][] = $data;
        }

		$this->log($doc, "parseDocumentProducts=doc");
        return true;

    } // parseDocumentProducts()


    /**
     * ******************************************* КАТЕГОРИИ *********************************************
     */


    /**
     * Обновляет иерархию категории
     */
    private function updateHierarchical($category_id, $parent_id) {

		if ($this->config->get('exchange1c_module_status') == "debug") {
			return;
		}

        // MySQL Hierarchical Data Closure Table Pattern
        $query = $this->query("SELECT * FROM " . DB_PREFIX . "category_path
			WHERE path_id = " . (int)$category_id . "
			ORDER BY level ASC");

        if ($query->rows) {
            foreach ($query->rows as $category_path) {
                // Delete the path below the current one
                $this->query("DELETE FROM " . DB_PREFIX . "category_path
					WHERE category_id = " . (int)$category_path['category_id'] . "
					AND level < " . (int)$category_path['level']);

                $path = array();

                // Get the nodes new parents
                $query = $this->query("SELECT * FROM " . DB_PREFIX . "category_path
					WHERE category_id = " . (int)$parent_id . "
					ORDER BY level ASC");

                foreach ($query->rows as $result) {
                    $path[] = $result['path_id'];
                }

                // Get whats left of the nodes current path
                $query = $this->query("SELECT * FROM " . DB_PREFIX . "category_path
					WHERE category_id = " . (int)$category_path['category_id'] . "
					ORDER BY level ASC"
				);

                foreach ($query->rows as $result) {
                    $path[] = $result['path_id'];
                }

                // Combine the paths with a new level
                $level = 0;

                foreach ($path as $path_id) {
                    $this->query("REPLACE INTO " . DB_PREFIX . "category_path
						SET
						category_id = " . (int)$category_path['category_id'] . ",
						path_id = " . (int)$path_id . ",
						level = " . $level
					);
                    $level++;
                }
            }

        } else {
        	// Delete the path below the current one
            $this->query("DELETE FROM " . DB_PREFIX . "category_path
				WHERE category_id = " . (int)$category_id
			);

            // Fix for records with no paths
            $level = 0;

            $query = $this->query("SELECT * FROM " . DB_PREFIX . "category_path
				WHERE category_id = " . (int)$parent_id . "
				ORDER BY level ASC"
			);

            foreach ($query->rows as $result) {
                $this->query("INSERT INTO " . DB_PREFIX . "category_path
					SET
					category_id = " . (int)$category_id . ",
					path_id = " . (int)$result['path_id'] . ",
					level = " . $level
				);
                $level++;
            }

            $this->query("REPLACE INTO " . DB_PREFIX . "category_path
				SET
				category_id = " . (int)$category_id . ",
				path_id = " . (int)$category_id . ",
				level = " . $level
			);
        }

        $this->log("Обновлена иерархия у категории", 2);

    } // updateHierarchical()


    /**
     * Добавляет категорию без значений SEO полей
     */
    private function addCategory($data, $desc) {
   	
		if ($this->config->get('exchange1c_module_status') == "debug") {
			return 1;
		}
		if ($this->config->get('exchange1c_category_new_no_create')) {
			$this->log("Включен запрет на создание новых категорий", 2);
			return 0;
		};

        $this->log($data, 2);
        $this->log($desc, 2);

		$data['date_modified'] = "NOW()";
		$data['status'] = 1;

		$dont_update = array("category_id");
		$set_fields_str = $this->querySetFields(DB_PREFIX."category", $data, 0, $dont_update);

        if ($set_fields_str) {
            $this->query("INSERT INTO " . DB_PREFIX . "category SET " . $set_fields_str . "");
	        $data['category_id'] = $this->db->getLastId();
        }

        if (!$data['category_id']) {
            $this->session->data['error'] = "M300";
            $this->log("При добавлении категории в таблицу category произошла ошибка, category_id = 0");
            $this->log($data);
            $this->log($desc);
            return 0;
        }

        // Подготовка запроса для записи в таблицу category_description
        foreach ($this->LANG as $lang_id) {
			$set_fields_str = $this->querySetFields(DB_PREFIX."category_description", $desc, $lang_id);
	        if ($set_fields_str) {
	            $this->query("INSERT INTO " . DB_PREFIX . "category_description 
					SET category_id = " . (int)$data['category_id'] . ", 
					language_id = " . (int)$lang_id . ", " . 
					$set_fields_str
				);
	        }
        }

        // MySQL Hierarchical Data Closure Table Pattern
        $level = 0;
        $query = $this->query("SELECT * FROM " . DB_PREFIX . "category_path
			WHERE category_id = " . $data['parent_id'] . " ORDER BY level ASC"
		);

        foreach ($query->rows as $result) {
            $this->query("INSERT INTO " . DB_PREFIX . "category_path SET
				category_id = " . $data['category_id'] . ",
				path_id = " . $result['path_id'] . ",
				level = " . $level
			);

            $level++;
        }

        $this->query("INSERT INTO " . DB_PREFIX . "category_path SET
			category_id = " . $data['category_id'] . ",
			path_id = " . $data['category_id'] . ",
			level = " . $level
		);

 		$set_fields_str = $this->querySetFields("1c_category", $data, 0);
        if ($set_fields_str) {
        	$this->query("INSERT INTO 1c_category SET " . $set_fields_str . "");
        }

        // URL ссылки
        if (isset($data['keyword'])) {
            $this->query("INSERT INTO " . DB_PREFIX . "url_alias SET
				query = 'category_id=" . $data['category_id'] . "',
				keyword = '" . $this->db->escape($data['keyword']) . "'"
			);
        }

        // Магазин
        $this->query("INSERT INTO " . DB_PREFIX . "category_to_store SET
			category_id = " . $data['category_id'] . ",
			store_id = " . $this->STORE_ID
		);

        return $data['category_id'];

    } // addCategory()


    /**
     * Получает данные о категории по Ид
     */
    private function getCategory($guid) {

    	$category = array(
    		'data'	=> array(),
    		'desc'	=> array()
		);

		if ($this->config->get('exchange1c_module_status') == "debug") {
			return $category;
		}

		$query = $this->query("SELECT * FROM 1c_category c2c
			LEFT JOIN " . DB_PREFIX . "category c
			ON (c.category_id = c2c.category_id)
			WHERE c2c.guid = '" . $this->db->escape($guid) . "'"
		);

		if (!$query->num_rows) {
			return $category;
		}
		
		if ($query->num_rows > 1) {
			$this->session->data['error'] = "M310";
			$this->log("К одному Ид привязано несколько категорий на сайте!");
			$this->log($query->rows, 2);
			return $category;
		}
		
		$category['data'] = $query->row;
		
		$desc = array();
		foreach ($this->LANG as $lang_id) {
			$query = $this->query("SELECT name, description, meta_title, meta_description, meta_keyword FROM " . DB_PREFIX . "category_description 
				WHERE category_id = " . (int)$category['data']['category_id'] . " 
				AND language_id = " . (int)$lang_id
			);
			foreach ($query->rows as $row) {
				$this->log($row, 2);
				foreach ($row as $field => $value) {
					if (!isset($category['desc'][$field])) {
						$category['desc'][$field] = array();
						$category['desc'][$field][$lang_id] = $value;
					} else {
						$category['desc'][$field	][$lang_id] = $value;
					}
				}
			}
		}
		$this->log($category, 2);
		return $category;

   	} // getCategory()


    /**
     * Обновляет категорию
     * При обновлении категории не происходит проверка на измененные поля, обновляются все поля
     */
    private function updateCategory($data, $desc, $category_info) {
    	
		$result = 0;
		if ($this->config->get('exchange1c_module_status') == "debug") {
    		return $result;
    	}

		$update_fields = $this->compareArraysData($data, $category_info['data']);
		//$update_fields['date_modified'] = "NOW()";

 		$dont_update = array("category_id");
		$set_fields_str = $this->querySetFields(DB_PREFIX."category", $update_fields, 0, $dont_update);

		if ($set_fields_str) {
            $this->query("UPDATE " . DB_PREFIX . "category 
				SET " . $set_fields_str . ", 
				date_modified = NOW()
				WHERE category_id = " . (int)$data['category_id']
			);
			$result = 1;
        }

		foreach ($this->LANG as $lang_id) {
			$update_fields = $this->compareArraysData($desc, $category_info['desc']);
			$set_fields_str = $this->querySetFields(DB_PREFIX."category_description", $update_fields, $lang_id, $dont_update);
			if ($set_fields_str) {
				$this->query("UPDATE " . DB_PREFIX . "category_description 
					SET " . $set_fields_str . " 
					WHERE category_id = " . (int)$data['category_id']
				);
				$result = 1;
			}
		}

		// Только при обновлении категории обновляем иерархию и связи
        if ($result) {

			// Нужно обновить иерархию
	        $this->log("Обновим иерархию", 2);
	        $this->updateHierarchical($data['category_id'], $data['parent_id']);
	
	        // Обновить связи
	        $this->query("UPDATE 1c_category 
				SET version = '" . $this->db->escape($data['version']) . "', 
				delete_mark = " . $data['delete_mark'] . ",
				date_modified = NOW()
				WHERE category_id = " . $data['category_id']
			);
        }

        return $result;

    } // updateCategory()


    /**
     * Читает из базы в массив товарные категории
     * Для одного языка (эксперементальная функция)
     */
    private function getPCategories() {
		$data = array();

        $query = $this->query("SELECT pcategory_id, guid, name FROM 1c_pcategory");
        foreach ($query->rows as $row) {
            $data[$row['guid']] = array(
            	'name'			=> $row['name'],
            	'pcategory_id'	=> $row['pcategory_id']
			);
        }

        return $data;

    } // getPCategories()


    /**
     * Возвращает данные по товароной категории по Ид
     */
    private function getPCategory($guid) {
    	$data = array();
    	
		// Запрос из базы
    	if (!$data) {
			$query = $this->query("SELECT * FROM 1c_pcategory WHERE guid = '" . $this->db->escape($guid) . "'");

	    	if ($query->num_rows) {
	    		$data = $query->row;
	    	}
    	}

    	return $data;

   	} // getPCategory()


    /**
     * Получает все свойства товарной категории
     */
    private function getPCProperty($pcategory_id) {
    	$data = array();
    	
		$query = $this->query("SELECT * FROM 1c_pcategory_property WHERE pcategory_id = " . (int)$pcategory_id);
    	foreach ($query->rows as $row) {
    		$data[] = $row['property_id'];
    	}
    	return $data;

    } // getPCProperty()


    /**
     * Парсит товарные категории
     * Для одного языка (эксперементальная функция)
     */
    private function parsePCategory($xml, $parent_id = 0) {
 
        $pcategories = array();

		foreach ($xml->Категория as $pcategory_xml) {
            $data = array(
                'pcategory_id' 	=> 0,
                'parent_id' 	=> $parent_id,
                'guid' 			=> (string )$pcategory_xml->Ид,
                'name' 			=> htmlspecialchars(trim((string)$pcategory_xml->Наименование)),
                'new'			=> 0
			);
			
			$this->log("Товарная категория " . $data['name']);

            if ($this->config->get('exchange1c_module_status') == "debug") {
            	$this->log($data);
            	continue;
           	}
            
			// получим имя и id существующей категории, если нет, то вернется пустой массив
			$pcategory_info = $this->getPCategory($data['guid']);
			$this->log($pcategory_info, 2);

            if ($pcategory_info) {
            	$data['pcategory_id'] = $pcategory_info['pcategory_id'];

            	if ($data['name'] != $pcategory_info['name']) {
            		// Отличается наименование
					$this->query ("UPDATE 1c_pcategory SET
						name = '" . $this->db->escape($data['name']) . "',
						date_modified = NOW()
						WHERE pcategory_id = " . (int)$data['pcategory_id']
					);
            	}
 
            } else {
            	// не найдено, добавляем
				$this->query("INSERT INTO 1c_pcategory SET 
					parent_id = " . (int)$data['parent_id'] . ",
					guid = '" . $this->db->escape($data['guid']) . "',
					name = '" . $this->db->escape($data['name']) . "',
					date_modified = NOW()" 
				);

				$data['pcategory_id'] = $this->db->getLastId();
				$data['new'] = 1;

				$this->PCATEGORY[$data['guid']] = $data['pcategory_id'];
            }

			// Свойства товарной категории
			$pc_property = array();
            if ($pcategory_xml->Свойства && $this->config->get('exchange1c_attribute_import')) {

				if (empty($this->PROPERTY)) {
					$this->PROPERTY = $this->getProperties();
				}

				// Получим все свойства товарной категории
				$pc_property_info = $this->getPCProperty($data['pcategory_id']);
				$this->log(" - Найдено свойств: " . count($pc_property_info));

                foreach ($pcategory_xml->Свойства->Ид as $property_xml) {
					$property_guid = (string)$property_xml;
					//$this->log($property_guid, 2);

					if ($this->config->get('exchange1c_module_status') == "debug") {
						$this->log(" - Свойство: ИД " . $property_guid . " (режим отладки)");
						continue;
					}

					$property_info = $this->getProperty($property_guid);

					if (!$property_info) {
						$this->session->data['error'] = "M230";
						$this->log("Не найдено свойство по Ид " . $property_guid);
						$this->log($property_info);
						return $pcategories;
					}

					$this->log(" - Свойство: " . $property_info['name'][$this->LANG_ID] . ", Ид: " . $property_guid . ", значений: " . count($property_info['values']));

					// проверим наличие связи
					// ВНИМАНИЕ! ДУБЛИРУЮТСЯ. Не находит и создает новое 
					$key = array_search($property_info['property_id'], $pc_property_info);
					if ($key) {
						// Существует
						unset($pc_property_info[$key]);
						//$this->log(" - Существует связь 'товарная категория' - 'Свойство', key=" . $key);

					} else {
						// новое свойство, добавим
						$this->query("INSERT INTO 1c_pcategory_property SET 
							pcategory_id = " . (int)$data['pcategory_id'] . ", 
							property_id = " . (int)$property_info['property_id']
						);
					}
					$pc_property[$property_info['property_id']] = $property_guid;
					//$this->log($pc_property_info, 2);

                } // foreach
                $this->log($pc_property, 2);

            } // if

			if ($pc_property) {
				$data['property'] = $pc_property;
			}
			$pcategories[$data['guid']] = $data;

            if ($pcategory_xml->Категория) {
                $pcategories_recusive = $this->parsePCategory($pcategory_xml, $data['pcategory_id']);
                $pcategories = array_merge_recursive($pcategories, $pcategories_recusive);
            }

        } // foreach
        return $pcategories;

    } // parsePCategory()


    /**
     * Парсит единицы измерений
     */
    private function parseUnit($xml) {

		$units = array();
		
		$result = array(
	       	'import'	=> 0,
        	'new'		=> 0
		);

		foreach ($xml->ЕдиницаИзмерения as $unit_xml) {
            $this->log($unit_xml, "parseUnit=unit_xml");
            $unit = array(
	            'code' 		=> trim((string)$unit_xml->Код),
	            'name' 		=> trim((string)$unit_xml->НаименованиеПолное),
	            'code_si'	=> trim((string)$unit_xml->МеждународноеСокращение)
			);

			if ($this->config->get('exchange1c_module_status') == "debug") {
				$this->log($unit, "parseUnit=unit");
				return $result;
			}

            if (!isset($this->UNIT[$unit['code']])) {
                // Добавляем
				$this->query("INSERT INTO 1c_unit SET
					code = " . $unit['code'] . ",
					name = '" . $this->db->escape($unit['name']) . "',
					name_short = '" . $this->db->escape($unit['name']) . "',
					code_si = '" . $unit['code_si'] . "'"
				);	

                $unit['unit_id'] = $this->db->getLastId();
                $this->UNIT[$unit['code']] = $unit['unit_id'];
                $result['new']++;
            
			} else {
            	// Обновляем
          		$unit['unit_id'] = $this->UNIT[$unit['code']];
                
				$unit_info = $this->getUnit($unit['code']);
				if ($this->session->data['error']) return $result;

				$diff_fields = $this->compareArraysData($unit, $unit_info);

				if ($diff_fields) {
					$set_fields_str = $this->querySetFields("1c_unit", $diff_fields);

					if ($set_fields_str) {
						$this->query("UPDATE 1c_unit SET " . $set_fields_str . " WHERE `unit_id` = " . $unit['unit_id']);
					}
				}
			}
			$this->log($unit, "parseUnit=unit");
			$units[$unit['unit_id']] = $unit;
        }

		$result['import'] = count($units);
        return $result;

    } // parseUnit()


    /**
     * Парсит единицы измерений
     */
    private function setUnit($xml) {

		$unit = array(
            'code' 		=> trim((string)$xml['Код']),
            'name' 		=> trim((string)$xml['НаименованиеПолное']),
            'code_si'	=> trim((string)$xml['МеждународноеСокращение']),
            'ratio'		=> array(),
            'result'	=> 0
		);
		
		if ($this->config->get('exchange1c_module_status') == "debug") {
			return $unit;
		}

		if (empty($unit['code'])) {
			$unit['code'] = "796";
			$unit['name'] = "Штука";
			$unit['code_si'] = "";
			$unit['ratio'] = 1;
		}
		
		if (!isset($this->UNIT[$unit['code']])) {

			// Добавляем
			$this->query("INSERT INTO 1c_unit SET
				code = " . $unit['code'] . ",
				name = '" . $this->db->escape($unit['name']) . "',
				name_short = '" . $this->db->escape($unit['name']) . "',
				code_si = '" . $unit['code_si'] . "'"
			);	

			$unit['unit_id'] = $this->db->getLastId();
			$this->UNIT[$unit['code']] = $unit['unit_id'];
			$unit['result'] = 2;
			
		} else {

			// Обновляем
			$unit['unit_id'] = $this->UNIT[$unit['code']];
				
			$unit_info = $this->getUnit($unit['code']);
			$diff_fields = $this->compareArraysData($unit, $unit_info);

			if ($diff_fields) {

				$set_fields_str = $this->querySetFields("1c_unit", $diff_fields);

				if ($set_fields_str) {
					$this->query("UPDATE 1c_unit 
						SET " . $set_fields_str . "
						WHERE unit_id = " . $unit['unit_id']
					);
				}
				$unit['result'] = 1;
			}
		}
		$this->log($unit, "setUnit=unit");
		
		return $unit;

    } // setUnit()


    /**
     * Парсит группы в классификаторе в XML на разных языках
     */
    private function parseClassifierCategories($xml, $parent_id = 0, $level = 0) {

        $sort_order = 0;
        $result = 0;

        foreach ($xml->Группа as $xml_category) {

            $sort_order++;

            $data = array(
                'category_id'	=> 0,
                'parent_id'		=> $parent_id,
                'version'		=> $xml_category->НомерВерсии ? (string )$xml_category->НомерВерсии : '',
                'guid'			=> (string)$xml_category->Ид,
                'delete_mark'	=> (string)$xml_category->ПометкаУдаления == 'true' ? 1 : 0,
                'level'			=> $level,
                'top'			=> $level < 1 ? 1 : 0,
                'sort_order'	=> 0
            );
            $desc = array(
                'name'			=> $this->getLanguageName($xml_category->Наименование)
			);
			$desc['meta_title'] = $desc['name'];

            // Возвращает два массива (data = category и desc = category_description)
			$category_info = $this->getCategory($data['guid']);
			//$this->log($category_info, 2);
            if ($this->session->data['error']) return false;

            if ($category_info['data']) {
            	$data['category_id'] 	= $category_info['data']['category_id'];
            	$data['status']			= $category_info['data']['status'];
            	$data['image']			= $category_info['data']['image'];
            	$data['column']			= $category_info['data']['column'];
            }

            // Сортировка
			if ($this->config->get('exchange1c_category_sort_order_from_1c')) {
                $data['sort_order'] = $sort_order;
				if ($xml_category->Сортировка) {
					$data['sort_order'] = (int)$xml_category->Сортировка;
				}
			}
            
            // Картинка категории (по просьбе Val)
            if ($xml_category->Картинка) {
                $data['image'] = (string)$xml_category->Картинка;
            }

            if ($data['category_id']) {
               	$data['update'] = $this->updateCategory($data, $desc, $category_info);

            } else {
				$data['category_id'] = $this->addCategory($data, $desc);
            }

            if ($xml_category->Группы) {
                $level++;
				if (!$this->parseClassifierCategories($xml_category->Группы, $data['category_id'], $level)) {
					return false;
				}
            }

        } // foreach

        return true;

    } // parseClassifierCategories()


    /**
     * Разбор документа
     */
    private function parseDocument($xml) {

        $order_guid = (string)$xml->Ид;
        $order_id = (string)$xml->Номер;

        $this->log("~ЗАГРУЖАЕТСЯ ЗАКАЗ #" . $order_id);
        //$this->log($xml, 2);

        $config_currency = $this->config->get('exchange1c_currency');
        if (empty($config_currency)) {
            $this->log("ERROR 2032");
            return false;
        }

        $doc = array(
            'order_id' => $order_id,
            'date' => (string)$xml->Дата,
            'time' => (string)$xml->Время,
            'currency' => $this->getCurrencyConfig($config_currency, (string)$xml->Валюта),
            'total' => (float)$xml->Сумма,
            'doc_type' => (string)$xml->ХозОперация,
            'date_pay' => (string)$xml->ДатаПлатежа);

        // Просроченный платеж если date_pay будет меньше текущей
        if ($doc['date_pay']) {
            $this->log("По документу просрочена оплата");
        }

        // УНФ
        if ($xml->СрокПлатежа) {
            $doc['time_payment'] = (string)$xml->СрокПлатежа;
        }

        $this->parseDocumentCustomer($xml->Контрагенты, $doc);
        if ($this->session->data['error']) return;

        // Налоги документа
        if ($xml->Налоги) {
            $this->load->model('localisation/tax_class');
            $doc['taxes'] = array();

            foreach ($xml->Налоги->Налог as $tax_xml) {
                $this->log($tax_xml, 2);
                $taxes_class = $this->model_localisation_tax_class->getTaxClasses();
                $this->log($taxes_class, 2);

                foreach ($taxes_class as $tax_class) {
                    $tax = array();
                    $tax['name'] = trim((string )$tax_xml->Наименование);
                    $tax['in_sum'] = ((string)$tax_xml->УчтеноВСумме == 'true' ? true : false);
                    $tax['sum'] = (float)$tax_xml->Сумма;
                    $this->log($tax, 2);

                    if ($tax_class['title'] == $tax['name']) {
                        $doc['taxes'][] = $tax;
                    }
                }
            }
        }

        $success = $this->parseDocumentProducts($xml->Товары, $doc);
        if ($this->session->data['error']) return;

        $this->parseDocumentRequisite($xml->ЗначенияРеквизитов, $doc);
        if ($this->session->data['error']) return;

        $this->load->model('sale/order');
        $order = $this->model_sale_order->getOrder($order_id);

        if ($order) {
            $products = $this->model_sale_order->getOrderProducts($order_id);
            $this->log("Заказ на сайте:", 2);
            $this->log($order, 2);
            $this->log("Товары заказа на сайте:", 2);
            $this->log($products, 2);

        } else {
            return "Заказ #" . $doc['order_id'] . " не найден в базе";
        }

        $this->log("Документ прочитанный из файла:", 2);
        $this->log($doc, 2);

        $this->updateDocument($doc, $order, $products);
        if ($this->session->data['error']) return;

        $this->log("[i] Прочитан документ: Заказ #" . $order_id . ", Ид '" . $order_guid .
            "'");

        return true;

    } // parseDocument()


    /**
     * Очистка лога
     * НЕИСПОЛЬЗУЕТСЯ
     */
    private function clearLog() {

        $file = DIR_LOGS . $this->config->get('config_error_filename');
        $handle = fopen($file, 'w+');
        fclose($handle);

    } // clearLog()


    /**
     * Импорт файла каталога
     */
    private function displayErrorXML($error) {

        switch ($error->level) {
            case LIBXML_ERR_WARNING:
                $return = "Warning $error->code: ";
                break;
            case LIBXML_ERR_ERROR:
                $return = "Error $error->code: ";
                break;
            case LIBXML_ERR_FATAL:
                $return = "Fatal Error $error->code: ";
                break;
        }

        $return .= trim($error->message) . "\n  Line: $error->line" . "\n  Column: $error->column";

        //if ($error->file) {
        //	$return .= "\n  File: $error->file";
        //}

        return "$return\n";

    } // displayErrorXML()


    /**
     * Импорт файла каталога (начальная загрузка товаров, предложений)
     * Вызывается из контроллера
     */
    public function importCatalog() {

        $this->log("Загрузка данных из XML");
        $this->log($this->session, "importCatalog=session");

         // Языки сайта
        $query = $this->query("SELECT * FROM " . DB_PREFIX . "language WHERE status = 1");
        foreach ($query->rows as $row) {
            $code = substr($row['code'], 0, 2);
            $this->LANG[$code] = $row['language_id'];
        }

        // Язык по-умолчанию
        $this->LANG_ID = $this->getLanguageIdDefault();
        $this->log("Язык по-умолчанию, language_id: " . $this->LANG_ID, "importCatalog");

        // Валюта по-умолчанию
        $this->CURRENCY_ID = $this->getCurrencyId();

        // Магазин по умолчанию
        $this->STORE_ID = 0;

        // Магазины в CMS
        $this->STORE = array();
        $query = $this->query("SELECT * FROM " . DB_PREFIX . "store");
        foreach ($query->rows as $row) {
            $this->STORE[$row['store_id']] = $row['name'];
        }

		// Включим пользовательскую обработку ошибок
		libxml_use_internal_errors(true);
		$this->log("Читается XML файл " . DIR_UPLOAD . $this->session->data['upload_dirname'] . $this->session->data['import_filename'], "importCatalog");

        $xml = @simplexml_load_file(DIR_UPLOAD . $this->session->data['upload_dirname'] . $this->session->data['import_filename']);
        $xml_error = libxml_get_errors();

        if ($xml_error) {
			$this->session->data['error'] = "M010";
			$this->log($xml_error, "Ошибка чтения XML, неверный формат, кодировка, либо файл обрезан, либо слеплены два файла...");
            return false;
		}

        $xml_version_support = array(
        	'2.05'	=> 0,
        	'2.07'	=> 0,
        	'2.08'	=> 1,
        	'2.09'	=> 1,
        	'2.10'	=> 0,
        	'3.10'	=> 0
		);
		
		// Файл стандарта Commerce ML
        $xml_info = $this->getInfoXML($xml);
        $this->log(array_keys($xml_info), "importCatalog");

        if (empty($xml_version_support[$xml_info['version']])) {
			$this->session->data['error'] = "M020";
 			$this->log("Версия CommerceML " . $xml_info['version'] . " не поддерживается модулем, обратитесь к разработчику", "importCatalog");
			return false;
        }
        
        // Сбросим флаг запуска следующего шага, он будет включен если это необходимо будет в процессе загрузки данных
		$this->session->data['progress'] = 0;
		
		if (!$this->config->get('exchange1c_first_load_config') && $this->config->get('exchange1c_module_status') == 'load_config') {
			$this->log("Первая загрузка настроек (first load config");
			$datetime_now = date('Y-m-d H:i:s', time());
			$this->setConfig('exchange1c_first_load_config', $datetime_now);
		}

		if (!$this->config->get('exchange1c_first_import_data') && $this->config->get('exchange1c_module_status') == 'import_data') {
			$this->log("Первая загрузка данных (first import data");
			$datetime_now = date('Y-m-d H:i:s', time());
			$this->setConfig('exchange1c_first_load_config', $datetime_now);
		}

        // Свойства в 1С -> Атрибуты opencart
        if (isset($xml_info['property'])) {
            $this->log("Загрузка свойств из 1С (классификатор)");
			if (!$this->parseProperty($xml_info['property'])) return false;
            unset($xml_info['property']);
        }

        if (isset($xml_info['price_type']) && $this->config->get('exchange1c_module_status') == 'load_config') {
            $this->log("Загрузка видов цен (классификатор)");
			if (!$this->parsePriceType($xml_info['price_type'])) return false;
            unset($xml_info['price_type']);
        }

        if (isset($xml_info['storage'])) {
            $this->log("Загрузка складов (классификатор)");
			if (!$this->parseStorage($xml_info['storage'])) return false;
			unset($xml_info['storage']);
        }

        if (isset($xml_info['pcategory'])) {
			$this->log("Загрузка товарных категорий из 1С (классификатор)");
			$this->PCATEGORY = $this->getPCategories();
			$this->parsePCategory($xml_info['pcategory'], 0);
			if ($this->session->data['error']) return false;
            unset($xml_info['pcategory']);
        }

        if (isset($xml_info['category']) && $this->config->get('exchange1c_categories_import')) {
            $this->log("Загрузка групп из 1С (классификатор)");
			$this->CATEGORY = $this->getCategories();
            if (!$this->parseClassifierCategories($xml_info['category'])) return false;
			unset($xml_info['category']);
        }

        if (isset($xml_info['unit'])) {
			$this->log("Загрузка единиц измерений (классификатор)");
			$this->UNIT = $this->getUnits();
			$this->parseUnit($xml_info['unit']);
			if ($this->session->data['error']) return false;
			unset($xml_info['unit']);
        }

        if (isset($xml_info['products'])) {
			$this->log("Загрузка каталога (товары)");
			if (empty($this->PRODUCT)) {
	            $query = $this->query("SELECT product_id, guid FROM 1c_product");
	            foreach ($query->rows as $row) {
	            	 $this->PRODUCT[$row['guid']] = $row['product_id'];
	            }
            }
            
            // Загрузим атрибуты в массив для быстрого поиска
            if (empty($this->ATTRIBUTE)) {
	            $query = $this->query("SELECT attribute_id, guid FROM 1c_property WHERE attribute_id > 0");
	            foreach ($query->rows as $row) {
	            	 $this->ATTRIBUTE[$row['guid']] = $row['attribute_id'];
	            }
            }

			$this->parseProducts($xml_info['products']);
			if ($this->session->data['error']) return false;
			unset($xml_info['products']);
        }

        if (isset($xml_info['offers'])) {
            $this->log("Загрузка предложений (остатки и цены)");
            if (empty($this->PRODUCT)) {
	            $query = $this->query("SELECT product_id, guid FROM 1c_product");
	            foreach ($query->rows as $row) {
	            	 $this->PRODUCT[$row['guid']] = $row['product_id'];
	            }
            }

			$this->parseOffers_v3($xml_info['offers']);
			if ($this->session->data['error']) return false;
            unset($xml_info['offers']);
        }

        if (isset($xml_info['documents'])) {
            $this->log("Загрузка документов (в разработке)");
            //$this->importDocuments($xml_info['documents']);
            unset($xml_info['document']);
			if ($this->session->data['error']) return false;
        }

        if ($xml_info) {
            $this->log("Не обработанные данныае XML", "importCatalog");
            $this->log(array_keys($xml_info), "importCatalog");
        }

        $this->log("Загрузка данных завершена");

        return true;

    } // importCatalog()


    /**
     */
    public function manualUpdateCatalog() {

   		$this->query("UPDATE 1c_product SET action = 1");
   		return $this->updateCatalog();

	} // manualUpdateCatalog()


    /**
     * Обновление поля цена и остаток в админке opencart
     * без проверки старого значения
     */
    public function updateCatalog($aSetting = array()) {
 
		$result = array(
         	'product_count' => 0
		);
        
		$aPriceTypeConfig = $this->getPriceConfig($aSetting);
		$this->log($aPriceTypeConfig);

		$query = $this->query("SELECT * FROM 1c_product WHERE action = 1");
		foreach ($query->rows as $aRowProduct) {

			$result['product_count']++;
			$this->log($aRowProduct, "updateCatalog=aRowProduct");
			$aData = array(
				'quantity' 	=> null,
				'price'		=> null
			);

			// Пересчитаем остаток общий по всем характеристикам
			$query_quantity = $this->query("SELECT * FROM 1c_rests WHERE product_id = " . (int)$aRowProduct['product_id']);
			foreach ($query_quantity->rows as $aRowQuantity) {
				if ($aData['quantity'] === null)
					$aData['quantity'] = $aRowQuantity['quantity'];
				else
					$aData['quantity'] += $aRowQuantity['quantity'];
			}
			
			$aDiscount = array();
			$aSpecial = array();

			$qPrice = $this->query("SELECT * FROM 1c_prices WHERE product_id = " . (int)$aRowProduct['product_id']);
			$aPricesFeature = array();
			$aPricesBase = array();

			foreach ($qPrice->rows as $aPriceRow) {
				$this->log($aPriceRow, "updateCatalog=aPriceRow");
				$found = 0;
				
				if ($aPriceRow['feature_id']) {
					$aPricesFeature[] = $aPriceRow['price'];
				}
				foreach ($aPriceTypeConfig as $aPriceConfigRow) {
					$aPriceConfigRow['delete'] = 0;
					$this->log($aPriceConfigRow, "updateCatalog=aPriceConfigRow");

					if ($aPriceConfigRow['price_config_id'] != $aPriceRow['price_config_id']) continue;

					// Базовая цена
					if ($aPriceConfigRow['purpose'] == 'B' && !$aPriceRow['feature_id']) {
						$aPricesBase[] = $aPriceRow['price'];
						if (count($aPricesBase) > 1) {
							$this->session->data['error'] = "M330";
							$this->log($qPrice->rows, "updateCatalog=qPrice->rows");
							$this->log($aPricesBase, "updateCatalog=aPricesBase");
							return $result;
						}
					}
					
					// Скидка
					if ($aPriceConfigRow['purpose'] == 'D') {
						$aPriceConfigRow['price'] = $aPriceRow['price'];
						$aPriceConfigRow['product_discount_id'] = 0;
						$aDiscount[] = $aPriceConfigRow;
					}

					// Акция
					if ($aPriceConfigRow['purpose'] == 'S') {
						$aPriceConfigRow['price'] = $aPriceRow['price'];
						$aPriceConfigRow['product_special_id'] = 0;
						$aSpecial[] = $aPriceConfigRow;
					}
				} // foreach
			} // foreach
			
			// Если будут указаны несколько базовых цен, берем самую низкую (хотя должна быть одна цена)
			if (count($aPricesBase)) {
				$aData['price'] = min($aPricesBase);
			}
			// Если же не указана базовая цена без характеристики, запишем самую низкую цену характеристики
			if (count($aPricesFeature) && !$aData['price']) {
				$aData['price'] = min($aPricesFeature);
			}

			$this->log($aData, "updateCatalog=aData");
			$this->log($aDiscount, "updateCatalog=aDiscount");
			$this->log($aSpecial, "updateCatalog=aSpecial");

			$date_start = date('Y-m-d H:i:s', time());
			$date_end = date('Y-m-d H:i:s', time()+(30*24*60*60));

			// Скидки
			$query = $this->query("SELECT * FROM " . DB_PREFIX . "product_discount WHERE product_id = " . (int)$aRowProduct['product_id']);
			$aProductDiscount = array();
			foreach ($query->rows as $row) {
				$aProductDiscount[$row['product_discount_id']] = $row;
				$iExpire = 0;
				$iFound = 0;
				$this->log($row, "updateCatalog=row");
				$timestamp = strtotime($row['date_end']);
				if ($timestamp && $timestamp < strtotime($date_start)) $iExpire = 1;

				foreach ($aDiscount as $key => $aDiscountRow) {
					if ($aDiscountRow['customer_group_id'] == $row['customer_group_id'] && $aDiscountRow['quantity'] == $row['quantity']) {
						$aDiscount[$key]['product_discount_id'] = $row['product_discount_id'];
						$iFound = 1;
						if ($iExpire) $aDiscount[$key]['delete'] = 1;
					}
				}
				if (!$iFound) {
					$this->query("DELETE FROM " . DB_PREFIX . "product_discount 
						WHERE product_discount_id = " . (int)$row['product_discount_id']
					);
				}
			}
			if ($aDiscount) {
				foreach ($aDiscount as $aDiscountRow) {
					if (!$aDiscountRow['product_discount_id']) {
						$this->query("INSERT INTO " . DB_PREFIX . "product_discount SET 
							product_id = " . (int)$aRowProduct['product_id'] . ",
							customer_group_id = " . (int)$aDiscountRow['customer_group_id'] . ",
							quantity = '" . $aDiscountRow['quantity'] . "',
							priority = " . (int)$aDiscountRow['priority'] . ",
							price = '" . $aDiscountRow['price'] . "',
							date_start = '" . $date_start . "',
							date_end = '" . $date_end . "'"
						);
						continue;
					} 
			 		if ($aDiscountRow['delete']) {
						$this->query("DELETE FROM " . DB_PREFIX . "product_discount 
							WHERE product_discount_id = " . (int)$aDiscountRow['product_discount_id']
						);
						continue;
					}
					// Если срок не вышел но изменилась цена, счетчик перезапускаем
					$this->query("UPDATE " . DB_PREFIX . "product_discount 
						SET price = '" . $aDiscountRow['price'] . "', 
						priority = " . (int)$aDiscountRow['priority'] . ",
						quantity = " . (int)$aDiscountRow['quantity'] . ",
						date_start = '" . $date_start . "',
						date_end = '" . $date_end . "'
						WHERE product_discount_id = " . (int)$aDiscountRow['product_discount_id']
					);
				}
			}
			

			// Акции
			$query = $this->query("SELECT * FROM " . DB_PREFIX . "product_special WHERE product_id = " . (int)$aRowProduct['product_id']);
			foreach ($query->rows as $row) {
				$iExpire = 0;
				$iFound = 0;
				$this->log($row, "updateCatalog=row");
				$timestamp = strtotime($row['date_end']);
				if ($timestamp && $timestamp < strtotime($date_start)) $iExpire = 1;

				foreach ($aSpecial as $key => $aSpecialRow) {
					if ($aSpecialRow['customer_group_id'] == $row['customer_group_id']) {
						$aSpecial[$key]['product_special_id'] = $row['product_special_id'];
						if ($iExpire) $aSpecial[$key]['delete'] = 1;
					}
				}
				if (!$iFound) {
					$this->query("DELETE FROM " . DB_PREFIX . "product_special 
						WHERE product_special_id = " . (int)$row['product_special_id']
					);
				}
			}
			if ($aSpecial) {
				foreach ($aSpecial as $aSpecialRow) {
					if (!$aSpecialRow['product_special_id']) {
						$this->query("INSERT INTO " . DB_PREFIX . "product_special SET 
							product_id = " . (int)$aRowProduct['product_id'] . ",
							customer_group_id = " . (int)$aSpecialRow['customer_group_id'] . ",
							priority = " . (int)$aSpecialRow['priority'] . ",
							price = '" . $aSpecialRow['price'] . "',
							date_start = '" . $date_start . "',
							date_end = '" . $date_end . "'"
						);
						continue;
					} 
			 		if ($aSpecialRow['delete']) {
						$this->query("DELETE FROM " . DB_PREFIX . "product_special 
							WHERE product_special_id = " . (int)$aSpecialRow['product_special_id']
						);
						continue;
					}
					// Если срок не вышел но изменилась цена, счетчик перезапускаем
					$this->query("UPDATE " . DB_PREFIX . "product_special 
						SET price = '" . $aSpecialRow['price'] . "', 
						priority = " . (int)$aSpecialRow['priority'] . ",
						date_start = '" . $date_start . "',
						date_end = '" . $date_end . "'
						WHERE product_special_id = " . (int)$aSpecialRow['product_special_id']
					);
				}
			}
			

			// Базовая цена
			$set_fields_str = $this->querySetFields(DB_PREFIX."product", $aData);
			if ($set_fields_str) {
				$this->query("UPDATE " . DB_PREFIX ."product SET " . $set_fields_str . " 
					WHERE product_id = " . (int)$aRowProduct['product_id']
				);
			}
			$this->query("UPDATE 1c_product SET action = 0 WHERE product_id = " . (int)$aRowProduct['product_id']);
		}
		return $result;

	} // updateCatalog()


    /**
     * Определение дополнительных полей и запись их в глобальную переменную типа массив
     */
    public function defineTableFields() {
        $result = array();

        $this->log("Поиск в базе данных дополнительных полей", "defineTableFields");

        $tables = array(
            'manufacturer' => array('noindex' => 1),
            'product_to_category' => array('main_category' => 1),
            'product_description' => array(
                'meta_h1' => '',
                'meta_title' => '',
                'meta_description' => '',
                'meta_keyword' => ''),
            'category_description' => array(
                'meta_h1' => '',
                'meta_title' => '',
                'meta_description' => '',
                'meta_keyword' => ''),
            'manufacturer_description' => array(
                'name' => '',
                'meta_h1' => '',
                'meta_title' => '',
                'meta_description' => '',
                'meta_keyword' => ''),
            'manufacturer_to_layout' => array(),
            'product' => array('noindex' => 1, 'unit_id' => 0),
            'order' => array(
                'middlename' => '',
                'shipping_middlename' => '',
                'payment_middlename' => ''),
            'order_product' => array('product_feature_id' => 0),
            'customer' => array(
                'middlename' => '',
                'company_inn' => '',
                'company_kpp' => ''),
            'cart' => array('product_feature_id' => 0),
            'attributes_value' => array(),
            'attributes_value_to_1c' => array(),
            'url_alias' => array('seomanager' => ''));

        foreach ($tables as $table => $fields) {

			$query = $this->db->query("SHOW TABLES LIKE '" . DB_PREFIX . $table . "'");
            if (!$query->num_rows)
                continue;

            $result[$table] = array();

            foreach ($fields as $field => $value) {

                $query = $this->db->query("SHOW COLUMNS FROM " . DB_PREFIX . $table . " WHERE field = '" . $field . "'");
                if (!$query->num_rows)
                    continue;

                $result[$table][$field] = $value;
            }
        }
        return $result;

    } // defineTableFields()


	public function createTables() {
		// События
		//$this->setEvents();
		$msg = array();

        $msg[] = "Создание таблицы 1c_session (сессии обмена с 1С)";
        $this->db->query("DROP TABLE IF EXISTS 1c_session");
        $this->db->query("CREATE TABLE 1c_session (
			session_id				VARCHAR(32) 	NOT NULL,
			data 					MEDIUMTEXT		NOT NULL,
			expire					DATETIME		NOT NULL,
			status					TINYINT(1)		NOT NULL,
			PRIMARY KEY (session_id)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8"
		);

        // Связь товаров с 1С

        $msg[] = "Создание таблицы  1c_product (товары 1С)";
        $this->db->query("DROP TABLE IF EXISTS 1c_product");
        $this->db->query("CREATE TABLE 1c_product (
			product_id 				INT(11) 		NOT NULL,
			guid 					VARCHAR(64) 	NOT NULL,
			version 				VARCHAR(32) 	NOT NULL,
			delete_mark 			TINYINT(1)	 	NOT NULL,
			date_modified 			DATETIME	 	NOT NULL,
			action					TINYINT(1)		NOT NULL,
			INDEX (guid),
			FOREIGN KEY (product_id) 			REFERENCES " . DB_PREFIX . "product(product_id)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8"
		);

		// Связь категорий с 1С

        $msg[] = "Создание таблицы 1c_category (категории 1С)";
        $this->db->query("DROP TABLE IF EXISTS 1c_category");
        $this->db->query("CREATE TABLE 1c_category (
			category_id 			INT(11) 		NOT NULL,
			guid 					VARCHAR(100) 	NOT NULL,
			version 				VARCHAR(32) 	NOT NULL,
			delete_mark				TINYINT(1)		NOT NULL,
			level					INT(6)			NOT NULL,
			date_modified 			DATETIME	 	NOT NULL,
			FOREIGN KEY (category_id) 		REFERENCES " . DB_PREFIX . "category(category_id),
			INDEX (guid)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8"
		);
        //}

        $msg[] = "Создание таблицы  1c_property (свойства 1С)";
        $this->db->query("DROP TABLE IF EXISTS 1c_property");
        $this->db->query("CREATE TABLE 1c_property (
			property_id 			INT(11) 		NOT NULL AUTO_INCREMENT,
			attribute_id 			INT(11) 		NOT NULL,
			manufacturer 			TINYINT(1)		NOT NULL,
			guid					VARCHAR(64) 	NOT NULL,
			type					VARCHAR(1) 		NOT NULL,
			version					VARCHAR(32) 	NOT NULL,
			date_modified 			DATETIME	 	NOT NULL,
			PRIMARY KEY (property_id),
			FOREIGN KEY (attribute_id) 		REFERENCES " . DB_PREFIX . "attribute(attribute_id)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8"
		);

        $msg[] = "Создание таблицы 1c_property_value (значения свойств 1С)";
        $this->db->query("DROP TABLE IF EXISTS 1c_property_value");
        $this->db->query("CREATE TABLE 1c_property_value (
			property_value_id 		INT(11) 		NOT NULL AUTO_INCREMENT,
			property_id 			INT(11) 		NOT NULL,
			guid					VARCHAR(100) 	NOT NULL,
			date_modified 			DATETIME	 	NOT NULL,
			PRIMARY KEY (property_value_id),
			INDEX (guid),
			FOREIGN KEY (property_id) 		REFERENCES 1c_property(property_id)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8"
		);

        $msg[] = "Создание таблицы 1c_property_value_description (Значения свойств на разных языках)";
        $this->db->query("DROP TABLE IF EXISTS 1c_property_value_description");
        $this->db->query("CREATE TABLE 1c_property_value_description (
			property_value_id 		INT(11) 		NOT NULL,
			property_id				INT(11)			NOT NULL,
			language_id				INT(11)			NOT NULL,
			name					VARCHAR(256) 	NOT NULL,
			FOREIGN KEY (property_value_id) 		REFERENCES 1c_property_value(property_value_id),
			FOREIGN KEY (property_id) 		REFERENCES 1c_property(property_id),
			FOREIGN KEY (language_id) 		REFERENCES " . DB_PREFIX . "language(language_id)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8"
		);

		$msg[] = "Создание таблицы  1c_manufacturer (Производители)";
		$this->db->query("DROP TABLE IF EXISTS 1c_manufacturer");
		$this->db->query("CREATE TABLE 1c_manufacturer (
			manufacturer_id 		INT(11) 		NOT NULL,
			guid 					VARCHAR(64) 	NOT NULL,
			property_value_id		INT(11)			NOT NULL,
			UNIQUE KEY manufacturer_link (manufacturer_id, guid),
			FOREIGN KEY (manufacturer_id) 		REFERENCES 1c_manufacturer(manufacturer_id),
			FOREIGN KEY (property_value_id) 	REFERENCES 1c_property_value(property_value_id)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8"
		);

        $msg[] = "Создание таблицы  1c_pcategory (Товарные категории)";
        $this->db->query("DROP TABLE IF EXISTS 1c_pcategory");
        $this->db->query("CREATE TABLE 1c_pcategory (
			pcategory_id 			INT(11) 		NOT NULL AUTO_INCREMENT,
			parent_id 				INT(11) 		NOT NULL,
			name	 				VARCHAR(255) 	NOT NULL,
			guid 					VARCHAR(64) 	NOT NULL,
			date_modified 			DATETIME	 	NOT NULL,
			PRIMARY KEY (pcategory_id),
			INDEX (guid)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8"
		);

        $msg[] = "Создание таблицы  1c_pcategory_property (Свойства товарных категорий)";
        $this->db->query("DROP TABLE IF EXISTS 1c_pcategory_property");
		$this->db->query("CREATE TABLE 1c_pcategory_property (
			pcategory_id			INT(11) 		NOT NULL,
			property_id 			INT(11) 		NOT NULL,
			date_modified 			DATETIME	 	NOT NULL,
			FOREIGN KEY (pcategory_id)		REFERENCES 1c_pcategory(pcategory_id),
			FOREIGN KEY (property_id) 		REFERENCES 1c_property(property_id)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8"
		);

 		// Характеристики предложений (привязана к предложению)

        $msg[] = "Создание таблицы 1c_features (Характеристики товара)";
        $this->db->query("DROP TABLE IF EXISTS 1c_features");
        $this->db->query("CREATE TABLE 1c_features (
			feature_id				INT(11) 		NOT NULL AUTO_INCREMENT,
			guid	 				VARCHAR(64)		NOT NULL,
			name	 				VARCHAR(256) 	NOT NULL,
			ean	 					VARCHAR(64) 	NOT NULL,
			version	 				VARCHAR(32) 	NOT NULL,
			date_modified 			DATE	 		NOT NULL,
			PRIMARY KEY (feature_id),
			INDEX (guid)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8"
		);

        $msg[] = "Создание таблицы 1c_offers (Предложения товаров)";
        $this->db->query("DROP TABLE IF EXISTS 1c_offers");
        $this->db->query("CREATE TABLE 1c_offers (
			offer_id				INT(11) 		NOT NULL AUTO_INCREMENT,
			feature_id				INT(11) 		NULL,
			product_id 				INT(11) 		NOT NULL,
			name	 				VARCHAR(256) 	NOT NULL,
			ean	 					VARCHAR(64) 	NOT NULL,
			version	 				VARCHAR(32) 	NOT NULL,
			date_modified 			DATE	 		NOT NULL,
			PRIMARY KEY (offer_id),
			INDEX (product_id,feature_id),
			FOREIGN KEY (feature_id)		REFERENCES 1c_feature(feature_id),
			FOREIGN KEY (product_id)		REFERENCES 1c_product(product_id)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8"
		);

		$msg[] = "Создание таблицы 1c_price_type (Типы цен)";
		$this->db->query("DROP TABLE IF EXISTS 1c_price_type");
		$this->db->query("CREATE TABLE 1c_price_type (
			price_type_id			INT(11)			NOT NULL AUTO_INCREMENT,
			currency_id				INT(3)			NOT NULL,
			guid	 				VARCHAR(64)		NOT NULL,
			name	 				VARCHAR(256) 	NOT NULL,
			PRIMARY KEY (price_type_id)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8"
		);

		$msg[] = "Создание таблицы 1c_price_type_config (Настройки типов цен на сайте)";
		$this->db->query("DROP TABLE IF EXISTS 1c_price_type_config");
		$this->db->query("CREATE TABLE  1c_price_type_config (
			price_config_id			INT(11)			NOT NULL AUTO_INCREMENT,
			price_type_id			INT(11)			NOT NULL,
			customer_group_id		INT(11)		 	NOT NULL,
			purpose					VARCHAR(1)		NOT NULL,
			quantity				INT(4)			NOT NULL,
			priority				INT(5)			NOT NULL,
			action					TINYINT(1)		NOT NULL,
			PRIMARY KEY (price_config_id),
			FOREIGN KEY (price_type_id)		REFERENCES 1c_price_type(price_type_id),
			FOREIGN KEY (customer_group_id)	REFERENCES " . DB_PREFIX . "customer_group(customer_group_id)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8"
		);

        $msg[] = "Создание таблицы 1c_prices (Цены)";
        $this->db->query("DROP TABLE IF EXISTS 1c_prices");
        $this->db->query("CREATE TABLE 1c_prices (
        	product_price_id		INT(11) 		NOT NULL AUTO_INCREMENT,
        	price_type_id			INT(11) 		NOT NULL,
        	price_config_id			INT(11)		NOT NULL,
			offer_id				INT(11) 		NOT NULL,
			feature_id				INT(11) 		NOT NULL,
			product_id 				INT(11) 		NOT NULL,
			unit_id	 				INT(3)		 	NOT NULL,
			price	 				DECIMAL(10,2) 	NOT NULL,
			PRIMARY KEY (product_price_id),
			FOREIGN KEY (price_type_id)		REFERENCES 1c_price_type(price_type_id),
			FOREIGN KEY (price_config_id)	REFERENCES 1c_price_type_config(price_config_id),
			FOREIGN KEY (offer_id)			REFERENCES 1c_offers(offer_id),
			FOREIGN KEY (feature_id)		REFERENCES 1c_features(feature_id),
			FOREIGN KEY (product_id) 		REFERENCES 1c_product(product_id),
			FOREIGN KEY (unit_id) 			REFERENCES 1c_unit(unit_id)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8"
		);

        $msg[] = "Создание таблицы 1c_unit (Единицы измерений)";
        $this->db->query("DROP TABLE IF EXISTS 1c_unit");
        $this->db->query("CREATE TABLE 1c_unit (
			unit_id					INT(3) 		NOT NULL AUTO_INCREMENT,
			code	 				INT(3)		 	NOT NULL,
			name	 				VARCHAR(32)	 	NOT NULL,
			name_short				VARCHAR(16) 	NOT NULL,
			code_si					VARCHAR(3)	 	NOT NULL,
			PRIMARY KEY (unit_id)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8"
		);

        $msg[] = "Создание таблицы 1c_storage (Склады / Места хранения)";
        $this->db->query("DROP TABLE IF EXISTS 1c_storage");
        $this->db->query("CREATE TABLE 1c_storage (
			storage_id	 			INT(8) 			NOT NULL AUTO_INCREMENT,
			store_id				INT(11)			NOT NULL,
			guid	 				VARCHAR(64) 	NOT NULL,
			name					VARCHAR(255)	NOT NULL,
			address 				VARCHAR(255)	NOT NULL,
			contact					VARCHAR(255)	NOT NULL,
			comment					VARCHAR(255)	NOT NULL,
			PRIMARY KEY (storage_id),
			FOREIGN KEY (store_id)			REFERENCES " . DB_PREFIX . "store(store_id)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8"
		);

        $msg[] = "Создание таблицы 1c_rests (Остатки)";
        $this->db->query("DROP TABLE IF EXISTS 1c_rests");
        $this->db->query("CREATE TABLE 1c_rests (
			product_rest_id			INT(11) 		NOT NULL AUTO_INCREMENT,
			storage_id				INT(11)		 	NOT NULL,
			offer_id				INT(11) 		NOT NULL,
			product_id 				INT(11) 		NOT NULL,
			quantity	 			DECIMAL(15,3) 	NOT NULL,
			PRIMARY KEY (product_rest_id),
			FOREIGN KEY (offer_id) 			REFERENCES 1c_offers(offer_id),
			FOREIGN KEY (product_id) 		REFERENCES 1c_product(product_id),
			FOREIGN KEY (storage_id) 		REFERENCES 1c_storage(storage_id)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8"
		);

        $msg[] = "Создание таблицы 1c_transaction (Оплата)";
        $this->db->query("DROP TABLE IF EXISTS 1c_transaction");
        $this->db->query("CREATE TABLE 1c_transaction (
			company_transaction_id		INT(11) 		NOT NULL AUTO_INCREMENT,
			company_id					INT(11)			NOT NULL,
			order_id					INT(11)			NOT NULL,
			method_pay_id				INT(3)			NOT NULL,
			description					VARCHAR(255)	NOT NULL,
			amount						DECIMAL(15,4)	NOT NULL,
			date_added					DATETIME		NOT NULL,
			PRIMARY KEY (company_transaction_id)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8"
		);
		
        $msg[] = "Создание таблицы 1c_order_shipping (Доставка по заказам)";
        $this->db->query("DROP TABLE IF EXISTS 1c_order_shipping");
        $this->db->query("CREATE TABLE 1c_order_shipping (
			order_shipping_id			INT(11) 		NOT NULL AUTO_INCREMENT,
			order_id					INT(11)			NOT NULL,
			method_shipping_id			INT(3)			NOT NULL,
			code						VARCHAR(64)		NOT NULL,
			description					VARCHAR(255)	NOT NULL,
			track_num					VARCHAR(64)		NOT NULL,
			date_added					DATETIME		NOT NULL,
			PRIMARY KEY (order_shipping_id)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8"
		);
	
        $msg[] = "Создание таблицы 1c_order (документ заказ из 1С)";
        $this->db->query("DROP TABLE IF EXISTS 1c_order");
        $this->db->query("CREATE TABLE 1c_order (
        	1c_order_id					INT(11)			NOT NULL AUTO_INCREMENT,
			guid						VARCHAR(32) 	NOT NULL,
			order_id					INT(11)			NOT NULL,
			operation					VARCHAR(1)		NOT NULL,
			number						VARCHAR(16)		NOT NULL,
			date_import					DATETIME		NOT NULL,
			date_export					DATETIME		NOT NULL,
			status_export				TINYINT(1)		NOT NULL,
			delete_mark					TINYINT(1)		NOT NULL,
			total						DECIMAL(10,2)	NOT NULL,
			posting_mode				TINYINT(1)		NOT NULL,
			payment						TINYINT(1)		NOT NULL,
			payment_date				DATETIME		NOT NULL,
			payment_number				VARCHAR(16)		NOT NULL,
			shipping					TINYINT(1)		NOT NULL,
			shipping_date				DATETIME		NOT NULL,
			shipping_number				VARCHAR(16)		NOT NULL,
			shipping_address			VARCHAR(255)	NOT NULL,
			shipping_name				VARCHAR(255)	NOT NULL,
			shipping_sum				DECIMAL(10,2)	NOT NULL,
			track_number				VARCHAR(32)		NOT NULL,
			contact_name				VARCHAR(255)	NOT NULL,
			contact_phone				VARCHAR(64)		NOT NULL,
			status_name					VARCHAR(128)	NOT NULL,
			type_name					VARCHAR(128)	NOT NULL,
			comment						VARCHAR(255)	NOT NULL,
			PRIMARY KEY (1c_order_id)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8"
		);

		$msg[] = "Создание таблицы 1c_order_tax (налоги документа)";
		$this->db->query("DROP TABLE IF EXISTS 1c_order_tax");
		$this->db->query("CREATE TABLE 1c_order_tax (
			1c_order_id					INT(11)			NOT NULL,
			tax_class_id				INT(11)			NOT NULL,
			tax_rate					DECIMAL(10,2)	NOT NULL,
			included_in_sum				TINYINT(1)		NOT NULL,
			sum							DECIMAL(10,2)	NOT NULL,
			FOREIGN KEY (1c_order_id) 		REFERENCES 1c_order(1c_order_id)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8"
		);

		$msg[] = "Создание таблицы 1c_order_contragent (контрагент документа)";
		$this->db->query("DROP TABLE IF EXISTS 1c_order_contragent");
		$this->db->query("CREATE TABLE 1c_order_contragent (
			1c_order_id					INT(11)			NOT NULL,
			contragent_id				INT(11)			NOT NULL,
			tax_rate					DECIMAL(10,2)	NOT NULL,
			included_in_sum				TINYINT(1)		NOT NULL,
			sum							DECIMAL(10,2)	NOT NULL,
			FOREIGN KEY (1c_order_id) 		REFERENCES 1c_order(1c_order_id)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8"
		);

		$msg[] = "Создание таблицы 1c_order_product (налоги документа)";
		$this->db->query("DROP TABLE IF EXISTS 1c_order_product");
		$this->db->query("CREATE TABLE 1c_order_product (
			1c_order_id					INT(11)			NOT NULL,
			product_guid				VARCHAR(32)		NOT NULL,
			product_id					INT(11)			NOT NULL,
			feature_guid				VARCHAR(32)		NOT NULL,
			price						DECIMAL(10,2)	NOT NULL,
			quantity					DECIMAL(10.3)	NOT NULL,
			discount					DECIMAL(10.3)	NOT NULL,
			tax_rate					DECIMAL(10,2)	NOT NULL,
			included_in_sum				TINYINT(1)			NOT NULL,
			sum							DECIMAL(10,2)	NOT NULL,
			FOREIGN KEY (1c_order_id) 		REFERENCES 1c_order(1c_order_id)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8"
		);

        $msg[] = "Создание таблицы 1c_contragent (контрагенты)";
        $this->db->query("DROP TABLE IF EXISTS 1c_contragent");
        $this->db->query("CREATE TABLE 1c_contragent (
			contragent_id				INT(11)			NOT NULL,
			company						TINYINT(1)			NOT NULL,
			guid						VARCHAR(32)		NOT NULL,
			name						VARCHAR(255)	NOT NULL,
			firstname					VARCHAR(255)	NOT NULL,
			lastname					VARCHAR(255)	NOT NULL,
			middlename					VARCHAR(255)	NOT NULL,
			address						VARCHAR(255)	NOT NULL,
			address_juridic				VARCHAR(255)	NOT NULL,
			base_contact				INT(11)			NOT NULL,
			inn							VARCHAR(12)		NOT NULL,
			kpp							VARCHAR(10)		NOT NULL,
			comment						VARCHAR(255)	NOT NULL,
			date_added					DATETIME		NOT NULL,
			date_update					DATETIME		NOT NULL,
			PRIMARY KEY (contragent_id)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8"
		);

		$msg[] = "Создание таблицы 1c_contragent_contact (контактные лица контрагента)";
		$this->db->query("DROP TABLE IF EXISTS 1c_contragent_contact");
		$this->db->query("CREATE TABLE 1c_contragent_contact (
			contact_id					INT(11)			NOT NULL AUTO_INCREMENT,
			contragent_id				INT(11)			NOT NULL,
			ref_contragent_id			INT(11)			NOT NULL,
			PRIMARY KEY (contact_id)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8"
		);

		$msg[] = "Создание таблицы 1c_bank_account (расчетные счета контрагентов)";
		$this->db->query("DROP TABLE IF EXISTS 1c_bank_account");
		$this->db->query("CREATE TABLE 1c_bank_account (
			account_number				CHAR(20)		NOT NULL,
			contragent_id				INT(11)			NOT NULL,
			bic							CHAR(9)			NOT NULL,
			bic_correspondent			CHAR(9)			NOT NULL,
			comment						VARCHAR(255)	NOT NULL,
			PRIMARY KEY (account_number),
			FOREIGN KEY (contragent_id) 		REFERENCES 1c_contragent(contragent_id),
			FOREIGN KEY (bic) 					REFERENCES 1c_bank(bic),
			FOREIGN KEY (bic_correspondent) 	REFERENCES 1c_bank(bic)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8"
		);

		$msg[] = "Создание таблицы 1c_bank";
		$this->db->query("DROP TABLE IF EXISTS 1c_bank");
		$this->db->query("CREATE TABLE 1c_bank (
			bic							CHAR(9)			NOT NULL,
			name						VARCHAR(255)	NOT NULL,
			location					VARCHAR(32)		NOT NULL,
			account_number				CHAR(20)		NOT NULL,
			address						VARCHAR(255)	NOT NULL,
			telephone					VARCHAR(255)	NOT NULL,
			comment						VARCHAR(255)	NOT NULL,
			PRIMARY KEY (bic)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8"
		);
		
		return $msg;

	} // createTables()

	
	public function dropTables() {
		$result = $this->db->query("DROP TABLE IF EXISTS
			1c_session,
			1c_product,
			1c_category,
			1c_property,
			1c_property_value,
			1c_property_value_description,
			1c_manufacturer,
			1c_pcategory,
			1c_pcategory_property,
			1c_features,
			1c_offers,
			1c_price_type,
			1c_price_type_config,
			1c_unit,
			1c_prices,
			1c_storage,
			1c_rests,
			1c_company,
			1c_transaction,
			1c_order,
			1c_order_tax,
			1c_order_shipping,
			1c_order_contragent,
			1c_order_product,
			1c_contragent,
			1c_contragent_contact,
			1c_bank_account,
			1c_bank"
		);
		return($result);

	}
}

?>