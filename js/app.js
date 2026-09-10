/* ==========================================================================
   DUÁS - JAVASCRIPT APP INTERACTION (ENHANCED UX/UI)
   Cart Drawer, AJAX Cart Handler, Modals, Size Selection, Accordions & Toasts
   ========================================================================== */

document.addEventListener('DOMContentLoaded', () => {
    initCartDrawer();
    initPDPControls();
    initMeasuresModal();
    initMobileNav();
    initAccordions();
    initNewsletterForm();
    initDepartmentsMenu();
    initCarousels();
    initCookieBar();
    initCepLookup();
    initCheckoutSteps();
});

/**
 * Aviso de cookies (LGPD) — some depois do aceite
 */
function initCookieBar() {
    const bar = document.getElementById('cookieBar');
    const btn = document.getElementById('cookieAccept');
    if (!bar || !btn) return;

    let aceito = false;
    try { aceito = localStorage.getItem('duas_cookies_ok') === '1'; } catch (e) { aceito = false; }
    if (aceito) return;

    bar.hidden = false;
    btn.addEventListener('click', () => {
        bar.hidden = true;
        try { localStorage.setItem('duas_cookies_ok', '1'); } catch (e) { /* modo privado */ }
    });
}

/**
 * Preenche o endereço a partir do CEP (ViaCEP), para reduzir erro de digitação
 */
function initCepLookup() {
    const cep = document.getElementById('ckCep');
    if (!cep) return;

    cep.addEventListener('blur', async () => {
        const digits = (cep.value || '').replace(/\D/g, '');
        if (digits.length !== 8) return;
        try {
            const r = await fetch('https://viacep.com.br/ws/' + digits + '/json/');
            const d = await r.json();
            if (d.erro) return;
            const set = (id, val) => {
                const el = document.getElementById(id);
                if (el && !el.value && val) el.value = val;
            };
            set('ckStreet', d.logradouro);
            set('ckDistrict', d.bairro);
            set('ckCity', d.localidade);
            set('ckState', d.uf);
        } catch (e) { /* offline ou CEP inexistente: o cliente preenche à mão */ }
    });
}

/**
 * Checkout em etapas: dados -> forma de pagamento -> confirmar e pagar.
 *
 * As três etapas vivem no mesmo <form> e são apenas mostradas/escondidas, então
 * o envio continua sendo um único POST e a tokenização do cartão não muda.
 * Sem JavaScript as três aparecem abertas e o formulário segue funcionando.
 */
function initCheckoutSteps() {
    const form = document.getElementById('checkoutForm');
    if (!form) return;

    const steps = Array.from(form.querySelectorAll('.ck-step'));
    const marks = Array.from(document.querySelectorAll('#ckStepper li'));
    const recap = document.getElementById('ckRecap');
    if (steps.length < 2) return;

    let atual = 0;

    function mostrar(indice, rolar) {
        atual = Math.max(0, Math.min(indice, steps.length - 1));
        steps.forEach((s, i) => { s.hidden = i !== atual; });
        marks.forEach((m, i) => {
            m.classList.toggle('is-current', i === atual);
            m.classList.toggle('is-done', i < atual);
        });
        if (atual === steps.length - 1) atualizarResumo();
        if (rolar) {
            const topo = document.getElementById('ckStepper') || form;
            topo.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    // Mostra na última etapa qual forma de pagamento foi escolhida.
    function atualizarResumo() {
        if (!recap) return;
        const escolhido = form.querySelector('input[name="mp_type"]:checked');
        if (!escolhido) { recap.hidden = true; return; }
        const nome = escolhido.closest('.mp-type').querySelector('.mp-type-name');
        recap.innerHTML = 'Forma de pagamento: <strong>' +
            (nome ? nome.textContent.trim() : '') + '</strong>';
        recap.hidden = false;
    }

    // Só avança com os campos da etapa atual preenchidos corretamente. O
    // servidor valida de novo — isto é só para não descobrir o erro no fim.
    function etapaValida() {
        const campos = steps[atual].querySelectorAll('input, select, textarea');
        for (const campo of campos) {
            if (campo.disabled || campo.type === 'hidden') continue;
            if (!campo.checkValidity()) {
                campo.reportValidity();
                return false;
            }
        }
        return true;
    }

    form.querySelectorAll('[data-goto]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const destino = parseInt(btn.dataset.goto, 10) - 1;
            if (destino > atual && !etapaValida()) return;
            mostrar(destino, true);
        });
    });

    // Trocar a forma de pagamento na etapa 2 atualiza o resumo da etapa 3.
    form.querySelectorAll('input[name="mp_type"]').forEach((r) => {
        r.addEventListener('change', atualizarResumo);
    });

    // Depois de um erro do servidor, reabre direto na etapa que precisa de ajuste.
    const inicial = parseInt(form.dataset.startStep || '1', 10) - 1;
    mostrar(isNaN(inicial) ? 0 : inicial, false);
}

/**
 * Menu "Todos os Departamentos" da home
 */
function initDepartmentsMenu() {
    const toggle = document.getElementById('deptToggle');
    const list = document.getElementById('deptList');
    if (!toggle || !list) return;

    const setOpen = (open) => {
        list.hidden = !open;
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    };

    toggle.addEventListener('click', (e) => {
        e.stopPropagation();
        setOpen(list.hidden);
    });

    document.addEventListener('click', (e) => {
        if (!list.hidden && !list.contains(e.target) && e.target !== toggle) setOpen(false);
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') setOpen(false);
    });
}

/**
 * Carrossel horizontal com setas e indicadores
 */
function initCarousels() {
    document.querySelectorAll('[data-carousel]').forEach((root) => {
        const track = root.querySelector('[data-carousel-track]');
        const dotsBox = root.querySelector('[data-carousel-dots]');
        const prev = root.querySelector('.carousel-prev');
        const next = root.querySelector('.carousel-next');
        if (!track) return;

        const pageCount = () => Math.max(1, Math.round(track.scrollWidth / track.clientWidth));
        const currentPage = () => Math.round(track.scrollLeft / track.clientWidth);

        function buildDots() {
            if (!dotsBox) return;
            const total = pageCount();
            dotsBox.innerHTML = '';
            if (total < 2) return;
            for (let i = 0; i < total; i++) {
                const b = document.createElement('button');
                b.type = 'button';
                b.setAttribute('aria-label', 'Ir para a página ' + (i + 1));
                b.addEventListener('click', () => track.scrollTo({ left: i * track.clientWidth, behavior: 'smooth' }));
                dotsBox.appendChild(b);
            }
        }

        function sync() {
            const page = currentPage();
            if (dotsBox) {
                dotsBox.querySelectorAll('button').forEach((b, i) => b.classList.toggle('is-active', i === page));
            }
            if (prev) prev.disabled = track.scrollLeft <= 4;
            if (next) next.disabled = track.scrollLeft + track.clientWidth >= track.scrollWidth - 4;
        }

        if (prev) prev.addEventListener('click', () => track.scrollBy({ left: -track.clientWidth, behavior: 'smooth' }));
        if (next) next.addEventListener('click', () => track.scrollBy({ left: track.clientWidth, behavior: 'smooth' }));

        track.addEventListener('scroll', () => window.requestAnimationFrame(sync), { passive: true });
        window.addEventListener('resize', () => { buildDots(); sync(); });

        buildDots();
        sync();
    });
}

/**
 * Cart Drawer & AJAX Handlers
 */
function initCartDrawer() {
    const overlay = document.getElementById('drawerOverlay');
    const drawer = document.getElementById('cartDrawer');
    const openBtns = document.querySelectorAll('.js-open-cart');
    const closeBtn = document.getElementById('drawerClose');

    function openCart() {
        if (overlay && drawer) {
            overlay.classList.add('active');
            drawer.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    }

    function closeCart() {
        if (overlay && drawer) {
            overlay.classList.remove('active');
            drawer.classList.remove('active');
            document.body.style.overflow = '';
        }
    }

    openBtns.forEach(btn => btn.addEventListener('click', (e) => {
        e.preventDefault();
        openCart();
    }));

    if (closeBtn) closeBtn.addEventListener('click', closeCart);
    if (overlay) overlay.addEventListener('click', closeCart);

    // Global listener for Quick Add buttons or Add to Cart form
    document.addEventListener('click', (e) => {
        if (e.target.closest('.js-add-to-cart')) {
            e.preventDefault();
            const btn = e.target.closest('.js-add-to-cart');
            const productId = btn.dataset.productId;
            const size = btn.dataset.size || (document.querySelector('.size-btn.active')?.dataset.size) || 'M';

            addToCartAJAX(productId, size, 1);
        }

        if (e.target.closest('.js-qty-change')) {
            const btn = e.target.closest('.js-qty-change');
            const action = btn.dataset.action;
            const productId = btn.dataset.productId;
            const size = btn.dataset.size;
            updateCartQuantityAJAX(productId, size, action);
        }

        if (e.target.closest('.js-remove-item')) {
            const btn = e.target.closest('.js-remove-item');
            const productId = btn.dataset.productId;
            const size = btn.dataset.size;
            removeFromCartAJAX(productId, size);
        }
    });
}

/**
 * Add Product to Cart via AJAX
 */
function addToCartAJAX(productId, size, quantity) {
    const formData = new FormData();
    formData.append('action', 'add');
    formData.append('productId', productId);
    formData.append('size', size);
    formData.append('quantity', quantity);

    fetch('api/cart.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            updateCartUI(data);
            showToast('Item adicionado à sua sacola!');
            
            // If user is on full carrinho.php page, refresh the page to keep checkout sync
            if (window.location.pathname.includes('carrinho.php')) {
                setTimeout(() => window.location.reload(), 400);
            } else {
                // Open drawer
                document.getElementById('drawerOverlay')?.classList.add('active');
                document.getElementById('cartDrawer')?.classList.add('active');
            }
        } else {
            showToast(data.message || 'Erro ao adicionar item.', 'error');
        }
    })
    .catch(err => {
        console.error('API Error:', err);
        showToast('Erro ao se comunicar com a sacola.', 'error');
    });
}

function updateCartQuantityAJAX(productId, size, action) {
    const formData = new FormData();
    formData.append('action', 'update');
    formData.append('productId', productId);
    formData.append('size', size);
    formData.append('type', action);

    fetch('api/cart.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            updateCartUI(data);
            if (window.location.pathname.includes('carrinho.php')) {
                window.location.reload();
            }
        }
    });
}

function removeFromCartAJAX(productId, size) {
    const formData = new FormData();
    formData.append('action', 'remove');
    formData.append('productId', productId);
    formData.append('size', size);

    fetch('api/cart.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            updateCartUI(data);
            showToast('Item removido da sacola.');
            if (window.location.pathname.includes('carrinho.php')) {
                window.location.reload();
            }
        }
    });
}

/**
 * Update Cart Drawer HTML & Badges
 */
function updateCartUI(data) {
    // Badges update
    const badges = document.querySelectorAll('.js-cart-badge');
    badges.forEach(b => b.textContent = data.summary.count);

    // Cart Drawer Body replacement
    const drawerBody = document.getElementById('drawerBodyContent');
    const drawerFooter = document.getElementById('drawerFooterContent');
    const freeShippingBar = document.getElementById('freeShippingBarContent');

    if (drawerBody && data.htmlItems) {
        drawerBody.innerHTML = data.htmlItems;
    }

    if (drawerFooter && data.htmlFooter) {
        drawerFooter.innerHTML = data.htmlFooter;
    }

    if (freeShippingBar && data.htmlShippingBar) {
        freeShippingBar.innerHTML = data.htmlShippingBar;
    }
}

/**
 * PDP Controls (Size Selection & Gallery & CEP Calculator)
 */
function initPDPControls() {
    const sizeBtns = document.querySelectorAll('.size-btn');
    const pdpAddToCartBtn = document.querySelector('.pdp-details-sticky .js-add-to-cart');

    sizeBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            sizeBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            
            const selectedSize = btn.dataset.size;
            if (pdpAddToCartBtn) {
                pdpAddToCartBtn.dataset.size = selectedSize;
            }

            const hiddenSizeInput = document.getElementById('selectedSizeInput');
            if (hiddenSizeInput) {
                hiddenSizeInput.value = selectedSize;
            }
        });
    });

    // Gallery: swap main image on thumbnail click
    const mainImg = document.getElementById('pdpMainImage');
    const thumbs = document.querySelectorAll('.pdp-thumb');
    thumbs.forEach(thumb => {
        thumb.addEventListener('click', () => {
            const src = thumb.dataset.img;
            if (mainImg && src) mainImg.src = src;
            thumbs.forEach(t => t.classList.remove('active'));
            thumb.classList.add('active');
        });
    });

    // Copy product link to clipboard
    const copyBtn = document.querySelector('.js-copy-link');
    if (copyBtn) {
        copyBtn.addEventListener('click', () => {
            const url = window.location.href;
            if (navigator.clipboard) {
                navigator.clipboard.writeText(url).then(() => showToast('Link copiado!'));
            } else {
                showToast('Link: ' + url);
            }
        });
    }

    // CEP Shipping calculator simulation
    const cepForm = document.getElementById('cepForm');
    if (cepForm) {
        cepForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const resultBox = document.getElementById('cepResult');
            if (resultBox) {
                resultBox.style.display = 'block';
                resultBox.innerHTML = `
                    <div style="font-size: 0.85rem; padding: 12px; background: #FFFFFF; border: 1px solid #E5E5E5; margin-top: 10px; border-radius: 4px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 6px; font-weight: 500;">
                            <span>📦 <strong>Frete Expresso Duás</strong> (2 a 3 dias)</span>
                            <span>R$ 24,90</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; color: #2E7D32; font-weight: 500;">
                            <span>✨ <strong>Frete Padrão / Grátis</strong> (4 a 6 dias)</span>
                            <span>GRÁTIS</span>
                        </div>
                    </div>
                `;
            }
        });
    }
}

/**
 * Guia de Medidas Modal Toggle
 */
function initMeasuresModal() {
    const backdrop = document.getElementById('measuresModalBackdrop');
    const openBtns = document.querySelectorAll('.js-open-measures');
    const closeBtn = document.getElementById('measuresModalClose');

    openBtns.forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            if (backdrop) backdrop.classList.add('active');
        });
    });

    if (closeBtn && backdrop) {
        closeBtn.addEventListener('click', () => backdrop.classList.remove('active'));
    }
    if (backdrop) {
        backdrop.addEventListener('click', (e) => {
            if (e.target === backdrop) backdrop.classList.remove('active');
        });
    }
}

/**
 * Mobile Navigation Drawer
 */
function initMobileNav() {
    const toggleBtn = document.getElementById('mobileNavToggle');
    const closeBtn = document.getElementById('mobileNavClose');
    const menu = document.getElementById('mobileNavMenu');
    const overlay = document.getElementById('mobileNavOverlay');

    function openMobileNav() {
        if (menu && overlay) {
            menu.classList.add('active');
            overlay.classList.add('active');
        }
    }

    function closeMobileNav() {
        if (menu && overlay) {
            menu.classList.remove('active');
            overlay.classList.remove('active');
        }
    }

    if (toggleBtn) toggleBtn.addEventListener('click', openMobileNav);
    if (closeBtn) closeBtn.addEventListener('click', closeMobileNav);
    if (overlay) overlay.addEventListener('click', closeMobileNav);
}

/**
 * Accordions for PDP and FAQ
 */
function initAccordions() {
    const headers = document.querySelectorAll('.accordion-header');
    headers.forEach(h => {
        h.addEventListener('click', () => {
            const item = h.parentElement;
            item.classList.toggle('active');
        });
    });
}

/**
 * Newsletter Form
 */
function initNewsletterForm() {
    const forms = document.querySelectorAll('.js-newsletter-form');
    forms.forEach(form => {
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            showToast('Obrigado por se inscrever! Bem-vinda à Duás.');
            form.reset();
        });
    });
}

/**
 * Toast Notifications System
 */
function showToast(message, type = 'success') {
    let container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="20 6 9 17 4 12"></polyline>
        </svg>
        <span>${message}</span>
    `;

    container.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transition = 'opacity 0.4s ease';
        setTimeout(() => toast.remove(), 400);
    }, 3500);
}
