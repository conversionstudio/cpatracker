<?php
if (!$include_flag) {
    exit();
}
?>
<div class="sidebar-left<?php echo $menu_toggle_class; ?>">
    <!-- Button sidebar left toggle -->
    <div class="btn-collapse-sidebar-left icon-dynamic<?php echo $menu_icon_class; ?>"  data-toggle="tooltip" data-placement="bottom" title="Свернуть левое меню"></div>

    <ul class="sidebar-menu"<?php echo $menu_sidebar_style; ?>>
        <li>
            <a class="logo-brand" href="<?php echo _HTML_ROOT_PATH; ?>">
                <span>CPA </span>Tracker
            </a>
        </li>
        <li>
            <a href="#">Поддержка</a>
        </li>
        <li>
            <a href="#">Оплата</a>
        </li>
        <li>
            <a href="#">Уведомления</a>
            <ul class="submenu" style="display: block;">
                <li class="<?php echo $status == 0 ? 'active' : ''; ?>" >
                    <a href="?page=notifications">
                        Непрочитанные
                        <span class="span-sidebar" id="ntf_unread_cnt"><?php echo $user_ntf_unread_cnt;?></span>
                    </a>
                </li>
                <li class="<?php echo $status == -1 ? 'active' : ''; ?>">
                    <a href="?page=notifications&status=all">
                        Все уведомления
                        <span class="span-sidebar" id="ntf_cnt"><?php echo $user_ntf_cnt;?></span>
                    </a>
                </li>
            </ul>
        </li>
        <?php
        /*
        if ($bHideLeftSidebar !== true and is_array($arr_left_menu) and count($arr_left_menu) > 0) {
            foreach ($arr_left_menu as $cur) {
                $class = ($cur['is_active'] == 1) ? 'active' : '';
                ?><li class="<?php echo $class; ?>"><a href="<?php echo _e($cur['link']); ?>"><?php echo _e($cur['caption']); ?></a></li><?php
    }
}*/
        ?>
    </ul>
</div><!-- END SIDEBAR LEFT -->
<?php
echo load_plugin('demo', 'demo_well');
?>