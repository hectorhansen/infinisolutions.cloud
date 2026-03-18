<?php
// ============================================
// GRANFINO - Configuração do Banco de Dados
// config.php
// ============================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'u752688765_granfino');
define('DB_USER', 'u752688765_granfino');
define('DB_PASS', 'GranfinoSAC2026');
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME', 'Granfino · Gestão de Qualidade');
define('SESSION_TIMEOUT', 3600); // 1 hora

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die('Erro de conexão com o banco de dados: ' . $e->getMessage());
        }
    }
    return $pdo;
}

function auth(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isset($_SESSION['atendente_id'])) {
        header('Location: login.php');
        exit;
    }
    // timeout de sessão
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
        session_destroy();
        header('Location: login.php?timeout=1');
        exit;
    }
    $_SESSION['last_activity'] = time();
}

function municipiosPorEstado(string $uf): array {
    // Lista simplificada dos principais municípios por UF
    $mapa = [
        'RJ' => ['Rio de Janeiro','Niterói','Nova Iguaçu','Duque de Caxias','São Gonçalo','Belford Roxo','Campos dos Goytacazes','Petrópolis','Volta Redonda','Macaé'],
        'SP' => ['São Paulo','Campinas','Guarulhos','Santo André','São Bernardo do Campo','Osasco','Ribeirão Preto','Sorocaba','Santos','São José dos Campos'],
        'MG' => ['Belo Horizonte','Uberlândia','Contagem','Juiz de Fora','Betim','Montes Claros','Ribeirão das Neves','Uberaba','Governador Valadares','Ipatinga'],
        'ES' => ['Vitória','Vila Velha','Cariacica','Serra','Cachoeiro de Itapemirim','Linhares','São Mateus','Colatina','Guarapari','Aracruz'],
        'BA' => ['Salvador','Feira de Santana','Vitória da Conquista','Camaçari','Itabuna','Juazeiro','Lauro de Freitas','Ilhéus','Jequié','Teixeira de Freitas'],
        'PE' => ['Recife','Caruaru','Petrolina','Olinda','Paulista','Jaboatão dos Guararapes','Camaçari','Garanhuns','Santa Cruz do Capibaribe','Vitória de Santo Antão'],
        'CE' => ['Fortaleza','Caucaia','Juazeiro do Norte','Maracanaú','Sobral','Crato','Itapipoca','Maranguape','Iguatu','Quixadá'],
        'PA' => ['Belém','Ananindeua','Santarém','Marabá','Castanhal','Parauapebas','Itaituba','Altamira','Abaetetuba','Cametá'],
        'AM' => ['Manaus','Parintins','Itacoatiara','Manacapuru','Coari','Tefé','Tabatinga','Maués','Humaitá','Tonantins'],
        'RS' => ['Porto Alegre','Caxias do Sul','Canoas','Pelotas','Santa Maria','Gravataí','Viamão','Novo Hamburgo','São Leopoldo','Rio Grande'],
        'SC' => ['Florianópolis','Joinville','Blumenau','São José','Criciúma','Chapecó','Itajaí','Lages','Jaraguá do Sul','Palhoça'],
        'PR' => ['Curitiba','Londrina','Maringá','Ponta Grossa','Cascavel','São José dos Pinhais','Guarapuava','Paranaguá','Apucarana','Campo Grande'],
        'GO' => ['Goiânia','Aparecida de Goiânia','Anápolis','Rio Verde','Luziânia','Águas Lindas de Goiás','Valparaíso de Goiás','Trindade','Formosa','Novo Gama'],
        'DF' => ['Brasília','Ceilândia','Taguatinga','Samambaia','Planaltina','Águas Claras','Gama','Recanto das Emas','Sobradinho','Guará'],
        'MT' => ['Cuiabá','Várzea Grande','Rondonópolis','Sinop','Tangará da Serra','Cáceres','Sorriso','Lucas do Rio Verde','Primavera do Leste','Barra do Garças'],
        'MS' => ['Campo Grande','Dourados','Três Lagoas','Corumbá','Grande Dourados','Naviraí','Nova Andradina','Aquidauana','Sidrolândia','Paranaíba'],
    ];
    return $mapa[$uf] ?? [];
}
