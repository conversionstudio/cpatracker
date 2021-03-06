<?php

function prepare_filtered_report($arr_allowed_main_columns, $IN)
{
    $sql_requests=array();
    $arr_sql_data=array();
    $main_column=$IN['main_column'];

    for ($i=0; $i<count($IN['filter_by']); $i++)
    {
        $cur_filter_by=$IN['filter_by'][$i];
        $cur_filter_value=$IN['filter_value'][$i];

        switch ($cur_filter_by)
        {
            case 'offer_name':
                $arr_sql_add['join'][]='LEFT JOIN tbl_offers on tbl_offers.id = tclicks.out_id';
                $arr_sql_add['where'][]="tbl_offers."._str($cur_filter_by)."='"._str($cur_filter_value)."'";
            break;

            case 'campaign_ads':
                $arr_campaign=explode ('-', $cur_filter_value);
                $arr_sql_add['where'][]="tclicks.campaign_name='"._str($arr_campaign[0])."' and tclicks.ads_name='"._str($arr_campaign[1])."'";
            break;

            default:
                $arr_sql_add['where'][]="tclicks."._str($cur_filter_by)."='"._str($cur_filter_value)."'";
            break;
        }
    }

    if ($main_column=='popular')
    {
        $arr_report_columns = array('offer_name', 'source_name', 'campaign_name', 'campaign_ads', 'referer_domain',
            'link_name', 'country', 'region', 'city', 'user_ip', 'isp', 'user_os', 'user_platform', 'user_browser',
            'campaign_param1', 'campaign_param2', 'campaign_param3', 'campaign_param4', 'campaign_param5',
            'click_param_value1', 'click_param_value2', 'click_param_value3', 'click_param_value4',
            'click_param_value5', 'click_param_value6', 'click_param_value7', 'click_param_value8',
            'click_param_value9', 'click_param_value10', 'click_param_value11', 'click_param_value12',
            'click_param_value13', 'click_param_value14', 'click_param_value15');

        $arr_sql_add['limit'] = 'LIMIT 2';
    }
    else
    {
        $arr_report_columns=array($IN['main_column']);
        $arr_sql_add['limit']='';
    }

    foreach ($arr_report_columns as $column)
    {
        if (in_array($column, $IN['filter_by']))
        {
            // Skip filtered row from traffic structure results
            continue;
        }
        $IN['main_column']=$column;

        list ($sql, $arr_resulting_sql)=generate_main_report_sql($arr_allowed_main_columns, $IN, $arr_sql_add);

        $result=mysql_query($sql);
        $arr_data=array();
        while($row=mysql_fetch_assoc($result))
        {
            if ($main_column=='popular')
            {
                $row['c0']=$column;
            }
            $arr_data[]=$row;
        }

        if ($main_column=='popular')
        {
            if (count($arr_data)==1 && $arr_data[0]['c1']=='')
            {
                // Do not show parameters with one empty value
                continue;
            }
            else
            {
                array_splice($arr_data, 1);
            }
        }
        $arr_sql_data=array_merge ($arr_sql_data, $arr_data);
    }

    return array($arr_sql_data, $arr_resulting_sql);
}

function prepare_report($report_name, $request_parameters, $return_sql_only=false)
{
    // Default currency for this account: RUB (16)
    $main_currency_id=16;

    global $arr_currencies_list;
    if (!(is_array($arr_currencies_list) && count($arr_currencies_list)>0)){
        $arr_currencies_list=get_active_currencies();
    }

    // Set default values
    $allowed_report_in_params=array(
        'report_type'=>'clicks_count', // clicks_count, actions_count, sales_count, leads_count, actions_conversion_rate, sales_conversion_rate, leads_conversion_rate, cost, profit, epc, roi, cpl
        'range_type'=>'all',           // all, hourly, daily, monthly, weekday
        'main_column'=>'offer_name',   // link_name, category, country, ...
        'filter_conversions'=>'actions',   // actions, has_actions, no_actions
        'filter_actions'=>'actions',       // actions, sales_only, leads_only
        'filter_by'=>'',
        'filter_value'=>'',
        'sort_by'=>'clicks_count',     // main_column, clicks_count, actions_count, conversion_rate, costs, profit, roi, date_column
        'sort_order'=>'DESC',
        'currency_id'=>'16',           // RUB (16) is default currency for this account
        'timezone_offset'=>get_current_timezone_shift(),
        'date_start'=>get_current_day('-7 days'),
        'date_end'=>get_current_day(),
        'report_period'=>'custom', // today, yesterday, lastweek, lastmonth, lastquarter, custom
        'type'=>''
        );

    //  Remove empty values and get only allowed keys
    $IN=array_replace($allowed_report_in_params, array_intersect_key(array_filter($request_parameters), $allowed_report_in_params));

    // Add default report params
    $IN['report_params']=isset($request_parameters['report_params'])?$request_parameters['report_params']:null;

    // Fill report range
    report_period_to_dates($IN);

    $arr_allowed_main_columns=array('popular'=>'Значение','offer_name'=>'Оффер', 'offer_category'=>'Категория', 'os_and_version'=>'ОС',
        'campaign_ads'=>'Объявление', 'link_name'=>'Ссылка', 'date_add_day'=>'День',
        'date_add_hour'=>'Час', 'user_ip'=>'IP', 'user_agent'=>'User agent',
        'user_os'=>'ОС', 'user_os_version'=>'Версия ОС', 'user_platform'=>'Платформа',
        'user_platform_info'=>'Платформа 2', 'user_platform_info_extra'=>'Платформа 3',
        'user_browser'=>'Браузер', 'user_browser_version'=>'Версия браузера',
        'country'=>'Страна', 'state'=>'Область', 'city'=>'Город', 'region'=>'Регион',
        'isp'=>'Провайдер', 'rule_id'=>'ID ссылки', 'out_id'=>'ID оффера',
        'source_name'=>'Источник', 'campaign_name'=>'Кампания', 'ads_name'=>'Объявление',
        'referer_domain'=>'Площадка', 'search_string'=>'Ключевое слово', 'campaign_param1'=>'Параметр ссылки #1',
        'campaign_param2'=>'Параметр ссылки #2', 'campaign_param3'=>'Параметр ссылки #3',
        'campaign_param4'=>'Параметр ссылки #4', 'campaign_param5'=>'Параметр ссылки #5',
        'click_param_value1'=>'Параметр перехода #1', 'click_param_value2'=>'Параметр перехода #2',
        'click_param_value3'=>'Параметр перехода #3', 'click_param_value4'=>'Параметр перехода #4',
        'click_param_value5'=>'Параметр перехода #5', 'click_param_value6'=>'Параметр перехода #6',
        'click_param_value7'=>'Параметр перехода #7', 'click_param_value8'=>'Параметр перехода #8',
        'click_param_value9'=>'Параметр перехода #9', 'click_param_value10'=>'Параметр перехода #10',
        'click_param_value11'=>'Параметр перехода #11', 'click_param_value12'=>'Параметр перехода #12',
        'click_param_value13'=>'Параметр перехода #13', 'click_param_value14'=>'Параметр перехода #14',
        'click_param_value15'=>'Параметр перехода #15');

    // Rename click parameters if we have filtered by known traffic source
    $source_filtered=array_search ('source_name', $IN['filter_by']);
    if ($source_filtered!==false)
    {
        global $source_config;
        // [!] LP can use source and still pass utm_
        // Skip source_name=source value, it's the same as undefined
        if ($IN['filter_value'][$source_filtered]!='source')
        {
            $i=1;
            foreach($source_config[$IN['filter_value'][$source_filtered]]['params'] as $key=>$val)
            {
                $arr_allowed_main_columns['click_param_value'.$i]=$val['name'];
                $i++;
            }
        }
    }

    $arr_data=array();
    if (isset($IN['filter_by']) && $IN['filter_by']!='')
    {
        // Traffic structure report
        list($arr_data, $arr_resulting_sql)=prepare_filtered_report($arr_allowed_main_columns, $IN);
        if ($return_sql_only)
        {
            return $arr_resulting_sql;
        }
    }
    else
    {
        switch ($IN['type'])
        {
            case 'lp':
                // Get list of LP
                $sql_add=array();
                $sql_add['where'][]='tclicks.is_parent=1';
                list ($sql, $arr_resulting_sql)=generate_main_report_sql($arr_allowed_main_columns, $IN, $sql_add);

                // Get list of values for LP
                $sql_add=array();
                $sql_add['select'][]='tclicks2.out_id as lp_out_id';
                $sql_add['join'][]='LEFT JOIN tbl_clicks tclicks2 ON tclicks.parent_id=tclicks2.id';
                $sql_add['where'][]='tclicks.parent_id!=0';
                $sql_add['group'][]='tclicks2.out_id';
                $sql_add['order'][]='tclicks2.out_id DESC';

                list ($sql, $arr_resulting_sql)=generate_main_report_sql($arr_allowed_main_columns, $IN, $sql_add);
            break;

            default:
                // Main report
                list ($sql, $arr_resulting_sql)=generate_main_report_sql($arr_allowed_main_columns, $IN);
            break;
        }


        if ($return_sql_only)
        {
            return $arr_resulting_sql;
        }
        $result=mysql_query($sql);
        while($row=mysql_fetch_assoc($result))
        {
            $arr_data[]=$row;
        }

        if ($IN['currency_id']!=$main_currency_id)
        {
            // Report is not using default currency, need to get correct sales values
            $sql2='SELECT '.implode (', ', array_merge($sql_select, array('SUM(tclicks.conversion_currency_sum) as profit_currency'))).'
                   FROM
                      tbl_clicks tclicks
                      '.implode (' ', $sql_join).'
                   WHERE
                      1=1 AND
                      '.implode (' AND ', array_merge($sql_where, array('tclicks.conversion_currency_id='._str($IN['currency_id'])))).'
                   GROUP BY
                      '.implode (',', $sql_group).'
                   ORDER BY '.implode (', ', $sql_order);
            $result2=mysql_query($sql2);
            $arr_data2=array();
            while($row=mysql_fetch_assoc($result2))
            {
                $arr_data2['_'.$row['c1']]=$row;
            }
        }
    }

    $arr_report_data=array();
    $T=array();
    $iRowNum=0;
    switch ($IN['range_type'])
    {
        case 'all':
            foreach ($arr_data as $i=>$row)
            {
                // 1. Main column
                $report_column=($IN['main_column']=='popular')?$row['c0']:$IN['main_column'];
                $arr_report_data['table_rows'][$i]['raw_values']['main-column']=format_cell_value($report_column, $row['c1']);
                $arr_report_data['table_rows'][$i]['values']['main-column']['value']=format_cell_value($report_column, $row['c1']);
                $arr_report_data['table_rows'][$i]['values']['main-column']['class']='report-header link';
                $arr_report_data['table_rows'][$i]['values']['main-column']['nowrap']='nowrap';
                $arr_report_data['table_rows'][$i]['values']['main-column']['action']=array(
                    'name'=>'main_column||filter|'.$IN['main_column'], 'value'=>'popular||'.$row['c1']
                );

                // 2. Clicks count
                $arr_report_data['table_rows'][$i]['raw_values']['clicks-count']=$row['clicks_count'];
                $arr_report_data['table_rows'][$i]['values']['clicks-count']['value']=format_cell_value('clicks_count', $row['clicks_count']);
                $T['clicks_count']+=$row['clicks_count'];

                // 3. Actions count
                $arr_report_data['table_rows'][$i]['raw_values']['actions-count']=$row['actions_count'];
                $arr_report_data['table_rows'][$i]['values']['actions-count']['value']=format_cell_value('actions_count', $row['actions_count']);
                $arr_report_data['table_rows'][$i]['values']['actions-count']['class']='c-action';
                if ($row['actions_count']==0){$arr_report_data['table_rows'][$i]['values']['actions-count']['class'].=' inactive';}
                $T['actions_count']+=$row['actions_count'];

                // 4. Sales count
                $arr_report_data['table_rows'][$i]['raw_values']['sales-count']=$row['sales_count'];
                $arr_report_data['table_rows'][$i]['values']['sales-count']['value']=format_cell_value('sales_count', $row['sales_count']);
                $arr_report_data['table_rows'][$i]['values']['sales-count']['class']='c-sale';
                if ($row['sales_count']==0){$arr_report_data['table_rows'][$i]['values']['sales-count']['class'].=' inactive';}
                $T['sales_count']+=$row['sales_count'];

                // 5. Leads count
                $arr_report_data['table_rows'][$i]['raw_values']['leads-count']=$row['leads_count'];
                $arr_report_data['table_rows'][$i]['values']['leads-count']['value']=format_cell_value('leads_count', $row['leads_count']);
                $arr_report_data['table_rows'][$i]['values']['leads-count']['class']='c-lead';
                if ($row['leads_count']==0){$arr_report_data['table_rows'][$i]['values']['leads-count']['class'].=' inactive';}
                $T['leads_count']+=$row['leads_count'];

                // 6. Actions conversion rate
                $arr_report_data['table_rows'][$i]['raw_values']['actions-conversion-rate']=$row['actions_conversion_rate'];
                $arr_report_data['table_rows'][$i]['values']['actions-conversion-rate']['value']=format_cell_value('actions_conversion_rate', $row['actions_conversion_rate']);
                $arr_report_data['table_rows'][$i]['values']['actions-conversion-rate']['class']='c-action';
                if (round($row['actions_conversion_rate'], 3)==0)
                {
                    $arr_report_data['table_rows'][$i]['values']['actions-conversion-rate']['class'].=' inactive';
                }

                // 7. Sales conversion rate
                $arr_report_data['table_rows'][$i]['raw_values']['sales-conversion-rate']=$row['sales_conversion_rate'];
                $arr_report_data['table_rows'][$i]['values']['sales-conversion-rate']['value']=format_cell_value('sales_conversion_rate', $row['sales_conversion_rate']);
                $arr_report_data['table_rows'][$i]['values']['sales-conversion-rate']['value']=round($row['sales_conversion_rate'], 3).'%';
                $arr_report_data['table_rows'][$i]['values']['sales-conversion-rate']['class']='c-sale';
                if (round($row['sales_conversion_rate'], 3)==0)
                {
                    $arr_report_data['table_rows'][$i]['values']['sales-conversion-rate']['class'].=' inactive';
                }

                // 8. Leads conversion rate
                $arr_report_data['table_rows'][$i]['raw_values']['leads-conversion-rate']=$row['leads_conversion_rate'];
                $arr_report_data['table_rows'][$i]['values']['leads-conversion-rate']['value']=format_cell_value('leads_conversion_rate', $row['leads_conversion_rate']);
                $arr_report_data['table_rows'][$i]['values']['leads-conversion-rate']['class']='c-lead';
                if (round($row['leads_conversion_rate'], 3)==0)
                {
                    $arr_report_data['table_rows'][$i]['values']['leads-conversion-rate']['class'].=' inactive';
                }

                // 9. Costs
                $arr_report_data['table_rows'][$i]['raw_values']['cost']=convert_currency($row['cost'], $main_currency_id, $IN['currency_id'], $IN['date_start']);
                $arr_report_data['table_rows'][$i]['values']['cost']['value']=format_cell_value('cost', convert_currency($row['cost'], $main_currency_id, $IN['currency_id'], $IN['date_start']), $IN);
                if ($row['cost']==0){$arr_report_data['table_rows'][$i]['values']['cost']['class']='inactive';}

                // Enable inline costs update

                if ($IN['main_column']!='popular' &&
                    $IN['filter_conversions']=='actions' &&
                    $IN['filter_actions']=='actions' &&
                    $IN['type']!='lp')
                {
                    $arr_report_data['table_rows'][$i]['values']['cost']['inner-id']="_e_{$report_name}{$iRowNum}";
                    $arr_report_data['table_rows'][$i]['values']['cost']['inner-class']='editable';
                    $arr_report_data['table_rows'][$i]['values']['cost']['inner-data']['values']=array(
                        array('name'=>'clicks_count', 'value'=>$row['clicks_count']),
                        array('name'=>'row_value', 'value'=>$row['c1']),
                    );
                }

                $T['cost']+=convert_currency($row['cost'], $main_currency_id, $IN['currency_id'], $IN['date_start']);

                // 10. Profit and EPC
                if (isset($arr_data2) && isset($arr_data2['_'.$row['c1']]) && $arr_data2['_'.$row['c1']]['profit']==$row['profit'])
                {
                    // All sales are in the same currency, use accurate value
                    $v=$arr_data2['_'.$row['c1']]['profit_currency'];
                    $arr_report_data['table_rows'][$i]['raw_values']['profit']=$v;
                    $arr_report_data['table_rows'][$i]['values']['profit']['value']=format_cell_value('profit', $v, $IN);
                    if ($v==0){$arr_report_data['table_rows'][$i]['values']['profit']['class']='inactive';}
                    $T['profit']+=$v;

                    $v=($row['clicks_count']==0)?0:$arr_data2['_'.$row['c1']]['profit_currency']/$row['clicks_count'];
                    $arr_report_data['table_rows'][$i]['raw_values']['epc']=$v;
                    $arr_report_data['table_rows'][$i]['values']['epc']['value']=format_cell_value('epc', $v, $IN);
                    if ($v==0){$arr_report_data['table_rows'][$i]['values']['epc']['class']='inactive';}
                }
                else
                {
                    // Using currency rate for the beginning of the period
                    $v=($row['profit']==0)?0:convert_currency($row['profit'], $main_currency_id, $IN['currency_id'], $IN['date_start']);
                    $arr_report_data['table_rows'][$i]['raw_values']['profit']=$v;
                    $arr_report_data['table_rows'][$i]['values']['profit']['value']=format_cell_value('profit', $v, $IN);
                    if ($v==0){$arr_report_data['table_rows'][$i]['values']['profit']['class']='inactive';}
                    $T['profit']+=$v;

                    $v=($row['epc']==0)?0:convert_currency($row['epc'], $main_currency_id, $IN['currency_id'], $IN['date_start']);
                    $arr_report_data['table_rows'][$i]['raw_values']['epc']=$v;
                    $arr_report_data['table_rows'][$i]['values']['epc']['value']=format_cell_value('epc', $v, $IN);
                    if ($v==0){$arr_report_data['table_rows'][$i]['values']['epc']['class']='inactive';}
                }

                $arr_report_data['table_rows'][$i]['values']['profit']['class'].=' c-action c-sale';
                $arr_report_data['table_rows'][$i]['values']['epc']['class'].=' c-sale';

                // 11. ROI
                $arr_report_data['table_rows'][$i]['raw_values']['roi']=$row['roi'];
                $arr_report_data['table_rows'][$i]['values']['roi']['value']=format_cell_value('roi', $row['roi']);
                $arr_report_data['table_rows'][$i]['values']['roi']['class']='c-action c-sale';
                if ($row['roi']==0){$arr_report_data['table_rows'][$i]['values']['roi']['class'].=' inactive';}
                if ($row['roi']<0){$arr_report_data['table_rows'][$i]['values']['roi']['class'].=' negative';}

                // 12. CPL
                $v=convert_currency($row['cpl'], $main_currency_id, $IN['currency_id'], $IN['date_start']);
                $arr_report_data['table_rows'][$i]['raw_values']['cpl']=$v+0;
                $arr_report_data['table_rows'][$i]['values']['cpl']['value']=format_cell_value('cpl', $v, $IN);
                $arr_report_data['table_rows'][$i]['values']['cpl']['class']='c-lead';
                if ($v==0){$arr_report_data['table_rows'][$i]['values']['cpl']['class'].=' inactive';}

                // 13. Return to numeric keys
                $arr_report_data['table_rows'][$i]['values']=array_values($arr_report_data['table_rows'][$i]['values']);

                // 14. Change columns for traffic structure report
                if ($IN['main_column']=='popular')
                {
                    $arr_report_data['table_rows'][$i]['values'][0]['action']=array(
                        'name'=>'main_column||filter|'.$row['c0'],
                        'value'=>'popular||'.$row['c1']
                    );

                    $arr_report_data['table_rows'][$i]['values'] = array_merge(array(
                        array('value' => $arr_allowed_main_columns[$row['c0']],
                        'action'=>array('name'=>'main_column', 'value'=>$row['c0']), 'class'=>'link')),
                        $arr_report_data['table_rows'][$i]['values']);
                }
                $iRowNum++;
            }

            // Fill totals row

            // 1. Clicks count
            $T['clicks_count']=isset($T['clicks_count'])?$T['clicks_count']:0;
            $T['clicks_count_formatted']=format_cell_value('clicks_count', $T['clicks_count']);

            // 2. Actions count
            $T['actions_count']=isset($T['actions_count'])?$T['actions_count']:0;
            $T['actions_count_formatted']=format_cell_value('actions_count', $T['actions_count']);

            // 3. Sales count
            $T['sales_count']=isset($T['sales_count'])?$T['sales_count']:0;
            $T['sales_count_formatted']=format_cell_value('sales_count', $T['sales_count']);

            // 4. Leads count
            $T['leads_count']=isset($T['leads_count'])?$T['leads_count']:0;
            $T['leads_count_formatted']=$T['leads_count'];

            // 5. Cost
            $T['cost']=isset($T['cost'])?$T['cost']:0;
            $T['cost_formatted']=format_cell_value('cost', $T['cost'], $IN);

            // 6. Profit
            $T['profit']=isset($T['profit'])?$T['profit']:0;
            $T['profit_formatted']=format_cell_value('profit', $T['profit'], $IN);

            // 7. Actions conversion
            if ($T['clicks_count']>0)
            {
                $T['actions_conversion']=$T['actions_count']/$T['clicks_count']*100;
            }
            else
            {
                $T['actions_conversion']=0;
            }
            $T['actions_conversion_formatted']=format_cell_value('actions_conversion_rate', $T['actions_conversion']);

            // 8. Sales conversion
            if ($T['clicks_count']>0)
            {
                $T['sales_conversion']=$T['sales_count']/$T['clicks_count']*100;
            }
            else
            {
                $T['sales_conversion']=0;
            }
            $T['sales_conversion_formatted']=format_cell_value('sales_conversion_rate', $T['sales_conversion']);

            // 9. Leads conversion
            if ($T['clicks_count']>0)
            {
                $T['leads_conversion']=$T['leads_count']/$T['clicks_count']*100;
            }
            else
            {
                $T['leads_conversion']=0;
            }
            $T['leads_conversion_formatted']=format_cell_value('leads_conversion_rate', $T['leads_conversion']);

            // 10. EPC
            if ($T['clicks_count']>0)
            {
                $T['epc']=$T['profit']/$T['clicks_count'];
            }
            else
            {
                $T['epc']=0;
            }
            $T['epc_formatted']=format_cell_value('epc', $T['epc'], $IN);

            // 11. ROI
            if ($T['cost']>0)
            {
                $T['roi']=($T['profit']-$T['cost'])/$T['cost']*100;
            }
            else
            {
                $T['roi']=0;
            }
            $T['roi_formatted']=format_cell_value('roi', $T['roi']);

            // 12. CPL
            if ($T['leads_count']>0)
            {
                $T['cpl']=$T['cost']/$T['leads_count'];
            }
            else
            {
                $T['cpl']=0;
            }
            $T['cpl_formatted']=format_cell_value('cpl', $T['cpl'], $IN);

            // 13. Fill totals RAW values
            $arr_report_data['table-total']['raw_values']=array(
                'main_column'=>'Итого',
                'clicks_count'=>$T['clicks_count'],
                'actions_count'=>$T['actions_count'],
                'sales_count'=>$T['sales_count'],
                'leads_count'=>$T['leads_count'],
                'actions_conversion'=>$T['actions_conversion'],
                'sales_conversion'=>$T['sales_conversion'],
                'leads_conversion'=>$T['leads_conversion'],
                'cost'=>$T['cost'],
                'profit'=>$T['profit'],
                'epc'=>$T['epc'],
                'roi'=>$T['roi'],
                'cpl'=>$T['cpl']
            );

            // 14. Fill report totals row values
            $arr_report_data['table-total']['values']=array(
                array('class'=>'', 'value'=>'Итого'),
                array('class'=>'', 'value'=>$T['clicks_count_formatted']),
                array('class'=>'c-action', 'value'=>$T['actions_count_formatted']),
                array('class'=>'c-sale', 'value'=>$T['sales_count_formatted']),
                array('class'=>'c-lead', 'value'=>$T['leads_count_formatted']),
                array('class'=>'c-action', 'value'=>$T['actions_conversion_formatted']),
                array('class'=>'c-sale', 'value'=>$T['sales_conversion_formatted']),
                array('class'=>'c-lead', 'value'=>$T['leads_conversion_formatted']),
                array('class'=>'', 'value'=>$T['cost_formatted']),
                array('class'=>'c-action c-sale', 'value'=>$T['profit_formatted']),
                array('class'=>'c-sale', 'value'=>$T['epc_formatted']),
                array('class'=>'c-action c-sale', 'value'=>$T['roi_formatted']),
                array('class'=>'c-lead', 'value'=>$T['cpl_formatted'])
            );
        break;

        case 'hourly': case 'daily': case 'monthly': case 'weekday':
            $arr_column_values['hourly']=getHoursBetween(0,23);
            $arr_column_values['daily']=getDatesBetween($IN['date_start'], $IN['date_end']);
            $arr_column_values['monthly']=getMonthsBetween($IN['date_start'], $IN['date_end']);
            $arr_column_values['weekday']=array('1'=>'Пн', '2'=>'Вт', '3'=>'Ср', '4'=>'Чт', '5'=>'Пт', '6'=>'Сб', '0'=>'Вс');

            $columns=array();
            foreach ($arr_data as $row)
            {
                $main_column_value=($row['c1']!='')?$row['c1']:'{empty}';
                $columns[$main_column_value][$row['column_name']]=$row;
            }

            $rowNumber=0;
            foreach ($columns as $key=>$value)
            {
                $column_index=0;
                // First column
                $arr_report_data['table_rows'][$rowNumber]['values'][$column_index]=array(
                    'value'=>format_cell_value($IN['main_column'], $key),
                    'class'=>'report-header',
                    'nowrap'=>'nowrap'
                );

                $arr_column_keys=array_keys($arr_column_values[$IN['range_type']]);
                foreach ($arr_column_keys as $i)
                {
                    switch ($IN['report_type'])
                    {
                        case 'stats_flow':
                            if (isset($columns[$key][$i]['actions_count']) && $columns[$key][$i]['actions_count']>0)
                            {
                                $curCellValue=$columns[$key][$i]['clicks_count'].':'.$columns[$key][$i]['actions_count'];
                                $class='bold';
                            }
                            else
                            {
                                $curCellValue=isset($columns[$key][$i]['clicks_count'])?$columns[$key][$i]['clicks_count']:null;
                                $class='';
                            }
                            $link="?date={$IN['date_start']}&filter_by=hour&filter_value[]={$i}&filter_value[]={$key}";
                        break;

                        case 'clicks_count':
                            $curCellValue=isset($columns[$key][$i]['clicks_count'])?format_cell_value('clicks_count', $columns[$key][$i]['clicks_count']):0;
                            if ($curCellValue==0){$class='inactive';}else{$class='';}
                        break;

                        case 'actions_count':
                            $curCellValue=(isset($columns[$key][$i]['actions_count']) && $columns[$key][$i]['actions_count']>0)?format_cell_value('actions_count', $columns[$key][$i]['actions_count']):0;
                            if ($curCellValue==0){$class='inactive';}else{$class='';}
                        break;

                        case 'sales_count':
                            $curCellValue=(isset($columns[$key][$i]['sales_count']) && $columns[$key][$i]['sales_count']>0)?format_cell_value('sales_count', $columns[$key][$i]['sales_count']):0;
                            if ($curCellValue==0){$class='inactive';}else{$class='';}
                        break;

                        case 'leads_count':
                            $curCellValue=(isset($columns[$key][$i]['leads_count']) && $columns[$key][$i]['leads_count']>0)?format_cell_value('leads_count', $columns[$key][$i]['leads_count']):0;
                            if ($curCellValue==0){$class='inactive';}else{$class='';}
                        break;

                        case 'actions_conversion_rate':
                            $curCellValue=format_cell_value('actions_conversion_rate', $columns[$key][$i]['actions_conversion_rate']);
                            if ($curCellValue==0){$class='inactive';}else{$class='';}
                        break;

                        case 'sales_conversion_rate':
                            $curCellValue=(isset($columns[$key][$i]['sales_conversion_rate']) && $columns[$key][$i]['sales_conversion_rate']>0)?format_cell_value('sales_conversion_rate', $columns[$key][$i]['sales_conversion_rate']):null;
                            $class='';
                        break;

                        case 'leads_conversion_rate':
                            $curCellValue=(isset($columns[$key][$i]['leads_conversion_rate']) && $columns[$key][$i]['leads_conversion_rate']>0)?format_cell_value('leads_conversion_rate', $columns[$key][$i]['leads_conversion_rate']):null;
                            $class='';
                        break;

                        case 'cost':
                            $v=convert_currency($columns[$key][$i]['cost'], $main_currency_id, $IN['currency_id'], $IN['date_start']);
                            $curCellValue=(isset($columns[$key][$i]['cost']) && $columns[$key][$i]['cost']>0)?format_cell_value('cost', $v, $IN):null;
                            $class='';
                        break;

                        case 'profit':
                            $v=convert_currency($columns[$key][$i]['profit'], $main_currency_id, $IN['currency_id'], $IN['date_start']);
                            $curCellValue=(isset($columns[$key][$i]['profit']) && ($columns[$key][$i]['profit']!=0))?format_cell_value('profit', $v, $IN):null;
                            $class='';
                        break;

                        case 'roi':
                            $curCellValue=(isset($columns[$key][$i]['roi']))?format_cell_value('roi', $columns[$key][$i]['roi']):null;
                            $class='';
                        break;

                        case 'epc':
                            $v=convert_currency($columns[$key][$i]['epc'], $main_currency_id, $IN['currency_id'], $IN['date_start']);
                            $curCellValue=(isset($columns[$key][$i]['epc']) && $columns[$key][$i]['epc']>0)?format_cell_value('epc', $v, $IN):null;
                            $class='';
                        break;

                        case 'cpl':
                            $v=convert_currency($columns[$key][$i]['cpl'], $main_currency_id, $IN['currency_id'], $IN['date_start']);
                            $curCellValue=(isset($columns[$key][$i]['cpl']) && $columns[$key][$i]['cpl']>0)?format_cell_value('cpl', $v, $IN):null;
                            $class='';
                        break;
                    }

                    $T[$column_index]['clicks_count']+=$columns[$key][$i]['clicks_count'];
                    $T[$column_index]['actions_count']+=$columns[$key][$i]['actions_count'];
                    $T[$column_index]['sales_count']+=$columns[$key][$i]['sales_count'];
                    $T[$column_index]['leads_count']+=$columns[$key][$i]['leads_count'];
                    $T[$column_index]['cost']+=convert_currency($columns[$key][$i]['cost'], $main_currency_id, $IN['currency_id'], $IN['date_start']);
                    $T[$column_index]['profit']+=convert_currency($columns[$key][$i]['profit'], $main_currency_id, $IN['currency_id'], $IN['date_start']);

                    $arr_report_data['table_rows'][$rowNumber]['values'][++$column_index]=array(
                        'value'=>$curCellValue,
                        'link'=>$link,
                        'class'=>$class
                    );
                }
                $rowNumber++;
            }

        // Fill totals row values
        $arr_report_data['table-total']['values'][]=array('class'=>'', 'value'=>'Итого');
        foreach ($T as $cur)
        {
            switch ($IN['report_type'])
            {
                case 'clicks_count':
                    $v=array('value'=>format_cell_value('clicks_count', $cur['clicks_count']));
                break;

                case 'actions_count':
                    $v=array('value'=>format_cell_value('actions_count', $cur['actions_count']));
                break;

                case 'sales_count':
                    $v=array('value'=>format_cell_value('sales_count', $cur['sales_count']));
                break;

                case 'leads_count':
                    $v=array('value'=>format_cell_value('leads_count', $cur['leads_count']));
                break;

                case 'actions_conversion_rate':
                    if ($cur['clicks_count']>0)
                    {
                        $v=array('value'=>format_cell_value('actions_conversion_rate', $cur['actions_count']/$cur['clicks_count']*100));
                    }
                    else
                    {
                        $v=array('value'=>format_cell_value('actions_conversion_rate', 0));
                    }
                break;

                case 'sales_conversion_rate':
                    if ($cur['clicks_count']>0)
                    {
                        $v=array('value'=>format_cell_value('sales_conversion_rate', $cur['sales_count']/$cur['clicks_count']*100));
                    }
                    else
                    {
                        $v=array('value'=>format_cell_value('sales_conversion_rate', 0));
                    }
                break;

                case 'leads_conversion_rate':
                    if ($cur['clicks_count']>0)
                    {
                        $v=array('value'=>format_cell_value('leads_conversion_rate', $cur['leads_count']/$cur['clicks_count']*100));
                    }
                    else
                    {
                        $v=array('value'=>format_cell_value('leads_conversion_rate', 0));
                    }
                break;

                case 'cost':
                    $v=array('value'=>format_cell_value('cost', $cur['cost'], $IN));
                break;

                case 'profit':
                    $v=array('value'=>format_cell_value('profit', $cur['profit'], $IN));
                break;

                case 'roi':
                    if ($cur['cost']!=0)
                    {
                        $v=array('value'=>format_cell_value('roi', ($cur['profit']-$cur['cost'])/$cur['cost']*100));
                    }
                    else
                    {
                        $v=array('value'=>format_cell_value('roi', 0));
                    }
                break;

                case 'epc':
                    if ($cur['clicks_count']!=0)
                    {
                        $v=array('value'=>format_cell_value('epc', $cur['profit']/$cur['clicks_count'], $IN));
                    }
                    else
                    {
                        $v=array('value'=>format_cell_value('epc', 0, $IN));
                    }
                break;

                case 'cpl':
                    if ($cur['leads_count']!=0)
                    {
                        $v=array('value'=>format_cell_value('cpl', $cur['cost']/$cur['leads_count'], $IN));
                    }
                    else
                    {
                        $v=array('value'=>format_cell_value('cpl', 0, $IN));
                    }
                break;
            }
            $arr_report_data['table-total']['values'][]=$v;
        }

        $arr_filter_column_buttons=array(
            'Переходы|clicks_count|actions,sales,leads', 'Действия|actions_count|actions', 'Продажи|sales_count|sales',
            'Лиды|leads_count|leads', 'Конверсия|actions_conversion_rate|actions', 'Конверсия|sales_conversion_rate|sales',
            'Конверсия|leads_conversion_rate|leads', 'Затраты|cost|actions,sales,leads', 'Прибыль|profit|actions,sales',
            'EPC|epc|actions,sales', 'CPL|cpl|leads', 'ROI|roi|actions,sales');

        foreach ($arr_filter_column_buttons as $cur)
        {
            list ($caption, $value, $filter_actions)=explode ('|', $cur);
            $arr_filter_actions=explode (',', $filter_actions);
            $active=($IN['report_type']==$value)?'active':'';
            if (in_array($IN['filter_actions'], $arr_filter_actions)){
                $arr_report_data['report-toolbar-column-type']['values'][]=array(
                    'caption'=>$caption, 'value'=>$value, 'active'=>$active
                );
            }
        }
        break;
    }

    // Remove total row for traffic structure report
    if ($IN['main_column']=='popular')
    {
        unset($arr_report_data['table-total']);
    }

    // Fill report params, hidden fields
    foreach ($IN as $key=>$value)
    {
        // Fill default report params
        if ($key=='report_params')
        {
            foreach ($IN['report_params'] as $param_key=>$param_value)
            {
                $arr_report_data['report_params'][]=array('name'=>$param_key, 'value'=>$param_value);
            }
            continue;
        }

        if ($allowed_report_in_params[$key]==$value)
        {
            // Skip parameters set to default values
            continue;
        }

        if ($IN['report_period']!='custom' && (($key=='date_start') || ($key=='date_end')))
        {
            // Report period is set, no need to send date_start and date_end with next request
            continue;
        }

        if (is_array($value))
        {
            foreach ($value as $cur)
            {
                $arr_report_data['report_params'][]=array('name'=>$key.'[]', 'value'=>$cur);
            }
        }
        else
        {
            $arr_report_data['report_params'][]=array('name'=>$key, 'value'=>$value);
        }
    }

    // Fill report header values
    $arr_report_data['report_name']=$report_name;
    $arr_report_data['date_from']=date('d.m.Y', strtotime($IN['date_start']));
    $arr_report_data['date_to']=date('d.m.Y', strtotime($IN['date_end']));

    // Fill report incoming parameters ($IN)
    $i=0;
    foreach ($IN as $key=>$value)
    {
        if (is_array($value))
        {
            $arr_report_data['IN']['values'][$i]=array('name'=>$key, 'value'=>implode ('||', $value));
        }
        else
        {
            $arr_report_data['IN']['values'][$i]=array('name'=>$key, 'value'=>$value);
        }
        $i++;
    }

    $arr_report_captions=array('default'=>'Отчет', 'offer_name'=>'Офферы', 'source_name'=>'Источники', 'campaign_name'=>'Кампании',
        'campaign_ads'=>'Объявления', 'referer_domain'=>'Площадки', 'link_name'=>'Ссылки', 'country'=>'Страны',
        'region'=>'Регионы', 'city'=>'Города', 'user_ip'=>'IP адреса', 'isp'=>'Провайдеры',
        'user_os'=>'Операционные системы', 'user_platform'=>'Платформы', 'user_browser'=>'Браузеры'
    );

    $arr_report_data['report_caption']=(isset($arr_report_captions[$IN['main_column']]))?$arr_report_captions[$IN['main_column']]:$arr_report_captions['default'];

    // Fill report breadcrumbs values
    if (isset($IN['filter_by']) && $IN['filter_by']!='')
    {
        $arr_breadcrumbs_captions=array_merge ($arr_allowed_main_columns, array('popular'=>'Популярные'));

        for ($i=0; $i<count($IN['filter_by']); $i++)
        {
            if (isset($arr_breadcrumbs_captions[$IN['filter_by'][$i]]))
            {
                $name=$arr_breadcrumbs_captions[$IN['filter_by'][$i]];
                $caption=format_cell_value($IN['filter_by'][$i], $IN['filter_value'][$i]);

                $arr_report_data['breadcrumbs']['values'][]=array
                (
                    'name'=>$name,
                    'caption'=>$caption,
                    'name_action'=>array('name'=>'main_column||force_filter|'.implode ('|', array_slice($IN['filter_by'], 0, $i)), 'value'=>$IN['filter_by'][$i].'||'.implode ('||', array_slice($IN['filter_value'], 0, $i))),
                    'value_action'=>array('name'=>'main_column||force_filter|'.implode ('|', array_slice($IN['filter_by'], 0, $i+1)), 'value'=>'popular||'.implode ('||', array_slice($IN['filter_value'], 0, $i+1))),
                );
            }
        }
        $arr_report_data['breadcrumbs']['selected_caption']=$arr_breadcrumbs_captions[$IN['main_column']];
    }

    // Fill report table toolbar values
    $arr_filter_conversions_buttons=array('Все переходы|actions', 'Только действия|has_actions', 'Без конверсий|no_actions');
    foreach ($arr_filter_conversions_buttons as $cur)
    {
        list ($caption, $value)=explode ('|', $cur);

        $active=($IN['filter_conversions']==$value)?'active':'';

        $arr_report_data['report-toolbar-filter-conversions'][]=array(
            'action'=>'filter_conversions', 'caption'=>$caption, 'value'=>$value, 'active'=>$active
        );
    }

    if (in_array($IN['range_type'], array('hourly', 'daily', 'monthly', 'weekday')))
    {
        $arr_report_data['report-toolbar-filter-conversions']=array();
        $arr_filter_column_buttons=array(
            'Переходы|clicks_count|actions,sales,leads', 'Действия|actions_count|actions', 'Продажи|sales_count|sales',
            'Лиды|leads_count|leads', 'Конверсия|actions_conversion_rate|actions', 'Конверсия|sales_conversion_rate|sales',
            'Конверсия|leads_conversion_rate|leads', 'Затраты|cost|actions,sales,leads', 'Прибыль|profit|actions,sales',
            'EPC|epc|actions,sales', 'CPL|cpl|leads', 'ROI|roi|actions,sales');

        foreach ($arr_filter_column_buttons as $cur)
        {
            list ($caption, $value, $filter_actions)=explode ('|', $cur);
            $arr_filter_actions=explode (',', $filter_actions);
            $active=($IN['report_type']==$value)?'active':'';
            if (in_array($IN['filter_actions'], $arr_filter_actions))
            {
                $arr_report_data['report-toolbar-filter-conversions'][]=array(
                    'action'=>'report_type', 'caption'=>$caption, 'value'=>$value, 'active'=>$active
                );
            }
        }
    }


    $arr_filter_actions_buttons=array('Все действия|actions', 'Продажи|sales', 'Лиды|leads');
    foreach ($arr_filter_actions_buttons as $cur)
    {
        list ($caption, $value)=explode ('|', $cur);

        $active=($IN['filter_actions']==$value)?'active':'';

        $arr_report_data['report-toolbar-filter-actions'][]=array(
            'caption'=>$caption, 'value'=>$value, 'active'=>$active
        );
    }

    // Add Excel export
    if ($IN['range_type']=='all'
        && $IN['main_column']!='popular'
        && $arr_report_data['table-total']['values'][1]['value']!=0)
    {
        $arr_report_data['report-toolbar-excel-export']=true;
    }

    switch ($IN['range_type'])
    {
        case 'all':
            $arr_table_columns=array($arr_allowed_main_columns[$IN['main_column']].'|main_column',
                'Переходы|clicks_count',
                'Действия|actions_count|c-action',
                'Продажи|sales_count|c-sale',
                'Лиды|leads_count|c-lead',
                'Конверсия|actions_conversion_rate|c-action',
                'Конверсия|sales_conversion_rate|c-sale',
                'Конверсия|leads_conversion_rate|c-lead',
                'Затраты|cost',
                'Прибыль|profit|c-action c-sale',
                'EPC|epc|c-sale',
                'ROI|roi|c-action c-sale',
                'CPL|cpl|c-lead');
            if ($IN['main_column']=='popular')
            {
                array_unshift($arr_table_columns, 'Параметр|popular');
            }
        break;

        case 'hourly': case 'daily': case 'monthly': case 'weekday':
            $arr_table_columns=array();
            $arr_table_columns[]=$arr_allowed_main_columns[$IN['main_column']].'|main_column|selected';
            foreach ($arr_column_values[$IN['range_type']] as $k=>$v)
            {
                $arr_table_columns[]=$v;
            }
        break;
    }

    foreach ($arr_table_columns as $cur)
    {
        list ($caption, $name, $class)=array_pad(explode ('|', $cur), 3, '');
        $is_sorted=($name==$IN['sort_by'])?'sorting_desc':'sorting';
        $arr_report_data['table-columns'][]=array(
            'caption'=>$caption, 'name'=>$name, 'class'=>$class,'is_sorted'=>$is_sorted
        );
    }

    // Fill toolbar buttons
    $arr_toolbar_buttons['main']=array('offer_name'=>'Оффер|main_column=offer_name',
        'source_name'=>'Источник|main_column=source_name',
        'campaign_name'=>'Кампания|main_column=campaign_name',
        'campaign_ads'=>'Объявление|main_column=campaign_ads',
        'referer_domain'=>'Площадка|main_column=referer_domain',
        'link_name'=>'Ссылка|main_column=link_name'
    );

    $arr_toolbar_buttons['geo']=array('country'=>'Страна|main_column=country',
        'region'=>'Регион|main_column=region',
        'city'=>'Город|main_column=city',
        'user_ip'=>'IP адрес|main_column=user_ip'
    );

    $arr_toolbar_buttons['isp']=array('isp'=>'Провайдер|main_column=isp');

    $arr_toolbar_buttons['device']=array('ОС|main_column=user_os', 'Платформа|main_column=user_platform',
                                         'Браузер|main_column=user_browser');

    $arr_toolbar_buttons['link-param']=array('Параметр ссылки #1|main_column=campaign_param1',
        'Параметр ссылки #2|main_column=campaign_param2',
        'Параметр ссылки #3|main_column=campaign_param3',
        'Параметр ссылки #4|main_column=campaign_param4',
        'Параметр ссылки #5|main_column=campaign_param5');

    $arr_toolbar_buttons['visit-param']=array($arr_allowed_main_columns['click_param_value1'].'|main_column=click_param_value1',
        $arr_allowed_main_columns['click_param_value2'].'|main_column=click_param_value2',
        $arr_allowed_main_columns['click_param_value3'].'|main_column=click_param_value3',
        $arr_allowed_main_columns['click_param_value4'].'|main_column=click_param_value4',
        $arr_allowed_main_columns['click_param_value5'].'|main_column=click_param_value5',
        $arr_allowed_main_columns['click_param_value6'].'|main_column=click_param_value6',
        $arr_allowed_main_columns['click_param_value7'].'|main_column=click_param_value7',
        $arr_allowed_main_columns['click_param_value8'].'|main_column=click_param_value8',
        $arr_allowed_main_columns['click_param_value9'].'|main_column=click_param_value9',
        $arr_allowed_main_columns['click_param_value10'].'|main_column=click_param_value10',
        $arr_allowed_main_columns['click_param_value11'].'|main_column=click_param_value11',
        $arr_allowed_main_columns['click_param_value12'].'|main_column=click_param_value12',
        $arr_allowed_main_columns['click_param_value13'].'|main_column=click_param_value13',
        $arr_allowed_main_columns['click_param_value14'].'|main_column=click_param_value14',
        $arr_allowed_main_columns['click_param_value15'].'|main_column=click_param_value15');

    $arr_toolbar_buttons['date-selector']=array('Сегодня|report_period=today',
        'Вчера|report_period=yesterday',
        'Последняя неделя|report_period=lastweek',
        'Последний месяц|report_period=lastmonth',
        'Последний квартал|report_period=lastquarter');

    // Remove toolbar buttons for filtered values
    foreach ($IN['filter_by'] as $cur)
    {
        foreach ($arr_toolbar_buttons as $key=>$toolbar_group)
        {
            if (isset($arr_toolbar_buttons[$key][$cur]))
            {
                unset($arr_toolbar_buttons[$key][$cur]);
            }
        }
    }

    foreach ($arr_toolbar_buttons as $key=>$toolbar_group)
    {
        foreach ($toolbar_group as $cur)
        {
            list ($caption, $data)=explode ('|', $cur);
            list ($name, $value)=explode ('=', $data);
            $is_active=($value==$IN['main_column'])?'active':'';
            $arr_report_data['toolbar-buttons-'.$key][]=array(
                'caption'=>$caption, 'name'=>$name, 'value'=>$value, 'is_active'=>$is_active
            );
        }
    }

    return $arr_report_data;
}

function generate_main_report_sql($arr_allowed_main_columns, $IN, $sql_add='')
{
    $sql_select=array();
    $sql_join=array();
    $sql_where=array();
    $sql_group=array();
    $sql_order=array();
    $sql_limit='';

    if ($sql_add!='')
    {
        $sql_select=isset($sql_add['select'])?array_merge($sql_add['select'], $sql_select):array();
        $sql_join=isset($sql_add['join'])?array_merge($sql_add['join'], $sql_join):array();
        $sql_where=isset($sql_add['where'])?array_merge($sql_add['where'], $sql_where):array();
        $sql_group=isset($sql_add['group'])?array_merge($sql_add['group'], $sql_group):array();
        $sql_order=isset($sql_add['order'])?array_merge($sql_add['order'], $sql_order):array();
        $sql_limit=isset($sql_add['limit'])?$sql_add['limit']:'';
    }

    // Process main column
    switch($IN['main_column'])
    {
        case 'offer_name':
            $sql_select['c1']='tbl_offers.offer_name as c1';
            $sql_select['c1_id']='tclicks.out_id as c1_id';
            $sql_join[]='LEFT JOIN tbl_offers on tbl_offers.id=tclicks.out_id';
            $sql_group[]='tclicks.out_id';
        break;

        case 'offer_category':
            $sql_select['c1']='tbl_links_categories_list.category_caption as c1';
            $sql_select['c1_id']='tbl_links_categories.category_id as c1_id';
            $sql_join[]='LEFT JOIN tbl_links_categories on tbl_links_categories.offer_id=tclicks.out_id';
            $sql_join[]='LEFT JOIN tbl_links_categories_list on tbl_links_categories_list.id=tbl_links_categories.category_id';
            $sql_group[]='tbl_links_categories.category_id';
        break;

        case 'os_and_version':
            $sql_select['c1']='CONCAT(tclicks.user_os, " ", tclicks.user_os_version) as c1';
            $sql_group[]='tclicks.user_os';
            $sql_group[]='tclicks.user_os_version';
        break;

        case 'campaign_ads':
            $sql_select['c1']='CONCAT(tclicks.campaign_name, "-", tclicks.ads_name) as c1';
            $sql_group[]='tclicks.campaign_name';
            $sql_group[]='tclicks.ads_name';
        break;

        case 'link_name':
            $sql_select['c1']='tbl_rules.link_name as c1';
            $sql_select['c1_id']='tclicks.rule_id as c1_id';
            $sql_join[]='LEFT JOIN tbl_rules on tbl_rules.id=tclicks.rule_id';
            $sql_group[]='tclicks.rule_id';
        break;

        default:
            if (in_array($IN['main_column'], array_keys($arr_allowed_main_columns)))
            {
                $sql_select['c1']='tclicks.'.$IN['main_column'].' as c1';
                $sql_group[]='tclicks.'.$IN['main_column'];
            }
        break;
    }

    $sql_select['clicks_count']='COUNT(tclicks.id) as clicks_count';
    $sql_select['leads_count']='SUM(tclicks.is_lead) as leads_count';
    $sql_select['sales_count']='SUM(tclicks.is_sale) as sales_count';
    $sql_select['actions_count']='SUM(tclicks.is_lead)+SUM(tclicks.is_sale) as actions_count';
    $sql_select['actions_conversion_rate']='(SUM(tclicks.is_lead)+SUM(tclicks.is_sale))/COUNT(tclicks.id)*100 as actions_conversion_rate';
    $sql_select['sales_conversion_rate']='(SUM(tclicks.is_sale))/COUNT(tclicks.id)*100 as sales_conversion_rate';
    $sql_select['leads_conversion_rate']='(SUM(tclicks.is_lead))/COUNT(tclicks.id)*100 as leads_conversion_rate';
    $sql_select['cost']='SUM(tclicks.click_price) as cost';
    $sql_select['profit']='SUM(tclicks.conversion_price_main) as profit';
    $sql_select['epc']='SUM(tclicks.conversion_price_main)/COUNT(tclicks.id) as epc';
    $sql_select['roi']='(SUM(tclicks.conversion_price_main)-SUM(tclicks.click_price))/SUM(tclicks.click_price)*100 as roi';
    $sql_select['cpl']='SUM(tclicks.click_price)/SUM(tclicks.is_lead) as cpl';

    // Process range_type
    if (in_array($IN['range_type'], array('hourly', 'daily', 'monthly', 'weekday')))
    {
        $t=array(
            'hourly'=>'%H',
            'daily'=>'%Y-%m-%d',
            'monthly'=>'%Y-%m',
            'weekday'=>'%w'
        );

        if (in_array($IN['timezone_offset'], array('+00:00', '-00:00', '00:00')))
        {
            $sql_select['column_name']="DATE_FORMAT(tclicks.date_add, '".$t[$IN['range_type']]."') as column_name";
        }
        else
        {
            $sql_select['column_name']="DATE_FORMAT(CONVERT_TZ(tclicks.date_add, '+00:00', '"._str($IN['timezone_offset'])."'), '".$t[$IN['range_type']]."') as column_name";
        }
        $sql_group[]='column_name';
    }

    switch ($IN['sort_by'])
    {
        case 'clicks_count':
            $sort_order=($IN['sort_order']=='ASC')?'ASC':'DESC';
            $sql_order[]='clicks_count '.$sort_order;
        break;

        case 'main_column':
            // Sorting by main column is always ASC
            $sql_order[]='c1 '.'ASC';
        break;

        case 'actions_count': case 'sales_count': case 'leads_count': case 'actions_conversion_rate':
        case 'sales_conversion_rate': case 'leads_conversion_rate': case 'cost': case 'profit':
        case 'roi': case 'epc': case 'cpl':
            $sort_order=($IN['sort_order']=='ASC')?'ASC':'DESC';
            $sql_order[]=$IN['sort_by'].' '.$sort_order.', clicks_count DESC';
        break;
    }

    // Apply timezone offset
    if (in_array ($IN['timezone_offset'], array('+00:00', '-00:00', '00:00')))
    {
        // Same timezones in DB and report
        $sql_date_start="'"._str($IN['date_start'])." 00:00:00'";
        $sql_date_end="'"._str($IN['date_end'])." 23:59:59'";
    }
    else
    {
        $timezone_offset_inverted=timezone_shift_invert($IN['timezone_offset']);
        $sql_date_start="CONVERT_TZ('"._str($IN['date_start'])." 00:00:00', '+00:00', '"._str($timezone_offset_inverted)."')";
        $sql_date_end="CONVERT_TZ('"._str($IN['date_end'])." 23:59:59', '+00:00', '"._str($timezone_offset_inverted)."')";
    }

    $sql_where[]="tclicks.date_add BETWEEN {$sql_date_start} AND {$sql_date_end}";

    $sql='SELECT '.implode (', ', $sql_select).'
          FROM
              tbl_clicks tclicks
              '.implode (' ', $sql_join).'
          WHERE
              1=1 AND
              '.implode (' AND ', $sql_where).'
          GROUP BY
              '.implode (',', $sql_group).'
          ORDER BY '.implode (', ', $sql_order);

    // Save sql in convenient format for costs update
    $arr_resulting_sql=array();
    $arr_resulting_sql['select']=implode (', ', $sql_select);
    $arr_resulting_sql['from']='tbl_clicks tclicks';
    $arr_resulting_sql['join']=implode (' ', $sql_join);
    $arr_resulting_sql['where']=implode (' AND ', $sql_where);
    $arr_resulting_sql['group']=implode (',', $sql_group);
    $arr_resulting_sql['order']=implode (', ', $sql_order);
    $arr_resulting_sql['limit']=$sql_limit;

    $arr_allowed_actions=array('actions'=>'actions_count', 'sales'=>'sales_count', 'leads'=>'leads_count');
    switch ($IN['filter_conversions'])
    {
        case 'has_actions':
            $outer_select=array();
            foreach (array_keys($sql_select) as $key)
            {
                $outer_select[]='main_table.'.$key;
            }
            $sql='SELECT '.implode (', ', $outer_select).' FROM ('.$sql.') as main_table
                    WHERE main_table.'.$arr_allowed_actions[$IN['filter_actions']].'>0'." {$sql_limit}";
        break;

        case 'no_actions':
            $outer_select=array();
            foreach (array_keys($sql_select) as $key){
                $outer_select[]='main_table.'.$key;
            }
            $sql='SELECT '.implode (', ', $outer_select).' FROM ('.$sql.') as main_table
                    WHERE main_table.'.$arr_allowed_actions[$IN['filter_actions']].'=0'." {$sql_limit}";
        break;

        default:
            $sql=$sql." {$sql_limit}";
        break;
    }

    return array($sql, $arr_resulting_sql);
}

function format_cell_value($name, $value, $IN='')
{
    $retval='';
    switch ($name)
    {
        case 'referer_domain':
            // Convert Punycode to UTF-8
            $url_host=parse_url($value, PHP_URL_HOST);
            $retval=str_replace ($url_host, idn_to_utf8($url_host), $value);
            if ($retval==''){$retval='—';}
        break;

        case 'source_name':
            global $source_config;
            if ($value!='source' && isset($source_config[$value]['name']))
            {
                $retval=$source_config[$value]['name'];
            }
            else
            {
                $retval=$value;
            }
            if ($retval==''){$retval='—';}
        break;

        case 'country':
            $arr_countries_rus=array('AD' => 'Андорра', 'AE' => 'ОАЭ', 'AF' => 'Афганистан', 'AG' => 'Антигуа и Барбуда', 'AI' => 'Ангилья', 'AL' => 'Албания', 'AM' => 'Армения', 'AO' => 'Ангола', 'AQ' => 'Антарктида', 'AR' => 'Аргентина', 'AS' => 'Американское Самоа', 'AT' => 'Австрия', 'AU' => 'Австралия', 'AW' => 'Аруба', 'AX' => 'Аландские острова', 'AZ' => 'Азербайджан', 'BA' => 'Босния и Герцеговина', 'BB' => 'Барбадос', 'BD' => 'Бангладеш', 'BE' => 'Бельгия', 'BF' => 'Буркина-Фасо', 'BG' => 'Болгария', 'BH' => 'Бахрейн', 'BI' => 'Бурунди', 'BJ' => 'Бенин', 'BL' => 'Сен-Бартелеми', 'BM' => 'Бермуды', 'BN' => 'Бруней', 'BO' => 'Боливия', 'BQ' => 'Бонэйр, Синт-Эстатиус и Саба', 'BR' => 'Бразилия', 'BS' => 'Багамы', 'BT' => 'Бутан', 'BV' => 'Остров Буве', 'BW' => 'Ботсвана', 'BY' => 'Белоруссия', 'BZ' => 'Белиз', 'CA' => 'Канада', 'CC' => 'Кокосовые острова', 'CD' => 'ДР Конго', 'CF' => 'ЦАР', 'CG' => 'Республика Конго', 'CH' => 'Швейцария', 'CI' => 'Кот-д’Ивуар', 'CK' => 'Острова Кука', 'CL' => 'Чили', 'CM' => 'Камерун', 'CN' => 'КНР', 'CO' => 'Колумбия', 'CR' => 'Коста-Рика', 'CU' => 'Куба', 'CV' => 'Кабо-Верде', 'CW' => 'Кюрасао', 'CX' => 'Остров Рождества', 'CY' => 'Кипр', 'CZ' => 'Чехия', 'DE' => 'Германия', 'DJ' => 'Джибути', 'DK' => 'Дания', 'DM' => 'Доминика', 'DO' => 'Доминиканская Республика', 'DZ' => 'Алжир', 'EC' => 'Эквадор', 'EE' => 'Эстония', 'EG' => 'Египет', 'EH' => 'Западная Сахара', 'ER' => 'Эритрея', 'ES' => 'Испания', 'ET' => 'Эфиопия', 'FI' => 'Финляндия', 'FJ' => 'Фиджи', 'FK' => 'Фолклендские острова', 'FM' => 'Микронезия', 'FO' => 'Фарерские острова', 'FR' => 'Франция', 'GA' => 'Габон', 'GB' => 'Великобритания', 'GD' => 'Гренада', 'GE' => 'Грузия', 'GF' => 'Гвиана', 'GG' => 'Гернси', 'GH' => 'Гана', 'GI' => 'Гибралтар', 'GL' => 'Гренландия', 'GM' => 'Гамбия', 'GN' => 'Гвинея', 'GP' => 'Гваделупа', 'GQ' => 'Экваториальная Гвинея', 'GR' => 'Греция', 'GS' => 'Южная Георгия и Южные Сандвичев', 'GT' => 'Гватемала', 'GU' => 'Гуам', 'GW' => 'Гвинея-Бисау', 'GY' => 'Гайана', 'HK' => 'Гонконг', 'HM' => 'Херд и Макдональд', 'HN' => 'Гондурас', 'HR' => 'Хорватия', 'HT' => 'Гаити', 'HU' => 'Венгрия', 'ID' => 'Индонезия', 'IE' => 'Ирландия', 'IL' => 'Израиль', 'IM' => 'Остров Мэн', 'IN' => 'Индия', 'IO' => 'Британская территория в Индийском океане', 'IQ' => 'Ирак', 'IR' => 'Иран', 'IS' => 'Исландия', 'IT' => 'Италия', 'JE' => 'Джерси', 'JM' => 'Ямайка', 'JO' => 'Иордания', 'JP' => 'Япония', 'KE' => 'Кения', 'KG' => 'Киргизия', 'KH' => 'Камбоджа', 'KI' => 'Кирибати', 'KM' => 'Коморы', 'KN' => 'Сент-Китс и Невис', 'KP' => 'КНДР', 'KR' => 'Республика Корея', 'KW' => 'Кувейт', 'KY' => 'Каймановы острова', 'RU' => 'Россия', 'KZ' => 'Казахстан', 'LA' => 'Лаос', 'LB' => 'Ливан', 'LC' => 'Сент-Люсия', 'LI' => 'Лихтенштейн', 'LK' => 'Шри-Ланка', 'LR' => 'Либерия', 'LS' => 'Лесото', 'LT' => 'Литва', 'LU' => 'Люксембург', 'LV' => 'Латвия', 'LY' => 'Ливия', 'MA' => 'Марокко', 'MC' => 'Монако', 'MD' => 'Молдавия', 'ME' => 'Черногория', 'MF' => 'Сен-Мартен', 'MG' => 'Мадагаскар', 'MH' => 'Маршалловы Острова', 'MK' => 'Македония', 'ML' => 'Мали', 'MM' => 'Мьянма', 'MN' => 'Монголия', 'MO' => 'Макао', 'MP' => 'Северные Марианские острова', 'MQ' => 'Мартиника', 'MR' => 'Мавритания', 'MS' => 'Монтсеррат', 'MT' => 'Мальта', 'MU' => 'Маврикий', 'MV' => 'Мальдивы', 'MW' => 'Малави', 'MX' => 'Мексика', 'MY' => 'Малайзия', 'MZ' => 'Мозамбик', 'NA' => 'Намибия', 'NC' => 'Новая Каледония', 'NE' => 'Нигер', 'NF' => 'Остров Норфолк', 'NG' => 'Нигерия', 'NI' => 'Никарагуа', 'NL' => 'Нидерланды', 'NO' => 'Норвегия', 'NP' => 'Непал', 'NR' => 'Науру', 'NU' => 'Ниуэ', 'NZ' => 'Новая Зеландия', 'OM' => 'Оман', 'PA' => 'Панама', 'PE' => 'Перу', 'PF' => 'Французская Полинезия', 'PG' => 'Папуа — Новая Гвинея', 'PH' => 'Филиппины', 'PK' => 'Пакистан', 'PL' => 'Польша', 'PM' => 'Сен-Пьер и Микелон', 'PN' => 'Острова Питкэрн', 'PR' => 'Пуэрто-Рико', 'PS' => 'Государство Палестина', 'PT' => 'Португалия', 'PW' => 'Палау', 'PY' => 'Парагвай', 'QA' => 'Катар', 'RE' => 'Реюньон', 'RO' => 'Румыния', 'RS' => 'Сербия', 'RW' => 'Руанда', 'SA' => 'Саудовская Аравия', 'SB' => 'Соломоновы Острова', 'SC' => 'Сейшельские Острова', 'SD' => 'Судан', 'SE' => 'Швеция', 'SG' => 'Сингапур', 'SH' => 'Острова Святой Елены, Вознесения и Тристан-да-Кунья', 'SI' => 'Словения', 'SJ' => 'Шпицберген и Ян-Майен', 'SK' => 'Словакия', 'SL' => 'Сьерра-Леоне', 'SM' => 'Сан-Марино', 'SN' => 'Сенегал', 'SO' => 'Сомали', 'SR' => 'Суринам', 'SS' => 'Южный Судан', 'ST' => 'Сан-Томе и Принсипи', 'SV' => 'Сальвадор', 'SX' => 'Синт-Мартен', 'SY' => 'Сирия', 'SZ' => 'Свазиленд', 'TC' => 'Тёркс и Кайкос', 'TD' => 'Чад', 'TF' => 'Французские Южные и Антарктические Территории', 'TG' => 'Того', 'TH' => 'Таиланд', 'TJ' => 'Таджикистан', 'TK' => 'Токелау', 'TL' => 'Восточный Тимор', 'TM' => 'Туркмения', 'TN' => 'Тунис', 'TO' => 'Тонга', 'TR' => 'Турция', 'TT' => 'Тринидад и Тобаго', 'TV' => 'Тувалу', 'TW' => 'Китайская Республика', 'TZ' => 'Танзания', 'UA' => 'Украина', 'UG' => 'Уганда', 'UM' => 'Внешние малые острова (США)', 'US' => 'США', 'UY' => 'Уругвай', 'UZ' => 'Узбекистан', 'VA' => 'Ватикан', 'VC' => 'Сент-Винсент и Гренадины', 'VE' => 'Венесуэла', 'VG' => 'Британские Виргинские острова', 'VI' => 'Американские Виргинские острова', 'VN' => 'Вьетнам', 'VU' => 'Вануату', 'WF' => 'Уоллис и Футуна', 'WS' => 'Самоа', 'YE' => 'Йемен', 'YT' => 'Майотта', 'ZA' => 'ЮАР', 'ZM' => 'Замбия', 'ZW' => 'Зимбабве');
            $retval=$arr_countries_rus[$value];
            if ($retval==''){$retval='—';}
        break;

        case 'clicks_count':
            $retval=$value;
        break;

        case 'actions_count':
        case 'sales_count':
        case 'leads_count':
            $retval=$value;
        break;

        case 'actions_conversion_rate':
        case 'sales_conversion_rate':
        case 'leads_conversion_rate':
            $retval=round($value, 3).'%';
        break;

        case 'cost':
        case 'profit':
        case 'epc':
            global $arr_currencies_list;
            $retval=number2currency(round($value, 2), $arr_currencies_list[$IN['currency_id']]['code']);
        break;

        case 'roi':
            $retval=number_format($value, 0, '.', ' ').' %';
        break;

        case 'cpl':
            global $arr_currencies_list;
            $retval=number2currency(round($value, 2), $arr_currencies_list[$IN['currency_id']]['code']);
        break;

        default:
            $retval=$value;
            if ($retval==''){$retval='—';}
        break;
    }
    return $retval;
}

function report_period_to_dates(&$IN)
{
    switch ($IN['report_period'])
    {
        case 'today':
            $IN['date_start']=$IN['date_end']=get_current_day();
        break;

        case 'yesterday':
            $IN['date_start']=$IN['date_end']=get_current_day('-1 days');
        break;

        case 'lastweek':
            $IN['date_start']=get_current_day('-1 week');
            $IN['date_end']=get_current_day();
        break;

        case 'lastmonth':
            $IN['date_start']=get_current_day('-1 months');
            $IN['date_end']=get_current_day();
        break;

        case 'lastquarter':
            $IN['date_start']=get_current_day('-3 months');
            $IN['date_end']=get_current_day();
        break;
    }
}


function load_from_cache($params) {
    $out = array();

    $date_formats = array(
        'hour' => 'H', // Y-m-d
        'day' => 'Y-m-d',
        'month' => 'm.Y'
    );

    $timezone_shift = get_current_timezone_shift();

    if (strlen($params['from']) == 10) {
        $params['from'] .= ' 00:00:00';
    }

    if (strlen($params['to']) == 10) {
        $params['to'] .= ' 23:59:59';
    }

    $click_params = array();
    $campaign_params = array();

    $cl_params = 0;

    $q = "select `type`, `id`, CONVERT_TZ(`time`, '+00:00', '" . _str($timezone_shift) . "') as `time`, `price`, `unique`, `income`, `direct`, `sale`, `lead`, `act`, `out`, `cnt`, `sale_lead`, `params`
        from `tbl_clicks_cache_hour` 
        where `type` = '" . $params['group_by'] . "' and
        CONVERT_TZ(`time`, '+00:00', '" . _str($timezone_shift) . "') BETWEEN STR_TO_DATE('" . $params['from'] . "', '%Y-%m-%d %H:%i:%s') AND STR_TO_DATE('" . $params['to'] . "', '%Y-%m-%d %H:%i:%s')" . $where . (empty($params['where']) ? '' : " and " . $params['where'] ) . " ";

    if ($rs = db_query($q) and mysql_num_rows($rs) > 0) {
        while ($r = mysql_fetch_assoc($rs)) {
            $r['name'] = param_val($r['id'], $params['group_by']);
            $tmp = $r;

            $cl_params = $cl_params | $r['params'];

            unset($tmp['time'], $tmp['type']);

            if ($params['part'] == 'all') {
                foreach ($tmp as $k => $v) {
                    if ($k == 'id' or $k == 'name') {
                        $out[$r['id']][$k] = $v;
                    } else {
                        $out[$r['id']][$k] += $v;
                    }
                }
            } else {
                $d = date($date_formats[$params['part']], strtotime($r['time']));
                foreach ($tmp as $k => $v) {
                    if ($k == 'id' or $k == 'name') {
                        $out[$r['id']][$k] = $v;
                        $out[$r['id']][$d][$k] = $v;
                    } else {
                        $out[$r['id']][$k] += $v;
                        $out[$r['id']][$d][$k] += $v;
                    }
                }
            }
        }

        // переводим параметры в 000011101011
        $cl_params = decbin($cl_params);

        if (strlen($cl_params) < 20) {
            $cl_params = str_repeat('0', 20 - strlen($cl_params)) . $cl_params;
        }

        for ($i = 1; $i <= 20; $i++) {
            if ($i <= 15) {
                $click_params[$i] = $cl_params[$i - 1];
            } else {
                $campaign_params[$i - 15] = $cl_params[$i - 1];
            }
        }
    }

    return array($out, $click_params, $campaign_params);
}

function pos_class($num) {
    return $num < 0 ? 'negative' : 'positive';
}

function get_visitors_flow_data($IN = '', $report_name='', $limit = 20, $offset = 0)
{

    global $allowed_report_in_params;

    $date=$IN[$report_name]['date'];

    $timezone_shift = get_current_timezone_shift();

    $convert_tz_condition="CONVERT_TZ(t1.date_add, '+00:00', '" . _str($timezone_shift) . "')";
    if (in_array($timezone_shift , array('+00:00', '-00:00', '00:00'))){$convert_tz_condition='t1.date_add';}
    if (substr($timezone_shift, 0, 1)=='+')
    {
        $timezone_shift_inverted=str_replace('+','-',$timezone_shift);
    }
    else
    {
        $timezone_shift_inverted=str_replace('-','+',$timezone_shift);
    }


    $more = 0; // Записей больше нет

    $filter_str = '';

    switch ($IN[$report_name]['filter_by'])
    {
        case 'none':
        break;

        case 'hour':
            $hour=sprintf('%02d', $IN[$report_name]['filter_value'][0]);
            $filter_str .= " AND `source_name` = '" . _str($IN[$report_name]['filter_value'][1]) . "' AND date_add BETWEEN CONVERT_TZ('" . _str($IN[$report_name]['date']) . " " . _str($hour) . ":00:00', '+00:00', '"._str($timezone_shift_inverted)."') AND
                             CONVERT_TZ('" . _str($IN[$report_name]['date']) . " " . _str($hour) . ":59:59', '+00:00', '"._str($timezone_shift_inverted)."')";
            $date='';
        break;

        case 'search':
            if (is_subid($IN[$report_name]['filter_value']))
            {
                $filter_str .= " and `subid` LIKE '" . _str($IN[$report_name]['filter_value']) . "'";
                $date = '';
            }
            else
            {
                $filter_str .= " and (
                    `user_ip` LIKE '" . _str($IN[$report_name]['filter_value']) . "' OR
                    `campaign_name` LIKE '%" . _str($IN[$report_name]['filter_value']) . "%' OR
                    `source_name` LIKE '%" . _str($IN[$report_name]['filter_value']) . "%' OR
                    `referer` LIKE '%" . _str($IN[$report_name]['filter_value']) . "%'
                )";
            }
        break;

        default:
            if ($IN[$report_name]['filter_by']=='source_name' && $IN[$report_name]['filter_value']=='{empty}')
            {
                $filter_str .= " and " . _str($IN[$report_name]['filter_by']) . "='source'";
            }
            else
            {
                $filter_str .= " and " . _str($IN[$report_name]['filter_by']) . "='" . _str($IN[$report_name]['filter_value']) . "'";
            }
        break;
    }

    if ($date!='')
    {
        if (in_array($timezone_shift_inverted, array('+00:00', '-00:00', '00:00')))
        {
            $date_str='AND t1.date_add BETWEEN '."'"._str($date)." 00:00:00'"." AND "."'"._str($date)." 23:59:59'";
        }
        else
        {
            $date_str='AND t1.date_add BETWEEN '.
                "CONVERT_TZ('"._str($date)." 00:00:00', '+00:00', '"._str($timezone_shift_inverted)."')".
                " AND ".
                "CONVERT_TZ('"._str($date)." 23:59:59', '+00:00', '"._str($timezone_shift_inverted)."')";
        }

    }
    else
    {
        $date_str='';
    }


    $q = "SELECT *, ".$convert_tz_condition." as click_date,
                    DATE_FORMAT(".$convert_tz_condition.", '%d.%m.%Y %H:%i') as dt,
                    timediff(NOW(), t1.date_add) as td
        FROM
            `tbl_clicks` t1
        WHERE
            1
            {$filter_str}
            {$date_str}
        ORDER BY
            date_add DESC
        LIMIT
            $offset, ".($limit+1);

    $rs = db_query($q);

    $arr_data = array();

    $i=0;
    while ($row = mysql_fetch_assoc($rs))
    {
        if ($i==$limit)
        {
            $more=1;
            $offset += $limit;
            break;
        }

        $cur=array();

        // 1. Date
        $cur['date'] = mysqldate2short($row['click_date']);

        // 2. Relative date
        $cur['date_relative'] = get_relative_mysql_time($row['td']);

        // 3. Location
        if ($row['country'] == '')
        {
            $cur['country']='';
            $cur['country_icon']=_HTML_TEMPLATE_PATH . "/img/countries/" .'question.png';
        }
        else
        {
            $cur['country']=format_cell_value('country', $row['country']);
            $cur['country_icon']=_HTML_TEMPLATE_PATH . "/img/countries/" .strtolower($row['country']) . '.png';
        }

        $cur['location']=implode(', ', array_filter(array($cur['country'], $row['state'], $row['city'])));

        // 4. ISP
        $cur['isp']=$row['isp'];

        // 5. IP
        $cur['ip']=$row['user_ip'];

        // 6. Subaccount
        $cur['subaccount']=$row['subaccount'];

        // 7. SubID
        $cur['subid']=$row['subid'];

        // 8. OS
        switch(strtolower($row['user_os']))
        {
            case 'android':
                $cur['os_icon'] = 'android';
            break;

            case 'linux':
                $cur['os_icon'] = 'linux';
            break;

            case 'ios':
                $cur['os_icon'] = 'apple-ios';
            break;

            default:
                $cur['os_icon']= '';
            break;
        }

        $cur['os']=implode(' ', array_filter(array($row['user_os'], $row['user_os_version'])));

        // 9. Device
        $cur['device']=implode(' ', array_filter(array($row['user_platform'], $row['user_platform_info'], $row['user_platform_info_extra'])));

        // 10. Device type
        if ($row['is_phone']==1)
        {
            $cur['tablet_icon']='phone';
        }
        elseif ($row['is_tablet']==1)
        {
            $cur['tablet_icon']='tablet';
        }
        else
        {
            $cur['tablet_icon']='';
        }

        // 11. User-agent
        $cur['user_agent']=$row['user_agent'];

        // 12. Browser
        $cur['browser']=implode(' ', array_filter(array($row['user_browser'], $row['user_browser_version'])));

        // 13. Referer, converting Punycode domain to UTF-8
        $referer_host=parse_url($row['referer'], PHP_URL_HOST);
        $referer=str_replace ($referer_host, idn_to_utf8($referer_host), $row['referer']);
        $cur['full_referer']=urldecode(str_replace(array('http://www.', 'www.'), '', $referer));

        if ($row['source_name'] == 'yadirect' and !empty($row['click_param_value8']))
        {
            $cur_referrer = $row['click_param_value8'];
            if (mb_strlen($cur_referrer, 'UTF-8') > 40) {
                $wrapped_referrer = mb_substr($cur_referrer, 0, 38, 'UTF-8') . '…';
            } else {
                $wrapped_referrer = $cur_referrer;
            }
            $cur['referer']=$wrapped_referrer;
        }
        else
        {
            if ($row['search_string']!='')
            {
                $cur_referrer=$row['search_string'];
                if (mb_strlen($cur_referrer, 'UTF-8') > 100) {
                    $wrapped_referrer = mb_substr($cur_referrer, 0, 98, 'UTF-8') . '…';
                } else {
                    $wrapped_referrer = $cur_referrer;
                }
                $cur['referer']=$wrapped_referrer;
            }
            else
            {
                // Converting from Punycode to UTF-8
                $referer_host=parse_url($row['referer'], PHP_URL_HOST);
                $referer=str_replace ($referer_host, idn_to_utf8($referer_host), $row['referer']);

                $cur_referrer = str_replace(array('http://www.', 'www.'), '', $referer);
                if (strpos($cur_referrer, 'http://') === 0) {
                    $cur_referrer = substr($cur_referrer, strlen('http://'));
                }
                if (mb_strlen($cur_referrer, 'UTF-8') > 100)
                {
                    $wrapped_referrer = mb_substr($cur_referrer, 0, 98, 'UTF-8') . '…';
                }
                else
                {
                    $wrapped_referrer = $cur_referrer;
                }
                $cur['referer']=$wrapped_referrer;
            }
        }

        // 14. Keyword
        $cur['keyword']=$row['search_string'];

        // 15. Link
        // $cur['link']=get_rule_description($row['rule_id']);

        // 16. Offer
        if ($row['out_id'] > 0)
        {
            $cur['offer']=current(get_out_description($row['out_id']));
        }
        else
        {
            $cur['offer']='Не определён';
        }

        // 17. Source

        $cur['source']=format_cell_value('source_name', $row['source_name']);

        // 18. Campaign and ads
        $cur['campaign']=$row['campaign_name'];
        $cur['ads']=$row['ads_name'];

        // 19. Click price	click_price
        $cur['click_price']=$row['click_price'];

        // 20. Profit
        $cur['profit']=$row['conversion_price_main'];

        // 21. Action type is_lead	is_sale

        // 22. Parent click	is_parent

        // 23. LP click	is_connected

        // 24. Parent click ID	parent_id

        // 25. Unique click	is_unique

        // 26. Link params	campaign_param1, campaign_param2, campaign_param3, campaign_param4, campaign_param5
        $campaign_params = params_list($row, 'campaign_param');
        if (!empty($campaign_params))
        {
            $cur['link_params']=join('; ', $campaign_params);
        }

        // 27. Visit params	click_param_name1, click_param_value1, …, click_param_name15, click_param_value15
        $click_params = params_list($row, 'click_param_value', $row['source_name']);
        if (!empty($click_params))
        {
            $cur['click_params']=join('; ', $click_params);
        }

        // Save current row data
        $arr_data['flow_rows'][$i++]=$cur;
    } // end $row iteration


    // Fill report params, hidden
    foreach ($allowed_report_in_params as $report=>$t)
    {
        foreach ($allowed_report_in_params[$report] as $key=>$value)
        {
            if (isset($IN[$report][$key]) && $IN[$report][$key]!=$value)
            {
                if (is_array($IN[$report][$key]))
                {
                    foreach ($IN[$report][$key] as $cur)
                    {
                        $arr_data['report_params'][]=array('name'=>$key.'[]', 'value'=>$cur);
                    }
                }
                else
                {
                    $arr_data['report_params'][]=array('name'=>$key, 'value'=>$IN[$report][$key]);
                }
            }
        }
    }

    return array($more, $arr_data, $offset);
}

function get_sales_flow_data($request_parameters, $report_name, $limit = 20, $offset = 0)
{
    // Default currency for this account: RUB (16)
    $main_currency_id=16;

    // Fill currency list
    global $arr_currencies_list;
    if (!(is_array($arr_currencies_list) && count($arr_currencies_list)>0))
    {
        $arr_currencies_list=get_active_currencies();
    }

    $allowed_report_in_params=array(
        'report_type'=>'actions_count', // actions_count, sales_count, leads_count
        'range_type'=>'daily',           // hourly, daily, monthly, weekday
        'main_column'=>'source_name',
        'filter_actions'=>'actions',       // actions, sales_only, leads_only
        'filter_by'=>'',
        'filter_value'=>'',
        'currency_id'=>'16',           // RUB (16) is default currency for this account
        'timezone_offset'=>get_current_timezone_shift(),
        'date_start'=>get_current_day('-7 days'),
        'date_end'=>get_current_day(),
        'report_period'=>'lastweek', // today, yesterday, lastweek, lastmonth, lastquarter, custom
    );

    //  Remove empty values and get only allowed keys
    $IN=array_replace($allowed_report_in_params, array_intersect_key(array_filter($request_parameters), $allowed_report_in_params));

    // Add default report params
    $IN['report_params']=isset($request_parameters['report_params'])?$request_parameters['report_params']:null;

    // Fill report range
    report_period_to_dates($IN);

    $sql_select=array();
    $sql_join=array();
    $sql_where=array();
    $sql_order=array();
    $sql_limit='LIMIT '._str($limit+1).' OFFSET '._str($offset);

    // Apply timezone offset
    $date_add_tz="CONVERT_TZ(tbl_conversions.date_add, '+00:00', '" . _str($IN['timezone_offset']) . "') as click_date";
    if (in_array($IN['timezone_offset'] , array('+00:00', '-00:00', '00:00'))){$date_add_tz='tbl_conversions.date_add as click_date';}
    $sql_select[]=$date_add_tz;

    if (in_array ($IN['timezone_offset'], array('+00:00', '-00:00', '00:00')))
    {
        // Same timezones in DB and report
        $sql_date_start="'"._str($IN['date_start'])." 00:00:00'";
        $sql_date_end="'"._str($IN['date_end'])." 23:59:59'";
    }
    else
    {
        $timezone_offset_inverted=timezone_shift_invert($IN['timezone_offset']);
        $sql_date_start="CONVERT_TZ('"._str($IN['date_start'])." 00:00:00', '+00:00', '"._str($timezone_offset_inverted)."')";
        $sql_date_end="CONVERT_TZ('"._str($IN['date_end'])." 23:59:59', '+00:00', '"._str($timezone_offset_inverted)."')";
    }

    if ($IN['filter_by']=='subid' && $IN['filter_value']!='')
    {
        // Looking for sale, no need to limit search by date range
        $sql_where[]="tbl_conversions.subid='"._str($IN['filter_value'])."'";
    }
    else
    {
        $sql_where[]="tbl_conversions.date_add BETWEEN {$sql_date_start} AND {$sql_date_end}";
    }

    $sql_select[]='tbl_conversions.*';
    $sql_order[]='date_add desc';

    // 1. Get sales list
    $sql='SELECT '.implode (', ', $sql_select).'
          FROM
              tbl_conversions
              '.implode (' ', $sql_join).'
          WHERE
              '.implode (' AND ', $sql_where).'
          ORDER BY '.implode (', ', $sql_order)."
          {$sql_limit}";

    $result=mysql_query($sql);
    $arr_sales=array();
    while ($row=mysql_fetch_assoc($result))
    {
        $arr_sales["'"._str($row['subid'])."'"]=$row;
    }

    // 2. Get clicks for sales
    $arr_sales_clicks=array();
    $arr_sales_subids=array_keys($arr_sales);
    $subids=implode (',', $arr_sales_subids);

    $sql="select
        tbl_clicks.*,
        tbl_offers.offer_name
        FROM
          tbl_clicks
        left join
          tbl_offers on tbl_clicks.out_id=tbl_offers.id
      where
        subid in ($subids)";
    $result=mysql_query($sql);
    $arr_parent_ids=array();
    while ($row=mysql_fetch_assoc($result))
    {
        if ($row['parent_id']>0)
        {
            $arr_parent_ids[]="'"._str($row['parent_id'])."'";
        }
        $arr_sales_clicks["'"._str($row['subid'])."'"]=$row;
    }

    $parent_ids=implode(',',$arr_parent_ids);

    $sql="select tbl_clicks.* from tbl_clicks where id in ($parent_ids)";
    $result=mysql_query($sql);
    $arr_parent_clicks=array();
    while ($row=mysql_fetch_assoc($result))
    {
        $arr_parent_clicks[$row['id']]=$row;
    }

    $arr_report_data=array();

    function net_loader($class)
    {
        include_once _TRACK_LIB_PATH.'/postback/'.$class.'.php';
    }

    spl_autoload_register('net_loader');
    include _TRACK_LIB_PATH . "/class/common.php";
    include _TRACK_LIB_PATH . "/class/custom.php";

    $arr_default_network_params=array('date_add', 'profit', 'status', 'subid', 'txt_status', 'type');

    $i=0; $more=0;
    foreach ($arr_sales as $subid=>$row)
    {
        if ($i==$limit)
        {
            $more=1;
            break;
        }
        $arr_report_data[$i]['short_date']=mysqldate2short($row['click_date']);
        $arr_report_data[$i]['offer_name']=$arr_sales_clicks[$subid]['offer_name'];

        // Network name
        $class_name=$row['network'];
        if (class_exists($class_name, true))
        {
            $t=new $class_name();
            if (method_exists($t, 'get_params_info'))
            {
                $arr_class_params=$t->get_params_info();
                $arr_report_data[$i]['network_params']=array();
                foreach ($row as $key=>$val)
                {
                    if (isset($arr_class_params[$key]) && !in_array($key, $arr_default_network_params))
                    {
                        if (trim($val)!=''){
                            $arr_report_data[$i]['network_params']['values'][]=array(
                                'caption'=>$arr_class_params[$key]['caption'],
                                'value'=>$val
                            );
                        }
                    }
                }
            }
        }

        $arr_report_data[$i]['network_name']=$row['network'];
        $arr_report_data[$i]['source_name']=($arr_sales_clicks[$subid]['source_name']=='')?'':format_cell_value('source_name', $arr_sales_clicks[$subid]['source_name']);

        // Campaign name
        $arr_report_data[$i]['campaign_name']=implode(' — ', array_filter(array($arr_sales_clicks[$subid]['campaign_name'], $arr_sales_clicks[$subid]['ads_name'])));

        $arr_report_data[$i]['placement']=format_cell_value('referer_domain', $arr_sales_clicks[$subid]['referer_domain']);
        $arr_report_data[$i]['subid']=$row['subid'];
        if ($row['currency_id']==$IN['currency_id'])
        {
            $arr_report_data[$i]['profit']=format_cell_value('profit', $row['profit_currency'], $IN);
        }
        else
        {
            $arr_report_data[$i]['profit']=format_cell_value('profit', convert_currency($row['profit'], $main_currency_id, $IN['currency_id'], date('Y-m-d')), $IN);
        }

        // $arr_report_data[$i]['status']=$row['txt_status'];
        switch ($row['txt_status'])
        {
            case 'approved':
                $arr_report_data[$i]['status-icon']="fa fa-check";
                $arr_report_data[$i]['status-title']="Подтвержден";
            break;

            case 'waiting':
                $arr_report_data[$i]['status-icon']="fa fa-hourglass-o";
                $arr_report_data[$i]['status-title']="В обработке";
            break;

            case 'rejected':
                $arr_report_data[$i]['status-icon']="fa fa-close";
                $arr_report_data[$i]['status-title']="Отклонен";
            break;

            default:
                $arr_report_data[$i]['status']=mb_strtolower($row['txt_status'], 'UTF-8');
            break;
        }

        if (isset($arr_parent_clicks[$arr_sales_clicks[$subid]['parent_id']]))
        {
            // Fill sale parameters from parent click
            $arr_report_data[$i]['source_name']=$arr_parent_clicks[$arr_sales_clicks[$subid]['parent_id']]['source_name'];
        }

        // FILL CLICK INFO
        if (isset($arr_parent_clicks[$subid]))
        {
            $click_info=$arr_parent_clicks[$subid];
        }
        else
        {
            $click_info=$arr_sales_clicks[$subid];
        }

        // Conversion ID
        $arr_report_data[$i]['conversion_id'] = $row['id'];

        // Date
        $arr_report_data[$i]['date'] = mysqldate2string($row['click_date']);

        // Location
        $arr_report_data[$i]['country']=format_cell_value('country', $click_info['country']);
        $arr_report_data[$i]['location']=implode(', ', array_filter(array($arr_report_data[$i]['country'], $click_info['state'], $click_info['city'])));

        // ISP
        $arr_report_data[$i]['isp']=$click_info['isp'];

        // IP
        $arr_report_data[$i]['ip']=$click_info['user_ip'];

        // Subaccount
        $arr_report_data[$i]['subaccount']=$click_info['subaccount'];

        // SubID
        $arr_report_data[$i]['subid']=$click_info['subid'];

        // OS
        $arr_report_data[$i]['os']=implode(' ', array_filter(array($click_info['user_os'], $click_info['user_os_version'])));

        // Device
        $arr_report_data[$i]['device']=implode(' ', array_filter(array($click_info['user_platform'], $click_info['user_platform_info'], $click_info['user_platform_info_extra'])));

        // Device type
        if ($click_info['is_phone']==1)
        {
            $arr_report_data[$i]['tablet_icon']='phone';
        }
        elseif ($click_info['is_tablet']==1)
        {
            $arr_report_data[$i]['tablet_icon']='tablet';
        }
        else
        {
            $arr_report_data[$i]['tablet_icon']='';
        }

        // User-agent
        $arr_report_data[$i]['user_agent']=$click_info['user_agent'];

        // Browser
        $arr_report_data[$i]['browser']=implode(' ', array_filter(array($click_info['user_browser'], $click_info['user_browser_version'])));

        // Referer
        $referer_host=parse_url($click_info['referer'], PHP_URL_HOST);
        $referer=str_replace ($referer_host, idn_to_utf8($referer_host), $click_info['referer']);
        $arr_report_data[$i]['full_referer']=urldecode(str_replace(array('http://www.', 'www.'), '', $referer));

        // Keyword
        $arr_report_data[$i]['keyword']=$click_info['search_string'];

        // Link params	campaign_param1, campaign_param2, campaign_param3, campaign_param4, campaign_param5
        $campaign_params = params_list($click_info, 'campaign_param');
        if (!empty($campaign_params))
        {
            $arr_report_data[$i]['link_params']=join('; ', $campaign_params);
        }

        // Visit params	click_param_name1, click_param_value1, …, click_param_name15, click_param_value15
        $click_params = params_list($click_info, 'click_param_value', $click_info['source_name']);
        if (!empty($click_params))
        {
            $arr_report_data[$i]['click_params']=join('; ', $click_params);
        }

        $i++;
    }

    return array($more, $arr_report_data, $offset);
}

function sdate($d, $today = true) {
    $d = strtotime($d);
    if ((empty($d) and $today) or date('Y-m-d') == date('Y-m-d', $d)) {
        return 'сегодня';
    } elseif (date('Y-m-d') == date('Y-m-d', $d + 86400)) {
        return 'вчера';
    } else {
        $months = array(
            '01' => "января",
            '02' => "февраля",
            '03' => "марта",
            '04' => "апреля",
            '05' => "мая",
            '06' => "июня",
            '07' => "июля",
            '08' => "августа",
            '09' => "сентября",
            '10' => "октября",
            '11' => "ноября",
            '12' => "декабря",
        );
        return date('j', $d) . ' ' . $months[date('m', $d)] . ' ' . date('Y', $d);
    }
}

function get_clicks_rows($params, $start = 0, $start_s = 0, $limit = 0, $campaign_params, $click_params)
{

    $more = 0; // Записей больше нет, этот запрос крайний

    $spot_ids = array(); // Споты, в которых будем искать данные
    // Вставка для Turbo режима
    if (_CLICKS_SPOT_SIZE > 0)
    {
        $spot_ids = clicks_spot_get($params['from'], $params['to']);

        if (empty($spot_ids)) {
            return array(0, array(), array(), array());
        }

        $clicks_table = "tbl_clicks_s" . $spot_ids[$start_s];
    }
    else
    {
        $clicks_table = "tbl_clicks";
    }

    // Применяем фильтры
    if (!empty($params['filter'][0]) and is_array($params['filter'][0]))
    {
        $tmp = array();
        foreach ($params['filter'][0] as $k => $v) {
            if ($k == 'referer') {
                if ($v == '{empty}') {
                    $tmp[] = "`" . $k . "` = ''";
                } else {
                    $tmp[] = "`" . $k . "` LIKE '%" . mysql_real_escape_string($v) . "%'";
                }
            } elseif ($k == 'ads_name') {
                list($campaign_name, $ads_name) = explode('-', $v);
                $tmp[] = "`campaign_name` = '" . mysql_real_escape_string($campaign_name) . "'";
                $tmp[] = "`ads_name` = '" . mysql_real_escape_string($ads_name) . "'";
            } elseif ($k == 'source_name' and empty($v)) {
                $tmp[] = "(`source_name` = '' or `source_name` = 'source' or `source_name` = 'SOURCE' or `source_name` = '{empty}')";
            } else {
                if ($v == '{empty}') {
                    $v = '';
                }
                $tmp[] = "`" . $k . "` = '" . mysql_real_escape_string($v) . "'";
            }
        }
        if (!empty($tmp)) {
            $where = ' and (' . join(' and ', $tmp) . ')';
        } else {
            $where = '';
        }
    } else {
        $where = '';
    }

    // Дополнительные поля для режима популярных параметров
    if ($params['mode'] == 'popular' or 1) {
        $select = ', out_id, source_name, ads_name, referer, user_os, user_ip, user_platform, user_browser, country, state, city, isp, campaign_param1, campaign_param2, campaign_param3, campaign_param4, campaign_param5 ';
        for ($i = 1; $i <= 15; $i++) {
            $select .= ', click_param_value' . $i . ' ';
        }
    } else {
        $select = '';
    }

    if (strlen($params['from']) == 10) {
        $params['from'] .= ' 00:00:00';
    }

    if (strlen($params['to']) == 10) {
        $params['to'] .= ' 23:59:59';
    }

    if (empty($params['cache']))
    { // Кэш мы считаем без смещения по часовому поясу
        $timezone_shift = get_current_timezone_shift(); // Смещение часового пояса
        $where .= " and CONVERT_TZ(t1.`date_add`, '+00:00', '" . _str($timezone_shift) . "') BETWEEN STR_TO_DATE('" . $params['from'] . "', '%Y-%m-%d %H:%i:%s') AND STR_TO_DATE('" . $params['to'] . "', '%Y-%m-%d %H:%i:%s')";
        $time_add = "UNIX_TIMESTAMP(CONVERT_TZ(t1.`date_add`, '+00:00', '" . _str($timezone_shift) . "')) as `time_add`,";
    } else {
        $where .= " and t1.`date_add` BETWEEN STR_TO_DATE('" . $params['from'] . "', '%Y-%m-%d %H:%i:%s') AND STR_TO_DATE('" . $params['to'] . "', '%Y-%m-%d %H:%i:%s')";
        $time_add = "UNIX_TIMESTAMP(t1.`date_add`) as `time_add`,";
    }

    // Выбираем все переходы за период
    $q = "SELECT " . (empty($params['group_by']) ? '' : " " . mysql_real_escape_string($params['group_by']) . " as `name`, ") .
            (($params['group_by'] == $params['subgroup_by'] or empty($params['subgroup_by'])) ? '' : " " . mysql_real_escape_string($params['subgroup_by']) . ", ") .
            "	1 as `cnt`,
            t1.id,
            t1.source_name,
            " . $time_add . "
            t1.rule_id,
            t1.out_id,
            t1.parent_id,
            t1.campaign_name,
            t1.click_price,
            t1.is_unique,
            t1.conversion_price_main,
            t1.is_sale,
            t1.is_lead,
            t1.is_parent,
            t1.is_connected " . $select . "
            FROM `" . $clicks_table . "` t1
            WHERE 1 " . $where . (empty($params['where']) ? '' : " and " . $params['where'] ) . "
            ORDER BY t1.id ASC
            LIMIT $start, $limit";

    if ($rs = db_query($q) and mysql_num_rows($rs) > 0) {
        while ($r = mysql_fetch_assoc($rs)) {
            $rows[$r['id']] = $r;

            // Определяем наличие пользовательских параметров
            for ($i = 1; $i <= 5; $i++) {
                if ($r['campaign_param' . $i] != '') {
                    $campaign_params[$i] = 1;
                }
            }

            for ($i = 1; $i <= 15; $i++) {
                if ($r['click_param_value' . $i] != '') {
                    $click_params[$i] = 1;
                }
            }
        }

        // Мы получили максимальное число строчек
        if (count($rows) == $limit) {
            $start += $limit;
            $more = 1;
        } elseif (($start_s + 1) < count($spot_ids)) {
            $start = 0;
            $start_s++;
            $more = 1;
        }
        // Нет тут больше строчек
    } else {
        // Если спот не последний - смещаем указатель
        if (($start_s + 1) < count($spot_ids)) {
            $start = 0;
            $start_s++;
            $more = 1;
        }
    }

    return array($more, $start, $start_s, $rows, $campaign_params, $click_params);
}

function php_date_default_timezone_set($GMT) {
    $timezones = array(
        '-12:00' => 'Pacific/Kwajalein',
        '-11:00' => 'Pacific/Samoa',
        '-10:00' => 'Pacific/Honolulu',
        '-09:00' => 'America/Juneau',
        '-08:00' => 'America/Los_Angeles',
        '-07:00' => 'America/Denver',
        '-06:00' => 'America/Mexico_City',
        '-05:00' => 'America/New_York',
        '-04:00' => 'America/Caracas',
        '-03:30' => 'America/St_Johns',
        '-03:00' => 'America/Argentina/Buenos_Aires',
        '-02:00' => 'Atlantic/Azores',
        '-01:00' => 'Atlantic/Azores',
        '+00:00' => 'Europe/London',
        '+01:00' => 'Europe/Paris',
        '+02:00' => 'Europe/Helsinki',
        '+03:00' => 'Europe/Moscow',
        '+03:30' => 'Asia/Tehran',
        '+04:00' => 'Asia/Baku',
        '+04:30' => 'Asia/Kabul',
        '+05:00' => 'Asia/Karachi',
        '+05:30' => 'Asia/Calcutta',
        '+06:00' => 'Asia/Colombo',
        '+07:00' => 'Asia/Bangkok',
        '+08:00' => 'Asia/Singapore',
        '+09:00' => 'Asia/Tokyo',
        '+09:00' => 'Australia/Darwin',
        '+10:00' => 'Pacific/Guam',
        '+11:00' => 'Asia/Magadan',
        '+12:00' => 'Asia/Kamchatka'
    );

    date_default_timezone_set($timezones[$GMT]);

    return date_default_timezone_get();
}

/**
 * Подготовка данных для отчётов:
 * subtype - колонка, по которой группируем данные (то же, что и group_by, если не задан limited_to)
 * limited_to - фильтр по subtype
 * group_by - группировка второго уровня, если задан limited_to
 * type - hourly, daily, monthly с каким шагом собираем статистику
 * from, to - временные рамки, за которые нужна статистика, обязательно в формате Y-m-d H:i:s
 * where - дополнительные условия выборки кликов
 * mode - режим выборки и группировки: offers, landings, lp_offers
 * cache - разрешить использовать кэш, 1 - из кэша, 2 - для кэша (без часового пояса)
 */
function get_clicks_report_grouped2($params)
{
    global $group_types;

    // Флаги существующих параметров
    $campaign_params = array(
        1 => 0, 0, 0, 0, 0
    );

    $click_params = array(
        1 => 0, 0, 0, 0, 0,
        0, 0, 0, 0, 0,
        0, 0, 0, 0, 0
    );

    // По временным промежуткам
    $date_formats = array(
        'hour' => 'H',
        'day' => 'Y-m-d',
        'month' => 'm.Y'
    );

    $groups = array(
        '00' => 'click',
        '01' => 'lead',
        '10' => 'sale',
        '11' => 'sale_lead'
    );

    // Смещение часового пояса
    $timezone_shift = get_current_timezone_shift();
    $timezone_shift_sec = get_current_timezone_shift(true);

    $timezone_backup = date_default_timezone_get();

    // Поправка на разницу времени PHP и Базы 
    $timezone_shift_sec += strtotime(mysql_now()) - time();

    $rows = array(); // все клики за период
    $data = array(); // сгруппированные данные
    $data2 = array();
    $arr_dates = array(); // даты для отчёта

    if ($params['part'] == 'month') {
        $arr_dates = getMonthsBetween($params['from'], $params['to']);
    } elseif ($params['part'] == 'day') {
        $arr_dates = getDatesBetween($params['from'], $params['to']);
    } elseif ($params['part'] == 'hour') {
        $arr_dates = getHours24();
    }

    global $pop_sort_by, $pop_sort_order;
    $pop_sort_by = 'cnt';
    $pop_sort_order = 1;

    // Режим показов конвертаций, все, только действия, только продажи, только лиды, без конвертаций.
    // В отчете "популярных параметров" этот фильтр работает ТОЛЬКО как параметр сортировки, в других режимах как условие для WHERE

    if ($params['conv'] != 'all') {
        if ($params['mode'] == 'popular') {
            if ($params['conv'] == 'sale') {
                $pop_sort_by = 'sale';
            } elseif ($params['conv'] == 'lead') {
                $pop_sort_by = 'lead';
            } elseif ($params['conv'] == 'act') {
                $pop_sort_by = 'act';
            } elseif ($params['conv'] == 'none') {
                $pop_sort_by = $params['col'];
                $pop_sort_order = -1;
            }
        } else {
            /*
              Если так сделать - то не посчитаются клики
              if($params['conv'] == 'sale') {
              $params['where'] = '`is_sale` = 1';
              } elseif($params['conv'] == 'lead') {
              $params['where'] = '`is_lead` = 1';
              } elseif($params['conv'] == 'act') {
              $params['where'] = '(`is_sale` = 1 or `is_lead` = 1)';
              } elseif($params['conv'] == 'none') {
              //$params['where'] = '';
              $params['where'] = '`is_sale` = 0 and `is_lead` = 0';
              }
             */
        }
    }

    $parent_clicks = array(); // массив для единичного зачёта дочерних кликов (иначе у нас LP CTR больше 100% может быть)

    $limit = 5000;

    $start = 0;
    $start_s = 0;
    $more = 1;

    // Используем кэш
    if ($params['cache'] == 1)
    {
        list($data, $click_params, $campaign_params) = load_from_cache($params);
        $load_live_data = 0;
    }
    else
    {
        $load_live_data = 1;
    }

    if ($load_live_data)
    {
        while ($more)
        {
            $rows = array();

            // Получаем порцию данных
            list($more, $start, $start_s, $rows, $campaign_params, $click_params) = get_clicks_rows($params, $start, $start_s, $limit, $campaign_params, $click_params);


            // Режим обработки для Landing Page
            // группируем всю информацию с подчинённых переходов на родительские
            if ($params['mode'] == 'lp' or $params['mode'] == '') {
                foreach ($rows as $k => $r) {
                    if ($r['parent_id'] > 0) { // ссылка на оффер
                        if (parent_row($r['parent_id'], 'id') == 0) {
                            unset($rows[$k]); // не найден лэндинг, удаляем переход
                            continue;
                        }
                        // не будем считать более одного исходящего с лэндинга
                        $out_calc = isset($parent_clicks[$r['parent_id']]) ? 0 : 1;
                        $parent_clicks[$r['parent_id']] = 1;

                        // исходящие
                        $rows[$r['parent_id']]['out'] += $out_calc;
                    }
                }
            }

            if ($params['mode'] == 'lp_offers') {

                foreach ($rows as $k => $r) {
                    if ($r['parent_id'] > 0) { // ссылка на оффер
                        // Несём продажи наверх, к лэндингу
                        $rows[$r['parent_id']]['is_sale'] += $r['is_sale'];
                        $rows[$r['parent_id']]['is_lead'] += $r['is_lead'];
                        $rows[$r['parent_id']]['conversion_price_main'] += $r['conversion_price_main'];

                        // А расходы вниз, к офферу
                        $rows[$k]['click_price'] += $rows[$r['parent_id']]['click_price'];

                        // Считаем исходящие для лэндингов
                        $out_calc = isset($parent_clicks[$r['parent_id']]) ? 0 : 1;
                        $parent_clicks[$r['parent_id']] = 1;

                        $rows[$r['parent_id']]['out'] += $out_calc;
                    }
                }
            }


            // Фильтры показа
            if (!empty($params['filter'][1])) {
                $parent_clicks2 = array(); // $parent_clicks у нас для исходящих, а тут костыль (
                $rows_new = array(); // сюда будем складывать новые строчки, вместо unset существующих
                foreach ($rows as $k => $v) {

                    if ($v['parent_id'] > 0) {
                        if (empty($parent_clicks2[$v['parent_id']])) {
                            $parent_clicks2[$v['parent_id']] = 1;
                        } else {
                            continue;
                        }
                    }

                    //dmp($params['filter'][1]);

                    $viz_filter = 1;

                    foreach ($params['filter'][1] as $name => $value) {
                        list($cur_val, $parent_val) = explode('|', $value);

                        if ($name == 'referer') {
                            $v[$name] = param_key($v, $name);
                        }

                        if (
                                $params['subgroup_by'] == 'out_id' and (

                                ($parent_val == 0 and ($v[$name] == $cur_val or parent_row($v['parent_id'], $name) == $cur_val))
                                or ($parent_val > 0 and ($v['parent_id'] > 0 and parent_row($v['parent_id'], $name) == $parent_val) and $v[$name] == $cur_val))
                                or ($v[$name] == $cur_val and (empty($parent_val) or $v[$params['group_by']] == $parent_val))
                        ) {
                            /*
                              $lp_offers_valid[$cur_val] = 1;

                              // Сбрасываем parent_id, чтобы оффер у нас был как бы "самостоятельный", без лэндинга. Иначе придётся дорабатывать шаблон отчёта
                              if($parent_val > 0) {
                              $v['parent_id'] = 0;
                              }
                              $rows_new[$k] = $v;
                             */
                            //dmp($v);
                        } else {
                            $viz_filter = 0;
                            break;
                        }
                    }

                    if ($viz_filter) {
                        //echo '1';
                        $lp_offers_valid[$cur_val] = 1;

                        // Сбрасываем parent_id, чтобы оффер у нас был как бы "самостоятельный", без лэндинга. Иначе придётся дорабатывать шаблон отчёта
                        if ($parent_val > 0) {
                            $v['parent_id'] = 0;
                        }
                        $rows_new[$k] = $v;
                    }
                }

                //dmp($rows_new);

                $rows = $rows_new;

                unset($rows_new); // Прибираемся
                unset($parent_clicks2);
            }

            //dmp($rows);
            // Режим популярных значений
            // Вынесен в отдельное условие из-за особой обработки по дням и месяцам
            if ($params['mode'] == 'popular') {
                foreach ($rows as $r) {
                    foreach ($group_types as $k => $v) {
                        $name = param_key($r, $k);

                        $data[$k][$name]['cnt'] += $r['cnt'];
                        $data[$k][$name]['price'] += $r['click_price'];
                        $data[$k][$name]['unique'] += $r['is_unique'];
                        $data[$k][$name]['income'] += $r['conversion_price_main'];
                        $data[$k][$name]['sale'] += $r['is_sale'];
                        $data[$k][$name]['lead'] += $r['is_lead'];
                        $data[$k][$name]['act'] += ($r['is_lead'] + $r['is_sale']);
                        $data[$k][$name]['out'] += $r['out'];

                        // Продажи + Лиды = Действия.
                        $sl = $r['is_sale'] + $r['is_lead'];
                        if ($sl > 2)
                            $sl = 2; // Не более двух на переход

                        $data[$k][$name]['sale_lead'] += $sl;

                        // Если это не общий режим - добавляем информацию о датах
                        if ($params['part'] != 'all') {

                            //$k1 = (trim($r['name']) == '' ? '{empty}' : $r['name']);
                            $k2 = date($date_formats[$params['part']], $r['time_add']);
                            //$k3 = $groups[$r['is_sale'].$r['is_lead']];
                            /*
                              $data2[$k][$name][$k2][$k3]['cnt'] += 1;
                              $data2[$k][$name][$k2][$k3]['cost'] += $r['clicks_price'];
                              $data2[$k][$name][$k2][$k3]['earnings'] += $r['conversions_sum'];
                              $data2[$k][$name][$k2][$k3]['is_parent_cnt'] += $r['is_parent'];
                             */
                            $data2[$k][$name][$k2]['cnt'] += 1;
                            $data2[$k][$name][$k2]['cost'] += $r['clicks_price'];
                            $data2[$k][$name][$k2]['earnings'] += $r['conversions_sum'];
                            $data2[$k][$name][$k2]['is_parent_cnt'] += $r['is_parent'];

                            stat_inc($data2[$k][$name][$k2], $r, $name, $r['name']);
                        }
                    }
                }

                // Режим показа группировки офферов и лэндингов
                // Тоже вынесен в отдельное условие из-за особой обработки по дням и месяцам
            } elseif ($params['mode'] == 'lp_offers') {

                $parent_clicks = array(); // массив для единичного зачёта дочерних кликов (иначе у нас LP CTR больше 100% может быть)
                // Вся статистика, без разбиения по времени
                foreach ($rows as $r) {

                    $k = param_key($r, $params['group_by']);
                    $name = param_val($r, $params['group_by']);

                    if (!isset($data[$k])) {
                        $data[$k] = array(
                            'id' => $k,
                            'name' => $name,
                            'price' => 0,
                            'unique' => 0,
                            'income' => 0,
                            'direct' => 0,
                            'sale' => 0,
                            'lead' => 0,
                            'out' => 0,
                            'cnt' => 0,
                            'sale_lead' => 0,
                        );
                    }

                    // Продажи + Лиды = Действия. 
                    $r['sale_lead'] = $r['is_sale'] + $r['is_lead'];
                    if ($r['sale_lead'] > 2)
                        $r['sale_lead'] = 2; // Не более одного на переход

                        
// Подчиненные связи будут формироваться не по parent_id перехода,
                    // а через другие параметры этого перехода (например через источники, с которых пришли)
                    // Лэндинг 1
                    // ├ Источник 1
                    // └ Источник 2

                    if ($params['subgroup_by'] != $params['group_by']) {

                        if ($r['parent_id'] == 0) {
                            $k1 = param_key($r, $params['subgroup_by']);
                            $r['name'] = param_val($r, $params['subgroup_by']);

                            // Общая часть статистики
                            stat_inc($data[$k]['sub'][$k1], $r, $k1, $r['name']);

                            // Выдаём офферу разрешение на показ (тут ведь у нас лэндинги, просто так не покажем)
                            $lp_offers_valid[$k] = 1;

                            // Информация о датах 
                            if ($params['part'] != 'all') {
                                $timekey = date($date_formats[$params['part']], $r['time_add']);

                                stat_inc($data[$k]['sub'][$k1][$timekey], $r, $k1, $r['name']);
                            }
                        } else {
                            // Будем считать исходящий только если у этого родителя его ещё нет
                            $r['cnt'] = isset($parent_clicks[$r['parent_id']]) ? 0 : 1;
                            $parent_clicks[$r['parent_id']] = 1;

                            // Отмечаем исходящий для лэндинга
                            if ($r['cnt']) {
                                $parent_row = parent_row($r['parent_id']);
                                $k0 = param_key($parent_row, $params['group_by']);

                                $data[$k0]['out'] += 1;
                            }
                            continue;
                        }
                    }

                    // Подчиненные связи будут формироваться по parent_id перехода
                    // Лэндинг 1
                    // ├ Оффер 1
                    // └ Оффер 2

                    if ($r['parent_id'] > 0) {
                        // Будем считать исходящий только если у этого родителя его ещё нет
                        $r['cnt'] = isset($parent_clicks[$r['parent_id']]) ? 0 : 1;
                        $parent_clicks[$r['parent_id']] = 1;

                        $parent_row = parent_row($r['parent_id']);
                        $k0 = param_key($parent_row, $params['group_by']);

                        $k1 = param_key($r, $params['subgroup_by']);
                        $name = param_val($r, $params['subgroup_by']);

                        stat_inc($data[$k0]['sub'][$k1], $r, $k1, $name);

                        // Отмечаем исходящий для лэндинга
                        if ($r['cnt']) {
                            $data[$k0]['out'] += 1;
                        }

                        $data[$k]['order'] = 1;

                        // Выдаём офферу разрешение на показ
                        $lp_offers_valid[$k0] = 1;
                        $lp_offers_valid[$k1] = 1;

                        // Запрошена информация по дням
                        if ($params['part'] != 'all') {

                            $k2 = date($date_formats[$params['part']], $r['time_add']);

                            $id = param_key($r, $params['subgroup_by']);
                            $name = param_val($r, $params['subgroup_by']);

                            stat_inc($data[$k0]['sub'][$k1][$k2], $r, $id, $name);
                        }

                        // Обычный инкремент статистики
                    } else {
                        stat_inc($data[$k], $r, $k, $name);

                        // Информация о датах
                        if ($params['part'] != 'all') {
                            $timekey = date($date_formats[$params['part']], $r['time_add']);
                            stat_inc($data[$k][$timekey], $r, $k, $name);
                        }
                    }
                }

                //dmp($data);
                /*                 * ********** */
            } else {
                // Данные выбраны, начинаем группировку
                // Статистика за весь период
                if ($params['part'] == 'all') {

                    $parent_clicks = array(); // массив для единичного зачёта дочерних кликов (иначе у нас LP CTR больше 100% может быть)
                    // Вся статистика, без разбиения по времени
                    foreach ($rows as $r) {
                        $k = param_key($r, $params['group_by']);
                        $name = param_val($r, $params['group_by']);

                        // Продажи + Лиды = Действия. 
                        $r['sale_lead'] = $r['is_sale'] + $r['is_lead'];
                        if ($r['sale_lead'] > 2)
                            $r['sale_lead'] = 2; // Не более одного на переход

                        stat_inc($data[$k], $r, $k, $name);
                    }

                    // Статистика по дням
                } else {

                    //echo mysql_now() . ' ' . time() . ' ' . date('Y-m-d H:i:s'). '<br>' ;

                    foreach ($rows as $r) {
                        $k1 = param_key($r, $params['group_by']);

                        $timekey = date($date_formats[$params['part']], $r['time_add']);

                        stat_inc($data[$k1], $r, $k1, $r['name']);
                        stat_inc($data[$k1][$timekey], $r, $k1, $r['name']);
                    }
                }
            } // Стандартный режим
        } // Цикличный сбор данных из БД

    }

    // ----------------------------------------
    // Постобработка, когда ВСЕ данные получены
    // ----------------------------------------
    //if($params['part'] == 'all') {
    if ($params['mode'] == 'popular') {

        if ($params['group_by'] != '') {

            foreach ($data as $k => $v) {
                if ($k != $params['group_by']) {
                    unset($data[$k]);
                } else {
                    $total = sum_arr($v, 'cnt');
                    foreach ($data[$k] as $k1 => $v1) {
                        $data[$k][$k1]['total'] = $total;
                    }
                }
            }
        } else {
            //dmp($data);
            foreach ($data as $k => $v) {
                uasort($v, 'params_order');

                $data[$k] = current($v);

                // Для этого режима нам нужны ТОЛЬКО нулевые конвертации
                if ($params['conv'] == 'none' and $data[$k][$params['col']] != 0) {
                    unset($data[$k]);
                    continue;
                }

                $data[$k]['total'] = sum_arr($v, 'cnt');
                $data[$k]['name'] = $k;
                $data[$k]['popular'] = current(array_keys($v));
            }
        }

        // Убираем из популярных "не определено", отфильрованные значения и если 100%

        foreach ($data as $k => $r) {
            if ($r['popular'] == $group_types[$r['name']][1]
                    or $r['popular'] == ''
                    or !empty($params['filter'][0][$r['name']])
                    or ($r['cnt'] == $r['total'] or round($r['cnt'] / $r['total'] * 100) == 100)
            ) {
                unset($data[$k]);
            }
        }

        if ($params['part'] != 'all') {
            $data3 = array();
            foreach ($data as $k => $v) {

                //$name = $group_types[$v['name']][1];
                $name = $v['name'];

                $data3[$name] = $data2[$k][$v['popular']];
                $data3[$name]['popular'] = $v['popular'];
            }
            unset($data2);
            $data = $data3;
        }
    } else {

        // Убираем строчки с конверсиями
        $data = conv_filter($data, $params['conv']);

        // "Один источник" - если группировка по источнику и он у нас один, то берём его именованные параметры
        if ($params['group_by'] == 'source_name' and count($data) == 1) { //
            global $one_source;
            $one_source = current(array_keys($data));
        }
    }
    //}

    if ($part != 'all') {
        // Оставляем только те даты, за которые есть данные
        $arr_dates = strip_empty_dates($arr_dates, $data);
    }

    // Особая сортировка для режима lp_offers, офферы с прямыми переходами в конце
    if ($params['mode'] == 'lp_offers') { //and $params['part'] == 'all'
        uasort($data, 'lp_order');

        //dmp($data); //111
        $lp_offers_valid = array_keys($lp_offers_valid);
        $ln = 0; // номер лэндинга - условное значение, необходимое для группировки при сортировке таблицы с подчиненными офферами. У лэндинга и его офферов должен быть один номер, уникальный для этой группы
        foreach ($data as $k => $v) {
            if ((!in_array($k, $lp_offers_valid) and $v['direct'] == 0) or $v['cnt'] == 0) {
                unset($data[$k]);
            } else {
                $data[$k]['ln'] = $ln;
                if (!empty($data[$k]['sub'])) {
                    foreach ($data[$k]['sub'] as $k0 => $v0) {
                        $data[$k]['sub'][$k0]['ln'] = $ln;
                    }
                }
                $ln++;
            }
        }
    }

    // Удаляем страницы, у которых нет исходящих (Это не Лэндинги)
    //and $params['part'] == 'all'
    if (($params['mode'] == 'lp') and empty($parent_val)) {
        foreach ($data as $k => $v) {
            if (empty($v['out']) and empty($v['direct'])) {
                unset($data[$k]);
            }
        }
    }

    // cсылка "Другие", для Площадки, параметров ссылки и перехода 
    // если не выбран какой-то определенный лэндинг.
    //
		
    global $pop_sort_by, $pop_sort_order;
    $max_sub = 50; // После скольки объектов начинаем сворачивать

    if ($params['no_other'] == 0
            and !isset($params['filter'][1]['out_id'])
            and (

            (($params['subgroup_by'] == 'referer' and $params['mode'] == 'lp_offers') or ($params['group_by'] == 'referer' and $params['mode'] == ''))
            or strstr($params['subgroup_by'], 'click_param_value') !== false)
    ) {


        if ($params['mode'] == 'lp_offers') {
            foreach ($data as $k => &$v) {
                if (isset($v['sub']) and count($v['sub']) > $max_sub) {
                    uasort($v['sub'], 'sub_order');

                    $sub = array_slice($v['sub'], $max_sub);
                    $v['sub'] = array_slice($v['sub'], 0, $max_sub);

                    $other = array(); // Сюда мы соберём всю статистику "других"
                    foreach ($sub as $sub_row) {
                        stat_inc($other, $sub_row, -1, 'Другие');
                    }
                    $v['sub'][-1] = $other;

                    //dmp($other);
                }
            }
        } elseif (($params['mode'] == '' or $params['mode'] == 'lp') and count($data) > $max_sub) {


            $pop_sort_by = 'cnt';
            $pop_sort_order = 1;

            uasort($data, 'params_order');

            $other_arr = array_slice($data, $max_sub);
            foreach ($other_arr as $row) {
                if (($params['mode'] == '' and empty($row['out']))
                        or ($params['mode'] == 'lp' and !empty($row['out']))
                ) {
                    foreach ($row as $k => $v) {
                        if (is_array($v)) {
                            foreach ($v as $d => $vd) {
                                $other[$k][$d] += $vd;
                            }
                        } else {
                            $other[$k] += $v;
                        }
                    }
                }
            }

            $data = array_slice($data, 0, $max_sub);

            $other['id'] = -1;
            $other['name'] = 'Другие';
            $data[-1] = $other;
        }
    }


    return array(
        'data' => $data,
        'dates' => $arr_dates,
        'click_params' => $click_params,
        'campaign_params' => $campaign_params
    );
}

// Сортировка по кликам 
function sub_order($a, $b) {
    if ($a['cnt'] == $b['cnt']) {
        return 0;
    }
    return ($a['cnt'] < $b['cnt']) ? 1 : -1;
}

// Суммирует значения из двухмерного массива
function sum_arr($arr, $param = 'cnt') {
    $summ = 0;
    foreach ($arr as $v) {
        $summ += $v[$param];
    }
    return $summ;
}

// Сортировка лэндингов
function lp_order($a, $b) {
    if ($a['order'] == $b['order']) {
        return 0;
    }
    return ($a['order'] < $b['order']) ? -1 : 1;
}

// Сортировка по конверсии
function params_order($a, $b) {
    global $pop_sort_by, $pop_sort_order;

    $k1 = $a[$pop_sort_by];
    $k2 = $b[$pop_sort_by];
    if ($k1 == $k2) {
        // Вторичная сортировка по переходам
        if ($pop_sort_by != 'cnt') {
            $k1 = $a['cnt'];
            $k2 = $b['cnt'];
            if ($k1 == $k2) {
                return 0;
            }
            return ($k1 < $k2) ? 1 : -1;
        } else {
            return 0;
        }
    }
    return ($k1 < $k2) ? $pop_sort_order * 1 : $pop_sort_order * -1;
}

/* Генерируем данные с возможностью переключения колонок (дневной режим) 
 * emp  - показывать пустую ячейку, если значение равно 0
 * sub  - данные иерархически организованы (отчёт "целевые страницы")
 * cols - предустановленный набор колонок для загрузки, двухмерный массив вида:
 * $cols = array(
 * 	'act'   => array('cnt', 'conversion_a', 'roi', 'epc', 'profit'),
 * 	'sale'  => array('cnt', 'conversion',   'roi', 'epc', 'profit'),
 * 	'lead'  => array('cnt', 'conversion_l', 'cpl')
 * );
 */

function get_clicks_report_element2($data, $emp = true, $sub = true, $cols = false)
{
    global $report_cols;
    $out = array();

    // Используем только пользовательские колонки, если они определены
    if ($cols and is_array($cols))
    {
        $data_cols = array();
        foreach ($cols as $type => $type_cols) {
            foreach ($type_cols as $col) {
                if (!isset($data_cols[$col])) {
                    $data_cols[$col] = $report_cols[$col];
                }
            }
        }
    } else {
        $data_cols = $report_cols; // все доступные колонки
    }

    foreach ($data_cols as $col => $options) {

        // С иерархически организованными данными используется функция sortdata для корректной сортировки по всем уровням
        if ($sub) {
            $out[] = '<span class="timetab sdata ' . $col . '">' . sortdata($col, $data, $emp) . '</span>';
        } else {
            $func = 't_' . $col;
            $out[] = '<span class="timetab sdata ' . $col . '">' . $func($data, true, $emp) . '</span>';
        }
    }
    return join('', $out);
}

/* /v2 */

function get_sales($from, $to, $days, $month) {
    $timezone_shift = get_current_timezone_shift();
    $sql = "SELECT *, `cnv`.`date_add` as `date` 
        FROM `tbl_conversions` `cnv` 
        LEFT JOIN `tbl_clicks` `clc` ON `cnv`.`subid` = `clc`.`subid`  
        WHERE (`cnv`.`status` = 0 or `cnv`.`status` = 1)
            AND CONVERT_TZ(`cnv`.`date_add`, '+00:00', '" . _str($timezone_shift) . "') BETWEEN STR_TO_DATE('" . _str($from) . " 00:00:00', '%Y-%m-%d %H:%i:%s') 
            AND STR_TO_DATE('" . _str($to) . " 23:59:59', '%Y-%m-%d %H:%i:%s') 
        ORDER BY `cnv`.`date_add` ASC";

    $r = mysql_query($sql);

    if (mysql_num_rows($r) == 0) {
        return false;
    }

    $data = array();
    $return = array();

    while ($f = mysql_fetch_assoc($r)) {
        $data[] = $f;
    }

    foreach ($data as $row) {
        if ($row['source_name'] == '') {
            $row['source_name'] = '_';
        }
        foreach ($days as $day) {
            $d = (!$month) ? date('d.m', strtotime($day)) : $day;
            if ($d == date((!$month) ? 'd.m' : 'm.Y', strtotime($row['date']))) {
                $return[$row['source_name']][$d]++;
            }
        }
    }

    return $return;
}

/*
 * Убираем даты, за которые нет данных
 */

function strip_empty_dates($arr_dates, $arr_report_data, $mode = 'date') {
    $dates = array();
    $begin = false;

    if ($mode == 'group') {
        $arr_report_data = current($arr_report_data);
    }

    foreach ($arr_report_data as $source_name => $data) {
        foreach ($data as $k => $v) {
            if ($mode == 'month')
                $k = date('m.Y', strtotime($k));
            $dates[$k] = 1;
        }
    }

    foreach ($arr_dates as $k => $v) {
        if (!isset($dates[$v]) and !$begin)
            unset($arr_dates[$k]);
        else
            $begin = true;
    }
    return $arr_dates;
}

/*
 * Готовит к выводу параметры перехода
 */

function params_list($row, $name, $source_name = '') {
    global $source_config;

    // Если есть фильтр по источнику - считаем именованные параметры
    if (!empty($source_config[$source_name]['params'])) {
        $named_params = $source_config[$source_name]['params'];
        $named_params_cnt = count($named_params);
        $named_params_keys = array_keys($named_params);
    } else {
        $named_params_cnt = 0;
    }

    $out = array();
    for ($i = 1; $i <= 15; $i++) {
        if (empty($row[$name . $i]))
            continue;

        list($param_name, $param_val) = click_param($i, $row[$name . $i], $source_name);
        /*
          if($i <= $named_params_cnt) {
          $param_name = $named_params[$named_params_keys[$i]]['name'];
          } else {
          $param_name = $i - $named_params_cnt;
          }
         */
        $out[] = $param_name . ': ' . $param_val;
    }
    /*
      $i = 1;

      while(isset($row[$name.$i])) {
      if($row[$name.$i] != '') {
      $out[] = $i.': '.$row[$name.$i] . '<br />';
      }
      $i++;
      } */
    return $out;
}

/*
 * Функция вывода кнопок статистики в интерфейс
 */

function type_subpanel() {
    global $type;

    // Кнопки типов статистики
    $type_buttons = array(
        'all_stats' => 'Все',
        'daily_stats' => 'По дням',
        'monthly_stats' => 'По месяцам',
    );

    $out = array();
    foreach ($type_buttons as $k => $v) {
        $out[] = '<a href="?act=reports&type=' . $k . '&subtype=' . $_GET['subtype'] . '" type="button" class="btn btn-default ' . ($type == $k ? 'active' : '') . '">' . $v . '</a>';
    }
    return $out;
}

// Литералы для группировок
$group_types = array(
    'out_id' => array('Оффер', 'Без оффера', 'Офферы'),
    'rule_id' => array('Ссылка', 'Без ссылки', 'Ссылки'),
    'source_name' => array('Источник', 'Не определён', 'Источники'),
    'campaign_name' => array('Кампания', 'Не определена', 'Кампании'),
    'ads_name' => array('Объявление', 'Не определено', 'Объявления'),
    'referer' => array('Площадка', 'Не определена', 'Площадки'),
    'user_os' => array('ОС', 'Не определена', 'ОС'),
    'user_platform' => array('Платформа', 'Не определена', 'Платформы'),
    'user_browser' => array('Браузер', 'Не определен', 'Браузеры'),
    'country' => array('Страна', 'Не определена', 'Страны'),
    'state' => array('Регион', 'Не определен', 'Регионы'),
    'city' => array('Город', 'Не определен', 'Города'),
    'user_ip' => array('IP адрес', 'Не определен', 'IP адреса'),
    'isp' => array('Провайдер', 'Не определен', 'Провайдеры'),
    'campaign_param1' => array('Параметр ссылки #1', 'Не определен', 'Параметр ссылки #1'),
    'campaign_param2' => array('Параметр ссылки #2', 'Не определен', 'Параметр ссылки #2'),
    'campaign_param3' => array('Параметр ссылки #3', 'Не определен', 'Параметр ссылки #3'),
    'campaign_param4' => array('Параметр ссылки #4', 'Не определен', 'Параметр ссылки #4'),
    'campaign_param5' => array('Параметр ссылки #5', 'Не определен', 'Параметр ссылки #5'),
    'click_param_value1' => array('Параметр перехода #1', 'Не определен', 'Параметр перехода #1'),
    'click_param_value2' => array('Параметр перехода #2', 'Не определен', 'Параметр перехода #2'),
    'click_param_value3' => array('Параметр перехода #3', 'Не определен', 'Параметр перехода #3'),
    'click_param_value4' => array('Параметр перехода #4', 'Не определен', 'Параметр перехода #4'),
    'click_param_value5' => array('Параметр перехода #5', 'Не определен', 'параметр перехода #5'),
    'click_param_value6' => array('Параметр перехода #6', 'Не определен', 'Параметр перехода #6'),
    'click_param_value7' => array('Параметр перехода #7', 'Не определен', 'Параметр перехода #7'),
    'click_param_value8' => array('Параметр перехода #8', 'Не определен', 'Параметр перехода #8'),
    'click_param_value9' => array('Параметр перехода #9', 'Не определен', 'Параметр перехода #9'),
    'click_param_value10' => array('Параметр перехода #10', 'Не определен', 'Параметр перехода #10'),
    'click_param_value11' => array('Параметр перехода #11', 'Не определен', 'Параметр перехода #11'),
    'click_param_value12' => array('Параметр перехода #12', 'Не определен', 'Параметр перехода #12'),
    'click_param_value13' => array('Параметр перехода #13', 'Не определен', 'Параметр перехода #13'),
    'click_param_value14' => array('Параметр перехода #14', 'Не определен', 'Параметр перехода #14'),
    'click_param_value15' => array('Параметр перехода #15', 'Не определен', 'Параметр перехода #15'), /*
          'cp1'  => array('Параметр перехода #1', 'Не определен', 'параметру #1'),
          'cp2'  => array('Параметр перехода #2', 'Не определен', 'параметру #2'),
          'cp3'  => array('Параметр перехода #3', 'Не определен', 'параметру #3'),
          'cp4'  => array('Параметр перехода #4', 'Не определен', 'параметру #4'),
          'cp5'  => array('Параметр перехода #5', 'Не определен', 'параметру #5'),
          'cp6'  => array('Параметр перехода #6', 'Не определен', 'параметру #6'),
          'cp7'  => array('Параметр перехода #7', 'Не определен', 'параметру #7'),
          'cp8'  => array('Параметр перехода #8', 'Не определен', 'параметру #8'),
          'cp9'  => array('Параметр перехода #9', 'Не определен', 'параметру #9'),
          'cp10' => array('Параметр перехода #10', 'Не определен', 'параметру #10'),
          'cp11' => array('Параметр перехода #11', 'Не определен', 'параметру #11'),
          'cp12' => array('Параметр перехода #12', 'Не определен', 'параметру #12'),
          'cp13' => array('Параметр перехода #13', 'Не определен', 'параметру #13'),
          'cp14' => array('Параметр перехода #14', 'Не определен', 'параметру #14'),
          'cp15' => array('Параметр перехода #15', 'Не определен', 'параметру #15'), */
);

/*
 * Ссылка согласно параметрам отчёта
 */

function report_lnk($params, $set = false) {
    if ($set and is_array($set)) {
        foreach ($set as $k => $v) {
            if ($k == 'filter') {
                $k = 'filter_str';
            }
            $params[$k] = $v;
        }
    }


    $tmp = array();

    foreach ($params['filter_str'] as $k => $v) {
        $tmp[] = $k . ':' . $v;
    }
    $vars = array(
        'act' => 'reports',
        'filter' => join(';', $tmp),
        'type' => $params['type'],
        'part' => $params['part'],
        'group_by' => $params['group_by'],
        'subgroup_by' => $params['subgroup_by'],
        'conv' => $params['conv'],
        'mode' => $params['mode'],
        'col' => $params['col'],
        'from' => $params['from'],
        'to' => $params['to'],
        'no_other' => $params['no_other']
    );

    return '?' . http_build_query($vars);
}

/*
 * Формируем параметры отчёта из REQUEST-переменных
 */

function report_options() {
    global $group_types;
    // Дешифруем фильтры
    $tmp_filters = rq('filter');
    $filter = array(0 => array(), 1 => array());
    $filter_str = array();

    if (!empty($tmp_filters)) {
        $tmp_filters = explode(';', $tmp_filters);
        foreach ($tmp_filters as $tmp_filter) {
            list($k, $v, $type) = explode(':', $tmp_filter);
            $type = intval($type);
            if (array_key_exists($k, $group_types)) {
                $filter[$type][$k] = $v;
                $filter_str[$k] = $v . ':' . $type;
            }
        }
    }

    $part = rq('part', 0, 'day');

    // Устанавливаем даты по умолчанию
    switch ($part) {
        case 'month':
            $from = date('Y-m-01', strtotime(get_current_day('-6 months')));
            $to = date('Y-m-t', strtotime(get_current_day()));
            break;
        default:
            $from = get_current_day('-6 days');
            $to = get_current_day();
            break;
    }

    $group_by = rq('group_by', 0, 'out_id');
    $subgroup_by = rq('subgroup_by', 0, $group_by);
    $conv = rq('conv', 0, 'all');
    $mode = rq('mode', 0, '');
    $col = rq('col', 0, 'act');

    // Если эта группировка уже затронута фильтром - выбираем следующую по приоритету
    // Примечание: в отчёте по целевым можно не выбирать
    if ($mode != 'lp') {
        $i = 0;
        $group_types_keys = array_keys($group_types);
        while (!empty($filter) and array_key_exists($group_by, $filter)) {
            $group_by = $group_types_keys[$i];
            $i++;
        }
    }
    /*
      for($i = 0; empty($filter) or array_key_exists($group_by, $filter); $i++) {
      $group_by = $group_types_keys[$i];
      } */

    // Готовим параметры для отдачи
    $v = array(
        'type' => rq('type', 0, 'basic'),
        'part' => rq('part', 0, 'all'),
        'filter' => $filter,
        'filter_str' => $filter_str,
        'group_by' => $group_by,
        'subgroup_by' => $subgroup_by,
        'conv' => $conv,
        'mode' => $mode,
        'col' => $col,
        'from' => rq('from', 4, $from),
        'to' => rq('to', 4, $to),
        'no_other' => rq('no_other', 2),
        'cache' => ((_CLICKS_SPOT_SIZE > 0 and empty($_GET['nocache'])) ? 1 : 0)
    );
    return $v;
}

// Набор функций для вычисления и форматирования показателей в отчётах
function t_price($r, $wrap = true, $emp = true) {
    $r['price'] = round($r['price'], 2);
    return currencies_span($r['price'], $wrap);
}

function t_lpctr($r, $wrap = true, $emp = true) {
    if (!empty($r['cnt'])) {
        $out = round($r['out'] / $r['cnt'] * 100, 1);
        return $wrap ? $out . '%' : $out;
    } else {
        return '';
    }
}

function t_income($r, $wrap = true, $emp = true) {
    return currencies_span($r['income'], $wrap);
}

function t_epc($r, $wrap = true, $emp = true) {
    return empty($r['cnt']) ? 0 : currencies_span(round2($r['income'] / $r['cnt']), $wrap);
}

function t_profit($r, $wrap = true, $emp = true) {
    return currencies_span(round2($r['income'] - $r['price']), $wrap);
}

function t_roi($r, $wrap = true, $emp = true) {
    $out = empty($r['price']) ? 0 : round(($r['income'] - $r['price']) / $r['price'] * 100, 1);
    return $wrap ? $out . '%' : $out;
}

function t_conversion($r, $wrap = true, $emp = true) {
    if ($r['sale'] == 0)
        return $wrap ? ($emp ? '' : '0') : 0;
    $out = round2($r['sale'] / $r['cnt'] * 100);
    return $wrap ? $out . '%' : $out;
}

function t_conversion_l($r, $wrap = true, $emp = true) {
    if ($r['lead'] == 0)
        return $wrap ? ($emp ? '' : '0') : 0;
    $out = round2($r['lead'] / $r['cnt'] * 100);
    return $wrap ? $out . '%' : $out;
}

function t_conversion_a($r, $wrap = true, $emp = true) {
    if ($r['act'] == 0)
        return $wrap ? ($emp ? '' : '0') : 0;
    $out = round2($r['act'] / $r['cnt'] * 100);
    return $wrap ? $out . '%' : $out;
}

function t_follow($r, $wrap = true, $emp = true) {
    $out = round($r['out'] / $r['cnt'] * 100, 1);
    return $wrap ? $out . '%' : $out;
}

function t_cps($r, $wrap = true, $emp = true) {
    return currencies_span(empty($r['sale']) ? 0 : round2($r['price'] / $r['sale']), $wrap);
}

function t_cpa($r, $wrap = true, $emp = true) {
    //return currencies_span($r['price'] / $r['act'], $wrap);
    return currencies_span(empty($r['act']) ? 0 : round2($r['price'] / $r['act']), $wrap);
}

function t_cpl($r, $wrap = true, $emp = true) {
    return currencies_span(round2(empty($r['lead']) ? 0 : ($r['price'] / $r['lead'])), $wrap);
}

function t_repeated($r, $wrap = true, $emp = true) {

    $repeated = $r['cnt'] - $r['unique'];
    //if($repeated < 0 or $repeated == 0) return $wrap ? '' : 0;
    if ($repeated < 0)
        $repeated = 0;

    $repeated = empty($r['cnt']) ? 0 : round($repeated / $r['cnt'] * 100, 1);
    return $wrap ? (($emp && $repeated <= 0) ? '' : $repeated . '%') : $repeated;
}

function t_cnt($r, $wrap = true, $emp = true) {
    return empty($r['cnt']) ? ($emp ? '' : '0') : $r['cnt'];
}

function t_sale($r, $wrap = true, $emp = true) {
    if ($r['sale'] == 0)
        return $wrap ? '' : 0;
    return $r['sale'];
}

function t_lead($r, $wrap = true, $emp = true) {
    if ($r['lead'] == 0)
        return $wrap ? '' : 0;
    return $r['lead'];
}

function t_act($r, $wrap = true, $emp = true) {
    if ($r['act'] == 0)
        return ($wrap && $emp) ? '' : 0;
    return $r['act'];
}

function t_cnt_sale($r, $wrap = true, $emp = true) {
    if ($r['sale'] == 0)
        return t_cnt($r, $wrap, $emp);
    return $wrap ? '<b>' . $r['cnt'] . ':' . $r['sale'] . '</b>' : $r['sale'] * 10000000 + $r['cnt'];
}

function t_cnt_lead($r, $wrap = true, $emp = true) {
    if ($r['lead'] == 0)
        return t_cnt($r, $wrap, $emp);
    return $wrap ? '<b>' . $r['cnt'] . ':' . $r['lead'] . '</b>' : $r['lead'] * 10000000 + $r['cnt'];
}

function t_cnt_act($r, $wrap = true, $emp = true) {
    if ($r['act'] == 0)
        return t_cnt($r, $wrap, $emp);
    return $wrap ? '<b>' . $r['cnt'] . ':' . $r['act'] . '</b>' : $r['act'] * 10000000 + $r['cnt'];
}

function t_sale_lead($r, $wrap = true, $emp = true) {
    if ($r['sale_lead'] == 0)
        return $wrap ? '' : 0;
    return $r['sale_lead'];
}

function cur_conv($n, $currency = 'RUB') {
    global $currencies;
    $curr_rates = array(
        'RUB' => $currencies['rub'],
    );
    // Нет такой валюты

    if (array_key_exists($currency, $curr_rates)) {
        return 0;
    }
    return $n * $curr_rates[$currency];
}

function currencies_span($v, $wrap = true) {
    if (!$wrap)
        return $v;
    global $currencies;
    $rub_rate = $currencies['rub'];
    $style = '';
    if (empty($v)) {
        $style = 'style="color:lightgray;font-weight:normal;"';
    } elseif ($v < 0) {
        $style = 'style="color:red;"';
    }
    return '<b><span class="sdata usd" ' . $style . '>' . ($v < 0 ? '-' : '') . '$' . abs($v) . '</span><span class="sdata rub" ' . $style . '>' . round($v * $rub_rate) . 'р.</span></b>';
}

function click_param($n, $val, $source_name) {
    global $source_config;
    if (!empty($source_config[$source_name]['params'])) {
        $named_params = $source_config[$source_name]['params'];
        $named_params_cnt = count($named_params);
        $named_params_keys = array_keys($named_params);
    } else {
        $named_params_cnt = 0;
    }

    if ($n <= $named_params_cnt) {
        $param_name = $named_params[$named_params_keys[$n - 1]]['name'];
        if (!empty($named_params[$named_params_keys[$n - 1]]['list']) and
                !empty($named_params[$named_params_keys[$n - 1]]['list'][$val])) {
            $val = $named_params[$named_params_keys[$n - 1]]['list'][$val];
        }
    } else {
        $param_name = '#' . ($n - $named_params_cnt);
    }
    return array($param_name, $val);
}

// Значение поля для рассчётов, например площадка
// http://site.ru/topic1/page1.html станет site.ru

function param_key($row, $type) {

    if (!is_array($row)) {
        $row = array($type => $row);
    }

    if (trim($row[$type]) != '') {
        // Обрезаем реферер до домена
        if ($type == 'referer') {
            $url = parse_url($row[$type]);
            $out = $url['host'];

            // Для объявления добавляем кампанию
        } elseif ($type == 'ads_name') {
            if ($row[$type] != '' and ($row[$type] != 'ads' or $row['campaign_name'] != 'campaign')) {
                $out = ($row['campaign_name'] . '-' . $row[$type]);
            } else {
                $out = '';
            }
        } elseif ($type == 'campaign_name') {
            if ($row[$type] != 'campaign') {
                $out = $row[$type];
            } else {
                $out = '';
            }
        } elseif ($type == 'out_id') {
            if ($row[$type] == '{empty}') {
                $out = '';
            } else {
                $out = $row[$type];
            }
        } elseif ($type == 'source_name') {
            if ($row[$type] == 'source' or $row[$type] == 'SOURCE') {
                $out = '';
            } else {
                $out = $row[$type];
            }
        } else {
            $out = $row[$type];
        }
    } else {
        $out = '';
    }

    return $out;
}

// Вливаем информацию о переходе в массив статистики 

function stat_inc(&$arr, $r, $id, $name) {
    if (!isset($arr)) {
        $arr = array(
            'id' => $id,
            'name' => $name,
            'price' => 0,
            'unique' => 0,
            'income' => 0,
            'direct' => 0,
            'sale' => 0,
            'lead' => 0,
            'act' => 0,
            'out' => 0,
            'cnt' => 0,
            'sale_lead' => 0,
        );
    }
    $arr['id'] = $id;
    $arr['name'] = $name;
    $arr['sale'] += $r['is_sale'];
    $arr['lead'] += $r['is_lead'];
    $arr['act'] += ($r['is_lead'] + $r['is_sale']);
    $arr['cnt'] += $r['cnt'];
    $arr['price'] += $r['click_price'];
    $arr['unique'] += $r['is_unique'];
    $arr['income'] += $r['conversion_price_main'];
    $arr['direct'] += ($r['rule_id'] == 0 and $r['time_add'] > 1419463566) ? 1 : 0;
    $arr['out'] += $r['is_connected'];
    $arr['sale_lead'] += $r['sale_lead'];
}

// Складываем дневную статистику для подведения итогов по строкам и колонкам
function stat_inc_total($cur_date, $row) {
    global $row_total_data, $column_total_data, $table_total_data;
    if (empty($row))
        return false;
    foreach ($row as $k => $v) {
        if (is_array($v))
            continue;

        // Служебные колонки ln (landing number) и order не должны суммироваться, но переносятся в итоговую статистику
        if ($k == 'order' or $k == 'ln') {
            $row_total_data[$k] = $v;
            continue;
        }
        $row_total_data[$k] += $v;
        $column_total_data[$cur_date][$k] += $v;
        $table_total_data[$k] += $v;
    }
}

// Значение поля для отображения пользователю, например
// out_id "10" становится названием ссылки "Ссылка 1",
// а источник popunder станет Popunder.ru
// нам нужно обрабатывать рефереров, имена объявлений, специальные параметры

function param_val($row, $type, $source_name = '') {
    global $group_types, $source_config;
    static $outs = array();
    static $links = array();

    $name = '';
    if (is_array($row)) {
        $v = $row[$type];
        $source_name = $row['source_name'];
    } else {
        $v = $row;
    }

    // Ссылка "Другие" для площадок и пользовательских параметров
    if (is_other_link($v, $type)) {
        $name = 'Другие';
    } else {
        if ($type == 'referer') {
            if (substr($v, 0, 4) == 'http' or strstr($v, '/') !== false) {
                $name = parse_url($v);
                $name = $name['host'];
            } else {
                $name = $v;
            }
        } elseif ($type == 'source_name') {
            if ($v == 'source' or $v == 'SOURCE') { // значение по умолчанию
                $name = '';
            } else {
                $name = empty($source_config[$v]['name']) ? $v : $source_config[$v]['name'];
            }
        } elseif ($type == 'ads_name') {
            if ($v != '') {
                $name = is_array($row) ? ($row['campaign_name'] . '-' . $row['ads_name']) : $row;
            }
        } elseif ($type == 'out_id') {
            if (isset($outs[$v])) {
                $name = $outs[$v];
            } else {
                $name = current(get_out_description($v));
                $outs[$v] = $name;
            }
        } elseif ($type == 'rule_id') {
            if (isset($links[$v])) {
                $name = $links[$v];
            } else {
                $name = get_rule_description($v);
                $links[$v] = $name;
            }
        } else {
            // Специальные поля, определённые для источника в виде списка
            if (!empty($source_config[$source_name]['params'])
                    and strstr($type, 'click_param_value') !== false) {
                $n = intval(str_replace('click_param_value', '', $type));
                $i = 1;
                foreach ($source_config[$source_name]['params'] as $param) {
                    if ($i == $n and !empty($param['list'][$v])) {
                        $name = str_replace(' ', '&nbsp;', $param['list'][$v]);
                        return $name;
                    }
                    $i++;
                }
                $name = $v;
            } else {
                $name = $v;
            }
        }
    }

    if (trim($name) == ''
            or $name == '{empty}'
            or ($type == 'campaign_name' and $name == 'campaign')
            or ($type == 'ads_name' and $name == 'campaign-ads'))
        $name = $group_types[$type][1];

    return $name;
}

/*
 * Название параметра (если пользовательский (click_param_value1-15) - зависит от источника)
 */

function param_name($type, $source = '', $only_name = false) {
    global $source_config, $group_types;

    $n = intval(str_replace('click_param_value', '', $type));

    // Если есть фильтр по источнику - считаем именованные параметры
    if (!empty($source) and !empty($source_config[$source]['params'])) {
        $named_params_cnt = count($source_config[$source]['params']);
    } else {
        $named_params_cnt = 0;
    }

    if (strstr($type, 'click_param_value') !== false and $named_params_cnt > 0) {
        $i = 1;
        foreach ($source_config[$source]['params'] as $v) {
            if ($i == $n) {
                $name = str_replace(' ', '&nbsp;', $v['name']);
                if ($only_name) {
                    return $name;
                }
                return $name;
            }
            $i++;
        }
    }

    if ($only_name) {
        if (strstr($type, 'click_param_value') !== false) {
            return 'Параметр #' . ($n - $named_params_cnt);
        } else {
            return $group_types[$type][2];
        }
    }

    $name = $group_types[$type][0];
    $name = str_replace('Параметр перехода', 'ПП', $name);
    $name = str_replace('Параметр ссылки', 'ПС', $name);
    $name = str_replace('#' . $n, '#' . ($n - $named_params_cnt), $name);
    return $name;
}

/**
 * Название ведущей колонки в отчёте (для специальных настроек источников)
 */
function col_name($params, $only_name = false) {

    // Для режима подчинённых страниц нужно брать второй уровень (subgroup_by)
    $group_by = ($params['mode'] == 'lp_offers') ? $params['subgroup_by'] : $params['group_by'];
    return param_name($group_by, $params['filter'][0]['source_name'], $only_name);
}

/*
 * фрагмент данных для сортировки подчинённых офферов (режим lp_offers)
 * Вид: order|val|ln|offer|val_offer
 * order - 1 для офферов с прямыми переходами и 0 для всех остальных, офферы всегда внизу
 * val   - значение ячейки лэндинга (у оффера - значение родительской ячейки)
 * ln    - номер группы, одинаковый у лэндинга и всех его подчиненённых офферов
 * offer - флаг оффера, 0 для лэндинга, 1 для оффера, сортируется всегда так чтобы лэндинг был вверху
 * val_offer - значение ячейки оффера (для лэндинга пустое)
 */

function sortdata($col_name, $data, $emp = false) {
    //dmp($data);
    //print_r($data);
    //static $l; // счётчик лэндингов
    $r = $data['r'];
    $parent = $data['parent'];
    $func = 't_' . $col_name;
    $tmp = array(
        intval($data['r']['order'])
            //empty($data['r']['sub']) ? 0 : 1 // есть ли подчинённые
    );

    $val0 = intval($func($r, false, false));
    $val = $func($r, true, $emp);

    if ($col_name == 'cnt' and $r['sale_lead'] > 0 and $data['part'] != 'all') {
        $val = $val . ':' . $r['sale_lead'];
        $val0 += ($r['sale_lead'] * 10000000);
    }

    if (!empty($parent)) {
        //dmp($parent);
        if ($col_name == 'cnt' and $data['part'] != 'all') {
            // В дневном режиме особый режим переноса родительских переходов
            $tmp[] = intval($func($parent, false) + ($parent['sale_lead'] * 10000000)); // значение лэндинга
        } else {
            $tmp[] = intval($func($parent, false)); // значение лэндинга
        }
        $tmp[] = $data['r']['ln']; // номер лэндинга
        $tmp[] = 1; // это оффер
        $tmp[] = $val0;
    } else {
        //$l[$col_name]++;
        $tmp[] = $val0;
        $tmp[] = $data['r']['ln']; // номер лэндинга
        $tmp[] = 0; // это лэндинг
    }

    return '<span class="sortdata">' . join('|', $tmp) . '|</span>' . $val;
}

/*
 * Мы загружаем данные частями и иногда получается так, что родительский клик мы загрузили, а подчиненный - нет, или наоборот. Лезем прямо в базу и проверяем наличие клика
 */

function parent_row($id, $name = '') {
    global $rows;
    if (empty($id))
        return 0;

    if (!isset($rows[$id])) {

        // Turbo
        if (_CLICKS_SPOT_SIZE > 0) {
            // Определяем, в каком споте у нас будет этот клик
            $spot_id = ceil($id / _CLICKS_SPOT_SIZE);
            $clicks_table = 'tbl_clicks_s' . $spot_id;
        } else {
            $clicks_table = 'tbl_clicks';
        }

        $q = "select * from `" . $clicks_table . "` where `id` = '" . intval($id) . "' limit 1";

        //echo $q. '<br >';
        if ($rs = db_query($q) and mysql_num_rows($rs) > 0) {
            $row = mysql_fetch_assoc($rs);
        } else {
            return 0;
        }
    } else {
        $row = $rows[$id];
    }
    return empty($name) ? $row : $row[$name];
}

/*
 * Получаем самую первую ссылку из правила
 */

function get_first_rule_link($rule_id) {
    $q = "select `tbl_offers`.`id`, `offer_tracking_url` 
				from `tbl_rules_items`
				left join `tbl_offers` on value = tbl_offers.id
				where `rule_id` = '" . intval($rule_id) . "'
					and `type` = 'redirect'
				order by `tbl_rules_items`.`id`
				limit 1";
    $rs = db_query($q);
    $r = mysql_fetch_assoc($rs);
    return array($r['id'], $r['offer_tracking_url']);
}

/*
 * Фильтруем конверсии
 */

function conv_filter($data, $conv = 'none') {
    switch ($conv) {
        case 'none':
            foreach ($data as $k => $v) {
                if ($v['sale_lead'] > 0)
                    unset($data[$k]);
                if (isset($v['sub']))
                    $data[$k]['sub'] = conv_filter($v['sub'], $conv);
            }
            break;
        case 'act':
            foreach ($data as $k => $v) {
                if ($v['act'] == 0 and $v['sale'] == 0 and $v['lead'] == 0)
                    unset($data[$k]);
                if (isset($v['sub']))
                    $data[$k]['sub'] = conv_filter($v['sub'], $conv);
            }
            break;
        case 'sale':
            foreach ($data as $k => $v) {
                if ($v['sale'] == 0)
                    unset($data[$k]);
                if (isset($v['sub']))
                    $data[$k]['sub'] = conv_filter($v['sub'], $conv);
            }
            break;
        case 'lead':
            foreach ($data as $k => $v) {
                if ($v['lead'] == 0)
                    unset($data[$k]);
                if (isset($v['sub']))
                    $data[$k]['sub'] = conv_filter($v['sub'], $conv);
            }
            break;
    }
    return $data;
}

/*
 * Массив из 24 часов
 */

function getHours24() {
    $hours = array(
        '00', '01', '02', '03', '04', '05',
        '06', '07', '08', '09', '10', '11',
        '12', '13', '14', '15', '16', '17',
        '18', '19', '20', '21', '22', '23',
    );
    return $hours;
}

/*
 * Ба! Да это же у нас ссылка "Другие"!
 */

function is_other_link($val, $type) {
    return ($val == -1 and ($type == 'referer' or strstr($type, 'click_param_value') !== false));
}

/**
 * Числовые формы (трекер, трекера, трекеров)
 * @param int число
 * @param array значения для 1, 3 и 12
 * @return string
 */
function numform($n, $expr) {
    if (empty($expr[2]))
        $expr[2] = $expr[1];
    //$i=preg_replace('/[^0-9]+/s','',$digit)%100; //intval не всегда корректно работает
    $i = intval($n) % 100; //intval всегда корректно работает
    if ($i >= 5 and $i <= 20)
        return $expr[2];
    else {
        $i%=10;
        if ($i == 1)
            $res = $expr[0];
        elseif ($i >= 2 && $i <= 4)
            $res = $expr[1];
        else
            $res = $expr[2];
    }
    return trim($res);
}

function type_subpanel2($params, $type, $mode = '')
{
    // Кнопки типов статистики
    $type_buttons = array(
        'all' => 'Все',
        'day' => 'По дням',
        'month' => 'По месяцам',
    );

    $out = '';

    foreach ($type_buttons as $k => $v)
    {
        $add_params = array(
            'part' => $k,
            'type' => $type,
            'mode' => $mode
        );

        // Дефолтные параметры для переключения на дни и месяцы
        if ($k == 'month')
        {
            $add_params['from'] = date('Y-m-01', strtotime(get_current_day('-6 months')));
            $add_params['to'] = date('Y-m-t', strtotime(get_current_day()));
        }
        elseif ($k == 'day' and $params['part'] == 'month')
        {
            $add_params['from'] = get_current_day('-6 days');
            $add_params['to'] = get_current_day();
        }

        echo '<li class="' . ($params['part'] == $k ? 'active' : '') . '"><a href="' . report_lnk($params, $add_params) . '">' . $v . '</a></li>';
    }
    return $out;
}