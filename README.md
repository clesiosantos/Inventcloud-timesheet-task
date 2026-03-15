# Monitor de Integração GLPI (Timesheet Task)

Sistema de automação para transformar formulários do GLPI Formcreator em tarefas estruturadas com cálculo automático de tempo.

## 🛠️ Operação do Backend

O backend PHP reside em `/data/Inventcloud-timesheet-task/php-backend/`.

### Configuração
As credenciais estão no arquivo `.env`. Nunca altere as URLs sem validar os tokens de acesso.

### Automação (Cron)
A tarefa está agendada para rodar a cada **5 minutos**.
Para monitorar as execuções em tempo real:
```bash
tail -f /data/Inventcloud-timesheet-task/php-backend/logs/cron.log
```

## 🖥️ Painel de Monitoramento (Frontend)

O painel visual permite acompanhar se os tickets estão sendo processados corretamente.

1. Instale as dependências: `npm install`
2. Inicie o servidor: `npm run dev`

## 📂 Estrutura de Logs
*   `php-backend/logs/sync_YYYY-MM-DD.json`: Logs detalhados de cada ticket processado.
*   `php-backend/logs/cron.log`: Registro de saída bruta do agendador do Linux.

---
*Desenvolvido por Dyad AI.*