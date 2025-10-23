<?php
/**
 * Hook para redirecionar da página inicial (index.php) para o dashboard
 * Este arquivo deve ser incluído no index.php principal
 */

defined('MOODLE_INTERNAL') || die();

// Verificar se o redirecionamento está habilitado
$enabled = get_config('local_dashboard', 'enabledredirect');
if (!empty($enabled)) {
    
    // Verificar se não é um usuário guest
    if (!isguestuser($USER)) {
        
        // Verificar se não estamos vindo do próprio dashboard (evitar loop)
        $from_dashboard = optional_param('pp_redirect', 0, PARAM_INT);
        if (!$from_dashboard) {
            
            // Redirecionar para o dashboard independentemente do parâmetro redirect
            $url = new moodle_url('/local/dashboard/index.php', ['pp_redirect' => 1]);
            redirect($url);
        }
    }
}