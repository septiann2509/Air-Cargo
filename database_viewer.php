<?php
// database_viewer.php
// Penjelajah Database Relasional Kargo Udara (7 Tabel)
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
include __DIR__ . '/includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    
    <!-- Top Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between pb-6 mb-6 border-b border-slate-800 gap-4">
        <div>
            <div class="flex items-center space-x-2">
                <h1 class="text-2xl font-black text-white tracking-tight">Database Viewer &amp; Schema Explorer</h1>
                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-[#087F8A]/20 text-[#0CA1AF] border border-[#087F8A]/50 uppercase">
                    MySQL Relational
                </span>
            </div>
            <p class="text-xs text-slate-400 mt-1">
                Transparansi 7 tabel relasional yang direkomendasikan kepada tim teknis klien pengelola terminal kargo.
            </p>
        </div>

        <div class="flex items-center space-x-2">
            <button onclick="confirmResetData()" class="px-3.5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-medium border border-slate-700 transition-colors flex items-center">
                <i class="fa-solid fa-rotate-left mr-1.5"></i>
                <span>Reset Demo State</span>
            </button>
            <button onclick="loadTableData()" class="px-3 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white border border-slate-700 text-xs transition-colors" title="Muat Ulang Tabel">
                <i class="fa-solid fa-arrows-rotate"></i>
            </button>
        </div>
    </div>

    <!-- 7-Table Navigation Tabs -->
    <div class="flex overflow-x-auto space-x-2 pb-3 mb-6 scrollbar-thin">
        <?php foreach ($allowedTables as $tblKey => $tblMeta): ?>
            <a href="database_viewer.php?table=<?= urlencode($tblKey) ?>" class="flex-shrink-0 px-3.5 py-2 rounded-xl text-xs font-semibold border transition-all flex items-center space-x-2 <?= ($activeTable === $tblKey) ? 'bg-[#087F8A]/30 text-[#0CA1AF] border-[#087F8A]/60 shadow-lg shadow-teal-500/10' : 'bg-slate-900/80 text-slate-400 border-slate-800 hover:text-white hover:border-slate-700' ?>">
                <i class="fa-solid <?= $tblMeta['icon'] ?>"></i>
                <span><?= $tblMeta['name'] ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Search & Filter Bar -->
    <div class="glass-panel p-4 rounded-2xl border border-slate-800 mb-6 flex flex-col sm:flex-row items-center justify-between gap-3">
        <div class="relative w-full sm:w-80">
            <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-500 text-xs">
                <i class="fa-solid fa-magnifying-glass"></i>
            </span>
            <input type="text" id="tableSearchInput" onkeyup="handleSearch(event)" placeholder="Cari dalam tabel `<?= htmlspecialchars($activeTable) ?>`..." class="w-full pl-9 pr-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-xs text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-[#0CA1AF] focus:border-[#0CA1AF]">
        </div>
        <div class="flex items-center space-x-3 text-xs text-slate-400">
            <span id="rowCountLabel">Memuat baris...</span>
            <button onclick="exportTableToCSV()" class="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 transition-colors flex items-center text-xs">
                <i class="fa-solid fa-file-csv mr-1.5 text-emerald-400"></i> Ekspor CSV
            </button>
        </div>
    </div>

    <!-- Table Container -->
    <div class="glass-panel rounded-2xl border border-slate-800 overflow-hidden shadow-2xl">
        <div class="overflow-x-auto min-h-[300px]" id="tableDataWrapper">
            <div class="p-12 text-center text-slate-500 text-xs">
                <i class="fa-solid fa-spinner fa-spin text-2xl mb-2 text-[#0CA1AF]"></i>
                <p>Memuat data tabel dari server MySQL...</p>
            </div>
        </div>
    </div>

</div>

<!-- Modal Inspect Payload / Cell Detail -->
<div id="cellModal" class="fixed inset-0 z-50 hidden bg-black/70 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="glass-panel w-full max-w-2xl rounded-2xl border border-slate-700 p-6 shadow-2xl relative flex flex-col max-h-[85vh]">
        <button onclick="closeCellModal()" class="absolute top-4 right-4 text-slate-400 hover:text-white text-lg">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <h3 id="cellModalTitle" class="text-sm font-bold text-white mb-2 flex items-center">
            <i class="fa-solid fa-code text-[#0CA1AF] mr-2"></i>
            Detail Konten Kolom
        </h3>
        <div class="flex-grow overflow-auto code-container rounded-xl text-xs font-mono p-4 my-3 text-teal-200">
            <pre id="cellModalContent"></pre>
        </div>
        <div class="text-right pt-2">
            <button onclick="closeCellModal()" class="px-4 py-2 rounded-xl bg-slate-800 text-slate-300 hover:text-white text-xs font-semibold">
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
            <div class="p-12 text-center text-slate-500 text-xs">
                <i class="fa-solid fa-spinner fa-spin text-2xl mb-2 text-sky-400"></i>
                <p>Memuat data tabel dari server MySQL...</p>
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
                    wrapper.innerHTML = `<div class="p-8 text-center text-rose-400 text-xs">${res.message}</div>`;
                }
            })
            .catch(err => {
                wrapper.innerHTML = `<div class="p-8 text-center text-rose-400 text-xs">Gagal mengambil data: ${err}</div>`;
            });
    }

    function renderTableHTML(columns, rows) {
        const wrapper = document.getElementById('tableDataWrapper');
        if (rows.length === 0) {
            wrapper.innerHTML = `
                <div class="p-12 text-center text-slate-500 text-xs">
                    <i class="fa-solid fa-inbox text-3xl mb-2 text-slate-600 block"></i>
                    Tidak ada baris data ditemukan untuk tabel ini.
                </div>
            `;
            return;
        }

        const colNames = columns.map(c => c.Field);
        let thead = '<tr class="bg-slate-900/90 text-slate-400 border-b border-slate-800 text-[10px] uppercase font-semibold">';
        colNames.forEach(col => {
            thead += `<th class="py-3 px-4">${col}</th>`;
        });
        thead += '</tr>';

        let tbody = '';
        rows.forEach(row => {
            tbody += '<tr class="hover:bg-slate-800/40 border-b border-slate-800/50 transition-colors font-mono text-[11px]">';
            colNames.forEach(col => {
                let val = row[col];
                if (val === null || val === undefined) {
                    tbody += '<td class="py-2.5 px-4 text-slate-600 font-sans italic">NULL</td>';
                } else if (typeof val === 'string' && (val.startsWith('{') || val.startsWith('['))) {
                    // JSON value: truncated with modal opener
                    const preview = val.length > 35 ? (val.substring(0, 35) + '...') : val;
                    tbody += `
                        <td class="py-2.5 px-4">
                            <button onclick="openJsonModal('${col}', ${JSON.stringify(val).replace(/"/g, '&quot;')})" class="text-[#0CA1AF] hover:text-teal-200 underline font-mono text-[10px]">
                                ${preview}
                            </button>
                        </td>
                    `;
                } else {
                    let displayVal = val;
                    if (val === 'CLEARED' || val === 'LOADED') {
                        displayVal = `<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-950 text-emerald-300 border border-emerald-800">${val}</span>`;
                    } else if (val === 'SUSPECT') {
                        displayVal = `<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-950 text-rose-300 border border-rose-800 animate-pulse">${val}</span>`;
                    }
                    tbody += `<td class="py-2.5 px-4 text-slate-300 max-w-xs truncate">${displayVal}</td>`;
                }
            });
            tbody += '</tr>';
        });

        wrapper.innerHTML = `
            <table class="w-full text-left text-xs">
                <thead>${thead}</thead>
                <tbody class="divide-y divide-slate-800/50">${tbody}</tbody>
            </table>
        `;
    }

    function openJsonModal(colName, rawJson) {
        document.getElementById('cellModalTitle').innerHTML = `<i class="fa-solid fa-code text-[#0CA1AF] mr-2"></i> JSON Payload: ${colName}`;
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

    function exportTableToCSV() {
        if (!loadedRows || loadedRows.length === 0) {
            Swal.fire('Info', 'Tidak ada data untuk diekspor', 'info');
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
