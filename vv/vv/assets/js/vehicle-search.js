document.addEventListener('DOMContentLoaded', function() {
    // Search functionality for Alkazi section
    window.searchCarByCodeOrDriver = function() {
        const searchInput = document.getElementById('alkaziSearchInput');
        const searchResults = document.getElementById('alkaziSearchResults');
        const searchBtn = document.querySelector('#alkaziSearchBtn');
        
        if (!searchInput || !searchResults || !searchBtn) {
            console.error('Search elements not found');
            return;
        }
        
        const searchTerm = searchInput.value.trim();
        
        if (!searchTerm) {
            searchResults.style.display = 'none';
            searchResults.innerHTML = '';
            return;
        }
        
        // Show loading state
        searchBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> البحث...';
        searchBtn.disabled = true;
        searchResults.innerHTML = '<div class="loading">جاري البحث...</div>';
        searchResults.style.display = 'block';
        
        // AJAX request to search endpoint
        fetch('pages/get_vehicle_details.php?search=' + encodeURIComponent(searchTerm))
            .then(response => {
                console.log('Search response status:', response.status);
                return response.text().then(text => {
                    console.log('Raw response:', text);
                    try {
                        const data = JSON.parse(text);
                        console.log('Parsed data:', data);
                        return data;
                    } catch (parseError) {
                        console.error('JSON parse error:', parseError);
                        throw new Error('Invalid JSON response');
                    }
                });
            })
            .then(data => {
                // Reset button state
                searchBtn.innerHTML = '<i class="fas fa-search"></i> بحث';
                searchBtn.disabled = false;
                
                console.log('Processing data - success:', data.success, 'data length:', data.data ? data.data.length : 'no data');
                
                if (data.success && data.data && data.data.length > 0) {
                    // Display search results
                    displaySearchResults(data.data, searchResults);
                    
                    // Auto-select first result for fueling form
                    setTimeout(() => {
                        if (data.data[0]) {
                            window.selectVehicleForFueling(data.data[0]);
                        }
                    }, 100);
                } else if (data.data && data.data.length > 0) {
                    // Handle case where success is false but data exists
                    console.log('Success false but data exists, displaying anyway');
                    displaySearchResults(data.data, searchResults);
                    
                    // Auto-select first result
                    setTimeout(() => {
                        if (data.data[0]) {
                            window.selectVehicleForFueling(data.data[0]);
                        }
                    }, 100);
                } else {
                    // No results found
                    displayNoResults(searchResults, searchTerm);
                }
            })
            .catch(error => {
                console.error('Search error:', error);
                searchBtn.innerHTML = '<i class="fas fa-search"></i> بحث';
                searchBtn.disabled = false;
                searchResults.innerHTML = '<div class="error">حدث خطأ أثناء البحث. يرجى المحاولة مرة أخرى.</div>';
                searchResults.style.display = 'block';
            });
    };

    // Select vehicle for fueling form
    window.selectVehicleForFueling = function(vehicle) {
        // Keep search results visible - do not hide them automatically
        
        // Populate fueling form
        const vehicleCodeInput = document.getElementById('vehicleCodeInput');
        const vehicleIdInput = document.getElementById('vehicle_id');
        const fuelTypeSelect = document.getElementById('fuel_type');
        const driverNameInput = document.getElementById('driver_name');
        const fillDateInput = document.getElementById('fill_date');
        
        if (vehicleCodeInput) {
            vehicleCodeInput.value = vehicle.car_code || '';
        }
        
        if (vehicleIdInput) {
            vehicleIdInput.value = vehicle.id || '';
        }
        
        if (fuelTypeSelect) {
            // Set fuel type based on vehicle
            const fuelTypeMap = {
                'diesel': 'diesel',
                'مازوت': 'diesel',
                'gasoline': 'gasoline',
                'بنزين': 'gasoline'
            };
            const selectedFuelType = fuelTypeMap[vehicle.fuel_type] || 'diesel';
            fuelTypeSelect.value = selectedFuelType;
        }
        
        if (driverNameInput) {
            driverNameInput.value = vehicle.driver_name || '';
        }
        
        if (fillDateInput) {
            // Set today's date
            fillDateInput.value = new Date().toISOString().split('T')[0];
        }
        
        // Enable the form
        const addFillingBtn = document.getElementById('addFillingBtn');
        if (addFillingBtn) {
            addFillingBtn.disabled = false;
        }
        
        // Show success message
        showVehicleSelectedMessage(vehicle.car_code || vehicle.car_name);
    };

    // Show vehicle selected message
    function showVehicleSelectedMessage(vehicleName) {
        // Create temporary success message
        const messageDiv = document.createElement('div');
        messageDiv.className = 'alert alert-success alert-dismissible fade show';
        messageDiv.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        messageDiv.innerHTML = `
            <i class="fas fa-check-circle"></i> تم اختيار السيارة: ${vehicleName}
            <button type="button" class="close" onclick="this.parentElement.remove()">
                <span>&times;</span>
            </button>
        `;
        document.body.appendChild(messageDiv);
        
        // Auto remove after 3 seconds
        setTimeout(() => {
            if (messageDiv.parentElement) {
                messageDiv.remove();
            }
        }, 3000);
    }
    
    // Clear search results
    window.clearAlkaziSearch = function() {
        const searchInput = document.getElementById('alkaziSearchInput');
        const searchResults = document.getElementById('alkaziSearchResults');
        
        if (searchInput) {
            searchInput.value = '';
        }
        
        if (searchResults) {
            searchResults.style.display = 'none';
            searchResults.innerHTML = '';
        }
    };
    
    // Display search results
    function displaySearchResults(vehicles, container) {
        let resultsHtml = '<div class="search-results-header">';
        resultsHtml += `<h4><i class="fas fa-car"></i> تم العثور على ${vehicles.length} نتيجة:</h4>`;
        resultsHtml += '<p>انقر على السيارة لاختيارها وملء نموذج التعبئة تلقائياً</p>';
        resultsHtml += '</div>';
        resultsHtml += '<div class="search-results-content">';
        
        vehicles.forEach((vehicle, index) => {
            resultsHtml += '<div class="vehicle-result-card" onclick="selectVehicleForFueling(' + JSON.stringify(vehicle).replace(/"/g, '"') + ')">';
            resultsHtml += '<div class="vehicle-header">';
            resultsHtml += `<h5><i class="fas fa-tag"></i> ${vehicle.car_code || 'غير محدد'}</h5>`;
            resultsHtml += `<span class="vehicle-status active">مُسجلة</span>`;
            resultsHtml += '</div>';
            resultsHtml += '<div class="vehicle-details-grid">';
            
            // Basic info row
            resultsHtml += '<div class="detail-row">';
            resultsHtml += `<div class="detail-item"><strong>اسم السيارة:</strong> ${vehicle.car_name || 'غير محدد'}</div>`;
            resultsHtml += `<div class="detail-item"><strong>اسم السائق:</strong> ${vehicle.driver_name || 'غير محدد'}</div>`;
            resultsHtml += '</div>';
            
            // License and department
            resultsHtml += '<div class="detail-row">';
            resultsHtml += `<div class="detail-item"><strong>رقم اللوحة:</strong> ${vehicle.license_plate || 'غير محدد'}</div>`;
            resultsHtml += `<div class="detail-item"><strong>القسم:</strong> ${vehicle.department || 'غير محدد'}</div>`;
            resultsHtml += '</div>';
            
            // Fuel and year
            resultsHtml += '<div class="detail-row">';
            resultsHtml += `<div class="detail-item"><strong>نوع الوقود:</strong> ${getFuelTypeText(vehicle.fuel_type)}</div>`;
            resultsHtml += `<div class="detail-item"><strong>سنة الصنع:</strong> ${vehicle.year || 'غير محدد'}</div>`;
            resultsHtml += '</div>';
            
            // Additional details
            if (vehicle.chassis_number || vehicle.engine_number || vehicle.color) {
                resultsHtml += '<div class="detail-row">';
                if (vehicle.chassis_number) {
                    resultsHtml += `<div class="detail-item"><strong>رقم الشاسيه:</strong> ${vehicle.chassis_number}</div>`;
                }
                if (vehicle.engine_number) {
                    resultsHtml += `<div class="detail-item"><strong>رقم المحرك:</strong> ${vehicle.engine_number}</div>`;
                }
                if (vehicle.color) {
                    resultsHtml += `<div class="detail-item"><strong>اللون:</strong> ${vehicle.color}</div>`;
                }
                resultsHtml += '</div>';
            }
            
            // Allocations and notes
            if (vehicle.monthly_allocations || vehicle.notes) {
                resultsHtml += '<div class="detail-row">';
                if (vehicle.monthly_allocations) {
                    resultsHtml += `<div class="detail-item"><strong>المخصصات الشهرية:</strong> ${parseFloat(vehicle.monthly_allocations).toLocaleString()} ل.س</div>`;
                }
                if (vehicle.notes) {
                    resultsHtml += `<div class="detail-item"><strong>ملاحظات:</strong> ${vehicle.notes}</div>`;
                }
                resultsHtml += '</div>';
            }
            
            resultsHtml += '</div>'; // End vehicle-details-grid
            resultsHtml += '<div class="select-indicator">';
            resultsHtml += '<i class="fas fa-mouse-pointer"></i> انقر للاختيار';
            resultsHtml += '</div>';
            resultsHtml += '</div>'; // End vehicle-result-card
        });
        
        resultsHtml += '</div>'; // End search-results-content
        
        container.innerHTML = resultsHtml;
    }
    
    // Display no results message
    function displayNoResults(container, searchTerm) {
        container.innerHTML = `
            <div class="no-results">
                <i class="fas fa-search fa-3x" style="color: #6c757d; margin-bottom: 15px;"></i>
                <h4>لا توجد نتائج</h4>
                <p>لم يتم العثور على سيارة بهذا الكود أو اسم السائق: "<strong>${searchTerm}</strong>"</p>
                <p>يرجى التحقق من الكود أو اسم السائق والمحاولة مرة أخرى.</p>
            </div>
        `;
    }
    
    // Helper function to get fuel type text in Arabic
    function getFuelTypeText(fuelType) {
        switch(fuelType) {
            case 'diesel':
                return 'مازوت';
            case 'gasoline':
                return 'بنزين';
            default:
                return fuelType || 'غير محدد';
        }
    }
    
    // Enter key support for search input
    function setupSearchInput() {
        const searchInput = document.getElementById('alkaziSearchInput');
        if (searchInput) {
            searchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    window.searchCarByCodeOrDriver();
                }
            });
        }
    }
    
    // Initialize when DOM is ready
    setupSearchInput();
});
