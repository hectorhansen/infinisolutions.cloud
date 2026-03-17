<?php
class Assignment {

    /**
     * Atribui uma conversa a um operador aleatório disponível.
     * Retorna o operator_id ou null se nenhum disponível (vai para fila).
     */
    public static function assign(string $conversationId): ?string {
        $pdo = DB::connect();

        // Buscar operadores elegíveis (online + abaixo do limite)
        $stmt = $pdo->prepare("
            SELECT o.id
            FROM operators o
            WHERE o.is_active = 1
              AND o.status = 'online'
              AND (
                SELECT COUNT(*) FROM conversations c
                WHERE c.operator_id = o.id
                  AND c.status IN ('open','pending')
              ) < o.max_concurrent
            ORDER BY RAND()
            LIMIT 1
        ");
        $stmt->execute();
        $operator = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$operator) {
            // Sem operadores disponíveis — coloca na fila
            self::enqueue($conversationId);
            return null;
        }

        $operatorId = $operator['id'];

        // Atribuição em transação para evitar race condition
        $pdo->beginTransaction();
        try {
            $pdo->prepare("
                UPDATE conversations
                SET operator_id = ?, status = 'open', assigned_at = NOW()
                WHERE id = ?
            ")->execute([$operatorId, $conversationId]);

            $pdo->prepare("
                INSERT INTO assignment_log (conversation_id, to_operator_id, reason)
                VALUES (?, ?, 'auto_random')
            ")->execute([$conversationId, $operatorId]);

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }

        // Notificar operador via polling_cache
        self::notifyOperator($operatorId, $conversationId);

        return $operatorId;
    }

    private static function enqueue(string $conversationId): void {
        $pdo = DB::connect();
        // Calcular próxima posição na fila
        $pos = $pdo->query("
            SELECT COALESCE(MAX(queue_position), 0) + 1 FROM conversations WHERE status = 'waiting'
        ")->fetchColumn();

        $pdo->prepare("
            UPDATE conversations
            SET status = 'waiting', queue_position = ?
            WHERE id = ?
        ")->execute([$pos, $conversationId]);
    }

    private static function notifyOperator(string $operatorId, string $conversationId): void {
        $pdo = DB::connect();
        $pdo->prepare("
            INSERT INTO polling_cache (operator_id, event_type, conversation_id)
            VALUES (?, 'new_conversation', ?)
        ")->execute([$operatorId, $conversationId]);
    }

    /**
     * Tenta reatribuir conversas em fila (chamado pelo cron).
     */
    public static function processQueue(): void {
        $pdo = DB::connect();
        $waiting = $pdo->query("
            SELECT id FROM conversations
            WHERE status = 'waiting'
            ORDER BY queue_position ASC
        ")->fetchAll(PDO::FETCH_COLUMN);

        foreach ($waiting as $convId) {
            $result = self::assign($convId);
            if (!$result) break; // Sem operadores disponíveis, parar
        }
    }
}
