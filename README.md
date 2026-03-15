# Monitor de Integração GLPI

Automação de tarefas para GLPI 10 a partir de formulários Formcreator.

## 🚀 Início Rápido

1. **Configurar Backend**:
   - Vá para `php-backend/`.
   - Configure o arquivo `.env` com suas credenciais de banco e API.
   - Teste a execução: `php sync_glpi.php`.

2. **Configurar Frontend**:
   - Instale as dependências: `npm install`.
   - Inicie o painel: `npm run dev`.

3. **Automação**:
   - Adicione o script `sync_glpi.php` ao seu CRON (recomendado a cada 10 min).

## 📄 Documentação Completa
Consulte o arquivo [DOCUMENTATION.md](./DOCUMENTATION.md) para detalhes de mapeamento de campos, requisitos e arquitetura.

---
*Desenvolvido com Dyad AI.*