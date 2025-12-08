let schedules = [];
let leaveRequests = [];
let shiftTemplates = [];
let currentScheduleId = null;
let scheduleCalendarDate = new Date();
let scheduleDetailsModal = null;

function toggleStaffMenu() {
    const toggle = document.getElementById('staffMenuToggle');
    const submenu = document.getElementById('staffSubmenu');
    
    if (!toggle || !submenu) return;
    
    // If in compact mode (icon-only rail), first expand the sidebar like YouTube does
    if (document.body.classList.contains('sidebar-compact')) {
        document.body.classList.remove('sidebar-compact');
        try { localStorage.setItem('sidebarCompact', 'false'); } catch (e) {}
    }
    
    const isExpanded = toggle.classList.contains('expanded');
    
    if (isExpanded) {
        toggle.classList.remove('expanded');
        submenu.classList.remove('expanded');
        // Force hide
        submenu.style.display = 'none';
        submenu.style.visibility = 'hidden';
    } else {
        toggle.classList.add('expanded');
        submenu.classList.add('expanded');
        // Force show with all necessary properties
        submenu.style.display = 'flex';
        submenu.style.visibility = 'visible';
        submenu.style.opacity = '1';
        submenu.style.maxHeight = '500px';
        submenu.style.flexDirection = 'column';
        submenu.style.gap = '0.2rem';
        submenu.style.paddingLeft = '0.25rem';
        submenu.style.marginTop = '0.35rem';
        submenu.style.overflow = 'visible';
    }
    
    // Force a reflow to ensure CSS updates
    submenu.offsetHeight;
}

async function loadSchedules() {
    try {
        const params = new URLSearchParams();
        
        const dateFrom = document.getElementById('scheduleDateFrom')?.value;
        const dateTo = document.getElementById('scheduleDateTo')?.value;
        const staffId = document.getElementById('scheduleStaffFilter')?.value;
        
        if (dateFrom) params.append('date_from', dateFrom);
        if (dateTo) params.append('date_to', dateTo);
        if (staffId) params.append('user_id', staffId);
        
        const response = await fetch(`${API_BASE}schedules.php?${params.toString()}`);
        
        if (!response.ok) {
            let errorMsg = 'Failed to load schedules';
            try {
                const errorData = await response.json();
                errorMsg = errorData.error || errorMsg;
            } catch (e) {
                errorMsg = `Failed to load schedules (HTTP ${response.status})`;
            }
            showAlert(errorMsg, 'error');
            schedules = [];
            displaySchedules(schedules);
            return;
        }
        
        schedules = await response.json();
        if (!Array.isArray(schedules)) {
            console.error('Invalid response format:', schedules);
            schedules = [];
        }
        displaySchedules(schedules);
    } catch (error) {
        console.error('Error loading schedules:', error);
        showAlert('Failed to load schedules', 'error');
        schedules = [];
        displaySchedules(schedules);
    }
}

function displaySchedules(data) {
    const tbody = document.querySelector('#schedulesTable tbody');
    tbody.innerHTML = '';
    
    if (!Array.isArray(data) || data.length === 0) {
        tbody.innerHTML = '<tr><td colspan="9" style="text-align: center;">No schedules found</td></tr>';
        return;
    }
    
    data.forEach(schedule => {
        const tr = document.createElement('tr');
        const appointmentCount = schedule.appointment_count || 0;
        const patientNames = schedule.patient_names ? schedule.patient_names.split(',') : [];
        const patientList = patientNames.length > 0 
            ? patientNames.slice(0, 3).join(', ') + (patientNames.length > 3 ? '...' : '')
            : 'No appointments';
        
        tr.innerHTML = `
            <td>${schedule.user_name || 'N/A'}</td>
            <td><span class="badge badge-${getRoleBadge(schedule.role)}">${schedule.role || 'N/A'}</span></td>
            <td>${formatDate(schedule.schedule_date)}</td>
            <td>
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    ${schedule.color ? `<span style="width: 12px; height: 12px; border-radius: 50%; background: ${schedule.color};"></span>` : ''}
                    ${schedule.shift_name || 'Custom'}
                </div>
            </td>
            <td>${formatTime(schedule.start_time)} - ${formatTime(schedule.end_time)}</td>
            <td>
                <span class="badge badge-${appointmentCount > 0 ? 'success' : 'secondary'}" title="${patientList}">
                    ${appointmentCount} ${appointmentCount === 1 ? 'appointment' : 'appointments'}
                </span>
            </td>
            <td style="font-size: 0.85rem; color: var(--text-muted); max-width: 200px; overflow: hidden; text-overflow: ellipsis;" title="${patientList}">
                ${patientList}
            </td>
            <td><span class="badge badge-${getScheduleStatusBadge(schedule.status)}">${schedule.status}</span></td>
            <td>
                <button onclick="viewScheduleDetails(${schedule.id})" class="btn btn-sm btn-info" title="View Details">View</button>
                <button onclick="editSchedule(${schedule.id})" class="btn btn-sm btn-primary">Edit</button>
                <button onclick="deleteSchedule(${schedule.id})" class="btn btn-sm btn-danger">Cancel</button>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

async function showScheduleModal(id = null) {
    currentScheduleId = id;
    const modal = document.getElementById('scheduleModal');
    const form = document.getElementById('scheduleForm');
    const title = document.getElementById('scheduleModalTitle');

    form.reset();
    modal.classList.remove('hidden');
    setTimeout(() => modal.classList.add('show'), 10);

    if (!id) {
        document.getElementById('scheduleDate').valueAsDate = new Date();
    }

    try { await loadStaffForSchedule(); } catch (e) { console.warn('loadStaffForSchedule failed', e); }
    try { await loadShiftTemplates(); } catch (e) { console.warn('loadShiftTemplates failed', e); }

    if (id) {
        title.textContent = 'Edit Schedule';
        const schedule = schedules.find(s => s.id === parseInt(id));
        if (schedule) {
            document.getElementById('scheduleUserId').value = schedule.user_id;
            document.getElementById('scheduleTemplate').value = schedule.shift_template_id || '';
            document.getElementById('scheduleDate').value = schedule.schedule_date;
            document.getElementById('scheduleStartTime').value = schedule.start_time;
            document.getElementById('scheduleEndTime').value = schedule.end_time;
            document.getElementById('scheduleStatus').value = schedule.status;
            document.getElementById('scheduleNotes').value = schedule.notes || '';
        } else {
            // If schedule not found in local array, fetch it from API
            try {
                const response = await fetch(`${API_BASE}schedules.php?action=list&id=${id}`);
                if (response.ok) {
                    const scheduleData = await response.json();
                    if (scheduleData && scheduleData.id) {
                        document.getElementById('scheduleUserId').value = scheduleData.user_id;
                        document.getElementById('scheduleTemplate').value = scheduleData.shift_template_id || '';
                        document.getElementById('scheduleDate').value = scheduleData.schedule_date;
                        document.getElementById('scheduleStartTime').value = scheduleData.start_time;
                        document.getElementById('scheduleEndTime').value = scheduleData.end_time;
                        document.getElementById('scheduleStatus').value = scheduleData.status;
                        document.getElementById('scheduleNotes').value = scheduleData.notes || '';
                    }
                }
            } catch (e) {
                console.error('Error fetching schedule:', e);
                showAlert('Failed to load schedule details', 'error');
            }
        }
    } else {
        title.textContent = 'Add Schedule';
    }
}


async function loadStaffForSchedule() {
    try {
        const response = await fetch(`${API_BASE}users.php`);
        const users = await response.json();
        
        const scheduleSelect = document.getElementById('scheduleUserId');
        const leaveSelect = document.getElementById('leaveUserId');
        const staffFilter = document.getElementById('scheduleStaffFilter');
        
        [scheduleSelect, leaveSelect].forEach(select => {
            if (select) {
                select.innerHTML = '<option value="">Select Staff</option>';
                users.forEach(user => {
                    select.innerHTML += `<option value="${user.id}">${user.name} (${user.role})</option>`;
                });
            }
        });
        
        if (staffFilter) {
            staffFilter.innerHTML = '<option value="">All Staff</option>';
            users.forEach(user => {
                staffFilter.innerHTML += `<option value="${user.id}">${user.name}</option>`;
            });
        }
    } catch (error) {
        console.error('Error loading staff:', error);
    }
}

async function loadShiftTemplates() {
    try {
        const response = await fetch(`${API_BASE}schedules.php?action=templates`);
        shiftTemplates = await response.json();
        console.log('Shift templates loaded:', shiftTemplates);
        
        const select = document.getElementById('scheduleTemplate');
        select.innerHTML = '<option value="">Custom Shift</option>';
        
        if (Array.isArray(shiftTemplates)) {
            shiftTemplates.forEach(template => {
                select.innerHTML += `<option value="${template.id}">${template.name} (${formatTime(template.start_time)} - ${formatTime(template.end_time)})</option>`;
            });
        } else {
            console.error('Shift templates is not an array:', shiftTemplates);
        }
    } catch (error) {
        console.error('Error loading shift templates:', error);
    }
}

function loadShiftTimes() {
    const templateId = document.getElementById('scheduleTemplate').value;
    if (!templateId) return;
    
    const template = shiftTemplates.find(t => t.id == templateId);
    if (template) {
        document.getElementById('scheduleStartTime').value = template.start_time;
        document.getElementById('scheduleEndTime').value = template.end_time;
    }
}

async function saveSchedule(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());

    if (data.start_time && data.start_time.length === 5) data.start_time = data.start_time + ':00';
    if (data.end_time && data.end_time.length === 5) data.end_time = data.end_time + ':00';
    if (data.shift_template_id === '') data.shift_template_id = null;
    if (data.notes === '') data.notes = null;

    if (!data.user_id || !data.schedule_date || !data.start_time || !data.end_time) {
        showAlert('Please fill all required fields', 'error');
        return;
    }
    
    try {
        const url = currentScheduleId 
            ? `${API_BASE}schedules.php?id=${currentScheduleId}`
            : `${API_BASE}schedules.php`;
        
        const method = currentScheduleId ? 'PUT' : 'POST';
        
        const response = await fetch(url, {
            method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        
        let result;
        try { result = await response.json(); } catch (_) { result = {}; }
        
        if (response.ok) {
            const title = currentScheduleId ? 'Schedule Updated' : 'Schedule Created';
            const message = result.message || (currentScheduleId 
                ? 'Shift details have been updated'
                : 'New shift added to the roster');
            showToast(title, message, 'success');
            hideScheduleModal('scheduleModal');
            loadSchedules();
        } else {
            showAlert(result.error || `Failed to save schedule (HTTP ${response.status})`, 'error');
            console.error('Save schedule failed', response.status, result);
        }
    } catch (error) {
        console.error('Error saving schedule:', error);
        showAlert('Failed to save schedule', 'error');
    }
}

function editSchedule(id) {
    showScheduleModal(id);
}

async function viewScheduleDetails(id) {
    try {
        const response = await fetch(`${API_BASE}schedules.php?action=list&id=${id}`);
        if (!response.ok) {
            showAlert('Failed to load schedule details', 'error');
            return;
        }
        
        const schedule = await response.json();
        
        // Create a modal or display details
        let detailsHtml = `
            <div style="padding: 1.5rem;">
                <h3 style="margin-bottom: 1rem;">Schedule Details</h3>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                    <div>
                        <strong>Staff Member:</strong> ${schedule.user_name || 'N/A'}<br>
                        <strong>Role:</strong> ${schedule.role || 'N/A'}<br>
                        <strong>Email:</strong> ${schedule.user_email || 'N/A'}<br>
                        <strong>Phone:</strong> ${schedule.user_phone || 'N/A'}
                    </div>
                    <div>
                        <strong>Date:</strong> ${formatDate(schedule.schedule_date)}<br>
                        <strong>Time:</strong> ${formatTime(schedule.start_time)} - ${formatTime(schedule.end_time)}<br>
                        <strong>Shift:</strong> ${schedule.shift_name || 'Custom'}<br>
                        <strong>Status:</strong> <span class="badge badge-${getScheduleStatusBadge(schedule.status)}">${schedule.status}</span>
                    </div>
                </div>
        `;
        
        if (schedule.appointments && schedule.appointments.length > 0) {
            detailsHtml += `
                <h4 style="margin-top: 1.5rem; margin-bottom: 0.5rem;">Appointments (${schedule.appointments.length})</h4>
                <table style="width: 100%; border-collapse: collapse; margin-bottom: 1.5rem;">
                    <thead>
                        <tr style="background: #f5f5f5;">
                            <th style="padding: 0.5rem; text-align: left; border: 1px solid #ddd;">Patient</th>
                            <th style="padding: 0.5rem; text-align: left; border: 1px solid #ddd;">Doctor</th>
                            <th style="padding: 0.5rem; text-align: left; border: 1px solid #ddd;">Time</th>
                            <th style="padding: 0.5rem; text-align: left; border: 1px solid #ddd;">Room</th>
                            <th style="padding: 0.5rem; text-align: left; border: 1px solid #ddd;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            
            schedule.appointments.forEach(apt => {
                const aptDate = new Date(apt.start_time);
                detailsHtml += `
                    <tr>
                        <td style="padding: 0.5rem; border: 1px solid #ddd;">
                            ${apt.patient_first_name || ''} ${apt.patient_last_name || ''}
                            ${apt.patient_phone ? `<br><small>${apt.patient_phone}</small>` : ''}
                        </td>
                        <td style="padding: 0.5rem; border: 1px solid #ddd;">
                            ${apt.doctor_first_name || ''} ${apt.doctor_last_name || ''}
                            ${apt.doctor_specialty ? `<br><small>${apt.doctor_specialty}</small>` : ''}
                        </td>
                        <td style="padding: 0.5rem; border: 1px solid #ddd;">
                            ${formatTime(apt.start_time)} - ${formatTime(apt.end_time)}
                        </td>
                        <td style="padding: 0.5rem; border: 1px solid #ddd;">
                            ${apt.room_number || apt.room_name || 'N/A'}
                        </td>
                        <td style="padding: 0.5rem; border: 1px solid #ddd;">
                            <span class="badge badge-${getScheduleStatusBadge(apt.status)}">${apt.status}</span>
                        </td>
                    </tr>
                `;
            });
            
            detailsHtml += `</tbody></table>`;
        } else {
            detailsHtml += `<p style="color: var(--text-muted); margin-bottom: 1.5rem;">No appointments scheduled for this shift.</p>`;
        }
        
        if (schedule.billing && schedule.billing.length > 0) {
            const totalBilling = schedule.billing.reduce((sum, b) => sum + parseFloat(b.amount || 0), 0);
            detailsHtml += `
                <h4 style="margin-top: 1.5rem; margin-bottom: 0.5rem;">Billing (${schedule.billing.length} records)</h4>
                <table style="width: 100%; border-collapse: collapse; margin-bottom: 1rem;">
                    <thead>
                        <tr style="background: #f5f5f5;">
                            <th style="padding: 0.5rem; text-align: left; border: 1px solid #ddd;">Patient</th>
                            <th style="padding: 0.5rem; text-align: left; border: 1px solid #ddd;">Amount</th>
                            <th style="padding: 0.5rem; text-align: left; border: 1px solid #ddd;">Status</th>
                            <th style="padding: 0.5rem; text-align: left; border: 1px solid #ddd;">Due Date</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            
            schedule.billing.forEach(bill => {
                detailsHtml += `
                    <tr>
                        <td style="padding: 0.5rem; border: 1px solid #ddd;">
                            ${bill.patient_first_name || ''} ${bill.patient_last_name || ''}
                        </td>
                        <td style="padding: 0.5rem; border: 1px solid #ddd;">
                            $${parseFloat(bill.amount || 0).toFixed(2)}
                        </td>
                        <td style="padding: 0.5rem; border: 1px solid #ddd;">
                            <span class="badge badge-${getScheduleStatusBadge(bill.status)}">${bill.status}</span>
                        </td>
                        <td style="padding: 0.5rem; border: 1px solid #ddd;">
                            ${bill.due_date ? formatDate(bill.due_date) : 'N/A'}
                        </td>
                    </tr>
                `;
            });
            
            detailsHtml += `
                    </tbody>
                </table>
                <div style="text-align: right; font-weight: bold; margin-top: 0.5rem;">
                    Total: $${totalBilling.toFixed(2)}
                </div>
            `;
        }
        
        detailsHtml += `</div>`;
        
        showScheduleDetailsModal(detailsHtml);
        
    } catch (error) {
        console.error('Error loading schedule details:', error);
        showAlert('Failed to load schedule details', 'error');
    }
}

async function deleteSchedule(id) {
    if (!confirm('Are you sure you want to cancel this schedule?')) return;
    
    try {
        const response = await fetch(`${API_BASE}schedules.php?id=${id}`, {
            method: 'DELETE'
        });
        
        const result = await response.json();
        
        if (response.ok) {
            showToast('Schedule Cancelled', 'The shift has been removed from the roster', 'success');
            loadSchedules();
        } else {
            showAlert(result.error || 'Failed to cancel schedule', 'error');
        }
    } catch (error) {
        console.error('Error deleting schedule:', error);
        showAlert('Failed to cancel schedule', 'error');
    }
}

function filterSchedules() {
    loadSchedules();
}

function showScheduleView(view) {
    // Update button states
    document.getElementById('scheduleCalendarBtn').classList.remove('active');
    document.getElementById('scheduleListBtn').classList.remove('active');
    document.getElementById('scheduleLeaveBtn').classList.remove('active');
    
    // Hide all views
    document.getElementById('scheduleCalendarView').style.display = 'none';
    document.getElementById('scheduleListView').style.display = 'none';
    document.getElementById('scheduleLeaveView').style.display = 'none';
    
    // Show selected view
    if (view === 'calendar') {
        document.getElementById('scheduleCalendarBtn').classList.add('active');
        document.getElementById('scheduleCalendarView').style.display = 'block';
        renderScheduleCalendar();
    } else if (view === 'list') {
        document.getElementById('scheduleListBtn').classList.add('active');
        document.getElementById('scheduleListView').style.display = 'block';
        loadSchedules();
    } else if (view === 'leave') {
        document.getElementById('scheduleLeaveBtn').classList.add('active');
        document.getElementById('scheduleLeaveView').style.display = 'block';
        loadLeaveRequests();
    }
}

function renderScheduleCalendar() {
    const monthTitle = document.getElementById('scheduleMonthTitle');
    const calendar = document.getElementById('scheduleCalendar');
    
    const year = scheduleCalendarDate.getFullYear();
    const month = scheduleCalendarDate.getMonth();
    
    monthTitle.textContent = scheduleCalendarDate.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
    
    calendar.innerHTML = '<p style="text-align: center; padding: 2rem;">Calendar view coming soon...</p>';
}

function previousMonth() {
    scheduleCalendarDate.setMonth(scheduleCalendarDate.getMonth() - 1);
    renderScheduleCalendar();
}

function nextMonth() {
    scheduleCalendarDate.setMonth(scheduleCalendarDate.getMonth() + 1);
    renderScheduleCalendar();
}

async function loadLeaveRequests() {
    try {
        const status = document.getElementById('leaveStatusFilter')?.value;
        const params = new URLSearchParams();
        if (status) params.append('status', status);
        
        const response = await fetch(`${API_BASE}schedules.php?action=leave_requests&${params.toString()}`);
        leaveRequests = await response.json();
        displayLeaveRequests(leaveRequests);
    } catch (error) {
        console.error('Error loading leave requests:', error);
        showAlert('Failed to load leave requests', 'error');
    }
}

function displayLeaveRequests(data) {
    const tbody = document.querySelector('#leaveRequestsTable tbody');
    tbody.innerHTML = '';
    
    if (data.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" style="text-align: center;">No leave requests found</td></tr>';
        return;
    }
    
    data.forEach(leave => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${leave.user_name || 'N/A'}</td>
            <td><span class="badge badge-${getRoleBadge(leave.role)}">${leave.role || 'N/A'}</span></td>
            <td>${leave.leave_type}</td>
            <td>${formatDate(leave.start_date)}</td>
            <td>${formatDate(leave.end_date)}</td>
            <td>${leave.reason || 'N/A'}</td>
            <td><span class="badge badge-${getLeaveStatusBadge(leave.status)}">${leave.status}</span></td>
            <td>
                ${leave.status === 'pending' ? `
                    <button onclick="approveLeaveRequest(${leave.id})" class="btn btn-sm btn-success">Approve</button>
                    <button onclick="rejectLeaveRequest(${leave.id})" class="btn btn-sm btn-danger">Reject</button>
                ` : `<span class="text-muted">Processed</span>`}
            </td>
        `;
        tbody.appendChild(tr);
    });
}

function showLeaveRequestModal() {
    const modal = document.getElementById('leaveRequestModal');
    const form = document.getElementById('leaveRequestForm');
    form.reset();
    loadStaffForSchedule();
    modal.classList.remove('hidden');
    setTimeout(() => modal.classList.add('show'), 10);
}

async function submitLeaveRequest(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());
    data.action = 'leave_request';
    
    try {
        const response = await fetch(`${API_BASE}schedules.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (response.ok) {
            showToast('Leave Request Submitted', result.message || 'Your leave request is now pending approval', 'success');
            hideScheduleModal('leaveRequestModal');
            loadLeaveRequests();
        } else {
            showAlert(result.error || 'Failed to submit leave request', 'error');
        }
    } catch (error) {
        console.error('Error submitting leave request:', error);
        showAlert('Failed to submit leave request', 'error');
    }
}

async function approveLeaveRequest(id) {
    await updateLeaveStatus(id, 'approved');
}

async function rejectLeaveRequest(id) {
    const reason = prompt('Rejection reason (optional):');
    await updateLeaveStatus(id, 'rejected', reason);
}

async function updateLeaveStatus(id, status, rejectionReason = null) {
    try {
        const response = await fetch(`${API_BASE}schedules.php?id=${id}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'approve_leave',
                status,
                rejection_reason: rejectionReason
            })
        });
        
        const result = await response.json();
        
        if (response.ok) {
            const statusLabel = (result.message || data.status || 'updated').toString();
            showToast('Leave Request Updated', statusLabel, 'success');
            loadLeaveRequests();
        } else {
            showAlert(result.error || 'Failed to update leave request', 'error');
        }
    } catch (error) {
        console.error('Error updating leave request:', error);
        showAlert('Failed to update leave request', 'error');
    }
}

function filterLeaveRequests() {
    loadLeaveRequests();
}

function getScheduleStatusBadge(status) {
    const badges = {
        'scheduled': 'primary',
        'completed': 'success',
        'cancelled': 'secondary'
    };
    return badges[status] || 'secondary';
}

function getLeaveStatusBadge(status) {
    const badges = {
        'pending': 'warning',
        'approved': 'success',
        'rejected': 'danger'
    };
    return badges[status] || 'secondary';
}

function getRoleBadge(role) {
    const badges = {
        'admin': 'danger',
        'doctor': 'success',
        'nurse': 'info',
        'receptionist': 'primary'
    };
    return badges[role] || 'secondary';
}

function formatTime(time) {
    if (!time) return 'N/A';
    const [h, m] = time.split(':');
    const hour = parseInt(h);
    const ampm = hour >= 12 ? 'PM' : 'AM';
    const hour12 = hour % 12 || 12;
    return `${hour12}:${m} ${ampm}`;
}

function formatDate(date) {
    if (!date) return 'N/A';
    return new Date(date).toLocaleDateString('en-US', { 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric' 
    });
}

// Helper function to hide a modal
function hideScheduleModal(modalId) {
    const modalElement = document.getElementById(modalId);
    if (modalElement) {
        modalElement.classList.remove('show');
        setTimeout(() => modalElement.classList.add('hidden'), 300);
    }
}

function showScheduleDetailsModal(contentHtml) {
    if (!scheduleDetailsModal) {
        scheduleDetailsModal = document.createElement('div');
        scheduleDetailsModal.id = 'scheduleDetailsModal';
        scheduleDetailsModal.className = 'modal hidden';
        scheduleDetailsModal.innerHTML = `
            <div class="modal-content" style="max-width: 900px; max-height: 90vh; overflow-y: auto;">
                <div class="modal-header">
                    <h2>Schedule Details</h2>
                    <button class="close-btn" onclick="hideModal()">&times;</button>
                </div>
                <div class="modal-body" id="scheduleDetailsBody"></div>
                <div class="modal-footer" style="padding: 1rem; border-top: 1px solid #ddd; text-align: right;">
                    <button class="btn btn-secondary" onclick="hideModal()">Close</button>
                </div>
            </div>`;
        document.body.appendChild(scheduleDetailsModal);
    }

    const body = scheduleDetailsModal.querySelector('#scheduleDetailsBody');
    if (body) {
        body.innerHTML = contentHtml;
    }

    scheduleDetailsModal.classList.remove('hidden');
    setTimeout(() => scheduleDetailsModal.classList.add('show'), 10);
}
