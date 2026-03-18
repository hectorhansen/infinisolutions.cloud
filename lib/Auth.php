<?php
class Auth {
    
    /**
     * Gera um token aleatório, faz hash e salva na sessão do operador.
     * Retorna o token em texto limpo para o cliente.
     */
    public static function createSession(string $operatorId): string {
        $pdo = DB::connect();
        
        // Gera um token seguro de 64 caracteres
        $rawToken = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $rawToken . SESSION_SECRET);
        
        // Expira em 30 dias
        $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));
        
        $stmt = $pdo->prepare("
            INSERT INTO operator_sessions (operator_id, token, user_agent, ip_address, expires_at)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $operatorId,
            $hashedToken,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $_SERVER['REMOTE_ADDR']     ?? null,
            $expiresAt
        ]);
        
        // Atualiza last_seen
        $pdo->prepare("UPDATE operators SET last_seen_at = NOW(), status = 'online' WHERE id = ?")
            ->execute([$operatorId]);
            
        return $rawToken;
    }

    /**
     * Valida o Header 'Authorization: Bearer <token>'.
     * Retorna o ID do operador se válido e não expirado/revogado.
     */
    public static function getSessionOperatorId(): ?string {
        $headers = function_exists('apache_request_headers') ? apache_request_headers() : $_SERVER;
        
        // Pega header Authorization ou HTTP_AUTHORIZATION
        $authHeader = $headers['Authorization'] ?? $headers['HTTP_AUTHORIZATION'] ?? null;
        
        if (!$authHeader || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return null; // Sem token
        }
        
        $rawToken = $matches[1];
        $hashedToken = hash('sha256', $rawToken . SESSION_SECRET);
        
        $pdo = DB::connect();
        $stmt = $pdo->prepare("
            SELECT operator_id, expires_at, revoked_at 
            FROM operator_sessions 
            WHERE token = ?
        ");
        $stmt->execute([$hashedToken]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$session || $session['revoked_at'] || strtotime($session['expires_at']) < time()) {
            return null; // Inválido, revogado ou expirado
        }
        
        // Update last_seen silenciosamente (opcional a cada requisição, ou via polling apenas)
        return $session['operator_id'];
    }

    /**
     * Exige sessão ativa; encerra com 401 caso não haja.
     * @param array $allowedRoles Array opcional com os roles permitidos
     * @return string O ID do operador logado
     */
    public static function requireSession(array $allowedRoles = []): string {
        $operatorId = self::getSessionOperatorId();
        
        if (!$operatorId) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        if (!empty($allowedRoles)) {
            $pdo = DB::connect();
            $role = $pdo->query("SELECT role FROM operators WHERE id = " . $pdo->quote($operatorId))
                        ->fetchColumn();
            
            if (!in_array($role, $allowedRoles)) {
                http_response_code(403);
                echo json_encode(['error' => 'Forbidden role']);
                exit;
            }
        }
        
        return $operatorId;
    }

    /**
     * Revoga a sessão atual fornecida no header.
     */
    public static function revokeCurrentSession(): void {
        $headers = function_exists('apache_request_headers') ? apache_request_headers() : $_SERVER;
        $authHeader = $headers['Authorization'] ?? $headers['HTTP_AUTHORIZATION'] ?? null;
        
        if ($authHeader && preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $hashedToken = hash('sha256', $matches[1] . SESSION_SECRET);
            DB::connect()->prepare("UPDATE operator_sessions SET revoked_at = NOW() WHERE token = ?")
                         ->execute([$hashedToken]);
        }
    }
}
