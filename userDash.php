<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EQUIPLEND | User Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        :root { --equi-red: #8B0000; }
        body { background-color: #f4f4f4; height: 100vh; display: flex; flex-direction: column; overflow: hidden; }
        
        /* Navigation Header Styling */
        header.navbar-custom { background-color: var(--equi-red); color: white; height: 60px; z-index: 1050; border-bottom: 2px solid rgba(0,0,0,0.1); }
        .menu-toggle { cursor: pointer; font-size: 1.2rem; color: white; border: none; background: none; margin-right: 15px; }

        /* Sidebar Styling */
        aside#sidebar { width: 280px; background: white; border-right: 1px solid #ddd; transition: margin-left 0.3s ease; height: 100%; }
        aside#sidebar.collapsed { margin-left: -280px; }
        .nav-link { color: #444; font-weight: 500; border-left: 5px solid transparent; transition: 0.2s; padding: 15px 25px; }
        .nav-link.active { background: #f8eaea; color: var(--equi-red); border-left-color: var(--equi-red); }

        /* Card & Table Styling */
        article.card { border-radius: 15px; border: none; box-shadow: 0 4px 12px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .table thead th { background-color: #222; color: white; border: none; font-size: 0.85rem; }
        .status-pill { height: 12px; width: 28px; border-radius: 4px; display: inline-block; background-color: #FFB300; }
        
        /* Equipment Browser Grid */
        figure.equipment-card { border: 1px solid #eee; border-radius: 12px; transition: 0.3s; margin: 0; padding: 20px; text-align: center; }
        figure.equipment-card:hover { box-shadow: 0 5px 15px rgba(0,0,0,0.1); transform: translateY(-3px); }

        .hidden { display: none !important; }
        main { flex-grow: 1; overflow-y: auto; padding: 25px; }
    </style>
</head>
<body>
<header class="navbar-custom px-4 d-flex align-items-center justify-content-between">
<div class="d-flex align-items-center">
<button class="menu-toggle" onclick="toggleSidebar()" aria-label="Toggle Sidebar"><i class="fas fa-bars"></i></button>
<h1 class="h5 mb-0 text-white fw-bold">EQUIPLEND <small class="fw-light opacity-75">USER</small></h1>
</div>
<button class="btn btn-outline-light btn-sm"><i class="fas fa-sign-out-alt"></i> Logout</button>
</header>

<div class="d-flex flex-grow-1 overflow-hidden">
<aside id="sidebar">
<nav class="nav flex-column mt-3">
    <button class="nav-link active text-start border-0 w-100 bg-transparent" id="btn-browse" onclick="showSection('browser-section', 'btn-browse')">
        <i class="fas fa-search me-3"></i> Browse Equipment
    </button>
    <button class="nav-link text-start border-0 w-100 bg-transparent" id="btn-status" onclick="showSection('status-section', 'btn-status')">
        <i class="fas fa-clipboard-check me-3"></i> My Requests
    </button>
</nav>
</aside>

<main>
<div id="overdue-alert" class="alert alert-danger shadow-sm hidden" role="alert">
    <i class="fas fa-exclamation-triangle me-2"></i> <strong>Overdue Alert:</strong> Please return your equipment to the laboratory immediately!
</div>
<section id="browser-section">
    <article class="card p-4">
        <h2 class="h5 fw-bold mb-4">Browse Available Equipment</h2>
        <div class="input-group mb-4 shadow-sm" style="max-width: 400px;">
            <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
            <input type="text" id="equipmentSearch" class="form-control border-start-0" placeholder="Search by name..." onkeyup="searchEquipment()">
        </div>

        <div class="row g-4" id="equipmentList">
            <div class="col-md-4 col-lg-3 item-node" data-name="DSLR Camera">
                <figure class="equipment-card card">
                    <i class="fas fa-camera fa-3x mb-3 text-dark"></i>
                    <figcaption>
                        <h3 class="h6 fw-bold">DSLR Camera</h3>
                        <button class="btn btn-success w-100 mt-2" onclick="openForm('DSLR Camera')">Borrow</button>
                    </figcaption>
                </figure>
            </div>
        </div>
    </article>
</section>

<section id="form-section" class="hidden">
    <article class="card shadow mx-auto" style="max-width: 750px;">
        <header class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h2 class="h5 mb-0 fw-bold">Borrowing Form</h2>
            <button class="btn-close" onclick="showSection('browser-section', 'btn-browse')"></button>
        </header>
        <div class="card-body p-4">
            <form id="borrowForm">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label small fw-bold">Full Name</label>
                                          <input type="text" id="stdName" class="form-control" required></div>
                    <div class="col-md-6"><label class="form-label small fw-bold">Student ID</label>
                                          <input type="text" id="stdID" class="form-control" required></div>
                    <div class="col-md-6"><label class="form-label small fw-bold">Year & Section</label><input type="text" id="stdYearSec" class="form-control" required></div>
                    <div class="col-md-6"><label class="form-label small fw-bold">Contact Number</label><input type="tel" id="stdContact" class="form-control" required></div>
                    
                    <div class="col-md-12"><label class="form-label small fw-bold">Professor/Instructor</label><input type="text" id="profInput" class="form-control" placeholder="Manual Input Name" required></div> 
                    <div class="col-md-12"><label class="form-label small fw-bold">Room / Laboratory</label><input type="text" id="roomInput" class="form-control" placeholder="e.g. Room 402 or ComLab 1" required></div> 
                    
                    <div class="col-md-4"><label class="form-label small fw-bold">Equipment</label>
                                          <input type="text" id="selectedItem" class="form-control bg-light" readonly></div>
                    <div class="col-md-4"><label class="form-label small fw-bold">Date Borrowed</label>
                                          <input type="date" id="borrowDate" class="form-control" required></div>
                    <div class="col-md-4"><label class="form-label small fw-bold">Time Borrowed</label>
                                          <input type="time" id="borrowTime" class="form-control" required></div>
                    <div class="col-md-12"><label class="form-label small fw-bold">Return Date</label>
                                          <input type="date" id="returnDate" class="form-control" required></div>
                </div>
                <button type="submit" class="btn btn-danger w-100 fw-bold py-2 mt-4" style="background: var(--equi-red);">Submit Request</button>
            </form>
        </div>
    </article>
</section>

<section id="status-section" class="hidden">
    <article class="card shadow">
        <header class="card-header bg-white py-3">
            <h2 class="h5 mb-0 fw-bold">My Borrowing Status</h2>
        </header>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Student Info</th>
                        <th>Instructor</th>
                        <th>Room</th>
                        <th>Date/Time Borrowed</th>
                        <th>Status</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody id="requestTableBody"></tbody>
            </table>
        </div>
    </article>
</section>
</main>
</div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Set reference for current date to check for overdue items
        const todayStr = new Date().toISOString().split('T')[0];

        // Sidebar Toggle Function
        function toggleSidebar() { document.getElementById('sidebar').classList.toggle('collapsed'); }

        // Switcher Function to hide/show sections
        function showSection(sectionId, btnId) {
            document.querySelectorAll('main > section').forEach(s => s.classList.add('hidden'));
            document.getElementById(sectionId).classList.remove('hidden');
            document.querySelectorAll('.nav-link').forEach(b => b.classList.remove('active'));
            if(btnId) document.getElementById(btnId).classList.add('active');
        }

        // Real-time Search Filter for equipment
        function searchEquipment() {
            let filter = document.getElementById('equipmentSearch').value.toLowerCase();
            document.querySelectorAll('.item-node').forEach(item => {
                let name = item.getAttribute('data-name').toLowerCase();
                item.style.display = name.includes(filter) ? "" : "none";
            });
        }

        // Pre-fills equipment name and opens the form
        function openForm(itemName) {
            document.getElementById('selectedItem').value = itemName;
            // Auto-fill today's date for convenience
            document.getElementById('borrowDate').value = todayStr;
            showSection('form-section', 'btn-browse');
        }

        // Submission
        document.getElementById('borrowForm').onsubmit = function(e) {
            e.preventDefault();
            const data = {
                item: document.getElementById('selectedItem').value,
                name: document.getElementById('stdName').value,
                id: document.getElementById('stdID').value,
                ys: document.getElementById('stdYearSec').value,
                room: document.getElementById('roomInput').value,
                prof: document.getElementById('profInput').value,
                bDate: document.getElementById('borrowDate').value,
                bTime: document.getElementById('borrowTime').value,
                rDate: document.getElementById('returnDate').value,
                contact: document.getElementById('stdContact').value
            };

            // Trigger for Overdue notification
            const isOverdue = data.rDate < todayStr;
            if (isOverdue) document.getElementById('overdue-alert').classList.remove('hidden');

            const tbody = document.getElementById('requestTableBody');
            const row = document.createElement('tr');
            if (isOverdue) row.classList.add('table-danger'); // Highlights row red if overdue

            row.innerHTML = `
                <td><b>${data.item}</b></td>
                <td><small>${data.name}<br>${data.id} | ${data.ys}<br>${data.contact}</small></td>
                <td>${data.prof}</td>
                <td>${data.room}</td>
                <td><small>${data.bDate}<br>${data.bTime}</small></td>
                <td><span class="status-pill"></span> ${isOverdue ? '<b class="text-danger small ms-1">OVERDUE</b>' : ''}</td>
                <td class="text-center">
                    <button class="btn btn-sm btn-outline-danger" onclick="cancelRequest(this)">
                        <i class="fas fa-trash-alt me-1"></i> Cancel
                    </button>
                </td>
            `;
            tbody.appendChild(row);
            this.reset();
            showSection('status-section', 'btn-status');
        };

        // Function for the Cancel button to remove specific rows
        function cancelRequest(btn) {
            if (confirm("Are you sure you want to cancel this borrowing request?")) {
                btn.closest('tr').remove();
                if (!document.querySelector('.table-danger')) document.getElementById('overdue-alert').classList.add('hidden');
            }
        }
    </script>
</body>
</html>