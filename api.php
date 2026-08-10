<?php
/* ============================================================
   API Calendario Estrategico 2026 - Barreto Imoveis
   Backend MySQL (substitui o Supabase)
   Estrategia: uma unica linha na tabela `eventos` guarda
   todo o estado do calendario no campo `payload` (JSON).
   ============================================================ */

/* -------- CONFIG: PREENCHER COM OS DADOS DA HOSTINGER -------- */
$DB_HOST = 'localhost';                 // na Hostinger quase sempre e 'localhost'
$DB_NAME = 'u257913324_calendario';     // banco MySQL
$DB_USER = 'u257913324_calendario';     // usuario MySQL
$DB_PASS = '@barretoMkt26';             // senha do usuario MySQL
$API_KEY = 'brr-cal-2026-K7p2Wq9fZx';   // chave compartilhada (igual no index.html)
/* ------------------------------------------------------------ */

header('Content-Type: application/json; charset=utf-8');

/* --- Autenticacao por chave compartilhada --- */
$sentKey = isset($_SERVER['HTTP_X_API_KEY']) ? $_SERVER['HTTP_X_API_KEY'] : '';
if (!hash_equals($API_KEY, $sentKey)) {
  http_response_code(401);
  echo json_encode(['error' => 'unauthorized']);
  exit;
}

/* --- Conexao MySQL via PDO --- */
try {
  $pdo = new PDO(
    "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
    $DB_USER, $DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
  );
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['error' => 'db_connection_failed']);
  exit;
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

/* --- LOAD: retorna {"payload": {...}} ou {"payload": null} --- */
if ($action === 'load') {
  $row = $pdo->query("SELECT payload FROM eventos ORDER BY id ASC LIMIT 1")
             ->fetch(PDO::FETCH_ASSOC);
  if ($row && $row['payload'] !== null) {
    // payload ja e JSON valido armazenado como texto: devolve embutido
    echo '{"payload":' . $row['payload'] . '}';
  } else {
    echo '{"payload":null}';
  }
  exit;
}

/* --- SAVE: upsert da linha unica --- */
if ($action === 'save') {
  $body = json_decode(file_get_contents('php://input'), true);
  if (!isset($body['payload'])) {
    http_response_code(400);
    echo json_encode(['error' => 'missing_payload']);
    exit;
  }
  $payloadJson = json_encode($body['payload'], JSON_UNESCAPED_UNICODE);

  $existing = $pdo->query("SELECT id FROM eventos ORDER BY id ASC LIMIT 1")
                  ->fetch(PDO::FETCH_ASSOC);
  if ($existing) {
    $stmt = $pdo->prepare("UPDATE eventos SET payload = :p, updated_at = NOW() WHERE id = :id");
    $stmt->execute([':p' => $payloadJson, ':id' => $existing['id']]);
  } else {
    $stmt = $pdo->prepare("INSERT INTO eventos (payload) VALUES (:p)");
    $stmt->execute([':p' => $payloadJson]);
  }
  echo json_encode(['ok' => true]);
  exit;
}

http_response_code(404);
echo json_encode(['error' => 'unknown_action']);
