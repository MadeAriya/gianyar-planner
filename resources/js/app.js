document.addEventListener('DOMContentLoaded', () => {
    // Mobile navigation toggle
    const menuToggle = document.getElementById('mobile-menu-toggle');
    const mobileNav = document.getElementById('mobile-navigation');

    if (menuToggle && mobileNav) {
        menuToggle.addEventListener('click', () => {
            const isClosed = mobileNav.classList.contains('hidden');
            mobileNav.classList.toggle('hidden', !isClosed);
            mobileNav.classList.toggle('flex', isClosed);
            menuToggle.setAttribute('aria-expanded', isClosed ? 'true' : 'false');
        });
        mobileNav.querySelectorAll('a').forEach((link) => {
            link.addEventListener('click', () => {
                mobileNav.classList.add('hidden');
                mobileNav.classList.remove('flex');
                menuToggle.setAttribute('aria-expanded', 'false');
            });
        });
    }

    // User dropdown toggle
    const avatarBtn = document.getElementById('avatar-btn');
    const avatarMenu = document.getElementById('avatar-menu');
    if (avatarBtn && avatarMenu) {
        avatarBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            avatarMenu.classList.toggle('hidden');
        });
        document.addEventListener('click', () => avatarMenu.classList.add('hidden'));
    }

    // Duration stepper & date syncing
    const durasiVal = document.getElementById('durasi-value');
    const durasiInput = document.getElementById('durasi-input');
    const malamLabel = document.getElementById('malam-label');
    const btnMinus = document.getElementById('durasi-minus');
    const btnPlus = document.getElementById('durasi-plus');

    if (durasiVal && btnMinus && btnPlus) {
        const updateDurasi = (val) => {
            val = Math.max(1, Math.min(7, val));
            durasiVal.textContent = val + ' Hari';
            if (durasiInput) durasiInput.value = val;
            if (malamLabel) malamLabel.textContent = Math.max(0, val - 1) + ' Malam';
            syncCheckoutFromDurasi(val);
        };
        btnMinus.addEventListener('click', () => updateDurasi(parseInt(durasiInput.value || 2) - 1));
        btnPlus.addEventListener('click', () => updateDurasi(parseInt(durasiInput.value || 2) + 1));
    }

    function syncCheckoutFromDurasi(durasi) {
        const checkinInput = document.getElementById('tgl_berangkat');
        const checkoutInput = document.getElementById('tgl_pulang');
        if (checkinInput && checkoutInput && checkinInput.value) {
            const d = new Date(checkinInput.value);
            d.setDate(d.getDate() + durasi);
            checkoutInput.value = d.toISOString().split('T')[0];
        }
    }

    // Category multi-select
    document.querySelectorAll('[data-kategori]').forEach((card) => {
        const input = card.querySelector('input[type="checkbox"]');
        if (input) {
            input.addEventListener('change', () => {
                const isChecked = input.checked;
                card.classList.toggle('border-forest', isChecked);
                card.classList.toggle('ring-1', isChecked);
                card.classList.toggle('ring-forest', isChecked);
                card.classList.toggle('border-line', !isChecked);
                const check = card.querySelector('.kategori-check');
                if (check) check.classList.toggle('hidden', !isChecked);
            });
        }
    });

    // Toggle group (Pilih Semua per Kategori Utama)
    document.querySelectorAll('[data-toggle-group]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const groupKey = btn.dataset.toggleGroup;
            const groupContainer = document.querySelector(`[data-category-group="${groupKey}"]`);
            if (!groupContainer) return;

            const checkboxes = groupContainer.querySelectorAll('input[type="checkbox"]');
            const allChecked = Array.from(checkboxes).every((cb) => cb.checked);

            checkboxes.forEach((cb) => {
                cb.checked = !allChecked;
                cb.dispatchEvent(new Event('change'));
            });

            btn.textContent = allChecked ? 'Pilih Semua' : 'Batal Pilih';
        });
    });

    // Day tabs
    document.querySelectorAll('[data-day-tab]').forEach((tab) => {
        tab.addEventListener('click', () => {
            const day = tab.dataset.dayTab;
            document.querySelectorAll('[data-day-tab]').forEach((t) => {
                t.classList.toggle('border-b-2', t.dataset.dayTab === day);
                t.classList.toggle('border-forest', t.dataset.dayTab === day);
                t.classList.toggle('text-forest', t.dataset.dayTab === day);
                t.classList.toggle('font-semibold', t.dataset.dayTab === day);
                t.classList.toggle('text-muted', t.dataset.dayTab !== day);
            });
            document.querySelectorAll('[data-day-content]').forEach((c) => {
                c.classList.toggle('hidden', c.dataset.dayContent !== day);
            });
        });
    });

    // Guide selection (radio behavior)
    document.querySelectorAll('[data-guide-select]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const id = btn.dataset.guideSelect;
            const guideInput = document.getElementById('selected-guide-input');
            if (guideInput) guideInput.value = id;

            document.querySelectorAll('[data-guide-card]').forEach((c) => {
                const isSelected = c.dataset.guideCard === id;
                c.classList.toggle('border-forest', isSelected);
                c.classList.toggle('ring-1', isSelected);
                c.classList.toggle('ring-forest', isSelected);
                c.classList.toggle('border-line', !isSelected);
                const check = c.querySelector('.guide-check');
                if (check) check.classList.toggle('hidden', !isSelected);
                const btnText = c.querySelector('[data-guide-select]');
                if (btnText) btnText.textContent = isSelected ? 'Terpilih' : 'Pilih';
            });
            recalcTotal();
        });
    });

    // Gear quantity steppers
    document.querySelectorAll('[data-gear-minus]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const id = btn.dataset.gearMinus;
            const input = document.getElementById('gear-qty-' + id);
            if (input) {
                let val = Math.max(0, parseInt(input.value || 0) - 1);
                input.value = val;
                recalcTotal();
            }
        });
    });
    document.querySelectorAll('[data-gear-plus]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const id = btn.dataset.gearPlus;
            const input = document.getElementById('gear-qty-' + id);
            if (input) {
                const max = parseInt(input.dataset.max || 99);
                let val = Math.min(max, parseInt(input.value || 0) + 1);
                input.value = val;
                recalcTotal();
            }
        });
    });

    // Recalculate total estimate
    function recalcTotal() {
        const totalEl = document.getElementById('estimasi-total');
        if (!totalEl) return;

        const durasi = parseInt(document.getElementById('itinerary-durasi')?.value || 1);
        const bundleDiscount = parseFloat(document.getElementById('bundle-discount')?.value || 0);
        const isBundle = document.getElementById('is-bundle')?.value === '1';
        let total = 0;

        // Guide tarif
        const guideId = document.getElementById('selected-guide-input')?.value;
        if (guideId) {
            const tarifEl = document.querySelector(`[data-guide-card="${guideId}"] [data-tarif]`);
            if (tarifEl) total += parseInt(tarifEl.dataset.tarif) * durasi;
        }

        // Gear totals
        document.querySelectorAll('[data-gear-harga]').forEach((el) => {
            const id = el.dataset.gearHarga;
            const harga = parseInt(el.dataset.harga || 0);
            const qty = parseInt(document.getElementById('gear-qty-' + id)?.value || 0);
            total += harga * qty * durasi;
        });

        if (isBundle && total > 0) {
            total = Math.round(total * (1 - bundleDiscount));
        }

        totalEl.textContent = total > 0 ? 'Rp ' + total.toLocaleString('id-ID') : 'Rp —';
    }

    // Initial calculation on page load if values exist
    recalcTotal();
});
