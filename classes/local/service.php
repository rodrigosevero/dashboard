<?php
namespace local_dashboard\local;

defined('MOODLE_INTERNAL') || die();

class service {
    public static function get_dashboard_data(\stdClass $user): array {
        global $CFG, $DB;
        require_once($CFG->libdir . '/enrollib.php');

        // Cursos em andamento do usuário.
        $courses = enrol_get_users_courses($user->id, true, 'id,shortname,fullname,startdate,enddate,visible');
        $coursesarr = [];
        $courseids = [];
        foreach ($courses as $c) {
            if (!$c->visible) { continue; }
            $coursesarr[] = [
                'id' => $c->id,
                'fullname' => format_string($c->fullname),
                'url' => (new \moodle_url('/course/view.php', ['id' => $c->id]))->out(false),
            ];
            $courseids[] = $c->id;
        }

                // Eventos próximos (próximos 14 dias) - com deduplicação inteligente.
        $eventsarr = [];
        $now = time();
        $timeend = $now + (14 * 86400); // 14 dias.

        list($inSql, $inParams) = empty($courseids)
            ? ['', []]
            : $DB->get_in_or_equal($courseids, \SQL_PARAMS_NAMED, 'cid');

        $where = '(timestart BETWEEN :tstart AND :tend) AND (visible = 1)';
        $params = ['tstart' => $now, 'tend' => $timeend];

        // Buscar eventos com informações extras para deduplicação (incluindo modulename)
        $selectfields = 'id,name,timestart,courseid,eventtype,instance,modulename';
        
        // Eventos do usuário.
        $userwhere = $where . ' AND (userid = :uid)';
        $userparams = $params + ['uid' => $user->id];
        $userevents = $DB->get_records_select('event', $userwhere, $userparams, 'timestart ASC', $selectfields);

        // Eventos por curso (se houver cursos).
        $courseevents = [];
        if (!empty($courseids)) {
            $coursewhere = $where . ' AND (courseid ' . $inSql . ')';
            $courseparams = $params + $inParams;
            $courseevents = $DB->get_records_select('event', $coursewhere, $courseparams, 'timestart ASC', $selectfields);
        }

        // Aplicar deduplicação inteligente
        $allevents = array_merge(array_values($userevents), array_values($courseevents));
        $cleanedEvents = self::deduplicate_events($allevents);
        
        // Limitar e formatar eventos
        $limitedEvents = array_slice($cleanedEvents, 0, 20);
        foreach ($limitedEvents as $e) {
            // Gerar URL correta baseada no tipo de evento
            $url = '#';
            if ($e->modulename === 'assign' && !empty($e->instance)) {
                // Para eventos de assign, buscar o course_module correto
                $cm = $DB->get_record_sql(
                    "SELECT cm.id 
                     FROM {course_modules} cm 
                     JOIN {modules} m ON m.id = cm.module 
                     WHERE cm.instance = ? AND cm.course = ? AND m.name = 'assign'",
                    [$e->instance, $e->courseid]
                );
                
                if ($cm) {
                    $url = (new \moodle_url('/mod/assign/view.php', ['id' => $cm->id]))->out(false);
                } else {
                    // Fallback para curso se course_module não existir
                    $url = (new \moodle_url('/course/view.php', ['id' => $e->courseid]))->out(false);
                }
            } elseif (!empty($e->courseid)) {
                // Para outros eventos, ir para o curso
                $url = (new \moodle_url('/course/view.php', ['id' => $e->courseid]))->out(false);
            }
            
            $eventsarr[] = [
                'name' => format_string($e->name),
                'time' => userdate($e->timestart, get_string('strftimedatetime', 'langconfig')),
                'url'  => $url,
                'courseid' => $e->courseid ?? null,
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

        // Conversas não lidas (com cache para performance).
        $unread = 0;
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
            }
        } catch (\Throwable $e) {
            $unread = 0;
        }



        // Atividades pendentes (assign) nos próximos 14 dias
        $pending = [];
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
            'events'  => $eventsarr,
            'unread'  => $unread,
            'announcements' => $announcements,
            'support' => $support,
            'pending' => $pending,
        ];
    }

    /**
     * Remove eventos duplicados e melhora nomes para melhor UX
     * 
     * @param array $events Lista de eventos brutos
     * @return array Lista de eventos limpos e deduplcados
     */
    private static function deduplicate_events($events) {
        $groups = [];
        $standalone = [];
        
        // Agrupar eventos por atividade (assign)
        foreach ($events as $event) {
            if ($event->modulename === 'assign' && !empty($event->instance)) {
                // Agrupar eventos de assign por instance + course
                $groupkey = "assign_{$event->instance}_{$event->courseid}";
                
                if (!isset($groups[$groupkey])) {
                    $groups[$groupkey] = [];
                }
                $groups[$groupkey][] = $event;
            } else {
                // Outros eventos ficam soltos
                $standalone[] = $event;
            }
        }
        
        $result = [];
        
        // Para cada grupo de assign, escolher o melhor representante
        foreach ($groups as $groupkey => $groupEvents) {
            $best = self::choose_best_assign_event($groupEvents);
            if ($best) {
                $result[] = $best;
            }
        }
        
        // Adicionar eventos standalone com nomes melhorados
        foreach ($standalone as $event) {
            $event->name = self::improve_event_name($event);
            $result[] = $event;
        }
        
        // Ordenar por data
        usort($result, function($a, $b) {
            return $a->timestart - $b->timestart;
        });
        
        return $result;
    }

    /**
     * Escolher o melhor evento para representar um grupo de assign
     * 
     * @param array $events Eventos do mesmo assign
     * @return object|null Melhor evento ou null
     */
    private static function choose_best_assign_event($events) {
        if (empty($events)) {
            return null;
        }
        
        $due = null;
        $gradingdue = null;
        $other = null;
        
        // Separar por tipo
        foreach ($events as $event) {
            switch ($event->eventtype) {
                case 'due':
                    $due = $event;
                    break;
                case 'gradingdue':
                    $gradingdue = $event;
                    break;
                default:
                    $other = $event;
                    break;
            }
        }
        
        // Prioridade: due > other > gradingdue
        // (prazos de entrega são mais importantes para alunos)
        $chosen = $due ?? $other ?? $gradingdue;
        
        if ($chosen) {
            // Melhorar o nome do evento escolhido
            if ($chosen->eventtype === 'due') {
                // Pegar nome mais limpo da tarefa
                $cleanName = preg_replace('/\s+está marcado\(a\) para esta data$/', '', $chosen->name);
                $chosen->name = "📝 Entrega: " . $cleanName;
            } elseif ($chosen->eventtype === 'gradingdue') {
                $cleanName = preg_replace('/\s+está com avaliação marcada para esta data$/', '', $chosen->name);
                $chosen->name = "✅ Correção: " . $cleanName;
            }
        }
        
        return $chosen;
    }

    /**
     * Melhorar nomes de eventos para melhor clareza
     * 
     * @param object $event Evento
     * @return string Nome melhorado
     */
    private static function improve_event_name($event) {
        $name = $event->name;
        
        // Remover frases genéricas
        $name = preg_replace('/\s+está marcado\(a\) para esta data$/', '', $name);
        $name = preg_replace('/\s+está com avaliação marcada para esta data$/', '', $name);
        
        // Adicionar emoji baseado no tipo
        switch ($event->eventtype) {
            case 'open':
                return "🔓 Abre: " . $name;
            case 'close':
                return "🔒 Fecha: " . $name;
            case 'due':
                return "📝 Prazo: " . $name;
            case 'gradingdue':
                return "✅ Correção: " . $name;
            default:
                return "📅 " . $name;
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
