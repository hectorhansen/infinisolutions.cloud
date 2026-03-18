<?php
// ============================================================
// Finance - Rotina de Geração de Relatórios
// ============================================================

namespace Finance\Lib;

class Reports {
    private FinanceLogic $logic;

    public function __construct() {
        $this->logic = new FinanceLogic();
    }

    /**
     * Retorna o resumo formatado diretamente para ser consumido
     * no front-end na hora de exibir o relatório.
     */
    public function getFormattedProjectReport(int $projectId): array {
        $data = $this->logic->getProjectSummary($projectId);

        // Opcional: Formatações extras, como BRL format, ou conversões 
        // caso o JSON nativo venha cru. No nosso caso o frontend fará o `toLocaleString`
        // para BRL. Aqui só asseguramos que tudo suba limpo.
        return $data;
    }
}
