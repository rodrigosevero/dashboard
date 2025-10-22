<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_dashboard', get_string('pluginname', 'local_dashboard'));

    // Toggle redirect
    $settings->add(new admin_setting_configcheckbox('local_dashboard/enabledredirect',
        get_string('enabledredirect', 'local_dashboard'),
        get_string('enabledredirect_desc', 'local_dashboard'),
        1));

    // Announcements fallback (com suporte a HTML e upload de imagens)
    $settings->add(new admin_setting_confightmleditor('local_dashboard/announcementsfallback',
        get_string('announcementsfallback', 'local_dashboard'),
        get_string('announcementsfallback_desc', 'local_dashboard'),
        '', PARAM_RAW, 60, 8, [
            'maxfiles' => 10,
            'maxbytes' => 0,
            'trusttext' => false,
            'subdirs' => false,
            'context' => context_system::instance()
        ]));
    
    // Banners/Imagens para o card de informações
    $settings->add(new admin_setting_heading('banners_heading', 
        get_string('banners_heading', 'local_dashboard'), 
        get_string('banners_heading_desc', 'local_dashboard')));
    
    // Banner 1
    $settings->add(new admin_setting_configstoredfile('local_dashboard/banner1_file',
        get_string('banner1_url', 'local_dashboard'),
        get_string('banner_file_desc', 'local_dashboard'),
        'banner1',
        0,
        array('maxfiles' => 1, 'accepted_types' => array('.jpg', '.jpeg', '.png', '.gif', '.webp'))));
    
    $settings->add(new admin_setting_configtext('local_dashboard/banner1_alt',
        get_string('banner1_alt', 'local_dashboard'),
        get_string('banner_alt_desc', 'local_dashboard'),
        '', PARAM_TEXT));
    
    $settings->add(new admin_setting_configtext('local_dashboard/banner1_link',
        get_string('banner1_link', 'local_dashboard'),
        get_string('banner_link_desc', 'local_dashboard'),
        '', PARAM_URL));
    
    // Banner 2
    $settings->add(new admin_setting_configstoredfile('local_dashboard/banner2_file',
        get_string('banner2_url', 'local_dashboard'),
        get_string('banner_file_desc', 'local_dashboard'),
        'banner2',
        0,
        array('maxfiles' => 1, 'accepted_types' => array('.jpg', '.jpeg', '.png', '.gif', '.webp'))));
    
    $settings->add(new admin_setting_configtext('local_dashboard/banner2_alt',
        get_string('banner2_alt', 'local_dashboard'),
        get_string('banner_alt_desc', 'local_dashboard'),
        '', PARAM_TEXT));
    
    $settings->add(new admin_setting_configtext('local_dashboard/banner2_link',
        get_string('banner2_link', 'local_dashboard'),
        get_string('banner_link_desc', 'local_dashboard'),
        '', PARAM_URL));
    
    // Banner 3
    $settings->add(new admin_setting_configstoredfile('local_dashboard/banner3_file',
        get_string('banner3_url', 'local_dashboard'),
        get_string('banner_file_desc', 'local_dashboard'),
        'banner3',
        0,
        array('maxfiles' => 1, 'accepted_types' => array('.jpg', '.jpeg', '.png', '.gif', '.webp'))));
    
    $settings->add(new admin_setting_configtext('local_dashboard/banner3_alt',
        get_string('banner3_alt', 'local_dashboard'),
        get_string('banner_alt_desc', 'local_dashboard'),
        '', PARAM_TEXT));
    
    $settings->add(new admin_setting_configtext('local_dashboard/banner3_link',
        get_string('banner3_link', 'local_dashboard'),
        get_string('banner_link_desc', 'local_dashboard'),
        '', PARAM_URL));
    
    // Banner 4
    $settings->add(new admin_setting_configstoredfile('local_dashboard/banner4_file',
        get_string('banner4_url', 'local_dashboard'),
        get_string('banner_file_desc', 'local_dashboard'),
        'banner4',
        0,
        array('maxfiles' => 1, 'accepted_types' => array('.jpg', '.jpeg', '.png', '.gif', '.webp'))));
    
    $settings->add(new admin_setting_configtext('local_dashboard/banner4_alt',
        get_string('banner4_alt', 'local_dashboard'),
        get_string('banner_alt_desc', 'local_dashboard'),
        '', PARAM_TEXT));
    
    $settings->add(new admin_setting_configtext('local_dashboard/banner4_link',
        get_string('banner4_link', 'local_dashboard'),
        get_string('banner_link_desc', 'local_dashboard'),
        '', PARAM_URL));
    
    // Número máximo de anúncios a exibir
    $settings->add(new admin_setting_configtext('local_dashboard/maxannouncements',
        get_string('maxannouncements', 'local_dashboard'),
        get_string('maxannouncements_desc', 'local_dashboard'),
        3, PARAM_INT));

    // Support settings
    $settings->add(new admin_setting_configtext('local_dashboard/supportname',
        get_string('supportname', 'local_dashboard'), '', 'Suporte Acadêmico', PARAM_TEXT));

    $settings->add(new admin_setting_configtext('local_dashboard/supportemail',
        get_string('supportemail', 'local_dashboard'), '', '', PARAM_RAW_TRIMMED));

    $settings->add(new admin_setting_configtext('local_dashboard/supportphone',
        get_string('supportphone', 'local_dashboard'), '', '', PARAM_TEXT));

    $settings->add(new admin_setting_configtext('local_dashboard/supportwhatsapp',
        get_string('supportwhatsapp', 'local_dashboard'), get_string('supportwhatsapp_desc', 'local_dashboard'), '', PARAM_RAW_TRIMMED));

    $settings->add(new admin_setting_configtext('local_dashboard/supporthelpdesk',
        get_string('supporthelpdesk', 'local_dashboard'), get_string('supporthelpdesk_desc', 'local_dashboard'), '', PARAM_RAW_TRIMMED));

    $settings->add(new admin_setting_configtext('local_dashboard/supporthours',
        get_string('supporthours', 'local_dashboard'), '', 'Seg a Sex, 08:00-18:00', PARAM_TEXT));

    $ADMIN->add('localplugins', $settings);
}
