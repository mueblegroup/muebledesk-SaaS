<section class="rounded-3xl border border-indigo-200 bg-indigo-50/70 p-5 dark:border-indigo-900 dark:bg-indigo-950/30">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h4 class="text-lg font-extrabold text-indigo-950 dark:text-indigo-100">MyInvois Individual Assistant</h4>
            <p class="mt-1 text-sm text-indigo-800 dark:text-indigo-300">
                Enter the buyer's NRIC under Tax &amp; E-Invoice Identity, then search MyInvois. The system will populate and verify the TIN, select NRIC, and set Malaysia automatically.
            </p>
            <p class="mt-1 text-xs text-indigo-700/80 dark:text-indigo-400">MyInvois does not return the buyer's legal name or address; enter those as shown on MyKad and the buyer's billing details.</p>
        </div>
        <button id="myinvois-nric-search" type="button" class="btn-primary shrink-0">
            <span id="myinvois-search-label">Search NRIC in MyInvois</span>
        </button>
    </div>

    <div id="myinvois-search-message" class="mt-4 hidden rounded-2xl border px-4 py-3 text-sm font-semibold" role="status" aria-live="polite"></div>
</section>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const searchButton = document.getElementById('myinvois-nric-search');
    const searchLabel = document.getElementById('myinvois-search-label');
    const messageBox = document.getElementById('myinvois-search-message');

    const showMessage = (message, success = false) => {
        if (!messageBox) return;
        messageBox.textContent = message;
        messageBox.classList.remove(
            'hidden',
            'border-emerald-200', 'bg-emerald-50', 'text-emerald-800',
            'dark:border-emerald-900', 'dark:bg-emerald-950/40', 'dark:text-emerald-300',
            'border-red-200', 'bg-red-50', 'text-red-800',
            'dark:border-red-900', 'dark:bg-red-950/40', 'dark:text-red-300'
        );
        messageBox.classList.add(...(success
            ? ['border-emerald-200', 'bg-emerald-50', 'text-emerald-800', 'dark:border-emerald-900', 'dark:bg-emerald-950/40', 'dark:text-emerald-300']
            : ['border-red-200', 'bg-red-50', 'text-red-800', 'dark:border-red-900', 'dark:bg-red-950/40', 'dark:text-red-300']
        ));
    };

    if (searchButton) {
        searchButton.addEventListener('click', async () => {
            const idInput = document.getElementById('id_number');
            const idNumber = (idInput?.value || '').replace(/[^A-Za-z0-9]/g, '');

            if (!idNumber) {
                showMessage('Enter the buyer NRIC first.');
                idInput?.focus();
                return;
            }

            searchButton.disabled = true;
            searchLabel.textContent = 'Searching…';
            showMessage('Contacting MyInvois ' + @js(strtoupper(config('myinvois.environment', 'sandbox'))) + '…');

            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                if (!csrfToken) throw new Error('Security token is missing. Refresh the page and try again.');

                const response = await fetch(@js(route('myinvois.taxpayers.search')), {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ id_type: 'NRIC', id_number: idNumber }),
                });

                const raw = await response.text();
                let data = {};
                try {
                    data = raw ? JSON.parse(raw) : {};
                } catch (parseError) {
                    throw new Error('The server returned an unexpected response (HTTP ' + response.status + ').');
                }

                if (!response.ok) {
                    throw new Error(data.message || data.error || 'Taxpayer lookup failed (HTTP ' + response.status + ').');
                }

                const clientType = document.getElementById('client_type');
                const idType = document.getElementById('id_type');
                const tinInput = document.getElementById('tin_number');
                const countryInput = document.getElementById('country_code');

                if (clientType) clientType.value = 'individual';
                if (idType) idType.value = 'NRIC';
                if (idInput) idInput.value = data.id_number || idNumber;
                if (tinInput) tinInput.value = data.tin || '';
                if (countryInput) countryInput.value = 'MYS';

                showMessage((data.message || 'Taxpayer found and verified.') + (data.tin ? ' TIN: ' + data.tin : '') + (data.environment ? ' (' + data.environment + ')' : ''), Boolean(data.verified));
            } catch (error) {
                console.error('MyInvois taxpayer lookup failed:', error);
                showMessage(error instanceof Error ? error.message : 'Taxpayer lookup failed.');
            } finally {
                searchButton.disabled = false;
                searchLabel.textContent = 'Search NRIC in MyInvois';
            }
        });
    }

    const stateInput = document.getElementById('state');
    if (stateInput && stateInput.tagName !== 'SELECT') {
        const states = {
            '01': 'Johor', '02': 'Kedah', '03': 'Kelantan', '04': 'Melaka',
            '05': 'Negeri Sembilan', '06': 'Pahang', '07': 'Pulau Pinang',
            '08': 'Perak', '09': 'Perlis', '10': 'Selangor', '11': 'Terengganu',
            '12': 'Sabah', '13': 'Sarawak', '14': 'W.P. Kuala Lumpur',
            '15': 'W.P. Labuan', '16': 'W.P. Putrajaya', '17': 'Not Applicable'
        };

        const select = document.createElement('select');
        select.id = stateInput.id;
        select.name = stateInput.name;
        select.className = stateInput.className;

        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = 'Select state';
        select.appendChild(placeholder);

        Object.entries(states).forEach(([code, label]) => {
            const option = document.createElement('option');
            option.value = code;
            option.textContent = `${label} (${code})`;
            option.selected = stateInput.value === code || stateInput.value.toLowerCase() === label.toLowerCase();
            select.appendChild(option);
        });

        stateInput.replaceWith(select);
    }

    const country = document.getElementById('country_code');
    if (country && (!country.value || country.value.toUpperCase() === 'MY')) country.value = 'MYS';
});
</script>
@endpush