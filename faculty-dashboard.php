<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Dashboard - PUP Biñan</title>
    <link rel="stylesheet" href="CSS/faculty-dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>

    <!-- Sidebar Navigation -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <i class="fas fa-chalkboard-teacher"></i>
                <span class="logo-text">Faculty Portal</span>
            </div>
            <button class="sidebar-toggle" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
        </div>

        <nav class="sidebar-nav">
            <a href="#" class="nav-item active" data-section="dashboard" onclick="event.preventDefault(); showSection('dashboard');">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="#" class="nav-item" data-section="my-equipment" onclick="event.preventDefault(); showSection('my-equipment');">
                <i class="fas fa-box"></i>
                <span>My Equipment</span>
                <span class="badge">8</span>
            </a>
            <a href="#" class="nav-item" data-section="add-equipment" onclick="event.preventDefault(); showSection('add-equipment');">
                <i class="fas fa-plus-circle"></i>
                <span>Add Equipment</span>
            </a>
            <a href="#" class="nav-item" data-section="proposals" onclick="event.preventDefault(); showSection('proposals');">
                <i class="fas fa-clipboard-list"></i>
                <span>My Proposals</span>
                <span class="badge badge-warning">2</span>
            </a>
            <a href="#" class="nav-item" data-section="borrow-tracking" onclick="event.preventDefault(); showSection('borrow-tracking');">
                <i class="fas fa-exchange-alt"></i>
                <span>Borrow Tracking</span>
                <span class="badge badge-info">5</span>
            </a>
            <a href="#" class="nav-item" data-section="notifications" onclick="event.preventDefault(); showSection('notifications');">
                <i class="fas fa-bell"></i>
                <span>Notifications</span>
                <span class="badge badge-danger">3</span>
            </a>
        </nav>

        <div class="sidebar-footer">
            <a href="landing-page.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">

        <!-- Top Header -->
        <header class="top-header">
            <div class="header-left">
                <button class="mobile-toggle" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </button>
                <h1 class="page-title">Faculty Dashboard</h1>
            </div>
            <div class="header-right">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search equipment...">
                </div>
                <div class="header-icons">
                    <button class="icon-btn" onclick="showSection('notifications')">
                        <i class="fas fa-bell"></i>
                        <span class="notification-dot"></span>
                    </button>
                </div>
                <div class="profile-dropdown">
                    <div class="profile-info">
                        <div class="profile-avatar">
                            <i class="fas fa-user-circle"></i>
                        </div>
                        <div class="profile-text">
                            <span class="profile-name">Dr. Maria Santos</span>
                            <span class="profile-role">Computer Science Dept.</span>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Dashboard Section -->
        <section id="dashboard-section" class="content-section active">
            <div class="welcome-banner">
                <div class="welcome-content">
                    <h2>Welcome back, Dr. Santos!</h2>
                    <p>Here's what's happening with your equipment today</p>
                </div>
                <div class="welcome-icon">
                    <i class="fas fa-hand-wave"></i>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card stat-primary">
                    <div class="stat-icon">
                        <i class="fas fa-box"></i>
                    </div>
                    <div class="stat-content">
                        <h3>8</h3>
                        <p>My Equipment</p>
                        <span class="stat-trend up">
                            <i class="fas fa-arrow-up"></i> 2 new
                        </span>
                    </div>
                </div>

                <div class="stat-card stat-warning">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-content">
                        <h3>2</h3>
                        <p>Pending Proposals</p>
                        <span class="stat-trend">Awaiting approval</span>
                    </div>
                </div>

                <div class="stat-card stat-info">
                    <div class="stat-icon">
                        <i class="fas fa-hand-holding"></i>
                    </div>
                    <div class="stat-content">
                        <h3>5</h3>
                        <p>Active Borrows</p>
                        <span class="stat-trend">In use now</span>
                    </div>
                </div>

                <div class="stat-card stat-success">
                    <div class="stat-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-content">
                        <h3>47</h3>
                        <p>Total Borrows</p>
                        <span class="stat-trend up">
                            <i class="fas fa-arrow-up"></i> 12 this month
                        </span>
                    </div>
                </div>
            </div>

            <!-- Recent Activity Grid -->
            <div class="dashboard-grid">
                <!-- Recent Borrows -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-history"></i> Recent Borrow Requests</h3>
                        <button class="btn-link" onclick="showSection('borrow-tracking')">View All</button>
                    </div>
                    <div class="card-body">
                        <div class="activity-list">
                            <div class="activity-item">
                                <div class="activity-icon status-approved">
                                    <i class="fas fa-check"></i>
                                </div>
                                <div class="activity-content">
                                    <strong>Juan Dela Cruz</strong> borrowed <strong>Wireless Projector</strong>
                                    <span class="activity-meta">
                                        <i class="fas fa-clock"></i> 2 hours ago
                                        <span class="status-badge status-approved">Approved</span>
                                    </span>
                                </div>
                            </div>
                            <div class="activity-item">
                                <div class="activity-icon status-returned">
                                    <i class="fas fa-undo"></i>
                                </div>
                                <div class="activity-content">
                                    <strong>Maria Santos</strong> returned <strong>HDMI Cable</strong>
                                    <span class="activity-meta">
                                        <i class="fas fa-clock"></i> 5 hours ago
                                        <span class="status-badge status-returned">Returned</span>
                                    </span>
                                </div>
                            </div>
                            <div class="activity-item">
                                <div class="activity-icon status-waiting">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="activity-content">
                                    <strong>Pedro Garcia</strong> requested <strong>Microphone Set</strong>
                                    <span class="activity-meta">
                                        <i class="fas fa-clock"></i> 1 day ago
                                        <span class="status-badge status-waiting">Waiting</span>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Stats -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-chart-pie"></i> Equipment Overview</h3>
                    </div>
                    <div class="card-body">
                        <div class="equipment-stats">
                            <div class="equipment-stat-item">
                                <div class="equipment-stat-bar">
                                    <div class="equipment-stat-fill" style="width: 75%"></div>
                                </div>
                                <div class="equipment-stat-label">
                                    <span>Available</span>
                                    <strong>6 items</strong>
                                </div>
                            </div>
                            <div class="equipment-stat-item">
                                <div class="equipment-stat-bar orange">
                                    <div class="equipment-stat-fill" style="width: 62%"></div>
                                </div>
                                <div class="equipment-stat-label">
                                    <span>In Use</span>
                                    <strong>5 items</strong>
                                </div>
                            </div>
                            <div class="equipment-stat-item">
                                <div class="equipment-stat-bar red">
                                    <div class="equipment-stat-fill" style="width: 25%"></div>
                                </div>
                                <div class="equipment-stat-label">
                                    <span>Maintenance</span>
                                    <strong>2 items</strong>
                                </div>
                            </div>
                        </div>
                        <div class="chart-placeholder">
                            <canvas id="equipmentChart"></canvas>
                            <div class="chart-overlay">
                                <i class="fas fa-chart-pie"></i>
                                <p>Usage analytics will appear here</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- My Equipment Section -->
        <section id="my-equipment-section" class="content-section">
            <div class="section-header">
                <div>
                    <h2><i class="fas fa-box"></i> My Equipment</h2>
                    <p class="section-subtitle">Equipment you manage and track</p>
                </div>
                <button class="btn btn-primary" onclick="showSection('add-equipment')">
                    <i class="fas fa-plus"></i> Add New Equipment
                </button>
            </div>

            <div class="filter-bar">
                <div class="filter-group">
                    <button class="filter-btn active" onclick="filterEquipment('all')">All <span class="count">8</span></button>
                    <button class="filter-btn" onclick="filterEquipment('available')">Available <span class="count">6</span></button>
                    <button class="filter-btn" onclick="filterEquipment('in-use')">In Use <span class="count">5</span></button>
                    <button class="filter-btn" onclick="filterEquipment('maintenance')">Maintenance <span class="count">2</span></button>
                </div>
                <div class="search-filter">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search equipment..." onkeyup="searchEquipment(this.value)">
                </div>
            </div>

            <div class="equipment-grid" id="equipmentGrid">
                <!-- Equipment Card 1 -->
                <div class="equipment-card" data-status="available" data-name="wireless projector">
                    <div class="equipment-image">
                        <img src="https://via.placeholder.com/300x200/10b981/ffffff?text=Wireless+Projector" alt="Equipment">
                        <span class="status-badge status-available">Available</span>
                    </div>
                    <div class="equipment-info">
                        <h3>Wireless Projector</h3>
                        <p class="equipment-category">Audio Visual Equipment</p>
                        <div class="equipment-details">
                            <span><i class="fas fa-boxes"></i> Qty: 2</span>
                            <span><i class="fas fa-calendar"></i> Added: Jan 15, 2026</span>
                        </div>
                        <div class="equipment-actions">
                            <button class="btn-action btn-edit" onclick="alert('Edit functionality will be added by backend team')">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button class="btn-action btn-view" onclick="alert('View details functionality will be added by backend team')">
                                <i class="fas fa-eye"></i> Details
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Equipment Card 2 -->
                <div class="equipment-card" data-status="in-use" data-name="hdmi cable">
                    <div class="equipment-image">
                        <img src="https://via.placeholder.com/300x200/3b82f6/ffffff?text=HDMI+Cable" alt="Equipment">
                        <span class="status-badge status-in-use">In Use</span>
                    </div>
                    <div class="equipment-info">
                        <h3>HDMI Cable (10ft)</h3>
                        <p class="equipment-category">Electronics & Accessories</p>
                        <div class="equipment-details">
                            <span><i class="fas fa-boxes"></i> Qty: 5</span>
                            <span><i class="fas fa-user"></i> Borrowed: 3</span>
                        </div>
                        <div class="equipment-actions">
                            <button class="btn-action btn-edit" onclick="alert('Edit functionality will be added by backend team')">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button class="btn-action btn-view" onclick="alert('View details functionality will be added by backend team')">
                                <i class="fas fa-eye"></i> Details
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Equipment Card 3 -->
                <div class="equipment-card" data-status="available" data-name="microphone set">
                    <div class="equipment-image">
                        <img src="https://via.placeholder.com/300x200/8b5cf6/ffffff?text=Microphone+Set" alt="Equipment">
                        <span class="status-badge status-available">Available</span>
                    </div>
                    <div class="equipment-info">
                        <h3>Wireless Microphone Set</h3>
                        <p class="equipment-category">Audio Visual Equipment</p>
                        <div class="equipment-details">
                            <span><i class="fas fa-boxes"></i> Qty: 3</span>
                            <span><i class="fas fa-calendar"></i> Added: Feb 3, 2026</span>
                        </div>
                        <div class="equipment-actions">
                            <button class="btn-action btn-edit" onclick="alert('Edit functionality will be added by backend team')">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button class="btn-action btn-view" onclick="alert('View details functionality will be added by backend team')">
                                <i class="fas fa-eye"></i> Details
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Equipment Card 4 -->
                <div class="equipment-card" data-status="maintenance" data-name="extension cord">
                    <div class="equipment-image">
                        <img src="https://via.placeholder.com/300x200/ef4444/ffffff?text=Extension+Cord" alt="Equipment">
                        <span class="status-badge status-maintenance">Maintenance</span>
                    </div>
                    <div class="equipment-info">
                        <h3>Extension Cord (15m)</h3>
                        <p class="equipment-category">Electronics & Accessories</p>
                        <div class="equipment-details">
                            <span><i class="fas fa-boxes"></i> Qty: 4</span>
                            <span><i class="fas fa-tools"></i> Repair needed</span>
                        </div>
                        <div class="equipment-actions">
                            <button class="btn-action btn-edit" onclick="alert('Edit functionality will be added by backend team')">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button class="btn-action btn-view" onclick="alert('View details functionality will be added by backend team')">
                                <i class="fas fa-eye"></i> Details
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Equipment Card 5 -->
                <div class="equipment-card" data-status="available" data-name="laser pointer">
                    <div class="equipment-image">
                        <img src="https://via.placeholder.com/300x200/14b8a6/ffffff?text=Laser+Pointer" alt="Equipment">
                        <span class="status-badge status-available">Available</span>
                    </div>
                    <div class="equipment-info">
                        <h3>Wireless Laser Pointer</h3>
                        <p class="equipment-category">Audio Visual Equipment</p>
                        <div class="equipment-details">
                            <span><i class="fas fa-boxes"></i> Qty: 4</span>
                            <span><i class="fas fa-calendar"></i> Added: Jan 20, 2026</span>
                        </div>
                        <div class="equipment-actions">
                            <button class="btn-action btn-edit" onclick="alert('Edit functionality will be added by backend team')">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button class="btn-action btn-view" onclick="alert('View details functionality will be added by backend team')">
                                <i class="fas fa-eye"></i> Details
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Equipment Card 6 -->
                <div class="equipment-card" data-status="in-use" data-name="webcam">
                    <div class="equipment-image">
                        <img src="https://via.placeholder.com/300x200/f59e0b/ffffff?text=Webcam" alt="Equipment">
                        <span class="status-badge status-in-use">In Use</span>
                    </div>
                    <div class="equipment-info">
                        <h3>HD Webcam</h3>
                        <p class="equipment-category">Computer Equipment</p>
                        <div class="equipment-details">
                            <span><i class="fas fa-boxes"></i> Qty: 3</span>
                            <span><i class="fas fa-user"></i> Borrowed: 2</span>
                        </div>
                        <div class="equipment-actions">
                            <button class="btn-action btn-edit" onclick="alert('Edit functionality will be added by backend team')">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button class="btn-action btn-view" onclick="alert('View details functionality will be added by backend team')">
                                <i class="fas fa-eye"></i> Details
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Equipment Card 7 -->
                <div class="equipment-card" data-status="available" data-name="document camera">
                    <div class="equipment-image">
                        <img src="https://via.placeholder.com/300x200/6366f1/ffffff?text=Document+Camera" alt="Equipment">
                        <span class="status-badge status-available">Available</span>
                    </div>
                    <div class="equipment-info">
                        <h3>Document Camera</h3>
                        <p class="equipment-category">Audio Visual Equipment</p>
                        <div class="equipment-details">
                            <span><i class="fas fa-boxes"></i> Qty: 2</span>
                            <span><i class="fas fa-calendar"></i> Added: Feb 10, 2026</span>
                        </div>
                        <div class="equipment-actions">
                            <button class="btn-action btn-edit" onclick="alert('Edit functionality will be added by backend team')">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button class="btn-action btn-view" onclick="alert('View details functionality will be added by backend team')">
                                <i class="fas fa-eye"></i> Details
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Equipment Card 8 -->
                <div class="equipment-card" data-status="available" data-name="whiteboard markers">
                    <div class="equipment-image">
                        <img src="https://via.placeholder.com/300x200/ec4899/ffffff?text=Markers" alt="Equipment">
                        <span class="status-badge status-available">Available</span>
                    </div>
                    <div class="equipment-info">
                        <h3>Whiteboard Marker Set</h3>
                        <p class="equipment-category">Office Supplies</p>
                        <div class="equipment-details">
                            <span><i class="fas fa-boxes"></i> Qty: 15</span>
                            <span><i class="fas fa-calendar"></i> Added: Mar 1, 2026</span>
                        </div>
                        <div class="equipment-actions">
                            <button class="btn-action btn-edit" onclick="alert('Edit functionality will be added by backend team')">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button class="btn-action btn-view" onclick="alert('View details functionality will be added by backend team')">
                                <i class="fas fa-eye"></i> Details
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Add Equipment Section -->
        <section id="add-equipment-section" class="content-section">
            <div class="section-header">
                <div>
                    <h2><i class="fas fa-plus-circle"></i> Add New Equipment</h2>
                    <p class="section-subtitle">Propose new equipment to be added to the system</p>
                </div>
            </div>

            <div class="form-card">
                <div class="form-info-banner">
                    <i class="fas fa-info-circle"></i>
                    <p>Submit your equipment proposal here. The admin will review and approve it before adding to inventory.</p>
                </div>

                <form id="addEquipmentForm" class="equipment-form" onsubmit="handleFormSubmit(event)">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="equipmentName">
                                Equipment Name <span class="required">*</span>
                            </label>
                            <input type="text" id="equipmentName" placeholder="e.g., Wireless Projector" required>
                            <span class="form-hint">Enter a clear, descriptive name</span>
                        </div>

                        <div class="form-group">
                            <label for="category">
                                Category <span class="required">*</span>
                            </label>
                            <select id="category" required>
                                <option value="">Select Category</option>
                                <option value="audio-visual">Audio Visual Equipment</option>
                                <option value="electronics">Electronics & Accessories</option>
                                <option value="computer">Computer Equipment</option>
                                <option value="laboratory">Laboratory Equipment</option>
                                <option value="sports">Sports Equipment</option>
                                <option value="office">Office Supplies</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="quantity">
                                Quantity <span class="required">*</span>
                            </label>
                            <input type="number" id="quantity" min="1" value="1" required>
                            <span class="form-hint">Number of items available</span>
                        </div>

                        <div class="form-group">
                            <label for="condition">Equipment Condition</label>
                            <select id="condition">
                                <option value="new">New</option>
                                <option value="excellent">Excellent</option>
                                <option value="good" selected>Good</option>
                                <option value="fair">Fair</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" rows="4" placeholder="Provide detailed information about the equipment, its features, and usage..."></textarea>
                    </div>

                    <div class="form-group">
                        <label for="specifications">Technical Specifications</label>
                        <textarea id="specifications" rows="3" placeholder="Model number, brand, technical specs, etc."></textarea>
                    </div>

                    <div class="form-group">
                        <label>Equipment Image</label>
                        <div class="file-upload-area" id="fileUploadArea">
                            <input type="file" id="equipmentImage" accept="image/*" hidden onchange="handleImageUpload(this)">
                            <div class="upload-placeholder" id="uploadPlaceholder" onclick="document.getElementById('equipmentImage').click()">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <p>Click to upload or drag and drop</p>
                                <span>PNG, JPG, WEBP up to 5MB</span>
                            </div>
                            <div class="image-preview" id="imagePreview"></div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Submit Proposal
                        </button>
                        <button type="reset" class="btn btn-secondary" onclick="resetForm()">
                            <i class="fas fa-redo"></i> Reset Form
                        </button>
                    </div>
                </form>
            </div>
        </section>

        <!-- Proposals Section -->
        <section id="proposals-section" class="content-section">
            <div class="section-header">
                <div>
                    <h2><i class="fas fa-clipboard-list"></i> My Proposals</h2>
                    <p class="section-subtitle">Track your equipment addition requests</p>
                </div>
            </div>

            <div class="filter-bar">
                <div class="filter-group">
                    <button class="filter-btn active" onclick="filterProposals('all')">All <span class="count">5</span></button>
                    <button class="filter-btn" onclick="filterProposals('pending')">Pending <span class="count">2</span></button>
                    <button class="filter-btn" onclick="filterProposals('approved')">Approved <span class="count">2</span></button>
                    <button class="filter-btn" onclick="filterProposals('declined')">Declined <span class="count">1</span></button>
                </div>
            </div>

            <div class="proposals-list" id="proposalsList">
                <!-- Proposal Card 1 - Pending -->
                <div class="proposal-card" data-status="pending">
                    <div class="proposal-image">
                        <img src="https://via.placeholder.com/200x150/f59e0b/ffffff?text=Pending" alt="Proposal">
                    </div>
                    <div class="proposal-content">
                        <div class="proposal-header">
                            <h3>Document Camera</h3>
                            <span class="status-badge status-pending">Pending</span>
                        </div>
                        <p class="proposal-category">Audio Visual Equipment</p>
                        <p class="proposal-description">High-resolution document camera for classroom demonstrations and presentations</p>
                        <div class="proposal-meta">
                            <span><i class="fas fa-calendar"></i> Submitted: Mar 10, 2026</span>
                            <span><i class="fas fa-box"></i> Qty: 2</span>
                        </div>
                    </div>
                </div>

                <!-- Proposal Card 2 - Pending -->
                <div class="proposal-card" data-status="pending">
                    <div class="proposal-image">
                        <img src="https://via.placeholder.com/200x150/f59e0b/ffffff?text=Pending" alt="Proposal">
                    </div>
                    <div class="proposal-content">
                        <div class="proposal-header">
                            <h3>Tablet with Stylus</h3>
                            <span class="status-badge status-pending">Pending</span>
                        </div>
                        <p class="proposal-category">Computer Equipment</p>
                        <p class="proposal-description">Drawing tablet for digital illustration and note-taking demonstrations</p>
                        <div class="proposal-meta">
                            <span><i class="fas fa-calendar"></i> Submitted: Mar 12, 2026</span>
                            <span><i class="fas fa-box"></i> Qty: 1</span>
                        </div>
                    </div>
                </div>

                <!-- Proposal Card 3 - Approved -->
                <div class="proposal-card" data-status="approved">
                    <div class="proposal-image">
                        <img src="https://via.placeholder.com/200x150/10b981/ffffff?text=Approved" alt="Proposal">
                    </div>
                    <div class="proposal-content">
                        <div class="proposal-header">
                            <h3>Wireless Microphone Set</h3>
                            <span class="status-badge status-approved">Approved</span>
                        </div>
                        <p class="proposal-category">Audio Visual Equipment</p>
                        <p class="proposal-description">Professional wireless microphone system for lectures and events</p>
                        <div class="proposal-meta">
                            <span><i class="fas fa-calendar"></i> Approved: Mar 5, 2026</span>
                            <span><i class="fas fa-box"></i> Qty: 3</span>
                        </div>
                        <div class="admin-notes">
                            <strong><i class="fas fa-sticky-note"></i> Admin Notes:</strong>
                            <p>Approved. Equipment has been added to your inventory.</p>
                        </div>
                    </div>
                </div>

                <!-- Proposal Card 4 - Approved -->
                <div class="proposal-card" data-status="approved">
                    <div class="proposal-image">
                        <img src="https://via.placeholder.com/200x150/10b981/ffffff?text=Approved" alt="Proposal">
                    </div>
                    <div class="proposal-content">
                        <div class="proposal-header">
                            <h3>Laser Pointer</h3>
                            <span class="status-badge status-approved">Approved</span>
                        </div>
                        <p class="proposal-category">Audio Visual Equipment</p>
                        <p class="proposal-description">Wireless laser pointer with presentation controls</p>
                        <div class="proposal-meta">
                            <span><i class="fas fa-calendar"></i> Approved: Feb 20, 2026</span>
                            <span><i class="fas fa-box"></i> Qty: 4</span>
                        </div>
                        <div class="admin-notes">
                            <strong><i class="fas fa-sticky-note"></i> Admin Notes:</strong>
                            <p>Good addition for classroom presentations. Approved and added.</p>
                        </div>
                    </div>
                </div>

                <!-- Proposal Card 5 - Declined -->
                <div class="proposal-card" data-status="declined">
                    <div class="proposal-image">
                        <img src="https://via.placeholder.com/200x150/ef4444/ffffff?text=Declined" alt="Proposal">
                    </div>
                    <div class="proposal-content">
                        <div class="proposal-header">
                            <h3>VR Headset</h3>
                            <span class="status-badge status-declined">Declined</span>
                        </div>
                        <p class="proposal-category">Computer Equipment</p>
                        <p class="proposal-description">Virtual reality headset for immersive learning experiences</p>
                        <div class="proposal-meta">
                            <span><i class="fas fa-calendar"></i> Declined: Feb 28, 2026</span>
                            <span><i class="fas fa-box"></i> Qty: 5</span>
                        </div>
                        <div class="admin-notes declined">
                            <strong><i class="fas fa-times-circle"></i> Decline Reason:</strong>
                            <p>Budget constraints. Please resubmit next quarter.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Borrow Tracking Section -->
        <section id="borrow-tracking-section" class="content-section">
            <div class="section-header">
                <div>
                    <h2><i class="fas fa-exchange-alt"></i> Borrow Tracking</h2>
                    <p class="section-subtitle">Monitor who's borrowing your equipment</p>
                </div>
                <button class="btn btn-secondary" onclick="alert('Export functionality will be added by backend team')">
                    <i class="fas fa-download"></i> Export Report
                </button>
            </div>

            <div class="filter-bar">
                <div class="filter-group">
                    <button class="filter-btn active" onclick="filterBorrows('all')">All <span class="count">47</span></button>
                    <button class="filter-btn" onclick="filterBorrows('active')">Active <span class="count">5</span></button>
                    <button class="filter-btn" onclick="filterBorrows('returned')">Returned <span class="count">40</span></button>
                    <button class="filter-btn" onclick="filterBorrows('overdue')">Overdue <span class="count">2</span></button>
                </div>
                <div class="search-filter">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search by student or equipment..." onkeyup="searchBorrows(this.value)">
                </div>
            </div>

            <div class="table-card">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Student ID</th>
                            <th>Equipment</th>
                            <th>Instructor</th>
                            <th>Room</th>
                            <th>Borrow Date</th>
                            <th>Return Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="borrowTableBody">
                        <tr data-status="active">
                            <td><strong>Juan Dela Cruz</strong></td>
                            <td>2023-00234-BN-0</td>
                            <td>Wireless Projector</td>
                            <td>Prof. Reyes</td>
                            <td>A305</td>
                            <td>Mar 15, 2026</td>
                            <td>Mar 16, 2026</td>
                            <td><span class="status-badge status-approved">Active</span></td>
                        </tr>
                        <tr data-status="returned">
                            <td><strong>Maria Santos</strong></td>
                            <td>2023-00156-BN-0</td>
                            <td>HDMI Cable</td>
                            <td>Prof. Garcia</td>
                            <td>B203</td>
                            <td>Mar 14, 2026</td>
                            <td>Mar 14, 2026</td>
                            <td><span class="status-badge status-returned">Returned</span></td>
                        </tr>
                        <tr data-status="overdue">
                            <td><strong>Pedro Garcia</strong></td>
                            <td>2023-00289-BN-0</td>
                            <td>Microphone Set</td>
                            <td>Prof. Lopez</td>
                            <td>E104</td>
                            <td>Mar 13, 2026</td>
                            <td>Mar 15, 2026</td>
                            <td><span class="status-badge status-overdue">Overdue</span></td>
                        </tr>
                        <tr data-status="active">
                            <td><strong>Ana Reyes</strong></td>
                            <td>2023-00345-BN-0</td>
                            <td>Extension Cord</td>
                            <td>Prof. Martinez</td>
                            <td>C201</td>
                            <td>Mar 15, 2026</td>
                            <td>Mar 15, 2026</td>
                            <td><span class="status-badge status-approved">Active</span></td>
                        </tr>
                        <tr data-status="returned">
                            <td><strong>Carlos Mendoza</strong></td>
                            <td>2023-00178-BN-0</td>
                            <td>HDMI Cable</td>
                            <td>Prof. Cruz</td>
                            <td>A101</td>
                            <td>Mar 12, 2026</td>
                            <td>Mar 13, 2026</td>
                            <td><span class="status-badge status-returned">Returned</span></td>
                        </tr>
                        <tr data-status="active">
                            <td><strong>Lisa Torres</strong></td>
                            <td>2023-00412-BN-0</td>
                            <td>Laser Pointer</td>
                            <td>Prof. Santos</td>
                            <td>D102</td>
                            <td>Mar 15, 2026</td>
                            <td>Mar 15, 2026</td>
                            <td><span class="status-badge status-approved">Active</span></td>
                        </tr>
                        <tr data-status="active">
                            <td><strong>Miguel Ramos</strong></td>
                            <td>2023-00567-BN-0</td>
                            <td>Webcam</td>
                            <td>Prof. Diaz</td>
                            <td>B405</td>
                            <td>Mar 14, 2026</td>
                            <td>Mar 16, 2026</td>
                            <td><span class="status-badge status-approved">Active</span></td>
                        </tr>
                        <tr data-status="overdue">
                            <td><strong>Sofia Ramirez</strong></td>
                            <td>2023-00623-BN-0</td>
                            <td>Document Camera</td>
                            <td>Prof. Fernandez</td>
                            <td>A208</td>
                            <td>Mar 11, 2026</td>
                            <td>Mar 13, 2026</td>
                            <td><span class="status-badge status-overdue">Overdue</span></td>
                        </tr>
                        <tr data-status="returned">
                            <td><strong>David Lopez</strong></td>
                            <td>2023-00789-BN-0</td>
                            <td>Microphone Set</td>
                            <td>Prof. Morales</td>
                            <td>C305</td>
                            <td>Mar 10, 2026</td>
                            <td>Mar 11, 2026</td>
                            <td><span class="status-badge status-returned">Returned</span></td>
                        </tr>
                        <tr data-status="active">
                            <td><strong>Elena Cruz</strong></td>
                            <td>2023-00234-BN-0</td>
                            <td>Whiteboard Markers</td>
                            <td>Prof. Rivera</td>
                            <td>E201</td>
                            <td>Mar 15, 2026</td>
                            <td>Mar 16, 2026</td>
                            <td><span class="status-badge status-approved">Active</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Notifications Section -->
        <section id="notifications-section" class="content-section">
            <div class="section-header">
                <div>
                    <h2><i class="fas fa-bell"></i> Notifications</h2>
                    <p class="section-subtitle">Stay updated on your equipment activity</p>
                </div>
                <button class="btn btn-secondary" onclick="markAllAsRead()">
                    <i class="fas fa-check-double"></i> Mark All as Read
                </button>
            </div>

            <div class="notifications-container">
                <!-- Unread Notification -->
                <div class="notification-card unread">
                    <div class="notification-icon icon-success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="notification-content">
                        <h4>Equipment Proposal Approved</h4>
                        <p>Your proposal for "Wireless Microphone Set" has been approved by the admin.</p>
                        <span class="notification-time">
                            <i class="fas fa-clock"></i> 2 hours ago
                        </span>
                    </div>
                    <button class="mark-read-btn" onclick="markAsRead(this)">
                        <i class="fas fa-check"></i>
                    </button>
                </div>

                <!-- Unread Notification -->
                <div class="notification-card unread">
                    <div class="notification-icon icon-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="notification-content">
                        <h4>Equipment Overdue</h4>
                        <p>Pedro Garcia has not returned "Microphone Set". Expected return: Mar 15, 2026</p>
                        <span class="notification-time">
                            <i class="fas fa-clock"></i> 5 hours ago
                        </span>
                    </div>
                    <button class="mark-read-btn" onclick="markAsRead(this)">
                        <i class="fas fa-check"></i>
                    </button>
                </div>

                <!-- Unread Notification -->
                <div class="notification-card unread">
                    <div class="notification-icon icon-info">
                        <i class="fas fa-hand-holding"></i>
                    </div>
                    <div class="notification-content">
                        <h4>New Borrow Request</h4>
                        <p>Juan Dela Cruz has borrowed your "Wireless Projector" for Room A305</p>
                        <span class="notification-time">
                            <i class="fas fa-clock"></i> 1 day ago
                        </span>
                    </div>
                    <button class="mark-read-btn" onclick="markAsRead(this)">
                        <i class="fas fa-check"></i>
                    </button>
                </div>

                <!-- Read Notification -->
                <div class="notification-card">
                    <div class="notification-icon icon-success">
                        <i class="fas fa-undo"></i>
                    </div>
                    <div class="notification-content">
                        <h4>Equipment Returned</h4>
                        <p>Maria Santos has returned "HDMI Cable" in good condition</p>
                        <span class="notification-time">
                            <i class="fas fa-clock"></i> 2 days ago
                        </span>
                    </div>
                </div>

                <!-- Read Notification -->
                <div class="notification-card">
                    <div class="notification-icon icon-danger">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="notification-content">
                        <h4>Proposal Declined</h4>
                        <p>Your proposal for "VR Headset" was declined. Reason: Budget constraints</p>
                        <span class="notification-time">
                            <i class="fas fa-clock"></i> 1 week ago
                        </span>
                    </div>
                </div>

                <!-- More Read Notifications -->
                <div class="notification-card">
                    <div class="notification-icon icon-info">
                        <i class="fas fa-check"></i>
                    </div>
                    <div class="notification-content">
                        <h4>Equipment Added</h4>
                        <p>Your "Laser Pointer" has been successfully added to the inventory system</p>
                        <span class="notification-time">
                            <i class="fas fa-clock"></i> 2 weeks ago
                        </span>
                    </div>
                </div>
            </div>
        </section>

    </main>

    <!-- Critical inline JavaScript for navigation -->
    <script>
        // Show section function - critical for navigation
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

            // Close sidebar on mobile
            if (window.innerWidth <= 1024) {
                document.getElementById('sidebar').classList.remove('active');
            }
        }

        // Toggle sidebar for mobile
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('active');
        }
    </script>

    <script src="JavaScript/faculty-dashboard.js"></script>
</body>

</html>