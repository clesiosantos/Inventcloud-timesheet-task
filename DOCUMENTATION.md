# Documentação Técnica: Monitor de Integração GLPI 10

Este projeto consiste em um ecossistema de automação que sincroniza respostas de formulários do plugin **Formcreator** para tarefas de chamados (**TicketTasks**) via API REST do GLPI 10, incluindo um painel de monitoramento em tempo real.

## 1. Arquitetura do Sistema

O fluxo de dados segue o seguinte caminho:
1. **Origem**: Banco de Dados GLPI (Tabelas do plugin Formcreator).
2. **Processamento**: Script PHP (`sync_glpi.php`) executado via Cron.
3. **Destino**: API REST do GLPI 10 (Endpoint `TicketTask`).
4. **Monitoramento**: Dashboard React que consome os logs gerados pelo script PHP.

---

## 2. Requisitos Prévios

- **GLPI 10.x** instalado.
- **Plugin Formcreator** configurado (Formulário ID 142).
- **API REST** habilitada no GLPI.
- **App-Token** gerado no GLPI.
- **User-Token** (ou login/senha) de um usuário com permissão de escrita em tarefas.
- Servidor com suporte a **PHP 7.4+** e extensões `curl` e `pdo_mysql`.

---

## 3. Configuração de Ambiente (.env)

Crie um arquivo `.env` na raiz do projeto ou dentro da pasta `php-backend/` com as seguintes chaves:

```env
# Configurações do Banco de Dados
DB_HOST=localhost
DB_NAME=glpi_db
DB_USER=usuario_db
DB_PASS=senha_db

# Configurações da API GLPI
GLPI_URL=https://seu-glpi.com/apirest.php
APP_TOKEN=seu_app_token_aqui
USER_TOKEN=seu_user_token_usuario_aqui
```

---

## 4. O Script de Sincronização (`sync_glpi.php`)

O script realiza as seguintes operações:
1. **Pivot de Dados**: Transforma as linhas de respostas do Formcreator em colunas legíveis.
2. **Deduplicação**: Verifica se o Chamado já possui tarefas antes de tentar inserir uma nova.
3. **Conversão de Unidades**: Calcula a duração em segundos (exigido pela API).
4. **Finalização Automática**: Após inserir a tarefa, o chamado é movido para o status **6 (Fechado)**.

### Mapeamento de Campos (SQL ➔ API)

| Campo SQL | Campo API GLPI | Observação |
| :--- | :--- | :--- |
| `ticket_id` | `tickets_id` | ID do Chamado |
| `titulo` | `content` | Resposta da pergunta 1643 |
| `segundos` | `actiontime` | Tempo de ação em segundos |
| `data_inicio` | `begin` | Formato Y-m-d H:i:s |
| `data_fim` | `end` | Formato Y-m-d H:i:s |
| `requisitante_id` | `users_id` | Autor (Técnico requisitante) |
| `requisitante_id` | `users_id_tech` | Técnico da tarefa |
| `area_atuacao_codigo`| `groups_id_tech` | Grupo responsável (Pergunta 1654) |
| `tipo_atend_cod` | `taskcategories_id` | Categoria da tarefa (Pergunta 1655) |
| Fixo: 1 | `is_private` | Tarefa Privada |
| Fixo: 2 | `state` | Estado "Feito" |

---

## 5. Automação (Cron Job)

Para que o sistema rode sozinho, adicione uma entrada no Crontab do seu servidor Linux:

```bash
# Executa a sincronização a cada 10 minutos
*/10 * * * * php /caminho/para/o/projeto/php-backend/sync_glpi.php >> /var/log/glpi_sync.log 2>&1
```

---

## 6. Logs de Execução

Os logs são armazenados em formato JSON em:
`php-backend/logs/sync_YYYY-MM-DD.json`