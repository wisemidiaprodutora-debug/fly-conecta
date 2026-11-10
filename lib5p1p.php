<?php
/**
 * Flyconecta — 5P1P
 * Helpers de leitura/escrita do contador (arquivo JSON com trava de arquivo).
 * O contador-base é editável pelo painel admin.php; entre uma edição e outra,
 * o número exibido no site é PROJETADO a partir da média de vendas por dia
 * (fly5p1p_project), para dar a sensação de contagem ao vivo.
 *
 * Três contadores de pratos, cada um com seu próprio "baseline" (valor real
 * confirmado + instante em que foi confirmado):
 * - total (desde sempre): nunca reseta.
 * - mês: reseta para 0 sozinho na virada do mês.
 * - ano: reseta para 0 sozinho na virada do ano.
 */

if (!defined('FLY_5P1P_FILE')) {
    define('FLY_5P1P_FILE', __DIR__ . '/data/5p1p.json');
}

date_default_timezone_set('America/Sao_Paulo');

function fly5p1p_defaults()
{
    return array(
        'planos_vendidos'  => 0,
        'planos_por_dia'   => 15,
        'planos_por_prato' => 5,
        'pratos_extra'     => 0,
        'baseline_ts'      => 0,
        'pratos_mes'       => 0,
        'pratos_mes_ts'    => 0,
        'pratos_ano'       => 0,
        'pratos_ano_ts'    => 0,
        'atualizado_em'    => '',
    );
}

function fly5p1p_write(array $data)
{
    $dir = dirname(FLY_5P1P_FILE);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $fp = fopen(FLY_5P1P_FILE, 'c+');
    if ($fp === false) {
        return;
    }
    if (flock($fp, LOCK_EX)) {
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, $json . "\n");
        fflush($fp);
        flock($fp, LOCK_UN);
    }
    fclose($fp);
}

function fly5p1p_load()
{
    $data = fly5p1p_defaults();
    // arquivo vivo (gravado pelo admin); se ainda não existir, usa o exemplo versionado
    $isLive = is_file(FLY_5P1P_FILE);
    $file = $isLive ? FLY_5P1P_FILE : __DIR__ . '/data/5p1p.example.json';
    if (is_file($file)) {
        $raw = file_get_contents($file);
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $data = array_merge($data, $decoded);
        }
    }
    $data['planos_vendidos']  = max(0, (int) $data['planos_vendidos']);
    $data['planos_por_dia']   = max(0, (float) $data['planos_por_dia']);
    $data['planos_por_prato'] = max(1, (int) $data['planos_por_prato']);
    $data['pratos_extra']     = max(0, (int) $data['pratos_extra']);
    $data['baseline_ts']      = (int) $data['baseline_ts'];
    $data['pratos_mes']       = max(0, (int) $data['pratos_mes']);
    $data['pratos_mes_ts']    = (int) $data['pratos_mes_ts'];
    $data['pratos_ano']       = max(0, (int) $data['pratos_ano']);
    $data['pratos_ano_ts']    = (int) $data['pratos_ano_ts'];

    // Arquivo antigo (schema anterior) ou primeira vez: ancora as contagens em
    // agora, para as projeções começarem do valor atual sem dar salto.
    $needsHeal = $data['baseline_ts'] <= 0 || $data['pratos_mes_ts'] <= 0 || $data['pratos_ano_ts'] <= 0;
    if ($isLive && $needsHeal) {
        $now = time();
        if ($data['baseline_ts'] <= 0)   $data['baseline_ts']   = $now;
        if ($data['pratos_mes_ts'] <= 0) $data['pratos_mes_ts'] = $now;
        if ($data['pratos_ano_ts'] <= 0) $data['pratos_ano_ts'] = $now;
        fly5p1p_write($data);
    }
    return $data;
}

/**
 * Projeta um contador periódico (mês/ano) a partir de um baseline, reiniciando
 * sozinho para 0 quando o período (mês ou ano) muda em relação ao baseline.
 */
function fly5p1p_project_periodo($base, $baseTs, $ratePerDay, $now, $periodFormat, $startOfPeriodFn)
{
    $base = max(0, (int) $base);
    $baseTs = (int) $baseTs;
    if ($baseTs <= 0) {
        $baseTs = $now;
    }
    if (date($periodFormat, $baseTs) !== date($periodFormat, $now)) {
        $base = 0;
        $baseTs = $startOfPeriodFn($now);
    }
    $elapsedDays = max(0, ($now - $baseTs) / 86400);
    return (int) floor($base + $ratePerDay * $elapsedDays);
}

/**
 * Calcula os números exibidos no site agora: parte dos valores-base
 * confirmados pelo admin e soma a projeção de vendas (planos_por_dia) desde
 * então.
 */
function fly5p1p_project(array $data, $now = null)
{
    $now = $now !== null ? (int) $now : time();
    $perPlate = max(1, (int) $data['planos_por_prato']);
    $perDay   = max(0, (float) $data['planos_por_dia']);
    $baseSold = max(0, (int) $data['planos_vendidos']);
    $baseTs   = (int) $data['baseline_ts'];
    if ($baseTs <= 0) {
        $baseTs = $now;
    }

    $elapsedDays = max(0, ($now - $baseTs) / 86400);
    $sold        = (int) floor($baseSold + $perDay * $elapsedDays);

    $pratosAuto  = intdiv($sold, $perPlate);
    $pratosExtra = max(0, (int) $data['pratos_extra']);
    $pratosTotal = $pratosAuto + $pratosExtra;

    $rest   = $sold % $perPlate;
    $faltam = $rest === 0 ? $perPlate : $perPlate - $rest;
    $pct    = (int) round(($rest / $perPlate) * 100);

    $pratosPerDay = $perDay / $perPlate;

    $pratosMes = fly5p1p_project_periodo(
        $data['pratos_mes'], $data['pratos_mes_ts'], $pratosPerDay, $now,
        'Y-m',
        function ($ts) { return strtotime(date('Y-m-01 00:00:00', $ts)); }
    );
    $pratosAno = fly5p1p_project_periodo(
        $data['pratos_ano'], $data['pratos_ano_ts'], $pratosPerDay, $now,
        'Y',
        function ($ts) { return strtotime(date('Y-01-01 00:00:00', $ts)); }
    );

    return array(
        'planos_vendidos'      => $sold,
        'pratos_doados'        => $pratosTotal,
        'pratos_mes'           => $pratosMes,
        'pratos_ano'           => $pratosAno,
        'planos_por_prato'     => $perPlate,
        'planos_por_dia'       => $perDay,
        'pratos_extra'         => $pratosExtra,
        'faltam_proximo_prato' => $faltam,
        'progresso_pct'        => $pct,
        'atualizado_em'        => $data['atualizado_em'],
    );
}

/**
 * Grava os campos recebidos (apenas os presentes) e reancora as projeções em
 * agora (o valor informado passa a ser o "real" a partir deste instante).
 * Retorna o estado bruto salvo (não o projetado).
 */
function fly5p1p_save(array $fields)
{
    $data = fly5p1p_load();
    $now = time();

    if (array_key_exists('planos_vendidos', $fields) && $fields['planos_vendidos'] !== '' && $fields['planos_vendidos'] !== null) {
        $data['planos_vendidos'] = max(0, (int) $fields['planos_vendidos']);
        $data['baseline_ts'] = $now;
    }
    if (array_key_exists('planos_por_dia', $fields) && $fields['planos_por_dia'] !== '' && $fields['planos_por_dia'] !== null) {
        $data['planos_por_dia'] = max(0, (float) $fields['planos_por_dia']);
    }
    if (array_key_exists('planos_por_prato', $fields) && $fields['planos_por_prato'] !== '' && $fields['planos_por_prato'] !== null) {
        $data['planos_por_prato'] = max(1, (int) $fields['planos_por_prato']);
    }
    if (array_key_exists('pratos_extra', $fields) && $fields['pratos_extra'] !== '' && $fields['pratos_extra'] !== null) {
        $data['pratos_extra'] = max(0, (int) $fields['pratos_extra']);
    }
    if (array_key_exists('pratos_mes', $fields) && $fields['pratos_mes'] !== '' && $fields['pratos_mes'] !== null) {
        $data['pratos_mes'] = max(0, (int) $fields['pratos_mes']);
        $data['pratos_mes_ts'] = $now;
    }
    if (array_key_exists('pratos_ano', $fields) && $fields['pratos_ano'] !== '' && $fields['pratos_ano'] !== null) {
        $data['pratos_ano'] = max(0, (int) $fields['pratos_ano']);
        $data['pratos_ano_ts'] = $now;
    }
    if ($data['planos_por_prato'] < 1) {
        $data['planos_por_prato'] = 5;
    }
    if ($data['baseline_ts'] <= 0)   $data['baseline_ts']   = $now;
    if ($data['pratos_mes_ts'] <= 0) $data['pratos_mes_ts'] = $now;
    if ($data['pratos_ano_ts'] <= 0) $data['pratos_ano_ts'] = $now;
    $data['atualizado_em'] = date('d/m/Y');

    fly5p1p_write($data);
    return $data;
}

/* ===================================================================
 * Instituição do mês (nome, texto, foto e chave PIX do botão "Colabore")
 * Editada pelo admin.php; o site lê pela API e troca o card sozinho.
 * Fotos enviadas vão para uploads/5p1p/ (fora do Git, como o contador).
 * =================================================================== */

if (!defined('FLY_5P1P_INST_FILE')) {
    define('FLY_5P1P_INST_FILE', __DIR__ . '/data/instituicao.json');
}
if (!defined('FLY_5P1P_UPLOAD_DIR')) {
    define('FLY_5P1P_UPLOAD_DIR', __DIR__ . '/uploads/5p1p');
}

function fly5p1p_inst_defaults()
{
    // espelha o que está no index.html (valor mostrado antes da API responder)
    return array(
        'nome'          => 'ACDC Taquarivaí',
        'texto'         => 'A ACDC Taquarivaí recebe cerca de 30 crianças no contraturno escolar. Lá elas têm reforço nos estudos, música, esporte e tempo para brincar. A instituição vive de doações, e é para ela que vão as refeições do 5P1P neste mês.',
        'foto'          => 'assets/5p1p/acdc-taquarivai-colagem.jpg',
        'pix'           => '381.961.718-38',
        'pix_nome'      => 'Jamily da Silva Almeida',
        'atualizado_em' => '',
    );
}

function fly5p1p_inst_load()
{
    $data = fly5p1p_inst_defaults();
    if (is_file(FLY_5P1P_INST_FILE)) {
        $decoded = json_decode((string) file_get_contents(FLY_5P1P_INST_FILE), true);
        if (is_array($decoded)) {
            $data = array_merge($data, array_intersect_key($decoded, $data));
        }
    }
    foreach ($data as $k => $v) {
        $data[$k] = trim((string) $v);
    }
    return $data;
}

/**
 * Grava nome/texto/pix/favorecido e, se veio, a foto nova (já validada e convertida).
 * Retorna array('data' => ..., 'erro' => '' | mensagem).
 */
function fly5p1p_inst_save(array $fields, $upload = null)
{
    $data = fly5p1p_inst_load();
    $nome  = isset($fields['nome'])  ? trim((string) $fields['nome'])  : $data['nome'];
    $texto = isset($fields['texto']) ? trim((string) $fields['texto']) : $data['texto'];
    $pix   = isset($fields['pix'])   ? trim((string) $fields['pix'])   : $data['pix'];
    $pixNome = isset($fields['pix_nome']) ? trim((string) $fields['pix_nome']) : $data['pix_nome'];

    if ($nome === '') {
        return array('data' => $data, 'erro' => 'Informe o nome da instituição.');
    }
    $data['nome']  = mb_substr($nome, 0, 80);
    $data['texto'] = mb_substr(preg_replace("/\r\n?/", "\n", $texto), 0, 600);
    $data['pix']   = mb_substr($pix, 0, 120);
    $data['pix_nome'] = mb_substr($pixNome, 0, 80);

    if (is_array($upload) && isset($upload['error']) && $upload['error'] !== UPLOAD_ERR_NO_FILE) {
        $foto = fly5p1p_inst_store_photo($upload);
        if (!$foto['ok']) {
            return array('data' => fly5p1p_inst_load(), 'erro' => $foto['erro']);
        }
        fly5p1p_inst_delete_photo($data['foto']);
        $data['foto'] = $foto['path'];
    }

    $data['atualizado_em'] = date('d/m/Y H:i');
    $dir = dirname(FLY_5P1P_INST_FILE);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (file_put_contents(FLY_5P1P_INST_FILE, $json . "\n", LOCK_EX) === false) {
        return array('data' => $data, 'erro' => 'Não foi possível salvar. Avise o suporte do site.');
    }
    return array('data' => $data, 'erro' => '');
}

/**
 * Valida a imagem enviada (JPG, PNG ou WEBP, até 15 MB) e salva em
 * uploads/5p1p/ com nome gerado aqui. Com a extensão GD, reduz para no
 * máximo 1600 px de largura e regrava em JPG (mais leve e sem metadados).
 */
function fly5p1p_inst_store_photo(array $up)
{
    if ($up['error'] === UPLOAD_ERR_INI_SIZE || $up['error'] === UPLOAD_ERR_FORM_SIZE) {
        return array('ok' => false, 'erro' => 'A foto é grande demais. Envie uma imagem de até 15 MB.');
    }
    if ($up['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($up['tmp_name'])) {
        return array('ok' => false, 'erro' => 'A foto não chegou. Tente enviar de novo.');
    }
    if ($up['size'] > 15 * 1024 * 1024) {
        return array('ok' => false, 'erro' => 'A foto é grande demais. Envie uma imagem de até 15 MB.');
    }
    $info = @getimagesize($up['tmp_name']);
    $tipos = array(IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp');
    if (!$info || !isset($tipos[$info[2]])) {
        return array('ok' => false, 'erro' => 'Formato não aceito. Envie uma foto em JPG, PNG ou WEBP.');
    }
    if (!is_dir(FLY_5P1P_UPLOAD_DIR) && !@mkdir(FLY_5P1P_UPLOAD_DIR, 0775, true)) {
        return array('ok' => false, 'erro' => 'Não foi possível salvar a foto. Avise o suporte do site.');
    }

    $base = 'instituicao-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3));
    $img = fly5p1p_inst_gd_open($up['tmp_name'], $info[2]);
    if ($img) {
        $w = imagesx($img);
        $h = imagesy($img);
        $nw = min($w, 1600);
        $nh = (int) round($h * $nw / $w);
        $out = imagecreatetruecolor($nw, $nh);
        imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255)); // PNG transparente vira fundo branco
        imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        $file = $base . '.jpg';
        $saved = imagejpeg($out, FLY_5P1P_UPLOAD_DIR . '/' . $file, 85);
        imagedestroy($img);
        imagedestroy($out);
    } else {
        $file = $base . '.' . $tipos[$info[2]];
        $saved = move_uploaded_file($up['tmp_name'], FLY_5P1P_UPLOAD_DIR . '/' . $file);
    }
    if (!$saved) {
        return array('ok' => false, 'erro' => 'Não foi possível salvar a foto. Avise o suporte do site.');
    }
    @chmod(FLY_5P1P_UPLOAD_DIR . '/' . $file, 0644);
    return array('ok' => true, 'path' => 'uploads/5p1p/' . $file);
}

function fly5p1p_inst_gd_open($tmp, $type)
{
    if (!function_exists('imagecreatetruecolor')) {
        return null;
    }
    if ($type === IMAGETYPE_JPEG && function_exists('imagecreatefromjpeg')) {
        $img = @imagecreatefromjpeg($tmp);
        // foto de celular "deitada": corrige pela orientação gravada pela câmera
        if ($img && function_exists('exif_read_data')) {
            $exif = @exif_read_data($tmp);
            $rot = array(3 => 180, 6 => -90, 8 => 90);
            if (!empty($exif['Orientation']) && isset($rot[$exif['Orientation']])) {
                $img = imagerotate($img, $rot[$exif['Orientation']], 0);
            }
        }
        return $img ?: null;
    }
    if ($type === IMAGETYPE_PNG && function_exists('imagecreatefrompng')) {
        return @imagecreatefrompng($tmp) ?: null;
    }
    if ($type === IMAGETYPE_WEBP && function_exists('imagecreatefromwebp')) {
        return @imagecreatefromwebp($tmp) ?: null;
    }
    return null;
}

/** Apaga a foto anterior, só se for uma enviada pelo painel (nunca as do site). */
function fly5p1p_inst_delete_photo($path)
{
    if (preg_match('#^uploads/5p1p/instituicao-[\w-]+\.(jpg|png|webp)$#', (string) $path)) {
        @unlink(__DIR__ . '/' . $path);
    }
}
