<?php
/**
 * TEMPORÁRIO — teste da libcliques.php rodado no servidor (não há PHP local).
 * Usa um arquivo de log separado; não toca em data/cliques.log.
 * Apagar depois de passar.
 */
header('Content-Type: text/plain; charset=utf-8');
define('FLYCL_FILE', sys_get_temp_dir() . '/flycl-teste-' . getmypid() . '.log');
require __DIR__ . '/libcliques.php';

$falhas = 0;
function ok($cond, $msg)
{
    global $falhas;
    if (!$cond) {
        $falhas++;
    }
    echo ($cond ? 'PASS' : 'FAIL') . '  ' . $msg . "\n";
}

// validação de ref
ok(flycl_ref_valido('site-plano-flypower'), 'ref válida aceita');
ok(!flycl_ref_valido('Site-Plano'), 'maiúscula rejeitada');
ok(!flycl_ref_valido('ab'), 'curta demais rejeitada');
ok(!flycl_ref_valido('-abc'), 'hífen no início rejeitado');
ok(!flycl_ref_valido(str_repeat('a', 41)), 'longa demais rejeitada');
ok(!flycl_ref_valido("a\tb"), 'tab rejeitado');

// registrar + ler
@unlink(FLYCL_FILE);
ok(flycl_registrar('site-plano-flypower', strtotime('2026-11-01 10:00:00')), 'grava 1');
ok(flycl_registrar('site-flutuante',      strtotime('2026-11-01 11:00:00')), 'grava 2');
ok(flycl_registrar('site-plano-flypower', strtotime('2026-11-05 09:30:00')), 'grava 3');
ok(flycl_registrar('site-boleto',         strtotime('2026-11-11 08:00:00')), 'grava 4');
ok(!flycl_registrar('INVALIDA!',          strtotime('2026-11-11 08:00:00')), 'inválida não grava');
ok(count(file(FLYCL_FILE)) === 4, 'arquivo tem 4 linhas');

$tudo = flycl_ler('2026-11-01', '2026-11-11');
ok(count($tudo) === 4, 'ler intervalo completo = 4');
ok($tudo[0]['data'] === '2026-11-01' && $tudo[0]['hora'] === '10:00:00' && $tudo[0]['ref'] === 'site-plano-flypower', 'campos da linha');
ok(count(flycl_ler('2026-11-02', '2026-11-10')) === 1, 'ler intervalo parcial = 1');
ok(count(flycl_ler('2026-11-11', '2026-11-11')) === 1, 'ler um dia (inclusivo) = 1');
ok(count(flycl_ler('2026-12-01', '2026-12-31')) === 0, 'ler sem cliques = 0');

// resumo
$r = flycl_resumo($tudo);
ok($r['total'] === 4, 'resumo total 4');
ok(array_keys($r['por_ref'])[0] === 'site-plano-flypower' && $r['por_ref']['site-plano-flypower'] === 2, 'por_ref ordenado, flypower=2 primeiro');
ok(array_keys($r['por_dia']) === array('2026-11-01', '2026-11-05', '2026-11-11'), 'por_dia cronológico');
ok($r['por_dia']['2026-11-01'] === 2, 'por_dia 01/11 = 2');
$vazio = flycl_resumo(array());
ok($vazio['total'] === 0 && $vazio['por_ref'] === array(), 'resumo vazio');

// períodos (hoje fixo = 2026-11-11)
ok(flycl_periodo('hoje', '', '', '2026-11-11') === array('2026-11-11', '2026-11-11'), 'período hoje');
ok(flycl_periodo('7d',   '', '', '2026-11-11') === array('2026-11-05', '2026-11-11'), 'período 7d');
ok(flycl_periodo('30d',  '', '', '2026-11-11') === array('2026-10-13', '2026-11-11'), 'período 30d');
ok(flycl_periodo('mes',  '', '', '2026-11-11') === array('2026-11-01', '2026-11-11'), 'período mês');
ok(flycl_periodo('custom', '2026-10-01', '2026-10-20', '2026-11-11') === array('2026-10-01', '2026-10-20'), 'período custom');
ok(flycl_periodo('custom', '2026-10-20', '2026-10-01', '2026-11-11') === array('2026-10-01', '2026-10-20'), 'custom invertido corrige');
ok(flycl_periodo('custom', 'x', '2026-10-01', '2026-11-11') === array('2026-10-13', '2026-11-11'), 'custom inválido cai em 30d');
ok(flycl_periodo('qualquer', '', '', '2026-11-11') === array('2026-10-13', '2026-11-11'), 'desconhecido cai em 30d');
ok(flycl_periodo('mes', '', '', '2026-03-01') === array('2026-03-01', '2026-03-01'), 'mês no dia 1');

// rótulos
ok(flycl_rotulo('site-flutuante') === 'Botão flutuante', 'rótulo conhecido');
ok(flycl_rotulo('site-xyz') === 'site-xyz', 'rótulo desconhecido devolve a ref');

@unlink(FLYCL_FILE);
echo "\n" . ($falhas === 0 ? 'TODOS OS TESTES PASSARAM' : $falhas . ' FALHA(S)') . "\n";
