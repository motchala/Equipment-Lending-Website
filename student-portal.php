<?php
session_start();
$borrow_disabled = ($_SESSION['borrow_code_attempts'] ?? 0) >= 3;
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PUPSync | Student Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@400;600;700&family=Inter:wght@400;500;600&display=swap"
        rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="CSS/student-portal.css">
</head>

<body>

    <!-- Top Bar — Minimal, no user account -->
    <header class="student-topbar">
        <div class="student-brand">
            <div class="student-brand-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polygon points="12 2 2 7 12 12 22 7 12 2" />
                    <polyline points="2 17 12 22 22 17" />
                    <polyline points="2 12 12 17 22 12" />
                </svg>
            </div>
            <div>
                <div class="student-brand-title"><strong>PUP</strong>SYNC</div>
                <div class="student-brand-sub">Student Services</div>
            </div>
        </div>
        <a href="landing-page.php" class="student-back-link">
            <span class="material-symbols-outlined">arrow_back</span>
            Back to Portal
        </a>
    </header>

    <!-- Main Content — Centered Choice Cards -->
    <main class="student-main">

        <!-- Hero Text -->
        <div class="student-hero">
            <h1>What do you need today?</h1>
            <p>Select a service to get started. No account required.</p>
        </div>

        <!-- Choice Cards — Large, Visual, Distinct from Faculty UI -->
        <div class="student-choices">

            <!-- Borrow Equipment -->
            <div class="student-choice-card" 
                data-action="borrow" 
                <?php echo $borrow_disabled ? '' : 'data-bs-toggle="modal" data-bs-target="#facultyCodeModal"'; ?>
                style="cursor:<?php echo $borrow_disabled ? 'not-allowed' : 'pointer'; ?>; 
                              <?php echo $borrow_disabled ? 'opacity:0.45; pointer-events:none;' : ''; ?>">
                <div class="choice-glow borrow-glow"></div>
                <div class="choice-icon-wrap borrow-icon">
                    <span class="material-symbols-outlined">inventory_2</span>
                </div>
                <h2>Borrow Equipment</h2>
                <p>Browse available laptops, projectors, lab equipment, and more. Submit a request for your class or
                    project.</p>
                <div class="choice-meta">
                    <span class="choice-badge">
                        <span class="material-symbols-outlined">check_circle</span>
                        Instant Request
                    </span>
                </div>
                <div class="choice-arrow">
                    <span class="material-symbols-outlined">arrow_forward</span>
                </div>
            </div>

            <!-- Reserve a Room -->
            <div class="student-choice-card" data-bs-toggle="modal" data-bs-target="#facultyCodeModal"
                data-action="room" style="cursor:pointer;">
                <div class="choice-glow room-glow"></div>
                <div class="choice-icon-wrap room-icon">
                    <span class="material-symbols-outlined">meeting_room</span>
                </div>
                <h2>Reserve a Room</h2>
                <p>Book lecture halls, computer labs, or study rooms for your group meetings and presentations.</p>
                <div class="choice-meta">
                    <span class="choice-badge">
                        <span class="material-symbols-outlined">event_available</span>
                        Real-time Availability
                    </span>
                </div>
                <div class="choice-arrow">
                    <span class="material-symbols-outlined">arrow_forward</span>
                </div>
            </div>

        </div>

        <!-- Quick Info Footer -->
        <div class="student-info-footer">
            <div class="info-item">
                <span class="material-symbols-outlined">schedule</span>
                <span>Mon – Fri · 7:00 AM – 5:00 PM</span>
            </div>
            <div class="info-divider"></div>
            <div class="info-item">
                <span class="material-symbols-outlined">location_on</span>
                <span>PUP Biñan Campus, Laguna</span>
            </div>
            <div class="info-divider"></div>
            <div class="info-item">
                <span class="material-symbols-outlined">help</span>
                <span>Visit the Admin Office for assistance</span>
            </div>
        </div>

    </main>

    <!-- Faculty Code Modal -->
    <div class="modal fade" id="facultyCodeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content"
                style="border-radius:20px; border:none; box-shadow:0 10px 40px rgba(128,0,0,0.2);">

                <!-- Modal Header -->
                <div class="modal-header"
                    style="background:linear-gradient(135deg, #800000 0%, #5a0000 100%); border-radius:20px 20px 0 0; border-bottom:none; padding:24px 28px;">
                    <div>
                        <h5 class="modal-title"
                            style="color:#fff; font-family:var(--font-display); font-weight:700; font-size:1.25rem; margin-bottom:4px;">
                            Faculty Authorization Required
                        </h5>
                        <p style="color:rgba(255,255,255,0.8); margin:0; font-size:0.875rem;" id="modalSubtitle">
                            Enter your faculty's code to proceed
                        </p>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"
                        style="opacity:0.8;"></button>
                </div>

                <!-- Modal Body -->
                <div class="modal-body" style="padding:32px 28px 24px; background:var(--color-surface);">

                    <!-- Faculty Code Input -->
                    <div class="mb-4">
                        <div class="input-group">
                            <span class="input-group-text"
                                style="background:var(--color-surface-container); border-color:var(--color-outline-variant); color:var(--color-primary);">
                                <span class="material-symbols-outlined" style="font-size:20px;">key</span>
                            </span>
                            <input type="text" class="form-control form-control-lg" id="facultyCode"
                                placeholder="Enter faculty code"
                                style="background:var(--color-surface-container); border-color:var(--color-outline-variant); color:var(--color-on-surface); font-size:1rem; padding:12px 16px;"
                                autocomplete="off">
                        </div>
                        <div class="form-text" style="color:var(--color-secondary); font-size:0.8rem; margin-top:8px;">
                            Ask your faculty adviser or instructor for their code.
                        </div>
                    </div>

                    <!-- Student Name -->
                    <div class="mb-3">
                        <label for="studentName" class="form-label"
                            style="font-weight:600; color:var(--color-on-surface); font-size:0.875rem;">
                            Your Name
                        </label>
                        <input type="text" class="form-control" id="studentName" placeholder="Juan Dela Cruz"
                            style="background:var(--color-surface-container); border-color:var(--color-outline-variant); color:var(--color-on-surface); padding:10px 14px;">
                    </div>

                    <!-- Student ID -->
                    <div class="mb-3">
                        <label for="studentId" class="form-label"
                            style="font-weight:600; color:var(--color-on-surface); font-size:0.875rem;">
                            Student ID
                        </label>
                        <input type="text" class="form-control" id="studentId" placeholder="20XX-XXXXX-BN-X"
                            style="background:var(--color-surface-container); border-color:var(--color-outline-variant); color:var(--color-on-surface); padding:10px 14px;">
                    </div>

                    <!-- Hidden action type -->
                    <input type="hidden" id="actionType" value="">

                </div>

                <!-- Modal Footer -->
                <div class="modal-footer"
                    style="border-top:1px solid var(--color-outline-variant); padding:20px 28px 28px; background:var(--color-surface); border-radius:0 0 20px 20px;">
                    <button type="button" class="btn" data-bs-dismiss="modal"
                        style="padding:10px 24px; border-radius:12px; border:1px solid var(--color-outline-variant); color:var(--color-secondary); font-weight:600; background:transparent;">
                        Cancel
                    </button>
                    <button type="button" class="btn" id="btnVerifyCode"
                        style="padding:10px 28px; border-radius:12px; background:linear-gradient(135deg, #800000 0%, #5a0000 100%); color:#fff; font-weight:700; border:none;">
                        <span class="material-symbols-outlined"
                            style="font-size:18px; vertical-align:middle; margin-right:6px;">verified</span>
                        Verify & Continue
                    </button>
                </div>

            </div>
        </div>
    </div>

    <!-- Decorative Background Elements -->
    <div class="student-bg-pattern"></div>
    <div class="student-bg-blob blob-1"></div>
    <div class="student-bg-blob blob-2"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        const facultyCodeModal = document.getElementById('facultyCodeModal');
        const actionTypeInput = document.getElementById('actionType');
        const modalSubtitle = document.getElementById('modalSubtitle');

        // Update modal content based on which card was clicked
        facultyCodeModal.addEventListener('show.bs.modal', function (event) {
            const card = event.relatedTarget;
            const action = card.getAttribute('data-action');
            actionTypeInput.value = action;

            if (action === 'borrow') {
                modalSubtitle.textContent = 'Enter your faculty\'s code to borrow equipment';
            } else if (action === 'room') {
                modalSubtitle.textContent = 'Enter your faculty\'s code to reserve a room';
            }
        });

        // Verify button handler        // Verify button handler — real backend verification
        document.getElementById('btnVerifyCode').addEventListener('click', function() {
            const code = document.getElementById('facultyCode').value.trim();
            const name = document.getElementById('studentName').value.trim();
            const id = document.getElementById('studentId').value.trim();
            const action = actionTypeInput.value;
            
            if (!code) {
                alert('Please enter a faculty code.');
                return;
            }
            if (!name || !id) {
                alert('Please fill in your name and student ID.');
                return;
            }
            
            const btn = this;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm" style="width:16px;height:16px;margin-right:8px;"></span> Verifying...';
            
            fetch('includes/faculty-functions/verify-faculty-code.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'faculty_code=' + encodeURIComponent(code) + 
                      '&student_name=' + encodeURIComponent(name) + 
                      '&student_id=' + encodeURIComponent(id) + 
                      '&action=' + encodeURIComponent(action)
            })
            .then(r => r.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = '<span class="material-symbols-outlined" style="font-size:18px; vertical-align:middle; margin-right:6px;">verified</span> Verify & Continue';
                
                if (data.attempts_exceeded) {
                    const borrowCard = document.querySelector('[data-action="borrow"]');
                    if (borrowCard) {
                        borrowCard.removeAttribute('data-bs-toggle');
                        borrowCard.removeAttribute('data-bs-target');
                        borrowCard.style.opacity = '0.45';
                        borrowCard.style.pointerEvents = 'none';
                        borrowCard.style.cursor = 'not-allowed';
                    }
                    alert(data.error);
                    return;
                }

                if (data.success) {
                    window.location.href = data.redirect;
                } else {
                    alert(data.error || 'Verification failed.');
                }
            })
            .catch(() => {
                btn.disabled = false;
                btn.innerHTML = '<span class="material-symbols-outlined" style="font-size:18px; vertical-align:middle; margin-right:6px;">verified</span> Verify & Continue';
                alert('Network error. Please try again.');
            });
        });
    </script>
</body>

</html>