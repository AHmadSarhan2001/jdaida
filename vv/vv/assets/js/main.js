// Main JavaScript for Dashboard Navigation and Functionality

document.addEventListener('DOMContentLoaded', function() {
    // Initialize the dashboard
    initializeDashboard();
    
    // Set up navigation
    setupNavigation();
    
    // Update date and time
    updateDateTime();
    setInterval(updateDateTime, 1000);
    
    // Initialize refresh functionality
    setupRefreshButton();
});

// Initialize Dashboard
function initializeDashboard() {
    console.log('Dashboard initialized');
    
    // Show the default page (dashboard)
    showPage('dashboard');
    
    // Set active navigation
    setActiveNavigation('dashboard');

    // Populate dashboard lists
    populateLatestWarehouseItems();
    populateLatestMaintenanceEvents();
}

function setupNavigation() {
    // Handle main navigation links
    const navLinks = document.querySelectorAll('.nav-link:not([data-subpage])');
    
    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const page = this.getAttribute('data-page');
            
            if (page) {
                // Close all dropdowns first
                closeAllDropdowns();
                
                // Remove active class from all main links
                navLinks.forEach(l => l.classList.remove('active'));
                
                // Add active class to clicked link
                this.classList.add('active');
                
                // Show the new page content
                showPage(page);
                
                // Update page title
                updatePageTitle(page);
            }
        });
    });
    
    // Handle dropdown toggle
    const dropdownToggles = document.querySelectorAll('.dropdown-toggle');
    dropdownToggles.forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const dropdown = this.closest('.dropdown');
            const isOpen = dropdown.classList.contains('open');
            
            // Close all other dropdowns
            closeAllDropdowns();
            
            if (!isOpen) {
                // Open this dropdown
                dropdown.classList.add('open');
                
                // Show gas station page if not already visible
                const page = this.getAttribute('data-page');
                if (page === 'gas-station') {
                    showPage('gas-station');
                    setActiveNavigation('gas-station');
                }
                
                // Add active class to toggle
                this.classList.add('active');
            } else {
                // Close this dropdown
                dropdown.classList.remove('open');
                
                // Remove active class only if no subpage is active
                const activeSubpage = dropdown.querySelector('.nav-link[data-subpage].active');
                if (!activeSubpage) {
                    this.classList.remove('active');
                }
            }
        });
    });
    
    // Handle dropdown subpage links
    const subpageLinks = document.querySelectorAll('.nav-link[data-subpage]');
    subpageLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const subpage = this.getAttribute('data-subpage');
            const dropdown = this.closest('.dropdown');
            
            if (subpage && dropdown) {
                // Keep dropdown open
                dropdown.classList.add('open');
                
                // Mark parent as active
                const parentToggle = dropdown.querySelector('.dropdown-toggle');
                if (parentToggle) {
                    parentToggle.classList.add('active');
                }
                
                // Remove active class from other subpages
                const otherSubpages = dropdown.querySelectorAll('.nav-link[data-subpage]');
                otherSubpages.forEach(sp => sp.classList.remove('active'));
                
                // Add active class to clicked subpage
                this.classList.add('active');
                
                // Show gas station page
                showPage('gas-station');
                
                // Switch to the specific tab
                if (window.openGasStationTab) {
                    // Simulate tab click by finding the corresponding tab button
                    const tabButton = document.querySelector(`.tab-link[onclick*="\'${subpage}\'"]`);
                    if (tabButton) {
                        const tabEvent = new Event('click', { bubbles: true });
                        tabButton.dispatchEvent(tabEvent);
                    }
                }
                
                // Update page title for subpage
                updatePageTitle(`gas-station-${subpage}`);
            }
        });
    });
    
    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.dropdown')) {
            closeAllDropdowns();
        }
    });
}

function closeAllDropdowns() {
    const dropdowns = document.querySelectorAll('.dropdown');
    dropdowns.forEach(dropdown => {
        dropdown.classList.remove('open');
    });
    
    // Remove active class from all subpage links
    const subpageLinks = document.querySelectorAll('.nav-link[data-subpage].active');
    subpageLinks.forEach(link => link.classList.remove('active'));
}

// Show Page Content
function showPage(page) {
    const pageWrappers = document.querySelectorAll('.page-wrapper');
    pageWrappers.forEach(wrapper => {
        wrapper.style.display = 'none';
    });

    const activePage = document.getElementById(`page-${page}`);
    if (activePage) {
        activePage.style.display = 'block';
    }
}

// Set Active Navigation
function setActiveNavigation(page) {
    const navLinks = document.querySelectorAll('.nav-link');
    navLinks.forEach(link => {
        link.classList.remove('active');
        if (link.getAttribute('data-page') === page) {
            link.classList.add('active');
        }
    });
}

function updatePageTitle(page) {
    const pageTitle = document.querySelector('.page-title');
    if (!pageTitle) return;
    
    const titles = {
        'dashboard': 'لوحة التحكم',
        'warehouse': 'المستودع',
        'maintenance': 'الصيانة',
        'vehicles': 'الآليات',
        'gas-report': 'تقرير الكازية',
        'maintenance-report': 'تقرير الصيانة',
        'report': 'تقرير الآليات',
        'warehouse-report': 'تقرير المستودع'
    };
    
    // Handle gas station subpages
    if (page.startsWith('gas-station-')) {
        const subpage = page.replace('gas-station-', '');
        const subTitles = {
            'fueling': 'الكازية - التعبئة اليومية',
            'tanks': 'الكازية - إدارة الخزانات',
            'vehicles': 'الكازية - إدارة الآليات',
            'reports': 'الكازية - التقارير والإحصائيات'
        };
        pageTitle.textContent = subTitles[subpage] || 'الكازية';
        return;
    }
    
    // Handle main pages
    if (titles[page]) {
        pageTitle.textContent = titles[page];
    }
    
    // Default for gas-station main page
    if (page === 'gas-station') {
        pageTitle.textContent = 'الكازية';
    }
}

// Update Date and Time
function updateDateTime() {
    const dateTimeElement = document.getElementById('currentDateTime');
    if (dateTimeElement) {
        const now = new Date();
        const options = {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: false
        };
        
        const arabicDate = now.toLocaleDateString('ar-SA', options);
        dateTimeElement.textContent = arabicDate;
    }
}

// Setup Refresh Button
function setupRefreshButton() {
    const refreshBtn = document.querySelector('.btn-refresh');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', refreshData);
    }
}

// Refresh Data Function
function refreshData() {
    const refreshBtn = document.querySelector('.btn-refresh');
    const icon = refreshBtn.querySelector('i');
    
    // Add loading animation
    icon.classList.add('fa-spin');
    refreshBtn.disabled = true;
    
    // Fetch data from the API
    fetch('pages/get_dashboard_data.php')
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            // Update dashboard cards with new data
            updateDashboardCards(data);
            
            // Show success message
            showNotification('تم تحديث البيانات بنجاح', 'success');
        })
        .catch(error => {
            console.error('Error fetching dashboard data:', error);
            showNotification('فشل تحديث البيانات', 'error');
        })
        .finally(() => {
            // Remove loading animation and re-enable button regardless of success or failure
            icon.classList.remove('fa-spin');
            refreshBtn.disabled = false;
        });
}

// Update Dashboard Cards with fetched data
function updateDashboardCards(data) {
    // Update dashboard cards
    const cardDataMap = {
        '.card:nth-child(1) .card-number': 'daily_warehouse_out',
        '.card:nth-child(2) .card-number': 'daily_maintenance', 
        '.card:nth-child(3) .card-number': 'daily_diesel',
        '.card:nth-child(4) .card-number': 'daily_gasoline'
    };

    for (const selector in cardDataMap) {
        const element = document.querySelector(selector);
        const dataKey = cardDataMap[selector];
        if (element && data.hasOwnProperty(dataKey)) {
            element.textContent = Number(data[dataKey]).toLocaleString('ar-SA');
        } else if (element) {
            element.textContent = '0';
        }
    }

    // Update warehouse items table
    if (data.last_warehouse_items && data.last_warehouse_items.length > 0) {
        updateWarehouseTable(data.last_warehouse_items);
    } else {
        updateWarehouseTable([]);
    }

    // Update maintenance events table
    if (data.last_maintenance_events && data.last_maintenance_events.length > 0) {
        updateMaintenanceTable(data.last_maintenance_events);
    } else {
        updateMaintenanceTable([]);
    }

    // Update fuel fillings table
    if (data.last_fuel_fillings && data.last_fuel_fillings.length > 0) {
        updateFuelFillingsTable(data.last_fuel_fillings);
    } else {
        updateFuelFillingsTable([]);
    }
}

// Update Warehouse Items Table
function updateWarehouseTable(items) {
    const tbody = document.querySelector('.dashboard-tables .table-container:nth-child(1) tbody');
    if (!tbody) return;

    if (items.length > 0) {
        tbody.innerHTML = items.map(item => `
            <tr>
                <td>${item.item_name || ''}</td>
                <td>${item.quantity || ''}</td>
                <td>${new Date(item.transaction_date).toISOString().split('T')[0]}</td>
            </tr>
        `).join('');
    } else {
        tbody.innerHTML = '<tr><td colspan="3">لا توجد سجلات حالياً</td></tr>';
    }
}

// Update Maintenance Events Table
function updateMaintenanceTable(events) {
    const tbody = document.querySelector('.dashboard-tables .table-container:nth-child(2) tbody');
    if (!tbody) return;

    if (events.length > 0) {
        tbody.innerHTML = events.map(event => `
            <tr>
                <td>${event.vehicle_name || ''}</td>
                <td>${event.maintenance_type || ''}</td>
                <td>${event.maintenance_date || ''}</td>
            </tr>
        `).join('');
    } else {
        tbody.innerHTML = '<tr><td colspan="3">لا توجد سجلات صيانة حالياً</td></tr>';
    }
}

// Update Fuel Fillings Table
function updateFuelFillingsTable(fillings) {
    const tbody = document.querySelector('.dashboard-tables .table-container:nth-child(3) tbody');
    if (!tbody) return;

    if (fillings.length > 0) {
        tbody.innerHTML = fillings.map(filling => `
            <tr>
                <td>${filling.plate_number || ''}</td>
                <td>${filling.fuel_type_ar || ''}</td>
                <td>${filling.liters_formatted || ''}</td>
                <td>${filling.total_cost_formatted || ''}</td>
                <td>${filling.fill_date_formatted || ''}</td>
            </tr>
        `).join('');
    } else {
        tbody.innerHTML = '<tr><td colspan="5">لا توجد سجلات تعبئة حالياً</td></tr>';
    }
}

// Show Notification
function showNotification(message, type = 'info') {
    // Remove existing notifications
    const existingNotifications = document.querySelectorAll('.notification');
    existingNotifications.forEach(notification => notification.remove());
    
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification alert alert-${type}`;
    notification.textContent = message;
    
    // Style the notification
    notification.style.position = 'fixed';
    notification.style.top = '20px';
    notification.style.left = '50%';
    notification.style.transform = 'translateX(-50%)';
    notification.style.zIndex = '9999';
    notification.style.minWidth = '300px';
    notification.style.textAlign = 'center';
    notification.style.borderRadius = '8px';
    notification.style.boxShadow = '0 4px 20px rgba(0, 0, 0, 0.15)';
    
    // Add to page
    document.body.appendChild(notification);
    
    // Remove after 3 seconds
    setTimeout(() => {
        notification.remove();
    }, 3000);
}

// Utility Functions
function formatNumber(number) {
    return number.toLocaleString('ar-SA');
}

function formatCurrency(amount) {
    return amount.toLocaleString('ar-SA') + ' ل.س';
}

function formatDate(date) {
    return date.toLocaleDateString('ar-SA', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
}

// Mobile Menu Toggle (for responsive design)
function toggleMobileMenu() {
    const sidebar = document.querySelector('.sidebar');
    sidebar.classList.toggle('open');
}

// Add mobile menu button functionality if needed
document.addEventListener('DOMContentLoaded', function() {
    // Check if we need to add mobile menu button
    if (window.innerWidth <= 768) {
        addMobileMenuButton();
    }
    
    // Listen for window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth <= 768) {
            addMobileMenuButton();
        } else {
            removeMobileMenuButton();
        }
    });
});

function addMobileMenuButton() {
    if (document.querySelector('.mobile-menu-btn')) return;
    
    const header = document.querySelector('.header-content');
    const menuBtn = document.createElement('button');
    menuBtn.className = 'mobile-menu-btn';
    menuBtn.innerHTML = '<i class="fas fa-bars"></i>';
    menuBtn.style.cssText = `
        background: #667eea;
        color: white;
        border: none;
        padding: 0.5rem;
        border-radius: 6px;
        cursor: pointer;
        font-size: 1.2rem;
        margin-left: 1rem;
    `;
    
    menuBtn.addEventListener('click', toggleMobileMenu);
    header.appendChild(menuBtn);
}

function removeMobileMenuButton() {
    const menuBtn = document.querySelector('.mobile-menu-btn');
    if (menuBtn) {
        menuBtn.remove();
    }
}

// Export functions for external use
window.dashboardFunctions = {
    updateDashboardCards,
    refreshData,
    showNotification,
    formatNumber,
    formatCurrency,
    formatDate
};

// ==================================================================
// Maintenance Page Specific Functions
// ==================================================================

// --- Modal Control ---
function openMaintenanceModal() {
    const modal = document.getElementById('addMaintenanceModal');
    if (modal) modal.style.display = 'flex';
}

function closeMaintenanceModal() {
    const modal = document.getElementById('addMaintenanceModal');
    if (modal) modal.style.display = 'none';
}

// --- Form Handling ---
function handleAddMaintenanceSubmit(e) {
    e.preventDefault();
    const vehicleCode = document.getElementById('vehicleCode').value;
    const vehicleOwner = document.getElementById('vehicleOwner').value;
    const repairDetails = document.getElementById('repairDetails').value;
    const warehouseSupplies = document.getElementById('warehouseSupplies').value;
    const purchasedSupplies = document.getElementById('purchasedSupplies').value;

    console.log('New Maintenance Event:', { vehicleCode, vehicleOwner, repairDetails, warehouseSupplies, purchasedSupplies });
    showNotification(`تمت إضافة حدث صيانة للآلية "${vehicleCode}" بنجاح (محاكاة)`, 'success');
    closeMaintenanceModal();
    this.reset();
}

// --- Table Actions ---
function editMaintenance(id) {
    console.log(`Editing maintenance event with ID: ${id}`);
    showNotification(`ميزة تعديل حدث الصيانة قيد التطوير (للرقم ${id})`, 'info');
}

function deleteMaintenance(id) {
    console.log(`Deleting maintenance event with ID: ${id}`);
    showNotification(`ميزة حذف حدث الصيانة قيد التطوير (للرقم ${id})`, 'info');
}


// ==================================================================
// Warehouse Page Specific Functions
// ==================================================================

// --- Modal Control ---
function openAddItemModal() {
    const modal = document.getElementById('addItemModal');
    if (modal) modal.style.display = 'flex';
}

function closeAddItemModal() {
    const modal = document.getElementById('addItemModal');
    if (modal) modal.style.display = 'none';
}

function openWithdrawModal() {
    const modal = document.getElementById('withdrawModal');
    if (modal) modal.style.display = 'flex';
}

function closeWithdrawModal() {
    const modal = document.getElementById('withdrawModal');
    if (modal) modal.style.display = 'none';
}

// --- Table Actions ---
function searchItems() {
    const input = document.getElementById('searchInput');
    const filter = input.value.toUpperCase();
    const table = document.getElementById('itemsTable');
    const tr = table.getElementsByTagName('tr');

    for (let i = 1; i < tr.length; i++) { // Start from 1 to skip header row
        const tdArray = tr[i].getElementsByTagName('td');
        let textValue = "";
        for (let j = 0; j < tdArray.length - 1; j++) { // Exclude actions column
            if (tdArray[j]) {
                textValue += tdArray[j].textContent || tdArray[j].innerText;
            }
        }
        if (textValue.toUpperCase().indexOf(filter) > -1) {
            tr[i].style.display = "";
        } else {
            tr[i].style.display = "none";
        }
    }
}

function editItem(itemId) {
    console.log('Opening edit modal for warehouse item ID:', itemId);
    
    const modal = document.getElementById('editItemModal');
    if (!modal) {
        console.error("Edit item modal not found!");
        showNotification('نافذة التعديل غير موجودة', 'error');
        return;
    }
    
    // Show the modal
    modal.style.display = 'flex';
    
    // Set the item ID
    const editItemId = document.getElementById('editItemId');
    if (editItemId) {
        editItemId.value = itemId;
    }
    
    // Fetch current item data
    fetch(`pages/get_item_details.php?item_id=${itemId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.statusText);
            }
            return response.json();
        })
        .then(data => {
            if (data.success && data.data) {
                const itemData = data.data;
                
                console.log('Loaded item data for edit:', itemData);
                
                // Store original values for validation
                window.itemOriginalValues = {
                    item_name: itemData.item_name || '',
                    quantity: itemData.quantity || 0,
                    unit_price: itemData.unit_price || 0
                };
                
                // Populate readonly display fields
                if (document.getElementById('display_item_name')) {
                    document.getElementById('display_item_name').textContent = itemData.item_name || 'غير محدد';
                }
                if (document.getElementById('display_quantity')) {
                    document.getElementById('display_quantity').textContent = itemData.quantity || 'غير محدد';
                }
                if (document.getElementById('display_unit_price')) {
                    document.getElementById('display_unit_price').textContent = itemData.unit_price || 'غير محدد';
                }
                
                // Populate editable input fields with current values
                if (document.getElementById('edit_item_name')) {
                    document.getElementById('edit_item_name').value = itemData.item_name || '';
                }
                if (document.getElementById('edit_quantity')) {
                    document.getElementById('edit_quantity').value = itemData.quantity || '';
                }
                if (document.getElementById('edit_unit_price')) {
                    document.getElementById('edit_unit_price').value = itemData.unit_price || '';
                }
                
                // Initially disable the submit button since no changes have been made
                const submitBtn = document.getElementById('updateItemBtn');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.textContent = 'يرجى تعديل احد البنود قبل الحفظ';
                }
                
                // Setup real-time validation for the edit form
                setupItemEditValidation();
                
                showNotification('تم تحميل بيانات القلم للتعديل. قم بتعديل الحقول المطلوبة', 'success');
            } else {
                showNotification('فشل في تحميل بيانات القلم: ' + (data.message || 'خطأ غير معروف'), 'error');
                closeEditItemModal();
            }
        })
        .catch((error) => {
            console.error('Error fetching item data for edit:', error);
            showNotification('حدث خطأ أثناء جلب بيانات القلم: ' + error.message, 'error');
            closeEditItemModal();
        });
}

function openDeleteItemModal(itemId) {
    console.log('Opening delete confirmation for item ID:', itemId);
    window.itemToDelete = itemId;
    
    const modal = document.getElementById('deleteItemModal');
    if (modal) {
        // Set the item ID display
        const itemIdDisplay = document.getElementById('itemIdDisplay');
        if (itemIdDisplay) {
            itemIdDisplay.textContent = itemId;
        }
        
        // Update the modal content with item information
        const itemInfo = document.getElementById('itemInfo');
        if (itemInfo) {
            itemInfo.textContent = `القلم رقم ${itemId}`;
            
            // Try to fetch basic item info to display in confirmation
            fetch(`pages/get_item_details.php?item_id=${itemId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data) {
                        const itemName = data.data.item_name || 'غير محدد';
                        itemInfo.textContent = `القلم: ${itemName}`;
                    }
                })
                .catch(error => {
                    console.error('Error fetching item info for delete confirmation:', error);
                    // Keep the default text if fetch fails
                });
        }
        
        modal.style.display = 'flex';
    }
}

function closeDeleteItemModal() {
    const modal = document.getElementById('deleteItemModal');
    if (modal) {
        modal.style.display = 'none';
    }
    window.itemToDelete = null;
}

function deleteItem(itemId) {
    if (!itemId) {
        showNotification('معرف القلم غير صحيح', 'error');
        return;
    }
    
    const deleteData = {
        id: parseInt(itemId)
    };
    
    console.log('Deleting warehouse item with ID:', itemId);
    
    fetch('pages/delete_warehouse_item.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(deleteData)
    })
    .then(response => {
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            return response.text().then(text => {
                console.error('Server returned non-JSON response:', text.substring(0, 200));
                throw new Error('خطأ في الخادم: تم إرجاع رد غير صالح');
            });
        }
        return response.json();
    })
    .then(result => {
        if (result.success) {
            showNotification('تم حذف القلم بنجاح', 'success');
            
            // Remove the row from the table
            const row = document.querySelector(`tr:has(button[onclick="deleteItem(${itemId})"])`);
            if (row) {
                row.remove();
            } else {
                // Fallback: find by edit button
                const editButton = document.querySelector(`button[onclick="editItem(${itemId})"]`);
                if (editButton) {
                    const tableRow = editButton.closest('tr');
                    if (tableRow) {
                        tableRow.remove();
                    }
                }
            }
            
            closeDeleteItemModal();
        } else {
            showNotification(`فشل في حذف القلم: ${result.message || 'خطأ غير معروف'}`, 'error');
        }
    })
    .catch(error => {
        console.error('Error deleting item:', error);
        showNotification('حدث خطأ أثناء حذف القلم: ' + error.message, 'error');
        closeDeleteItemModal();
    });
}

// Setup real-time validation for item edit form
function setupItemEditValidation() {
    const editFields = ['edit_item_name', 'edit_quantity', 'edit_unit_price'];
    
    editFields.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field) {
            field.addEventListener('input', function() {
                updateItemSubmitButtonState();
            });
        }
    });
}

// Check if any changes have been made for item edit and update submit button
function updateItemSubmitButtonState() {
    if (!window.itemOriginalValues) {
        return;
    }
    
    const currentValues = {
        item_name: document.getElementById('edit_item_name')?.value || '',
        quantity: parseInt(document.getElementById('edit_quantity')?.value || 0),
        unit_price: parseFloat(document.getElementById('edit_unit_price')?.value || 0)
    };
    
    let hasChanges = false;
    for (let field in currentValues) {
        if (currentValues[field] !== window.itemOriginalValues[field]) {
            hasChanges = true;
            break;
        }
    }
    
    const submitBtn = document.getElementById('updateItemBtn');
    if (submitBtn) {
        if (true) {
            submitBtn.disabled = false;
            submitBtn.textContent = 'حفظ التعديلات';
        } else {
            submitBtn.disabled = true;
            submitBtn.textContent = 'يرجى تعديل احد البنود قبل الحفظ';
        }
    }
}

// Item edit form submission handler
function handleEditItemSubmit(event) {
    event.preventDefault();
    event.stopPropagation();
    
    // Check if any changes were made
    if (!itemHasChangesBeenMade()) {
        showNotification('يرجى تعديل احد البنود قبل الحفظ', 'warning');
        return;
    }
    
    const itemId = document.getElementById('editItemId').value;
    const submitBtn = document.getElementById('updateItemBtn');
    
    // Disable button and show loading
    submitBtn.disabled = true;
    submitBtn.textContent = 'جاري الحفظ...';
    
    // Collect form data
    const formData = new FormData();
    formData.append('item_id', itemId);
    formData.append('item_name', document.getElementById('edit_item_name').value.trim());
    formData.append('quantity', document.getElementById('edit_quantity').value);
    formData.append('unit_price', document.getElementById('edit_unit_price').value);
    
    fetch('pages/update_warehouse_item.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            return response.text().then(text => {
                console.error('Server returned non-JSON response:', text.substring(0, 200));
                throw new Error('خطأ في الخادم: تم إرجاع رد غير صالح');
            });
        }
        return response.json();
    })
    .then(result => {
        if (result.success) {
            showNotification('تم تحديث بيانات القلم بنجاح', 'success');
            
            // Update the readonly display fields
            document.getElementById('display_item_name').textContent = result.data.item_name || 'غير محدد';
            document.getElementById('display_quantity').textContent = result.data.quantity || 'غير محدد';
            document.getElementById('display_unit_price').textContent = result.data.unit_price || 'غير محدد';
            
            // Update the original values to match the current form values
            window.itemOriginalValues = {
                item_name: result.data.item_name || '',
                quantity: parseInt(result.data.quantity || 0),
                unit_price: parseFloat(result.data.unit_price || 0)
            };
            
            // Update the table row with new data
            updateItemTableRow(itemId, result.data);
            
            closeEditItemModal();
        } else {
            showNotification(result.message || 'فشل في تحديث البيانات', 'error');
        }
    })
    .catch(error => {
        console.error('Error updating item:', error);
        showNotification('حدث خطأ أثناء تحديث القلم: ' + error.message, 'error');
    })
    .finally(() => {
        // Re-enable button
        submitBtn.disabled = false;
        updateItemSubmitButtonState();
    });
}

function itemHasChangesBeenMade() {
    if (!window.itemOriginalValues) {
        return false;
    }
    
    const currentValues = {
        item_name: document.getElementById('edit_item_name')?.value || '',
        quantity: parseInt(document.getElementById('edit_quantity')?.value || 0),
        unit_price: parseFloat(document.getElementById('edit_unit_price')?.value || 0)
    };
    
    for (let field in currentValues) {
        if (currentValues[field] !== window.itemOriginalValues[field]) {
            return true;
        }
    }
    return false;
}

// Update the specific row in the items table
function updateItemTableRow(itemId, itemData) {
    // Find the table row containing the edit button with this item ID
    const editButton = document.querySelector(`button[onclick="editItem(${itemId})"]`);
    if (!editButton) {
        console.warn(`No table row found for item ID: ${itemId}`);
        return;
    }
    
    const row = editButton.closest('tr');
    if (!row) {
        console.warn(`No parent row found for edit button of item ID: ${itemId}`);
        return;
    }
    
    // Update the cells: Name (0), Quantity (1), Unit Price (2), Actions (3)
    const cells = row.querySelectorAll('td');
    
    if (cells[0]) cells[0].textContent = itemData.item_name || '';
    if (cells[1]) cells[1].textContent = itemData.quantity || '';
    if (cells[2]) cells[2].textContent = itemData.unit_price || '';
    
    console.log(`Updated table row for item ID: ${itemId}`);
}

// Close edit item modal
function closeEditItemModal() {
    const modal = document.getElementById('editItemModal');
    if (modal) {
        modal.style.display = 'none';
    }
    
    // Reset form
    const form = document.getElementById('editItemForm');
    if (form) {
        form.reset();
    }
    
    // Clear original values
    window.itemOriginalValues = null;
    
    // Reset submit button
    const submitBtn = document.getElementById('updateItemBtn');
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'يرجى تعديل احد البنود قبل الحفظ';
    }
    
    // Clear display fields
    const displayFields = ['item_name', 'quantity', 'unit_price'];
    displayFields.forEach(field => {
        const displayElement = document.getElementById('display_' + field);
        if (displayElement) {
            displayElement.textContent = '-';
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    // Vehicle Add Form Handler
    const addVehicleForm = document.getElementById('addVehicleForm');
    if (addVehicleForm) {
        addVehicleForm.addEventListener('submit', handleAddVehicleSubmit);
    }

    // Vehicle Edit Form Handler (for main.php AJAX)
    const editVehicleForm = document.getElementById('editVehicleForm');
    if (editVehicleForm) {
        editVehicleForm.addEventListener('submit', handleEditVehicleSubmit);
    }

    // Maintenance Add Form Handler
    const addMaintenanceForm = document.getElementById('addMaintenanceForm');
    if (addMaintenanceForm) {
        addMaintenanceForm.addEventListener('submit', handleAddMaintenanceSubmit);
    }

    // Initialize item edit form handler when DOM loads
    const editItemForm = document.getElementById('editItemForm');
    if (editItemForm) {
        editItemForm.addEventListener('submit', handleEditItemSubmit);
    }
    
    // Setup delete confirmation for items
    const confirmDeleteItemBtn = document.getElementById('confirmDeleteItemBtn');
    if (confirmDeleteItemBtn) {
        confirmDeleteItemBtn.addEventListener('click', function() {
            if (window.itemToDelete !== null) {
                deleteItem(window.itemToDelete);
                closeDeleteItemModal();
            }
        });
    }
});

// Vehicle Add Form AJAX Handler
function handleAddVehicleSubmit(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const submitBtn = document.getElementById('addVehicleSubmit');
    const originalText = submitBtn.innerHTML;
    
    // Disable button and show loading
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري الحفظ...';
    
    fetch('pages/add_vehicle.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            closeVehicleModal();
            addVehicleToTable(data.data);
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error adding vehicle:', error);
        showNotification('حدث خطأ أثناء إضافة الآلية: ' + error.message, 'error');
    })
    .finally(() => {
        // Re-enable button
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
}

// Add new vehicle row to the vehicles table
function addVehicleToTable(vehicle) {
    const tableBody = document.getElementById('vehiclesTableBody');
    if (!tableBody) return;
    
    // Create new row HTML
    const newRow = `
        <tr>
            <td>${vehicle.make || ''}</td>
            <td>${vehicle.type || ''}</td>
            <td>${vehicle.plate_number || ''}</td>
            <td>${vehicle.recipient || ''}</td>
            <td>${vehicle.fuel_type === 'diesel' ? 'مازوت' : 'بنزين'}</td>
            <td>${number_format(vehicle.monthly_allocations || 0, 2)}</td>
            <td>${vehicle.chassis_number || ''}</td>
            <td>${vehicle.engine_number || ''}</td>
            <td>${vehicle.color || ''}</td>
            <td>${vehicle.notes || ''}</td>
            <td>
                <button class="btn-icon btn-edit" onclick="editVehicle(${vehicle.id})"><i class="fas fa-edit"></i></button>
                <button class="btn-icon btn-delete" onclick="openDeleteVehicleModal(${vehicle.id})"><i class="fas fa-trash"></i></button>
            </td>
        </tr>
    `;
    
    // Insert new row at the beginning
    tableBody.insertAdjacentHTML('afterbegin', newRow);
    
    // Update any empty table message
    const emptyRow = tableBody.querySelector('tr td[colspan="11"]');
    if (emptyRow) {
        emptyRow.parentElement.remove();
    }
}

// Vehicle Edit Form AJAX Handler (for main.php)
function handleEditVehicleSubmit(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const submitBtn = document.getElementById('updateVehicleBtn');
    const originalText = submitBtn.innerHTML;
    
    // Disable button and show loading
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري الحفظ...';
    
    fetch('main.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            closeeditVehicleModal();
            updateVehicleTableRow(data.data.id, data.data);
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error updating vehicle:', error);
        showNotification('حدث خطأ أثناء تحديث الآلية: ' + error.message, 'error');
    })
    .finally(() => {
        // Re-enable button
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
}

// Maintenance Add Form AJAX Handler
function handleAddMaintenanceSubmit(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const submitBtn = e.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    
    // Disable button and show loading
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري الحفظ...';
    }
    
    fetch('pages/add_maintenance.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            closeMaintenanceModal();
            addMaintenanceToTable(data.data);
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error adding maintenance:', error);
        showNotification('حدث خطأ أثناء إضافة الصيانة: ' + error.message, 'error');
    })
    .finally(() => {
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    });
}

// Add new maintenance row to the maintenance table
function addMaintenanceToTable(maintenance) {
    const tableBody = document.getElementById('maintenanceTableBody');
    if (!tableBody) return;
    
    // Get vehicle details for display
    const vehicleCode = maintenance.vehicle_id; // This would need to be fetched or stored
    const vehicleOwner = 'مالك الآلية'; // This would need to be fetched
    const materialsFromWarehouse = 'لا يوجد'; // This would need to be fetched
    const purchasedMaterials = 'لا يوجد'; // This would need to be fetched
    
    // Create new row HTML
    const newRow = `
        <tr>
            <td>${vehicleCode}</td>
            <td>${vehicleOwner}</td>
            <td>${maintenance.maintenance_type}</td>
            <td>${materialsFromWarehouse}</td>
            <td>${purchasedMaterials}</td>
            <td>
                <button class="btn-icon btn-edit" onclick="editMaintenance(${maintenance.id})"><i class="fas fa-edit"></i></button>
                <button class="btn-icon btn-delete" onclick="deleteMaintenance(${maintenance.id})"><i class="fas fa-trash"></i></button>
            </td>
        </tr>
    `;
    
    // Insert new row at the beginning
    tableBody.insertAdjacentHTML('afterbegin', newRow);
    
    // Update any empty table message
    const emptyRow = tableBody.querySelector('tr td[colspan="6"]');
    if (emptyRow) {
        emptyRow.parentElement.remove();
    }
}

// Warehouse Add Item Form AJAX Handler
function handleAddItemSubmit(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const submitBtn = e.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    
    // Disable button and show loading
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري الحفظ...';
    }
    
    fetch('pages/add_warehouse_item.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            closeAddItemModal();
            addItemToTable(data.data);
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error adding item:', error);
        showNotification('حدث خطأ أثناء إضافة القلم: ' + error.message, 'error');
    })
    .finally(() => {
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    });
}

// Add new item row to the items table
function addItemToTable(item) {
    const tableBody = document.getElementById('itemsTableBody');
    if (!tableBody) return;
    
    // Create new row HTML
    const newRow = `
        <tr>
            <td>${item.item_name || ''}</td>
            <td>${item.quantity || ''}</td>
            <td>${item.unit_price || ''}</td>
            <td>
                <button class="btn-icon btn-edit" onclick="editItem(${item.id})"><i class="fas fa-edit"></i></button>
                <button class="btn-icon btn-delete" onclick="deleteItem(${item.id})"><i class="fas fa-trash"></i></button>
            </td>
        </tr>
    `;
    
    // Insert new row at the beginning
    tableBody.insertAdjacentHTML('afterbegin', newRow);
    
    // Update any empty table message
    const emptyRow = tableBody.querySelector('tr td[colspan="4"]');
    if (emptyRow) {
        emptyRow.parentElement.remove();
    }
}

// Warehouse Withdraw Item Form AJAX Handler
function handleWithdrawSubmit(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const submitBtn = e.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    
    // Disable button and show loading
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري التخريج...';
    }
    
    fetch('pages/withdraw_warehouse_item.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            closeWithdrawModal();
            updateItemQuantityInTable(data.data.id, data.data.new_quantity);
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error withdrawing item:', error);
        showNotification('حدث خطأ أثناء التخريج: ' + error.message, 'error');
    })
    .finally(() => {
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    });
}

// Update item quantity in the table
function updateItemQuantityInTable(itemId, newQuantity) {
    const editButton = document.querySelector(`button[onclick="editItem(${itemId})"]`);
    if (editButton) {
        const row = editButton.closest('tr');
        if (row) {
            const cells = row.querySelectorAll('td');
            if (cells[1]) { // Quantity column is index 1
                cells[1].textContent = newQuantity;
            }
        }
    }
}

// Warehouse Add Invoice Form AJAX Handler
function handleAddInvoiceSubmit(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const submitBtn = e.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    
    // Disable button and show loading
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري الحفظ...';
    }
    
    fetch('pages/add_invoice.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            closeAddInvoiceModal();
            // Optionally update invoices list or show success
            if (window.updateInvoicesList) {
                window.updateInvoicesList(data.data);
            }
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error adding invoice:', error);
        showNotification('حدث خطأ أثناء إضافة الفاتورة: ' + error.message, 'error');
    })
    .finally(() => {
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    });
}

// Update invoices list after adding new invoice
function updateInvoicesList(invoice) {
    const invoicesList = document.getElementById('invoicesList');
    if (!invoicesList) return;
    
    // Remove empty message if present
    const emptyLi = invoicesList.querySelector('li:not([onclick])');
    if (emptyLi) {
        emptyLi.remove();
    }
    
    // Create new list item
    const newLi = document.createElement('li');
    newLi.setAttribute('onclick', `showInvoiceDetails(${invoice.id})`);
    newLi.innerHTML = `
        <span>رقم الفاتورة: ${invoice.invoice_number}</span>
        <span>التاريخ: ${formatDate(new Date(invoice.invoice_date))}</span>
        <span>الإجمالي: ${formatCurrency(invoice.total_amount)}</span>
    `;
    invoicesList.insertAdjacentElement('afterbegin', newLi);
}

// Global variable to store the item ID to be deleted
window.itemToDelete = null;

// Make item delete functions globally available
window.openDeleteItemModal = openDeleteItemModal;
window.closeDeleteItemModal = closeDeleteItemModal;
window.deleteItem = deleteItem;
window.editItem = editItem;
window.closeEditItemModal = closeEditItemModal;
window.updateItemSubmitButtonState = updateItemSubmitButtonState;
window.itemHasChangesBeenMade = itemHasChangesBeenMade;

// ==================================================================
// Warehouse Page - Invoice Functions
// ==================================================================

function openAddInvoiceModal() {
    const modal = document.getElementById('addInvoiceModal');
    if (modal) modal.style.display = 'flex';
}

function closeAddInvoiceModal() {
    const modal = document.getElementById('addInvoiceModal');
    if (modal) modal.style.display = 'none';
}

function openViewInvoicesPanel() {
    console.log('openViewInvoicesPanel called');
    const panel = document.getElementById('viewInvoicesPanel');
    if (panel) {
        panel.classList.add('open');
        console.log('View Invoices Panel opened');
    } else {
        console.error('View Invoices Panel not found!');
    }
}

function closeViewInvoicesPanel() {
    const panel = document.getElementById('viewInvoicesPanel');
    if (panel) panel.classList.remove('open');
}

function closeInvoiceDetailsModal() {
    const modal = document.getElementById('invoiceDetailsModal');
    if (modal) modal.style.display = 'none';
}

function addInvoiceItem() {
    const container = document.getElementById('invoiceItemsContainer');
    if (container.children.length >= 10) {
        showNotification('يمكن إضافة 10 أقلام كحد أقصى للفاتورة الواحدة', 'warning');
        return;
    }
    const newItem = document.createElement('div');
    newItem.className = 'invoice-item';
    newItem.innerHTML = `
        <input type="text" placeholder="نوع القلم" name="itemType[]" required>
        <input type="number" placeholder="العدد" name="itemCount[]" min="1" required>
        <input type="number" placeholder="القيمة" name="itemValue[]" min="0" step="0.01" required>
        <input type="text" placeholder="ملاحظات" name="itemNotes[]">
        <button type="button" class="btn-icon btn-delete" onclick="removeInvoiceItem(this)"><i class="fas fa-trash"></i></button>
    `;
    container.appendChild(newItem);
}

function removeInvoiceItem(button) {
    const item = button.closest('.invoice-item');
    const container = document.getElementById('invoiceItemsContainer');
    // Prevent removing the last item
    if (container.children.length > 1) {
        if (item) {
            item.remove();
        }
    } else {
        showNotification('يجب أن تحتوي الفاتورة على قلم واحد على الأقل', 'warning');
    }
}

function showInvoiceDetails(invoiceId) {
    console.log('showInvoiceDetails called with ID:', invoiceId);
    const modal = document.getElementById('invoiceDetailsModal');
    const title = document.getElementById('invoiceDetailsTitle');
    const body = document.getElementById('invoiceDetailsBody');

    body.innerHTML = '<p>جاري تحميل التفاصيل...</p>';
    modal.style.display = 'flex';

    console.log('Fetching invoice details from:', `pages/get_invoice_details.php?invoice_id=${invoiceId}`);
    fetch(`pages/get_invoice_details.php?invoice_id=${invoiceId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.error) {
                showNotification(data.error, 'error');
                body.innerHTML = `<p>خطأ: ${data.error}</p>`;
                return;
            }

            title.textContent = `تفاصيل الفاتورة: ${data.invoice_number}`;
            
            let contentHtml = `
                <div class="invoice-meta">
                    <span><strong>رقم الفاتورة:</strong> ${data.invoice_number}</span>
                    <span><strong>تاريخ الفاتورة:</strong> ${data.invoice_date}</span>
                    <span><strong>المبلغ الإجمالي:</strong> ${formatCurrency(data.total_amount)}</span>
                </div>
                <div class="table-container">
                    <table class="items-table">
                        <thead>
                            <tr>
                                <th>اسم القلم</th>
                                <th>الكمية</th>
                                <th>سعر الوحدة</th>
                                <th>الإجمالي</th>
                                <th>ملاحظات</th>
                            </tr>
                        </thead>
                        <tbody>`;
            
            data.items.forEach(item => {
                const itemTotal = item.quantity * item.unit_price;
                contentHtml += `<tr>
                                <td>${item.item_name}</td>
                                <td>${item.quantity}</td>
                                <td>${formatCurrency(item.unit_price)}</td>
                                <td>${formatCurrency(itemTotal)}</td>
                                <td>${item.notes || '-'}</td>
                              </tr>`;
            });

            contentHtml += `</tbody></table></div>`;
            body.innerHTML = contentHtml;
        })
        .catch(error => {
            console.error('Error fetching invoice details:', error);
            showNotification('حدث خطأ أثناء جلب تفاصيل الفاتورة', 'error');
            body.innerHTML = '<p>فشل تحميل البيانات. يرجى المحاولة مرة أخرى.</p>';
        });
}

function searchInvoices() {
    console.log('searchInvoices called');
    const input = document.getElementById('invoiceSearchInput');
    const searchTerm = input.value.trim();
    const ul = document.getElementById('invoicesList');

    if (searchTerm.length > 0) {
        console.log('Fetching search results from:', `main.php?action=search_invoices&search_term=${encodeURIComponent(searchTerm)}`);
        fetch(`main.php?action=search_invoices&search_term=${encodeURIComponent(searchTerm)}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                ul.innerHTML = ''; // Clear existing list items
                if (data.length > 0) {
                    data.forEach(invoice => {
                        const listItem = document.createElement('li');
                        listItem.setAttribute('onclick', `showInvoiceDetails(${invoice.id})`);
                        listItem.innerHTML = `
                            <span>رقم الفاتورة: ${invoice.invoice_number}</span>
                            <span>التاريخ: ${formatDate(new Date(invoice.invoice_date))}</span>
                            <span>الإجمالي: ${formatCurrency(invoice.total_amount)}</span>
                        `;
                        ul.appendChild(listItem);
                    });
                } else {
                    ul.innerHTML = '<li>لا توجد فواتير مطابقة</li>';
                }
            })
            .catch(error => {
                console.error('Error fetching filtered invoices:', error);
                showNotification('حدث خطأ أثناء البحث عن الفواتير', 'error');
                ul.innerHTML = '<li>فشل تحميل البيانات. يرجى المحاولة مرة أخرى.</li>';
            });
    } else {
        // If search term is empty, reload all invoices (or clear the list)
        // For now, I'll just clear the list and let the user refresh the page to see all.
        // A more robust solution would involve another AJAX call to get all invoices.
        // Or, if the initial list is always loaded, we could filter that.
        // For simplicity, let's just clear and show a message.
        ul.innerHTML = '<li>ادخل رقم الفاتورة أو اسم القلم للبحث</li>';
        // Alternatively, to re-fetch all invoices:
        // location.reload(); // This would refresh the entire page, not ideal for UX
        // Or, make another fetch to main.php without search_term to get all invoices
        console.log('Fetching all invoices (search term empty) from:', `main.php?action=search_invoices&search_term=`);
        fetch(`main.php?action=search_invoices&search_term=`) // Fetch all if search_term is empty
            .then(response => response.json())
            .then(data => {
                ul.innerHTML = '';
                if (data.length > 0) {
                    data.forEach(invoice => {
                        const listItem = document.createElement('li');
                        listItem.setAttribute('onclick', `showInvoiceDetails(${invoice.id})`);
                        listItem.innerHTML = `
                            <span>رقم الفاتورة: ${invoice.invoice_number}</span>
                            <span>التاريخ: ${formatDate(new Date(invoice.invoice_date))}</span>
                            <span>الإجمالي: ${formatCurrency(invoice.total_amount)}</span>
                        `;
                        ul.appendChild(listItem);
                    });
                } else {
                    ul.innerHTML = '<li>لا توجد فواتير حالياً</li>';
                }
            })
            .catch(error => {
                console.error('Error fetching all invoices:', error);
                showNotification('حدث خطأ أثناء جلب الفواتير', 'error');
                ul.innerHTML = '<li>فشل تحميل البيانات. يرجى المحاولة مرة أخرى.</li>';
            });
    }
}


// --- Form Handling ---
// --- Form Submission Handlers ---
function handleAddItemSubmit(e) {
    e.preventDefault();
    const itemName = document.getElementById('itemName').value;
    const itemType = document.getElementById('itemType').value;
    const itemQuantity = document.getElementById('itemQuantity').value;
    const quantityType = document.getElementById('quantityType').value;
    
    console.log('New Item:', { itemName, itemType, itemQuantity, quantityType });
    showNotification(`تمت إضافة "${itemName}" بنجاح (محاكاة)`, 'success');
    closeAddItemModal();
    this.reset();
}

function handleWithdrawSubmit(e) {
    e.preventDefault();
    const itemName = document.getElementById('withdrawItemName').value;
    const quantity = document.getElementById('withdrawQuantity').value;

    console.log('Withdrawal:', { itemName, quantity });
    showNotification(`تم تخريج ${quantity} من "${itemName}" (محاكاة)`, 'success');
    closeWithdrawModal();
    this.reset();
}

// Attach maintenance form handler
document.addEventListener('DOMContentLoaded', function() {
    const addMaintenanceForm = document.getElementById('addMaintenanceForm');
    if (addMaintenanceForm) {
        addMaintenanceForm.addEventListener('submit', handleAddMaintenanceSubmit);
    }
});

// ==================================================================
// Dashboard List Population
// ==================================================================

function populateLatestWarehouseItems() {
    const items = [
        { name: 'قلم حبر أزرق', quantity: 10, date: '2025-08-11' },
        { name: 'دفتر ملاحظات', quantity: 5, date: '2025-08-11' },
        { name: 'مشبك ورق', quantity: 100, date: '2025-08-10' },
        { name: 'قلم رصاص', quantity: 20, date: '2025-08-10' },
        { name: 'ممحاة', quantity: 15, date: '2025-08-09' }
    ];

    const list = document.getElementById('latest-warehouse-items');
    if (list) {
        list.innerHTML = '';
        items.forEach(item => {
            const listItem = document.createElement('li');
            listItem.innerHTML = `
                <span class="item-name">${item.name}</span>
                <span class="item-details">الكمية: ${item.quantity} - ${item.date}</span>
            `;
            list.appendChild(listItem);
        });
    }
}

function populateLatestMaintenanceEvents() {
    const events = [
        { vehicle: 'V-102', repair: 'تغيير زيت', date: '2025-08-11' },
        { vehicle: 'T-55', repair: 'إصلاح الإطارات', date: '2025-08-11' },
        { vehicle: 'C-301', repair: 'فحص المحرك', date: '2025-08-10' },
        { vehicle: 'V-103', repair: 'تغيير البطارية', date: '2025-08-09' },
        { vehicle: 'T-56', repair: 'إصلاح الفرامل', date: '2025-08-09' }
    ];

    const list = document.getElementById('latest-maintenance-events');
    if (list) {
        list.innerHTML = '';
        events.forEach(event => {
            const listItem = document.createElement('li');
            listItem.innerHTML = `
                <span class="item-name">${event.vehicle}</span>
                <span class="item-details">${event.repair} - ${event.date}</span>
            `;
            list.appendChild(listItem);
        });
    }
}

/* ==================================================================
   GAS STATION PAGE SPECIFIC FUNCTIONS
   ================================================================== */

// Enhanced Gas Station Tab Function
function openGasStationTab(evt, tabName) {
    // Get all tab contents and links
    var i, tabcontent, tablinks;
    tabcontent = document.getElementsByClassName("tab-content");
    tablinks = document.getElementsByClassName("tab-link");
    
    // Hide all tab contents
    for (i = 0; i < tabcontent.length; i++) {
        tabcontent[i].style.display = "none";
    }
    
    // Remove active class from all tab links
    for (i = 0; i < tablinks.length; i++) {
        tablinks[i].className = tablinks[i].className.replace(" active", "");
    }
    
    // Show the selected tab content
    var selectedTab = document.getElementById(tabName);
    if (selectedTab) {
        selectedTab.style.display = "block";
        evt.currentTarget.className += " active";
        
        // Initialize tab-specific functionality
        initializeGasStationTab(tabName);
        
        // Update page title for the specific tab
        updateGasStationSubTitle(tabName);
    }
}

// Initialize functionality for specific gas station tabs
function initializeGasStationTab(tabName) {
    switch(tabName) {
        case 'fueling':
            initializeFuelingTab();
            break;
        case 'tanks':
            initializeTanksTab();
            break;
        case 'vehicles':
            initializeVehiclesTab();
            break;
        case 'reports':
            initializeReportsTab();
            break;
    }
}

// Update subtitle for gas station sub-sections
function updateGasStationSubTitle(tabName) {
    const pageTitle = document.querySelector('.page-title');
    if (pageTitle) {
        const subTitles = {
            'fueling': 'التعبئة اليومية',
            'tanks': 'إدارة الخزانات',
            'vehicles': 'إدارة الآليات',
            'reports': 'التقارير والإحصائيات'
        };
        pageTitle.innerHTML = subTitles[tabName] ? 
            `<i class="fas fa-gas-pump"></i> الكازية - ${subTitles[tabName]}` : 
            '<i class="fas fa-gas-pump"></i> الكازية';
    }
}

/* ==================================================================
   FUELING TAB FUNCTIONS
   ================================================================== */

function initializeFuelingTab() {
    // Set today's date as default for fill_date
    const today = new Date().toISOString().split('T')[0];
    const fillDateInput = document.getElementById('fill_date');
    if (fillDateInput && !fillDateInput.value) {
        fillDateInput.value = today;
    }
    
    // Auto-calculate total cost based on liters and fuel type
    setupFuelCostCalculation();
    
    // Initialize vehicle search
    setupVehicleSearch();
    
    // Initialize fueling records search
    setupFuelingSearch();
    
    // Setup fueling form submission
    setupFuelingFormSubmission();
    
    // Load daily statistics
    loadDailyFuelingStats();
    
    // Setup table sorting
    setupFuelingTableSorting();
}

// Vehicle Search Functions
function setupVehicleSearch() {
    const searchInput = document.getElementById('vehicleSearch');
    const searchButton = document.querySelector('#vehicleSearch + .btn-primary');
    
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                searchVehicleForFueling();
            }
        });
    }
    
    if (searchButton) {
        searchButton.addEventListener('click', function(e) {
            e.preventDefault();
            searchVehicleForFueling();
        });
    }
    
    // Reset button
    const resetButton = document.querySelector('#vehicleSearch + .btn-primary + .btn-secondary');
    if (resetButton) {
        resetButton.addEventListener('click', function(e) {
            e.preventDefault();
            clearVehicleSearch();
        });
    }
}

function searchVehicleForFueling() {
    const searchInput = document.getElementById('vehicleSearch');
    const searchTerm = searchInput.value.trim();
    
    if (!searchTerm) {
        showNotification('يرجى إدخال كود الآلية أو رقم اللوحة للبحث', 'warning');
        return;
    }
    
    // Show loading state
    const searchButton = document.querySelector('#vehicleSearch + .btn-primary');
    if (searchButton) {
        searchButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري البحث...';
        searchButton.disabled = true;
    }
    
    // Clear previous results
    const vehicleInfoDisplay = document.getElementById('vehicleInfoDisplay');
    const vehicleDetailsContent = document.getElementById('vehicleDetailsContent');
    const fuelingForm = document.getElementById('fuelingForm');
    const addFillingBtn = document.getElementById('addFillingBtn');
    
    if (vehicleInfoDisplay) vehicleInfoDisplay.style.display = 'none';
    if (vehicleDetailsContent) vehicleDetailsContent.innerHTML = '<p>جاري البحث...</p>';
    if (addFillingBtn) {
        addFillingBtn.disabled = true;
        addFillingBtn.textContent = 'جاري البحث...';
    }
    
    // Search vehicles by code or plate number
    fetch(`pages/get_vehicle_details.php?search=${encodeURIComponent(searchTerm)}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success && data.data && data.data.length > 0) {
                // Display first matching vehicle (or modify to show list if multiple)
                const vehicle = data.data[0];
                displayVehicleInfo(vehicle);
                populateFuelingForm(vehicle);
                enableFuelingForm();
                
                showNotification(`تم العثور على الآلية: ${vehicle.make} - ${vehicle.plate_number}`, 'success');
            } else {
                // No vehicle found
                if (vehicleDetailsContent) {
                    vehicleDetailsContent.innerHTML = `
                        <div style="text-align: center; color: #7f8c8d; padding: 20px;">
                            <i class="fas fa-search fa-3x" style="margin-bottom: 15px; opacity: 0.5;"></i>
                            <p>لم يتم العثور على آلية بالكود أو رقم اللوحة المُدخل</p>
                            <p style="font-size: 0.9rem; margin-top: 10px;">يرجى المحاولة مرة أخرى أو التحقق من صحة البيانات</p>
                        </div>
                    `;
                }
                disableFuelingForm();
                
                showNotification('لم يتم العثور على الآلية المطلوبة', 'warning');
            }
        })
        .catch(error => {
            console.error('Error searching vehicle:', error);
            if (vehicleDetailsContent) {
                vehicleDetailsContent.innerHTML = `
                    <div style="text-align: center; color: #e74c3c; padding: 20px;">
                        <i class="fas fa-exclamation-triangle fa-3x" style="margin-bottom: 15px;"></i>
                        <p>حدث خطأ أثناء البحث</p>
                        <p style="font-size: 0.9rem; margin-top: 10px;">يرجى المحاولة مرة أخرى</p>
                    </div>
                `;
            }
            disableFuelingForm();
            
            showNotification('حدث خطأ أثناء البحث: ' + error.message, 'error');
        })
        .finally(() => {
            // Reset button state
            if (searchButton) {
                searchButton.innerHTML = '<i class="fas fa-search"></i> بحث';
                searchButton.disabled = false;
            }
            if (addFillingBtn) {
                addFillingBtn.textContent = 'حفظ السجل';
            }
        });
}

function displayVehicleInfo(vehicle) {
    const vehicleInfoDisplay = document.getElementById('vehicleInfoDisplay');
    const vehicleDetailsContent = document.getElementById('vehicleDetailsContent');
    
    if (!vehicleInfoDisplay || !vehicleDetailsContent) return;
    
    vehicleInfoDisplay.style.display = 'block';
    
    vehicleDetailsContent.innerHTML = `
        <div class="vehicle-detail-item">
            <strong>كود الآلية:</strong> ${vehicle.make || 'غير محدد'}
        </div>
        <div class="vehicle-detail-item">
            <strong>النوع:</strong> ${vehicle.type || 'غير محدد'}
        </div>
        <div class="vehicle-detail-item">
            <strong>رقم اللوحة:</strong> ${vehicle.plate_number || 'غير محدد'}
        </div>
        <div class="vehicle-detail-item">
            <strong>الموديل:</strong> ${vehicle.model || 'غير محدد'}
        </div>
        <div class="vehicle-detail-item">
            <strong>سنة الصنع:</strong> ${vehicle.year || 'غير محدد'}
        </div>
        <div class="vehicle-detail-item">
            <strong>المستلم:</strong> ${vehicle.recipient || 'غير محدد'}
        </div>
        <div class="vehicle-detail-item">
            <strong>رقم الشاسيه:</strong> ${vehicle.chassis_number || 'غير محدد'}
        </div>
        <div class="vehicle-detail-item">
            <strong>رقم المحرك:</strong> ${vehicle.engine_number || 'غير محدد'}
        </div>
        <div class="vehicle-detail-item">
            <strong>اللون:</strong> ${vehicle.color || 'غير محدد'}
        </div>
    `;
    
    // Add some styling to vehicle detail items
    const detailItems = vehicleDetailsContent.querySelectorAll('.vehicle-detail-item');
    detailItems.forEach((item, index) => {
        if (index % 2 === 0) {
            item.style.background = '#f8f9fa';
            item.style.padding = '8px 12px';
            item.style.borderRadius = '6px';
            item.style.marginBottom = '5px';
        }
    });
}

function populateFuelingForm(vehicle) {
    const vehicleIdInput = document.getElementById('vehicle_id');
    if (vehicleIdInput) {
        vehicleIdInput.value = vehicle.id;
    }
}

function enableFuelingForm() {
    const addFillingBtn = document.getElementById('addFillingBtn');
    if (addFillingBtn) {
        addFillingBtn.disabled = false;
        addFillingBtn.textContent = '<i class="fas fa-save"></i> حفظ السجل';
    }
}

function disableFuelingForm() {
    const addFillingBtn = document.getElementById('addFillingBtn');
    if (addFillingBtn) {
        addFillingBtn.disabled = true;
        addFillingBtn.innerHTML = 'يرجى اختيار آلية أولاً';
    }
    
    // Clear form fields except date and fuel type
    const vehicleIdInput = document.getElementById('vehicle_id');
    if (vehicleIdInput) {
        vehicleIdInput.value = '';
    }
    
    // Optionally clear other fields
    // document.getElementById('liters').value = '';
    // document.getElementById('total_cost').value = '';
    // document.getElementById('driver_name').value = '';
    // document.getElementById('notes').value = '';
}

// Setup fueling form submission handler
function setupFuelingFormSubmission() {
    const fuelingForm = document.getElementById('fuelingForm');
    const addFillingBtn = document.getElementById('addFillingBtn');
    
    if (!fuelingForm || !addFillingBtn) {
        console.warn('Fueling form or submit button not found');
        return;
    }
    
    // Handle form submission
    fuelingForm.addEventListener('submit', function(e) {
        e.preventDefault();
        handleFuelingSubmit();
    });
    
    // Also handle direct button click
    addFillingBtn.addEventListener('click', function(e) {
        if (!this.disabled) {
            e.preventDefault();
            handleFuelingSubmit();
        }
    });
}

function handleFuelingSubmit() {
    const addFillingBtn = document.getElementById('addFillingBtn');
    const originalText = addFillingBtn.innerHTML;
    
    // Validate required fields
    const vehicleId = document.getElementById('vehicle_id')?.value;
    const liters = document.getElementById('liters')?.value;
    const fuelType = document.getElementById('fuel_type')?.value;
    
    if (!vehicleId || !liters || parseFloat(liters) <= 0) {
        showNotification('يرجى اختيار الآلية وإدخال كمية صحيحة', 'warning');
        return;
    }
    
    if (!fuelType) {
        showNotification('يرجى تحديد نوع الوقود', 'warning');
        return;
    }
    
    // Show loading state
    addFillingBtn.disabled = true;
    addFillingBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري الحفظ...';
    
    // Collect form data
    const formData = new FormData();
    formData.append('vehicle_id', vehicleId);
    formData.append('liters', liters);
    formData.append('fuel_type', fuelType);
    formData.append('fill_date', document.getElementById('fill_date')?.value || '');
    formData.append('total_cost', document.getElementById('total_cost')?.value || '');
    formData.append('driver_name', document.getElementById('driver_name')?.value || '');
    formData.append('notes', document.getElementById('notes')?.value || '');
    
    // Send AJAX request
    fetch('pages/update_gas_log.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            showNotification(data.message || 'تم حفظ سجل التعبئة بنجاح', 'success');
            
            // Clear form (except date)
            document.getElementById('liters').value = '';
            document.getElementById('total_cost').value = '';
            document.getElementById('driver_name').value = '';
            document.getElementById('notes').value = '';
            document.getElementById('fuel_type').value = 'diesel'; // Reset to default
            
            // Reset cost calculation
            if (window.calculateCost) {
                window.calculateCost();
            }
            
            // Clear vehicle selection
            clearVehicleSearch();
            
            // Refresh fueling table if it exists
            if (window.loadDailyFuelingStats) {
                loadDailyFuelingStats();
            }
            
            // Add new record to table if visible
            if (data.data && data.action === 'insert') {
                addFuelingRecordToTable(data.data);
            }
        } else {
            showNotification(data.message || 'فشل في حفظ السجل', 'error');
        }
    })
    .catch(error => {
        console.error('Error submitting fueling record:', error);
        showNotification('حدث خطأ أثناء حفظ السجل: ' + error.message, 'error');
    })
    .finally(() => {
        // Reset button state
        addFillingBtn.disabled = false;
        addFillingBtn.innerHTML = originalText;
    });
}

// Add new fueling record to table
function addFuelingRecordToTable(record) {
    const tableBody = document.getElementById('fuelingTableBody');
    if (!tableBody) return;
    
    // Create new row HTML
    const fuelTypeAr = record.fuel_type === 'diesel' ? 'مازوت' : 'بنزين';
    const newRow = `
        <tr>
            <td>${record.fill_date || new Date().toISOString().split('T')[0]}</td>
            <td>${record.vehicle_plate || 'غير محدد'}</td>
            <td>${litersToArabic(record.liters || 0)}</td>
            <td>${formatCurrency(record.total_cost || 0)}</td>
            <td>${fuelTypeAr}</td>
            <td>${record.notes || ''}</td>
            <td>
                <button class="btn-icon btn-edit" onclick="editFuelingRecord(${record.id})" title="تعديل">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn-icon btn-delete" onclick="deleteFuelingRecord(${record.id})" title="حذف">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        </tr>
    `;
    
    // Insert at the beginning
    tableBody.insertAdjacentHTML('afterbegin', newRow);
    
    // Remove empty message if present
    const emptyRow = tableBody.querySelector('tr td[colspan]');
    if (emptyRow) {
        emptyRow.parentElement.remove();
    }
}

// Helper function to format liters in Arabic
function litersToArabic(liters) {
    return parseFloat(liters).toLocaleString('ar-SA', {
        minimumFractionDigits: 1,
        maximumFractionDigits: 1
    }) + ' ل';
}

// Update the cost calculation function to be globally accessible
window.calculateCost = function() {
    const litersInput = document.getElementById('liters');
    const fuelTypeSelect = document.getElementById('fuel_type');
    const totalCostInput = document.getElementById('total_cost');
    
    if (!litersInput || !fuelTypeSelect || !totalCostInput) return;
    
    const fuelPrices = {
        diesel: 500,
        gasoline: 750
    };
    
    const liters = parseFloat(litersInput.value) || 0;
    const fuelType = fuelTypeSelect.value;
    const pricePerLiter = fuelPrices[fuelType] || 500;
    const totalCost = liters * pricePerLiter;
    
    totalCostInput.value = totalCost.toFixed(2);
};

function clearVehicleSearch() {
    const searchInput = document.getElementById('vehicleSearch');
    const vehicleInfoDisplay = document.getElementById('vehicleInfoDisplay');
    const fuelingForm = document.getElementById('fuelingForm');
    
    if (searchInput) {
        searchInput.value = '';
    }
    
    if (vehicleInfoDisplay) {
        vehicleInfoDisplay.style.display = 'none';
    }
    
    // Reset form
    if (fuelingForm) {
        const formData = new FormData();
        fuelingForm.reset();
        const vehicleIdInput = document.getElementById('vehicle_id');
        if (vehicleIdInput) {
            vehicleIdInput.value = '';
        }
    }
    
    disableFuelingForm();
    
    // Set today's date again
    const today = new Date().toISOString().split('T')[0];
    const fillDateInput = document.getElementById('fill_date');
    if (fillDateInput) {
        fillDateInput.value = today;
    }
    
    showNotification('تم مسح نتائج البحث', 'info');
}

// Auto-calculate total cost when liters or fuel type changes
function setupFuelCostCalculation() {
    const litersInput = document.getElementById('liters');
    const fuelTypeSelect = document.getElementById('fuel_type');
    const totalCostInput = document.getElementById('total_cost');
    
    if (!litersInput || !fuelTypeSelect || !totalCostInput) return;
    
    // Fuel prices (can be updated from database or settings)
    const fuelPrices = {
        diesel: 500,    // 500 SYP per liter
        gasoline: 750   // 750 SYP per liter
    };
    
    function calculateCost() {
        const liters = parseFloat(litersInput.value) || 0;
        const fuelType = fuelTypeSelect.value;
        const pricePerLiter = fuelPrices[fuelType] || 0;
        const totalCost = liters * pricePerLiter;
        
        totalCostInput.value = totalCost.toFixed(2);
    }
    
    // Listen for changes
    litersInput.addEventListener('input', calculateCost);
    fuelTypeSelect.addEventListener('change', calculateCost);
    
    // Initial calculation
    calculateCost();
}

// Setup search functionality for fueling records
function setupFuelingSearch() {
    const searchInput = document.getElementById('fuelingSearch');
    if (!searchInput) return;
    
    searchInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        const table = document.getElementById('fuelingTable');
        const rows = table.tBodies[0].rows;
        
        for (let i = 0; i < rows.length; i++) {
            const cells = rows[i].getElementsByTagName('td');
            let found = false;
            
            for (let j = 0; j < cells.length - 1; j++) { // Skip actions column
                if (cells[j].textContent.toLowerCase().includes(searchTerm)) {
                    found = true;
                    break;
                }
            }
            
            rows[i].style.display = found ? '' : 'none';
        }
    });
}

function loadDailyFuelingStats() {
    // The fueling statistics are already rendered correctly by PHP
    // No need to override them with JavaScript
    console.log('Fueling statistics already rendered by PHP - no JS update needed');
}

// Setup table sorting for fueling records
function setupFuelingTableSorting() {
    const table = document.getElementById('fuelingTable');
    if (!table) return;
    
    const headers = table.querySelectorAll('th[onclick]');
    let sortDirections = {};
    
    headers.forEach((header, index) => {
        header.addEventListener('click', function() {
            sortFuelingTable(index);
        });
    });
}

let fuelingSortDirections = {};

function sortFuelingTable(columnIndex) {
    const table = document.getElementById('fuelingTable');
    if (!table) return;
    
    const tbody = table.tBodies[0];
    const rows = Array.from(tbody.rows);
    const header = table.tHead.rows[0].cells[columnIndex];
    
    // Toggle sort direction
    const direction = fuelingSortDirections[columnIndex] === 'asc' ? 'desc' : 'asc';
    fuelingSortDirections[columnIndex] = direction;
    
    // Update sort icons
    const icons = table.querySelectorAll('th i');
    icons.forEach(icon => {
        icon.className = 'fas fa-sort';
    });
    
    const icon = header.querySelector('i');
    if (icon) {
        icon.className = direction === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down';
    }
    
    // Sort rows
    const sortedRows = rows.sort((a, b) => {
        const aText = a.cells[columnIndex].textContent.trim();
        const bText = b.cells[columnIndex].textContent.trim();
        
        // Handle different column types
        if (columnIndex === 0) { // Date column
            const aDate = new Date(aText);
            const bDate = new Date(bText);
            return direction === 'asc' ? aDate - bDate : bDate - aDate;
        } else if (columnIndex === 2 || columnIndex === 3) { // Numeric columns (liters, cost)
            const aNum = parseFloat(aText) || 0;
            const bNum = parseFloat(bText) || 0;
            return direction === 'asc' ? aNum - bNum : bNum - aNum;
        } else {
            // Text comparison
            return direction === 'asc' ? 
                aText.localeCompare(bText, 'ar') : 
                bText.localeCompare(aText, 'ar');
        }
    });
    
    // Rebuild table body
    tbody.innerHTML = '';
    sortedRows.forEach(row => tbody.appendChild(row));
}

// Fueling record management functions
function editFuelingRecord(recordId) {
    showNotification('ميزة تعديل السجل قيد التطوير', 'info');
    console.log('Edit fueling record:', recordId);
}

function deleteFuelingRecord(recordId) {
    if (confirm('هل أنت متأكد من رغبتك في حذف هذا السجل؟')) {
        // Here you would make an AJAX call to delete the record
        fetch('pages/delete_fueling_record.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: recordId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('تم حذف السجل بنجاح', 'success');
                // Remove the row from the table
                const row = document.querySelector(`#fuelingTableBody tr:nth-child(${recordId + 1})`);
                if (row) row.remove();
            } else {
                showNotification('فشل في حذف السجل: ' + (data.message || 'خطأ غير معروف'), 'error');
            }
        })
        .catch(error => {
            console.error('Error deleting record:', error);
            showNotification('حدث خطأ أثناء الحذف', 'error');
        });
    }
}

function exportFuelingRecords() {
    showNotification('ميزة التصدير قيد التطوير', 'info');
    console.log('Export fueling records');
}

/* ==================================================================
   TANKS TAB FUNCTIONS
   ================================================================== */

function initializeTanksTab() {
    // Initialize tank level animations
    animateTankLevels();
    
    // Setup tank update buttons
    setupTankUpdateButtons();
    
    // Load tank history
    loadTankHistory();
}

function animateTankLevels() {
    const levelBars = document.querySelectorAll('.level-bar');
    levelBars.forEach(bar => {
        const currentHeight = bar.style.height;
        if (currentHeight) {
            // Add a subtle pulse animation for active tanks
            bar.style.animation = 'shimmer 2s infinite';
        }
    });
}

function setupTankUpdateButtons() {
    const updateButtons = document.querySelectorAll('.tank-actions .btn-warning');
    updateButtons.forEach(button => {
        button.addEventListener('click', function() {
            const tankType = this.closest('.tank-card').classList.contains('diesel-tank') ? 'diesel' : 'gasoline';
            updateTankLevel(tankType);
        });
    });
}

function updateTankLevel(tankType) {
    // Show loading state
    const button = event ? event.target.closest('button') : null;
    if (button) {
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري التحديث...';
        button.disabled = true;
    }
    
    // Simulate API call to update tank level
    setTimeout(() => {
        // Generate random level change
        const change = Math.random() > 0.5 ? -50 : 50; // Random increase/decrease
        const currentLevel = tankType === 'diesel' ? 7500 : 1500;
        const newLevel = Math.max(0, Math.min(10000, currentLevel + change));
        
        // Update the tank card
        const tankCard = document.querySelector(`.${tankType}-tank`);
        if (tankCard) {
            const levelBar = tankCard.querySelector('.level-bar');
            const levelText = tankCard.querySelector('.level-text');
            const currentAmount = tankCard.querySelector('p:nth-child(2)');
            const remainingAmount = tankCard.querySelector('p:nth-child(3)');
            
            const percentage = (newLevel / (tankType === 'diesel' ? 10000 : 5000)) * 100;
            
            if (levelBar) levelBar.style.height = percentage + '%';
            if (levelText) levelText.textContent = Math.round(percentage) + '%';
            if (currentAmount) currentAmount.innerHTML = `<strong>الكمية الحالية:</strong> ${newLevel.toLocaleString()} لتر`;
            if (remainingAmount) {
                const remaining = (tankType === 'diesel' ? 10000 : 5000) - newLevel;
                remainingAmount.innerHTML = `<strong>الكمية المتبقية:</strong> ${remaining.toLocaleString()} لتر`;
            }
            
            // Update status
            const status = tankCard.querySelector('.tank-status');
            if (status) {
                if (percentage > 70) {
                    status.textContent = 'نشط';
                    status.className = 'tank-status active';
                } else if (percentage > 30) {
                    status.textContent = 'جيد';
                    status.className = 'tank-status good';
                } else {
                    status.textContent = 'منخفض';
                    status.className = 'tank-status low';
                }
            }
        }
        
        // Update button state
        if (button) {
            button.innerHTML = '<i class="fas fa-sync"></i>  مرتجع ';
            button.disabled = false;
        }
        
        showNotification(`تم تحديث مستوى خزان ${tankType === 'diesel' ? 'المازوت' : 'البنزين'} بنجاح`, 'success');
    }, 1500);
}

function viewTankHistory(tankType) {
    showNotification(`عرض سجل خزان ${tankType}`, 'info');
    console.log('View tank history for:', tankType);
}

function alertLowLevel(tankType) {
    showNotification(`تنبيه: مستوى خزان ${tankType === 'diesel' ? 'المازوت' : 'البنزين'} منخفض! يرجى التعبئة فوراً`, 'warning');
}

function generateTankReport() {
    showNotification('جاري إنشاء تقرير الخزانات...', 'info');
    // Simulate report generation
    setTimeout(() => {
        showNotification('تم إنشاء التقرير بنجاح', 'success');
        // Here you would typically open a PDF or download file
        window.open('reports/tank-report.pdf', '_blank');
    }, 2000);
}

function refreshTankData() {
    showNotification('جاري تحديث بيانات الخزانات...', 'info');
    // Reload all tank data
    setTimeout(() => {
        showNotification('تم تحديث بيانات الخزانات بنجاح', 'success');
        // Trigger updates for all tanks
        updateTankLevel('diesel');
        updateTankLevel('gasoline');
    }, 1500);
}

function loadTankHistory() {
    // This would load historical data from the database
    // For now, we'll just ensure the table is visible
    const historyTable = document.querySelector('.tank-history table');
    if (historyTable) {
        // Add some animation to show data is loaded
        historyTable.style.opacity = '0';
        setTimeout(() => {
            historyTable.style.transition = 'opacity 0.5s ease';
            historyTable.style.opacity = '1';
        }, 100);
    }
}

function openAddTankModal() {
    showNotification('ميزة إضافة خزان جديد قيد التطوير', 'info');
}

/* ==================================================================
   VEHICLES TAB FUNCTIONS
   ================================================================== */

function initializeVehiclesTab() {
    // Setup vehicle card interactions
    setupVehicleCardInteractions();
    
    // Load vehicle statistics
    loadVehicleStats();
}

function setupVehicleCardInteractions() {
    const vehicleCards = document.querySelectorAll('.vehicle-card');
    vehicleCards.forEach(card => {
        // Add hover effects
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-3px)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
}

function loadVehicleStats() {
    // This would fetch vehicle statistics from the database
    // For now, we'll just ensure the cards are visible with animation
    const vehicleCards = document.querySelectorAll('.vehicle-card');
    vehicleCards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        setTimeout(() => {
            card.style.transition = 'all 0.6s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 200);
    });
}

function openVehicleRegistrationModal() {
    showNotification('ميزة تسجيل آلية جديدة قيد التطوير', 'info');
}

function viewVehicleHistory(vehicleCode) {
    showNotification(`عرض سجل تعبئة الآلية ${vehicleCode}`, 'info');
    console.log('View history for vehicle:', vehicleCode);
}

function editVehicleDetails(vehicleCode) {
    showNotification(`تعديل تفاصيل الآلية ${vehicleCode}`, 'info');
    console.log('Edit details for vehicle:', vehicleCode);
}

function markVehicleInactive(vehicleCode) {
    if (confirm(`هل أنت متأكد من تعطيل الآلية ${vehicleCode}؟`)) {
        const vehicleCard = document.querySelector(`[onclick="markVehicleInactive('${vehicleCode}')"]`).closest('.vehicle-card');
        if (vehicleCard) {
            const status = vehicleCard.querySelector('.vehicle-status');
            if (status) {
                status.textContent = 'معطلة';
                status.className = 'vehicle-status inactive';
                status.style.background = '#f8f9fa';
                status.style.color = '#6c757d';
            }
            
            // Disable buttons
            const buttons = vehicleCard.querySelectorAll('.vehicle-actions button');
            buttons.forEach(btn => btn.disabled = true);
            
            showNotification(`تم تعطيل الآلية ${vehicleCode} بنجاح`, 'success');
        }
    }
}

/* ==================================================================
   REPORTS TAB FUNCTIONS
   ================================================================== */

function initializeReportsTab() {
    // Setup report filters
    setupReportFilters();
    
    // Initialize charts (placeholder)
    initializeReportCharts();
    
    // Load report data
    loadReportData();
}

function setupReportFilters() {
    const periodSelect = document.getElementById('reportPeriod');
    if (!periodSelect) return;
    
    periodSelect.addEventListener('change', function() {
        const customDateGroup = document.querySelector('.report-filters + .date-picker-group');
        if (this.value === 'custom' && customDateGroup) {
            customDateGroup.style.display = 'flex';
        } else if (customDateGroup) {
            customDateGroup.style.display = 'none';
        }
    });
}

function initializeReportCharts() {
    // Placeholder for chart initialization
    // In a real implementation, this would use Chart.js or similar library
    const chartPlaceholders = document.querySelectorAll('.chart-placeholder');
    chartPlaceholders.forEach(placeholder => {
        placeholder.addEventListener('click', function() {
            showNotification('سيتم إضافة الرسوم البيانية قريباً', 'info');
        });
    });
}

function loadReportData() {
    // Animate report cards appearance
    const reportCards = document.querySelectorAll('.report-card');
    reportCards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        setTimeout(() => {
            card.style.transition = 'all 0.6s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 150);
    });
    
    // Update report numbers with animations
    const reportNumbers = document.querySelectorAll('.report-number');
    reportNumbers.forEach(number => {
        const targetValue = parseInt(number.textContent.replace(/[^\d]/g, ''));
        if (!isNaN(targetValue)) {
            animateNumber(number, 0, targetValue, 2000);
        }
    });
}

function animateNumber(element, start, end, duration) {
    let startTimestamp = null;
    const step = (timestamp) => {
        if (!startTimestamp) startTimestamp = timestamp;
        const progress = Math.min((timestamp - startTimestamp) / duration, 1);
        const currentValue = Math.floor(progress * (end - start) + start);
        
        // Format the number based on its original format
        const originalText = element.textContent;
        if (originalText.includes('%')) {
            element.textContent = currentValue + '%';
        } else if (originalText.includes(',')) {
            element.textContent = currentValue.toLocaleString();
        } else {
            element.textContent = currentValue;
        }
        
        if (progress < 1) {
            requestAnimationFrame(step);
        }
    };
    requestAnimationFrame(step);
}

function generateReport() {
    const period = document.getElementById('reportPeriod')?.value || 'week';
    showNotification(`جاري إنشاء تقرير للفترة: ${getPeriodName(period)}...`, 'info');
    
    setTimeout(() => {
        // Simulate report generation
        updateReportData(period);
        showNotification('تم إنشاء التقرير بنجاح', 'success');
    }, 1500);
}

function getPeriodName(period) {
    const periodNames = {
        'today': 'اليوم',
        'week': 'هذا الأسبوع',
        'month': 'هذا الشهر',
        'year': 'هذا العام',
        'custom': 'الفترة المخصصة'
    };
    return periodNames[period] || 'الفترة الحالية';
}

function updateReportData(period) {
    // Update report numbers based on period
    const multipliers = {
        'today': 1,
        'week': 7,
        'month': 30,
        'year': 365,
        'custom': 15
    };
    
    const multiplier = multipliers[period] || 1;
    const baseValues = { consumption: 2850, cost: 1425000, efficiency: 85, alerts: 2 };
    
    Object.keys(baseValues).forEach(key => {
        const element = document.querySelector(`.report-card.${key} .report-number`);
        if (element) {
            const newValue = Math.round(baseValues[key] * multiplier * 0.8); // 80% efficiency factor
            animateNumber(element, parseInt(element.textContent.replace(/[^\d]/g, '') || 0), newValue, 1000);
        }
    });
}

function exportReport() {
    showNotification('جاري تحضير ملف التصدير...', 'info');
    setTimeout(() => {
        // Simulate PDF generation
        const link = document.createElement('a');
        link.href = 'data:text/plain;charset=utf-8,تقرير الكازية.pdf';
        link.download = 'gas-station-report.pdf';
        link.click();
        showNotification('تم تحميل ملف التقرير بنجاح', 'success');
    }, 2000);
}

// Initialize gas station functionality when page loads
document.addEventListener('DOMContentLoaded', function() {
    // Check if we're on the gas station page
    if (document.getElementById('page-gas-station')) {
        // Ensure the fueling tab is active by default
        const defaultTab = document.getElementById('fueling');
        if (defaultTab && defaultTab.style.display !== 'block') {
            defaultTab.style.display = 'block';
            const activeTabLink = document.querySelector('.tab-link.active');
            if (activeTabLink) {
                activeTabLink.classList.remove('active');
            }
            const fuelingTabLink = document.querySelector('button[onclick="openGasStationTab(event, \'fueling\')"]');
            if (fuelingTabLink) {
                fuelingTabLink.classList.add('active');
            }
        }
        
        // Initialize the default tab
        initializeGasStationTab('fueling');
    }
});

// Make gas station functions globally available
window.openGasStationTab = openGasStationTab;
window.initializeGasStationTab = initializeGasStationTab;
window.setupFuelCostCalculation = setupFuelCostCalculation;
window.loadDailyFuelingStats = loadDailyFuelingStats;
window.sortFuelingTable = sortFuelingTable;
window.editFuelingRecord = editFuelingRecord;
window.deleteFuelingRecord = deleteFuelingRecord;
window.exportFuelingRecords = exportFuelingRecords;
window.updateTankLevel = updateTankLevel;
window.viewTankHistory = viewTankHistory;
window.alertLowLevel = alertLowLevel;
window.generateTankReport = generateTankReport;
window.refreshTankData = refreshTankData;
window.openVehicleRegistrationModal = openVehicleRegistrationModal;
window.viewVehicleHistory = viewVehicleHistory;
window.editVehicleDetails = editVehicleDetails;
window.markVehicleInactive = markVehicleInactive;
window.generateReport = generateReport;
window.exportReport = exportReport;

// ==================================================================
// Vehicles Page Specific Functions
// ==================================================================

function openVehicleModal() {
    const modal = document.getElementById('addVehicleModal');
    if (modal) modal.style.display = 'flex';
}

function closeVehicleModal() {
    const modal = document.getElementById('addVehicleModal');
    if (modal) modal.style.display = 'none';
}
function closeeditVehicleModal() {
    const modal = document.getElementById('editVehicleModal');
    if (modal) modal.style.display = 'none';
    // Clear stored original values
    window.vehicleOriginalValues = null;
}

// Store original values globally for validation
window.vehicleOriginalValues = null;

// --- Table Actions ---
function editVehicle(vehicleId) {
    const modal = document.getElementById('editVehicleModal');
    if (!modal) {
        console.error("Edit vehicle modal not found!");
        return;
    }
    modal.style.display = 'flex';

    // Fetch vehicle data from the backend
    fetch(`pages/get_vehicle_details.php?vehicle_id=${vehicleId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data) {
                const vehicleData = data.data;
                
                // Set hidden ID field
                const editVehicleId = document.getElementById('editVehicleId');
                if (editVehicleId) editVehicleId.value = vehicleData.id || '';
                
                // Populate display fields (readonly)
                document.getElementById('display_make').textContent = vehicleData.make || '';
                document.getElementById('display_type').textContent = vehicleData.type || '';
                document.getElementById('display_plate_number').textContent = vehicleData.plate_number || '';
                document.getElementById('display_model').textContent = vehicleData.model || '';
                document.getElementById('display_year').textContent = vehicleData.year || '';
                document.getElementById('display_recipient').textContent = vehicleData.recipient || '';
                document.getElementById('display_chassis_number').textContent = vehicleData.chassis_number || '';
                document.getElementById('display_engine_number').textContent = vehicleData.engine_number || '';
                document.getElementById('display_color').textContent = vehicleData.color || '';
                document.getElementById('display_fuel_type').textContent = vehicleData.fuel_type ? (vehicleData.fuel_type === 'diesel' ? 'مازوت' : 'بنزين') : '';
                document.getElementById('display_monthly_allocations').textContent = vehicleData.monthly_allocations ? parseFloat(vehicleData.monthly_allocations).toFixed(2) : '0.00';
                document.getElementById('display_notes').textContent = vehicleData.notes || '';
                
                // Populate edit fields with original values
                document.getElementById('edit_make').value = vehicleData.make || '';
                document.getElementById('edit_type').value = vehicleData.type || '';
                document.getElementById('edit_plate_number').value = vehicleData.plate_number || '';
                document.getElementById('edit_model').value = vehicleData.model || '';
                document.getElementById('edit_year').value = vehicleData.year || '';
                document.getElementById('edit_recipient').value = vehicleData.recipient || '';
                document.getElementById('edit_chassis_number').value = vehicleData.chassis_number || '';
                document.getElementById('edit_engine_number').value = vehicleData.engine_number || '';
                document.getElementById('edit_color').value = vehicleData.color || '';
                document.getElementById('edit_fuel_type').value = vehicleData.fuel_type || '';
                document.getElementById('edit_monthly_allocations').value = vehicleData.monthly_allocations || '';
                document.getElementById('edit_notes').value = vehicleData.notes || '';
                
                // Store original values for validation
                window.vehicleOriginalValues = {
                    make: vehicleData.make || '',
                    type: vehicleData.type || '',
                    plate_number: vehicleData.plate_number || '',
                    model: vehicleData.model || '',
                    year: vehicleData.year || '',
                    recipient: vehicleData.recipient || '',
                    chassis_number: vehicleData.chassis_number || '',
                    engine_number: vehicleData.engine_number || '',
                    color: vehicleData.color || '',
                    fuel_type: vehicleData.fuel_type || '',
                    monthly_allocations: vehicleData.monthly_allocations || 0,
                    notes: vehicleData.notes || ''
                };
                
                // Reset submit button state
                const submitBtn = document.getElementById('updateVehicleBtn');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'تعديل';
                }
                
                showNotification('تم تحميل بيانات الآلية للتعديل', 'success');
            } else {
                showNotification('فشل في تحميل بيانات الآلية للتعديل: ' + (data.message || 'خطأ غير معروف'), 'error');
                closeeditVehicleModal();
            }
        })
        .catch((error) => {
            console.error('Error fetching vehicle data:', error);
            showNotification('حدث خطأ أثناء جلب بيانات الآلية', 'error');
            closeeditVehicleModal();
        });
}

/* 
NOTE: The following functions are for the OLD vehicles page implementation 
and should NOT be used with vehicles-report.html individual field editing.
They are kept for backward compatibility with the main vehicles page.
*/

/* 
NOTE: The following functions are for the OLD vehicles page implementation 
and should NOT be used with vehicles-report.html individual field editing.
They are kept for backward compatibility with the main vehicles page.
*/

// Validation function to check if any changes were made (OLD IMPLEMENTATION)
function hasChangesBeenMade() {
    if (!window.vehicleOriginalValues) {
        return false;
    }
    
    const currentValues = {
        make: document.getElementById('edit_make')?.value || '',
        type: document.getElementById('edit_type')?.value || '',
        plate_number: document.getElementById('edit_plate_number')?.value || '',
        model: document.getElementById('edit_model')?.value || '',
        year: parseInt(document.getElementById('edit_year')?.value || 0),
        recipient: document.getElementById('edit_recipient')?.value || '',
        chassis_number: document.getElementById('edit_chassis_number')?.value || '',
        engine_number: document.getElementById('edit_engine_number')?.value || '',
        color: document.getElementById('edit_color')?.value || '',
        fuel_type: document.getElementById('edit_fuel_type')?.value || '',
        monthly_allocations: parseFloat(document.getElementById('edit_monthly_allocations')?.value || 0),
        notes: document.getElementById('edit_notes')?.value || ''
    };
    
    for (let field in currentValues) {
        if (currentValues[field] !== window.vehicleOriginalValues[field]) {
            console.log(`Change detected in field ${field}: original="${window.vehicleOriginalValues[field]}", current="${currentValues[field]}"`);
            return true;
        }
    }
    return false;
}

// Real-time validation on input change (OLD IMPLEMENTATION)
function setupEditVehicleValidation() {
    const editFields = ['edit_make', 'edit_type', 'edit_plate_number', 'edit_model', 
                       'edit_year', 'edit_recipient', 'edit_chassis_number', 
                       'edit_engine_number', 'edit_color'];
    
    editFields.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field) {
            field.addEventListener('input', function() {
                const submitBtn = document.getElementById('updateVehicleBtn');
                if (submitBtn) {
                    if (hasChangesBeenMade()) {
                        submitBtn.disabled = true;
                        submitBtn.textContent = 'تعديل';
                    } else {
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'يرجى تعديل احد البنود قبل الحفظ';
                    }
                }
            });
        }
    });
}

// Form submission handler for edit vehicle (OLD IMPLEMENTATION)
function handleEditVehicleSubmit(event) {
    event.preventDefault();
    event.stopPropagation();
    
    if (hasChangesBeenMade()) {
        showNotification('يرجى تعديل احد البنود قبل الحفظ', 'warning');
        return;
    }
    
    const formData = new FormData(event.target);
    const submitBtn = document.getElementById('updateVehicleBtn');
    
    // Add AJAX header to identify this as AJAX request
    const headers = new Headers();
    headers.append('X-Requested-With', 'XMLHttpRequest');
    
    // Disable button and show loading
    submitBtn.disabled = true;
    submitBtn.textContent = 'جاري الحفظ...';
    
    fetch('main.php', {
        method: 'POST',
        body: formData,
        headers: headers
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(result => {
        if (result.success) {
            showNotification('تم تحديث الآلية بنجاح', 'success');
            closeeditVehicleModal();
            // Update the table row with new data from response
            if (result.data) {
                updateVehicleTableRow(result.data.id, result.data);
            }
        } else {
            showNotification(result.message, 'error');
        }
    })
    // .catch(error => {
    //     console.error('Error updating vehicle:', error);
    //     showNotification('حدث خطأ أثناء تحديث الآلية: ' + error.message, 'error');
    // })
    .finally(() => {
        // Re-enable button with appropriate text
        submitBtn.disabled = false;
        const hasChanges = hasChangesBeenMade();
        submitBtn.textContent = hasChanges ? 'تعديل' : 'يرجى تعديل احد البنود قبل الحفظ';
        submitBtn.disabled = !hasChanges;
    });
}

// Update the specific row in the vehicles table (OLD IMPLEMENTATION)
function updateVehicleTableRow(vehicleId, formData) {
    const row = document.querySelector(`#vehiclesTableBody tr:nth-child(${vehicleId + 1})`);
    if (row) {
        // Update cells with new values (skip first cell as it's index-based)
        row.cells[0].textContent = formData.get('make') || '';
        row.cells[1].textContent = formData.get('type') || '';
        row.cells[2].textContent = formData.get('plate_number') || '';
        row.cells[3].textContent = formData.get('chassis_number') || '';
        row.cells[4].textContent = formData.get('engine_number') || '';
        row.cells[5].textContent = formData.get('color') || '';
        // Actions column remains the same
    }
}

// Initialize edit vehicle functionality when DOM loads (OLD IMPLEMENTATION - for vehicles page only)
document.addEventListener('DOMContentLoaded', function() {
    // Only run old validation if we're on the vehicles page, not vehicles-report
    if (document.getElementById('page-vehicles')) {
        const editVehicleForm = document.getElementById('editVehicleForm');
        if (editVehicleForm) {
            editVehicleForm.addEventListener('submit', handleEditVehicleSubmit);
            setupEditVehicleValidation();
        }
    }
});


function openDeleteVehicleModal(vehicleId) {
    console.log('Opening delete confirmation for vehicle ID:', vehicleId);
    window.vehicleToDelete = vehicleId;
    
    const modal = document.getElementById('deleteVehicleModal');
    if (modal) {
        // Always set the vehicle ID display
        const vehicleIdDisplay = document.getElementById('vehicleIdDisplay');
        if (vehicleIdDisplay) {
            vehicleIdDisplay.textContent = vehicleId;
        }
        
        // Update the modal content with vehicle information
        const vehicleInfo = document.getElementById('vehicleInfo');
        if (vehicleInfo) {
            // Set default text first
            vehicleInfo.textContent = `الآلية رقم ${vehicleId}`;
            
            // Try to fetch basic vehicle info to display in confirmation
            fetch(`pages/get_vehicle_details.php?vehicle_id=${vehicleId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data) {
                        const make = data.data.make || 'غير محدد';
                        const plate = data.data.plate_number || 'غير محدد';
                        vehicleInfo.textContent = `الآلية: ${make} - ${plate}`;
                    }
                })
                .catch(error => {
                    console.error('Error fetching vehicle info for delete confirmation:', error);
                    // Keep the default text if fetch fails
                });
        }
        
        modal.style.display = 'flex';
    }
}

function closeDeleteVehicleModal() {
    const modal = document.getElementById('deleteVehicleModal');
    if (modal) {
        modal.style.display = 'none';
    }
    window.vehicleToDelete = null;
}

function deleteVehicle(vehicleId) {
    if (!vehicleId) {
        showNotification('معرف الآلية غير صحيح', 'error');
        return;
    }
    
    const deleteData = {
        id: parseInt(vehicleId)
    };
    
    console.log('Deleting vehicle with ID:', vehicleId);
    
    fetch('pages/delete_vehicle.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(deleteData)
    })
    .then(response => {
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            return response.text().then(text => {
                console.error('Server returned non-JSON response:', text.substring(0, 200));
                throw new Error('خطأ في الخادم: تم إرجاع رد غير صالح');
            });
        }
        return response.json();
    })
    .then(result => {
        if (result.success) {
            showNotification('تم حذف الآلية بنجاح', 'success');
            
            // Remove the row from the table
            const row = document.querySelector(`tr[data-vehicle-id="${vehicleId}"]`);
            if (row) {
                row.remove();
            } else {
                // If data-vehicle-id attribute is not found, try to find by edit button
                const editButton = document.querySelector(`button[onclick="editVehicle(${vehicleId})"]`);
                if (editButton) {
                    const tableRow = editButton.closest('tr');
                    if (tableRow) {
                        tableRow.remove();
                    }
                }
            }
            
            // Refresh the page or reload vehicle data if needed
            // For now, just close the modal
            closeDeleteVehicleModal();
        } else {
            showNotification(`فشل في حذف الآلية: ${result.message || 'خطأ غير معروف'}`, 'error');
        }
    })
    .catch(error => {
        console.error('Error deleting vehicle:', error);
        showNotification('حدث خطأ أثناء حذف الآلية: ' + error.message, 'error');
        closeDeleteVehicleModal();
    });
}

// Global variable to store the vehicle ID to be deleted
window.vehicleToDelete = null;

// Make delete functions globally available
window.openDeleteVehicleModal = openDeleteVehicleModal;
window.closeDeleteVehicleModal = closeDeleteVehicleModal;
window.deleteVehicle = deleteVehicle;

// ==================================================================
// Vehicles Report Page Specific Functions
// ==================================================================

document.addEventListener('DOMContentLoaded', function() {
    // Listener for the date range dropdown
    const dateRangeSelect = document.getElementById('dateRange');
    if (dateRangeSelect) {
        dateRangeSelect.addEventListener('change', function() {
            const customDateRange = document.getElementById('customDateRange');
            if (this.value === 'custom') {
                customDateRange.style.display = 'flex';
            } else {
                customDateRange.style.display = 'none';
            }
        });
    }
});

function applyVehicleReportFilter() {
    const searchCode = document.getElementById('searchVehicleCode').value.trim();
    const allVehiclesView = document.getElementById('all-vehicles-view');
    const singleVehicleView = document.getElementById('single-vehicle-view');

    if (!allVehiclesView || !singleVehicleView) {
        console.error("Report views not found!");
        return;
    }

    if (searchCode) {
        // --- Simulate fetching and displaying data for a single vehicle ---
        console.log(`Searching for vehicle code: ${searchCode}`);
        
        // Hide the general report and show the specific one
        allVehiclesView.style.display = 'none';
        singleVehicleView.style.display = 'block';
        
        // Update the title with the selected vehicle code
        document.getElementById('selectedVehicleCode').textContent = searchCode;
        
        // Get the date range
        const dateRange = document.getElementById('dateRange').value;
        console.log(`Filtering by date range: ${dateRange}`);
        
        // (In a real application, you would fetch this data via an API call)
        const maintenanceHistory = [
            { date: '2025-08-12', details: 'تغيير زيت المحرك', warehouse: 'فلتر زيت (2), زيت محرك (5L)', purchased: 'لا يوجد' },
            { date: '2025-07-20', details: 'تغيير إطارات', warehouse: 'إطارات (4)', purchased: 'لا يوجد' },
            { date: '2025-06-15', details: 'إصلاح الفرامل', warehouse: 'أقمشة فرامل (2)', purchased: 'زيت فرامل (1L)' },
        ];
        
        const tableBody = document.getElementById('maintenanceHistoryBody');
        tableBody.innerHTML = ''; // Clear previous results
        
        if (maintenanceHistory.length > 0) {
            maintenanceHistory.forEach(record => {
                const row = `<tr>
                                <td>${record.date}</td>
                                <td>${record.details}</td>
                                <td>${record.warehouse}</td>
                                <td>${record.purchased}</td>
                             </tr>`;
                tableBody.innerHTML += row;
            });
        } else {
            tableBody.innerHTML = '<tr><td colspan="4">لا توجد سجلات صيانة لهذه الآلية خلال الفترة المحددة.</td></tr>';
        }
        
        showNotification(`عرض سجل الصيانة للآلية ${searchCode}`, 'info');

    } else {
        // If no code is entered, show the general report
        allVehiclesView.style.display = 'block';
        singleVehicleView.style.display = 'none';
        showNotification('عرض التقرير الشامل لجميع الآليات', 'info');
    }
}

function resetVehicleReportFilter() {
    document.getElementById('searchVehicleCode').value = '';
    document.getElementById('dateRange').value = 'this_month';
    document.getElementById('customDateRange').style.display = 'none';
    
    // Show the general report and hide the specific one
    document.getElementById('all-vehicles-view').style.display = 'block';
    document.getElementById('single-vehicle-view').style.display = 'none';
    
    showNotification('تمت إعادة تعيين الفلاتر', 'info');
}

// ==================================================================
// Maintenance Report Page Specific Functions
// ==================================================================

const allMaintenanceData = [
    { date: "2025-08-12", vehicleCode: "TR-01", vehicleType: "شاحنة", maintenanceType: "تغيير زيت", details: "تغيير زيت المحرك وفلتر الزيت", materials: "فلتر زيت (2), زيت محرك (5L)" },
    { date: "2025-08-11", vehicleCode: "FL-103", vehicleType: "رافعة شوكية", maintenanceType: "فحص دوري", details: "فحص شامل للأنظمة الهيدروليكية والكهربائية", materials: "لا يوجد" },
    { date: "2025-08-10", vehicleCode: "GR-101", vehicleType: "جرافة", maintenanceType: "إصلاح محرك", details: "إصلاح نظام التبريد وتغيير مضخة المياه", materials: "مضخة مياه (1), سائل تبريد (10L)" },
    { date: "2025-07-20", vehicleCode: "TR-01", vehicleType: "شاحنة", maintenanceType: "تغيير إطارات", details: "تغيير الإطارات الأربعة الخلفية", materials: "إطارات (4)" },
    { date: "2025-06-15", vehicleCode: "GR-101", vehicleType: "جرافة", maintenanceType: "تغيير زيت", details: "تغيير زيت الهيدروليك", materials: "زيت هيدروليك (20L)" }
];

document.addEventListener('DOMContentLoaded', function() {
    // Initial render of the maintenance report table
    if (document.getElementById('page-maintenance-report')) {
        renderMaintenanceReportTable(allMaintenanceData);
    }

    // Handle custom date range visibility for maintenance report
    const maintenanceDateRangeSelect = document.getElementById('maintenanceDateRange');
    if (maintenanceDateRangeSelect) {
        maintenanceDateRangeSelect.addEventListener('change', function() {
            const customDateRange = document.getElementById('maintenanceCustomDateRange');
            if (this.value === 'custom') {
                customDateRange.style.display = 'flex';
            } else {
                customDateRange.style.display = 'none';
            }
        });
    }
});

function renderMaintenanceReportTable(data) {
    const tableBody = document.getElementById('maintenanceReportBody');
    if (!tableBody) return;
    tableBody.innerHTML = ''; // Clear existing rows

    const sortedData = data.sort((a, b) => new Date(b.date) - new Date(a.date));

    sortedData.forEach(item => {
        const row = `<tr>
            <td>${item.date}</td>
            <td>${item.vehicleCode}</td>
            <td>${item.vehicleType}</td>
            <td>${item.maintenanceType}</td>
            <td>${item.details}</td>
            <td>${item.materials}</td>
        </tr>`;
        tableBody.innerHTML += row;
    });
}

function applyMaintenanceReportFilter() {
    const vehicleFilter = document.getElementById('maintenanceFilterVehicle').value;
    const typeFilter = document.getElementById('maintenanceFilterMaintenanceType').value;
    const dateRangeFilter = document.getElementById('maintenanceDateRange').value;
    const startDateFilter = document.getElementById('maintenanceStartDate').value;
    const endDateFilter = document.getElementById('maintenanceEndDate').value;

    let filteredData = allMaintenanceData;

    // Filter by vehicle
    if (vehicleFilter !== 'all') {
        filteredData = filteredData.filter(item => item.vehicleCode === vehicleFilter);
    }

    // Filter by maintenance type
    if (typeFilter !== 'all') {
        filteredData = filteredData.filter(item => item.maintenanceType === typeFilter);
    }

    // Filter by date
    const now = new Date();
    const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    
    switch (dateRangeFilter) {
        case 'today':
            filteredData = filteredData.filter(item => new Date(item.date) >= today);
            break;
        case 'this_week':
            const firstDayOfWeek = new Date(today);
            firstDayOfWeek.setDate(today.getDate() - today.getDay());
            filteredData = filteredData.filter(item => new Date(item.date) >= firstDayOfWeek);
            break;
        case 'this_month':
            const firstDayOfMonth = new Date(now.getFullYear(), now.getMonth(), 1);
            filteredData = filteredData.filter(item => new Date(item.date) >= firstDayOfMonth);
            break;
        case 'custom':
            if (startDateFilter && endDateFilter) {
                const start = new Date(startDateFilter);
                const end = new Date(endDateFilter);
                end.setHours(23, 59, 59, 999); // Include the entire end day
                filteredData = filteredData.filter(item => {
                    const itemDate = new Date(item.date);
                    return itemDate >= start && itemDate <= end;
                });
            }
            break;
    }

    renderMaintenanceReportTable(filteredData);
    showNotification(`تم عرض ${filteredData.length} من النتائج`, 'success');
}

function resetMaintenanceReportFilter() {
    document.getElementById('maintenanceFilterVehicle').value = 'all';
    document.getElementById('maintenanceFilterMaintenanceType').value = 'all';
    document.getElementById('maintenanceDateRange').value = 'this_month';
    document.getElementById('maintenanceStartDate').value = '';
    document.getElementById('maintenanceEndDate').value = '';
    document.getElementById('maintenanceCustomDateRange').style.display = 'none';

    renderMaintenanceReportTable(allMaintenanceData);
    showNotification('تمت إعادة تعيين الفلاتر', 'info');
}

let maintenanceSortDirections = {};

function sortTable(tableId, columnIndex) {
    const table = document.getElementById(tableId);
    if (!table) return;

    const tbody = table.tBodies[0];
    const rows = Array.from(tbody.rows);
    const header = table.tHead.rows[0].cells[columnIndex];

    const direction = maintenanceSortDirections[columnIndex] === 'asc' ? 'desc' : 'asc';
    maintenanceSortDirections[columnIndex] = direction;

    document.querySelectorAll(`#${tableId} th i`).forEach(icon => {
        icon.classList.remove('fa-sort-up', 'fa-sort-down');
        icon.classList.add('fa-sort');
    });

    const icon = header.querySelector('i');
    icon.classList.remove('fa-sort');
    icon.classList.add(direction === 'asc' ? 'fa-sort-up' : 'fa-sort-down');

    const sortedRows = rows.sort((a, b) => {
        const aText = a.cells[columnIndex].textContent.trim();
        const bText = b.cells[columnIndex].textContent.trim();

        if (columnIndex === 0) { // Date column
            const aDate = new Date(aText);
            const bDate = new Date(bText);
            return direction === 'asc' ? aDate - bDate : bDate - aDate;
        }

        return direction === 'asc' ? aText.localeCompare(bText) : bText.localeCompare(aText);
    });

    tbody.innerHTML = '';
    sortedRows.forEach(row => tbody.appendChild(row));
}

// ==================================================================
// Vehicle Management Functions (for vehicles-report.html)
// ==================================================================

// --- Edit Vehicle Modal Functions - Individual Field Updates ---
function closeEditVehicleModal() {
    const modal = document.getElementById('editVehicleModal');
    if (modal) modal.style.display = 'none';
    // Reset all individual field forms
    const fieldForms = modal.querySelectorAll('.field-form');
    fieldForms.forEach(form => form.reset());
}

// Generic function to update a single vehicle field
function updateSingleVehicleField(vehicleId, fieldName, fieldValue, displayElementId = null) {
    if (!fieldValue || fieldValue.trim() === '') {
        showNotification('القيمة لا يمكن أن تكون فارغة', 'warning');
        return;
    }
    
    const updateData = {
        id: vehicleId,
        [fieldName]: fieldValue
    };
    
    console.log(`Updating vehicle ${vehicleId} field ${fieldName}:`, updateData);

    fetch('pages/update_vehicle.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(updateData),
    })
    .then(response => {
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            return response.text().then(text => {
                console.error('Server returned HTML instead of JSON:', text.substring(0, 200));
                throw new Error('خطأ في الخادم: تم إرجاع HTML بدلاً من JSON');
            });
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            showNotification(`تم تحديث ${getFieldDisplayName(fieldName)} بنجاح`, 'success');
            
            // Update the display element if specified
            if (displayElementId && document.getElementById(displayElementId)) {
                document.getElementById(displayElementId).textContent = fieldValue;
            }
            
            // Update the table row
            updateVehicleTableRow(vehicleId, { [fieldName]: fieldValue });
            
            // Reset the input field
            const inputField = document.querySelector(`input[name="${fieldName}"]`);
            if (inputField) {
                inputField.value = '';
            }
        } else {
            showNotification(`فشل تحديث ${getFieldDisplayName(fieldName)}: ${data.message || 'خطأ غير معروف'}`, 'error');
        }
    })
    .catch((error) => {
        console.error(`Error updating ${fieldName}:`, error);
        showNotification(`حدث خطأ أثناء تحديث ${getFieldDisplayName(fieldName)}: ` + error.message, 'error');
    });
}

// Get display name for field in Arabic
function getFieldDisplayName(fieldName) {
    const fieldNames = {
        'make': 'الطراز',
        'type': 'النوع',
        'model': 'الموديل',
        'plate_number': 'رقم اللوحة',
        'year': 'سنة الصنع',
        'recipient': 'المستلم',
        'chassis_number': 'رقم الشاسيه',
        'engine_number': 'رقم المحرك',
        'color': 'اللون'
    };
    return fieldNames[fieldName] || fieldName;
}

// Individual field form handlers
function handleMakeUpdate(event) {
    event.preventDefault();
    const vehicleId = document.getElementById('editVehicleId').value;
    const makeValue = document.getElementById('edit_make_input').value.trim();
    updateSingleVehicleField(vehicleId, 'make', makeValue, 'display_make');
}

function handleTypeUpdate(event) {
    event.preventDefault();
    const vehicleId = document.getElementById('editVehicleId').value;
    const typeValue = document.getElementById('edit_type_input').value.trim();
    updateSingleVehicleField(vehicleId, 'type', typeValue, 'display_type');
}

function handlePlateNumberUpdate(event) {
    event.preventDefault();
    const vehicleId = document.getElementById('editVehicleId').value;
    const plateValue = document.getElementById('edit_plate_number_input').value.trim();
    updateSingleVehicleField(vehicleId, 'plate_number', plateValue, 'display_plate_number');
}

function handleModelUpdate(event) {
    event.preventDefault();
    const vehicleId = document.getElementById('editVehicleId').value;
    const modelValue = document.getElementById('edit_model_input').value.trim();
    updateSingleVehicleField(vehicleId, 'model', modelValue, 'display_model');
}

function handleYearUpdate(event) {
    event.preventDefault();
    const vehicleId = document.getElementById('editVehicleId').value;
    const yearValue = document.getElementById('edit_year_input').value.trim();
    updateSingleVehicleField(vehicleId, 'year', yearValue, 'display_year');
}

function handleRecipientUpdate(event) {
    event.preventDefault();
    const vehicleId = document.getElementById('editVehicleId').value;
    const recipientValue = document.getElementById('edit_recipient_input').value.trim();
    updateSingleVehicleField(vehicleId, 'recipient', recipientValue, 'display_recipient');
}

function handleChassisNumberUpdate(event) {
    event.preventDefault();
    const vehicleId = document.getElementById('editVehicleId').value;
    const chassisValue = document.getElementById('edit_chassis_number_input').value.trim();
    updateSingleVehicleField(vehicleId, 'chassis_number', chassisValue, 'display_chassis_number');
}

function handleEngineNumberUpdate(event) {
    event.preventDefault();
    const vehicleId = document.getElementById('editVehicleId').value;
    const engineValue = document.getElementById('edit_engine_number_input').value.trim();
    updateSingleVehicleField(vehicleId, 'engine_number', engineValue, 'display_engine_number');
}

function handleColorUpdate(event) {
    event.preventDefault();
    const vehicleId = document.getElementById('editVehicleId').value;
    const colorValue = document.getElementById('edit_color_input').value.trim();
    updateSingleVehicleField(vehicleId, 'color', colorValue, 'display_color');
}

// Initialize individual field form handlers
document.addEventListener('DOMContentLoaded', function() {
    // Attach handlers to individual field forms
    const fieldForms = document.querySelectorAll('.field-form');
    fieldForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            // This will be handled by the specific field handlers above
            e.preventDefault();
        });
    });
});

/* 
NEW VEHICLES REPORT: Two-Column Edit Vehicle Function
This function is for vehicles-report.html with two-column editing (readonly + editable)
*/
function editVehicle(vehicleId) {
    console.log('Opening two-column edit modal for vehicle ID:', vehicleId);
    
    const modal = document.getElementById('editVehicleModal');
    if (!modal) {
        console.error("Two-column edit modal not found!");
        showNotification('نافذة التعديل غير موجودة', 'error');
        return;
    }
    
    // Show the modal
    modal.style.display = 'flex';
    
    // Set the vehicle ID
    const editVehicleId = document.getElementById('editVehicleId');
    if (editVehicleId) {
        editVehicleId.value = vehicleId;
    }
    
    // Fetch current vehicle data
    fetch(`pages/get_vehicle_details.php?vehicle_id=${vehicleId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.statusText);
            }
            return response.json();
        })
        .then(data => {
            if (data.success && data.data) {
                const vehicleData = data.data;
                
                console.log('Loaded vehicle data for two-column edit:', vehicleData);
                
                // Store original values for validation
                window.vehicleOriginalValues = {
                    make: vehicleData.make || '',
                    type: vehicleData.type || '',
                    plate_number: vehicleData.plate_number || '',
                    model: vehicleData.model || '',
                    year: vehicleData.year || '',
                    recipient: vehicleData.recipient || '',
                    chassis_number: vehicleData.chassis_number || '',
                    engine_number: vehicleData.engine_number || '',
                    color: vehicleData.color || '',
                    fuel_type: vehicleData.fuel_type || '',
                    monthly_allocations: vehicleData.monthly_allocations || 0,
                    notes: vehicleData.notes || ''
                };
                
                // Populate readonly display fields
                if (document.getElementById('display_make')) {
                    document.getElementById('display_make').textContent = vehicleData.make || 'غير محدد';
                }
                if (document.getElementById('display_type')) {
                    document.getElementById('display_type').textContent = vehicleData.type || 'غير محدد';
                }
                if (document.getElementById('display_plate_number')) {
                    document.getElementById('display_plate_number').textContent = vehicleData.plate_number || 'غير محدد';
                }
                if (document.getElementById('display_model')) {
                    document.getElementById('display_model').textContent = vehicleData.model || 'غير محدد';
                }
                if (document.getElementById('display_year')) {
                    document.getElementById('display_year').textContent = vehicleData.year || 'غير محدد';
                }
                if (document.getElementById('display_recipient')) {
                    document.getElementById('display_recipient').textContent = vehicleData.recipient || 'غير محدد';
                }
                if (document.getElementById('display_chassis_number')) {
                    document.getElementById('display_chassis_number').textContent = vehicleData.chassis_number || 'غير محدد';
                }
                if (document.getElementById('display_engine_number')) {
                    document.getElementById('display_engine_number').textContent = vehicleData.engine_number || 'غير محدد';
                }
                if (document.getElementById('display_color')) {
                    document.getElementById('display_color').textContent = vehicleData.color || 'غير محدد';
                }
                if (document.getElementById('display_fuel_type')) {
                    document.getElementById('display_fuel_type').textContent = vehicleData.fuel_type ? (vehicleData.fuel_type === 'diesel' ? 'مازوت' : 'بنزين') : 'غير محدد';
                }
                if (document.getElementById('display_monthly_allocations')) {
                    document.getElementById('display_monthly_allocations').textContent = vehicleData.monthly_allocations ? parseFloat(vehicleData.monthly_allocations).toFixed(2) : '0.00';
                }
                if (document.getElementById('display_notes')) {
                    document.getElementById('display_notes').textContent = vehicleData.notes || 'لا يوجد';
                }
                
                // Populate editable input fields with current values
                if (document.getElementById('edit_make')) {
                    document.getElementById('edit_make').value = vehicleData.make || '';
                }
                if (document.getElementById('edit_type')) {
                    document.getElementById('edit_type').value = vehicleData.type || '';
                }
                if (document.getElementById('edit_plate_number')) {
                    document.getElementById('edit_plate_number').value = vehicleData.plate_number || '';
                }
                if (document.getElementById('edit_model')) {
                    document.getElementById('edit_model').value = vehicleData.model || '';
                }
                if (document.getElementById('edit_year')) {
                    document.getElementById('edit_year').value = vehicleData.year || '';
                }
                if (document.getElementById('edit_recipient')) {
                    document.getElementById('edit_recipient').value = vehicleData.recipient || '';
                }
                if (document.getElementById('edit_chassis_number')) {
                    document.getElementById('edit_chassis_number').value = vehicleData.chassis_number || '';
                }
                if (document.getElementById('edit_engine_number')) {
                    document.getElementById('edit_engine_number').value = vehicleData.engine_number || '';
                }
                if (document.getElementById('edit_color')) {
                    document.getElementById('edit_color').value = vehicleData.color || '';
                }
                if (document.getElementById('edit_fuel_type')) {
                    document.getElementById('edit_fuel_type').value = vehicleData.fuel_type || '';
                }
                if (document.getElementById('edit_monthly_allocations')) {
                    document.getElementById('edit_monthly_allocations').value = vehicleData.monthly_allocations || '';
                }
                if (document.getElementById('edit_notes')) {
                    document.getElementById('edit_notes').value = vehicleData.notes || '';
                }
                
                // Initially enable the submit button when modal opens with data
                const submitBtn = document.getElementById('updateVehicleBtn');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'تعديل';
                }
                
                // Setup real-time validation for the two-column form
                setupTwoColumnEditValidation();
                
                showNotification('تم تحميل بيانات الآلية للتعديل. قم بتعديل الحقول المطلوبة', 'success');
            } else {
                showNotification('فشل في تحميل بيانات الآلية: ' + (data.message || 'خطأ غير معروف'), 'error');
                closeEditVehicleModal();
            }
        })
        .catch((error) => {
            console.error('Error fetching vehicle data for two-column edit:', error);
            showNotification('حدث خطأ أثناء جلب بيانات الآلية: ' + error.message, 'error');
            closeEditVehicleModal();
        });
}

// Setup real-time validation for two-column edit form
function setupTwoColumnEditValidation() {
    const editFields = ['edit_make', 'edit_type', 'edit_plate_number', 'edit_model', 
                       'edit_year', 'edit_recipient', 'edit_chassis_number', 
                       'edit_engine_number', 'edit_color'];
    
    editFields.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field) {
            field.addEventListener('input', function() {
                updateSubmitButtonState();
            });
        }
    });
}

// Check if any changes have been made and update submit button accordingly
function updateSubmitButtonState() {
    if (!window.vehicleOriginalValues) {
        return;
    }
    
    const currentValues = {
        make: document.getElementById('edit_make')?.value || '',
        type: document.getElementById('edit_type')?.value || '',
        plate_number: document.getElementById('edit_plate_number')?.value || '',
        model: document.getElementById('edit_model')?.value || '',
        year: document.getElementById('edit_year')?.value || '',
        recipient: document.getElementById('edit_recipient')?.value || '',
        chassis_number: document.getElementById('edit_chassis_number')?.value || '',
        engine_number: document.getElementById('edit_engine_number')?.value || '',
        color: document.getElementById('edit_color')?.value || '',
        fuel_type: document.getElementById('edit_fuel_type')?.value || '',
        monthly_allocations: parseFloat(document.getElementById('edit_monthly_allocations')?.value || 0),
        notes: document.getElementById('edit_notes')?.value || ''
    };
    
    let hasChanges = false;
    for (let field in currentValues) {
        if (currentValues[field] !== window.vehicleOriginalValues[field]) {
            hasChanges = true;
            break;
        }
    }
    
    const submitBtn = document.getElementById('updateVehicleBtn');
    if (submitBtn) {
        // Always keep button enabled and show appropriate text
        submitBtn.disabled = false;
        submitBtn.textContent = hasChanges ? 'حفظ التعديلات' : 'تعديل';
    }
}

function handleEditVehicleSubmit(event) {
    event.preventDefault();
    event.stopPropagation();
    
    // Remove the changes check - always allow submission
    // if (!hasChangesBeenMade()) {
    //     showNotification('يرجى تعديل احد البنود قبل الحفظ', 'warning');
    //     return;
    // }
    
    const vehicleId = document.getElementById('editVehicleId').value;
    const submitBtn = document.getElementById('updateVehicleBtn');
    
    // Disable button and show loading
    submitBtn.disabled = true;
    submitBtn.textContent = 'جاري الحفظ...';
    
    // Collect ALL current form values (both original and changed fields)
    const updateData = {
        id: parseInt(vehicleId),
        make: document.getElementById('edit_make')?.value.trim() || '',
        type: document.getElementById('edit_type')?.value.trim() || '',
        plate_number: document.getElementById('edit_plate_number')?.value.trim() || '',
        model: document.getElementById('edit_model')?.value.trim() || '',
        year: parseInt(document.getElementById('edit_year')?.value || 0),
        recipient: document.getElementById('edit_recipient')?.value.trim() || '',
        chassis_number: document.getElementById('edit_chassis_number')?.value.trim() || '',
        engine_number: document.getElementById('edit_engine_number')?.value.trim() || '',
        color: document.getElementById('edit_color')?.value.trim() || '',
        fuel_type: document.getElementById('edit_fuel_type')?.value.trim() || '',
        monthly_allocations: parseFloat(document.getElementById('edit_monthly_allocations')?.value || 0),
        notes: document.getElementById('edit_notes')?.value.trim() || ''
    };
    
    console.log('Sending complete update data:', updateData);
    
    console.log('Sending update data:', updateData);
    
    fetch('pages/update_vehicle.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(updateData)
    })
    .then(response => {
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            return response.text().then(text => {
                console.error('Server returned non-JSON response:', text.substring(0, 200));
                throw new Error('خطأ في الخادم: تم إرجاع رد غير صالح');
            });
        }
        return response.json();
    })
    .then(result => {
        if (result.success) {
            showNotification('تم تحديث بيانات الآلية بنجاح', 'success');
            
            // Update the readonly display fields with new values from form
            Object.keys(updateData).forEach(field => {
                if (field !== 'id') {
                    const displayElement = document.getElementById('display_' + field);
                    if (displayElement) {
                        displayElement.textContent = updateData[field] || 'غير محدد';
                    }
                }
            });
            
            // Update the original values to match the current form values
            window.vehicleOriginalValues = {
                ...window.vehicleOriginalValues,
                ...currentValues
            };
            
            // Update the table row with new data
            if (vehicleId) {
                const rowData = { id: parseInt(vehicleId), ...updateData };
                updateVehicleRowInTable(vehicleId, rowData);
            }
            
            closeEditVehicleModal();
} else {
    showNotification(result.message || 'فشل في تحديث البيانات', 'error');
}
    })
    // .catch(error => {
    //     console.error('Error updating vehicle:', error);
    //     showNotification('حدث خطأ أثناء تحديث الآلية: ' + error.message, 'error');
    // })
    .finally(() => {
        // Re-enable button
        submitBtn.disabled = false;
        updateSubmitButtonState();
    });
}

function hasChangesBeenMade() {
    if (!window.vehicleOriginalValues) {
        return false;
    }
    
    const currentValues = {
        make: document.getElementById('edit_make')?.value || '',
        type: document.getElementById('edit_type')?.value || '',
        plate_number: document.getElementById('edit_plate_number')?.value || '',
        model: document.getElementById('edit_model')?.value || '',
        year: document.getElementById('edit_year')?.value || '',
        recipient: document.getElementById('edit_recipient')?.value || '',
        chassis_number: document.getElementById('edit_chassis_number')?.value || '',
        engine_number: document.getElementById('edit_engine_number')?.value || '',
        color: document.getElementById('edit_color')?.value || '',
        fuel_type: document.getElementById('edit_fuel_type')?.value || '',
        monthly_allocations: parseFloat(document.getElementById('edit_monthly_allocations')?.value || 0),
        notes: document.getElementById('edit_notes')?.value || ''
    };
    
    for (let field in currentValues) {
        if (currentValues[field] !== window.vehicleOriginalValues[field]) {
            return true;
        }
    }
    return false;
}

// Updated closeEditVehicleModal function
function closeEditVehicleModal() {
    const modal = document.getElementById('editVehicleModal');
    if (modal) {
        modal.style.display = 'none';
    }
    
    // Reset form
    const form = document.getElementById('editVehicleForm');
    if (form) {
        form.reset();
    }
    
    // Clear original values
    window.vehicleOriginalValues = null;
    
    // Reset submit button (if modal is reopened, it will be handled by editVehicle)
    const submitBtn = document.getElementById('updateVehicleBtn');
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'يرجى تعديل احد البنود قبل الحفظ';
    }
    
    // Clear display fields
    const displayFields = ['make', 'type', 'plate_number', 'model', 'year', 'recipient', 'chassis_number', 'engine_number', 'color', 'fuel_type', 'monthly_allocations', 'notes'];
    displayFields.forEach(field => {
        const displayElement = document.getElementById('display_' + field);
        if (displayElement) {
            displayElement.textContent = '-';
        }
    });
}

window.editVehicle = editVehicle;
window.handleEditVehicleSubmit = handleEditVehicleSubmit;
window.updateSubmitButtonState = updateSubmitButtonState;
window.hasChangesBeenMade = hasChangesBeenMade;

// Update the specific row in the vehicles table for the new two-column edit system
function updateVehicleRowInTable(vehicleId, vehicleData) {
    // Find the table row containing the edit button with this vehicle ID
    const editButton = document.querySelector(`button[onclick="editVehicle(${vehicleId})"]`);
    if (!editButton) {
        console.warn(`No table row found for vehicle ID: ${vehicleId}`);
        return;
    }
    
    const row = editButton.closest('tr');
    if (!row) {
        console.warn(`No parent row found for edit button of vehicle ID: ${vehicleId}`);
        return;
    }
    
    // Update the cells based on the expected table structure
    // Assuming the table has columns: ID, Make, Type, Plate, Model, Year, Recipient, Chassis, Engine, Color, Actions
    const cells = row.querySelectorAll('td');
    
    // Map data fields to cell indices (adjust based on your actual table structure)
    const cellMapping = {
        1: 'make',           // Second cell (index 1): Make
        2: 'type',           // Third cell: Type  
        3: 'plate_number',   // Fourth cell: Plate Number
        4: 'model',          // Fifth cell: Model
        5: 'year',           // Sixth cell: Year
        6: 'recipient',      // Seventh cell: Recipient
        7: 'chassis_number', // Eighth cell: Chassis Number
        8: 'engine_number',  // Ninth cell: Engine Number
        9: 'color'           // Tenth cell: Color
    };
    
    Object.keys(cellMapping).forEach(cellIndex => {
        const field = cellMapping[cellIndex];
        const cell = cells[parseInt(cellIndex)];
        if (cell && vehicleData[field] !== undefined) {
            cell.textContent = vehicleData[field] || '';
        }
    });
    
    console.log(`Updated table row for vehicle ID: ${vehicleId}`);
}
