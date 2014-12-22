<?php
class common {
    
    
    private $params = array();
    
    
    function __construct($params = array()) {
       $this->params = $params;
    }
    
    function set_params($params) {
        if (is_array($params)) {
            $this->params = $params;
        }
    }
    
    function process_conversion($data) {
        $cnt  = count($this->params);
        $i   = 0;
        $is_lead = (isset($data['is_lead']))?1:0;
        $is_sale = (isset($data['is_sale']))?1:0;
        unset($data['is_lead']);
        unset($data['is_sale']);
        
        switch ($data['txt_param2']) {
            case 'uah':
                $data['profit'] = convert_to_usd('uah', $data['profit']);
                break;
            case 'usd':
                $data['profit'] = convert_to_usd('usd', $data['profit']);
                break;
            default:
                $data['profit'] = convert_to_usd('rub', $data['profit']);
                break;
        }
        
        if (isset($data['subid']) && $data['subid'] != '') {
        	
        	//to_log('data', $data);
        	
        	$subid  = $data['subid']; // мы скоро обнулим массив data, а subid нам ещё понадобится
        	$status = $data['status'];
        	
            //Проверяем есть ли клик с этим SibID
            $r = mysql_query('SELECT `id`, `is_sale`, `is_lead` FROM `tbl_clicks` WHERE `subid` = "'.mysql_real_escape_string($subid).'"') or die(mysql_error());
            
            if (mysql_num_rows($r) > 0) {
                $f = mysql_fetch_assoc($r);
                $click_id = $f['id'];
                if($data['profit'] > 0) {
                	$is_lead = $f['is_lead'] > 0 ? 1 : 0;
                	$is_sale = 1;
                } else {
                	$is_lead = 1;
                	$is_sale = $f['is_sale'] > 0 ? 1 : 0;
                }
                mysql_query('UPDATE `tbl_clicks` SET `is_sale` = ' . $is_sale . ', `is_lead` = '.intval($is_lead).', `conversion_price_main` = "'.mysql_real_escape_string($data['profit']).'" WHERE `id` = '.$click_id) or die(mysql_error());
            }
            
            // ----------------------------
            // Готовим данные для конверсии
            // ----------------------------
            
            $upd = array(); // Инициализируем массив для запроса на обновление
            
            // Дополнительные поля, которых нет в $params, но которые нам нужны в БД
            $additional_fields = array('date_add', 'txt_status', 'status', 'network', 'type');
            
            
            
            foreach ($data as $name => $value) {
                if (array_key_exists($name, $this->params) or in_array($name, $additional_fields)) {
                    $upd[$name] = $value;
                    unset($data[$name]);
                }
            }
            
            // Проверяем, есть ли уже конверсия с таким SubID
            $r = db_query('SELECT * FROM `tbl_conversions` WHERE `subid` = "' . mysql_real_escape_string($subid) . '" LIMIT 1') or die(mysql_error());
            if (mysql_num_rows($r) > 0) {
                $f = mysql_fetch_assoc($r);

                $upd['id'] = $conv_id = $f['id'];
                
                $q = updatesql($upd, 'tbl_conversions', 'id');
                db_query($q);
               	
               	// Чистим логи
                db_query('DELETE FROM `tbl_postback_params` WHERE `conv_id` = '.$f['id']) or die(mysql_error());
            } else {
            	$q = insertsql($upd, 'tbl_conversions');
            	db_query($q);
            	
            	$conv_id = mysql_insert_id();
            }
            
            // Нужно ли нам отменить продажу?
		    if($status == 2) {
		    	delete_sale($click_id, $conv_id, 'sale');
		    	//return false;
		    }
            
            // Пишем postback логи
            foreach ($data as $name => $value) {
            	if (strpos($name, 'pbsave_') !== false) {
                	$name = str_replace('pbsave_', '', $name);
	            	$ins = array(
	            		'conv_id' => $conv_id,
	            		'name' => $name,
	            		'value' => value,
	            	);
	            	$q = insertsql($ins, 'tbl_postback_params');
	            	db_query($q);
            	}
            }
        }
    }
    
    function get_code() {
        if (is_file(_ROOT_PATH.'/cache/.postback.key')) {
            $key = file_get_contents(_ROOT_PATH.'/cache/.postback.key');
            return $key;
        }
        else {
            $key = substr(md5(__FILE__), 3, 10);
            file_put_contents(_ROOT_PATH.'/cache/.postback.key', $key);
            return $key;
        }
    }
    
    function get_pixelcode() {
        if (is_file(_ROOT_PATH.'/cache/.pixel.key')) {
            $key = file_get_contents(_ROOT_PATH.'/cache/.pixel.key');
            return $key;
        }
        else {
            $key = substr(md5(__FILE__.'TraCKKERPIxxel'), 3, 10);
            file_put_contents(_ROOT_PATH.'/cache/.pixel.key', $key);
            return $key;
        }
    }
    
    function log($net, $post, $get) 
    {
        if (!isset($get['apikey']) || ($this->get_code() != $get['apikey'])) {
            return;
        }
        
        if (!is_dir(_ROOT_PATH.'/cache/pblogs/')) {
            mkdir(_ROOT_PATH.'/cache/pblogs');
        }
        
        $log = fopen(_ROOT_PATH.'/cache/pblogs/.'.$net.date('Y-m-d').'.txt', 'a+');
        
        if ($log) {
            fwrite($log, '['.date('Y-m-d H:i:s').'] [POST] '. var_export($post));
            fwrite($log, '['.date('Y-m-d H:i:s').'] [GET] '.  var_export($get));
            fclose($log);
        }        
    }   
}