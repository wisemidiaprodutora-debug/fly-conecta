<?php
/**
 * Flyconecta — registro de cliques nos botões de WhatsApp
 * ------------------------------------------------------------------
 * Cada botão do site que abre o WhatsApp tem um identificador curto
 * ("ref", ex.: site-plano-flypower). O mesmo código vai no fim da mensagem
 * que o cliente envia e é gravado aqui quando ele clica.
 *
 * Armazenamento: data/cliques.log, uma linha por clique no formato
 *   AAAA-MM-DD HH:MM:SS<TAB>ref
 * Sem IP, sem nome, sem telefone — só data/hora e o botão.
 * O arquivo fica fora do Git (deploy não o apaga) e é bloqueado no .htaccess.
 * ------------------------------------------------------------------
 */

if (!defined('FLYCL_FILE')) {
    define('FLYCL_FILE', __DIR__ . '/data/cliques.log');
}

date_default_timezone_set('America/Sao_Paulo');

/** Identificador válido: minúsculas, dígitos e hífen, 3 a 40 caracteres. */
function flycl_ref_valido($ref)
{
    return is_string($ref) && preg_match('/^[a-z0-9][a-z0-9-]{1,38}[a-z0-9]$/', $ref) === 1;
}

/** Grava um clique. Devolve true se gravou. */
function flycl_registrar($ref, $quando = null)
{
    if (!flycl_ref_valido($ref)) {
        return false;
    }
    $dir = dirname(FLYCL_FILE);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $ts = $quando === null ? time() : (int) $quando;
    $linha = date('Y-m-d H:i:s', $ts) . "\t" . $ref . "\n";
    return file_put_contents(FLYCL_FILE, $linha, FILE_APPEND | LOCK_EX) !== false;
}

/**
 * Lê os cliques entre duas datas (AAAA-MM-DD, inclusivas).
 * Devolve lista de array('data' => 'AAAA-MM-DD', 'hora' => 'HH:MM:SS', 'ref' => '...').
 */
function flycl_ler($de, $ate)
{
    $out = array();
    if (!is_file(FLYCL_FILE)) {
        return $out;
    }
    $fp = fopen(FLYCL_FILE, 'r');
    if ($fp === false) {
        return $out;
    }
    while (($linha = fgets($fp)) !== false) {
        $linha = rtrim($linha, "\r\n");
        if ($linha === '') {
            continue;
        }
        $partes = explode("\t", $linha, 2);
        if (count($partes) !== 2) {
            continue;
        }
        $data = substr($partes[0], 0, 10);
        if ($data < $de || $data > $ate) {
            continue;
        }
        $out[] = array(
            'data' => $data,
            'hora' => substr($partes[0], 11, 8),
            'ref'  => $partes[1],
        );
    }
    fclose($fp);
    return $out;
}

/**
 * Agrega uma lista de cliques.
 * Devolve array('total' => n, 'por_ref' => array(ref => n) do maior para o
 * menor, 'por_dia' => array('AAAA-MM-DD' => n) em ordem cronológica).
 */
function flycl_resumo(array $cliques)
{
    $porRef = array();
    $porDia = array();
    foreach ($cliques as $c) {
        $porRef[$c['ref']] = isset($porRef[$c['ref']]) ? $porRef[$c['ref']] + 1 : 1;
        $porDia[$c['data']] = isset($porDia[$c['data']]) ? $porDia[$c['data']] + 1 : 1;
    }
    arsort($porRef);
    ksort($porDia);
    return array(
        'total'   => count($cliques),
        'por_ref' => $porRef,
        'por_dia' => $porDia,
    );
}

/**
 * Converte a escolha de período do painel em datas (AAAA-MM-DD).
 * $periodo: hoje | 7d | 30d | mes | custom. Para custom, usa $de/$ate;
 * se vierem inválidos, cai em 30d. $hoje serve para testes.
 */
function flycl_periodo($periodo, $de = '', $ate = '', $hoje = null)
{
    $hojeTs = $hoje === null ? time() : strtotime($hoje . ' 12:00:00');
    $hojeStr = date('Y-m-d', $hojeTs);
    switch ($periodo) {
        case 'hoje':
            return array($hojeStr, $hojeStr);
        case '7d':
            return array(date('Y-m-d', $hojeTs - 6 * 86400), $hojeStr);
        case 'mes':
            return array(date('Y-m-01', $hojeTs), $hojeStr);
        case 'custom':
            if (flycl_data_valida($de) && flycl_data_valida($ate)) {
                return $de <= $ate ? array($de, $ate) : array($ate, $de);
            }
            // sem datas válidas: cai no padrão
        case '30d':
        default:
            return array(date('Y-m-d', $hojeTs - 29 * 86400), $hojeStr);
    }
}

function flycl_data_valida($s)
{
    if (!is_string($s) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $s, $m)) {
        return false;
    }
    return checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
}

/** Nome legível de cada botão, para o painel. Refs fora da lista aparecem como estão. */
function flycl_rotulos()
{
    return array(
        'site-plano-flylight'        => 'Contratar · FlyLight',
        'site-plano-flyspeed'        => 'Contratar · FlySpeed',
        'site-plano-flypower'        => 'Contratar · FlyPower',
        'site-plano-flyselecao'      => 'Contratar · FlySeleção',
        'site-hero-flylight'         => 'Topo (rotativo) · FlyLight',
        'site-hero-flyspeed'         => 'Topo (rotativo) · FlySpeed',
        'site-hero-flypower'         => 'Topo (rotativo) · FlyPower',
        'site-hero-flyselecao'       => 'Topo (rotativo) · FlySeleção',
        'site-wifi-casa-toda'        => 'Contratar · Wi-Fi casa toda',
        'site-watch-tv'              => 'Contratar · Watch TV',
        'site-hbo-max'               => 'Contratar · HBO Max',
        'site-premiere'              => 'Contratar · Premiere',
        'site-disponibilidade'       => 'Verificar disponibilidade',
        'site-cta-whatsapp'          => 'Chamada final · WhatsApp',
        'site-ajuda'                 => 'Central de ajuda · WhatsApp',
        'site-boleto'                => '2ª via de boleto (seção Ajuda)',
        'site-boleto-rodape'         => '2ª via de boleto (rodapé)',
        'site-trabalhe-conosco'      => 'Trabalhe conosco (seção Ajuda)',
        'site-trabalhe-conosco-menu' => 'Trabalhe conosco (menu)',
        'site-trabalhe-conosco-rodape' => 'Trabalhe conosco (rodapé)',
        'site-rodape-icone'          => 'Ícone WhatsApp (rodapé)',
        'site-rodape-whatsapp'       => 'WhatsApp (15) 99738-8161 (rodapé)',
        'site-flutuante'             => 'Botão flutuante',
        'site-404-whatsapp'          => 'Página não encontrada · WhatsApp',
        'site-privacidade-contato'   => 'Privacidade · contato',
        'site-regulamento-contato'   => 'Regulamento · contato',
    );
}

function flycl_rotulo($ref)
{
    $r = flycl_rotulos();
    return isset($r[$ref]) ? $r[$ref] : $ref;
}
