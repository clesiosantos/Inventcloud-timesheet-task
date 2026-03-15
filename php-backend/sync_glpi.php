<?php
/**
 * Integração GLPI 10 - Sincronização de Tarefas (SQL -> API)
 * Autor: Dyad AI
 * Versão: 1.6.1
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

class GLPISync {
    private $db;
    private $sessionToken;
    private $logFile;

    public function __construct() {
        $this->logFile = __DIR__ . '/logs/sync_' . date('Y-m-d') . '.json';
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
        if (!is_dir(__DIR__ . '/logs')) mkdir(__DIR__ . '/logs', 0755, true);
        $currentLogs = file_exists($this->logFile) ? json_decode(file_get_contents($this->logFile), true) : [];
        $currentLogs[] = $logEntry;
        file_put_contents($this->logFile, json_encode($currentLogs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        $color = ($status === 'Erro') ? "\033[31m" : (($status === 'Sucesso') ? "\033[32m" : "\033[36m");
        $reset = "\033[0m";
        echo "{$color}[$status]{$reset} " . date('H:i:s') . " - $message\n";
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
        
        $responseRaw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $response = json_decode($responseRaw, true);

        if ($httpCode !== 200) {
            $msg = $response[0] ?? ($response['message'] ?? 'Erro desconhecido');
            if (is_array($msg)) $msg = implode(' ', $msg);
            $this->log('Erro', "API recusou autenticação (HTTP $httpCode): $msg");
            return false;
        }

        return $response['session_token'] ?? false;
    }

    public function run($targetTicketId = null) {
        $startTime = microtime(true);
        $this->log('Info', "Iniciando processamento");

        $this->sessionToken = $this->initSession();
        if (!$this->sessionToken) return;

        try {
            $filterSql = $targetTicketId ? "AND t.ticket_id = :ticket_id" : "";
            
            $sql = "
                SELECT
                    t.ticket_id,
                    tk.status AS ticket_status,
                    tk.date_mod AS data_modificacao,
                    tk.solvedate AS data_solucao,
                    tk.closedate AS data_fechamento,
                    u.id AS requisitante_id,
                    MAX(CASE WHEN t.id_pergunta = 1643 THEN t.resposta END) AS titulo,
                    MAX(CASE WHEN t.id_pergunta = 1651 THEN t.resposta END) AS data_inicio,
                    MAX(CASE WHEN t.id_pergunta = 1652 THEN t.resposta END) AS data_fim,
                    MAX(CASE WHEN t.id_pergunta = 1654 THEN t.grupo_id END) AS area_atuacao_codigo,
                    CASE 
                        WHEN MAX(CASE WHEN t.id_pergunta = 1655 THEN t.resposta END) = 'Comercial' THEN 1
                        WHEN MAX(CASE WHEN t.id_pergunta = 1655 THEN t.resposta END) = 'Fora do Horario' THEN 2
                        ELSE 1
                    END AS tipo_atendimento_codigo,
                    TIMESTAMPDIFF(SECOND, MAX(CASE WHEN t.id_pergunta = 1651 THEN t.resposta END), MAX(CASE WHEN t.id_pergunta = 1652 THEN t.resposta END)) AS segundos
                FROM (
                    SELECT
                        fa.requester_id, it.tickets_id AS ticket_id, q.id AS id_pergunta, a.answer AS resposta, g.id AS grupo_id
                    FROM glpi_plugin_formcreator_formanswers fa
                    JOIN glpi_plugin_formcreator_answers a ON a.plugin_formcreator_formanswers_id = fa.id
                    JOIN glpi_plugin_formcreator_questions q ON q.id = a.plugin_formcreator_questions_id
                    LEFT JOIN glpi_items_tickets it ON it.items_id = fa.id AND it.itemtype = 'PluginFormcreatorFormAnswer'
                    LEFT JOIN glpi_groups g ON q.id = 1654 AND g.id = a.answer
                    WHERE fa.plugin_formcreator_forms_id = 142 AND q.id IN (1643,1651,1652,1654,1655)
                ) t
                LEFT JOIN glpi_users u ON u.id = t.requester_id
                LEFT JOIN glpi_tickets tk ON tk.id = t.ticket_id
                WHERE t.ticket_id IS NOT NULL
                $filterSql
                AND NOT EXISTS (SELECT 1 FROM glpi_tickettasks tt WHERE tt.tickets_id = t.ticket_id)
                GROUP BY t.ticket_id, tk.status, tk.date_mod, tk.solvedate, tk.closedate, u.id
                ORDER BY t.ticket_id";

            $stmt = $this->db->prepare($sql);
            if ($targetTicketId) $stmt->bindParam(':ticket_id', $targetTicketId, PDO::PARAM_INT);
            $stmt->execute();
            $pendentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($pendentes) === 0) {
                $this->log('Info', "Nenhum dado pendente para processar");
            }

            foreach ($pendentes as $row) {
                $ticketId = (int)$row['ticket_id'];
                $isClosed = ((int)$row['ticket_status'] === 6);
                
                $this->log('Info', "Ticket #$ticketId - Status Atual: " . $row['ticket_status']);

                // 1. Reabrir apenas se estiver fechado (status 6)
                if ($isClosed) {
                    $resReopen = $this->callAPI("Ticket/$ticketId", 'PUT', ['id' => $ticketId, 'status' => 2]);
                    if ($resReopen['code'] != 200) {
                        $this->log('Erro', "Falha ao reabrir Ticket #$ticketId", ['response' => $resReopen['data']]);
                        continue;
                    }
                    $this->log('Sucesso', "Ticket #$ticketId reaberto temporariamente");
                }

                // 2. Inserir a Tarefa
                $payloadTask = [
                    'tickets_id' => $ticketId,
                    'content' => $row['titulo'],
                    'actiontime' => (int)$row['segundos'],
                    'begin' => $row['data_inicio'],
                    'end' => $row['data_fim'],
                    'is_private' => 1,
                    'state' => 2,
                    'users_id' => (int)$row['requisitante_id'],
                    'users_id_tech' => (int)$row['requisitante_id'],
                    'taskcategories_id' => (int)$row['tipo_atendimento_codigo']
                ];

                if ((int)$row['area_atuacao_codigo'] > 0) {
                    $payloadTask['groups_id_tech'] = (int)$row['area_atuacao_codigo'];
                }

                $resTask = $this->callAPI('TicketTask', 'POST', $payloadTask);

                if ($resTask['code'] == 201) {
                    $this->log('Sucesso', "Tarefa inserida no Ticket #$ticketId");
                    
                    // 3. Finalizar Chamado
                    $payloadFinal = ['id' => $ticketId, 'status' => 6];
                    
                    // Se estava fechado, restauramos as datas originais
                    if ($isClosed) {
                        $payloadFinal['date_mod'] = $row['data_modificacao'];
                        $payloadFinal['solvedate'] = $row['data_solucao'];
                        $payloadFinal['closedate'] = $row['data_fechamento'];
                    }
                    
                    $resFinal = $this->callAPI("Ticket/$ticketId", 'PUT', $payloadFinal);
                    
                    if ($resFinal['code'] == 200) {
                        $this->log('Sucesso', "Ticket #$ticketId finalizado" . ($isClosed ? " com datas restauradas" : ""));
                    }
                } else {
                    $this->log('Erro', "Falha na tarefa do Ticket #$ticketId", ['response' => $resTask['data']]);
                    // Tentar voltar para o status original se houver erro
                    if ($isClosed) $this->callAPI("Ticket/$ticketId", 'PUT', ['id' => $ticketId, 'status' => 6]);
                }
            }

        } catch (Exception $e) {
            $this->log('Erro', 'Exceção: ' . $e->getMessage());
        }

        $duration = round(microtime(true) - $startTime, 2);
        $this->log('Fim', 'Execução encerrada', ['duration' => $duration . 's']);
    }
}

$sync = new GLPISync();
$ticketArg = isset($argv[1]) ? (int)$argv[1] : null;
$sync->run($ticketArg);