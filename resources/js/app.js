import './bootstrap';
import './theme';
import Chart from 'chart.js/auto';
import Alpine from 'alpinejs';

window.Alpine = Alpine;
window.Chart = Chart;

const countryCodes = 'AD AE AF AG AI AL AM AO AQ AR AS AT AU AW AX AZ BA BB BD BE BF BG BH BI BJ BL BM BN BO BQ BR BS BT BV BW BY BZ CA CC CD CF CG CH CI CK CL CM CN CO CR CU CV CW CX CY CZ DE DJ DK DM DO DZ EC EE EG EH ER ES ET FI FJ FK FM FO FR GA GB GD GE GF GG GH GI GL GM GN GP GQ GR GS GT GU GW GY HK HM HN HR HT HU ID IE IL IM IN IO IQ IR IS IT JE JM JO JP KE KG KH KI KM KN KP KR KW KY KZ LA LB LC LI LK LR LS LT LU LV LY MA MC MD ME MF MG MH MK ML MM MN MO MP MQ MR MS MT MU MV MW MX MY MZ NA NC NE NF NG NI NL NO NP NR NU NZ OM PA PE PF PG PH PK PL PM PN PR PS PT PW PY QA RE RO RS RU RW SA SB SC SD SE SG SH SI SJ SK SL SM SN SO SR SS ST SV SX SY SZ TC TD TF TG TH TJ TK TL TM TN TO TR TT TV TW TZ UA UG UM US UY UZ VA VC VE VG VI VN VU WF WS YE YT ZA ZM ZW'.split(' ');

function countryLabel(code) {
    try {
        return new Intl.DisplayNames([document.documentElement.lang || 'en'], { type: 'region' }).of(code) || code;
    } catch (_) {
        return code;
    }
}

function enhanceCountrySelector() {
    const input = document.querySelector('input#country_code');
    if (!input || input.dataset.enhanced === '1') return;

    const select = document.createElement('select');
    select.id = input.id;
    select.name = input.name;
    select.required = input.required;
    select.className = input.className;
    select.dataset.enhanced = '1';

    const current = (input.value || 'MY').toUpperCase();
    countryCodes
        .map(code => ({ code, label: countryLabel(code) }))
        .sort((a, b) => a.label.localeCompare(b.label))
        .forEach(({ code, label }) => {
            const option = document.createElement('option');
            option.value = code;
            option.textContent = `${label} (${code})`;
            option.selected = code === current;
            select.appendChild(option);
        });

    input.replaceWith(select);
}

function enhancePaymentReceiptUpload() {
    document.querySelectorAll('form[action*="/payments"]').forEach(form => {
        if (form.dataset.receiptEnhanced === '1' || form.querySelector('[name="transfer_receipt"]')) return;

        const method = form.querySelector('[name="payment_method"]');
        if (!method) return;

        form.enctype = 'multipart/form-data';
        form.dataset.receiptEnhanced = '1';

        const wrapper = document.createElement('div');
        wrapper.className = 'payment-transfer-receipt-field';
        wrapper.innerHTML = `
            <label for="transfer_receipt" class="mb-2 block text-sm font-bold text-slate-700 dark:text-slate-200">
                Bank Transfer Receipt <span class="text-xs font-medium text-slate-400">Optional</span>
            </label>
            <input id="transfer_receipt" name="transfer_receipt" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp" class="block w-full rounded-2xl border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900">
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">PDF or image, maximum 5 MB. Recommended for bank transfers.</p>
        `;

        const notes = form.querySelector('[name="notes"]')?.closest('div');
        if (notes) notes.insertAdjacentElement('afterend', wrapper);
        else form.appendChild(wrapper);

        const toggle = () => {
            wrapper.style.display = method.value === 'bank_transfer' ? '' : 'none';
            if (method.value !== 'bank_transfer') wrapper.querySelector('input').value = '';
        };

        method.addEventListener('change', toggle);
        toggle();
    });
}

function enhanceForms() {
    enhanceCountrySelector();
    enhancePaymentReceiptUpload();
}

document.addEventListener('DOMContentLoaded', enhanceForms);
document.addEventListener('alpine:initialized', enhanceForms);
new MutationObserver(enhanceForms).observe(document.documentElement, { childList: true, subtree: true });

Alpine.start();
