<?php
// database_viewer.php
// Penjelajah Database Relasional Kargo Udara (7 Tabel) — InJourney Airports Design System
require_once __DIR__ . '/config/database.php';
requireAuth();

$activeTable = $_GET['table'] ?? 'cargo_shipments';
$allowedTables = [
    'cargo_shipments' => ['name' => 'Data Kargo (Shipments)', 'icon' => 'fa-boxes-stacked'],
    'truck_arrivals' => ['name' => 'Kedatangan Truk (TMS)', 'icon' => 'fa-truck'],
    'security_checks' => ['name' => 'Pemeriksaan AVSEC', 'icon' => 'fa-shield-halved'],
    'weight_records' => ['name' => 'Penimbangan Kargo', 'icon' => 'fa-scale-balanced'],
    'uld_containers' => ['name' => 'Kontainer ULD', 'icon' => 'fa-box-open'],
    'flight_manifests' => ['name' => 'Manifes Penerbangan', 'icon' => 'fa-plane'],
    'scan_logs' => ['name' => 'Log Pertukaran Data & JSON', 'icon' => 'fa-code']
];

if (!array_key_exists($activeTable, $allowedTables)) {
    $activeTable = 'cargo_shipments';
}

$pageTitle = 'Database Viewer — 7 Tabel Relasional';
$bodyClass = 'bg-[#F8FAFC] text-slate-800';
include __DIR__ . '/includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
    
    <!-- Top Header (InJourney Style) -->
    <div class="flex flex-col md:flex-row md:items-center justify-between pb-6 border-b border-gray-200 gap-4">
        <div>
            <div class="flex items-center space-x-2.5">
                <span class="w-2.5 h-6 rounded-full bg-gradient-to-b from-[#0CA1AF] to-[#087F8A]"></span>
                <h1 class="text-2xl lg:text-3xl font-extrabold text-[#0D1C42] tracking-tight">Database Viewer &amp; Schema Explorer</h1>
                <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-[#087F8A]/10 text-[#087F8A] border border-[#087F8A]/30 uppercase tracking-wider">
                    MySQL Relational
                </span>
            </div>
            <p class="text-xs lg:text-sm text-slate-500 mt-1 pl-5">
                Transparansi 7 tabel relasional operasional terminal kargo multimoda terintegrasi.
            </p>
        </div>

        <div class="flex items-center space-x-2.5">
            <button onclick="confirmResetData()" class="px-3.5 py-2.5 rounded-xl bg-white hover:bg-slate-50 text-slate-600 hover:text-rose-600 text-xs font-bold border border-gray-300 shadow-sm transition-colors flex items-center">
                <i class="fa-solid fa-rotate-left mr-1.5 text-rose-500"></i>
                <span>Reset Demo State</span>
            </button>
            <button onclick="loadTableData()" class="px-3.5 py-2.5 rounded-xl bg-white hover:bg-slate-50 text-slate-600 hover:text-[#087F8A] border border-gray-300 text-xs shadow-sm transition-colors" title="Muat Ulang Tabel">
                <i class="fa-solid fa-arrows-rotate"></i>
            </button>
        </div>
    </div>

    <!-- 7-Table Navigation Tabs (InJourney Clean Pills) -->
    <div class="flex overflow-x-auto space-x-2 pb-2 scrollbar-thin">
        <?php foreach ($allowedTables as $tblKey => $tblMeta): ?>
            <?php $isActive = ($activeTable === $tblKey); ?>
            <a href="database_viewer.php?table=<?= urlencode($tblKey) ?>" class="flex-shrink-0 px-4 py-2.5 rounded-xl text-xs font-bold border transition-all flex items-center space-x-2 <?= $isActive ? 'bg-gradient-to-r from-[#04AFBF] to-[#087F8A] text-white border-transparent shadow-md shadow-teal-500/20 transform -translate-y-0.5' : 'bg-white text-slate-600 border-gray-200/90 hover:bg-slate-50 hover:text-[#087F8A] shadow-sm' ?>">
                <i class="fa-solid <?= $tblMeta['icon'] ?> text-xs <?= $isActive ? 'text-white' : 'text-slate-400' ?>"></i>
                <span><?= $tblMeta['name'] ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Search & Action Bar (InJourney White Card) -->
    <div class="bg-white p-5 rounded-2xl border border-gray-200/90 shadow-sm flex flex-col sm:flex-row items-center justify-between gap-4">
        <div class="relative w-full sm:w-80">
            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400 text-xs">
                <i class="fa-solid fa-magnifying-glass"></i>
            </span>
            <input type="text" id="tableSearchInput" onkeyup="handleSearch(event)" placeholder="Cari dalam tabel `<?= htmlspecialchars($activeTable) ?>`..." class="w-full pl-9 pr-3.5 py-2 bg-slate-50 border border-gray-200 rounded-xl text-xs text-[#0D1C42] placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#0CA1AF] focus:border-[#0CA1AF] font-medium shadow-inner">
        </div>
        <div class="flex items-center space-x-3 text-xs w-full sm:w-auto justify-between sm:justify-end">
            <span id="rowCountLabel" class="text-xs font-bold text-slate-600 bg-slate-100 px-3 py-1.5 rounded-lg border border-gray-200">
                Memuat baris...
            </span>
            <button onclick="exportTableToCSV()" class="px-3.5 py-2 rounded-xl bg-white hover:bg-slate-50 text-slate-700 hover:text-emerald-700 border border-gray-300 shadow-sm transition-colors flex items-center text-xs font-bold">
                <i class="fa-solid fa-file-csv mr-1.5 text-emerald-600 text-sm"></i>
                <span>Ekspor CSV</span>
            </button>
        </div>
    </div>

    <!-- Table Container (InJourney Clean White Card) -->
    <div class="bg-white rounded-2xl border border-gray-200/90 shadow-sm overflow-hidden">
        <div class="overflow-x-auto min-h-[340px]" id="tableDataWrapper">
            <div class="p-16 text-center text-slate-400 text-xs">
                <i class="fa-solid fa-spinner fa-spin text-2xl mb-3 text-[#087F8A]"></i>
                <p class="font-medium text-slate-500">Memuat data tabel dari server MySQL...</p>
            </div>
        </div>
    </div>

</div>

<!-- Modal Inspect Payload / Cell Detail (InJourney White Popup) -->
<div id="cellModal" class="fixed inset-0 z-50 hidden bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white w-full max-w-2xl rounded-2xl border border-gray-200 p-6 shadow-2xl relative flex flex-col max-h-[85vh]">
        <button onclick="closeCellModal()" class="absolute top-4 right-4 text-slate-400 hover:text-[#0D1C42] text-lg w-8 h-8 rounded-full hover:bg-slate-100 flex items-center justify-center transition-colors">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <h3 id="cellModalTitle" class="text-sm font-bold text-[#0D1C42] mb-1 flex items-center">
            <i class="fa-solid fa-code text-[#087F8A] mr-2"></i>
            Detail Konten Kolom
        </h3>
        <p class="text-[11px] text-slate-500 mb-2">Representasi terformat dari data JSON yang tersimpan di kolom database.</p>
        
        <div class="flex-grow overflow-auto bg-[#0D1C42] border border-slate-700/80 rounded-xl text-xs font-mono p-4 my-2 text-emerald-300 shadow-inner">
            <pre id="cellModalContent" class="leading-relaxed text-[11px]"></pre>
        </div>
        
        <div class="flex items-center justify-between pt-3 border-t border-gray-100 mt-2">
            <button onclick="copyModalJson()" class="px-3.5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold border border-gray-200 transition-colors flex items-center">
                <i class="fa-solid fa-copy mr-1.5 text-[#087F8A]"></i> Salin JSON
            </button>
            <button onclick="closeCellModal()" class="px-4 py-2 rounded-xl bg-[#087F8A] hover:bg-[#0CA1AF] text-white text-xs font-bold shadow-md shadow-teal-500/20 transition-all">
                Tutup
            </button>
        </div>
    </div>
</div>

<script>
    let currentTable = '<?= htmlspecialchars($activeTable) ?>';
    let loadedRows = [];

    document.addEventListener('DOMContentLoaded', () => {
        loadTableData();
    });

    function handleSearch(e) {
        if (e.key === 'Enter' || e.type === 'keyup') {
            const query = document.getElementById('tableSearchInput').value;
            loadTableData(query);
        }
    }

    function loadTableData(search = '') {
        const wrapper = document.getElementById('tableDataWrapper');
        wrapper.innerHTML = `
            <div class="p-16 text-center text-slate-400 text-xs">
                <i class="fa-solid fa-spinner fa-spin text-2xl mb-3 text-[#087F8A]"></i>
                <p class="font-medium text-slate-500">Memuat data tabel dari server MySQL...</p>
            </div>
        `;

        fetch(`api/tables.php?table=${encodeURIComponent(currentTable)}&search=${encodeURIComponent(search)}`)
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    loadedRows = res.rows;
                    renderTableHTML(res.columns, res.rows);
                    document.getElementById('rowCountLabel').textContent = `Total: ${res.total_rows} baris`;
                } else {
                    wrapper.innerHTML = `<div class="p-8 text-center text-rose-500 text-xs font-bold">${res.message}</div>`;
                }
            })
            .catch(err => {
                wrapper.innerHTML = `<div class="p-8 text-center text-rose-500 text-xs font-bold">Gagal mengambil data: ${err}</div>`;
            });
    }

    function renderTableHTML(columns, rows) {
        const wrapper = document.getElementById('tableDataWrapper');
        if (rows.length === 0) {
            wrapper.innerHTML = `
                <div class="p-16 text-center text-slate-400 text-xs">
                    <i class="fa-solid fa-inbox text-3xl mb-3 text-slate-300 block"></i>
                    <p class="font-medium text-slate-500">Tidak ada baris data ditemukan untuk tabel ini.</p>
                </div>
            `;
            return;
        }

        const colNames = columns.map(c => c.Field);
        let thead = '<tr class="bg-slate-50/90 text-slate-500 border-b border-gray-200 text-[10.5px] uppercase font-bold tracking-wider">';
        colNames.forEach(col => {
            thead += `<th class="py-3.5 px-4 font-bold text-[#0D1C42]">${col}</th>`;
        });
        thead += '</tr>';

        let tbody = '';
        rows.forEach(row => {
            tbody += '<tr class="hover:bg-teal-50/30 border-b border-gray-100 transition-colors font-mono text-[11.5px]">';
            colNames.forEach(col => {
                let val = row[col];
                if (val === null || val === undefined) {
                    tbody += '<td class="py-3 px-4 text-slate-400 font-sans italic text-[11px]">NULL</td>';
                } else if (typeof val === 'string' && (val.startsWith('{') || val.startsWith('['))) {
                    // JSON value: truncated with modal opener
                    const preview = val.length > 35 ? (val.substring(0, 35) + '...') : val;
                    tbody += `
                        <td class="py-3 px-4">
                            <button onclick="openJsonModal('${col}', ${JSON.stringify(val).replace(/"/g, '&quot;')})" class="text-[#087F8A] hover:text-[#0CA1AF] underline font-mono text-[10.5px] font-semibold flex items-center gap-1.5 group">
                                <i class="fa-solid fa-code text-[9px] group-hover:scale-110 transition-transform"></i>
                                <span>${preview}</span>
                            </button>
                        </td>
                    `;
                } else {
                    let displayVal = val;
                    if (val === 'CLEARED' || val === 'LOADED') {
                        displayVal = `<span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-emerald-50 text-emerald-700 border border-emerald-200">${val}</span>`;
                    } else if (val === 'SUSPECT') {
                        displayVal = `<span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-rose-50 text-rose-700 border border-rose-300 animate-pulse">${val}</span>`;
                    }
                    tbody += `<td class="py-3 px-4 text-slate-700 max-w-xs truncate">${displayVal}</td>`;
                }
            });
            tbody += '</tr>';
        });

        wrapper.innerHTML = `
            <table class="w-full text-left text-xs divide-y divide-gray-100">
                <thead class="bg-slate-50">${thead}</thead>
                <tbody class="divide-y divide-gray-100 bg-white">${tbody}</tbody>
            </table>
        `;
    }

    function openJsonModal(colName, rawJson) {
        document.getElementById('cellModalTitle').innerHTML = `<i class="fa-solid fa-code text-[#087F8A] mr-2"></i> JSON Payload: ${colName}`;
        try {
            const parsed = JSON.parse(rawJson);
            document.getElementById('cellModalContent').textContent = JSON.stringify(parsed, null, 2);
        } catch (e) {
            document.getElementById('cellModalContent').textContent = rawJson;
        }
        document.getElementById('cellModal').classList.remove('hidden');
    }

    function closeCellModal() {
        document.getElementById('cellModal').classList.add('hidden');
    }

    function copyModalJson() {
        const text = document.getElementById('cellModalContent').textContent;
        navigator.clipboard.writeText(text).then(() => {
            Swal.fire({
                toast: true,
                position: 'top-end',
                title: 'JSON berhasil disalin!',
                icon: 'success',
                showConfirmButton: false,
                timer: 2000,
                background: '#ffffff',
                color: '#0D1C42',
                customClass: { popup: 'rounded-xl shadow-lg border border-gray-200' }
            });
        });
    }

    function exportTableToCSV() {
        if (!loadedRows || loadedRows.length === 0) {
            Swal.fire({
                title: 'Info',
                text: 'Tidak ada data untuk diekspor',
                icon: 'info',
                background: '#ffffff',
                color: '#0D1C42',
                confirmButtonColor: '#087F8A',
                customClass: { popup: 'rounded-2xl shadow-2xl border border-gray-200' }
            });
            return;
        }

        const keys = Object.keys(loadedRows[0]);
        let csvContent = 'data:text/csv;charset=utf-8,';
        csvContent += keys.join(',') + '\r\n';

        loadedRows.forEach(row => {
            let rowArr = keys.map(k => {
                let cell = row[k] === null ? '' : ('' + row[k]).replace(/"/g, '""');
                return `"${cell}"`;
            });
            csvContent += rowArr.join(',') + '\r\n';
        });

        const encodedUri = encodeURI(csvContent);
        const link = document.createElement('a');
        link.setAttribute('href', encodedUri);
        link.setAttribute('download', `${currentTable}_${Date.now()}.csv`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
