<?php
/**
 * Integração GLPI 10 - Sincronização de Tarefas
 * Autor: Dyad AI
 * Versão: 1.1.0
 */

class EnvLoader {
    public static function load($path) {
        if (!file_exists($path)) {
            return false;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);

            if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                putenv(sprintf('%s=%s', $name, $value));
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
        return true;
    }
}

// Tenta carregar o .env do diretório atual ou da raiz
if (!EnvLoader::load(__DIR__ . '/.env')) {
    EnvLoader::load(__DIR__ . '/../.env');
}

class GLPISync {
    private $db;
    private $sessionToken;
    private $logFile;

    public function __construct() {
        $this->logFile = __DIR__ . '/logs/sync_' . date('Y-m-d') . '.json';
        $this->initDatabase();
    }

    private function initDatabase() {
        try {
            $host = getenv('DB_HOST');
            $name = getenv('DB_NAME');
            $user = getenv('DB_USER');
            $pass = getenv('DB_PASS');

            $this->db = new PDO("mysql:host=$host;dbname=$name;charset=utf8", $user, $pass);
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

        if (!is_dir(__DIR__ . '/logs')) {
            mkdir(__DIR__ . '/logs', 0755, true);
        }

        $currentLogs = file_exists($this->logFile) ? json_decode(file_get_contents($this->logFile), true) : [];
        $currentLogs[] = $logEntry;
        file_put_contents($this->logFile, json_encode($currentLogs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        echo "[$status] " . date('H:i:s') . " - $message\n";
    }

    private function getSessionToken() {
        $url = getenv('GLPI_URL') . '/initSession';
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'App-Token: ' . getenv('APP_TOKEN'),
            'Authorization: user_token ' . getenv('USER_TOKEN')
        ]);
        
        $response = curl_exec($ch);
        $data = json_decode($response, true);
        curl_close($ch);

        if (isset($data['session_token'])) {
            return $data['session_token'];
        }

        $this->log('Erro', 'Falha ao iniciar sessão na API do GLPI. Verifique as credenciais no .env');
        return false;
    }

    public function run() {
        $startTime = microtime(true);
        $this->log('Info', 'Iniciando processamento da cron');

        $this->sessionToken = $this->getSessionToken();
        if (!$this->sessionToken) return;

        // AGUARDANDO SUA CONSULTA SQL E LÓGICA DE INSERÇÃO
        $this->log('Aviso', 'Aguardando consulta SQL do usuário para prosseguir com a sincronização');

        $duration = round(microtime(true) - $startTime, 2);
        $this->log('Sucesso', 'Execução finalizada', ['duration' => $duration . 's']);
    }
}

$sync = new GLPISync();
$sync->run();