<?php
/**
 * Script de Correção Retroativa - Sincronização de Datas
 * Autor: Dyad AI
 */

class EnvLoader {
    public static function load($path) {
        if (!file_exists($path)) return false;
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            $parts = explode('=', $line, 2);
            if (count($parts) !== 2) continue;
            $name = trim($parts[0]); $value = trim($parts[1]);
            if (!array_key_exists($name, $_SERVER)) {
                putenv("$name=$value");
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
        return true;
    }
}

EnvLoader::load(__DIR__ . '/.env') || EnvLoader::load(__DIR__ . '/../.env');

class GLPIFixer {
    private $db;
    private $sessionToken;

    public function __construct() {
        $this->initDatabase();
    }

    private function getEnvVar($name) {
        $val = getenv($name);
        if (!$val) $val = getenv(str_replace('_', '-', $name));
        return $val;
    }

    private function initDatabase() {
        try {
            $this->db = new PDO(
                "mysql:host=".$this->getEnvVar('DB_HOST').";dbname=".$this->getEnvVar('DB_NAME').";charset=utf8",
                $this->getEnvVar('DB_USER'),
                $this->getEnvVar('DB_PASS')
            );
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die("Erro DB: " . $e->getMessage());
        }
    }

    private function callAPI($endpoint, $method = 'GET', $params = []) {
        $url = $this->getEnvVar('GLPI_URL') . '/' . $endpoint;
        $ch = curl_init($url);
        $headers = [
            'Content-Type: application/json',
            'App-Token: ' . $this->getEnvVar('APP_TOKEN'),
            'Session-Token: ' . $this->sessionToken
        ];
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if (in_array($method, ['POST', 'PUT'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['input' => $params]));
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['code' => $httpCode, 'data' => json_decode($response, true)];
    }

    private function initSession() {
        $url = $this->getEnvVar('GLPI_URL') . '/initSession';
        $ch = curl_init($url);
        $headers = [
            'Content-Type: application/json',
            'App-Token: ' . $this->getEnvVar('APP_TOKEN'),
            'Authorization: user_token ' . $this->getEnvVar('USER_TOKEN')
        ];
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = json_decode(curl_exec($ch), true);
        curl_close($ch);
        return $response['session_token'] ?? false;
    }

    public function run() {
        echo "Iniciando correção de datas...\n";
        $this->sessionToken = $this->initSession();
        if (!$this->sessionToken) die("Erro na sessão API\n");

        $sql = "
            SELECT 
                tk.id as ticket_id, 
                tk.date as data_abertura
            FROM glpi_tickets tk
            JOIN glpi_items_tickets it ON it.tickets_id = tk.id AND it.itemtype = 'PluginFormcreatorFormAnswer'
            JOIN glpi_plugin_formcreator_formanswers fa ON fa.id = it.items_id
            WHERE fa.plugin_formcreator_forms_id = 142
            AND tk.status = 6
            AND EXISTS (SELECT 1 FROM glpi_tickettasks tt WHERE tt.tickets_id = tk.id)";

        $tickets = $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        echo "Encontrados " . count($tickets) . " tickets para analisar.\n";

        foreach ($tickets as $row) {
            $id = $row['ticket_id'];
            $data = $row['data_abertura'];

            echo "Tentando corrigir Ticket #$id para data $data...\n";
            
            // Tenta o update direto primeiro
            $res = $this->callAPI("Ticket/$id", 'PUT', [
                'id' => $id,
                'solvedate' => $data,
                'closedate' => $data
            ]);

            if ($res['code'] == 200) {
                echo "[Sucesso] Ticket #$id atualizado diretamente.\n";
            } else {
                echo "[Info] Update direto falhou (HTTP {$res['code']}). Tentando via reabertura...\n";
                
                // Se falhar, tenta reabrir, atualizar e fechar
                $this->callAPI("Ticket/$id", 'PUT', ['id' => $id, 'status' => 2]); // Reabre
                
                $resRetry = $this->callAPI("Ticket/$id", 'PUT', [
                    'id' => $id,
                    'status' => 6,
                    'solvedate' => $data,
                    'closedate' => $data
                ]);

                if ($resRetry['code'] == 200) {
                    echo "[Sucesso] Ticket #$id corrigido via ciclo de status.\n";
                } else {
                    $error = is_array($resRetry['data']) ? json_encode($resRetry['data']) : $resRetry['data'];
                    echo "[Erro] Falha definitiva no Ticket #$id: $error\n";
                }
            }
        }
        echo "Correção finalizada.\n";
    }
}

$fixer = new GLPIFixer();
$fixer->run();