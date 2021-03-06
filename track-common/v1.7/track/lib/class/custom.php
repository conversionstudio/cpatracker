<?php

class custom {
    
    public $params = array(
        'profit'        => 'profit',
        'subid'         => 'subid',
        'status'        => 'status',
        'txt_status'    => 'txt_status',
        'date_add'      => 'date_add',
        't1'    => 'txt_param1',
        't2'    => 'txt_param2',
        't4'    => 'txt_param4',
        't7'    => 'txt_param7',
        'i1'    => 'int_param1',
        'i2'    => 'int_param2',
        'i3'    => 'int_param3'
        
    );
            
    function __construct() {
        $this->common = new common($this->params);
    }

    function process_conversion($data)
    {
        if (isset($data['get']['t']))
        {
            $this->proceed_old($data['get']);
            return;
        }
        
        if (!isset($data['get']['date_add']) || $data['get']['date_add'] == '')
        {
            $data['get']['date_add'] = date('Y-m-d H:i:s');
        }
        
        if (is_int($data['get']['date_add']))
        {
            $data['get']['date_add'] = date('Y-m-d H:i:s', $data['get']['date_add']);
        }
        
        unset($data['get']['n']);
        
        $this->common->process_conversion($data['get']);
    }

    function proceed_old($data)
    {
        // Default account currency id: 16; RUB
        $main_currency_id=16;

        $lead = '';
        $sale = '';
        if (isset($data['s']))
        {
            $arr_currencies=get_active_currencies();
            $conversion_currency_code=strtoupper($data['c']);
            if ($conversion_currency_code=='RUB'){$conversion_currency_code='RUR';}
            $conversion_currency_id=0;
            foreach ($arr_currencies as $id=>$cur)
            {
                if ($cur['code']==$conversion_currency_code)
                {
                    $conversion_currency_id=$id;
                }
            }

            // Currency is not found in active, use default currency
            if ($conversion_currency_id==0){$conversion_currency_id=$main_currency_id;}

            if ($conversion_currency_id==$main_currency_id)
            {
                $conversion_profit=$data['a'];
            }
            else
            {
                $conversion_profit=convert_currency($data['a'], $conversion_currency_id, $main_currency_id, date('Y-m-d'));
            }
            $conversion_profit_currency=$data['a'];

            $r = mysql_query('SELECT `id` FROM `tbl_clicks` WHERE `subid` = "'.mysql_real_escape_string($data['s']).'"');
            if (mysql_num_rows($r) > 0)
            {
                $f = mysql_fetch_assoc($r);
                switch ($data['t'])
                {
                    case 'lead':
                        $lead = ',`is_lead` = 1';
                    break;

                    case 'sale':
                        $sale = ', `is_sale` = 1';
                    break;
                }
                if (($sale != '' || $lead != '') && isset($data['a']))
                {
                    mysql_query("UPDATE `tbl_clicks` SET
                                    `conversion_currency_sum`='".mysql_real_escape_string($conversion_profit_currency)."',
                                    `conversion_currency_id`='".mysql_real_escape_string($conversion_currency_id)."',
                                    `conversion_price_main` = '".mysql_real_escape_string($conversion_profit)."' ".$lead.$sale.'
                                  WHERE
                                    `id` = '.$f['id']);
                }
            }

            $r = mysql_query('SELECT `id` FROM `tbl_conversions` WHERE `subid` = "'.mysql_real_escape_string($data['s']).'"');
            if (mysql_num_rows($r) > 0)
            {
                $f = mysql_fetch_assoc($r);
                if (isset($data['a'])) {
                    mysql_query("UPDATE `tbl_conversions` SET
                                    `currency_id`='".mysql_real_escape_string($conversion_currency_id)."',
                                    `profit_currency`='".mysql_real_escape_string($conversion_profit_currency)."',
                                    `profit` = '".mysql_real_escape_string($conversion_profit)."'
                                  WHERE
                                    `id` = '".mysql_real_escape_string($f['id'])."'");
                }
            }
            else
            {
                mysql_query("INSERT INTO `tbl_conversions` (`network`, `currency_id`, `profit_currency`, `profit`, `subid`, `status`, `t20`, `date_add`) "
                        . "VALUES
                        (
                            '".mysql_real_escape_string($data['n'])."',
                            '".mysql_real_escape_string($conversion_currency_id)."',
                            '".mysql_real_escape_string($conversion_profit_currency)."',
                            '".mysql_real_escape_string($conversion_profit)."',
                            '".mysql_real_escape_string($data['s'])."',
                            1,
                            '".mysql_real_escape_string($data['c'])."', NOW()
                        )");
            }
        }
    }

    function get_network_info()
    {
        $postback_links=array();
        $url = tracklink() . '/p.php?n=custom&ak='.$this->common->get_code();
        $postback_links[]=array('id'=>'main',
            'url'=>$url,
            'description'=>'Универсальная Postback ссылка. Перед использованием измените значения параметров согласно документации CPA сети.');

        return array(
            'base_url'=>$url,
            'links'=>$postback_links
        );
    }
    
    function get_pixel_link()
    {
        $protocol = isset($_SERVER["HTTPS"]) ? (($_SERVER["HTTPS"]==="on" || $_SERVER["HTTPS"]===1 || $_SERVER["SERVER_PORT"]===$pv_sslport) ? "https://" : "http://") :  (($_SERVER["SERVER_PORT"]===$pv_sslport) ? "https://" : "http://");
        $cur_url = $protocol.$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF'];
        $url = substr($cur_url, 0, strlen($cur_url)-21);
        $url .= '/track/pixel.php';
        
        $code = $this->common->get_pixelcode();
        $url .= '?ak='.$code;
        
        
        return $url;
    }
    
    function process_pixel($data_all)
    {
        $data = $data_all['get'];
        
        unset($data['n']);
        
        if (!isset($data['subid']))
        {
            $data['subid'] = date("YmdHis").'x'.sprintf ("%05d",rand(0,99999));
        }

        if (!isset($data['date_add']))
        {
            $data['date_add'] = date('Y-m-d H:i:s');
        }
        
        $data['network'] = 'pixel';
                
        $this->common->process_conversion($data);
    }
}