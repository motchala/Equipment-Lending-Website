// Consolidated admin JS for admin-dashboard.php
let inventory = [];
let currentImageData = "https://via.placeholder.com/150?text=No+Photo";

// Sidebar Slide Toggle
function toggleSidebar() {
    const overlay = document.getElementById('ui-overlay');
    document.body.classList.toggle('sidebar-open');
    overlay.classList.toggle('active');
}

// Close sidebar/blur when clicking the blurry area
document.getElementById('ui-overlay').addEventListener('click', () => {
    document.body.classList.remove('sidebar-open');
    document.getElementById('ui-overlay').classList.remove('active');
});

// Navigation Logic
function showSection(sectionId) {
    // Hide All sections
    document.querySelectorAll('.view-section').forEach(s => s.classList.remove('active'));

    // Show Active section
    const target = document.getElementById('sec-' + sectionId);
    if (target) target.classList.add('active');

    // UI Highlighting for Sidebar Buttons
    document.querySelectorAll('.sidebar-btn').forEach(el => el.classList.remove('active'));
    const activeBtn = document.getElementById('link-' + sectionId);
    if (activeBtn) activeBtn.classList.add('active');

    // Auto-close sidebar on mobile after clicking
    if (window.innerWidth < 992) {
        document.body.classList.remove('sidebar-open');
        document.getElementById('ui-overlay').classList.remove('active');
    }
}

function handleLogout() {
    if (confirm("Confirm Logout?")) {
        window.location.href = "logout.php";

    }
}
// CRUD/Utility Functions
function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            document.getElementById('imagePreview').src = e.target.result;
            document.getElementById('imagePreview').style.display = 'block';
            currentImageData = e.target.result;
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function renderInventory() {
    const list = document.getElementById('inventory-list');
    if (!list) return;
    list.innerHTML = inventory.length === 0 ? '<tr><td colspan="6" class="text-center text-muted py-5">Inventory is empty.</td></tr>' : '';
    inventory.forEach((item, index) => {
        list.innerHTML += `
        <tr>
            <td><img src="${item.image}" class="item-img shadow-sm"></td>
            <td class="fw-bold text-maroon">${item.name}</td>
            <td>${item.category}</td>
            <td><span class="badge bg-info text-dark">${item.qty} units</span></td>
            <td><span class="badge ${item.qty > 0 ? 'bg-success' : 'bg-danger'}">${item.qty > 0 ? 'Available' : 'No Stock'}</span></td>
            <td>
                <button class="btn btn-sm btn-outline-primary" onclick="editItem(${index})"><i class="bi bi-pencil"></i></button>
                <button class="btn btn-sm btn-outline-danger" onclick="deleteItem(${index})"><i class="bi bi-trash"></i></button>
            </td>
        </tr>`;
    });
}

function saveItem() {
    const index = document.getElementById('editIndex').value;
    const newItem = {
        name: document.getElementById('itemName').value,
        category: document.getElementById('itemCategory').value,
        qty: document.getElementById('itemQty').value,
        image: currentImageData
    };
    if (index === "") inventory.push(newItem);
    else inventory[index] = newItem;
    renderInventory();
    bootstrap.Modal.getInstance(document.getElementById('itemModal')).hide();
}

function prepareAdd() {
    const form = document.getElementById('itemForm');
    if (form) form.reset();
    const editIndex = document.getElementById('editIndex');
    if (editIndex) editIndex.value = "";
    const img = document.getElementById('imagePreview');
    if (img) img.style.display = 'none';
    currentImageData = "https://via.placeholder.com/150?text=No+Photo";
}

function editItem(index) {
    const item = inventory[index];
    if (!item) return;
    const ei = document.getElementById('editIndex');
    if (ei) ei.value = index;
    const name = document.getElementById('itemName');
    if (name) name.value = item.name;
    const cat = document.getElementById('itemCategory');
    if (cat) cat.value = item.category;
    const qty = document.getElementById('itemQty');
    if (qty) qty.value = item.qty;
    const img = document.getElementById('imagePreview');
    if (img) { img.src = item.image; img.style.display = 'block'; }
    currentImageData = item.image;
    new bootstrap.Modal(document.getElementById('itemModal')).show();
}

function deleteItem(index) { if (confirm("Permanently delete?")) { inventory.splice(index, 1); renderInventory(); } }

function processRequest(id, action) {
    const row = document.getElementById('req-' + id);
    if (!row) return;
    const name = row.cells[1].innerText;
    const item = row.cells[2].innerText;
    const targetList = action === 'approve' ? 'approved-list' : 'declined-list';
    const badge = action === 'approve' ? 'bg-success' : 'bg-danger';

    const target = document.getElementById(targetList);
    if (target) target.innerHTML += `\n        <tr><td>${id}</td><td class="fw-bold">${name}</td><td>${item}</td><td><span class="badge ${badge}">${action.toUpperCase()}</span></td></tr>`;
    row.remove();
}

renderInventory();

// Handle view param / hash mapping
window.addEventListener('DOMContentLoaded', function () {
    const urlParams = new URLSearchParams(window.location.search);
    const viewParam = urlParams.get('view'); // Checks ?view=
    const hash = window.location.hash.replace('#sec-', ''); // Checks #sec-

    // Priority: 1. URL Parameter (from search) 2. Hash (from direct link)
    if (viewParam) {
        // Map 'raw' to 'raw-data' to match your IDs
        const target = viewParam === 'raw' ? 'raw-data' : viewParam;
        showSection(target);
    } else if (hash) {
        showSection(hash);
    }
});

// Image drag/drop and paste handlers
const dropZone = document.getElementById('dropZone');
const fileInput = document.getElementById('itemImageInput');
const preview = document.getElementById('imagePreview');
const removeBtn = document.getElementById('removeImageBtn');

const DEFAULT_IMAGE = 'uploads/default.png';

if (dropZone) {
    // Click to open file picker
    dropZone.addEventListener('click', () => fileInput.click());

    // Drag hover
    ['dragenter', 'dragover'].forEach(e =>
        dropZone.addEventListener(e, ev => {
            ev.preventDefault();
            dropZone.classList.add('border-primary');
        })
    );

    ['dragleave', 'drop'].forEach(e =>
        dropZone.addEventListener(e, ev => {
            ev.preventDefault();
            dropZone.classList.remove('border-primary');
        })
    );

    // Drop image
    dropZone.addEventListener('drop', e => {
        const file = e.dataTransfer.files[0];
        if (file) handleFile(file);
    });
}

if (fileInput) {
    // File picker
    fileInput.addEventListener('change', () => {
        if (fileInput.files[0]) handleFile(fileInput.files[0]);
    });
}

// Paste image
document.addEventListener('paste', e => {
    const item = [...e.clipboardData.items].find(i => i.type.startsWith('image'));
    if (!item) return;
    handleFile(item.getAsFile());
});

// Handle file
function handleFile(file) {
    if (!file.type.startsWith('image/')) {
        alert("Only image files allowed.");
        return;
    }

    const reader = new FileReader();
    reader.onload = e => preview.src = e.target.result;
    reader.readAsDataURL(file);

    const dt = new DataTransfer();
    dt.items.add(file);
    if (fileInput) fileInput.files = dt.files;

    if (removeBtn) removeBtn.classList.remove('d-none');
}

// ❌ RESET IMAGE
if (removeBtn) {
    removeBtn.addEventListener('click', e => {
        e.stopPropagation(); // prevent opening file picker

        if (preview) preview.src = DEFAULT_IMAGE;
        if (fileInput) fileInput.value = ""; // clear file input
        removeBtn.classList.add('d-none');
    });
}
