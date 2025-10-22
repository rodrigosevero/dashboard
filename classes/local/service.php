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
            $banner_url = get_config('local_dashboard', "banner{$i}_url");
            $banner_alt = get_config('local_dashboard', "banner{$i}_alt");
            $banner_link = get_config('local_dashboard', "banner{$i}_link");
            
            if (!empty($banner_url)) {
                $banners[] = [
                    'url' => $banner_url,
                    'alt' => $banner_alt ?: "Banner {$i}",
                    'link' => $banner_link ?: '#',
                    'haslink' => !empty($banner_link),
                    'number' => $i
                ];
            }
        }

        return [
            'courses' => $coursesarr,
            'coursesempty' => empty($coursesarr),
            'announcements' => $announcements,
            'banners' => $banners,
        ];
    }
}
