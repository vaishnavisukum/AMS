// Student Dashboard JavaScript

let html5QrCode;
let currentUser;
let scannerInitialized = false;
let scannerRunning = false;

// Initialize
(async function init() {
    currentUser = await checkAuth();
    
    console.log('Student dashboard - session check:', currentUser);
    
    if (!currentUser || currentUser.role !== 'student') {
        console.error('Not logged in as student. Current user:', currentUser);
        window.location.href = '../index.html';
        return;
    }
    
    // Update UI with user info
    document.getElementById('studentName').textContent = currentUser.full_name;
    document.getElementById('userEmail').textContent = currentUser.email;
    
    // Setup navigation
    setupNavigation();
    
    console.log('✅ Student dashboard loaded');
})();

// Request camera permission after page is fully loaded and visible
window.addEventListener('load', () => {
    console.log('Page fully loaded');
    
    // Reset flags for fresh initialization
    scannerInitialized = false;
    scannerRunning = false;
    
    // Do NOT request camera permission automatically - let user click the button
    console.log('Camera permission request will be triggered by user clicking the button');
});

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
            
            // Control camera usage based on view
            if (viewName === 'scanner') {
                // Start scanner when user navigates to scanner view
                startScanner();
            } else {
                // Stop scanner when leaving scanner view to release camera
                stopQRScanner();
            }

            // Load data for the view
            if (viewName === 'history') {
                loadAttendanceHistory();
            } else if (viewName === 'timetable') {
                loadTimetable();
            }
        });
    });
    
    // Setup camera permission request button
    const requestCameraBtn = document.getElementById('requestCameraBtn');
    if (requestCameraBtn) {
        requestCameraBtn.addEventListener('click', async (e) => {
            e.preventDefault();
            console.log('Camera permission button clicked');
            await requestCameraPermissionOnLoad();
        });
    }
    
    // Setup QR file upload handler
    const qrFileInput = document.getElementById('qrFileInput');
    if (qrFileInput) {
        qrFileInput.addEventListener('change', async (e) => {
            const file = e.target.files[0];
            if (file) {
                console.log('QR image file selected:', file.name, 'Type:', file.type, 'Size:', file.size);
                
                // Validate it's an image file
                if (!file.type.startsWith('image/')) {
                    showError('Please select a valid image file.');
                    return;
                }
                
                await scanQRFromFile(file);
                // Reset file input so same file can be uploaded again
                qrFileInput.value = '';
            }
        });
    }
    
    // Check camera permission status and show/hide button
    checkCameraPermission();
}

// Check camera permission status
async function checkCameraPermission() {
    try {
        const permission = await navigator.permissions.query({ name: 'camera' });
        const requestCameraBtn = document.getElementById('requestCameraBtn');
        
        if (permission.state === 'granted') {
            // Camera permission is granted - hide button
            if (requestCameraBtn) {
                requestCameraBtn.style.display = 'none';
            }
        } else {
            // State is 'prompt' or 'denied' - show button to allow user to request
            if (requestCameraBtn) {
                requestCameraBtn.style.display = 'block';
            }
        }
        
        // Listen for permission changes
        permission.addEventListener('change', () => {
            if (permission.state === 'granted') {
                if (requestCameraBtn) {
                    requestCameraBtn.style.display = 'none';
                }
            } else {
                if (requestCameraBtn) {
                    requestCameraBtn.style.display = 'block';
                }
            }
        });
    } catch (error) {
        console.warn('Could not check camera permission:', error);
        // If we can't check permission, show the button as a fallback
        const requestCameraBtn = document.getElementById('requestCameraBtn');
        if (requestCameraBtn) {
            requestCameraBtn.style.display = 'block';
        }
    }
}

// Request camera permission immediately on dashboard load
async function requestCameraPermissionOnLoad() {
    try {
        console.log('requestCameraPermissionOnLoad() called - requesting camera access...');
        console.log('About to call navigator.mediaDevices.getUserMedia()');
        
        // ALWAYS request permission fresh - don't use cached decision
        // This will show the dialog every time or use the user's saved preference
        const stream = await navigator.mediaDevices.getUserMedia({
            video: {
                facingMode: 'environment',
                width: { ideal: 1280 },
                height: { ideal: 720 }
            },
            audio: false
        });

        // ✅ Permission GRANTED - stop stream and initialize scanner
        console.log('✅ Camera permission GRANTED - stream received');
        
        // Hide the request camera permission button
        const requestCameraBtn = document.getElementById('requestCameraBtn');
        if (requestCameraBtn) {
            requestCameraBtn.style.display = 'none';
        }
        
        stream.getTracks().forEach(track => {
            console.log('Stopping track:', track.kind);
            track.stop(); // Stop all tracks to release camera
        });
        
        // Small delay to ensure camera is fully released before reinitializing
        setTimeout(() => {
            console.log('Reinitializing scanner after 100ms delay...');
            // Re-initialize scanner fresh each time
            if (html5QrCode) {
                html5QrCode = null; // Clear old instance
            }
            html5QrCode = new Html5Qrcode("reader");
            scannerInitialized = true;
            console.log('Scanner initialized, auto-opening scanner view...');
            
            // Auto-open QR Scanner view after permission is granted
            autoOpenScannerView();
            
            // Start scanning immediately
            initQRScanner();
        }, 100);
        
    } catch (error) {
        // ❌ Permission denied or blocked
        console.error('Camera permission error caught in catch block:', error.name, error.message);
        
        // Show the request camera permission button on permission error
        const requestCameraBtn = document.getElementById('requestCameraBtn');
        if (requestCameraBtn) {
            requestCameraBtn.style.display = 'block';
        }
        
        if (error.name === 'NotAllowedError') {
            console.warn('⚠️ User denied camera permission (NotAllowedError)');
            alert('Camera access is required to scan QR codes and mark attendance.\n\nPlease enable camera permission in your browser settings to proceed.');
        } else if (error.name === 'NotFoundError') {
            console.warn('❌ No camera device found (NotFoundError)');
            alert('No camera found on this device. Please use a device with a camera.');
        } else if (error.name === 'NotReadableError') {
            console.warn('Camera is in use by another application (NotReadableError)');
            alert('Camera is currently in use by another application. Please close it and try again.');
        } else {
            console.warn('Unknown camera permission error:', error.name, error.message);
            alert('Could not access camera. Please check permissions and try again.');
        }
    }
}

// Auto-open QR Scanner view
function autoOpenScannerView() {
    // Get all menu items and remove active class
    const menuItems = document.querySelectorAll('.menu-item[data-view]');
    menuItems.forEach(item => item.classList.remove('active'));
    
    // Set scanner menu item as active
    const scannerMenuItem = document.querySelector('.menu-item[data-view="scanner"]');
    if (scannerMenuItem) {
        scannerMenuItem.classList.add('active');
    }
    
    // Hide all views
    document.querySelectorAll('.view-content').forEach(view => {
        view.classList.add('hidden');
    });
    
    // Show scanner view
    const scannerView = document.getElementById('scanner-view');
    if (scannerView) {
        scannerView.classList.remove('hidden');
    }
    
    console.log('✅ QR Scanner view auto-opened');
}

// Start scanner when user navigates to scanner view
function startScanner() {
    if (scannerInitialized) {
        initQRScanner();
    } else {
        console.warn('Scanner not initialized. Camera permission may have been denied.');
        showError('Camera access is required. Please allow camera permission in browser settings.');
    }
}

// Scan QR code from an uploaded image file
async function scanQRFromFile(file) {
    try {
        // Validate the file parameter
        if (!file || !(file instanceof File)) {
            console.error('Invalid file object:', file);
            showError('Invalid file. Please select an image file.');
            return;
        }
        
        console.log('Starting QR scan from file:', file.name, 'Type:', file.type);
        
        // Show processing message
        const resultDiv = document.getElementById('scan-result');
        resultDiv.style.display = 'block';
        resultDiv.className = 'scan-result';
        resultDiv.innerHTML = '<div class="spinner"></div><p>Processing image...</p>';
        
        // Initialize html5QrCode if not already done
        if (!html5QrCode) {
            html5QrCode = new Html5Qrcode("reader");
        }
        
        try {
            // Scan the image - pass the File object directly
            console.log('Calling scanFile with file object...');
            const decodedText = await html5QrCode.scanFile(file, true);
            console.log('✅ QR code decoded from image:', decodedText);
            
            // Process the scanned QR data
            await onScanSuccess(decodedText);
            
        } catch (error) {
            console.error('Error scanning QR from file:', error.message || error);
            resultDiv.className = 'scan-result error-message';
            resultDiv.innerHTML = `
                <h3>❌ No QR Code Found</h3>
                <p>Could not find or read a QR code in the uploaded image. Please try another image.</p>
            `;
            showError('No QR code found in the image. Please try another image.');
        }
        
    } catch (error) {
        console.error('Error in scanQRFromFile:', error.message || error);
        const resultDiv = document.getElementById('scan-result');
        resultDiv.className = 'scan-result error-message';
        resultDiv.innerHTML = `
            <h3>❌ Error</h3>
            <p>An error occurred while processing the image. Please try again.</p>
        `;
        showError('An error occurred. Please try again.');
    }
}

// Initialize / start QR Scanner (only when scanner view is active)
function initQRScanner() {
    const scannerView = document.getElementById('scanner-view');
    if (!scannerView || scannerView.classList.contains('hidden')) {
        return;
    }

    if (!scannerInitialized) {
        html5QrCode = new Html5Qrcode("reader");
        scannerInitialized = true;
    }

    if (scannerRunning) {
        return;
    }

    const config = {
        fps: 10,
        qrbox: { width: 250, height: 250 }
    };

    html5QrCode
        .start(
            { facingMode: "environment" },
            config,
            onScanSuccess,
            onScanError
        )
        .then(() => {
            scannerRunning = true;
        })
        .catch(err => {
            console.error('Scanner initialization error:', err);
            showError('Camera access denied or not available');
        });
}

// Stop QR scanner and release camera
function stopQRScanner() {
    if (html5QrCode && scannerRunning) {
        html5QrCode
            .stop()
            .then(() => {
                scannerRunning = false;
            })
            .catch(err => {
                console.error('Error stopping QR scanner:', err);
            });
    }
}

// On successful QR scan
async function onScanSuccess(decodedText, decodedResult) {
    // Stop scanning temporarily
    if (html5QrCode && scannerRunning) {
        html5QrCode.pause(true);
    }
    
    const resultDiv = document.getElementById('scan-result');
    resultDiv.style.display = 'block';
    resultDiv.innerHTML = '<div class="spinner"></div><p>Processing...</p>';
    
    // Verify user is still logged in before attempting to scan
    const sessionCheck = await checkAuth();
    if (!sessionCheck || sessionCheck.role !== 'student') {
        resultDiv.className = 'scan-result error-message';
        resultDiv.innerHTML = `
            <h3>❌ Session Expired</h3>
            <p>Your session has expired. Please log in again.</p>
        `;
        showError('Session expired. Redirecting to login...');
        setTimeout(() => { window.location.href = '../index.html'; }, 1500);
        return;
    }
    
    try {
        const response = await fetch('../api/student/scan_qr.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ qr_data: decodedText })
        });
        
        const rawText = await response.text();
        let data;
        try {
            data = JSON.parse(rawText);
        } catch (err) {
            console.error('Invalid response from scan API:', rawText);
            showError('Server returned invalid response. Please re-login and try again.');
            return;
        }

        if (!response.ok) {
            if (response.status === 401) {
                resultDiv.className = 'scan-result error-message';
                resultDiv.innerHTML = `
                    <h3>❌ Not Logged In</h3>
                    <p>Please log in as a student and try again.</p>
                `;
                showError('Not logged in. Redirecting to login...');
                setTimeout(() => { window.location.href = '../index.html'; }, 1500);
                return;
            }
            if (response.status === 403) {
                console.error('403 Forbidden Response:', data);
                const msg = data.current_role && data.required_role
                    ? `Access denied. You are logged in as "${data.current_role}", but scanning requires "${data.required_role}".`
                    : (data.message || 'Forbidden - Access denied');
                
                resultDiv.className = 'scan-result error-message';
                resultDiv.innerHTML = `
                    <h3>❌ Access Denied</h3>
                    <p>${msg}</p>
                    ${data.current_role ? `<p><small>Your role: ${data.current_role}</small></p>` : ''}
                    ${data.required_role ? `<p><small>Required role: ${data.required_role}</small></p>` : ''}
                `;
                
                showError(msg);
                setTimeout(() => { window.location.href = '../index.html'; }, 2000);
                return;
            }
            resultDiv.className = 'scan-result error-message';
            resultDiv.innerHTML = `
                <h3>❌ Error</h3>
                <p>${data.message || 'Scan failed.'}</p>
            `;
            showError(data.message || 'Scan failed.');
            return;
        }
        
        if (data.success) {
            resultDiv.className = 'scan-result success-message';

            if (data.attendance_type === 'campus') {
                // Explicit campus attendance notification
                resultDiv.innerHTML = `
                    <h3>✅ Campus Attendance Marked</h3>
                    <p>${data.message}</p>
                    <p><strong>Date:</strong> ${data.date ? formatDate(data.date) : formatDate(data.marked_at)}</p>
                    <p><strong>Time:</strong> ${formatDateTime(data.marked_at)}</p>
                `;
            } else {
                // Subject attendance notification
                resultDiv.innerHTML = `
                    <h3>✅ Subject Attendance Marked</h3>
                    <p>${data.message}</p>
                    <p><strong>Type:</strong> ${data.attendance_type}</p>
                    ${data.subject ? `<p><strong>Subject:</strong> ${data.subject}</p>` : ''}
                    <p><strong>Time:</strong> ${formatDateTime(data.marked_at)}</p>
                `;
            }

            showSuccess(data.message);

            // Refresh history so student can immediately see updated records
            loadAttendanceHistory();
        } else {
            resultDiv.className = 'scan-result error-message';
            resultDiv.innerHTML = `
                <h3>❌ Error</h3>
                <p>${data.message}</p>
            `;
            
            showError(data.message);
        }
    } catch (error) {
        console.error('Scan error:', error);
        resultDiv.className = 'scan-result error-message';
        resultDiv.innerHTML = `
            <h3>❌ Error</h3>
            <p>Failed to process QR code</p>
        `;
        showError('Failed to process QR code');
    }
    
    // Resume scanning after 3 seconds (only if still on scanner view)
    setTimeout(() => {
        resultDiv.style.display = 'none';
        const scannerView = document.getElementById('scanner-view');
        if (scannerView && !scannerView.classList.contains('hidden') && html5QrCode && scannerInitialized) {
            html5QrCode.resume();
        }
    }, 3000);
}

// On scan error (usually just no QR code detected)
function onScanError(errorMessage) {
    // Ignore these errors - they're normal when no QR code is visible
}

// Load attendance history
async function loadAttendanceHistory() {
    try {
        const response = await fetch('../api/student/get_attendance_history.php');
        const rawText = await response.text();
        let data;
        
        try {
            data = JSON.parse(rawText);
        } catch (err) {
            console.error('Invalid JSON from attendance history API:', rawText);
            showError('Failed to load attendance history (invalid response).');
            return;
        }

        if (!response.ok) {
            if (response.status === 401 || response.status === 403) {
                const msg = data.current_role && data.required_role
                    ? `Access denied. Logged in as ${data.current_role}, need ${data.required_role}.`
                    : 'Session expired or access denied.';
                showError(msg);
                console.error('403/401 on attendance history:', data);
                setTimeout(() => { window.location.href = '../index.html'; }, 1500);
                return;
            }
            showError(data.message || 'Failed to load attendance history.');
            return;
        }
        
        if (data.success) {
            // Render average subject attendance chart
            renderAverageSubjectChart(data.subject_stats);

            // Render subject-wise charts
            renderSubjectCharts(data.subject_stats);
            
            // Render lecture-wise attendance details
            renderLectureWiseAttendance(data.lectures_by_subject);
            
            // Render campus attendance table
            const campusTableBody = document.querySelector('#campusAttendanceTable tbody');
            if (data.campus_attendance.length > 0) {
                campusTableBody.innerHTML = data.campus_attendance.map(record => `
                    <tr>
                        <td>${formatDate(record.attendance_date)}</td>
                        <td>${createStatusBadge(record.status)}</td>
                        <td>${record.marked_at ? formatDateTime(record.marked_at) : '-'}</td>
                        <td>${record.is_derived == 1 ? '<span class="badge badge-info">Derived</span>' : '<span class="badge badge-success">Direct</span>'}</td>
                    </tr>
                `).join('');
            } else {
                campusTableBody.innerHTML = '<tr><td colspan="4" class="text-center">No records found</td></tr>';
            }
            
            // Render subject attendance table
            const subjectTableBody = document.querySelector('#subjectAttendanceTable tbody');
            if (data.subject_attendance.length > 0) {
                subjectTableBody.innerHTML = data.subject_attendance.map(record => `
                    <tr>
                        <td>${formatDate(record.attendance_date)}</td>
                        <td>${record.subject_code} - ${record.subject_name}</td>
                        <td>${record.faculty_name}</td>
                        <td>${createStatusBadge(record.status)}</td>
                        <td>${formatDateTime(record.marked_at)}</td>
                        <td>${createStatusBadge(record.marked_method)}</td>
                    </tr>
                `).join('');
            } else {
                subjectTableBody.innerHTML = '<tr><td colspan="6" class="text-center">No records found</td></tr>';
            }
        }
    } catch (error) {
        console.error('Error loading attendance history:', error);
        showError('Failed to load attendance history');
    }
}

// Store chart instances to allow destruction before recreation
let averageChartInstance = null;
let subjectChartInstances = [];

// Render average subject attendance chart (aggregated across all subjects)
function renderAverageSubjectChart(subjectStats) {
    const container = document.getElementById('subjectAvgStatsText');

    // Destroy existing chart instance if it exists
    if (averageChartInstance) {
        averageChartInstance.destroy();
        averageChartInstance = null;
    }

    if (!subjectStats || subjectStats.length === 0) {
        container.innerHTML = '<p class="text-center">No subject attendance records yet</p>';
        return;
    }

    // Calculate average by summing percentages and dividing by number of subjects
    let totalPercentage = 0;
    let validSubjects = 0;
    const totals = { present: 0, absent: 0, total: 0 };

    subjectStats.forEach(subj => {
        const present = Number(subj.present_count || 0);
        const totalCount = Number(subj.total_count || 0);
        const abs = Number(subj.absent_count || Math.max(totalCount - present, 0));
        
        totals.present += present;
        totals.absent += abs;
        totals.total += totalCount;

        if (totalCount > 0) {
            const subjectPercentage = (present / totalCount) * 100;
            totalPercentage += subjectPercentage;
            validSubjects++;
        }
    });

    if (validSubjects === 0) {
        container.innerHTML = '<p class="text-center">No completed sessions yet</p>';
        return;
    }

    const attendancePercentage = Math.round(totalPercentage / validSubjects);

    container.innerHTML = `
        <div style="font-size: 18px;">
            <p><strong>Total Sessions:</strong> ${totals.total}</p>
            <p><strong>Present:</strong> ${totals.present} lectures</p>
            <p><strong>Absent:</strong> ${totals.absent} lectures</p>
            <p style="font-size: 24px; color: #4CAF50; margin-top: 10px;"><strong>${attendancePercentage}%</strong></p>
        </div>
    `;

    const ctx = document.getElementById('averageSubjectChart');
    if (ctx) {
        averageChartInstance = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Present', 'Absent'],
                datasets: [{
                    data: [attendancePercentage, 100 - attendancePercentage],
                    backgroundColor: ['#4CAF50', '#e0e0e0'],
                    borderColor: ['#45a049', '#bdbdbd'],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            filter: function(legendItem) {
                                return legendItem.text === 'Present';
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed || 0;
                                return label + ': ' + value + '%';
                            }
                        }
                    }
                }
            }
        });
    }
}

// Render subject-wise attendance charts
function renderSubjectCharts(subjectStats) {
    const container = document.getElementById('subjectChartsContainer');
    
    // Destroy all existing subject chart instances
    subjectChartInstances.forEach(chart => {
        if (chart) {
            chart.destroy();
        }
    });
    subjectChartInstances = [];
    
    if (!subjectStats || subjectStats.length === 0) {
        container.innerHTML = '<p class="text-center">No subject attendance records yet</p>';
        return;
    }
    
    let html = '';
    subjectStats.forEach((subject, index) => {
        const attendancePercentage = Math.round((subject.present_count / subject.total_count) * 100);
        const canvasId = `subjectChart_${index}`;
        
        html += `
            <div style="border: 1px solid #ddd; border-radius: 8px; padding: 15px; text-align: center;">
                <h4>${subject.subject_code} - ${subject.subject_name}</h4>
                <canvas id="${canvasId}" style="max-height: 150px;"></canvas>
                <div style="margin-top: 15px; font-size: 16px;">
                    <p><strong>${attendancePercentage}%</strong></p>
                    <p style="font-size: 13px; color: #666;">
                        ${subject.present_count} Present / ${subject.absent_count} Absent (Total: ${subject.total_count})
                    </p>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
    
    // Render each chart
    subjectStats.forEach((subject, index) => {
        const canvasId = `subjectChart_${index}`;
        const ctx = document.getElementById(canvasId);
        
        if (ctx) {
            const chartInstance = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Present', 'Absent'],
                    datasets: [{
                        data: [subject.present_count, subject.absent_count],
                        backgroundColor: ['#4CAF50', '#e0e0e0'],
                        borderColor: ['#45a049', '#bdbdbd'],
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                font: { size: 12 },
                                filter: function(legendItem, chartData) {
                                    // Only show "Present" in legend
                                    return legendItem.text === 'Present';
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed || 0;
                                    return label + ': ' + value + ' lectures';
                                }
                            }
                        }
                    }
                }
            });
            subjectChartInstances.push(chartInstance);
        }
    });
}

// Render lecture-wise attendance summary using dropdowns
function renderLectureWiseAttendance(lecturesBySubject) {
    const container = document.getElementById('lectureWiseContainer');
    
    if (!lecturesBySubject || lecturesBySubject.length === 0) {
        container.innerHTML = '<p class="text-center">No lecture records available yet</p>';
        return;
    }
    
    let html = '';
    lecturesBySubject.forEach(subject => {
        const totalLectures = subject.lectures ? subject.lectures.length : 0;
        const attendedLectures = subject.lectures
            ? subject.lectures.filter(lec => lec.status === 'present').length
            : 0;
        
        html += `
            <details style="margin-bottom: 20px; border: 1px solid #ddd; border-radius: 8px; padding: 12px;">
                <summary style="cursor: pointer; font-weight: 600; display: flex; justify-content: space-between; align-items: center;">
                    <span>${subject.subject_code} - ${subject.subject_name}</span>
                    <span style="color: #4CAF50;">${attendedLectures} / ${totalLectures} lectures attended</span>
                </summary>
                <div style="margin-top: 15px;">
                    <table>
                        <thead>
                            <tr>
                                <th>Lecture #</th>
                                <th>Date & Time</th>
                                <th>Faculty</th>
                                <th>Status</th>
                                <th>Method</th>
                                <th>Marked At</th>
                            </tr>
                        </thead>
                        <tbody>
        `;
        
        if (subject.lectures && subject.lectures.length > 0) {
            subject.lectures.forEach((lecture, index) => {
                const lectureNum = subject.lectures.length - index;
                html += `
                    <tr>
                        <td><strong>#${lectureNum}</strong></td>
                        <td>${formatDateTime(lecture.lecture_date)}</td>
                        <td>${lecture.faculty_name}</td>
                        <td>${createStatusBadge(lecture.status)}</td>
                        <td>${lecture.marked_method ? createStatusBadge(lecture.marked_method) : '-'}</td>
                        <td>${lecture.marked_at ? formatDateTime(lecture.marked_at) : '-'}</td>
                    </tr>
                `;
            });
        } else {
            html += '<tr><td colspan="6" class="text-center">No lectures conducted yet</td></tr>';
        }
        
        html += `
                        </tbody>
                    </table>
                </div>
            </details>
        `;
    });
    
    container.innerHTML = html;
}

// Load timetable
async function loadTimetable() {
    try {
        console.log('Loading timetable...');
        const response = await fetch('../api/get_timetable.php');
        console.log('Timetable API response status:', response.status);
        
        const rawText = await response.text();
        console.log('Raw timetable response:', rawText);
        
        let data;
        try {
            data = JSON.parse(rawText);
        } catch (err) {
            console.error('Failed to parse timetable JSON:', rawText);
            document.getElementById('timetableContainer').innerHTML = '<p class="error-message">Failed to load timetable (invalid response)</p>';
            return;
        }
        
        if (!response.ok || !data.success) {
            console.error('Timetable API error:', data);
            document.getElementById('timetableContainer').innerHTML = '<p class="error-message">' + (data.message || 'Failed to load timetable') + '</p>';
            return;
        }
        
        if (data.success) {
            const container = document.getElementById('timetableContainer');
            
            if (data.timetable.length > 0) {
                // Get today's and tomorrow's dates
                const now = new Date();
                const yyyy = now.getFullYear();
                const mm = String(now.getMonth() + 1).padStart(2, '0');
                const dd = String(now.getDate()).padStart(2, '0');
                const todayStr = `${yyyy}-${mm}-${dd}`;
                
                const tomorrow = new Date(now);
                tomorrow.setDate(tomorrow.getDate() + 1);
                const tomorrowStr = `${tomorrow.getFullYear()}-${String(tomorrow.getMonth() + 1).padStart(2, '0')}-${String(tomorrow.getDate()).padStart(2, '0')}`;
                
                // Filter lectures for today and tomorrow
                const slotsToday = data.timetable.filter(slot => slot.lecture_date === todayStr);
                const slotsTomorrow = data.timetable.filter(slot => slot.lecture_date === tomorrowStr);
                const otherLectures = data.timetable.filter(slot => 
                    slot.lecture_date !== todayStr && slot.lecture_date !== tomorrowStr
                );

                let html = '';

                // Helper to render a section
                const renderSection = (slots, dateStr, heading) => {
                    if (!dateStr) return '';
                    const dateObj = new Date(dateStr + 'T00:00:00');
                    const dayName = dateObj.toLocaleDateString('en-US', { weekday: 'long' });
                    let sectionHtml = `<h3>${heading} — ${dateStr} (${dayName})</h3>`;
                    if (slots.length === 0) {
                        sectionHtml += '<p class="text-center">No lectures scheduled</p>';
                        return sectionHtml;
                    }
                    sectionHtml += '<table><thead><tr><th>Time</th><th>Subject</th><th>Room</th><th>Faculty</th><th>Status</th></tr></thead><tbody>';
                    slots.forEach(slot => {
                        const status = slot.attendance_status || 'scheduled';
                        const statusBadge = createStatusBadge(status);
                        sectionHtml += `
                            <tr>
                                <td>${slot.start_time.substring(0, 5)} - ${slot.end_time.substring(0, 5)}</td>
                                <td>${slot.subject_code} - ${slot.subject_name}</td>
                                <td>${slot.room_number || '-'}</td>
                                <td>${slot.faculty_name || '-'}</td>
                                <td>${statusBadge}</td>
                            </tr>
                        `;
                    });
                    sectionHtml += '</tbody></table>';
                    return sectionHtml;
                };

                // Render today's lectures first
                html += renderSection(slotsToday, todayStr, 'Today');
                
                // Then render tomorrow's lectures
                html += renderSection(slotsTomorrow, tomorrowStr, 'Tomorrow');

                if (html === '') {
                    html = '<p class="text-center">No lectures scheduled for today or tomorrow</p>';
                }

                container.innerHTML = html;
                console.log('Timetable rendered successfully');
                
                // Setup date picker for searching other lectures
                setupLectureSearch(data.timetable);
            } else {
                container.innerHTML = '<p class="text-center">No timetable available</p>';
                console.log('No timetable data available');
                setupLectureSearch([]);
            }
        }
    } catch (error) {
        console.error('Error loading timetable:', error);
        document.getElementById('timetableContainer').innerHTML = '<p class="error-message">Failed to load timetable: ' + error.message + '</p>';
    }
}

// Setup lecture search with date picker
function setupLectureSearch(allLectures) {
    const datePicker = document.getElementById('pastDatePicker');
    if (!datePicker) return;

    // Allow selecting any date
    datePicker.min = '2000-01-01';
    datePicker.max = '2100-12-31';

    // Add change listener
    datePicker.onchange = function() {
        if (this.value) {
            displayLecturesForDate(allLectures, this.value);
        } else {
            document.getElementById('pastLecturesContainer').innerHTML = '<p class="text-center">Select a date to view lectures</p>';
        }
    };
}

// Display lectures for selected date
function displayLecturesForDate(allLectures, selectedDate) {
    const lecturesOnDate = allLectures.filter(slot => slot.lecture_date === selectedDate);
    const container = document.getElementById('pastLecturesContainer');
    
    if (lecturesOnDate.length === 0) {
        container.innerHTML = '<p class="text-center">No lectures scheduled for this date</p>';
        return;
    }
    
    const dateObj = new Date(selectedDate + 'T00:00:00');
    const dayName = dateObj.toLocaleDateString('en-US', { weekday: 'long' });
    
    let html = `<h4>${selectedDate} - ${dayName}</h4>`;
    html += '<table><thead><tr><th>Time</th><th>Subject</th><th>Room</th><th>Faculty</th><th>Status</th></tr></thead><tbody>';
    
    lecturesOnDate.forEach(slot => {
        const status = slot.attendance_status || 'scheduled';
        const statusBadge = createStatusBadge(status);
        html += `
            <tr>
                <td>${slot.start_time.substring(0, 5)} - ${slot.end_time.substring(0, 5)}</td>
                <td>${slot.subject_code} - ${slot.subject_name}</td>
                <td>${slot.room_number || '-'}</td>
                <td>${slot.faculty_name || '-'}</td>
                <td>${statusBadge}</td>
            </tr>
        `;
    });
    html += '</tbody></table>';
    
    container.innerHTML = html;
}

// Helper function to create status badge
function createStatusBadge(status) {
    const statusColors = {
        'present': 'success',
        'absent': 'danger',
        'scheduled': 'info',
        'completed': 'secondary'
    };
    const color = statusColors[status] || 'info';
    return `<span class="badge badge-${color}">${status.charAt(0).toUpperCase() + status.slice(1)}</span>`;
}


