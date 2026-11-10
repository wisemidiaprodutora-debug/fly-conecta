<?php
/**
 * Flyconecta — API pública do contador 5P1P (somente leitura).
 * GET  -> devolve o JSON atual.
 * A edição é feita pelo painel /flyconecta/admin.php.
 */

require __DIR__ . '/../lib5p1p.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(array('erro' => 'Use o painel admin.php para editar.'));
    exit;
}

$data = fly5p1p_project(fly5p1p_load());

$inst = fly5p1p_inst_load();
$data['instituicao'] = array(
    'nome'  => $inst['nome'],
    'texto' => $inst['texto'],
    'foto'  => $inst['foto'],
    'pix'   => $inst['pix'],
    'pix_nome' => $inst['pix_nome'],
);

echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
