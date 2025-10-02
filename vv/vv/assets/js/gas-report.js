document.addEventListener('DOMContentLoaded', function() {
    // Sample data for gas station refueling events
    const gasData = [
        { date: "2025-08-15", vehicleCode: "TR-01", fuelType: "مازوت", quantity: 150, driver: "أحمد محمد" },
        { date: "2025-08-14", vehicleCode: "GR-101", fuelType: "ديزل", quantity: 200, driver: "علي محمد" },
        { date: "2025-08-13", vehicleCode: "FL-103", fuelType: "ديزل", quantity: 50, driver: "محمود حسين" },
        { date: "2025-08-12", vehicleCode: "TR-02", fuelType: "مازوت", quantity: 180, driver: "خالد علي" },
        { date: "2025-07-30", vehicleCode: "TR-01", fuelType: "مازوت", quantity: 160, driver: "أحمد محمد" }
    ];

    // Initial render of the table
    renderGasTable(gasData);

    // Handle custom date range visibility
    const dateRangeSelect = document.getElementById('gasDateRange');
    const customDateRange = document.getElementById('gasCustomDateRange');
    if (dateRangeSelect) {
        dateRangeSelect.addEventListener('change', function() {
            if (this.value === 'custom') {
                customDateRange.style.display = 'flex';
            } else {
                customDateRange.style.display = 'none';
            }
        });
    }
});

function renderGasTable(data) {
    const tableBody = document.getElementById('gasReportBody');
    if (!tableBody) return;
    tableBody.innerHTML = ''; // Clear existing rows

    // Sort data by date descending by default
    const sortedData = data.sort((a, b) => new Date(b.date) - new Date(a.date));

    sortedData.forEach(item => {
        const row = `<tr>
            <td>${item.date}</td>
            <td>${item.vehicleCode}</td>
            <td>${item.fuelType}</td>
            <td>${item.quantity}</td>
            <td>${item.driver}</td>
        </tr>`;
        tableBody.innerHTML += row;
    });
}

function applyGasReportFilter() {
    const vehicle = document.getElementById('filterGasVehicle').value;
    const dateRange = document.getElementById('gasDateRange').value;
    const startDate = document.getElementById('gasStartDate').value;
    const endDate = document.getElementById('gasEndDate').value;

    console.log('Applying gas report filters:', { vehicle, dateRange, startDate, endDate });
    alert('تم تطبيق الفرز. في التطبيق الفعلي، سيتم تحديث الجدول هنا.');
    // Logic to filter 'gasData' and call renderGasTable() would be added here.
}

function resetGasReportFilter() {
    document.getElementById('filterGasVehicle').value = 'all';
    document.getElementById('gasDateRange').value = 'this_month';
    document.getElementById('gasStartDate').value = '';
    document.getElementById('gasEndDate').value = '';
    document.getElementById('gasCustomDateRange').style.display = 'none';

    console.log('Gas report filters reset');
    alert('تم إعادة تعيين الفرز.');
    // Call renderGasTable() with the original, unfiltered data here.
}

let gasSortDirections = {};

function sortGasTable(columnIndex) {
    const table = document.querySelector('#page-gas-report .items-table');
    const tbody = table.tBodies[0];
    const rows = Array.from(tbody.rows);
    const header = table.tHead.rows[0].cells[columnIndex];

    const direction = gasSortDirections[columnIndex] === 'asc' ? 'desc' : 'asc';
    gasSortDirections[columnIndex] = direction;

    document.querySelectorAll('#page-gas-report .items-table th i').forEach(icon => {
        icon.classList.remove('fa-sort-up', 'fa-sort-down');
        icon.classList.add('fa-sort');
    });

    const icon = header.querySelector('i');
    icon.classList.remove('fa-sort');
    icon.classList.add(direction === 'asc' ? 'fa-sort-up' : 'fa-sort-down');

    const sortedRows = rows.sort((a, b) => {
        const aText = a.cells[columnIndex].textContent.trim();
        const bText = b.cells[columnIndex].textContent.trim();

        if (columnIndex === 0) { // Date sorting
            return direction === 'asc' ? new Date(aText) - new Date(bText) : new Date(bText) - new Date(aText);
        } else if (columnIndex === 2) { // Quantity sorting (numeric)
            return direction === 'asc' ? aText - bText : bText - aText;
        } else { // Standard string comparison
            return direction === 'asc' ? aText.localeCompare(bText) : bText.localeCompare(aText);
        }
    });

    tbody.innerHTML = '';
    sortedRows.forEach(row => tbody.appendChild(row));
}
