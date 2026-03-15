/* ================================================================
   FACULTY DASHBOARD - JAVASCRIPT
   Navigation and UI Interactions for PUP Biñan Faculty Portal
================================================================ */

// ================================================================
// NAVIGATION
// ================================================================

/**
 * Show a specific section and hide others
 * @param {string} sectionName - Name of the section to show
 */
function showSection(sectionName) {
    // Hide all sections
    const sections = document.querySelectorAll('.content-section');
    sections.forEach(section => section.classList.remove('active'));

    // Show selected section
    const targetSection = document.getElementById(`${sectionName}-section`);
    if (targetSection) {
        targetSection.classList.add('active');
    }

    // Update nav active state
    const navItems = document.querySelectorAll('.nav-item');
    navItems.forEach(item => {
        item.classList.remove('active');
        if (item.dataset.section === sectionName) {
            item.classList.add('active');
        }
    });

    // Update page title
    updatePageTitle(sectionName);

    // Close sidebar on mobile after navigation
    if (window.innerWidth <= 1024) {
        document.getElementById('sidebar').classList.remove('active');
    }
}

/**
 * Update page title based on section
 * @param {string} sectionName - Name of the section
 */
function updatePageTitle(sectionName) {
    const titles = {
        'dashboard': 'Faculty Dashboard',
        'my-equipment': 'My Equipment',
        'add-equipment': 'Add New Equipment',
        'proposals': 'My Proposals',
        'borrow-tracking': 'Borrow Tracking',
        'notifications': 'Notifications'
    };

    const pageTitle = document.querySelector('.page-title');
    if (pageTitle && titles[sectionName]) {
        pageTitle.textContent = titles[sectionName];
    }
}

/**
 * Toggle sidebar visibility (mobile)
 */
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('active');
}

// ================================================================
// EQUIPMENT FILTERING & SEARCH
// ================================================================

/**
 * Filter equipment by status
 * @param {string} status - Status to filter by ('all', 'available', 'in-use', 'maintenance')
 */
function filterEquipment(status) {
    const cards = document.querySelectorAll('.equipment-card');
    const filterBtns = document.querySelectorAll('#my-equipment-section .filter-btn');

    // Update active filter button
    filterBtns.forEach(btn => {
        btn.classList.remove('active');
        if (btn.textContent.toLowerCase().includes(status.replace('-', ' ')) ||
            (status === 'all' && btn.textContent.toLowerCase().includes('all'))) {
            btn.classList.add('active');
        }
    });

    // Filter cards
    cards.forEach(card => {
        if (status === 'all') {
            card.style.display = '';
        } else {
            const cardStatus = card.dataset.status;
            card.style.display = cardStatus === status ? '' : 'none';
        }
    });
}

/**
 * Search equipment by name
 * @param {string} searchTerm - Search term
 */
function searchEquipment(searchTerm) {
    const cards = document.querySelectorAll('.equipment-card');
    const term = searchTerm.toLowerCase().trim();

    cards.forEach(card => {
        const name = card.dataset.name || card.querySelector('h3').textContent.toLowerCase();
        card.style.display = name.includes(term) ? '' : 'none';
    });
}

// ================================================================
// PROPOSALS FILTERING
// ================================================================

/**
 * Filter proposals by status
 * @param {string} status - Status to filter by ('all', 'pending', 'approved', 'declined')
 */
function filterProposals(status) {
    const cards = document.querySelectorAll('.proposal-card');
    const filterBtns = document.querySelectorAll('#proposals-section .filter-btn');

    // Update active filter button
    filterBtns.forEach(btn => {
        btn.classList.remove('active');
        if (btn.textContent.toLowerCase().includes(status) ||
            (status === 'all' && btn.textContent.toLowerCase().includes('all'))) {
            btn.classList.add('active');
        }
    });

    // Filter cards
    cards.forEach(card => {
        if (status === 'all') {
            card.style.display = '';
        } else {
            const cardStatus = card.dataset.status;
            card.style.display = cardStatus === status ? '' : 'none';
        }
    });
}

// ================================================================
// BORROW TRACKING FILTERING & SEARCH
// ================================================================

/**
 * Filter borrow records by status
 * @param {string} status - Status to filter by ('all', 'active', 'returned', 'overdue')
 */
function filterBorrows(status) {
    const rows = document.querySelectorAll('#borrowTableBody tr');
    const filterBtns = document.querySelectorAll('#borrow-tracking-section .filter-btn');

    // Update active filter button
    filterBtns.forEach(btn => {
        btn.classList.remove('active');
        if (btn.textContent.toLowerCase().includes(status) ||
            (status === 'all' && btn.textContent.toLowerCase().includes('all'))) {
            btn.classList.add('active');
        }
    });

    // Filter rows
    rows.forEach(row => {
        if (status === 'all') {
            row.style.display = '';
        } else {
            const rowStatus = row.dataset.status;
            row.style.display = rowStatus === status ? '' : 'none';
        }
    });
}

/**
 * Search borrow records
 * @param {string} searchTerm - Search term
 */
function searchBorrows(searchTerm) {
    const rows = document.querySelectorAll('#borrowTableBody tr');
    const term = searchTerm.toLowerCase().trim();

    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(term) ? '' : 'none';
    });
}

// ================================================================
// FORM HANDLING
// ================================================================

/**
 * Handle image upload and preview
 * @param {HTMLInputElement} input - File input element
 */
function handleImageUpload(input) {
    const file = input.files[0];
    const preview = document.getElementById('imagePreview');
    const placeholder = document.getElementById('uploadPlaceholder');

    if (file) {
        const reader = new FileReader();

        reader.onload = function (e) {
            preview.innerHTML = `
                <img src="${e.target.result}" alt="Preview">
                <button type="button" onclick="removeImage()" style="
                    position: absolute;
                    top: 0.5rem;
                    right: 0.5rem;
                    background: rgba(239, 68, 68, 0.9);
                    color: white;
                    border: none;
                    width: 32px;
                    height: 32px;
                    border-radius: 50%;
                    cursor: pointer;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                ">
                    <i class="fas fa-times"></i>
                </button>
            `;
            preview.style.position = 'relative';
            preview.classList.add('active');
            placeholder.style.display = 'none';
        };

        reader.readAsDataURL(file);
    }
}

/**
 * Remove uploaded image
 */
function removeImage() {
    const input = document.getElementById('equipmentImage');
    const preview = document.getElementById('imagePreview');
    const placeholder = document.getElementById('uploadPlaceholder');

    input.value = '';
    preview.innerHTML = '';
    preview.classList.remove('active');
    placeholder.style.display = '';
}

/**
 * Handle form submission
 * @param {Event} event - Form submit event
 */
function handleFormSubmit(event) {
    event.preventDefault();

    // Get form values
    const equipmentName = document.getElementById('equipmentName').value;
    const category = document.getElementById('category').value;
    const quantity = document.getElementById('quantity').value;
    const condition = document.getElementById('condition').value;

    // Show success message (placeholder)
    alert(`Equipment Proposal Submitted!\n\nName: ${equipmentName}\nCategory: ${category}\nQuantity: ${quantity}\nCondition: ${condition}\n\nThis will be sent to admin for approval.\n\n(Backend team will implement actual submission logic)`);

    // Reset form
    document.getElementById('addEquipmentForm').reset();
    removeImage();

    // Navigate to proposals section
    showSection('proposals');
}

/**
 * Reset form
 */
function resetForm() {
    removeImage();
}

// ================================================================
// NOTIFICATIONS
// ================================================================

/**
 * Mark single notification as read
 * @param {HTMLButtonElement} button - Mark as read button
 */
function markAsRead(button) {
    const card = button.closest('.notification-card');
    if (card) {
        card.classList.remove('unread');
        button.style.display = 'none';

        // Update notification badge count
        updateNotificationCount();

        // Show feedback
        showToast('Notification marked as read');
    }
}

/**
 * Mark all notifications as read
 */
function markAllAsRead() {
    const unreadCards = document.querySelectorAll('.notification-card.unread');

    unreadCards.forEach(card => {
        card.classList.remove('unread');
        const btn = card.querySelector('.mark-read-btn');
        if (btn) btn.style.display = 'none';
    });

    // Update notification badge count
    updateNotificationCount();

    // Show feedback
    showToast(`${unreadCards.length} notifications marked as read`);
}

/**
 * Update notification badge count
 */
function updateNotificationCount() {
    const unreadCount = document.querySelectorAll('.notification-card.unread').length;
    const badge = document.querySelector('.nav-item[data-section="notifications"] .badge-danger');
    const notificationDot = document.querySelector('.notification-dot');

    if (badge) {
        if (unreadCount > 0) {
            badge.textContent = unreadCount;
        } else {
            badge.style.display = 'none';
        }
    }

    if (notificationDot) {
        notificationDot.style.display = unreadCount > 0 ? '' : 'none';
    }
}

/**
 * Show toast notification
 * @param {string} message - Toast message
 */
function showToast(message) {
    // Create toast element
    const toast = document.createElement('div');
    toast.style.cssText = `
        position: fixed;
        bottom: 2rem;
        right: 2rem;
        background: rgba(16, 185, 129, 0.95);
        color: white;
        padding: 1rem 1.5rem;
        border-radius: 0.5rem;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.2);
        display: flex;
        align-items: center;
        gap: 0.75rem;
        z-index: 9999;
        animation: slideIn 0.3s ease;
    `;
    toast.innerHTML = `
        <i class="fas fa-check-circle"></i>
        <span>${message}</span>
    `;

    document.body.appendChild(toast);

    // Remove after 3 seconds
    setTimeout(() => {
        toast.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Add animations for toast
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(400px);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOut {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(400px);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);

// ================================================================
// EVENT LISTENERS
// ================================================================

document.addEventListener('DOMContentLoaded', function () {

    // Navigation items click handler
    const navItems = document.querySelectorAll('.nav-item');
    navItems.forEach(item => {
        item.addEventListener('click', function (e) {
            e.preventDefault();
            const section = this.dataset.section;
            if (section) {
                showSection(section);
            }
        });
    });

    // Close sidebar when clicking outside (mobile)
    document.addEventListener('click', function (e) {
        const sidebar = document.getElementById('sidebar');
        const mobileToggle = document.querySelector('.mobile-toggle');

        if (window.innerWidth <= 1024) {
            if (!sidebar.contains(e.target) &&
                !mobileToggle.contains(e.target) &&
                sidebar.classList.contains('active')) {
                sidebar.classList.remove('active');
            }
        }
    });

    // Equipment filter buttons
    const equipmentFilterBtns = document.querySelectorAll('#my-equipment-section .filter-btn');
    equipmentFilterBtns.forEach(btn => {
        btn.addEventListener('click', function () {
            const btnText = this.textContent.toLowerCase();
            if (btnText.includes('all')) {
                filterEquipment('all');
            } else if (btnText.includes('available')) {
                filterEquipment('available');
            } else if (btnText.includes('in use')) {
                filterEquipment('in-use');
            } else if (btnText.includes('maintenance')) {
                filterEquipment('maintenance');
            }
        });
    });

    // Proposals filter buttons
    const proposalFilterBtns = document.querySelectorAll('#proposals-section .filter-btn');
    proposalFilterBtns.forEach(btn => {
        btn.addEventListener('click', function () {
            const btnText = this.textContent.toLowerCase();
            if (btnText.includes('all')) {
                filterProposals('all');
            } else if (btnText.includes('pending')) {
                filterProposals('pending');
            } else if (btnText.includes('approved')) {
                filterProposals('approved');
            } else if (btnText.includes('declined')) {
                filterProposals('declined');
            }
        });
    });

    // Borrow tracking filter buttons
    const borrowFilterBtns = document.querySelectorAll('#borrow-tracking-section .filter-btn');
    borrowFilterBtns.forEach(btn => {
        btn.addEventListener('click', function () {
            const btnText = this.textContent.toLowerCase();
            if (btnText.includes('all')) {
                filterBorrows('all');
            } else if (btnText.includes('active')) {
                filterBorrows('active');
            } else if (btnText.includes('returned')) {
                filterBorrows('returned');
            } else if (btnText.includes('overdue')) {
                filterBorrows('overdue');
            }
        });
    });

    // Initialize notification count
    updateNotificationCount();

    // Smooth scroll behavior
    document.documentElement.style.scrollBehavior = 'smooth';

    console.log('Faculty Dashboard initialized successfully! 🎉');
    console.log('All navigation and UI features are ready.');
    console.log('Backend integration points are marked with alerts.');
});

// ================================================================
// WINDOW RESIZE HANDLER
// ================================================================

window.addEventListener('resize', function () {
    const sidebar = document.getElementById('sidebar');

    // Close sidebar on resize to desktop
    if (window.innerWidth > 1024) {
        sidebar.classList.remove('active');
    }
});

// ================================================================
// HELPER FUNCTIONS
// ================================================================

/**
 * Format date to readable string
 * @param {Date} date - Date object
 * @returns {string} Formatted date string
 */
function formatDate(date) {
    const options = { year: 'numeric', month: 'short', day: 'numeric' };
    return date.toLocaleDateString('en-US', options);
}

/**
 * Calculate days difference
 * @param {Date} date1 - First date
 * @param {Date} date2 - Second date
 * @returns {number} Days difference
 */
function daysDifference(date1, date2) {
    const diffTime = Math.abs(date2 - date1);
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    return diffDays;
}

/**
 * Debounce function for search inputs
 * @param {Function} func - Function to debounce
 * @param {number} wait - Wait time in milliseconds
 * @returns {Function} Debounced function
 */
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// ================================================================
// EXPORT FUNCTIONS (for external use if needed)
// ================================================================

window.facultyDashboard = {
    showSection,
    toggleSidebar,
    filterEquipment,
    searchEquipment,
    filterProposals,
    filterBorrows,
    searchBorrows,
    handleImageUpload,
    handleFormSubmit,
    resetForm,
    markAsRead,
    markAllAsRead,
    showToast
};

console.log('%c Faculty Dashboard Ready! ', 'background: #10b981; color: white; font-size: 16px; font-weight: bold; padding: 10px;');
console.log('%c All UI features are functional. Backend integration points are clearly marked. ', 'color: #059669; font-size: 12px;');