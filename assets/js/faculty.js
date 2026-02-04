// Faculty Dashboard JavaScript

let currentUser;
let qrRefreshInterval;
let currentSessionId;

// Initialize
(async function init() {
    currentUser = await checkAuth();
    
    if (!currentUser || currentUser.role !== 'faculty') {
        window.location.href = '../index.html';
        return;
    }
    
    // Update UI with user info
    document.getElementById('facultyName').textContent = currentUser.full_name;
    document.getElementById('userEmail').textContent = currentUser.email;
    
    // Setup navigation
    setupNavigation();
    
    // Load active sessions
    loadActiveSessions();
    
    // Auto-refresh active sessions every 10 seconds
    setInterval(loadActiveSessions, 10000);
    
    // Load subjects for start attendance form
    loadSubjects();
    loadTimetableForForm();

    // Set minimum date for scheduling to today and keep day-of-week in sync
    setScheduleDateMin();
    attachScheduleDateSync();
    
    // Setup date input display format handler
    setupDateInputDisplay();
})();

// Setup date input to show placeholder hint for DD/MM/YYYY format
function setupDateInputDisplay() {
    const dateInput = document.getElementById('scheduleDate');
    if (!dateInput) return;
    
    // Show the calendar popup when input is focused
    dateInput.addEventListener('focus', () => {
        dateInput.showPicker?.();
    });
}

// Setup navigation
function setupNavigation() {
    const menuItems = document.querySelectorAll('.menu-item[data-view]');
    
    menuItems.forEach(item => {
        item.addEventListener('click', (e) => {
            e.preventDefault();
            
            // Remove active class from all items
            menuItems.forEach(mi => mi.classList.remove('active'));
            
            // Add active class to clicked item
            item.classList.add('active');
            
            // Get view name
            const viewName = item.getAttribute('data-view');
            
            // Hide all views
            document.querySelectorAll('.view-content').forEach(view => {
                view.classList.add('hidden');
            });
            
            // Show selected view
            document.getElementById(`${viewName}-view`).classList.remove('hidden');
            
            // Load data for the view
            if (viewName === 'sessions') {
                loadActiveSessions();
            } else if (viewName === 'timetable') {
                loadTimetable();
            } else if (viewName === 'schedule-lecture') {
                loadSubjects(); // Ensure subjects are loaded for the schedule form
                loadLecturesSummary(); // Load lectures summary
            } else if (viewName === 'logs') {
                loadSubjectsForModify();
                loadLogs();
            }
        });
    });
}

// Load active sessions
async function loadActiveSessions() {
    try {
        const response = await fetch('../api/faculty/get_active_sessions.php');
        const text = await response.text();
        let data;
        try {
            data = JSON.parse(text);
        } catch (err) {
            console.error('Failed to parse active sessions response:', text);
            showError('Could not load sessions (invalid response). Please re-login.');
            return;
        }

        if (!response.ok) {
            // Show role mismatch if available
            const msg = data.current_role && data.required_role
                ? `Access denied. You are logged in as ${data.current_role}, but this page requires ${data.required_role}.`
                : (data.message || 'Forbidden');
            showError(msg);
            if (response.status === 401 || response.status === 403) {
                setTimeout(() => { window.location.href = '../index.html'; }, 1200);
            }
            return;
        }

        if (data.success) {
            const container = document.getElementById('activeSessions');
            
            if (data.sessions.length > 0) {
                container.innerHTML = data.sessions.map(session => `
                    <div class="card" style="border-left: 4px solid var(--secondary-color);">
                        <h3>${session.subject_code} - ${session.subject_name}</h3>
                        <p><strong>Started:</strong> ${formatDateTime(session.started_at)}</p>
                        <p><strong>QR Scans:</strong> ${session.qr_scan_count}</p>
                        <p><strong>Attendance Marked:</strong> ${session.attendance_count}</p>
                        <div style="margin-top: 15px;">
                            <button class="btn btn-primary btn-sm" onclick="viewSessionDetails(${session.id})">View Details & QR</button>
                            <button class="btn btn-danger btn-sm" onclick="openEndSessionModal(${session.id})">End Session</button>
                        </div>
                    </div>
                `).join('');
            } else {
                container.innerHTML = '<p class="text-center">No active sessions. Start a new attendance session to begin.</p>';
            }
        }
    } catch (error) {
        console.error('Error loading active sessions:', error);
    }
}

// View session details
async function viewSessionDetails(sessionId) {
    currentSessionId = sessionId;
    
    const modal = document.getElementById('sessionModal');
    const detailsContainer = document.getElementById('sessionDetails');
    
    modal.classList.add('show');
    detailsContainer.innerHTML = '<div class="spinner"></div><p class="text-center">Loading...</p>';
    
    try {
        // Load QR code
        const qrResponse = await fetch(`../api/faculty/get_qr.php?session_id=${sessionId}`);
        const qrResponseText = await qrResponse.text();
        let qrData;
        try {
            qrData = JSON.parse(qrResponseText);
        } catch (parseError) {
            console.error('Failed to parse QR response:', qrResponseText);
            detailsContainer.innerHTML = '<p class="error-message">Invalid response from server. Check console for details.</p>';
            return;
        }
        
        if (!qrData.success) {
            detailsContainer.innerHTML = `<p class="error-message">${qrData.message || 'Failed to load QR code'}</p>`;
            return;
        }
        
        // Load attendance list
        const attendanceResponse = await fetch(`../api/faculty/get_session_attendance.php?session_id=${sessionId}`);
        const attendanceData = await attendanceResponse.json();
        
        if (qrData.success && attendanceData.success) {
            let html = `
                <div class="qr-container">
                    <h3>Scan this QR Code</h3>
                    <div class="qr-code-display">
                        <img id="qrImage" src="${qrData.qr_image_url}" alt="QR Code">
                    </div>
                    <div class="qr-timer">
                        Refreshing in: <span id="qrTimer">${qrData.rotation_interval}</span>s
                    </div>
                    <p class="text-light">QR code rotates every ${qrData.rotation_interval} seconds</p>
                </div>
                
                <div style="margin-top: 30px;">
                    <h3>Attendance List</h3>
                    <button class="btn btn-secondary btn-sm" onclick="openManualModal(${sessionId})">Manual Mark</button>
                    <button class="btn btn-primary btn-sm" onclick="refreshAttendanceList(${sessionId})">Refresh</button>
            `;
            
            if (attendanceData.attendance.length > 0) {
                html += `
                    <table>
                        <thead>
                            <tr>
                                <th>Student Name</th>
                                <th>Student ID</th>
                                <th>Status</th>
                                <th>Marked At</th>
                                <th>Method</th>
                            </tr>
                        </thead>
                        <tbody id="attendanceListBody">
                            ${attendanceData.attendance.map(record => `
                                <tr>
                                    <td>${record.full_name}</td>
                                    <td>${record.student_number}</td>
                                    <td>${createStatusBadge(record.status)}</td>
                                    <td>${formatDateTime(record.marked_at)}</td>
                                    <td>${createStatusBadge(record.marked_method)}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                `;
            } else {
                html += '<p class="text-center mt-20">No attendance marked yet</p>';
            }
            
            html += '</div>';
            detailsContainer.innerHTML = html;

            // Render QR locally to avoid external image dependency
            await renderQrImage(qrData.qr_data, qrData.qr_image_url);
            
            // Start QR refresh timer
            startQRRefresh(sessionId, qrData.rotation_interval);
        } else {
            // Handle API errors
            let errorMsg = 'Failed to load session details';
            if (!qrData.success) {
                errorMsg = qrData.message || errorMsg;
            } else if (!attendanceData.success) {
                errorMsg = attendanceData.message || errorMsg;
            }
            detailsContainer.innerHTML = `<p class="error-message">${errorMsg}</p>`;
        }
    } catch (error) {
        console.error('Error loading session details:', error);
        detailsContainer.innerHTML = '<p class="error-message">Failed to load session details: ' + error.message + '</p>';
    }
}

// Render QR locally with remote fallback to avoid missing images
async function renderQrImage(qrDataString, fallbackUrl) {
    const qrImage = document.getElementById('qrImage');
    if (!qrImage) {
        console.error('QR image element not found');
        return;
    }

    // Try local QR generation first (using QRCode library from CDN)
    if (window.QRCode && typeof window.QRCode.toDataURL === 'function') {
        try {
            const dataUrl = await window.QRCode.toDataURL(qrDataString, { 
                width: 300, 
                margin: 2,
                color: {
                    dark: '#000000',
                    light: '#FFFFFF'
                }
            });
            qrImage.src = dataUrl;
            qrImage.style.display = 'block';
            console.log('QR code generated locally');
            return;
        } catch (err) {
            console.error('Local QR generation failed, falling back to remote image:', err);
        }
    } else {
        console.warn('QRCode library not available, using remote image');
    }

    // Fallback to remote QR image
    if (fallbackUrl) {
        qrImage.src = fallbackUrl;
        qrImage.style.display = 'block';
        qrImage.onerror = function() {
            console.error('Failed to load remote QR image');
            qrImage.alt = 'QR code failed to load';
            qrImage.style.display = 'none';
        };
    } else {
        console.error('No QR image URL provided');
        qrImage.alt = 'QR unavailable';
        qrImage.style.display = 'none';
    }
}

// Start QR refresh timer
function startQRRefresh(sessionId, interval) {
    // Clear existing interval if any
    if (qrRefreshInterval) {
        clearInterval(qrRefreshInterval);
    }
    
    let timeLeft = interval;
    const timerElement = document.getElementById('qrTimer');
    
    qrRefreshInterval = setInterval(async () => {
        timeLeft--;
        if (timerElement) {
            timerElement.textContent = timeLeft;
        }
        
        if (timeLeft <= 0) {
            // Refresh QR code
            try {
                const response = await fetch(`../api/faculty/get_qr.php?session_id=${sessionId}`);
                const data = await response.json();
                
                if (data.success && data.qr_data) {
                    await renderQrImage(data.qr_data, data.qr_image_url);
                } else {
                    console.error('Failed to refresh QR:', data.message || 'Unknown error');
                }
            } catch (error) {
                console.error('Error refreshing QR:', error);
            }
            
            timeLeft = interval;
        }
    }, 1000);
}

// Close session modal
function closeSessionModal() {
    const modal = document.getElementById('sessionModal');
    modal.classList.remove('show');
    
    // Clear QR refresh interval
    if (qrRefreshInterval) {
        clearInterval(qrRefreshInterval);
    }
}

// Refresh attendance list
async function refreshAttendanceList(sessionId) {
    try {
        const response = await fetch(`../api/faculty/get_session_attendance.php?session_id=${sessionId}`);
        const data = await response.json();
        
        if (data.success) {
            const tbody = document.getElementById('attendanceListBody');
            if (tbody && data.attendance.length > 0) {
                tbody.innerHTML = data.attendance.map(record => `
                    <tr>
                        <td>${record.full_name}</td>
                        <td>${record.student_number}</td>
                        <td>${createStatusBadge(record.status)}</td>
                        <td>${formatDateTime(record.marked_at)}</td>
                        <td>${createStatusBadge(record.marked_method)}</td>
                    </tr>
                `).join('');
            }
            showSuccess('Attendance list refreshed');
        }
    } catch (error) {
        console.error('Error refreshing attendance:', error);
        showError('Failed to refresh attendance list');
    }
}

// Open manual attendance modal
function openManualModal(sessionId) {
    document.getElementById('manualSessionId').value = sessionId;
    document.getElementById('manualAttendanceModal').classList.add('show');
}

// Close manual attendance modal
function closeManualModal() {
    document.getElementById('manualAttendanceModal').classList.remove('show');
    document.getElementById('manualAttendanceForm').reset();
}

// Manual attendance form handler
document.getElementById('manualAttendanceForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const data = {
        session_id: parseInt(formData.get('session_id')),
        student_id: parseInt(formData.get('student_id')),
        status: formData.get('status'),
        reason: formData.get('reason')
    };
    
    try {
        const response = await fetch('../api/faculty/mark_manual_attendance.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showSuccess(result.message);
            closeManualModal();
            refreshAttendanceList(data.session_id);
        } else {
            showError(result.message);
        }
    } catch (error) {
        console.error('Error marking manual attendance:', error);
        showError('Failed to mark attendance');
    }
});

// Open end session modal
async function openEndSessionModal(sessionId) {
    document.getElementById('endSessionId').value = sessionId;
    
    try {
        const response = await fetch(`../api/faculty/get_active_sessions.php`);
        const data = await response.json();
        
        if (data.success) {
            const session = data.sessions.find(s => s.id === sessionId);
            if (session) {
                document.getElementById('endSessionInfo').innerHTML = `
                    <p><strong>Subject:</strong> ${session.subject_name}</p>
                    <p><strong>QR Scan Count:</strong> ${session.qr_scan_count}</p>
                    <p><strong>Attendance Marked:</strong> ${session.attendance_count}</p>
                `;
            }
        }
    } catch (error) {
        console.error('Error loading session info:', error);
    }
    
    document.getElementById('endSessionModal').classList.add('show');
}

// Close end session modal
function closeEndModal() {
    document.getElementById('endSessionModal').classList.remove('show');
    document.getElementById('endSessionForm').reset();
}

// End session form handler
document.getElementById('endSessionForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const data = {
        session_id: parseInt(formData.get('session_id')),
        physical_headcount: parseInt(formData.get('physical_headcount'))
    };
    
    try {
        const response = await fetch('../api/faculty/end_attendance.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });
        
        // Get response as text first to check for errors
        const responseText = await response.text();
        let result;
        
        try {
            result = JSON.parse(responseText);
        } catch (parseError) {
            console.error('Failed to parse JSON response:', parseError);
            console.error('Response text:', responseText);
            showError('Server returned invalid response. Check console for details.');
            return;
        }
        
        if (result.success) {
            if (result.session.count_mismatch) {
                showError(result.session.mismatch_message);
            } else {
                showSuccess('Session ended successfully. Counts match!');
            }
            
            closeEndModal();
            closeSessionModal();
            
            // Reload active sessions to update the list
            loadActiveSessions();
        } else {
            showError(result.message || 'Failed to end session');
        }
    } catch (error) {
        console.error('Error ending session:', error);
        showError('Failed to end session: ' + error.message);
    }
});

// Load subjects for form
async function loadSubjects() {
    try {
        const response = await fetch('../api/get_subjects.php');
        const data = await response.json();
        
        if (data.success) {
            const selectStart = document.getElementById('subjectSelect');
            const selectSchedule = document.getElementById('scheduleSubject');
            
            const subjectOptions = '<option value="">Select a subject</option>' +
                data.subjects.map(subject => 
                    `<option value="${subject.id}">${subject.subject_code} - ${subject.subject_name}</option>`
                ).join('');
            
            if (selectStart) selectStart.innerHTML = subjectOptions;
            if (selectSchedule) selectSchedule.innerHTML = subjectOptions;
        }
    } catch (error) {
        console.error('Error loading subjects:', error);
    }
}

// Set minimum lecture date to today to prevent scheduling past dates
function setScheduleDateMin() {
    const dateInput = document.getElementById('scheduleDate');
    if (!dateInput) return;
    const today = new Date();
    const yyyy = today.getFullYear();
    const mm = String(today.getMonth() + 1).padStart(2, '0');
    const dd = String(today.getDate()).padStart(2, '0');
    const todayStr = `${yyyy}-${mm}-${dd}`;
    dateInput.min = todayStr;
    // Prefill with today to avoid missing date submissions
    if (!dateInput.value) {
        dateInput.value = todayStr;
    }
}

// Keep day-of-week dropdown in sync with chosen date
function attachScheduleDateSync() {
    const dateInput = document.getElementById('scheduleDate');
    const daySelect = document.getElementById('scheduleDay');
    if (!dateInput || !daySelect) return;

    dateInput.addEventListener('change', () => {
        const day = deriveDayOfWeek(dateInput.value);
        if (day) {
            daySelect.value = day;
        }
    });
}

function deriveDayOfWeek(dateStr) {
    if (!dateStr) return '';
    const date = new Date(dateStr + 'T00:00:00');
    if (Number.isNaN(date.getTime())) return '';
    const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    return days[date.getDay()];
}

// Load lectures summary with accordion
async function loadLecturesSummary() {
    try {
        const response = await fetch('../api/faculty/get_lectures_summary.php');
        const data = await response.json();
        
        const container = document.getElementById('lecturesSummaryContainer');
        
        if (!data.success) {
            container.innerHTML = '<p class="error-message">Failed to load lectures</p>';
            return;
        }
        
        const subjects = data.subjects;
        
        if (subjects.length === 0) {
            container.innerHTML = '<p class="text-center">No lectures scheduled yet</p>';
            return;
        }
        
        // Create accordion HTML
        let html = '<div class="lectures-accordion">';
        
        subjects.forEach((subject, index) => {
            html += `
                <div class="lecture-accordion-item">
                    <div class="lecture-accordion-header" onclick="toggleLectureAccordion(this)">
                        <div class="lecture-header-title">${subject.subject_code} - ${subject.subject_name}</div>
                        <div class="lecture-header-stats">
                            <div class="lecture-stat">
                                <span class="lecture-stat-label">✓ Completed:</span>
                                <span>${subject.completed_lectures}</span>
                            </div>
                            <div class="lecture-stat">
                                <span class="lecture-stat-label">⏱ Active:</span>
                                <span>${subject.active_lectures}</span>
                            </div>
                            <div class="lecture-stat">
                                <span class="lecture-stat-label">⏳ Pending:</span>
                                <span>${subject.not_started_lectures}</span>
                            </div>
                        </div>
                        <span class="lecture-accordion-toggle">▼</span>
                    </div>
                    <div class="lecture-accordion-content">
                        <div class="lectures-list">
            `;
            
            if (subject.lectures.length > 0) {
                subject.lectures.forEach(lecture => {
                    const statusClass = `lecture-status-${lecture.attendance_status}`;
                    const statusText = lecture.attendance_status.charAt(0).toUpperCase() + lecture.attendance_status.slice(1).replace('_', ' ');
                    
                    html += `
                        <div class="lecture-item">
                            <div class="lecture-item-date">📅 ${formatDate(lecture.lecture_date)}</div>
                            <div class="lecture-item-time">🕐 ${formatTime(lecture.start_time)} - ${formatTime(lecture.end_time)}</div>
                            <div class="lecture-item-room">${lecture.room_number ? '📍 ' + lecture.room_number : '📍 No room'}</div>
                            <span class="lecture-status-badge ${statusClass}">${statusText}</span>
                        </div>
                    `;
                });
            } else {
                html += '<div class="no-lectures-msg">No lectures scheduled for this subject</div>';
            }
            
            html += `
                        </div>
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
        container.innerHTML = html;
        
    } catch (error) {
        console.error('Error loading lectures summary:', error);
        document.getElementById('lecturesSummaryContainer').innerHTML = '<p class="error-message">Error loading lectures</p>';
    }
}

// Toggle lecture accordion - close others when opening one
function toggleLectureAccordion(headerElement) {
    const item = headerElement.parentElement;
    const content = item.querySelector('.lecture-accordion-content');
    const isActive = headerElement.classList.contains('active');
    
    // Close all other accordions
    document.querySelectorAll('.lecture-accordion-header').forEach(header => {
        if (header !== headerElement) {
            header.classList.remove('active');
            header.parentElement.querySelector('.lecture-accordion-content').classList.remove('active');
        }
    });
    
    // Toggle current accordion
    if (isActive) {
        headerElement.classList.remove('active');
        content.classList.remove('active');
    } else {
        headerElement.classList.add('active');
        content.classList.add('active');
    }
}

// Format date helper
function formatDate(dateStr) {
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

// Format time helper
function formatTime(timeStr) {
    if (!timeStr) return '';
    const [hours, minutes] = timeStr.split(':');
    const hour = parseInt(hours);
    const ampm = hour >= 12 ? 'PM' : 'AM';
    const displayHour = hour % 12 || 12;
    return `${displayHour}:${minutes} ${ampm}`;
}

// Load timetable for form
async function loadTimetableForForm() {
    try {
        const response = await fetch('../api/get_timetable.php');
        const data = await response.json();
        
        if (data.success) {
            const select = document.getElementById('timetableSelect');

            const now = new Date();
            const yyyy = now.getFullYear();
            const mm = String(now.getMonth() + 1).padStart(2, '0');
            const dd = String(now.getDate()).padStart(2, '0');
            const todayStr = `${yyyy}-${mm}-${dd}`;
            const currentTime = `${now.getHours().toString().padStart(2, '0')}:${now.getMinutes().toString().padStart(2, '0')}:00`;

            const availableSlots = data.timetable.filter(slot => {
                if (!slot.lecture_date) return false; // require explicit date

                // Only show lectures scheduled for today
                if (slot.lecture_date !== todayStr) return false;

                // Exclude completed lectures
                if (slot.attendance_status === 'completed') return false;

                // Exclude if end time already passed today
                if (slot.end_time && slot.end_time < currentTime) return false;

                return true;
            });

            select.innerHTML = '<option value="">No timetable link</option>' +
                availableSlots.map(slot => 
                    `<option value="${slot.id}">${slot.lecture_date} ${slot.day_of_week} ${slot.start_time.substring(0,5)} - ${slot.subject_name}</option>`
                ).join('');
        }
    } catch (error) {
        console.error('Error loading timetable:', error);
    }
}

// Start attendance form handler
document.getElementById('startAttendanceForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const data = {
        subject_id: parseInt(formData.get('subject_id')),
        timetable_id: formData.get('timetable_id') ? parseInt(formData.get('timetable_id')) : null
    };
    
    try {
        const response = await fetch('../api/faculty/start_attendance.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showSuccess('Attendance session started successfully!');
            this.reset();
            
            // Reload active sessions
            loadActiveSessions();
            
            // Switch to sessions view
            const sessionsMenuItem = document.querySelector('.menu-item[data-view="sessions"]');
            if (sessionsMenuItem) {
                sessionsMenuItem.click();
            }
            
            // Open session details after a short delay
            setTimeout(() => {
                viewSessionDetails(result.session.id);
            }, 500);
        } else {
            showError(result.message);
        }
    } catch (error) {
        console.error('Error starting session:', error);
        showError('Failed to start attendance session');
    }
});

// Schedule lecture form handler
document.getElementById('scheduleLectureForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const subjectId = formData.get('subject_id');
    const lectureDate = formData.get('lecture_date');
    const startTime = formData.get('start_time');
    const endTime = formData.get('end_time');
    const roomNumber = formData.get('room_number') || null;
    const derivedDay = deriveDayOfWeek(lectureDate);
    
    // Log form data for debugging
    console.log('Form submission data:', {
        subjectId,
        lectureDate,
        startTime,
        endTime,
        derivedDay,
        roomNumber
    });
    
    // Validate all required fields
    if (!subjectId) {
        showError('Please select a subject.');
        return;
    }
    
    if (!lectureDate) {
        showError('Please select a lecture date.');
        return;
    }
    
    if (!startTime) {
        showError('Please select a start time.');
        return;
    }
    
    if (!endTime) {
        showError('Please select an end time.');
        return;
    }
    
    if (!derivedDay) {
        showError('Invalid lecture date.');
        return;
    }
    
    // Validate times
    if (startTime >= endTime) {
        showError('Start time must be before end time');
        return;
    }
    
    const data = {
        subject_id: parseInt(subjectId),
        day_of_week: derivedDay,
        lecture_date: lectureDate,
        start_time: startTime,
        end_time: endTime,
        room_number: roomNumber
    };
    
    console.log('Sending data to API:', data);
    
    try {
        const response = await fetch('../api/faculty/schedule_lecture.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });
        const rawText = await response.text();

        // Try to parse JSON; if it fails, log the raw response for debugging
        let result;
        try {
            result = JSON.parse(rawText);
        } catch (parseError) {
            console.error('Failed to parse JSON response:', parseError);
            console.error('Raw response text:', rawText);
            showError('Server returned invalid JSON. Check console for details.');
            return;
        }
        
        console.log('API response:', result);
        
        if (result.success) {
            showSuccess('Lecture scheduled successfully!');
            this.reset();
            
            // Reset date to today after successful submission
            setScheduleDateMin();
            
            // Reload timetable for form (dropdown)
            loadTimetableForForm();
            
            // Reload timetable
            loadTimetable();
            
            // Switch to timetable view
            setTimeout(() => {
                document.querySelector('.menu-item[data-view="timetable"]').click();
            }, 500);
        } else {
            showError(result.message || 'Failed to schedule lecture');
        }
    } catch (error) {
        console.error('Error scheduling lecture:', error);
        console.error('Error details:', error.message);
        showError('Failed to schedule lecture: ' + error.message);
    }
});

// Load timetable
async function loadTimetable() {
    try {
        const response = await fetch('../api/get_timetable.php');
        const data = await response.json();
        
        if (data.success) {
            const container = document.getElementById('timetableContainer');
            
            if (data.timetable.length > 0) {
                // Today and tomorrow strings
                const now = new Date();
                const yyyy = now.getFullYear();
                const mm = String(now.getMonth() + 1).padStart(2, '0');
                const dd = String(now.getDate()).padStart(2, '0');
                const todayStr = `${yyyy}-${mm}-${dd}`;
                const tomorrow = new Date(now);
                tomorrow.setDate(tomorrow.getDate() + 1);
                const tomorrowStr = `${tomorrow.getFullYear()}-${String(tomorrow.getMonth() + 1).padStart(2, '0')}-${String(tomorrow.getDate()).padStart(2, '0')}`;
                
                const slotsToday = data.timetable.filter(slot => slot.lecture_date === todayStr);
                const slotsTomorrow = data.timetable.filter(slot => slot.lecture_date === tomorrowStr);
                const futureLater = data.timetable.filter(slot => slot.lecture_date > tomorrowStr);
                const pastLectures = data.timetable.filter(slot => slot.lecture_date < todayStr);

                let html = '';

                // Helper to render a section
                const renderSection = (dateStr, heading) => {
                    if (!dateStr) return;
                    const dateObj = new Date(dateStr + 'T00:00:00');
                    const dayName = dateObj.toLocaleDateString('en-US', { weekday: 'long' });
                    const slots = data.timetable.filter(slot => slot.lecture_date === dateStr);
                    html += `<h3>${heading} — ${dateStr} (${dayName})</h3>`;
                    if (slots.length === 0) {
                        html += '<p class="text-center">No lectures scheduled</p>';
                        return;
                    }
                    html += '<table><thead><tr><th>Time</th><th>Subject</th><th>Room</th><th>Status</th></tr></thead><tbody>';
                    slots.forEach(slot => {
                        html += `
                            <tr>
                                <td>${slot.start_time.substring(0, 5)} - ${slot.end_time.substring(0, 5)}</td>
                                <td>${slot.subject_code} - ${slot.subject_name}</td>
                                <td>${slot.room_number || '-'}</td>
                                <td>${createStatusBadge(slot.attendance_status)}</td>
                            </tr>
                        `;
                    });
                    html += '</tbody></table>';
                };

                // Render today then tomorrow
                renderSection(todayStr, 'Today');
                renderSection(tomorrowStr, 'Tomorrow');

                if (html === '') {
                    html = '<p class="text-center">No upcoming lectures scheduled</p>';
                } else {
                    // If there are further future dates, hint to use the date picker
                    if (futureLater.length > 0) {
                        html += '<p class="text-light" style="margin-top: 10px;">Looking for later dates? Use the date picker below.</p>';
                    }
                }

                container.innerHTML = html;
                
                // Populate date picker for any date (past or future)
                populatePastDatesDropdown(pastLectures.concat(futureLater));
            } else {
                container.innerHTML = '<p class="text-center">No timetable available</p>';
                populatePastDatesDropdown([]);
            }
        }
    } catch (error) {
        console.error('Error loading timetable:', error);
        showError('Failed to load timetable');
    }
}

// Populate past dates dropdown
function populatePastDatesDropdown(pastLectures) {
    const datePicker = document.getElementById('pastDatePicker');
    if (!datePicker) return;

    // Allow selecting any date (past or future)
    datePicker.min = '2000-01-01';
    datePicker.max = '2100-12-31';

    // Add change listener
    datePicker.onchange = function() {
        if (this.value) {
            displayPastLectures(pastLectures, this.value);
        } else {
            document.getElementById('pastLecturesContainer').innerHTML = '<p class="text-center">Select a date to view past lectures</p>';
        }
    };
}

// Display lectures for selected past date
function displayPastLectures(pastLectures, selectedDate) {
    const lecturesOnDate = pastLectures.filter(slot => slot.lecture_date === selectedDate);
    const container = document.getElementById('pastLecturesContainer');
    
    if (lecturesOnDate.length === 0) {
        container.innerHTML = '<p class="text-center">No lectures scheduled for this date</p>';
        return;
    }
    
    const dateObj = new Date(selectedDate + 'T00:00:00');
    const dayName = dateObj.toLocaleDateString('en-US', { weekday: 'long' });
    
    let html = `<h4>${selectedDate} - ${dayName}</h4>`;
    html += '<table><thead><tr><th>Time</th><th>Subject</th><th>Room</th><th>Status</th></tr></thead><tbody>';
    
    lecturesOnDate.forEach(slot => {
        html += `
            <tr>
                <td>${slot.start_time.substring(0, 5)} - ${slot.end_time.substring(0, 5)}</td>
                <td>${slot.subject_code} - ${slot.subject_name}</td>
                <td>${slot.room_number || '-'}</td>
                <td>${createStatusBadge(slot.attendance_status)}</td>
            </tr>
        `;
    });
    html += '</tbody></table>';
    
    container.innerHTML = html;
}

// Load subjects for modify attendance form
async function loadSubjectsForModify() {
    try {
        const response = await fetch('../api/get_subjects.php');
        const data = await response.json();
        
        if (data.success) {
            const select = document.getElementById('modifySubject');
            select.innerHTML = '<option value="">Select Subject</option>' +
                data.subjects.map(subject => 
                    `<option value="${subject.id}">${subject.subject_code} - ${subject.subject_name}</option>`
                ).join('');
        }
    } catch (error) {
        console.error('Error loading subjects:', error);
    }
}

// Setup modify attendance form
document.addEventListener('DOMContentLoaded', function() {
    const modifyForm = document.getElementById('modifyAttendanceForm');
    const subjectSelect = document.getElementById('modifySubject');
    const dateInput = document.getElementById('modifyDate');
    const sessionGroup = document.getElementById('sessionSelectorGroup');
    const sessionSelect = document.getElementById('modifySession');
    
    // Load sessions when subject and date are selected
    async function loadSessionsForDate() {
        const subjectId = subjectSelect.value;
        const date = dateInput.value;
        
        if (!subjectId || !date) {
            sessionGroup.style.display = 'none';
            sessionSelect.removeAttribute('required');
            return;
        }
        
        try {
            const response = await fetch(`../api/faculty/get_sessions_for_date.php?subject_id=${subjectId}&date=${date}`);
            const data = await response.json();
            
            if (data.success && data.sessions.length > 0) {
                if (data.sessions.length === 1) {
                    // Only one session, hide selector and auto-select
                    sessionGroup.style.display = 'none';
                    sessionSelect.removeAttribute('required');
                    sessionSelect.innerHTML = `<option value="${data.sessions[0].id}" selected>${data.sessions[0].subject_code} - ${formatTime(data.sessions[0].started_at)} to ${formatTime(data.sessions[0].ended_at)}</option>`;
                } else {
                    // Multiple sessions, show selector
                    sessionGroup.style.display = 'block';
                    sessionSelect.setAttribute('required', 'required');
                    sessionSelect.innerHTML = '<option value="">Select a session</option>' +
                        data.sessions.map(session => {
                            const startTime = formatTime(session.started_at);
                            const endTime = formatTime(session.ended_at);
                            return `<option value="${session.id}">${session.subject_code} - ${startTime} to ${endTime} (${session.faculty_name})</option>`;
                        }).join('');
                }
            } else {
                sessionGroup.style.display = 'none';
                sessionSelect.removeAttribute('required');
                sessionSelect.innerHTML = '<option value="">No sessions found</option>';
            }
        } catch (error) {
            console.error('Error loading sessions:', error);
        }
    }
    
    // Format time from datetime
    function formatTime(datetime) {
        const date = new Date(datetime);
        return date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
    }
    
    if (subjectSelect && dateInput) {
        subjectSelect.addEventListener('change', loadSessionsForDate);
        dateInput.addEventListener('change', loadSessionsForDate);
    }
    
    if (modifyForm) {
        modifyForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const studentId = document.getElementById('modifyStudentId').value.trim();
            const sessionId = document.getElementById('modifySession').value;
            const status = document.getElementById('modifyStatus').value;
            const reason = document.getElementById('modifyReason').value.trim();
            
            if (!studentId || !sessionId || !status || !reason) {
                alert('Please fill in all fields');
                return;
            }
            
            try {
                const response = await fetch('../api/faculty/modify_attendance.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        student_id: studentId,
                        session_id: parseInt(sessionId),
                        new_status: status,
                        reason: reason
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    alert('Attendance modified successfully!');
                    modifyForm.reset();
                    sessionGroup.style.display = 'none';
                    loadLogs(); // Reload logs to show the new entry
                } else {
                    alert('Error: ' + data.message);
                }
            } catch (error) {
                console.error('Error modifying attendance:', error);
                alert('Failed to modify attendance. Please try again.');
            }
        });
    }
});

// Load logs
async function loadLogs() {
    const container = document.getElementById('logsTable');
    container.innerHTML = '<div class="spinner"></div><p class="text-center">Loading...</p>';
    
    try {
        const response = await fetch('../api/admin/get_attendance_logs.php');
        const data = await response.json();
        
        if (!data.success) {
            container.innerHTML = `<p class="error-message">Error: ${data.message || 'Failed to load logs'}</p>`;
            return;
        }
        
        if (data.logs.length > 0) {
            let html = `
                <table>
                    <thead>
                        <tr>
                            <th>Modified Date/Time</th>
                            <th>Attendance Date</th>
                            <th>Type</th>
                            <th>Subject</th>
                            <th>Student</th>
                            <th>Modified By</th>
                            <th>Old Status</th>
                            <th>New Status</th>
                            <th>Reason</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            
            data.logs.forEach(log => {
                const attendanceDate = log.subject_attendance_date || log.campus_attendance_date || '-';
                const subject = log.subject_code ? `${log.subject_code} - ${log.subject_name}` : (log.attendance_type === 'campus' ? 'Campus' : '-');
                
                html += `
                    <tr>
                        <td>${formatDateTime(log.created_at)}</td>
                        <td>${attendanceDate ? formatDate(attendanceDate) : '-'}</td>
                        <td>${createStatusBadge(log.attendance_type)}</td>
                        <td>${subject}</td>
                        <td>${log.student_name} (${log.student_number})</td>
                        <td>${log.modified_by_name} (${log.modified_by_role})</td>
                        <td>${log.old_status ? createStatusBadge(log.old_status) : '-'}</td>
                        <td>${createStatusBadge(log.new_status)}</td>
                        <td>${log.reason || '-'}</td>
                    </tr>
                `;
            });
            
            html += '</tbody></table>';
            container.innerHTML = html;
        } else {
            container.innerHTML = '<p class="text-center">No logs found</p>';
        }
    } catch (error) {
        console.error('Error loading logs:', error);
        container.innerHTML = '<p class="error-message">Failed to load logs</p>';
    }
}

// Cleanup on page unload
window.addEventListener('beforeunload', () => {
    if (qrRefreshInterval) {
        clearInterval(qrRefreshInterval);
    }
});

