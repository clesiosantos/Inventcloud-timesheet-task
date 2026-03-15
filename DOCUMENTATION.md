# Documentação Técnica: Ecossistema de Integração GLPI 10

Este sistema automatiza a criação de tarefas (**TicketTasks**) no GLPI 10 a partir de formulários do plugin **Formcreator** (ID 142), garantindo que o tempo de atendimento seja registrado corretamente e o chamado seja encerrado de forma estruturada.

## 1. Arquitetura e Fluxo de Dados

O sistema opera em um modelo híbrido:
1.  **Extração (SQL):** O script PHP lê diretamente do banco de dados do GLPI as respostas do Formcreator.
2.  **Processamento (PHP):** Consolida respostas em colunas (Pivot) e calcula a duração da tarefa.
3.  **Carga (API REST):** Insere a tarefa no chamado correspondente via API oficial do GLPI.
4.  **Monitoramento (React/Vite):** Um painel web consome os logs JSON gerados para visualização do status das operações.

---

## 2. Requisitos de Ambiente

*   **Servidor:** Linux (caminho base: `/data/Inventcloud-timesheet-task/`)
*   **GLPI:** Versão 10.x com API REST ativa.
*   **Banco de Dados:** MySQL/MariaDB com acesso de leitura/escrita.
*   **Tokens:** `App-Token` (da API) e `User-Token` (do técnico executor).

---

## 3. Mapeamento de Campos (Formulário 142)

| Pergunta ID | Descrição no Formcreator | Campo API GLPI | Lógica / Formato |
| :--- | :--- | :--- | :--- |
| **1643** | Título/Descrição da Tarefa | `content` | Texto simples |
| **1651** | Data/Hora de Início | `begin` | YYYY-MM-DD HH:MM:SS |
| **1652** | Data/Hora de Fim | `end` | YYYY-MM-DD HH:MM:SS |
| **1654** | Área de Atuação | `groups_id_tech` | ID do Grupo (Mapeado via SQL) |
| **1655** | Tipo de Atendimento | `taskcategories_id` | Mapeado: Comercial (1), Outros (2) |
| **Cálculo** | Diferença entre 1652 e 1651 | `actiontime` | Inteiro em segundos |

---

## 4. Componentes do Sistema

### 4.1. Sincronizador Principal (`sync_glpi.php`)
Localizado em `php-backend/sync_glpi.php`. 
*   Identifica chamados que vieram do formulário 142 e **não possuem tarefas**.
*   Se o chamado estiver fechado (status 6), ele reabre temporariamente (status 2), insere a tarefa e fecha novamente.
*   **Logs:** Gera arquivos JSON diários em `php-backend/logs/sync_YYYY-MM-DD.json`.

### 4.2. Corretor Retroativo (`fix_dates.php`)
Localizado em `php-backend/fix_dates.php`.
*   Corrige as datas de solução (`solvedate`) e fechamento (`closedate`) de chamados antigos para que coincidam com a data de abertura, evitando distorções em indicadores (SLA).

### 4.3. Dashboard de Monitoramento
Interface React que exibe:
*   Status do backend e conectividade.
*   Taxa de sucesso/erro das últimas 24h.
*   Tabela detalhada de logs para auditoria rápida.

---

## 5. Automação (Cron Job)

O sistema está configurado para rodar automaticamente a cada 5 minutos no servidor.

**Linha de execução no Crontab:**
```bash
*/5 * * * * php /data/Inventcloud-timesheet-task/php-backend/sync_glpi.php >> /data/Inventcloud-timesheet-task/php-backend/logs/cron.log 2>&1
```

---

## 6. Comandos Úteis de Manutenção

*   **Executar sincronização manualmente:**
    `php /data/Inventcloud-timesheet-task/php-backend/sync_glpi.php`

*   **Sincronizar um único ticket específico:**
    `php /data/Inventcloud-timesheet-task/php-backend/sync_glpi.php [ID_DO_TICKET]`

*   **Verificar erros da automação:**
    `tail -f /data/Inventcloud-timesheet-task/php-backend/logs/cron.log`

---
*Documentação atualizada em: 2024-05-20*