document.addEventListener('DOMContentLoaded', function() {
    // Sample data for warehouse items
    const warehouseData = [
        { date: "2025-08-15", item: "زيت محرك", quantity: 5, recipient: "قسم صيانة الشاحنات", notes: "صيانة دورية لـ TR-01" },
        { date: "2025-08-14", item: "فلتر زيت", quantity: 10, recipient: "قسم صيانة الجرافات", notes: "تغيير فلاتر لـ GR-101" },
        { date: "2025-08-12", item: "إطارات", quantity: 4, recipient: "قسم صيانة الشاحنات", notes: "تغيير إطارات لـ TR-02" },
        { date: "2025-08-10", item: "سائل تبريد", quantity: 2, recipient: "قسم صيانة الرافعات", notes: "صيانة FL-103" },
        { date: "2025-07-25", item: "زيت محرك", quantity: 8, recipient: "قسم صيانة الجرافات", notes: "صيانة GR-102" }
    ];

    // Initial render of the table
    renderTable(warehouseData);

    // Handle custom date range visibility
    const dateRangeSelect = document.getElementById('dateRange');
    const customDateRange = document.getElementById('customDateRange');
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

function renderTable(data) {
    const tableBody = document.getElementById('warehouseReportBody');
    if (!tableBody) return;
    tableBody.innerHTML = ''; // Clear existing rows

    // Sort data by date descending by default
    const sortedData = data.sort((a, b) => new Date(b.date) - new Date(a.date));

    sortedData.forEach(item => {
        const row = `<tr>
            <td>${item.date}</td>
            <td>${item.item}</td>
            <td>${item.quantity}</td>
            <td>${item.recipient}</td>
            <td>${item.notes}</td>
        </tr>`;
        tableBody.innerHTML += row;
    });
}

function applyWarehouseReportFilter() {
    const item = document.getElementById('filterItem').value;
    const quantity = document.getElementById('filterQuantity').value;
    const dateRange = document.getElementById('dateRange').value;
    const startDate = document.getElementById('startDate').value;
    const endDate = document.getElementById('endDate').value;

    console.log('Applying filters:', { item, quantity, dateRange, startDate, endDate });
    alert('تم تطبيق الفرز. في التطبيق الفعلي، سيتم تحديث الجدول هنا.');
    // Logic to filter 'warehouseData' and call renderTable() would be added here.
}

function resetWarehouseReportFilter() {
    document.getElementById('filterItem').value = 'all';
    document.getElementById('filterQuantity').value = '';
    document.getElementById('dateRange').value = 'this_month';
    document.getElementById('startDate').value = '';
    document.getElementById('endDate').value = '';
    document.getElementById('customDateRange').style.display = 'none';

    console.log('Filters reset');
    alert('تم إعادة تعيين الفرز.');
    // Call renderTable() with the original, unfiltered data here.
}

let sortDirections = {};

function sortTable(columnIndex) {
    const table = document.querySelector('.items-table');
    const tbody = table.tBodies[0];
    const rows = Array.from(tbody.rows);
    const header = table.tHead.rows[0].cells[columnIndex];

    const direction = sortDirections[columnIndex] === 'asc' ? 'desc' : 'asc';
    sortDirections[columnIndex] = direction;

    document.querySelectorAll('.items-table th i').forEach(icon => {
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
