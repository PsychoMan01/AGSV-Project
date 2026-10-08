/* AGSV — comportamento partilhado por todas as páginas.
   Cada bloco só atua se os elementos de que precisa existirem na página. */
(function () {
    'use strict';

    const root = document.documentElement;
    const body = document.body;
    const reduzirMovimento = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* ---------- Tema claro/escuro ---------- */
    // O tema inicial é aplicado no <head> (antes de pintar) para evitar o "flash".
    document.querySelectorAll('[data-theme-toggle]').forEach(botao => {
        botao.setAttribute('aria-pressed', String(root.dataset.theme === 'dark'));
        botao.addEventListener('click', () => {
            const tema = root.dataset.theme === 'dark' ? 'light' : 'dark';
            root.dataset.theme = tema;
            botao.setAttribute('aria-pressed', String(tema === 'dark'));
            try { localStorage.setItem('agsv-theme', tema); } catch (e) { /* armazenamento indisponível */ }
        });
    });

    /* ---------- Menu lateral (mobile) ---------- */
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const backdrop = document.getElementById('sidebarBackdrop');

    function definirSidebar(aberta) {
        if (!sidebar) return;
        sidebar.classList.toggle('aberta', aberta);
        backdrop?.classList.toggle('visivel', aberta);
        body.classList.toggle('sidebar-aberta', aberta);
        sidebarToggle?.setAttribute('aria-expanded', String(aberta));
        sidebarToggle?.setAttribute('aria-label', aberta ? 'Fechar menu' : 'Abrir menu');
        if (aberta) sidebar.querySelector('a, summary')?.focus({ preventScroll: true });
    }

    sidebarToggle?.addEventListener('click', () => definirSidebar(!sidebar.classList.contains('aberta')));
    backdrop?.addEventListener('click', () => definirSidebar(false));
    window.matchMedia('(min-width: 961px)').addEventListener('change', e => { if (e.matches) definirSidebar(false); });

    /* ---------- Menu da conta ---------- */
    const userButton = document.getElementById('userMenuButton');
    const userMenu = document.getElementById('dropdown-menu');

    function definirMenuConta(aberto) {
        if (!userMenu) return;
        userMenu.classList.toggle('aberto', aberto);
        userButton?.setAttribute('aria-expanded', String(aberto));
    }

    userButton?.addEventListener('click', e => {
        e.stopPropagation();
        const abrir = !userMenu.classList.contains('aberto');
        definirMenuConta(abrir);
        if (abrir) userMenu.querySelector('a')?.focus();
    });
    document.addEventListener('click', e => {
        if (userMenu && !e.target.closest('.user-panel')) definirMenuConta(false);
    });

    /* ---------- Modais ---------- */
    let ultimoFoco = null;

    function elementosFocaveis(container) {
        return [...container.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled])')];
    }

    function abrirModal(id) {
        const modal = typeof id === 'string' ? document.getElementById(id) : id;
        if (!modal) return;
        ultimoFoco = document.activeElement;
        modal.classList.add('aberto');
        modal.setAttribute('aria-hidden', 'false');
        body.classList.add('modal-aberto');
        const alvo = modal.querySelector('[autofocus]') || modal.querySelector('input:not([type="hidden"]), select') || elementosFocaveis(modal)[0];
        setTimeout(() => alvo?.focus(), 60);
    }

    function fecharModal(id) {
        const modal = typeof id === 'string' ? document.getElementById(id) : id;
        if (!modal || !modal.classList.contains('aberto')) return;
        modal.classList.remove('aberto');
        modal.setAttribute('aria-hidden', 'true');
        if (!document.querySelector('.modal-overlay.aberto')) body.classList.remove('modal-aberto');
        ultimoFoco?.focus?.();
    }

    // Mantidas no window para compatibilidade com código antigo que as chame diretamente.
    window.abrirModal = abrirModal;
    window.fecharModal = fecharModal;

    document.addEventListener('click', e => {
        const abrir = e.target.closest('[data-modal-open]');
        if (abrir) { abrirModal(abrir.dataset.modalOpen); return; }

        const fechar = e.target.closest('[data-modal-close]');
        if (fechar) { fecharModal(fechar.closest('.modal-overlay')); return; }

        if (e.target.classList?.contains('modal-overlay')) fecharModal(e.target);
    });

    document.addEventListener('keydown', e => {
        const modal = document.querySelector('.modal-overlay.aberto');

        if (e.key === 'Escape') {
            if (modal) { fecharModal(modal); return; }
            if (userMenu?.classList.contains('aberto')) { definirMenuConta(false); userButton?.focus(); return; }
            if (sidebar?.classList.contains('aberta')) { definirSidebar(false); sidebarToggle?.focus(); }
            return;
        }

        // Mantém o foco do teclado dentro do modal aberto
        if (e.key === 'Tab' && modal) {
            const focaveis = elementosFocaveis(modal);
            if (focaveis.length === 0) return;
            const primeiro = focaveis[0];
            const ultimo = focaveis[focaveis.length - 1];
            if (e.shiftKey && document.activeElement === primeiro) { e.preventDefault(); ultimo.focus(); }
            else if (!e.shiftKey && document.activeElement === ultimo) { e.preventDefault(); primeiro.focus(); }
        }
    });

    /* ---------- Confirmação de ações (substitui o confirm() nativo) ---------- */
    let confirmDialog = null;
    let formularioPendente = null;

    function criarConfirmDialog() {
        const el = document.createElement('div');
        el.className = 'modal-overlay';
        el.id = 'confirmDialog';
        el.setAttribute('role', 'alertdialog');
        el.setAttribute('aria-modal', 'true');
        el.setAttribute('aria-hidden', 'true');
        el.setAttribute('aria-labelledby', 'confirmTitle');
        el.setAttribute('aria-describedby', 'confirmMessage');
        el.innerHTML =
            '<div class="modal-box modal-box--small">' +
                '<div class="confirm-icon"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i></div>' +
                '<h2 id="confirmTitle">Confirmar ação</h2>' +
                '<p class="confirm-message" id="confirmMessage"></p>' +
                '<div class="modal-actions">' +
                    '<button type="button" class="btn" data-modal-close>Cancelar</button>' +
                    '<button type="button" class="btn btn-danger" data-confirm-ok>Confirmar</button>' +
                '</div>' +
            '</div>';
        body.appendChild(el);

        el.querySelector('[data-confirm-ok]').addEventListener('click', () => {
            const pendente = formularioPendente;
            formularioPendente = null;
            fecharModal(el);
            if (!pendente) return;
            pendente.form.dataset.confirmado = '1';
            if (pendente.form.requestSubmit) pendente.form.requestSubmit(pendente.submitter || undefined);
            else pendente.form.submit();
        });
        return el;
    }

    function pedirConfirmacao(form, submitter) {
        confirmDialog = confirmDialog || criarConfirmDialog();
        confirmDialog.querySelector('#confirmMessage').textContent = form.dataset.confirm;
        const rotulo = form.dataset.confirmLabel || submitter?.textContent.trim() || 'Confirmar';
        confirmDialog.querySelector('[data-confirm-ok]').textContent = rotulo;
        formularioPendente = { form, submitter };
        abrirModal(confirmDialog);
        setTimeout(() => confirmDialog.querySelector('[data-confirm-ok]').focus(), 60);
    }

    /* ---------- Submissão de formulários ---------- */
    document.addEventListener('submit', e => {
        const form = e.target;

        if (form.dataset.confirm && form.dataset.confirmado !== '1') {
            e.preventDefault();
            pedirConfirmacao(form, e.submitter);
            return;
        }
        delete form.dataset.confirmado;

        if (e.defaultPrevented || form.hasAttribute('data-no-lock')) return;

        const botao = e.submitter || form.querySelector('button[type="submit"]');
        if (!botao || botao.hasAttribute('data-no-lock')) return;

        // Desativar só depois de o browser registar o botão que submeteu
        setTimeout(() => {
            botao.classList.add('is-loading');
            botao.disabled = true;
            botao.setAttribute('aria-busy', 'true');
        }, 0);
        setTimeout(() => repor(botao), 10000);
    });

    function repor(botao) {
        botao.classList.remove('is-loading');
        botao.disabled = false;
        botao.removeAttribute('aria-busy');
    }

    // Ao voltar atrás no browser a página pode vir da cache com botões ainda "a processar"
    window.addEventListener('pageshow', e => {
        if (e.persisted) document.querySelectorAll('.is-loading').forEach(repor);
    });

    /* ---------- Alertas ---------- */
    document.addEventListener('click', e => {
        const fechar = e.target.closest('[data-dismiss="alert"]');
        if (!fechar) return;
        const alerta = fechar.closest('.alert');
        alerta.classList.add('is-leaving');
        alerta.addEventListener('animationend', () => alerta.remove(), { once: true });
    });

    /* ---------- Mostrar/ocultar palavra-passe ---------- */
    document.querySelectorAll('[data-password-toggle]').forEach(botao => {
        const campo = document.getElementById(botao.getAttribute('aria-controls'));
        if (!campo) return;
        botao.addEventListener('click', () => {
            const mostrar = campo.type === 'password';
            campo.type = mostrar ? 'text' : 'password';
            const rotulo = mostrar ? 'Ocultar palavra-passe' : 'Mostrar palavra-passe';
            botao.setAttribute('aria-label', rotulo);
            botao.setAttribute('title', rotulo);
            botao.setAttribute('aria-pressed', String(mostrar));
            const icone = botao.querySelector('i');
            icone?.classList.toggle('fa-eye', !mostrar);
            icone?.classList.toggle('fa-eye-slash', mostrar);
        });
    });

    /* ---------- Contadores do painel ---------- */
    if (!reduzirMovimento) {
        document.querySelectorAll('[data-count]').forEach(el => {
            const alvo = Number(el.dataset.count);
            if (!Number.isFinite(alvo) || alvo === 0) return;
            const duracao = 700;
            const inicio = performance.now();
            const formato = new Intl.NumberFormat('pt-PT');
            function frame(agora) {
                const progresso = Math.min((agora - inicio) / duracao, 1);
                el.textContent = formato.format(Math.round(alvo * (1 - Math.pow(1 - progresso, 3))));
                if (progresso < 1) requestAnimationFrame(frame);
            }
            el.textContent = '0';
            requestAnimationFrame(frame);
        });
    }

    /* ---------- Galeria: visualizador de imagens ---------- */
    const itensGaleria = [...document.querySelectorAll('[data-gallery-item]')];
    if (itensGaleria.length) {
        const lightbox = document.createElement('div');
        lightbox.className = 'modal-overlay lightbox';
        lightbox.setAttribute('role', 'dialog');
        lightbox.setAttribute('aria-modal', 'true');
        lightbox.setAttribute('aria-hidden', 'true');
        lightbox.setAttribute('aria-label', 'Visualizador de imagens');
        lightbox.innerHTML =
            '<button type="button" class="lightbox-btn lightbox-close" data-modal-close aria-label="Fechar"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>' +
            '<button type="button" class="lightbox-btn lightbox-prev" aria-label="Imagem anterior"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></button>' +
            '<figure class="lightbox-box"><img alt=""><figcaption class="lightbox-caption"></figcaption></figure>' +
            '<button type="button" class="lightbox-btn lightbox-next" aria-label="Imagem seguinte"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></button>';
        body.appendChild(lightbox);

        const imagem = lightbox.querySelector('img');
        const legenda = lightbox.querySelector('figcaption');
        let indice = 0;

        const mostrar = i => {
            indice = (i + itensGaleria.length) % itensGaleria.length;
            const item = itensGaleria[indice];
            imagem.src = item.href;
            imagem.alt = item.dataset.legenda || '';
            legenda.textContent = `${item.dataset.legenda || ''} · ${indice + 1} de ${itensGaleria.length}`;
        };

        itensGaleria.forEach((item, i) => item.addEventListener('click', e => {
            e.preventDefault();
            mostrar(i);
            abrirModal(lightbox);
        }));
        lightbox.querySelector('.lightbox-prev').addEventListener('click', () => mostrar(indice - 1));
        lightbox.querySelector('.lightbox-next').addEventListener('click', () => mostrar(indice + 1));
        lightbox.querySelector('.lightbox-box').addEventListener('click', e => { if (e.target !== imagem) fecharModal(lightbox); });
        document.addEventListener('keydown', e => {
            if (!lightbox.classList.contains('aberto')) return;
            if (e.key === 'ArrowLeft') mostrar(indice - 1);
            if (e.key === 'ArrowRight') mostrar(indice + 1);
        });
    }

    /* ---------- Mostrar um bloco escondido (ex.: formulário "Novo utilizador") ---------- */
    document.querySelectorAll('[data-reveal]').forEach(botao => {
        botao.addEventListener('click', () => {
            const alvo = document.getElementById(botao.dataset.reveal);
            if (!alvo) return;
            alvo.hidden = false;
            alvo.scrollIntoView({ behavior: reduzirMovimento ? 'auto' : 'smooth', block: 'start' });
            setTimeout(() => alvo.querySelector('input:not([type="hidden"]), select')?.focus({ preventScroll: true }), reduzirMovimento ? 0 : 350);
        });
    });

    /* ---------- Atribuir juízes: números disponíveis consoante o papel ---------- */
    const selectPapel = document.querySelector('select[data-campos-por-papel]');
    if (selectPapel) {
        const camposPorPapel = JSON.parse(selectPapel.dataset.camposPorPapel);
        const selectNumero = document.getElementById(selectPapel.dataset.numeroTarget);
        const atualizarNumeros = () => {
            const campos = camposPorPapel[selectPapel.value] || [];
            selectNumero.replaceChildren(new Option(campos.length ? '-- Escolhe um número --' : '-- Escolhe primeiro um papel --', ''));
            campos.forEach((campo, i) => selectNumero.appendChild(new Option(campo, i + 1)));
            selectNumero.disabled = campos.length === 0;
        };
        selectPapel.addEventListener('change', atualizarNumeros);
        atualizarNumeros();
    }

    /* ---------- Inscrições: escalão segue o atleta; pré-visualização dos aparelhos ---------- */
    const selectAtleta = document.querySelector('select[data-categoria-target]');
    if (selectAtleta) {
        const selectCategoria = document.getElementById(selectAtleta.dataset.categoriaTarget);
        const textoAparelhos = document.getElementById(selectCategoria.dataset.aparelhosTarget);
        const formInscricao = selectAtleta.closest('form');

        const mostrarAparelhos = () => {
            const opcao = selectCategoria.options[selectCategoria.selectedIndex];
            const aparelhos = opcao ? opcao.dataset.aparelhos : '';
            textoAparelhos.textContent = aparelhos ? 'Aparelhos que vão ser inscritos: ' + aparelhos : '';
        };

        // Quando o atleta já tem escalão definido, este fica fixo (o servidor valida igualmente).
        const ajustarCategoria = () => {
            const opcao = selectAtleta.options[selectAtleta.selectedIndex];
            const categoria = opcao ? opcao.dataset.categoryId : '';
            if (categoria) {
                selectCategoria.value = categoria;
                selectCategoria.disabled = true;
            } else {
                selectCategoria.disabled = false;
            }
            mostrarAparelhos();
        };

        // Campos desativados não seguem no POST: enviar o valor por um campo escondido.
        formInscricao.addEventListener('submit', () => {
            formInscricao.querySelector('input[data-categoria-oculta]')?.remove();
            if (!selectCategoria.disabled) return;
            const oculto = document.createElement('input');
            oculto.type = 'hidden';
            oculto.name = selectCategoria.name;
            oculto.value = selectCategoria.value;
            oculto.dataset.categoriaOculta = '';
            formInscricao.appendChild(oculto);
        }, true);

        selectAtleta.addEventListener('change', ajustarCategoria);
        selectCategoria.addEventListener('change', mostrarAparelhos);
        mostrarAparelhos();
    }

    /* ---------- Escalões da competição: só os aparelhos do escalão escolhido ---------- */
    const selectEscalao = document.querySelector('select[data-grupos-escalao]');
    if (selectEscalao) {
        const grupos = document.querySelectorAll(selectEscalao.dataset.gruposEscalao);
        const mostrarGrupo = () => {
            grupos.forEach(grupo => {
                const ativo = grupo.dataset.categoryId === selectEscalao.value;
                grupo.hidden = !ativo;
                // Os grupos escondidos ficam desativados para não seguirem no formulário
                grupo.querySelectorAll('input[type="checkbox"]').forEach(cb => { cb.disabled = !ativo; });
            });
        };
        selectEscalao.addEventListener('change', mostrarGrupo);
        mostrarGrupo();
    }

    /* ---------- Utilizadores: o campo "função" não se aplica a juízes ---------- */
    const selectTipo = document.querySelector('select[data-funcao-target]');
    if (selectTipo) {
        const campoFuncao = document.getElementById(selectTipo.dataset.funcaoTarget);
        const inputFuncao = campoFuncao?.querySelector('input');
        const atualizarCampos = () => {
            const ehJuiz = selectTipo.value === 'JUDGE';
            if (!campoFuncao || !inputFuncao) return;
            campoFuncao.hidden = ehJuiz;
            inputFuncao.required = !ehJuiz;
            inputFuncao.disabled = ehJuiz;
        };
        selectTipo.addEventListener('change', atualizarCampos);
        atualizarCampos();
    }
})();
