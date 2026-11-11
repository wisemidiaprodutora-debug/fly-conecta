<?php
/**
 * Flyconecta — registra um clique em botão de WhatsApp.
 * POST ref=<identificador>   (enviado pelo script.js via sendBeacon)
 * Responde 204 sempre que possível; nunca devolve dados.
 */

require __DIR__ . '/../libcliques.php';

header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$ref = isset($_POST['ref']) ? (string) $_POST['ref'] : '';
if (!flycl_ref_valido($ref)) {
    http_response_code(400);
    exit;
}

flycl_registrar($ref);
http_response_code(204);
