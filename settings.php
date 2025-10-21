<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_dashboard', get_string('pluginname', 'local_dashboard'));

    // Toggle redirect
    $settings->add(new admin_setting_configcheckbox('local_dashboard/enabledredirect',
        get_string('enabledredirect', 'local_dashboard'),
        get_string('enabledredirect_desc', 'local_dashboard'),
        1));

    // Announcements fallback (com suporte a HTML)
    $settings->add(new admin_setting_confightmleditor('local_dashboard/announcementsfallback',
        get_string('announcementsfallback', 'local_dashboard'),
        get_string('announcementsfallback_desc', 'local_dashboard'),
        ''));
    
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
