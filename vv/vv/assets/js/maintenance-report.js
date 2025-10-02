document.addEventListener('DOMContentLoaded', function() {
    // Check if we're on a maintenance page (either main or report)
    const maintenanceTableBody = document.getElementById('maintenanceTableBody');
    const maintenanceReportBody = document.getElementById('maintenanceReportBody');
    const maintenancePage = document.getElementById('page-maintenance');
    const maintenanceReportPage = document.getElementById('page-maintenance-report');
    
    const isMaintenancePage = maintenanceTableBody || maintenancePage || maintenanceReportBody || maintenanceReportPage;
    
    if (isMaintenancePage) {
        // Populate vehicle dropdowns for both pages
        populateVehicleDropdowns();
        
        // Load maintenance data for main page if present
        if (maintenanceTableBody || maintenancePage) {
            loadMaintenanceTable();
        }
        
        // Load maintenance data for report page if present
        if (maintenanceReportBody || maintenanceReportPage) {
            fetchMaintenanceData();
        }

        // Handle custom date range visibility for report
        const dateRangeSelect = document.getElementById('dateRange');
        const customDateRange = document.getElementById('customDateRange');
        if (dateRangeSelect && customDateRange) {
            dateRangeSelect.addEventListener('change', function() {
                if (this.value === 'custom') {
                    customDateRange.style.display = 'flex';
                } else {
                    customDateRange.style.display = 'none';
                }
            });
        }

        // --- Modal functionality for adding maintenance ---
        const modal = document.getElementById("addMaintenanceModal");
        const btn = document.getElementById("addMaintenanceBtn");
        const span = document.getElementsByClassName("close-button")[0];
        const addMaintenanceForm = document.getElementById("addMaintenanceForm");

        // When the user clicks the button, open the modal
        if (btn && modal) {
            btn.onclick = function() {
                modal.style.display = "block";
            }
        }

        // When the user clicks on <span> (x), close the modal
        if (span && modal) {
            span.onclick = function() {
                modal.style.display = "none";
                resetFormErrors(); // Clear errors when closing
            }
        }

        // When the user clicks anywhere outside of the modal, close it
        if (modal) {
            window.onclick = function(event) {
                if (event.target == modal) {
                    modal.style.display = "none";
                    resetFormErrors(); // Clear errors when closing
                }
            }
        }

        // Handle form submission for adding maintenance
        if (addMaintenanceForm && modal) {
            // Ensure handler is bound only once
            if (!addMaintenanceForm.hasAttribute('data-submit-handler-bound')) {
                addMaintenanceForm.setAttribute('data-submit-handler-bound', 'true');
                addMaintenanceForm.addEventListener('submit', function(event) {
                    event.preventDefault(); // Prevent default form submission

                    const formData = new FormData(addMaintenanceForm);
                    
                    // Clear previous errors
                    resetFormErrors();

                    fetch('pages/add_maintenance.php', {
                        method: 'POST',
                        body: formData // Use FormData directly for POST requests
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(result => {
                        if (result.success) {
                            alert(result.message);
                            modal.style.display = "none"; // Close modal on success
                            addMaintenanceForm.reset(); // Reset form fields
                            
                            // Refresh both tables if they exist
                            if (maintenanceTableBody || maintenancePage) {
                                loadMaintenanceTable();
                            }
                            if (maintenanceReportBody || maintenanceReportPage) {
                                fetchMaintenanceData();
                            }
                        } else {
                            // Display validation errors
                            if (result.errors) {
                                for (const key in result.errors) {
                                    const capitalizedKey = key.charAt(0).toUpperCase() + key.slice(1);
                                    const errorElement = document.getElementById(`${key}Error`);
                                    if (errorElement) {
                                        errorElement.textContent = result.errors[key];
                                    } else {
                                        console.warn(`Error element for key "${key}" not found.`);
                                    }
                                }
                            }
                            if (result.message) {
                                alert("فشل في إضافة الصيانة: " + result.message);
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error adding maintenance:', error);
                        alert('حدث خطأ أثناء إضافة الصيانة. يرجى المحاولة مرة أخرى.');
                    });
                });
            }
        }

        // Handle form submission for editing maintenance
        const editMaintenanceForm = document.getElementById("editMaintenanceForm");
        if (editMaintenanceForm) {
            editMaintenanceForm.addEventListener('submit', function(event) {
                event.preventDefault();
                
                const formData = new FormData(editMaintenanceForm);
                
                // Clear previous errors
                resetFormErrors();

                fetch('pages/update_maintenance.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(result => {
                    if (result.success) {
                        alert(result.message);
                        closeEditMaintenanceModal();
                        
                        // Refresh both tables if they exist
                        if (maintenanceTableBody || maintenancePage) {
                            loadMaintenanceTable();
                        }
                        if (maintenanceReportBody || maintenanceReportPage) {
                            fetchMaintenanceData();
                        }
                    } else {
                        // Display validation errors
                        if (result.errors) {
                            for (const key in result.errors) {
                                const capitalizedKey = key.charAt(0).toUpperCase() + key.slice(1);
                                const errorElement = document.getElementById(`edit${capitalizedKey}Error`);
                                if (errorElement) {
                                    errorElement.textContent = result.errors[key];
                                } else {
                                    console.warn(`Edit error element for key "${key}" not found.`);
                                }
                            }
                        }
                        if (result.message) {
                            alert("فشل في تحديث الصيانة: " + result.message);
                        }
                    }
                })
                .catch(error => {
                    console.error('Error updating maintenance:', error);
                    alert('حدث خطأ أثناء تحديث الصيانة. يرجى المحاولة مرة أخرى.');
                });
            });
        }
    }
});

function resetFormErrors() {
    const errorElements = document.querySelectorAll('.error-message');
    errorElements.forEach(el => el.textContent = '');
}

function populateVehicleDropdowns() {
    // Only populate if relevant elements exist
    const filterVehicleSelect = document.getElementById('filterVehicle');
    const modalVehiclePlateSelect = document.getElementById('modalVehiclePlate');
    
    if (!filterVehicleSelect && !modalVehiclePlateSelect) {
        console.log('No vehicle dropdown elements found, skipping population');
        return;
    }
    
    fetch('pages/get_vehicle_plates.php')
        .then(response => response.json())
        .then(vehicles => {
            // Populate filter dropdown if it exists
            if (filterVehicleSelect) {
                // Clear existing options except 'all'
                filterVehicleSelect.innerHTML = '<option value="all">جميع الآليات</option>';
                vehicles.forEach(vehicle => {
                    const option = document.createElement('option');
                    option.value = vehicle.id;
                    option.textContent = vehicle.plate_number;
                    filterVehicleSelect.appendChild(option);
                });
            }

            // Populate modal select element if it exists
            if (modalVehiclePlateSelect) {
                // Clear existing options except the default one
                modalVehiclePlateSelect.innerHTML = '<option value="">-- اختر الآلية --</option>';
                vehicles.forEach(vehicle => {
                    const option = document.createElement('option');
                    option.value = vehicle.id;
                    option.textContent = vehicle.plate_number;
                    modalVehiclePlateSelect.appendChild(option);
                });
            }
        })
        .catch(error => {
            console.error('Error populating vehicle dropdowns:', error);
            if (filterVehicleSelect || modalVehiclePlateSelect) {
                alert('فشل في تحميل قائمة الآليات. يرجى المحاولة لاحقاً.');
            }
        });
}


function fetchMaintenanceData(filters = {}) {
    let url = 'pages/get_maintenance_data.php';
    const params = new URLSearchParams();

    // Add filters to URL parameters
    if (filters.vehiclePlate && filters.vehiclePlate !== 'all') {
        params.append('vehicle_id', filters.vehiclePlate);
    }
    if (filters.maintenanceType && filters.maintenanceType !== 'all') {
        params.append('maintenance_type', filters.maintenanceType);
    }

    let startDate = '';
    let endDate = '';

    if (filters.dateRange === 'today') {
        startDate = new Date().toISOString().split('T')[0];
        endDate = startDate;
    } else if (filters.dateRange === 'this_week') {
        const today = new Date();
        // Set to start of the week (Sunday)
        const firstDayOfWeek = new Date(today.setDate(today.getDate() - today.getDay()));
        startDate = firstDayOfWeek.toISOString().split('T')[0];
        // Set to end of the week (Saturday)
        const lastDayOfWeek = new Date(today.setDate(today.getDate() + (6 - today.getDay())));
        endDate = lastDayOfWeek.toISOString().split('T')[0];
    } else if (filters.dateRange === 'this_month') {
        const today = new Date();
        startDate = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().split('T')[0];
        endDate = new Date(today.getFullYear(), today.getMonth() + 1, 0).toISOString().split('T')[0];
    } else if (filters.dateRange === 'custom') {
        startDate = filters.startDate || '';
        endDate = filters.endDate || '';
    }

    if (startDate) {
        params.append('start_date', startDate);
    }
    if (endDate) {
        params.append('end_date', endDate);
    }

    url = url + '?' + params.toString();

    console.log("Fetching data from:", url); // Debugging log

    fetch(url)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log("Received data:", data); // Debugging log
            if (data.errors && Object.keys(data.errors).length > 0) {
                console.error("Error fetching data:", data.errors);
                // Display errors to the user if necessary
                alert("حدث خطأ أثناء جلب البيانات. يرجى التحقق من سجلات المطور.");
            } else {
                renderTable(data.data);
            }
        })
        .catch(error => {
            console.error("Fetch error:", error);
            alert("فشل الاتصال بالخادم. يرجى المحاولة مرة أخرى لاحقًا.");
        });
}

function renderTable(data) {
    const tableBody = document.getElementById('maintenanceReportBody');
    if (!tableBody) {
        console.error("Table body element not found.");
        return;
    }
    tableBody.innerHTML = ''; // Clear existing rows

    if (data.length === 0) {
        tableBody.innerHTML = '<tr><td colspan="6">لا توجد بيانات صيانة مطابقة للمعايير المحددة.</td></tr>';
        return;
    }

    data.forEach(item => {
        const row = `<tr>
            <td>${item.maintenance_date}</td>
            <td>${item.vehicle_plate}</td>
            <td>${item.vehicle_type}</td>
            <td>${item.maintenance_type}</td>
            <td>${item.description}</td>
            <td>${item.cost}</td>
        </tr>`;
        tableBody.innerHTML += row;
    });
}

function applyMaintenanceReportFilter() {
    // Check if filter elements exist before trying to use them
    const filterVehicleEl = document.getElementById('filterVehicle');
    const filterMaintenanceTypeEl = document.getElementById('filterMaintenanceType');
    const dateRangeEl = document.getElementById('dateRange');
    const startDateEl = document.getElementById('startDate');
    const endDateEl = document.getElementById('endDate');
    
    if (!filterVehicleEl || !filterMaintenanceTypeEl || !dateRangeEl) {
        console.warn('Maintenance filter elements not found, skipping filter application');
        return;
    }
    
    const vehiclePlate = filterVehicleEl.value;
    const maintenanceType = filterMaintenanceTypeEl.value;
    const dateRange = dateRangeEl.value;
    const startDateInput = startDateEl ? startDateEl.value : '';
    const endDateInput = endDateEl ? endDateEl.value : '';

    const filters = {
        vehiclePlate: vehiclePlate,
        maintenanceType: maintenanceType,
        dateRange: dateRange,
        startDate: startDateInput,
        endDate: endDateInput
    };

    fetchMaintenanceData(filters);
}

function resetMaintenanceReportFilter() {
    // Check if filter elements exist before trying to reset them
    const filterVehicleEl = document.getElementById('filterVehicle');
    const filterMaintenanceTypeEl = document.getElementById('filterMaintenanceType');
    const dateRangeEl = document.getElementById('dateRange');
    const startDateEl = document.getElementById('startDate');
    const endDateEl = document.getElementById('endDate');
    const customDateRangeEl = document.getElementById('customDateRange');
    
    if (filterVehicleEl) filterVehicleEl.value = 'all';
    if (filterMaintenanceTypeEl) filterMaintenanceTypeEl.value = 'all';
    if (dateRangeEl) dateRangeEl.value = 'this_month';
    if (startDateEl) startDateEl.value = '';
    if (endDateEl) endDateEl.value = '';
    if (customDateRangeEl) customDateRangeEl.style.display = 'none';

    // Only fetch data if we're on a maintenance report page
    if (document.getElementById('maintenanceReportBody')) {
        fetchMaintenanceData();
    }
}

// The sortTable function is kept from the original HTML, but client-side sorting
// might need adjustments if the data is fetched dynamically and the table is re-rendered.
// For now, we rely on the backend sorting provided by pages/get_maintenance_data.php.
function loadMaintenanceTable() {
    const tableBody = document.getElementById('maintenanceTableBody');
    if (!tableBody) {
        console.error("Main maintenance table body element not found.");
        return;
    }

    fetch('pages/get_maintenance_data.php')
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success && data.data) {
                renderMainTable(data.data);
            } else {
                console.error("Error fetching maintenance data for main table:", data);
                tableBody.innerHTML = '<tr><td colspan="6">فشل في تحميل بيانات الصيانة</td></tr>';
            }
        })
        .catch(error => {
            console.error("Fetch error for main table:", error);
            tableBody.innerHTML = '<tr><td colspan="6">فشل في الاتصال بالخادم</td></tr>';
        });
}

function renderMainTable(data) {
    const tableBody = document.getElementById('maintenanceTableBody');
    if (!tableBody) {
        console.error("Main maintenance table body element not found.");
        return;
    }
    tableBody.innerHTML = ''; // Clear existing rows

    if (data.length === 0) {
        tableBody.innerHTML = '<tr><td colspan="6">لا توجد أحداث صيانة حالياً</td></tr>';
        return;
    }

    data.forEach(item => {
        const row = `<tr>
            <td>${item.vehicle_type}</td>
            <td>${item.vehicle_plate}</td>
            <td>${item.maintenance_type}</td>
            <td>${item.description}</td>
            <td>${item.cost} ر.س</td>
            <td>
                <button class="btn-icon btn-edit" onclick="editMaintenance(${item.id})">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn-icon btn-delete" onclick="deleteMaintenance(${item.id})">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        </tr>`;
        tableBody.innerHTML += row;
    });
}

function editMaintenance(id) {
    // Fetch maintenance record data
    fetch(`pages/update_maintenance.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data) {
                // Populate the edit form with existing data
                const modal = document.getElementById('editMaintenanceModal');
                if (modal) {
                    // Fill form fields
                    document.getElementById('editMaintenanceId').value = id;
                    document.getElementById('editMaintenanceDateCurrent').textContent = data.data.maintenance_date;
                    document.getElementById('editMaintenanceTypeCurrent').textContent = data.data.maintenance_type;
                    document.getElementById('editMaintenanceDescriptionCurrent').textContent = data.data.description;
                    document.getElementById('editMaintenanceCostCurrent').textContent = data.data.cost;
                    
                    // Populate vehicle dropdown
                    populateEditVehicleDropdown();
                    
                    // Set form values
                    document.getElementById('editVehiclePlate').value = data.data.vehicle_id;
                    document.getElementById('editMaintenanceDate').value = data.data.maintenance_date;
                    
                    // Set maintenance type
                    const typeSelect = document.getElementById('editMaintenanceType');
                    if (typeSelect) {
                        typeSelect.value = data.data.maintenance_type;
                    }
                    
                    document.getElementById('editMaintenanceDescription').value = data.data.description;
                    document.getElementById('editMaintenanceCost').value = data.data.cost;
                    
                    // Open modal
                    modal.style.display = 'block';
                }
            } else {
                alert('فشل في تحميل بيانات الصيانة للتعديل');
            }
        })
        .catch(error => {
            console.error('Error fetching maintenance data:', error);
            alert('حدث خطأ أثناء تحميل بيانات الصيانة');
        });
}

function populateEditVehicleDropdown() {
    fetch('pages/get_vehicle_plates.php')
        .then(response => response.json())
        .then(vehicles => {
            const select = document.getElementById('editVehiclePlate');
            if (select) {
                // Clear existing options except the default one
                select.innerHTML = '<option value="">-- اختر الآلية --</option>';
                vehicles.forEach(vehicle => {
                    const option = document.createElement('option');
                    option.value = vehicle.id;
                    option.textContent = vehicle.plate_number;
                    select.appendChild(option);
                });
            }
        })
        .catch(error => {
            console.error('Error populating edit vehicle dropdown:', error);
        });
}

function closeEditMaintenanceModal() {
    const modal = document.getElementById('editMaintenanceModal');
    if (modal) {
        modal.style.display = 'none';
        // Reset form if it exists
        const form = document.getElementById('editMaintenanceForm');
        if (form) {
            form.reset();
        }
        // Clear readonly fields
        document.getElementById('editMaintenanceDateCurrent').textContent = '[التاريخ الحالي]';
        document.getElementById('editMaintenanceTypeCurrent').textContent = '[النوع الحالي]';
        document.getElementById('editMaintenanceDescriptionCurrent').textContent = '[التفاصيل الحالية]';
        document.getElementById('editMaintenanceCostCurrent').textContent = '[التكلفة الحالية]';
    }
}

function deleteMaintenance(id) {
    if (confirm('هل أنت متأكد من رغبتك في حذف هذا حدث الصيانة؟ هذا الإجراء لا يمكن التراجع عنه.')) {
        const formData = new FormData();
        formData.append('maintenance_id', id);
        
        fetch('pages/delete_maintenance.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                alert(result.message);
                // Refresh the tables
                if (document.getElementById('maintenanceTableBody') || document.getElementById('page-maintenance')) {
                    loadMaintenanceTable();
                }
                if (document.getElementById('maintenanceReportBody') || document.getElementById('page-maintenance-report')) {
                    fetchMaintenanceData();
                }
            } else {
                alert('فشل في حذف حدث الصيانة: ' + result.message);
            }
        })
        .catch(error => {
            console.error('Error deleting maintenance:', error);
            alert('حدث خطأ أثناء حذف حدث الصيانة');
        });
    }
}

function sortTable(columnIndex) {
    console.log(`Sorting by column ${columnIndex}. Note: Backend provides sorting, client-side sorting might need re-implementation if dynamic data changes.`);
    // If client-side sorting is strictly required, you would need to:
    // 1. Store the fetched data in a global variable.
    // 2. Re-sort that data based on columnIndex and direction.
    // 3. Call renderTable with the re-sorted data.
    // For now, we'll assume backend sorting is sufficient.
}
