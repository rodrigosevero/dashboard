<?php
namespace local_dashboard\local;

defined('MOODLE_INTERNAL') || die();

class service {
    public static function get_dashboard_data(\stdClass $user): array {
        global $CFG, $DB;
        require_once($CFG->libdir . '/enrollib.php');
        require_once($CFG->dirroot . '/lib/filelib.php');

        // Cursos em andamento do usuário com categorias.
        $courses = enrol_get_users_courses($user->id, true, 'id,shortname,fullname,startdate,enddate,visible,category');
        $coursesarr = [];
        $courseids = [];
        $coursesByCategory = [];
        
        foreach ($courses as $c) {
            if (!$c->visible) { continue; }
            
            // Buscar categoria do curso
            $category = $DB->get_record('course_categories', ['id' => $c->category], 'id,name,path');
            $categoryName = $category ? format_string($category->name) : get_string('uncategorized', 'moodle');
            
            // Inicializar array da categoria se não existir
            if (!isset($coursesByCategory[$categoryName])) {
                $coursesByCategory[$categoryName] = [];
            }
            
            // Adicionar curso à categoria
            $coursesByCategory[$categoryName][] = [
                'id' => $c->id,
                'fullname' => format_string($c->fullname),
                'url' => (new \moodle_url('/course/view.php', ['id' => $c->id]))->out(false),
            ];
            
            $courseids[] = $c->id;
        }
        
        // Ordenar categorias alfabeticamente e converter para formato do template
        ksort($coursesByCategory);
        foreach ($coursesByCategory as $categoryName => $courses) {
            $coursesarr[] = [
                'categoryname' => $categoryName,
                'courses' => $courses,
                'coursecount' => count($courses)
            ];
        }

        // Calendário Acadêmico (substitui anúncios)
        $calendario = self::get_calendario_data($user);

        // Mensagens do usuário
        $messages = [];
        $totalunreadconversations = 0;

        
        if (file_exists($CFG->dirroot . '/message/lib.php')) {
            require_once($CFG->dirroot . '/message/lib.php');
            
            // Buscar todas as conversas do usuário
            $conversations = \core_message\api::get_conversations($user->id, 0, 50);
            
            // Contar conversas com mensagens não lidas
            foreach ($conversations as $conversation) {
                if ($conversation->unreadcount > 0) {
                    $totalunreadconversations++;
                }
            }
            
            // Buscar apenas as conversas com mensagens não lidas (limitando a 5)
            // As conversas já vêm ordenadas por última atividade
            $unreadConversations = array_filter($conversations, function($conversation) {
                return $conversation->unreadcount > 0;
            });
            
            foreach (array_slice($unreadConversations, 0, 5) as $conversation) {
                // Verificar se o usuário ainda tem acesso à conversa
                if (!\core_message\api::is_user_in_conversation($user->id, $conversation->id)) {
                    continue;
                }
                
                $members = \core_message\api::get_conversation_members($user->id, $conversation->id);
                
                // Encontrar o outro usuário na conversa
                $otheruser = null;
                foreach ($members as $member) {
                    if ($member->id != $user->id) {
                        $otheruser = $member;
                        break;
                    }
                }
                
                if ($otheruser) {
                    // Buscar a última mensagem da conversa
                    // Parâmetros: userid, conversationid, limitfrom, limitnum, sort, timefrom
                    // sort = 'timecreated DESC' para pegar a mais recente primeiro
                    $lastmessages = \core_message\api::get_conversation_messages($user->id, $conversation->id, 0, 1, 'timecreated DESC', 0);
                    $lastmessage = !empty($lastmessages['messages']) ? reset($lastmessages['messages']) : null;
                    
                    // Determinar quem enviou a última mensagem
                    $sender_name = '';
                    $message_text = 'Nova mensagem';
                    
                    if ($lastmessage) {
                        $message_text = format_string($lastmessage->text);
                        
                        // Verificar se foi o usuário atual ou o outro usuário que enviou
                        if ($lastmessage->useridfrom == $user->id) {
                            $sender_name = 'Você: ';
                        } else {
                            // Buscar dados do remetente
                            $sender = $DB->get_record('user', ['id' => $lastmessage->useridfrom], 'id,firstname,lastname');
                            $sender_name = $sender ? fullname($sender) . ': ' : '';
                        }
                    }
                    
                    // Gerar URL correta para a conversa
                    $conversation_url = $CFG->wwwroot . '/message/index.php?convid=' . $conversation->id;
                    
                    $messages[] = [
                        'id' => $conversation->id,
                        'name' => fullname($otheruser),
                        'lastmessage' => $message_text,
                        'sendername' => $sender_name,
                        'timeago' => $lastmessage ? userdate($lastmessage->timecreated, get_string('strftimerecent')) : '',
                        'unread' => $conversation->unreadcount > 0, // True se tem mensagens não lidas
                        'unreadcount' => $conversation->unreadcount,
                        'url' => $conversation_url
                    ];
                }
            }
        }

        return [
            'courses' => $coursesarr,
            'coursesempty' => empty($coursesarr),
            'calendario' => $calendario,
            'hascalendario' => !empty($calendario['hassemestres']),
            'messages' => $messages,
            'messagesempty' => empty($messages),
            'totalunreadconversations' => $totalunreadconversations,
            'allmessagesurl' => $CFG->wwwroot . '/message/index.php',
        ];
    }
    
    /**
     * Get calendario academico data if plugin is available
     *
     * @param \stdClass $user User object
     * @return array Calendario widget data
     */
    private static function get_calendario_data(\stdClass $user): array {
        global $CFG;
        
        // Check if report_calendario plugin exists
        $calendario_lib = $CFG->dirroot . '/report/calendario/lib.php';
        if (!file_exists($calendario_lib)) {
            return ['hassemestres' => false];
        }
        
        require_once($calendario_lib);
        
        // Note: View capability check removed - calendar is visible to all logged-in users
        // The manage capability is still checked inside report_calendario_get_widget_data
        
        // Check if function exists
        if (!function_exists('report_calendario_get_widget_data')) {
            return ['hassemestres' => false];
        }
        
        // Get widget data - show all active semesters, both types
        return report_calendario_get_widget_data($user->id, 0, true, true);
    }
}

