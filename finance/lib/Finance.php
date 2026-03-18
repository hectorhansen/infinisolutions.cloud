<?php
// ============================================================
// Finance - Regras de Negócio e Cálculos
// ============================================================

namespace Finance\Lib;

use PDO;

class FinanceLogic {
    private PDO $db;

    public function __construct() {
        $this->db = FinanceDB::connect();
    }

    /**
     * Calcula e retorna todo o resumo financeiro, split e reembolsos de um projeto.
     */
    public function getProjectSummary(int $projectId): array {
        // 1. Busca os dados do projeto
        $stmt = $this->db->prepare("SELECT * FROM projects WHERE id = ?");
        $stmt->execute([$projectId]);
        $project = $stmt->fetch();

        if (!$project) {
            throw new \Exception("Projeto não encontrado.");
        }

        // 2. Busca todos os lançamentos
        $stmt = $this->db->prepare("
            SELECT e.*, c.name as category_name, p.name as partner_name, p.type as partner_type
            FROM entries e
            LEFT JOIN categories c ON e.category_id = c.id
            JOIN partners p ON e.paid_by = p.id
            WHERE e.project_id = ?
            ORDER BY e.entry_date DESC, e.id DESC
        ");
        $stmt->execute([$projectId]);
        $allEntries = $stmt->fetchAll();

        // 3. Inicializa os contadores
        $totalIncome   = 0.0;
        $totalExpenses = 0.0;
        $incomeEntries = [];
        $expenseEntries= [];
        $expensesByCategory = [];
        
        // Rastrea quanto cada parceiro (ID) pagou no total (apenas de despesas)
        $paidByPartnerMap = [];

        foreach ($allEntries as $entry) {
            $amount = (float) $entry['amount'];

            if ($entry['type'] === 'income') {
                $totalIncome += $amount;
                $incomeEntries[] = $entry;
            } else {
                $totalExpenses += $amount;
                $expenseEntries[] = $entry;

                // Agrupa por categoria
                $catName = $entry['category_name'] ?? 'Sem Categoria';
                $expensesByCategory[$catName] = ($expensesByCategory[$catName] ?? 0) + $amount;

                // Acumula quem pagou
                $pid = $entry['paid_by'];
                if (!isset($paidByPartnerMap[$pid])) {
                    $paidByPartnerMap[$pid] = [
                        'partner_id'   => $pid,
                        'partner_name' => $entry['partner_name'],
                        'type'         => $entry['partner_type'],
                        'total_paid'   => 0.0
                    ];
                }
                $paidByPartnerMap[$pid]['total_paid'] += $amount;
            }
        }

        // 4. Lucro Bruto
        $grossProfit = $totalIncome - $totalExpenses;

        // 5. Calcula o Split baseado no lucro bruto
        $splitA_pct = (float) $project['split_a'] / 100;
        $splitB_pct = (float) $project['split_b'] / 100;

        $split = [
            'partner_a' => $grossProfit * $splitA_pct,
            'partner_b' => $grossProfit * $splitB_pct
        ];

        // 6. Reembolsos
        // Lógica: Se o partner_type for 'pf', ele tirou dinheiro do próprio bolso e precisa ser reembolsado.
        $reimbursements = [];
        foreach ($paidByPartnerMap as $pid => $data) {
            if ($data['type'] === 'pf' && $data['total_paid'] > 0) {
                $reimbursements[] = [
                    'partner_id'          => $data['partner_id'],
                    'partner_name'        => $data['partner_name'],
                    'amount_to_reimburse' => $data['total_paid']
                ];
            }
        }

        // 7. Líquido a Receber (Net to Receive)
        // Valor que cada sócio PF (ID 1 = A, ID 2 = B) deve embolsar.
        // Fórmula: Sua parte do lucro (Split) - O que já adiantou do próprio bolso (que será o reembolso dele).
        // Aqui assumimos IDs fixados para A=1 e B=2 conforme o seed do banco para simplificar as lógicas.
        // Mas vamos procurar dinamicamente no paidByPartnerMap
        
        $paidPF_A = isset($paidByPartnerMap[1]) ? $paidByPartnerMap[1]['total_paid'] : 0.0;
        $paidPF_B = isset($paidByPartnerMap[2]) ? $paidByPartnerMap[2]['total_paid'] : 0.0;

        // "Eu tenho que ganhar $1000 de lucro. Mas eu já tirei do bolso $200 para pagar um freela. 
        // Eu vou receber $200 de reembolso tirado do projeto, então quanto tem que cair na minha conta 
        // como lucro puro? $1000 - $200 = $800."
        $netToReceive = [
            'partner_a' => $split['partner_a'] - $paidPF_A,
            'partner_b' => $split['partner_b'] - $paidPF_B
        ];

        // Transforma o map em array sequencial p/ o JSON
        $paidByPartner = array_values($paidByPartnerMap);

        // Retorna o Summary modelado
        return [
            'project'              => $project,
            'total_income'         => $totalIncome,
            'total_expenses'       => $totalExpenses,
            'gross_profit'         => $grossProfit,
            'split'                => $split,
            'paid_by_partner'      => $paidByPartner,
            'reimbursements'       => $reimbursements,
            'net_to_receive'       => $netToReceive,
            'expenses_by_category' => $expensesByCategory,
            'income_entries'       => $incomeEntries,
            'expense_entries'      => $expenseEntries
        ];
    }
}
