# 📘 Plugin Moodle: Dashboard - Documentação de Referência Completa

**Versão do Plugin:** 2025101401  
**Compatibilidade:** Moodle 4.0+  
**Tipo:** Local Plugin (Portal Acadêmico Inteligente)  
**Data da Análise:** 21 de outubro de 2025

---

## 🎯 Visão Geral

### Propósito
O **Dashboard** é um plugin Moodle que cria um dashboard centralizado e inteligente para estudantes, funcionando como uma página inicial personalizada após o login. Ele agrega informações de múltiplas fontes do Moodle em uma interface limpa e organizada.

### Problema que Resolve
- **Fragmentação de informações**: Estudantes precisam navegar por várias páginas para ver cursos, prazos, mensagens e anúncios
- **Baixo engajamento**: Interface padrão do Moodle pode ser intimidante para novos usuários
- **Perda de prazos**: Informações importantes estão espalhadas em diferentes áreas
- **Falta de visão unificada**: Não há um ponto central que mostre o status acadêmico do aluno

### Funcionalidades Principais
1. ✅ **Dashboard unificado** - Visão 360° do ambiente acadêmico do aluno
2. 🔄 **Redirecionamento automático** - Redireciona estudantes após login (configurável)
3. 📊 **6 Cards informativos** organizados em grid responsivo:
   - 📚 Minhas Disciplinas
   - ✅ Atividades Pendentes
   - 📅 Próximos Eventos
   - 📬 Mensagens Não Lidas
   - 📢 Avisos e Anúncios
   - 🛠️ Suporte Técnico
4. 🚀 **Performance otimizada** - Sistema de cache para contadores
5. 🔄 **Auto-refresh** - Atualização automática do contador de mensagens (30s)
6. 🌐 **Multilíngue** - Suporte para Inglês e Português Brasileiro

---

## 🗂️ Estrutura de Arquivos

```
local/primeirapagina_pro/
│
├── version.php              # Metadados do plugin (versão, dependências)
├── settings.php             # Configurações administrativas
├── index.php                # Página principal do dashboard
├── styles.css               # Estilos CSS do dashboard
├── CONFIGURACAO.md          # Guia de configuração para administradores
├── PLUGIN_REFERENCE.md      # Este documento
│
├── classes/                 # Classes PHP do plugin
│   ├── observers.php        # Observadores de eventos do Moodle
│   ├── local/
│   │   └── service.php      # Lógica de negócio (coleta de dados)
│   └── output/
│       └── renderer.php     # Renderização de templates
│
├── db/                      # Definições de banco de dados
│   ├── events.php           # Registro de event observers
│   └── caches.php           # Definições de cache
│
├── ajax/                    # Endpoints AJAX
│   └── messages.php         # API para contador de mensagens
│
├── js/                      # JavaScript
│   └── message_counter.js   # Auto-refresh do contador de mensagens
│
├── debug_pending.php        # 🐛 Script de diagnóstico para atividades pendentes
├── clear_opcache.php        # 🔧 Ferramenta para limpar PHP OpCache
│
├── lang/                    # Arquivos de idioma
│   ├── en/
│   │   └── local_dashboard.php    # Strings em inglês
│   └── pt_br/
│       └── local_dashboard.php    # Strings em português
│
└── templates/               # Templates Mustache
    └── landing.mustache     # Template principal do dashboard
```

---

## 🔧 Componentes Técnicos Detalhados

### 1. **version.php**
```php
$plugin->component = 'local_dashboard';
$plugin->version   = 2025101401;
$plugin->requires  = 2022041900; // Moodle 4.0+
```

**Função:** Define metadados do plugin para o Moodle
- **component**: Identificador único no formato `plugintype_pluginname`
- **version**: YYYYMMDDXX (ano, mês, dia, sequencial)
- **requires**: Versão mínima do Moodle necessária

---

### 2. **settings.php** - Painel Administrativo

**Localização no Moodle:**  
`Administração do Site → Plugins → Plugins locais → Portal Acadêmico Inteligente`

#### Configurações Disponíveis:

| Configuração | Tipo | Padrão | Descrição |
|--------------|------|--------|-----------|
| `enabledredirect` | Checkbox | ✅ Habilitado | Redirecionar estudantes após login |
| `announcementsfallback` | HTML Editor | vazio | Mensagem fallback quando não há anúncios |
| `maxannouncements` | Integer | 3 | Máximo de anúncios a exibir |
| `supportname` | Text | "Suporte Acadêmico" | Nome da equipe de suporte |
| `supportemail` | Email | vazio | Email do suporte |
| `supportphone` | Text | vazio | Telefone do suporte |
| `supportwhatsapp` | URL | vazio | Link do WhatsApp (wa.me) |
| `supporthelpdesk` | URL | vazio | URL do portal de chamados |
| `supporthours` | Text | "Seg a Sex, 08:00-18:00" | Horário de atendimento |

**Exemplo de código:**
```php
$settings->add(new admin_setting_configcheckbox(
    'local_dashboard/enabledredirect',
    get_string('enabledredirect', 'local_dashboard'),
    get_string('enabledredirect_desc', 'local_dashboard'),
    1  // Habilitado por padrão
));
```

---

### 3. **index.php** - Página Principal

**Fluxo de Execução:**

```
1. require_login() → Verifica autenticação
2. Configura contexto e layout da página
3. Inclui CSS e JavaScript
4. Coleta dados via service::get_dashboard_data()
5. Adiciona metadados extras (nome completo, URLs)
6. Renderiza template via renderer
```

**Código Principal:**
```php
require_login();
$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/dashboard/index.php'));
$PAGE->set_pagelayout('mydashboard');

$renderer = $PAGE->get_renderer('local_dashboard');
$data = \local_dashboard\local\service::get_dashboard_data($USER);

echo $renderer->render_landing($data);
```

**Layout usado:** `mydashboard` - Layout padrão do Moodle para dashboards pessoais

---

### 4. **classes/local/service.php** - Lógica de Negócio

**Função principal:** `get_dashboard_data(\stdClass $user): array`

Esta é a classe mais complexa e importante do plugin. Ela coleta e processa dados de diversas fontes.

#### 4.1 Cursos do Usuário

```php
$courses = enrol_get_users_courses($user->id, true, 'id,shortname,fullname,startdate,enddate,visible');
```

**Processamento:**
- Busca todos os cursos onde o usuário está matriculado
- Filtra apenas cursos visíveis (`$c->visible`)
- Formata nome com `format_string()` para segurança
- Gera URL para cada curso

**Retorno:**
```php
[
    'id' => 123,
    'fullname' => 'Introdução à Programação',
    'url' => 'https://moodle.site.com/course/view.php?id=123'
]
```

#### 4.2 Eventos Próximos (14 dias)

**Query SQL:**
```sql
WHERE (timestart BETWEEN :tstart AND :tend) 
  AND (visible = 1)
  AND (userid = :uid OR courseid IN (...))
```

**Lógica:**
1. Busca eventos pessoais do usuário
2. Busca eventos dos cursos matriculados
3. Faz merge das duas listas
4. Limita a 20 eventos
5. Ordena por `timestart ASC`

**Estratégia de busca:**
- Primeiro busca eventos do usuário
- Depois busca eventos dos cursos (se houver)
- Combina e limita os resultados

#### 4.3 Anúncios (Site News Forum)

**📋 Fontes de Dados (Sistema Hierárquico):**

1. **🎯 Fórum de Site News (Prioridade 1 - Recomendado)**
   - **Query:** `{forum}` WHERE `course = SITEID` AND `type = 'news'`
   - **Status atual:** ❌ Não configurado 
   - **Como habilitar:** Administração > Front page > Front page settings > "News items"
   - **Vantagens:** Integração nativa, URLs funcionais, controle de permissões

2. **🔍 Fóruns de Avisos Personalizados (Prioridade 2)**
   - **Query:** `{forum}` WHERE nome LIKE '%announcement%' OR '%aviso%' OR '%notícia%'
   - **Status atual:** ❌ Nenhum encontrado
   - **Como criar:** Fórum no site principal (Course ID = 1) com nome apropriado
   - **Vantagens:** Controle total, personalizável, fácil de gerenciar

3. **⚙️ Texto de Fallback (Prioridade 3 - Atual)**
   - **Configuração:** `local_dashboard/announcementsfallback` 
   - **Status atual:** ✅ Em uso ("fdas sda fsa fasf asfd asfas fsa")
   - **Editor:** HTML completo com formatação
   - **Quando usado:** Nenhum fórum disponível

**Algoritmo de busca:**
```
1. Tenta buscar fórum tipo 'news' no SITEID
2. Se não encontrar, busca por nome (announcement/aviso/notícia)
3. Busca posts dos últimos 30 dias
4. Limita ao número configurado (padrão: 3)
5. Se não encontrar nenhum, usa fallback HTML configurado
```

**Query SQL:**
```sql
SELECT p.id, p.subject, p.message, p.modified, d.id AS did
FROM {forum_posts} p
JOIN {forum_discussions} d ON d.id = p.discussion
WHERE d.forum = :fid 
  AND p.parent = 0 
  AND d.timemodified > :timefilter
ORDER BY p.modified DESC
```

**Configurações relacionadas:**
- `maxannouncements` (padrão: 3) - Número máximo de posts
- `announcementsfallback` - Texto HTML usado quando não há fóruns

**Dados retornados para template:**
```php
[
    'title' => 'Título do post/fallback',
    'excerpt' => 'Resumo truncado (140 chars)', 
    'time' => 'Data formatada (userdate)',
    'url' => 'URL do post (/mod/forum/discuss.php) ou #',
    'fulltext' => 'HTML completo (só fallback)'
]
```

**Arquivos de diagnóstico:**
- `debug_announcements.php` - Análise completa das fontes
- `guia_configuracao_avisos.php` - Guia de configuração passo-a-passo

**Fallback:** Se não houver anúncios, usa a configuração `announcementsfallback` que suporta HTML formatado.

#### 4.4 Mensagens Não Lidas (com Cache)

**Sistema de Cache:**
```php
$cache = \cache::make('local_dashboard', 'unread_messages');
$cachekey = "user_{$user->id}";
$unread = $cache->get($cachekey);

if ($unread === false) {
    // Não está no cache, buscar do banco
    $unread = \core_message\api::count_unread_conversations($user->id);
    $cache->set($cachekey, $unread);
}
```

**Detalhes do Cache:**
- **TTL**: 5 minutos (300 segundos)
- **Tipo**: APPLICATION (compartilhado entre requests)
- **Invalidação**: Automática por eventos (message_sent, message_viewed)

#### 4.3 Próximos Eventos (Calendário) 📅

**Objetivo:** Mostrar eventos do calendário dos próximos 14 dias (pessoais + dos cursos matriculados)

**Período de busca:**
```php
$now = time();                    // Agora
$timeend = $now + (14 * 86400);  // Daqui a 14 dias (86400 = 1 dia em segundos)
```

**Estratégia de Coleta:**

O plugin busca eventos de **duas fontes separadas** e depois faz merge:

**1. Eventos Pessoais do Usuário:**
```sql
SELECT * FROM {event}
WHERE (timestart BETWEEN :tstart AND :tend)
  AND (visible = 1)
  AND (userid = :uid)          -- Eventos do próprio usuário
  AND (courseid = 0 OR courseid = 1)  -- Eventos pessoais ou do site
```

**2. Eventos dos Cursos Matriculados:**
```sql
SELECT * FROM {event}
WHERE (timestart BETWEEN :tstart AND :tend)
  AND (visible = 1)
  AND (userid = 0)             -- Eventos públicos (não-pessoais)
  AND (courseid IN (...))      -- IDs dos cursos onde está matriculado
```

**Por que duas queries separadas?**
- Eventos pessoais: `userid = X` e `courseid = 0 ou 1`
- Eventos de curso: `userid = 0` e `courseid IN (cursos)`
- Evita conflitos de condições WHERE complexas

**Processamento dos Dados:**
```php
// 1. Converte para arrays
$userevents = array_values($userevents);
$courseevents = array_values($courseevents);

// 2. Faz merge das duas fontes
$all = array_merge($userevents, $courseevents);

// 3. Limita a 20 eventos
$all = array_slice($all, 0, 20);

// 4. Formata cada evento
foreach ($all as $e) {
    $eventsarr[] = [
        'name' => format_string($e->name),  // Sanitiza nome
        'time' => userdate($e->timestart),   // Formata data/hora
        'url'  => (new moodle_url('/course/view.php', ['id' => $e->courseid]))->out(false),
        'courseid' => $e->courseid,
    ];
}
```

**Tipos de Eventos Capturados:**

| Tipo | Origem | Exemplo |
|------|--------|---------|
| **Tarefa (assign)** | Due date | "Entrega do Trabalho Final" |
| **Quiz** | Close date | "Prova Bimestral - Fecha às 23:59" |
| **Lesson** | Deadline | "Lição 5 - Prazo de conclusão" |
| **Evento de curso** | Manual | "Palestra: IA na Educação" |
| **Evento pessoal** | Calendário | "Reunião com orientador" |
| **Evento do site** | Global | "Manutenção do sistema" |

**Campos do Evento (tabela {event}):**

```php
{
    id: 123,
    name: "Entrega do Trabalho Final",
    description: "Enviar PDF até...",
    timestart: 1761170400,        // UNIX timestamp
    timeduration: 0,               // Duração em segundos
    visible: 1,                    // Visível?
    userid: 0,                     // 0 = curso, X = pessoal
    courseid: 3,                   // ID do curso
    eventtype: 'due',              // Tipo: due, open, close, etc
    type: 1,                       // 1 = padrão
}
```

**Formatação da Data:**
```php
userdate($e->timestart, get_string('strftimedatetime', 'langconfig'))
// Resultado: "22 outubro 2025, 23:00 PM"
```

**URL Gerada:**
- Se tem `courseid`: `/course/view.php?id=3` (vai para o curso)
- Se não tem: `#` (sem link, apenas informativo)

**Ordenação:**
- Já vem ordenado por `timestart ASC` do banco
- Eventos mais próximos aparecem primeiro

**Limitações:**
- Apenas eventos visíveis (`visible = 1`)
- Apenas próximos 14 dias (hardcoded)
- Máximo de 20 eventos exibidos
- Não filtra eventos já passados (mas query garante `timestart > now`)

**Exemplo de Dados Retornados:**
```php
'events' => [
    [
        'name' => 'tarefa testet está marcado(a) para esta data',
        'time' => '22 outubro 2025, 00:00 AM',
        'url' => 'http://localhost/moodle/course/view.php?id=3',
        'courseid' => 3
    ],
    [
        'name' => 'tes te de tarefa carai está marcado(a) para esta data',
        'time' => '22 outubro 2025, 23:00 PM',
        'url' => 'http://localhost/moodle/course/view.php?id=3',
        'courseid' => 3
    ],
    // ... até 20 eventos
]
```

**Fallback (lista vazia):**
```mustache
{{^events}}
    <p class="pp-empty">{{#str}} noevents, local_dashboard {{/str}}</p>
{{/events}}
```

**Performance:**
- 2 queries SQL (uma para eventos pessoais, outra para de curso)
- Uso de índices nativos do Moodle em `timestart` e `visible`
- Merge em memória (rápido, apenas 20 itens)

#### 4.4 Mensagens Não Lidas (com Cache)

**Query SQL:**
```sql
SELECT a.id, a.course, a.duedate, a.name, cm.id AS cmid, s.status
FROM {assign} a
JOIN {course_modules} cm ON cm.instance = a.id
JOIN {modules} m ON m.id = cm.module AND m.name = 'assign'
LEFT JOIN {assign_submission} s ON s.assignment = a.id AND s.userid = :uid
WHERE a.duedate > :now 
  AND a.duedate < :limit 
  AND a.course IN (...)
ORDER BY a.duedate ASC
```

**Critério de "pendente":**
- Não submetido (`empty($r->status)`)
- OU com status 'draft' (rascunho)
- OU com status 'new' (submissão não finalizada) ✨ **NOVO**

**Limitações:**
- Apenas atividades tipo `assign` (tarefas)
- Prazo entre agora e 14 dias
- Máximo de 10 atividades

**⚠️ CORREÇÃO CRÍTICA (21/10/2025):**
- **Bug identificado:** O array `$pending` era calculado mas **não era retornado** pela função
- **Linha afetada:** 186 - faltava `'pending' => $pending` no return
- **Impacto:** Card "Atividades Pendentes" sempre exibia "Nenhuma atividade pendente"
- **Status:** ✅ Corrigido - agora retorna corretamente as tarefas pendentes

#### 4.6 Suporte Técnico

**Simples leitura de configurações:**
```php
$support = [
    'name' => get_config('local_dashboard', 'supportname'),
    'email' => get_config('local_dashboard', 'supportemail'),
    'phone' => get_config('local_dashboard', 'supportphone'),
    'whatsapp' => get_config('local_dashboard', 'supportwhatsapp'),
    'helpdesk' => get_config('local_dashboard', 'supporthelpdesk'),
    'hours' => get_config('local_dashboard', 'supporthours'),
];
```

---

### 5. **classes/observers.php** - Event Observers

#### 5.1 `on_login()` - Redirecionamento após Login

**Evento:** `\core\event\user_loggedin`  
**Prioridade:** 9999 (executa por último)

**Lógica:**
```php
1. Verifica se redirecionamento está habilitado
2. Se usuário for administrador (site:config), não redireciona
3. Verifica flag 'pp_redirect' para evitar loop infinito
4. Redireciona para /local/dashboard/index.php?pp_redirect=1
```

**Prevenção de loop:**
```php
$flag = optional_param('pp_redirect', 0, PARAM_INT);
if (!$flag) {
    redirect($url);
}
```

#### 5.2 `on_message_sent()` - Invalidação de Cache (Mensagens Enviadas)

**Evento:** `\core\event\message_sent`  
**Prioridade:** 500

**Ação:**
```php
$cache = \cache::make('local_dashboard', 'unread_messages');
$cache->delete("user_{$relateduserid}"); // Destinatário
```

**Por que?** Quando alguém recebe uma mensagem, o contador de não lidas do destinatário precisa ser atualizado.

#### 5.3 `on_message_viewed()` - Invalidação de Cache (Mensagens Visualizadas)

**Evento:** `\core\event\message_viewed`  
**Prioridade:** 500

**Ação:**
```php
$cache = \cache::make('local_dashboard', 'unread_messages');
$cache->delete("user_{$userid}"); // Quem visualizou
```

**Por que?** Quando alguém lê uma mensagem, o contador de não lidas precisa diminuir.

---

### 6. **db/caches.php** - Definições de Cache

```php
$definitions = [
    'unread_messages' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => true,
        'ttl' => 300, // 5 minutos
        'staticacceleration' => true,
        'staticaccelerationsize' => 100,
    ],
];
```

**Parâmetros:**
- **mode**: APPLICATION - Cache compartilhado entre sessões
- **simplekeys**: true - Apenas strings simples como chaves
- **simpledata**: true - Apenas dados escalares (int, string)
- **ttl**: 300s - Expira após 5 minutos
- **staticacceleration**: true - Cache em memória PHP para mesma requisição
- **staticaccelerationsize**: 100 - Máximo de 100 itens em memória

---

### 7. **ajax/messages.php** - API AJAX

**Endpoint:** `/local/dashboard/ajax/messages.php`  
**Método:** POST  
**Content-Type:** application/json

**Request Body:**
```json
{
    "sesskey": "abc123..."
}
```

**Response (Success):**
```json
{
    "success": true,
    "unread": 5,
    "timestamp": 1729526400
}
```

**Response (Error):**
```json
{
    "success": false,
    "error": "Invalid session",
    "unread": 0
}
```

**Segurança:**
- `require_login()` - Verifica autenticação
- `confirm_sesskey()` - Proteção CSRF
- `AJAX_SCRIPT` - Define como script AJAX

---

### 8. **js/message_counter.js** - Auto-refresh JavaScript

**Funcionalidade:** Atualiza contador de mensagens não lidas a cada 30 segundos.

**Fluxo:**
```
1. Aguarda DOM carregar (DOMContentLoaded)
2. Define função updateMessageCounter()
3. Faz fetch para /ajax/messages.php
4. Compara contador atual vs novo
5. Se diferente, anima mudança
6. Se aumentou, destaca em vermelho por 2s
7. Repete a cada 30 segundos
8. Primeira execução após 5 segundos
```

**Animações:**
```javascript
// Efeito de "crescimento"
counterElement.style.transform = 'scale(1.1)';
setTimeout(() => {
    counterElement.style.transform = 'scale(1)';
}, 300);

// Destaque vermelho para novas mensagens
if (newCount > currentCount) {
    counterElement.style.color = '#ef4444'; // Vermelho
    setTimeout(() => {
        counterElement.style.color = ''; // Volta ao normal
    }, 2000);
}
```

**Performance:**
- Usa `fetch()` moderno
- Não recarrega a página
- Animações suaves com CSS transitions
- Tratamento de erros silencioso (console.log)

---

### 9. **templates/landing.mustache** - Template Mustache

**Tecnologia:** Mustache (template engine do Moodle)

**Estrutura:**
```html
<div class="pp-container">
    <div class="pp-header">...</div>
    <div class="pp-grid">
        <!-- 6 cards aqui -->
    </div>
</div>
```

#### Grid Responsivo

```css
.pp-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 16px;
}
```

**Comportamento:**
- Desktop: 3 colunas
- Tablet: 2 colunas
- Mobile: 1 coluna

#### Sintaxe Mustache

**Strings localizadas:**
```mustache
{{#str}} welcome_title, local_dashboard {{/str}}
```

**Loops:**
```mustache
{{#courses}}
    <a href="{{url}}">{{fullname}}</a>
{{/courses}}
```

**Condicionais:**
```mustache
{{#coursesempty}}
    <p>Nenhum curso encontrado</p>
{{/coursesempty}}
```

**Negação:**
```mustache
{{^announcements}}
    <p>Sem anúncios</p>
{{/announcements}}
```

---

### 10. **styles.css** - Estilos CSS

**Design System:**

| Classe | Função |
|--------|--------|
| `.pp-container` | Container principal (max-width: 1100px) |
| `.pp-header` | Cabeçalho de boas-vindas |
| `.pp-grid` | Grid CSS responsivo |
| `.pp-card` | Card individual (branco, sombra, border-radius) |
| `.pp-item` | Item dentro do card (hover effect) |
| `.pp-item-title` | Título do item (bold) |
| `.pp-item-meta` | Metadados (cinza, menor) |
| `.pp-empty` | Mensagem de lista vazia (itálico, cinza) |
| `.pp-cta` | Container de call-to-action |
| `.pp-button` | Botão estilizado |
| `.pp-kpi` | Número grande (2.25rem, bold) |
| `.pp-muted` | Texto secundário (cinza) |

**Paleta de Cores:**
- Branco: `#fff`
- Cinza claro: `#f9fafb`, `#eef0f4`, `#e5e7eb`
- Cinza médio: `#6b7280`
- Bordas: `rgba(0,0,0,.04)`
- Sombras: `rgba(0,0,0,.05)`

**Acessibilidade:**
- Hover states visíveis
- Contraste adequado de cores
- Borders para definir áreas clicáveis

---

## 🔄 Fluxo de Dados Completo

### Cenário: Usuário faz login

```
1. USER LOGIN
   ↓
2. Moodle dispara evento: user_loggedin
   ↓
3. observers::on_login() é chamado
   ↓
4. Verifica se enabledredirect está ON
   ↓
5. Verifica se usuário não é admin
   ↓
6. Redireciona para /local/dashboard/index.php?pp_redirect=1
   ↓
7. index.php executa:
   - require_login()
   - Configura PAGE
   - Chama service::get_dashboard_data($USER)
   ↓
8. service::get_dashboard_data() coleta:
   a. Cursos (enrol_get_users_courses)
   b. Eventos (query SQL em {event})
   c. Anúncios (query SQL em {forum_posts})
   d. Mensagens não lidas (cache ou core_message\api)
   e. Atividades pendentes (query SQL em {assign})
   f. Suporte (get_config)
   ↓
9. index.php adiciona metadados extras
   ↓
10. renderer::render_landing() renderiza template
   ↓
11. Template Mustache gera HTML
   ↓
12. Página exibida com:
    - CSS carregado
    - JavaScript iniciado
    ↓
13. JavaScript agenda:
    - Auto-refresh a cada 30s
    - Primeira atualização em 5s
    ↓
14. A cada 30s:
    - Fetch para /ajax/messages.php
    - Atualiza contador de mensagens
    - Anima mudanças
```

---

## 📊 Diagrama de Arquitetura

```
┌─────────────────────────────────────────────────────────────────┐
│                         MOODLE CORE                              │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐          │
│  │   Events     │  │   Enrolment  │  │   Messages   │          │
│  │   System     │  │   API        │  │   API        │          │
│  └──────┬───────┘  └──────┬───────┘  └──────┬───────┘          │
│         │                  │                  │                  │
└─────────┼──────────────────┼──────────────────┼──────────────────┘
          │                  │                  │
          ▼                  ▼                  ▼
┌─────────────────────────────────────────────────────────────────┐
│               LOCAL_PRIMEIRAPAGINA_PRO PLUGIN                    │
│                                                                   │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │                    EVENT OBSERVERS                        │   │
│  │  • on_login()          → Redirection Logic               │   │
│  │  • on_message_sent()   → Cache Invalidation              │   │
│  │  • on_message_viewed() → Cache Invalidation              │   │
│  └────────────────────────┬─────────────────────────────────┘   │
│                            │                                     │
│  ┌────────────────────────▼─────────────────────────────────┐   │
│  │                  SERVICE LAYER                            │   │
│  │  get_dashboard_data($user):                               │   │
│  │    • Fetch courses                                        │   │
│  │    • Fetch events (14 days)                               │   │
│  │    • Fetch announcements (forum posts)                    │   │
│  │    • Fetch unread messages (cached)                       │   │
│  │    • Fetch pending assignments                            │   │
│  │    • Fetch support config                                 │   │
│  └────────────────────────┬─────────────────────────────────┘   │
│                            │                                     │
│  ┌────────────────────────▼─────────────────────────────────┐   │
│  │                   CACHE SYSTEM                            │   │
│  │  • unread_messages (TTL: 5min)                            │   │
│  │  • MODE: APPLICATION                                      │   │
│  │  • Static acceleration: 100 items                         │   │
│  └────────────────────────┬─────────────────────────────────┘   │
│                            │                                     │
│  ┌────────────────────────▼─────────────────────────────────┐   │
│  │                   RENDERER                                │   │
│  │  render_landing($data) → Mustache Template                │   │
│  └────────────────────────┬─────────────────────────────────┘   │
│                            │                                     │
│  ┌────────────────────────▼─────────────────────────────────┐   │
│  │                  AJAX ENDPOINT                            │   │
│  │  /ajax/messages.php → Real-time message count             │   │
│  └────────────────────────┬─────────────────────────────────┘   │
│                            │                                     │
└────────────────────────────┼─────────────────────────────────────┘
                             │
                             ▼
                   ┌──────────────────┐
                   │  FRONT-END       │
                   │  • HTML/Mustache │
                   │  • CSS           │
                   │  • JavaScript    │
                   │    (auto-refresh)│
                   └──────────────────┘
```

---

## 🔒 Segurança

### Medidas Implementadas

1. **Autenticação:**
   - `require_login()` em todos os endpoints
   - Verificação de contexto do sistema

2. **Proteção CSRF:**
   - `confirm_sesskey()` em requisições AJAX
   - Token de sessão validado

3. **Sanitização de dados:**
   - `format_string()` para textos do usuário
   - `format_text()` para HTML (com contexto)
   - `shorten_text()` + `strip_tags()` para excerpts

4. **SQL Injection:**
   - Uso de prepared statements
   - `$DB->get_in_or_equal()` para arrays
   - Named parameters (`:param`)

5. **XSS Prevention:**
   - Output escaping pelo Mustache
   - `strip_tags()` onde HTML não é permitido
   - `format_text()` com contexto apropriado

6. **Controle de acesso:**
   - Verifica se usuário é admin antes de redirecionar
   - Contexto apropriado para cada operação

### Vulnerabilidades Potenciais

⚠️ **Áreas de atenção:**

1. **announcementsfallback** permite HTML:
   - Apenas admins podem configurar (ok)
   - Usa `format_text()` com contexto (ok)
   - ✅ Sem vulnerabilidade real

2. **Cache de mensagens:**
   - Não valida propriedade dos dados
   - Usuário só acessa seu próprio cache
   - ✅ Isolamento adequado

3. **AJAX sem rate limiting:**
   - Pode ser chamado muitas vezes
   - 💡 Sugestão: adicionar throttling

---

## ⚡ Performance

### Otimizações Implementadas

1. **Sistema de Cache:**
   - Mensagens não lidas cacheadas por 5 minutos
   - Static acceleration para mesma requisição
   - Invalidação inteligente por eventos

2. **Query Optimization:**
   - Limita resultados (LIMIT 10, 20, etc)
   - Filtra por data (últimos 14-30 dias)
   - Índices nas tabelas nativas do Moodle

3. **Lazy Loading:**
   - JavaScript carregado após DOM ready
   - CSS inline (pequeno, sem request extra)
   - AJAX assíncrono não bloqueia página

4. **Fallback Gracioso:**
   - Try-catch em operações críticas
   - Retorna arrays vazios em erro
   - Não quebra o dashboard se uma fonte falhar

### Métricas Estimadas

| Operação | Tempo Estimado | Queries DB |
|----------|----------------|------------|
| Primeira carga | 200-500ms | 5-8 |
| Com cache | 100-200ms | 3-5 |
| Auto-refresh | 50-100ms | 1 |

### Gargalos Potenciais

1. **Muitos cursos (100+):**
   - `enrol_get_users_courses()` pode ser lento
   - 💡 Sugestão: paginar ou limitar

2. **Muitos eventos:**
   - Query em {event} sem índice personalizado
   - 💡 Sugestão: adicionar índice composto

3. **Fórum de anúncios grande:**
   - JOIN entre posts e discussions
   - ✅ Já limitado por LIMIT e timefilter

---

## 🌍 Internacionalização (i18n)

### Idiomas Suportados

1. **Inglês (en):** `lang/en/local_dashboard.php`
2. **Português BR (pt_br):** `lang/pt_br/local_dashboard.php`

### Strings Disponíveis

**Total:** 30+ strings

**Categorias:**
- Interface do usuário (welcome, titles, labels)
- Mensagens de estado vazio (no courses, no events)
- Configurações administrativas (descriptions)
- Cards e seções (courses, messages, support)

### Como Adicionar Novo Idioma

```bash
# 1. Criar diretório do idioma
mkdir -p lang/es

# 2. Copiar arquivo base
cp lang/en/local_dashboard.php lang/es/

# 3. Traduzir strings
vim lang/es/local_dashboard.php

# 4. Limpar cache de idiomas
php admin/cli/purge_caches.php
```

### Strings Mais Usadas

```php
// Títulos dos cards
$string['mycourses'] = 'Minhas Disciplinas';
$string['pending'] = 'Atividades pendentes';
$string['upcoming'] = 'Próximos eventos';
$string['messages'] = 'Mensagens';
$string['announcements'] = 'Avisos importantes';
$string['support'] = 'Suporte técnico';

// Mensagens vazias
$string['nocourses'] = 'Nenhuma matrícula ativa encontrada.';
$string['nopending'] = 'Nenhuma atividade pendente.';
$string['noevents'] = 'Sem eventos nos próximos dias.';
```

---

## 🐛 Debugging e Troubleshooting

### Problemas Comuns

#### 1. "Dashboard não aparece após login"

**Diagnóstico:**
```php
// Verificar se redirecionamento está habilitado
SELECT * FROM {config_plugins} 
WHERE plugin = 'local_dashboard' 
AND name = 'enabledredirect';
```

**Soluções:**
- Verificar se `enabledredirect` está em 1
- Verificar se usuário não é admin
- Verificar se há flag `pp_redirect` na URL

#### 2. "Contador de mensagens não atualiza"

**Diagnóstico:**
- Abrir console do navegador (F12)
- Verificar erros JavaScript
- Verificar network requests para `/ajax/messages.php`

**Soluções:**
```bash
# Limpar cache
php admin/cli/purge_caches.php

# Verificar permissões
ls -la local/primeirapagina_pro/ajax/

# Testar endpoint
curl -X POST https://seu-moodle.com/local/dashboard/ajax/messages.php \
  -H "Cookie: MoodleSession=..." \
  -d '{"sesskey":"abc123"}'
```

#### 3. "Anúncios não aparecem"

**Diagnóstico:**
```sql
-- Verificar se existe fórum de notícias
SELECT * FROM {forum} WHERE course = 1 AND type = 'news';

-- Verificar posts recentes
SELECT p.* FROM {forum_posts} p
JOIN {forum_discussions} d ON d.id = p.discussion
WHERE d.forum = X AND p.modified > (UNIX_TIMESTAMP() - 2592000);
```

**Soluções:**
- Criar fórum tipo 'news' no site
- Ou configurar `announcementsfallback`
- Verificar se há posts nos últimos 30 dias

#### 4. "Cache não invalida"

**Diagnóstico:**
```bash
# Ver configuração de cache
php admin/cli/cfg.php --component=core --name=cachedir

# Verificar observers registrados
SELECT * FROM {events_handlers} 
WHERE component = 'local_dashboard';
```

**Soluções:**
```bash
# Reinstalar plugin para recriar observers
php admin/cli/uninstall_plugins.php --plugins=local_dashboard
php admin/cli/install_database.php

# Ou limpar cache manualmente
php admin/cli/purge_caches.php
```

### Modo Debug

**Habilitar debug no Moodle:**
```php
// config.php
$CFG->debug = 32767;
$CFG->debugdisplay = 1;
```

**Adicionar logs customizados:**
```php
// Em service.php
debugging("Dashboard data: " . json_encode($data), DEBUG_DEVELOPER);
mtrace("Courses found: " . count($coursesarr));
```

---

## 🧪 Testes

### Checklist de Testes Manuais

**Autenticação e Redirecionamento:**
- [ ] Login como estudante → redireciona para dashboard
- [ ] Login como admin → NÃO redireciona
- [ ] Desabilitar `enabledredirect` → não redireciona
- [ ] Acessar URL direta → funciona

**Cards e Dados:**
- [ ] Card Cursos → mostra cursos matriculados
- [ ] Card Cursos vazio → mostra mensagem "sem cursos"
- [ ] Card Eventos → mostra eventos dos próximos 14 dias
- [ ] Card Mensagens → mostra contador correto
- [ ] Card Anúncios → mostra posts do fórum
- [ ] Card Anúncios fallback → mostra mensagem configurada
- [ ] Card Suporte → mostra dados configurados
- [ ] Card Pendentes → mostra tarefas não entregues

**Performance e Cache:**
- [ ] Primeira carga → < 500ms
- [ ] Segunda carga → < 200ms (cache ativo)
- [ ] Enviar mensagem → cache invalida
- [ ] Ler mensagem → cache invalida

**JavaScript:**
- [ ] Auto-refresh → atualiza a cada 30s
- [ ] Nova mensagem → anima contador
- [ ] Animação → scale e cor funcionam

**Responsividade:**
- [ ] Desktop (1920px) → 3 colunas
- [ ] Tablet (768px) → 2 colunas
- [ ] Mobile (375px) → 1 coluna

### Teste de Carga

**Simular muitos usuários:**
```bash
# Criar script PHP
php <<EOF
<?php
require(__DIR__ . '/config.php');
for ($i = 0; $i < 100; $i++) {
    $user = $DB->get_record('user', ['id' => rand(2, 1000)]);
    $data = \local_dashboard\local\service::get_dashboard_data($user);
    echo "User {$user->id}: " . count($data['courses']) . " courses\n";
}
EOF
```

---

## 🔄 Dependências

### Moodle Core APIs Utilizadas

1. **Enrolment API:**
   - `enrol_get_users_courses()` → Cursos do usuário

2. **Message API:**
   - `\core_message\api::count_unread_conversations()` → Contador de mensagens

3. **Cache API:**
   - `\cache::make()` → Sistema de cache

4. **Event API:**
   - Event observers para login e mensagens

5. **Database API:**
   - `$DB->get_records()`, `$DB->get_records_sql()` → Queries

6. **Output API:**
   - `plugin_renderer_base` → Renderização
   - Mustache templates

7. **Format API:**
   - `format_string()`, `format_text()` → Sanitização

### Tabelas do Moodle Usadas

| Tabela | Uso |
|--------|-----|
| `{course}` | Via API enrol |
| `{user}` | Dados do usuário |
| `{enrol}` | Via API enrol |
| `{event}` | Eventos do calendário |
| `{forum}` | Fórum de anúncios |
| `{forum_posts}` | Posts de anúncios |
| `{forum_discussions}` | Discussões do fórum |
| `{assign}` | Tarefas pendentes |
| `{assign_submission}` | Submissões de tarefas |
| `{course_modules}` | Módulos dos cursos |
| `{modules}` | Definições de módulos |
| `{config_plugins}` | Configurações do plugin |

### Requisitos de Sistema

- **Moodle:** 4.0+ (2022041900)
- **PHP:** 7.4+ (requisito do Moodle 4.0)
- **Database:** MySQL 5.7+ ou PostgreSQL 11+
- **Browser:** Moderno com suporte a:
  - CSS Grid
  - Fetch API
  - ES6 JavaScript

---

## 📈 Roadmap e Melhorias Futuras

### Funcionalidades Sugeridas

1. **Dashboard Personalizável:**
   - Permitir usuário escolher quais cards exibir
   - Drag-and-drop para reordenar cards
   - Configuração de limites por usuário

2. **Mais Tipos de Atividades:**
   - Quizzes pendentes
   - Lições não completadas
   - Fóruns com posts não lidos

3. **Gráficos e Visualizações:**
   - Progresso em cursos (%)
   - Estatísticas de conclusão
   - Timeline de atividades

4. **Notificações Push:**
   - Web push para novas mensagens
   - Alertas de prazos próximos

5. **Integração com Apps:**
   - API REST para apps mobile
   - Webhooks para integrações externas

6. **Analytics:**
   - Tempo no dashboard
   - Cards mais clicados
   - Patterns de uso

### Melhorias de Performance

1. **Lazy Loading:**
   - Carregar cards sob demanda
   - Infinite scroll para listas grandes

2. **Service Workers:**
   - Cache offline
   - Background sync

3. **Database Optimization:**
   - Índices customizados
   - Query optimization
   - Materialized views

### Melhorias de UX

1. **Dark Mode:**
   - Tema escuro opcional
   - Respeita preferência do sistema

2. **Acessibilidade:**
   - ARIA labels completos
   - Navegação por teclado
   - Screen reader friendly

3. **Animações:**
   - Transições suaves
   - Loading skeletons
   - Micro-interações

---

## 🤝 Como Contribuir

### Para Desenvolvedores

**1. Clonar e instalar:**
```bash
cd /var/www/html/moodle/local
git clone [repo-url] primeirapagina_pro
cd primeirapagina_pro
```

**2. Fazer alterações:**
- Editar arquivos necessários
- Seguir coding standards do Moodle
- Adicionar comentários em inglês

**3. Testar:**
```bash
# Limpar cache
php admin/cli/purge_caches.php

# Testar interface
firefox http://localhost/local/dashboard/index.php

# Rodar code checker (se disponível)
php admin/tool/phpcs/cli/check.php local/primeirapagina_pro
```

**4. Documentar:**
- Atualizar este README
- Adicionar docblocks PHPDoc
- Atualizar CHANGELOG

### Coding Standards

**PHP:**
```php
/**
 * Get dashboard data for a user
 * 
 * @param stdClass $user Moodle user object
 * @return array Dashboard data array
 */
public static function get_dashboard_data(\stdClass $user): array {
    // Code here
}
```

**CSS:**
```css
/* Card container */
.pp-card {
    background: #fff;
    border-radius: 16px;
}
```

**JavaScript:**
```javascript
/**
 * Update message counter
 * Fetches new count from server and animates changes
 */
function updateMessageCounter() {
    // Code here
}
```

---

## 📚 Referências

### Documentação Oficial do Moodle

- [Local plugins](https://docs.moodle.org/dev/Local_plugins)
- [Event observers](https://docs.moodle.org/dev/Event_2)
- [Cache API](https://docs.moodle.org/dev/Cache_API)
- [Mustache templates](https://docs.moodle.org/dev/Templates)
- [Coding style](https://moodledev.io/general/development/policies/codingstyle)

### APIs Utilizadas

- [Enrolment API](https://docs.moodle.org/dev/Enrolment_API)
- [Message API](https://docs.moodle.org/dev/Message_API)
- [Database API](https://docs.moodle.org/dev/Data_manipulation_API)
- [Output API](https://docs.moodle.org/dev/Output_API)

### Tecnologias

- [Mustache.js](https://mustache.github.io/)
- [CSS Grid](https://css-tricks.com/snippets/css/complete-guide-grid/)
- [Fetch API](https://developer.mozilla.org/en-US/docs/Web/API/Fetch_API)

---

## 📄 Licença e Créditos

**Tipo de Plugin:** Local  
**Categoria:** Dashboard / Portal  
**Autor:** [Informação não disponível no código]  
**Licença:** [A definir - presumivelmente GPL v3 como Moodle]

### Compatibilidade

- ✅ Moodle 4.0
- ✅ Moodle 4.1
- ✅ Moodle 4.2
- ✅ Moodle 4.3
- ✅ Moodle 4.4

### Créditos de Tecnologias

- **Moodle**: Modular Object-Oriented Dynamic Learning Environment
- **Mustache**: Logic-less templates
- **CSS Grid**: Layout module

---

## 🆘 Suporte

### Onde Buscar Ajuda

1. **Documentação oficial:** Este arquivo
2. **Configuração:** `CONFIGURACAO.md`
3. **Moodle Forums:** https://moodle.org/mod/forum/
4. **Stack Overflow:** Tag `moodle`

### Reportar Bugs

**Template de issue:**
```markdown
**Descrição do bug:**
[Descreva o problema]

**Como reproduzir:**
1. Passo 1
2. Passo 2
3. Erro acontece

**Comportamento esperado:**
[O que deveria acontecer]

**Screenshots:**
[Se aplicável]

**Ambiente:**
- Moodle version: 4.x
- PHP version: 7.x
- Browser: Chrome 120
- Plugin version: 2025101401
```

---

## 📝 Changelog

### v2025101404 (21/10/2025) - 🔧 Correção do Card Mensagens
- 🐛 **PROBLEMA IDENTIFICADO**: API `core_message\api::count_unread_conversations()` inconsistente
  - 📊 API retornava: **0 conversas**
  - 🎯 Badge menu mostrava: **1 conversa não lida**
  - ❌ Card mensagens: **0** (incorreto)
- ✨ **SOLUÇÃO IMPLEMENTADA**: Query personalizada `count_unread_conversations_custom()`
  - 🔍 Replica exatamente a lógica do badge do menu superior
  - 📊 Conta conversas com mensagens não lidas (não apenas total de mensagens)
  - 🎯 **Consistência garantida**: Card = Badge do menu
- 🔧 **Arquivos alterados**:
  - `classes/local/service.php`: Método personalizado para contagem
  - `ajax/messages.php`: Mesma lógica para atualizações AJAX
- 📋 **Diagnósticos criados**: `debug_conversations.php`, `debug_badge_vs_api.php`
- ✅ **Resultado**: Card de mensagens agora mostra valores corretos e consistentes

### v2025101403 (21/10/2025) - 🎯 Deduplicação de Eventos + Links Corretos
- ✨ **NOVA FUNCIONALIDADE:** Deduplicação inteligente de eventos no card "Próximos Eventos"
- ✨ **Redução de 67%**: 10 → 3 eventos (remove duplicatas de assign due/gradingdue)
- ✨ **Priorização inteligente**: Prazos de entrega > Prazos de correção
- ✨ **Nomes melhorados**: "📝 Entrega: [tarefa]" e "✅ Correção: [tarefa]"
- 🐛 **CORREÇÃO CRÍTICA:** Links corretos para eventos de assign
  - ✅ **Antes**: `/course/view.php?id=3` (ia para curso)
  - ✅ **Depois**: `/mod/assign/view.php?id=11` (vai direto para tarefa)
- 🔧 **Lógica aprimorada**: Uso do campo `modulename` para identificar eventos de assign
- 🔧 **URLs dinâmicas**: Query automática para encontrar course_module correto
- 📝 Documentação atualizada com troubleshooting de eventos duplicados

### v2025101402 (21/10/2025) - 🐛 Bug Fix Critical
- 🐛 **CORREÇÃO CRÍTICA:** Card "Atividades Pendentes" não exibia tarefas
- ✨ Adicionado suporte para status 'new' em submissões
- ✨ Criado script de diagnóstico `debug_pending.php`
- ✨ Criado script `clear_opcache.php` para limpar cache PHP
- 📝 Documentação atualizada com troubleshooting detalhado

### v2025101401 (Original)
- ✨ Dashboard completo com 6 cards
- ✨ Sistema de cache para mensagens
- ✨ Auto-refresh JavaScript
- ✨ Redirecionamento pós-login configurável
- ✨ Suporte HTML em anúncios fallback
- ✨ Observadores para invalidação de cache
- 🌐 Suporte bilíngue (EN/PT-BR)
- 🎨 Design responsivo com CSS Grid
- ⚡ Otimizações de performance

---

## 🎓 Glossário

**Term** | **Definição**
---------|-------------
**Local Plugin** | Tipo de plugin Moodle que adiciona funcionalidades customizadas
**Observer** | Classe que "escuta" eventos do Moodle e executa ações
**Mustache** | Template engine usado pelo Moodle para renderização
**Cache** | Sistema de armazenamento temporário para dados frequentes
**AJAX** | Técnica para atualizar partes da página sem reload
**CSRF** | Cross-Site Request Forgery (proteção com sesskey)
**TTL** | Time To Live - tempo de expiração de cache
**KPI** | Key Performance Indicator - métrica importante (usado para números grandes)
**CTA** | Call To Action - botão ou link de ação
**Fallback** | Valor/ação alternativa quando primária falha

---

## 🔍 Palavras-chave para Busca

`moodle local plugin`, `dashboard plugin`, `student portal`, `primeirapagina pro`, `academic hub`, `moodle 4.0`, `custom landing page`, `moodle dashboard`, `event observers`, `cache api`, `mustache templates`, `responsive design`, `ajax refresh`, `message counter`, `student engagement`

---

**Última atualização:** 21 de outubro de 2025  
**Documento mantido por:** Análise automatizada  
**Versão do documento:** 1.0.0
