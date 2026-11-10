<?php
/**
 * Flyconecta — Painel do 5P1P
 * ------------------------------------------------------------------
 * Instituição do mês (nome, texto, foto, chave PIX) + contador de refeições.
 * Senha padrao abaixo. Para trocar sem expor no Git, crie o arquivo
 * flyconecta/config.local.php com:  <?php  $FLY_5P1P_SENHA = 'suaSenha';
 * ------------------------------------------------------------------
 */

require __DIR__ . '/lib5p1p.php';

$FLY_5P1P_SENHA = 'fly5p1p';                 // <-- senha padrao (trocar!)
$local = __DIR__ . '/config.local.php';
if (is_file($local)) {
    require $local;                          // pode redefinir $FLY_5P1P_SENHA
}

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');

session_name('fly5p1p');
session_set_cookie_params(array(
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
));
session_start();

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$logado = !empty($_SESSION['fly5p1p_ok']);
$erro = '';

function h($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }

function volta($msg, $tipo = 'ok')
{
    $_SESSION['flash'] = array('msg' => $msg, 'tipo' => $tipo);
    header('Location: admin.php');
    exit;
}

if (isset($_GET['sair'])) {
    $_SESSION = array();
    session_destroy();
    header('Location: admin.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = isset($_POST['acao']) ? (string) $_POST['acao'] : '';

    // upload maior que o limite do servidor chega com $_POST vazio
    if ($acao === '' && $logado && !empty($_SERVER['CONTENT_LENGTH'])) {
        volta('A foto é grande demais para o servidor. Envie uma imagem menor.', 'err');
    }

    if ($acao === 'entrar') {
        $senha = isset($_POST['senha']) ? (string) $_POST['senha'] : '';
        if (hash_equals($FLY_5P1P_SENHA, $senha)) {
            session_regenerate_id(true);
            $_SESSION['fly5p1p_ok'] = true;
            header('Location: admin.php');
            exit;
        }
        sleep(1);
        $erro = 'Senha incorreta.';
    } elseif ($logado) {
        $token = isset($_POST['csrf']) ? (string) $_POST['csrf'] : '';
        if (!hash_equals($_SESSION['csrf'], $token)) {
            volta('A página ficou aberta por muito tempo. Confira os dados e salve de novo.', 'err');
        }
        if ($acao === 'instituicao') {
            $res = fly5p1p_inst_save($_POST, isset($_FILES['foto']) ? $_FILES['foto'] : null);
            if ($res['erro']) {
                volta($res['erro'], 'err');
            }
            volta('Instituição do mês atualizada. Já está no site.');
        }
        if ($acao === 'contador') {
            fly5p1p_save(array(
                'planos_vendidos'  => isset($_POST['planos_vendidos'])  ? $_POST['planos_vendidos']  : null,
                'planos_por_dia'   => isset($_POST['planos_por_dia'])   ? $_POST['planos_por_dia']   : null,
                'planos_por_prato' => isset($_POST['planos_por_prato']) ? $_POST['planos_por_prato'] : null,
                'pratos_extra'     => isset($_POST['pratos_extra'])     ? $_POST['pratos_extra']     : null,
                'pratos_mes'       => isset($_POST['pratos_mes'])       ? $_POST['pratos_mes']       : null,
                'pratos_ano'       => isset($_POST['pratos_ano'])       ? $_POST['pratos_ano']       : null,
            ));
            volta('Contador atualizado e recontado a partir de agora.');
        }
    }
}

$flash = isset($_SESSION['flash']) ? $_SESSION['flash'] : null;
unset($_SESSION['flash']);

$data = fly5p1p_load();
$proj = fly5p1p_project($data);
$inst = fly5p1p_inst_load();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>5P1P — Painel · Flyconecta</title>
<link rel="icon" href="assets/favicon.png">
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Poppins',system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:#f3f6fb;color:#0f1626;
    min-height:100vh;padding:28px 16px 48px}
  .wrap{max-width:560px;margin:0 auto}
  .top{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:18px}
  .top img{height:28px}
  .top a{font-size:.85rem;color:#00246A}
  .card{background:#fff;border:1px solid #e5e9f2;border-radius:20px;box-shadow:0 18px 44px -18px rgba(15,22,38,.2);
    padding:24px;margin-bottom:20px}
  h1{font-size:1.15rem;margin-bottom:4px}
  .sub{color:#5b6577;font-size:.85rem;margin-bottom:18px}
  label{display:block;font-size:.82rem;font-weight:600;margin:14px 0 6px}
  input,textarea{width:100%;padding:11px 13px;border:1px solid #e5e9f2;border-radius:10px;font:inherit;font-size:16px;background:#fff}
  textarea{min-height:120px;resize:vertical;line-height:1.5}
  input:focus,textarea:focus{outline:2px solid #00246A;outline-offset:1px}
  input[type=file]{padding:9px;background:#f3f6fb}
  .hint{font-size:.75rem;color:#5b6577;margin-top:4px}
  button{margin-top:22px;width:100%;padding:13px;border:0;border-radius:999px;background:#00246A;color:#fff;
    font:inherit;font-weight:600;cursor:pointer}
  button:hover{background:#001A4F}
  .msg{padding:11px 13px;border-radius:10px;font-size:.85rem;margin-bottom:16px}
  .msg--ok{background:#e7f6ec;color:#12683a}
  .msg--err{background:#fdecec;color:#b0030a}
  .now{background:#f3f6fb;border-radius:12px;padding:14px;margin-bottom:6px;font-size:.85rem;line-height:1.7}
  .now b{color:#00246A}
  .row{display:flex;gap:12px}
  .row>div{flex:1;min-width:0}
  .foto{aspect-ratio:4/3;border-radius:12px;overflow:hidden;background:#e5e9f2}
  .foto img{display:block;width:100%;height:100%;object-fit:cover}
  .count{float:right;font-weight:400;color:#5b6577}
  a.back{display:inline-block;margin-top:16px;font-size:.8rem;color:#00246A}
  @media (max-width:420px){.row{flex-direction:column;gap:0}}
</style>
</head>
<body>
<div class="wrap">
<?php if (!$logado): ?>
  <form class="card" method="post" autocomplete="off" style="margin-top:8vh">
    <h1>Painel 5P1P</h1>
    <p class="sub">Entre com a senha do painel para atualizar a instituição do mês e o contador de refeições.</p>
    <?php if ($erro): ?><div class="msg msg--err"><?= h($erro) ?></div><?php endif; ?>
    <input type="hidden" name="acao" value="entrar">
    <label for="senha">Senha</label>
    <input type="password" id="senha" name="senha" required autofocus>
    <button type="submit">Entrar</button>
  </form>
<?php else: ?>
  <div class="top">
    <img src="assets/logo-flyconecta-dark.png" alt="Flyconecta">
    <span><a href="./#social" target="_blank" rel="noopener">Ver no site</a> · <a href="?sair=1">Sair</a></span>
  </div>

  <?php if ($flash): ?>
    <div class="msg msg--<?= $flash['tipo'] === 'err' ? 'err' : 'ok' ?>"><?= h($flash['msg']) ?></div>
  <?php endif; ?>

  <!-- Instituição do mês -->
  <form class="card" method="post" enctype="multipart/form-data" autocomplete="off">
    <h1>Instituição do mês</h1>
    <p class="sub">O que aparece no card do 5P1P no site. Ao salvar, o site muda na hora.<?php if ($inst['atualizado_em']): ?> Última atualização: <b><?= h($inst['atualizado_em']) ?></b>.<?php endif; ?></p>
    <input type="hidden" name="acao" value="instituicao">
    <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">

    <label>Foto atual</label>
    <div class="foto"><img id="fotoPreview" src="<?= h($inst['foto']) ?>" alt=""></div>

    <label for="foto">Trocar foto <span style="font-weight:400">(opcional)</span></label>
    <input type="file" id="foto" name="foto" accept="image/jpeg,image/png,image/webp">
    <div class="hint">JPG, PNG ou WEBP, até 15 MB. No site a foto aparece em formato 4:3 (deitado) e é cortada nas bordas se for de outro formato — prefira foto horizontal com o assunto no centro.</div>

    <label for="nome">Nome da instituição</label>
    <input type="text" id="nome" name="nome" maxlength="80" value="<?= h($inst['nome']) ?>" required>

    <label for="texto">Texto sobre a instituição <span class="count" id="textoCount"></span></label>
    <textarea id="texto" name="texto" maxlength="600" placeholder="Quem ela atende, onde fica e como as refeições doadas ajudam."><?= h($inst['texto']) ?></textarea>
    <div class="hint">Até 600 caracteres. Se ficar em branco, o texto não aparece no site.</div>

    <label for="pix">Chave PIX do botão "Colabore"</label>
    <input type="text" id="pix" name="pix" maxlength="120" value="<?= h($inst['pix']) ?>" placeholder="CNPJ, e-mail, telefone ou chave aleatória">
    <div class="hint">Se ficar em branco, o botão "Colabore via PIX" some do site.</div>

    <label for="pix_nome">Nome de quem recebe o PIX</label>
    <input type="text" id="pix_nome" name="pix_nome" maxlength="80" value="<?= h($inst['pix_nome']) ?>" placeholder="Como aparece no app do banco ao doar">
    <div class="hint">Aparece junto da chave, para quem doa conferir antes de confirmar o pagamento.</div>

    <button type="submit">Salvar instituição</button>
  </form>

  <!-- Contador -->
  <form class="card" method="post" autocomplete="off">
    <h1>Contador de refeições</h1>
    <p class="sub">A cada <?= h($proj['planos_por_prato']) ?> planos vendidos, 1 refeição doada. O número sobe sozinho no site ao longo do dia, na média informada abaixo.</p>
    <input type="hidden" name="acao" value="contador">
    <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">

    <div class="now">
      Neste instante, no site: <b><?= h($proj['planos_vendidos']) ?></b> planos vendidos ·
      <b><?= h($proj['pratos_doados']) ?></b> refeição(ões) doada(s) no total<br>
      <b><?= h($proj['pratos_mes']) ?></b> no mês · <b><?= h($proj['pratos_ano']) ?></b> no ano<br>
      Faltam <b><?= h($proj['faltam_proximo_prato']) ?></b> plano(s) para a próxima refeição<br>
      Última correção manual: <b><?= h($data['atualizado_em'] ?: '—') ?></b>
    </div>

    <label for="planos_vendidos">Planos vendidos (número real, agora)</label>
    <input type="number" id="planos_vendidos" name="planos_vendidos" min="0" step="1" value="<?= h($proj['planos_vendidos']) ?>" required>
    <div class="hint">Corrija aqui de tempos em tempos com o número real; o site continua contando sozinho a partir dele.</div>

    <label for="planos_por_dia">Média de planos vendidos por dia</label>
    <input type="number" id="planos_por_dia" name="planos_por_dia" min="0" step="0.1" value="<?= h($data['planos_por_dia']) ?>" required>
    <div class="hint">Define a velocidade da contagem automática no site (hoje: ~<?= h($proj['planos_por_dia']) ?> planos/dia).</div>

    <div class="row">
      <div>
        <label for="planos_por_prato">Planos por refeição</label>
        <input type="number" id="planos_por_prato" name="planos_por_prato" min="1" step="1" value="<?= h($data['planos_por_prato']) ?>" required>
      </div>
      <div>
        <label for="pratos_extra">Refeições extras (fora da regra)</label>
        <input type="number" id="pratos_extra" name="pratos_extra" min="0" step="1" value="<?= h($data['pratos_extra']) ?>" required>
      </div>
    </div>

    <div class="row">
      <div>
        <label for="pratos_mes">Refeições doadas no mês (real, agora)</label>
        <input type="number" id="pratos_mes" name="pratos_mes" min="0" step="1" value="<?= h($proj['pratos_mes']) ?>" required>
        <div class="hint">Reinicia sozinho a 0 na virada do mês.</div>
      </div>
      <div>
        <label for="pratos_ano">Refeições doadas no ano (real, agora)</label>
        <input type="number" id="pratos_ano" name="pratos_ano" min="0" step="1" value="<?= h($proj['pratos_ano']) ?>" required>
        <div class="hint">Reinicia sozinho a 0 na virada do ano.</div>
      </div>
    </div>

    <button type="submit">Salvar contador</button>
  </form>

  <a class="back" href="./#social" target="_blank" rel="noopener">Ver o 5P1P no site &rarr;</a>

  <script>
    // prévia da foto escolhida e contador de caracteres do texto
    (function () {
      var input = document.getElementById('foto');
      var prev = document.getElementById('fotoPreview');
      input.addEventListener('change', function () {
        if (input.files && input.files[0]) prev.src = URL.createObjectURL(input.files[0]);
      });
      var t = document.getElementById('texto');
      var c = document.getElementById('textoCount');
      function upd() { c.textContent = t.value.length + '/600'; }
      t.addEventListener('input', upd);
      upd();
    })();
  </script>
<?php endif; ?>
</div>
</body>
</html>
