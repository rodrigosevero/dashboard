<?php
namespace local_dashboard\local;

defined('MOODLE_INTERNAL') || die();

class service {
    public static function get_dashboard_data(\stdClass $user): array {
        global $CFG, $DB;
        require_once($CFG->libdir . '/enrollib.php');

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

        // Avisos/Informações importantes (Site news) - busca melhorada no fórum de anúncios.
        $announcements = [];
        $maxannouncements = (int) get_config('local_dashboard', 'maxannouncements') ?: 3;
        
        try {
            if (defined('SITEID')) {
                // Primeiro, tenta buscar fórum de site news
                $forum = $DB->get_record('forum', ['course' => SITEID, 'type' => 'news'], '*', IGNORE_MULTIPLE);
                
                // Se não encontrou, tenta buscar qualquer fórum chamado "Announcements" ou "Avisos"
                if (!$forum) {
                    $forums = $DB->get_records_sql(
                        "SELECT * FROM {forum} WHERE course = :siteid AND (name LIKE '%announcement%' OR name LIKE '%aviso%' OR name LIKE '%notícia%') ORDER BY id LIMIT 1",
                        ['siteid' => SITEID]
                    );
                    $forum = reset($forums);
                }
                
                if ($forum) {
                    $sql = "SELECT p.id, p.subject, p.message, p.modified, d.id AS did, d.name AS discussionname
                              FROM {forum_posts} p
                              JOIN {forum_discussions} d ON d.id = p.discussion
                             WHERE d.forum = :fid AND p.parent = 0 AND d.timemodified > :timefilter
                             ORDER BY p.modified DESC";
                    
                    // Só mostra anúncios dos últimos 30 dias
                    $timefilter = time() - (30 * 24 * 60 * 60);
                    $posts = $DB->get_records_sql($sql, ['fid' => $forum->id, 'timefilter' => $timefilter], 0, $maxannouncements);
                    
                    foreach ($posts as $p) {
                        $announcements[] = [
                            'title' => format_string($p->subject),
                            'excerpt' => shorten_text(strip_tags($p->message), 140),
                            'time' => userdate($p->modified, get_string('strftimedatetime', 'langconfig')),
                            'url' => (new \moodle_url('/mod/forum/discuss.php', ['d' => $p->did]))->out(false),
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {
            // ignora erros e usa fallback
        }

        // Fallback: usa mensagem de configurações se não houver anúncios (com suporte HTML)
        if (empty($announcements)) {
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
        }

        // Suporte técnico (config)
        $support = [
            'name' => (string) get_config('local_dashboard', 'supportname'),
            'email' => (string) get_config('local_dashboard', 'supportemail'),
            'phone' => (string) get_config('local_dashboard', 'supportphone'),
            'whatsapp' => (string) get_config('local_dashboard', 'supportwhatsapp'),
            'helpdesk' => (string) get_config('local_dashboard', 'supporthelpdesk'),
            'hours' => (string) get_config('local_dashboard', 'supporthours'),
        ];

        // Conversas não lidas e últimas mensagens (com cache para performance).
        $unread = 0;
        $recent_messages = [];
        try {
            if (class_exists('core_message\\api')) {
                // Tentar buscar do cache primeiro
                $cache = \cache::make('local_dashboard', 'unread_messages');
                $cachekey = "user_{$user->id}";
                $unread = $cache->get($cachekey);
                
                if ($unread === false) {
                    // Não está no cache, buscar do banco e cachear
                    // Usar query personalizada que funciona corretamente (API padrão tem problemas)
                    $unread = self::count_unread_conversations_custom($user->id);
                    $cache->set($cachekey, $unread);
                }
                
                // Buscar últimas mensagens (sempre busca fresco, não cacheia por ser mais dinâmico)
                $recent_messages = self::get_recent_messages($user->id, 5); // Últimas 5 mensagens
            }
        } catch (\Throwable $e) {
            $unread = 0;
            $recent_messages = [];
        }



        // Atividades pendentes (assign) nos próximos 14 dias
        $pending = [];
        $now = time();
        $timeend = $now + (14 * 86400); // 14 dias
        try {
            require_once($CFG->dirroot . '/mod/assign/locallib.php');
            if (!empty($courseids)) {
                list($inSql2, $inParams2) = $DB->get_in_or_equal($courseids, \SQL_PARAMS_NAMED, 'acid');
                $sql = "SELECT a.id, a.course, a.duedate, a.name, cm.id AS cmid, s.status
                          FROM {assign} a
                          JOIN {course_modules} cm ON cm.instance = a.id
                          JOIN {modules} m ON m.id = cm.module AND m.name = 'assign'
                     LEFT JOIN {assign_submission} s ON s.assignment = a.id AND s.userid = :uid
                         WHERE a.duedate > :now AND a.duedate < :limit AND a.course " . $inSql2 . "
                      ORDER BY a.duedate ASC";
                $params2 = ['uid' => $user->id, 'now' => $now, 'limit' => $timeend] + $inParams2;
                $rows = $DB->get_records_sql($sql, $params2, 0, 10);
                foreach ($rows as $r) {
                    // Considera pendente se não submetido, status 'draft' ou 'new' (submissão não finalizada)
                    if (empty($r->status) || $r->status === 'draft' || $r->status === 'new') {
                        $pending[] = [
                            'name' => format_string($r->name),
                            'due'  => userdate($r->duedate, get_string('strftimedatetime', 'langconfig')),
                            'url'  => (new \moodle_url('/mod/assign/view.php', ['id' => $r->cmid]))->out(false),
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {
            // silencioso
        }

        return [
            'courses' => $coursesarr,
            'coursesempty' => empty($coursesarr),
            'unread'  => $unread,
            'recent_messages' => $recent_messages,
            'announcements' => $announcements,
            'support' => $support,
            'pending' => $pending,
        ];
    }

    /**
     * Busca as mensagens mais recentes do usuário
     * 
     * @param int $userid ID do usuário
     * @param int $limit Número máximo de mensagens (padrão: 5)
     * @return array Lista de mensagens recentes
     */
    private static function get_recent_messages($userid, $limit = 5) {
        global $DB;
        
        try {
            // Buscar as últimas conversas do usuário com a mensagem mais recente
            $sql = "SELECT DISTINCT 
                        mc.id as conversationid,
                        mc.name as conversationname,
                        mc.type as conversationtype,
                        m.id as messageid,
                        m.subject,
                        m.fullmessage,
                        m.timecreated,
                        m.useridfrom,
                        sender.firstname as senderfirstname,
                        sender.lastname as senderlastname,
                        sender.picture as senderpicture,
                        sender.imagealt as senderimagealt,
                        sender.email as senderemail
                    FROM {message_conversations} mc
                    JOIN {message_conversation_members} mcm ON mcm.conversationid = mc.id
                    JOIN {messages} m ON m.conversationid = mc.id
                    JOIN {user} sender ON sender.id = m.useridfrom
                    WHERE mcm.userid = :userid
                      AND m.id = (
                          SELECT MAX(m2.id) 
                          FROM {messages} m2 
                          WHERE m2.conversationid = mc.id
                      )
                    ORDER BY m.timecreated DESC
                    LIMIT :limit";
            
            $messages = $DB->get_records_sql($sql, [
                'userid' => $userid,
                'limit' => $limit
            ]);
            
            $result = [];
            foreach ($messages as $msg) {
                // Determinar se é conversa individual ou grupo
                $isGroup = ($msg->conversationtype == \core_message\api::MESSAGE_CONVERSATION_TYPE_GROUP);
                $conversationName = '';
                
                if ($isGroup) {
                    $conversationName = format_string($msg->conversationname ?: 'Grupo');
                } else {
                    // Para conversa individual, usar nome do remetente
                    $conversationName = fullname($msg);
                }
                
                // Encurtar mensagem se muito longa
                $messageText = strip_tags($msg->fullmessage);
                $messageText = shorten_text($messageText, 80);
                
                // Verificar se mensagem é não lida para este usuário
                $isUnread = !$DB->record_exists('message_user_actions', [
                    'userid' => $userid,
                    'messageid' => $msg->messageid,
                    'action' => \core_message\api::MESSAGE_ACTION_READ
                ]);
                
                $result[] = [
                    'conversationid' => $msg->conversationid,
                    'conversationname' => $conversationName,
                    'messagetext' => $messageText,
                    'timeago' => self::time_ago($msg->timecreated),
                    'isunread' => $isUnread,
                    'url' => (new \moodle_url('/message/index.php', ['id' => $msg->conversationid]))->out(false),
                    'sendername' => ($msg->useridfrom != $userid) ? fullname($msg) : 'Você'
                ];
            }
            
            return $result;
            
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Converte timestamp em formato "tempo atrás"
     * 
     * @param int $timestamp
     * @return string
     */
    private static function time_ago($timestamp) {
        $diff = time() - $timestamp;
        
        if ($diff < 60) {
            return 'agora';
        } elseif ($diff < 3600) {
            $minutes = floor($diff / 60);
            return $minutes . 'min';
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return $hours . 'h';
        } elseif ($diff < 604800) {
            $days = floor($diff / 86400);
            return $days . 'd';
        } else {
            return userdate($timestamp, '%d/%m');
        }
    }

    /**
     * Conta conversas não lidas usando query personalizada
     * Funciona corretamente ao contrário da API padrão que tem problemas
     * 
     * @param int $userid ID do usuário
     * @return int Número de conversas não lidas
     */
    private static function count_unread_conversations_custom($userid) {
        global $DB;
        
        try {
            // Query que replica exatamente a lógica do badge do menu superior
            // Conta conversas que têm mensagens não lidas
            $sql = "SELECT COUNT(DISTINCT mc.id)
                    FROM {message_conversations} mc
                    JOIN {message_conversation_members} mcm ON mcm.conversationid = mc.id
                    WHERE mcm.userid = ?
                      AND EXISTS (
                          SELECT 1 FROM {messages} m
                          WHERE m.conversationid = mc.id
                            AND m.useridfrom != ?
                            AND NOT EXISTS (
                                SELECT 1 FROM {message_user_actions} mua
                                WHERE mua.messageid = m.id
                                  AND mua.userid = ?
                                  AND mua.action = ?
                            )
                      )";
            
            return $DB->count_records_sql($sql, [
                $userid,
                $userid, 
                $userid,
                \core_message\api::MESSAGE_ACTION_READ
            ]);
            
        } catch (\Throwable $e) {
            // Em caso de erro, usar API padrão como fallback
            try {
                return \core_message\api::count_unread_conversations($userid);
            } catch (\Throwable $e2) {
                return 0;
            }
        }
    }
}
