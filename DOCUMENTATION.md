# Documentação Técnica: Ecossistema de Integração GLPI 10

Este sistema automatiza a criação de tarefas (**TicketTasks**) no GLPI 10 a partir de formulários do plugin **Formcreator** (ID 142). Ele garante a integridade dos dados de tempo e sincroniza o fechamento dos chamados.

## 1. Arquitetura do Sistema

O sistema utiliza PHP para a lógica de backend (automação) e React para a interface de monitoramento.

- **Backend:** Scripts PHP que consultam o banco de dados via PDO e realizam escritas via API REST do GLPI.
- **Frontend:** Dashboard em React/Vite para auditoria de logs e status.

---

## 2. Tabela Comparativa (Mapeamento de Dados)

Esta tabela descreve como os dados capturados no formulário são transformados em campos da tarefa no GLPI.

| Origem (Formcreator ID 142) | Campo Destino (API GLPI) | Tipo de Dado | Lógica de Conversão |
| :--- | :--- | :--- | :--- |
| Pergunta ID 1643 | `content` | String | Texto bruto da resposta |
| Pergunta ID 1651 (Início) | `begin` | Datetime | Formato Y-m-d H:i:s |
| Pergunta ID 1652 (Fim) | `end` | Datetime | Formato Y-m-d H:i:s |
| Diferença (1652 - 1651) | `actiontime` | Integer | Calculado em Segundos |
| Pergunta ID 1654 (Área) | `groups_id_tech` | ID (FK) | ID do grupo técnico mapeado |
| Pergunta ID 1655 (Tipo) | `taskcategories_id` | ID (FK) | Mapeamento: Comercial=1, Outros=2 |
| Requester ID | `users_id_tech` | ID (FK) | Atribuído ao técnico que abriu o form |

---

## 3. Configuração da Automação (Cron)

Para que o sistema funcione sem intervenção humana, ele deve ser configurado no agendador de tarefas do Linux (**Crontab**).

### Parâmetros da Cron
- **Frequência:** A cada 5 minutos (`*/5 * * * *`).
- **Usuário Sugerido:** `root` ou usuário com permissão de escrita na pasta de logs.
- **Caminho do Script:** `/data/Inventcloud-timesheet-task/php-backend/sync_glpi.php`.

### Comando de Instalação
Execute `crontab -e` e insira a linha abaixo ao final do arquivo:

```bash
# GATILHO DE SINCRONIZAÇÃO GLPI - TIMESHEET
*/5 * * * * php /data/Inventcloud-timesheet-task/php-backend/sync_glpi.php >> /data/Inventcloud-timesheet-task/php-backend/logs/cron.log 2>&1
```

> **Nota:** O redirecionamento `>> .../cron.log 2>&1` garante que tanto as saídas de sucesso quanto os erros sejam registrados para auditoria.

---

## 4. Componentes de Software

### 4.1. `sync_glpi.php` (O Motor)
- Verifica tickets do Form 142 sem tarefas.
- Gerencia o ciclo de status (Reabre se fechado -> Insere -> Fecha).
- Gera logs diários em formato JSON para o Dashboard.

### 4.2. `fix_dates.php` (Utilitário)
- Script para correção em lote de datas de fechamento divergentes da data de abertura.

### 4.3. Interface de Monitoramento
- Localizada na raiz do projeto, acessível via porta configurada no Vite (ex: 8080).

---

## 5. Resolução de Problemas

1. **Log de Erro Cron:** Verifique `/data/Inventcloud-timesheet-task/php-backend/logs/cron.log`.
2. **Falha de API:** Valide se o `USER_TOKEN` e `APP_TOKEN` no arquivo `.env` não expiraram.
3. **DB Connection:** Garanta que o host do banco de dados permita conexões do IP onde o script roda.

---
*Última atualização: 2024-05-20*