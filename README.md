# 📊 Dashboard - Portal Acadêmico para Moodle

[![Moodle](https://img.shields.io/badge/Moodle-4.0%2B-orange)](https://moodle.org/)
[![PHP](https://img.shields.io/badge/PHP-8.0%2B-blue)](https://php.net/)
[![License](https://img.shields.io/badge/License-MIT-green)](LICENSE)

> 🎯 **Dashboard centralizado que agrega informações acadêmicas do estudante em uma única página.**

## 🌟 Funcionalidades

**6 Cards Informativos:**
- 📚 **Minhas Disciplinas** - Cursos matriculados
- ✅ **Atividades Pendentes** - Tarefas com prazos
- 📅 **Próximos Eventos** - Agenda unificada
- 📬 **Mensagens** - Conversas não lidas
- 📢 **Avisos** - Comunicados importantes
- 🛠️ **Suporte Técnico** - Informações de contato

**Características:**
- ✅ Design responsivo (mobile, tablet, desktop)
- ✅ Cache otimizado para performance
- ✅ Redirecionamento automático após login (opcional)
- ✅ Sistema hierárquico de avisos

## 🚀 Instalação

### Via Interface do Moodle (Recomendado)
1. Acesse **Administração → Plugins → Instalar plugins**
2. Faça upload do arquivo ZIP do plugin
3. Complete a instalação seguindo as instruções

### Via FTP/SSH
1. Extraia os arquivos para `/path/to/moodle/local/dashboard/`
2. Configure permissões: `chmod -R 755 dashboard/`
3. Acesse a administração do Moodle para completar a instalação

## ⚙️ Configuração

### Configurações Básicas
Acesse: **Administração → Plugins → Plugins locais → Dashboard**

- **Redirecionamento**: Ative para redirecionar usuários após login
- **Suporte Técnico**: Configure informações de contato

### Sistema de Avisos
**Opção 1 - Fórum de Site News:**
1. Vá em **Administração → Front page → Front page settings**
2. Selecione "News items" em "Front page"

**Opção 2 - Fórum Personalizado:**
1. Crie um fórum com nome "Avisos" ou "Anúncios"
2. Coloque no curso principal do site

## 🛠️ Requisitos

- **Moodle**: 4.0 ou superior
- **PHP**: 8.0 ou superior
- **Navegadores**: Chrome 70+, Firefox 65+, Safari 12+, Edge 79+

## 📱 Acesso

Após a instalação, acesse: `http://seu-moodle/local/dashboard/`

## 📄 Licença

Este projeto está licenciado sob a [Licença MIT](LICENSE).

---

**Desenvolvido para melhorar a experiência educacional no Moodle** 🎓