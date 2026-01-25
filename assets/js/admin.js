// Admin Dashboard JavaScript

let currentUser;
let statsRefreshInterval;

// Initialize
(async function init() {
    currentUser = await checkAuth();
    
    if (!currentUser || currentUser.role !== 'admin') {
        window.location.href = '../index.html';
        return;
    }
    
    // Update UI with user info
    document.getElementById('adminName').textContent = currentUser.full_name;
    document.getElementById('userEmail').textContent = currentUser.email;
    
    // Setup navigation
    setupNavigation();
    
    // Load statistics
    loadStatistics();
    
    // Load low attendance students
    loadLowAttendanceStudents();
    
    // Auto-refresh statistics every 30 seconds
    statsRefreshInterval = setInterval(loadStatistics, 30000);
    
    // Load subjects for filter
    loadSubjectsForFilter();

    // Bind add faculty form
    setupAddFacultyForm();
})();

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
            switch(viewName) {
                case 'statistics':
                    loadStatistics();
                    break;
                case 'subject-attendance':
                    loadSubjectAttendance();
                    break;
                case 'campus-attendance':
                    loadCampusAttendance();
                    break;
                case 'campus-qr':
                    loadCampusQR();
                    break;
                case 'add-faculty':
                    // Form view; nothing to preload yet
                    break;
            }
        });
    });
}

// Load statistics
async function loadStatistics() {
    try {
        const response = await fetch('../api/admin/get_statistics.php');
        const data = await response.json();
        
        if (data.success) {
            const stats = data.statistics;
            
            // Update user counts
            const students = stats.users.find(u => u.role === 'student');
            const faculty = stats.users.find(u => u.role === 'faculty');
            
            document.getElementById('totalStudents').textContent = students ? students.count : 0;
            document.getElementById('totalFaculty').textContent = faculty ? faculty.count : 0;
            document.getElementById('totalSubjects').textContent = stats.total_subjects;
            document.getElementById('activeSessions').textContent = stats.active_sessions;
            
            // Campus attendance stats
            let campusHTML = '<table><tr><th>Status</th><th>Count</th></tr>';
            if (stats.campus_attendance_today.length > 0) {
                stats.campus_attendance_today.forEach(item => {
                    campusHTML += `<tr><td>${createStatusBadge(item.status)}</td><td>${item.count}</td></tr>`;
                });
            } else {
                campusHTML += '<tr><td colspan="2" class="text-center">No records today</td></tr>';
            }
            campusHTML += '</table>';
            document.getElementById('campusStats').innerHTML = campusHTML;
            
            // Subject attendance stats
            let subjectHTML = '<table><tr><th>Status</th><th>Count</th></tr>';
            if (stats.subject_attendance_today.length > 0) {
                stats.subject_attendance_today.forEach(item => {
                    subjectHTML += `<tr><td>${createStatusBadge(item.status)}</td><td>${item.count}</td></tr>`;
                });
            } else {
                subjectHTML += '<tr><td colspan="2" class="text-center">No records today</td></tr>';
            }
            subjectHTML += '</table>';
            document.getElementById('subjectStats').innerHTML = subjectHTML;
        }
    } catch (error) {
        console.error('Error loading statistics:', error);
    }
}

// Load subjects for filter
async function loadSubjectsForFilter() {
    try {
        const response = await fetch('../api/get_subjects.php');
        const data = await response.json();
        
        if (data.success) {
            const select = document.getElementById('filterSubject');
            select.innerHTML = '<option value="">All Subjects</option>' +
                data.subjects.map(subject => 
                    `<option value="${subject.id}">${subject.subject_code} - ${subject.subject_name}</option>`
                ).join('');
        }
    } catch (error) {
        console.error('Error loading subjects:', error);
    }
}

// Load subject attendance
async function loadSubjectAttendance() {
    const container = document.getElementById('subjectAttendanceTable');
    container.innerHTML = '<div class="spinner"></div><p class="text-center">Loading...</p>';
    
    try {
        const subjectId = document.getElementById('filterSubject').value;
        const studentId = document.getElementById('filterStudent').value;
        const dateFrom = document.getElementById('filterDateFrom').value;
        const dateTo = document.getElementById('filterDateTo').value;
        
        let url = '../api/admin/get_all_attendance.php?type=subject';
        if (subjectId) url += `&subject_id=${subjectId}`;
        if (studentId) url += `&student_id=${studentId}`;
        if (dateFrom) url += `&date_from=${dateFrom}`;
        if (dateTo) url += `&date_to=${dateTo}`;
        
        const response = await fetch(url);
        const data = await response.json();
        
        if (!data.success) {
            container.innerHTML = `<p class="error-message">Error: ${data.message || 'Failed to load attendance records'}</p>`;
            return;
        }
        
        if (data.records.length > 0) {
            let html = `
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Student</th>
                            <th>Student ID</th>
                            <th>Subject</th>
                            <th>Faculty</th>
                            <th>Status</th>
                            <th>Marked At</th>
                            <th>Method</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            
            data.records.forEach(record => {
                html += `
                    <tr>
                        <td>${formatDate(record.attendance_date)}</td>
                        <td>${record.student_name}</td>
                        <td>${record.student_number}</td>
                        <td>${record.subject_code} - ${record.subject_name}</td>
                        <td>${record.faculty_name}</td>
                        <td>${createStatusBadge(record.status)}</td>
                        <td>${formatDateTime(record.marked_at)}</td>
                        <td>${createStatusBadge(record.marked_method)}</td>
                    </tr>
                `;
            });
            
            html += '</tbody></table>';
            container.innerHTML = html;
        } else {
            container.innerHTML = '<p class="text-center">No records found</p>';
        }
    } catch (error) {
        console.error('Error loading subject attendance:', error);
        container.innerHTML = '<p class="error-message">Failed to load attendance records</p>';
    }
}

// Load campus attendance
async function loadCampusAttendance() {
    const container = document.getElementById('campusAttendanceTable');
    container.innerHTML = '<div class="spinner"></div><p class="text-center">Loading...</p>';
    
    try {
        const studentId = document.getElementById('campusFilterStudent').value;
        const dateFrom = document.getElementById('campusFilterDateFrom').value;
        const dateTo = document.getElementById('campusFilterDateTo').value;
        
        let url = '../api/admin/get_all_attendance.php?type=campus';
        if (studentId) url += `&student_id=${studentId}`;
        if (dateFrom) url += `&date_from=${dateFrom}`;
        if (dateTo) url += `&date_to=${dateTo}`;
        
        const response = await fetch(url);
        const data = await response.json();
        
        if (!data.success) {
            container.innerHTML = `<p class="error-message">Error: ${data.message || 'Failed to load attendance records'}</p>`;
            return;
        }
        
        if (data.records.length > 0) {
            let html = `
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Student</th>
                            <th>Student ID</th>
                            <th>Status</th>
                            <th>Marked At</th>
                            <th>Type</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            
            data.records.forEach(record => {
                html += `
                    <tr>
                        <td>${formatDate(record.attendance_date)}</td>
                        <td>${record.student_name}</td>
                        <td>${record.student_number}</td>
                        <td>${createStatusBadge(record.status)}</td>
                        <td>${record.marked_at ? formatDateTime(record.marked_at) : '-'}</td>
                        <td>${record.is_derived == 1 ? '<span class="badge badge-info">Derived</span>' : '<span class="badge badge-success">Direct</span>'}</td>
                    </tr>
                `;
            });
            
            html += '</tbody></table>';
            container.innerHTML = html;
        } else {
            container.innerHTML = '<p class="text-center">No records found</p>';
        }
    } catch (error) {
        console.error('Error loading campus attendance:', error);
        container.innerHTML = '<p class="error-message">Failed to load attendance records</p>';
    }
}

// Load campus QR
async function loadCampusQR() {
    try {
        const response = await fetch('../api/get_campus_qr.php');
        const data = await response.json();
        
        if (data.success) {
            document.getElementById('campusQRImage').src = data.qr_image_url;
            document.getElementById('qrDate').textContent = formatDate(data.date);
        }
    } catch (error) {
        console.error('Error loading campus QR:', error);
        showError('Failed to load campus QR code');
    }
}

// Refresh campus QR
async function refreshCampusQR() {
    await loadCampusQR();
    showSuccess('Campus QR code refreshed');
}

// Add faculty handler
function setupAddFacultyForm() {
    const form = document.getElementById('addFacultyForm');
    if (!form) {
        console.warn('addFacultyForm not found in DOM');
        return;
    }
    
    console.log('Setting up add faculty form listener');

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        console.log('Add faculty form submitted');

        const full_name = form.full_name.value.trim();
        const username = form.username.value.trim();
        const email = form.email.value.trim();
        const faculty_id = form.faculty_id.value.trim();
        const password = form.password.value.trim();

        console.log('Form data:', { full_name, username, email, faculty_id });

        if (!full_name || !username || !email || !faculty_id || password.length < 6) {
            showError('All fields are required and password must be at least 6 characters.');
            return;
        }

        try {
            console.log('Sending request to ../api/admin/create_faculty.php');
            const response = await fetch('../api/admin/create_faculty.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ full_name, username, email, faculty_id, password })
            });

            console.log('Response status:', response.status);
            const raw = await response.text();
            console.log('Raw response:', raw);
            
            let data;
            try {
                data = JSON.parse(raw);
            } catch (err) {
                console.error('Invalid JSON from create_faculty:', raw);
                showError('Server error. Invalid response. Check console.');
                return;
            }

            console.log('Parsed data:', data);

            if (!response.ok || !data.success) {
                showError(data.message || 'Failed to create faculty');
                return;
            }

            showSuccess(data.message || 'Faculty created successfully');
            form.reset();
            
            // Refresh statistics to show updated faculty count
            loadStatistics();
        } catch (error) {
            console.error('Error creating faculty:', error);
            showError('Failed to create faculty: ' + error.message);
        }
    });
}

// Load low attendance students
async function loadLowAttendanceStudents() {
    const container = document.getElementById('lowAttendanceContainer');
    
    try {
        const response = await fetch('../api/admin/get_low_attendance_students.php');
        const data = await response.json();
        
        if (!data.success) {
            container.innerHTML = `<p class="error-message">Error: ${data.message || 'Failed to load low attendance students'}</p>`;
            return;
        }
        
        if (data.students.length === 0) {
            container.innerHTML = '<p class="text-center text-success">✓ All students have attendance above 75%</p>';
            return;
        }
        
        // Create table with expandable rows
        let html = `
            <table class="low-attendance-table">
                <thead>
                    <tr>
                        <th>Student ID</th>
                        <th>Student Name</th>
                        <th>Overall Attendance</th>
                        <th>Total Classes</th>
                        <th>Subjects Below 75%</th>
                    </tr>
                </thead>
                <tbody>
        `;
        
        data.students.forEach((student, index) => {
            const rowId = `low-att-${index}`;
            const overallPercentage = student.overall_attendance || 0;
            const overallBadgeClass = overallPercentage < 50 ? 'badge-danger' : 'badge-warning';
            
            html += `
                <tr class="main-row" onclick="toggleSubjectDetails('${rowId}')">
                    <td><strong>${student.student_id}</strong></td>
                    <td>${student.full_name}</td>
                    <td>
                        <span class="badge ${overallBadgeClass}">
                            ${overallPercentage}%
                        </span>
                    </td>
                    <td>${student.total_classes || 0}</td>
                    <td>
                        <span class="badge badge-info">${student.subjects.length} subject(s)</span>
                        <span class="expand-icon" id="icon-${rowId}">▼</span>
                    </td>
                </tr>
                <tr id="${rowId}" class="details-row hidden">
                    <td colspan="5" class="details-cell">
                        <div class="subject-details">
                            <h5>Subjects with Below 75% Attendance:</h5>
                            <table class="subject-table">
                                <thead>
                                    <tr>
                                        <th>Subject Code</th>
                                        <th>Subject Name</th>
                                        <th>Attendance</th>
                                        <th>Classes</th>
                                    </tr>
                                </thead>
                                <tbody>
            `;
            
            student.subjects.forEach(subject => {
                const badgeClass = subject.attendance_percentage < 50 ? 'badge-danger' : 'badge-warning';
                html += `
                    <tr>
                        <td><strong>${subject.subject_code}</strong></td>
                        <td>${subject.subject_name}</td>
                        <td>
                            <span class="badge ${badgeClass}">
                                ${subject.attendance_percentage}%
                            </span>
                        </td>
                        <td>${subject.present_count}/${subject.total_classes}</td>
                    </tr>
                `;
            });
            
            html += `
                                </tbody>
                            </table>
                        </div>
                    </td>
                </tr>
            `;
        });
        
        html += '</tbody></table>';
        container.innerHTML = html;
        
    } catch (error) {
        console.error('Error loading low attendance students:', error);
        container.innerHTML = '<p class="error-message">Failed to load low attendance students</p>';
    }
}

// Toggle subject details visibility
function toggleSubjectDetails(rowId) {
    const detailsRow = document.getElementById(rowId);
    const icon = document.getElementById(`icon-${rowId}`);
    
    if (detailsRow.classList.contains('hidden')) {
        detailsRow.classList.remove('hidden');
        icon.textContent = '▲';
    } else {
        detailsRow.classList.add('hidden');
        icon.textContent = '▼';
    }
}

// Cleanup on page unload
window.addEventListener('beforeunload', () => {
    if (statsRefreshInterval) {
        clearInterval(statsRefreshInterval);
    }
});

