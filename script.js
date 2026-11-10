(function () {
  'use strict';

  /* ===== 5P1P — chave PIX do botão "Colabore" =====
     Vem da API (editada no painel admin.php). Sem chave, o botão fica escondido. */
  var PIX_CHAVE = '381.961.718-38';
  var PIX_NOME = 'Jamily da Silva Almeida';

  var header = document.querySelector('.header');
  var burger = document.getElementById('burger');
  var nav = document.getElementById('nav');
  var brand = document.querySelector('.brand');

  /* Header shadow on scroll */
  var onScroll = function () {
    header.classList.toggle('is-scrolled', window.scrollY > 8);
  };
  onScroll();
  window.addEventListener('scroll', onScroll, { passive: true });

  /* Logo — sempre volta ao topo (link para elemento sticky pode falhar em alguns navegadores) */
  if (brand && brand.getAttribute('href').charAt(0) === '#') {
    brand.addEventListener('click', function (e) {
      e.preventDefault();
      window.scrollTo({ top: 0, behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
    });
  }

  /* Mobile nav */
  if (burger && nav) {
    burger.addEventListener('click', function () {
      var open = nav.classList.toggle('is-open');
      burger.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    nav.addEventListener('click', function (e) {
      if (e.target.tagName === 'A') {
        nav.classList.remove('is-open');
        burger.setAttribute('aria-expanded', 'false');
      }
    });
  }

  /* FAQ — one open at a time */
  var faq = document.getElementById('faqList');
  if (faq) {
    faq.addEventListener('toggle', function (e) {
      var t = e.target;
      if (t.tagName === 'DETAILS' && t.open) {
        faq.querySelectorAll('details[open]').forEach(function (d) {
          if (d !== t) d.open = false;
        });
      }
    }, true);
  }

  /* Active nav link on scroll */
  var links = Array.prototype.slice.call(nav ? nav.querySelectorAll('a[href^="#"]') : []);
  var sections = links
    .map(function (a) { return document.querySelector(a.getAttribute('href')); })
    .filter(Boolean);

  if ('IntersectionObserver' in window && sections.length) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          var id = entry.target.id;
          links.forEach(function (a) {
            a.classList.toggle('is-active', a.getAttribute('href') === '#' + id);
          });
        }
      });
    }, { rootMargin: '-45% 0px -50% 0px' });
    sections.forEach(function (s) { io.observe(s); });
  }

  /* Year */
  var ano = document.getElementById('ano');
  if (ano) ano.textContent = new Date().getFullYear();

  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ===== Hero — planos rotativos ===== */
  (function () {
    var root = document.getElementById('planrot');
    if (!root) return;
    var track = document.getElementById('planrotTrack');
    var dotsWrap = document.getElementById('planrotDots');
    var WA = 'https://wa.me/5515997388161?text=Quero%20o%20plano%20';
    var DELAY = 4500;

    var PLANOS = [
      { nome: 'FlyLight', num: '300', un: 'MEGA', preco: ['89', '90'] },
      { nome: 'FlySpeed', num: '600', un: 'MEGA', preco: ['99', '90'] },
      { nome: 'FlyPower', num: '1', un: 'GIGA', preco: ['119', '90'], tag: 'Mais popular' },
      { nome: 'FlySeleção', num: '1', un: 'GIGA', preco: ['149', '90'] }
    ];

    PLANOS.forEach(function (p, i) {
      var li = document.createElement('li');
      li.className = 'planrot__slide';
      li.innerHTML =
        '<span class="planrot__name">' + p.nome +
          (p.tag ? ' <b class="planrot__tag">' + p.tag + '</b>' : '') + '</span>' +
        '<span class="planrot__speed">' + p.num + '<b>' + p.un + '</b></span>' +
        '<span class="planrot__price">R$ <strong>' + p.preco[0] + '</strong><sup>,' + p.preco[1] + '</sup> <i>/mês</i></span>' +
        '<a class="btn btn--light" href="' + WA + encodeURIComponent(p.nome) + '" target="_blank" rel="noopener">Assinar plano</a>';
      track.appendChild(li);

      var b = document.createElement('button');
      b.type = 'button';
      b.setAttribute('role', 'tab');
      b.setAttribute('aria-label', 'Plano ' + p.nome);
      b.addEventListener('click', function () { go(i); restart(); });
      dotsWrap.appendChild(b);
    });

    var dots = Array.prototype.slice.call(dotsWrap.children);
    var index = 0;
    var timer = null;

    function render() {
      track.style.transform = 'translateX(' + (-index * 100) + '%)';
      dots.forEach(function (d, i) { d.setAttribute('aria-selected', i === index ? 'true' : 'false'); });
    }
    function go(i) { index = (i + PLANOS.length) % PLANOS.length; render(); }
    function start() { if (!reduce && !timer) timer = setInterval(function () { go(index + 1); }, DELAY); }
    function stop() { if (timer) { clearInterval(timer); timer = null; } }
    function restart() { stop(); start(); }

    root.addEventListener('mouseenter', stop);
    root.addEventListener('mouseleave', start);
    root.addEventListener('focusin', stop);
    root.addEventListener('focusout', start);
    document.addEventListener('visibilitychange', function () { if (document.hidden) stop(); else start(); });

    var x0 = null;
    root.addEventListener('touchstart', function (e) { x0 = e.touches[0].clientX; stop(); }, { passive: true });
    root.addEventListener('touchend', function (e) {
      if (x0 === null) return;
      var dx = e.changedTouches[0].clientX - x0;
      if (Math.abs(dx) > 40) go(index + (dx < 0 ? 1 : -1));
      x0 = null;
      start();
    });

    render();
    start();
  })();

  /* ===== 5P1P — contador de refeições + foguete até a próxima refeição ===== */
  (function () {
    var nodes = document.querySelectorAll('[data-5p1p]');
    if (!nodes.length) return;

    var POLL_MS = 600000; // reconsulta o servidor a cada 10min para ir subindo
    var revealed = false;

    function animateCount(el, target) {
      target = Number(target) || 0;
      var current = parseInt((el.textContent || '0').replace(/\D/g, ''), 10) || 0;
      if (reduce || current === target) { el.textContent = target.toLocaleString('pt-BR'); return; }
      var start = current, dur = 900, t0 = null;
      function step(ts) {
        if (!t0) t0 = ts;
        var p = Math.min((ts - t0) / dur, 1);
        var eased = 1 - Math.pow(1 - p, 3);
        el.textContent = Math.round(start + (target - start) * eased).toLocaleString('pt-BR');
        if (p < 1) requestAnimationFrame(step);
      }
      requestAnimationFrame(step);
    }

    function countWhenVisible(el, value) {
      if (!('IntersectionObserver' in window)) { revealed = true; animateCount(el, value); return; }
      var obs = new IntersectionObserver(function (entries, o) {
        entries.forEach(function (e) {
          if (e.isIntersecting) { revealed = true; animateCount(el, value); o.disconnect(); }
        });
      }, { threshold: 0.4 });
      obs.observe(el);
    }

    function apply(data) {
      var perMeal = Math.max(1, parseInt(data.planos_por_prato, 10) || 5);
      var sold = Math.max(0, parseInt(data.planos_vendidos, 10) || 0);
      var rest = sold % perMeal;
      var missing = rest === 0 ? perMeal : perMeal - rest;
      var progress = Math.round((rest / perMeal) * 100);
      var counters = {
        pratos_mes: Math.max(0, parseInt(data.pratos_mes, 10) || 0),
        pratos_ano: Math.max(0, parseInt(data.pratos_ano, 10) || 0)
      };

      nodes.forEach(function (el) {
        var key = el.getAttribute('data-5p1p');
        if (key in counters) {
          if (revealed) animateCount(el, counters[key]);
          else countWhenVisible(el, counters[key]);
        } else if (key === 'faltam') {
          el.textContent = missing;
        } else if (key === 'progress' || key === 'rocket') {
          el.style.setProperty('--p', progress + '%');
        }
      });
    }

    // instituição do mês: nome, texto, foto e chave PIX vêm do painel
    function applyInst(inst) {
      if (!inst) return;
      var name = document.querySelector('.inst__name');
      var text = document.querySelector('.inst__text');
      var img = document.querySelector('.inst__photo img');
      if (name && inst.nome) name.textContent = inst.nome;
      if (text) {
        text.textContent = inst.texto || '';
        text.hidden = !inst.texto;
      }
      if (img && inst.foto && img.getAttribute('src') !== inst.foto) {
        img.src = inst.foto;
        img.alt = 'Foto da instituição ' + (inst.nome || 'ajudada no mês');
      }
      PIX_CHAVE = inst.pix || '';
      PIX_NOME = inst.pix_nome || '';
      document.querySelectorAll('[data-pix-open]').forEach(function (b) { b.hidden = !PIX_CHAVE; });
    }

    function fetchAndApply() {
      fetch('api/5p1p.php', { headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
        .then(function (data) { applyInst(data.instituicao); apply(data); })
        .catch(function () { /* sem API, mantém os valores já exibidos */ });
    }

    fetchAndApply();

    var timer = null;
    function startPolling() { if (!timer) timer = setInterval(fetchAndApply, POLL_MS); }
    function stopPolling() { if (timer) { clearInterval(timer); timer = null; } }
    startPolling();
    document.addEventListener('visibilitychange', function () {
      if (document.hidden) stopPolling();
      else { fetchAndApply(); startPolling(); }
    });
  })();

  /* ===== 5P1P — botão "Colabore" abre a chave PIX ===== */
  (function () {
    var btns = document.querySelectorAll('[data-pix-open]');
    if (!btns.length) return;

    var dlg = document.createElement('dialog');
    dlg.className = 'helpdlg pixdlg';
    dlg.setAttribute('aria-labelledby', 'pixdlgTitle');
    dlg.innerHTML =
      '<button type="button" class="helpdlg__close" aria-label="Fechar">&times;</button>' +
      '<span class="eyebrow">5P1P · Colabore</span>' +
      '<h2 id="pixdlgTitle">Faça parte desse projeto</h2>' +
      '<p class="pixdlg__text">Doe qualquer valor via PIX usando a chave abaixo.</p>' +
      '<code class="pixdlg__key"></code>' +
      '<p class="pixdlg__name"></p>' +
      '<button type="button" class="btn btn--primary btn--block pixdlg__copy">Copiar chave PIX</button>';
    var keyEl = dlg.querySelector('.pixdlg__key');
    var nameEl = dlg.querySelector('.pixdlg__name');
    document.body.appendChild(dlg);

    var copyBtn = dlg.querySelector('.pixdlg__copy');
    copyBtn.addEventListener('click', function () {
      var done = function () { copyBtn.textContent = 'Chave copiada!'; };
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(PIX_CHAVE).then(done, function () {});
      }
    });

    function close() { if (dlg.open) dlg.close(); }
    dlg.querySelector('.helpdlg__close').addEventListener('click', close);
    dlg.addEventListener('click', function (e) { if (e.target === dlg) close(); });

    Array.prototype.forEach.call(btns, function (b) {
      b.addEventListener('click', function () {
        keyEl.textContent = PIX_CHAVE;
        nameEl.textContent = PIX_NOME ? 'Favorecido(a): ' + PIX_NOME : '';
        nameEl.hidden = !PIX_NOME;
        copyBtn.textContent = 'Copiar chave PIX';
        if (typeof dlg.showModal === 'function') dlg.showModal();
        else window.prompt('Chave PIX:', PIX_CHAVE);
      });
    });
  })();

  /* ===== TV — grade de canais interativa ===== */
  (function () {
    var grid = document.getElementById('channelGrid');
    if (!grid) return;
    var filters = document.getElementById('channelFilters');
    var search = document.getElementById('channelSearch');
    var empty = document.getElementById('channelEmpty');
    var totalEl = document.querySelector('[data-canais="total"]');

    var COLORS = {
      'TV Aberta': '#00246A', 'Filmes e Séries': '#5646CE', 'Esportes': '#1E8E3E',
      'Notícias': '#DF040B', 'Infantil': '#E8590C', 'Variedades': '#0B7285',
      'Watch Free': '#7B2CBF', 'Musical': '#C2185B', 'Religioso': '#6A5ACD'
    };

    // Cada canal lista os planos que o incluem (campo "planos"); plano sem
    // nenhum canal cadastrado mostra aviso de grade em breve.
    var PLANOS = ['FlyLight', 'FlySpeed', 'FlyPower', 'FlySeleção'];

    // Streamings inclusos em cada plano (aparecem na aba acima da grade).
    var STREAMINGS = {
      'FlyLight': ['Fly Play'],
      'FlySpeed': ['Fly Play', 'Watch'],
      'FlyPower': ['Fly Play', 'Watch'],
      'FlySeleção': ['Fly Play', 'Premiere']
    };
    var streams = document.getElementById('channelStreams');

    var all = [];
    var active = PLANOS[0];

    function channelInPlan(c, plano) {
      return (c.planos || []).indexOf(plano) !== -1;
    }

    function initials(name) {
      var parts = name.replace(/[^A-Za-zÀ-ÿ0-9 ]/g, '').trim().split(/\s+/);
      if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase();
      return name.replace(/[^A-Za-zÀ-ÿ0-9]/g, '').slice(0, 2).toUpperCase();
    }

    function norm(s) {
      return s.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
    }

    function render() {
      var q = norm(search.value.trim());
      var list = all.filter(function (c) {
        if (!channelInPlan(c, active)) return false;
        if (q && norm(c.nome).indexOf(q) === -1) return false;
        return true;
      });
      renderStreams();
      grid.innerHTML = '';
      list.forEach(function (c) {
        var card = document.createElement('div');
        card.className = 'chcard';
        var color = COLORS[c.categoria] || '#00246A';
        card.innerHTML =
          '<span class="chcard__logo" style="background:' + color + '">' + initials(c.nome) + '</span>' +
          '<span class="chcard__body">' +
            '<span class="chcard__name">' + c.nome + '</span>' +
            '<span class="chcard__meta">' +
              (c.vod ? '<span class="chcard__hd" title="Ao vivo e sob demanda">VOD</span>' : '') +
              '<span class="chcard__cat">' + c.categoria + '</span>' +
            '</span>' +
          '</span>';
        grid.appendChild(card);
      });
      empty.hidden = list.length !== 0;
      if (!list.length) {
        var semGrade = !all.some(function (c) { return channelInPlan(c, active); });
        empty.textContent = semGrade
          ? 'A grade do ' + active + ' será publicada em breve.'
          : 'Nenhum canal encontrado com esse filtro.';
      }
    }

    function renderStreams() {
      if (!streams) return;
      var total = all.filter(function (c) { return channelInPlan(c, active); }).length;
      streams.innerHTML =
        '<span class="channels__streams-label">Streamings inclusos no ' + active + '</span>' +
        (STREAMINGS[active] || []).map(function (n) {
          return '<span class="stream">' + n + '</span>';
        }).join('') +
        '<span class="channels__streams-count">' + total + ' canais</span>';
    }

    function selectPlan(plano) {
      if (PLANOS.indexOf(plano) === -1) return;
      active = plano;
      filters.querySelectorAll('.chip').forEach(function (x) {
        x.setAttribute('aria-selected', x.textContent === plano ? 'true' : 'false');
      });
      render();
    }

    function buildFilters() {
      PLANOS.forEach(function (plano) {
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'chip';
        b.textContent = plano;
        b.setAttribute('role', 'tab');
        b.setAttribute('aria-selected', plano === active ? 'true' : 'false');
        b.addEventListener('click', function () { selectPlan(plano); });
        filters.appendChild(b);
      });
    }

    if (search) {
      var t;
      search.addEventListener('input', function () {
        clearTimeout(t);
        t = setTimeout(render, 120);
      });
    }

    function buildWall() {
      var wall = document.getElementById('chpromoWall');
      if (!wall || !all.length) return;
      wall.innerHTML = '';
      var ROWS = 6;
      for (var r = 0; r < ROWS; r++) {
        var row = document.createElement('div');
        row.className = 'channels__wall-row';
        var seq = all.slice(r * 3).concat(all.slice(0, r * 3));
        seq.concat(seq).forEach(function (c) {
          var i = document.createElement('i');
          i.textContent = initials(c.nome);
          row.appendChild(i);
        });
        wall.appendChild(row);
      }
    }

    var toggle = document.getElementById('channelToggle');
    var panel = document.getElementById('channelPanel');
    var tvSection = document.getElementById('tv');
    function setOpen(open, focus) {
      if (open) { panel.removeAttribute('hidden'); }
      else { panel.setAttribute('hidden', ''); }
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      toggle.firstChild.textContent = open ? 'Ocultar grade de canais ' : 'Ver grade de canais ';
      toggle.classList.toggle('is-open', open);
      if (tvSection) tvSection.classList.toggle('grid-open', open);
      if (open && focus && search) search.focus();
    }
    if (toggle && panel) {
      toggle.addEventListener('click', function () {
        setOpen(panel.hasAttribute('hidden'), true);
      });
      // "+ de N canais" nos cards de plano: abre a grade já no plano clicado
      document.querySelectorAll('a[data-plano]').forEach(function (a) {
        a.addEventListener('click', function () {
          setOpen(true, false);
          selectPlan(a.getAttribute('data-plano'));
        });
      });
    }

    fetch('data/canais.json', { headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
      .then(function (data) {
        all = Array.isArray(data.canais) ? data.canais : [];
        if (totalEl) totalEl.textContent = all.length;
        buildFilters();
        buildWall();
        render();
      })
      .catch(function () {
        grid.innerHTML = '';
        empty.hidden = false;
        empty.textContent = 'Não foi possível carregar a grade de canais.';
      });
  })();
  /* ===== Central de ajuda — escolha entre WhatsApp e 0800 ===== */
  (function () {
    var links = document.querySelectorAll('a[href="#ajuda"]');
    if (!links.length) return;

    var dlg = document.createElement('dialog');
    dlg.className = 'helpdlg';
    dlg.setAttribute('aria-labelledby', 'helpdlgTitle');
    dlg.innerHTML =
      '<button type="button" class="helpdlg__close" aria-label="Fechar">&times;</button>' +
      '<span class="eyebrow">Central de ajuda</span>' +
      '<h2 id="helpdlgTitle">Como prefere falar com a gente?</h2>' +
      '<div class="helpdlg__opts">' +
        '<a class="helpdlg__opt helpdlg__opt--wa" href="https://wa.me/5515997388161?text=Ol%C3%A1%2C%20preciso%20de%20ajuda" target="_blank" rel="noopener">' +
          '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15l-1.3 4.7 4.8-1.3A10 10 0 1 0 12 2z"/></svg>' +
          '<span><b>WhatsApp</b><small>(15) 99738-8161 · atendimento pelo chat</small></span>' +
        '</a>' +
        '<a class="helpdlg__opt helpdlg__opt--tel" href="tel:08002663282">' +
          '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6.6 10.8a15.1 15.1 0 0 0 6.6 6.6l2.2-2.2a1 1 0 0 1 1-.25 11.4 11.4 0 0 0 3.6.57 1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17 17 0 0 1 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1c0 1.25.2 2.45.57 3.57a1 1 0 0 1-.25 1z"/></svg>' +
          '<span><b>Ligar 0800 266 3282</b><small>Ligação gratuita</small></span>' +
        '</a>' +
      '</div>' +
      '<p class="helpdlg__hours">Seg. a sex. 8h–20h · Sáb. e dom. 9h–18h</p>';
    document.body.appendChild(dlg);

    function close() { if (dlg.open) dlg.close(); }
    dlg.querySelector('.helpdlg__close').addEventListener('click', close);
    dlg.addEventListener('click', function (e) {
      if (e.target === dlg) close();               // clique fora da caixa
      if (e.target.closest('.helpdlg__opt')) close();
    });

    Array.prototype.forEach.call(links, function (a) {
      a.addEventListener('click', function (e) {
        e.preventDefault();
        if (typeof dlg.showModal === 'function') dlg.showModal();
        else window.open('https://wa.me/5515997388161', '_blank', 'noopener');
      });
    });
  })();
})();
