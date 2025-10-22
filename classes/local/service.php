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

        // Informações e Avisos importantes - usando apenas configuração fallback
        $announcements = [];
        
        $fallback = get_config('local_dashboard', 'announcementsfallback');
        if (!empty($fallback)) {
            // Processa HTML no fallback
            $fallback_text = format_text($fallback['text'] ?? $fallback, FORMAT_HTML, ['context' => \context_system::instance()]);
            $announcements[] = [
                'title' => get_string('important_info', 'local_dashboard'),
                'excerpt' => shorten_text(strip_tags($fallback_text), 140),
                'fulltext' => $fallback_text, // Texto completo com HTML
                'time' => userdate(time(), get_string('strftimedatetime', 'langconfig')),
                'url' => '#'
            ];
        }

        // Banners/Imagens configuráveis
        $banners = [];
        for ($i = 1; $i <= 4; $i++) {
            $banner_file = get_config('local_dashboard', "banner{$i}_file");
            $banner_alt = get_config('local_dashboard', "banner{$i}_alt");
            $banner_link = get_config('local_dashboard', "banner{$i}_link");
            
            if (!empty($banner_file)) {
                // Gera URL do arquivo
                $syscontext = \context_system::instance();
                $fs = get_file_storage();
                $files = $fs->get_area_files($syscontext->id, 'local_dashboard', "banner{$i}", 0, 'sortorder', false);
                
                if (!empty($files)) {
                    $file = reset($files);
                    $banner_url = $CFG->wwwroot . '/pluginfile.php/' . $syscontext->id . '/local_dashboard/banner' . $i . '/0/' . $file->get_filename();
                    
                    $banners[] = [
                        'url' => $banner_url,
                        'alt' => $banner_alt ?: "Banner {$i}",
                        'link' => $banner_link ?: '#',
                        'haslink' => !empty($banner_link),
                        'number' => $i
                    ];
                }
            }
        }

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
            
            // Buscar detalhes das últimas 5 conversas (independente de serem lidas)
            // As conversas já vêm ordenadas por última atividade
            foreach (array_slice($conversations, 0, 5) as $conversation) {
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
                    
                    $messages[] = [
                        'id' => $conversation->id,
                        'name' => fullname($otheruser),
                        'lastmessage' => $message_text,
                        'sendername' => $sender_name,
                        'timeago' => $lastmessage ? userdate($lastmessage->timecreated, get_string('strftimerecent')) : '',
                        'unread' => $conversation->unreadcount > 0, // True se tem mensagens não lidas
                        'unreadcount' => $conversation->unreadcount,
                        'url' => $CFG->wwwroot . '/message/index.php?id=' . $otheruser->id
                    ];
                }
            }
        }

        return [
            'courses' => $coursesarr,
            'coursesempty' => empty($coursesarr),
            'announcements' => $announcements,
            'banners' => $banners,
            'messages' => $messages,
            'messagesempty' => empty($messages),
            'totalunreadconversations' => $totalunreadconversations,
        ];
    }
}
