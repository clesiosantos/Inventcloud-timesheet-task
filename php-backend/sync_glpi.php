<?php
/**
 * Integração GLPI 10 - Sincronização de Tarefas
 * Autor: Dyad AI
 * Versão: 1.0.0
 * 
 * Este script deve ser configurado na cron:
 * *\/10 * * * * php /caminho/para/sync_glpi.php
 */

// Configurações de Banco de Dados GLPI
define('DB_HOST', 'localhost');
define('DB_NAME', 'glpi_database');
define('DB_USER', 'usuario_glpi');
define('DB_PASS', 'senha_segura');

// Configurações API GLPI 10
define('GLPI_URL', 'https://seu-glpi.com/apirest.php');
define('APP_TOKEN', 'seu_app_token');
define('USER_TOKEN', 'seu_user_token');

// Configuração de Log
define('LOG_FILE', __DIR__ . '/logs/sync_' . date('Y-m-d') . '.json');

class GLPISync {
    private $db;
    private $sessionToken;

    public function __construct() {
        $this->initDatabase();
    }

    private function initDatabase() {
        try {
            $this->db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            $this->log('Erro', 'Conexão DB falhou: ' . $e->getMessage());
            die();
        }
    }

    private function log($status, $message, $extra = []) {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'status' => $status,
            'message' => $message,
            'extra' => $extra
        ];

        // Cria diretório de logs se não existir
        if (!is_dir(__DIR__ . '/logs')) {
            mkdir(__DIR__ . '/logs', 0755, true);
        }

        $currentLogs = file_exists(LOG_FILE) ? json_decode(file_get_contents(LOG_FILE), true) : [];
        $currentLogs[] = $logEntry;
        file_put_contents(LOG_FILE, json_encode($currentLogs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        echo "[$status] $message\n";
    }

    private function getSessionToken() {
        $ch = curl_init(GLPI_URL . '/initSession');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'App-Token: ' . APP_TOKEN,
            'Authorization: user_token ' . USER_TOKEN
        ]);
        
        $response = curl_exec($ch);
        $data = json_decode($response, true);
        curl_close($ch);

        if (isset($data['session_token'])) {
            return $data['session_token'];
        }

        $this->log('Erro', 'Falha ao iniciar sessão na API do GLPI');
        return false;
    }

    public function run() {
        $startTime = microtime(true);
        $this->log('Info', 'Iniciando processamento da cron');

        // 1. Inicia Sessão
        $this->sessionToken = $this->getSessionToken();
        if (!$this->sessionToken) return;

        // 2. Consulta Dados (Aqui entra a query que você vai me passar)
        // Exemplo genérico:
        /*
        $query = "SUA_CONSULTA_AQUI";
        $stmt = $this->db->query($query);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        */

        $this->log('Info', 'Aguardando consulta SQL para processamento...');

        // 3. Finaliza Cron
        $duration = round(microtime(true) - $startTime, 2);
        $this->log('Sucesso', 'Sincronização finalizada', ['duration' => $duration . 's']);
    }
}

// Execução
$sync = new GLPISync();
$sync->run();